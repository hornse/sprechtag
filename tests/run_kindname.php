<?php
// ============================================================
// tests/run_kindname.php – Zug 4, Schritt 2 (v0.9.76, E20): Kindname und
// Klasse werden am Vorgang festgehalten, nicht zur Laufzeit geholt
// Aufruf: php tests/run_kindname.php   → Exit-Code 0 = alles grün
//
// Quelle (gemessen, Befund pageconfig-Schülerliste, Abschnitt 20):
// Nachname aus pageconfig.longName (1314/1314), Vorname aus forename
// (1313/1314), Klasse aus timetable/filter classes[].class.displayName
// (40/40; longName nur 34 – NICHT verwenden). pageconfig.name ist keine
// Namensform.
//
// Geprüft wird:
//   * kd_aus_listen / kd_name / kd_ermitteln – die eine Stelle, die Name
//     und Klasse aus den beiden Antworten liest (ausgeführt);
//   * wu_kind_daten_login – bei der Anmeldung einmal, nur die EIGENEN Kinder
//     (Eltern) bzw. die eigene Person (volljährige Schüler: Name aus der
//     Anmeldung, ungemessen, ob pageconfig sie führt);
//   * die Kalender lesen den gespeicherten Namen – ausgeführt gegen eine
//     Datenbank OHNE Tabelle schueler (die Kalender-Abos haben keine
//     Sitzung, E8-Nachtrag);
//   * Engstelle: keine Anwendungsdatei liest Namen aus der Tabelle schueler;
//   * Schreibstellen: Buchen, stellvertretend, Einladen, Einreihen;
//   * Elternbuchung ohne Kinddaten in der Sitzung (Anmeldung vor v0.9.76,
//     oder WebUntis gab sie beim Login nicht her): nachholen über die
//     Sitzung – sonst NICHT buchen, nie still einen leeren Namen schreiben.
//
// Werte ERFUNDEN, Form wie belegt (Feldnamen aus dem Befund, Abschnitte 1,
// 11 und 20). Die „Klasse“ Veranst1 ohne Schüler ist belegt (Abschnitt 20).
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
require __DIR__ . '/../backend/api/kalender.php';

$fehlt = array_filter(['kd_aus_listen', 'kd_name', 'kd_ermitteln', 'wu_kind_daten_login',
    'auth_kind_daten_merken', 'auth_kind_daten', 'wu_kind_daten_buchung', 'auth_kind_daten_ergaenzen'], fn($f) => !function_exists($f));
pruefe('Voraussetzung: Funktionen vorhanden', $fehlt === []);
if ($fehlt !== []) { echo '    (fehlen: ' . implode(', ', $fehlt) . ")\n\n$fehler ROT\n"; exit(1); }

$PC = '/WebUntis/api/public/timetable/weekly/pageconfig';
$TF = '/WebUntis/api/rest/view/v1/timetable/filter';
$pageconfig = ['data' => ['elements' => [
    ['id' => 601, 'name' => 'k601x', 'forename' => 'Ute', 'longName' => 'ErfundenA',
     'externKey' => '9100601', 'klasseId' => 7],
    ['id' => 602, 'name' => 'k602x', 'forename' => ' Ole ', 'longName' => 'ErfundenB',
     'externKey' => '9100602', 'klasseId' => 0],                 // ohne Klasse (belegt: 79)
    ['id' => 603, 'name' => 'k603x', 'forename' => '', 'longName' => 'ErfundenC', 'klasseId' => 9],
]]];
$filter = ['classes' => [
    ['class' => ['id' => 7, 'shortName' => '5a', 'longName' => '5a Langform', 'displayName' => '5a'],
     'classTeacher1' => ['id' => 104, 'shortName' => 'Aa']],
    ['class' => ['id' => 30, 'shortName' => 'Veranst1', 'longName' => 'Veranstaltung', 'displayName' => 'Veranst1']],
]];

