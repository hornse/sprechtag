<?php
// ============================================================
// tests/run_schuelerliste_abbau.php – die alte Schülerliste ist fort
// Aufruf: php tests/run_schuelerliste_abbau.php   → Exit-Code 0 = alles grün
//
// v0.9.82 (Zug 4, Schritt 4, E20): Schild-CSV-Import, Austrittsdatum,
// getStudents-Abgleich mit eingetippten Zugangsdaten und die Admin-Seite
// „Schülerliste" entfallen; die Tabelle schueler fällt mit Migration 23.
// Die Engstelle „keine Datei unter backend/ nennt schueler als Tabelle"
// steht in run_kindname.php – hier steht, was mit der Tabelle wegging.
//
// Was bleibt: die Sondierung (Diagnose, speichert nichts, abschaltbar –
// Betreiber 10.10.2026, F4) und getStudents im vendorten Client.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

/** PHP-Code ohne Kommentare (Zeichenketten bleiben – dort steht SQL). */
function ohne_kommentare(string $pfad): string
{
    $c = '';
    foreach (token_get_all((string)file_get_contents($pfad)) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
}

$WURZEL = (string)realpath(__DIR__ . '/..');
$API = $WURZEL . '/backend/api';

// ------------------------------------------------------------
echo "Backend: Routen und Dateien\n";
$ix = ohne_kommentare($API . '/index.php');
pruefe('Voraussetzung: index.php gelesen, Route /api/schueler-gruppen (E15) gefunden',
    str_contains($ix, "(\$seg[0] ?? '') === 'schueler-gruppen'"));
$liste = scandir($API) ?: [];
pruefe('Voraussetzung: backend/api gelistet (index.php dabei)', in_array('index.php', $liste, true));
pruefe('backend/api/schueler.php gibt es nicht mehr', !in_array('schueler.php', $liste, true));
pruefe('index.php lädt schueler.php nicht mehr', preg_match('/schueler\.php/', $ix) === 0);
pruefe('kein Routenzweig für /api/schueler (GET, csv, sync, DELETE)',
    preg_match("/\\\$seg\\[0\\][^;{]{0,20}===\\s*'schueler'(?![\\w-])/", $ix) === 0);

$backend = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($WURZEL . '/backend', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) $backend[] = $f->getPathname();
sort($backend);
$definiert = []; $rufen = [];
foreach ($backend as $p) {
    $c = ohne_kommentare($p);
    $rel = substr($p, strlen($WURZEL) + 1);
    if (preg_match('/function\s+schueler_(csv_parsen|csv_importieren|datum_normieren|ist_aktiv|klasse_normieren|webuntis_sync|liste)\b/', $c)) {
        $definiert[] = $rel;
    }
    if (preg_match('/->getStudents\s*\(/', $c)) $rufen[] = $rel;
}
pruefe('Voraussetzung: PHP-Dateien unter backend/ gefunden (mind. 15)', count($backend) >= 15);
pruefe('keine Funktion des alten Imports, Austritts oder Abgleichs unter backend/ definiert', $definiert === []);
if ($definiert !== []) echo '    (' . implode(', ', $definiert) . ")\n";
pruefe('getStudents wird nur noch in der Sondierung gerufen (der Abgleich mit eingetippten Zugangsdaten ist fort)',
    $rufen === ['backend/api/sondierung.php']);
if ($rufen !== ['backend/api/sondierung.php']) echo '    (' . implode(', ', $rufen) . ")\n";

// ------------------------------------------------------------
echo "Migration 23: Prüfdatei (gegen SQLite AUSGEFÜHRT)\n";
// Die Prüfdatei ist im gemeinsamen Teil von MariaDB und SQLite geschrieben
// (CASE statt IF, CONCAT, TRIM, COALESCE) und läuft hier gegen ERFUNDENE
// Zeilen. Bekannter Unterschied, hier nicht abbildbar: MariaDB vergleicht
// '' und '   ' als gleich (PAD SPACE). kind_name wird getrimmt geschrieben.
$SQL = $WURZEL . '/sql';
$sqlListe = scandir($SQL) ?: [];
pruefe('Voraussetzung: sql/23_pruefung.sql und sql/23_schueler_entfernen.sql gelistet',
    in_array('23_pruefung.sql', $sqlListe, true) && in_array('23_schueler_entfernen.sql', $sqlListe, true));
$ohneSqlKommentar = fn(string $t): string => (string)preg_replace('/^\s*--.*$/m', '', $t);
$pruefRoh = (string)@file_get_contents($SQL . '/23_pruefung.sql');
$pruef = $ohneSqlKommentar($pruefRoh);
pruefe('Prüfdatei liest nur (kein UPDATE, DELETE, INSERT, DROP, ALTER, CREATE, TRUNCATE, REPLACE, RENAME, SET)',
    trim($pruef) !== '' && preg_match('/\b(UPDATE|DELETE|INSERT|DROP|ALTER|CREATE|TRUNCATE|REPLACE|RENAME|SET)\b/i', $pruef) === 0);

/** Testdatenbank mit nur den Spalten, die die Dateien lesen dürfen. */
function abbau_db(array $schueler, array $buchungen, array $einladungen, array $mitteilungen): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE schueler (id INTEGER PRIMARY KEY, webuntis_id INT UNIQUE,
        vorname TEXT NOT NULL DEFAULT "", nachname TEXT NOT NULL DEFAULT "", klasse TEXT NOT NULL DEFAULT "")');
    foreach (['buchungen' => 'NOT NULL', 'einladungen' => 'NOT NULL', 'mitteilungen' => 'NULL'] as $t => $n) {
        $pdo->exec("CREATE TABLE $t (id INTEGER PRIMARY KEY, schueler_id INT $n,
            kind_name TEXT NOT NULL DEFAULT '', kind_klasse TEXT NOT NULL DEFAULT '')");
    }
    $ein = $pdo->prepare('INSERT INTO schueler (webuntis_id, vorname, nachname) VALUES (?, ?, ?)');
    foreach ($schueler as $z) $ein->execute($z);
    foreach (['buchungen' => $buchungen, 'einladungen' => $einladungen, 'mitteilungen' => $mitteilungen] as $t => $zeilen) {
        $st = $pdo->prepare("INSERT INTO $t (schueler_id, kind_name) VALUES (?, ?)");
        foreach ($zeilen as $z) $st->execute($z);
    }
    return $pdo;
}
/** Führt jede Anweisung der Prüfdatei aus, liefert alle Ergebniszeilen. */
function abbau_pruefung(PDO $pdo, string $sql): array
{
    $aus = [];
    // Getrennt wird am Semikolon am Zeilenende – im Urteilssatz steht eins.
    foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', $sql))) as $anw) {
        $aus[] = $pdo->query($anw)->fetchAll();
    }
    return $aus;
}
// ERFUNDEN: 9001 voll in der alten Liste, 9002 nur Nachname, 9003 in der
// alten Liste ohne Namen, 9004 gar nicht in der alten Liste.
$alteListe = [[9001, 'Ute', 'ErfundenA'], [9002, '', 'ErfundenB'], [9003, '', '']];
$db = abbau_db($alteListe,
    [[9001, ''], [9002, ''], [9004, ''], [9001, 'ErfundenA, Ute']],   // buchungen: 3 leer, 2 füllbar, 1 nicht
    [[9003, ''], [9001, 'schon da']],                                 // einladungen: 1 leer, 0 füllbar, 1 nicht
    [[9001, ''], [null, ''], [0, '']]);                               // mitteilungen: 1 leer mit Kind, 2 ohne Kind
