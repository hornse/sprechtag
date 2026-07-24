<?php
require __DIR__ . '/../backend/api/schueler.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

echo "schueler_datum_normieren\n";
pruefe('deutsches Format', schueler_datum_normieren('31.07.2029') === '2029-07-31');
pruefe('einstellige Werte', schueler_datum_normieren('1.7.2029') === '2029-07-01');
pruefe('ISO bleibt', schueler_datum_normieren('2029-07-31') === '2029-07-31');
pruefe('leer bleibt leer', schueler_datum_normieren('') === '');
pruefe('Unsinn -> leer', schueler_datum_normieren('irgendwas') === '');
pruefe('Leerzeichen toleriert', schueler_datum_normieren(' 31.07.2029 ') === '2029-07-31');

echo "schueler_ist_aktiv (Stichtag 2026-07-24)\n";
$h = '2026-07-24';
pruefe('ohne Austritt -> aktiv', schueler_ist_aktiv('', $h) === true);
pruefe('Austritt in der Zukunft -> aktiv', schueler_ist_aktiv('2029-07-31', $h) === true);
pruefe('Austritt in der Vergangenheit -> nicht aktiv',
    schueler_ist_aktiv('2024-07-31', $h) === false);
pruefe('Austritt heute -> noch aktiv', schueler_ist_aktiv('2026-07-24', $h) === true);
pruefe('Austritt gestern -> nicht aktiv', schueler_ist_aktiv('2026-07-23', $h) === false);

echo "CSV mit Austrittsdatum\n";
$r = schueler_csv_parsen(
    "Aahan;Aahan;06B;1101130;31.07.2029\n" .
    "Ehemalig;Erika;10a;407169;31.07.2024\n" .
    "OhneDatum;Otto;7c;12345");
pruefe('drei Zeilen', count($r['zeilen']) === 3);
pruefe('aktueller Schüler aktiv', $r['zeilen'][0]['aktiv'] === 1);
pruefe('Austritt normiert', $r['zeilen'][0]['austritt'] === '2029-07-31');
pruefe('ehemaliger Schüler inaktiv', $r['zeilen'][1]['aktiv'] === 0);
pruefe('Schild-ID 407169 erkannt', $r['zeilen'][1]['schild_id'] === '407169');
pruefe('ohne Datum -> aktiv', $r['zeilen'][2]['aktiv'] === 1);
pruefe('ohne Datum -> Austritt leer', $r['zeilen'][2]['austritt'] === '');

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
