<?php
// ============================================================
// tests/run_messung_parents.php – Messung recipientOption PARENTS (v0.9.69)
// Aufruf: php tests/run_messung_parents.php   → Exit-Code 0 = alles grün
//
// MESSUNG, KEIN FEATURE. Frage: Erreicht recipientOption „PARENTS“ mit der
// Kennung des Kindes die Erziehungsberechtigten auch auf unserem Pfad – auf
// /v2/messages/users (heute im Betrieb) bzw. /v2/messages (lernzeiten)?
//
// Die Messung VERSCHICKT eine echte Nachricht. Geprüft wird deshalb vor
// allem, dass sie das nur tut, wenn sie soll: genau einmal, nur bestätigt,
// nur an einen angegebenen Pfad, nur mit dem festen Testbetreff – und dass
// die Antwort keine Personenangaben trägt.
//
// Körper nach der Beilage docs/beilagen/lernzeiten-mitteilung-parents.md
// (dort gemessen). Antworten hier ERFUNDEN in der dort belegten Form
// (numberOfRecipients / numberOfCCRecipients).
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

// Ersatz für Ausgabe und Datenbank (für die ausgeführte Route unten).
final class Antwort extends Exception
{
    public function __construct(public int $status, public array $daten) { parent::__construct('antwort'); }
}
function json_ok(array $d, int $s = 200): never { throw new Antwort($s, $d); }
function json_err(string $m, int $s = 400): never { throw new Antwort($s, ['fehler' => $m]); }
function db(array $cfg): PDO { return new PDO('sqlite::memory:'); }

require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/auth/WebUntisRest.php';
require __DIR__ . '/../backend/auth/extractors.php';
require __DIR__ . '/../backend/api/auth.php';
require __DIR__ . '/../backend/api/mitteilungen.php';
require __DIR__ . '/../backend/api/messung_sitzung.php';

$fehlt = array_filter(['messung_parents_koerper', 'messung_parents_deuten', 'messung_parents_ausfuehren'],
    fn($f) => !function_exists($f));
pruefe('Voraussetzung: Funktionen vorhanden', $fehlt === []);
if ($fehlt !== []) { echo '    (fehlen: ' . implode(', ', $fehlt) . ")\n\n$fehler ROT\n"; exit(1); }

// ------------------------------------------------------------
echo "Der Körper – wie in lernzeiten gemessen\n";
$k = messung_parents_koerper(90042);
pruefe('Felder und Reihenfolge wie die Beilage (subject … forbidReply)',
    array_keys($k) === ['subject', 'content', 'recipientOption', 'recipientPersonIds', 'recipientGroupIds',
        'copyToStudent', 'requestConfirmation', 'oneDriveAttachments', 'forbidReply']);
pruefe('PARENTS, die Kennung des Kindes, ohne Kopie an das Kind, ohne Lesebestätigung',
    $k['recipientOption'] === 'PARENTS' && $k['recipientPersonIds'] === [90042] && $k['recipientGroupIds'] === []
    && $k['copyToStudent'] === false && $k['requestConfirmation'] === false);
pruefe('fester Testbetreff, als Messung erkennbar', str_contains($k['subject'], 'Testnachricht')
    && str_contains($k['subject'], 'bitte ignorieren'));

// ------------------------------------------------------------
echo "Die Antwort deuten\n";
$d = fn(int $st, $json) => messung_parents_deuten(['status' => $st, 'json' => $json, 'text' => '']);
$e2 = $d(200, ['numberOfRecipients' => 2, 'numberOfCCRecipients' => null]);
pruefe('200 mit numberOfRecipients 2: erreicht 2', $e2['ergebnis'] === 'erreicht' && $e2['empfaenger'] === 2);
$e0 = $d(200, ['numberOfRecipients' => 0]);
$eu = $d(200, ['irgendwas' => true]);
pruefe('200 mit 0: angenommen, niemand erreicht; 200 ohne Zahl: unklar – nicht „erreicht“',
    $e0['ergebnis'] === 'niemand' && $eu['ergebnis'] === 'unklar');
$e4 = $d(400, ['errorMessage' => 'unbekanntes Feld', 'validationErrors' => [['path' => 'recipientOption', 'errorMessage' => 'x']]]);
pruefe('400: abgelehnt, mit Meldung von WebUntis und den Pfaden der Prüffehler',
    $e4['ergebnis'] === 'abgelehnt' && str_contains($e4['meldung'], 'unbekanntes Feld')
    && in_array('recipientOption', $e4['pruef_pfade'], true));
$e3 = $d(403, null);
pruefe('403: keine Rechte (eigenes Ergebnis)', $e3['ergebnis'] === 'keine_rechte');

