<?php
require __DIR__ . '/../backend/api/mitteilungen.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

echo "Erweiterte Variantenliste\n";
$v = mit_varianten();
pruefe('sieben Varianten', count($v) === 7);
$ok = true; $ids = true;
foreach ($v as $name => $e) {
    if (!str_starts_with($e['pfad'], '/WebUntis/')) $ok = false;
    $b = ($e['body'])(5984, 'Betreff', 'Text');
    if (!str_contains(json_encode($b), '5984')) $ids = false;
}
pruefe('alle Pfade unter /WebUntis/', $ok);
pruefe('alle Bodies enthalten die Empfänger-ID', $ids);
pruefe('recipientIds-Variante vorhanden', isset($v['v1_recipientids']));
pruefe('message-Wrapper vorhanden', isset($v['v1_message_wrapper']));
pruefe('Wrapper kapselt korrekt',
    isset(($v['v1_message_wrapper']['body'])(1,'B','T')['message']['subject']));

echo "Detailmeldungen bei Fehlschlag\n";
// Simuliert vier abgelehnte Varianten
$antworten = [
    ['status' => 400, 'json' => ['errorMessage' => 'field recipients missing']],
    ['status' => 404, 'json' => ['errorCode' => 'NOT_FOUND']],
    ['status' => 500, 'json' => null],
];
$gruende = [];
foreach ($antworten as $a) {
    $b = mit_antwort_bewerten($a);
    $gruende[] = $b['grund'];
}
pruefe('400 nennt das fehlende Feld', str_contains($gruende[0], 'recipients missing'));
pruefe('404 wird als solches gemeldet', str_contains($gruende[1], '404'));
pruefe('500 wird gemeldet', str_contains($gruende[2], '500'));
pruefe('alle Gründe unterscheidbar', count(array_unique($gruende)) === 3);

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
