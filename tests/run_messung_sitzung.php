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
// Die Gründe der Sitzung selbst (kein_cookie / kein_token / Netz / Ausnahme)
// prüft seit v0.9.72 tests/run_sitzungszugang.php an wu_sitzung(), dem
// einzigen Sitzungszugang. Hier bleibt die Nachprobe der Messung.
echo "Nachprobe der Messung: Netz weg ist nicht abgelaufen\n";
$tot = ['webuntis' => ['base_url' => 'http://127.0.0.1:9', 'school' => 'x']];
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
        if ($a instanceof Closure) $a = $a($query);
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

echo "Klassenleitung (Zug 3, v0.9.55): Format ohne Wert\n";
pruefe('Format: Zahl', messung_format(7) === 'Zahl');
pruefe('Format: Ziffernfolge als Text', messung_format('123') === 'Ziffernfolge (Text)');
pruefe('Format: Text', messung_format('Ab') === 'Text');
pruefe('Format: Objekt nennt nur Schlüssel', messung_format(['name' => 'Ab', 'id' => 7]) === 'Objekt{id,name}');
pruefe('Format: leer für null, "" und []',
    messung_format(null) === 'leer' && messung_format('') === 'leer' && messung_format([]) === 'leer');
pruefe('Format: Liste mit Länge', messung_format([7, 8]) === 'Liste[2] von Zahl');
$kk = messung_kandidaten(['id' => 5, 'name' => 'Ab']);
pruefe('Kandidaten aus Objekt: Kennung und Text', $kk === ['ids' => [5], 'texte' => ['Ab']]);

echo "Klassenleitung – Eltern-Sicht je Kind\n";
$lehrerBestand = ['webuntis_ids' => [7, 8], 'kuerzel' => ['Ab', 'Cd']];
$pcEltern = [
    ['id' => 90001, 'klasseId' => 3, 'classteacher' => ['id' => 7, 'name' => 'Ab'], 'classteacher2' => null],
    ['id' => 90002],
];
$kl = messung_klassenleitung($pcEltern, $lehrerBestand,
    [['id' => 90001], ['id' => 90002], ['id' => 90003]]);
$k1 = $kl['kinder'][0] ?? [];
pruefe('Kind 1: Klassenleitung gefüllt, Format Objekt{id,name}',
    ($k1['classteacher']['gefuellt'] ?? null) === true
    && ($k1['classteacher']['format'] ?? null) === 'Objekt{id,name}');
pruefe('Kind 1: Kennung passt zu lehrer.webuntis_id, Text zu kuerzel',
    ($k1['classteacher']['passt_webuntis_id'] ?? null) === 1
    && ($k1['classteacher']['passt_kuerzel'] ?? null) === 1);
pruefe('Kind 1: classteacher2 vorhanden, aber leer',
    ($k1['classteacher2']['vorhanden'] ?? null) === true && ($k1['classteacher2']['gefuellt'] ?? null) === false);
pruefe('Kind 2 ohne Klasse: Feld fehlt, nicht gefüllt',
    ($kl['kinder'][1]['hat_klasse'] ?? null) === false
    && ($kl['kinder'][1]['classteacher']['vorhanden'] ?? null) === false
    && ($kl['kinder'][1]['classteacher']['gefuellt'] ?? null) === false);
pruefe('Kind ohne Eintrag in pageconfig wird so benannt',
    ($kl['kinder'][2]['in_pageconfig'] ?? null) === false);
$passtNicht = messung_klassenleitung([['id' => 90001, 'classteacher' => 99]], $lehrerBestand, [['id' => 90001]]);
pruefe('fremde Kennung passt nicht', ($passtNicht['kinder'][0]['classteacher']['passt_webuntis_id'] ?? null) === 0
    && ($passtNicht['kinder'][0]['classteacher']['kennungen'] ?? null) === 1);

echo "Klassenleitung – Lehrkraft-Sicht summiert\n";
$ks = messung_klassenleitung([['id' => 1, 'classteacher' => 7], ['id' => 2, 'classteacher' => '8'],
                              ['id' => 3]], $lehrerBestand, null);