$erg = [];
try { $erg = abbau_pruefung($db, $pruef); } catch (Throwable $e) { echo '    (' . $e->getMessage() . ")\n"; }
$zeilen = []; foreach (($erg[0] ?? []) as $z) $zeilen[(string)($z['tabelle'] ?? '')] = $z;
$zahl = fn(string $t, string $s) => isset($zeilen[$t][$s]) ? (int)$zeilen[$t][$s] : null;
pruefe('Voraussetzung: zwei Ergebnisse (Zahlen je Tabelle, dann das Urteil), drei Tabellen',
    count($erg) === 2 && array_keys($zeilen) === ['buchungen', 'einladungen', 'mitteilungen']);
pruefe('Spalten je Tabelle: leer_mit_kind, noch_fuellbar, NICHT_FUELLBAR, ohne_kind',
    isset($erg[0][0]) && array_keys($erg[0][0]) === ['tabelle', 'leer_mit_kind', 'noch_fuellbar', 'NICHT_FUELLBAR', 'ohne_kind']);
pruefe('Buchungen: 3 leer, 2 aus der alten Liste füllbar (auch nur mit Nachname), 1 NICHT füllbar',
    $zahl('buchungen', 'leer_mit_kind') === 3 && $zahl('buchungen', 'noch_fuellbar') === 2
    && $zahl('buchungen', 'NICHT_FUELLBAR') === 1);
pruefe('Einladungen: in der alten Liste, aber ohne Namen – zählt als NICHT füllbar',
    $zahl('einladungen', 'leer_mit_kind') === 1 && $zahl('einladungen', 'noch_fuellbar') === 0
    && $zahl('einladungen', 'NICHT_FUELLBAR') === 1);
