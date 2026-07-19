<?php
// ============================================================
// bootstrap.php – Session, Config, PDO, JSON-Helfer
// Wird von backend/api/index.php eingebunden. Session-Name und
// HTTPS-Flag setzt bereits router.php (GANZ OBEN, kritisch!).
// ============================================================

declare(strict_types=1);

session_start();

$configPfad = __DIR__ . '/config.php';
if (!is_file($configPfad)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['fehler' => 'config.php fehlt – aus config.example.php erzeugen'],
        JSON_UNESCAPED_UNICODE);
    exit;
}
$cfg = require $configPfad;

/** PDO-Verbindung (lazy – die Sondierung braucht sie nur für login_log). */
function db(array $cfg): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO($cfg['db']['dsn'], $cfg['db']['benutzer'], $cfg['db']['passwort'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
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
