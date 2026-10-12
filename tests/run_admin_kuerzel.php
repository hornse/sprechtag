<?php
// ============================================================
// tests/run_admin_kuerzel.php – admin_kuerzel aus der config (v0.9.78, E22)
// Aufruf: php tests/run_admin_kuerzel.php   → Exit-Code 0 = alles grün
//
// Entscheidung des Betreibers: Der Kürzelvergleich ist unempfindlich gegen
// Groß- und Kleinschreibung, in BEIDE Richtungen – ['hq'], ['Hq'] und
// ['HQ'] treffen dasselbe Kürzel. Nicht: auf Kleinschreibung festlegen.
// Zweiter Stolperstein derselben Art: ['Hq, Mq'] ist EIN Eintrag und traf
// still niemanden. Er wird jetzt aufgeteilt.
//
// Bis v0.9.77 verglich wu_login() mit in_array(…, true) – streng, also
// empfindlich –, und personType 16 nahm admin_kuerzel[0], bei einer
// Zeichenkette statt eines Arrays also nur den ersten Buchstaben.
// Die Datenbankvergleiche (lehrer.kuerzel, app_admins.lehrer_kuerzel)
// laufen unter utf8mb4_unicode_ci und sind laut Schema schon unempfindlich
// (sql/02_sprechtag.sql, ohne eigene Kollation je Spalte; nicht am Server
// gemessen). Seit v0.9.98 (E29) wird app_admins in PHP verglichen
// (backend/api/admins.php, ausgeführt in run_admins.php); die Entscheidung
// über den Notzugang und die Wache stehen seitdem dort.
//
// Ausgeführt: wu_kuerzel_gleich, wu_kuerzel_liste, wu_ist_config_admin,
// wu_admin_eigenes_kuerzel. Quelltext: die Aufrufstellen in wu_login (die
// Anmeldung braucht WebUntis) und die Engstelle über alle Backend-Dateien.
// Kürzel ERFUNDEN (seit v0.9.97; bis dahin standen hier echte, darunter das
// des Betreibers).
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
foreach (['hq', 'Hq', 'HQ', 'hQ'] as $a) foreach (['hq', 'Hq', 'HQ'] as $b) $alle = $alle && wu_kuerzel_gleich($a, $b);
pruefe('hq, Hq, HQ, hQ treffen einander je paarweise', $alle);
pruefe('Leerzeichen am Rand zählen nicht', wu_kuerzel_gleich(' Hq ', 'hq'));
pruefe('verschiedene Kürzel bleiben verschieden (Hq/Hx, Hq/Hqe)',
    !wu_kuerzel_gleich('Hq', 'Hx') && !wu_kuerzel_gleich('Hq', 'Hqe') && !wu_kuerzel_gleich('Hqe', 'Hq'));
pruefe('leer trifft nie, auch nicht leer', !wu_kuerzel_gleich('', '') && !wu_kuerzel_gleich(' ', ''));
if (!function_exists('mb_strtolower')) {
    pruefe('Voraussetzung: mbstring für Kürzel mit Umlaut (fehlt hier – Mü/MÜ träfen sich nicht)', false);
} else {
    pruefe('Umlaut: Mü trifft MÜ (mit mbstring)', wu_kuerzel_gleich('Mü', 'MÜ'));
}

// ------------------------------------------------------------
echo "Liste: Array wie dokumentiert, Komma-Einträge aufgeteilt\n";
pruefe('[\'Hq\', \'Mq\', \'Sq\'] bleibt, wie es ist', wu_kuerzel_liste(['Hq', 'Mq', 'Sq']) === ['Hq', 'Mq', 'Sq']);
pruefe('[\'Hq, Mq\'] wird aufgeteilt (traf bisher still niemanden)', wu_kuerzel_liste(['Hq, Mq']) === ['Hq', 'Mq']);
pruefe('gemischt, ohne Leerzeichen, Rand entfernt', wu_kuerzel_liste(['Hq,Mq', ' Sq ']) === ['Hq', 'Mq', 'Sq']);
pruefe('Zeichenkette statt Array: \'Hq\' bleibt \'Hq\', \'Hq, Mq\' wird geteilt',
    wu_kuerzel_liste('Hq') === ['Hq'] && wu_kuerzel_liste('Hq, Mq') === ['Hq', 'Mq']);
