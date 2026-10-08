<?php
// ============================================================
// tests/run_dreiteilung.php – Zug 3: Dreiteilung der buchbaren Lehrkräfte
// Aufruf: php tests/run_dreiteilung.php   → Exit-Code 0 = alles grün
//
// E10 (docs/ENTSCHEIDUNGEN.md) mit den Nachträgen vom 08.10.2026:
//   Phase 1: nur Eingeladene (unverändert, v0.9.53).
//   Ab Phase 2: 1. Eingeladene, 2. Unterrichtende UND Klassenleitung
//   (hervorgehoben), Sonderrollen sichtbar dahinter, 3. weitere
//   teilnehmende Lehrkräfte hinter einer Suche – buchbar.
//   Jede Lehrkraft genau einmal. Teilnehmend: alle außer teilnahme = 0.
//
// Kachel und Buchungsrecht werden zusammen geprüft: Wer in einer Liste
// steht, muss bei bu_lehrer_erlaubt() durchgehen (Ausführung, nicht Suche).
//
// Klassenleitung: timetable/filter?resourceType=CLASS über die Sitzung,
// klasseId aus pageconfig → class.id, classTeacher1/2.id →
// lehrer.webuntis_id (Befund pageconfig-Schülerliste, Abschnitt 11).
// Die Feldnamen sind belegt (Mitschnitt des Betreibers, Messung v0.9.56).
// Wie eine FEHLENDE Leitung in der Antwort aussieht, ist nicht belegt –
// gemessen ist nur „4 von 40 ohne classTeacher1“. Deshalb sind alle drei
// Ausprägungen geprüft: Schlüssel fehlt, null, Objekt ohne Kennung.
//
// Testdaten erfunden. Kennungen ohne Bezug zu echten Personen.
// ============================================================

declare(strict_types=1);

$methode = ''; $seg = []; $cfg = []; $body = [];
require __DIR__ . '/../backend/api/slots.php';
require __DIR__ . '/../backend/api/buchungen.php';

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

