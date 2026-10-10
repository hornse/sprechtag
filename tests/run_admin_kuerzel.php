<?php
// ============================================================
// tests/run_admin_kuerzel.php – admin_kuerzel aus der config (v0.9.78, E22)
// Aufruf: php tests/run_admin_kuerzel.php   → Exit-Code 0 = alles grün
//
// Entscheidung des Betreibers: Der Kürzelvergleich ist unempfindlich gegen
// Groß- und Kleinschreibung, in BEIDE Richtungen – ['ho'], ['Ho'] und
// ['HO'] treffen dasselbe Kürzel. Nicht: auf Kleinschreibung festlegen.
// Zweiter Stolperstein derselben Art: ['Ho, Mu'] ist EIN Eintrag und traf
// still niemanden. Er wird jetzt aufgeteilt.
//
// Bis v0.9.77 verglich wu_login() mit in_array(…, true) – streng, also
// empfindlich –, und personType 16 nahm admin_kuerzel[0], bei einer
// Zeichenkette statt eines Arrays also nur den ersten Buchstaben.
// Die Datenbankvergleiche (lehrer.kuerzel, app_admins.lehrer_kuerzel)
// laufen unter utf8mb4_unicode_ci und sind laut Schema schon unempfindlich
// (sql/02_sprechtag.sql, ohne eigene Kollation je Spalte; nicht am Server
// gemessen).
//
// Ausgeführt: wu_kuerzel_gleich, wu_kuerzel_liste, wu_ist_config_admin,
// wu_admin_eigenes_kuerzel. Quelltext: die Aufrufstellen in wu_login (die
// Anmeldung braucht WebUntis) und die Engstelle über alle Backend-Dateien.
// Kürzel ERFUNDEN.
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
require $wurzel . '/backend/helfer.php';
require $wurzel . '/backend/api/auth.php';
require $wurzel . '/backend/api/webuntis_adapter.php';

$fehlt = array_filter(['wu_kuerzel_gleich', 'wu_kuerzel_liste', 'wu_ist_config_admin',
    'wu_admin_eigenes_kuerzel'], fn($f) => !function_exists($f));
pruefe('Voraussetzung: Funktionen vorhanden', $fehlt === []);
if ($fehlt !== []) { echo '    (fehlen: ' . implode(', ', $fehlt) . ")\n\n$fehler ROT\n"; exit(1); }

// ------------------------------------------------------------
echo "Vergleich: unempfindlich, in beide Richtungen\n";
$alle = true;
foreach (['ho', 'Ho', 'HO', 'hO'] as $a) foreach (['ho', 'Ho', 'HO'] as $b) $alle = $alle && wu_kuerzel_gleich($a, $b);
pruefe('ho, Ho, HO, hO treffen einander je paarweise', $alle);
pruefe('Leerzeichen am Rand zählen nicht', wu_kuerzel_gleich(' Ho ', 'ho'));
pruefe('verschiedene Kürzel bleiben verschieden (Ho/Hu, Ho/Hoe)',
    !wu_kuerzel_gleich('Ho', 'Hu') && !wu_kuerzel_gleich('Ho', 'Hoe') && !wu_kuerzel_gleich('Hoe', 'Ho'));
pruefe('leer trifft nie, auch nicht leer', !wu_kuerzel_gleich('', '') && !wu_kuerzel_gleich(' ', ''));
if (!function_exists('mb_strtolower')) {
    pruefe('Voraussetzung: mbstring für Kürzel mit Umlaut (fehlt hier – Mü/MÜ träfen sich nicht)', false);
} else {
    pruefe('Umlaut: Mü trifft MÜ (mit mbstring)', wu_kuerzel_gleich('Mü', 'MÜ'));
}

// ------------------------------------------------------------
echo "Liste: Array wie dokumentiert, Komma-Einträge aufgeteilt\n";
pruefe('[\'Ho\', \'Mu\', \'Sr\'] bleibt, wie es ist', wu_kuerzel_liste(['Ho', 'Mu', 'Sr']) === ['Ho', 'Mu', 'Sr']);
pruefe('[\'Ho, Mu\'] wird aufgeteilt (traf bisher still niemanden)', wu_kuerzel_liste(['Ho, Mu']) === ['Ho', 'Mu']);
pruefe('gemischt, ohne Leerzeichen, Rand entfernt', wu_kuerzel_liste(['Ho,Mu', ' Sr ']) === ['Ho', 'Mu', 'Sr']);
pruefe('Zeichenkette statt Array: \'Ho\' bleibt \'Ho\', \'Ho, Mu\' wird geteilt',
    wu_kuerzel_liste('Ho') === ['Ho'] && wu_kuerzel_liste('Ho, Mu') === ['Ho', 'Mu']);
pruefe('leere Teile fallen weg; fehlt der Eintrag: leere Liste',
    wu_kuerzel_liste(['Ho,', '', ' ', ',']) === ['Ho'] && wu_kuerzel_liste(null) === [] && wu_kuerzel_liste([]) === []);

