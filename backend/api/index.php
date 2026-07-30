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
require_once __DIR__ . '/einstellungen.php';
require_once __DIR__ . '/kalender.php';

$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pfad    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$seg     = array_values(array_filter(explode('/', trim($pfad, '/'))));
array_shift($seg);   // 'api' entfernen
$body    = in_array($methode, ['POST', 'PATCH', 'PUT'], true) ? body_json() : [];

// ---- GET /api/health ---------------------------------------
if ($methode === 'GET' && ($seg[0] ?? '') === 'health') {
    $db = 'fehlt';
    try { db($cfg)->query('SELECT 1'); $db = 'ok'; } catch (Throwable $e) { }
    json_ok(['app' => 'sprechtag', 'version' => '0.9.37', 'db' => $db]);
}

// ---- GET /api/anzeige : öffentliche Raumübersicht (Signage) --------
// Bewusst OHNE Login und OHNE persönliche oder Buchungsdaten: nur die
// neutrale Raumzuordnung des aktiven Sprechtags (Kürzel, Name, Raum,
// Anwesenheitszeit). Diese Information hängt am Sprechtag ohnehin öffentlich
// aus. Keine Eltern, keine Slots, kein frei/belegt.
if ($methode === 'GET' && ($seg[0] ?? '') === 'anzeige') {
    $pdo = db($cfg);
    $s = $pdo->query(
        "SELECT id, name, datum, beginn, ende, phase FROM sprechtage
         WHERE phase IN ('phase1','phase2')
         ORDER BY datum ASC LIMIT 1")->fetch();
    if (!$s) {
        json_ok(['aktiv' => false, 'sprechtag' => null, 'lehrer' => []]);
    }
    // Anzeige-Einstellungen sammeln (Standardwerte, dann überschreiben).
    $einst = ['anzeige_sortierung' => 'raum',
              'anzeige_kacheln' => 'auto',
              'anzeige_intervall' => '10'];
    $stE = $pdo->query("SELECT schluessel, wert FROM einstellungen
                        WHERE schluessel LIKE 'anzeige_%'");
    foreach ($stE->fetchAll() as $r) {
        $einst[(string)$r['schluessel']] = (string)$r['wert'];
    }
    $sortWahl = $einst['anzeige_sortierung'] === 'kuerzel' ? 'kuerzel' : 'raum';
    $orderBy = $sortWahl === 'kuerzel'
        ? 'l.kuerzel'
        : 'r.kuerzel IS NULL, r.kuerzel, l.kuerzel';
    $st = $pdo->prepare(
        'SELECT l.kuerzel, l.name, l.halbtags,
                sl.anwesend_von, sl.anwesend_bis,
                r.kuerzel AS raum_kuerzel, r.name AS raum_name
         FROM sprechtag_lehrer sl
         JOIN lehrer l ON l.id = sl.lehrer_id
         LEFT JOIN raeume r ON r.id = sl.raum_id
         WHERE sl.sprechtag_id = ? AND sl.teilnahme = 1
         ORDER BY ' . $orderBy);
    $st->execute([(int)$s['id']]);
    // Kacheln: 'auto' oder eine positive Zahl.
    $kacheln = $einst['anzeige_kacheln'];
    if ($kacheln !== 'auto') {
        $kacheln = (string)max(1, min(60, (int)$kacheln));
    }
    $intervall = max(3, min(60, (int)$einst['anzeige_intervall'] ?: 10));
    json_ok([
        'aktiv' => true,
        'sprechtag' => [
            'name' => $s['name'], 'datum' => $s['datum'],
            'beginn' => substr((string)$s['beginn'], 0, 5),
            'ende' => substr((string)$s['ende'], 0, 5),
        ],
        'sortierung' => $sortWahl,
        'kacheln' => $kacheln,        // 'auto' oder Zahl als String
        'intervall' => $intervall,    // Sekunden
        'lehrer' => $st->fetchAll(),
    ]);
}

// ---- GET /api/kalender/{token}.ics : öffentlicher iCal-Abo-Feed ----
// Der Token IST die Berechtigung (wie ein privater Kalender-Feed). Kein Login.
// Liefert ausschließlich die eigenen Termine des zugehörigen Elternkontos.
if ($methode === 'GET' && ($seg[0] ?? '') === 'kalender' && isset($seg[1])) {
    $token = preg_replace('/\.ics$/', '', (string)$seg[1]);
    if (!preg_match('/^[a-f0-9]{48}$/', (string)$token)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nicht gefunden.';
        exit;
    }
    $pdo = db($cfg);
    // Lehrkraft-Feed? (Token gehört zu einer Lehrkraft -> Kind-zentrierte Events)
    $lehrerId = kal_lehrer_aus_token($pdo, $token);
    if ($lehrerId !== null) {
        $buchungen = kal_lehrer_buchungen($pdo, $lehrerId);
        $marke = $pdo->query("SELECT wert FROM einstellungen
                              WHERE schluessel = 'marke_titel'")->fetchColumn();
        $ics = kal_kalender(kal_vevents_lehrer($buchungen),
            ($marke ?: 'Sprechtag') . ' – meine Termine');
        kal_ausliefern($ics, 'sprechtag-lehrkraft.ics');
    }
    $st = $pdo->prepare('SELECT eltern_user_id FROM kalender_abo WHERE token = ?');
    $st->execute([$token]);
    $uid = $st->fetchColumn();
    if ($uid === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nicht gefunden.';
        exit;
    }
    $buchungen = kal_buchungen_laden($pdo, (int)$uid);
    $marke = $pdo->query("SELECT wert FROM einstellungen
                          WHERE schluessel = 'marke_titel'")->fetchColumn();
    $ics = kal_kalender(kal_vevents($buchungen), ($marke ?: 'Sprechtag'));
    kal_ausliefern($ics, 'sprechtag.ics');
}

// ---- /api/einstellungen (Branding) -------------------------
// Eigene Guards je Route (GET öffentlich, POST/DELETE admin).
if (marke_route($seg, $methode, $body, $cfg)) {
    // marke_route hat bereits geantwortet und beendet.
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

        // Optionales Login-Protokoll (Admin-Feature). Fehlschläge werden oben
        // aus Sicherheitsgründen (Brute-Force-Bremse) immer festgehalten;
        // ERFOLGE nur, wenn die Schule das Protokoll aktiviert und Erfolge
        // ausdrücklich einbezogen hat.
        $logAktiv = (int)marke_wert($pdo, 'login_log_aktiv', '0') === 1;
        $logErfolge = (int)marke_wert($pdo, 'login_log_erfolge', '0') === 1;
        if ($logAktiv && $logErfolge) {
            $pdo->prepare('INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, ip)
                           VALUES (?, 1, ?, ?)')
                ->execute([$benutzer, $daten['rolle'], $_SERVER['REMOTE_ADDR'] ?? '']);
        }
        // Aufbewahrung begrenzen: Einträge älter als login_log_tage entfernen
        // (Bereinigung bei Login, kein Cron nötig).
        $tage = max(1, min(365, (int)marke_wert($pdo, 'login_log_tage', '30')));
        $pdo->prepare('DELETE FROM login_log WHERE zeitpunkt < NOW() - INTERVAL ? DAY')
            ->execute([$tage]);

        json_ok(['angemeldet' => true] + auth_user());
    }
}

// ============================================================
// LOGIN-PROTOKOLL (optionales Admin-Feature)
// ============================================================
if (($seg[0] ?? '') === 'login-log') {
    auth_require_admin();
    $pdo = db($cfg);

    // Einstellungen lesen
    if ($methode === 'GET' && ($seg[1] ?? '') === 'einstellungen') {
        json_ok([
            'aktiv'   => (int)marke_wert($pdo, 'login_log_aktiv', '0'),
            'erfolge' => (int)marke_wert($pdo, 'login_log_erfolge', '0'),
            'tage'    => (int)marke_wert($pdo, 'login_log_tage', '30'),
        ]);
    }

    // Einstellungen speichern
    if ($methode === 'POST' && ($seg[1] ?? '') === 'einstellungen') {
        $aktiv   = !empty($body['aktiv']) ? '1' : '0';
        $erfolge = !empty($body['erfolge']) ? '1' : '0';
        $tage    = (string)max(1, min(365, (int)($body['tage'] ?? 30)));
        marke_schreiben($pdo, 'login_log_aktiv', $aktiv);
        marke_schreiben($pdo, 'login_log_erfolge', $erfolge);
        marke_schreiben($pdo, 'login_log_tage', $tage);
        json_ok(['ok' => true]);
    }

    // Protokoll lesen (nur wenn aktiv)
    if ($methode === 'GET' && !isset($seg[1])) {
        if ((int)marke_wert($pdo, 'login_log_aktiv', '0') !== 1) {
            json_ok(['aktiv' => false, 'eintraege' => []]);
        }
        $limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));
        // Optionaler Filter nach Benutzername
        $filter = trim((string)($_GET['benutzer'] ?? ''));
        if ($filter !== '') {
            $st = $pdo->prepare(
                'SELECT webuntis_benutzer, erfolgreich, grund, zeitpunkt
                 FROM login_log WHERE webuntis_benutzer LIKE ?
                 ORDER BY zeitpunkt DESC LIMIT ?');
            $st->bindValue(1, '%' . $filter . '%');
            $st->bindValue(2, $limit, PDO::PARAM_INT);
            $st->execute();
        } else {
            $st = $pdo->prepare(
                'SELECT webuntis_benutzer, erfolgreich, grund, zeitpunkt
                 FROM login_log ORDER BY zeitpunkt DESC LIMIT ?');
            $st->bindValue(1, $limit, PDO::PARAM_INT);
            $st->execute();
        }
        json_ok(['aktiv' => true, 'eintraege' => $st->fetchAll()]);
    }

    // Protokoll leeren
    if ($methode === 'DELETE' && !isset($seg[1])) {
        $pdo->exec('DELETE FROM login_log');
        json_ok(['ok' => true]);
    }

    json_err('Methode nicht unterstützt.', 405);
}

// ============================================================
// LEHRKRAFT-EXPORT (iCal-Abo, iCal-Datei, druckbare Tischvorlage)
// ============================================================
if (($seg[0] ?? '') === 'lehrer-kalender') {
    $u = auth_require();                       // Guard VOR db()
    if ($u === null) json_err('Bitte anmelden', 401);
    if ($u['lehrer_id'] === null) json_err('Nur für Lehrkräfte', 403);
    $pdo = db($cfg);
    if ($methode === 'POST' && ($seg[1] ?? '') === 'neu') {
        $token = kal_lehrer_token_neu($pdo, (int)$u['lehrer_id']);
        json_ok(['url' => kal_basis_url() . '/api/kalender/' . $token . '.ics']);
    }
    if ($methode === 'GET' && !isset($seg[1])) {
        $token = kal_lehrer_token_holen($pdo, (int)$u['lehrer_id']);
        json_ok(['url' => kal_basis_url() . '/api/kalender/' . $token . '.ics']);
    }
    json_err('Methode nicht unterstützt.', 405);
}

// ---- GET /api/lehrer-termine/{sprechtag}.ics : Tagesliste als .ics ----
if ($methode === 'GET' && ($seg[0] ?? '') === 'lehrer-termine' && isset($seg[1])) {
    $u = auth_require();
    if ($u === null) json_err('Bitte anmelden', 401);
    if ($u['lehrer_id'] === null) json_err('Nur für Lehrkräfte', 403);
    $sid = (int)preg_replace('/\.ics$/', '', (string)$seg[1]);
    $pdo = db($cfg);
    $buchungen = kal_lehrer_buchungen($pdo, (int)$u['lehrer_id'], $sid);
    $ics = kal_kalender(kal_vevents_lehrer($buchungen), 'Sprechtag – meine Termine');
    kal_ausliefern($ics, 'meine-termine.ics');
}

// ---- GET /api/lehrer-tischvorlage/{sprechtag} : druckbare HTML-Liste ----
if ($methode === 'GET' && ($seg[0] ?? '') === 'lehrer-tischvorlage' && isset($seg[1])) {
    $u = auth_require();
    if ($u === null) json_err('Bitte anmelden', 401);
    if ($u['lehrer_id'] === null) json_err('Nur für Lehrkräfte', 403);
    $sid = (int)$seg[1];
    $lid = (int)$u['lehrer_id'];
    $pdo = db($cfg);
    // Sprechtag laden (bu_sprechtag ist an dieser Stelle noch nicht geladen).
    $stS = $pdo->prepare('SELECT * FROM sprechtage WHERE id = ?');
    $stS->execute([$sid]);
    $s = $stS->fetch();
    if (!$s) json_err('Sprechtag nicht gefunden', 404);
    // Anwesenheitsfenster der Lehrkraft (NULL = ganzer Rahmen).
    $stF = $pdo->prepare('SELECT anwesend_von, anwesend_bis
                          FROM sprechtag_lehrer WHERE sprechtag_id = ? AND lehrer_id = ?');
    $stF->execute([$sid, $lid]);
    $fenster = $stF->fetch() ?: ['anwesend_von' => null, 'anwesend_bis' => null];
    $raster = slot_raster($s, $fenster['anwesend_von'], $fenster['anwesend_bis']);
    if ((int)($s['pause_dynamisch'] ?? 0) === 1) {
        $stB = $pdo->prepare('SELECT slot_beginn FROM buchungen
                              WHERE sprechtag_id = ? AND lehrer_id = ?');
        $stB->execute([$sid, $lid]);
        $bset = [];
        foreach ($stB->fetchAll() as $b) { $bset[substr((string)$b['slot_beginn'], 0, 5)] = true; }
        $raster = slot_pausen_anwenden($raster, $bset, true, (int)($s['pause_nach_terminen'] ?? 0));
    }
    // Buchungen (Kindname/Klasse) je Slot.
    $st = $pdo->prepare(
        'SELECT b.slot_beginn, b.kommentar,
                TRIM(CONCAT(COALESCE(s.nachname,""),
                     IF(s.vorname IS NULL OR s.vorname = "", "",
                        CONCAT(", ", s.vorname)))) AS kind_name, s.klasse AS kind_klasse
         FROM buchungen b LEFT JOIN schueler s ON s.webuntis_id = b.schueler_id
         WHERE b.sprechtag_id = ? AND b.lehrer_id = ?');
    $st->execute([$sid, $lid]);
    $belegt = [];
    foreach ($st->fetchAll() as $b) { $belegt[substr((string)$b['slot_beginn'], 0, 5)] = $b; }

    $zeilen = [];
    foreach ($raster as $z) {
        if (($z['typ'] ?? '') === 'pause') {
            $zeilen[] = ['slot_beginn' => $z['beginn'], 'kind_name' => 'Pause',
                         'kind_klasse' => '', 'kommentar' => ''];
            continue;
        }
        $b = $belegt[$z['beginn']] ?? null;
        $zeilen[] = ['slot_beginn' => $z['beginn'],
                     'kind_name' => $b['kind_name'] ?? '',
                     'kind_klasse' => $b['kind_klasse'] ?? '',
                     'kommentar' => $b['kommentar'] ?? ''];
    }
    // Kopfdaten
    $stR = $pdo->prepare('SELECT r.kuerzel FROM sprechtag_lehrer sl
                          LEFT JOIN raeume r ON r.id = sl.raum_id
                          WHERE sl.sprechtag_id = ? AND sl.lehrer_id = ?');
    $stR->execute([$sid, $lid]);
    $raum = (string)($stR->fetchColumn() ?: '');
    $kopf = [
        'lehrer' => (string)($u['name'] ?: $u['kuerzel']),
        'sprechtag' => (string)$s['name'],
        'datum' => (string)$s['datum'],
        'zeit' => substr((string)$s['beginn'], 0, 5) . '–' . substr((string)$s['ende'], 0, 5) . ' Uhr',
        'raum' => $raum,
    ];
    kal_html_ausliefern(kal_tischvorlage_html($kopf, $zeilen));
}

// ============================================================
// KALENDER-LINK (Abo-URL für Eltern)
// ============================================================
if (($seg[0] ?? '') === 'kalender-link') {
    $u = auth_require();                       // Guard VOR db()
    if ($u === null) json_err('Bitte anmelden', 401);
    if ($u['user_id'] === null) json_err('Nur mit Elternkonto verfügbar', 403);
    $pdo = db($cfg);

    // Neu erzeugen (alten Link ungültig machen)
    if ($methode === 'POST' && ($seg[1] ?? '') === 'neu') {
        $token = kal_token_neu($pdo, (int)$u['user_id']);
        json_ok(['url' => kal_basis_url() . '/api/kalender/' . $token . '.ics']);
    }
    if ($methode === 'GET' && !isset($seg[1])) {
        $token = kal_token_holen($pdo, (int)$u['user_id']);
        json_ok(['url' => kal_basis_url() . '/api/kalender/' . $token . '.ics']);
    }
    json_err('Methode nicht unterstützt.', 405);
}

// ---- GET /api/buchung/{id}.ics : einzelne Buchung als .ics-Datei ----
if ($methode === 'GET' && ($seg[0] ?? '') === 'buchung' && isset($seg[1])) {
    $u = auth_require();
    if ($u === null) json_err('Bitte anmelden', 401);
    if ($u['user_id'] === null) json_err('Nur mit Elternkonto verfügbar', 403);
    $bid = (int)preg_replace('/\.ics$/', '', (string)$seg[1]);
    if ($bid <= 0) json_err('Ungültige Buchung.');
    $pdo = db($cfg);
    // kal_buchungen_laden filtert bereits auf eltern_user_id -> nur eigene.
    $buchungen = kal_buchungen_laden($pdo, (int)$u['user_id'], $bid);
    if (!$buchungen) json_err('Buchung nicht gefunden.', 404);
    $ics = kal_kalender(kal_vevents($buchungen), 'Sprechtag-Termin');
    kal_ausliefern($ics, 'termin.ics');
}

// ============================================================
// STAMMDATEN
// ============================================================
if (($seg[0] ?? '') === 'stammdaten') {
    if ($methode === 'GET' && !isset($seg[1])) {
        auth_require();          // Guard VOR db(): 401 statt 503 bei DB-Ausfall
        $pdo = db($cfg);
        json_ok([
            'lehrer' => $pdo->query('SELECT id, webuntis_id, kuerzel, name, aktiv, halbtags
                FROM lehrer WHERE aktiv = 1 ORDER BY kuerzel')->fetchAll(),
            'raeume' => $pdo->query('SELECT id, webuntis_id, kuerzel, name
                FROM raeume WHERE aktiv = 1 ORDER BY kuerzel')->fetchAll(),
            'sonderrollen' => $pdo->query('SELECT id, bezeichnung
                FROM sonderrollen ORDER BY reihenfolge, bezeichnung')->fetchAll(),
        ]);
    }

    // ---- PATCH /api/stammdaten/lehrer/{id} : Halbtags-Kennzeichen ----
    if ($methode === 'PATCH' && ($seg[1] ?? '') === 'lehrer'
        && isset($seg[2]) && ctype_digit($seg[2])) {
        auth_require_admin();
        $pdo = db($cfg);
        $lid = (int)$seg[2];
        if (!array_key_exists('halbtags', $body)) {
            json_err('Feld halbtags fehlt.');
        }
        $pdo->prepare('UPDATE lehrer SET halbtags = ? WHERE id = ?')
            ->execute([(int)(bool)$body['halbtags'], $lid]);
        json_ok(['ok' => true, 'halbtags' => (int)(bool)$body['halbtags']]);
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
// ANZEIGE-EINSTELLUNGEN (Signage)
// ============================================================
if (($seg[0] ?? '') === 'anzeige-einstellungen') {
    auth_require_admin();       // Guard VOR db()
    $pdo = db($cfg);
    $lesen = function (PDO $pdo): array {
        $w = ['anzeige_sortierung' => 'raum', 'anzeige_kacheln' => 'auto',
              'anzeige_intervall' => '10'];
        foreach ($pdo->query("SELECT schluessel, wert FROM einstellungen
                              WHERE schluessel LIKE 'anzeige_%'")->fetchAll() as $r) {
            $w[(string)$r['schluessel']] = (string)$r['wert'];
        }
        return [
            'sortierung' => $w['anzeige_sortierung'] === 'kuerzel' ? 'kuerzel' : 'raum',
            'kacheln' => $w['anzeige_kacheln'],
            'intervall' => (int)($w['anzeige_intervall'] ?: 10),
        ];
    };
    if ($methode === 'GET') {
        json_ok($lesen($pdo));
    }
    if ($methode === 'POST') {
        $setzen = $pdo->prepare(
            'INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE wert = VALUES(wert)');
        if (isset($body['sortierung'])) {
            $v = (string)$body['sortierung'];
            if (!in_array($v, ['raum', 'kuerzel'], true)) {
                json_err("sortierung muss 'raum' oder 'kuerzel' sein.");
            }
            $setzen->execute(['anzeige_sortierung', $v]);
        }
        if (isset($body['kacheln'])) {
            $v = (string)$body['kacheln'];
            if ($v !== 'auto') {
                $n = (int)$v;
                if ($n < 1 || $n > 60) json_err('kacheln: 1–60 oder "auto".');
                $v = (string)$n;
            }
            $setzen->execute(['anzeige_kacheln', $v]);
        }
        if (isset($body['intervall'])) {
            $n = (int)$body['intervall'];
            if ($n < 3 || $n > 60) json_err('intervall: 3–60 Sekunden.');
            $setzen->execute(['anzeige_intervall', (string)$n]);
        }
        json_ok(['ok' => true] + $lesen($pdo));
    }
    json_err('Methode nicht unterstützt.', 405);
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

    // Lokaler Sprechtag-Fetch – bu_sprechtag() aus buchungen.php wird erst
    // am Dateiende geladen und steht hier noch nicht zur Verfügung.
    $sprechtagHolen = static function (PDO $pdo, int $id): array {
        $st = $pdo->prepare('SELECT * FROM sprechtage WHERE id = ?');
        $st->execute([$id]);
        $s = $st->fetch();
        if (!$s) json_err('Sprechtag nicht gefunden', 404);
        return $s;
    };

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
             pause_nach_terminen, pause_minuten, pause_dynamisch,
             referenz_von, referenz_bis)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                req($body, 'name'), $datum,
                (string)($body['beginn'] ?? '15:00'),
                (string)($body['ende'] ?? '19:00'),
                (int)($body['slot_minuten'] ?? 10),
                (int)($body['max_termine_pro_eltern'] ?? 6),
                (int)($body['pause_nach_terminen'] ?? 0),
                (int)($body['pause_minuten'] ?? 10),
                (int)(bool)($body['pause_dynamisch'] ?? 0),
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
            'pause_dynamisch', 'phase', 'referenz_von', 'referenz_bis',
            'klausuren_werten'];
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
             pause_nach_terminen, pause_minuten, pause_dynamisch, phase,
             referenz_von, referenz_bis)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "vorbereitung", ?, ?)')
            ->execute([
                (string)($body['name'] ?? ($alt['name'] . ' (Kopie)')), $datum,
                $alt['beginn'], $alt['ende'], $alt['slot_minuten'],
                $alt['max_termine_pro_eltern'], $alt['pause_nach_terminen'],
                $alt['pause_minuten'], (int)($alt['pause_dynamisch'] ?? 0),
                $ref['von'], $ref['bis'],
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
        // ---- PUT .../lehrer : alle Zeilen auf einmal speichern (admin) ----
        // Erwartet body.zeilen = [{lehrer_id, teilnahme, raum_id, haelfte|
        // anwesend_von/bis}, …]. In einer Transaktion, damit entweder alles
        // oder nichts gespeichert wird.
        if ($methode === 'PUT' && !isset($seg[3])) {
            auth_require_admin();
            $zeilen = $body['zeilen'] ?? null;
            if (!is_array($zeilen)) json_err('Feld zeilen (Array) fehlt.');
            $s = $sprechtagHolen($pdo, $sid);

            $stmt = $pdo->prepare('INSERT INTO sprechtag_lehrer
                (sprechtag_id, lehrer_id, anwesend_von, anwesend_bis, raum_id, teilnahme, bemerkung)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE anwesend_von = VALUES(anwesend_von),
                    anwesend_bis = VALUES(anwesend_bis), raum_id = VALUES(raum_id),
                    teilnahme = VALUES(teilnahme), bemerkung = VALUES(bemerkung)');

            $pdo->beginTransaction();
            try {
                $anzahl = 0;
                foreach ($zeilen as $z) {
                    $lid = (int)($z['lehrer_id'] ?? 0);
                    if ($lid <= 0) continue;
                    $von = ($z['anwesend_von'] ?? '') !== '' ? $z['anwesend_von'] : null;
                    $bis = ($z['anwesend_bis'] ?? '') !== '' ? $z['anwesend_bis'] : null;
                    if (isset($z['haelfte'])) {
                        $h = (string)$z['haelfte'];
                        if (!in_array($h, ['erste', 'zweite', 'ganz'], true)) {
                            throw new RuntimeException("Ungültige Hälfte bei Lehrkraft $lid");
                        }
                        $f = slot_haelfte_fenster($s, $h);
                        $von = $f['von']; $bis = $f['bis'];
                    }
                    foreach ([$von, $bis] as $w) {
                        if ($w !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$w)) {
                            throw new RuntimeException("Ungültige Zeit bei Lehrkraft $lid");
                        }
                    }
                    $stmt->execute([$sid, $lid, $von, $bis,
                        ($z['raum_id'] ?? '') !== '' ? (int)$z['raum_id'] : null,
                        isset($z['teilnahme']) ? (int)(bool)$z['teilnahme'] : 1,
                        substr((string)($z['bemerkung'] ?? ''), 0, 190)]);
                    $anzahl++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                json_err('Speichern abgebrochen: ' . $e->getMessage(), 400);
            }
            json_ok(['ok' => true, 'gespeichert' => $anzahl]);
        }

        if ($methode === 'PATCH' && isset($seg[3]) && ctype_digit($seg[3])) {
            $u = auth_require();
            $lid = (int)$seg[3];
            // Admins dürfen jede Lehrkraft pflegen; eine Lehrkraft darf ihren
            // EIGENEN Eintrag anpassen (z. B. die Hälfte selbst wählen).
            $eigene = ($u['rolle'] === 'lehrkraft'
                && (int)($u['lehrer_id'] ?? 0) === $lid);
            if ($u['rolle'] !== 'admin' && !$eigene) {
                json_err('Nur die Administration oder die Lehrkraft selbst darf '
                    . 'diesen Eintrag ändern.', 403);
            }

            // Kurzwahl „haelfte": erste/zweite/ganz -> Fenster berechnen.
            // Überschreibt anwesend_von/bis, damit niemand Zeiten tippen muss.
            $von = ($body['anwesend_von'] ?? '') !== '' ? $body['anwesend_von'] : null;
            $bis = ($body['anwesend_bis'] ?? '') !== '' ? $body['anwesend_bis'] : null;
            if (isset($body['haelfte'])) {
                $h = (string)$body['haelfte'];
                if (!in_array($h, ['erste', 'zweite', 'ganz'], true)) {
                    json_err("haelfte muss 'erste', 'zweite' oder 'ganz' sein");
                }
                $s = $sprechtagHolen($pdo, $sid);
                $f = slot_haelfte_fenster($s, $h);
                $von = $f['von'];
                $bis = $f['bis'];
            }
            foreach (['anwesend_von' => $von, 'anwesend_bis' => $bis] as $feld => $w) {
                if ($w !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$w)) {
                    json_err("$feld muss das Format HH:MM haben");
                }
            }

            // Eine Lehrkraft darf an ihrem eigenen Eintrag nur Fenster/Hälfte
            // ändern – nicht Raum, Teilnahme oder Bemerkung (das bleibt Admin).
            if ($eigene) {
                $pdo->prepare('INSERT INTO sprechtag_lehrer
                    (sprechtag_id, lehrer_id, anwesend_von, anwesend_bis, teilnahme)
                    VALUES (?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE anwesend_von = VALUES(anwesend_von),
                        anwesend_bis = VALUES(anwesend_bis)')
                    ->execute([$sid, $lid, $von, $bis]);
                json_ok(['ok' => true, 'anwesend_von' => $von, 'anwesend_bis' => $bis]);
            }

            $pdo->prepare('INSERT INTO sprechtag_lehrer
                (sprechtag_id, lehrer_id, anwesend_von, anwesend_bis, raum_id, teilnahme, bemerkung)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE anwesend_von = VALUES(anwesend_von),
                    anwesend_bis = VALUES(anwesend_bis), raum_id = VALUES(raum_id),
                    teilnahme = VALUES(teilnahme), bemerkung = VALUES(bemerkung)')
                ->execute([$sid, $lid, $von, $bis,
                    ($body['raum_id'] ?? '') !== '' ? (int)$body['raum_id'] : null,
                    isset($body['teilnahme']) ? (int)(bool)$body['teilnahme'] : 1,
                    substr((string)($body['bemerkung'] ?? ''), 0, 190)]);
            json_ok(['ok' => true, 'anwesend_von' => $von, 'anwesend_bis' => $bis]);
        }

        // ---- POST .../lehrer/{lid}/ausfall : Lehrkraft fällt aus ----
        // Setzt teilnahme=0, gibt alle gebuchten Termine dieser Lehrkraft
        // wieder frei und benachrichtigt die betroffenen Eltern, dass ihr
        // Termin entfällt. Für krankheitsbedingte Ausfälle.
        if ($methode === 'POST' && isset($seg[3]) && ctype_digit($seg[3])
            && ($seg[4] ?? '') === 'ausfall') {
            auth_require_admin();
            $lid = (int)$seg[3];
            $s = $sprechtagHolen($pdo, $sid);

            // Teilnahme abschalten (Eintrag ggf. anlegen).
            $pdo->prepare('INSERT INTO sprechtag_lehrer
                (sprechtag_id, lehrer_id, teilnahme) VALUES (?, ?, 0)
                ON DUPLICATE KEY UPDATE teilnahme = 0')
                ->execute([$sid, $lid]);

            // Betroffene Buchungen einsammeln, dann freigeben.
            $stB = $pdo->prepare(
                'SELECT id, slot_beginn, eltern_user_id, schueler_id
                 FROM buchungen WHERE sprechtag_id = ? AND lehrer_id = ?');
            $stB->execute([$sid, $lid]);
            $buchungen = $stB->fetchAll();

            $stL = $pdo->prepare('SELECT kuerzel, name FROM lehrer WHERE id = ?');
            $stL->execute([$lid]);
            $le = $stL->fetch() ?: [];
            $lehrkraft = (string)($le['name'] ?: ($le['kuerzel'] ?? 'die Lehrkraft'));
            $grund = substr((string)($body['nachricht'] ?? ''), 0, 500);

            $pdo->prepare('DELETE FROM buchungen WHERE sprechtag_id = ? AND lehrer_id = ?')
                ->execute([$sid, $lid]);

            // Betroffene Eltern benachrichtigen (Absage). Fehler beim
            // einzelnen Versand dürfen die Freigabe nicht rückgängig machen.
            $zugang = dk_lesen($cfg, $pdo);
            $benachrichtigt = 0;
            foreach ($buchungen as $b) {
                try {
                    $t = mit_text_absage((string)$s['name'], (string)$s['datum'],
                        (string)$b['slot_beginn'], $lehrkraft, $grund);
                    mit_einreihen_und_senden($cfg, $pdo, $sid,
                        (int)$b['eltern_user_id'], 'absage', $t['betreff'], $t['text'],
                        $zugang['benutzer'] ?? null, $zugang['passwort'] ?? null,
                        (int)$b['schueler_id']);
                    $benachrichtigt++;
                } catch (Throwable $e) {
                    error_log('sprechtag: Ausfall-Absage nicht vorgemerkt: '
                        . $e->getMessage());
                }
            }

            json_ok(['ok' => true,
                'freigegeben'   => count($buchungen),
                'benachrichtigt'=> $benachrichtigt,
                'hinweis' => count($buchungen) . ' Termin(e) freigegeben, '
                    . $benachrichtigt . ' Elternteil(e) benachrichtigt.']);
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
