<?php
// ============================================================
// tests/run_sprechtag_lehrer.php – GET /api/sprechtage/{id}/lehrer
// Aufruf: php tests/run_sprechtag_lehrer.php   → Exit-Code 0 = alles grün
//
// v0.9.60: Die Lehrkraft-Tabelle der Verwaltung („Aktiver Sprechtag“)
// liest l.halbtags aus dieser Antwort (Häkchen „½“, Hälfte-Auswahl) – die
// Abfrage lieferte das Feld nicht, das Häkchen stand immer leer.
//
// Ausgeführt wird die ABFRAGE AUS index.php selbst (per Tokenizer aus dem
// Zweig der Route gelesen), gegen SQLite mit dem Schema der gelesenen
// Spalten. Nicht gesucht wird, ob „halbtags“ irgendwo in der Datei steht:
// Es steht dort mehrfach, in anderen Abfragen.
//
// v0.9.61: Die Route verlangt die Verwaltung (vorher genügte jede Anmeldung
// – Eltern konnten Teilnahme, Anwesenheit und Bemerkung aller Lehrkräfte
// abrufen). Ausgeführt wird der GET-ZWEIG AUS index.php mit drei Rollen.
// Die Wächter auth_require()/auth_require_admin() sind hier ersetzt: Geprüft
// wird, welchen der Zweig ruft und ob vorher Daten hinausgehen – nicht die
// Wächter selbst (auth.php).
// Und GET /api/anzeige (öffentlich) liefert kein halbtags mehr: Die Anzeige
// liest es nicht. Ausgeführt wird deren Abfrage.
//
// Testdaten erfunden.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

// ---- Abfrage aus dem Zweig der Route lesen --------------------------
$quelle = (string)file_get_contents(__DIR__ . '/../backend/api/index.php');
$start = strpos($quelle, '// ---- Teilnehmende Lehrkräfte ----');
$ende  = $start === false ? false : strpos($quelle, "json_ok(['lehrer' => \$st->fetchAll()]);", $start);
$sql = null;
if ($start !== false && $ende !== false) {
    // Die erste Zeichenkette nach prepare( im Zweig ist die Abfrage.
    $toks = token_get_all('<?php ' . substr($quelle, $start, $ende - $start));
    $nachPrepare = false;
    foreach ($toks as $t) {
        if (is_array($t) && $t[0] === T_STRING && $t[1] === 'prepare') { $nachPrepare = true; continue; }
        if ($nachPrepare && is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
            $sql = stripcslashes(substr($t[1], 1, -1));
            break;
        }
    }
}
pruefe('Voraussetzung: Abfrage der Route im Quelltext gefunden',
    $sql !== null && str_contains($sql, 'FROM lehrer l'));
if ($sql === null) { echo "\n$fehler ROT\n"; exit(1); }

// ---- Ausführen --------------------------------------------------------
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
// Schema nach sql/02_sprechtag.sql und sql/11_halbtags.sql, gekürzt auf
// die gelesenen Spalten.
$pdo->exec('CREATE TABLE lehrer (id INTEGER PRIMARY KEY, kuerzel TEXT NOT NULL,
    name TEXT NOT NULL DEFAULT "", aktiv INT NOT NULL DEFAULT 1, halbtags INT NOT NULL DEFAULT 0)');
$pdo->exec('CREATE TABLE raeume (id INTEGER PRIMARY KEY, kuerzel TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sprechtag_lehrer (id INTEGER PRIMARY KEY, sprechtag_id INT NOT NULL,
    lehrer_id INT NOT NULL, anwesend_von TEXT NULL, anwesend_bis TEXT NULL,
    raum_id INT NULL, teilnahme INT NOT NULL DEFAULT 1, bemerkung TEXT NULL,
    UNIQUE (sprechtag_id, lehrer_id))');
