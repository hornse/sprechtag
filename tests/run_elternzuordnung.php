<?php
require __DIR__ . '/../backend/api/mitteilungen.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

// Aufbau aus dem Mitschnitt vom 24.07.2026 (echte Antwortform).
// Erster Eintrag: Kennung und Anzeigename ERFUNDEN (v0.9.94, gleiche Länge,
// Sortierstelle zwischen 5979 und 10515 erhalten). Zweiter und dritter
// Eintrag ebenso ERFUNDEN (v0.9.97, Sortierstellen erhalten). Die
// Paulowski-Konten sind erfundene Testkonten (Betreiber, 12.10.2026) und
// stehen wie im Mitschnitt.
$users = [
    ['id'=>7350,'displayName'=>'Beispiel Anton','role'=>'LEGAL_GUARDIAN',
     'tags'=>['Paulowski Paul','Paulowski Petra']],
    ['id'=>16820,'displayName'=>'Erdacht Raimund','role'=>'LEGAL_GUARDIAN',
     'tags'=>['Paulowski Petra']],
    ['id'=>19460,'displayName'=>'Ausgedachte Frieda','role'=>'LEGAL_GUARDIAN',
     'tags'=>['Paulowski Paul']],
    ['id'=>10515,'displayName'=>'Paulowski Paula','role'=>'LEGAL_GUARDIAN',
     'tags'=>['Paulowski Petra']],
    ['id'=>17232,'displayName'=>'Paulowski Paula','role'=>'LEGAL_GUARDIAN',
     'tags'=>['Paulowski Petra']],
    ['id'=>5979,'displayName'=>'Paulowski Peter','role'=>'LEGAL_GUARDIAN',
     'tags'=>['Paulowski Paul','Paulowski Petra']],
    ['id'=>7480,'displayName'=>'Paulowski Paul','role'=>'STUDENT','tags'=>[]],
    ['id'=>7485,'displayName'=>'Paulowski Petra','role'=>'STUDENT','tags'=>[]],
];

echo "Zuordnung Kind -> Elternkonten (echte Daten)\n";
$paul = mit_eltern_zu_kind($users, 'Paulowski Paul');
$ids = array_column($paul['konten'], 'id');
sort($ids);
pruefe('Paul: drei Elternkonten', count($ids) === 3);
pruefe('Paul: korrekte IDs (7350, 5979, 19460)', $ids === [5979, 7350, 19460]);
pruefe('Paul: NICHT Erdacht (nur Petra)', !in_array(16820, $ids, true));
pruefe('Paul: KEINE Schüler-Konten', !in_array(7480, $ids, true));

$petra = mit_eltern_zu_kind($users, 'Paulowski Petra');
$idsP = array_column($petra['konten'], 'id');
sort($idsP);
pruefe('Petra: fünf Elternkonten', count($idsP) === 5);
pruefe('Petra: korrekte IDs', $idsP === [5979, 7350, 10515, 16820, 17232]);
pruefe('Petra: NICHT Ausgedachte (nur Paul)', !in_array(19460, $idsP, true));

echo "Namensvergleich\n";
pruefe('Reihenfolge egal (Vorname/Nachname)',
    count(mit_eltern_zu_kind($users, 'Paul Paulowski')['konten']) === 3);
pruefe('Komma-Schreibweise', 
    count(mit_eltern_zu_kind($users, 'Paulowski, Paul')['konten']) === 3);
pruefe('Groß-/Kleinschreibung egal',
    count(mit_eltern_zu_kind($users, 'PAULOWSKI PAUL')['konten']) === 3);
pruefe('doppelte Leerzeichen',
    count(mit_eltern_zu_kind($users, 'Paulowski   Paul')['konten']) === 3);

echo "Abgrenzung (keine Fehltreffer)\n";
pruefe('Teilname trifft nicht', mit_eltern_zu_kind($users, 'Paulowski')['konten'] === []);
pruefe('fremdes Kind trifft nicht',
    mit_eltern_zu_kind($users, 'Mustermann Max')['konten'] === []);
pruefe('leerer Name trifft nicht', mit_eltern_zu_kind($users, '')['konten'] === []);
pruefe('leerer Name meldet uneindeutig',
    mit_eltern_zu_kind($users, '')['eindeutig'] === false);
pruefe('leere Nutzerliste', mit_eltern_zu_kind([], 'Paulowski Paul')['konten'] === []);
pruefe('nur Schüler in der Liste',
    mit_eltern_zu_kind([['id'=>1,'role'=>'STUDENT','tags'=>[]]], 'X Y')['konten'] === []);

echo "mit_name_normieren\n";
pruefe('sortiert Wörter', mit_name_normieren('Paulowski Paul') === mit_name_normieren('Paul Paulowski'));
pruefe('Umlaute', mit_name_normieren('Mürfel Jöko') === mit_name_normieren('jöko mürfel'));
pruefe('leer bleibt leer', mit_name_normieren('') === '');
pruefe('nur Leerzeichen -> leer', mit_name_normieren('   ') === '');

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
