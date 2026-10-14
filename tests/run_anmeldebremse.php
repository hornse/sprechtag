<?php
// ============================================================
// tests/run_anmeldebremse.php – jede Stelle, an der ein WebUntis-Passwort
// entgegengenommen wird, geht durch dieselbe Bremse und dasselbe Protokoll
// Aufruf: php tests/run_anmeldebremse.php   → Exit-Code 0 = alles grün
//
// Anlass (v0.9.93): POST /api/sondierung prüfte nur den Schalter in
// config.php, keine Anmeldung und keine Rolle, und der Schalter stand im
// Betrieb auf true (gemessen 11.10.2026: 400 „Feld 'benutzername' fehlt“
// statt 403). Die Route reichte fremde WebUntis-Zugangsdaten an WebUntis
// weiter – ohne Bremse und ohne Protokoll, obwohl SONDIERUNG.md beides
// behauptete. Gefunden bei der Durchsicht der Dokumentation. Der
// Stammdaten-Sync nahm ebenfalls ein Passwort ohne Bremse entgegen.
//
// Teil 1 FÜHRT die Routen AUS, statt im Quelltext zu suchen: der Zweig aus
// index.php in einem eigenen Prozess (Vorbild run_ladereihenfolge.php), mit
// der echten auth.php. Ersetzt sind Ausgabe (json_ok/json_err werfen),
// Datenbank, der WebUntis-Aufruf und die Bremse (alle schreiben mit, wann
// sie gerufen werden). Die Bremse ist hier ersetzt; ihre Zählung führt seit
// v0.9.107 tests/run_bremse.php gegen SQLite aus (E35). Die Ersatzfunktionen
// prüfen, dass Fehlschlag und Erfolg die Kennung des Versuchs bekommen.
// Teil 2 ist die Engstelle: Jede Funktion, die authenticate mit übergebenen
// Zugangsdaten ruft, wird nur aus Routen gerufen, die vorher die Bremse
// fragen – aus dem Code ermittelt, nicht aufgezählt.
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
$INDEX = $API . '/index.php';
$quelle = (string)file_get_contents($INDEX);

