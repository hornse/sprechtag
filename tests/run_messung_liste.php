<?php
// ============================================================
// tests/run_messung_liste.php – Messung: Liste auflösen und an sie senden
// über die Sitzung der angemeldeten Person (v0.9.71)
// Aufruf: php tests/run_messung_liste.php   → Exit-Code 0 = alles grün
//
// MESSUNG, KEIN FEATURE. Frage: Ersetzt die Sitzung einer Lehrkraft (bzw. der
// Verwaltung) das Dienstkonto bei den Erinnerungen – Liste auflösen
// (CUSTOM/filter) und an sie senden (/v2/messages/users, recipientUserIds)?
//
// Die Messung kann eine echte Nachricht verschicken. Geprüft wird vor allem,
// dass sie das nur an eine kleine Testliste tut: nur QUICK, höchstens 5
// Empfänger, nur bestätigt, genau ein Versand – und dass die Antwort keine
// Personenangaben trägt. Körper wie erinnerung_versenden(); Antworten
// ERFUNDEN in der dort belegten Form (numberOfRecipients, gemessen 07.10.).
// Die echte listeAufloesen() läuft über einen Ersatz für post().
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
function db(array $cfg): PDO { return new PDO('sqlite::memory:'); }

require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/auth/WebUntisRest.php';
require __DIR__ . '/../backend/auth/extractors.php';
require __DIR__ . '/../backend/api/auth.php';
require __DIR__ . '/../backend/api/mitteilungen.php';
require __DIR__ . '/../backend/api/einstellungen.php';
require __DIR__ . '/../backend/api/erinnerungen.php';
require __DIR__ . '/../backend/api/messung_sitzung.php';

$fehlt = array_filter(['messung_liste_koerper', 'messung_liste_ausfuehren'], fn($f) => !function_exists($f));
pruefe('Voraussetzung: Funktionen vorhanden', $fehlt === []);
if ($fehlt !== []) { echo '    (fehlen: ' . implode(', ', $fehlt) . ")\n\n$fehler ROT\n"; exit(1); }

/** Echter WebUntisRest, nur post()/postMultipart() ersetzt – listeAufloesen() läuft echt. */
final class ErsatzRest extends WebUntisRest
{
    public array $posts = [];
    public array $multipart = [];
    public function __construct(private array $users, private array $antwort)
    { parent::__construct('http://127.0.0.1:9', 'x'); }
    public function post(string $pfad, array $daten): array
    { $this->posts[] = [$pfad, $daten]; return ['status' => 200, 'json' => ['users' => $this->users]]; }
    public function postMultipart(string $pfad, array $daten, string $feldname = 'request'): array
    { $this->multipart[] = [$pfad, $daten]; return $this->antwort; }
}
$zwei = [['id' => 70001, 'displayName' => 'Erfunden Eins', 'role' => 'LEGAL_GUARDIAN'],
         ['id' => 70002, 'displayName' => 'Erfunden Zwei', 'role' => 'TEACHER']];
$ok = ['status' => 200, 'json' => ['numberOfRecipients' => 2, 'numberOfCCRecipients' => null], 'text' => ''];
$lauf = fn(array $e, ?ErsatzRest $r, string $rolle = 'lehrkraft') =>
    messung_liste_ausfuehren($e, $rolle, fn() => ['rest' => $r, 'grund' => $r === null ? 'kein_cookie' : null]);

// ------------------------------------------------------------
echo "Der Körper – wie die Erinnerungen\n";
$k = messung_liste_koerper([70001, 70002]);
pruefe('Felder wie erinnerung_versenden(): subject, content, requestConfirmation, recipientUserIds, oneDriveAttachments, forbidReply',
    array_keys($k) === ['subject', 'content', 'requestConfirmation', 'recipientUserIds', 'oneDriveAttachments', 'forbidReply']
    && $k['recipientUserIds'] === [70001, 70002]);
pruefe('fester Testbetreff', str_contains($k['subject'], 'Testnachricht') && str_contains($k['subject'], 'bitte ignorieren'));

// ------------------------------------------------------------
echo "Schritt 1: auflösen (nur lesen)\n";
$r1 = new ErsatzRest($zwei, $ok);
$a1 = $lauf(['schritt' => 'aufloesen', 'liste_typ' => 'QUICK', 'liste_id' => 31], $r1);
pruefe('löst über CUSTOM/filter auf: Status, Anzahl 2 – und sendet nichts',
    ($a1['aufloesung']['status'] ?? null) === 200 && ($a1['aufloesung']['anzahl'] ?? null) === 2
    && count($r1->multipart) === 0 && str_ends_with((string)($r1->posts[0][0] ?? ''), '/CUSTOM/filter')
    && ($r1->posts[0][1]['filters'][0]['type'] ?? null) === 'QUICK');
pruefe('der Bericht nennt die Rolle der sendenden Sitzung', ($a1['rolle'] ?? null) === 'lehrkraft');

// ------------------------------------------------------------
echo "Schritt 2: senden – nur, wenn es soll\n";
$r2 = new ErsatzRest($zwei, $ok);
$a2 = $lauf(['schritt' => 'senden', 'liste_typ' => 'QUICK', 'liste_id' => 31, 'bestaetigt' => true], $r2, 'admin');
pruefe('bestätigt: genau EIN Versand an /v2/messages/users mit den aufgelösten Empfängern',
    count($r2->multipart) === 1 && $r2->multipart[0][0] === '/WebUntis/api/rest/view/v2/messages/users'
    && ($r2->multipart[0][1]['recipientUserIds'] ?? null) === [70001, 70002]);
