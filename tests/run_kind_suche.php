<?php
// ============================================================
// tests/run_kind_suche.php – Zug 4, Schritt 3 (v0.9.81, E20 A/D/E):
// Einladungsauswahl und Kind-Suche lesen pageconfig über die Sitzung
// Aufruf: php tests/run_kind_suche.php   → Exit-Code 0 = alles grün
//
// Entschieden (Betreiber, 10.10.2026, Richtungsfragen R1–R4):
//   R1 ohne Suchbegriff wird nichts geladen (kein WebUntis-Abruf, keine
//      Namen an den Browser);
//   R2 Teilstring ohne Groß/Klein in Nachname, Vorname, Klasse; Grenze 60;
//   R3 (Oberfläche: Knopf und Eingabetaste – frontend_kind_suche_test.js);
//   R4 der Server lehnt Kinder ohne Klasse auch beim stellvertretenden
//      Buchen ab, mit demselben Satz wie beim Einladen.
//
// Geprüft wird:
//   * kd_suchen – reine Funktion, ausgeführt;
//   * kd_suche – Abrufe über die Sitzung, ausgeführt mit Ersatz-REST;
//   * kd_vorgang_pruefen – die eine Regel für Einladen und stellvertretend,
//     ausgeführt; dazu die Aufrufstellen (Quelltext, die Routen enden mit
//     json_ok und sind nicht ausführbar);
//   * mit_eltern_ids_ermitteln reicht das Ergebnis von kd_ermitteln weiter.
//
// Werte ERFUNDEN, Form wie belegt (Befund pageconfig-Schülerliste,
// Abschnitte 1, 11 und 20). Klassennamen erfunden – die echten sind
// bewusst nicht ausgegeben.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/api/auth.php';
require __DIR__ . '/../backend/api/webuntis_adapter.php';
require __DIR__ . '/../backend/api/mitteilungen.php';
require_once __DIR__ . '/../backend/api/klassenleitung.php';

$fehlt = array_filter(['kd_suchen', 'kd_suche', 'kd_hat_klasse', 'kd_vorgang_pruefen'],
    fn($f) => !function_exists($f));
pruefe('Voraussetzung: Funktionen vorhanden', $fehlt === []);
if ($fehlt !== []) { echo '    (fehlen: ' . implode(', ', $fehlt) . ")\n\n$fehler ROT\n"; exit(1); }

$PC = '/WebUntis/api/public/timetable/weekly/pageconfig';
$TF = '/WebUntis/api/rest/view/v1/timetable/filter';
$pageconfig = ['data' => ['elements' => [
    ['id' => 701, 'name' => 'k701x', 'forename' => 'Ute', 'longName' => 'Erfundena', 'externKey' => '9100701', 'klasseId' => 7],
    ['id' => 702, 'name' => 'k702x', 'forename' => 'Ole', 'longName' => 'Ärmelkanal', 'externKey' => '9100702', 'klasseId' => 7],
    ['id' => 703, 'name' => 'k703x', 'forename' => 'Ida', 'longName' => 'Erfundenb', 'externKey' => '9100703', 'klasseId' => 12],
    ['id' => 704, 'name' => 'k704x', 'forename' => 'Max', 'longName' => 'Erfundenc', 'externKey' => '9100704', 'klasseId' => 0],
    ['id' => 705, 'name' => 'k705x', 'forename' => 'Eva', 'longName' => 'Erfundend', 'externKey' => '9100705', 'klasseId' => 9],
]]];
$filter = ['classes' => [
    ['class' => ['id' => 7,  'shortName' => '5a',  'longName' => '5a Langform', 'displayName' => '5a']],
    ['class' => ['id' => 12, 'shortName' => '10b', 'longName' => 'Zehn B', 'displayName' => '10b']],
]];
$ids = fn(array $e) => array_column($e['kinder'], 'id');

// ------------------------------------------------------------
echo "kd_suchen: Teilstring ohne Groß/Klein, nur Kinder mit Klasse (R2, E20 E)\n";
$e = kd_suchen($pageconfig, $filter, 'erfunden');
pruefe('Name: Teilstring im Nachnamen, ohne Kind ohne Klasse (704)',
    $ids($e) === [705, 701, 703] && $e['anzahl'] === 3);
pruefe('Reihenfolge: Klasse natürlich („5a“ vor „10b“), fehlender Klassenname zuerst',
    array_column($e['kinder'], 'klasse') === ['', '5a', '10b']);
pruefe('Kind mit klasse_id, aber Klasse nicht im Filter (705): erscheint, Klasse leer – dieselbe Regel wie beim Einladen (klasse_id > 0)',
    in_array(705, $ids($e), true));
pruefe('Kind ohne Klasse erscheint auch bei genauem Namen nicht',
    $ids(kd_suchen($pageconfig, $filter, 'Erfundenc')) === []);