/** PHP ohne Kommentare (Tokenizer). */
function ohne_kommentar(string $q): string
{
    $c = '';
    foreach (token_get_all($q) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
}

/** Zweig ab $kopf bis zur passenden Klammer; '' wenn der Kopf nicht genau einmal vorkommt. */
function zweig(string $quelle, string $kopf): string
{
    $pos = strpos($quelle, $kopf);
    if ($pos === false || strpos($quelle, $kopf, $pos + 1) !== false) return '';
    $aus = ''; $tiefe = 0;
    foreach (array_slice(token_get_all('<?php ' . substr($quelle, $pos)), 1) as $t) {
        $txt = is_array($t) ? $t[1] : $t;
        $aus .= $txt;
        if ($txt === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $tiefe++;
        if ($txt === '}') { $tiefe--; if ($tiefe === 0) return $aus; }
    }
    return '';
}

// ---- 1. ausgeführt ---------------------------------------------------------
$ersatz = '<?php
final class Antwort extends Exception { public function __construct(public int $status, public array $daten) { parent::__construct("a"); } }
function json_ok(array $d, int $s = 200): never { throw new Antwort($s, $d); }
function json_err(string $m, int $s = 400): never { throw new Antwort($s, ["fehler" => $m]); }
function req(array $b, string $k): string { $w = trim((string)($b[$k] ?? "")); if ($w === "") json_err("Feld \'$k\' fehlt"); return $w; }
function db(array $cfg): PDO { return new PDO("sqlite::memory:"); }
function sleep_ersatz(int $s): int { return 0; }
$GLOBALS["spur"] = [];
function bremse_beginnen(PDO $p, array $cfg, string $b): int { $GLOBALS["spur"][] = "bremse:" . $b; if ($GLOBALS["BREMSE"] ?? false) json_ok(["fehler" => "warten", "warten_sekunden" => 5], 429); return 7; }
function bremse_fehlschlag(PDO $p, int $v, string $b, string $g): void { $GLOBALS["spur"][] = "fehlschlag:" . $b . ($v === 7 ? "" : ":falscher-versuch"); }
function anmeldung_erfolg_merken(PDO $p, int $v, string $b, string $g): void { $GLOBALS["spur"][] = "erfolg:" . $b . ":" . $g . ($v === 7 ? "" : ":falscher-versuch"); }
function wu_webuntis() { $GLOBALS["spur"][] = "webuntis"; if (($GLOBALS["WU"] ?? "ok") !== "ok") throw new RuntimeException("Anmeldung abgelehnt"); }
function sondierung_ausfuehren(array $cfg, string $b, string $p, array $g, array $x = [], string $s = "", string $v = "", string $bis = ""): array { wu_webuntis(); return ["ok" => true]; }
function wu_stammdaten_sync(array $cfg, PDO $pdo, string $b, string $p): array { wu_webuntis(); return ["lehrer" => 0, "raeume" => 0]; }
';
$tmp = sys_get_temp_dir() . '/sprechtag-bremse-' . getmypid();
@mkdir($tmp);
file_put_contents("$tmp/ersatz.php", $ersatz);

/** Führt einen Zweig aus. $rolle: null = nicht angemeldet. */
function lauf(string $tmp, string $zweig, string $api, array $seg, ?string $rolle, bool $schalter = true,
              bool $bremse = false, string $wu = 'ok'): array
{
    // sleep() ist eingebaut und lässt sich nicht ersetzen – im Zweig umbenannt.
    $z = str_replace('sleep(', 'sleep_ersatz(', $zweig);
    $skript = '<?php declare(strict_types=1);
require ' . var_export("$tmp/ersatz.php", true) . ';
require_once ' . var_export("$api/auth.php", true) . ';
$methode = "POST"; $seg = ' . var_export($seg, true) . ';
$body = ["benutzername" => "probe", "passwort" => "geheim"];
$cfg = ["sondierung_freigeschaltet" => ' . ($schalter ? 'true' : 'false') . ', "security" => []];
$GLOBALS["BREMSE"] = ' . ($bremse ? 'true' : 'false') . '; $GLOBALS["WU"] = ' . var_export($wu, true) . ';
$_SESSION = ' . ($rolle === null ? '[]' : '["rolle" => ' . var_export($rolle, true) . ', "name" => ""]') . ';
try { ' . $z . ' echo "KEINE ANTWORT"; }
catch (Antwort $a) { echo "ANTWORT " . $a->status . " " . json_encode($a->daten, JSON_UNESCAPED_UNICODE); }
echo "\nSPUR " . implode(",", $GLOBALS["spur"]);
';
    file_put_contents("$tmp/lauf.php", $skript);
    $aus = []; $rc = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/lauf.php") . ' 2>&1', $aus, $rc);
    $text = implode("\n", $aus);
    preg_match('/^ANTWORT (\d+) (.*)$/m', $text, $m);
    preg_match('/^SPUR (.*)$/m', $text, $s);
    return ['status' => (int)($m[1] ?? 0), 'daten' => json_decode($m[2] ?? 'null', true),
        'spur' => isset($s[1]) && $s[1] !== '' ? explode(',', $s[1]) : [], 'roh' => $text];
}
$zeig = fn(array $r): string => $r['status'] . ' ' . substr(preg_replace('/\s+/', ' ', $r['roh']), 0, 140);
$fehlerText = fn(array $r): string => (string)($r['daten']['fehler'] ?? '');

// -- Sondierung: Schalter, Verwaltung, Bremse --
echo "Sondierung\n";
$sond = zweig($quelle, "if (\$methode === 'POST' && (\$seg[0] ?? '') === 'sondierung') {");
pruefe('Voraussetzung: Route POST /api/sondierung genau einmal gefunden', $sond !== '');
$sg = ['sondierung'];
// Stufe 1: der Schalter. Abgeschaltet hilft auch die Verwaltung nicht.
$r = lauf($tmp, $sond, $API, $sg, 'admin', false);
pruefe('abgeschaltet: 403 „abgeschaltet“, auch für die Verwaltung; WebUntis nicht gerufen – ' . $zeig($r),
    $r['status'] === 403 && str_contains($fehlerText($r), 'abgeschaltet') && $r['spur'] === []);
// Stufe 2: die Rolle – je einzeln belegt, bei eingeschaltetem Schalter.
$r = lauf($tmp, $sond, $API, $sg, null);
pruefe('eingeschaltet, nicht angemeldet: 401, WebUntis nicht gerufen, Bremse nicht befragt – ' . $zeig($r),
    $r['status'] === 401 && $r['spur'] === []);
$r = lauf($tmp, $sond, $API, $sg, 'lehrkraft');
pruefe('eingeschaltet, Lehrkraft: 403 „nur für die Sprechtags-Administration“, WebUntis nicht gerufen – ' . $zeig($r),
    $r['status'] === 403 && str_contains($fehlerText($r), 'Sprechtags-Administration') && $r['spur'] === []);
$r = lauf($tmp, $sond, $API, $sg, 'eltern');
pruefe('eingeschaltet, Eltern: 403, WebUntis nicht gerufen – ' . $zeig($r),
    $r['status'] === 403 && $r['spur'] === []);
// Stufe 3: die Bremse – vor dem WebUntis-Aufruf, für den übergebenen Benutzernamen.
$r = lauf($tmp, $sond, $API, $sg, 'admin', true, true);
pruefe('Verwaltung, Bremse greift: 429, WebUntis nicht gerufen – ' . $zeig($r),
    $r['status'] === 429 && $r['spur'] === ['bremse:probe']);
$r = lauf($tmp, $sond, $API, $sg, 'admin');
pruefe('Verwaltung, Bremse frei: 200; erst die Bremse, dann WebUntis, dann der Erfolg ins Protokoll – ' . $zeig($r),
    $r['status'] === 200 && $r['spur'] === ['bremse:probe', 'webuntis', 'erfolg:probe:sondierung']);
$r = lauf($tmp, $sond, $API, $sg, 'admin', true, false, 'abgelehnt');
pruefe('Verwaltung, WebUntis lehnt ab: 502, der Fehlschlag steht im Protokoll (zählt für die Bremse) – ' . $zeig($r),
    $r['status'] === 502 && $r['spur'] === ['bremse:probe', 'webuntis', 'fehlschlag:probe']);

// -- Stammdaten-Sync: Verwaltung, Bremse --
echo "Stammdaten-Sync\n";
$sync = zweig($quelle, "if (\$methode === 'POST' && (\$seg[1] ?? '') === 'sync') {");
pruefe('Voraussetzung: Route POST /api/stammdaten/sync genau einmal gefunden', $sync !== '');
$sy = ['stammdaten', 'sync'];
$r = lauf($tmp, $sync, $API, $sy, 'lehrkraft');
pruefe('Sync, Lehrkraft: 403, WebUntis nicht gerufen – ' . $zeig($r),
    $r['status'] === 403 && $r['spur'] === []);
$r = lauf($tmp, $sync, $API, $sy, 'admin', true, true);
pruefe('Sync, Bremse greift: 429, WebUntis nicht gerufen – ' . $zeig($r),
    $r['status'] === 429 && $r['spur'] === ['bremse:probe']);
$r = lauf($tmp, $sync, $API, $sy, 'admin');
pruefe('Sync, Bremse frei: 200; erst die Bremse, dann WebUntis, dann der Erfolg ins Protokoll – ' . $zeig($r),
    $r['status'] === 200 && $r['spur'] === ['bremse:probe', 'webuntis', 'erfolg:probe:stammdaten-sync']);
$r = lauf($tmp, $sync, $API, $sy, 'admin', true, false, 'abgelehnt');
pruefe('Sync, WebUntis lehnt ab: 502, der Fehlschlag steht im Protokoll – ' . $zeig($r),
    $r['status'] === 502 && $r['spur'] === ['bremse:probe', 'webuntis', 'fehlschlag:probe']);

@unlink("$tmp/lauf.php"); @unlink("$tmp/ersatz.php"); @rmdir($tmp);

// ---- 2. eine Bremse, eine Stelle -------------------------------------------
echo "Engstelle\n";
$bremseDatei = $API . '/anmeldebremse.php';
$bremse = is_file($bremseDatei) ? ohne_kommentar((string)file_get_contents($bremseDatei)) : '';
$idxCode = ohne_kommentar($quelle);
pruefe('Voraussetzung: backend/api/anmeldebremse.php vorhanden und von index.php vor den Routen geladen',
    $bremse !== '' && preg_match("/require_once __DIR__ \\. '\\/anmeldebremse\\.php';/", $idxCode) === 1
    && strpos($idxCode, "anmeldebremse.php") < strpos($idxCode, "=== 'auth') {"));

// Welche Funktionen rufen authenticate mit übergebenen Zugangsdaten? Aus dem
// Code ermittelt (außerhalb der vendorten Module unter backend/auth/).
$rufer = [];
foreach (glob($API . '/*.php') as $f) {
    $toks = token_get_all((string)file_get_contents($f));
    $aktuell = null; $tiefe = 0; $start = null;
    foreach ($toks as $i => $t) {
        if (is_array($t) && $t[0] === T_FUNCTION) {
            for ($j = $i + 1; isset($toks[$j]); $j++) {
                if (is_array($toks[$j]) && $toks[$j][0] === T_WHITESPACE) continue;
                if (is_array($toks[$j]) && $toks[$j][0] === T_STRING && $tiefe === 0) { $aktuell = $toks[$j][1]; $start = true; }
                break;
            }
        }
        $txt = is_array($t) ? $t[1] : $t;
        if ($txt === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $tiefe++;
        if ($txt === '}') { $tiefe--; if ($tiefe === 0) $aktuell = null; }
        if (is_array($t) && $t[0] === T_STRING && $t[1] === 'authenticate' && $aktuell !== null
            && is_array($toks[$i - 1] ?? null) && $toks[$i - 1][0] === T_OBJECT_OPERATOR) {
            $rufer[$aktuell] = basename($f);
        }
    }
}
ksort($rufer);
pruefe('Voraussetzung: Funktionen gefunden, die authenticate mit übergebenen Zugangsdaten rufen (mind. 3: '
    . implode(', ', array_keys($rufer)) . ')', count($rufer) >= 3);

// Jeder Aufruf dieser Funktionen steht in index.php, in einer Route, die
// vorher bremse_beginnen fragt (bis v0.9.106: bremse_greift). Anderswo
// gerufen, wäre es eine Stelle ohne Bremse.
$ohneBremse = [];
foreach (array_keys($rufer) as $fn) {
    foreach (glob($API . '/*.php') as $f) {
        $code = ohne_kommentar((string)file_get_contents($f));
        $off = 0;
        while (preg_match('/(?<![\w>$])' . preg_quote($fn, '/') . '\(/', $code, $m, PREG_OFFSET_CAPTURE, $off)) {
            $p = $m[0][1]; $off = $p + 1;
            if (preg_match('/function\s+$/', substr($code, max(0, $p - 12), min(12, $p)))) continue;   // Definition
            if (basename($f) !== 'index.php') { $ohneBremse[] = "$fn() in " . basename($f); continue; }
            // die Route, in der der Aufruf steht: ab dem letzten „if ($methode ===“ davor
            $kopf = strrpos(substr($code, 0, $p), 'if ($methode ===');
            $route = $kopf === false ? '' : substr($code, $kopf, $p - $kopf);
            if (!str_contains($route, 'bremse_beginnen(')) $ohneBremse[] = "$fn() in index.php ohne Bremse davor";
        }
    }
}
pruefe('jede Stelle, die authenticate mit übergebenen Zugangsdaten ruft, fragt vorher die Bremse'
    . ($ohneBremse === [] ? '' : ' – ' . implode('; ', $ohneBremse)), count($rufer) >= 3 && $ohneBremse === []);

// Ins Protokoll schreibt nur die Bremse.
$schreiber = [];
foreach (glob($API . '/*.php') as $f) {
    if (preg_match_all('/INSERT INTO login_log/', ohne_kommentar((string)file_get_contents($f))) > 0) $schreiber[] = basename($f);
}
pruefe('in login_log schreibt nur anmeldebremse.php (gefunden: ' . implode(', ', $schreiber) . ')',
    $schreiber === ['anmeldebremse.php']);
$login = zweig($quelle, "if (\$methode === 'POST' && \$unter === 'login') {");
pruefe('Login benutzt dieselbe Bremse: bremse_beginnen vor wu_login, bremse_fehlschlag im catch, anmeldung_erfolg_merken danach – je mit dem Versuch',
    $login !== ''
    && preg_match('/\$versuch = bremse_beginnen\(\$pdo, \$cfg, \$benutzer\)[\s\S]*wu_login\([\s\S]*catch \(RuntimeException \$e\) \{\s*bremse_fehlschlag\(\$pdo, \$versuch, \$benutzer,[\s\S]*anmeldung_erfolg_merken\(\$pdo, \$versuch, \$benutzer, \$daten\[\'rolle\'\]\)/', $login) === 1);
// Bis v0.9.106 stand hier eine Textprüfung des Zähl-SQL (NOW() - INTERVAL);
// seit v0.9.107 führt tests/run_bremse.php die Zählung aus (E35).
pruefe('die Bremse zählt je Benutzername im Zeitfenster, mit den Grenzen aus config.php (ausgeführt in run_bremse.php)',
    str_contains($bremse, "WHERE webuntis_benutzer = ? AND erfolgreich = 0 AND id <> ? AND zeitpunkt >= ?")
    && str_contains($bremse, "(int)(\$cfg['security']['lockout_minutes'] ?? 15)")
    && str_contains($bremse, "(int)(\$cfg['security']['max_failed_logins'] ?? 5)"));
pruefe('Erfolge nur, wenn die Schule das Protokoll samt Erfolgen eingeschaltet hat (wie bis v0.9.92 im Login)',
    preg_match("/function anmeldung_erfolg_merken\([\s\S]*marke_wert\(\\\$pdo, 'login_log_aktiv', '0'\)[\s\S]*marke_wert\(\\\$pdo, 'login_log_erfolge', '0'\)[\s\S]*if \(\\\$logAktiv && \\\$logErfolge\)/", $bremse) === 1);

// ---- 3. die Vorlage ----------------------------------------------------------
$vorlage = ohne_kommentar((string)file_get_contents(__DIR__ . '/../backend/config.example.php'));
pruefe('config.example.php: die Sondierung ist abgeschaltet voreingestellt',
    preg_match("/'sondierung_freigeschaltet'\s*=>\s*false\s*,/", $vorlage) === 1
    && preg_match("/'sondierung_freigeschaltet'\s*=>\s*true/", $vorlage) === 0);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
