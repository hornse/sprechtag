<?php
// DB-Logiktest mit SQLite (kein Serverprozess nötig).
// Prüft die SQL-Aussagen der Mitteilungs- und Buchungslogik.
declare(strict_types=1);
require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/api/mitteilungen.php';

$fehler = 0;
function pruefe(string $n, bool $ok): void {
    global $fehler; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; if (!$ok) $fehler++;
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE mitteilungen (id INTEGER PRIMARY KEY AUTOINCREMENT,
  sprechtag_id INT, empfaenger_user_id INT, schueler_id INT, anlass TEXT,
  betreff TEXT, text TEXT,
  status TEXT DEFAULT "offen", grund TEXT DEFAULT "", versuche INT DEFAULT 0,
  angelegt_am TEXT DEFAULT CURRENT_TIMESTAMP, gesendet_am TEXT)');
$pdo->exec('CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)');

echo "Warteschlange\n";
$cfg = ['webuntis' => ['base_url' => 'https://example.invalid', 'school' => 'x',
    'client' => 'test', 'allowed_person_types' => [2]]];

// Ohne Zugangsdaten -> bleibt offen, kein Netzzugriff
$e = mit_einreihen_und_senden($cfg, $pdo, 1, 5984, 'bestaetigung', 'Betreff', 'Text');
pruefe('Mitteilung angelegt', $e['id'] > 0);
pruefe('Status offen ohne Zugangsdaten', $e['status'] === 'offen');
$row = $pdo->query('SELECT * FROM mitteilungen WHERE id = ' . $e['id'])->fetch();
pruefe('Empfänger gespeichert', (int)$row['empfaenger_user_id'] === 5984);
pruefe('Anlass gespeichert', $row['anlass'] === 'bestaetigung');
pruefe('Versuche zunächst 0', (int)$row['versuche'] === 0);
pruefe('kein Sendezeitpunkt', $row['gesendet_am'] === null);

// Versand mit unerreichbarem Host -> Mitteilung bleibt offen, kein Absturz
$r = mit_versand_ausfuehren($cfg, $pdo, [$e['id']], 'user', 'pass');
pruefe('Versand meldet Fehlschlag sauber', $r['gesendet'] === 0);
pruefe('Grund genannt', $r['grund'] !== '');
$row = $pdo->query('SELECT * FROM mitteilungen WHERE id = ' . $e['id'])->fetch();
pruefe('Mitteilung bleibt offen (nicht verloren)', $row['status'] === 'offen');

// Leere ID-Liste
$r2 = mit_versand_ausfuehren($cfg, $pdo, [], 'u', 'p');
pruefe('leere Liste ohne Netzzugriff', $r2['gesendet'] === 0 && $r2['fehler'] === 0);

echo "kuerze (mbstring-Fallback)\n";
pruefe('kurzer Text unverändert', kuerze('abc', 10) === 'abc');
pruefe('langer Text gekürzt', strlen(kuerze(str_repeat('a', 500), 190)) === 190);
$um = kuerze(str_repeat('ä', 200), 191);
pruefe('keine kaputte UTF-8-Sequenz', json_encode($um) !== false);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler TEST(S) ROT\n");
exit($fehler === 0 ? 0 : 1);
