<?php
// ============================================================
// tests/run_mitteilungen_rechte.php – wem eine Mitteilung gehört (v0.9.102, E31)
// Aufruf: php tests/run_mitteilungen_rechte.php   → Exit-Code 0 = alles grün
//
// Anlass: Review aus koordination (BEFUND-2026-10-14-review-sprechtag-1.md,
// Commit a700ce2), Befunde 2 und 3, dort gemessen an bff6a89:
//   2 – GET /api/mitteilungen filterte nur nach Sprechtag und Status: Jede
//       Lehrkraft las alle Mitteilungen aller Lehrkräfte, mit Kindname,
//       Klasse, Betreff und Anlass.
//   3 – DELETE /api/mitteilungen/{id} verwarf ohne Eigentumsprüfung: Eine
//       offene Absage einer anderen Lehrkraft ginge nie an die Eltern.
// Eine Ursache: Die Frage „gehört die Mitteilung zu meinen Terminen?“
// stellte nur der Versand. Jetzt stellt sie eine Funktion
// (mit_gehoert_bedingung) für Liste, Verwerfen und Versand.
// Wer alles sieht und verwerfen darf: die Verwaltung (E31, benannt).
//
// Ausgeführt: der Zweig /api/mitteilungen aus index.php, wie in
// run_sitzung_versand.php, gegen SQLite. Kindnamen ERFUNDEN.
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

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE sprechtage (id INTEGER PRIMARY KEY, name TEXT, datum TEXT)');
$pdo->exec('CREATE TABLE mitteilungen (id INTEGER PRIMARY KEY AUTOINCREMENT,
  sprechtag_id INT, empfaenger_user_id INT NOT NULL, empfaenger_art TEXT NOT NULL DEFAULT "konto",
  schueler_id INT, lehrer_id INT, anlass TEXT, betreff TEXT, text TEXT,
  status TEXT DEFAULT "offen", grund TEXT DEFAULT "", versuche INT DEFAULT 0,
  angelegt_am TEXT DEFAULT CURRENT_TIMESTAMP, gesendet_am TEXT,
  kind_name TEXT NOT NULL DEFAULT "", kind_klasse TEXT NOT NULL DEFAULT "")');
$pdo->exec('CREATE TABLE buchungen (id INTEGER PRIMARY KEY, sprechtag_id INT, lehrer_id INT,
  eltern_user_id INT, schueler_id INT, slot_beginn TEXT)');
$pdo->exec("INSERT INTO sprechtage VALUES (1, 'Sprechtag', '2099-11-20')");
$GLOBALS['testPdo'] = $pdo;
$neu = function (int $empf, ?int $lehrer, string $kind) use ($pdo): int {
    $pdo->prepare("INSERT INTO mitteilungen (sprechtag_id, empfaenger_user_id, lehrer_id, anlass, betreff, text,
        kind_name, kind_klasse) VALUES (1, ?, ?, 'absage', 'Terminabsage', 'T', ?, '5x')")
        ->execute([$empf, $lehrer, $kind]);
    return (int)$pdo->lastInsertId();
};
$eigen    = $neu(7350, 7, 'Erfundenes Kind Sieben');
$fremd    = $neu(6000, 8, 'Erfundenes Kind Acht');
$alt      = $neu(6100, null, 'Erfundenes Kind Alt Sieben');   // vor v0.9.72: ohne Lehrkraft
$altFremd = $neu(6300, null, 'Erfundenes Kind Alt Acht');
$pdo->exec('INSERT INTO buchungen VALUES (1, 1, 7, 6100, 90003, "15:00:00"), (2, 1, 8, 6300, 90004, "15:10:00")');
$status = fn(int $id) => (string)$pdo->query('SELECT status FROM mitteilungen WHERE id = ' . $id)->fetchColumn();
$offen = fn() => $pdo->exec("UPDATE mitteilungen SET status = 'offen'");

