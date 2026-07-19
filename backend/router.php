<?php
// ============================================================
// router.php – Einstiegspunkt (PHP built-in Server, Port 8085)
//
// KRITISCH (siehe NEUES_PROJEKT_PROMPT.md, Abschnitt 5):
// Die beiden folgenden Zeilen MÜSSEN vor jedem require stehen.
// Uberspace terminiert SSL vor PHP – ohne $_SERVER['HTTPS']='on'
// fehlt das Secure-Flag am Session-Cookie und der Browser
// verwirft es auf HTTPS. Ohne session_name() geht die Session
// nach dem Login verloren.
// ============================================================
$_SERVER['HTTPS'] = 'on';
session_name('sprechtag_session');
// Kein declare(strict_types) hier: es müsste VOR den beiden kritischen
// Zeilen stehen (PHP-Regel „very first statement") – die übrigen Dateien
// deklarieren strict_types selbst.

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = dirname(__DIR__);

// ---- API ----------------------------------------------------
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api/index.php';
    exit;
}

// ---- Statische Frontend-Dateien -----------------------------
$statisch = [
    '/app.js'      => ['frontend/app.js',      'application/javascript; charset=utf-8'],
    '/style.css'   => ['frontend/style.css',   'text/css; charset=utf-8'],
    '/favicon.svg' => ['frontend/favicon.svg', 'image/svg+xml'],
];
if (isset($statisch[$uri])) {
    [$pfad, $typ] = $statisch[$uri];
    $datei = $root . '/' . $pfad;
    if (is_file($datei)) {
        header('Content-Type: ' . $typ);
        header('Cache-Control: no-cache');
        readfile($datei);
        exit;
    }
    http_response_code(404);
    exit;
}

// ---- SPA-Fallback: alles andere -> index.html ---------------
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
readfile($root . '/frontend/index.html');
