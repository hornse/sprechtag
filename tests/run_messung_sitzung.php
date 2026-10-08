<?php
// ============================================================
// tests/run_messung_sitzung.php – die Messung zu Frage 2 (v0.9.54)
// Aufruf: php tests/run_messung_sitzung.php   → Exit-Code 0 = alles grün
//
// Prüft, dass die Messung aussagekräftig ist, nicht WebUntis:
//   * „abgelaufen“, „kein Cookie“ und „Ausnahme“ melden sich verschieden –
//     auch an der echten mit_rest_aus_sitzung(), nicht nur am Bericht;
//   * null Einträge und eine fehlende Liste gelten nicht als Befund;
//   * die Antwort enthält keine Namen und keine Kennungen.
//
// Testdaten erfunden. Die Form der Stundenplan-Einträge folgt
// tests/run_slots.php (rest_lehrkraefte_aus_entries); die
// pageconfig-Form den beiden Pfaden, die sondierung.php liest.
// ============================================================

declare(strict_types=1);

require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/auth/WebUntisRest.php';
require __DIR__ . '/../backend/auth/extractors.php';
require __DIR__ . '/../backend/api/auth.php';
require __DIR__ . '/../backend/api/mitteilungen.php';
require __DIR__ . '/../backend/api/messung_sitzung.php';

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

// ------------------------------------------------------------
echo "mit_rest_aus_sitzung() nennt den Grund\n";
$_SESSION = [];
$g = 'vorbelegt';
$r = mit_rest_aus_sitzung(['webuntis' => ['base_url' => 'http://127.0.0.1:9', 'school' => 'x']], $g);
pruefe('ohne Cookie: null und Grund kein_cookie', $r === null && $g === 'kein_cookie');
$_SESSION = ['wu_cookie' => 'JSESSIONID=erfunden'];
$g = null;
// base_url null: Der Konstruktor wirft einen TypeError – ein Programmierfehler,
// der bisher genau wie eine abgelaufene Sitzung aussah (E9).
$r = mit_rest_aus_sitzung(['webuntis' => ['base_url' => null, 'school' => null]], $g);
pruefe('Ausnahme: null, Grund nennt Klasse und Meldung',
    $r === null && is_string($g) && str_starts_with($g, 'fehler: TypeError: '));
pruefe('bisherige Aufrufer ohne Grund-Parameter: unverändert null',
    mit_rest_aus_sitzung(['webuntis' => ['base_url' => null, 'school' => null]]) === null);

echo "Netz weg sieht aus wie abgelaufen – die Nachprobe trennt es\n";
// Port 9 auf 127.0.0.1 ist geschlossen: cURL scheitert sofort, Status 0.
$tot = ['webuntis' => ['base_url' => 'http://127.0.0.1:9', 'school' => 'x']];
$g = null;
$r = mit_rest_aus_sitzung($tot, $g);
pruefe('mit_rest_aus_sitzung(): unerreichbar ergibt ebenfalls kein_token',
    $r === null && $g === 'kein_token');
$p = messung_token_probe($tot, 'JSESSIONID=erfunden');
pruefe('Nachprobe: unerreichbar ist art netz, Status 0',
    ($p['art'] ?? null) === 'netz' && ($p['status'] ?? null) === 0);
$pf = messung_token_probe(['webuntis' => ['base_url' => null, 'school' => null]], 'x');
pruefe('Nachprobe wirft nie: Ausnahme als art fehler',
    ($pf['art'] ?? null) === 'fehler' && str_starts_with((string)($pf['fehler'] ?? ''), 'TypeError: '));
$_SESSION = [];

// ------------------------------------------------------------
echo "Bericht ohne nutzbare Sitzung – drei Gründe, drei Deutungen\n";
$eltern = ['rolle' => 'eltern', 'kinder' => [['id' => 90001, 'name' => 'Erfunden Eins']]];
$b1 = messung_sitzung_bericht($eltern, null, 'kein_cookie');
$b2 = messung_sitzung_bericht($eltern, null, 'kein_token');
$b3 = messung_sitzung_bericht($eltern, null, 'fehler: TypeError: x');
pruefe('nicht nutzbar wird gemeldet', $b1['sitzung']['nutzbar'] === false
    && $b2['sitzung']['nutzbar'] === false && $b3['sitzung']['nutzbar'] === false);
pruefe('Grund kommt in der Antwort an', $b3['sitzung']['grund'] === 'fehler: TypeError: x');
pruefe('drei verschiedene Deutungen',
    count(array_unique([$b1['sitzung']['deutung'], $b2['sitzung']['deutung'],
                        $b3['sitzung']['deutung']])) === 3);