$quelle = (string)file_get_contents(__DIR__ . '/../backend/api/index.php');
$kopf = "if ((\$seg[0] ?? '') === 'mitteilungen') {";
$zweig = '';
if (($a0 = strpos($quelle, $kopf)) !== false && strpos($quelle, $kopf, $a0 + 1) === false) {
    $tiefe = 0;
    foreach (array_slice(token_get_all('<?php ' . substr($quelle, $a0)), 1) as $t) {
        $txt = is_array($t) ? $t[1] : $t;
        $zweig .= $txt;
        if ($txt === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $tiefe++;
        if ($txt === '}') { $tiefe--; if ($tiefe === 0) break; }
    }
}
pruefe('Voraussetzung: Zweig /api/mitteilungen genau einmal gefunden', $zweig !== '');
function route(string $zweig, array $sitzung, string $methode, array $seg, array $b = [], array $get = []): Antwort
{
    $_SESSION = $sitzung; $body = $b; $_GET = $get;
    $cfg = ['webuntis' => ['base_url' => 'http://127.0.0.1:9', 'school' => 'x']];
    try { eval($zweig); } catch (Antwort $a) { return $a; }
    return new Antwort(0, []);
}
$lk7 = ['rolle' => 'lehrkraft', 'lehrer_id' => 7, 'name' => 'x'];
$lk8 = ['rolle' => 'lehrkraft', 'lehrer_id' => 8, 'name' => 'x'];
$lk0 = ['rolle' => 'lehrkraft', 'lehrer_id' => null, 'name' => 'x'];   // ohne Stammsatz
$ad  = ['rolle' => 'admin', 'lehrer_id' => null, 'name' => 'x'];
$ids = fn(Antwort $a) => array_map(fn($m) => (int)$m['id'], (array)($a->daten['mitteilungen'] ?? []));
$sortiert = function (array $l) { sort($l); return $l; };
$zeig = fn(Antwort $a) => $a->status . ' ' . substr(json_encode($a->daten, JSON_UNESCAPED_UNICODE), 0, 120);

// ------------------------------------------------------------
echo "Befund 2: die Liste – nur die eigenen\n";
$r = route($zweig, $lk7, 'GET', ['mitteilungen'], [], ['sprechtag' => '1']);
pruefe('Lehrkraft 7: die eigene und die alte mit eigener Buchung, keine fremde – ' . json_encode($ids($r)),
    $r->status === 200 && $sortiert($ids($r)) === $sortiert([$eigen, $alt]));
pruefe('… kein fremder Kindname in der Antwort, nirgends',
    $r->status === 200 && !str_contains(json_encode($r->daten, JSON_UNESCAPED_UNICODE), 'Kind Acht')
    && !str_contains(json_encode($r->daten, JSON_UNESCAPED_UNICODE), 'Alt Acht'));
$r = route($zweig, $lk8, 'GET', ['mitteilungen'], [], ['sprechtag' => '1']);
pruefe('Lehrkraft 8: spiegelbildlich ihre beiden – ' . json_encode($ids($r)),
    $r->status === 200 && $sortiert($ids($r)) === $sortiert([$fremd, $altFremd]));
$r = route($zweig, $lk7, 'GET', ['mitteilungen'], [], ['sprechtag' => '1', 'status' => 'offen']);
pruefe('mit Statusfilter „offen“: weiterhin nur die eigenen', $sortiert($ids($r)) === $sortiert([$eigen, $alt]));
$r = route($zweig, $lk0, 'GET', ['mitteilungen'], [], ['sprechtag' => '1']);
pruefe('Lehrkraft ohne Stammsatz: leere Liste, nicht alle – ' . $zeig($r), $r->status === 200 && $ids($r) === []);
$r = route($zweig, $ad, 'GET', ['mitteilungen'], [], ['sprechtag' => '1']);
pruefe('Verwaltung: alle vier (E31)', $sortiert($ids($r)) === $sortiert([$eigen, $fremd, $alt, $altFremd]));
$r = route($zweig, ['rolle' => 'eltern', 'kinder' => []], 'GET', ['mitteilungen'], [], ['sprechtag' => '1']);
pruefe('Gegenprobe Eltern: 403', $r->status === 403);

echo "Hinweis nach der Anmeldung: dieselben wie die Liste (Aufräumliste 17)\n";
foreach (['Lehrkraft 7' => $lk7, 'Lehrkraft 8' => $lk8, 'Verwaltung' => $ad] as $wer => $sitz) {
    $h = route($zweig, $sitz, 'GET', ['mitteilungen', 'offen-eigene']);
    $l = route($zweig, $sitz, 'GET', ['mitteilungen'], [], ['sprechtag' => '1', 'status' => 'offen']);
    pruefe($wer . ': Hinweis und Liste der offenen nennen dieselben – ' . json_encode((array)($h->daten['ids'] ?? [])),
        $h->status === 200 && $l->status === 200 && count($ids($l)) > 0
        && $sortiert(array_map('intval', (array)($h->daten['ids'] ?? []))) === $sortiert($ids($l)));
}

echo "Befund 3: verwerfen – nur die eigenen\n";
$r = route($zweig, $lk7, 'DELETE', ['mitteilungen', (string)$fremd]);
pruefe('Lehrkraft 7 verwirft die fremde: 403, sie bleibt offen – ' . $zeig($r),
    $r->status === 403 && $status($fremd) === 'offen');
$r = route($zweig, $lk7, 'DELETE', ['mitteilungen', (string)$altFremd]);
pruefe('… die alte fremde (Buchung bei Lehrkraft 8): 403, bleibt offen',
    $r->status === 403 && $status($altFremd) === 'offen');
$r = route($zweig, $lk0, 'DELETE', ['mitteilungen', (string)$eigen]);
pruefe('Lehrkraft ohne Stammsatz: 403, bleibt offen', $r->status === 403 && $status($eigen) === 'offen');
$r = route($zweig, $lk7, 'DELETE', ['mitteilungen', '9999']);
pruefe('unbekannte Kennung: 404 – ' . $zeig($r), $r->status === 404);
$r = route($zweig, $lk7, 'DELETE', ['mitteilungen', (string)$eigen]);
pruefe('Lehrkraft 7 verwirft die eigene: 200, verworfen', $r->status === 200 && $status($eigen) === 'verworfen');
$r = route($zweig, $lk7, 'DELETE', ['mitteilungen', (string)$alt]);
pruefe('… und die alte mit eigener Buchung: 200, verworfen', $r->status === 200 && $status($alt) === 'verworfen');
$offen();
$r = route($zweig, $ad, 'DELETE', ['mitteilungen', (string)$fremd]);
pruefe('Verwaltung verwirft jede: 200, verworfen (E31)', $r->status === 200 && $status($fremd) === 'verworfen');

echo "Eine Bedingung, eine Stelle (Quelltext)\n";
$ohne = function (string $q): string {
    $c = '';
    foreach (token_get_all($q) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $c .= is_array($t) ? $t[1] : $t;
    }
    return $c;
};
$z = $ohne('<?php ' . $zweig);
pruefe('Liste, Verwerfen und Versand fragen mit_gehoert_bedingung, dazu der Hinweis nach der Anmeldung (viermal im Zweig)',
    substr_count($z, 'mit_gehoert_bedingung(') === 4 && !str_contains($z, "' AND m.lehrer_id = ?'"));
$mi = $ohne((string)file_get_contents(__DIR__ . '/../backend/api/mitteilungen.php'));
pruefe('die Unterabfrage über die Buchungen steht nur in mitteilungen.php, nicht mehr im Zweig',
    !str_contains($z, 'b.eltern_user_id = m.empfaenger_user_id')
    && substr_count($mi, 'b.eltern_user_id = m.empfaenger_user_id') === 1);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
