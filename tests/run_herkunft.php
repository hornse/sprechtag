<?php
// ============================================================
// tests/run_herkunft.php – kommt ein verändernder Aufruf aus der eigenen
// Oberfläche? (v0.9.105, E33)
// Aufruf: php tests/run_herkunft.php   → Exit-Code 0 = alles grün
//
// Anlass: Review aus koordination (BEFUND-2026-10-14-review-sprechtag-1.md,
// Commit a700ce2), Befund 1: Kein Schutz gegen fremde Formulare; body_json()
// las auch einen text/plain-Körper als JSON. Gewichtung des Betreibers
// (14.10.2026): „bald“ statt „sofort“, weil SameSite=Lax (v0.9.101) fremde
// Formular-POSTs abhält – aber ohne zweite Sicherung, und sie greift wieder,
// sobald jemand den Cookie anders setzt.
//
// Gebaut nach koordination M22 und dem Vorbild kurse/backend/api/herkunft.php:
// eine FESTE Kopfzeile (X-Angefordert-Von: sprechtag), EINMAL im Vorspann von
// index.php geprüft, kein Token. Dazu als zweite Stufe: Ein nicht leerer
// Körper muss als application/json kommen. Die beiden Stufen melden sich
// verschieden (403 „Ungültige Anfrage“ / 415 „… JSON …“).
//
// A. Die Kopfzeile, reine Funktion. B. Der Körper, reine Funktion.
// C. Der Vorspann von index.php, AUSGEFÜHRT in eigenem Prozess (Vorbild
//    run_admins.php). D. Aufbau, statisch: eine Stelle, vor dem Körper und
//    vor der ersten Route; Oberfläche und Server erwarten denselben Wert.
// DER ERFOLGSFALL IST DIE WICHTIGSTE PRÜFUNG (kurse, M22): Eine geschlossen
// scheiternde Sicherung hat in der Reihe einmal eine Anwendung für alle
// unbenutzbar gemacht. Dass die Oberfläche die Kopfzeile wirklich schickt,
// führt tests/frontend_herkunft_test.js aus.
// Was diese Suite nicht sieht: den Webserver vor dem Dienst (CORS) – das
// misst der Abruf am Server nach der Auslieferung (CHANGELOG v0.9.105).
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

$API = (string)realpath(__DIR__ . '/../backend/api');
$datei = $API . '/herkunft.php';
if (is_file($datei)) require_once $datei;
$da = function_exists('spt_herkunft_pruefen') && function_exists('spt_koerper_pruefen')
    && function_exists('spt_herkunft_kopfzeile');
pruefe('Voraussetzung: backend/api/herkunft.php mit spt_herkunft_kopfzeile, spt_herkunft_pruefen, spt_koerper_pruefen', $da);
// Fehlt die Datei, wird jede Prüfung rot, keine fällt aus der Zählung.
$h = fn(string $m, ?string $w): bool => $da && (spt_herkunft_pruefen($m, $w)['ok'] ?? null) === true;
$hg = fn(string $m, ?string $w): string => $da ? (string)(spt_herkunft_pruefen($m, $w)['grund'] ?? '') : '';
$k = fn(?string $t, string $roh): ?string => $da ? spt_koerper_pruefen($t, $roh) : 'FEHLT';

echo "A. Die Kopfzeile\n";
pruefe('Erfolgsfall: POST mit „sprechtag“ geht durch', $h('POST', 'sprechtag'));
pruefe('… ebenso PATCH, PUT, DELETE', $h('PATCH', 'sprechtag') && $h('PUT', 'sprechtag') && $h('DELETE', 'sprechtag'));
pruefe('GET braucht sie nicht (ein <img> schickt keine Kopfzeile)', $h('GET', null));
pruefe('ohne Kopfzeile: POST, PATCH, PUT, DELETE abgewiesen',
    $da && !$h('POST', null) && !$h('PATCH', null) && !$h('PUT', null) && !$h('DELETE', null));
pruefe('Positivliste: auch HEAD, „post“ klein, leere Methode brauchen sie',
    $da && !$h('HEAD', null) && !$h('post', null) && !$h('', null));
