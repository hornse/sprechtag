<?php
// ============================================================
// api/index.php – API-Router des Projekts "sprechtag"
// Paket 1: Gesundheitscheck + Sondierwerkzeug.
// Login/Rollen/Buchung folgen in den nächsten Paketen.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/sondierung.php';

$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pfad    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// ---- GET /api/health ---------------------------------------
if ($methode === 'GET' && $pfad === '/api/health') {
    $db = 'fehlt';
    try { db($cfg)->query('SELECT 1'); $db = 'ok'; } catch (Throwable $e) { /* Bericht unten */ }
    json_ok(['app' => 'sprechtag', 'version' => '0.1.0', 'db' => $db]);
}

// ---- POST /api/sondierung ----------------------------------
if ($methode === 'POST' && $pfad === '/api/sondierung') {
    if (($cfg['sondierung_freigeschaltet'] ?? false) !== true) {
        json_err('Sondierung ist in config.php abgeschaltet', 403);
    }

    $b        = body_json();
    $benutzer = req($b, 'benutzername');
    $passwort = req($b, 'passwort');
    $gruppen  = array_values(array_intersect(
        (array)($b['gruppen'] ?? []),
        ['basis', 'sprechtag', 'stundenplan', 'mitteilungen']
    ));
    if ($gruppen === []) $gruppen = ['basis'];
    $extraPfade = preg_split('/\r?\n/', (string)($b['extra_pfade'] ?? '')) ?: [];
    $schuelerId = trim((string)($b['schueler_id'] ?? ''));
    if ($schuelerId !== '' && !ctype_digit($schuelerId)) {
        json_err('schueler_id muss eine Zahl sein');
    }
    $von = trim((string)($b['von'] ?? ''));
    $bis = trim((string)($b['bis'] ?? ''));
    foreach (['von' => $von, 'bis' => $bis] as $feld => $wert) {
        if ($wert !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert)) {
            json_err("$feld muss das Format JJJJ-MM-TT haben");
        }
    }

    // Brute-Force-Bremse: Fehlversuche je Benutzername im Zeitfenster zählen,
    // BEVOR WebUntis gefragt wird. Ohne DB (frische Installation) greift
    // ersatzweise eine Wartezeit nach Fehlversuchen.
    $dbVerfuegbar = true;
    try {
        $pdo = db($cfg);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_log
             WHERE webuntis_benutzer = ? AND erfolgreich = 0
               AND zeitpunkt >= NOW() - INTERVAL ? MINUTE'
        );
        $stmt->execute([$benutzer, (int)($cfg['security']['lockout_minutes'] ?? 15)]);
        if ((int)$stmt->fetchColumn() >= (int)($cfg['security']['max_failed_logins'] ?? 5)) {
            json_err('Zu viele Fehlversuche – bitte später erneut versuchen', 429);
        }
    } catch (Throwable $e) {
        $dbVerfuegbar = false;
    }

    // Lange Abläufe: serverseitig zu Ende laufen lassen, auch wenn der
    // Proxy den Request kappt (Muster aus NEUES_PROJEKT_PROMPT.md).
    ignore_user_abort(true);
    set_time_limit(0);

    try {
        $bericht = sondierung_ausfuehren($cfg, $benutzer, $passwort,
            $gruppen, $extraPfade, $schuelerId, $von, $bis);
        if ($dbVerfuegbar) {
            $pdo->prepare('INSERT INTO login_log
                (webuntis_benutzer, erfolgreich, grund, ip) VALUES (?, 1, ?, ?)')
                ->execute([$benutzer, 'sondierung',
                    $_SERVER['REMOTE_ADDR'] ?? '']);
        }
        json_ok(['bericht' => $bericht]);
    } catch (RuntimeException $e) {
        if ($dbVerfuegbar) {
            $pdo->prepare('INSERT INTO login_log
                (webuntis_benutzer, erfolgreich, grund, ip) VALUES (?, 0, ?, ?)')
                ->execute([$benutzer, substr($e->getMessage(), 0, 190),
                    $_SERVER['REMOTE_ADDR'] ?? '']);
        } else {
            sleep(2);   // Ersatz-Bremse ohne DB
        }
        json_err('Sondierung fehlgeschlagen: ' . $e->getMessage(), 502);
    }
}

json_err('Unbekannter API-Pfad', 404);
