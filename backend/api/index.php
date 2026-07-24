<?php
// ============================================================
// api/index.php – API-Router des Projekts "sprechtag"
//
// Routen (Auszug):
//   GET  /api/health
//   POST /api/auth/login          {benutzername, passwort}
//   POST /api/auth/logout
//   GET  /api/auth/me
//   GET  /api/sprechtage                  (Liste; Admin sieht alle)
//   POST /api/sprechtage                  (anlegen, Admin)
//   PATCH/DELETE /api/sprechtage/{id}     (Admin)
//   POST /api/sprechtage/{id}/kopieren    (Archiv wiederverwenden)
//   GET  /api/sprechtage/{id}/lehrer      (Teilnahme/Räume)
//   PATCH /api/sprechtage/{id}/lehrer/{lid}
//   GET  /api/sprechtage/{id}/raumkonflikte
//   GET  /api/stammdaten                  (Lehrer/Räume/Rollen)
//   POST /api/stammdaten/sync             (Admin, WebUntis)
//   GET/POST/DELETE /api/admins           (Admin)
//   GET/POST/DELETE /api/sonderlehrer
//   GET  /api/buchbare-lehrer?kind=ID     (Eltern)
//   GET  /api/raster?sprechtag=ID&lehrer=ID
//   GET/POST /api/buchungen, DELETE /api/buchungen/{id}
//   GET/POST/DELETE /api/einladungen      (Phase 1, Lehrkraft)
//   POST /api/sondierung                  (Werkzeug, abschaltbar)
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/slots.php';
require_once __DIR__ . '/webuntis_adapter.php';
require_once __DIR__ . '/sondierung.php';
require_once __DIR__ . '/mitteilungen.php';
require_once __DIR__ . '/dienstkonto.php';
require_once __DIR__ . '/schueler.php';

$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pfad    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$seg     = array_values(array_filter(explode('/', trim($pfad, '/'))));
array_shift($seg);   // 'api' entfernen
$body    = in_array($methode, ['POST', 'PATCH', 'PUT'], true) ? body_json() : [];

// ---- GET /api/health ---------------------------------------
if ($methode === 'GET' && ($seg[0] ?? '') === 'health') {
    $db = 'fehlt';
    try { db($cfg)->query('SELECT 1'); $db = 'ok'; } catch (Throwable $e) { }
    json_ok(['app' => 'sprechtag', 'version' => '0.9.4', 'db' => $db]);
}