pruefe('gefüllt 2 von 3', ($ks['summe']['classteacher']['gefuellt'] ?? null) === 2);
pruefe('beide passen zu webuntis_id', ($ks['summe']['classteacher']['passt_webuntis_id'] ?? null) === 2);
pruefe('Formate gezählt', ($ks['summe']['classteacher']['formate'] ?? null)
    === ['Zahl' => 1, 'Ziffernfolge (Text)' => 1, 'leer' => 1]);
$kleer = messung_klassenleitung([['id' => 1, 'classteacher' => 7]], [], null);
pruefe('ohne Lehrkräfte im Bestand: KEIN Befund', str_contains((string)($kleer['deutung'] ?? ''), 'KEIN Befund'));

echo "Klassenleitung im Bericht – keine Kennungen, keine Namen\n";
$rest5 = new ErsatzRest([$PC => ['status' => 200, 'json' => ['data' => ['elements' => [
    ['id' => 90001, 'klasseId' => 3, 'classteacher' => ['id' => 7777, 'name' => 'Geheimlehrer']]]]]],
    $TT => ['status' => 200, 'json' => $plan]]);
$bk = messung_sitzung_bericht(['rolle' => 'eltern', 'kinder' => [['id' => 90001, 'name' => 'Erfunden']]],
    $rest5, null, '2026-10-08', null, ['webuntis_ids' => [7777], 'kuerzel' => []]);
pruefe('Bericht enthält die Klassenleitung je Kind',
    ($bk['klassenleitung']['kinder'][0]['classteacher']['passt_webuntis_id'] ?? null) === 1);
$tk = json_encode($bk, JSON_UNESCAPED_UNICODE);
pruefe('weder Kennung noch Name der Klassenleitung in der Antwort',
    !str_contains($tk, '7777') && !str_contains($tk, 'Geheimlehrer') && !str_contains($tk, '90001'));

echo "timetable/filter (v0.9.56): Doppelabgleich je Klassenleitung\n";
$paare = ['paare' => [7 => 'Ab', 8 => 'Cd'], 'webuntis_ids' => [7, 8], 'kuerzel' => ['Ab', 'Cd']];
$kf = ['classes' => [
    ['class' => ['id' => 10], 'classTeacher1' => ['id' => 7, 'shortName' => 'Ab', 'longName' => 'x', 'displayName' => 'x'],
     'classTeacher2' => null],
    ['class' => ['id' => 11], 'classTeacher1' => ['id' => 8, 'shortName' => 'Xx']],
    ['class' => ['id' => 12]],
]];
$au = messung_klassenfilter_auswerten($kf, $paare)['bericht'];
pruefe('drei Klassen gelesen, Pfad classes', ($au['klassen'] ?? null) === 3 && ($au['pfad'] ?? null) === 'classes');
pruefe('classTeacher1: vorhanden 2, gefüllt 2', ($au['classTeacher1']['vorhanden'] ?? null) === 2
    && ($au['classTeacher1']['gefuellt'] ?? null) === 2);
pruefe('classTeacher1: id passt 2, Kürzel passt 1', ($au['classTeacher1']['id_passt'] ?? null) === 2
    && ($au['classTeacher1']['kuerzel_passt'] ?? null) === 1);
pruefe('classTeacher1: beide auf dieselbe Lehrkraft nur 1 (Kennung 8 ist Cd, nicht Xx)',
    ($au['classTeacher1']['beide_dieselbe'] ?? null) === 1);
pruefe('classTeacher2: vorhanden 1, gefüllt 0', ($au['classTeacher2']['vorhanden'] ?? null) === 1
    && ($au['classTeacher2']['gefuellt'] ?? null) === 0);
pruefe('Form data.classes erkannt',
    (messung_klassenfilter_auswerten(['data' => $kf], $paare)['bericht']['pfad'] ?? null) === 'data.classes');
pruefe('ohne classes[]: liste_gefunden false',
    messung_klassenfilter_auswerten(['x' => 1], $paare)['bericht']['liste_gefunden'] === false);