// ------------------------------------------------------------
echo "Ausführen – nur, wenn es soll\n";
final class ErsatzRest
{
    public array $posts = [];
    public function __construct(private array $antwort) {}
    public function postMultipart(string $pfad, array $daten, string $feld = 'request'): array
    { $this->posts[] = [$pfad, $daten, $feld]; return $this->antwort; }
    public function post(string $pfad, array $daten): array
    { $this->posts[] = [$pfad, $daten, 'json']; return $this->antwort; }
}
$ok = ['status' => 200, 'json' => ['numberOfRecipients' => 4, 'numberOfCCRecipients' => null], 'text' => ''];
$lauf = function (array $eingabe, ?ErsatzRest $rest, array $namensweg = ['ids' => [7, 8], 'quelle' => 'webuntis', 'kind_name' => 'Erfunden Kind'],
                  string $rolle = 'admin', ?ErsatzRest $dk = null, ?int &$abgemeldet = null)
{
    $abgemeldet = 0;
    return messung_parents_ausfuehren($eingabe, $rolle,
        fn() => ['rest' => $rest, 'grund' => $rest === null ? 'kein_cookie' : null],
        function () use ($dk, &$abgemeldet) { return ['rest' => $dk, 'grund' => $dk === null ? 'kein_dienstkonto' : null,
            'abmelden' => function () use (&$abgemeldet) { $abgemeldet++; }]; },
        fn(int $kind) => $namensweg, fn(int $kind) => true);
};
$r1 = new ErsatzRest($ok);
$a1 = $lauf(['kind_id' => 90042, 'pfad' => 'users', 'bestaetigt' => true], $r1);
pruefe('Pfad users: genau EIN Versand, multipart an /v2/messages/users',
    count($r1->posts) === 1 && $r1->posts[0][0] === '/WebUntis/api/rest/view/v2/messages/users'
    && $r1->posts[0][2] === 'request' && ($r1->posts[0][1]['recipientOption'] ?? null) === 'PARENTS');
$r2 = new ErsatzRest($ok);
$lauf(['kind_id' => 90042, 'pfad' => 'messages', 'bestaetigt' => true], $r2);
pruefe('Pfad messages: genau EIN Versand an /v2/messages',
    count($r2->posts) === 1 && $r2->posts[0][0] === '/WebUntis/api/rest/view/v2/messages');
$r3 = new ErsatzRest($ok);
$a3 = $lauf(['kind_id' => 90042, 'pfad' => 'users'], $r3);
$a4 = $lauf(['kind_id' => 90042, 'pfad' => 'beides', 'bestaetigt' => true], $r3);
$a5 = $lauf(['kind_id' => 0, 'pfad' => 'users', 'bestaetigt' => true], $r3);
pruefe('ohne Bestätigung, mit unbekanntem Pfad, ohne Kind-Kennung: KEIN Versand, Grund genannt',
    count($r3->posts) === 0 && ($a3['gesendet'] ?? null) === false && ($a4['gesendet'] ?? null) === false
    && ($a5['gesendet'] ?? null) === false && ($a3['grund'] ?? '') !== '' && ($a4['grund'] ?? '') !== '');
$a6 = $lauf(['kind_id' => 90042, 'pfad' => 'users', 'bestaetigt' => true], null);
pruefe('ohne nutzbare Sitzung: kein Versand, Grund aus der Sitzung', ($a6['gesendet'] ?? null) === false
    && str_contains((string)($a6['grund'] ?? ''), 'kein_cookie'));
pruefe('Ergebnis: Empfänger von WebUntis, daneben die Zahl des Namenswegs und ob die Kennung in der Schülerliste steht',
    ($a1['antwort']['empfaenger'] ?? null) === 4 && ($a1['namensweg']['konten'] ?? null) === 2
    && ($a1['namensweg']['quelle'] ?? null) === 'webuntis' && ($a1['kennung_in_schuelerliste'] ?? null) === true);
$t1 = json_encode($a1, JSON_UNESCAPED_UNICODE);
pruefe('keine Personenangaben: weder Kindname noch Eltern-Kennungen noch die Kind-Kennung',
    !str_contains($t1, 'Erfunden Kind') && !str_contains($t1, '90042') && !preg_match('/"ids"/', $t1));

