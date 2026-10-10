<?php
// ============================================================
// tests/run_sitzung_versand.php – Versand ohne Dienstkonto (v0.9.72, E17)
// Aufruf: php tests/run_sitzung_versand.php   → Exit-Code 0 = alles grün
//
// Der eigentliche Teil des Zugs: die abgelaufene Sitzung.
//   * Die Mitteilung bleibt stehen, der Text geht nicht verloren.
//   * Die Antwort sagt KLAR, warum – je Ursache verschieden (abgelaufen /
//     nicht erreichbar / kaputt); kein Rückfall auf ein anderes Konto.
//   * Nach der Neuanmeldung geht dieselbe Mitteilung mit einem Aufruf raus.
//   * An die Eltern eines Kindes über recipientOption PARENTS: genau ein
//     Aufruf, Erfolg erst bei numberOfRecipients ≥ 1 (Befund 16).
//   * Die Routen: Versand, Hinweis nach der Anmeldung, Erinnerungen.
//
// Datenbank: SQLite im Speicher mit den Spalten, die sql/21 anlegt.
// WebUntis-Antworten ERFUNDEN in der belegten Form (numberOfRecipients,
// gemessen 07.10. bzw. 09.10.2026).
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

final class Antwort extends Exception
{
    public function __construct(public int $status, public array $daten) { parent::__construct('antwort'); }
}
function json_ok(array $d, int $s = 200): never { throw new Antwort($s, $d); }
function json_err(string $m, int $s = 400): never { throw new Antwort($s, ['fehler' => $m]); }
$GLOBALS['testPdo'] = null;
function db(array $cfg): PDO { return $GLOBALS['testPdo']; }

require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/api/auth.php';
require __DIR__ . '/../backend/api/einstellungen.php';
require __DIR__ . '/../backend/api/mitteilungen.php';
require __DIR__ . '/../backend/api/erinnerungen.php';

$fehlt = array_filter(['mit_einreihen', 'mit_senden_oder_vormerken', 'mit_senden_eltern',
    'mit_parents_koerper', 'json_sitzung_fehlt'], fn($f) => !function_exists($f));
pruefe('Voraussetzung: Funktionen vorhanden', $fehlt === []);
if ($fehlt !== []) { echo '    (fehlen: ' . implode(', ', $fehlt) . ")\n\n$fehler ROT\n"; exit(1); }

function neue_db(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE sprechtage (id INTEGER PRIMARY KEY, name TEXT, datum TEXT)');
    $pdo->exec('CREATE TABLE mitteilungen (id INTEGER PRIMARY KEY AUTOINCREMENT,
      sprechtag_id INT, empfaenger_user_id INT NOT NULL, empfaenger_art TEXT NOT NULL DEFAULT "konto",
      schueler_id INT, lehrer_id INT, anlass TEXT, betreff TEXT, text TEXT,
      status TEXT DEFAULT "offen", grund TEXT DEFAULT "", versuche INT DEFAULT 0,
      angelegt_am TEXT DEFAULT CURRENT_TIMESTAMP, gesendet_am TEXT,
      kind_name TEXT NOT NULL DEFAULT "", kind_klasse TEXT NOT NULL DEFAULT "")');  // sql/22 (v0.9.76)
    $pdo->exec('CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)');
    $pdo->exec('CREATE TABLE buchungen (id INTEGER PRIMARY KEY, sprechtag_id INT, lehrer_id INT,
      eltern_user_id INT, schueler_id INT, slot_beginn TEXT)');
    // Keine Tabelle schueler: seit sql/23 (v0.9.82) gibt es sie im Betrieb nicht mehr.
    $pdo->exec("INSERT INTO sprechtage VALUES (1, 'Sprechtag', '2099-11-20'), (2, 'Vorbei', '2000-01-01')");
    return $pdo;
}