// ------------------------------------------------------------
echo "kd_aus_listen: Name aus longName/forename, Klasse aus displayName\n";
$a = kd_aus_listen($pageconfig, $filter, 601);
pruefe('Nachname aus longName, Vorname aus forename',
    ($a['nachname'] ?? null) === 'ErfundenA' && ($a['vorname'] ?? null) === 'Ute');
pruefe('Klasse aus displayName („5a“), NICHT aus longName („5a Langform“)',
    ($a['klasse'] ?? null) === '5a' && ($a['klasse_id'] ?? null) === 7);
pruefe('Klassenleitung aus derselben Antwort (classTeacher1)', ($a['leitung'] ?? null) === [104]);
pruefe('pageconfig.name wird nicht verwendet', !str_contains((string)json_encode($a), 'k601x'));
pruefe('kd_name: „Nachname, Vorname“', kd_name($a) === 'ErfundenA, Ute');
$b = kd_aus_listen($pageconfig, $filter, 602);
pruefe('ohne Klasse: klasse_id 0, Klasse leer; Leerzeichen am Rand entfernt',
    ($b['klasse_id'] ?? null) === 0 && ($b['klasse'] ?? null) === '' && kd_name($b) === 'ErfundenB, Ole');
$c = kd_aus_listen($pageconfig, $filter, 603);
pruefe('ohne Vorname: nur der Nachname, kein Komma', kd_name($c) === 'ErfundenC');
pruefe('Klasse nicht im Filter: Name ja, Klasse leer, klasse_id bleibt',
    ($c['klasse'] ?? null) === '' && ($c['klasse_id'] ?? null) === 9);
pruefe('nicht in pageconfig: null', kd_aus_listen($pageconfig, $filter, 999) === null);
pruefe('Liste unter data (ohne elements) wird ebenso gelesen',
    kd_name((array)kd_aus_listen(['data' => $pageconfig['data']['elements']], $filter, 601)) === 'ErfundenA, Ute');
pruefe('„Klasse“ ohne Schüler (Veranst1) hängt an keinem Kind',
    !in_array('Veranst1', array_map(fn($i) => kd_aus_listen($pageconfig, $filter, $i)['klasse'] ?? '', [601, 602, 603]), true));

// ------------------------------------------------------------
echo "kd_ermitteln: zwei Abrufe über die Sitzung, Fehler als Grund\n";
class KdRest
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
$ok = fn() => new KdRest([$PC => ['status' => 200, 'json' => $pageconfig], $TF => ['status' => 200, 'json' => $filter]]);
$r = $ok();
$e = kd_ermitteln($r, [601, 602, 999]);
pruefe('Erfolg: grund null, je Kind ein Eintrag (999 null)',
    $e['grund'] === null && kd_name((array)$e['kinder'][601]) === 'ErfundenA, Ute'
    && ($e['kinder'][602]['klasse_id'] ?? null) === 0 && array_key_exists(999, $e['kinder']) && $e['kinder'][999] === null);
pruefe('genau zwei Abrufe: pageconfig, dann timetable/filter', $r->aufrufe === [$PC, $TF]);
$r = $ok();
kd_ermitteln($r, [602]);
pruefe('kein Kind mit Klasse: nur pageconfig, kein Filterabruf', $r->aufrufe === [$PC]);
$e403 = kd_ermitteln(new KdRest([$PC => ['status' => 403, 'json' => null]]), [601]);
pruefe('pageconfig 403: Grund nennt Abruf und Status, keine Kinder',
    $e403['grund'] === 'pageconfig: Status 403' && $e403['kinder'] === []);
$e500 = kd_ermitteln(new KdRest([$PC => ['status' => 200, 'json' => $pageconfig], $TF => ['status' => 500, 'json' => null]]), [601]);
pruefe('timetable/filter 500: Grund nennt den Filter', $e500['grund'] === 'timetable/filter: Status 500');
$eEx = kd_ermitteln(new KdRest([$PC => new RuntimeException('Netz')]), [601]);
pruefe('Betriebsfehler (Exception): als Grund, kein Abbruch', $eEx['grund'] === 'Ausnahme RuntimeException');
$geworfen = false;
try { kd_ermitteln(new KdRest([$PC => new TypeError('kaputt')]), [601]); } catch (TypeError $t) { $geworfen = true; }
pruefe('Programmfehler (Error) geht weiter (FALLSTRICKE 3)', $geworfen);

