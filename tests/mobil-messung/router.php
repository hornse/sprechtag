<?php
// ============================================================
// tests/mobil-messung/router.php – liefert die echte Oberfläche mit
// Ersatz-API (mock.js, stub.js) für die Breitenmessung. Nur lokal.
//   FRONTEND = Verzeichnis der Oberfläche (Vorgabe: frontend/ im Repo)
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================
declare(strict_types=1);

// realpath: sonst scheitert die Präfixprüfung unten an einem doppelten
// Schrägstrich (TMPDIR endet auf „/“), und alles kommt als 404 – die
// Messung sähe dann eine leere Seite und meldete „nichts ragt über“.
$R = realpath((string)(getenv('FRONTEND') ?: dirname(__DIR__, 2) . '/frontend'));
if ($R === false) { http_response_code(500); echo 'FRONTEND nicht gefunden'; exit; }
$H = __DIR__;
$pfad = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($pfad === '/' || $pfad === '/index.html') {
    $html = (string)file_get_contents("$R/index.html");
    $html = preg_replace('#<script src="/app\.js[^"]*"></script>#',
        '<script src="/__h/mock.js"></script><script src="/__h/stub.js"></script>$0', $html, 1, $n);
    if ($n !== 1) { http_response_code(500); echo 'app.js-Zeile nicht gefunden'; exit; }
    header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
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
