<?php
// ============================================================
// tests/run_schueler_gruppe.php – wer als Schüler:in selbst buchen darf
// Aufruf: php tests/run_schueler_gruppe.php   → Exit-Code 0 = alles grün
//
// v0.9.65 (E15): Volljährige Schüler buchen selbst, wenn ihre
// WebUntis-Benutzergruppe in der Liste der Schule steht. Die Gruppe kommt
// bei der Anmeldung aus /WebUntis/api/profile/general (data.profile.
// userGroup, Text). Gemessen am 09.10.2026 (Betreiber, alle drei Rollen):
//   Lehrkraft „Lehrkräfte“/2, Eltern „01_Eltern Attest“/12, Schülerin
//   „SuS über 18“ bzw. „SuS über 18 mit Atte“/5 – eine Person, ein Wert,
//   keine Kennung, und der Name ist in WebUntis auf 20 Zeichen gekürzt.
//
//   * Rolle vor Gruppe: Eltern tragen ebenfalls eine Gruppe – geprüft wird
//     sie NUR bei der Rolle schueler (personType 5).
//   * Beide Seiten werden auf 20 Zeichen verglichen: Wer „SuS über 18 mit
//     Attest“ einträgt, trifft „SuS über 18 mit Atte“.
//   * Nicht zugelassen: anmelden ja, buchen nein – mit Erklärung.
//   * Kachel und Buchungsrecht zusammen: Beide Routen fragen dieselbe
//     Funktion; die Zweige werden AUSGEFÜHRT (aus buchungen.php gelesen).
//
// Gruppennamen aus der Messung (von der Schule vergeben, keine
// Personenangaben); alles andere erfunden.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

// Ersatz für Ausgabe und Datenbankzugang – jede Antwort endet als Ausnahme.
final class Antwort extends Exception
{
    public function __construct(public int $status, public array $daten) { parent::__construct('antwort'); }
}
function json_ok(array $d, int $status = 200): never { throw new Antwort($status, $d); }
function json_err(string $m, int $status = 400): never { throw new Antwort($status, ['fehler' => $m]); }
$PDO = null;
function db(array $cfg): PDO { global $PDO; return $PDO; }

$methode = ''; $seg = []; $cfg = []; $body = [];
require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/api/auth.php';
require __DIR__ . '/../backend/api/einstellungen.php';
require __DIR__ . '/../backend/api/slots.php';
require __DIR__ . '/../backend/api/buchungen.php';

$fehlt = array_filter(['gruppe_normalisieren', 'gruppen_liste', 'bu_buchen_gesperrt', 'bu_sperre_text',
    'bu_zugelassene_gruppen'], fn($f) => !function_exists($f));
pruefe('Voraussetzung: Funktionen vorhanden', $fehlt === []);
if ($fehlt !== []) { echo '    (fehlen: ' . implode(', ', $fehlt) . ")\n\n$fehler ROT\n"; exit(1); }

// ------------------------------------------------------------
echo "20 Zeichen – wie WebUntis kürzt\n";
pruefe('„SuS über 18 mit Attest“ → „SuS über 18 mit Atte“ (ü zählt als ein Zeichen)',
    gruppe_normalisieren('SuS über 18 mit Attest') === 'SuS über 18 mit Atte');
pruefe('Grenze: genau 20 Zeichen bleiben, 21 werden gekürzt',
    gruppe_normalisieren('12345678901234567890') === '12345678901234567890'
    && gruppe_normalisieren('123456789012345678901') === '12345678901234567890');
pruefe('Leerraum außen zählt nicht', gruppe_normalisieren("  SuS über 18 \t") === 'SuS über 18');
$liste = gruppen_liste("SuS über 18\n\nSuS über 18 mit Attest\r\nSuS über 18 mit Atte\n  ");
pruefe('Liste: je Zeile eine Gruppe, leere Zeilen und nach dem Kürzen Doppelte entfallen',
    $liste === ['SuS über 18', 'SuS über 18 mit Atte']);

// ------------------------------------------------------------
echo "Rolle vor Gruppe\n";
$zug = gruppen_liste("SuS über 18\nSuS über 18 mit Attest");
$sch = fn(?string $g) => ['rolle' => 'schueler', 'wu_gruppe' => $g];
pruefe('Eltern mit eigener Gruppe („01_Eltern Attest“): nie geprüft, auch bei leerer Liste',
    bu_buchen_gesperrt(['rolle' => 'eltern', 'wu_gruppe' => '01_Eltern Attest'], $zug) === null
    && bu_buchen_gesperrt(['rolle' => 'eltern', 'wu_gruppe' => '01_Eltern Attest'], []) === null);
