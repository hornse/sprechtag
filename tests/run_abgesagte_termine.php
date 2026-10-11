<?php
// ============================================================
// tests/run_abgesagte_termine.php – abgesagte Termine und Einladungsstatus
// Aufruf: php tests/run_abgesagte_termine.php   → Exit-Code 0 = alles grün
//
// v0.9.83 (E18, E19): Was die Schule absagt – einzeln oder per
// Krankheitsausfall –, wird in abgesagte_termine abgelegt und dann wie
// bisher aus buchungen gelöscht; der Slot ist sofort frei. Der Status
// einer Einladung wird abgeleitet (gebucht / abgesagt / offen), nicht
// gespeichert; einladungen.erledigt entfällt (sql/25).
//
// Was das Archivieren löscht, prüft run_archivieren.php.
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

/** SQL ohne Zeilenkommentare. */
$ohneSqlKommentar = fn(string $s): string => (string)preg_replace('/^\s*--.*$/m', '', $s);

// ------------------------------------------------------------
echo "Migration 24: die Ablage (am Text geprüft – kein MariaDB hier)\n";
$m24 = $ohneSqlKommentar((string)@file_get_contents($SQL . '/24_abgesagte_termine.sql'));
pruefe('Voraussetzung: sql/24_abgesagte_termine.sql gelistet', in_array('24_abgesagte_termine.sql', scandir($SQL) ?: [], true));
$tab = preg_match('/CREATE TABLE IF NOT EXISTS abgesagte_termine \((.*)\)\s*ENGINE=InnoDB[^;]*;/s', $m24, $m) === 1 ? $m[1] : '';
pruefe('legt abgesagte_termine an, zweimal einspielbar (IF NOT EXISTS)', $tab !== '');
preg_match_all('/^\s+(\w+)\s+(?:INT|TIME|VARCHAR|ENUM|DATETIME)\b/m', $tab, $sm);
$spalten = $sm[1];
pruefe('Spalten: Sprechtag, Lehrkraft, Uhrzeit, Kind (Kennung, Name, Klasse), Hinweis der Eltern, Anlass, Zeitpunkt',
    $spalten === ['id', 'sprechtag_id', 'lehrer_id', 'slot_beginn', 'schueler_id', 'kind_name', 'kind_klasse',
        'kommentar', 'anlass', 'abgesagt_am']);
pruefe('keine Kennung der Eltern und kein Absagetext (G3: steht in mitteilungen, braucht hier niemand)',
    $tab !== '' && preg_match('/eltern_user_id|betreff|\btext\b|nachricht/i', $tab) === 0);
pruefe('Anlass ist absage oder ausfall – nichts sonst (G2: nur was die Schule absagt)',
    preg_match("/^\s+anlass\s+ENUM\('absage',\s*'ausfall'\)\s+NOT NULL/m", $tab) === 1);

// Die Ablage übernimmt Name, Klasse und Hinweis aus buchungen – sie darf
// nicht schmaler sein, sonst schneidet die Übernahme ab. Die Breiten
// kommen aus den Migrationen, die buchungen erweitern (sql/22, sql/14).
$breite = function (string $text, string $spalte): ?int {
    return preg_match('/\b' . $spalte . '\s+VARCHAR\((\d+)\)/', $text, $b) === 1 ? (int)$b[1] : null;
};
$m22 = (string)@file_get_contents($SQL . '/22_kindname.sql');
$m14 = (string)@file_get_contents($SQL . '/14_kommentar.sql');
$quelle = ['kind_name' => $breite($m22, 'kind_name'), 'kind_klasse' => $breite($m22, 'kind_klasse'),
    'kommentar' => $breite($m14, 'kommentar')];
pruefe('Voraussetzung: Breiten aus buchungen gelesen (sql/22, sql/14)', !in_array(null, $quelle, true));
pruefe('Name, Klasse und Hinweis so breit wie in buchungen',
    !in_array(null, $quelle, true) && $breite($tab, 'kind_name') === $quelle['kind_name']
    && $breite($tab, 'kind_klasse') === $quelle['kind_klasse'] && $breite($tab, 'kommentar') === $quelle['kommentar']);