$vg = messung_zeitraum_vergleich([10 => [7, 0], 11 => [8, 0]], [10 => [7, 0], 11 => [9, 0], 13 => [1, 0]]);
pruefe('Zeitraumvergleich: 2 in beiden, 1 gleich, 1 anders, 1 nur Ferien',
    $vg === ['in_beiden' => 2, 'gleiche_leitung' => 1, 'andere_leitung' => 1,
             'nur_schulzeit' => 0, 'nur_ferien' => 1]);

echo "timetable/filter im Bericht – Zeiträume und Kinder\n";
$FI = '/WebUntis/api/rest/view/v1/timetable/filter';
$filterAntwort = function (array $q) {
    $ferien = ($q['start'] ?? '') === '2026-10-19';
    return ['status' => 200, 'json' => ['classes' => [
        ['class' => ['id' => 4242],
         'classTeacher1' => ['id' => 7777, 'shortName' => 'Gh', 'longName' => 'Geheimlehrer'],
         'classTeacher2' => $ferien ? null : ['id' => 8, 'shortName' => 'Cd']],
    ]]];
};
$rest6 = new ErsatzRest([
    $PC => ['status' => 200, 'json' => ['data' => ['elements' => [['id' => 90001, 'klasseId' => 4242], ['id' => 90002]]]]],
    $FI => $filterAntwort, $TT => ['status' => 200, 'json' => $plan]]);
$el2 = ['rolle' => 'eltern', 'kinder' => [['id' => 90001], ['id' => 90002]]];
$lb  = ['paare' => [7777 => 'Gh', 8 => 'Cd'], 'webuntis_ids' => [7777, 8], 'kuerzel' => ['Gh', 'Cd']];
$ohneF = messung_sitzung_bericht($el2, $rest6, null, '2026-10-08', null, $lb, null);
pruefe('ohne Ferienzeitraum: Ferien und Vergleich „nicht gemessen“',
    is_string($ohneF['klassenfilter']['ferien'] ?? null) && str_starts_with($ohneF['klassenfilter']['ferien'], 'nicht gemessen')
    && is_string($ohneF['klassenfilter']['vergleich'] ?? null));
pruefe('ohne Ferienzeitraum: genau ein Abruf',
    count(array_filter($rest6->aufrufe, fn($a) => $a[0] === $FI)) === 1);
$rest6->aufrufe = [];
$mitF = messung_sitzung_bericht($el2, $rest6, null, '2026-10-08', null, $lb,
    ['von' => '2026-10-19', 'bis' => '2026-10-23']);
$starts = array_values(array_map(fn($a) => $a[1]['start'], array_filter($rest6->aufrufe, fn($a) => $a[0] === $FI)));
pruefe('mit Ferienzeitraum: Schulzeit und Ferien abgefragt', $starts === ['2026-09-11', '2026-10-19']);
pruefe('Vergleich: 1 in beiden, Leitung anders (classTeacher2 fehlt in den Ferien)',
    ($mitF['klassenfilter']['vergleich']['in_beiden'] ?? null) === 1
    && ($mitF['klassenfilter']['vergleich']['andere_leitung'] ?? null) === 1);
$kk1 = $mitF['klassenfilter']['kinder'][0] ?? [];
pruefe('Kind 1: Klasse gefunden, classTeacher1 doppelt belegt',
    ($kk1['schulzeit']['klasse_gefunden'] ?? null) === true
    && ($kk1['schulzeit']['classTeacher1']['beide_dieselbe'] ?? null) === true);
pruefe('Kind 1: gleiche Leitung in beiden Zeiträumen = nein', ($kk1['gleiche_leitung_in_beiden'] ?? null) === false);
pruefe('Kind 2 ohne Klasse: hat_klasse nein, nichts gefunden',
    ($mitF['klassenfilter']['kinder'][1]['hat_klasse'] ?? null) === false
    && ($mitF['klassenfilter']['kinder'][1]['schulzeit']['klasse_gefunden'] ?? null) === false);