/** Echter WebUntisRest, nur die sendenden Aufrufe ersetzt. */
final class VersandRest extends WebUntisRest
{
    public array $aufrufe = [];
    public function __construct(private array $antworten) { parent::__construct('http://127.0.0.1:9', 'x'); }
    public function postMultipart(string $pfad, array $daten, string $feldname = 'request'): array
    { $this->aufrufe[] = [$pfad, $daten]; return $this->antworten[$pfad] ?? ['status' => 500, 'json' => null, 'text' => '']; }
    public function post(string $pfad, array $daten): array
    { $this->aufrufe[] = [$pfad, $daten]; return ['status' => 500, 'json' => null, 'text' => '']; }
}
const P_USERS = '/WebUntis/api/rest/view/v2/messages/users';
const P_MSG   = '/WebUntis/api/rest/view/v2/messages';
$ok  = ['status' => 200, 'json' => ['numberOfRecipients' => 1], 'text' => ''];
$ok4 = ['status' => 200, 'json' => ['numberOfRecipients' => 4, 'numberOfCCRecipients' => null], 'text' => ''];
$weg = fn(string $art, string $grund) => ['rest' => null, 'art' => $art, 'grund' => $grund];
$zeile = fn(PDO $pdo, int $id) => $pdo->query('SELECT * FROM mitteilungen WHERE id = ' . $id)->fetch();

// ------------------------------------------------------------
echo "Abgelaufen: die Mitteilung bleibt stehen, die Antwort sagt es\n";
$pdo = neue_db();
$text = "Guten Tag,\n\nleider muss Ihr Termin entfallen.\n\nMit freundlichen Grüßen";
$e = mit_einreihen_und_senden($pdo, 1, 5984, 'absage', 'Terminabsage', $text, 90001, 7,
    $weg('abgelaufen', 'kein_token'));
$z = $zeile($pdo, $e['ids'][0]);
pruefe('Status offen, Ursache abgelaufen, eine Kennung der Mitteilung',
    $e['status'] === 'offen' && $e['sitzung'] === 'abgelaufen' && count($e['ids']) === 1 && $e['gesendet'] === 0);
pruefe('die Meldung sagt KLAR: neu anmelden', $e['grund'] === wu_sitzung_meldung('abgelaufen')
    && str_contains($e['grund'], 'neu an'));
pruefe('der Text geht nicht verloren: gespeichert, Status offen, Lehrkraft vermerkt',
    $z['text'] === $text && $z['status'] === 'offen' && (int)$z['lehrer_id'] === 7 && (int)$z['versuche'] === 0);
pruefe('der Grund steht an der Mitteilung (für die Ansicht)', str_contains((string)$z['grund'], 'abgelaufen'));
$n = mit_senden_oder_vormerken($pdo, [$e['ids'][0]], $weg('nicht_erreichbar', 'nicht_erreichbar: Status 0'));
$k = mit_senden_oder_vormerken($pdo, [$e['ids'][0]], $weg('kaputt', 'fehler: TypeError: x'));
pruefe('nicht erreichbar und kaputt: ebenfalls offen, je eigene Ursache und eigener Satz',
    $n['sitzung'] === 'nicht_erreichbar' && $k['sitzung'] === 'kaputt'
    && count(array_unique([$e['grund'], $n['grund'], $k['grund']])) === 3);

echo "Nach der Neuanmeldung: dieselbe Mitteilung mit einem Aufruf hinaus\n";
$r = new VersandRest([P_USERS => $ok]);
$g = mit_senden_oder_vormerken($pdo, $e['ids'], ['rest' => $r, 'art' => null, 'grund' => null]);
$z = $zeile($pdo, $e['ids'][0]);
pruefe('gesendet: ein Aufruf an /v2/messages/users, Text und Empfänger aus der Warteschlange',
    $g['status'] === 'gesendet' && $g['sitzung'] === null && count($r->aufrufe) === 1
    && $r->aufrufe[0][0] === P_USERS && ($r->aufrufe[0][1]['content'] ?? null) === $text
    && ($r->aufrufe[0][1]['recipientUserIds'] ?? null) === [5984]);
pruefe('… in der Datenbank gesendet, mit Zeitpunkt', $z['status'] === 'gesendet' && $z['gesendet_am'] !== null);
$r2 = new VersandRest([P_USERS => $ok]);
mit_senden_oder_vormerken($pdo, $e['ids'], ['rest' => $r2, 'art' => null, 'grund' => null]);
pruefe('ein zweiter Klick sendet nicht doppelt', count($r2->aufrufe) === 0);
$leer = mit_senden_oder_vormerken($pdo, [], $weg('abgelaufen', 'kein_cookie'));
pruefe('nichts zu senden: Status keine, keine Ursache', $leer['status'] === 'keine' && $leer['sitzung'] === null);

