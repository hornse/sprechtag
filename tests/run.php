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

echo "sondierung_kandidaten\n";
$k = sondierung_kandidaten();
pruefe('drei Gruppen vorhanden',
    isset($k['sprechtag'], $k['stundenplan'], $k['mitteilungen']));
$allePfadeOk = true;
foreach ($k as $proben) {
    foreach ($proben as $p) {
        if (!str_starts_with($p['pfad'], '/WebUntis/')) $allePfadeOk = false;
    }
}
pruefe('alle Pfade beginnen mit /WebUntis/', $allePfadeOk);
$platzhalter = $k['stundenplan'][0]['query'];
pruefe('Stundenplan-Probe nutzt Platzhalter',
    $platzhalter['resources'] === '{SCHUELER_ID}' && $platzhalter['start'] === '{START}');

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler TEST(S) ROT\n");
exit($fehler === 0 ? 0 : 1);