pruefe('falscher Wert, auch fast richtig („Sprechtag“, „ sprechtag“, „kurse“): abgewiesen',
    $da && !$h('POST', 'Sprechtag') && !$h('POST', ' sprechtag') && !$h('POST', 'kurse') && !$h('POST', ''));
pruefe('der Grund (fürs Protokoll) nennt fehlt/falsch, aber nicht den fremden Wert',
    str_contains($hg('POST', null), 'fehlt') && str_contains($hg('POST', 'xyzgeheim'), 'falschen Wert')
    && !str_contains($hg('POST', 'xyzgeheim'), 'xyzgeheim'));

echo "B. Der Körper\n";
pruefe('leerer Körper: gleich welcher Typ (Abmelden, Verwerfen)', $k(null, '') === null && $k('text/plain', '') === null);
pruefe('JSON mit application/json, auch mit charset', $k('application/json', '{"a":1}') === null
    && $k('application/json; charset=utf-8', '{"a":1}') === null);
pruefe('text/plain abgewiesen – das, womit ein fremdes Formular JSON nachbaut (Review)',
    $da && is_string($k('text/plain', '{"kuerzel":"Xq","x":"="}')));
pruefe('Formular-Typen und fehlender Typ abgewiesen',
    $da && is_string($k('application/x-www-form-urlencoded', 'a=1')) && is_string($k('multipart/form-data; boundary=x', 'x'))
    && is_string($k(null, '{"a":1}')));

