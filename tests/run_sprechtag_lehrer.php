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

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
