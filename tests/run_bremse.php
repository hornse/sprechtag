<?php
// ============================================================
// tests/run_bremse.php – die Anmeldebremse: Wartezeit statt Sperre, zuerst
// eintragen, dann zählen (v0.9.107, E35)
// Aufruf: php tests/run_bremse.php   → Exit-Code 0 = alles grün
//
// Anlass: Review aus koordination (Commit a700ce2), Befund 5. Entscheidung
// des Betreibers (14.10.2026):
//   1 – Wartezeit statt Sperre, je Benutzername, nicht je Herkunft. Im
//       Schulnetz teilen sich alle eine Adresse (gemessen: hinter dem Proxy
//       sieht die Anwendung ohnehin nur ihn), und die Kürzel stehen
//       öffentlich. Eine gedeckelte Wartezeit schützt gegen jeden.
//   2 – keine Grenze je Herkunft.
//   3 – zuerst eintragen, dann zählen, dann WebUntis fragen: Gleichzeitige
//       Anfragen sehen einander.
// Bis v0.9.106 sperrte die Bremse nach 5 Fehlversuchen 15 Minuten lang – ein
// Werkzeug, mit dem ein Fremder jede Lehrkraft aussperren konnte.
//
// AUSGEFÜHRT gegen SQLite: Das SQL der Bremse ist seit v0.9.107 portabel
// (die Zeit rechnet PHP aus den Zeitstempeln der Datenbank). Bis dahin war
// die Bremse in jeder Suite ersetzt, weil ihr SQL MariaDB sprach.
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

// Bricht die Suite ab, steht das als eigene rote Zeile da, nicht nur im
// Exit-Code (Art 7, Befund 11.10.2026; Vorbild frontend_abgesagte_termine_test).
// Gefunden beim Bau von v0.9.108: Die fehlende Spalte ip ließ die Suite nach
// 6 Zeilen ohne ✗ sterben.
set_exception_handler(function (Throwable $e): void {
    echo '  ✗ Suite abgebrochen: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(1);
});

final class Antwort extends Exception
{
    public function __construct(public int $status, public array $daten) { parent::__construct('antwort'); }
}
function json_ok(array $d, int $s = 200): never { throw new Antwort($s, $d); }
function json_err(string $m, int $s = 400): never { throw new Antwort($s, ['fehler' => $m]); }

require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/api/einstellungen.php';
require __DIR__ . '/../backend/api/anmeldebremse.php';

$da = function_exists('bremse_beginnen') && function_exists('bremse_warte_sekunden');
pruefe('Voraussetzung: bremse_beginnen und bremse_warte_sekunden vorhanden', $da);
$cfg = ['security' => ['max_failed_logins' => 5, 'lockout_minutes' => 15]];

// ---- die Rechnung, rein ----------------------------------------------------
// v0.9.109 (E35, Nachtrag): ab der Schwelle gleich der Deckel, ohne Staffel.
// Die Staffel ab 5 s war im Tempo einer Person nie zu spüren – die Abnahme
// vom 14.10.2026 hat es gezeigt (Fall unten).
echo "Wartezeit: ab der Schwelle gleich 60 Sekunden\n";
$w = fn(int $fehl, int $seit): int => $da ? bremse_warte_sekunden($fehl, $seit, $cfg) : -1;
pruefe('unter der Schwelle (4 Fehlversuche): keine Wartezeit', $w(4, 0) === 0);
pruefe('genau an der Schwelle (5): 60 Sekunden ab dem letzten, nach 15 s noch 45', $w(5, 0) === 60 && $w(5, 15) === 45);
pruefe('ohne Staffel: 6 und 7 Fehlversuche warten wie 5 – 60 Sekunden', $w(6, 0) === 60 && $w(7, 0) === 60);
pruefe('gedeckelt bei 60 Sekunden: 9, 20, 500 Fehlversuche', $w(9, 0) === 60 && $w(20, 0) === 60 && $w(500, 0) === 60);
pruefe('auf der Grenze: 59 s nach dem letzten noch 1 s, nach 60 s nichts mehr', $w(5, 59) === 1 && $w(5, 60) === 0);
pruefe('abgelaufen: nichts mehr zu warten, nie negativ', $w(5, 61) === 0 && $w(9, 61) === 0 && $w(9, 600) === 0);