// ------------------------------------------------------------
echo "An die Eltern eines Kindes (PARENTS)\n";
$pdo = neue_db();
$fehlerKind = false;
try { mit_einreihen($pdo, 1, 0, 'einladung', 'B', 'T', null, 7, 'eltern'); }
catch (InvalidArgumentException $x) { $fehlerKind = true; }
pruefe('PARENTS nur mit der Kennung des Kindes', $fehlerKind);
$id = mit_einreihen($pdo, 1, 5984, 'einladung', 'Einladung', 'Einladungstext', 90042, 7, 'eltern');
$z = $zeile($pdo, $id);
pruefe('gespeichert als Empfängerart eltern, ohne Konto-Kennung (0)',
    $z['empfaenger_art'] === 'eltern' && (int)$z['empfaenger_user_id'] === 0 && (int)$z['schueler_id'] === 90042);
$idK = mit_einreihen($pdo, 1, 5984, 'absage', 'Absage', 'Absagetext', 90001, 7);
$r = new VersandRest([P_MSG => $ok4, P_USERS => $ok]);
$g = mit_senden_oder_vormerken($pdo, [$id, $idK], ['rest' => $r, 'art' => null, 'grund' => null]);
$aufP = array_values(array_filter($r->aufrufe, fn($a) => $a[0] === P_MSG));
pruefe('genau ein Aufruf an /v2/messages mit PARENTS und der Kennung des Kindes, ohne Kopie an das Kind',
    count($aufP) === 1 && ($aufP[0][1]['recipientOption'] ?? null) === 'PARENTS'
    && ($aufP[0][1]['recipientPersonIds'] ?? null) === [90042] && ($aufP[0][1]['copyToStudent'] ?? null) === false
    && ($aufP[0][1]['subject'] ?? null) === 'Einladung' && ($aufP[0][1]['content'] ?? null) === 'Einladungstext');
pruefe('die Konto-Mitteilung daneben geht weiter an /v2/messages/users, nicht über PARENTS',
    count(array_filter($r->aufrufe, fn($a) => $a[0] === P_USERS)) === 1 && $g['status'] === 'gesendet');
pruefe('… beide gesendet, der Grund nennt die Zahl der Empfänger',
    $zeile($pdo, $id)['status'] === 'gesendet' && str_contains((string)$zeile($pdo, $id)['grund'], '4 Empfänger'));
$pdo = neue_db();
$i0 = mit_einreihen($pdo, 1, 0, 'einladung', 'B', 'T', 90042, 7, 'eltern');
$g0 = mit_senden_oder_vormerken($pdo, [$i0], ['rest' => new VersandRest([P_MSG => ['status' => 200,
    'json' => ['numberOfRecipients' => 0], 'text' => '']]), 'art' => null, 'grund' => null]);
$iu = mit_einreihen($pdo, 1, 0, 'einladung', 'B', 'T', 90042, 7, 'eltern');
$gu = mit_senden_oder_vormerken($pdo, [$iu], ['rest' => new VersandRest([P_MSG => ['status' => 200,
    'json' => ['x' => 1], 'text' => '']]), 'art' => null, 'grund' => null]);
pruefe('200 mit 0 Empfängern: NICHT gesendet, bleibt offen, Grund „niemand erreicht“',
    $g0['status'] === 'fehler' && $zeile($pdo, $i0)['status'] === 'offen'
    && str_contains((string)$zeile($pdo, $i0)['grund'], 'niemand'));
pruefe('200 ohne Zahl: NICHT gesendet, Grund „unklar – nachsehen“',
    $gu['status'] === 'fehler' && str_contains((string)$zeile($pdo, $iu)['grund'], 'unklar'));
$grenze = mit_senden_eltern(new VersandRest([P_MSG => ['status' => 200, 'json' => ['numberOfRecipients' => 1], 'text' => '']]), 90042, 'B', 'T');
pruefe('Grenze: 1 Empfänger ist erreicht', $grenze['ok'] === true);

// ------------------------------------------------------------
echo "Absagen an alle Erziehungsberechtigten (v0.9.73)\n";
pruefe('mit_absage_art: mit Kind-Kennung an die Eltern (PARENTS), Grenze 1',
    function_exists('mit_absage_art') && mit_absage_art(90001) === 'eltern' && mit_absage_art(1) === 'eltern');