pruefe('Vorname trifft', $ids(kd_suchen($pageconfig, $filter, 'Ida')) === [703]);
pruefe('Name trifft ohne Groß/Klein („ida“ und „IDA“ treffen „Ida“)',
    $ids(kd_suchen($pageconfig, $filter, 'ida')) === [703] && $ids(kd_suchen($pageconfig, $filter, 'IDA')) === [703]);
pruefe('Klasse trifft, ohne Groß/Klein („5A“ liefert die ganze Klasse); Ä sortiert wie A, vor E',
    $ids(kd_suchen($pageconfig, $filter, '5A')) === [702, 701]);
pruefe('Teilstring in der Klasse: „b“ trifft 10b (und Namen mit b)',
    in_array(703, $ids(kd_suchen($pageconfig, $filter, '10')), true));
pruefe('Umlaut ohne Groß/Klein: „ärm“ trifft „Ärmelkanal“',
    $ids(kd_suchen($pageconfig, $filter, 'ärm')) === [702]);
pruefe('Suchbegriff wird getrimmt', $ids(kd_suchen($pageconfig, $filter, '  ida ')) === [703]);
pruefe('leerer Suchbegriff: keine Treffer (R1)',
    kd_suchen($pageconfig, $filter, '   ')['kinder'] === [] && kd_suchen($pageconfig, $filter, '')['anzahl'] === 0);
$k = kd_suchen($pageconfig, $filter, 'ida')['kinder'][0] ?? [];
pruefe('Treffer tragen nur Kennung, Name „Nachname, Vorname“ und Klasse',
    $k === ['id' => 703, 'name' => 'Erfundenb, Ida', 'klasse' => '10b']);
pruefe('pageconfig.name und externKey gehen nicht hinaus',
    !str_contains((string)json_encode(kd_suchen($pageconfig, $filter, 'erfunden')), 'k70')
    && !str_contains((string)json_encode(kd_suchen($pageconfig, $filter, 'erfunden')), '91007'));
pruefe('Klasse aus displayName, nicht aus longName',
    !str_contains((string)json_encode(kd_suchen($pageconfig, $filter, '5a')), 'Langform'));

echo "kd_suchen: Grenze 60 (R2) – eine ganze Klasse muss darunter passen\n";
pruefe('Grenze ist 60', KD_SUCHE_GRENZE === 60 && kd_suchen($pageconfig, $filter, 'x')['grenze'] === 60);
$gross = function (int $n): array {
    $el = [];
    for ($i = 1; $i <= $n; $i++) {
        $el[] = ['id' => 800 + $i, 'forename' => 'V' . $i, 'longName' => sprintf('Gross%03d', $i), 'klasseId' => 20];
    }
    return ['data' => ['elements' => $el]];
};
$f20 = ['classes' => [['class' => ['id' => 20, 'displayName' => '7c']]]];
$g60 = kd_suchen($gross(60), $f20, '7c');
pruefe('genau auf der Grenze (60): alle 60, anzahl 60', count($g60['kinder']) === 60 && $g60['anzahl'] === 60);
$g61 = kd_suchen($gross(61), $f20, '7c');
pruefe('eins darüber (61): 60 geliefert, anzahl nennt alle 61', count($g61['kinder']) === 60 && $g61['anzahl'] === 61);
pruefe('gekürzt wird hinten, nach der Sortierung',
    ($g61['kinder'][59]['name'] ?? '') === 'Gross060, V60');

// ------------------------------------------------------------
echo "kd_suche: zwei Abrufe über die Sitzung, Gründe statt stiller Leere\n";
class SuRest
{
    public array $aufrufe = [];
    public function __construct(private array $antworten) {}
    public function get(string $pfad, array $q = []): array
    {
        $this->aufrufe[] = $pfad;
        $a = $this->antworten[$pfad] ?? ['status' => 404, 'json' => null];
        if ($a instanceof Throwable) throw $a;
        return $a;
    }
}
$gefragt = 0;
$sitzung = function (?object $rest, string $art = 'abgelaufen') use (&$gefragt) {
    return function () use ($rest, $art, &$gefragt): array {
        $gefragt++;
        return $rest === null ? ['rest' => null, 'art' => $art, 'grund' => 'x'] : ['rest' => $rest, 'art' => null, 'grund' => null];
    };
};
$okRest = fn() => new SuRest([$PC => ['status' => 200, 'json' => $pageconfig], $TF => ['status' => 200, 'json' => $filter]]);

$gefragt = 0;
$e = kd_suche('   ', $sitzung($okRest()));
pruefe('ohne Suchbegriff: Sitzung nicht einmal gefragt, keine Treffer (R1)',
    $gefragt === 0 && ($e['kinder'] ?? null) === [] && ($e['anzahl'] ?? null) === 0);
