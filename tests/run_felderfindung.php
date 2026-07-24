<?php
require __DIR__ . '/../backend/api/sondierung.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

echo "sondierung_felder_finden\n";
// Nachbau einer denkbaren REST-Antwort mit den gesuchten Feldern
$antwort = ['data' => ['students' => [
    ['id'=>9569,'foreName'=>'Anna','longName'=>'Muster','active'=>true,
     'exitDate'=>'2029-07-31','externId'=>'1101130','klasse'=>'6b'],
    ['id'=>9570,'foreName'=>'Ben','longName'=>'Beispiel','active'=>false,
     'exitDate'=>'2024-07-31','externId'=>'407169','klasse'=>''],
]]];
$r = sondierung_felder_finden($antwort);
pruefe('Datensätze gezählt', $r['anzahl_datensaetze'] === 2);
pruefe('aktiv-Feld erkannt', $r['aktiv_feld'] === ['active']);
pruefe('Austrittsfeld erkannt', in_array('exitDate', $r['austritt_feld'], true));
pruefe('externe ID erkannt', in_array('externId', $r['externe_id_feld'], true));
pruefe('Klassenfeld erkannt', in_array('klasse', $r['klassen_feld'], true));
pruefe('KEINE Werte im Ergebnis',
    !str_contains(json_encode($r), 'Muster') && !str_contains(json_encode($r), '1101130'));

echo "Varianten der Feldbenennung\n";
$v = sondierung_felder_finden(['liste' => [['id'=>1,'isActive'=>1,'validTo'=>'x','key'=>'abc']]]);
pruefe('isActive erkannt', $v['aktiv_feld'] === ['isActive']);
pruefe('validTo als Austritt', in_array('validTo', $v['austritt_feld'], true));
pruefe('key als externe ID', in_array('key', $v['externe_id_feld'], true));

echo "Fehlerfälle\n";
pruefe('leere Antwort', sondierung_felder_finden([]) === []);
pruefe('kein Array', sondierung_felder_finden('x') === []);
pruefe('keine Liste von Objekten', sondierung_felder_finden(['a'=>'b','c'=>'d']) === []);
$ohne = sondierung_felder_finden(['x' => [['id'=>1,'name'=>'y']]]);
pruefe('fehlende Felder als leere Listen',
    $ohne['aktiv_feld'] === [] && $ohne['austritt_feld'] === []);

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