pruefe('mit_absage_art: ohne Kind-Kennung (0) an das gebuchte Konto – nie ohne Empfänger',
    function_exists('mit_absage_art') && mit_absage_art(0) === 'konto');
if (function_exists('mit_absage_art')) {
    $pdo = neue_db();
    $ia = mit_einreihen($pdo, 1, 5984, 'absage', 'Terminabsage', 'Absagetext', 90001, 7, mit_absage_art(90001));
    $r = new VersandRest([P_MSG => $ok4, P_USERS => $ok]);
    mit_senden_oder_vormerken($pdo, [$ia], ['rest' => $r, 'art' => null, 'grund' => null]);
    pruefe('eine Absage geht als EIN Aufruf über PARENTS mit der Kind-Kennung, nicht an das Konto',
        count($r->aufrufe) === 1 && $r->aufrufe[0][0] === P_MSG
        && ($r->aufrufe[0][1]['recipientPersonIds'] ?? null) === [90001]
        && ($r->aufrufe[0][1]['content'] ?? null) === 'Absagetext');
} else {
    pruefe('eine Absage geht als EIN Aufruf über PARENTS mit der Kind-Kennung, nicht an das Konto', false);
}

// ------------------------------------------------------------
echo "json_sitzung_fehlt: 409, nie 401\n";
try { json_sitzung_fehlt($weg('abgelaufen', 'kein_token')); $a = null; } catch (Antwort $a) {}
pruefe('409 mit Meldung und Ursache', $a !== null && $a->status === 409 && $a->daten['sitzung'] === 'abgelaufen'
    && $a->daten['fehler'] === wu_sitzung_meldung('abgelaufen'));