pruefe('Lehrkraft und Verwaltung: nie geprüft',
    bu_buchen_gesperrt(['rolle' => 'lehrkraft', 'wu_gruppe' => 'Lehrkräfte'], []) === null
    && bu_buchen_gesperrt(['rolle' => 'admin', 'wu_gruppe' => null], []) === null);
pruefe('Schüler:in in zugelassener Gruppe: darf buchen', bu_buchen_gesperrt($sch('SuS über 18'), $zug) === null);
pruefe('… auch in der gekürzt gelieferten zweiten Gruppe, eingetragen in voller Länge',
    bu_buchen_gesperrt($sch('SuS über 18 mit Atte'), $zug) === null);
pruefe('Schüler:in in anderer Gruppe: gesperrt (gruppe_nicht_zugelassen)',
    bu_buchen_gesperrt($sch('SuS Sek I'), $zug) === 'gruppe_nicht_zugelassen');
pruefe('Schüler:in ohne ermittelte Gruppe: gesperrt, eigener Grund (gruppe_unbekannt)',
    bu_buchen_gesperrt($sch(null), $zug) === 'gruppe_unbekannt'
    && bu_buchen_gesperrt(['rolle' => 'schueler'], $zug) === 'gruppe_unbekannt');
pruefe('leere Liste: Schüler:innen gesperrt (schließt, statt alle durchzulassen)',
    bu_buchen_gesperrt($sch('SuS über 18'), []) === 'gruppe_nicht_zugelassen');
$t1 = bu_sperre_text('gruppe_nicht_zugelassen'); $t2 = bu_sperre_text('gruppe_unbekannt');
pruefe('Erklärung: „Termine buchen die Erziehungsberechtigten“, für beide Gründe verschieden',
    str_contains($t1, 'Termine buchen die Erziehungsberechtigten')
    && str_contains($t2, 'Termine buchen die Erziehungsberechtigten') && $t1 !== $t2);