// ------------------------------------------------------------
echo "Die Route (aus index.php, ausgeführt)\n";
$quelle = (string)file_get_contents(__DIR__ . '/../backend/api/index.php');
$kopf = "if (\$methode === 'POST' && (\$seg[0] ?? '') === 'messung' && (\$seg[1] ?? '') === 'parents') {";
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
function route(string $zweig, array $sitzung, array $b): Antwort
{
    $_SESSION = $sitzung; $body = $b; $methode = 'POST'; $seg = ['messung', 'parents']; $cfg = [];
    try { eval($zweig); } catch (Antwort $a) { return $a; }
    return new Antwort(0, []);
}
$re = route($zweig, ['rolle' => 'eltern', 'kinder' => [], 'wu_cookie' => 'x'],
    ['kind_id' => 90042, 'pfad' => 'users', 'bestaetigt' => true]);
pruefe('Eltern können die Messung nicht auslösen (403)', $zweig !== '' && $re->status === 403);
$rl = route($zweig, ['rolle' => 'lehrkraft', 'kinder' => []], ['kind_id' => 90042, 'pfad' => 'users']);
pruefe('Lehrkraft ohne Bestätigung: Antwort 200, nicht gesendet, mit Grund',
    $rl->status === 200 && ($rl->daten['bericht']['gesendet'] ?? null) === false
    && str_contains((string)($rl->daten['bericht']['grund'] ?? ''), 'bestätigt'));

// ------------------------------------------------------------
// v0.9.70: über die Sitzung des DIENSTKONTOS. Bestätigungen und Absagen
// laufen heute notwendigerweise darüber (bei einer Buchung durch Eltern ist
// keine Lehrkraft angemeldet); PARENTS ist nur über die Lehrkraft-Sitzung
// gemessen (Befund Abschnitt 16).
echo "Über die Sitzung des Dienstkontos (v0.9.70)\n";
$eig = new ErsatzRest($ok); $dk = new ErsatzRest($ok); $ab = null;
$ad = $lauf(['kind_id' => 90042, 'pfad' => 'messages', 'bestaetigt' => true, 'sitzung' => 'dienstkonto'],
    $eig, ['ids' => [7, 8], 'quelle' => 'webuntis', 'kind_name' => 'x'], 'admin', $dk, $ab);
pruefe('sitzung dienstkonto: der Versand geht über die Dienstkonto-Sitzung, nicht über die eigene',
    count($dk->posts) === 1 && count($eig->posts) === 0 && ($ad['sitzung'] ?? null) === 'dienstkonto'
    && ($ad['antwort']['empfaenger'] ?? null) === 4);
pruefe('… und die Dienstkonto-Sitzung wird danach abgemeldet (genau einmal)', $ab === 1);
$eig2 = new ErsatzRest($ok); $dk2 = new ErsatzRest($ok); $ab2 = null;
$al = $lauf(['kind_id' => 90042, 'pfad' => 'messages', 'bestaetigt' => true, 'sitzung' => 'dienstkonto'],
    $eig2, ['ids' => [], 'quelle' => null, 'kind_name' => ''], 'lehrkraft', $dk2, $ab2);
pruefe('über das Dienstkonto nur für die Verwaltung: Lehrkraft – kein Versand, Grund genannt',
    count($dk2->posts) === 0 && count($eig2->posts) === 0 && ($al['gesendet'] ?? null) === false
    && str_contains((string)($al['grund'] ?? ''), 'Verwaltung'));
$eig3 = new ErsatzRest($ok);
$au = $lauf(['kind_id' => 90042, 'pfad' => 'messages', 'bestaetigt' => true, 'sitzung' => 'irgendeine'], $eig3);
$ae = $lauf(['kind_id' => 90042, 'pfad' => 'messages', 'bestaetigt' => true], $eig3);
pruefe('unbekannte Sitzungsangabe: kein Versand; ohne Angabe: die eigene Sitzung (wie bisher)',
    ($au['gesendet'] ?? null) === false && count($eig3->posts) === 1 && ($ae['sitzung'] ?? null) === 'eigene');
$ak = $lauf(['kind_id' => 90042, 'pfad' => 'messages', 'bestaetigt' => true, 'sitzung' => 'dienstkonto'],
    new ErsatzRest($ok), ['ids' => [], 'quelle' => null, 'kind_name' => ''], 'admin', null);
pruefe('ohne nutzbares Dienstkonto: kein Versand, Grund aus der Sitzung',
    ($ak['gesendet'] ?? null) === false && str_contains((string)($ak['grund'] ?? ''), 'kein_dienstkonto'));
$rd = route($zweig, ['rolle' => 'lehrkraft', 'kinder' => []],
    ['kind_id' => 90042, 'pfad' => 'messages', 'bestaetigt' => true, 'sitzung' => 'dienstkonto']);
pruefe('Route: Lehrkraft mit sitzung dienstkonto – Antwort 200, nicht gesendet',
    $rd->status === 200 && ($rd->daten['bericht']['gesendet'] ?? null) === false);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
