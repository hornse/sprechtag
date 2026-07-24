<?php
// Dienstkonto-Kette gegen SQLite: speichern, lesen, Status, löschen.
declare(strict_types=1);
require __DIR__ . '/../backend/api/dienstkonto.php';
$fehler = 0;
function pruefe(string $n, bool $ok): void {
    global $fehler; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; if (!$ok) $fehler++;
}
$pdo = new PDO('sqlite::memory:', null, null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)');
// SQLite kennt kein ON DUPLICATE KEY – für den Test nachbilden
$pdo->exec('CREATE TRIGGER t BEFORE INSERT ON einstellungen BEGIN
    DELETE FROM einstellungen WHERE schluessel = NEW.schluessel; END');

$cfg = ['dienstkonto_schluessel' => str_repeat('S', 40)];

echo "Status ohne Dienstkonto\n";
$st = dk_status($cfg, $pdo);
pruefe('nicht hinterlegt', $st['hinterlegt'] === false);
pruefe('Schlüssel erkannt', $st['schluessel_ok'] === true);
pruefe('Verfahren benannt', $st['verfahren'] !== 'keines');
pruefe('lesen liefert null', dk_lesen($cfg, $pdo) === null);

echo "Speichern und lesen\n";
// ON DUPLICATE KEY ist MySQL-Syntax; für SQLite direkt einfügen
$pdo->prepare('INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?)')
    ->execute(['dienstkonto_benutzer', 'dienstho']);
$pdo->prepare('INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?)')
    ->execute(['dienstkonto_passwort', dk_verschluesseln($cfg, 'DienstPass!123')]);

$gelesen = dk_lesen($cfg, $pdo);
pruefe('Benutzer gelesen', $gelesen['benutzer'] === 'dienstho');
pruefe('Passwort entschlüsselt', $gelesen['passwort'] === 'DienstPass!123');

$st = dk_status($cfg, $pdo);
pruefe('Status: hinterlegt', $st['hinterlegt'] === true);
pruefe('Status: entschlüsselbar', $st['entschluesselbar'] === true);
pruefe('Status enthält KEIN Passwort',
    !str_contains(json_encode($st), 'DienstPass'));

echo "Schlüsselwechsel\n";
$cfgNeu = ['dienstkonto_schluessel' => str_repeat('T', 40)];
pruefe('anderer Schlüssel -> nicht lesbar', dk_lesen($cfgNeu, $pdo) === null);
pruefe('Status meldet nicht entschlüsselbar',
    dk_status($cfgNeu, $pdo)['entschluesselbar'] === false);

echo "Löschen\n";
dk_loeschen($pdo);
pruefe('nach Löschen nicht mehr lesbar', dk_lesen($cfg, $pdo) === null);
pruefe('Status: nicht hinterlegt', dk_status($cfg, $pdo)['hinterlegt'] === false);
pruefe('keine Reste in der Tabelle',
    (int)$pdo->query('SELECT COUNT(*) FROM einstellungen')->fetchColumn() === 0);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