$tf = json_encode($mitF, JSON_UNESCAPED_UNICODE);
pruefe('keine Kennung (Klasse, Lehrkraft, Kind) und kein Name in der Antwort',
    !str_contains($tf, '4242') && !str_contains($tf, '7777') && !str_contains($tf, '90001')
    && !str_contains($tf, 'Geheimlehrer') && !str_contains($tf, '"Gh"'));

// ------------------------------------------------------------
// v0.9.64: /WebUntis/api/profile/general – Benutzergruppe der angemeldeten
// Person (Frage: volljährige Schüler erkennen). Belegt ist aus einem
// Mitschnitt des Betreibers (Browser, Schülerkonto) nur: data.profile trägt
// userGroup als Text („SuS über 18“) und userRoleId (5). Alle anderen
// Felder hier sind ERFUNDEN – auch die Listenform für mehrere Gruppen, die
// nicht belegt ist; sie prüft, dass die Messung sie erkennen würde.
echo "profile/general – Gruppe der angemeldeten Person (v0.9.64)\n";
$PR = '/WebUntis/api/profile/general';
pruefe('Voraussetzung: messung_profil() vorhanden', function_exists('messung_profil'));
if (!function_exists('messung_profil')) { echo "\n$fehler ROT\n"; exit(1); }
$profil = ['data' => ['profile' => [
    'userGroup' => 'SuS über 18', 'userRoleId' => 5,
    'displayName' => 'Erfunden Person', 'email' => 'erfunden@beispiel.invalid',
    'personId' => 4712, 'id' => 4711,
]]];
$m = messung_profil(['status' => 200, 'json' => $profil, 'fehler' => null]);
pruefe('Profil gelesen: Status 200, data.profile vorhanden, Schlüssel genannt',
    $m['status'] === 200 && $m['profil_vorhanden'] === true
    && in_array('userGroup', $m['schluessel'] ?? [], true) && in_array('userRoleId', $m['schluessel'] ?? [], true));
pruefe('userGroup: vorhanden, gefüllt, Format Text',
    ($m['userGroup']['vorhanden'] ?? null) === true && ($m['userGroup']['gefuellt'] ?? null) === true
    && ($m['userGroup']['format'] ?? null) === 'Text');
$gf = [];
foreach ($m['gruppenfelder'] ?? [] as $f) $gf[$f['pfad']] = $f;
pruefe('Gruppen- und Rollenfelder mit Wert (von der Schule vergeben): userGroup und userRoleId',
    ($gf['profile.userGroup']['wert'] ?? null) === 'SuS über 18' && ($gf['profile.userRoleId']['wert'] ?? null) === 5);
$tp = json_encode($m, JSON_UNESCAPED_UNICODE);
pruefe('keine Personenangaben: weder Name, E-Mail noch Kennungen der Person',
    !str_contains($tp, 'Erfunden Person') && !str_contains($tp, 'erfunden@') && !str_contains($tp, '4711')
    && !str_contains($tp, '4712'));
$mehr = messung_profil(['status' => 200, 'fehler' => null, 'json' => ['data' => ['profile' => [
    'userGroups' => [['id' => 25, 'name' => 'SuS über 18'], ['id' => 26, 'name' => 'SuSüber18 mit Attest',
        'mitglieder' => [['id' => 98765, 'firstName' => 'Erfunden', 'lastName' => 'Geheim']]]]]]]]);
$gm = [];
foreach ($mehr['gruppenfelder'] ?? [] as $f) $gm[$f['pfad']] = $f;
pruefe('mehrere Gruppen (Listenform, erfunden): Format Liste, je Gruppe Kennung und Name',
    ($gm['profile.userGroups']['format'] ?? null) === 'Liste[2] von Objekt{id,name}'
    && ($gm['profile.userGroups.0.id']['wert'] ?? null) === 25
    && ($gm['profile.userGroups.1.name']['wert'] ?? null) === 'SuSüber18 mit Attest');
