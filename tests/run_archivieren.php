<?php
// ============================================================
// tests/run_archivieren.php – was das Archivieren eines Sprechtags löscht
// (v0.9.74, H9 aus docs/HILFE-ABGLEICH-2026-10-09.md, E19-Nachtrag)
// Aufruf: php tests/run_archivieren.php   → Exit-Code 0 = alles grün
//
// Die Hilfeseite sagt den Eltern, was beim Archivieren gelöscht wird und
// was bleibt. Diese Suite hält die Aussage an den Code:
//   * AUSGEFÜHRT: Der Archivierzweig aus index.php läuft gegen eine
//     mitschreibende Datenbank – welche Tabellen er leert, nur für diesen
//     Sprechtag, und ohne Archivieren gar nichts.
//   * ENGSTELLE: Jede Tabelle mit einer Spalte sprechtag_id (ermittelt aus
//     sql/*.sql, nicht aufgezählt) wird beim Archivieren geleert – oder
//     steht ausdrücklich unter STRUKTUR. Kommt mit E19 die Ablage für
//     abgesagte Termine hinzu, wird diese Suite rot, bis sie mitgelöscht
//     wird.
//   * EINORDNUNG: Jede Tabelle OHNE sprechtag_id ist eingeordnet – als
//     personenbezogen (dann nennt die Hilfeseite sie) oder ohne Bezug zu
//     Eltern und Kindern. Eine neue Tabelle macht die Suite rot, bis
//     jemand entschieden hat, ob die Hilfeseite sie nennen muss.
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

// Bleibt beim Archivieren absichtlich stehen: die Struktur, die
// „Kopieren (Archiv wiederverwenden)“ übernimmt.
const STRUKTUR = ['sprechtag_lehrer', 'sprechtag_sonderlehrer'];

// Tabellen ohne sprechtag_id. Personenbezogen = über Eltern, Kinder oder
// deren Konten; diese nennt die Hilfeseite (frontend_datenschutz_test.js).
const OHNE_SPRECHTAG = [
    'login_log'    => 'personenbezogen',
    'schueler'     => 'personenbezogen',
    'kalender_abo' => 'personenbezogen',
    'app_admins'   => 'kollegium',
    'lehrer'       => 'kollegium',
    'raeume'       => 'struktur',
    'sonderrollen' => 'struktur',
    'sprechtage'   => 'struktur',
    'einstellungen'=> 'struktur',
];

// ---- Schema aus sql/*.sql ermitteln ----------------------------------
$spalten = [];   // tabelle => [spalte => true]
foreach (glob($wurzel . '/sql/*.sql') as $datei) {
    $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($datei));
    preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\n\) ENGINE/s', $sql, $tm, PREG_SET_ORDER);
    foreach ($tm as $t) {
        $spalten[$t[1]] ??= [];
        preg_match_all('/^\s+(\w+)\s+[A-Z]/m', $t[2], $sm);
        foreach ($sm[1] as $s) {
            if (in_array($s, ['PRIMARY', 'KEY', 'UNIQUE', 'CONSTRAINT'], true)) continue;
            $spalten[$t[1]][$s] = true;
        }
    }
    preg_match_all('/ALTER TABLE (\w+)(.*?)(?:;|\'|$)/s', $sql, $am, PREG_SET_ORDER);
    foreach ($am as $a) {
        preg_match_all('/ADD COLUMN (?:IF NOT EXISTS )?(\w+)/', $a[2], $cm);
        foreach ($cm[1] as $s) $spalten[$a[1]][$s] = true;
    }
}
$mitSid = array_keys(array_filter($spalten, fn($c) => isset($c['sprechtag_id'])));
sort($mitSid);
pruefe('Voraussetzung: Schema gelesen (mind. 14 Tabellen, mind. 6 mit sprechtag_id)',
    count($spalten) >= 14 && count($mitSid) >= 6);
pruefe('Voraussetzung: bekannte Spalte über ALTER erkannt (buchungen.kommentar)',
    isset($spalten['buchungen']['kommentar']));

// ---- Archivierzweig aus index.php ausführen ---------------------------
$tokens = token_get_all((string)file_get_contents($wurzel . '/backend/api/index.php'));
$code = []; // ohne Kommentare und Leerraum
foreach ($tokens as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) continue;
    $code[] = $t;
}
$txt = fn($t) => is_array($t) ? $t[1] : $t;
$rumpf = null; $anzahl = 0;
for ($i = 0; $i + 4 < count($code); $i++) {
    if (is_array($code[$i]) && $code[$i][0] === T_IF && $txt($code[$i + 1]) === '('
        && $txt($code[$i + 2]) === '$archivieren' && $txt($code[$i + 3]) === ')'
        && $txt($code[$i + 4]) === '{') {
        $anzahl++;
        $tiefe = 0; $teile = [];
        for ($k = $i + 4; $k < count($code); $k++) {
            $s = $txt($code[$k]);
            if ($s === '{' || (is_array($code[$k]) && $code[$k][0] === T_CURLY_OPEN)) $tiefe++;
            if ($s === '}') $tiefe--;
            $teile[] = $s;
            if ($tiefe === 0) break;
        }
        $rumpf = implode(' ', $teile);
    }
}
pruefe('Voraussetzung: genau ein Block „if ($archivieren) { … }“ in index.php', $anzahl === 1);

