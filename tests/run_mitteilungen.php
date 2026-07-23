<?php
// ============================================================
// tests/run_mitteilungen.php – Offline-Tests des Mitteilungsbausteins
// Aufruf: php tests/run_mitteilungen.php
// ============================================================

declare(strict_types=1);

require __DIR__ . '/../backend/api/mitteilungen.php';

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

echo "mit_antwort_bewerten\n";
$ok200 = mit_antwort_bewerten(['status' => 200, 'json' => null]);
pruefe('200 = Erfolg', $ok200['erfolg'] === true && $ok200['endgueltig'] === true);
pruefe('201 = Erfolg', mit_antwort_bewerten(['status' => 201])['erfolg'] === true);
pruefe('204 = Erfolg', mit_antwort_bewerten(['status' => 204])['erfolg'] === true);

$b400 = mit_antwort_bewerten(['status' => 400, 'json' =>
    ['errorMessage' => "parameter 'recipients' is no valid value"]]);
pruefe('400 = kein Erfolg', $b400['erfolg'] === false);
pruefe('400 NICHT endgültig (andere Variante probieren)', $b400['endgueltig'] === false);
pruefe('400 übernimmt Fehlertext', str_contains($b400['grund'], 'recipients'));

$b403 = mit_antwort_bewerten(['status' => 403]);
pruefe('403 ist endgültig (Rechteproblem)', $b403['endgueltig'] === true);
pruefe('403 nennt fehlendes Recht', str_contains($b403['grund'], 'Mitteilungen senden'));
pruefe('401 ist endgültig', mit_antwort_bewerten(['status' => 401])['endgueltig'] === true);

$b404 = mit_antwort_bewerten(['status' => 404]);
pruefe('404 NICHT endgültig', $b404['endgueltig'] === false);
pruefe('405 NICHT endgültig', mit_antwort_bewerten(['status' => 405])['endgueltig'] === false);
pruefe('500 NICHT endgültig', mit_antwort_bewerten(['status' => 500])['endgueltig'] === false);

$b0 = mit_antwort_bewerten(['status' => 0, 'text' => 'cURL: timeout']);
pruefe('Netzfehler ist endgültig', $b0['endgueltig'] === true);
pruefe('Netzfehler meldet Unerreichbarkeit', str_contains($b0['grund'], 'nicht erreichbar'));

$bVal = mit_antwort_bewerten(['status' => 400, 'json' =>
    ['validationErrors' => [['path' => 'content', 'errorMessage' => 'darf nicht leer sein']]]]);
pruefe('validationErrors werden ausgegeben', str_contains($bVal['grund'], 'darf nicht leer'));

echo "mit_varianten\n";
$v = mit_varianten();
pruefe('mindestens 4 Varianten', count($v) >= 4);
$allePfade = true; $alleBodies = true;
foreach ($v as $name => $e) {
    if (!str_starts_with($e['pfad'], '/WebUntis/')) $allePfade = false;
    $body = ($e['body'])(5984, 'Betreff', 'Text');
    if (!is_array($body) || $body === []) $alleBodies = false;
    // Empfänger-ID muss irgendwo im Body auftauchen
    if (!str_contains(json_encode($body), '5984')) $alleBodies = false;
}
pruefe('alle Pfade unter /WebUntis/', $allePfade);
pruefe('alle Bodies enthalten Empfänger-ID', $alleBodies);
$erste = $v[array_key_first($v)];
$body = ($erste['body'])(5984, 'B', 'T');
pruefe('Betreff im Body', ($body['subject'] ?? '') === 'B');
pruefe('Text im Body', ($body['content'] ?? '') === 'T');

echo "mit_datum_deutsch\n";
pruefe('ISO wird umgewandelt', mit_datum_deutsch('2026-11-20') === '20.11.2026');
pruefe('unbekanntes Format bleibt', mit_datum_deutsch('demnächst') === 'demnächst');
pruefe('leer bleibt leer', mit_datum_deutsch('') === '');

echo "mit_text_bestaetigung\n";
$t = mit_text_bestaetigung('Elternsprechtag Herbst', '2026-11-20', [
    ['slot_beginn' => '15:00:00', 'name' => 'Anna Greitemann',
     'kuerzel' => 'Gr', 'raum_kuerzel' => 'A101'],
    ['slot_beginn' => '15:30:00', 'name' => 'Bernd Klein',
     'kuerzel' => 'Kl', 'raum_kuerzel' => ''],
]);
pruefe('Betreff nennt Sprechtag', str_contains($t['betreff'], 'Elternsprechtag Herbst'));
pruefe('Datum deutsch im Text', str_contains($t['text'], '20.11.2026'));
pruefe('erster Termin mit Uhrzeit', str_contains($t['text'], '15:00 Uhr'));
pruefe('Sekunden abgeschnitten', !str_contains($t['text'], '15:00:00'));
pruefe('Lehrkraft genannt', str_contains($t['text'], 'Anna Greitemann'));
pruefe('Raum genannt', str_contains($t['text'], 'Raum A101'));
pruefe('leerer Raum ohne Klammer', !str_contains($t['text'], '(Raum )'));
pruefe('beide Termine enthalten',
    substr_count($t['text'], 'Uhr:') === 2);

echo "mit_text_absage\n";
$a = mit_text_absage('Elternsprechtag Herbst', '2026-11-20', '15:00:00',
    'Anna Greitemann', 'Ich bin leider erkrankt.');
pruefe('Betreff kennzeichnet Absage', str_contains($a['betreff'], 'Terminabsage'));
pruefe('Uhrzeit genannt', str_contains($a['text'], '15:00 Uhr'));
pruefe('Datum deutsch', str_contains($a['text'], '20.11.2026'));
pruefe('Freitext übernommen', str_contains($a['text'], 'Ich bin leider erkrankt.'));
pruefe('Lehrkraft als Absender', str_contains($a['text'], 'Anna Greitemann'));

$a2 = mit_text_absage('Sprechtag', '2026-11-20', '15:00', 'Gr', '');
pruefe('ohne Freitext keine Leerzeile zu viel',
    !str_contains($a2['text'], "\n\n\n"));
pruefe('Hinweis auf Neubuchung', str_contains($a2['text'], 'neuen Termin'));

$a3 = mit_text_absage('Sprechtag', '2026-11-20', '15:00', 'Gr', '   ');
pruefe('nur-Leerzeichen-Freitext ignoriert',
    substr_count($a3['text'], "\n\n") === substr_count($a2['text'], "\n\n"));

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler TEST(S) ROT\n");
exit($fehler === 0 ? 0 : 1);
