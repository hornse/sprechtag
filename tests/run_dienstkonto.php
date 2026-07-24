<?php
declare(strict_types=1);
require __DIR__ . '/../backend/api/dienstkonto.php';
$fehler = 0;
function pruefe(string $n, bool $ok): void {
    global $fehler; echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n"; if (!$ok) $fehler++;
}

$cfg = ['dienstkonto_schluessel' => str_repeat('K', 40)];

echo "Verschlüsselung\n";
$c = dk_verschluesseln($cfg, 'geheimesPasswort123');
pruefe('erzeugt Chiffre', is_string($c) && $c !== '');
pruefe('Klartext nicht enthalten', !str_contains(base64_decode($c), 'geheimes'));
pruefe('Rückweg funktioniert', dk_entschluesseln($cfg, $c) === 'geheimesPasswort123');

$c2 = dk_verschluesseln($cfg, 'geheimesPasswort123');
pruefe('zwei Durchgänge -> verschiedene Chiffren (Nonce)', $c !== $c2);
pruefe('beide entschlüsselbar', dk_entschluesseln($cfg, $c2) === 'geheimesPasswort123');

echo "Umlaute und Sonderzeichen\n";
$s = 'Pässwört!#äöü§$%&';
pruefe('UTF-8 unverändert',
    dk_entschluesseln($cfg, dk_verschluesseln($cfg, $s)) === $s);

echo "Fehlerfälle\n";
pruefe('kein Schlüssel -> null',
    dk_verschluesseln(['dienstkonto_schluessel' => ''], 'x') === null);
pruefe('zu kurzer Schlüssel -> null',
    dk_verschluesseln(['dienstkonto_schluessel' => 'kurz'], 'x') === null);
pruefe('falscher Schlüssel entschlüsselt nicht',
    dk_entschluesseln(['dienstkonto_schluessel' => str_repeat('X', 40)], $c) === null);
pruefe('Müll-Eingabe -> null', dk_entschluesseln($cfg, 'kein-base64!!') === null);
pruefe('leere Eingabe -> null', dk_entschluesseln($cfg, '') === null);
pruefe('manipulierte Chiffre -> null',
    dk_entschluesseln($cfg, base64_encode(substr(base64_decode($c), 0, -3) . 'XYZ')) === null);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
