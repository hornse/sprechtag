<?php
// ============================================================
// tests/mobil-messung/router.php – liefert die echte Oberfläche mit
// Ersatz-API (mock.js, stub.js) für die Breitenmessung. Nur lokal.
//   FRONTEND = Verzeichnis der Oberfläche (Vorgabe: frontend/ im Repo)
//   AUS      = Verzeichnis für die Ergebnisse
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================
declare(strict_types=1);

// realpath: sonst scheitert die Präfixprüfung unten an einem doppelten
// Schrägstrich (TMPDIR endet auf „/“), und alles kommt als 404 – die
// Messung sähe dann eine leere Seite und meldete „nichts ragt über“.
$R = realpath((string)(getenv('FRONTEND') ?: dirname(__DIR__, 2) . '/frontend'));
if ($R === false) { http_response_code(500); echo 'FRONTEND nicht gefunden'; exit; }
$H = __DIR__;
$AUS = (string)getenv('AUS');
$pfad = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($pfad === '/' || $pfad === '/index.html') {
    $html = (string)file_get_contents("$R/index.html");
    $html = preg_replace('#<script src="/app\.js[^"]*"></script>#',
        '<script src="/__h/mock.js"></script><script src="/__h/stub.js"></script>$0', $html, 1, $n);
    if ($n !== 1) { http_response_code(500); echo 'app.js-Zeile nicht gefunden'; exit; }
    header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
}
// Rahmen: Chrome ohne Kopf geht nicht unter 500 px; die Seite läuft
// deshalb in einem iframe der gewünschten Breite.
if ($pfad === '/__rahmen') {
    $q = htmlspecialchars((string)($_SERVER['QUERY_STRING'] ?? ''), ENT_QUOTES);
    $b = (int)($_GET['breite'] ?? 390);
    $frag = rawurlencode((string)($_GET['ansicht'] ?? ''));
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><body style='margin:0;background:#888'><iframe src='/?$q#/$frag' "
       . "style='border:0;width:{$b}px;height:844px;background:#fff'></iframe></body>";
    exit;
}
if ($pfad === '/__ergebnis' && $_SERVER['REQUEST_METHOD'] === 'POST' && $AUS !== '') {
    $id = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['id'] ?? 'x'));
    file_put_contents("$AUS/$id.json", (string)file_get_contents('php://input'));
    exit;
}
if (str_starts_with($pfad, '/__h/')) {
    $f = $H . '/' . basename($pfad);
} else {
    $f = realpath($R . $pfad);
    if ($f === false || !str_starts_with($f, $R . '/')) { http_response_code(404); exit; }
}
if (!is_file($f)) { http_response_code(404); exit; }
$typ = ['js' => 'text/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml',
        'png' => 'image/png'][pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
header("Content-Type: $typ; charset=utf-8");
readfile($f);