// ------------------------------------------------------------
echo "Anmeldung: einmal festhalten, nur die eigenen Kinder\n";
$r = $ok();
$l = wu_kind_daten_login($r, 'eltern', [['id' => 601, 'name' => 'x'], ['id' => 602, 'name' => 'y'],
    ['id' => 999, 'name' => 'z']], 'Elternteil');
pruefe('Eltern: Name und Klasse je eigenem Kind',
    ($l[601] ?? null) === ['name' => 'ErfundenA, Ute', 'klasse' => '5a', 'leitung' => [104]]
    && ($l[602] ?? null) === ['name' => 'ErfundenB, Ole', 'klasse' => '', 'leitung' => []]);
pruefe('Eltern: Kind, das pageconfig nicht führt, fehlt (kein Name geraten)', !array_key_exists(999, $l));
pruefe('Eltern: die Kennungen der Kinder kommen aus der Anmeldung, nicht aus der Liste',
    !array_key_exists(603, $l));
$ls = wu_kind_daten_login($ok(), 'schueler', [['id' => 601, 'name' => 'Ute ErfundenA']], 'Ute ErfundenA');
pruefe('Schüler: Name aus der Anmeldung (ungemessen, ob pageconfig ihn führt), Klasse aus pageconfig',
    ($ls[601] ?? null) === ['name' => 'Ute ErfundenA', 'klasse' => '5a', 'leitung' => [104]]);
$ls2 = wu_kind_daten_login($ok(), 'schueler', [['id' => 999, 'name' => 'Eigen']], 'Eigen');
pruefe('Schüler, den pageconfig nicht führt: Name aus der Anmeldung, Klasse leer',
    ($ls2[999] ?? null) === ['name' => 'Eigen', 'klasse' => '', 'leitung' => []]);
$lf = wu_kind_daten_login(new KdRest([$PC => ['status' => 403, 'json' => null]]), 'eltern', [['id' => 601, 'name' => 'x']], 'E');
pruefe('Eltern, Abruf scheitert: nichts festgehalten', $lf === []);
$lsf = wu_kind_daten_login(new KdRest([$PC => ['status' => 403, 'json' => null]]), 'schueler', [['id' => 601, 'name' => 'S']], 'S');
pruefe('Schüler, Abruf scheitert: der eigene Name bleibt', ($lsf[601]['name'] ?? null) === 'S');
$rl = $ok();
pruefe('Lehrkraft: nichts, kein Abruf', wu_kind_daten_login($rl, 'lehrkraft', [], 'L') === [] && $rl->aufrufe === []);

$_SESSION = ['klassenleitung' => [601 => [999]]];
auth_kind_daten_merken($l);
pruefe('Sitzung: Name und Klasse je Kind gemerkt',
    ($_SESSION['kind_daten'][601] ?? null) === ['name' => 'ErfundenA, Ute', 'klasse' => '5a']);
pruefe('Sitzung: Klassenleitung aus derselben Antwort vorbelegt (kein zweiter Abruf), alte ersetzt',
    ($_SESSION['klassenleitung'][601] ?? null) === [104] && ($_SESSION['klassenleitung'][602] ?? null) === []);
pruefe('auth_kind_daten: gemerkt', auth_kind_daten(601) === ['name' => 'ErfundenA, Ute', 'klasse' => '5a']);
pruefe('auth_kind_daten: unbekannt → leer, kein Fehler', auth_kind_daten(4711) === ['name' => '', 'klasse' => '']);
auth_kind_daten_merken([]);
pruefe('erneute Anmeldung ohne Daten: Altes nicht stehen gelassen', auth_kind_daten(601) === ['name' => '', 'klasse' => '']);

