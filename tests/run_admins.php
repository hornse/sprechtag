<?php
// ============================================================
// tests/run_admins.php – wer Administrator ist und woher (v0.9.98, E29)
// Aufruf: php tests/run_admins.php   → Exit-Code 0 = alles grün
//
// Entscheidung des Betreibers (E29): admin_kuerzel in config.php ist der
// NOTZUGANG, alles Weitere wird über die Oberfläche in app_admins gepflegt;
// das WebUntis-Admin-Konto (personType 16) bleibt ohne Bedingung Admin und
// ist als dritte Herkunft sichtbar. Die Rolle wird bei jedem Aufruf einer
// Admin-Sitzung neu geprüft, nicht nur bei der Anmeldung. POST lehnt Kürzel
// ohne Stammsatz und Kürzel aus dem Notzugang ab. Verglichen wird in PHP mit
// wu_kuerzel_gleich(), nicht in SQL – sonst hinge das Ergebnis an der
// Kollation (MariaDB unempfindlich, SQLite empfindlich).
//
// Teil 1 FÜHRT die Route GET/POST/DELETE /api/admins AUS: der Zweig aus
// index.php in einem eigenen Prozess (Vorbild run_anmeldebremse.php), mit
// der echten auth.php, webuntis_adapter.php und admins.php gegen eine
// SQLite-Datei. Ersetzt sind Ausgabe (json_ok/json_err werfen) und db().
// Teil 2 führt die Nachprüfung der Sitzung aus: der Vorspann von index.php
// vor der ersten Route, danach GET /api/auth/me und GET /api/admins.
// Teil 3 führt adm_herkunft() aus. Teil 4 ist Quelltext: wu_login fragt
// adm_herkunft, app_admins wird nur in admins.php gelesen, und die
// Nachprüfung steht vor der ersten Route (Reihenfolge, Rückfallstufe – die
// Wirkung belegt Teil 2).
//
// Die Testtabelle app_admins hat einen eindeutigen Schlüssel OHNE Rücksicht
// auf Groß- und Kleinschreibung wie im Betrieb (utf8mb4_unicode_ci) – die
// Prüfumgebung soll nicht großzügiger sein als der Betrieb.
//
// Kürzel und Namen ERFUNDEN.
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

$tmp = sys_get_temp_dir() . '/sprechtag-admins-' . getmypid();
@mkdir($tmp);
$DB = "$tmp/probe.sqlite";