// ------------------------------------------------------------
echo "Die Route /api/mitteilungen (aus index.php, ausgeführt)\n";
$quelle = (string)file_get_contents(__DIR__ . '/../backend/api/index.php');
$kopf = "if ((\$seg[0] ?? '') === 'mitteilungen') {";
$zweig = '';
if (($a0 = strpos($quelle, $kopf)) !== false) {
    $tiefe = 0;
    foreach (array_slice(token_get_all('<?php ' . substr($quelle, $a0)), 1) as $t) {
        $txt = is_array($t) ? $t[1] : $t;
        $zweig .= $txt;
        if ($txt === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $tiefe++;
        if ($txt === '}') { $tiefe--; if ($tiefe === 0) break; }
    }
}
pruefe('Voraussetzung: Zweig gefunden', $zweig !== '');
function route(string $zweig, array $sitzung, string $methode, array $seg, array $b = [], array $get = []): Antwort
{
    $_SESSION = $sitzung; $body = $b; $_GET = $get;
    $cfg = ['webuntis' => ['base_url' => 'http://127.0.0.1:9', 'school' => 'x']];
    try { eval($zweig); } catch (Antwort $a) { return $a; }
    return new Antwort(0, []);
}
$pdo = neue_db(); $GLOBALS['testPdo'] = $pdo;
// Absage der Lehrkraft 7: die Buchung ist danach gelöscht (keine Zeile in buchungen).
$eigen  = mit_einreihen($pdo, 1, 5984, 'absage', 'A', 'T', 90001, 7);
$fremd  = mit_einreihen($pdo, 1, 6000, 'absage', 'A', 'T', 90002, 8);
$alt    = mit_einreihen($pdo, 1, 6100, 'bestaetigung', 'B', 'T', 90003, null);   // vor v0.9.72: ohne Lehrkraft
$pdo->exec('INSERT INTO buchungen VALUES (1, 1, 7, 6100, 90003, "15:00:00")');
$vorbei = mit_einreihen($pdo, 2, 6200, 'absage', 'A', 'T', 90004, 7);
$lk = ['rolle' => 'lehrkraft', 'lehrer_id' => 7, 'name' => 'x'];
$ad = ['rolle' => 'admin', 'lehrer_id' => null, 'name' => 'x'];

$rs = route($zweig, $lk, 'POST', ['mitteilungen', 'senden'], ['ids' => [$eigen, $fremd, $alt]]);
pruefe('senden, Sitzung abgelaufen (kein Cookie): 200, gesendet 0, Ursache abgelaufen, klare Meldung',
    $rs->status === 200 && ($rs->daten['gesendet'] ?? null) === 0 && ($rs->daten['sitzung'] ?? null) === 'abgelaufen'
    && ($rs->daten['grund'] ?? null) === wu_sitzung_meldung('abgelaufen'));
pruefe('… die eigene Absage gehört zu „meinen Terminen“, obwohl die Buchung gelöscht ist; die fremde nicht',
    ($rs->daten['ids'] ?? null) === [$eigen, $alt]);
pruefe('… alle bleiben offen', $zeile($pdo, $eigen)['status'] === 'offen' && $zeile($pdo, $fremd)['status'] === 'offen');
$rf = route($zweig, $lk, 'POST', ['mitteilungen', 'senden'], ['ids' => [$fremd]]);
pruefe('nur fremde: 403', $rf->status === 403);
$rh = route($zweig, $lk, 'GET', ['mitteilungen', 'offen-eigene']);
$lkIds = (array)($rh->daten['ids'] ?? []);
pruefe('Hinweis nach der Anmeldung, Lehrkraft: nur die eigenen (keine fremde Absage)',
    $rh->status === 200 && in_array($eigen, $lkIds, true) && !in_array($fremd, $lkIds, true));
pruefe('Hinweis: nur Sprechtage ab heute (der vergangene fehlt)', !in_array($vorbei, $lkIds, true));
pruefe('Hinweis, Lehrkraft: genau die eine, Anzahl 1', $lkIds === [$eigen] && ($rh->daten['anzahl'] ?? null) === 1);
$rha = route($zweig, $ad, 'GET', ['mitteilungen', 'offen-eigene']);
pruefe('… Verwaltung: alle offenen ab heute', ($rha->daten['ids'] ?? null) === [$eigen, $fremd, $alt]);
pruefe('… ohne Personenangaben: nur Anzahl und Kennungen der Mitteilungen',
    array_keys($rha->daten) === ['anzahl', 'ids']);
$re = route($zweig, ['rolle' => 'eltern', 'kinder' => []], 'GET', ['mitteilungen', 'offen-eigene']);
pruefe('Eltern: 403', $re->status === 403);

// ------------------------------------------------------------
echo "Erinnerungen über die Sitzung der Verwaltung\n";
$pdo = neue_db();
$pdo->exec("INSERT INTO einstellungen VALUES ('erinnerung_liste_typ', 'QUICK'), ('erinnerung_liste_id', '230')");
$ev = erinnerung_versenden($pdo, $weg('abgelaufen', 'kein_token'));
pruefe('Versand bei abgelaufener Sitzung: nichts gesendet, Ursache und klare Meldung',
    $ev['gesendet'] === 0 && ($ev['sitzung'] ?? null) === 'abgelaufen' && $ev['grund'] === wu_sitzung_meldung('abgelaufen'));
$ee = erinnerung_empfaenger_ermitteln($pdo, $weg('nicht_erreichbar', 'nicht_erreichbar: Status 0'));
pruefe('Vorschau bei nicht erreichbarem WebUntis: eigene Ursache',
    $ee['ok'] === false && ($ee['sitzung'] ?? null) === 'nicht_erreichbar');

// ------------------------------------------------------------
echo "Aufrufstellen (Quelltext – Rückfall, wo die Route nicht ausführbar ist)\n";
$code = '';
foreach (token_get_all((string)file_get_contents(__DIR__ . '/../backend/api/buchungen.php')) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
    $code .= is_array($t) ? $t[1] : $t;
}
// Ausschnitt bis zur nächsten Route, nicht eine feste Länge (die erste Fassung
// mit 6000 Zeichen schnitt die Bestätigung ab).
$ausschnitt = function (string $von, string $bis) use ($code): string {
    $a = strpos($code, $von);
    if ($a === false) return '';
    $b = strpos($code, $bis, $a + strlen($von));
    return $b === false ? '' : substr($code, $a, $b - $a);
};
$sv = $ausschnitt("=== 'stellvertretend')", "if (\$methode === 'POST' && !isset(\$seg[1]))");
pruefe('Voraussetzung: Ausschnitt stellvertretend gefunden', $sv !== '');
$p1 = strpos($sv, 'json_sitzung_fehlt($sz)');
$p2 = strpos($sv, 'mit_eltern_ids_ermitteln($pdo, $kind, $sz[\'rest\'])');
$p3 = strpos($sv, 'INSERT INTO buchungen');
pruefe('stellvertretend: ohne nutzbare Sitzung Abbruch VOR der Suche und VOR der Buchung (Reihenfolge)',
    $p1 !== false && $p2 !== false && $p3 !== false && $p1 < $p2 && $p2 < $p3);
pruefe('stellvertretend: Bestätigung über PARENTS an alle', (bool)preg_match(
    "/mit_einreihen_und_senden\(\\\$pdo, \\\$sid, 0, 'bestaetigung',[^;]*\\\$sz, 'eltern',/s", $sv));   // v0.9.76: dahinter Name und Klasse
$ei = $ausschnitt("=== 'einladungen')", "if (\$methode === 'DELETE' && isset(\$seg[1])");
pruefe('Voraussetzung: Ausschnitt Einladung gefunden', $ei !== '');
pruefe('Einladung: über PARENTS, ohne Elternkonten zu suchen', (bool)preg_match(
    "/mit_einreihen_und_senden\(\\\$pdo, \\\$sid, 0, 'einladung',[^;]*\\\$sz, 'eltern',/s", $ei)   // v0.9.76: Sitzung aus der Prüfung davor
    && !str_contains($ei, 'mit_eltern_ids_ermitteln'));
// v0.9.73 (Betreiber): Absagen gehen über PARENTS an ALLE Erziehungsberechtigten
// – Stelle 3 (Absage) und Stelle 5 (Ausfall). Eine Entscheidung, eine Stelle:
// mit_absage_art(); eine Bestätigung nach einer Elternbuchung gibt es nicht mehr (v0.9.79).
pruefe('Absage: über die Sitzung der absagenden Person, mit der Lehrkraft der Buchung, an alle (mit_absage_art)',
    (bool)preg_match("/'absage', \\\$t\['betreff'\], \\\$t\['text'\],\s*\(int\)\\\$b\['schueler_id'\], \(int\)\\\$b\['lehrer_id'\], wu_sitzung\(\\\$cfg\),\s*mit_absage_art\(\(int\)\\\$b\['schueler_id'\]\),/", $code));
$ixCode = '';
foreach (token_get_all((string)file_get_contents(__DIR__ . '/../backend/api/index.php')) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
    $ixCode .= is_array($t) ? $t[1] : $t;
}
pruefe('Ausfall: jede Absage an alle Erziehungsberechtigten (mit_absage_art)', (bool)preg_match(
    "/mit_einreihen\(\\\$pdo, \\\$sid, \(int\)\\\$b\['eltern_user_id'\], 'absage',[^;]*\(int\)\\\$b\['schueler_id'\], \\\$lid,\s*mit_absage_art\(\(int\)\\\$b\['schueler_id'\]\),/s", $ixCode));
// v0.9.79 (E17-Nachtrag): Elternkonten dürfen nicht senden (403, gemessen);
// die Bestätigung nach einer Elternbuchung entfällt. Ausführlich in
// run_versandstellen.php; hier die alte Fassung ausdrücklich ausgeschlossen.
pruefe('keine Bestätigung nach Elternbuchung mehr (alte Fassung fehlt)', !preg_match(
    "/mit_einreihen_und_senden\(\\\$pdo, \\\$sid, \(int\)\\\$elternUserId,\s*'bestaetigung'/s", $code));

// ------------------------------------------------------------
echo "Migration sql/21\n";
$mig = (string)@file_get_contents(__DIR__ . '/../sql/21_dienstkonto_entfernen.sql');
pruefe('löscht beide hinterlegten Zugangsdaten', str_contains($mig, "DELETE FROM einstellungen")
    && str_contains($mig, "'dienstkonto_benutzer'") && str_contains($mig, "'dienstkonto_passwort'"));
pruefe('legt lehrer_id und empfaenger_art an, zweimal einspielbar (IF NOT EXISTS)',
    (bool)preg_match('/ADD COLUMN IF NOT EXISTS lehrer_id INT UNSIGNED NULL/', $mig)
    && (bool)preg_match("/ADD COLUMN IF NOT EXISTS empfaenger_art VARCHAR\(10\) NOT NULL DEFAULT 'konto'/", $mig));

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
