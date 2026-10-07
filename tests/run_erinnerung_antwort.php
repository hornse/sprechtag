<?php
// ============================================================
// tests/run_erinnerung_antwort.php
// Prüft erinnerung_antwort_deuten(): Wird ein Versand nur dann als
// Erfolg gewertet, wenn WebUntis die Empfängerzahl bestätigt?
//
// Grundlage: Messung vom 07.10.2026 am Produktivsystem
//   POST /WebUntis/api/rest/view/v2/messages/users  (Liste „testen", 2 Pers.)
//   -> {"numberOfRecipients": 2, "numberOfCCRecipients": null}
//
// Fehler- und Teilerfolgsfälle sind NICHT gemessen; sie müssen deshalb als
// 'unklar' gelten und dürfen nie als Erfolg durchgehen.
//
// Aufruf: php tests/run_erinnerung_antwort.php
// ============================================================
declare(strict_types=1);

// Nur die zu prüfende Funktion laden, ohne DB/WebUntis-Abhängigkeiten.
$quelle = file_get_contents(__DIR__ . '/../backend/api/erinnerungen.php');
$anfang = strpos($quelle, 'function erinnerung_antwort_deuten');
$ende   = strpos($quelle, 'function erinnerung_ids_aus_users');
if ($anfang === false || $ende === false) {
    fwrite(STDERR, "Funktion nicht gefunden\n"); exit(1);
}
eval(substr($quelle, $anfang, $ende - $anfang));

$fehler = 0;
function pruefe(string $name, bool $ok): void {
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

// ---- Der gemessene Erfolgsfall ----
$d = erinnerung_antwort_deuten(
    ['status' => 200, 'text' => '',
     'json' => ['numberOfRecipients' => 2, 'numberOfCCRecipients' => null]], 2);
pruefe('gemessener Erfolgsfall (2 von 2) -> gesendet',
    $d['stand'] === 'gesendet' && $d['erreicht'] === 2);

$d = erinnerung_antwort_deuten(
    ['status' => 200, 'json' => ['numberOfRecipients' => 500]], 500);
pruefe('voller Block (500 von 500) -> gesendet', $d['stand'] === 'gesendet');

// ---- Der Kern: 2xx allein ist KEIN Erfolg ----
$d = erinnerung_antwort_deuten(['status' => 200, 'json' => []], 500);
pruefe('200 ohne Empfaengerzahl -> unklar, nicht gesendet',
    $d['stand'] === 'unklar' && $d['erreicht'] === 0);

$d = erinnerung_antwort_deuten(
    ['status' => 200, 'json' => ['numberOfRecipients' => 0]], 500);
pruefe('200 mit 0 Empfaengern -> fehler (nichts ging hinaus)',
    $d['stand'] === 'fehler' && $d['erreicht'] === 0);

$d = erinnerung_antwort_deuten(
    ['status' => 200, 'json' => ['numberOfRecipients' => 300]], 500);
pruefe('200 mit zu wenig Empfaengern -> unklar',
    $d['stand'] === 'unklar' && $d['erreicht'] === 300);

$d = erinnerung_antwort_deuten(
    ['status' => 200, 'json' => ['numberOfRecipients' => '2']], 2);
pruefe('Zahl als Zeichenkette wird akzeptiert', $d['stand'] === 'gesendet');

// ---- Fehlerstatus ----
$d = erinnerung_antwort_deuten(['status' => 403, 'json' => null], 2);
pruefe('403 -> fehler mit Hinweis aufs Dienstkonto',
    $d['stand'] === 'fehler' && strpos($d['grund'], 'Dienstkonto') !== false);

$d = erinnerung_antwort_deuten(['status' => 500, 'json' => null], 2);
pruefe('500 -> fehler', $d['stand'] === 'fehler');

// ---- Verbindungsfehler: sicher nichts hinausgegangen ----
$d = erinnerung_antwort_deuten(
    ['status' => 0, 'text' => 'cURL: Failed to connect to 127.0.0.1 port 1 after 1 ms',
     'json' => null], 2);
pruefe('Verbindung abgewiesen -> fehler (sicher nichts gesendet)',
    $d['stand'] === 'fehler');

$d = erinnerung_antwort_deuten(
    ['status' => 0, 'text' => 'cURL: Could not resolve host: nicht-da.invalid',
     'json' => null], 2);
pruefe('Name nicht aufloesbar -> fehler', $d['stand'] === 'fehler');

// ---- Zeitueberschreitung: KANN angekommen sein -> unklar ----
$d = erinnerung_antwort_deuten(
    ['status' => 0, 'text' => 'cURL: Operation timed out after 30001 milliseconds',
     'json' => null], 2);
pruefe('Zeitueberschreitung -> unklar (nicht als Fehlschlag werten)',
    $d['stand'] === 'unklar');

echo $fehler === 0 ? "\nALLE TESTS GRUEN\n" : "\n$fehler FEHLER\n";
exit($fehler === 0 ? 0 : 1);