$ad = (string)file_get_contents(__DIR__ . '/../backend/api/webuntis_adapter.php');
$au = (string)file_get_contents(__DIR__ . '/../backend/api/auth.php');
pruefe('wu_login hält die Kinddaten fest (Aufrufstelle)',
    preg_match("/\\\$ergebnis\['kind_daten'\] = wu_kind_daten_login\(\\\$restOk \\? \\\$rest : null, \\\$ergebnis\['rolle'\],\s*\\\$ergebnis\['kinder'\], \\\$ergebnis\['name'\]\)/", $ad) === 1);
pruefe('auth_login_speichern merkt sie (Aufrufstelle)',
    str_contains($au, "auth_kind_daten_merken(\$daten['kind_daten'] ?? []);"));

// ------------------------------------------------------------
echo "Elternbuchung: fehlende Kinddaten nachholen – oder nicht buchen\n";
class KdSitzRest extends KdRest
{
    public bool $token = true;
    public function mitSessionCookie(string $c): void {}
    public function setzeTimeout(int $s): void {}
    public function tokenHolen(): bool { return $this->token; }
    public function tenantErmitteln(): void {}
}
$cfgT = ['webuntis' => ['base_url' => 'https://erfunden.invalid', 'school' => 'x']];
$gebaut = 0;
$fabrik = function (array $antworten, bool $token = true) use (&$gebaut): callable {
    return function () use ($antworten, $token, &$gebaut) {
        $gebaut++;
        $r = new KdSitzRest($antworten);
        $r->token = $token;
        return $r;
    };
};
$okA = [$PC => ['status' => 200, 'json' => $pageconfig], $TF => ['status' => 200, 'json' => $filter]];

$_SESSION = ['wu_cookie' => 'c', 'kind_daten' => [601 => ['name' => 'ErfundenA, Ute', 'klasse' => '5a']]];
$gebaut = 0;
pruefe('Name in der Sitzung: genau der, kein WebUntis-Abruf',
    wu_kind_daten_buchung($cfgT, 601, 'eltern', 'E', $fabrik($okA)) === ['name' => 'ErfundenA, Ute', 'klasse' => '5a']
    && $gebaut === 0);

$_SESSION = ['wu_cookie' => 'c', 'kind_daten' => [602 => ['name' => 'ErfundenB, Ole', 'klasse' => '']],
             'klassenleitung' => [602 => []]];
unset($_SESSION['kind_daten'][601]);
$gebaut = 0;
$n = wu_kind_daten_buchung($cfgT, 601, 'eltern', 'E', $fabrik($okA));
pruefe('fehlt der Name, frische Sitzung: Name und Klasse aus pageconfig nachgeholt',
    $n === ['name' => 'ErfundenA, Ute', 'klasse' => '5a'] && $gebaut === 1);
pruefe('… in die Sitzung ergänzt, samt Klassenleitung; die nächste Buchung ohne Abruf',
    auth_kind_daten(601) === ['name' => 'ErfundenA, Ute', 'klasse' => '5a']
    && ($_SESSION['klassenleitung'][601] ?? null) === [104]
    && wu_kind_daten_buchung($cfgT, 601, 'eltern', 'E', $fabrik($okA)) === $n && $gebaut === 1);
pruefe('… die anderen Kinder der Sitzung bleiben stehen (ergänzt, nicht ersetzt)',
    auth_kind_daten(602) === ['name' => 'ErfundenB, Ole', 'klasse' => '']);

$_SESSION = [];   // Anmeldung vor v0.9.76: weder kind_daten noch Cookie-Ersatz
$gebaut = 0;
$o = wu_kind_daten_buchung($cfgT, 601, 'eltern', 'E', $fabrik($okA));
pruefe('ohne WebUntis-Sitzung: „abgelaufen“ (Kasten), kein Name, kein Abruf',
    ($o['sitzung']['art'] ?? null) === 'abgelaufen' && !isset($o['name']) && $gebaut === 0);