pruefe('… auch in einer Gruppe keine Personennamen und keine Kennung eines Mitglieds',
    !str_contains(json_encode($mehr, JSON_UNESCAPED_UNICODE), 'Geheim')
    && !str_contains(json_encode($mehr, JSON_UNESCAPED_UNICODE), '98765'));
$leer = messung_profil(['status' => 200, 'fehler' => null, 'json' => ['data' => ['profile' => ['userGroup' => '']]]]);
// Zwei Stufen schützen: Personenfelder werden übersprungen, und Werte gibt es
// nur an der Gruppe selbst. Hier steht die Personenangabe DIREKT an der
// Gruppe – nur die erste Stufe hält sie zurück (die Mitglieder oben prüfen
// die zweite).
$direkt = messung_profil(['status' => 200, 'fehler' => null, 'json' => ['data' => ['profile' => [
    'userGroup' => ['id' => 25, 'name' => 'SuS über 18', 'leitungDisplayName' => 'Erfunden Leitung',
                    'kontaktMail' => 'leitung@beispiel.invalid']]]]]);
$td = json_encode($direkt, JSON_UNESCAPED_UNICODE);
pruefe('Personenangabe direkt an einer Gruppe: übersprungen (Gruppenname bleibt)',
    !str_contains($td, 'Erfunden Leitung') && !str_contains($td, 'leitung@') && str_contains($td, 'SuS über 18'));
pruefe('leere userGroup: vorhanden, aber nicht gefüllt',
    ($leer['userGroup']['vorhanden'] ?? null) === true && ($leer['userGroup']['gefuellt'] ?? null) === false);
$ohne = messung_profil(['status' => 200, 'fehler' => null, 'json' => null]);
$verb = messung_profil(['status' => 403, 'fehler' => null, 'json' => null]);
$ausn = messung_profil(['status' => null, 'fehler' => 'RuntimeException: weg', 'json' => null]);
pruefe('ohne data.profile: KEIN Befund; 403: kein Zugriff; Ausnahme: kein Befund – drei Deutungen',
    $ohne['profil_vorhanden'] === false && str_contains($ohne['deutung'], 'KEIN Befund')
    && str_contains($verb['deutung'], 'kein Zugriff') && str_contains($ausn['deutung'], 'Ausnahme')
    && count(array_unique([$ohne['deutung'], $verb['deutung'], $ausn['deutung']])) === 3);
$restP = new ErsatzRest([$PR => ['status' => 200, 'json' => $profil],
                         $PC => ['status' => 200, 'json' => ['data' => [['id' => 1]]]]]);
$bp = messung_sitzung_bericht(['rolle' => 'lehrkraft'], $restP, null, '2026-10-08');
pruefe('Bericht: Profil für jede Rolle gemessen (hier Lehrkraft, deren Bericht früh endet)',
    ($bp['profil']['userGroup']['gefuellt'] ?? null) === true
    && in_array($PR, array_column($restP->aufrufe, 0), true));