pruefe('Ausnahme wird als Fehler gedeutet, nicht als Ablauf',
    str_contains($b3['sitzung']['deutung'], 'NICHT der Ablauf'));
pruefe('ohne Sitzung wird nichts als gemessen ausgegeben',
    is_string($b2['pageconfig']) && is_string($b2['stundenplan']));

// ------------------------------------------------------------
echo "kein_token – Deutung je Nachprobe\n";
$dA = messung_sitzung_bericht($eltern, null, 'kein_token', null, ['status' => 200, 'art' => 'anmeldeseite']);
$dN = messung_sitzung_bericht($eltern, null, 'kein_token', null, ['status' => 0, 'art' => 'netz']);
$dO = messung_sitzung_bericht($eltern, null, 'kein_token', null, null);
pruefe('Anmeldeseite: als abgelaufen gedeutet', str_contains($dA['sitzung']['deutung'], 'abgelaufen'));
pruefe('Status 0: ausdrücklich kein Ablauf', str_contains($dN['sitzung']['deutung'], 'kein Ablauf'));
pruefe('ohne Nachprobe: KEIN Befund', str_contains($dO['sitzung']['deutung'], 'KEIN Befund'));
pruefe('Nachprobe steht im Bericht', ($dN['sitzung']['token_probe']['art'] ?? null) === 'netz');

echo "messung_pageconfig_zaehlen\n";
$liste = [['id' => 1, 'klasseId' => 7], ['id' => 2, 'klasseId' => 7], ['id' => 3]];
$a = messung_pageconfig_zaehlen(['data' => ['elements' => $liste]]);
pruefe('Form data.elements: 3 Einträge, 2 mit Klasse',
    $a['eintraege'] === 3 && $a['mit_klasse'] === 2 && $a['liste_gefunden']);
$c = messung_pageconfig_zaehlen(['data' => $liste]);
pruefe('Form data: 3 Einträge, 2 mit Klasse', $c['eintraege'] === 3 && $c['mit_klasse'] === 2);
pruefe('klasseId 0 zählt nicht als Klasse',
    messung_pageconfig_zaehlen(['data' => [['id' => 1, 'klasseId' => 0]]])['mit_klasse'] === 0);
pruefe('keine Liste (z. B. Anmeldeseite): liste_gefunden false',
    messung_pageconfig_zaehlen(null)['liste_gefunden'] === false);

// ------------------------------------------------------------
// Ersatz-Client: liefert je Pfad eine feste Antwort, wirft auf Wunsch.
final class ErsatzRest
{
    public array $aufrufe = [];
    public function __construct(private array $antworten) {}
    public function get(string $pfad, array $query = []): array
    {
        $this->aufrufe[] = [$pfad, $query];
        $a = $this->antworten[$pfad] ?? ['status' => 404, 'json' => null];
        if ($a instanceof Throwable) throw $a;
        return $a;
    }
}
$eintrag = fn(string $k, string $fach) => ['type' => 'NORMAL_TEACHING_PERIOD', 'status' => 'REGULAR',
    'position1' => [['current' => ['type' => 'SUBJECT', 'shortName' => $fach]]],
    'position2' => [['current' => ['type' => 'TEACHER', 'shortName' => $k,
                                   'longName' => 'Erfunden ' . $k, 'status' => 'REGULAR']]]];
$plan = ['days' => [['gridEntries' => [$eintrag('Aa', 'M'), $eintrag('Bb', 'D'), $eintrag('Aa', 'M')]]]];
$PC = '/WebUntis/api/public/timetable/weekly/pageconfig';
$TT = '/WebUntis/api/rest/view/v1/timetable/entries';

echo "Bericht mit Sitzung – Eltern\n";
$rest = new ErsatzRest([
    $PC => ['status' => 200, 'json' => ['data' => ['elements' =>
        [['id' => 11, 'name' => 'Geheim', 'forename' => 'Name', 'klasseId' => 7], ['id' => 12]]]]],
    $TT => ['status' => 200, 'json' => $plan],
]);
$eltern2 = ['rolle' => 'eltern', 'kinder' => [['id' => 90001, 'name' => 'Erfunden Eins'],
                                              ['id' => 90002, 'name' => 'Erfunden Zwei']]];