// ============================================================
// AUTH
// ============================================================
if (($seg[0] ?? '') === 'auth') {
    $unter = $seg[1] ?? '';

    if ($methode === 'GET' && $unter === 'me') {
        $u = auth_user();
        json_ok($u === null ? ['angemeldet' => false] : ['angemeldet' => true] + $u);
    }

    if ($methode === 'POST' && $unter === 'logout') {
        auth_logout();
        json_ok(['ok' => true]);
    }

    if ($methode === 'POST' && $unter === 'login') {
        $benutzer = req($body, 'benutzername');
        $passwort = req($body, 'passwort');
        $pdo = db($cfg);

        // Brute-Force-Bremse VOR dem WebUntis-Aufruf
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM login_log
             WHERE webuntis_benutzer = ? AND erfolgreich = 0
               AND zeitpunkt >= NOW() - INTERVAL ? MINUTE');
        $st->execute([$benutzer, (int)($cfg['security']['lockout_minutes'] ?? 15)]);
        if ((int)$st->fetchColumn() >= (int)($cfg['security']['max_failed_logins'] ?? 5)) {
            json_err('Zu viele Fehlversuche – bitte später erneut versuchen', 429);
        }

        try {
            $daten = wu_login($cfg, $pdo, $benutzer, $passwort);
        } catch (RuntimeException $e) {
            $pdo->prepare('INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, ip)
                           VALUES (?, 0, ?, ?)')
                ->execute([$benutzer, substr($e->getMessage(), 0, 190),
                    $_SERVER['REMOTE_ADDR'] ?? '']);
            json_err('Anmeldung fehlgeschlagen: ' . $e->getMessage(), 401);
        }

        auth_login_speichern($daten);
        $pdo->prepare('INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, ip)
                       VALUES (?, 1, ?, ?)')
            ->execute([$benutzer, $daten['rolle'], $_SERVER['REMOTE_ADDR'] ?? '']);
        json_ok(['angemeldet' => true] + auth_user());
    }
}

// ============================================================
// STAMMDATEN
// ============================================================
if (($seg[0] ?? '') === 'stammdaten') {
    if ($methode === 'GET' && !isset($seg[1])) {
        auth_require();          // Guard VOR db(): 401 statt 503 bei DB-Ausfall
        $pdo = db($cfg);
        json_ok([
            'lehrer' => $pdo->query('SELECT id, webuntis_id, kuerzel, name, aktiv
                FROM lehrer WHERE aktiv = 1 ORDER BY kuerzel')->fetchAll(),
            'raeume' => $pdo->query('SELECT id, webuntis_id, kuerzel, name
                FROM raeume WHERE aktiv = 1 ORDER BY kuerzel')->fetchAll(),
            'sonderrollen' => $pdo->query('SELECT id, bezeichnung
                FROM sonderrollen ORDER BY reihenfolge, bezeichnung')->fetchAll(),
        ]);
    }

    if ($methode === 'POST' && ($seg[1] ?? '') === 'sync') {
        auth_require_admin();
        $pdo = db($cfg);
        ignore_user_abort(true);
        set_time_limit(0);
        try {
            $zahl = wu_stammdaten_sync($cfg, $pdo,
                req($body, 'benutzername'), req($body, 'passwort'));
            json_ok(['ok' => true] + $zahl);
        } catch (RuntimeException $e) {
            json_err('Stammdaten-Sync fehlgeschlagen: ' . $e->getMessage(), 502);
        }
    }
}

// ============================================================
// APP-ADMINS
// ============================================================
if (($seg[0] ?? '') === 'admins') {
    auth_require_admin();        // Guard VOR db()
    $pdo = db($cfg);

    if ($methode === 'GET') {
        json_ok(['admins' => $pdo->query('SELECT id, lehrer_kuerzel, anzeigename,
            angelegt_von, angelegt_am FROM app_admins ORDER BY lehrer_kuerzel')->fetchAll()]);
    }
    if ($methode === 'POST') {
        $u = auth_user();
        $kuerzel = req($body, 'kuerzel');
        $st = $pdo->prepare('SELECT name FROM lehrer WHERE kuerzel = ? LIMIT 1');
        $st->execute([$kuerzel]);
        $name = (string)($st->fetchColumn() ?: '');
        $pdo->prepare('INSERT INTO app_admins (lehrer_kuerzel, anzeigename, angelegt_von)
             VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE anzeigename = VALUES(anzeigename)')
            ->execute([$kuerzel, $name, (string)($u['kuerzel'] ?? '')]);
        json_ok(['ok' => true]);
    }
    if ($methode === 'DELETE' && isset($seg[1])) {
        $u = auth_user();
        $st = $pdo->prepare('SELECT lehrer_kuerzel FROM app_admins WHERE id = ?');
        $st->execute([(int)$seg[1]]);
        $ziel = $st->fetchColumn();
        if ($ziel !== false && $ziel === ($u['kuerzel'] ?? null)) {
            json_err('Du kannst dich nicht selbst als Administrator entfernen', 403);
        }
        $pdo->prepare('DELETE FROM app_admins WHERE id = ?')->execute([(int)$seg[1]]);
        json_ok(['ok' => true]);
    }
}

// ============================================================
// SPRECHTAGE
// ============================================================
if (($seg[0] ?? '') === 'sprechtage') {
    $u = auth_require();         // Guard VOR db(): alle Sprechtag-Routen
    $pdo = db($cfg);

    // ---- Liste ----
    if ($methode === 'GET' && !isset($seg[1])) {
        $sql = 'SELECT * FROM sprechtage';
        if ($u['rolle'] !== 'admin') {
            $sql .= " WHERE phase IN ('phase1','phase2','geschlossen')";
        }
        json_ok(['sprechtage' => $pdo->query($sql . ' ORDER BY datum DESC')->fetchAll()]);
    }

    // ---- Anlegen ----
    if ($methode === 'POST' && !isset($seg[1])) {
        auth_require_admin();
        $datum = req($body, 'datum');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            json_err('datum muss das Format JJJJ-MM-TT haben');
        }
        $ref = wu_referenzzeitraum($datum);
        $pdo->prepare('INSERT INTO sprechtage
            (name, datum, beginn, ende, slot_minuten, max_termine_pro_eltern,
             pause_nach_terminen, pause_minuten, referenz_von, referenz_bis)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                req($body, 'name'), $datum,
                (string)($body['beginn'] ?? '15:00'),
                (string)($body['ende'] ?? '19:00'),
                (int)($body['slot_minuten'] ?? 10),
                (int)($body['max_termine_pro_eltern'] ?? 6),
                (int)($body['pause_nach_terminen'] ?? 0),
                (int)($body['pause_minuten'] ?? 10),
                $ref['von'], $ref['bis'],
            ]);
        json_ok(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
    }

    $sid = isset($seg[1]) && ctype_digit($seg[1]) ? (int)$seg[1] : 0;

    // ---- Ändern ----
    if ($methode === 'PATCH' && $sid > 0 && !isset($seg[2])) {
        auth_require_admin();
        $erlaubt = ['name', 'datum', 'beginn', 'ende', 'slot_minuten',
            'max_termine_pro_eltern', 'pause_nach_terminen', 'pause_minuten',
            'phase', 'referenz_von', 'referenz_bis', 'klausuren_werten'];
        $sets = []; $werte = [];
        foreach ($erlaubt as $feld) {
            if (!array_key_exists($feld, $body)) continue;
            if ($feld === 'phase' && !in_array($body[$feld],
                ['vorbereitung','phase1','phase2','geschlossen','archiviert'], true)) {
                json_err('Unbekannte Phase');
            }
            $sets[]  = "$feld = ?";
            $werte[] = $body[$feld];
        }
        if ($sets === []) json_err('Keine Änderungen übergeben');
        $archivieren = ($body['phase'] ?? '') === 'archiviert';
        if ($archivieren) $sets[] = 'archiviert_am = NOW()';
        $werte[] = $sid;
        $pdo->prepare('UPDATE sprechtage SET ' . implode(', ', $sets) . ' WHERE id = ?')
            ->execute($werte);

        // Archivieren = personenbezogene Daten löschen (Datensparsamkeit).
        // Struktur (Lehrkräfte, Räume, Sonderrollen) bleibt für die
        // Wiederverwendung erhalten.
        if ($archivieren) {
            $pdo->prepare('DELETE FROM buchungen WHERE sprechtag_id = ?')->execute([$sid]);
            $pdo->prepare('DELETE FROM einladungen WHERE sprechtag_id = ?')->execute([$sid]);
            $pdo->prepare('DELETE FROM kind_lehrer_cache WHERE sprechtag_id = ?')->execute([$sid]);
            // Mitteilungstexte enthalten Namen von Lehrkräften und Zeiten
            $pdo->prepare('DELETE FROM mitteilungen WHERE sprechtag_id = ?')->execute([$sid]);
        }
        json_ok(['ok' => true, 'anonymisiert' => $archivieren]);
    }

    // ---- Löschen ----
    if ($methode === 'DELETE' && $sid > 0 && !isset($seg[2])) {
        auth_require_admin();
        $pdo->prepare('DELETE FROM sprechtage WHERE id = ?')->execute([$sid]);
        json_ok(['ok' => true]);
    }

    // ---- Kopieren (Archiv wiederverwenden) ----
    if ($methode === 'POST' && $sid > 0 && ($seg[2] ?? '') === 'kopieren') {
        auth_require_admin();
        $st = $pdo->prepare('SELECT * FROM sprechtage WHERE id = ?');
        $st->execute([$sid]);
        $alt = $st->fetch();
        if (!$alt) json_err('Sprechtag nicht gefunden', 404);

        $datum = (string)($body['datum'] ?? $alt['datum']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            json_err('datum muss das Format JJJJ-MM-TT haben');
        }
        $ref = wu_referenzzeitraum($datum);
        $pdo->prepare('INSERT INTO sprechtage
            (name, datum, beginn, ende, slot_minuten, max_termine_pro_eltern,
             pause_nach_terminen, pause_minuten, phase, referenz_von, referenz_bis)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "vorbereitung", ?, ?)')
            ->execute([
                (string)($body['name'] ?? ($alt['name'] . ' (Kopie)')), $datum,
                $alt['beginn'], $alt['ende'], $alt['slot_minuten'],
                $alt['max_termine_pro_eltern'], $alt['pause_nach_terminen'],
                $alt['pause_minuten'], $ref['von'], $ref['bis'],
            ]);
        $neu = (int)$pdo->lastInsertId();

        // Struktur übernehmen – NICHT die Buchungen (personenbezogen!)
        $pdo->prepare('INSERT INTO sprechtag_lehrer
            (sprechtag_id, lehrer_id, anwesend_von, anwesend_bis, raum_id, teilnahme, bemerkung)
            SELECT ?, lehrer_id, anwesend_von, anwesend_bis, raum_id, teilnahme, bemerkung
            FROM sprechtag_lehrer WHERE sprechtag_id = ?')->execute([$neu, $sid]);
        $pdo->prepare('INSERT INTO sprechtag_sonderlehrer
            (sprechtag_id, lehrer_id, rolle_id, jahrgaenge)
            SELECT ?, lehrer_id, rolle_id, jahrgaenge
            FROM sprechtag_sonderlehrer WHERE sprechtag_id = ?')->execute([$neu, $sid]);

        json_ok(['ok' => true, 'id' => $neu], 201);
    }

    // ---- Teilnehmende Lehrkräfte ----
    if ($sid > 0 && ($seg[2] ?? '') === 'lehrer') {
        if ($methode === 'GET') {
            auth_require();
            $st = $pdo->prepare(
                'SELECT l.id AS lehrer_id, l.kuerzel, l.name,
                        sl.id AS zuweisung_id, sl.anwesend_von, sl.anwesend_bis,
                        sl.raum_id, sl.teilnahme, sl.bemerkung,
                        r.kuerzel AS raum_kuerzel
                 FROM lehrer l
                 LEFT JOIN sprechtag_lehrer sl
                        ON sl.lehrer_id = l.id AND sl.sprechtag_id = ?
                 LEFT JOIN raeume r ON r.id = sl.raum_id
                 WHERE l.aktiv = 1 ORDER BY l.kuerzel');
            $st->execute([$sid]);
            json_ok(['lehrer' => $st->fetchAll()]);
        }
        if ($methode === 'PATCH' && isset($seg[3]) && ctype_digit($seg[3])) {
            auth_require_admin();
            $lid = (int)$seg[3];
            foreach (['anwesend_von', 'anwesend_bis'] as $feld) {
                $w = (string)($body[$feld] ?? '');
                if ($w !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $w)) {
                    json_err("$feld muss das Format HH:MM haben");
                }
            }
            $pdo->prepare('INSERT INTO sprechtag_lehrer
                (sprechtag_id, lehrer_id, anwesend_von, anwesend_bis, raum_id, teilnahme, bemerkung)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE anwesend_von = VALUES(anwesend_von),
                    anwesend_bis = VALUES(anwesend_bis), raum_id = VALUES(raum_id),
                    teilnahme = VALUES(teilnahme), bemerkung = VALUES(bemerkung)')
                ->execute([$sid, $lid,
                    ($body['anwesend_von'] ?? '') !== '' ? $body['anwesend_von'] : null,
                    ($body['anwesend_bis'] ?? '') !== '' ? $body['anwesend_bis'] : null,
                    ($body['raum_id'] ?? '') !== '' ? (int)$body['raum_id'] : null,
                    isset($body['teilnahme']) ? (int)(bool)$body['teilnahme'] : 1,
                    substr((string)($body['bemerkung'] ?? ''), 0, 190)]);
            json_ok(['ok' => true]);
        }
    }

    // ---- Raumkonflikte (doppelt belegte Räume) ----
    if ($methode === 'GET' && $sid > 0 && ($seg[2] ?? '') === 'raumkonflikte') {
        auth_require();
        $st = $pdo->prepare('SELECT lehrer_id, raum_id FROM sprechtag_lehrer
                             WHERE sprechtag_id = ? AND teilnahme = 1');
        $st->execute([$sid]);
        json_ok(['konflikte' => slot_raumkonflikte($st->fetchAll())]);
    }
}

// ============================================================
// SONDERLEHRKRÄFTE
// ============================================================
if (($seg[0] ?? '') === 'sonderlehrer') {
    auth_require();              // Guard VOR db()
    $pdo = db($cfg);
    $sid = (int)($_GET['sprechtag'] ?? $body['sprechtag_id'] ?? 0);

    if ($methode === 'GET') {
        $st = $pdo->prepare(
            'SELECT sl.id, sl.lehrer_id, sl.rolle_id, sl.jahrgaenge,
                    l.kuerzel, l.name, sr.bezeichnung AS rolle
             FROM sprechtag_sonderlehrer sl
             JOIN lehrer l ON l.id = sl.lehrer_id
             JOIN sonderrollen sr ON sr.id = sl.rolle_id
             WHERE sl.sprechtag_id = ? ORDER BY sr.reihenfolge, l.kuerzel');
        $st->execute([$sid]);
        json_ok(['sonderlehrer' => $st->fetchAll()]);
    }
    if ($methode === 'POST') {
        auth_require_admin();
        $pdo->prepare('INSERT IGNORE INTO sprechtag_sonderlehrer
            (sprechtag_id, lehrer_id, rolle_id, jahrgaenge) VALUES (?, ?, ?, ?)')
            ->execute([$sid, (int)req($body, 'lehrer_id'), (int)req($body, 'rolle_id'),
                substr((string)($body['jahrgaenge'] ?? ''), 0, 120)]);
        json_ok(['ok' => true], 201);
    }
    if ($methode === 'DELETE' && isset($seg[1]) && ctype_digit($seg[1])) {
        auth_require_admin();
        $pdo->prepare('DELETE FROM sprechtag_sonderlehrer WHERE id = ?')->execute([(int)$seg[1]]);
        json_ok(['ok' => true]);
    }
}

// ============================================================
// SONDIERUNG (Werkzeug aus Paket 1, abschaltbar)
// ============================================================
if ($methode === 'POST' && ($seg[0] ?? '') === 'sondierung') {
    if (($cfg['sondierung_freigeschaltet'] ?? false) !== true) {
        json_err('Sondierung ist in config.php abgeschaltet', 403);
    }
    $benutzer = req($body, 'benutzername');
    $passwort = req($body, 'passwort');
    $gruppen  = array_values(array_intersect((array)($body['gruppen'] ?? []),
        ['basis', 'sprechtag', 'stundenplan', 'mitteilungen', 'stammdaten']));
    if ($gruppen === []) $gruppen = ['basis'];
    $extraPfade = preg_split('/\r?\n/', (string)($body['extra_pfade'] ?? '')) ?: [];
    $schuelerId = trim((string)($body['schueler_id'] ?? ''));
    if ($schuelerId !== '' && !ctype_digit($schuelerId)) {
        json_err('schueler_id muss eine Zahl sein');
    }
    $von = trim((string)($body['von'] ?? ''));
    $bis = trim((string)($body['bis'] ?? ''));
    foreach (['von' => $von, 'bis' => $bis] as $feld => $wert) {
        if ($wert !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert)) {
            json_err("$feld muss das Format JJJJ-MM-TT haben");
        }
    }
    ignore_user_abort(true);
    set_time_limit(0);
    try {
        json_ok(['bericht' => sondierung_ausfuehren($cfg, $benutzer, $passwort,
            $gruppen, $extraPfade, $schuelerId, $von, $bis)]);
    } catch (RuntimeException $e) {
        sleep(2);
        json_err('Sondierung fehlgeschlagen: ' . $e->getMessage(), 502);
    }
}

// ============================================================
// MITTEILUNGEN
//   GET    /api/mitteilungen?sprechtag=ID[&status=offen]
//   POST   /api/mitteilungen/senden   {sprechtag_id, ids?, benutzername, passwort}
//   POST   /api/mitteilungen          {sprechtag_id, empfaenger_user_id, betreff, text}
//   DELETE /api/mitteilungen/{id}     (verwerfen)
// ============================================================
if (($seg[0] ?? '') === 'mitteilungen') {
    $u   = auth_require_lehrkraft();   // Eltern haben hier nichts zu suchen
    $pdo = db($cfg);

    if ($methode === 'GET' && !isset($seg[1])) {
        $sid = (int)($_GET['sprechtag'] ?? 0);
        $sql = 'SELECT m.id, m.empfaenger_user_id, m.schueler_id, m.anlass,
                       m.betreff, m.status, m.grund, m.versuche,
                       m.angelegt_am, m.gesendet_am,
                       TRIM(CONCAT(COALESCE(s.nachname,""),
                            IF(s.vorname IS NULL OR s.vorname = "", "",
                               CONCAT(", ", s.vorname)))) AS kind_name,
                       s.klasse
                FROM mitteilungen m
                LEFT JOIN schueler s ON s.webuntis_id = m.schueler_id
                WHERE m.sprechtag_id = ?';
        $werte = [$sid];
        if (($_GET['status'] ?? '') !== '') {
            $st = (string)$_GET['status'];
            if (!in_array($st, ['offen', 'gesendet', 'verworfen'], true)) {
                json_err('Unbekannter Status');
            }
            $sql .= ' AND m.status = ?';
            $werte[] = $st;
        }
        $stmt = $pdo->prepare($sql . ' ORDER BY m.angelegt_am DESC LIMIT 500');
        $stmt->execute($werte);
        json_ok(['mitteilungen' => $stmt->fetchAll()]);
    }

    // Versand anstoßen.
    // Zugangsdaten: übergeben > hinterlegtes Dienstkonto. Ist eines
    // hinterlegt, brauchen weder Admins noch Lehrkräfte etwas einzugeben.
    if ($methode === 'POST' && ($seg[1] ?? '') === 'senden') {
        $sid = (int)($body['sprechtag_id'] ?? 0);
        $ids = array_values(array_filter(array_map('intval',
            (array)($body['ids'] ?? [])), fn($i) => $i > 0));

        if ($ids === []) {   // alle offenen des Sprechtags
            $stmt = $pdo->prepare("SELECT id FROM mitteilungen
                WHERE sprechtag_id = ? AND status = 'offen' LIMIT 200");
            $stmt->execute([$sid]);
            $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        }

        // Lehrkräfte dürfen nur Mitteilungen versenden, die zu ihren
        // eigenen Buchungen gehören; Admins alles.
        if ($u['rolle'] !== 'admin' && $ids !== []) {
            $lid = (int)($u['lehrer_id'] ?? 0);
            if ($lid <= 0) json_err('Kein Lehrkraft-Stammsatz zugeordnet', 403);
            $platz = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                "SELECT m.id FROM mitteilungen m
                 WHERE m.id IN ($platz) AND m.sprechtag_id = ?
                   AND EXISTS (SELECT 1 FROM buchungen b
                               WHERE b.sprechtag_id = m.sprechtag_id
                                 AND b.eltern_user_id = m.empfaenger_user_id
                                 AND b.lehrer_id = ?)");
            $stmt->execute(array_merge($ids, [$sid, $lid]));
            $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
            if ($ids === []) {
                json_err('Keine dieser Mitteilungen gehört zu Ihren Terminen. '
                    . 'Der Sammelversand ist der Administration vorbehalten.', 403);
            }
        }

        $zugang = null;
        if (($body['benutzername'] ?? '') !== '' && ($body['passwort'] ?? '') !== '') {
            $zugang = ['benutzer' => (string)$body['benutzername'],
                       'passwort' => (string)$body['passwort']];
        } else {
            $zugang = dk_lesen($cfg, $pdo);
        }
        if ($zugang === null) {
            json_err('Kein Dienstkonto hinterlegt und keine Zugangsdaten '
                . 'übergeben. Die Administration kann ein Dienstkonto '
                . 'unter „Administration → Dienstkonto" eintragen.', 409);
        }

        ignore_user_abort(true);
        set_time_limit(0);
        $e = mit_versand_ausfuehren($cfg, $pdo, $ids,
            $zugang['benutzer'], $zugang['passwort']);
        // Protokoll je Variante mitgeben – ohne diese Details lässt sich
        // der undokumentierte Versandweg nicht kalibrieren.
        json_ok($e);
    }

    // Freie Mitteilung vormerken
    if ($methode === 'POST' && !isset($seg[1])) {
        $empf = (int)($body['empfaenger_user_id'] ?? 0);
        if ($empf <= 0) json_err('empfaenger_user_id fehlt');
        $e = mit_einreihen_und_senden($cfg, $pdo,
            (int)($body['sprechtag_id'] ?? 0), $empf, 'hinweis',
            req($body, 'betreff'), req($body, 'text'));
        json_ok($e, 201);
    }

    if ($methode === 'DELETE' && isset($seg[1]) && ctype_digit($seg[1])) {
        $pdo->prepare("UPDATE mitteilungen SET status = 'verworfen' WHERE id = ?")
            ->execute([(int)$seg[1]]);
        json_ok(['ok' => true]);
    }
}

// ============================================================
// DIENSTKONTO (verschlüsselt gespeicherte Zugangsdaten)
//   GET    /api/dienstkonto          Status (nie das Passwort!)
//   POST   /api/dienstkonto          {benutzername, passwort}
//   DELETE /api/dienstkonto          entfernen
// ============================================================
if (($seg[0] ?? '') === 'dienstkonto') {
    // Lesen dürfen auch Lehrkräfte (die Oberfläche muss wissen, ob
    // Zugangsdaten abgefragt werden müssen). Ändern nur Admins.
    $u = auth_require_lehrkraft();
    $pdo = db($cfg);

    if ($methode === 'GET') {
        $st = dk_status($cfg, $pdo);
        if ($u['rolle'] !== 'admin') {
            // Lehrkräfte sehen nur, OB eines nutzbar ist – nicht welches
            $st = ['hinterlegt' => $st['hinterlegt'],
                   'entschluesselbar' => $st['entschluesselbar']];
        }
        json_ok($st);
    }

    if ($methode !== 'GET') auth_require_admin();
    if ($methode === 'POST') {
        $e = dk_speichern($cfg, $pdo, req($body, 'benutzername'), req($body, 'passwort'));
        if (!$e['ok']) json_err($e['grund'], 409);
        json_ok(['ok' => true, 'grund' => $e['grund']] + dk_status($cfg, $pdo));
    }
    if ($methode === 'DELETE') {
        dk_loeschen($pdo);
        json_ok(['ok' => true]);
    }
}

// ============================================================
// SCHÜLERLISTE (für die Einladungsauswahl)
//   GET    /api/schueler[?suche=...]   nach Klassen gruppiert
//   POST   /api/schueler/csv           {csv}          (Admin)
//   POST   /api/schueler/sync          [{benutzername, passwort}] (Admin)
//   DELETE /api/schueler               alle löschen   (Admin)
// ============================================================
if (($seg[0] ?? '') === 'schueler') {
    $u   = auth_require_lehrkraft();   // Eltern haben hier nichts zu suchen
    $pdo = db($cfg);

    if ($methode === 'GET' && !isset($seg[1])) {
        $klassen = schueler_liste($pdo, trim((string)($_GET['suche'] ?? '')));
        json_ok(['klassen' => $klassen,
                 'anzahl' => array_sum(array_map('count', $klassen))]);
    }

    if ($methode === 'POST' && ($seg[1] ?? '') === 'csv') {
        auth_require_admin();
        $roh = (string)($body['csv'] ?? '');
        if (trim($roh) === '') json_err('Keine CSV-Daten übergeben');
        $g = schueler_csv_parsen($roh);
        if ($g['zeilen'] === []) {
            json_err('Keine gültigen Zeilen erkannt. Erwartet wird je Zeile: '
                . 'Nachname;Vorname;Klasse[;Schild-ID]');
        }
        $e = schueler_csv_importieren($pdo, $g['zeilen']);
        json_ok(['ok' => true] + $e + ['uebersprungen' => $g['uebersprungen']]);
    }

    if ($methode === 'POST' && ($seg[1] ?? '') === 'sync') {
        auth_require_admin();
        $zugang = null;
        if (($body['benutzername'] ?? '') !== '' && ($body['passwort'] ?? '') !== '') {
            $zugang = ['benutzer' => (string)$body['benutzername'],
                       'passwort' => (string)$body['passwort']];
        } else {
            $zugang = dk_lesen($cfg, $pdo);
        }
        if ($zugang === null) json_err('Kein Dienstkonto hinterlegt', 409);

        ignore_user_abort(true);
        set_time_limit(0);
        try {
            json_ok(['ok' => true] + schueler_webuntis_sync($cfg, $pdo,
                $zugang['benutzer'], $zugang['passwort']));
        } catch (RuntimeException $e) {
            json_err('Schüler-Sync fehlgeschlagen: ' . $e->getMessage(), 502);
        }
    }

    if ($methode === 'DELETE' && !isset($seg[1])) {
        auth_require_admin();
        $pdo->exec('DELETE FROM schueler');
        json_ok(['ok' => true]);
    }
}

require __DIR__ . '/buchungen.php';   // Buchungs-, Raster- und Einladungsrouten

json_err('Unbekannter API-Pfad', 404);
