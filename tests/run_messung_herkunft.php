<?php
// ============================================================
// tests/run_messung_herkunft.php – MESSUNG (v0.9.106), wird mit der Route
// entfernt. Welche Adresse sieht die Anwendung hinter dem Proxy von Uberspace?
// (Review koordination 14.10.2026, offene Frage O2, für Befund 5)
// Aufruf: php tests/run_messung_herkunft.php   → Exit-Code 0 = alles grün
//
// Ausgeführt: mh_vergleich() mit erfundenen $_SERVER-Inhalten. Geprüft wird,
// dass die Antwort nur Wahrheitswerte, Zahlen, Kopfzeilen-NAMEN und
// gesalzene Prüfsummen enthält – keine Adresse, auch nicht in einer
// Prüfsumme, die sich ohne Salz zurückrechnen ließe – und dass die Vergleiche
// stimmen. Adressen aus den Dokumentationsbereichen (RFC 5737), ERFUNDEN.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

$datei = __DIR__ . '/../backend/api/messung_herkunft.php';
if (is_file($datei)) require_once $datei;
$da = function_exists('mh_vergleich');
pruefe('Voraussetzung: mh_vergleich vorhanden', $da);

$ich = '198.51.100.7';
$proxy = ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '192.0.2.1, ' . $ich,
          'HTTP_X_REAL_IP' => $ich, 'HTTP_X_FORWARDED_PROTO' => 'https'];
$direkt = ['REMOTE_ADDR' => $ich];
$v = $da ? mh_vergleich($proxy, $ich, '203.0.113.9') : [];
$w = $da ? mh_vergleich($direkt, $ich, $ich) : [];
$json = json_encode([$v, $w]);

pruefe('keine Adresse in der Antwort, weder erfundene noch eigene',
    $da && !str_contains($json, '198.51.100') && !str_contains($json, '203.0.113') && !str_contains($json, '192.0.2'));
pruefe('keine ungesalzene Prüfsumme einer Adresse in der Antwort',
    $da && !str_contains($json, substr(hash('sha256', $ich), 0, 8))
    && !str_contains($json, substr(hash('sha256', '203.0.113.9'), 0, 8)));
pruefe('hinter einem Proxy: REMOTE_ADDR ist nicht ich, der letzte X-Forwarded-For-Eintrag ist es, der erste nicht',
    ($v['remote_addr_gleich_ich'] ?? null) === false && ($v['xff_letzter_gleich_ich'] ?? null) === true
    && ($v['xff_erster_gleich_ich'] ?? null) === false && ($v['xff_eintraege'] ?? null) === 2);
pruefe('… X-Real-IP vorhanden und gleich ich; login_log gleich REMOTE_ADDR, nicht ich',
    ($v['x_real_ip_gleich_ich'] ?? null) === true && ($v['login_log_gleich_remote_addr'] ?? null) === true
    && ($v['login_log_gleich_ich'] ?? null) === false);
pruefe('… Kopfzeilen nur als NAMEN, die mit Weiterleitung zu tun haben',
    ($v['kopfzeilen'] ?? null) === ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_REAL_IP']);
pruefe('direkt: REMOTE_ADDR ist ich, kein X-Forwarded-For, login_log gleich ich',
    ($w['remote_addr_gleich_ich'] ?? null) === true && ($w['xff_vorhanden'] ?? null) === false
    && ($w['xff_eintraege'] ?? null) === 0 && ($w['login_log_gleich_ich'] ?? null) === true);
pruefe('gesalzene Prüfsummen: gleiche Werte gleich, verschiedene verschieden, innerhalb einer Antwort',
    $da && ($w['pruefsummen']['remote_addr'] ?? 'a') === ($w['pruefsummen']['ich'] ?? 'b')
    && ($v['pruefsummen']['remote_addr'] ?? 'a') !== ($v['pruefsummen']['ich'] ?? 'a'));
pruefe('ohne login_log-Eintrag: die beiden Vergleiche sind null, nicht false',
    $da && array_key_exists('login_log_gleich_ich', mh_vergleich($direkt, $ich, null))
    && mh_vergleich($direkt, $ich, null)['login_log_gleich_ich'] === null);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