$pdo->exec("INSERT INTO lehrer (id, kuerzel, name, aktiv, halbtags) VALUES
    (1, 'AAA', 'Erfunden Eins', 1, 1), (2, 'BBB', 'Erfunden Zwei', 1, 0)");
$pdo->exec("INSERT INTO raeume VALUES (1, 'R1')");
$pdo->exec("INSERT INTO sprechtag_lehrer (sprechtag_id, lehrer_id, raum_id) VALUES (7, 1, 1)");

$zeilen = [];
try {
    $st = $pdo->prepare($sql);
    $st->execute([7]);
    $zeilen = $st->fetchAll();
} catch (Throwable $e) {
    echo '    (Abfrage scheiterte: ' . $e->getMessage() . ")\n";
}
$jeKuerzel = [];
foreach ($zeilen as $z) $jeKuerzel[$z['kuerzel']] = $z;

pruefe('Abfrage läuft und liefert beide aktiven Lehrkräfte (mit und ohne Zuweisung)',
    count($zeilen) === 2 && isset($jeKuerzel['AAA'], $jeKuerzel['BBB']));
pruefe('jede Zeile führt halbtags',
    $zeilen !== [] && array_reduce($zeilen, fn ($ok, $z) => $ok && array_key_exists('halbtags', $z), true));
pruefe('Halbtagskraft 1, andere 0 (Wert aus lehrer.halbtags, nicht fest)',
    (int)($jeKuerzel['AAA']['halbtags'] ?? -1) === 1 && (int)($jeKuerzel['BBB']['halbtags'] ?? -1) === 0);

// ---- v0.9.61: Rolle -------------------------------------------------
// Ersatz für Wächter und Ausgabe: Jede Antwort endet als Ausnahme mit
// Status und Daten, wie json_ok()/json_err() den Lauf beenden.
final class Antwort extends Exception
{
    public function __construct(public int $status, public array $daten) { parent::__construct('antwort'); }
}
$ROLLE = null;
function json_ok(array $d, int $status = 200): never { throw new Antwort($status, $d); }
function json_err(string $m, int $status = 400): never { throw new Antwort($status, ['fehler' => $m]); }
function auth_require(): array
{
    global $ROLLE;
    if ($ROLLE === null) json_err('Nicht angemeldet', 401);
    return ['rolle' => $ROLLE];
}
function auth_require_admin(): array
{
    $u = auth_require();
    if ($u['rolle'] !== 'admin') json_err('nur Verwaltung', 403);
    return $u;
}

// Zweig „if ($methode === 'GET') { … }“ nach der Markierung der Route,
// roh bis zur passenden Klammer.
$zweig = '';
if ($start !== false) {
    $a = strpos($quelle, "if (\$methode === 'GET') {", $start);
    if ($a !== false && $a < (int)$ende) {
        $toks = token_get_all('<?php ' . substr($quelle, $a));
        $tiefe = 0; $teil = '';
        foreach (array_slice($toks, 1) as $t) {
            $txt = is_array($t) ? $t[1] : $t;
            $teil .= $txt;
            if ($txt === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $tiefe++;
            if ($txt === '}') { $tiefe--; if ($tiefe === 0) break; }
        }
        $zweig = $teil;
    }
}
pruefe('Voraussetzung: GET-Zweig der Route gelesen', $zweig !== '' && str_contains($zweig, 'json_ok'));
function lauf(string $zweig, ?string $rolle, PDO $pdo): Antwort
{
    global $ROLLE;
    $ROLLE = $rolle;
    $methode = 'GET'; $sid = 7;
    try { eval($zweig); } catch (Antwort $a) { return $a; }
    return new Antwort(0, []);
}
$el = lauf($zweig, 'eltern', $pdo);
$lk = lauf($zweig, 'lehrkraft', $pdo);
$ad = lauf($zweig, 'admin', $pdo);
pruefe('Eltern: abgewiesen (403), keine Lehrkraftdaten',
    $el->status === 403 && !array_key_exists('lehrer', $el->daten));
pruefe('Lehrkraft: abgewiesen (403) – keine Ansicht der Lehrkräfte ruft die Liste',
    $lk->status === 403 && !array_key_exists('lehrer', $lk->daten));
pruefe('Verwaltung: bekommt die Liste',
    $ad->status === 200 && count($ad->daten['lehrer'] ?? []) === 2);

// ---- v0.9.61: /api/anzeige ohne halbtags ------------------------------
$aStart = strpos($quelle, '// ---- GET /api/anzeige');
$aSql = null;
if ($aStart !== false) {
    $toks = token_get_all('<?php ' . substr($quelle, $aStart, (int)strpos($quelle, "'lehrer' => \$st->fetchAll(),", $aStart) - $aStart));
    $nachPrepare = false;
    foreach ($toks as $t) {
        if (is_array($t) && $t[0] === T_STRING && $t[1] === 'prepare') { $nachPrepare = true; continue; }
        if ($nachPrepare && is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
            $aSql = stripcslashes(substr($t[1], 1, -1)); break;
        }
    }
}
// Die Anzeige liest r.name; das Schema oben kennt es noch nicht.
$pdo->exec('ALTER TABLE raeume ADD COLUMN name TEXT NULL');
$aZeilen = [];
try {
    // ORDER BY wird im Code angehängt; die Abfrage endet mit „ORDER BY “.
    $st = $pdo->prepare($aSql . 'l.kuerzel');
    $st->execute([7]);
    $aZeilen = $st->fetchAll();
} catch (Throwable $e) { echo '    (Anzeige-Abfrage scheiterte: ' . $e->getMessage() . ")\n"; }
pruefe('Voraussetzung: Anzeige-Abfrage gelesen und ausgeführt (1 teilnehmende Lehrkraft)',
    $aSql !== null && count($aZeilen) === 1);
pruefe('Anzeige (öffentlich) liefert kein halbtags',
    $aZeilen !== [] && !array_key_exists('halbtags', $aZeilen[0]));
pruefe('… aber alles, was die Anzeige liest (Kürzel, Name, Raum, Anwesenheit)',
    $aZeilen !== [] && count(array_intersect(['kuerzel', 'name', 'raum_kuerzel', 'anwesend_von', 'anwesend_bis'],
        array_keys($aZeilen[0]))) === 5);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
