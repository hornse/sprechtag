<?php
// ============================================================
// tests/run_kalender_abo.php – das Kalender-Abo liefert nur kommende
// Sprechtage (v0.9.77, Fehler aus dem Betrieb, E21)
// Aufruf: php tests/run_kalender_abo.php   → Exit-Code 0 = alles grün
//
// Bis v0.9.76 lieferte GET /api/kalender/{token}.ics die Termine ALLER
// Sprechtage, auch vergangener (gemeldet: ein Termin vom 27.07.2026 stand
// noch in der Kalender-App). Damit wanderten sie dauerhaft in fremde
// Kalender-Apps.
//
// Geprüft wird AUSGEFÜHRT, gegen eine Datenbank:
//   * kal_abo_ics() – Eltern- und Lehrkraft-Abo: Sprechtag von heute und
//     alle künftigen drin, gestern (Grenze) und Juli nicht;
//   * Archivieren (Zweig aus index.php, ausgeführt) leert den Inhalt
//     beider Abos für diesen Sprechtag;
//   * die Route ruft kal_abo_ics() mit dem heutigen Datum und lädt keine
//     Buchungen an ihr vorbei (Quelltext; die Route endet mit exit).
//
// Werte ERFUNDEN.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

$wurzel = __DIR__ . '/..';
require $wurzel . '/backend/api/kalender.php';

pruefe('Voraussetzung: kal_abo_ics() vorhanden', function_exists('kal_abo_ics'));
if (!function_exists('kal_abo_ics')) { echo "\n$fehler ROT\n"; exit(1); }

$HEUTE = '2026-10-10';
$neu = function (): PDO {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('CREATE TABLE sprechtage (id INTEGER PRIMARY KEY, name TEXT, datum TEXT, slot_minuten INT,
        beginn TEXT, ende TEXT, phase TEXT)');
    $db->exec('CREATE TABLE lehrer (id INTEGER PRIMARY KEY, name TEXT, kuerzel TEXT)');
    $db->exec('CREATE TABLE raeume (id INTEGER PRIMARY KEY, kuerzel TEXT)');
    $db->exec('CREATE TABLE sprechtag_lehrer (sprechtag_id INT, lehrer_id INT, raum_id INT)');
    $db->exec('CREATE TABLE buchungen (id INTEGER PRIMARY KEY, sprechtag_id INT, lehrer_id INT, slot_beginn TEXT,
        eltern_user_id INT, schueler_id INT, kommentar TEXT DEFAULT "", kind_name TEXT NOT NULL DEFAULT "",
        kind_klasse TEXT NOT NULL DEFAULT "")');
    $db->exec('CREATE TABLE einladungen (id INTEGER PRIMARY KEY, sprechtag_id INT)');
    $db->exec('CREATE TABLE kind_lehrer_cache (sprechtag_id INT)');
    $db->exec('CREATE TABLE mitteilungen (id INTEGER PRIMARY KEY, sprechtag_id INT)');
    $db->exec('CREATE TABLE kalender_abo (eltern_user_id INT PRIMARY KEY, token TEXT)');
    $db->exec('CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)');
    $db->exec("INSERT INTO lehrer VALUES (7, 'Erfundene Lehrkraft', 'Ef')");
    // Juli (vergangen, nicht archiviert), gestern (Grenze), heute, zwei künftige
    $tage = [1 => '2026-07-27', 2 => '2026-10-09', 3 => '2026-10-10', 4 => '2026-11-12', 5 => '2026-12-01'];
    $phase = [1 => 'geschlossen', 2 => 'geschlossen', 3 => 'phase2', 4 => 'geschlossen', 5 => 'phase1'];
    foreach ($tage as $id => $d) {
        $db->prepare("INSERT INTO sprechtage VALUES (?, ?, ?, 10, '15:00:00', '19:00:00', ?)")
           ->execute([$id, "Sprechtag $id", $d, $phase[$id]]);
        $db->prepare("INSERT INTO buchungen (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id,
                      kind_name, kind_klasse) VALUES (?, 7, '15:00:00', 5001, 601, ?, '5a')")
           ->execute([$id, "KindTag$id"]);
    }
    // fremde Eltern am heutigen Sprechtag
    $db->exec("INSERT INTO buchungen (sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id, kind_name)
               VALUES (3, 7, '15:10:00', 5002, 602, 'FremdesKind')");
    $db->exec("INSERT INTO kalender_abo VALUES (5001, '" . str_repeat('a', 48) . "')");
    $db->exec("INSERT INTO kalender_abo VALUES (1000000007, '" . str_repeat('b', 48) . "')");
    return $db;
};
$ELT = str_repeat('a', 48);
$LK = str_repeat('b', 48);
$tageIn = function (?array $abo): array {
    preg_match_all('/DTSTART[^:]*:(\d{8})/', (string)($abo['ics'] ?? ''), $m);
    return $m[1];
};

// ------------------------------------------------------------
echo "Eltern-Abo: nur kommende Sprechtage\n";
$db = $neu();
$e = kal_abo_ics($db, $ELT, $HEUTE);
pruefe('heute und die zwei künftigen Sprechtage drin (genau drei Termine), Datei sprechtag.ics',
    $tageIn($e) === ['20261010', '20261112', '20261201'] && ($e['datei'] ?? '') === 'sprechtag.ics');
pruefe('vergangener Sprechtag (27.07.) fehlt', !in_array('20260727', $tageIn($e), true));
pruefe('Grenze: Sprechtag von gestern fehlt', !in_array('20261009', $tageIn($e), true));
pruefe('nur die eigenen Termine (fremdes Kind am selben Tag fehlt)',
    !str_contains((string)($e['ics'] ?? ''), 'FremdesKind'));

echo "Lehrkraft-Abo: ebenso\n";
$l = kal_abo_ics($db, $LK, $HEUTE);
pruefe('heute und die zwei künftigen drin, Datei sprechtag-lehrkraft.ics',
    array_values(array_unique($tageIn($l))) === ['20261010', '20261112', '20261201']
    && ($l['datei'] ?? '') === 'sprechtag-lehrkraft.ics');
pruefe('gestern und Juli fehlen, auch die Kindnamen von dort',
    !in_array('20261009', $tageIn($l), true) && !in_array('20260727', $tageIn($l), true)
    && !str_contains((string)($l['ics'] ?? ''), 'KindTag1') && !str_contains((string)($l['ics'] ?? ''), 'KindTag2'));
pruefe('unbekannter Token: null (die Route antwortet 404)', kal_abo_ics($db, str_repeat('c', 48), $HEUTE) === null);
pruefe('Stichtag wirkt: einen Tag später fällt auch der heutige heraus',
    $tageIn(kal_abo_ics($db, $ELT, '2026-10-11')) === ['20261112', '20261201']);

// ------------------------------------------------------------
echo "Archivieren leert den Inhalt der Abos (Zweig aus index.php, ausgeführt)\n";
$code = [];
foreach (token_get_all((string)file_get_contents($wurzel . '/backend/api/index.php')) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) continue;
    $code[] = $t;
}
$txt = fn($t) => is_array($t) ? $t[1] : $t;
$rumpf = null; $anzahl = 0;
for ($i = 0; $i + 4 < count($code); $i++) {
    if (is_array($code[$i]) && $code[$i][0] === T_IF && $txt($code[$i + 1]) === '('
        && $txt($code[$i + 2]) === '$archivieren' && $txt($code[$i + 3]) === ')' && $txt($code[$i + 4]) === '{') {
        $anzahl++; $tiefe = 0; $teile = [];
        for ($k = $i + 4; $k < count($code); $k++) {
            $s = $txt($code[$k]);
            if ($s === '{') $tiefe++;
            if ($s === '}') $tiefe--;
            $teile[] = $s;
            if ($tiefe === 0) break;
        }
        $rumpf = implode(' ', $teile);
    }
}
pruefe('Voraussetzung: genau ein Block „if ($archivieren) { … }“ in index.php', $anzahl === 1);
$db = $neu();
if ($rumpf !== null) {
    $f = eval('return function ($pdo, $sid, $archivieren) { if ($archivieren) ' . $rumpf . ' };');
    $f($db, 4, true);
}
pruefe('nach dem Archivieren des Sprechtags vom 12.11.: fehlt im Eltern-Abo, die anderen bleiben',
    $tageIn(kal_abo_ics($db, $ELT, $HEUTE)) === ['20261010', '20261201']);