pruefe('hängt am Sprechtag und an der Lehrkraft (ON DELETE CASCADE)',
    preg_match('/FOREIGN KEY \(sprechtag_id\)\s+REFERENCES sprechtage \(id\) ON DELETE CASCADE/', $tab) === 1
    && preg_match('/FOREIGN KEY \(lehrer_id\)\s+REFERENCES lehrer \(id\) ON DELETE CASCADE/', $tab) === 1);
pruefe('kein eindeutiger Schlüssel außer id (derselbe Slot kann mehrmals abgesagt werden)',
    $tab !== '' && preg_match('/\bUNIQUE\b/i', $tab) === 0);
pruefe('rührt sonst nichts an (kein ALTER, DROP, DELETE, UPDATE, INSERT)',
    // „ON DELETE CASCADE“ ist Teil des Fremdschlüssels, keine Anweisung.
    $m24 !== '' && preg_match('/\b(ALTER|DROP|(?<!ON )DELETE|UPDATE|INSERT)\b/i', $m24) === 0);

// ------------------------------------------------------------
echo "Eine Funktion entfernt Buchungen (E19, G2) – ausgeführt gegen SQLite\n";
$API = $WURZEL . '/backend/api';
pruefe('Voraussetzung: backend/api/absagen.php gelistet', in_array('absagen.php', scandir($API) ?: [], true));
if (is_file($API . '/absagen.php')) require_once $API . '/absagen.php';
$daSind = function_exists('bu_buchungen_entfernen') && function_exists('bu_absage_anlass');
pruefe('Voraussetzung: bu_buchungen_entfernen und bu_absage_anlass definiert', $daSind);

/** Testdatenbank: buchungen mit dem Schlüssel aus sql/02 (Slot eindeutig), die Ablage wie sql/24. */
function ab_db(bool $mitAblage = true): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE buchungen (id INTEGER PRIMARY KEY AUTOINCREMENT, sprechtag_id INT NOT NULL,
        lehrer_id INT NOT NULL, slot_beginn TEXT NOT NULL, eltern_user_id INT NOT NULL, schueler_id INT NOT NULL,
        kommentar TEXT NOT NULL DEFAULT '', phase TEXT NOT NULL DEFAULT 'phase2', gebucht_von TEXT NOT NULL DEFAULT 'eltern',
        kind_name TEXT NOT NULL DEFAULT '', kind_klasse TEXT NOT NULL DEFAULT '',
        UNIQUE (sprechtag_id, lehrer_id, slot_beginn))");
    // ENUM im Betrieb: ein anderer Anlass wird dort abgewiesen, hier auch.
    if ($mitAblage) $pdo->exec("CREATE TABLE abgesagte_termine (id INTEGER PRIMARY KEY AUTOINCREMENT,
        sprechtag_id INT NOT NULL, lehrer_id INT NOT NULL, slot_beginn TEXT NOT NULL, schueler_id INT NOT NULL,
        kind_name TEXT NOT NULL DEFAULT '', kind_klasse TEXT NOT NULL DEFAULT '', kommentar TEXT NOT NULL DEFAULT '',
        anlass TEXT NOT NULL CHECK (anlass IN ('absage', 'ausfall')),
        abgesagt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    // ERFUNDEN: Sprechtag 1, Lehrkraft 7 mit drei Terminen, Lehrkraft 8 mit
    // einem; Sprechtag 2, Lehrkraft 7 mit einem.
    $ein = $pdo->prepare('INSERT INTO buchungen (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id,
        kommentar, kind_name, kind_klasse) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ([[1, 7, '15:00:00', 501, 9001, 'Mathe', 'ErfundenA, Ute', '7a'],
              [1, 7, '15:10:00', 502, 9002, '', 'ErfundenB, Tom', '7b'],
              [1, 7, '15:20:00', 503, 9003, 'Versetzung', 'ErfundenC, Ida', '8a'],
              [1, 8, '15:00:00', 501, 9001, '', 'ErfundenA, Ute', '7a'],
              [2, 7, '15:00:00', 504, 9004, '', 'ErfundenD, Max', '9c']] as $z) $ein->execute($z);
    return $pdo;
}
$zahl = fn(PDO $p, string $sql): int => (int)$p->query($sql)->fetchColumn();