echo "C. Der Vorspann von index.php, ausgeführt\n";
$quelle = (string)file_get_contents($API . '/index.php');
$ab = strpos($quelle, '$methode = $_SERVER');
$bis = strpos($quelle, '// ---- GET /api/health');
$vorspann = ($ab !== false && $bis !== false && $ab < $bis) ? substr($quelle, $ab, $bis - $ab) : '';
pruefe('Voraussetzung: Vorspann zwischen $methode und der ersten Route gefunden', $vorspann !== '');
$tmp = sys_get_temp_dir() . '/sprechtag-herkunft-' . getmypid();
@mkdir($tmp);
file_put_contents("$tmp/ersatz.php", '<?php
final class Antwort extends Exception { public function __construct(public int $status, public array $daten) { parent::__construct("a"); } }
function json_ok(array $d, int $s = 200): never { throw new Antwort($s, $d); }
function json_err(string $m, int $s = 400): never { throw new Antwort($s, ["fehler" => $m]); }
$GLOBALS["spur"] = [];
function db(array $cfg): PDO { $GLOBALS["spur"][] = "db"; throw new RuntimeException("keine DB im Test"); }
function body_json(): array { $GLOBALS["spur"][] = "body"; return spt_body_json($GLOBALS["ROH"]); }
');
$lauf = function (string $methode, ?string $kopf, ?string $typ, string $roh) use ($tmp, $vorspann, $API): array {
    $skript = '<?php declare(strict_types=1);
require ' . var_export("$tmp/ersatz.php", true) . ';
require_once ' . var_export("$API/auth.php", true) . ';
if (is_file(' . var_export("$API/herkunft.php", true) . ')) require_once ' . var_export("$API/herkunft.php", true) . ';
if (!function_exists("adm_sitzung_nachpruefen")) { function adm_sitzung_nachpruefen(array $c): void {} }
if (!function_exists("spt_body_json")) { function spt_body_json(string $r): array { $j = json_decode($r, true); return is_array($j) ? $j : []; } }
$_SERVER["REQUEST_METHOD"] = ' . var_export($methode, true) . ';
$_SERVER["REQUEST_URI"] = "/api/sprechtage";
' . ($kopf === null ? '' : '$_SERVER["HTTP_X_ANGEFORDERT_VON"] = ' . var_export($kopf, true) . ';') . '
' . ($typ === null ? '' : '$_SERVER["CONTENT_TYPE"] = ' . var_export($typ, true) . ';') . '
$GLOBALS["ROH"] = ' . var_export($roh, true) . ';
$cfg = []; $_SESSION = [];
try { ' . $vorspann . ' echo "DURCH " . json_encode($body); }
catch (Antwort $a) { echo "ANTWORT " . $a->status . " " . json_encode($a->daten, JSON_UNESCAPED_UNICODE); }
echo "\nSPUR " . implode(",", $GLOBALS["spur"]);
';
    file_put_contents("$tmp/lauf.php", $skript);
    $aus = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/lauf.php") . ' 2>&1', $aus);
    $text = implode("\n", $aus);
    preg_match('/^ANTWORT (\d+)/m', $text, $m);
    preg_match('/^SPUR (.*)$/m', $text, $s);
    return ['status' => (int)($m[1] ?? 0), 'durch' => str_contains($text, "\nDURCH ") || str_starts_with($text, 'DURCH '),
        'spur' => isset($s[1]) && $s[1] !== '' ? explode(',', $s[1]) : [], 'roh' => $text];
};
$zeig = fn(array $r): string => substr(preg_replace('/\s+/', ' ', $r['roh']), 0, 140);
$r = $lauf('POST', 'sprechtag', 'application/json', '{"name":"x"}');
pruefe('Erfolgsfall: POST mit Kopfzeile und JSON läuft durch den Vorspann, Körper gelesen – ' . $zeig($r),
    $r['durch'] && str_contains($r['roh'], '"name":"x"'));
$r = $lauf('POST', null, 'application/json', '{"name":"x"}');
pruefe('POST ohne Kopfzeile: 403, Körper nicht gelesen, keine Datenbank – ' . $zeig($r),
    $r['status'] === 403 && $r['spur'] === []);
$r = $lauf('POST', null, 'text/plain', '{"kuerzel":"Xq","x":"="}');
pruefe('das Formular der Review (text/plain, ohne Kopfzeile): 403 an der ersten Stufe – ' . $zeig($r),
    $r['status'] === 403 && $r['spur'] === []);
$r = $lauf('POST', 'sprechtag', 'text/plain', '{"kuerzel":"Xq","x":"="}');
pruefe('zweite Stufe einzeln: mit Kopfzeile, aber text/plain – 415, nicht 403 – ' . $zeig($r),
    $r['status'] === 415);
$r = $lauf('GET', null, null, '');
pruefe('GET ohne Kopfzeile läuft durch – ' . $zeig($r), $r['durch']);
$r = $lauf('DELETE', 'sprechtag', null, '');
pruefe('DELETE mit Kopfzeile, ohne Körper und ohne Typ läuft durch – ' . $zeig($r), $r['durch']);
foreach (glob("$tmp/*") ?: [] as $f) @unlink($f);
@rmdir($tmp);

echo "D. Aufbau (Quelltext)\n";
$ohne = function (string $q): string {
    $c = '';
    foreach (token_get_all($q) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
};
$ix = $ohne($quelle);
$aufruf = strpos($ix, 'spt_herkunft_pruefen($methode,');
$koerper = strpos($ix, '$body    = ');
$route = strpos($ix, "if (\$methode === 'GET' && (\$seg[0] ?? '') === 'health')");
pruefe('eine Stelle: spt_herkunft_pruefen genau einmal in index.php, vor dem Körper und vor der ersten Route',
    substr_count($ix, 'spt_herkunft_pruefen(') === 1 && $aufruf !== false && $koerper !== false && $route !== false
    && $aufruf < $koerper && $koerper < $route);
$boot = $ohne((string)file_get_contents(__DIR__ . '/../backend/bootstrap.php'));
pruefe('body_json() geht durch spt_body_json (die zweite Stufe sitzt dort, wo der Körper gelesen wird)',
    preg_match('/function body_json\(\): array\s*\{\s*return spt_body_json\(/', $boot) === 1);
$js = (string)file_get_contents(__DIR__ . '/../frontend/app.js');
pruefe('Oberfläche und Server erwarten denselben Namen und Wert',
    $da && str_contains($js, "'" . spt_herkunft_kopfzeile()['name'] . "': '" . spt_herkunft_kopfzeile()['wert'] . "'"));

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