$b = messung_sitzung_bericht($eltern2, $rest, null, '2026-10-08');
pruefe('Sitzung nutzbar', $b['sitzung']['nutzbar'] === true);
pruefe('pageconfig: Status 200, 2 Einträge, 1 mit Klasse',
    $b['pageconfig']['status'] === 200 && $b['pageconfig']['eintraege'] === 2
    && $b['pageconfig']['mit_klasse'] === 1);
pruefe('Stundenplan: je Kind ein Eintrag', count($b['stundenplan']['kinder']) === 2);
pruefe('Stundenplan: 2 Lehrkraft-Kürzel (Aa doppelt zählt einmal)',
    $b['stundenplan']['kinder'][0]['lehrkraefte'] === 2);
$kinderAbgefragt = array_values(array_map(fn($a) => $a[1]['resources'] ?? null,
    array_filter($rest->aufrufe, fn($a) => $a[0] === $TT)));
pruefe('abgefragt werden genau die Kinder der Sitzung', $kinderAbgefragt === [90001, 90002]);
pruefe('Zeitraum: vier Wochen bis heute',
    $b['stundenplan']['zeitraum'] === ['von' => '2026-09-11', 'bis' => '2026-10-08']);
$text = json_encode($b, JSON_UNESCAPED_UNICODE);
pruefe('Antwort ohne Kennungen der Kinder',
    !str_contains($text, '90001') && !str_contains($text, '90002'));
pruefe('Antwort ohne Namen (Kinder, pageconfig, Lehrkräfte)',
    !str_contains($text, 'Erfunden') && !str_contains($text, 'Geheim') && !str_contains($text, 'Aa'));

echo "Bericht mit Sitzung – Lehrkraft\n";
$rest2 = new ErsatzRest([$PC => ['status' => 200, 'json' => ['data' => [['id' => 1, 'klasseId' => 3]]]]]);
$bl = messung_sitzung_bericht(['rolle' => 'lehrkraft', 'kinder' => []], $rest2, null);
pruefe('Lehrkraft: pageconfig gemessen', $bl['pageconfig']['eintraege'] === 1);
pruefe('Lehrkraft: Stundenplan entfällt, kein Abruf',
    is_string($bl['stundenplan']) && str_starts_with($bl['stundenplan'], 'entfällt')
    && count(array_filter($rest2->aufrufe, fn($a) => $a[0] === $TT)) === 0);

echo "Was kein Befund ist, sagt es\n";
$rest3 = new ErsatzRest([$PC => ['status' => 200, 'json' => null],
                         $TT => new RuntimeException('Verbindung abgebrochen')]);
$bx = messung_sitzung_bericht($eltern, $rest3, null, '2026-10-08');
pruefe('Status 200 ohne Liste: „KEIN Befund“',
    str_contains($bx['pageconfig']['deutung'], 'KEIN Befund'));
pruefe('Ausnahme beim Abruf: Klasse und Meldung, Status null',
    $bx['stundenplan']['kinder'][0]['status'] === null
    && $bx['stundenplan']['kinder'][0]['fehler'] === 'RuntimeException: Verbindung abgebrochen');
$rest4 = new ErsatzRest([$PC => ['status' => 403, 'json' => null]]);
$by = messung_sitzung_bericht(['rolle' => 'lehrkraft'], $rest4, null);
pruefe('Status 403: als kein Zugriff gedeutet',
    $by['pageconfig']['status'] === 403 && str_contains($by['pageconfig']['deutung'], 'kein Zugriff'));

echo "Aufrufstelle in index.php (ohne Kommentare)\n";
$code = '';
foreach (token_get_all((string)file_get_contents(__DIR__ . '/../backend/api/index.php')) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
    $code .= is_array($t) ? $t[1] : $t;
}
$a = strpos($code, "=== 'messung' && (\$seg[1] ?? '') === 'sitzung'");
$route = $a === false ? '' : substr($code, $a, (int)strpos($code, 'json_ok(', $a) - $a + 80);
pruefe('Route reicht den Grund aus mit_rest_aus_sitzung() durch',
    $route !== '' && preg_match('/mit_rest_aus_sitzung\(\$cfg,\s*\$grund\)/', $route) === 1
    && preg_match('/messung_sitzung_bericht\(\$u,\s*\$rest,\s*\$grund,\s*null,\s*\$probe\)/', $route) === 1);
pruefe('Route fährt die Nachprobe genau bei kein_token',
    $route !== '' && preg_match("/\\\$probe = \\\$grund === 'kein_token'\s*\?\s*messung_token_probe\(/", $route) === 1);
pruefe('Route verlangt eine Anmeldung', $route !== '' && str_contains($route, 'auth_require()'));

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