$_SESSION = ['wu_cookie' => 'c'];
$o2 = wu_kind_daten_buchung($cfgT, 601, 'eltern', 'E', $fabrik($okA, false));
pruefe('Sitzung abgelaufen (kein Token): „abgelaufen“, kein Name',
    ($o2['sitzung']['art'] ?? null) === 'abgelaufen' && !isset($o2['name']));
$_SESSION = ['wu_cookie' => 'c'];
$o3 = wu_kind_daten_buchung($cfgT, 601, 'eltern', 'E', $fabrik([$PC => ['status' => 403, 'json' => null]]));
pruefe('Liste nicht lesbar (403): Grund, kein Name, nichts in die Sitzung',
    isset($o3['grund']) && !isset($o3['name']) && !isset($o3['sitzung']) && auth_kind_daten(601)['name'] === '');
$o4 = wu_kind_daten_buchung($cfgT, 999, 'eltern', 'E', $fabrik($okA));
pruefe('Kind, das pageconfig nicht führt: Grund, kein geratener Name',
    isset($o4['grund']) && !isset($o4['name']));
$_SESSION = ['wu_cookie' => 'c'];
$o5 = wu_kind_daten_buchung($cfgT, 601, 'schueler', 'Ute ErfundenA', $fabrik($okA));
pruefe('Schüler (Anmeldung vor v0.9.76): eigener Name, Klasse aus pageconfig',
    $o5 === ['name' => 'Ute ErfundenA', 'klasse' => '5a']);
$_SESSION = ['wu_cookie' => 'c'];
$o6 = wu_kind_daten_buchung($cfgT, 601, 'schueler', '', $fabrik($okA));
pruefe('Schüler ohne eigenen Namen: Grund, nicht leer gebucht', isset($o6['grund']) && !isset($o6['name']));
$_SESSION = ['wu_cookie' => 'c'];
$o7 = wu_kind_daten_buchung($cfgT, 601, 'lehrkraft', 'L', $fabrik($okA));
pruefe('Lehrkraft-Zweig mit Eltern-Benutzer-ID (von der Oberfläche nicht gerufen): Grund, nie leer',
    isset($o7['grund']) && !isset($o7['name']));

// ------------------------------------------------------------
echo "Die Kalender lesen den gespeicherten Namen (ausgeführt, OHNE Tabelle schueler)\n";
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE sprechtage (id INTEGER PRIMARY KEY, name TEXT, datum TEXT, slot_minuten INT, beginn TEXT, ende TEXT)');
$db->exec('CREATE TABLE lehrer (id INTEGER PRIMARY KEY, name TEXT, kuerzel TEXT)');
$db->exec('CREATE TABLE raeume (id INTEGER PRIMARY KEY, kuerzel TEXT)');
$db->exec('CREATE TABLE sprechtag_lehrer (sprechtag_id INT, lehrer_id INT, raum_id INT)');
$db->exec('CREATE TABLE buchungen (id INTEGER PRIMARY KEY, sprechtag_id INT, lehrer_id INT, slot_beginn TEXT,
    eltern_user_id INT, schueler_id INT, kommentar TEXT DEFAULT "", kind_name TEXT NOT NULL DEFAULT "",
    kind_klasse TEXT NOT NULL DEFAULT "")');