pruefe('Mitteilungen ohne Kind (Kennung leer oder 0) zählen getrennt, nicht als verloren',
    $zahl('mitteilungen', 'leer_mit_kind') === 1 && $zahl('mitteilungen', 'NICHT_FUELLBAR') === 0
    && $zahl('mitteilungen', 'ohne_kind') === 2);
$urteil = (string)($erg[1][0]['ergebnis'] ?? '');
pruefe('Urteil bei verlorenen Namen: beginnt mit ANSEHEN, nennt die Summe (2) und dass das Entfernen daran nichts ändert',
    str_starts_with($urteil, 'ANSEHEN') && preg_match('/\b2 Vorgänge\b/', $urteil) === 1
    && str_contains($urteil, 'schon heute verloren'));
$txt = json_encode($erg, JSON_UNESCAPED_UNICODE);
pruefe('Ausgabe ohne Namen und ohne Kennungen der Kinder',
    $erg !== [] && $txt !== false && preg_match('/Erfunden|Ute|schon da|900[1-4]/', $txt) === 0);
$db0 = abbau_db($alteListe, [[9001, ''], [9002, 'ErfundenB']], [], [[null, '']]);
$erg0 = [];
try { $erg0 = abbau_pruefung($db0, $pruef); } catch (Throwable $e) { echo '    (' . $e->getMessage() . ")\n"; }
pruefe('Urteil ohne verlorene Namen: beginnt mit OK',
    str_starts_with((string)($erg0[1][0]['ergebnis'] ?? ''), 'OK'));
pruefe('Kopf der Prüfdatei erklärt NICHT_FUELLBAR als die Zahl, auf die es ankommt',
    preg_match('/^-- .*NICHT_FUELLBAR.*die Zahl, auf die es ankommt/m', $pruefRoh) === 1
    && preg_match('/^-- .*schon heute verloren/m', $pruefRoh) === 1);

// ------------------------------------------------------------
echo "Migration 23: Entfernen (am Text geprüft – kein MariaDB hier)\n";
$migRoh = (string)@file_get_contents($SQL . '/23_schueler_entfernen.sql');
$mig = $ohneSqlKommentar($migRoh);
// Der Name, den die Migration schreibt, ist der, den die Prüfdatei als
// „füllbar“ zählt – eine Quelle, sonst zählt die Prüfung etwas anderes.
$NAME = "TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END))";
pruefe('Prüfdatei und Migration benutzen denselben Namensausdruck',
    substr_count($pruef, $NAME) >= 3 && substr_count($mig, $NAME) === 3);
$fuell = [];
foreach (['buchungen', 'einladungen', 'mitteilungen'] as $t) {
    $fuell[$t] = preg_match('/UPDATE ' . $t . ' x JOIN schueler s ON s\.webuntis_id = x\.schueler_id\s+SET x\.kind_name = '
        . preg_quote($NAME, '/') . ',\s+x\.kind_klasse = CASE WHEN x\.kind_klasse = \'\' THEN s\.klasse ELSE x\.kind_klasse END\s+WHERE x\.kind_name = \'\'"/', $mig, $m, PREG_OFFSET_CAPTURE) === 1
        ? $m[0][1] : null;
}
pruefe('füllt alle drei Tabellen nach, nur wo der Name leer ist, und überschreibt keine Klasse',
    !in_array(null, $fuell, true));
$schutz = preg_match_all('/SET @sql_\w+ := IF\(@schueler_da = 1,/', $mig);
pruefe('jedes Nachfüllen nur, wenn die Tabelle noch da ist (zweimal einspielbar)',
    preg_match("/SET @schueler_da := \(\s*SELECT COUNT\(\*\) FROM information_schema\.TABLES\s+WHERE TABLE_SCHEMA = DATABASE\(\) AND TABLE_NAME = 'schueler'\s*\);/", $mig) === 1
    && $schutz === 3);
$drop = strpos($mig, 'DROP TABLE IF EXISTS schueler;');
pruefe('entfernt die Tabelle mit DROP TABLE IF EXISTS – erst nach allen drei Nachfüllungen',
    $drop !== false && !in_array(null, $fuell, true) && $drop > max($fuell));
pruefe('rührt sonst nichts an (genau ein DROP, kein DELETE, kein TRUNCATE, kein ALTER)',
    preg_match_all('/\bDROP\b/i', $mig) === 1 && preg_match('/\b(DELETE|TRUNCATE|ALTER)\b/i', $mig) === 0);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