$fehlt = [];
foreach (['slot_alle_teilnehmenden_buchbar', 'bu_teilnehmend_sql', 'bu_teilnehmend',
          'bu_teilnehmende_lehrer', 'bu_buchbare_lehrer',
          'bu_lehrer_erlaubt', 'kl_klasse_des_kindes', 'kl_leitung_kennungen',
          'kl_lehrer_ids', 'kl_ermitteln', 'kl_aus_sitzung'] as $f) {
    if (!function_exists($f)) $fehlt[] = $f;
}
if ($fehlt !== []) {
    echo "  ✗ Voraussetzung: Funktionen fehlen: " . implode(', ', $fehlt) . "\n";
    echo "\n1 ROT\n";
    exit(1);
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
// Schema nach sql/02_sprechtag.sql, gekürzt auf die gelesenen Spalten;
// eindeutige Schlüssel übernommen.
$pdo->exec('CREATE TABLE lehrer (id INTEGER PRIMARY KEY, webuntis_id INT NOT NULL,
    kuerzel TEXT NOT NULL, name TEXT NOT NULL DEFAULT "", aktiv INT NOT NULL DEFAULT 1,
    UNIQUE (webuntis_id))');
$pdo->exec('CREATE TABLE raeume (id INTEGER PRIMARY KEY, kuerzel TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sonderrollen (id INTEGER PRIMARY KEY, bezeichnung TEXT NOT NULL,
    reihenfolge INT NOT NULL DEFAULT 100)');
$pdo->exec('CREATE TABLE sprechtag_lehrer (id INTEGER PRIMARY KEY, sprechtag_id INT NOT NULL,
    lehrer_id INT NOT NULL, anwesend_von TEXT NULL, anwesend_bis TEXT NULL,
    raum_id INT NULL, teilnahme INT NOT NULL DEFAULT 1,
    UNIQUE (sprechtag_id, lehrer_id))');
$pdo->exec('CREATE TABLE sprechtag_sonderlehrer (id INTEGER PRIMARY KEY,
    sprechtag_id INT NOT NULL, lehrer_id INT NOT NULL, rolle_id INT NOT NULL,
    jahrgaenge TEXT NOT NULL DEFAULT "", UNIQUE (sprechtag_id, lehrer_id, rolle_id))');
$pdo->exec('CREATE TABLE kind_lehrer_cache (id INTEGER PRIMARY KEY, sprechtag_id INT NOT NULL,
    schueler_id INT NOT NULL, lehrer_id INT NOT NULL, faecher TEXT NOT NULL DEFAULT "",
    stunden INT NOT NULL DEFAULT 0, klausuren INT NOT NULL DEFAULT 0,
    UNIQUE (sprechtag_id, schueler_id, lehrer_id))');
$pdo->exec('CREATE TABLE einladungen (id INTEGER PRIMARY KEY, sprechtag_id INT NOT NULL,
    lehrer_id INT NOT NULL, schueler_id INT NOT NULL, hinweis TEXT NOT NULL DEFAULT "",
    erledigt INT NOT NULL DEFAULT 0, angelegt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (sprechtag_id, lehrer_id, schueler_id))');

// Sprechtag 1, Kind 500. Lehrkräfte (id / WebUntis-Kennung = 100 + id):
//   1 Ei – hat eingeladen, unterrichtet nicht
//   2 Un – unterrichtet (4 Std.)
//   3 So – Sonderrolle (alle Jahrgänge)
//   4 Kl – Klassenleitung 1, unterrichtet NICHT (Teilzeit, Oberstufe)
//   5 Ku – Klassenleitung 2, unterrichtet (2 Std.)
//   6 We – weder noch, Zeile in sprechtag_lehrer mit teilnahme = 1
//   7 Wn – weder noch, KEINE Zeile in sprechtag_lehrer (gilt als teilnehmend)
//   8 Ab – teilnahme = 0; unterrichtet Kind 503
//   9 In – aktiv = 0 (ausgeschieden), keine Zeile in sprechtag_lehrer
$pdo->exec("INSERT INTO lehrer (id, webuntis_id, kuerzel, name, aktiv) VALUES
    (1,101,'Ei','Eins',1), (2,102,'Un','Zwei',1), (3,103,'So','Drei',1),
    (4,104,'Kl','Vier',1), (5,105,'Ku','Fünf',1), (6,106,'We','Sechs',1),
    (7,107,'Wn','Sieben',1), (8,108,'Ab','Acht',1), (9,109,'In','Neun',0)");
$pdo->exec("INSERT INTO raeume (id, kuerzel) VALUES (1,'A101'), (2,'B202')");
$pdo->exec("INSERT INTO sonderrollen (id, bezeichnung) VALUES (1,'Beratung')");
$pdo->exec("INSERT INTO sprechtag_lehrer (sprechtag_id, lehrer_id, raum_id, teilnahme) VALUES
    (1,1,1,1), (1,4,2,1), (1,6,NULL,1), (1,8,NULL,0)");
$pdo->exec("INSERT INTO sprechtag_sonderlehrer (sprechtag_id, lehrer_id, rolle_id, jahrgaenge)
    VALUES (1,3,1,'')");
$pdo->exec("INSERT INTO kind_lehrer_cache (sprechtag_id, schueler_id, lehrer_id, faecher, stunden)
    VALUES (1,500,2,'M',4), (1,500,5,'D',2), (1,503,8,'E',1)");
$pdo->exec("INSERT INTO einladungen (sprechtag_id, lehrer_id, schueler_id) VALUES
    (1,1,500), (1,1,501)");

$ids = fn(array $zeilen): array => array_map('intval', array_column($zeilen, 'lehrer_id'));
$sortiert = function (array $a): array { sort($a); return $a; };
$gruppen = fn(array $k): array => array_merge($k['eingeladen'], $k['unterrichtend'],
    $k['sonderlehrer'], $k['weitere'] ?? []);
$zeile = function (array $liste, int $lid): array {
    foreach ($liste as $z) if ((int)$z['lehrer_id'] === $lid) return $z;
    return [];
};

/** Dieselbe Zusammensetzung wie die Buchungsroute (POST /api/buchungen). */
function buchbar(PDO $pdo, string $phase, string $rolle, int $kind, int $lid): bool
{
    return slot_buchung_erlaubt([
        'phase'          => $phase,
        'rolle'          => $rolle,
        'eingeladen'     => bu_eingeladen($pdo, 1, $kind, $lid),
        'darf_lehrkraft' => bu_lehrer_erlaubt($pdo, 1, $kind, $lid, '', $phase),
        'slot_frei'      => true,
        'slot_im_raster' => true,
        'anzahl_termine' => 0,
        'max_termine'    => 0,
    ])['ok'];
}

// ------------------------------------------------------------
echo "slot_alle_teilnehmenden_buchbar – eine Stelle für die Phasenfrage\n";
pruefe('Phase 2: alle Teilnehmenden buchbar', slot_alle_teilnehmenden_buchbar('phase2') === true);
foreach (['phase1', 'vorbereitung', 'geschlossen', 'archiviert', ''] as $p) {
    pruefe("„{$p}“: nicht", slot_alle_teilnehmenden_buchbar($p) === false);
}

// ------------------------------------------------------------
echo "Teilnahme – eine Regel, in SQL und PHP gleich: alle außer teilnahme = 0\n";
foreach ([[null, true], [0, false], [1, true], ['0', false], ['1', true]] as [$w, $soll]) {
    $sql = (int)$pdo->query('SELECT CASE WHEN ' . bu_teilnehmend_sql('t') . ' THEN 1 ELSE 0 END
        FROM (SELECT ' . ($w === null ? 'NULL' : (int)$w) . ' AS teilnahme) t')->fetchColumn() === 1;
    $anz = var_export($w, true);
    pruefe("teilnahme {$anz}: " . ($soll ? 'teilnehmend' : 'nicht'), $sql === $soll && bu_teilnehmend($w) === $soll);
}

echo "bu_teilnehmende_lehrer – aktiv und nicht teilnahme = 0\n";
pruefe('alle Teilnehmenden (1–7), ohne teilnahme = 0 (8) und ohne Inaktive (9)',
    $sortiert($ids(bu_teilnehmende_lehrer($pdo, 1))) === [1, 2, 3, 4, 5, 6, 7]);
pruefe('keine Zeile in sprechtag_lehrer gilt als teilnehmend (7)',
    in_array(7, $ids(bu_teilnehmende_lehrer($pdo, 1)), true));
pruefe('eingeschränkt auf Kennungen: nur diese', $ids(bu_teilnehmende_lehrer($pdo, 1, [4, 8, 9])) === [4]);
pruefe('eingeschränkt auf leere Liste: nichts', bu_teilnehmende_lehrer($pdo, 1, []) === []);
pruefe('Raum kommt mit', ($zeile(bu_teilnehmende_lehrer($pdo, 1), 4)['raum_kuerzel'] ?? null) === 'B202');
pruefe('anderer Sprechtag: Zeile mit teilnahme = 0 gilt dort nicht',
    in_array(8, $ids(bu_teilnehmende_lehrer($pdo, 2)), true));

// ------------------------------------------------------------
echo "kl_klasse_des_kindes – klasseId aus pageconfig\n";
$pc = ['data' => ['elements' => [
    ['id' => 500, 'klasseId' => 70], ['id' => 501, 'klasseId' => 71],
    ['id' => 502], ['id' => 503, 'klasseId' => 0]]]];
pruefe('Kind mit Klasse', kl_klasse_des_kindes($pc, 500) === 70);
pruefe('zweites Kind', kl_klasse_des_kindes($pc, 501) === 71);
pruefe('Kind ohne klasseId (wie Kind 2 der Messung): 0', kl_klasse_des_kindes($pc, 502) === 0);
pruefe('klasseId 0: 0', kl_klasse_des_kindes($pc, 503) === 0);
pruefe('Kind nicht in der Liste: 0', kl_klasse_des_kindes($pc, 999) === 0);
pruefe('Liste unmittelbar unter data', kl_klasse_des_kindes(['data' => [['id' => 500, 'klasseId' => 70]]], 500) === 70);
pruefe('keine Liste: 0', kl_klasse_des_kindes(['fehler' => 'x'], 500) === 0 && kl_klasse_des_kindes(null, 500) === 0);

// ------------------------------------------------------------
echo "kl_leitung_kennungen – classTeacher1/2 der Klasse\n";
$lt = fn(int $id, string $k) => ['id' => $id, 'shortName' => $k, 'longName' => 'L', 'displayName' => 'D'];
$kl = fn(int $id) => ['id' => $id, 'shortName' => 'K' . $id, 'longName' => 'L', 'displayName' => 'D'];
$tf = ['classes' => [
    ['class' => $kl(70), 'classTeacher1' => $lt(104, 'Kl'), 'classTeacher2' => $lt(105, 'Ku')],
    ['class' => $kl(71), 'classTeacher1' => $lt(101, 'Ei')],                          // CT2 fehlt
    ['class' => $kl(72), 'classTeacher1' => null, 'classTeacher2' => $lt(102, 'Un')],  // CT1 null
    ['class' => $kl(73), 'classTeacher1' => [], 'classTeacher2' => ['id' => 0]],       // leer
    ['class' => $kl(74), 'classTeacher1' => $lt(104, 'Kl'), 'classTeacher2' => $lt(104, 'Kl')],
    ['class' => $kl(75)],                                                              // beide fehlen
]];
pruefe('beide Leitungen', kl_leitung_kennungen($tf, 70) === [104, 105]);
pruefe('classTeacher2 fehlt: nur die erste', kl_leitung_kennungen($tf, 71) === [101]);
pruefe('classTeacher1 null: nur die zweite', kl_leitung_kennungen($tf, 72) === [102]);
pruefe('leeres Objekt und Kennung 0: keine', kl_leitung_kennungen($tf, 73) === []);
pruefe('dieselbe Lehrkraft in beiden: einmal', kl_leitung_kennungen($tf, 74) === [104]);
pruefe('Klasse ohne beide Felder: keine', kl_leitung_kennungen($tf, 75) === []);
pruefe('Klasse nicht in der Liste: keine', kl_leitung_kennungen($tf, 99) === []);
pruefe('klasseId 0 (Kind ohne Klasse): keine', kl_leitung_kennungen($tf, 0) === []);
pruefe('Liste unter data.classes', kl_leitung_kennungen(['data' => $tf], 70) === [104, 105]);
pruefe('keine Liste: keine', kl_leitung_kennungen(['x' => 1], 70) === [] && kl_leitung_kennungen(null, 70) === []);

// ------------------------------------------------------------
echo "kl_lehrer_ids – WebUntis-Kennung → lehrer.id\n";
pruefe('zwei Kennungen', $sortiert(kl_lehrer_ids($pdo, [104, 105])) === [4, 5]);
pruefe('unbekannte Kennung fällt weg', kl_lehrer_ids($pdo, [104, 999]) === [4]);
pruefe('leer bleibt leer', kl_lehrer_ids($pdo, []) === []);
pruefe('Kennung 0 trifft nichts, auch wenn webuntis_id 0 im Bestand stünde',
    (function () use ($pdo) {
        $pdo->exec("INSERT INTO lehrer (id, webuntis_id, kuerzel, aktiv) VALUES (99, 0, 'Nu', 0)");
        $r = kl_lehrer_ids($pdo, [0]);
        $pdo->exec('DELETE FROM lehrer WHERE id = 99');
        return $r === [];
    })());

// ------------------------------------------------------------
echo "kl_ermitteln – zwei Abrufe über die Sitzung, wirft nie bei Betriebsfehlern\n";
final class ErsatzRest
{
    public array $anfragen = [];
    public function __construct(private array $antworten) {}
    public function get(string $pfad, array $query = []): array
    {
        $this->anfragen[] = ['pfad' => $pfad, 'query' => $query];
        $a = $this->antworten[$pfad] ?? ['status' => 404, 'json' => null];
        if ($a instanceof Closure) return $a();
        return $a;
    }
}
$PC = '/WebUntis/api/public/timetable/weekly/pageconfig';
$TF = '/WebUntis/api/rest/view/v1/timetable/filter';
$ok = new ErsatzRest([$PC => ['status' => 200, 'json' => $pc], $TF => ['status' => 200, 'json' => $tf]]);
$e = kl_ermitteln($ok, 500, '2026-10-08');
pruefe('Kind 500: Kennungen der Klassenleitung', $e['kennungen'] === [104, 105] && $e['grund'] === null);
pruefe('erster Abruf pageconfig?type=5', ($ok->anfragen[0]['pfad'] ?? '') === $PC
    && ($ok->anfragen[0]['query'] ?? []) === ['type' => 5]);
$q = $ok->anfragen[1]['query'] ?? [];
pruefe('zweiter Abruf timetable/filter mit resourceType=CLASS, STANDARD',
    ($ok->anfragen[1]['pfad'] ?? '') === $TF && ($q['resourceType'] ?? '') === 'CLASS'
    && ($q['timetableType'] ?? '') === 'STANDARD');
pruefe('Zeitraum wie gemessen: vier Wochen bis heute, JJJJ-MM-TT',
    ($q['start'] ?? '') === '2026-09-11' && ($q['end'] ?? '') === '2026-10-08');
$ohne = new ErsatzRest([$PC => ['status' => 200, 'json' => $pc], $TF => ['status' => 200, 'json' => $tf]]);
$e = kl_ermitteln($ohne, 502, '2026-10-08');
pruefe('Kind ohne Klasse: keine Kennungen, kein Fehler', $e['kennungen'] === [] && $e['grund'] === null);
pruefe('… und kein zweiter Abruf', count($ohne->anfragen) === 1);
$e = kl_ermitteln(new ErsatzRest([$PC => ['status' => 403, 'json' => null]]), 500, '2026-10-08');
pruefe('pageconfig 403: Grund nennt Abruf und Status',
    $e['kennungen'] === [] && str_contains((string)$e['grund'], 'pageconfig') && str_contains((string)$e['grund'], '403'));
$e = kl_ermitteln(new ErsatzRest([$PC => ['status' => 200, 'json' => ['x' => 1]]]), 500, '2026-10-08');
pruefe('pageconfig ohne Liste ist ein Fehler, nicht „keine Klasse“',
    $e['grund'] !== null && str_contains((string)$e['grund'], 'pageconfig'));
$e = kl_ermitteln(new ErsatzRest([$PC => ['status' => 200, 'json' => $pc], $TF => ['status' => 400, 'json' => null]]), 500, '2026-10-08');
pruefe('timetable/filter 400: Grund nennt Abruf und Status',
    str_contains((string)$e['grund'], 'timetable/filter') && str_contains((string)$e['grund'], '400'));
$e = kl_ermitteln(new ErsatzRest([$PC => ['status' => 200, 'json' => $pc], $TF => ['status' => 200, 'json' => ['y' => 1]]]), 500, '2026-10-08');
pruefe('timetable/filter ohne classes[] ist ein Fehler, nicht „keine Leitung“',
    $e['grund'] !== null && str_contains((string)$e['grund'], 'classes'));
$e = kl_ermitteln(new ErsatzRest([$PC => fn() => throw new RuntimeException('Netz')]), 500, '2026-10-08');
pruefe('Betriebsfehler (Exception) wird zum Grund, nicht zum Absturz',
    $e['kennungen'] === [] && str_contains((string)$e['grund'], 'RuntimeException'));
$durch = false;
try { kl_ermitteln(new ErsatzRest([$PC => fn() => throw new TypeError('Programmfehler')]), 500, '2026-10-08'); }
catch (TypeError $t) { $durch = true; }
pruefe('Programmfehler (Error) wird NICHT verschluckt (catch Exception, nicht Throwable)', $durch);

// ------------------------------------------------------------
echo "kl_aus_sitzung – merkt Erfolg in der Sitzung, Fehler nicht\n";
$_SESSION = [];
$rufe = 0;
$holen = function () use (&$rufe, $PC, $TF, $pc, $tf) {
    $rufe++;
    return ['rest' => new ErsatzRest([$PC => ['status' => 200, 'json' => $pc],
                                      $TF => ['status' => 200, 'json' => $tf]]), 'grund' => null];
};
pruefe('erster Aufruf ermittelt', kl_aus_sitzung(500, $holen) === [104, 105] && $rufe === 1);
pruefe('zweiter Aufruf kommt aus der Sitzung, ohne WebUntis', kl_aus_sitzung(500, $holen) === [104, 105] && $rufe === 1);
pruefe('Kind ohne Klasse wird ebenfalls gemerkt (Erfolg ohne Leitung)',
    kl_aus_sitzung(502, $holen) === [] && kl_aus_sitzung(502, $holen) === [] && $rufe === 2);
$_SESSION = [];
$rufe = 0;
$abgelaufen = function () use (&$rufe) { $rufe++; return ['rest' => null, 'grund' => 'kein_token']; };
pruefe('abgelaufene Sitzung: keine Leitung, kein Fehler', kl_aus_sitzung(500, $abgelaufen) === []);
pruefe('… und nicht gemerkt – der nächste Aufruf versucht es wieder',
    kl_aus_sitzung(500, $abgelaufen) === [] && $rufe === 2);
$_SESSION = [];
$kaputt = fn() => ['rest' => new ErsatzRest([$PC => ['status' => 500, 'json' => null]]), 'grund' => null];
kl_aus_sitzung(500, $kaputt);
pruefe('fehlgeschlagener Abruf wird nicht gemerkt', !isset($_SESSION['klassenleitung'][500]));
$_SESSION = [];

// ------------------------------------------------------------
echo "Phase 2, Eltern, Klassenleitung 4 und 5: Dreiteilung\n";
$k = bu_buchbare_lehrer($pdo, 1, 500, 'phase2', 'eltern', '', [4, 5]);
pruefe('1. Eingeladene (1)', $ids($k['eingeladen']) === [1]);
pruefe('2. Klassenleitung zuerst, dann Unterrichtende (4, 5, 2)', $ids($k['unterrichtend']) === [4, 5, 2]);
pruefe('   Sonderrolle sichtbar dahinter (3)', $ids($k['sonderlehrer']) === [3]);
pruefe('3. Weitere: teilnehmend, aktiv, sonst nirgends (6, 7)', $ids($k['weitere'] ?? []) === [6, 7]);
$alle = $gruppen($k);
pruefe('jede Lehrkraft genau einmal', count($alle) === count(array_unique($ids($alle))));
pruefe('teilnahme = 0 erscheint nirgends (8)', !in_array(8, $ids($alle), true));
pruefe('inaktive Lehrkraft erscheint nirgends (9)', !in_array(9, $ids($alle), true));
pruefe('unterrichtende Lehrkraft mit teilnahme = 0 erscheint nicht (Kind 503, Lehrkraft 8)',
    !in_array(8, $ids($gruppen(bu_buchbare_lehrer($pdo, 1, 503, 'phase2', 'eltern', ''))), true));
pruefe('Klassenleitung gekennzeichnet (4 und 5)',
    (int)($zeile($k['unterrichtend'], 4)['klassenleitung'] ?? 0) === 1
    && (int)($zeile($k['unterrichtend'], 5)['klassenleitung'] ?? 0) === 1);
pruefe('sonst niemand gekennzeichnet',
    count(array_filter($alle, fn($z) => (int)($z['klassenleitung'] ?? 0) === 1)) === 2);
pruefe('nicht unterrichtende Klassenleitung: Raum und Name kommen mit',
    ($zeile($k['unterrichtend'], 4)['raum_kuerzel'] ?? null) === 'B202'
    && ($zeile($k['unterrichtend'], 4)['name'] ?? null) === 'Vier');
pruefe('unterrichtende Klassenleitung behält ihr Fach',
    ($zeile($k['unterrichtend'], 5)['faecher'] ?? null) === 'D');
pruefe('Weitere tragen dieselben Kachelspalten',
    array_diff(['lehrer_id', 'kuerzel', 'name', 'faecher', 'stunden', 'klausuren',
                'anwesend_von', 'anwesend_bis', 'raum_kuerzel', 'rolle'],
               array_keys($zeile($k['weitere'] ?? [], 6))) === []);
pruefe('nicht nur Eingeladene', ($k['nur_eingeladene'] ?? null) === false);

echo "Phase 2: Kachel ⇒ Buchungsrecht, für jede gezeigte Lehrkraft\n";
$nichtErlaubt = array_filter($ids($alle), fn($lid) => !bu_lehrer_erlaubt($pdo, 1, 500, $lid, '', 'phase2'));
pruefe('jede gezeigte Lehrkraft ist erlaubt (auch Klassenleitung 4 und Weitere 6, 7)', $nichtErlaubt === []);
pruefe('Buchung bei weiterer Lehrkraft geht durch (6)', buchbar($pdo, 'phase2', 'eltern', 500, 6));
pruefe('Buchung bei weiterer Lehrkraft ohne Zeile geht durch (7)', buchbar($pdo, 'phase2', 'eltern', 500, 7));
pruefe('Buchung bei nicht unterrichtender Klassenleitung geht durch (4)', buchbar($pdo, 'phase2', 'eltern', 500, 4));
pruefe('teilnahme = 0 bleibt abgewiesen (8)', !bu_lehrer_erlaubt($pdo, 1, 500, 8, '', 'phase2'));
pruefe('inaktive Lehrkraft bleibt abgewiesen (9)', !bu_lehrer_erlaubt($pdo, 1, 500, 9, '', 'phase2'));

// ------------------------------------------------------------
echo "Phase 2 ohne Klassenleitung: keine Hervorhebung, kein Fehler\n";
$k0 = bu_buchbare_lehrer($pdo, 1, 500, 'phase2', 'eltern', '');
pruefe('Unterrichtende nach Stunden (2, 5)', $ids($k0['unterrichtend']) === [2, 5]);
pruefe('nicht unterrichtende Klassenleitung steht dann bei den Weiteren (4, 6, 7)',
    $ids($k0['weitere'] ?? []) === [4, 6, 7]);
pruefe('niemand gekennzeichnet',
    array_filter($gruppen($k0), fn($z) => (int)($z['klassenleitung'] ?? 0) === 1) === []);
$kx = bu_buchbare_lehrer($pdo, 1, 500, 'phase2', 'eltern', '', [999]);
pruefe('unbekannte lehrer.id als Klassenleitung: wie ohne', $ids($kx['unterrichtend']) === [2, 5]
    && array_filter($gruppen($kx), fn($z) => (int)($z['klassenleitung'] ?? 0) === 1) === []);

// ------------------------------------------------------------
echo "Klassenleitung in Sonderfällen\n";
$ke = bu_buchbare_lehrer($pdo, 1, 501, 'phase2', 'eltern', '', [1]);
pruefe('eingeladene Klassenleitung: nur bei den Eingeladenen, dort gekennzeichnet',
    $ids($ke['eingeladen']) === [1] && (int)($zeile($ke['eingeladen'], 1)['klassenleitung'] ?? 0) === 1
    && !in_array(1, $ids(array_merge($ke['unterrichtend'], $ke['weitere'] ?? [])), true));
$ka = bu_buchbare_lehrer($pdo, 1, 500, 'phase2', 'eltern', '', [8]);
pruefe('Klassenleitung mit teilnahme = 0 erscheint nirgends', !in_array(8, $ids($gruppen($ka)), true));
$ki = bu_buchbare_lehrer($pdo, 1, 500, 'phase2', 'eltern', '', [9]);
pruefe('inaktive Klassenleitung erscheint nirgends', !in_array(9, $ids($gruppen($ki)), true));
$ks = bu_buchbare_lehrer($pdo, 1, 500, 'phase2', 'eltern', '', [3]);
pruefe('Klassenleitung mit Sonderrolle: in Gruppe 2, nicht doppelt',
    in_array(3, $ids($ks['unterrichtend']), true) && $ks['sonderlehrer'] === []);

// ------------------------------------------------------------
echo "Phase 1 und Vorbereitung: keine Weiteren, keine fremde Klassenleitung\n";
$k1 = bu_buchbare_lehrer($pdo, 1, 500, 'phase1', 'eltern', '', [4, 5]);
pruefe('Phase 1, Eltern: nur Eingeladene (1)', $ids($gruppen($k1)) === [1]);
pruefe('Phase 1, Eltern: Weitere leer', ($k1['weitere'] ?? null) === []);
pruefe('Phase 1, Eltern: Buchung bei Weiteren abgewiesen (6)', !buchbar($pdo, 'phase1', 'eltern', 500, 6));
pruefe('Phase 1, Eltern: Buchung bei Klassenleitung ohne Einladung abgewiesen (4)',
    !buchbar($pdo, 'phase1', 'eltern', 500, 4));
pruefe('Phase 1: bu_lehrer_erlaubt öffnet Weitere nicht', !bu_lehrer_erlaubt($pdo, 1, 500, 6, '', 'phase1'));
pruefe('ohne Phase: bu_lehrer_erlaubt wie bisher', !bu_lehrer_erlaubt($pdo, 1, 500, 6, ''));
$kv = bu_buchbare_lehrer($pdo, 1, 500, 'vorbereitung', 'eltern', '', [4, 5]);
pruefe('Vorbereitung: Weitere leer', ($kv['weitere'] ?? null) === []);
pruefe('Vorbereitung: nicht unterrichtende Klassenleitung fehlt (sonst Kachel ohne Recht)',
    !in_array(4, $ids($gruppen($kv)), true));
pruefe('Vorbereitung: jede gezeigte Lehrkraft ist erlaubt (Phase vorbereitung)',
    array_filter($ids($gruppen($kv)), fn($lid) => !bu_lehrer_erlaubt($pdo, 1, 500, $lid, '', 'vorbereitung')) === []);
$k1a = bu_buchbare_lehrer($pdo, 1, 500, 'phase1', 'admin', '', []);
pruefe('Phase 1, Verwaltung: jede gezeigte Lehrkraft ist erlaubt (Phase 1)',
    array_filter($ids($gruppen($k1a)), fn($lid) => !bu_lehrer_erlaubt($pdo, 1, 500, $lid, '', 'phase1')) === []);

// ------------------------------------------------------------
echo "Aufrufstellen (ohne Kommentare; Stufe „richtige Stelle“, nicht Wirkung)\n";
$code = '';
foreach (token_get_all((string)file_get_contents(__DIR__ . '/../backend/api/buchungen.php')) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
    $code .= is_array($t) ? $t[1] : $t;
}
$zwischen = function (string $von, string $bis) use ($code): string {
    $a = strpos($code, $von);
    if ($a === false) return '';
    $b = strpos($code, $bis, $a + strlen($von));
    return $b === false ? '' : substr($code, $a, $b - $a);
};
$route = $zwischen("=== 'buchbare-lehrer'", 'json_ok(');
pruefe('Kachel-Route holt die Klassenleitung über kl_aus_sitzung() und kl_lehrer_ids()',
    preg_match('/kl_lehrer_ids\(\$pdo,\s*kl_aus_sitzung\(\$kind,/', $route) === 1);
pruefe('… nur für Eltern und nur, wenn alle Teilnehmenden buchbar sind',
    preg_match("/if \(\\\$u\['rolle'\] === 'eltern'\s*&& slot_alle_teilnehmenden_buchbar\(/", $route) === 1);
pruefe('… über die Sitzung der angemeldeten Person (mit_rest_aus_sitzung)',
    str_contains($route, 'mit_rest_aus_sitzung($cfg,'));
pruefe('… und reicht sie an bu_buchbare_lehrer() weiter',
    preg_match('/bu_buchbare_lehrer\(\$pdo, \$sid, \$kind,[^;]*\$klassenleitung\)/s', $route) === 1);
$buch = $zwischen('$pruefung = slot_buchung_erlaubt(', ']);');
pruefe('Buchungsroute gibt die Phase an bu_lehrer_erlaubt()',
    preg_match("/'darf_lehrkraft'\s*=>\s*bu_lehrer_erlaubt\([^;]*\(string\)\\\$s\['phase'\]\)/s", $buch) === 1);
$erlaubt = $zwischen('function bu_lehrer_erlaubt(', "\n}\n");
$kachel  = $zwischen('function bu_buchbare_lehrer(', "\n}\n");
pruefe('eine Quelle für „teilnehmend“: bu_lehrer_erlaubt() fragt bu_teilnehmende_lehrer()',
    str_contains($erlaubt, 'bu_teilnehmende_lehrer('));
pruefe('… und bu_buchbare_lehrer() ebenso', str_contains($kachel, 'bu_teilnehmende_lehrer('));
pruefe('keine zweite Teilnahmeregel in bu_buchbare_lehrer() (SQL- oder PHP-Vergleich auf teilnahme)',
    $kachel !== '' && preg_match('/teilnahme\s*(=|<>|!=|IS)|teilnahme\'\]\s*(===|!==|==|!=)/', $kachel) === 0);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