// Wann $archivieren wahr wird: die Zuweisung ausgeführt.
$zuweisung = null; $zAnzahl = 0;
for ($i = 0; $i + 1 < count($code); $i++) {
    if ($txt($code[$i]) === '$archivieren' && $txt($code[$i + 1]) === '=') {
        $zAnzahl++; $teile = [];
        for ($k = $i; $k < count($code) && $txt($code[$k]) !== ';'; $k++) $teile[] = $txt($code[$k]);
        $zuweisung = implode(' ', $teile) . ';';
    }
}
pruefe('Voraussetzung: genau eine Zuweisung an $archivieren', $zAnzahl === 1);
$wird = function (array $body) use ($zuweisung): ?bool {
    if ($zuweisung === null) return null;
    return eval($zuweisung . ' return $archivieren;');
};
pruefe('Archivieren nur bei Phase „archiviert“',
    $wird(['phase' => 'archiviert']) === true
    && $wird(['phase' => 'geschlossen']) === false
    && $wird(['name' => 'x']) === false
    && $wird(['phase' => '']) === false);

final class SchreibPdo
{
    public array $aufrufe = [];
    public function prepare(string $sql): object
    {
        $p = $this;
        return new class($sql, $p) {
            public function __construct(private string $sql, private SchreibPdo $p) {}
            public function execute(array $werte = []): bool
            { $this->p->aufrufe[] = [preg_replace('/\s+/', ' ', $this->sql), $werte]; return true; }
        };
    }
    public function exec(string $sql): int { $this->aufrufe[] = [$sql, []]; return 0; }
}
$lauf = function (bool $archivieren, int $sid) use ($rumpf): SchreibPdo {
    $pdo = new SchreibPdo();
    if ($rumpf !== null) {
        $f = eval('return function ($pdo, $sid, $archivieren) { if ($archivieren) ' . $rumpf . ' };');
        $f($pdo, $sid, $archivieren);
    }
    return $pdo;
};

$mit = $lauf(true, 42);
$geleert = []; $fremd = [];
foreach ($mit->aufrufe as [$sql, $werte]) {
    if (preg_match('/^DELETE FROM (\w+) WHERE sprechtag_id = \?$/', $sql, $m) && $werte === [42]) {
        $geleert[] = $m[1];
    } else {
        $fremd[] = $sql;
    }
}
sort($geleert);
pruefe('Archivieren leert Tabellen (mind. 4)', count($geleert) >= 4);
pruefe('jede Löschung nur für DIESEN Sprechtag (WHERE sprechtag_id = ?, Wert 42)', $fremd === []);
if ($fremd !== []) echo '    (anders: ' . implode(' | ', $fremd) . ")\n";
pruefe('ohne Archivieren wird nichts gelöscht', $lauf(false, 42)->aufrufe === []);
foreach (['buchungen', 'einladungen', 'kind_lehrer_cache', 'mitteilungen'] as $t) {
    pruefe("Archivieren leert $t", in_array($t, $geleert, true));
}

// ---- Engstelle: jede Tabelle mit sprechtag_id ---------------------------
$offen = array_values(array_diff($mitSid, $geleert, STRUKTUR));
pruefe('jede Tabelle mit sprechtag_id wird geleert oder ist Struktur', $offen === []);
if ($offen !== []) echo '    (weder geleert noch Struktur: ' . implode(', ', $offen) . ")\n";
pruefe('Struktur wird NICHT geleert (bleibt für „Kopieren“)',
    array_intersect(STRUKTUR, $geleert) === []);
pruefe('Struktur-Tabellen gibt es (Liste nicht veraltet)',
    array_diff(STRUKTUR, $mitSid) === []);
pruefe('geleert wird nur, was es im Schema gibt', array_diff($geleert, $mitSid) === []);

// ---- Einordnung der Tabellen ohne sprechtag_id -------------------------
$ohne = array_values(array_diff(array_keys($spalten), $mitSid));
$uneingeordnet = array_values(array_diff($ohne, array_keys(OHNE_SPRECHTAG)));
pruefe('jede Tabelle ohne sprechtag_id ist eingeordnet', $uneingeordnet === []);
if ($uneingeordnet !== []) echo '    (neu, nicht eingeordnet: ' . implode(', ', $uneingeordnet) . ")\n";
pruefe('Einordnung nicht veraltet (jede genannte Tabelle gibt es)',
    array_diff(array_keys(OHNE_SPRECHTAG), $ohne) === []);

// Die personenbezogenen bleiben beim Archivieren – genau das muss die
// Hilfe sagen. Hier: dass das Archivieren sie wirklich nicht anfasst.
$roh = implode(' ', array_column($mit->aufrufe, 0));
foreach (array_keys(array_filter(OHNE_SPRECHTAG, fn($a) => $a === 'personenbezogen')) as $t) {
    pruefe("$t bleibt beim Archivieren (die Hilfe nennt es)", !str_contains($roh, " $t "));
}

// Aufbewahrung des Login-Protokolls: Voreinstellung und Obergrenze, auf
// die sich die Hilfe beruft (frontend_datenschutz_test.js liest dieselben
// Stellen).
$idx = (string)file_get_contents($wurzel . '/backend/api/index.php');
pruefe('Login-Protokoll: Bereinigung nach login_log_tage vorhanden',
    str_contains($idx, "DELETE FROM login_log WHERE zeitpunkt < NOW() - INTERVAL ? DAY"));
pruefe('Kalender-Abo: kein Löschweg im Code (die Hilfe sagt: keine Frist)',
    preg_match('/DELETE FROM kalender_abo/', implode("\n", array_map(
        fn($f) => (string)file_get_contents($f), glob($wurzel . '/backend/api/*.php')))) === 0);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
