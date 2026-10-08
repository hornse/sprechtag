<?php
// ============================================================
// tests/run_einladung_kachel.php – Eingeladene Lehrkraft: Kachel UND Buchung
// Aufruf: php tests/run_einladung_kachel.php   → Exit-Code 0 = alles grün
//
// Anlass: docs/BEFUND-2026-10-08-einladungs-kachel.md. Eine Lehrkraft, die
// eingeladen hat, das Kind aber nicht unterrichtet, fehlte in den Kacheln
// (Ursache 1) UND wäre an darf_lehrkraft abgewiesen worden (Ursache 2).
// Deshalb prüft jeder Fall beide Wege: Kachel erscheint und Buchung geht
// durch – eine Prüfung nur der Kachel ließe Ursache 2 durch.
//
// Geprüft werden die echten Funktionen aus backend/api/buchungen.php und
// slots.php gegen SQLite. Das Schema folgt sql/02_sprechtag.sql und
// sql/04_klausuren.sql, gekürzt auf die gelesenen Spalten; die
// eindeutigen Schlüssel sind übernommen.
//
// Testdaten erfunden. Kennungen ohne Bezug zu echten Personen.
// ============================================================

declare(strict_types=1);

// buchungen.php ist eine Routendatei: ohne Methode und Pfad läuft keine Route.
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
foreach (['slot_nur_eingeladene', 'bu_eingeladen', 'bu_einladende_lehrer',
          'bu_buchbare_lehrer', 'bu_lehrer_erlaubt', 'slot_buchung_erlaubt'] as $f) {
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
$pdo->exec('CREATE TABLE lehrer (id INTEGER PRIMARY KEY, kuerzel TEXT NOT NULL,
    name TEXT NOT NULL DEFAULT "")');
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

// Sprechtag 1, Kind 500. Lehrkräfte:
//   1 Ei – hat eingeladen, unterrichtet nicht (der gemeldete Fall)
//   2 Un – unterrichtet, hat nicht eingeladen
//   3 So – Sonderrolle (alle Jahrgänge), hat nicht eingeladen
//   4 Bo – hat eingeladen UND unterrichtet
//   5 Ab – hat eingeladen, nimmt aber nicht teil (teilnahme = 0)
//   6 Fr – hat ein ANDERES Kind (501) eingeladen
//   7 Zw – hat Kind 500 eingeladen, aber für Sprechtag 2
$pdo->exec("INSERT INTO lehrer (id, kuerzel, name) VALUES
    (1,'Ei','Eins'), (2,'Un','Zwei'), (3,'So','Drei'), (4,'Bo','Vier'),
    (5,'Ab','Fünf'), (6,'Fr','Sechs'), (7,'Zw','Sieben')");
$pdo->exec("INSERT INTO raeume (id, kuerzel) VALUES (1,'A101')");
$pdo->exec("INSERT INTO sonderrollen (id, bezeichnung) VALUES (1,'Beratung')");
$pdo->exec("INSERT INTO sprechtag_lehrer (sprechtag_id, lehrer_id, raum_id, teilnahme) VALUES
    (1,1,1,1), (1,5,NULL,0)");
$pdo->exec("INSERT INTO sprechtag_sonderlehrer (sprechtag_id, lehrer_id, rolle_id, jahrgaenge)
    VALUES (1,3,1,'')");
$pdo->exec("INSERT INTO kind_lehrer_cache (sprechtag_id, schueler_id, lehrer_id, faecher, stunden)
    VALUES (1,500,2,'M',4), (1,500,4,'D',3)");
$pdo->exec("INSERT INTO einladungen (sprechtag_id, lehrer_id, schueler_id) VALUES
    (1,1,500), (1,4,500), (1,5,500), (1,6,501), (2,7,500)");

$ids = fn(array $zeilen): array => (function ($z) { sort($z); return $z; })(
    array_map('intval', array_column($zeilen, 'lehrer_id')));

/** Dieselbe Zusammensetzung wie die Buchungsroute (POST /api/buchungen). */
function buchbar(PDO $pdo, string $phase, string $rolle, int $lid): bool
{
    return slot_buchung_erlaubt([
        'phase'          => $phase,
        'rolle'          => $rolle,
        'eingeladen'     => bu_eingeladen($pdo, 1, 500, $lid),
        'darf_lehrkraft' => bu_lehrer_erlaubt($pdo, 1, 500, $lid, ''),
        'slot_frei'      => true,
        'slot_im_raster' => true,
        'anzahl_termine' => 0,
        'max_termine'    => 0,
    ])['ok'];
}

// ------------------------------------------------------------
echo "slot_nur_eingeladene – eine Stelle für die Phasenfrage\n";
pruefe('Phase 1, Eltern: nur Eingeladene', slot_nur_eingeladene('phase1', 'eltern') === true);
pruefe('Phase 1, volljährige Schüler: nur Eingeladene', slot_nur_eingeladene('phase1', 'schueler') === true);
pruefe('Phase 1, Verwaltung: nicht beschränkt', slot_nur_eingeladene('phase1', 'admin') === false);
pruefe('Phase 1, Lehrkraft: nicht beschränkt', slot_nur_eingeladene('phase1', 'lehrkraft') === false);
pruefe('Phase 2, Eltern: nicht beschränkt', slot_nur_eingeladene('phase2', 'eltern') === false);

// ------------------------------------------------------------
echo "bu_eingeladen – genau dieses Kind, diese Lehrkraft, dieser Sprechtag\n";
pruefe('eingeladen: Lehrkraft 1, Kind 500, Sprechtag 1', bu_eingeladen($pdo, 1, 500, 1) === true);
pruefe('nicht eingeladen: Lehrkraft 2', bu_eingeladen($pdo, 1, 500, 2) === false);
pruefe('anderes Kind zählt nicht (Lehrkraft 6 lud 501 ein)', bu_eingeladen($pdo, 1, 500, 6) === false);
pruefe('… für 501 dagegen schon', bu_eingeladen($pdo, 1, 501, 6) === true);
pruefe('anderer Sprechtag zählt nicht (Lehrkraft 7)', bu_eingeladen($pdo, 1, 500, 7) === false);
pruefe('… für Sprechtag 2 dagegen schon', bu_eingeladen($pdo, 2, 500, 7) === true);

// ------------------------------------------------------------
echo "bu_lehrer_erlaubt kennt Einladungen (Ursache 2)\n";
pruefe('eingeladene, nicht unterrichtende Lehrkraft ist erlaubt',
    bu_lehrer_erlaubt($pdo, 1, 500, 1, '') === true);
pruefe('Einladung für ein anderes Kind erlaubt nichts',
    bu_lehrer_erlaubt($pdo, 1, 500, 6, '') === false);
pruefe('Einladung für einen anderen Sprechtag erlaubt nichts',
    bu_lehrer_erlaubt($pdo, 1, 500, 7, '') === false);
pruefe('Unterrichtende bleiben erlaubt', bu_lehrer_erlaubt($pdo, 1, 500, 2, '') === true);
pruefe('Sonderrolle bleibt erlaubt', bu_lehrer_erlaubt($pdo, 1, 500, 3, '') === true);

// ------------------------------------------------------------
echo "Phase 1, Eltern: Kachel UND Buchung nur für Eingeladene\n";
$k1 = bu_buchbare_lehrer($pdo, 1, 500, 'phase1', 'eltern', '');
pruefe('Kacheln: genau die Eingeladenen, die teilnehmen (1, 4)', $ids($k1['eingeladen']) === [1, 4]);
pruefe('Kacheln: keine Unterrichtenden daneben', $k1['unterrichtend'] === []);
pruefe('Kacheln: keine Sonderrollen daneben', $k1['sonderlehrer'] === []);
pruefe('Antwort sagt, dass nur Eingeladene gelten', ($k1['nur_eingeladene'] ?? null) === true);
pruefe('Buchung: eingeladene, nicht unterrichtende Lehrkraft geht durch', buchbar($pdo, 'phase1', 'eltern', 1));
pruefe('Buchung: eingeladene, unterrichtende Lehrkraft geht durch', buchbar($pdo, 'phase1', 'eltern', 4));
pruefe('Buchung: Unterrichtende ohne Einladung wird abgewiesen', !buchbar($pdo, 'phase1', 'eltern', 2));
pruefe('Buchung: Sonderrolle ohne Einladung wird abgewiesen', !buchbar($pdo, 'phase1', 'eltern', 3));
pruefe('Buchung: Einladung für anderes Kind öffnet nichts', !buchbar($pdo, 'phase1', 'eltern', 6));

// ------------------------------------------------------------
echo "Phase 2, Eltern: Eingeladene bleiben sichtbar und buchbar\n";
$k2 = bu_buchbare_lehrer($pdo, 1, 500, 'phase2', 'eltern', '');
pruefe('Kacheln: Eingeladene (1, 4)', $ids($k2['eingeladen']) === [1, 4]);
pruefe('Kacheln: Unterrichtende ohne die schon Eingeladenen (2)', $ids($k2['unterrichtend']) === [2]);
pruefe('Kacheln: Sonderrolle (3)', $ids($k2['sonderlehrer']) === [3]);
$alle = array_merge($k2['eingeladen'], $k2['unterrichtend'], $k2['sonderlehrer']);
pruefe('jede Lehrkraft genau einmal', count($alle) === count(array_unique(array_column($alle, 'lehrer_id'))));
pruefe('Antwort sagt, dass nicht nur Eingeladene gelten', ($k2['nur_eingeladene'] ?? null) === false);
pruefe('Buchung: eingeladene, nicht unterrichtende Lehrkraft geht durch', buchbar($pdo, 'phase2', 'eltern', 1));
pruefe('Buchung: Unterrichtende geht durch', buchbar($pdo, 'phase2', 'eltern', 2));
pruefe('Buchung: Einladung für anderes Kind öffnet nichts', !buchbar($pdo, 'phase2', 'eltern', 6));

// ------------------------------------------------------------
echo "Kachelinhalt der Eingeladenen\n";
$z1 = array_values(array_filter($k2['eingeladen'], fn($z) => (int)$z['lehrer_id'] === 1))[0] ?? [];
$z4 = array_values(array_filter($k2['eingeladen'], fn($z) => (int)$z['lehrer_id'] === 4))[0] ?? [];
pruefe('Eingeladene tragen die Kennzeichnung', (int)($z1['eingeladen'] ?? 0) === 1 && (int)($z4['eingeladen'] ?? 0) === 1);
pruefe('Unterrichtende tragen sie nicht',
    array_filter($k2['unterrichtend'], fn($z) => (int)($z['eingeladen'] ?? 0) === 1) === []);
pruefe('Raum der Eingeladenen kommt mit', ($z1['raum_kuerzel'] ?? null) === 'A101');
pruefe('Fächer, wo die Eingeladene auch unterrichtet', ($z4['faecher'] ?? null) === 'D');
pruefe('Name kommt mit', ($z1['name'] ?? null) === 'Eins');

// ------------------------------------------------------------
echo "Phase 1, Verwaltung: unbeschränkt wie die Buchungsprüfung\n";
$ka = bu_buchbare_lehrer($pdo, 1, 500, 'phase1', 'admin', '');
pruefe('Verwaltung sieht auch Unterrichtende', $ids($ka['unterrichtend']) === [2]);

// ------------------------------------------------------------
echo "bu_einladende_lehrer – eine Abfrage für Kachel und Elternzweig\n";
$e = bu_einladende_lehrer($pdo, 1, [500, 501]);
pruefe('alle Einladungen beider Kinder (1, 4, 5, 6)', $ids($e) === [1, 4, 5, 6]);
pruefe('Kind-Kennung steht dabei',
    in_array(501, array_map('intval', array_column($e, 'schueler_id')), true));
pruefe('leere Kinderliste liefert nichts', bu_einladende_lehrer($pdo, 1, []) === []);

// ------------------------------------------------------------
echo "Aufrufstellen in buchungen.php (ohne Kommentare)\n";
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
$kachelRoute = $zwischen("=== 'buchbare-lehrer'", 'json_ok(');
pruefe('Kachel-Route ruft bu_buchbare_lehrer()',
    $kachelRoute !== '' && str_contains($kachelRoute, 'bu_buchbare_lehrer('));
$buchRoute = $zwischen('$pruefung = slot_buchung_erlaubt(', ']);');
$vorher = $zwischen("if (\$methode === 'POST' && !isset(\$seg[1]))", '$pruefung = slot_buchung_erlaubt(');
pruefe('Buchungsroute: eingeladen kommt aus bu_eingeladen()',
    $vorher !== '' && preg_match('/\$eingeladen\s*=\s*bu_eingeladen\(/', $vorher) === 1
    && str_contains($buchRoute, "'eingeladen'     => \$eingeladen"));
pruefe('Buchungsroute: darf_lehrkraft kommt aus bu_lehrer_erlaubt()',
    preg_match("/'darf_lehrkraft'\s*=>\s*bu_lehrer_erlaubt\(/", $buchRoute) === 1);
pruefe('Elternzweig von GET /api/einladungen nutzt dieselbe Abfrage',
    $zwischen("=== 'einladungen'", "if (\$methode === 'POST')") !== ''
    && str_contains($zwischen("\$kinder = array_column(\$u['kinder']", "if (\$methode === 'POST')"),
        'bu_einladende_lehrer('));
pruefe('keine zweite Einladungsabfrage nach Kind und Lehrkraft außerhalb bu_eingeladen()',
    preg_match_all('/FROM einladungen\s+WHERE sprechtag_id = \? AND lehrer_id = \? AND schueler_id = \?/',
        str_replace($zwischen('function bu_eingeladen(', "\n}\n"), '', $code)) === 0);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
