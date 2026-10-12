<?php
require __DIR__ . '/../backend/auth/extractors.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

function e(string $typ, string $status, array $lehrer, string $fach): array {
    $p2 = [];
    foreach ($lehrer as $k => $l) {
        $p2[] = ['current' => ['type'=>'TEACHER','shortName'=>$k,'longName'=>$l]];
    }
    return ['type'=>$typ,'status'=>$status,
        'position1'=>[['current'=>['type'=>'SUBJECT','shortName'=>$fach]]],
        'position2'=>$p2];
}

// Aufbau nach der Sondierung vom 24.07.2026. Namen und Kürzel ERFUNDEN
// (v0.9.97, gleiche Länge, Sortierstellen erhalten); bis dahin standen hier echte.
$plan = ['days'=>[['gridEntries'=>[
    e('NORMAL_TEACHING_PERIOD','REGULAR',  ['Gq'=>'Erfundmann'],'WP'),
    e('NORMAL_TEACHING_PERIOD','REGULAR',  ['Gq'=>'Erfundmann'],'WP'),
    e('NORMAL_TEACHING_PERIOD','REGULAR',  ['Kq'=>'Erfuk'],'Sp'),
    e('NORMAL_TEACHING_PERIOD','CHANGED',  ['Mqx'=>'Mqx'],'If6'),   // Vertretung
    e('NORMAL_TEACHING_PERIOD','CHANGED',  ['Gx'=>'Gx'],'If6'),     // Vertretung
    e('EXAM','REGULAR',                    ['Ez'=>'ErfundenE'],'If6'),
    e('EXAM','CHANGED',                    ['Gq'=>'Erfundmann'],'WP'), // verlegt
    e('EVENT','CHANGED',                   ['Ev'=>'Event'],'X'),
]]]];

echo "Mit Klausuren (Voreinstellung)\n";
$r = rest_lehrkraefte_aus_entries($plan, true);
$k = array_keys($r['lehrkraefte']);
pruefe('Ez wird über EXAM/REGULAR gefunden', in_array('Ez',$k,true));
pruefe('Vertretung Mqx NICHT gewertet', !in_array('Mqx',$k,true));
pruefe('Vertretung Gx NICHT gewertet', !in_array('Gx',$k,true));
pruefe('EVENT NICHT gewertet', !in_array('Ev',$k,true));
pruefe('EXAM/CHANGED zählt nicht als Klausur',
    ($r['lehrkraefte']['Gq']['klausuren'] ?? 0) === 0);
pruefe('Ez hat 0 reguläre Stunden', $r['lehrkraefte']['Ez']['stunden'] === 0);
pruefe('Ez hat 1 Klausur', $r['lehrkraefte']['Ez']['klausuren'] === 1);
pruefe('Sortierung: Gq (2 Std) zuerst', $k[0] === 'Gq');
pruefe('Ez steht hinter regulären Lehrkräften', array_search('Ez',$k,true) === 2);

echo "Ohne Klausuren (abgeschaltet)\n";
$r2 = rest_lehrkraefte_aus_entries($plan, false);
pruefe('Ez fehlt', !in_array('Ez', array_keys($r2['lehrkraefte']), true));
pruefe('Gq und Kq weiterhin da', count($r2['lehrkraefte']) === 2);

echo "Rückwärtskompatibilität\n";
$r3 = rest_lehrkraefte_aus_entries($plan);   // ohne zweiten Parameter
pruefe('Standard = mit Klausuren', isset($r3['lehrkraefte']['Ez']));
pruefe('Feld stunden weiterhin vorhanden', isset($r3['lehrkraefte']['Gq']['stunden']));
pruefe('Feld faecher weiterhin vorhanden', isset($r3['lehrkraefte']['Gq']['faecher']));

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
