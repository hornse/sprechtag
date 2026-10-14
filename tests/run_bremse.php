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
echo "Wartezeit: ab der Schwelle, verdoppelt, gedeckelt\n";
$w = fn(int $fehl, int $seit): int => $da ? bremse_warte_sekunden($fehl, $seit, $cfg) : -1;
pruefe('unter der Schwelle (4 Fehlversuche): keine Wartezeit', $w(4, 0) === 0);
pruefe('genau an der Schwelle (5): 5 Sekunden ab dem letzten', $w(5, 0) === 5 && $w(5, 3) === 2);
pruefe('verdoppelt: 6 → 10, 7 → 20, 8 → 40', $w(6, 0) === 10 && $w(7, 0) === 20 && $w(8, 0) === 40);
pruefe('gedeckelt bei 60 Sekunden: 9, 20, 500 Fehlversuche', $w(9, 0) === 60 && $w(20, 0) === 60 && $w(500, 0) === 60);
pruefe('abgelaufen: nichts mehr zu warten, nie negativ', $w(5, 5) === 0 && $w(9, 61) === 0 && $w(9, 600) === 0);

// ---- ausgeführt gegen SQLite ----------------------------------------------
function neue_db(): PDO
{
    $p = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $p->exec('CREATE TABLE login_log (id INTEGER PRIMARY KEY AUTOINCREMENT, webuntis_benutzer TEXT NOT NULL,
        erfolgreich INTEGER NOT NULL DEFAULT 0, grund TEXT NOT NULL DEFAULT \'\', ip TEXT NOT NULL DEFAULT \'\',
        zeitpunkt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    $p->exec('CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)');
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
// 3 oder 4: Die Zeitstempel haben Sekunden; liegt zwischen den Einträgen eine
// Sekundengrenze, ist der letzte schon 2 s alt.
$ws = $r['daten']['warten_sekunden'] ?? null;
pruefe('5 Fehlversuche, der letzte vor 1 s: 429 mit Wartezeit in Sekunden (3–4) und Satz dazu',
    $r['status'] === 429 && in_array($ws, [3, 4], true)
    && str_contains((string)($r['daten']['fehler'] ?? ''), $ws . ' Sekunden'));
$p = neue_db();
fehl($p, 'probe', 5, 10);
pruefe('… der letzte vor 10 s: die Wartezeit ist vorbei, durch – keine Sperre über 15 Minuten',
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
pruefe('Erfolg, Protokoll aus: der laufende Eintrag verschwindet, nichts bleibt', $da && $zeilen($p) === 0);
$p = neue_db();
$p->exec("INSERT INTO einstellungen VALUES ('login_log_aktiv', '1'), ('login_log_erfolge', '1')");
$id = $beginnen($p, 'probe')['id'];
if ($da && $id !== null) anmeldung_erfolg_merken($p, (int)$id, 'probe', 'lehrkraft');
$alle = $p->query('SELECT erfolgreich, grund FROM login_log')->fetchAll();
pruefe('Erfolg, Protokoll mit Erfolgen: genau ein Eintrag, erfolgreich, mit der Rolle',
    $da && count($alle) === 1 && (int)$alle[0]['erfolgreich'] === 1 && $alle[0]['grund'] === 'lehrkraft');

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
