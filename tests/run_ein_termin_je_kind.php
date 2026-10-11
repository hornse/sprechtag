<?php
// ============================================================
// tests/run_ein_termin_je_kind.php – ein Termin je Kind und Lehrkraft (E24)
// Aufruf: php tests/run_ein_termin_je_kind.php   → Exit-Code 0 = alles grün
//
// v0.9.84: Fehler aus dem Betrieb – ein Elternteil buchte bei derselben
// Lehrkraft für dasselbe Kind zwei Termine. Die stellvertretende Buchung
// prüfte das schon („Fall B“), der Weg der Eltern nicht; geprüft war keiner
// von beiden. Die Regel gilt je Kind und Lehrkraft, nicht je Elternkonto:
// Getrennt lebende Eltern buchen über zwei Konten.
//
//   * AUSGEFÜHRT gegen SQLite: bu_kind_termin(), die Meldungen, der
//     Schlüssel aus sql/26 und die Prüfdatei sql/26_pruefung.sql.
//   * QUELLTEXT: Beide Buchungswege fragen dieselbe Funktion vor dem
//     Schreiben (die Routen enden mit json_ok/json_err und sind hier nicht
//     ausführbar); der tote Zweig mit eltern_user_id aus dem Request ist fort.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

$WURZEL = (string)realpath(__DIR__ . '/..');
$SQL = $WURZEL . '/sql';
$API = $WURZEL . '/backend/api';
require_once $API . '/slots.php';
require_once $API . '/absagen.php';

/** SQL ohne Zeilenkommentare. */
$ohneSqlKommentar = fn(string $s): string => (string)preg_replace('/^\s*--.*$/m', '', $s);