$db->exec("INSERT INTO sprechtage VALUES (1, 'Sprechtag', '2026-11-12', 10, '15:00:00', '19:00:00')");
$db->exec("INSERT INTO lehrer VALUES (7, 'Erfundene Lehrkraft', 'Ef')");
$db->exec("INSERT INTO buchungen (id, sprechtag_id, lehrer_id, slot_beginn, eltern_user_id, schueler_id, kind_name, kind_klasse)
           VALUES (1, 1, 7, '15:00:00', 5001, 601, 'ErfundenA, Ute', '5a')");
$fehlerText = null;
try {
    $kl = kal_lehrer_buchungen($db, 7, 1);
    $ke = kal_buchungen_laden($db, 5001);
} catch (PDOException $x) { $kl = $ke = []; $fehlerText = $x->getMessage(); }
pruefe('Abfragen laufen ohne Tabelle schueler', $fehlerText === null);
if ($fehlerText !== null) echo "    ($fehlerText)\n";
pruefe('Kalender der Lehrkraft: Name und Klasse aus der Buchung',
    ($kl[0]['kind_name'] ?? null) === 'ErfundenA, Ute' && ($kl[0]['kind_klasse'] ?? null) === '5a');
pruefe('… und im Eintrag (Titel mit Kind und Klasse)',
    str_contains(kal_vevents_lehrer($kl), 'ErfundenA') && str_contains(kal_vevents_lehrer($kl), '5a'));
pruefe('Kalender der Eltern: Name aus der Buchung', ($ke[0]['kind_name'] ?? null) === 'ErfundenA, Ute');

// ------------------------------------------------------------
echo "Engstelle: kein Name mehr aus der Tabelle schueler\n";
$code = function (string $datei): string {
    $c = '';
    foreach (token_get_all((string)file_get_contents(__DIR__ . '/../backend/api/' . $datei)) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
};
$dateien = array_map('basename', glob(__DIR__ . '/../backend/api/*.php'));
pruefe('Voraussetzung: Anwendungsdateien gefunden (mind. 10)', count($dateien) >= 10);
$joins = [];
foreach ($dateien as $d) if (preg_match('/JOIN\s+schueler\b/i', $code($d))) $joins[] = $d;
pruefe('keine Datei verbindet mit der Tabelle schueler (JOIN)', $joins === []);
if ($joins !== []) echo '    (' . implode(', ', $joins) . ")\n";
$lesen = [];
foreach (['buchungen.php', 'kalender.php', 'mitteilungen.php', 'klassenleitung.php'] as $d) {
    if (preg_match('/FROM\s+schueler\b/i', $code($d))) $lesen[] = $d;
}
pruefe('Buchen, Kalender, Mitteilungen lesen die Tabelle schueler nicht mehr', $lesen === []);
if ($lesen !== []) echo '    (' . implode(', ', $lesen) . ")\n";

$bu = $code('buchungen.php');
$ix = $code('index.php');
pruefe('Anzeigen lesen den gespeicherten Namen: Raster und Lehrkraft-Sicht (b.kind_name)',
    substr_count($bu, 'b.kind_name') >= 2 && substr_count($bu, 'b.kind_klasse AS klasse') >= 2);
pruefe('Anzeigen: Einladungsliste (e.kind_name, e.kind_klasse)',
    str_contains($bu, 'e.kind_name') && str_contains($bu, 'e.kind_klasse AS klasse'));
pruefe('Anzeigen: Tischvorlage (b.kind_name, b.kind_klasse)',
    str_contains($ix, 'SELECT b.slot_beginn, b.kommentar, b.kind_name, b.kind_klasse'));
pruefe('Anzeigen: Mitteilungsliste (m.kind_name, m.kind_klasse)',
    str_contains($ix, 'm.kind_name, m.kind_klasse AS klasse'));

// ------------------------------------------------------------
echo "Schreibstellen\n";
$ausschnitt = function (string $quelle, string $von, string $bis): string {
    $a = strpos($quelle, $von);
    if ($a === false) return '';
    $b = strpos($quelle, $bis, $a + strlen($von));
    return $b === false ? '' : substr($quelle, $a, $b - $a);
};
$sv = $ausschnitt($bu, "=== 'stellvertretend')", "if (\$methode === 'POST' && !isset(\$seg[1]))");
$eb = $ausschnitt($bu, "if (\$methode === 'POST' && !isset(\$seg[1]))", "if (\$methode === 'DELETE'");
$ei = $ausschnitt($bu, "=== 'einladungen')", "if (\$methode === 'DELETE' && isset(\$seg[1])");
pruefe('Voraussetzung: drei Ausschnitte gefunden', $sv !== '' && $eb !== '' && $ei !== '');
pruefe('Elternbuchung: Name und Klasse über wu_kind_daten_buchung (Sitzung, sonst nachgeholt), in die Buchung',
    preg_match("/\\\$kd = wu_kind_daten_buchung\(\\\$cfg, \\\$kind, \\\$rolle, \(string\)\\\$u\['name'\]\);/", $eb) === 1
    && preg_match("/kommentar, phase, gebucht_von, kind_name, kind_klasse\)/", $eb) === 1
    && preg_match("/\\\$kd\['name'\], \\\$kd\['klasse'\]\]\);/", $eb) === 1);
pruefe('Elternbuchung: nicht mehr unmittelbar aus der Sitzung (die stille Fassung)',
    !str_contains($eb, 'auth_kind_daten($kind)'));
$q1 = strpos($eb, '$kd = wu_kind_daten_buchung(');
$q2 = strpos($eb, "if (isset(\$kd['sitzung'])) json_sitzung_fehlt(\$kd['sitzung']);");
$q3 = strpos($eb, "if (!isset(\$kd['name'])) json_err(");
$q4 = strpos($eb, '$pdo->beginTransaction();');
pruefe('Elternbuchung: ohne Sitzung Kasten, ohne Name 502 – beides VOR dem Speichern (Reihenfolge; Route nicht ausführbar)',
    $q1 !== false && $q2 !== false && $q3 !== false && $q4 !== false && $q1 < $q2 && $q2 < $q3 && $q3 < $q4
    && preg_match("/if \(!isset\(\\\$kd\['name'\]\)\) json_err\([^;]*502\);/s", $eb) === 1);
pruefe('stellvertretend: Name und Klasse aus der Suche über die Sitzung (kd), in die Buchung',
    preg_match("/kommentar, phase, gebucht_von, kind_name, kind_klasse\)/", $sv) === 1
    && preg_match("/\\\$kindDaten\['name'\], \\\$kindDaten\['klasse'\]\]\);/", $sv) === 1);
$p1 = strpos($ei, 'json_sitzung_fehlt($sz)');
$p2 = strpos($ei, 'kd_ermitteln($sz[\'rest\'], [$kind])');
$p3 = strpos($ei, 'INSERT IGNORE INTO einladungen');
pruefe('Einladung: ohne Sitzung Abbruch (Kasten), VOR dem Abruf und VOR dem Speichern (Reihenfolge; Route nicht ausführbar)',
    $p1 !== false && $p2 !== false && $p3 !== false && $p1 < $p2 && $p2 < $p3);
// Seit v0.9.81 entscheidet kd_vorgang_pruefen (ausgeführt in run_kind_suche.php);
// hier die Aufrufstelle vor dem Speichern.
pruefe('Einladung: Kind ohne Klasse oder nicht in der Liste → Abbruch über kd_vorgang_pruefen, nichts gespeichert',
    preg_match("/kd_vorgang_pruefen\(\\\$ermittelt, \\\$kind, 'eingeladen'\);\s*if \(\\\$einwand !== null\) \{.*?json_err\(\\\$einwand\['text'\], \\\$einwand\['status'\]\);\s*\}/s", $ei) === 1);
pruefe('Einladung: keine Prüfung mehr gegen die alte Tabelle', !str_contains($ei, 'schueler WHERE'));
pruefe('Einladung: speichert Name und Klasse',
    str_contains($ei, '(sprechtag_id, lehrer_id, schueler_id, hinweis, kind_name, kind_klasse)'));

$s = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$s->exec('CREATE TABLE mitteilungen (id INTEGER PRIMARY KEY AUTOINCREMENT, sprechtag_id INT,
    empfaenger_user_id INT, empfaenger_art TEXT, schueler_id INT, lehrer_id INT, anlass TEXT,
    betreff TEXT, text TEXT, status TEXT, grund TEXT, kind_name TEXT NOT NULL DEFAULT "",
    kind_klasse TEXT NOT NULL DEFAULT "")');
$mid = mit_einreihen($s, 1, 0, 'absage', 'B', 'T', 601, 7, 'eltern', 'ErfundenA, Ute', '5a');
$z = $s->query("SELECT kind_name, kind_klasse FROM mitteilungen WHERE id = $mid")->fetch();
pruefe('mit_einreihen speichert Name und Klasse (ausgeführt)', $z === ['kind_name' => 'ErfundenA, Ute', 'kind_klasse' => '5a']);
$mid2 = mit_einreihen($s, 1, 5, 'hinweis', 'B', 'T');
pruefe('… ohne Angabe: leer, kein Fehler',
    $s->query("SELECT kind_name FROM mitteilungen WHERE id = $mid2")->fetchColumn() === '');
pruefe('Absage und Ausfall übernehmen Name und Klasse aus der Buchung',
    preg_match("/mit_absage_art\(\(int\)\\\$b\['schueler_id'\]\),\s*\(string\)\\\$b\['kind_name'\], \(string\)\\\$b\['kind_klasse'\]\)/", $bu) === 1
    && preg_match("/mit_absage_art\(\(int\)\\\$b\['schueler_id'\]\),\s*\(string\)\\\$b\['kind_name'\], \(string\)\\\$b\['kind_klasse'\]\)/", $ix) === 1);

// ------------------------------------------------------------
echo "mit_eltern_ids_ermitteln: Name aus pageconfig, nicht aus der Tabelle\n";
final class SuchRest extends WebUntisRest
{
    public array $suchen = [];
    public array $aufrufe = [];
    public function __construct(private array $antworten) { parent::__construct('http://127.0.0.1:9', 'x'); }
    public function get(string $pfad, array $query = []): array
    {
        $this->aufrufe[] = $pfad;
        return $this->antworten[$pfad] ?? ['status' => 404, 'json' => null];
    }
    public function empfaengerSuchen(string $suchtext): array
    {
        $this->suchen[] = $suchtext;
        // Form wie mit_eltern_zu_kind() sie liest: role, tags mit Kindnamen.
        return ['users' => [['id' => 7001, 'displayName' => 'Elternteil ErfundenA',
            'role' => 'LEGAL_GUARDIAN', 'tags' => ['ErfundenA Ute']]]];
    }
}
$sr = new SuchRest([$PC => ['status' => 200, 'json' => $pageconfig], $TF => ['status' => 200, 'json' => $filter]]);
$leer = new PDO('sqlite::memory:');
$leer->exec('CREATE TABLE buchungen (schueler_id INT, eltern_user_id INT)');
$au2 = mit_eltern_ids_ermitteln($leer, 601, $sr);
pruefe('sucht mit dem Nachnamen aus pageconfig', $sr->suchen === ['ErfundenA']);
pruefe('liefert die Kinddaten mit (für die Buchung)', kd_name((array)($au2['kind'] ?? [])) === 'ErfundenA, Ute'
    && ($au2['kind']['klasse'] ?? null) === '5a');
pruefe('kind_name für Texte: „Vorname Nachname“', ($au2['kind_name'] ?? null) === 'Ute ErfundenA');

// ------------------------------------------------------------
echo "Migration sql/22\n";
$mig = (string)@file_get_contents(__DIR__ . '/../sql/22_kindname.sql');
foreach (['buchungen', 'einladungen', 'mitteilungen'] as $t) {
    pruefe("$t: kind_name und kind_klasse, zweimal einspielbar",
        preg_match("/ALTER TABLE $t\s+ADD COLUMN IF NOT EXISTS kind_name VARCHAR\(170\) NOT NULL DEFAULT ''[^;]*ADD COLUMN IF NOT EXISTS kind_klasse VARCHAR\(30\) NOT NULL DEFAULT ''/s", $mig) === 1);
}
pruefe('übernimmt Namen vorhandener Zeilen aus der alten Tabelle, nur wo leer (idempotent)',
    substr_count($mig, "WHERE x.kind_name = ''") === 3);
pruefe('die Tabelle schueler bleibt (fällt erst mit Schritt 4)', !preg_match('/DROP\s+TABLE/i', $mig));

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
