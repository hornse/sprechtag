<?php
// ============================================================
// dienstkonto.php – verschlüsselte Ablage der Zugangsdaten
//
// ZWECK: Die Lehrkraft-Ermittlung braucht ein WebUntis-Konto mit
// Leserecht auf Schülerstundenpläne. Damit sie auch dann läuft,
// wenn die Administration nicht angemeldet ist (Eltern buchen
// abends), werden die Zugangsdaten verschlüsselt gespeichert.
//
// SICHERHEIT – ehrliche Einordnung:
//  * Verschlüsselt wird mit AES-256-GCM (libsodium falls vorhanden,
//    sonst OpenSSL). Der Schlüssel steht in config.php und damit
//    NICHT in der Datenbank – ein reiner DB-Dump reicht also nicht.
//  * Wer Lesezugriff auf BEIDES hat (Dateisystem und Datenbank),
//    kann die Zugangsdaten entschlüsseln. Das ist prinzipbedingt so,
//    weil der Server sie im Klartext braucht, um sich anzumelden.
//  * Daher: möglichst ein eigenes Dienstkonto mit MINIMALEN Rechten
//    verwenden (Stundenpläne lesen, Mitteilungen senden) – nicht das
//    persönliche Admin-Konto.
//  * Die Zugangsdaten können jederzeit über die Adminseite gelöscht
//    werden; ohne sie fällt das System auf Ermittlung mit der
//    Eltern-Session zurück.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../helfer.php';

/** Liefert den Schlüssel aus der Konfiguration oder null. */
function dk_schluessel(array $cfg): ?string
{
    $roh = (string)($cfg['dienstkonto_schluessel'] ?? '');
    if (strlen($roh) < 32) return null;   // zu kurz = unbrauchbar
    return hash('sha256', $roh, true);    // immer 32 Byte
}

/**
 * Verschlüsselt einen Text. Rückgabe: base64(nonce|ciphertext)
 * oder null, wenn kein Schlüssel konfiguriert ist.
 */
function dk_verschluesseln(array $cfg, string $klartext): ?string
{
    $key = dk_schluessel($cfg);
    if ($key === null) return null;

    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $chiffre = sodium_crypto_secretbox($klartext, $nonce, $key);
        return base64_encode('s' . $nonce . $chiffre);
    }
    if (function_exists('openssl_encrypt')) {
        $nonce = random_bytes(12);
        $tag = '';
        $chiffre = openssl_encrypt($klartext, 'aes-256-gcm', $key,
            OPENSSL_RAW_DATA, $nonce, $tag);
        if ($chiffre === false) return null;
        return base64_encode('o' . $nonce . $tag . $chiffre);
    }
    return null;
}

/** Entschlüsselt einen mit dk_verschluesseln() erzeugten Text. */
function dk_entschluesseln(array $cfg, string $gespeichert): ?string
{
    $key = dk_schluessel($cfg);
    if ($key === null) return null;

    $roh = base64_decode($gespeichert, true);
    if ($roh === false || $roh === '') return null;
    $art = $roh[0];
    $rest = substr($roh, 1);

    if ($art === 's' && function_exists('sodium_crypto_secretbox_open')) {
        $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($rest) <= $n) return null;
        $klar = sodium_crypto_secretbox_open(substr($rest, $n),
            substr($rest, 0, $n), $key);
        return $klar === false ? null : $klar;
    }
    if ($art === 'o' && function_exists('openssl_decrypt')) {
        if (strlen($rest) <= 28) return null;
        $klar = openssl_decrypt(substr($rest, 28), 'aes-256-gcm', $key,
            OPENSSL_RAW_DATA, substr($rest, 0, 12), substr($rest, 12, 16));
        return $klar === false ? null : $klar;
    }
    return null;
}

/**
 * Speichert die Zugangsdaten des Dienstkontos.
 * Rückgabe: ['ok' => bool, 'grund' => string]
 */
function dk_speichern(array $cfg, PDO $pdo, string $benutzer, string $passwort): array
{
    $chiffre = dk_verschluesseln($cfg, $passwort);
    if ($chiffre === null) {
        return ['ok' => false, 'grund' => 'Kein Verschlüsselungsschlüssel in '
            . 'config.php hinterlegt (dienstkonto_schluessel, mindestens 32 '
            . 'Zeichen). Ohne ihn werden keine Zugangsdaten gespeichert.'];
    }
    $stmt = $pdo->prepare("INSERT INTO einstellungen (schluessel, wert)
        VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)");
    $stmt->execute(['dienstkonto_benutzer', $benutzer]);
    $stmt->execute(['dienstkonto_passwort', $chiffre]);
    return ['ok' => true, 'grund' => 'Zugangsdaten verschlüsselt gespeichert.'];
}

/** Entfernt die gespeicherten Zugangsdaten. */
function dk_loeschen(PDO $pdo): void
{
    $pdo->prepare("DELETE FROM einstellungen
        WHERE schluessel IN ('dienstkonto_benutzer', 'dienstkonto_passwort')")
        ->execute();
}

/**
 * Liest die Zugangsdaten. Rückgabe: ['benutzer' => string, 'passwort' => string]
 * oder null, wenn nichts hinterlegt oder nicht entschlüsselbar ist.
 */
function dk_lesen(array $cfg, PDO $pdo): ?array
{
    $stmt = $pdo->query("SELECT schluessel, wert FROM einstellungen
        WHERE schluessel IN ('dienstkonto_benutzer', 'dienstkonto_passwort')");
    $werte = [];
    foreach ($stmt->fetchAll() as $z) {
        $werte[$z['schluessel']] = (string)$z['wert'];
    }
    $benutzer = $werte['dienstkonto_benutzer'] ?? '';
    $chiffre  = $werte['dienstkonto_passwort'] ?? '';
    if ($benutzer === '' || $chiffre === '') return null;

    $passwort = dk_entschluesseln($cfg, $chiffre);
    if ($passwort === null || $passwort === '') return null;
    return ['benutzer' => $benutzer, 'passwort' => $passwort];
}

/** Status für die Adminseite – ohne Passwort preiszugeben. */
function dk_status(array $cfg, PDO $pdo): array
{
    $stmt = $pdo->query("SELECT wert FROM einstellungen
        WHERE schluessel = 'dienstkonto_benutzer'");
    $benutzer = (string)($stmt->fetchColumn() ?: '');

    return [
        'hinterlegt'      => $benutzer !== '',
        'benutzer'        => $benutzer,
        'schluessel_ok'   => dk_schluessel($cfg) !== null,
        'verfahren'       => function_exists('sodium_crypto_secretbox')
            ? 'libsodium (XSalsa20-Poly1305)'
            : (function_exists('openssl_encrypt') ? 'OpenSSL (AES-256-GCM)' : 'keines'),
        'entschluesselbar' => dk_lesen($cfg, $pdo) !== null,
    ];
}