if ($daSind) {
    pruefe('Anlass: Lehrkraft und Verwaltung sagen ab, Familie und volljährige Schüler stornieren (nichts abzulegen)',
        bu_absage_anlass('lehrkraft') === 'absage' && bu_absage_anlass('admin') === 'absage'
        && bu_absage_anlass('eltern') === null && bu_absage_anlass('schueler') === null && bu_absage_anlass('') === null);

    $db = ab_db();
    $weg = bu_buchungen_entfernen($db, ['id' => 1], 'absage');
    $ab = $db->query('SELECT * FROM abgesagte_termine')->fetchAll();
    pruefe('einzelne Absage: abgelegt mit Sprechtag, Lehrkraft, Uhrzeit, Kind, Name, Klasse, Hinweis, Anlass',
        count($ab) === 1 && (int)$ab[0]['sprechtag_id'] === 1 && (int)$ab[0]['lehrer_id'] === 7
        && $ab[0]['slot_beginn'] === '15:00:00' && (int)$ab[0]['schueler_id'] === 9001
        && $ab[0]['kind_name'] === 'ErfundenA, Ute' && $ab[0]['kind_klasse'] === '7a'
        && $ab[0]['kommentar'] === 'Mathe' && $ab[0]['anlass'] === 'absage' && $ab[0]['abgesagt_am'] !== '');
    pruefe('… die Buchung ist fort, die übrigen stehen', $zahl($db, 'SELECT COUNT(*) FROM buchungen WHERE id = 1') === 0
        && $zahl($db, 'SELECT COUNT(*) FROM buchungen') === 4);
    pruefe('… und liefert die entfernte Zeile zurück (für die Benachrichtigung)',
        count($weg) === 1 && (int)$weg[0]['eltern_user_id'] === 501 && (int)$weg[0]['schueler_id'] === 9001);
    $frei = true;
    try { $db->exec("INSERT INTO buchungen (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id)
        VALUES (1, 7, '15:00:00', 599, 9099)"); } catch (PDOException $e) { $frei = false; }
    pruefe('… der Slot ist sofort wieder buchbar (der eindeutige Schlüssel lässt ihn zu)', $frei);

    $db = ab_db();
    bu_buchungen_entfernen($db, ['id' => 2], null);
    pruefe('Familie storniert selbst: gelöscht, nichts abgelegt',
        $zahl($db, 'SELECT COUNT(*) FROM buchungen WHERE id = 2') === 0
        && $zahl($db, 'SELECT COUNT(*) FROM abgesagte_termine') === 0);

    $db = ab_db();
    $weg = bu_buchungen_entfernen($db, ['sprechtag_id' => 1, 'lehrer_id' => 7], 'ausfall');
    pruefe('Ausfall: alle drei Termine der Lehrkraft an diesem Sprechtag abgelegt, Anlass ausfall',
        $zahl($db, "SELECT COUNT(*) FROM abgesagte_termine WHERE anlass = 'ausfall' AND sprechtag_id = 1 AND lehrer_id = 7") === 3
        && $zahl($db, 'SELECT COUNT(*) FROM abgesagte_termine') === 3);
    pruefe('… alle drei gelöscht; die andere Lehrkraft und der andere Sprechtag bleiben',
        $zahl($db, 'SELECT COUNT(*) FROM buchungen WHERE sprechtag_id = 1 AND lehrer_id = 7') === 0
        && $zahl($db, 'SELECT COUNT(*) FROM buchungen WHERE sprechtag_id = 1 AND lehrer_id = 8') === 1
        && $zahl($db, 'SELECT COUNT(*) FROM buchungen WHERE sprechtag_id = 2 AND lehrer_id = 7') === 1);
    pruefe('… und liefert alle drei zurück, mit Eltern-Kennung, Uhrzeit, Name und Klasse',
        array_map(fn($z) => (int)$z['eltern_user_id'], $weg) === [501, 502, 503]
        && array_column($weg, 'slot_beginn') === ['15:00:00', '15:10:00', '15:20:00']
        && array_column($weg, 'kind_klasse') === ['7a', '7b', '8a']);
    pruefe('nichts gefunden: leer, nichts geschrieben',
        bu_buchungen_entfernen($db, ['sprechtag_id' => 1, 'lehrer_id' => 99], 'ausfall') === []
        && $zahl($db, 'SELECT COUNT(*) FROM abgesagte_termine') === 3);

    // Ablegen und Löschen gehören zusammen: scheitert das Ablegen, bleibt die Buchung.
    $db = ab_db(false);
    $geworfen = false;
    try { bu_buchungen_entfernen($db, ['id' => 3], 'absage'); } catch (PDOException $e) { $geworfen = true; }
    pruefe('scheitert das Ablegen (Tabelle fehlt), bleibt die Buchung stehen und der Fehler geht weiter',
        $geworfen && $zahl($db, 'SELECT COUNT(*) FROM buchungen WHERE id = 3') === 1 && !$db->inTransaction());

    // Als Abweisung zählt nur InvalidArgumentException. Jede andere Ausnahme
    // ist „nicht abgewiesen“ und damit eine rote Zeile – bis v0.9.83 (vor der
    // Auslieferung) ging sie ungefangen durch, und die Suite endete ohne
    // Zeile mit Exit 255 (Mutation AT11, E5-Nachtrag).
    $abgelehnt = function (array $wo, ?string $anlass): bool {
        $db = ab_db();
        try { bu_buchungen_entfernen($db, $wo, $anlass); } catch (InvalidArgumentException $e) {
            return (int)$db->query('SELECT COUNT(*) FROM buchungen')->fetchColumn() === 5;
        } catch (Throwable $e) {
            echo '    (nicht abgewiesen, sondern ' . get_class($e) . ': ' . $e->getMessage() . ")\n";
            return false;
        }
        return false;
    };
    pruefe('ohne Bedingung: abgewiesen, nichts gelöscht (sonst träfe es jede Buchung)', $abgelehnt([], 'ausfall'));
    pruefe('nur Sprechtag ohne Lehrkraft, oder ein fremder Schlüssel: abgewiesen',
        $abgelehnt(['sprechtag_id' => 1], 'ausfall') && $abgelehnt(['schueler_id' => 9001], null)
        && $abgelehnt(['id' => 1, 'lehrer_id' => 7], 'absage'));
    pruefe('anderer Anlass als absage/ausfall: abgewiesen, nichts gelöscht', $abgelehnt(['id' => 1], 'storno'));
}

// ------------------------------------------------------------
echo "Einladungsstatus (E18, G1): gebucht vor abgesagt vor offen – abgeleitet, ausgeführt gegen SQLite\n";
$statusDa = function_exists('bu_einladung_status_sql');
pruefe('Voraussetzung: bu_einladung_status_sql definiert', $statusDa);

/** ab_db() mit Einladungen wie sql/02 + sql/22, ohne erledigt (sql/25). */
function ab_db_einl(): PDO
{
    $pdo = ab_db();
    $pdo->exec("CREATE TABLE einladungen (id INTEGER PRIMARY KEY AUTOINCREMENT, sprechtag_id INT NOT NULL,
        lehrer_id INT NOT NULL, schueler_id INT NOT NULL, hinweis TEXT NOT NULL DEFAULT '',
        angelegt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, kind_name TEXT NOT NULL DEFAULT '',
        kind_klasse TEXT NOT NULL DEFAULT '', UNIQUE (sprechtag_id, lehrer_id, schueler_id))");
    // ERFUNDEN: Lehrkraft 7 lädt an Sprechtag 1 fünf Kinder ein – drei mit
    // Termin bei ihr, 9004 nur mit Termin an Sprechtag 2, 9005 ohne Termin.
    // Lehrkraft 8 lädt 9001 (Termin bei ihr) und 9002 (Termin nur bei 7) ein.
    $ein = $pdo->prepare('INSERT INTO einladungen (sprechtag_id, lehrer_id, schueler_id) VALUES (?, ?, ?)');
    foreach ([[1, 7, 9001], [1, 7, 9002], [1, 7, 9003], [1, 7, 9004], [1, 7, 9005],
              [1, 8, 9001], [1, 8, 9002], [2, 7, 9004]] as $z) $ein->execute($z);
    return $pdo;
}
/** Status je Kind für eine Lehrkraft an einem Sprechtag, über die eine Ableitung. */
$status = function (PDO $p, int $sid, int $lid): array {
    $st = $p->prepare('SELECT e.schueler_id, ' . bu_einladung_status_sql('e') . ' AS status
        FROM einladungen e WHERE e.sprechtag_id = ? AND e.lehrer_id = ? ORDER BY e.schueler_id');
    $st->execute([$sid, $lid]);
    $aus = [];
    foreach ($st->fetchAll() as $z) $aus[(int)$z['schueler_id']] = $z['status'];
    return $aus;
};

if ($statusDa) {
    $db = ab_db_einl();
    pruefe('ohne Absage: gebucht, wo das Kind bei DIESER Lehrkraft an DIESEM Sprechtag einen Termin hat, sonst offen',
        $status($db, 1, 7) === [9001 => 'gebucht', 9002 => 'gebucht', 9003 => 'gebucht', 9004 => 'offen', 9005 => 'offen']
        && $status($db, 1, 8) === [9001 => 'gebucht', 9002 => 'offen']
        && $status($db, 2, 7) === [9004 => 'gebucht']);

    bu_buchungen_entfernen($db, ['sprechtag_id' => 1, 'lehrer_id' => 7], 'ausfall');
    pruefe('Ausfall bei mehreren Einladungen derselben Lehrkraft: alle drei abgesagt, die ohne Termin bleiben offen',
        $status($db, 1, 7) === [9001 => 'abgesagt', 9002 => 'abgesagt', 9003 => 'abgesagt', 9004 => 'offen', 9005 => 'offen']);
    pruefe('… die Ablage zählt nur bei dieser Lehrkraft und an diesem Sprechtag',
        $status($db, 1, 8) === [9001 => 'gebucht', 9002 => 'offen'] && $status($db, 2, 7) === [9004 => 'gebucht']);

    $db->exec("INSERT INTO buchungen (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id)
        VALUES (1, 7, '16:00:00', 502, 9002)");
    pruefe('neu gebucht nach der Absage: gebucht geht vor abgesagt',
        $status($db, 1, 7) === [9001 => 'abgesagt', 9002 => 'gebucht', 9003 => 'abgesagt', 9004 => 'offen', 9005 => 'offen']);

    $db = ab_db_einl();
    bu_buchungen_entfernen($db, ['id' => 5], 'absage');
    pruefe('einzelne Absage an Sprechtag 2: dort abgesagt, an Sprechtag 1 bleibt dasselbe Kind offen',
        $status($db, 2, 7) === [9004 => 'abgesagt'] && $status($db, 1, 7)[9004] === 'offen');

    $db = ab_db_einl();
    bu_buchungen_entfernen($db, ['id' => 2], null);
    pruefe('Familie storniert selbst: offen, nicht abgesagt (nichts abgelegt)',
        $status($db, 1, 7)[9002] === 'offen');
}

// ------------------------------------------------------------
echo "Abgesagte Termine für die Lehrkraft (G4) – ausgeführt gegen SQLite\n";
// Die Felder, die die Ansicht liest. tests/frontend_abgesagte_termine_test.js
// liest diese Liste und baut ihre Eingaben daraus – so hängt die Anzeige an
// dem, was die Abfrage liefert (v0.9.83: app.js las erledigt weiter, als die
// API es nicht mehr lieferte, und keine Prüfung wurde rot).
const AB_FELDER = ['slot_beginn', 'kind_name', 'klasse', 'kommentar', 'anlass', 'abgesagt_am', 'neu_gebucht'];
$listeDa = function_exists('bu_abgesagte_termine');
pruefe('Voraussetzung: bu_abgesagte_termine definiert', $listeDa);
if ($listeDa) {
    $db = ab_db_einl();
    bu_buchungen_entfernen($db, ['sprechtag_id' => 1, 'lehrer_id' => 7], 'ausfall');
    $db->exec("INSERT INTO buchungen (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id)
        VALUES (1, 7, '16:00:00', 502, 9002)");
    $liste = bu_abgesagte_termine($db, 1, 7);
    pruefe('nach dem Ausfall: alle drei, nach Uhrzeit, mit genau den Feldern der Ansicht (keine Kennung von Kind oder Eltern)',
        count($liste) === 3 && array_column($liste, 'slot_beginn') === ['15:00:00', '15:10:00', '15:20:00']
        && array_keys($liste[0]) === AB_FELDER);
    pruefe('… mit Name, Klasse, Hinweis und Anlass aus der Ablage',
        array_column($liste, 'kind_name') === ['ErfundenA, Ute', 'ErfundenB, Tom', 'ErfundenC, Ida']
        && array_column($liste, 'klasse') === ['7a', '7b', '8a']
        && array_column($liste, 'kommentar') === ['Mathe', '', 'Versetzung']
        && array_column($liste, 'anlass') === ['ausfall', 'ausfall', 'ausfall']);
    pruefe('neu gebucht: die Uhrzeit des neuen Termins bei DIESER Lehrkraft; ein Termin des Kindes bei einer anderen zählt nicht',
        array_column($liste, 'neu_gebucht') === [null, '16:00:00', null]);
    pruefe('andere Lehrkraft und anderer Sprechtag: nichts',
        bu_abgesagte_termine($db, 1, 8) === [] && bu_abgesagte_termine($db, 2, 7) === []);
}

// ------------------------------------------------------------
echo "Aufrufstellen und Engstelle (Quelltext)\n";
/** PHP-Code ohne Kommentare (Zeichenketten bleiben – dort steht SQL). */
function ab_code(string $pfad): string
{
    $c = '';
    foreach (token_get_all((string)@file_get_contents($pfad)) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
}
/** Text von der ersten { ab $start bis zur passenden } (SQL hier ohne geschweifte Klammern). */
function ab_block(string $c, int $start): string
{
    $a = strpos($c, '{', $start);
    if ($a === false) return '';
    $t = 0;
    for ($i = $a; $i < strlen($c); $i++) {
        if ($c[$i] === '{') $t++;
        if ($c[$i] === '}' && --$t === 0) return substr($c, $a, $i - $a + 1);
    }
    return '';
}
$ix = ab_code($API . '/index.php');
$bu = ab_code($API . '/buchungen.php');
$abq = ab_code($API . '/absagen.php');

$pos = strpos($bu, "json_err('Buchung nicht gefunden', 404);");
$ende = $pos === false ? false : strpos($bu, "json_ok(['ok' => true, 'mitteilung' => \$mitteilung]);", $pos);
$storno = ($pos === false || $ende === false) ? '' : substr($bu, $pos, $ende - $pos);
pruefe('Voraussetzung: Absage-Route (DELETE /api/buchungen/{id}) gefunden', $storno !== '');
pruefe('Absage-Route: Anlass aus der Rolle, dann über die eine Funktion entfernt',
    preg_match('/\$anlass = bu_absage_anlass\(\(string\)\$u\[\'rolle\'\]\);\s*bu_buchungen_entfernen\(\$pdo, \[\'id\' => \$bid\], \$anlass\);/', $storno) === 1);
pruefe('… benachrichtigt genau dann, wenn abgelegt wird – eine Entscheidung, nicht zwei',
    preg_match('/if \(\$anlass !== null\) \{/', $storno) === 1
    && preg_match("/in_array\(\\\$u\['rolle'\], \['lehrkraft', 'admin'\]/", $storno) === 0);

$a = strpos($ix, "=== 'ausfall')");
$ausfall = $a === false ? '' : ab_block($ix, $a);
pruefe('Voraussetzung: Ausfall-Route gefunden', $ausfall !== '' && str_contains($ausfall, 'teilnahme'));
pruefe('Ausfall: alle Termine der Lehrkraft über die eine Funktion, Anlass ausfall',
    preg_match("/\\\$buchungen = bu_buchungen_entfernen\(\\\$pdo, \['sprechtag_id' => \\\$sid, 'lehrer_id' => \\\$lid\], 'ausfall'\);/", $ausfall) === 1);
pruefe('… erst die Teilnahme abschalten, dann entfernen (kein neuer Termin dazwischen)',
    ($t0 = strpos($ausfall, 'teilnahme = 0')) !== false && ($t1 = strpos($ausfall, 'bu_buchungen_entfernen(')) !== false && $t0 < $t1);

// Einladungsstatus: im Lehrkraft-Zweig von GET /api/einladungen gesucht, nicht
// in der Datei – derselbe Wortlaut steht sonst womöglich anderswo.
$e0 = strpos($bu, "if ((\$seg[0] ?? '') === 'einladungen') {");
$e1 = $e0 === false ? false : strpos($bu, "if (in_array(\$u['rolle'], ['lehrkraft', 'admin'], true)) {", $e0);
$einlLehrkraft = $e1 === false ? '' : ab_block($bu, $e1);
$f = strpos($bu, 'function bu_einladende_lehrer(');
$einlEltern = $f === false ? '' : ab_block($bu, $f);
pruefe('Voraussetzung: Lehrkraft-Zweig von GET /api/einladungen und bu_einladende_lehrer gefunden',
    str_contains($einlLehrkraft, 'FROM einladungen e') && str_contains($einlEltern, 'FROM einladungen e'));
pruefe('Lehrkraft-Zweig: Status über die eine Ableitung, erledigt wird nicht gelesen',
    preg_match("/bu_einladung_status_sql\('e'\) \. ' AS status,/", $einlLehrkraft) === 1
    && !str_contains($einlLehrkraft, 'erledigt'));
pruefe('Eltern (bu_einladende_lehrer): weder erledigt noch ein Status (nicht in diesem Zug)',
    !str_contains($einlEltern, 'erledigt') && !str_contains($einlEltern, 'bu_einladung_status_sql')
    && !str_contains($einlEltern, 'status'));

// Abgesagte Termine im Raster: Die Route endet mit json_ok (exit) und ist
// hier nicht ausführbar – deshalb Quelltext, im Rumpf der Route gesucht.
$r0 = strpos($bu, "if (\$methode === 'GET' && (\$seg[0] ?? '') === 'raster') {");
$rasterRoute = $r0 === false ? '' : ab_block($bu, $r0);
pruefe('Voraussetzung: Raster-Route gefunden', str_contains($rasterRoute, 'slot_raster('));
pruefe('Raster-Route: Lehrkraft und Verwaltung bekommen die abgesagten Termine, Eltern nicht',
    preg_match('/\$abgesagt = \$istLehrkraft \? bu_abgesagte_termine\(\$pdo, \$sid, \$lid\) : \[\];/', $rasterRoute) === 1
    && str_contains($rasterRoute, "'abgesagt' => \$abgesagt"));
$fl = ($f = strpos($abq, 'function bu_abgesagte_termine(')) === false ? '' : ab_block($abq, $f);
pruefe('… lädt auch bei Teilnahme 0: weder die Route noch die Abfrage fragen die Teilnahme (Quelltext)',
    $fl !== '' && !str_contains($rasterRoute, 'teilnahme') && !str_contains($fl, 'teilnahme'));
// Eine Ableitung: „dasselbe Kind bei derselben Lehrkraft am selben Sprechtag“
// steht einmal, Status und „neu gebucht“ fragen dort.
$fs = ($f = strpos($abq, 'function bu_einladung_status_sql(')) === false ? '' : ab_block($abq, $f);
pruefe('Status und „neu gebucht“ nehmen dieselbe Bedingung (bu_selber_vorgang_sql), keine schreibt sie selbst',
    $fs !== '' && $fl !== '' && str_contains($fs, 'bu_selber_vorgang_sql(') && str_contains($fl, 'bu_selber_vorgang_sql(')
    && !str_contains($fs, '.schueler_id =') && !str_contains($fl, '.schueler_id ='));

// Engstelle: Buchungen löscht nur die eine Funktion – und das Archivieren,
// das den ganzen Sprechtag leert. Jede Schreibweise, die eine Buchung
// entfernt, zählt: DELETE … FROM (auch mit Alias), TRUNCATE.
$loescht = '/\b(?:DELETE\b[^;\'"]{0,40}?\bFROM|TRUNCATE(?:\s+TABLE)?)\s+`?buchungen`?(?![\w-])/i';
pruefe('Suchausdruck trifft DELETE FROM, DELETE b FROM … b, Backticks und TRUNCATE',
    preg_match($loescht, 'DELETE FROM buchungen WHERE id = ?') === 1
    && preg_match($loescht, 'DELETE b FROM buchungen b JOIN x') === 1
    && preg_match($loescht, 'delete from `buchungen`') === 1
    && preg_match($loescht, 'TRUNCATE TABLE buchungen') === 1);
pruefe('… und nicht SELECT … FROM buchungen, buchungen_x oder die Ablage',
    preg_match($loescht, 'SELECT * FROM buchungen WHERE id = ?') === 0
    && preg_match($loescht, 'DELETE FROM buchungen_alt') === 0
    && preg_match($loescht, 'DELETE FROM abgesagte_termine WHERE sprechtag_id = ?') === 0);
$fn = ($f = strpos($abq, 'function bu_buchungen_entfernen(')) === false ? '' : ab_block($abq, $f);
$arch = ($f = strpos($ix, 'if ($archivieren) {')) === false ? '' : ab_block($ix, $f);
pruefe('Voraussetzung: genau eine Löschstelle in der Funktion und eine im Archivieren gefunden',
    preg_match_all($loescht, $fn) === 1 && preg_match_all($loescht, $arch) === 1);
$backend = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($WURZEL . '/backend', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) $backend[] = $f->getPathname();
$sonst = [];
foreach ($backend as $p) {
    $c = ab_code($p);
    if ($fn !== '') $c = str_replace($fn, '', $c);
    if ($arch !== '') $c = str_replace($arch, '', $c);
    if (preg_match($loescht, $c)) $sonst[] = substr($p, strlen($WURZEL) + 1);
}
pruefe('Voraussetzung: PHP-Dateien unter backend/ samt Unterordnern gefunden (mind. 15)', count($backend) >= 15);
pruefe('keine Datei löscht Buchungen außer bu_buchungen_entfernen und dem Archivieren', $sonst === []);
if ($sonst !== []) echo '    (' . implode(', ', $sonst) . ")\n";
pruefe('index.php lädt absagen.php vor der ersten Route (die Ausfall-Route steht vor buchungen.php)',
    ($r = strpos($ix, "require_once __DIR__ . '/absagen.php';")) !== false
    && $r < (int)strpos($ix, "require __DIR__ . '/buchungen.php';") && $r < (int)strpos($ix, "\$seg[0]"));

// ------------------------------------------------------------
echo "einladungen.erledigt entfällt (E18, G5)\n";
// Engstelle über alle PHP-Dateien unter backend/ (oben ermittelt), ohne
// Kommentare: Kein Code liest oder schreibt die Spalte. Gesucht werden die
// Schreibweisen der Spalte, nicht das Wort – „ist erledigt.“ in einem Satz
// bleibt erlaubt.
$spalte = '/\.\s*erledigt\b|\bSET\s+erledigt\b|\berledigt\s*=|[\'"]erledigt[\'"]|\berledigt\s*,/';
pruefe('Suchausdruck trifft SET erledigt, e.erledigt, $r[\'erledigt\'] und erledigt in einer Spaltenliste',
    preg_match($spalte, 'UPDATE einladungen SET erledigt = 1 WHERE id = ?') === 1
    && preg_match($spalte, 'SELECT e.id, e.erledigt FROM einladungen e') === 1
    && preg_match($spalte, "\$z['erledigt']") === 1
    && preg_match($spalte, 'SELECT id, erledigt, hinweis FROM einladungen') === 1);
pruefe('… und nicht das Wort in einem Satz', preg_match($spalte, "'Die Einladung ist erledigt.'") === 0);
$mitSpalte = [];
foreach ($backend as $p) {
    if (preg_match($spalte, ab_code($p))) $mitSpalte[] = substr($p, strlen($WURZEL) + 1);
}
pruefe('kein Code unter backend/ liest oder schreibt einladungen.erledigt', $backend !== [] && $mitSpalte === []);
if ($mitSpalte !== []) echo '    (' . implode(', ', $mitSpalte) . ")\n";

echo "Migration 25 (am Text geprüft – kein MariaDB hier)\n";
$m25roh = (string)@file_get_contents($SQL . '/25_einladung_erledigt_entfernen.sql');
pruefe('Voraussetzung: sql/25_einladung_erledigt_entfernen.sql gelistet',
    in_array('25_einladung_erledigt_entfernen.sql', scandir($SQL) ?: [], true));
pruefe('entfernt genau die Spalte, zweimal einspielbar (DROP COLUMN IF EXISTS), sonst nichts',
    trim($ohneSqlKommentar($m25roh)) === 'ALTER TABLE einladungen DROP COLUMN IF EXISTS erledigt;');
// Die Reihenfolge (G6) steht im Kopf beider Dateien; der Betreiber liest
// sie dort. Geprüft ist der Wortlaut, nicht die Ausführung.
pruefe('Reihenfolge im Kopf: 24 VOR dem Deploy, 25 NACH dem Deploy',
    str_contains((string)@file_get_contents($SQL . '/24_abgesagte_termine.sql'), 'Diese Datei VOR dem Deploy einspielen.')
    && str_contains($m25roh, 'Diese Datei NACH dem Deploy einspielen.'));

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
