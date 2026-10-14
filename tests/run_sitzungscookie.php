<?php
// ============================================================
// tests/run_sitzungscookie.php – der Sitzungs-Cookie trägt Secure, HttpOnly
// und SameSite=Lax (v0.9.101)
// Aufruf: php tests/run_sitzungscookie.php   → Exit-Code 0 = alles grün
//
// Anlass: Messung über die Reihe (koordination, 14.10.2026): sechs Projekte
// setzen alle drei Flags, sprechtag keines. Am Server gemessen 14.10.2026,
// 20:20: `curl -sI https://sprechtag.hornse.de/api/auth/me` lieferte
// `set-cookie: sprechtag_session=…; path=/` – ohne Secure, HttpOnly,
// SameSite. Der Kommentar im Router behauptete, `$_SERVER['HTTPS'] = 'on'`
// setze das Secure-Flag; deshalb wurde der Schutz nicht vermisst. PHP leitet
// session.cookie_secure nicht aus $_SERVER['HTTPS'] ab.
//
// Teil 1 FÜHRT bootstrap.php AUS – eine Kopie mit helfer.php und einer
// erfundenen config.php in einem eigenen Prozess – und liest danach
// session_get_cookie_params(). Teil 2: der Router-Kommentar behauptet das
// Falsche nicht mehr (die bekannte falsche Fassung wird ausdrücklich
// gesucht). Ob der Server den Kopf so sendet, misst nur der Abruf am Server
// (Abnahme nach der Auslieferung, CHANGELOG v0.9.101).
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

$backend = (string)realpath(__DIR__ . '/../backend');
$tmp = sys_get_temp_dir() . '/sprechtag-cookie-' . getmypid();
@mkdir($tmp);
copy("$backend/bootstrap.php", "$tmp/bootstrap.php");
copy("$backend/helfer.php", "$tmp/helfer.php");
file_put_contents("$tmp/config.php", "<?php return ['db' => ['dsn' => 'sqlite::memory:', 'benutzer' => null, 'passwort' => null]];\n");
// Wie im Betrieb: router.php setzt den Namen vor bootstrap.php.
file_put_contents("$tmp/lauf.php", '<?php
session_save_path(' . var_export($tmp, true) . ');
session_name("sprechtag_session");
require ' . var_export("$tmp/bootstrap.php", true) . ';
echo "STATUS " . session_status() . "\n";
echo "PARAMS " . json_encode(session_get_cookie_params()) . "\n";
');
$aus = []; $rc = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/lauf.php") . ' 2>&1', $aus, $rc);
$text = implode("\n", $aus);
preg_match('/^STATUS (\d+)$/m', $text, $s);
preg_match('/^PARAMS (.*)$/m', $text, $p);
$par = json_decode($p[1] ?? 'null', true);
foreach (glob("$tmp/*") ?: [] as $f) @unlink($f);
@rmdir($tmp);

echo "bootstrap.php, ausgeführt\n";
pruefe('Voraussetzung: bootstrap.php läuft durch und startet die Sitzung – '
    . substr(preg_replace('/\s+/', ' ', $text), 0, 160),
    (int)($s[1] ?? 0) === PHP_SESSION_ACTIVE && is_array($par));
$par = is_array($par) ? $par : [];
pruefe('Secure gesetzt', ($par['secure'] ?? null) === true);
pruefe('HttpOnly gesetzt', ($par['httponly'] ?? null) === true);
pruefe('SameSite=Lax', ($par['samesite'] ?? null) === 'Lax');
pruefe('Pfad /, Lebensdauer 0 (Sitzungs-Cookie, wie bisher)',
    ($par['path'] ?? null) === '/' && ($par['lifetime'] ?? null) === 0);
pruefe('keine Warnung beim Start (Parameter vor session_start gesetzt)',
    !preg_match('/Warning|Notice|Deprecated/', $text));

echo "Router-Kommentar\n";
$router = (string)file_get_contents("$backend/router.php");
pruefe('behauptet nicht mehr, $_SERVER[\'HTTPS\'] setze das Secure-Flag (die alte Fassung)',
    !str_contains($router, "fehlt das Secure-Flag am Session-Cookie"));
pruefe('nennt, wo die Flags gesetzt werden (bootstrap.php)',
    preg_match('/Secure, HttpOnly und SameSite[^\n]*\n?[^\n]*bootstrap\.php/', $router) === 1);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
