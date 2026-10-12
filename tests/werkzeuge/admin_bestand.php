<?php
// ============================================================
// tests/werkzeuge/admin_bestand.php – Bestand der Administratoren, nur Zahlen
// (v0.9.98, E29)
//
// Aufruf auf dem Server, im Projektordner:  php tests/werkzeuge/admin_bestand.php
//
// Beantwortet die Frage des Betreibers, ob es im Betrieb Kürzel gibt, die in
// admin_kuerzel UND in app_admins stehen (dann zeigt die Oberfläche sie als
// Notzugang mit entfernbarem Tabelleneintrag) und ob app_admins Einträge
// ohne Stammsatz hat (die POST seit v0.9.98 ablehnt). Liest nur; zählt mit
// derselben Funktion wie die Oberfläche (adm_liste). Gibt keine Kürzel und
// keine Namen aus.
//
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================

declare(strict_types=1);

$wurzel = dirname(__DIR__, 2);
$cfgDatei = $wurzel . '/backend/config.php';
if (!is_file($cfgDatei)) { fwrite(STDERR, "FEHLER: backend/config.php fehlt – im Projektordner aufrufen\n"); exit(2); }
$cfg = require $cfgDatei;

require_once $wurzel . '/backend/helfer.php';
require_once $wurzel . '/backend/api/webuntis_adapter.php';
require_once $wurzel . '/backend/api/admins.php';

$pdo = new PDO($cfg['db']['dsn'], $cfg['db']['benutzer'], $cfg['db']['passwort'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$liste = adm_liste($pdo, $cfg['webuntis'] ?? [], null);
$zahl = fn(callable $f): int => count(array_filter($liste, $f));
$tabelle = count(adm_tabelle($pdo));

echo "Notzugang (admin_kuerzel in config.php):  " . $zahl(fn($e) => $e['herkunft'] === 'notzugang') . "\n";
echo "  davon ohne Stammsatz:                     " . $zahl(fn($e) => $e['herkunft'] === 'notzugang' && $e['ohne_stammsatz']) . "\n";
echo "Einträge in app_admins:                    $tabelle\n";
echo "  davon zugleich Notzugang (Doppel):        " . $zahl(fn($e) => $e['auch_oberflaeche']) . "\n";
echo "  davon nur Oberfläche:                     " . $zahl(fn($e) => $e['herkunft'] === 'oberflaeche') . "\n";
echo "  davon nur Oberfläche, ohne Stammsatz:     " . $zahl(fn($e) => $e['herkunft'] === 'oberflaeche' && $e['ohne_stammsatz']) . "\n";
// Gegenprobe der Zählung: Jeder Tabelleneintrag erscheint genau einmal –
// entweder als Doppel beim Notzugang oder als Oberfläche. Mehrere Einträge
// zu demselben Notzugang-Kürzel (nur in der Schreibweise verschieden) ließe
// das Schema nicht zu (eindeutiger Schlüssel, unempfindliche Kollation).
$gezaehlt = $zahl(fn($e) => $e['auch_oberflaeche']) + $zahl(fn($e) => $e['herkunft'] === 'oberflaeche');
echo $gezaehlt === $tabelle ? "Gegenprobe: Summe stimmt ($gezaehlt)\n"
    : "WIDERSPRUCH: $gezaehlt gezählt, $tabelle in der Tabelle\n";
