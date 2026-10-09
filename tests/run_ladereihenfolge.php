<?php
// ============================================================
// tests/run_ladereihenfolge.php – ruft eine Route nur, was schon geladen ist?
// Aufruf: php tests/run_ladereihenfolge.php   → Exit-Code 0 = alles grün
//
// Anlass (v0.9.66): GET /api/schueler-gruppen stürzte im Betrieb ab –
// „Call to undefined function bu_zugelassene_gruppen()“ (Server-Log,
// 09.10.2026). Die Route steht in index.php weit oben, die Funktion stand in
// buchungen.php, das index.php erst am Ende lädt. Die Prüfung der Route lud
// buchungen.php vorab – sie kannte mehr als der Betrieb und war grün.
//
// Zwei Prüfungen, beide aus index.php selbst ermittelt, nichts aufgezählt:
//   1. STATISCH, für ALLE Routen: Jeder Funktionsaufruf in index.php ist an
//      seiner Stelle definiert – in PHP selbst, in index.php oder in einer
//      Datei, die index.php VORHER lädt (mit deren eigenen Ladeanweisungen).
//   2. AUSGEFÜHRT: die Route /api/schueler-gruppen in einem eigenen Prozess,
//      der genau die Dateien lädt, die index.php vor ihr lädt.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

$API = realpath(__DIR__ . '/../backend/api');
$INDEX = $API . '/index.php';

