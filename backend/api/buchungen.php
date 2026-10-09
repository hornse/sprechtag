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

require_once __DIR__ . '/klassenleitung.php';

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
 * Hat diese Lehrkraft dieses Kind für diesen Sprechtag eingeladen?
 * Die einzige Stelle für diese Frage – Buchungsrecht und Buchungsroute
 * fragen hier.
 */
function bu_eingeladen(PDO $pdo, int $sprechtagId, int $schuelerId, int $lehrerId): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM einladungen
                         WHERE sprechtag_id = ? AND lehrer_id = ? AND schueler_id = ?');
    $st->execute([$sprechtagId, $lehrerId, $schuelerId]);
    return (int)$st->fetchColumn() > 0;
}

/**
 * Lehrkräfte, die eines dieser Kinder für diesen Sprechtag eingeladen
 * haben – eine Abfrage für die Kacheln (bu_buchbare_lehrer) und den
 * Elternzweig von GET /api/einladungen. Liefert die Spalten einer
 * Kachel, dazu Einladungsdaten und `teilnahme` (ungefiltert).
 */
function bu_einladende_lehrer(PDO $pdo, int $sprechtagId, array $kinder): array
{
    $kinder = array_values(array_map('intval', $kinder));
    if ($kinder === []) return [];
    $platzhalter = implode(',', array_fill(0, count($kinder), '?'));
    $st = $pdo->prepare(
        "SELECT e.id, e.schueler_id, e.hinweis, e.erledigt,
                l.id AS lehrer_id, l.kuerzel, l.name,
                COALESCE(c.faecher, '') AS faecher,
                COALESCE(c.stunden, 0) AS stunden,
                COALESCE(c.klausuren, 0) AS klausuren,
                sl.anwesend_von, sl.anwesend_bis, sl.teilnahme,
                r.kuerzel AS raum_kuerzel, NULL AS rolle, 1 AS eingeladen
         FROM einladungen e
         JOIN lehrer l ON l.id = e.lehrer_id
         LEFT JOIN kind_lehrer_cache c
                ON c.sprechtag_id = e.sprechtag_id AND c.schueler_id = e.schueler_id
               AND c.lehrer_id = e.lehrer_id
         LEFT JOIN sprechtag_lehrer sl
                ON sl.sprechtag_id = e.sprechtag_id AND sl.lehrer_id = l.id
         LEFT JOIN raeume r ON r.id = sl.raum_id
         WHERE e.sprechtag_id = ? AND e.schueler_id IN ($platzhalter)
         ORDER BY l.kuerzel");
    $st->execute(array_merge([$sprechtagId], $kinder));
    return $st->fetchAll();
}

/**
 * „Teilnehmend“ – eine Regel, in zwei Formen: alle außer teilnahme = 0;
 * keine Zeile in sprechtag_lehrer (NULL) gilt als teilnehmend (E10,
 * Nachtrag Zug 3). Eine Prüfung hält beide Formen gegeneinander.
 */
function bu_teilnehmend_sql(string $alias): string
{
    return "($alias.teilnahme IS NULL OR $alias.teilnahme <> 0)";
}

function bu_teilnehmend($teilnahme): bool
{
    return $teilnahme === null || (int)$teilnahme !== 0;
}

/**
 * Aktive, teilnehmende Lehrkräfte mit den Spalten einer Kachel –
 * optional nur diese lehrer.ids. Quelle für 'weitere', für die nicht
 * unterrichtende Klassenleitung und für das Buchungsrecht ab Phase 2
 * (bu_lehrer_erlaubt). `aktiv` gilt hier, weil ausgeschiedene
 * Lehrkräfte in der Verwaltung nicht erscheinen und dort also nicht auf
 * teilnahme = 0 gesetzt werden können.
 */
function bu_teilnehmende_lehrer(PDO $pdo, int $sid, ?array $nur = null): array
{
    $bedingung = '';
    $werte = [$sid];
    if ($nur !== null) {
        $nur = array_values(array_unique(array_map('intval', $nur)));
        if ($nur === []) return [];
        $bedingung = ' AND l.id IN (' . implode(',', array_fill(0, count($nur), '?')) . ')';
        $werte = array_merge($werte, $nur);
    }
    $st = $pdo->prepare(
        "SELECT l.id AS lehrer_id, l.kuerzel, l.name, '' AS faecher, 0 AS stunden,
                0 AS klausuren,
                sl.anwesend_von, sl.anwesend_bis, r.kuerzel AS raum_kuerzel,
                NULL AS rolle
         FROM lehrer l
         LEFT JOIN sprechtag_lehrer sl ON sl.sprechtag_id = ? AND sl.lehrer_id = l.id
         LEFT JOIN raeume r ON r.id = sl.raum_id
         WHERE l.aktiv = 1 AND " . bu_teilnehmend_sql('sl') . $bedingung . "
         ORDER BY l.kuerzel");
    $st->execute($werte);
    return $st->fetchAll();
}

/**
 * Kachel-Antwort bei gesperrtem Buchen (E15): dieselbe Form wie sonst,
 * aber ohne Lehrkräfte, mit Grund und Erklärung. Beendet den Aufruf.
 */
function bu_gesperrt_antwort(string $sperre): never
{
    json_ok(['eingeladen' => [], 'unterrichtend' => [], 'sonderlehrer' => [], 'weitere' => [],
             'nur_eingeladene' => false, 'buchen_gesperrt' => $sperre,
             'hinweis' => bu_sperre_text($sperre),
             'automatisch_ermittelt' => null, 'ohne_stammsatz' => []]);
}

/**
 * Darf für dieses Kind bei dieser Lehrkraft gebucht werden?
 * Erlaubt, wenn die Lehrkraft das Kind unterrichtet (Cache), als
 * Sonderlehrkraft für den Jahrgang freigegeben ist ODER das Kind
 * eingeladen hat – ab Phase 2 zusätzlich jede aktive, teilnehmende. Ohne die Einladung scheiterte eine eingeladene, nicht
 * unterrichtende Lehrkraft hier, bevor die Phase-1-Prüfung 'eingeladen'
 * las (Befund 08.10.2026, Einladungs-Kachel).
 */
function bu_lehrer_erlaubt(PDO $pdo, int $sprechtagId, int $schuelerId,
                           int $lehrerId, string $jahrgang = '', string $phase = ''): bool
{
    if (bu_eingeladen($pdo, $sprechtagId, $schuelerId, $lehrerId)) return true;

    // Ab Phase 2 jede aktive, teilnehmende Lehrkraft – dieselbe Quelle wie
    // 'weitere' in bu_buchbare_lehrer() (E10, Zug 3).
    if (slot_alle_teilnehmenden_buchbar($phase)
        && bu_teilnehmende_lehrer($pdo, $sprechtagId, [$lehrerId]) !== []) return true;

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

/**
 * Die Kacheln für Eltern: wer für dieses Kind buchbar erscheint (E10).
 *
 *   eingeladen    – Lehrkräfte, die das Kind eingeladen haben und teilnehmen
 *   unterrichtend – Gruppe 2: die Klassenleitung zuerst (ab Phase 2 auch,
 *                   wenn sie nicht unterrichtet), dann laut Stundenplan
 *   sonderlehrer  – Sonderrollen, sichtbar hinter Gruppe 2
 *   weitere       – Gruppe 3, ab Phase 2: alle übrigen aktiven,
 *                   teilnehmenden Lehrkräfte (für die Suche)
 *   nur_eingeladene – true in Phase 1 für Eltern/Schüler: dann stehen
 *                     NUR die Eingeladenen da, nicht zusätzlich (E10)
 *
 * Jede Lehrkraft genau einmal, in der ersten Gruppe, in die sie fällt.
 * $klassenleitung sind lehrer.ids (kl_lehrer_ids); sie tragen
 * 'klassenleitung' = 1, alle anderen 0. Leer heißt: keine Hervorhebung.
 *
 * Dieselben Quellen fragt bu_lehrer_erlaubt() fürs Buchungsrecht – wer
 * hier erscheint, muss dort erlaubt sein.
 */
function bu_buchbare_lehrer(PDO $pdo, int $sid, int $kind, string $phase,
                            string $rolle, string $jahrgang, array $klassenleitung = []): array
{
    $kl = array_values(array_unique(array_map('intval', $klassenleitung)));
    $kennzeichnen = function (array $zeilen) use ($kl): array {
        foreach ($zeilen as $i => $z) {
            $zeilen[$i]['klassenleitung'] = in_array((int)$z['lehrer_id'], $kl, true) ? 1 : 0;
        }
        return $zeilen;
    };
    $idsVon = fn(array $zeilen): array => array_map('intval', array_column($zeilen, 'lehrer_id'));

    $eingeladen = $kennzeichnen(array_values(array_filter(
        bu_einladende_lehrer($pdo, $sid, [$kind]), fn($z) => bu_teilnehmend($z['teilnahme']))));
    $bekannt = $idsVon($eingeladen);

    if (slot_nur_eingeladene($phase, $rolle)) {
        return ['eingeladen' => $eingeladen, 'unterrichtend' => [],
                'sonderlehrer' => [], 'weitere' => [], 'nur_eingeladene' => true];
    }
    $alleBuchbar = slot_alle_teilnehmenden_buchbar($phase);

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
           AND ' . bu_teilnehmend_sql('sl') . '
         ORDER BY c.stunden DESC, l.kuerzel');
    $st->execute([$sid, $kind]);
    // Wer schon als Eingeladene steht, erscheint nicht ein zweites Mal.
    $unterrichtend = array_values(array_filter($st->fetchAll(),
        fn($z) => !in_array((int)$z['lehrer_id'], $bekannt, true)));

    // Klassenleitung: die unterrichtende aus der Liste nach vorn, die nicht
    // unterrichtende nur dort, wo sie auch buchbar ist (ab Phase 2).
    $klVorn = array_values(array_filter($unterrichtend,
        fn($z) => in_array((int)$z['lehrer_id'], $kl, true)));
    $rest = array_values(array_filter($unterrichtend,
        fn($z) => !in_array((int)$z['lehrer_id'], $kl, true)));
    if ($alleBuchbar) {
        $schon = array_merge($bekannt, $idsVon($klVorn));
        foreach (bu_teilnehmende_lehrer($pdo, $sid, $kl) as $z) {
            if (!in_array((int)$z['lehrer_id'], $schon, true)) $klVorn[] = $z;
        }
        usort($klVorn, fn($a, $b) => strcmp((string)$a['kuerzel'], (string)$b['kuerzel']));
    }
    $lehrer = $kennzeichnen(array_merge($klVorn, $rest));
    $bekannt = array_merge($bekannt, $idsVon($lehrer));

    // Sonderlehrkräfte (Jahrgangsfilter greift erst, wenn der Jahrgang bekannt ist)
    $st = $pdo->prepare(
        'SELECT l.id AS lehrer_id, l.kuerzel, l.name, \'\' AS faecher, 0 AS stunden,
                0 AS klausuren,
                sl2.anwesend_von, sl2.anwesend_bis, r.kuerzel AS raum_kuerzel,
                sr.bezeichnung AS rolle, s.jahrgaenge
         FROM sprechtag_sonderlehrer s
         JOIN lehrer l ON l.id = s.lehrer_id
         JOIN sonderrollen sr ON sr.id = s.rolle_id
         LEFT JOIN sprechtag_lehrer sl2
                ON sl2.sprechtag_id = s.sprechtag_id AND sl2.lehrer_id = l.id
         LEFT JOIN raeume r ON r.id = sl2.raum_id
         WHERE s.sprechtag_id = ? AND ' . bu_teilnehmend_sql('sl2') . '
         ORDER BY sr.reihenfolge, l.kuerzel');
    $st->execute([$sid]);
    $sonder = [];
    foreach ($st->fetchAll() as $z) {
        if (!slot_sonderlehrer_passt((string)$z['jahrgaenge'], $jahrgang)) continue;
        if (in_array((int)$z['lehrer_id'], $bekannt, true)) continue;   // schon genannt
        unset($z['jahrgaenge']);
        $sonder[] = $z;
        $bekannt[] = (int)$z['lehrer_id'];
    }
    $sonder = $kennzeichnen($sonder);

    // Gruppe 3: alle übrigen – nur, wo sie auch buchbar sind.
    $weitere = [];
    if ($alleBuchbar) {
        $weitere = $kennzeichnen(array_values(array_filter(bu_teilnehmende_lehrer($pdo, $sid),
            fn($z) => !in_array((int)$z['lehrer_id'], $bekannt, true))));
    }

    return ['eingeladen' => $eingeladen, 'unterrichtend' => $lehrer,
            'sonderlehrer' => $sonder, 'weitere' => $weitere, 'nur_eingeladene' => false];
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
    // Volljährige Schüler nur in zugelassener Gruppe (E15) – dieselbe
    // Entscheidung wie beim Buchen: keine Kachel ohne Buchungsrecht.
    $sperre = bu_buchen_gesperrt($u, bu_zugelassene_gruppen($pdo));
    if ($sperre !== null) bu_gesperrt_antwort($sperre);
    $sprechtag = bu_sprechtag($pdo, $sid);

    // Cache leer? Dann einmalig über die WebUntis-Sitzung der angemeldeten
    // Person ermitteln – kein Dienstkonto mehr (E17). Eltern: gemessen
    // (08.10.2026, Stundenplan des eigenen Kindes). Volljährige Schüler und
    // Verwaltung für beliebige Kinder: NICHT gemessen. Scheitert es, gibt es
    // für dieses Kind keine Kacheln, und 'sitzung' sagt warum.
    // Nicht, wenn ohnehin nur Eingeladene erscheinen (Phase 1, E10).
    // Das passiert genau einmal je Kind und Sprechtag und dauert
    // ein bis zwei Sekunden; danach kommt alles aus der Datenbank.
    $st = $pdo->prepare('SELECT COUNT(*) FROM kind_lehrer_cache
                         WHERE sprechtag_id = ? AND schueler_id = ?');
    $st->execute([$sid, $kind]);
    $ermittelt = null;
    $fehlendeStammdaten = [];
    $sitzungFehlt = null;
    $sitzung = null;   // höchstens ein Sitzungsabruf je Aufruf
    $holeSitzung = function () use (&$sitzung, $cfg): array {
        return $sitzung ??= wu_sitzung($cfg);
    };
    if ((int)$st->fetchColumn() === 0
        && !slot_nur_eingeladene((string)$sprechtag['phase'], (string)$u['rolle'])) {
        $sz = $holeSitzung();
        if ($sz['rest'] === null) {
            $sitzungFehlt = $sz['art'];
        } else {
            $stS = $pdo->prepare('SELECT datum, referenz_von, referenz_bis,
                                         klausuren_werten
                                  FROM sprechtage WHERE id = ?');
            $stS->execute([$sid]);
            $sp = $stS->fetch() ?: [];
            $ref = (!empty($sp['referenz_von']) && !empty($sp['referenz_bis']))
                ? ['von' => $sp['referenz_von'], 'bis' => $sp['referenz_bis']]
                : wu_referenzzeitraum((string)($sp['datum'] ?? date('Y-m-d')));
            try {
                $sz['rest']->setzeTimeout(20);
                $e = wu_kind_lehrer_ermitteln($cfg, $pdo, $sz['rest'],
                    $sid, $kind, (string)$ref['von'], (string)$ref['bis'],
                    (int)($sp['klausuren_werten'] ?? 1) === 1);
                $ermittelt = $e['anzahl'];
                $fehlendeStammdaten = $e['uebersprungen'];
            } catch (Throwable $e) {
                // Ermittlung darf die Ansicht nicht scheitern lassen
                error_log('sprechtag: Auto-Ermittlung fehlgeschlagen: ' . $e->getMessage());
            }
        }
    }

    // Klassenleitung (E10, Zug 3): über die WebUntis-Sitzung der Eltern,
    // einmal je Anmeldung, und nur, wo sie auch ohne Unterricht buchbar
    // ist. Gemessen ist nur die Eltern-Sicht – volljährige Schüler und
    // Verwaltung bekommen keine Hervorhebung.
    $klassenleitung = [];
    if ($u['rolle'] === 'eltern' && slot_alle_teilnehmenden_buchbar((string)$sprechtag['phase'])) {
        $klassenleitung = kl_lehrer_ids($pdo, kl_aus_sitzung($kind, $holeSitzung));
    }

    $liste = bu_buchbare_lehrer($pdo, $sid, $kind, (string)$sprechtag['phase'],
        (string)$u['rolle'], trim((string)($_GET['jahrgang'] ?? '')), $klassenleitung);
    json_ok($liste + ['automatisch_ermittelt' => $ermittelt,
                      'ohne_stammsatz' => $fehlendeStammdaten,
                      'sitzung' => $sitzungFehlt,
                      'sitzung_meldung' => wu_sitzung_meldung($sitzungFehlt)]);
}

// ============================================================
// POST /api/lehrer-ermitteln  {sprechtag_id, kind_id[, benutzername, passwort]}
// Füllt kind_lehrer_cache über den Referenzzeitraum.
//
// Über die WebUntis-Sitzung der angemeldeten Person (E17) – keine
// Zugangsdaten, weder hinterlegte noch eingetippte. Ist die Sitzung nicht
// nutzbar: 409 mit 'sitzung' (abgelaufen / nicht_erreichbar / kaputt).
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

    $sz = wu_sitzung($cfg);
    if ($sz['rest'] === null) json_sitzung_fehlt($sz);
    $rest = $sz['rest'];
    try {
        $rest->setzeTimeout(20);
        ignore_user_abort(true);
        set_time_limit(0);
        $e = wu_kind_lehrer_ermitteln($cfg, $pdo, $rest, $sid, $kind,
            (string)$ref['von'], (string)$ref['bis'],
            (int)($s['klausuren_werten'] ?? 1) === 1);
        $anzahl = $e['anzahl'];
        $fehlend = $e['uebersprungen'];
    } catch (RuntimeException $e) {
        json_err('Ermittlung fehlgeschlagen: ' . $e->getMessage(), 502);
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
                b.gebucht_von, b.kommentar,
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

    // Dynamische Pausen (Variante 1): freie Slots nach x zusammenhängend
    // belegten werden zu Pausen. Bei festem Modus bleibt das Raster unverändert.
    $belegtSet = [];
    foreach ($belegt as $t => $_) { $belegtSet[$t] = true; }
    $raster = slot_pausen_anwenden($raster, $belegtSet,
        (int)($s['pause_dynamisch'] ?? 0) === 1,
        (int)($s['pause_nach_terminen'] ?? 0));

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
                $eintrag['kommentar']   = (string)($b['kommentar'] ?? '');
            }
        }
        $ausgabe[] = $eintrag;
    }
    // Halbtags-Kennzeichen der Lehrkraft für die Selbstbedienung.
    $stH = $pdo->prepare('SELECT halbtags FROM lehrer WHERE id = ?');
    $stH->execute([$lid]);
    $halbtags = (int)($stH->fetchColumn() ?: 0);

    json_ok(['raster' => $ausgabe,
        'lehrer' => ['id' => $lid, 'halbtags' => $halbtags,
            'anwesend_von' => $fenster['anwesend_von'],
            'anwesend_bis' => $fenster['anwesend_bis']],
        'sprechtag' => [
            'id' => (int)$s['id'], 'phase' => $s['phase'], 'datum' => $s['datum'],
            'beginn' => $s['beginn'], 'ende' => $s['ende']]]);
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
        $kommentar = kuerze(trim((string)($body['kommentar'] ?? '')), 280);
        // Stellvertretend buchen darf man nur bei SICH SELBST – auch ein
        // Admin nicht in fremdem Namen. Ein optional übergebenes lehrer_id
        // muss daher mit dem eigenen Stammsatz übereinstimmen.
        $lid = (int)($u['lehrer_id'] ?? 0);
        if (isset($body['lehrer_id']) && (int)$body['lehrer_id'] !== $lid) {
            json_err('Es kann nur bei der eigenen Lehrkraft stellvertretend '
                . 'gebucht werden, nicht im Namen einer anderen.', 403);
        }

        if ($lid <= 0) {
            json_err('Diesem Konto ist keine Lehrkraft zugeordnet. Bitte die '
                . 'Administration bitten, die Stammdaten zu synchronisieren.', 409);
        }
        if ($kind <= 0) json_err('Bitte ein Kind auswählen');
        if (!preg_match('/^\d{2}:\d{2}$/', $slot)) {
            json_err('Bitte einen freien Zeitpunkt auswählen');
        }
        $s = bu_sprechtag($pdo, $sid);

        // Elternkonto für die BUCHUNG selbst (Meine Termine, Kalender,
        // Fall A, spätere Absage) über die Sitzung der Lehrkraft – kein
        // Dienstkonto mehr (E17). Ist die Sitzung nicht nutzbar, wird NICHT
        // gebucht; die Antwort sagt warum, die Oberfläche bietet „Anmelden
        // und buchen“ an. Die Bestätigung geht über PARENTS an alle.
        $sz = wu_sitzung($cfg);
        if ($sz['rest'] === null) json_sitzung_fehlt($sz);
        $aufl = mit_eltern_ids_ermitteln($pdo, $kind, $sz['rest']);
        if ($aufl['ids'] === []) {
            json_err('Zu diesem Kind ließ sich kein Elternkonto ermitteln. '
                . 'Steht das Kind in der Schülerliste? Ersatzweise bleibt die '
                . 'Einladung, mit der die Eltern selbst buchen.', 409);
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
        // Dynamische Pausen: aktuelle Buchungen laden und Kandidaten auflösen,
        // damit eine gerade wirksam gewordene Pause nicht buchbar ist.
        if ((int)($s['pause_dynamisch'] ?? 0) === 1) {
            $stB = $pdo->prepare('SELECT slot_beginn FROM buchungen
                                  WHERE sprechtag_id = ? AND lehrer_id = ?');
            $stB->execute([$sid, $lid]);
            $belegtSet = [];
            foreach ($stB->fetchAll() as $b) {
                $belegtSet[substr((string)$b['slot_beginn'], 0, 5)] = true;
            }
            $raster = slot_pausen_anwenden($raster, $belegtSet, true,
                (int)($s['pause_nach_terminen'] ?? 0));
        }
        $imRaster = false;
        foreach ($raster as $z) {
            if ($z['typ'] === 'slot' && $z['beginn'] === $slot) { $imRaster = true; break; }
        }
        if (!$imRaster) json_err('Dieser Zeitpunkt gehört nicht zum Raster.');

        // ---- Doppelbuchung verhindern (zwei Fälle) -----------------------
        // Beides in einer Transaktion mit Zeilensperre, damit zwei parallele
        // Buchungen sich nicht gegenseitig überholen (Race Condition).
        $pdo->beginTransaction();
        try {
            // Fall A: Ist dieses Elternteil zu DIESER Uhrzeit schon bei einer
            // (anderen) Lehrkraft gebucht? Ein Elternteil kann nicht an zwei
            // Orten gleichzeitig sein. Der UNIQUE-Key deckt das NICHT ab, weil
            // er nur (sprechtag, lehrer, slot) sperrt – hier geht es über
            // Lehrergrenzen hinweg.
            $stKoll = $pdo->prepare(
                'SELECT b.id, l.kuerzel FROM buchungen b
                 JOIN lehrer l ON l.id = b.lehrer_id
                 WHERE b.sprechtag_id = ? AND b.eltern_user_id = ?
                   AND b.slot_beginn = ?
                 FOR UPDATE');
            $stKoll->execute([$sid, $elternUserId, $slot . ':00']);
            $koll = $stKoll->fetch();
            if ($koll !== false) {
                $pdo->rollBack();
                json_err('Dieses Elternteil ist um ' . $slot . ' Uhr bereits '
                    . 'bei ' . ($koll['kuerzel'] ?? 'einer anderen Lehrkraft')
                    . ' gebucht. Bitte einen anderen Zeitpunkt wählen.', 409);
            }

            // Fall B: Ist dieses Kind bei DIESER Lehrkraft schon gebucht?
            // Ein zweites Gespräch über dasselbe Kind bei derselben Lehrkraft
            // ergibt keinen Sinn.
            $stKind = $pdo->prepare(
                'SELECT slot_beginn FROM buchungen
                 WHERE sprechtag_id = ? AND lehrer_id = ? AND schueler_id = ?
                 FOR UPDATE');
            $stKind->execute([$sid, $lid, $kind]);
            $vorhanden = $stKind->fetch();
            if ($vorhanden !== false) {
                $pdo->rollBack();
                json_err('Für dieses Kind besteht bei dieser Lehrkraft bereits '
                    . 'ein Termin um ' . substr((string)$vorhanden['slot_beginn'], 0, 5)
                    . ' Uhr.', 409);
            }

            // Schreiben – der UNIQUE KEY sperrt den Slot zusätzlich ab.
            $pdo->prepare('INSERT INTO buchungen
                (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id,
                 kommentar, phase, gebucht_von)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$sid, $lid, $slot . ':00', $elternUserId, $kind, $kommentar,
                    (string)$s['phase'] === 'phase1' ? 'phase1' : 'phase2',
                    $u['rolle']]);
            $neueId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int)$e->errorInfo[1] === 1062) {
                json_err('Dieser Termin ist bereits vergeben.', 409);
            }
            throw $e;
        }

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
            ]], marke_schulname($pdo));
            // EINE Mitteilung an alle Erziehungsberechtigten des Kindes
            // (PARENTS, E17) – über dieselbe Sitzung wie die Ermittlung.
            $mitteilung = mit_einreihen_und_senden($pdo, $sid, 0, 'bestaetigung',
                $t['betreff'], $t['text'], $kind, $lid, $sz, 'eltern');
        } catch (PDOException $e) {
            error_log('sprechtag: Bestätigung (stellvertretend) nicht vorgemerkt: '
                . $e->getMessage());
        }

        json_ok(['ok' => true, 'id' => $neueId,
            'kind_name'     => $kindName,
            'mitteilung'    => $mitteilung,
            'hinweis' => 'Termin um ' . $slot . ' Uhr für ' . ($kindName ?: 'das Kind')
                . ' eingetragen. ' . (($mitteilung['status'] ?? '') === 'gesendet'
                    ? 'Die Erziehungsberechtigten wurden benachrichtigt.'
                    : 'Die Bestätigung ist gespeichert, aber noch nicht verschickt'
                        . (($mitteilung['grund'] ?? '') !== '' ? ': ' . $mitteilung['grund'] : '.'))], 201);
    }

    // ---- POST: buchen ----
    if ($methode === 'POST' && !isset($seg[1])) {
        $sid  = (int)($body['sprechtag_id'] ?? 0);
        $lid  = (int)($body['lehrer_id'] ?? 0);
        $kind = (int)($body['schueler_id'] ?? 0);
        $slot = substr(trim((string)($body['slot_beginn'] ?? '')), 0, 5);
        $kommentar = kuerze(trim((string)($body['kommentar'] ?? '')), 280);
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
            // Nur bei der EIGENEN Lehrkraft – auch ein Admin nicht in fremdem
            // Namen. Der übergebene lehrer_id muss dem eigenen Stammsatz gleichen.
            if ($lid !== (int)($u['lehrer_id'] ?? 0)) {
                json_err('Es kann nur bei der eigenen Lehrkraft stellvertretend '
                    . 'gebucht werden, nicht im Namen einer anderen.', 403);
            }
        } else {
            if (!auth_kind_erlaubt($u, $kind)) {
                json_err('Für dieses Kind besteht keine Berechtigung', 403);
            }
            // Volljährige Schüler nur in zugelassener Gruppe (E15) – dieselbe
            // Entscheidung wie bei den Kacheln.
            $sperre = bu_buchen_gesperrt($u, bu_zugelassene_gruppen($pdo));
            if ($sperre !== null) json_err(bu_sperre_text($sperre), 403);
            if ($elternUserId === null) json_err('Konto unvollständig – bitte neu anmelden', 401);
        }

        // Regelprüfung
        $fenster = bu_lehrer_fenster($pdo, $sid, $lid);
        if ((int)$fenster['teilnahme'] !== 1) {
            json_err('Diese Lehrkraft nimmt am Sprechtag nicht teil');
        }
        $raster = slot_raster($s, $fenster['anwesend_von'], $fenster['anwesend_bis']);
        if ((int)($s['pause_dynamisch'] ?? 0) === 1) {
            $stB = $pdo->prepare('SELECT slot_beginn FROM buchungen
                                  WHERE sprechtag_id = ? AND lehrer_id = ?');
            $stB->execute([$sid, $lid]);
            $belegtSet = [];
            foreach ($stB->fetchAll() as $b) {
                $belegtSet[substr((string)$b['slot_beginn'], 0, 5)] = true;
            }
            $raster = slot_pausen_anwenden($raster, $belegtSet, true,
                (int)($s['pause_nach_terminen'] ?? 0));
        }
        $imRaster = false;
        foreach ($raster as $z) {
            if ($z['typ'] === 'slot' && $z['beginn'] === $slot) { $imRaster = true; break; }
        }

        // Ist der gewünschte Slot bei DIESER Lehrkraft aktuell frei? (Diese
        // Berechnung fehlte – dadurch wurde $frei nie gesetzt und jede Buchung
        // fälschlich mit „bereits vergeben" abgelehnt.)
        $stFrei = $pdo->prepare('SELECT COUNT(*) FROM buchungen
                                 WHERE sprechtag_id = ? AND lehrer_id = ? AND slot_beginn = ?');
        $stFrei->execute([$sid, $lid, $slot . ':00']);
        $frei = (int)$stFrei->fetchColumn() === 0;

        $st = $pdo->prepare('SELECT COUNT(*) FROM buchungen
                             WHERE sprechtag_id = ? AND eltern_user_id = ?');
        $st->execute([$sid, $elternUserId]);
        $anzahl = (int)$st->fetchColumn();

        $eingeladen = bu_eingeladen($pdo, $sid, $kind, $lid);

        $pruefung = slot_buchung_erlaubt([
            'phase'          => (string)$s['phase'],
            'rolle'          => $rolle,
            'eingeladen'     => $eingeladen,
            'darf_lehrkraft' => bu_lehrer_erlaubt($pdo, $sid, $kind, $lid,
                                    (string)($body['jahrgang'] ?? ''), (string)$s['phase']),
            'slot_frei'      => $frei,
            'slot_im_raster' => $imRaster,
            'anzahl_termine' => $anzahl,
            'max_termine'    => (int)$s['max_termine_pro_eltern'],
        ]);
        if (!$pruefung['ok']) json_err($pruefung['grund'], 409);

        // Schreiben – der UNIQUE KEY entscheidet bei gleichzeitigen Anfragen.
        // Zusätzlich prüfen wir in derselben Transaktion, ob dieses Elternteil
        // zur selben Uhrzeit schon bei einer ANDEREN Lehrkraft gebucht ist –
        // das deckt der UNIQUE-Key nicht ab, ein Elternteil kann aber nicht an
        // zwei Orten gleichzeitig sein.
        $pdo->beginTransaction();
        try {
            $stKoll = $pdo->prepare(
                'SELECT l.kuerzel FROM buchungen b
                 JOIN lehrer l ON l.id = b.lehrer_id
                 WHERE b.sprechtag_id = ? AND b.eltern_user_id = ?
                   AND b.slot_beginn = ?
                 FOR UPDATE');
            $stKoll->execute([$sid, $elternUserId, $slot . ':00']);
            $koll = $stKoll->fetch();
            if ($koll !== false) {
                $pdo->rollBack();
                json_err('Sie haben um ' . $slot . ' Uhr bereits einen Termin bei '
                    . ($koll['kuerzel'] ?? 'einer anderen Lehrkraft')
                    . '. Bitte einen anderen Zeitpunkt wählen.', 409);
            }

            $pdo->prepare('INSERT INTO buchungen
                (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id,
                 kommentar, phase, gebucht_von)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$sid, $lid, $slot . ':00', $elternUserId, $kind, $kommentar,
                    (string)$s['phase'] === 'phase1' ? 'phase1' : 'phase2', $rolle]);
            // ID sofort sichern: nachfolgende Statements überschreiben lastInsertId()
            $neueId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int)$e->errorInfo[1] === 1062) {
                json_err('Dieser Termin wurde soeben von jemand anderem gebucht', 409);
            }
            throw $e;
        }

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
                $st->fetchAll(), marke_schulname($pdo));
            // Ältere offene Bestätigungen ersetzen – es gilt der aktuelle Stand
            $pdo->prepare("DELETE FROM mitteilungen WHERE sprechtag_id = ?
                           AND empfaenger_user_id = ? AND anlass = 'bestaetigung'
                           AND status = 'offen'")->execute([$sid, $elternUserId]);
            // Über die Sitzung der buchenden Eltern (Stelle 1 der
            // Bestandsaufnahme – nie über ein Dienstkonto). Klappt es nicht,
            // bleibt sie offen. Ohne Lehrkraft (null): Die Bestätigung nennt
            // ALLE Termine der Eltern, nicht nur einen – nachsenden darf jede
            // Lehrkraft mit einem dieser Termine (bisherige Regel) und die
            // Verwaltung; im Hinweis nach der Anmeldung steht sie bei der
            // Verwaltung.
            mit_einreihen_und_senden($pdo, $sid, (int)$elternUserId,
                'bestaetigung', $t['betreff'], $t['text'], $kind, null,
                wu_sitzung($cfg));
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
                // An ALLE Erziehungsberechtigten des Kindes (PARENTS, v0.9.73),
                // über die Sitzung der Person, die absagt (E17). Ist sie
                // abgelaufen, bleibt die Absage stehen – die Antwort sagt
                // es, und nach der Neuanmeldung geht sie mit einem Klick raus.
                $mitteilung = mit_einreihen_und_senden($pdo,
                    (int)$b['sprechtag_id'], (int)$b['eltern_user_id'],
                    'absage', $t['betreff'], $t['text'],
                    (int)$b['schueler_id'], (int)$b['lehrer_id'], wu_sitzung($cfg),
                    mit_absage_art((int)$b['schueler_id']));
            } catch (PDOException $e) {
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
        json_ok(['einladungen' => bu_einladende_lehrer($pdo, $sid, $kinder)]);
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
        // EINE Mitteilung an alle Erziehungsberechtigten des Kindes über
        // recipientOption PARENTS (E16, E17) – ohne Elternkonten zu suchen,
        // über die Sitzung der einladenden Person. Ist sie abgelaufen, bleibt
        // die Einladung gespeichert UND die Mitteilung steht in der
        // Warteschlange; die Antwort sagt es.
        $mitteilung = null;
        try {
            $stS = $pdo->prepare('SELECT name, datum FROM sprechtage WHERE id = ?');
            $stS->execute([$sid]);
            $sp = $stS->fetch() ?: ['name' => 'Elternsprechtag', 'datum' => ''];

            $stL = $pdo->prepare('SELECT kuerzel, name FROM lehrer WHERE id = ?');
            $stL->execute([$lid]);
            $le = $stL->fetch() ?: [];
            $lehrkraft = (string)($le['name'] ?: ($le['kuerzel'] ?? 'die Lehrkraft'));

            $stK = $pdo->prepare('SELECT vorname, nachname FROM schueler WHERE webuntis_id = ? LIMIT 1');
            $stK->execute([$kind]);
            $kd = $stK->fetch() ?: [];
            $kindName = trim(((string)($kd['vorname'] ?? '')) . ' ' . ((string)($kd['nachname'] ?? '')));

            $t = mit_text_einladung((string)$sp['name'], (string)$sp['datum'],
                $lehrkraft, $kindName,
                substr((string)($body['hinweis'] ?? ''), 0, 500));
            $mitteilung = mit_einreihen_und_senden($pdo, $sid, 0, 'einladung',
                $t['betreff'], $t['text'], $kind, $lid, wu_sitzung($cfg), 'eltern');
        } catch (PDOException $e) {
            error_log('sprechtag: Einladungs-Mitteilung nicht vorgemerkt: '
                . $e->getMessage());
        }

        json_ok(['ok' => true,
            'mitteilung' => $mitteilung,
            'hinweis' => $mitteilung === null
                ? 'Einladung angelegt. Die Mitteilung an die Eltern ließ sich nicht '
                    . 'vormerken – bitte die Eltern auf anderem Weg informieren.'
                : (($mitteilung['status'] ?? '') === 'gesendet'
                    ? 'Einladung angelegt, die Erziehungsberechtigten wurden benachrichtigt.'
                    : 'Einladung angelegt. Die Mitteilung ist gespeichert, aber noch '
                        . 'nicht verschickt: ' . (string)($mitteilung['grund'] ?? ''))], 201);
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
