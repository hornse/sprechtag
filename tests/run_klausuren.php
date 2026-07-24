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

// Nachbau der echten Instanz-Daten (Sondierung 24.07.2026)
$plan = ['days'=>[['gridEntries'=>[
    e('NORMAL_TEACHING_PERIOD','REGULAR',  ['Gr'=>'Greitemann'],'WP'),
    e('NORMAL_TEACHING_PERIOD','REGULAR',  ['Gr'=>'Greitemann'],'WP'),
    e('NORMAL_TEACHING_PERIOD','REGULAR',  ['Kl'=>'Kleis'],'Sp'),
    e('NORMAL_TEACHING_PERIOD','CHANGED',  ['Mor'=>'Mor'],'If6'),   // Vertretung
    e('NORMAL_TEACHING_PERIOD','CHANGED',  ['Gv'=>'Gv'],'If6'),     // Vertretung
    e('EXAM','REGULAR',                    ['Ew'=>'Erlenwein'],'If6'),
    e('EXAM','CHANGED',                    ['Gr'=>'Greitemann'],'WP'), // verlegt
    e('EVENT','CHANGED',                   ['Ev'=>'Event'],'X'),
]]]];

echo "Mit Klausuren (Voreinstellung)\n";
$r = rest_lehrkraefte_aus_entries($plan, true);
$k = array_keys($r['lehrkraefte']);
pruefe('Ew wird über EXAM/REGULAR gefunden', in_array('Ew',$k,true));
pruefe('Vertretung Mor NICHT gewertet', !in_array('Mor',$k,true));
pruefe('Vertretung Gv NICHT gewertet', !in_array('Gv',$k,true));
pruefe('EVENT NICHT gewertet', !in_array('Ev',$k,true));
pruefe('EXAM/CHANGED zählt nicht als Klausur',
    ($r['lehrkraefte']['Gr']['klausuren'] ?? 0) === 0);
pruefe('Ew hat 0 reguläre Stunden', $r['lehrkraefte']['Ew']['stunden'] === 0);
pruefe('Ew hat 1 Klausur', $r['lehrkraefte']['Ew']['klausuren'] === 1);
pruefe('Sortierung: Gr (2 Std) zuerst', $k[0] === 'Gr');
pruefe('Ew steht hinter regulären Lehrkräften', array_search('Ew',$k,true) === 2);

echo "Ohne Klausuren (abgeschaltet)\n";
$r2 = rest_lehrkraefte_aus_entries($plan, false);
pruefe('Ew fehlt', !in_array('Ew', array_keys($r2['lehrkraefte']), true));
pruefe('Gr und Kl weiterhin da', count($r2['lehrkraefte']) === 2);

echo "Rückwärtskompatibilität\n";
$r3 = rest_lehrkraefte_aus_entries($plan);   // ohne zweiten Parameter
pruefe('Standard = mit Klausuren', isset($r3['lehrkraefte']['Ew']));
pruefe('Feld stunden weiterhin vorhanden', isset($r3['lehrkraefte']['Gr']['stunden']));
pruefe('Feld faecher weiterhin vorhanden', isset($r3['lehrkraefte']['Gr']['faecher']));

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