// ------------------------------------------------------------
echo "Wer ist Admin aus der config\n";
pruefe('Lehrkraft HO, config [\'ho\']: Admin (und umgekehrt)',
    wu_ist_config_admin('HO', ['admin_kuerzel' => ['ho']]) && wu_ist_config_admin('ho', ['admin_kuerzel' => ['HO']]));
pruefe('config [\'Ho, Mu\']: beide sind Admin', wu_ist_config_admin('Ho', ['admin_kuerzel' => ['Ho, Mu']])
    && wu_ist_config_admin('MU', ['admin_kuerzel' => ['Ho, Mu']]));
pruefe('anderes Kürzel, leeres Kürzel, kein Eintrag: kein Admin',
    !wu_ist_config_admin('Hu', ['admin_kuerzel' => ['Ho, Mu']]) && !wu_ist_config_admin('', ['admin_kuerzel' => ['Ho']])
    && !wu_ist_config_admin(null, ['admin_kuerzel' => ['Ho']]) && !wu_ist_config_admin('Ho', []));
pruefe('WebUntis-Admin (personType 16): erstes Kürzel der Liste, auch aus [\'Ho, Mu\']',
    wu_admin_eigenes_kuerzel(['admin_kuerzel' => ['Ho, Mu']]) === 'Ho'
    && wu_admin_eigenes_kuerzel(['admin_kuerzel' => ['Ho', 'Mu']]) === 'Ho');
pruefe('… aus der Zeichenkette \'Ho\' das ganze Kürzel, nicht \'H\'; ohne Eintrag null',
    wu_admin_eigenes_kuerzel(['admin_kuerzel' => 'Ho']) === 'Ho' && wu_admin_eigenes_kuerzel([]) === null);

// ------------------------------------------------------------
echo "Aufrufstellen und Engstelle (Quelltext)\n";
$ohneKommentar = function (string $datei): string {
    $c = '';
    foreach (token_get_all((string)file_get_contents($datei)) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
};
$ad = $ohneKommentar($wurzel . '/backend/api/webuntis_adapter.php');
pruefe('wu_login, personType 16: Kürzel über wu_admin_eigenes_kuerzel',
    str_contains($ad, "\$ergebnis['kuerzel'] = wu_admin_eigenes_kuerzel(\$wcfg);"));
pruefe('wu_login, Lehrkraft: Admin über wu_ist_config_admin',
    str_contains($ad, "\$ausConfig = wu_ist_config_admin(\$ergebnis['kuerzel'], \$wcfg);"));

$dateien = array_merge(glob($wurzel . '/backend/*.php'), glob($wurzel . '/backend/*/*.php'));
$dateien = array_values(array_filter($dateien, fn($f) => !preg_match('/config(\.example)?\.php$/', $f)));
pruefe('Voraussetzung: Backend-Dateien gefunden (mind. 15)', count($dateien) >= 15);
$roh = 0; $gekapselt = 0; $wo = [];
foreach ($dateien as $f) {
    $c = $ohneKommentar($f);
    $n = substr_count($c, "'admin_kuerzel'");
    $g = substr_count($c, "wu_kuerzel_liste(\$wcfg['admin_kuerzel'] ?? [])");
    $roh += $n; $gekapselt += $g;
    if ($n !== $g) $wo[] = basename($f);
}
pruefe('Engstelle: admin_kuerzel wird nur über wu_kuerzel_liste gelesen (mind. ein Vorkommen)',
    $roh >= 1 && $wo === []);
if ($wo !== []) echo '    (unmittelbar gelesen in: ' . implode(', ', $wo) . ")\n";
pruefe('kein strenger in_array-Vergleich auf Kürzel mehr im Adapter (die alte Fassung)',
    preg_match("/in_array\(\\\$ergebnis\['kuerzel'\]/", $ad) === 0);

$ix = $ohneKommentar($wurzel . '/backend/api/index.php');
pruefe('Selbst-Entfernen-Wache (app_admins) vergleicht unempfindlich',
    str_contains($ix, "wu_kuerzel_gleich((string)\$ziel, (string)(\$u['kuerzel'] ?? ''))")
    && !str_contains($ix, "\$ziel === (\$u['kuerzel'] ?? null)"));

// ------------------------------------------------------------
echo "config.example.php erklärt die Schreibweise\n";
$bsp = '';
foreach (token_get_all((string)file_get_contents($wurzel . '/backend/config.example.php')) as $t) {
    if (is_array($t) && $t[0] === T_COMMENT) $bsp .= $t[1] . "\n";
}
pruefe('Kommentar zeigt die Array-Form mit mehreren Kürzeln',
    str_contains($bsp, "'admin_kuerzel' => ['Ho', 'Mu', 'Sr']"));
pruefe('Kommentar warnt vor [\'Ho, Mu\'] und nennt die Groß-/Kleinschreibung',
    str_contains($bsp, "['Ho, Mu']") && preg_match('/Groß- und Kleinschreibung/', $bsp) === 1);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