/** Ladeanweisungen (require/include … __DIR__ . '/x.php') einer Datei mit Position. */
function ladeanweisungen(string $datei): array
{
    $toks = token_get_all((string)file_get_contents($datei));
    $aus = [];
    foreach ($toks as $i => $t) {
        if (!is_array($t) || !in_array($t[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) continue;
        // __DIR__ . '/pfad.php'
        for ($j = $i + 1; $j < $i + 8 && isset($toks[$j]); $j++) {
            if (is_array($toks[$j]) && $toks[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
                $pfad = realpath(dirname($datei) . substr($toks[$j][1], 1, -1));
                if ($pfad !== false) $aus[] = ['pos' => $i, 'datei' => $pfad];
                break;
            }
        }
    }
    return $aus;
}

/** Alle Dateien, die eine Datei (transitiv) lädt, einschließlich ihrer selbst. */
function geladen(string $datei, array &$seen = []): array
{
    if (isset($seen[$datei])) return [];
    $seen[$datei] = true;
    $aus = [$datei];
    foreach (ladeanweisungen($datei) as $l) $aus = array_merge($aus, geladen($l['datei'], $seen));
    return $aus;
}

/** Funktionsdefinitionen (Namen, klein) einer Datei. */
function definitionen(string $datei): array
{
    $toks = token_get_all((string)file_get_contents($datei));
    $aus = [];
    foreach ($toks as $i => $t) {
        if (!is_array($t) || $t[0] !== T_FUNCTION) continue;
        for ($j = $i + 1; isset($toks[$j]); $j++) {
            if (is_array($toks[$j]) && $toks[$j][0] === T_WHITESPACE) continue;
            if (is_array($toks[$j]) && $toks[$j][0] === T_STRING) $aus[] = strtolower($toks[$j][1]);
            break;   // „function (“ ist eine anonyme Funktion
        }
    }
    return $aus;
}

// ---- 1. statisch ---------------------------------------------------------
$toks = token_get_all((string)file_get_contents($INDEX));
$anweisungen = ladeanweisungen($INDEX);
pruefe('Voraussetzung: index.php lädt Dateien vorab und spät (gefunden: ' . count($anweisungen) . ')',
    count($anweisungen) >= 5);
$eigene = definitionen($INDEX);
$intern = array_map('strtolower', get_defined_functions()['internal']);
$defCache = [];
$fehltBei = [];
$aufrufe = 0;
foreach ($toks as $i => $t) {
    if (!is_array($t) || $t[0] !== T_STRING) continue;
    // nächstes bedeutsames Zeichen „(“?
    $j = $i + 1;
    while (isset($toks[$j]) && is_array($toks[$j]) && $toks[$j][0] === T_WHITESPACE) $j++;
    if (($toks[$j] ?? null) !== '(') continue;
    // vorheriges bedeutsames Zeichen: kein Methoden-/Klassenaufruf, keine Definition
    $k = $i - 1;
    while ($k >= 0 && is_array($toks[$k]) && in_array($toks[$k][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $k--;
    $vor = $toks[$k] ?? null;
    if (is_array($vor) && in_array($vor[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST], true)) continue;
    $name = strtolower($t[1]);
    $aufrufe++;
    if (in_array($name, $intern, true) || in_array($name, $eigene, true)) continue;
    $ok = false;
    foreach ($anweisungen as $a) {
        if ($a['pos'] > $i) continue;          // erst später geladen
        foreach (geladen($a['datei']) as $d) {
            $defCache[$d] ??= definitionen($d);
            if (in_array($name, $defCache[$d], true)) { $ok = true; break 2; }
        }
    }
    if (!$ok) $fehltBei[] = $t[1] . '() in index.php:' . $t[2];
}
pruefe('Voraussetzung: Funktionsaufrufe in index.php gefunden (' . $aufrufe . ')', $aufrufe > 100);
pruefe('jeder Funktionsaufruf in index.php ist an seiner Stelle schon definiert'
    . ($fehltBei === [] ? '' : ' – fehlt: ' . implode(', ', array_unique($fehltBei))), $fehltBei === []);

// ---- 2. ausgeführt: /api/schueler-gruppen in Betriebsladereihenfolge ------
// Die Dateien, die index.php vor der Route lädt – aus index.php gelesen.
$quelle = (string)file_get_contents($INDEX);
$routePos = strpos($quelle, "if ((\$seg[0] ?? '') === 'schueler-gruppen') {");
$vorher = [];
$zeileRoute = $routePos === false ? 0 : substr_count(substr($quelle, 0, $routePos), "\n") + 1;
foreach ($anweisungen as $a) {
    $zeile = is_array($toks[$a['pos']]) ? $toks[$a['pos']][2] : 0;
    if ($zeile < $zeileRoute) $vorher[] = $a['datei'];
}
pruefe('Voraussetzung: Route gefunden, Dateien davor ermittelt (' . count($vorher) . ')',
    $routePos !== false && count($vorher) >= 5 && !in_array($API . '/buchungen.php', $vorher, true));

// Zweig roh bis zur passenden Klammer.
$zweig = '';
if ($routePos !== false) {
    $tz = token_get_all('<?php ' . substr($quelle, $routePos));
    $tiefe = 0;
    foreach (array_slice($tz, 1) as $t) {
        $txt = is_array($t) ? $t[1] : $t;
        $zweig .= $txt;
        if ($txt === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $tiefe++;
        if ($txt === '}') { $tiefe--; if ($tiefe === 0) break; }
    }
}
// Eigener Prozess: lädt NUR $vorher (wie der Betrieb), ersetzt Ausgabe und
// Datenbank, setzt eine Verwaltungssitzung und führt den Zweig aus.
$skript = '<?php
$_SERVER["REQUEST_METHOD"] = "GET"; $_SERVER["REQUEST_URI"] = "/api/schueler-gruppen";
' . implode("\n", array_map(fn($d) => 'require_once ' . var_export($d, true) . ';',
        array_filter($vorher, fn($d) => basename($d) !== 'bootstrap.php'))) . '
';
// bootstrap.php liefert json_ok/json_err/db – hier ersetzt (Ausgabe, SQLite).
$ersatz = '<?php
final class Antwort extends Exception { public function __construct(public int $status, public array $daten) { parent::__construct("a"); } }
function json_ok(array $d, int $s = 200): never { throw new Antwort($s, $d); }
function json_err(string $m, int $s = 400): never { throw new Antwort($s, ["fehler" => $m]); }
function db(array $cfg): PDO { $p = new PDO("sqlite::memory:", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $p->exec("CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)"); return $p; }
function req(array $b, string $k): string { return (string)($b[$k] ?? ""); }
';
$tmp = sys_get_temp_dir() . '/sprechtag-lade-' . getmypid();
@mkdir($tmp);
file_put_contents("$tmp/ersatz.php", $ersatz);
$lauf = str_replace('<?php', '<?php declare(strict_types=1); require ' . var_export("$tmp/ersatz.php", true) . ';', $skript) . '
$methode = "GET"; $seg = ["schueler-gruppen"]; $body = []; $cfg = [];
$_SESSION = ["rolle" => "admin", "name" => "", "kinder" => [], "wu_gruppe" => "Lehrkräfte"];
try { ' . $zweig . ' } catch (Antwort $a) { echo "ANTWORT " . $a->status . " " . json_encode($a->daten, JSON_UNESCAPED_UNICODE); }
';
file_put_contents("$tmp/lauf.php", $lauf);
$ausgabe = []; $rc = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/lauf.php") . ' 2>&1', $ausgabe, $rc);
$text = implode("\n", $ausgabe);
@unlink("$tmp/lauf.php"); @unlink("$tmp/ersatz.php"); @rmdir($tmp);
pruefe('GET /api/schueler-gruppen in Betriebsladereihenfolge: Antwort 200 mit Gruppenliste'
    . (str_starts_with($text, 'ANTWORT 200') ? '' : ' – ' . substr(preg_replace('/\s+/', ' ', $text), 0, 160)),
    $zweig !== '' && str_starts_with($text, 'ANTWORT 200') && str_contains($text, '"gruppen":[]')
    && str_contains($text, '"eigene_gruppe":"Lehrkräfte"'));

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
