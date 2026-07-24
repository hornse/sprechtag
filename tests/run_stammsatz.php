<?php
require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/auth/extractors.php';
$fehler = 0;
function pruefe(string $n, bool $ok): void {
    global $fehler; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; if (!$ok) $fehler++;
}
// Simuliert wu_kind_lehrer_ermitteln mit SQLite: Ew fehlt in den Stammdaten
$pdo = new PDO('sqlite::memory:', null, null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE lehrer (id INTEGER PRIMARY KEY, kuerzel TEXT)');
$pdo->exec("INSERT INTO lehrer VALUES (1,'Gr'), (2,'Kl')");   // Ew fehlt!
$pdo->exec('CREATE TABLE kind_lehrer_cache (sprechtag_id INT, schueler_id INT,
    lehrer_id INT, faecher TEXT, stunden INT, ermittelt_am TEXT)');

$ex = ['lehrkraefte' => [
    'Gr' => ['name'=>'Greitemann','stunden'=>1,'faecher'=>['WP'=>1]],
    'Kl' => ['name'=>'Kleis','stunden'=>2,'faecher'=>['Sp'=>2]],
    'Ew' => ['name'=>'Erlenwein','stunden'=>1,'faecher'=>['If6'=>1]],
]];
$stmtLehrer = $pdo->prepare('SELECT id FROM lehrer WHERE kuerzel = ? LIMIT 1');
$stmtCache = $pdo->prepare('INSERT INTO kind_lehrer_cache
    (sprechtag_id,schueler_id,lehrer_id,faecher,stunden,ermittelt_am)
    VALUES (?,?,?,?,?,datetime("now"))');
$anzahl = 0; $uebersprungen = [];
foreach ($ex['lehrkraefte'] as $kuerzel => $info) {
    $stmtLehrer->execute([$kuerzel]);
    $lid = $stmtLehrer->fetchColumn();
    if ($lid === false) { $uebersprungen[] = $kuerzel; continue; }
    $stmtCache->execute([1, 13914, (int)$lid,
        implode(', ', array_keys($info['faecher'])), $info['stunden']]);
    $anzahl++;
}
echo "Lehrkraft ohne Stammsatz\n";
pruefe('zwei geschrieben', $anzahl === 2);
pruefe('Ew als übersprungen gemeldet', $uebersprungen === ['Ew']);
pruefe('Meldung nicht leer (wäre früher stillschweigend)', $uebersprungen !== []);
pruefe('Cache enthält genau 2 Zeilen',
    (int)$pdo->query('SELECT COUNT(*) FROM kind_lehrer_cache')->fetchColumn() === 2);
echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
