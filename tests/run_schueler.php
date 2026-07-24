<?php
require __DIR__ . '/../backend/api/schueler.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

echo "schueler_klasse_normieren\n";
pruefe('06B -> 6b', schueler_klasse_normieren('06B') === '6b');
pruefe('6b bleibt 6b', schueler_klasse_normieren('6b') === '6b');
pruefe('6 B -> 6b', schueler_klasse_normieren('6 B') === '6b');
pruefe('05A -> 5a', schueler_klasse_normieren('05A') === '5a');
pruefe('10 bleibt 10', schueler_klasse_normieren('10') === '10');
pruefe('EF bleibt EF', schueler_klasse_normieren('EF') === 'EF');
pruefe('ef -> EF', schueler_klasse_normieren('ef') === 'EF');
pruefe('Q1 bleibt Q1', schueler_klasse_normieren('Q1') === 'Q1');
pruefe('leer bleibt leer', schueler_klasse_normieren('') === '');
pruefe('Leerzeichen weg', schueler_klasse_normieren(' Q 2 ') === 'Q2');

echo "schueler_csv_parsen\n";
$r = schueler_csv_parsen("Paulowski;Paul;06B;007\nMuster;Maxi;6b;008");
pruefe('zwei Zeilen', count($r['zeilen']) === 2);
pruefe('Nachname gelesen', $r['zeilen'][0]['nachname'] === 'Paulowski');
pruefe('Vorname gelesen', $r['zeilen'][0]['vorname'] === 'Paul');
pruefe('Klasse normiert', $r['zeilen'][0]['klasse'] === '6b');
pruefe('Schild-ID gelesen', $r['zeilen'][0]['schild_id'] === '007');
pruefe('beide dieselbe Klasse',
    $r['zeilen'][0]['klasse'] === $r['zeilen'][1]['klasse']);

echo "Trennzeichen und Sonderfälle\n";
pruefe('Komma als Trenner',
    schueler_csv_parsen("A,B,7c")['zeilen'][0]['klasse'] === '7c');
pruefe('Tab als Trenner',
    schueler_csv_parsen("A\tB\t7c")['zeilen'][0]['klasse'] === '7c');
pruefe('Kopfzeile übersprungen',
    count(schueler_csv_parsen("Nachname;Vorname;Klasse\nA;B;7c")['zeilen']) === 1);
pruefe('Kommentarzeile übersprungen',
    count(schueler_csv_parsen("# Kommentar\nA;B;7c")['zeilen']) === 1);
pruefe('Leerzeile übersprungen',
    count(schueler_csv_parsen("\n\nA;B;7c\n\n")['zeilen']) === 1);
pruefe('ohne Schild-ID möglich',
    schueler_csv_parsen("A;B;7c")['zeilen'][0]['schild_id'] === '');
$u = schueler_csv_parsen("NurZweiFelder;X");
pruefe('zu kurze Zeile gemeldet', count($u['uebersprungen']) === 1);
pruefe('Zeilennummer in der Meldung', str_contains($u['uebersprungen'][0], 'Zeile 1'));
$u2 = schueler_csv_parsen("A;B;\n;X;7c");
pruefe('leere Klasse gemeldet', count($u2['uebersprungen']) === 2);
pruefe('Windows-Zeilenenden', count(schueler_csv_parsen("A;B;7c\r\nC;D;8a")['zeilen']) === 2);
pruefe('Umlaute erhalten',
    schueler_csv_parsen("Müller;Jörg;7c")['zeilen'][0]['nachname'] === 'Müller');

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