// ------------------------------------------------------------
// Die Zweige der beiden Routen aus buchungen.php, roh bis zur passenden
// Klammer, ausgeführt mit echter auth.php (Sitzung gesetzt) und SQLite.
function zweig(string $quelle, string $kopf): string
{
    $a = strpos($quelle, $kopf);
    if ($a === false) return '';
    $toks = token_get_all('<?php ' . substr($quelle, $a));
    $tiefe = 0; $teil = '';
    foreach (array_slice($toks, 1) as $t) {
        $txt = is_array($t) ? $t[1] : $t;
        $teil .= $txt;
        if ($txt === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $tiefe++;
        if ($txt === '}') { $tiefe--; if ($tiefe === 0) break; }
    }
    return $teil;
}
$quelle = (string)file_get_contents(__DIR__ . '/../backend/api/buchungen.php');
$zKachel = zweig($quelle, "if (\$methode === 'GET' && (\$seg[0] ?? '') === 'buchbare-lehrer') {");
$zBuchen = zweig($quelle, "if ((\$seg[0] ?? '') === 'buchungen') {");
pruefe('Voraussetzung: beide Routen-Zweige gelesen', $zKachel !== '' && $zBuchen !== '');

// SQLite kennt MariaDBs „ON DUPLICATE KEY UPDATE“ nicht. Übersetzt wird
// GENAU die Klausel aus marke_schreiben() – ändert sie sich, greift die
// Übersetzung nicht, und die Abfrage scheitert laut statt still.
final class SqliteMitUpsert extends PDO
{
    public function prepare(string $sql, array $optionen = []): PDOStatement|false
    {
        return parent::prepare(str_replace('ON DUPLICATE KEY UPDATE wert = VALUES(wert)',
            'ON CONFLICT(schluessel) DO UPDATE SET wert = excluded.wert', $sql), $optionen);
    }
}
$PDO = new SqliteMitUpsert('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$PDO->exec('CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)');
$PDO->exec("INSERT INTO einstellungen VALUES ('schueler_buchen_gruppen', 'SuS über 18\nSuS über 18 mit Atte')");
$PDO->exec("CREATE TABLE sprechtage (id INTEGER PRIMARY KEY, phase TEXT, name TEXT, datum TEXT)");
$PDO->exec("INSERT INTO sprechtage VALUES (7, 'phase2', 'Erfunden', '2026-11-12')");

/** Führt einen Zweig aus. „weiter“: Er lief über die Sperre hinaus (eine
 *  Datenbankausnahme an einer fehlenden Tabelle danach). */
function lauf(string $zweig, string $m, array $s, array $get, array $b, array $sitzung): array
{
    global $cfg;
    $_SESSION = $sitzung; $_GET = $get; $body = $b; $methode = $m; $seg = $s;
    try { eval($zweig); } catch (Antwort $a) { return ['antwort', $a->status, $a->daten]; }
    catch (PDOException $e) { return ['weiter', 0, []]; }
    return ['ende', 0, []];
}
$schueler = fn(?string $g) => ['rolle' => 'schueler', 'name' => '', 'person_id' => 4242, 'user_id' => 4243,
    'kinder' => [['id' => 4242, 'name' => '']], 'wu_gruppe' => $g];
$get = ['sprechtag' => 7, 'kind' => 4242];
$k1 = lauf($zKachel, 'GET', ['buchbare-lehrer'], $get, [], $schueler('SuS Sek I'));
pruefe('Kacheln, nicht zugelassen: Antwort ohne Lehrkräfte, mit Grund und Erklärung',
    $k1[0] === 'antwort' && $k1[1] === 200 && ($k1[2]['buchen_gesperrt'] ?? null) === 'gruppe_nicht_zugelassen'
    && str_contains((string)($k1[2]['hinweis'] ?? ''), 'Erziehungsberechtigten')
    && ($k1[2]['unterrichtend'] ?? null) === [] && ($k1[2]['weitere'] ?? null) === []
    && ($k1[2]['eingeladen'] ?? null) === [] && ($k1[2]['sonderlehrer'] ?? null) === []);
$k2 = lauf($zKachel, 'GET', ['buchbare-lehrer'], $get, [], $schueler('SuS über 18 mit Atte'));
pruefe('Kacheln, zugelassen: läuft über die Sperre hinaus', $k2[0] === 'weiter');
$b1 = lauf($zBuchen, 'POST', ['buchungen'], [],
    ['sprechtag_id' => 7, 'lehrer_id' => 1, 'schueler_id' => 4242, 'slot_beginn' => '15:00'], $schueler('SuS Sek I'));
pruefe('Buchen, nicht zugelassen: 403 mit der Erklärung',
    $b1[0] === 'antwort' && $b1[1] === 403 && str_contains((string)($b1[2]['fehler'] ?? ''), 'Termine buchen die Erziehungsberechtigten'));
$b2 = lauf($zBuchen, 'POST', ['buchungen'], [],
    ['sprechtag_id' => 7, 'lehrer_id' => 1, 'schueler_id' => 4242, 'slot_beginn' => '15:00'], $schueler('SuS über 18'));
pruefe('Buchen, zugelassen: läuft über die Sperre hinaus', $b2[0] === 'weiter');
$eltern = ['rolle' => 'eltern', 'name' => '', 'person_id' => 1, 'user_id' => 2,
    'kinder' => [['id' => 4242, 'name' => '']], 'wu_gruppe' => '01_Eltern Attest'];
$b3 = lauf($zBuchen, 'POST', ['buchungen'], [],
    ['sprechtag_id' => 7, 'lehrer_id' => 1, 'schueler_id' => 4242, 'slot_beginn' => '15:00'], $eltern);
pruefe('Buchen, Eltern mit Gruppe außerhalb der Liste: nicht gesperrt', $b3[0] === 'weiter');

// ------------------------------------------------------------
echo "Anmeldung: Gruppe lesen und in der Sitzung halten\n";
require_once __DIR__ . '/../backend/api/webuntis_adapter.php';
final class ErsatzRest
{
    public function __construct(private $antwort) {}
    public function get(string $pfad, array $query = []): array
    {
        if ($this->antwort instanceof Throwable) throw $this->antwort;
        return $pfad === '/WebUntis/api/profile/general' ? $this->antwort : ['status' => 404, 'json' => null];
    }
}
$prof = fn($g) => new ErsatzRest(['status' => 200, 'json' => ['data' => ['profile' => ['userGroup' => $g, 'userRoleId' => 5]]]]);
pruefe('profile/general: userGroup als Text, getrimmt',
    wu_profil_gruppe($prof(' SuS über 18 mit Atte ')) === 'SuS über 18 mit Atte');
pruefe('… leer, fehlend, kein Text oder Abruf mit Ausnahme: null (Anmeldung scheitert daran nicht)',
    wu_profil_gruppe($prof('')) === null && wu_profil_gruppe($prof(['x'])) === null
    && wu_profil_gruppe(new ErsatzRest(['status' => 200, 'json' => null])) === null
    && @wu_profil_gruppe(new ErsatzRest(new RuntimeException('weg'))) === null);
// wu_login() selbst braucht WebUntis; geprüft wird die Aufrufstelle
// (Rückfall „richtige Stelle“, nicht Wirkung): im Zweig mit gültigem
// Token, ohne Kommentare gelesen.
$code = '';
foreach (token_get_all((string)file_get_contents(__DIR__ . '/../backend/api/webuntis_adapter.php')) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
    $code .= is_array($t) ? $t[1] : $t;
}
$iLogin = strpos($code, 'function wu_login(');
$iToken = $iLogin === false ? false : strpos($code, 'if ($rest->tokenHolen()) {', $iLogin);
$iAufruf = $iLogin === false ? false : strpos($code, "\$ergebnis['wu_gruppe'] = wu_profil_gruppe(\$rest);", $iLogin);
$iRolle = $iLogin === false ? false : strpos($code, 'if ($personType === 2)', $iLogin);
pruefe('wu_login() liest die Gruppe im Zweig mit gültigem Token (Aufrufstelle)',
    $iToken !== false && $iAufruf !== false && $iAufruf > $iToken && $iRolle !== false && $iAufruf < $iRolle);

$_SESSION = [];
@auth_login_speichern(['rolle' => 'schueler', 'name' => '', 'kuerzel' => null, 'lehrer_id' => null,
    'user_id' => 1, 'person_id' => 2, 'kinder' => [], 'wu_gruppe' => 'SuS über 18']);
pruefe('Sitzung trägt die Gruppe, auth_user() gibt sie heraus',
    ($_SESSION['wu_gruppe'] ?? null) === 'SuS über 18' && (auth_user()['wu_gruppe'] ?? null) === 'SuS über 18');

// ------------------------------------------------------------
echo "Einstellung der Verwaltung (GET/POST /api/schueler-gruppen)\n";
$zRoute = zweig((string)file_get_contents(__DIR__ . '/../backend/api/index.php'),
    "if ((\$seg[0] ?? '') === 'schueler-gruppen') {");
$admin = ['rolle' => 'admin', 'name' => '', 'person_id' => -1, 'user_id' => 9, 'kinder' => [], 'wu_gruppe' => 'Lehrkräfte'];
$r0 = lauf($zRoute, 'GET', ['schueler-gruppen'], [], [], $schueler('SuS über 18'));
pruefe('nur die Verwaltung (Schüler:in: 403)', $zRoute !== '' && $r0[0] === 'antwort' && $r0[1] === 403);
$r1 = lauf($zRoute, 'GET', ['schueler-gruppen'], [], [], $admin);
pruefe('GET: gespeicherte Gruppen, die Gruppe des eigenen Kontos, Länge 20',
    $r1[1] === 200 && ($r1[2]['gruppen'] ?? null) === ['SuS über 18', 'SuS über 18 mit Atte']
    && ($r1[2]['eigene_gruppe'] ?? null) === 'Lehrkräfte' && ($r1[2]['laenge'] ?? null) === 20);
$r2 = lauf($zRoute, 'POST', ['schueler-gruppen'], [], ['gruppen' => "SuS über 18 mit Attest\nSuS über 18\n\nSuS über 18"], $admin);
$gespeichert = $PDO->query("SELECT wert FROM einstellungen WHERE schluessel = 'schueler_buchen_gruppen'")->fetchColumn();
pruefe('POST: gespeichert wird, was verglichen wird (gekürzt, ohne Doppelte)',
    $r2[1] === 200 && $gespeichert === "SuS über 18 mit Atte\nSuS über 18"
    && ($r2[2]['gruppen'] ?? null) === ['SuS über 18 mit Atte', 'SuS über 18']);
pruefe('… und die Antwort nennt, was gekürzt wurde',
    ($r2[2]['gekuerzt'] ?? null) === [['eingegeben' => 'SuS über 18 mit Attest', 'verglichen' => 'SuS über 18 mit Atte']]);
lauf($zRoute, 'POST', ['schueler-gruppen'], [], ['gruppen' => 'SuS über 18'], $admin);
$k3 = lauf($zKachel, 'GET', ['buchbare-lehrer'], $get, [], $schueler('SuS über 18 mit Atte'));
pruefe('die gespeicherte Liste wirkt: Gruppe entfernt → Kacheln gesperrt',
    $k3[0] === 'antwort' && ($k3[2]['buchen_gesperrt'] ?? null) === 'gruppe_nicht_zugelassen');

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