pruefe('leere Teile fallen weg; fehlt der Eintrag: leere Liste',
    wu_kuerzel_liste(['Hq,', '', ' ', ',']) === ['Hq'] && wu_kuerzel_liste(null) === [] && wu_kuerzel_liste([]) === []);

// ------------------------------------------------------------
echo "Wer ist Admin aus der config\n";
pruefe('Lehrkraft HQ, config [\'hq\']: Admin (und umgekehrt)',
    wu_ist_config_admin('HQ', ['admin_kuerzel' => ['hq']]) && wu_ist_config_admin('hq', ['admin_kuerzel' => ['HQ']]));
pruefe('config [\'Hq, Mq\']: beide sind Admin', wu_ist_config_admin('Hq', ['admin_kuerzel' => ['Hq, Mq']])
    && wu_ist_config_admin('MQ', ['admin_kuerzel' => ['Hq, Mq']]));
pruefe('anderes Kürzel, leeres Kürzel, kein Eintrag: kein Admin',
    !wu_ist_config_admin('Hx', ['admin_kuerzel' => ['Hq, Mq']]) && !wu_ist_config_admin('', ['admin_kuerzel' => ['Hq']])
    && !wu_ist_config_admin(null, ['admin_kuerzel' => ['Hq']]) && !wu_ist_config_admin('Hq', []));
pruefe('WebUntis-Admin (personType 16): erstes Kürzel der Liste, auch aus [\'Hq, Mq\']',
    wu_admin_eigenes_kuerzel(['admin_kuerzel' => ['Hq, Mq']]) === 'Hq'
    && wu_admin_eigenes_kuerzel(['admin_kuerzel' => ['Hq', 'Mq']]) === 'Hq');
pruefe('… aus der Zeichenkette \'Hq\' das ganze Kürzel, nicht \'H\'; ohne Eintrag null',
    wu_admin_eigenes_kuerzel(['admin_kuerzel' => 'Hq']) === 'Hq' && wu_admin_eigenes_kuerzel([]) === null);

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
$adm = $ohneKommentar($wurzel . '/backend/api/admins.php');
pruefe('wu_login, Lehrkraft: Admin über wu_ist_config_admin (seit v0.9.98 in adm_herkunft, admins.php)',
    str_contains($adm, "if (wu_ist_config_admin(\$kuerzel, \$wcfg)) return 'notzugang';"));

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

pruefe('Selbst-Entfernen-Wache (app_admins) vergleicht unempfindlich (seit v0.9.98 in adm_entfernen, admins.php)',
    str_contains($adm, "wu_kuerzel_gleich(\$ziel, (string)(\$eigenes ?? ''))")
    && !str_contains($adm, "\$ziel === \$eigenes") && !str_contains($adm, "\$ziel === (\$u['kuerzel'] ?? null)"));

// ------------------------------------------------------------
echo "config.example.php erklärt die Schreibweise\n";
$bsp = '';
foreach (token_get_all((string)file_get_contents($wurzel . '/backend/config.example.php')) as $t) {
    if (is_array($t) && $t[0] === T_COMMENT) $bsp .= $t[1] . "\n";
}
pruefe('Kommentar zeigt die Array-Form mit mehreren Kürzeln',
    str_contains($bsp, "'admin_kuerzel' => ['Hq', 'Mq', 'Sq']"));
pruefe('Kommentar nennt den Notzugang und verweist für alles Weitere auf die Seite „Administratoren“ (E29)',
    preg_match('/NOTZUGANG/', $bsp) === 1 && str_contains($bsp, '„Administratoren“')
    && preg_match('/aussperren/', $bsp) === 1);
pruefe('Kommentar warnt vor [\'Hq, Mq\'] und nennt die Groß-/Kleinschreibung',
    str_contains($bsp, "['Hq, Mq']") && preg_match('/Groß- und Kleinschreibung/', $bsp) === 1);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
