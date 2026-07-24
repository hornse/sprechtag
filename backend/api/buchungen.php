<?php
// ============================================================
// buchungen.php – Raster, Buchungen, Einladungen
// Wird von api/index.php eingebunden ($cfg, $seg, $methode, $body).
//
// DATENSCHUTZ-REGELN (durchgängig geprüft):
//  * Eltern sehen ausschließlich eigene Buchungen.
//  * Eltern dürfen nur für Kinder buchen, die in ihrer Session
//    stehen (auth_kind_erlaubt) – nie für fremde Kinder.
//  * Lehrkräfte sehen nur Buchungen bei sich selbst.
//  * Es werden keine Namen von Eltern/Kindern gespeichert.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/dienstkonto.php';

/** Lädt einen Sprechtag oder bricht ab. */
function bu_sprechtag(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM sprechtage WHERE id = ?');
    $st->execute([$id]);
    $s = $st->fetch();
    if (!$s) json_err('Sprechtag nicht gefunden', 404);
    return $s;
}

/** Anwesenheitsfenster einer Lehrkraft (NULL = ganzer Rahmen). */
function bu_lehrer_fenster(PDO $pdo, int $sprechtagId, int $lehrerId): array
{
    $st = $pdo->prepare('SELECT anwesend_von, anwesend_bis, teilnahme
                         FROM sprechtag_lehrer WHERE sprechtag_id = ? AND lehrer_id = ?');
    $st->execute([$sprechtagId, $lehrerId]);
    $r = $st->fetch();
    return $r ?: ['anwesend_von' => null, 'anwesend_bis' => null, 'teilnahme' => 1];
}

/**
 * Darf für dieses Kind bei dieser Lehrkraft gebucht werden?
 * Erlaubt, wenn die Lehrkraft das Kind unterrichtet (Cache) ODER
 * als Sonderlehrkraft für den Jahrgang freigegeben ist.
 */
function bu_lehrer_erlaubt(PDO $pdo, int $sprechtagId, int $schuelerId,
                           int $lehrerId, string $jahrgang = ''): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM kind_lehrer_cache
                         WHERE sprechtag_id = ? AND schueler_id = ? AND lehrer_id = ?');
    $st->execute([$sprechtagId, $schuelerId, $lehrerId]);
    if ((int)$st->fetchColumn() > 0) return true;

    $st = $pdo->prepare('SELECT jahrgaenge FROM sprechtag_sonderlehrer
                         WHERE sprechtag_id = ? AND lehrer_id = ?');
    $st->execute([$sprechtagId, $lehrerId]);
    foreach ($st->fetchAll() as $zeile) {
        if (slot_sonderlehrer_passt((string)$zeile['jahrgaenge'], $jahrgang)) return true;
    }
    return false;
}

// ============================================================
// GET /api/buchbare-lehrer?sprechtag=ID&kind=ID
// ============================================================
if ($methode === 'GET' && ($seg[0] ?? '') === 'buchbare-lehrer') {
    $u   = auth_require();
    $pdo = db($cfg);
    $sid = (int)($_GET['sprechtag'] ?? 0);
    $kind = (int)($_GET['kind'] ?? 0);

    if (!in_array($u['rolle'], ['eltern', 'schueler', 'admin'], true)) {
        json_err('Diese Ansicht ist für Erziehungsberechtigte bestimmt', 403);
    }
    if ($u['rolle'] !== 'admin' && !auth_kind_erlaubt($u, $kind)) {
        json_err('Für dieses Kind besteht keine Berechtigung', 403);
    }
    bu_sprechtag($pdo, $sid);

    // Cache leer? Dann einmalig mit dem Dienstkonto ermitteln.
    // Das passiert genau einmal je Kind und Sprechtag und dauert
    // ein bis zwei Sekunden; danach kommt alles aus der Datenbank.
    $st = $pdo->prepare('SELECT COUNT(*) FROM kind_lehrer_cache
                         WHERE sprechtag_id = ? AND schueler_id = ?');
    $st->execute([$sid, $kind]);
    $ermittelt = null;
    $fehlendeStammdaten = [];
    if ((int)$st->fetchColumn() === 0) {
        $zugang = dk_lesen($cfg, $pdo);
        if ($zugang !== null) {
            $stS = $pdo->prepare('SELECT datum, referenz_von, referenz_bis,
                                         klausuren_werten
                                  FROM sprechtage WHERE id = ?');
            $stS->execute([$sid]);
            $sp = $stS->fetch() ?: [];
            $ref = (!empty($sp['referenz_von']) && !empty($sp['referenz_bis']))
                ? ['von' => $sp['referenz_von'], 'bis' => $sp['referenz_bis']]
                : wu_referenzzeitraum((string)($sp['datum'] ?? date('Y-m-d')));

            $wcfg = $cfg['webuntis'];
            $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);
            try {
                $wu->authenticate($zugang['benutzer'], $zugang['passwort']);
                $rest = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
                $rest->mitSessionCookie((string)$wu->sessionCookie());
                $rest->setzeTimeout(20);
                if ($rest->tokenHolen()) {
                    $rest->tenantErmitteln();
                    $e = wu_kind_lehrer_ermitteln($cfg, $pdo, $rest,
                        $sid, $kind, (string)$ref['von'], (string)$ref['bis'],
                        (int)($sp['klausuren_werten'] ?? 1) === 1);
                    $ermittelt = $e['anzahl'];
                    $fehlendeStammdaten = $e['uebersprungen'];
                }
            } catch (Throwable $e) {
                // Ermittlung darf die Ansicht nicht scheitern lassen
                error_log('sprechtag: Auto-Ermittlung fehlgeschlagen: ' . $e->getMessage());
            } finally {
                $wu->logout();
            }
        }
    }

    // Unterrichtende Lehrkräfte (nach Stundenzahl sortiert = Hauptfächer zuerst)
    $st = $pdo->prepare(
        'SELECT l.id AS lehrer_id, l.kuerzel, l.name, c.faecher, c.stunden,
                c.klausuren,
                sl.anwesend_von, sl.anwesend_bis, r.kuerzel AS raum_kuerzel,
                NULL AS rolle
         FROM kind_lehrer_cache c
         JOIN lehrer l ON l.id = c.lehrer_id
         LEFT JOIN sprechtag_lehrer sl
                ON sl.sprechtag_id = c.sprechtag_id AND sl.lehrer_id = l.id
         LEFT JOIN raeume r ON r.id = sl.raum_id
         WHERE c.sprechtag_id = ? AND c.schueler_id = ?
           AND (sl.teilnahme IS NULL OR sl.teilnahme = 1)
         ORDER BY c.stunden DESC, l.kuerzel');
    $st->execute([$sid, $kind]);
    $lehrer = $st->fetchAll();

    // Sonderlehrkräfte (Jahrgangsfilter greift erst, wenn der Jahrgang bekannt ist)
    $jahrgang = trim((string)($_GET['jahrgang'] ?? ''));
    $st = $pdo->prepare(
        'SELECT l.id AS lehrer_id, l.kuerzel, l.name, "" AS faecher, 0 AS stunden,
                0 AS klausuren,
                sl2.anwesend_von, sl2.anwesend_bis, r.kuerzel AS raum_kuerzel,
                sr.bezeichnung AS rolle, s.jahrgaenge
         FROM sprechtag_sonderlehrer s
         JOIN lehrer l ON l.id = s.lehrer_id
         JOIN sonderrollen sr ON sr.id = s.rolle_id
         LEFT JOIN sprechtag_lehrer sl2
                ON sl2.sprechtag_id = s.sprechtag_id AND sl2.lehrer_id = l.id
         LEFT JOIN raeume r ON r.id = sl2.raum_id
         WHERE s.sprechtag_id = ? AND (sl2.teilnahme IS NULL OR sl2.teilnahme = 1)
         ORDER BY sr.reihenfolge, l.kuerzel');
    $st->execute([$sid]);
    $sonder = [];
    $bekannt = array_column($lehrer, 'lehrer_id');
    foreach ($st->fetchAll() as $z) {
        if (!slot_sonderlehrer_passt((string)$z['jahrgaenge'], $jahrgang)) continue;
        if (in_array($z['lehrer_id'], $bekannt)) continue;   // schon als Fachlehrkraft
        unset($z['jahrgaenge']);
        $sonder[] = $z;
    }

    json_ok(['unterrichtend' => $lehrer, 'sonderlehrer' => $sonder,
             'automatisch_ermittelt' => $ermittelt,
             'ohne_stammsatz' => $fehlendeStammdaten]);
}

// ============================================================
// POST /api/lehrer-ermitteln  {sprechtag_id, kind_id[, benutzername, passwort]}
// Füllt kind_lehrer_cache über den Referenzzeitraum.
//
// Zugangsdaten: Ohne Angabe wird das hinterlegte Dienstkonto genutzt
// (verschlüsselt in `einstellungen`). Nur wenn keines hinterlegt ist,
// müssen Zugangsdaten mitgeschickt werden.
// ============================================================
if ($methode === 'POST' && ($seg[0] ?? '') === 'lehrer-ermitteln') {
    $u   = auth_require();
    $pdo = db($cfg);
    $sid  = (int)($body['sprechtag_id'] ?? 0);
    $kind = (int)($body['kind_id'] ?? 0);

    if ($u['rolle'] !== 'admin' && !auth_kind_erlaubt($u, $kind)) {
        json_err('Für dieses Kind besteht keine Berechtigung', 403);
    }
    $s = bu_sprechtag($pdo, $sid);
    $ref = ($s['referenz_von'] && $s['referenz_bis'])
        ? ['von' => $s['referenz_von'], 'bis' => $s['referenz_bis']]
        : wu_referenzzeitraum((string)$s['datum']);

    // Zugangsdaten: übergeben > Dienstkonto
    $zugang = null;
    if (($body['benutzername'] ?? '') !== '' && ($body['passwort'] ?? '') !== '') {
        $zugang = ['benutzer' => (string)$body['benutzername'],
                   'passwort' => (string)$body['passwort']];
    } else {
        $zugang = dk_lesen($cfg, $pdo);
    }
    if ($zugang === null) {
        json_err('Kein Dienstkonto hinterlegt. Die Administration kann es '
            . 'unter „Administration → Dienstkonto" eintragen.', 409);
    }

    // Eine frische WebUntis-Session ist nötig (Cookie lebt nicht in der PHP-Session)
    $wcfg = $cfg['webuntis'];
    $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);
    try {
        $wu->authenticate($zugang['benutzer'], $zugang['passwort']);
        $rest = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $rest->mitSessionCookie((string)$wu->sessionCookie());
        $rest->setzeTimeout(20);
        if (!$rest->tokenHolen()) json_err('Kein REST-Zugang (JWT)', 502);
        $rest->tenantErmitteln();
        ignore_user_abort(true);
        set_time_limit(0);
        $e = wu_kind_lehrer_ermitteln($cfg, $pdo, $rest, $sid, $kind,
            (string)$ref['von'], (string)$ref['bis'],
            (int)($s['klausuren_werten'] ?? 1) === 1);
        $anzahl = $e['anzahl'];
        $fehlend = $e['uebersprungen'];
    } catch (RuntimeException $e) {
        json_err('Ermittlung fehlgeschlagen: ' . $e->getMessage(), 502);
    } finally {
        $wu->logout();
    }
    json_ok(['ok' => true, 'lehrkraefte' => $anzahl,
             'ohne_stammsatz' => $fehlend ?? [],
             'zeitraum' => $ref['von'] . ' bis ' . $ref['bis']]);
}

// ============================================================
// GET /api/raster?sprechtag=ID&lehrer=ID
// Zeitraster einer Lehrkraft samt Belegung.
// ============================================================
if ($methode === 'GET' && ($seg[0] ?? '') === 'raster') {
    $u   = auth_require();
    $pdo = db($cfg);
    $sid = (int)($_GET['sprechtag'] ?? 0);
    $lid = (int)($_GET['lehrer'] ?? 0);
    $s   = bu_sprechtag($pdo, $sid);

    $fenster = bu_lehrer_fenster($pdo, $sid, $lid);
    $raster  = slot_raster($s, $fenster['anwesend_von'], $fenster['anwesend_bis']);

    $st = $pdo->prepare(
        'SELECT b.id, b.slot_beginn, b.eltern_user_id, b.schueler_id, b.phase,
                b.gebucht_von,
                TRIM(CONCAT(COALESCE(s.nachname,""),
                     IF(s.vorname IS NULL OR s.vorname = "", "",
                        CONCAT(", ", s.vorname)))) AS kind_name,
                s.klasse
         FROM buchungen b
         LEFT JOIN schueler s ON s.webuntis_id = b.schueler_id
         WHERE b.sprechtag_id = ? AND b.lehrer_id = ?');
    $st->execute([$sid, $lid]);
    $belegt = [];
    foreach ($st->fetchAll() as $b) {
        $belegt[substr((string)$b['slot_beginn'], 0, 5)] = $b;
    }

    // Eltern sehen nur "frei"/"belegt" – nie, WER gebucht hat.
    $istLehrkraft = in_array($u['rolle'], ['lehrkraft', 'admin'], true)
        && ($u['rolle'] === 'admin' || $u['lehrer_id'] === $lid);

    $ausgabe = [];
    foreach ($raster as $z) {
        if ($z['typ'] === 'pause') { $ausgabe[] = $z; continue; }
        $b = $belegt[$z['beginn']] ?? null;
        $eintrag = $z + ['frei' => $b === null];
        if ($b !== null) {
            $eintrag['eigene'] = $u['user_id'] !== null
                && (int)$b['eltern_user_id'] === $u['user_id'];
            $eintrag['phase_gebucht'] = $b['phase'];
            // Nur die Lehrkraft (bzw. Admin) sieht, WER gebucht hat.
            if ($istLehrkraft) {
                $eintrag['buchung_id']  = (int)$b['id'];
                $eintrag['schueler_id'] = (int)$b['schueler_id'];
                $eintrag['kind_name']   = (string)($b['kind_name'] ?? '');
                $eintrag['klasse']      = (string)($b['klasse'] ?? '');
                $eintrag['gebucht_von'] = (string)($b['gebucht_von'] ?? '');
            }
        }
        $ausgabe[] = $eintrag;
    }
    json_ok(['raster' => $ausgabe, 'sprechtag' => [
        'id' => (int)$s['id'], 'phase' => $s['phase'], 'datum' => $s['datum']]]);
}

// ============================================================
// BUCHUNGEN
// ============================================================
if (($seg[0] ?? '') === 'buchungen') {
    $u   = auth_require();
    $pdo = db($cfg);

    // ---- GET: eigene Buchungen bzw. die der Lehrkraft ----
    if ($methode === 'GET' && !isset($seg[1])) {
        $sid = (int)($_GET['sprechtag'] ?? 0);
        if (in_array($u['rolle'], ['lehrkraft', 'admin'], true)
            && ($_GET['sicht'] ?? '') === 'lehrkraft') {
            $lid = $u['rolle'] === 'admin' && isset($_GET['lehrer'])
                ? (int)$_GET['lehrer'] : (int)($u['lehrer_id'] ?? 0);
            $st = $pdo->prepare(
                'SELECT b.id, b.slot_beginn, b.schueler_id, b.phase, b.gebucht_von,
                        b.gebucht_am,
                        TRIM(CONCAT(COALESCE(s.nachname,""),
                             IF(s.vorname IS NULL OR s.vorname = "", "",
                                CONCAT(", ", s.vorname)))) AS kind_name,
                        s.klasse
                 FROM buchungen b
                 LEFT JOIN schueler s ON s.webuntis_id = b.schueler_id
                 WHERE b.sprechtag_id = ? AND b.lehrer_id = ?
                 ORDER BY b.slot_beginn');
            $st->execute([$sid, $lid]);
            json_ok(['buchungen' => $st->fetchAll(), 'sicht' => 'lehrkraft']);
        }

        // Eltern/Schüler: ausschließlich eigene
        if ($u['user_id'] === null) json_ok(['buchungen' => [], 'sicht' => 'eigene']);
        $st = $pdo->prepare(
            'SELECT b.id, b.slot_beginn, b.schueler_id, b.phase, b.lehrer_id,
                    l.kuerzel, l.name, r.kuerzel AS raum_kuerzel
             FROM buchungen b
             JOIN lehrer l ON l.id = b.lehrer_id
             LEFT JOIN sprechtag_lehrer sl
                    ON sl.sprechtag_id = b.sprechtag_id AND sl.lehrer_id = b.lehrer_id
             LEFT JOIN raeume r ON r.id = sl.raum_id
             WHERE b.sprechtag_id = ? AND b.eltern_user_id = ?
             ORDER BY b.slot_beginn');
        $st->execute([$sid, $u['user_id']]);
        json_ok(['buchungen' => $st->fetchAll(), 'sicht' => 'eigene']);
    }

    // ---- POST /api/buchungen/stellvertretend ----
    // Notfallbuchung durch die Lehrkraft: Eltern, die aus welchem Grund
    // auch immer nicht selbst buchen können, bekommen von der Lehrkraft
    // einen Termin bei sich selbst eingetragen. Das Kind wird ausgewählt,
    // das Elternkonto ermittelt das System automatisch (wie bei der
    // Einladung). Der Slot ist danach über den UNIQUE-Key für alle anderen
    // gesperrt. Alle Erziehungsberechtigten werden per Mitteilung über den
    // gebuchten Termin informiert.
    if ($methode === 'POST' && ($seg[1] ?? '') === 'stellvertretend') {
        $u = auth_require_lehrkraft();

        $sid  = (int)($body['sprechtag_id'] ?? 0);
        $kind = (int)($body['schueler_id'] ?? 0);
        $slot = substr(trim((string)($body['slot_beginn'] ?? '')), 0, 5);
        // Admins dürfen für eine beliebige Lehrkraft, Lehrkräfte nur für sich
        $lid = $u['rolle'] === 'admin' && isset($body['lehrer_id'])
            ? (int)$body['lehrer_id'] : (int)($u['lehrer_id'] ?? 0);

        if ($lid <= 0) {
            json_err('Diesem Konto ist keine Lehrkraft zugeordnet. Bitte die '
                . 'Administration bitten, die Stammdaten zu synchronisieren.', 409);
        }
        if ($kind <= 0) json_err('Bitte ein Kind auswählen');
        if (!preg_match('/^\d{2}:\d{2}$/', $slot)) {
            json_err('Bitte einen freien Zeitpunkt auswählen');
        }
        $s = bu_sprechtag($pdo, $sid);

        // Elternkonten ermitteln (gemeinsamer Helfer). Für die Buchung
        // selbst wird EIN Konto benötigt – wir nehmen das erste; alle
        // werden anschließend über den Termin informiert.
        $aufl = mit_eltern_ids_ermitteln($cfg, $pdo, $kind);
        if ($aufl['ids'] === []) {
            json_err('Zu diesem Kind ließ sich kein Elternkonto ermitteln. '
                . 'Ist die Schülerliste gepflegt und ein Dienstkonto '
                . 'hinterlegt? Ersatzweise bleibt die Einladung, mit der die '
                . 'Eltern selbst buchen.', 409);
        }
        $elternIds    = $aufl['ids'];
        $elternUserId = (int)$elternIds[0];
        $kindName     = $aufl['kind_name'];

        // Der Slot muss zum Raster der Lehrkraft gehören und frei sein.
        $fenster = bu_lehrer_fenster($pdo, $sid, $lid);
        if ((int)$fenster['teilnahme'] !== 1) {
            json_err('Diese Lehrkraft nimmt am Sprechtag nicht teil');
        }
        $raster = slot_raster($s, $fenster['anwesend_von'], $fenster['anwesend_bis']);
        $imRaster = false;
        foreach ($raster as $z) {
            if ($z['typ'] === 'slot' && $z['beginn'] === $slot) { $imRaster = true; break; }
        }
        if (!$imRaster) json_err('Dieser Zeitpunkt gehört nicht zum Raster.');

        // Schreiben – der UNIQUE KEY sperrt den Slot für alle anderen.
        try {
            $pdo->prepare('INSERT INTO buchungen
                (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id,
                 phase, gebucht_von)
                VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$sid, $lid, $slot . ':00', $elternUserId, $kind,
                    (string)$s['phase'] === 'phase1' ? 'phase1' : 'phase2',
                    $u['rolle']]);
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                json_err('Dieser Termin ist bereits vergeben.', 409);
            }
            throw $e;
        }
        $neueId = (int)$pdo->lastInsertId();

        // Einladung (falls vorhanden) als erledigt markieren
        $pdo->prepare('UPDATE einladungen SET erledigt = 1
                       WHERE sprechtag_id = ? AND lehrer_id = ? AND schueler_id = ?')
            ->execute([$sid, $lid, $kind]);

        // ---- Alle Erziehungsberechtigten über den Termin informieren ----
        $mitteilung = null;
        try {
            $stL = $pdo->prepare('SELECT l.kuerzel, l.name, r.kuerzel AS raum_kuerzel
                FROM lehrer l
                LEFT JOIN sprechtag_lehrer sl
                       ON sl.sprechtag_id = ? AND sl.lehrer_id = l.id
                LEFT JOIN raeume r ON r.id = sl.raum_id
                WHERE l.id = ?');
            $stL->execute([$sid, $lid]);
            $le = $stL->fetch() ?: [];

            $t = mit_text_bestaetigung((string)$s['name'], (string)$s['datum'], [[
                'slot_beginn' => $slot,
                'name'        => (string)($le['name'] ?: ($le['kuerzel'] ?? '')),
                'raum_kuerzel'=> (string)($le['raum_kuerzel'] ?? ''),
            ]]);
            $zugang = dk_lesen($cfg, $pdo);
            foreach ($elternIds as $eid) {
                $mitteilung = mit_einreihen_und_senden($cfg, $pdo, $sid, (int)$eid,
                    'bestaetigung', $t['betreff'], $t['text'],
                    $zugang['benutzer'] ?? null, $zugang['passwort'] ?? null,
                    $kind);
            }
        } catch (Throwable $e) {
            error_log('sprechtag: Bestätigung (stellvertretend) fehlgeschlagen: '
                . $e->getMessage());
        }

        json_ok(['ok' => true, 'id' => $neueId,
            'kind_name'     => $kindName,
            'eltern_anzahl' => count($elternIds),
            'mitteilung'    => $mitteilung,
            'hinweis' => 'Termin um ' . $slot . ' Uhr für ' . ($kindName ?: 'das Kind')
                . ' eingetragen. ' . count($elternIds)
                . ' Erziehungsberechtigte(r) benachrichtigt.'], 201);
    }

    // ---- POST: buchen ----
    if ($methode === 'POST' && !isset($seg[1])) {
        $sid  = (int)($body['sprechtag_id'] ?? 0);
        $lid  = (int)($body['lehrer_id'] ?? 0);
        $kind = (int)($body['schueler_id'] ?? 0);
        $slot = substr(trim((string)($body['slot_beginn'] ?? '')), 0, 5);
        if (!preg_match('/^\d{2}:\d{2}$/', $slot)) {
            json_err('slot_beginn muss das Format HH:MM haben');
        }
        $s = bu_sprechtag($pdo, $sid);

        // Wer bucht für wen?
        $rolle = $u['rolle'];
        $elternUserId = $u['user_id'];
        if (in_array($rolle, ['lehrkraft', 'admin'], true)) {
            // Stellvertretende Buchung (Phase 1): Eltern-Konto wird angegeben
            $elternUserId = isset($body['eltern_user_id'])
                ? (int)$body['eltern_user_id'] : null;
            if ($elternUserId === null || $elternUserId <= 0) {
                json_err('Für eine stellvertretende Buchung wird die WebUntis-Benutzer-ID '
                    . 'der Erziehungsberechtigten benötigt');
            }
            if ($rolle === 'lehrkraft' && $lid !== (int)($u['lehrer_id'] ?? 0)) {
                json_err('Lehrkräfte können nur Termine bei sich selbst vergeben', 403);
            }
        } else {
            if (!auth_kind_erlaubt($u, $kind)) {
                json_err('Für dieses Kind besteht keine Berechtigung', 403);
            }
            if ($elternUserId === null) json_err('Konto unvollständig – bitte neu anmelden', 401);
        }

        // Regelprüfung
        $fenster = bu_lehrer_fenster($pdo, $sid, $lid);
        if ((int)$fenster['teilnahme'] !== 1) {
            json_err('Diese Lehrkraft nimmt am Sprechtag nicht teil');
        }
        $raster = slot_raster($s, $fenster['anwesend_von'], $fenster['anwesend_bis']);
        $imRaster = false;
        foreach ($raster as $z) {
            if ($z['typ'] === 'slot' && $z['beginn'] === $slot) { $imRaster = true; break; }
        }

        $st = $pdo->prepare('SELECT COUNT(*) FROM buchungen
                             WHERE sprechtag_id = ? AND lehrer_id = ? AND slot_beginn = ?');
        $st->execute([$sid, $lid, $slot . ':00']);
        $frei = (int)$st->fetchColumn() === 0;

        $st = $pdo->prepare('SELECT COUNT(*) FROM buchungen
                             WHERE sprechtag_id = ? AND eltern_user_id = ?');
        $st->execute([$sid, $elternUserId]);
        $anzahl = (int)$st->fetchColumn();

        $st = $pdo->prepare('SELECT COUNT(*) FROM einladungen
                             WHERE sprechtag_id = ? AND lehrer_id = ? AND schueler_id = ?');
        $st->execute([$sid, $lid, $kind]);
        $eingeladen = (int)$st->fetchColumn() > 0;

        $pruefung = slot_buchung_erlaubt([
            'phase'          => (string)$s['phase'],
            'rolle'          => $rolle,
            'eingeladen'     => $eingeladen,
            'darf_lehrkraft' => bu_lehrer_erlaubt($pdo, $sid, $kind, $lid,
                                    (string)($body['jahrgang'] ?? '')),
            'slot_frei'      => $frei,
            'slot_im_raster' => $imRaster,
            'anzahl_termine' => $anzahl,
            'max_termine'    => (int)$s['max_termine_pro_eltern'],
        ]);
        if (!$pruefung['ok']) json_err($pruefung['grund'], 409);

        // Schreiben – der UNIQUE KEY entscheidet bei gleichzeitigen Anfragen
        try {
            $pdo->prepare('INSERT INTO buchungen
                (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id,
                 phase, gebucht_von)
                VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$sid, $lid, $slot . ':00', $elternUserId, $kind,
                    (string)$s['phase'] === 'phase1' ? 'phase1' : 'phase2', $rolle]);
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                json_err('Dieser Termin wurde soeben von jemand anderem gebucht', 409);
            }
            throw $e;
        }
        // ID sofort sichern: nachfolgende Statements überschreiben lastInsertId()
        $neueId = (int)$pdo->lastInsertId();

        // Einladung als erledigt markieren
        if ($eingeladen) {
            $pdo->prepare('UPDATE einladungen SET erledigt = 1
                           WHERE sprechtag_id = ? AND lehrer_id = ? AND schueler_id = ?')
                ->execute([$sid, $lid, $kind]);
        }

        // Bestätigung vormerken (Versand sammelt die Administration).
        // Fehler hier dürfen die Buchung NICHT scheitern lassen.
        try {
            $st = $pdo->prepare(
                'SELECT b.slot_beginn, l.kuerzel, l.name, r.kuerzel AS raum_kuerzel
                 FROM buchungen b
                 JOIN lehrer l ON l.id = b.lehrer_id
                 LEFT JOIN sprechtag_lehrer sl
                        ON sl.sprechtag_id = b.sprechtag_id AND sl.lehrer_id = b.lehrer_id
                 LEFT JOIN raeume r ON r.id = sl.raum_id
                 WHERE b.sprechtag_id = ? AND b.eltern_user_id = ?
                 ORDER BY b.slot_beginn');
            $st->execute([$sid, $elternUserId]);
            $t = mit_text_bestaetigung((string)$s['name'], (string)$s['datum'],
                $st->fetchAll());
            // Ältere offene Bestätigungen ersetzen – es gilt der aktuelle Stand
            $pdo->prepare("DELETE FROM mitteilungen WHERE sprechtag_id = ?
                           AND empfaenger_user_id = ? AND anlass = 'bestaetigung'
                           AND status = 'offen'")->execute([$sid, $elternUserId]);
            mit_einreihen_und_senden($cfg, $pdo, $sid, (int)$elternUserId,
                'bestaetigung', $t['betreff'], $t['text'],
                null, null, $kind);
        } catch (Throwable $e) {
            error_log('sprechtag: Bestaetigung nicht vorgemerkt: ' . $e->getMessage());
        }

        json_ok(['ok' => true, 'id' => $neueId], 201);
    }

    // ---- DELETE: stornieren ----
    if ($methode === 'DELETE' && isset($seg[1]) && ctype_digit($seg[1])) {
        $bid = (int)$seg[1];
        $st = $pdo->prepare('SELECT * FROM buchungen WHERE id = ?');
        $st->execute([$bid]);
        $b = $st->fetch();
        if (!$b) json_err('Buchung nicht gefunden', 404);

        if ($u['rolle'] === 'lehrkraft' && (int)$b['lehrer_id'] !== (int)($u['lehrer_id'] ?? 0)) {
            json_err('Diese Buchung gehört zu einer anderen Lehrkraft', 403);
        }
        $pruefung = slot_storno_erlaubt($b, $u['rolle'], (int)($u['user_id'] ?? 0));
        if (!$pruefung['ok']) json_err($pruefung['grund'], 403);

        $pdo->prepare('DELETE FROM buchungen WHERE id = ?')->execute([$bid]);

        // Sagt die LEHRKRAFT ab, werden die Eltern benachrichtigt.
        // Sagen Eltern selbst ab, ist keine Mitteilung nötig.
        $mitteilung = null;
        if (in_array($u['rolle'], ['lehrkraft', 'admin'], true)) {
            try {
                $stS = $pdo->prepare('SELECT name, datum FROM sprechtage WHERE id = ?');
                $stS->execute([(int)$b['sprechtag_id']]);
                $sp = $stS->fetch() ?: ['name' => 'Elternsprechtag', 'datum' => ''];

                $stL = $pdo->prepare('SELECT kuerzel, name FROM lehrer WHERE id = ?');
                $stL->execute([(int)$b['lehrer_id']]);
                $le = $stL->fetch() ?: [];
                $lehrkraft = (string)($le['name'] ?: ($le['kuerzel'] ?? 'die Lehrkraft'));

                $t = mit_text_absage((string)$sp['name'], (string)$sp['datum'],
                    (string)$b['slot_beginn'], $lehrkraft,
                    substr((string)($_GET['nachricht'] ?? ''), 0, 500));
                // Mit hinterlegtem Dienstkonto direkt versenden –
                // sonst nur vormerken (Versand über die Mitteilungsansicht).
                $zugang = dk_lesen($cfg, $pdo);
                $mitteilung = mit_einreihen_und_senden($cfg, $pdo,
                    (int)$b['sprechtag_id'], (int)$b['eltern_user_id'],
                    'absage', $t['betreff'], $t['text'],
                    $zugang['benutzer'] ?? null, $zugang['passwort'] ?? null,
                    (int)$b['schueler_id']);
            } catch (Throwable $e) {
                error_log('sprechtag: Absage nicht vorgemerkt: ' . $e->getMessage());
            }
        }

        json_ok(['ok' => true, 'mitteilung' => $mitteilung]);
    }
}

// ============================================================
// EINLADUNGEN (Phase 1)
// ============================================================
if (($seg[0] ?? '') === 'einladungen') {
    $u   = auth_require();       // Guard VOR db()
    $pdo = db($cfg);

    if ($methode === 'GET') {
        $sid = (int)($_GET['sprechtag'] ?? 0);

        if (in_array($u['rolle'], ['lehrkraft', 'admin'], true)) {
            $lid = $u['rolle'] === 'admin' && isset($_GET['lehrer'])
                ? (int)$_GET['lehrer'] : (int)($u['lehrer_id'] ?? 0);
            $st = $pdo->prepare(
                'SELECT e.id, e.schueler_id, e.hinweis, e.erledigt, e.angelegt_am,
                        TRIM(CONCAT(COALESCE(s.nachname,""),
                             IF(s.vorname IS NULL OR s.vorname = "", "",
                                CONCAT(", ", s.vorname)))) AS kind_name,
                        s.klasse
                 FROM einladungen e
                 LEFT JOIN schueler s ON s.webuntis_id = e.schueler_id
                 WHERE e.sprechtag_id = ? AND e.lehrer_id = ?
                 ORDER BY s.klasse, s.nachname, e.angelegt_am DESC');
            $st->execute([$sid, $lid]);
            json_ok(['einladungen' => $st->fetchAll()]);
        }

        // Eltern: nur Einladungen für die eigenen Kinder
        $kinder = array_column($u['kinder'], 'id');
        if ($kinder === []) json_ok(['einladungen' => []]);
        $platzhalter = implode(',', array_fill(0, count($kinder), '?'));
        $st = $pdo->prepare(
            "SELECT e.id, e.schueler_id, e.hinweis, e.erledigt,
                    l.id AS lehrer_id, l.kuerzel, l.name
             FROM einladungen e JOIN lehrer l ON l.id = e.lehrer_id
             WHERE e.sprechtag_id = ? AND e.schueler_id IN ($platzhalter)");
        $st->execute(array_merge([$sid], $kinder));
        json_ok(['einladungen' => $st->fetchAll()]);
    }

    if ($methode === 'POST') {
        $u   = auth_require_lehrkraft();
        $sid = (int)($body['sprechtag_id'] ?? 0);
        $lid = $u['rolle'] === 'admin' && isset($body['lehrer_id'])
            ? (int)$body['lehrer_id'] : (int)($u['lehrer_id'] ?? 0);
        if ($lid <= 0) {
            json_err('Diesem Konto ist keine Lehrkraft zugeordnet. Bitte die '
                . 'Administration bitten, die Stammdaten zu synchronisieren.', 409);
        }
        if ($sid <= 0) json_err('Kein Sprechtag ausgewählt');
        bu_sprechtag($pdo, $sid);   // bricht mit 404 ab, wenn es ihn nicht gibt

        $kind = (int)req($body, 'schueler_id');
        if ($kind <= 0) json_err('Ungültige Schüler-ID');

        // Ist die ID plausibel? Wenn eine Schülerliste gepflegt ist,
        // muss die ID darin vorkommen – sonst entstehen Einladungen für
        // Kinder, die nie buchen können (z. B. Tippfehler wie "7").
        $st = $pdo->query('SELECT COUNT(*) FROM schueler WHERE webuntis_id IS NOT NULL');
        if ((int)$st->fetchColumn() > 0) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM schueler WHERE webuntis_id = ?');
            $st->execute([$kind]);
            if ((int)$st->fetchColumn() === 0) {
                json_err('Zu dieser Schüler-ID gibt es keinen Eintrag in der '
                    . 'Schülerliste. Bitte über die Klassenauswahl einladen '
                    . 'oder die Liste aktualisieren.', 404);
            }
        }

        $pdo->prepare('INSERT IGNORE INTO einladungen
            (sprechtag_id, lehrer_id, schueler_id, hinweis) VALUES (?, ?, ?, ?)')
            ->execute([$sid, $lid, $kind,
                substr((string)($body['hinweis'] ?? ''), 0, 190)]);

        // ---- Benachrichtigung der Eltern --------------------------------
        // Elternkonten über den gemeinsamen Helfer ermitteln (WebUntis-
        // Suche mit Rückfall auf frühere Buchungen). Dieselbe Logik nutzt
        // auch die stellvertretende Buchung.
        $mitteilung = null;
        $aufl = mit_eltern_ids_ermitteln($cfg, $pdo, $kind);
        $elternIds = $aufl['ids'];
        $quelle    = $aufl['quelle'];
        $kindName  = $aufl['kind_name'];
        $zugang    = dk_lesen($cfg, $pdo);

        if ($elternIds !== []) {
            try {
                $stS = $pdo->prepare('SELECT name, datum FROM sprechtage WHERE id = ?');
                $stS->execute([$sid]);
                $sp = $stS->fetch() ?: ['name' => 'Elternsprechtag', 'datum' => ''];

                $stL = $pdo->prepare('SELECT kuerzel, name FROM lehrer WHERE id = ?');
                $stL->execute([$lid]);
                $le = $stL->fetch() ?: [];
                $lehrkraft = (string)($le['name'] ?: ($le['kuerzel'] ?? 'die Lehrkraft'));

                $t = mit_text_einladung((string)$sp['name'], (string)$sp['datum'],
                    $lehrkraft, $kindName,
                    substr((string)($body['hinweis'] ?? ''), 0, 500));

                foreach ($elternIds as $eid) {
                    $mitteilung = mit_einreihen_und_senden($cfg, $pdo, $sid, (int)$eid,
                        'einladung', $t['betreff'], $t['text'],
                        $zugang['benutzer'] ?? null, $zugang['passwort'] ?? null,
                        $kind);
                }
            } catch (Throwable $e) {
                error_log('sprechtag: Einladungs-Mitteilung fehlgeschlagen: '
                    . $e->getMessage());
            }
        }

        json_ok(['ok' => true,
            'mitteilung' => $mitteilung,
            'eltern_bekannt' => $elternIds !== [],
            'eltern_anzahl' => count($elternIds),
            'quelle' => $quelle,
            'hinweis' => $elternIds === []
                ? 'Einladung angelegt. Eine automatische Benachrichtigung war '
                    . 'nicht möglich, weil kein Elternkonto gefunden wurde – '
                    . 'bitte die Eltern auf anderem Weg informieren.'
                : 'Einladung angelegt, ' . count($elternIds)
                    . ' Erziehungsberechtigte(r) benachrichtigt.'], 201);
    }

    if ($methode === 'DELETE' && isset($seg[1]) && ctype_digit($seg[1])) {
        $u  = auth_require_lehrkraft();
        $st = $pdo->prepare('SELECT lehrer_id FROM einladungen WHERE id = ?');
        $st->execute([(int)$seg[1]]);
        $ziel = $st->fetchColumn();
        if ($ziel === false) json_err('Einladung nicht gefunden', 404);
        if ($u['rolle'] === 'lehrkraft' && (int)$ziel !== (int)($u['lehrer_id'] ?? 0)) {
            json_err('Diese Einladung gehört zu einer anderen Lehrkraft', 403);
        }
        $pdo->prepare('DELETE FROM einladungen WHERE id = ?')->execute([(int)$seg[1]]);
        json_ok(['ok' => true]);
    }
}