pruefe('… und im Lehrkraft-Abo samt Kindname',
    !in_array('20261112', $tageIn(kal_abo_ics($db, $LK, $HEUTE)), true)
    && !str_contains((string)(kal_abo_ics($db, $LK, $HEUTE)['ics'] ?? ''), 'KindTag4'));
$schema = (string)file_get_contents($wurzel . '/sql/02_sprechtag.sql');
pruefe('Löschen eines Sprechtags nimmt seine Buchungen mit (ON DELETE CASCADE, Schema)',
    preg_match('/CONSTRAINT fk_bu_sprechtag FOREIGN KEY \(sprechtag_id\)\s+REFERENCES sprechtage \(id\) ON DELETE CASCADE/', $schema) === 1);

// ------------------------------------------------------------
echo "Route (Quelltext – sie endet mit exit und ist nicht ausführbar)\n";
$idx = '';
foreach (token_get_all((string)file_get_contents($wurzel . '/backend/api/index.php')) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
    $idx .= is_array($t) ? $t[1] : $t;
}
$a = strpos($idx, "=== 'kalender' && isset(\$seg[1])");
$b = $a === false ? false : strpos($idx, "=== 'lehrer-kalender'", $a);
$route = ($a !== false && $b !== false) ? substr($idx, $a, $b - $a) : '';
pruefe('Voraussetzung: Abo-Route gefunden', $route !== '');
pruefe('Abo-Route ruft kal_abo_ics mit dem heutigen Datum',
    str_contains($route, "kal_abo_ics(\$pdo, (string)\$token, date('Y-m-d'))"));
pruefe('Abo-Route lädt keine Buchungen an kal_abo_ics vorbei',
    !str_contains($route, 'kal_buchungen_laden(') && !str_contains($route, 'kal_lehrer_buchungen('));

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
