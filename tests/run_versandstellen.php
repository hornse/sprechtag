<?php
// ============================================================
// tests/run_versandstellen.php – wer sendet wo (v0.9.79, E17-Nachtrag)
// Aufruf: php tests/run_versandstellen.php   → Exit-Code 0 = alles grün
//
// Gemessen (Betreiber, 10.10.2026, Betrieb): Die Bestätigung nach einer
// Elternbuchung blieb offen mit „Keine Berechtigung zum Versenden von
// Mitteilungen (HTTP 403)“, Empfängerart „konto“. Elternkonten dürfen in
// WebUntis nicht senden. Damit ist Stelle 1 der Bestandsaufnahme
// (docs/BESTAND-DIENSTKONTO-2026-10-09.md) gemessen.
// Entscheidung (Betreiber): Die Bestätigung nach einer Elternbuchung
// entfällt. Die stellvertretende Buchung behält ihre.
//
// Geprüft (Quelltext – die Routen enden mit json_ok und sind nicht
// ausführbar):
//   * die Elternbuchung reiht nichts ein und sendet nichts;
//   * die stellvertretende Buchung behält die Bestätigung;
//   * REGISTER: jede Stelle, die eine Mitteilung einreiht oder sendet, ist
//     eingeordnet – mit der Rolle, deren Sitzung dort sendet. Eine neue
//     Stelle macht die Suite rot, bis jemand eingeordnet hat, wer dort
//     angemeldet ist. Das Register prüft KEINE Rechte; es zwingt zur Frage.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

$wurzel = __DIR__ . '/..';
$code = function (string $datei): string {
    $c = '';
    foreach (token_get_all((string)file_get_contents($datei)) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
};
$ausschnitt = function (string $quelle, string $von, string $bis): string {
    $a = strpos($quelle, $von);
    if ($a === false) return '';
    $b = strpos($quelle, $bis, $a + strlen($von));
    return $b === false ? '' : substr($quelle, $a, $b - $a);
};

// Ein- und Versandfunktionen der Anwendung (Definitionen in
// mitteilungen.php und erinnerungen.php – dort ist es die Maschinerie,
// nicht eine Anwendungsstelle).
const VERSAND = ['mit_einreihen_und_senden', 'mit_einreihen', 'mit_senden_oder_vormerken',
    'mit_versand_ausfuehren', 'erinnerung_versenden', 'mit_senden', 'mit_senden_eltern'];
const MASCHINERIE = ['mitteilungen.php', 'erinnerungen.php'];

// Datei => [Anzahl Aufrufe, wer dort angemeldet ist]
const REGISTER = [
    'buchungen.php' => [3, 'Lehrkraft/Verwaltung: stellvertretende Buchung (Bestätigung), '
        . 'Absage durch die Lehrkraft, Einladung – nie Eltern'],
    'index.php'     => [6, 'Verwaltung: Erinnerungen, Ausfall (einreihen + senden); '
        . 'Lehrkraft/Verwaltung: Mitteilungen senden (zwei Wege) und einzeln anlegen'],
];

// ------------------------------------------------------------
echo "Elternbuchung: keine Bestätigung mehr\n";
$bu = $code($wurzel . '/backend/api/buchungen.php');
$eb = $ausschnitt($bu, "if (\$methode === 'POST' && !isset(\$seg[1]))", "if (\$methode === 'DELETE'");
$sv = $ausschnitt($bu, "=== 'stellvertretend')", "if (\$methode === 'POST' && !isset(\$seg[1]))");
pruefe('Voraussetzung: Elternbuchung und stellvertretende Buchung gefunden', $eb !== '' && $sv !== '');
$gerufen = array_values(array_filter(VERSAND, fn($f) => preg_match('/\b' . $f . '\(/', $eb) === 1));
pruefe('Elternbuchung reiht nichts ein und sendet nichts', $eb !== '' && $gerufen === []);
if ($gerufen !== []) echo '    (gerufen: ' . implode(', ', $gerufen) . ")\n";
pruefe('Elternbuchung: kein Bestätigungstext, kein Aufräumen offener Bestätigungen, kein WebUntis-Versandweg (die alte Fassung)',
    $eb !== '' && !str_contains($eb, 'mit_text_bestaetigung(') && !str_contains($eb, 'DELETE FROM mitteilungen')
    && !str_contains($eb, 'wu_sitzung($cfg)'));
pruefe('stellvertretende Buchung behält ihre Bestätigung',
    str_contains($sv, "mit_einreihen_und_senden(\$pdo, \$sid, 0, 'bestaetigung',"));

// ------------------------------------------------------------
echo "Register der Versandstellen\n";
$dateien = glob($wurzel . '/backend/api/*.php');
pruefe('Voraussetzung: Anwendungsdateien gefunden (mind. 10)', count($dateien) >= 10);
$ist = [];
foreach ($dateien as $f) {
    $name = basename($f);
    if (in_array($name, MASCHINERIE, true)) continue;
    $c = $code($f);
    $n = 0;
    foreach (VERSAND as $fn) {
        $n += preg_match_all('/(?<!function )(?<![\w>:$])' . $fn . '\(/', $c);
    }
    if ($n > 0) $ist[$name] = $n;
}
ksort($ist);
$soll = array_map(fn($e) => $e[0], REGISTER);
ksort($soll);
pruefe('jede Versandstelle ist eingeordnet, keine fehlt, keine ist neu', $ist === $soll);
if ($ist !== $soll) echo '    (ist: ' . json_encode($ist) . ', Register: ' . json_encode($soll) . ")\n";
pruefe('Gegenprobe der Zählung: die Maschinerie selbst ruft Versandfunktionen (mitteilungen.php)',
    preg_match('/(?<!function )mit_versand_ausfuehren\(/', $code($wurzel . '/backend/api/mitteilungen.php')) === 1);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