/** Frischer Bestand: Stammdaten, Notzugang ['aq', 'Zq'] (Zq ohne Stammsatz), Tabelle Bq, cq, AQ. */
function bestand(string $db): PDO
{
    @unlink($db);
    $p = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $p->exec('CREATE TABLE lehrer (id INTEGER PRIMARY KEY AUTOINCREMENT, webuntis_id INTEGER NOT NULL,
        kuerzel TEXT NOT NULL, name TEXT NOT NULL DEFAULT \'\', aktiv INTEGER NOT NULL DEFAULT 1)');
    $p->exec('CREATE TABLE app_admins (id INTEGER PRIMARY KEY AUTOINCREMENT,
        lehrer_kuerzel TEXT NOT NULL, anzeigename TEXT NOT NULL DEFAULT \'\',
        angelegt_von TEXT NOT NULL DEFAULT \'\', angelegt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (lehrer_kuerzel COLLATE NOCASE))');
    $l = $p->prepare('INSERT INTO lehrer (webuntis_id, kuerzel, name) VALUES (?, ?, ?)');
    foreach ([[901, 'Aq', 'Anna Erfunden'], [902, 'Bq', 'Berta Erfunden'], [903, 'Cq', 'Carl Erfunden'],
              [904, 'Dq', 'Dora Erfunden'], [905, 'Eq', 'Emil Erfunden']] as $z) $l->execute($z);
    $p->exec("UPDATE lehrer SET aktiv = 0 WHERE kuerzel = 'Eq'");
    $a = $p->prepare('INSERT INTO app_admins (lehrer_kuerzel, anzeigename, angelegt_von) VALUES (?, ?, ?)');
    foreach ([['Bq', 'Berta Erfunden', 'Aq'], ['cq', 'Carl Erfunden', 'Aq'], ['AQ', 'Anna Erfunden', 'Aq']] as $z) $a->execute($z);
    return $p;
}
$tabelle = fn(PDO $p): array => $p->query('SELECT id, lehrer_kuerzel, anzeigename, angelegt_von
    FROM app_admins ORDER BY id')->fetchAll();
$idVon = function (PDO $p, string $k): int {
    $st = $p->prepare('SELECT id FROM app_admins WHERE lehrer_kuerzel = ?');
    $st->execute([$k]);
    return (int)$st->fetchColumn();
};

$ersatz = '<?php
final class Antwort extends Exception { public function __construct(public int $status, public array $daten) { parent::__construct("a"); } }
function json_ok(array $d, int $s = 200): never { throw new Antwort($s, $d); }
function json_err(string $m, int $s = 400): never { throw new Antwort($s, ["fehler" => $m]); }
function req(array $b, string $k): string { $w = trim((string)($b[$k] ?? "")); if ($w === "") json_err("Feld \'$k\' fehlt"); return $w; }
$GLOBALS["spur"] = [];
function db(array $cfg): PDO { $GLOBALS["spur"][] = "db"; static $p = null;
    return $p ??= new PDO("sqlite:" . $cfg["db"]["dsn"], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
';
file_put_contents("$tmp/ersatz.php", $ersatz);

/**
 * Führt Quelltext aus index.php aus. $sitzung: null = nicht angemeldet.
 * $notzugang: admin_kuerzel der Probe-config.
 */
function lauf(string $tmp, string $db, string $code, string $api, string $methode, array $seg, ?array $sitzung,
              array $body = [], array $notzugang = ['aq', 'Zq']): array
{
    $admins = "$api/admins.php";
    $skript = '<?php declare(strict_types=1);
require ' . var_export("$tmp/ersatz.php", true) . ';
require_once ' . var_export("$api/auth.php", true) . ';
require_once ' . var_export("$api/webuntis_adapter.php", true) . ';
if (is_file(' . var_export($admins, true) . ')) require_once ' . var_export($admins, true) . ';
$methode = ' . var_export($methode, true) . '; $seg = ' . var_export($seg, true) . ';
$body = ' . var_export($body, true) . ';
$cfg = ["db" => ["dsn" => ' . var_export($db, true) . '], "webuntis" => ["admin_kuerzel" => ' . var_export($notzugang, true) . ']];
$_SESSION = ' . var_export($sitzung ?? [], true) . ';
try { ' . $code . ' echo "KEINE ANTWORT"; }
catch (Antwort $a) { echo "ANTWORT " . $a->status . " " . json_encode($a->daten, JSON_UNESCAPED_UNICODE); }
echo "\nSPUR " . implode(",", $GLOBALS["spur"]);
echo "\nROLLE " . ($_SESSION["rolle"] ?? "-");
';
    file_put_contents("$tmp/lauf.php", $skript);
    $aus = []; $rc = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/lauf.php") . ' 2>&1', $aus, $rc);
    $text = implode("\n", $aus);
    preg_match('/^ANTWORT (\d+) (.*)$/m', $text, $m);
    preg_match('/^SPUR (.*)$/m', $text, $s);
    preg_match('/^ROLLE (.*)$/m', $text, $r);
    return ['status' => (int)($m[1] ?? 0), 'daten' => json_decode($m[2] ?? 'null', true),
        'spur' => isset($s[1]) && $s[1] !== '' ? explode(',', $s[1]) : [], 'rolle' => $r[1] ?? '?', 'roh' => $text];
}
$zeig = fn(array $r): string => $r['status'] . ' ' . substr(preg_replace('/\s+/', ' ', $r['roh']), 0, 160);
$fehlerText = fn(array $r): string => (string)($r['daten']['fehler'] ?? '');
$lehrkraft = fn(string $k): array => ['rolle' => 'admin', 'name' => 'N', 'kuerzel' => $k, 'wu_admin_konto' => false];

// ---- 1. Route /api/admins, ausgeführt --------------------------------------
$route = zweig($quelle, "if ((\$seg[0] ?? '') === 'admins') {");
pruefe('Voraussetzung: Route /api/admins genau einmal gefunden', $route !== '');

echo "GET: eine Liste, drei Herkünfte\n";
$p = bestand($DB);
$r = lauf($tmp, $DB, $route, $API, 'GET', ['admins'], $lehrkraft('Bq'));
$liste = (array)($r['daten']['admins'] ?? []);
$her = array_map(fn($e) => (string)($e['herkunft'] ?? '?') . ':' . (string)($e['kuerzel'] ?? '-'), $liste);
pruefe('GET: 200, Reihenfolge WebUntis-Konto, Notzugang in config-Reihenfolge, dann Oberfläche – ' . implode(' ', $her)
    . ' – ' . $zeig($r),
    $r['status'] === 200 && $her === ['webuntis:-', 'notzugang:Aq', 'notzugang:Zq', 'oberflaeche:Bq', 'oberflaeche:Cq']);
$nach = [];
foreach ($liste as $e) $nach[(string)($e['herkunft'] ?? '?') . ':' . (string)($e['kuerzel'] ?? '-')] = $e;
pruefe('WebUntis-Konto: nicht entfernbar, ohne Tabellen-Id',
    ($nach['webuntis:-']['entfernbar'] ?? null) === false && array_key_exists('id', $nach['webuntis:-'] ?? [])
    && $nach['webuntis:-']['id'] === null);
pruefe('Notzugang trägt die Schreibweise und den Namen des Stammsatzes (config „aq“ → Aq, Anna Erfunden)',
    ($nach['notzugang:Aq']['name'] ?? null) === 'Anna Erfunden' && ($nach['notzugang:Aq']['ohne_stammsatz'] ?? null) === false);
pruefe('Notzugang ohne Stammsatz ist als solcher gekennzeichnet (Zq), nicht entfernbar',
    ($nach['notzugang:Zq']['ohne_stammsatz'] ?? null) === true && ($nach['notzugang:Zq']['entfernbar'] ?? null) === false
    && ($nach['notzugang:Zq']['name'] ?? null) === '');
pruefe('Kürzel in beiden Listen (AQ): einmal, als Notzugang, mit Tabellen-Id, entfernbar, auch_oberflaeche',
    count(array_filter($liste, fn($e) => strcasecmp((string)($e['kuerzel'] ?? ''), 'aq') === 0)) === 1
    && ($nach['notzugang:Aq']['id'] ?? null) === $idVon($p, 'AQ') && ($nach['notzugang:Aq']['entfernbar'] ?? null) === true
    && ($nach['notzugang:Aq']['auch_oberflaeche'] ?? null) === true
    && ($nach['notzugang:Zq']['auch_oberflaeche'] ?? null) === false);
pruefe('Oberfläche: eigener Eintrag (Bq) selbst und nicht entfernbar, fremder (cq → Cq) entfernbar, je mit Id',
    ($nach['oberflaeche:Bq']['selbst'] ?? null) === true && ($nach['oberflaeche:Bq']['entfernbar'] ?? null) === false
    && ($nach['oberflaeche:Cq']['selbst'] ?? null) === false && ($nach['oberflaeche:Cq']['entfernbar'] ?? null) === true
    && ($nach['oberflaeche:Bq']['id'] ?? null) === $idVon($p, 'Bq') && ($nach['oberflaeche:Cq']['id'] ?? null) === $idVon($p, 'cq'));
$r = lauf($tmp, $DB, $route, $API, 'GET', ['admins'], ['rolle' => 'lehrkraft', 'name' => 'N', 'kuerzel' => 'Dq']);
pruefe('GET als Lehrkraft: 403, keine Datenbank – ' . $zeig($r), $r['status'] === 403 && $r['spur'] === []);

echo "POST: nur Lehrkräfte mit Stammsatz, nicht der Notzugang\n";
$p = bestand($DB);
$vorher = $tabelle($p);
$r = lauf($tmp, $DB, $route, $API, 'POST', ['admins'], $lehrkraft('Bq'), ['kuerzel' => 'Xq']);
pruefe('Kürzel ohne Stammsatz: 404 „gibt es … nicht“, Tabelle unverändert – ' . $zeig($r),
    $r['status'] === 404 && str_contains($fehlerText($r), 'gibt es') && $tabelle($p) === $vorher);
$r = lauf($tmp, $DB, $route, $API, 'POST', ['admins'], $lehrkraft('Bq'), ['kuerzel' => 'eq']);
pruefe('Kürzel mit stillgelegtem Stammsatz (aktiv = 0): ebenso 404 – ' . $zeig($r),
    $r['status'] === 404 && $tabelle($p) === $vorher);
$r = lauf($tmp, $DB, $route, $API, 'POST', ['admins'], $lehrkraft('Bq'), ['kuerzel' => 'zQ']);
pruefe('Kürzel aus dem Notzugang (zQ, auch ohne Stammsatz): 409 „Notzugang“, Tabelle unverändert – ' . $zeig($r),
    $r['status'] === 409 && str_contains($fehlerText($r), 'Notzugang') && $tabelle($p) === $vorher);
$r = lauf($tmp, $DB, $route, $API, 'POST', ['admins'], $lehrkraft('Bq'), ['kuerzel' => 'aQ']);
pruefe('Kürzel aus dem Notzugang mit Stammsatz (aQ): ebenso 409 – ' . $zeig($r),
    $r['status'] === 409 && $tabelle($p) === $vorher);
$r = lauf($tmp, $DB, $route, $API, 'POST', ['admins'], $lehrkraft('Bq'), ['kuerzel' => ' dq ']);
$neu = array_values(array_filter($tabelle($p), fn($z) => strcasecmp($z['lehrer_kuerzel'], 'dq') === 0));
pruefe('Lehrkraft mit Stammsatz (eingegeben „ dq “): 200, eingetragen in der Schreibweise des Stammsatzes, mit Name und Urheber – '
    . $zeig($r),
    $r['status'] === 200 && count($neu) === 1 && $neu[0]['lehrer_kuerzel'] === 'Dq'
    && $neu[0]['anzeigename'] === 'Dora Erfunden' && $neu[0]['angelegt_von'] === 'Bq');
$zwischen = $tabelle($p);
$r = lauf($tmp, $DB, $route, $API, 'POST', ['admins'], $lehrkraft('Bq'), ['kuerzel' => 'DQ']);
pruefe('dasselbe Kürzel noch einmal (DQ): 200 mit Hinweis „bereits“, kein zweiter Eintrag – ' . $zeig($r),
    $r['status'] === 200 && str_contains((string)($r['daten']['hinweis'] ?? ''), 'bereits') && $tabelle($p) === $zwischen);

echo "DELETE: Wache, Notzugang, der letzte Eintrag\n";
$p = bestand($DB);
$vorher = $tabelle($p);
$r = lauf($tmp, $DB, $route, $API, 'DELETE', ['admins', (string)$idVon($p, 'Bq')], $lehrkraft('BQ'));
pruefe('eigener Eintrag (Sitzung BQ, Eintrag Bq): 403 „selbst“, bleibt – ' . $zeig($r),
    $r['status'] === 403 && str_contains($fehlerText($r), 'selbst') && $tabelle($p) === $vorher);
$r = lauf($tmp, $DB, $route, $API, 'DELETE', ['admins', '9999'], $lehrkraft('Bq'));
pruefe('unbekannte Id: 404, Tabelle unverändert – ' . $zeig($r), $r['status'] === 404 && $tabelle($p) === $vorher);
$r = lauf($tmp, $DB, $route, $API, 'DELETE', ['admins', (string)$idVon($p, 'AQ')], $lehrkraft('Bq'));
$h = implode(' ', (array)($r['daten']['hinweise'] ?? []));
pruefe('Eintrag, der auch Notzugang ist (AQ): 200, entfernt, Hinweis „bleibt Administrator … Notzugang“ – ' . $zeig($r),
    $r['status'] === 200 && $idVon($p, 'AQ') === 0 && str_contains($h, 'bleibt Administrator') && str_contains($h, 'Notzugang'));
$r = lauf($tmp, $DB, $route, $API, 'DELETE', ['admins', (string)$idVon($p, 'cq')], $lehrkraft('Bq'));
pruefe('fremder Eintrag (cq), danach bleibt Bq: 200, entfernt, kein Hinweis – ' . $zeig($r),
    $r['status'] === 200 && $idVon($p, 'cq') === 0 && (array)($r['daten']['hinweise'] ?? ['?']) === []);
$r = lauf($tmp, $DB, $route, $API, 'DELETE', ['admins', (string)$idVon($p, 'Bq')], $lehrkraft('Aq'));
$h = implode(' ', (array)($r['daten']['hinweise'] ?? []));
pruefe('letzter Eintrag der Oberfläche (Bq, durch Notzugang Aq): 200, Hinweis „niemand mehr“, nennt Notzugang und WebUntis-Konto – '
    . $zeig($r),
    $r['status'] === 200 && $idVon($p, 'Bq') === 0 && str_contains($h, 'niemand mehr')
    && str_contains($h, 'Notzugang') && str_contains($h, 'WebUntis'));
$p = bestand($DB);
$p->exec("DELETE FROM app_admins WHERE lehrer_kuerzel = 'cq'");
$r = lauf($tmp, $DB, $route, $API, 'DELETE', ['admins', (string)$idVon($p, 'Bq')], $lehrkraft('Aq'));
$h = implode(' ', (array)($r['daten']['hinweise'] ?? []));
pruefe('letzter Eintrag der Oberfläche, während AQ (auch Notzugang) in der Tabelle bleibt: Hinweis „niemand mehr“ trotzdem – '
    . $zeig($r),
    $r['status'] === 200 && $idVon($p, 'AQ') > 0 && str_contains($h, 'niemand mehr'));
$p = bestand($DB);
$p->exec("DELETE FROM app_admins WHERE lehrer_kuerzel IN ('cq', 'AQ')");
$r = lauf($tmp, $DB, $route, $API, 'DELETE', ['admins', (string)$idVon($p, 'Bq')],
    ['rolle' => 'admin', 'name' => 'N', 'kuerzel' => null, 'wu_admin_konto' => true], [], []);
$h = implode(' ', (array)($r['daten']['hinweise'] ?? []));
pruefe('… ohne Notzugang in der config (durch das WebUntis-Konto): der Hinweis sagt, dass nur das WebUntis-Konto bleibt – '
    . $zeig($r),
    $r['status'] === 200 && str_contains($h, 'niemand mehr') && str_contains($h, 'nur') && str_contains($h, 'WebUntis')
    && !str_contains($h, 'Notzugang (admin_kuerzel'));

// ---- 2. Nachprüfung der Sitzung, ausgeführt --------------------------------
echo "Rolle bei jedem Aufruf neu geprüft\n";
$ab = strpos($quelle, '$body    = in_array(');
$bis = strpos($quelle, '// ---- GET /api/health');
$start = $ab === false ? false : strpos($quelle, "\n", $ab);
$vorspann = ($start !== false && $bis !== false && $start < $bis) ? substr($quelle, $start + 1, $bis - $start - 1) : '';
pruefe('Voraussetzung: Vorspann von index.php zwischen $body und der ersten Route gefunden', $vorspann !== '');
$me = zweig($quelle, "if ((\$seg[0] ?? '') === 'auth') {");
pruefe('Voraussetzung: Zweig /api/auth gefunden', $me !== '');

$p = bestand($DB);
$p->exec("DELETE FROM app_admins WHERE lehrer_kuerzel = 'cq'");
$r = lauf($tmp, $DB, $vorspann . $me, $API, 'GET', ['auth', 'me'], $lehrkraft('Cq'));
pruefe('entfernt (Cq) während der Sitzung: /api/auth/me meldet danach lehrkraft – ' . $zeig($r),
    $r['status'] === 200 && ($r['daten']['rolle'] ?? null) === 'lehrkraft' && $r['rolle'] === 'lehrkraft');
$r = lauf($tmp, $DB, $vorspann . $route, $API, 'GET', ['admins'], $lehrkraft('Cq'));
pruefe('… und GET /api/admins antwortet 403 – ' . $zeig($r), $r['status'] === 403);
$r = lauf($tmp, $DB, $vorspann . $me, $API, 'GET', ['auth', 'me'], $lehrkraft('BQ'));
pruefe('weiter eingetragen (Sitzung BQ, Eintrag Bq): bleibt admin – ' . $zeig($r),
    ($r['daten']['rolle'] ?? null) === 'admin');
$r = lauf($tmp, $DB, $vorspann . $me, $API, 'GET', ['auth', 'me'], $lehrkraft('Zq'));
pruefe('Notzugang (Zq, nicht in der Tabelle): bleibt admin – ' . $zeig($r), ($r['daten']['rolle'] ?? null) === 'admin');
$r = lauf($tmp, $DB, $vorspann . $me, $API, 'GET', ['auth', 'me'],
    ['rolle' => 'admin', 'name' => 'N', 'kuerzel' => null, 'wu_admin_konto' => true], [], []);
pruefe('WebUntis-Konto ohne Kürzel und ohne Notzugang in der config: bleibt admin – ' . $zeig($r),
    ($r['daten']['rolle'] ?? null) === 'admin');
$r = lauf($tmp, $DB, $vorspann . $me, $API, 'GET', ['auth', 'me'],
    ['rolle' => 'lehrkraft', 'name' => 'N', 'kuerzel' => 'Bq']);
pruefe('Lehrkraft, die eingetragen ist: wird nicht während der Sitzung befördert, keine Datenbank – ' . $zeig($r),
    ($r['daten']['rolle'] ?? null) === 'lehrkraft' && $r['spur'] === []);
$r = lauf($tmp, $DB, $vorspann . $me, $API, 'GET', ['auth', 'me'], null);
pruefe('nicht angemeldet: keine Datenbank – ' . $zeig($r), $r['spur'] === [] && ($r['daten']['angemeldet'] ?? null) === false);

// ---- 3. adm_herkunft, ausgeführt -------------------------------------------
echo "Herkunft\n";
require_once __DIR__ . '/../backend/helfer.php';
require_once $API . '/auth.php';
require_once $API . '/webuntis_adapter.php';
if (is_file($API . '/admins.php')) require_once $API . '/admins.php';
pruefe('Voraussetzung: adm_herkunft vorhanden', function_exists('adm_herkunft'));
if (function_exists('adm_herkunft')) {
    $p = bestand($DB);
    $w = ['admin_kuerzel' => ['aq', 'Zq']];
    pruefe('WebUntis-Konto: webuntis, auch ohne Kürzel und ohne config',
        adm_herkunft($p, [], null, true) === 'webuntis' && adm_herkunft($p, $w, 'Bq', true) === 'webuntis');
    pruefe('Notzugang geht vor der Tabelle (AQ steht in beiden): notzugang',
        adm_herkunft($p, $w, 'AQ', false) === 'notzugang' && adm_herkunft($p, $w, 'zq', false) === 'notzugang');
    pruefe('Tabelle, Vergleich in PHP (Eintrag cq, Sitzung CQ – SQLite vergliche empfindlich): oberflaeche',
        adm_herkunft($p, $w, 'CQ', false) === 'oberflaeche' && adm_herkunft($p, $w, 'Bq', false) === 'oberflaeche');
    pruefe('nirgends (Dq), leer, null: kein Admin',
        adm_herkunft($p, $w, 'Dq', false) === null && adm_herkunft($p, $w, '', false) === null
        && adm_herkunft($p, $w, null, false) === null);
}

// ---- 4. Quelltext: Aufrufstellen, Engstelle, Reihenfolge -------------------
echo "Aufrufstellen und Engstelle (Quelltext)\n";
$ad = ohne_kommentar((string)file_get_contents($API . '/webuntis_adapter.php'));
pruefe('wu_login: eine Lehrkraft wird Admin über adm_herkunft (personType 2, ohne WebUntis-Konto)',
    str_contains($ad, "adm_herkunft(\$pdo, \$wcfg, \$ergebnis['kuerzel'], false) !== null"));
$dateien = array_merge(glob(__DIR__ . '/../backend/*.php'), glob(__DIR__ . '/../backend/*/*.php'));
$wo = [];
foreach ($dateien as $f) {
    if (preg_match('/config(\.example)?\.php$/', $f)) continue;
    if (str_contains(ohne_kommentar((string)file_get_contents($f)), 'app_admins')) $wo[] = basename($f);
}
pruefe('Engstelle: app_admins steht nur in admins.php (gefunden in: ' . implode(', ', $wo) . ')', $wo === ['admins.php']);
$ix = ohne_kommentar($quelle);
$aufruf = 'adm_sitzung_nachpruefen($cfg);';
$erste = strpos($ix, "if (\$methode === 'GET' && (\$seg[0] ?? '') === 'health')");
pruefe('Nachprüfung genau einmal in index.php, vor der ersten Route (Reihenfolge; die Wirkung belegt Teil 2)',
    substr_count($ix, $aufruf) === 1 && $erste !== false && strpos($ix, $aufruf) < $erste
    && preg_match("/require_once __DIR__ \\. '\\/admins\\.php';/", $ix) === 1);

@unlink("$tmp/lauf.php"); @unlink("$tmp/ersatz.php"); @unlink($DB); @rmdir($tmp);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