// ---- ausgeführt gegen SQLite ----------------------------------------------
function neue_db(): PDO
{
    $p = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $p->exec('CREATE TABLE login_log (id INTEGER PRIMARY KEY AUTOINCREMENT, webuntis_benutzer TEXT NOT NULL,
        erfolgreich INTEGER NOT NULL DEFAULT 0, grund TEXT NOT NULL DEFAULT \'\',
        zeitpunkt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');   // ohne ip: so nach sql/27 (E36)
    $p->exec('CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)');
    // KENNUNGEN BEGINNEN NICHT BEI 0 (E37): Das Rücksetzen vergleicht mit der
    // Kennung der letzten gelungenen Anmeldung. Eine Rechnung, die „keine“ als
    // 0 nimmt, wäre bei Kennungen ab 1 nicht von einer richtigen zu
    // unterscheiden – in lernzeiten war eine Prüfung genau dort blind.
    $p->exec("INSERT INTO sqlite_sequence (name, seq) VALUES ('login_log', 4710)");
    return $p;
}
/** $n Fehlversuche für $wer, der letzte vor $vor Sekunden. */
function fehl(PDO $p, string $wer, int $n, int $vor): void
{
    for ($i = 0; $i < $n; $i++) {
        $p->prepare("INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, zeitpunkt)
                     VALUES (?, 0, 'falsch', datetime('now', ?))")->execute([$wer, '-' . ($vor + $i) . ' seconds']);
    }
}
$zeilen = fn(PDO $p): int => (int)$p->query('SELECT COUNT(*) FROM login_log')->fetchColumn();
$beginnen = function (PDO $p, string $wer) use ($cfg, $da): array {
    if (!$da) return ['id' => null, 'status' => 0, 'daten' => []];
    try { return ['id' => bremse_beginnen($p, $cfg, $wer), 'status' => 200, 'daten' => []]; }
    catch (Antwort $a) { return ['id' => null, 'status' => $a->status, 'daten' => $a->daten]; }
    // Jede andere Ausnahme wird an der Prüfung rot, die sie auslöst – nicht
    // als Abbruch der Suite.
    catch (Throwable $e) { echo '    (Ausnahme: ' . $e->getMessage() . ")\n"; return ['id' => null, 'status' => -1, 'daten' => []]; }
};

echo "Zuerst eintragen, dann zählen (Punkt 3)\n";
$p = neue_db();
$r = $beginnen($p, 'probe');
$z = $da && $r['id'] !== null ? $p->query('SELECT * FROM login_log WHERE id = ' . (int)$r['id'])->fetch() : false;
pruefe('ohne Vorgeschichte: durch, und der Versuch steht schon im Protokoll – als laufend, nicht erfolgreich',
    $r['status'] === 200 && is_array($z) && $z['webuntis_benutzer'] === 'probe' && (int)$z['erfolgreich'] === 0
    && $z['grund'] === '(läuft)');
$p = neue_db();
fehl($p, 'probe', 4, 30);
$r1 = $beginnen($p, 'probe');
$r2 = $beginnen($p, 'probe');
pruefe('gleichzeitig: 4 Fehlversuche, zwei Anfragen – die erste läuft, die zweite sieht die erste und wartet',
    $r1['status'] === 200 && $r2['status'] === 429);
pruefe('der abgewiesene Versuch zählt nicht: kein Eintrag dafür (sonst verlängerte jeder Klick die Wartezeit)',
    $zeilen($p) === 5);

echo "Wartezeit statt Sperre (Punkt 1)\n";
$p = neue_db();
fehl($p, 'probe', 5, 1);
$r = $beginnen($p, 'probe');
// 58 oder 59: Die Zeitstempel haben Sekunden; liegt zwischen den Einträgen eine
// Sekundengrenze, ist der letzte schon 2 s alt.
$ws = $r['daten']['warten_sekunden'] ?? null;
pruefe('5 Fehlversuche, der letzte vor 1 s: 429 mit Wartezeit in Sekunden (58–59) und Satz dazu',
    $r['status'] === 429 && in_array($ws, [58, 59], true)
    && str_contains((string)($r['daten']['fehler'] ?? ''), $ws . ' Sekunden'));
$p = neue_db();
fehl($p, 'probe', 5, 61);
pruefe('… der letzte vor 61 s: die Wartezeit ist vorbei, durch – keine Sperre über 15 Minuten',
    $beginnen($p, 'probe')['status'] === 200);
$p = neue_db();
fehl($p, 'probe', 40, 0);
$r = $beginnen($p, 'probe');
pruefe('40 Fehlversuche gerade eben: gewartet wird höchstens 60 Sekunden (gedeckelt)',
    $r['status'] === 429 && ($r['daten']['warten_sekunden'] ?? 999) <= 60 && ($r['daten']['warten_sekunden'] ?? 0) >= 59);
$p = neue_db();
fehl($p, 'probe', 40, 61);
pruefe('… nach 61 Sekunden durch', $beginnen($p, 'probe')['status'] === 200);
$p = neue_db();
fehl($p, 'andere', 9, 0);
pruefe('je Benutzername: 9 fremde Fehlversuche gerade eben zählen nicht',
    $beginnen($p, 'probe')['status'] === 200);
// Das Fenster wirkt, weil der Deckel kurz ist, nur auf die ZAHL: Alte
// Fehlversuche allein sind ohnehin abgelaufen. Erst zusammen mit einem neuen
// heben sie die Zahl über die Schwelle (v0.9.107, nach WZ6 – bis dahin stand
// hier ein Fall, der das Fenster nicht zeigte; Art 6).
$p = neue_db();
fehl($p, 'probe', 4, 16 * 60);
fehl($p, 'probe', 1, 1);
pruefe('Fenster: 4 Fehlversuche vor 16 Minuten und einer vor 1 s – nur der eine zählt, durch',
    $beginnen($p, 'probe')['status'] === 200);

echo "Der Fall der Abnahme (14.10.2026)\n";
// Im Betrieb: ein echter Benutzername, falsches Passwort, sieben Versuche von
// Hand – nie eine Wartezeit, immer -8504. Die Zeilen standen im Protokoll.
// Der Unterschied zur Messung am Server um 22:16 war das TEMPO, nicht der
// Name: dort Bruchteile einer Sekunde, hier eine Person, die neu tippt.
/** $n Fehlversuche mit dem Grund aus WebUntis, im Abstand von $abstand Sekunden, der letzte vor $abstand. */
function fehl_von_hand(PDO $p, string $wer, int $n, int $abstand): void
{
    for ($i = 1; $i <= $n; $i++) {
        $p->prepare("INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, zeitpunkt)
                     VALUES (?, 0, 'WebUntis-Fehler -8504: bad credentials', datetime('now', ?))")
            ->execute([$wer, '-' . ($i * $abstand) . ' seconds']);
    }
}
$p = neue_db();
fehl_von_hand($p, 'erfundenq', 5, 15);
$r = $beginnen($p, 'erfundenq');
pruefe('fünf Fehlversuche (-8504) im Abstand von 15 s, von Hand: die sechste Anfrage wartet',
    $r['status'] === 429);
$p = neue_db();
fehl_von_hand($p, 'erfundenq', 6, 15);
$r = $beginnen($p, 'erfundenq');
pruefe('… sechs im Abstand von 15 s: die siebte wartet ebenso',
    $r['status'] === 429);

echo "Abschluss des Versuchs\n";
$p = neue_db();
$id = $beginnen($p, 'probe')['id'];
if ($da && $id !== null) bremse_fehlschlag($p, (int)$id, 'probe', 'WebUntis: falsches Passwort');
$z = $da && $id !== null ? $p->query('SELECT * FROM login_log WHERE id = ' . (int)$id)->fetch() : false;
pruefe('Fehlschlag: derselbe Eintrag bekommt den Grund, kein zweiter', $zeilen($p) === 1 && is_array($z)
    && $z['grund'] === 'WebUntis: falsches Passwort' && (int)$z['erfolgreich'] === 0);
$p = neue_db();
$id = $beginnen($p, 'probe')['id'];
if ($da && $id !== null) anmeldung_erfolg_merken($p, (int)$id, 'probe', 'lehrkraft');
// Bis E37: „der laufende Eintrag verschwindet, nichts bleibt“. Seit E37 bleibt
// der Vermerk für das Rücksetzen – ohne Rolle, und nicht im Protokoll.
$alle = $p->query('SELECT erfolgreich, grund FROM login_log')->fetchAll();
pruefe('Erfolg, Protokoll aus: nur der Vermerk bleibt – erfolgreich, ohne Rolle, „(zurückgesetzt)“',
    $da && count($alle) === 1 && (int)$alle[0]['erfolgreich'] === 1 && $alle[0]['grund'] === '(zurückgesetzt)');
$p = neue_db();
$p->exec("INSERT INTO einstellungen VALUES ('login_log_aktiv', '1'), ('login_log_erfolge', '1')");
$id = $beginnen($p, 'probe')['id'];
if ($da && $id !== null) anmeldung_erfolg_merken($p, (int)$id, 'probe', 'lehrkraft');
$alle = $p->query('SELECT erfolgreich, grund FROM login_log')->fetchAll();
pruefe('Erfolg, Protokoll mit Erfolgen: genau ein Eintrag, erfolgreich, mit der Rolle',
    $da && count($alle) === 1 && (int)$alle[0]['erfolgreich'] === 1 && $alle[0]['grund'] === 'lehrkraft');

echo "Ohne Entscheid: WebUntis nicht erreichbar (v0.9.109)\n";
// Entscheidung Betreiber (15.10.2026): Ein Versuch, bei dem WebUntis nicht
// antwortete, zählt nicht – kein Passwort hat entschieden.
$oe = $da && function_exists('bremse_ohne_entscheid');
$p = neue_db();
$id = $beginnen($p, 'probe')['id'];
if ($oe && $id !== null) bremse_ohne_entscheid($p, (int)$id, 'probe');
pruefe('ohne Entscheid: der laufende Eintrag verschwindet, nichts bleibt', $oe && $zeilen($p) === 0);
$p = neue_db();
fehl($p, 'probe', 4, 1);
for ($i = 0; $i < 5 && $oe; $i++) {
    $id = $beginnen($p, 'probe')['id'];
    if ($id !== null) bremse_ohne_entscheid($p, (int)$id, 'probe');
}
pruefe('ohne Entscheid zählt nicht: 4 Fehlversuche, dann fünf Versuche ohne Antwort von WebUntis – der nächste ist durch',
    $oe && $beginnen($p, 'probe')['status'] === 200);

// ---- E37: Rücksetzen, Grenze, Datenbank – Auftrag koordination 16.10.2026 --
/** Fehlversuche für $wer mit den Altern $alter (Sekunden), in dieser Reihenfolge eingetragen. */
function fehl_alter(PDO $p, string $wer, array $alter): void
{
    foreach ($alter as $a) {
        $p->prepare("INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, zeitpunkt)
                     VALUES (?, 0, 'WebUntis-Fehler -8504: bad credentials', datetime('now', ?))")
            ->execute([$wer, '-' . $a . ' seconds']);
    }
}
$anmelden = function (PDO $p, string $wer) use ($beginnen, $da): void {
    $id = $beginnen($p, $wer)['id'];
    if ($da && $id !== null) anmeldung_erfolg_merken($p, (int)$id, $wer, 'lehrkraft');
};

echo "Eine gelungene Anmeldung setzt zurück (E37)\n";
// Fünf Fehlversuche, deren Wartezeit vorbei ist; dann meldet sich der Inhaber
// an; dann vertippt er sich einmal. Ohne Rücksetzen wären es sechs, der
// letzte vor 1 s – er müsste warten.
$p = neue_db();
fehl_alter($p, 'erfundenq', [300, 280, 260, 240, 220]);
$anmelden($p, 'erfundenq');
fehl_alter($p, 'erfundenq', [1]);
pruefe('Rücksetzen: 5 Fehlversuche, dann angemeldet, dann ein Fehlversuch vor 1 s – durch',
    $beginnen($p, 'erfundenq')['status'] === 200);
// Gegenprobe: dieselbe Lage ohne die Anmeldung muss warten – sonst prüfte der
// Fall oben nur, dass die Schwelle nicht erreicht ist.
$p = neue_db();
fehl_alter($p, 'erfundenq', [300, 280, 260, 240, 220, 1]);
pruefe('Gegenprobe: dieselben sechs Fehlversuche ohne Anmeldung dazwischen – es wird gewartet',
    $beginnen($p, 'erfundenq')['status'] === 429);
// Gezählt wird ab der KENNUNG der letzten gelungenen, nicht ab ihrer Uhrzeit:
// Ein Fehlversuch, der dieselbe Sekunde trägt wie die Anmeldung, aber danach
// eingetragen wurde, zählt mit.
$p = neue_db();
fehl_alter($p, 'erfundenq', [300, 280, 260, 240]);
$anmelden($p, 'erfundenq');
$z = $p->query("SELECT zeitpunkt FROM login_log WHERE erfolgreich = 1")->fetchColumn();
for ($i = 0; $i < 5; $i++) {
    $p->prepare("INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, zeitpunkt) VALUES ('erfundenq', 0, 'falsch', ?)")
      ->execute([(string)$z]);
}
pruefe('nach der Nummer: fünf Fehlversuche in derselben Sekunde wie die Anmeldung, danach eingetragen – sie zählen, es wird gewartet',
    $z !== false && $beginnen($p, 'erfundenq')['status'] === 429);
$p = neue_db();
fehl_alter($p, 'andere', [300, 280, 260, 240, 220]);
$anmelden($p, 'erfundenq');
fehl_alter($p, 'andere', [1]);
pruefe('je Benutzername: die Anmeldung eines anderen setzt nicht zurück',
    $beginnen($p, 'andere')['status'] === 429);

echo "Der Vermerk ist ein Zähler, kein Protokolleintrag (E37)\n";
$le = $da && function_exists('login_log_eintraege');
$p = neue_db();
$p->exec("INSERT INTO einstellungen VALUES ('login_log_aktiv', '1'), ('login_log_erfolge', '0')");
fehl_alter($p, 'erfundenq', [100]);
$anmelden($p, 'erfundenq');
$e = $le ? login_log_eintraege($p, '', 200) : null;
pruefe('Protokoll an, Erfolge aus: die Verwaltung sieht den Fehlversuch, nicht den Vermerk',
    is_array($e) && count($e) === 1 && (int)$e[0]['erfolgreich'] === 0);
$e = $le ? login_log_eintraege($p, 'erfund', 200) : null;
pruefe('… auch mit Filter nach Benutzername',
    is_array($e) && count($e) === 1 && (int)$e[0]['erfolgreich'] === 0);
$p = neue_db();
$p->prepare("INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, zeitpunkt)
             VALUES ('erfundenq', 1, '(zurückgesetzt)', datetime('now', '-16 minutes'))")->execute();
$beginnen($p, 'probe');
pruefe('der Vermerk hält höchstens das Zählfenster: einer von vor 16 Minuten ist beim nächsten Versuch fort',
    (int)$p->query("SELECT COUNT(*) FROM login_log WHERE grund = '(zurückgesetzt)'")->fetchColumn() === 0);
$p = neue_db();
$p->prepare("INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, zeitpunkt)
             VALUES ('erfundenq', 1, '(zurückgesetzt)', datetime('now', '-14 minutes'))")->execute();
$beginnen($p, 'probe');
pruefe('… einer von vor 14 Minuten bleibt',
    (int)$p->query("SELECT COUNT(*) FROM login_log WHERE grund = '(zurückgesetzt)'")->fetchColumn() === 1);

echo "Die Grenze, ausgeführt\n";
// Die Zeitstempel haben Sekunden: Zwischen dem Eintragen der Vorgeschichte und
// dem eigenen Versuch kann eine Sekundengrenze liegen. Deshalb 58 und 60 –
// die Grenze auf die Sekunde steht rein oben („auf der Grenze“).
$p = neue_db();
fehl_alter($p, 'erfundenq', [240, 180, 120, 90, 58]);
pruefe('der letzte von 5 vor 58 s (59 beim Zählen): abgewiesen, 1–2 s Rest',
    in_array($beginnen($p, 'erfundenq')['daten']['warten_sekunden'] ?? null, [1, 2], true));
$p = neue_db();
fehl_alter($p, 'erfundenq', [240, 180, 120, 90, 60]);
pruefe('der letzte von 5 vor 60 s: durch', $beginnen($p, 'erfundenq')['status'] === 200);

echo "Die bekannte Grenze: ein Skript im Minutentakt (E35, E37)\n";
// Ein Skript, das einmal je Minute fehlschlägt, hält das Konto in der
// Wartezeit – mühsamer als die Sperre, nicht ausgeschlossen. Diese Prüfung
// hält fest, dass es so ist; wird sie rot, hat sich die Bauart geändert.
$p = neue_db();
fehl_alter($p, 'erfundenq', [541, 481, 421, 361, 301, 241, 181, 121, 61, 1]);
$r = $beginnen($p, 'erfundenq');
pruefe('Skript im Minutentakt, der letzte vor 1 s: der Inhaber muss warten (58–59 s)',
    $r['status'] === 429 && in_array($r['daten']['warten_sekunden'] ?? null, [58, 59], true));
$p = neue_db();
fehl_alter($p, 'erfundenq', [600, 540, 480, 420, 360, 300, 240, 180, 120, 60]);
pruefe('… und der nächste Versuch des Skripts nach der Minute ist wieder durch',
    $beginnen($p, 'erfundenq')['status'] === 200);

echo "Die Datenbank antwortet nicht (E37)\n";
// Bis E37 fiel eine Ausnahme aus bremse_beginnen ungefangen hinaus: Der
// Browser bekam einen 500 ohne Satz. Geschlossen war das auch – aber nicht
// unterscheidbar von einem Absturz.
$p = neue_db();
$p->exec('DROP TABLE login_log');
$r = $beginnen($p, 'erfundenq');
pruefe('ohne Tabelle: abgewiesen mit 503 und eigenem Satz, nicht „Passwort falsch“',
    $r['status'] === 503 && str_contains((string)($r['daten']['fehler'] ?? ''), 'Anmeldeversuche')
    && !str_contains((string)($r['daten']['fehler'] ?? ''), 'Zugangsdaten'));

echo "Ohne IP-Adresse (E36)\n";
// Die Testtabelle oben hat keine Spalte ip mehr: Schriebe die Bremse sie noch,
// scheiterte jede Prüfung oben. Hier die Migration, die sie im Betrieb entfernt.
$mig = is_file(__DIR__ . '/../sql/27_login_log_ohne_ip.sql')
    ? (string)file_get_contents(__DIR__ . '/../sql/27_login_log_ohne_ip.sql') : '';
pruefe('sql/27 entfernt login_log.ip, zweimal einspielbar (DROP COLUMN IF EXISTS)',
    preg_match('/^ALTER TABLE login_log DROP COLUMN IF EXISTS ip;$/m', $mig) === 1);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