/** PHP ohne Kommentare (Tokenizer). */
function ek_code(string $pfad): string
{
    $c = '';
    foreach (token_get_all((string)@file_get_contents($pfad)) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
}
/** Text von der ersten { ab $start bis zur passenden }. */
function ek_block(string $c, int $start): string
{
    $a = strpos($c, '{', $start);
    if ($a === false) return '';
    $t = 0;
    for ($i = $a, $n = strlen($c); $i < $n; $i++) {
        if ($c[$i] === '{') $t++;
        elseif ($c[$i] === '}' && --$t === 0) return substr($c, $a, $i - $a + 1);
    }
    return '';
}

// Der Schlüssel kommt aus der Migration, nicht aus dieser Datei: Ändert
// sql/26 die Spalten, baut die Testdatenbank den geänderten Schlüssel.
$m26roh = (string)@file_get_contents($SQL . '/26_ein_termin_je_kind.sql');
$schluessel = preg_match('/ADD UNIQUE KEY IF NOT EXISTS uniq_kind_termin \(([\w, ]+)\);/', $ohneSqlKommentar($m26roh), $m) === 1
    ? $m[1] : '';

/** Testdatenbank: buchungen mit den Schlüsseln aus sql/02 und sql/26, die Ablage wie sql/24. */
function ek_db(string $schluessel): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE buchungen (id INTEGER PRIMARY KEY AUTOINCREMENT, sprechtag_id INT NOT NULL,
        lehrer_id INT NOT NULL, slot_beginn TEXT NOT NULL, eltern_user_id INT NOT NULL, schueler_id INT NOT NULL,
        kommentar TEXT NOT NULL DEFAULT '', phase TEXT NOT NULL DEFAULT 'phase2', gebucht_von TEXT NOT NULL DEFAULT 'eltern',
        kind_name TEXT NOT NULL DEFAULT '', kind_klasse TEXT NOT NULL DEFAULT '',
        UNIQUE (sprechtag_id, lehrer_id, slot_beginn))");
    if ($schluessel !== '') $pdo->exec("CREATE UNIQUE INDEX uniq_kind_termin ON buchungen ($schluessel)");
    $pdo->exec("CREATE TABLE abgesagte_termine (id INTEGER PRIMARY KEY AUTOINCREMENT,
        sprechtag_id INT NOT NULL, lehrer_id INT NOT NULL, slot_beginn TEXT NOT NULL, schueler_id INT NOT NULL,
        kind_name TEXT NOT NULL DEFAULT '', kind_klasse TEXT NOT NULL DEFAULT '', kommentar TEXT NOT NULL DEFAULT '',
        anlass TEXT NOT NULL CHECK (anlass IN ('absage', 'ausfall')),
        abgesagt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    // ERFUNDEN: Sprechtag 1. Kind 9001 hat bei Lehrkraft 7 um 15:10 einen
    // Termin, gebucht über Konto 501; Kind 9002 bei Lehrkraft 8 um 15:00.
    $ein = $pdo->prepare('INSERT INTO buchungen (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id)
        VALUES (?, ?, ?, ?, ?)');
    foreach ([[1, 7, '15:10:00', 501, 9001], [1, 8, '15:00:00', 502, 9002]] as $z) $ein->execute($z);
    return $pdo;
}
$neue = function (PDO $db, array $z): ?string {
    try {
        $db->prepare('INSERT INTO buchungen (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id)
            VALUES (?, ?, ?, ?, ?)')->execute($z);
        return null;
    } catch (PDOException $e) { return $e->getMessage(); }
};

// ------------------------------------------------------------
echo "Die Regel: je Kind und Lehrkraft, nicht je Konto – ausgeführt gegen SQLite\n";
$da = function_exists('bu_kind_termin') && function_exists('bu_kind_termin_text') && function_exists('bu_konflikt_text');
pruefe('Voraussetzung: bu_kind_termin, bu_kind_termin_text und bu_konflikt_text definiert (slots.php)', $da);
if ($da) {
    $db = ek_db($schluessel);
    $t = bu_kind_termin($db, 1, 7, 9001);
    pruefe('Termin desselben Kindes bei derselben Lehrkraft gefunden – mit Uhrzeit und Konto',
        is_array($t) && substr((string)$t['slot_beginn'], 0, 5) === '15:10' && (int)$t['eltern_user_id'] === 501);
    pruefe('anderes Kind, andere Lehrkraft, anderer Sprechtag: kein Termin',
        bu_kind_termin($db, 1, 7, 9002) === null && bu_kind_termin($db, 1, 8, 9001) === null
        && bu_kind_termin($db, 2, 7, 9001) === null);
    // Nach einer Absage soll neu gebucht werden können: Die Ablage (E19) zählt nicht.
    bu_buchungen_entfernen($db, ['id' => 1], 'absage');
    pruefe('nach einer Absage: kein Termin mehr, obwohl die Ablage ihn führt (E19 blockiert nicht)',
        bu_kind_termin($db, 1, 7, 9001) === null
        && (int)$db->query('SELECT COUNT(*) FROM abgesagte_termine WHERE schueler_id = 9001')->fetchColumn() === 1);
}

// ------------------------------------------------------------
echo "Die Meldung: Uhrzeit nur, wo sie beim Handeln hilft (Betreiber, 2b)\n";
if ($da) {
    $termin = ['slot_beginn' => '15:10:00', 'eltern_user_id' => 501];
    pruefe('eigenes Konto: mit Uhrzeit',
        bu_kind_termin_text($termin, 'eltern', 501)
        === 'Sie haben für dieses Kind bei dieser Lehrkraft bereits einen Termin um 15:10 Uhr.');
    $fremd = bu_kind_termin_text($termin, 'eltern', 777);
    pruefe('anderes Konto: ohne Uhrzeit – sie verriete, wo jemand zu einer bestimmten Zeit ist',
        $fremd === 'Für dieses Kind besteht bei dieser Lehrkraft bereits ein Termin, gebucht über ein '
            . 'anderes Konto. Bitte wenden Sie sich an die Lehrkraft.'
        && preg_match('/\d{1,2}[:.]\d{2}/', $fremd) === 0);
    pruefe('Lehrkraft, die stellvertretend bucht: mit Uhrzeit, gleich an welchem Konto',
        bu_kind_termin_text($termin, 'lehrkraft', 0)
        === 'Für dieses Kind besteht bei dieser Lehrkraft bereits ein Termin um 15:10 Uhr.');
}

// ------------------------------------------------------------
echo "Zweite Stufe: der Schlüssel aus sql/26 (gleichzeitige Buchungen)\n";
pruefe('Voraussetzung: Spalten des Schlüssels aus sql/26 gelesen', $schluessel === 'sprechtag_id, lehrer_id, schueler_id');
if ($da && $schluessel !== '') {
    // Die Funktion ausgehängt: Was durchkommt, hält der Schlüssel allein.
    $db = ek_db($schluessel);
    pruefe('Schlüssel lehnt einen zweiten Termin desselben Kindes bei derselben Lehrkraft ab, auch über ein anderes Konto',
        $neue($db, [1, 7, '16:00:00', 777, 9001]) !== null
        && (int)$db->query('SELECT COUNT(*) FROM buchungen WHERE schueler_id = 9001')->fetchColumn() === 1);
    pruefe('… und lässt anderes Kind, andere Lehrkraft und anderen Sprechtag zu',
        $neue($db, [1, 7, '16:00:00', 777, 9003]) === null && $neue($db, [1, 9, '16:00:00', 501, 9001]) === null
        && $neue($db, [2, 7, '16:00:00', 501, 9001]) === null);
    // Nach einer Schlüsselverletzung sagt die Meldung, welcher Fall es war.
    pruefe('nach der Verletzung: Meldung zum Kind, wenn es einen Termin hat (anderes Konto: ohne Uhrzeit)',
        bu_konflikt_text($db, 1, 7, 9001, 'eltern', 777, 'SONST')
        === bu_kind_termin_text(['slot_beginn' => '15:10:00', 'eltern_user_id' => 501], 'eltern', 777));
    pruefe('… sonst die Meldung zum Slot',
        bu_konflikt_text($db, 1, 7, 9099, 'eltern', 777, 'SONST') === 'SONST');
}

// ------------------------------------------------------------
echo "Migration 26 und Prüfdatei (Migration am Text, Prüfdatei gegen SQLite)\n";
$liste = scandir($SQL) ?: [];
pruefe('Voraussetzung: sql/26_pruefung.sql und sql/26_ein_termin_je_kind.sql gelistet',
    in_array('26_pruefung.sql', $liste, true) && in_array('26_ein_termin_je_kind.sql', $liste, true));
pruefe('Migration legt genau den Schlüssel an, zweimal einspielbar (IF NOT EXISTS), sonst nichts',
    trim($ohneSqlKommentar($m26roh))
    === 'ALTER TABLE buchungen ADD UNIQUE KEY IF NOT EXISTS uniq_kind_termin (sprechtag_id, lehrer_id, schueler_id);');
$p26roh = (string)@file_get_contents($SQL . '/26_pruefung.sql');
$p26 = $ohneSqlKommentar($p26roh);
pruefe('Prüfdatei liest nur (kein UPDATE, DELETE, INSERT, DROP, ALTER, CREATE, TRUNCATE, REPLACE, RENAME, SET)',
    trim($p26) !== '' && preg_match('/\b(UPDATE|DELETE|INSERT|DROP|ALTER|CREATE|TRUNCATE|REPLACE|RENAME|SET)\b/i', $p26) === 0);
/** Führt jede Anweisung der Prüfdatei aus, liefert alle Ergebniszeilen. */
function ek_pruefung(PDO $pdo, string $sql): array
{
    $aus = [];
    foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', $sql))) as $anw) {
        $aus[] = $pdo->query($anw)->fetchAll();
    }
    return $aus;
}
// Ohne Schlüssel – so steht die Tabelle vor sql/26 im Betrieb. ERFUNDEN:
// Kind 9001 dreimal bei Lehrkraft 7 (zwei Konten), Kind 9002 zweimal bei
// Lehrkraft 8, Kind 9003 einmal; dasselbe Kind an Sprechtag 2 zählt nicht mit.
$ohne = ek_db('');
foreach ([[1, 7, '15:00:00', 777, 9001], [1, 7, '15:20:00', 501, 9001], [1, 8, '15:10:00', 502, 9002],
          [1, 7, '15:30:00', 503, 9003], [2, 7, '15:10:00', 501, 9001]] as $z) $neue($ohne, $z);
$erg = [];
try { $erg = ek_pruefung($ohne, $p26); } catch (Throwable $e) { echo '    (' . $e->getMessage() . ")\n"; }
$zahlen = $erg[0][0] ?? [];
pruefe('Prüfdatei zählt: 2 Fälle, 3 überzählige Termine',
    (int)($zahlen['DOPPELT_FAELLE'] ?? -1) === 2 && (int)($zahlen['UEBERZAHLIGE_TERMINE'] ?? -1) === 3);
pruefe('… gibt nur Zahlen aus, keine Kennungen und keine Namen (genau zwei Spalten)',
    array_keys($zahlen) === ['DOPPELT_FAELLE', 'UEBERZAHLIGE_TERMINE']);
pruefe('… letzte Zeile ein Satz: ANSEHEN, wenn es doppelte gibt',
    str_starts_with((string)($erg[1][0]['ergebnis'] ?? ''), 'ANSEHEN'));
$sauber = [];
try { $sauber = ek_pruefung(ek_db(''), $p26); } catch (Throwable $e) { echo '    (' . $e->getMessage() . ")\n"; }
pruefe('… und OK mit 0 und 0, wenn es keine gibt',
    (int)($sauber[0][0]['DOPPELT_FAELLE'] ?? -1) === 0 && (int)($sauber[0][0]['UEBERZAHLIGE_TERMINE'] ?? -1) === 0
    && str_starts_with((string)($sauber[1][0]['ergebnis'] ?? ''), 'OK'));
pruefe('Reihenfolge im Kopf beider Dateien: Deploy, dann Prüfdatei, dann Migration',
    str_contains($p26roh, 'REIHENFOLGE (v0.9.84): Deploy, dann diese Prüfdatei, dann sql/26_ein_termin_je_kind.sql.')
    && str_contains($m26roh, 'REIHENFOLGE (v0.9.84): Deploy, dann sql/26_pruefung.sql, dann diese Datei.'));

// ------------------------------------------------------------
echo "Aufrufstellen und Engstelle (Quelltext)\n";
$bu = ek_code($API . '/buchungen.php');
$stv = ($p = strpos($bu, "if (\$methode === 'POST' && (\$seg[1] ?? '') === 'stellvertretend') {")) === false ? '' : ek_block($bu, $p);
$elt = ($p = strpos($bu, "if (\$methode === 'POST' && !isset(\$seg[1])) {")) === false ? '' : ek_block($bu, $p);
pruefe('Voraussetzung: beide Buchungswege gefunden (stellvertretend, Eltern)',
    str_contains($stv, 'INSERT INTO buchungen') && str_contains($elt, 'INSERT INTO buchungen'));
// Jede Schreibstelle in buchungen fragt die Regel – ermittelt über backend/,
// nicht aufgezählt. Die Ablage schreibt in abgesagte_termine und zählt nicht.
$backend = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($WURZEL . '/backend', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) $backend[] = $f->getPathname();
$schreib = 0;
foreach ($backend as $pfad) $schreib += preg_match_all('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?buchungen`?[\s(]/i', ek_code($pfad));
pruefe('genau zwei Stellen unter backend/ schreiben in buchungen – beide oben gefunden (' . $schreib . ')',
    count($backend) >= 15 && $schreib === 2);
// Der ganze Block zusammenhängend – Abfrage, Bedingung, Zurückrollen,
// Meldung –, nicht nur die Abfrage irgendwo davor: Ein „if (false)“
// dazwischen bestünde sonst (REIHENREGELN 2, Nachbarschaft zweier Stellen).
$wache = fn(string $wer): string => '/\$vorhanden = bu_kind_termin\(\$pdo, \$sid, \$lid, \$kind\);\s*'
    . 'if \(\$vorhanden !== null\) \{\s*\$pdo->rollBack\(\);\s*'
    . 'json_err\(bu_kind_termin_text\(\$vorhanden, ' . $wer . '\), 409\);\s*\}/';
$vorher = function (string $r, string $muster): bool {
    return preg_match($muster, $r, $m, PREG_OFFSET_CAPTURE) === 1
        && ($j = strpos($r, 'INSERT INTO buchungen')) !== false && $m[0][1] < $j;
};
pruefe('stellvertretend: fragt bu_kind_termin vor dem Schreiben, Meldung für die Lehrkraft (Reihenfolge, Quelltext)',
    $vorher($stv, $wache("'lehrkraft', 0")));
pruefe('Eltern: fragt bu_kind_termin vor dem Schreiben, Meldung je nach Konto (Reihenfolge, Quelltext)',
    $vorher($elt, $wache("'eltern', \\(int\\)\\\$elternUserId")));
pruefe('beide: nach einer Schlüsselverletzung sagt bu_konflikt_text, ob das Kind oder der Slot es war',
    str_contains($stv, "json_err(bu_konflikt_text(\$pdo, \$sid, \$lid, \$kind, 'lehrkraft', 0,")
    && str_contains($elt, "json_err(bu_konflikt_text(\$pdo, \$sid, \$lid, \$kind, 'eltern', (int)\$elternUserId,"));
pruefe('die Regel steht einmal: keine Route fragt buchungen selbst nach Lehrkraft und Kind',
    $bu !== '' && preg_match('/FROM buchungen\s+WHERE sprechtag_id = \? AND lehrer_id = \? AND schueler_id = \?/', $bu) === 0);
// Toter Zweig (Betreiber, 4): Lehrkraft und Verwaltung buchten über den
// Elternweg mit einer Konto-Kennung aus dem Request, ohne die Regel.
$irgendwo = false;
foreach ($backend as $pfad) if (str_contains(ek_code($pfad), "\$body['eltern_user_id']")) $irgendwo = true;
pruefe('kein Code übernimmt eltern_user_id aus dem Request (der tote Zweig ist fort)', $backend !== [] && !$irgendwo);
pruefe('Elternweg weist Lehrkraft und Verwaltung ab, statt für sie zu buchen',
    str_contains($elt, "if (in_array(\$rolle, ['lehrkraft', 'admin'], true)) {\n            json_err('Lehrkräfte buchen stellvertretend in ihrer eigenen Ansicht.', 403);"));

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