// ------------------------------------------------------------
// v0.9.67: /WebUntis/api/userrole/config – alle Benutzergruppen der Schule
// (Auswahlliste statt Eintippen). Belegt aus dem Browser-Mitschnitt des
// Betreibers (Admin): data.userGroups, je Eintrag id, label, userCount,
// userRole, userCountByUserRole; „SuS über 18“ id 25 mit 185 Schülern,
// „SuS über 18 mit Atte“ id 45, „01_Eltern Attest“ mit 7 Erziehungs-
// berechtigten. NICHT belegt ist die Form von userCountByUserRole (Objekt
// oder Liste) – beide Formen sind ERFUNDEN und werden erkannt. Übrige
// Einträge und Zahlen erfunden.
echo "userrole/config – Benutzergruppen der Schule (v0.9.67)\n";
$UR = '/WebUntis/api/userrole/config';
pruefe('Voraussetzung: messung_benutzergruppen() vorhanden', function_exists('messung_benutzergruppen'));
if (!function_exists('messung_benutzergruppen')) { echo "\n$fehler ROT\n"; exit(1); }
$gruppenObj = ['data' => ['userGroups' => [
    ['id' => 25, 'label' => 'SuS über 18', 'userCount' => 185, 'userRole' => -1,
     'userCountByUserRole' => ['STUDENT' => 185]],
    ['id' => 45, 'label' => 'SuS über 18 mit Atte', 'userCount' => 3, 'userRole' => -1,
     'userCountByUserRole' => ['STUDENT' => 3]],
    ['id' => 61, 'label' => '01_Eltern Attest', 'userCount' => 7, 'userRole' => -1,
     'userCountByUserRole' => ['LEGAL_GUARDIAN' => 7]],
    ['id' => 2, 'label' => 'Lehrkräfte', 'userCount' => 90, 'userRole' => 2,
     'userCountByUserRole' => ['TEACHER' => 90], 'mitglieder' => [['id' => 777001, 'displayName' => 'Erfunden Geheim']]],
]]];
$bg = messung_benutzergruppen(['status' => 200, 'json' => $gruppenObj, 'fehler' => null], 'SuS über 18 mit Atte');
pruefe('Liste gelesen: Status 200, data.userGroups, 4 Einträge, Felder genannt',
    $bg['status'] === 200 && $bg['liste_vorhanden'] === true && $bg['eintraege'] === 4
    && count(array_intersect(['id', 'label', 'userCount', 'userRole', 'userCountByUserRole'], array_keys($bg['felder'] ?? []))) === 5);
$je = [];
foreach ($bg['gruppen'] ?? [] as $g) $je[$g['label']] = $g;
pruefe('je Gruppe Kennung, Name, Rolle, Anzahl und Schüleranzahl (von der Schule vergeben, Zahlen)',
    ($je['SuS über 18']['id'] ?? null) === 25 && ($je['SuS über 18']['schueler'] ?? null) === 185
    && ($je['SuS über 18 mit Atte']['id'] ?? null) === 45 && ($je['01_Eltern Attest']['schueler'] ?? null) === 0
    && ($je['Lehrkräfte']['userRole'] ?? null) === 2 && ($je['Lehrkräfte']['userCount'] ?? null) === 90);
pruefe('Gruppen mit Schülern gezählt (STUDENT > 0): 2 von 4', ($bg['mit_schuelern'] ?? null) === 2);
$liste = messung_benutzergruppen(['status' => 200, 'fehler' => null, 'json' => ['data' => ['userGroups' => [
    ['id' => 25, 'label' => 'SuS über 18', 'userCount' => 185, 'userRole' => -1,
     'userCountByUserRole' => [['userRole' => 'STUDENT', 'count' => 185], ['userRole' => 'TEACHER', 'count' => 0]]],
]]]], null);
pruefe('userCountByUserRole als Liste (erfunden): ebenfalls erkannt, Format genannt',
    ($liste['gruppen'][0]['schueler'] ?? null) === 185 && str_starts_with((string)($liste['felder']['userCountByUserRole'] ?? ''), 'Liste'));
pruefe('Abgleich mit profile.userGroup: zeichengenau gefunden',
    ($bg['eigene_gruppe']['ergebnis'] ?? null) === 'zeichengenau' && ($bg['eigene_gruppe']['id'] ?? null) === 45);
$fast = messung_benutzergruppen(['status' => 200, 'json' => $gruppenObj, 'fehler' => null], 'SuS über 18 mit atte ');
pruefe('… nur nach Angleichen gefunden: so gemeldet, mit erster abweichender Stelle',
    ($fast['eigene_gruppe']['ergebnis'] ?? null) === 'nur_angeglichen'
    && ($fast['eigene_gruppe']['erste_abweichung'] ?? null) === 17);
$nein = messung_benutzergruppen(['status' => 200, 'json' => $gruppenObj, 'fehler' => null], 'Gibt es nicht');
$ohne = messung_benutzergruppen(['status' => 200, 'json' => $gruppenObj, 'fehler' => null], null);
pruefe('… nicht gefunden: „nein“; ohne eigene Gruppe: „nicht messbar“',
    ($nein['eigene_gruppe']['ergebnis'] ?? null) === 'nein' && ($ohne['eigene_gruppe']['ergebnis'] ?? null) === 'nicht_messbar');