pruefe('… Ergebnis: erreicht, 2 Empfänger, Rolle Verwaltung',
    ($a2['antwort']['ergebnis'] ?? null) === 'erreicht' && ($a2['antwort']['empfaenger'] ?? null) === 2
    && ($a2['rolle'] ?? null) === 'admin');
$r3 = new ErsatzRest($zwei, $ok);
$a3 = $lauf(['schritt' => 'senden', 'liste_typ' => 'QUICK', 'liste_id' => 31], $r3);
pruefe('ohne Bestätigung: kein Versand (auch kein Auflösen), Grund genannt',
    count($r3->multipart) === 0 && count($r3->posts) === 0 && ($a3['gesendet'] ?? null) === false
    && str_contains((string)($a3['grund'] ?? ''), 'bestätigt'));
$sechs = array_map(fn($i) => ['id' => 71000 + $i, 'displayName' => 'Erfunden ' . $i], range(1, 6));
$r4 = new ErsatzRest($sechs, $ok);
$a4 = $lauf(['schritt' => 'senden', 'liste_typ' => 'QUICK', 'liste_id' => 31, 'bestaetigt' => true], $r4);
pruefe('mehr als 5 Empfänger: KEIN Versand – nur an eine kleine Testliste',
    count($r4->multipart) === 0 && ($a4['gesendet'] ?? null) === false
    && ($a4['aufloesung']['anzahl'] ?? null) === 6 && str_contains((string)($a4['grund'] ?? ''), 'höchstens 5'));
$r5 = new ErsatzRest([], $ok);
$a5 = $lauf(['schritt' => 'senden', 'liste_typ' => 'QUICK', 'liste_id' => 31, 'bestaetigt' => true], $r5);
pruefe('keine Empfänger: kein Versand', count($r5->multipart) === 0 && ($a5['gesendet'] ?? null) === false);
$r6 = new ErsatzRest($zwei, $ok);
$a6 = $lauf(['schritt' => 'senden', 'liste_typ' => 'DYNAMIC', 'liste_id' => 31, 'bestaetigt' => true], $r6);
$a7 = $lauf(['schritt' => 'senden', 'liste_typ' => 'QUICK', 'liste_id' => 0, 'bestaetigt' => true], $r6);
$a8 = $lauf(['schritt' => 'beides', 'liste_typ' => 'QUICK', 'liste_id' => 31, 'bestaetigt' => true], $r6);
pruefe('nur QUICK, nur mit Listen-Kennung, nur bekannte Schritte: sonst nichts (weder lesen noch senden)',
    count($r6->posts) === 0 && count($r6->multipart) === 0
    && ($a6['gesendet'] ?? null) === false && ($a7['gesendet'] ?? null) === false && ($a8['gesendet'] ?? null) === false);
$a9 = $lauf(['schritt' => 'aufloesen', 'liste_typ' => 'QUICK', 'liste_id' => 31], null);
pruefe('ohne nutzbare Sitzung: nichts, Grund aus der Sitzung',
    str_contains((string)($a9['grund'] ?? ''), 'kein_cookie'));
$t = json_encode([$a1, $a2, $a4], JSON_UNESCAPED_UNICODE);
pruefe('keine Personenangaben: weder Kennungen noch Namen der Empfänger',
    !str_contains($t, '7000') && !str_contains($t, '7100') && !str_contains($t, 'Erfunden'));

// ------------------------------------------------------------
echo "Die Route (aus index.php, ausgeführt)\n";
$quelle = (string)file_get_contents(__DIR__ . '/../backend/api/index.php');
$kopf = "if (\$methode === 'POST' && (\$seg[0] ?? '') === 'messung' && (\$seg[1] ?? '') === 'liste') {";
$zweig = '';
if (($a0 = strpos($quelle, $kopf)) !== false) {
    $tiefe = 0;
    foreach (array_slice(token_get_all('<?php ' . substr($quelle, $a0)), 1) as $tk) {
        $txt = is_array($tk) ? $tk[1] : $tk;
        $zweig .= $txt;
        if ($txt === '{' || (is_array($tk) && in_array($tk[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $tiefe++;
        if ($txt === '}') { $tiefe--; if ($tiefe === 0) break; }
    }
}
function route(string $zweig, array $sitzung, array $b): Antwort
{
    $_SESSION = $sitzung; $body = $b; $methode = 'POST'; $seg = ['messung', 'liste']; $cfg = [];
    try { eval($zweig); } catch (Antwort $a) { return $a; }
    return new Antwort(0, []);
}
$re = route($zweig, ['rolle' => 'eltern', 'kinder' => []], ['schritt' => 'aufloesen', 'liste_typ' => 'QUICK', 'liste_id' => 31]);
pruefe('Eltern können die Messung nicht auslösen (403)', $zweig !== '' && $re->status === 403);
$rl = route($zweig, ['rolle' => 'lehrkraft', 'kinder' => []], ['schritt' => 'senden', 'liste_typ' => 'QUICK', 'liste_id' => 31]);
pruefe('Lehrkraft ohne Bestätigung: Antwort 200, nicht gesendet',
    $rl->status === 200 && ($rl->daten['bericht']['gesendet'] ?? null) === false);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