$r = $okRest();
$e = kd_suche('5a', $sitzung($r), '2026-10-10');
pruefe('Erfolg: Treffer der Klasse', array_column($e['kinder'] ?? [], 'id') === [702, 701] && !isset($e['grund']));
pruefe('genau zwei Abrufe: pageconfig, dann timetable/filter', $r->aufrufe === [$PC, $TF]);
$e = kd_suche('5a', $sitzung(null, 'abgelaufen'));
pruefe('Sitzung abgelaufen: sitzung mit art, keine Treffer', ($e['sitzung']['art'] ?? null) === 'abgelaufen' && !isset($e['kinder']));
$e = kd_suche('5a', $sitzung(new SuRest([$PC => ['status' => 403, 'json' => null]])));
pruefe('pageconfig 403: Grund nennt Abruf und Status, keine Treffer',
    ($e['grund'] ?? null) === 'pageconfig: Status 403' && !isset($e['kinder']));
$e = kd_suche('5a', $sitzung(new SuRest([$PC => ['status' => 200, 'json' => ['data' => 'kaputt']]])));
pruefe('pageconfig ohne Liste: Grund, nicht still leer', ($e['grund'] ?? null) === 'pageconfig: keine Liste in der Antwort');
$e = kd_suche('5a', $sitzung(new SuRest([$PC => ['status' => 200, 'json' => $pageconfig], $TF => ['status' => 500, 'json' => null]])));
pruefe('timetable/filter 500: Grund nennt den Filter', ($e['grund'] ?? null) === 'timetable/filter: Status 500');
$e = kd_suche('5a', $sitzung(new SuRest([$PC => ['status' => 200, 'json' => $pageconfig], $TF => ['status' => 200, 'json' => ['x' => 1]]])));
pruefe('timetable/filter ohne classes[]: Grund', ($e['grund'] ?? null) === 'timetable/filter: kein classes[] in der Antwort');
$e = kd_suche('5a', $sitzung(new SuRest([$PC => new RuntimeException('Netz')])));
pruefe('Betriebsfehler (Exception): als Grund', ($e['grund'] ?? null) === 'Ausnahme RuntimeException');
$geworfen = false;
try { kd_suche('5a', $sitzung(new SuRest([$PC => new TypeError('kaputt')]))); } catch (TypeError $t) { $geworfen = true; }
pruefe('Programmfehler (Error) geht weiter (FALLSTRICKE 3)', $geworfen);
$e = kd_suche(str_repeat('a', 300), $sitzung($okRest()));
pruefe('überlanger Suchbegriff wird gekürzt, keine Ausnahme', isset($e['kinder']));

// ------------------------------------------------------------
echo "kd_vorgang_pruefen: eine Regel für Einladen und stellvertretend (R4, E20 E)\n";
$mit   = ['grund' => null, 'kinder' => [701 => kd_aus_listen($pageconfig, $filter, 701)]];
$ohne  = ['grund' => null, 'kinder' => [704 => kd_aus_listen($pageconfig, $filter, 704)]];
$nicht = ['grund' => null, 'kinder' => [999 => null]];
$kaputt = ['grund' => 'pageconfig: Status 403', 'kinder' => []];
pruefe('Kind mit Klasse: kein Einwand', kd_vorgang_pruefen($mit, 701, 'eingeladen') === null);
$p = kd_vorgang_pruefen($ohne, 704, 'eingeladen');
pruefe('ohne Klasse, Einladen: 404 mit dem bisherigen Satz',
    ($p['status'] ?? 0) === 404 && ($p['text'] ?? '') === 'Dieses Kind steht nicht in der Klassenliste aus WebUntis. Es wurde nicht eingeladen.');
$p = kd_vorgang_pruefen($ohne, 704, 'gebucht');
pruefe('ohne Klasse, stellvertretend: 404, derselbe Satz, „nicht gebucht“',
    ($p['status'] ?? 0) === 404 && ($p['text'] ?? '') === 'Dieses Kind steht nicht in der Klassenliste aus WebUntis. Es wurde nicht gebucht.');
pruefe('nicht in pageconfig: ebenso 404', (kd_vorgang_pruefen($nicht, 999, 'gebucht')['status'] ?? 0) === 404);
$p = kd_vorgang_pruefen($kaputt, 701, 'eingeladen');
pruefe('Liste nicht lesbar: 502 mit dem bisherigen Satz, Grund fürs Protokoll',
    ($p['status'] ?? 0) === 502
    && ($p['text'] ?? '') === 'Die Klassenliste aus WebUntis ließ sich gerade nicht lesen. Es wurde nicht eingeladen – bitte erneut versuchen.'
    && ($p['grund'] ?? '') === 'pageconfig: Status 403');
