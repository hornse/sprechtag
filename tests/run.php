<?php
// ============================================================
// tests/run.php – Offline-Tests (ohne Netz, ohne DB)
// Aufruf: php tests/run.php   → Exit-Code 0 = alles grün
// ============================================================

declare(strict_types=1);

require __DIR__ . '/../backend/api/sondierung.php';

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

echo "sondierung_kind_strukturen\n";

// 1. Typische app/data-Form eines Eltern-Accounts: user.students[]
$json = ['user' => ['id' => 7, 'students' => [
    ['id' => 111, 'displayName' => 'Testkind A'],
    ['id' => 222, 'displayName' => 'Testkind B'],
]]];
$funde = sondierung_kind_strukturen($json);
pruefe('findet user.students', count($funde) === 1 && $funde[0]['pfad'] === 'user.students');
pruefe('zählt 2 Kinder', $funde[0]['anzahl'] === 2);
pruefe('extrahiert IDs 111,222', $funde[0]['ids'] === [111, 222]);
pruefe('liefert Feldnamen', in_array('displayName', $funde[0]['schluessel_erstes'], true));

// 2. Alternative Formen: children als Einzelobjekt, personId statt id
$json = ['data' => ['children' => ['personId' => 333, 'name' => 'X']]];
$funde = sondierung_kind_strukturen($json);
pruefe('findet Einzelobjekt children', count($funde) === 1 && $funde[0]['anzahl'] === 1);
pruefe('nutzt personId als Fallback', $funde[0]['ids'] === [333]);

// 3. Verschachtelt + kein Fund bei irrelevanten Schlüsseln
$funde = sondierung_kind_strukturen(['a' => ['b' => ['rooms' => [['id' => 1]]]]]);
pruefe('keine Fehltreffer bei rooms', $funde === []);
$funde = sondierung_kind_strukturen(['x' => ['y' => ['persons' => [['id' => 9]]]]]);
pruefe('findet tief verschachtelte persons', count($funde) === 1 && $funde[0]['pfad'] === 'x.y.persons');

// 4. Nicht-Array-Eingaben stürzen nicht ab
pruefe('robust bei String-Eingabe', sondierung_kind_strukturen('kaputt') === []);
pruefe('robust bei null', sondierung_kind_strukturen(null) === []);

echo "sondierung_kuerzen\n";
pruefe('kurzer Text unverändert', sondierung_kuerzen('abc') === 'abc');
$lang = str_repeat('a', 1000);
$g = sondierung_kuerzen($lang, 100);
pruefe('langer Text gekürzt + markiert',
    strlen($g) < 150 && str_ends_with($g, '…[gekürzt]'));

echo "sondierung_schueler_ids\n";
// Befund 07/2026: user.person (Eltern-Person!) darf NICHT als Kind gelten
$funde = [
    ['pfad' => 'user.person',   'ids' => [476]],
    ['pfad' => 'user.students', 'ids' => [13914, 14069]],
];
pruefe('ignoriert user.person, nimmt user.students',
    sondierung_schueler_ids($funde) === ['13914', '14069']);
pruefe('manuelle ID hat Vorrang',
    sondierung_schueler_ids($funde, '999') === ['999']);
pruefe('leer ohne Student-Pfade',
    sondierung_schueler_ids([['pfad' => 'user.person', 'ids' => [476]]]) === []);
pruefe('-1 wird verworfen (Admin)',
    sondierung_schueler_ids([['pfad' => 'user.students', 'ids' => [-1]]]) === []);
pruefe('max begrenzt',
    sondierung_schueler_ids([['pfad' => 'x.children', 'ids' => [1, 2, 3, 4]]], '', 2) === ['1', '2']);

echo "sondierung_lehrer_aus_entries\n";
$entries = ['days' => [['gridEntries' => [[
    'position1' => [['current' => ['type' => 'CLASS',   'shortName' => '7a', 'id' => 3]]],
    'position2' => [['current' => ['type' => 'TEACHER', 'shortName' => 'Ho', 'id' => 1013]],
                    ['current' => ['type' => 'TEACHER', 'shortName' => 'Mu', 'id' => 7]]],
    'position3' => [['current' => ['type' => 'SUBJECT', 'shortName' => 'M',  'id' => 9]]],
]]]]];
$lehrer = sondierung_lehrer_aus_entries($entries);
pruefe('findet beide Lehrkräfte, keine Fächer/Klassen',
    $lehrer === ['Ho (1013)', 'Mu (7)']);
pruefe('dedupliziert', count(sondierung_lehrer_aus_entries(
    ['a' => $entries, 'b' => $entries])) === 2);
pruefe('robust bei Nicht-Array', sondierung_lehrer_aus_entries('x') === []);

echo "sondierung_kandidaten\n";
$k = sondierung_kandidaten();
pruefe('Gruppen sprechtag + mitteilungen vorhanden',
    isset($k['sprechtag'], $k['mitteilungen']));
$allePfadeOk = true;
foreach ($k as $proben) {
    foreach ($proben as $p) {
        if (!str_starts_with($p['pfad'], '/WebUntis/')) $allePfadeOk = false;
    }
}
pruefe('alle Pfade beginnen mit /WebUntis/', $allePfadeOk);
$mitParam = array_filter($k['mitteilungen'], fn($p) => isset($p['query']['recipients']));
pruefe('recipients-Parameterprobe vorhanden',
    count($mitParam) === 1 && reset($mitParam)['query']['recipients'] === '{USER_ID}');


echo "sondierung_stammdaten – Anonymisierung\n";
// Die Funktion braucht Client-Objekte; hier wird nur die Logik der
// Feldauswahl geprüft, indem der relevante Code nachgebildet wird.
// (Der Netzteil ist in den Integrationstests abgedeckt.)
$namensfelder = ['name', 'foreName', 'longName', 'displayName', 'key'];
$schueler = ['id' => 13914, 'key' => 'PAULOWSKI', 'name' => 'PaulowskiPau',
             'foreName' => 'Paul', 'longName' => 'Paulowski', 'klasseId' => 42];
$beispiel = [];
foreach ($schueler as $feld => $wert) {
    $beispiel[$feld] = in_array($feld, $namensfelder, true) ? '…' : $wert;
}
pruefe('Vorname anonymisiert',   $beispiel['foreName'] === '…');
pruefe('Nachname anonymisiert',  $beispiel['longName'] === '…');
pruefe('Kurzname anonymisiert',  $beispiel['name'] === '…');
pruefe('key anonymisiert',       $beispiel['key'] === '…');
pruefe('ID bleibt sichtbar',     $beispiel['id'] === 13914);
pruefe('Klassen-ID bleibt',      $beispiel['klasseId'] === 42);
pruefe('kein Klarname im Beispiel',
    !str_contains(json_encode($beispiel), 'Paul'));

echo "Gruppenfeld-Erkennung\n";
$erkenne = fn(array $felder) => array_values(array_filter($felder,
    fn($f) => preg_match('/group|gruppe|category|kategorie|type|typ|flag/i', $f)));
pruefe('erkennt studentGroup', $erkenne(['id', 'studentGroup']) === ['studentGroup']);
pruefe('erkennt category',     $erkenne(['id', 'category']) === ['category']);
pruefe('erkennt personType',   $erkenne(['id', 'personType']) === ['personType']);
pruefe('keine Fehltreffer',    $erkenne(['id', 'name', 'klasseId']) === []);


echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler TEST(S) ROT\n");
exit($fehler === 0 ? 0 : 1);
