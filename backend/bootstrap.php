<?php
// ============================================================
// bootstrap.php – Session, Config, PDO, JSON-Helfer
// Wird von backend/api/index.php eingebunden. Den Session-Namen setzt
// bereits router.php (GANZ OBEN, kritisch!); die Cookie-Flags (Secure,
// HttpOnly, SameSite) stehen unten vor session_start().
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/helfer.php';

// Sitzungs-Cookie mit Secure, HttpOnly und SameSite=Lax (v0.9.101), vor
// session_start(). Muster aus lernzeiten/backend/bootstrap.php (Messung über
// die Reihe, 14.10.2026: sechs Projekte setzen alle drei, sprechtag keines).
// Secure kommt NICHT aus $_SERVER['HTTPS'] – PHP leitet es nicht daraus ab;
// gemessen am Server 14.10.2026: Cookie nur mit „path=/“.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

$configPfad = __DIR__ . '/config.php';
if (!is_file($configPfad)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['fehler' => 'config.php fehlt – aus config.example.php erzeugen'],
        JSON_UNESCAPED_UNICODE);
    exit;
}
$cfg = require $configPfad;

/** PDO-Verbindung (lazy). Fehler werden als sauberes JSON gemeldet,
 *  nicht als Fatal Error – sonst sieht der Browser nur einen 500er. */
function db(array $cfg): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO($cfg['db']['dsn'], $cfg['db']['benutzer'], $cfg['db']['passwort'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log('sprechtag: DB-Verbindung fehlgeschlagen: ' . $e->getMessage());
            json_err('Die Datenbank ist derzeit nicht erreichbar. '
                . 'Bitte später erneut versuchen.', 503);
        }
    }
    return $pdo;
}

function json_ok(array $daten, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_err(string $meldung, int $status = 400): never
{
    json_ok(['fehler' => $meldung], $status);
}

/** JSON-Body des Requests lesen. */
function body_json(): array
{
    $roh = file_get_contents('php://input');
    $j = json_decode($roh === false ? '' : $roh, true);
    return is_array($j) ? $j : [];
}

/** Pflichtfeld aus dem Body. */
function req(array $b, string $feld): string
{
    $wert = trim((string)($b[$feld] ?? ''));
    if ($wert === '') json_err("Feld '$feld' fehlt");
    return $wert;
}