pruefe('Liste nicht lesbar ist NICHT „steht nicht in der Liste“ (zwei Stufen, zwei Sätze)',
    !str_contains((string)(kd_vorgang_pruefen($kaputt, 701, 'gebucht')['text'] ?? ''), 'steht nicht'));
pruefe('kd_hat_klasse: null und klasse_id 0 nein, > 0 ja',
    !kd_hat_klasse(null) && !kd_hat_klasse(['klasse_id' => 0]) && kd_hat_klasse(['klasse_id' => 9]));

// ------------------------------------------------------------
echo "mit_eltern_ids_ermitteln reicht das Ergebnis von kd_ermitteln weiter\n";
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE buchungen (id INTEGER PRIMARY KEY, schueler_id INTEGER, eltern_user_id INTEGER)');
final class EmpfRest extends WebUntisRest
{
    public function __construct(private array $antworten) { parent::__construct('http://127.0.0.1:9', 'x'); }
    public function get(string $pfad, array $query = []): array
    {
        return $this->antworten[$pfad] ?? ['status' => 404, 'json' => null];
    }
    public function empfaengerSuchen(string $suchtext): array { return ['users' => []]; }
}
$m = mit_eltern_ids_ermitteln($pdo, 704, new EmpfRest([$PC => ['status' => 200, 'json' => $pageconfig]]));
pruefe('ermittelt: Kinddaten des Kindes (ohne Klasse) und grund null',
    is_array($m['ermittelt'] ?? null) && array_key_exists('grund', $m['ermittelt']) && $m['ermittelt']['grund'] === null
    && (($m['ermittelt']['kinder'][704]['klasse_id'] ?? -1) === 0));
$m = mit_eltern_ids_ermitteln($pdo, 701, new EmpfRest([$PC => ['status' => 403, 'json' => null]]));
pruefe('ermittelt: Grund, wenn die Liste nicht lesbar war', ($m['ermittelt']['grund'] ?? null) === 'pageconfig: Status 403');

// ------------------------------------------------------------
echo "Aufrufstellen (Quelltext – die Routen sind nicht ausführbar)\n";
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
$bu = $code(__DIR__ . '/../backend/api/buchungen.php');
$ei = $ausschnitt($bu, "\$kind = (int)req(\$body, 'schueler_id');", 'INSERT IGNORE INTO einladungen');
pruefe('Einladen: fragt kd_vorgang_pruefen vor dem Speichern, mit „eingeladen“',
    preg_match('/kd_vorgang_pruefen\(\$ermittelt, \$kind, \'eingeladen\'\)/', $ei) === 1);
pruefe('Einladen: keine eigene Klassenprüfung mehr daneben (die alte Fassung)',
    $ei !== '' && !str_contains($ei, "['klasse_id'] <= 0"));
$sv = $ausschnitt($bu, "=== 'stellvertretend')", '$pdo->beginTransaction();');
$p1 = strpos($sv, 'mit_eltern_ids_ermitteln(');
$p2 = strpos($sv, "kd_vorgang_pruefen(\$aufl['ermittelt'], \$kind, 'gebucht')");
$p3 = strpos($sv, "\$aufl['ids'] === []");
pruefe('stellvertretend: fragt kd_vorgang_pruefen nach der Ermittlung und vor der Elternkonto-Frage und der Transaktion',
    $p1 !== false && $p2 !== false && $p3 !== false && $p1 < $p2 && $p2 < $p3);
pruefe('stellvertretend: Einwand führt zum Abbruch mit Status und Text',
    str_contains($ausschnitt($sv, 'if ($einwand !== null) {', "\$aufl['ids'] === []"), "json_err(\$einwand['text'], \$einwand['status'])"));
pruefe('stellvertretend: die Meldung nennt die verschwindende Schülerliste nicht mehr',
    $sv !== '' && !str_contains($sv, 'Schülerliste'));

$ix = $code(__DIR__ . '/../backend/api/index.php');
$ro = $ausschnitt($ix, "if ((\$seg[0] ?? '') === 'kinder')", "json_err('Methode");
pruefe('Route /api/kinder vorhanden', $ro !== '');
$a1 = strpos($ro, 'auth_require_lehrkraft()');
$a2 = strpos($ro, 'kd_suche(');
pruefe('Route: Lehrkraft/Verwaltung geprüft, bevor gesucht wird', $a1 !== false && $a2 !== false && $a1 < $a2);
pruefe('Route: Sitzung fehlt → json_sitzung_fehlt (Kasten „Anmelden und suchen“)',
    str_contains($ro, "json_sitzung_fehlt(\$e['sitzung'])"));
pruefe('Route: nicht lesbar → 502 mit dem Satz zur Klassenliste, Grund ins Protokoll',
    preg_match('/json_err\(KD_SATZ_LISTE_NICHT_LESBAR[^;]*502\)/', $ro) === 1 && str_contains($ro, 'error_log('));

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