pruefe('keine Personenangaben (Mitglieder einer Gruppe erscheinen nicht)',
    !str_contains(json_encode($bg, JSON_UNESCAPED_UNICODE), 'Geheim') && !str_contains(json_encode($bg), '777001'));
$k1 = messung_benutzergruppen(['status' => 200, 'json' => ['data' => []], 'fehler' => null], null);
$k2 = messung_benutzergruppen(['status' => 403, 'json' => null, 'fehler' => null], null);
$k3 = messung_benutzergruppen(['status' => null, 'json' => null, 'fehler' => 'RuntimeException: weg'], null);
pruefe('keine Liste: KEIN Befund; 403: kein Zugriff; Ausnahme: kein Befund – drei Deutungen',
    $k1['liste_vorhanden'] === false && str_contains($k1['deutung'], 'KEIN Befund')
    && str_contains($k2['deutung'], 'kein Zugriff') && str_contains($k3['deutung'], 'Ausnahme')
    && count(array_unique([$k1['deutung'], $k2['deutung'], $k3['deutung']])) === 3);
$restU = new ErsatzRest([$PR => ['status' => 200, 'json' => ['data' => ['profile' => ['userGroup' => 'Lehrkräfte']]]],
                         $UR => ['status' => 200, 'json' => $gruppenObj],
                         $PC => ['status' => 200, 'json' => ['data' => [['id' => 1]]]]]);
$bu = messung_sitzung_bericht(['rolle' => 'lehrkraft'], $restU, null, '2026-10-09');
pruefe('Bericht: für jede Rolle gemessen, Abgleich mit der Gruppe aus profile/general',
    ($bu['benutzergruppen']['eintraege'] ?? null) === 4
    && ($bu['benutzergruppen']['eigene_gruppe']['ergebnis'] ?? null) === 'zeichengenau'
    && in_array($UR, array_column($restU->aufrufe, 0), true));

echo "Aufrufstelle in index.php (ohne Kommentare)\n";
$code = '';
foreach (token_get_all((string)file_get_contents(__DIR__ . '/../backend/api/index.php')) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
    $code .= is_array($t) ? $t[1] : $t;
}
$a = strpos($code, "=== 'messung' && (\$seg[1] ?? '') === 'sitzung'");
// bis zum Ende der json_ok-Anweisung (erstes „]);“ danach)
$route = $a === false ? '' : substr($code, $a,
    (int)strpos($code, ']);', (int)strpos($code, 'json_ok(', $a)) - $a + 3);
pruefe('Route reicht den Grund aus wu_sitzung() durch',
    $route !== '' && preg_match('/\$sitzung = wu_sitzung\(\$cfg\);\s*\$rest = \$sitzung\[\'rest\'\];\s*\$grund = \$sitzung\[\'grund\'\];/', $route) === 1
    && preg_match('/messung_sitzung_bericht\(\$u,\s*\$rest,\s*\$grund,\s*null,\s*\$probe,\s*\$lehrer,\s*\$ferien\)/', $route) === 1);
pruefe('Route fährt die Nachprobe genau bei kein_token',
    $route !== '' && preg_match("/\\\$probe = \\\$grund === 'kein_token'\s*\?\s*messung_token_probe\(/", $route) === 1);
pruefe('Route verlangt eine Anmeldung', $route !== '' && str_contains($route, 'auth_require()'));
pruefe('Route nimmt den Ferienzeitraum nur mit zwei gültigen Daten',
    $route !== '' && preg_match('/\$ferien = preg_match\([^;]*\$fv\)\s*&&\s*preg_match\([^;]*\$fb\)/s', $route) === 1);
pruefe('Route gleicht gegen lehrer.webuntis_id und kuerzel ab',
    $route !== '' && str_contains($route, 'SELECT webuntis_id, kuerzel FROM lehrer'));

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
