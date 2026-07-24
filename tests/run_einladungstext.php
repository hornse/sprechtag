<?php
require __DIR__ . '/../backend/api/mitteilungen.php';
$f = 0;
function pruefe(string $n, bool $ok): void { global $f; echo ($ok?'  ✓ ':'  ✗ ').$n."\n"; if(!$ok)$f++; }

echo "mit_text_einladung\n";
$t = mit_text_einladung('Elternsprechtag Herbst', '2026-11-20',
    'Jenny Greitemann', 'Paul Paulowski', 'Es geht um die Mitarbeit.');
pruefe('Betreff kennzeichnet Einladung', str_contains($t['betreff'], 'Einladung'));
pruefe('Sprechtag im Betreff', str_contains($t['betreff'], 'Elternsprechtag Herbst'));
pruefe('Datum deutsch', str_contains($t['text'], '20.11.2026'));
pruefe('Kind genannt', str_contains($t['text'], 'Paul Paulowski'));
pruefe('Lehrkraft als Absender', str_contains($t['text'], 'Jenny Greitemann'));
pruefe('Freitext übernommen', str_contains($t['text'], 'Es geht um die Mitarbeit.'));
pruefe('Hinweis auf Buchung', str_contains($t['text'], 'Termin'));
pruefe('Phase-1-Hinweis', str_contains($t['text'], 'eingeladenen'));

$t2 = mit_text_einladung('Sprechtag', '2026-11-20', 'Gr', '', '');
pruefe('ohne Kind kein leerer Einschub', !str_contains($t2['text'], 'über  '));
pruefe('ohne Freitext keine Doppelleerzeile', !str_contains($t2['text'], "\n\n\n"));

echo "\n" . ($f===0 ? "ALLE TESTS GRÜN\n" : "$f ROT\n");
exit($f===0?0:1);
