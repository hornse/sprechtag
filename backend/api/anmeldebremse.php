<?php
// ============================================================
// anmeldebremse.php – Brute-Force-Bremse und Login-Protokoll
//
// Für jede Route, an der ein WebUntis-Passwort entgegengenommen wird: die
// Anmeldung, die Sondierung und der Stammdaten-Sync (v0.9.93). Bis v0.9.92
// stand die Bremse nur in der Anmelderoute; Sondierung und Sync reichten
// Zugangsdaten ohne Bremse und ohne Protokoll an WebUntis weiter, obwohl
// SONDIERUNG.md beides behauptete. tests/run_anmeldebremse.php ermittelt
// jede Stelle, die authenticate mit übergebenen Zugangsdaten ruft, und
// verlangt, dass sie vorher die Bremse fragt.
//
// Der SQL-Text ist aus der Anmelderoute übernommen, unverändert: Das
// Zeitfenster rechnet die Datenbank (NOW()), wie die Spalte zeitpunkt.
// ============================================================

declare(strict_types=1);

/** Zu viele Fehlversuche für diesen Benutzernamen im Zeitfenster? */
function bremse_greift(PDO $pdo, array $cfg, string $benutzer): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM login_log
         WHERE webuntis_benutzer = ? AND erfolgreich = 0
               AND zeitpunkt >= NOW() - INTERVAL ? MINUTE');
    $st->execute([$benutzer, (int)($cfg['security']['lockout_minutes'] ?? 15)]);
    return (int)$st->fetchColumn() >= (int)($cfg['security']['max_failed_logins'] ?? 5);
}

/** Fehlschlag festhalten – immer: Die Bremse zählt ihn. */
function bremse_fehlschlag(PDO $pdo, string $benutzer, string $grund): void
{
    $pdo->prepare('INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, ip)
                   VALUES (?, 0, ?, ?)')
        ->execute([$benutzer, substr($grund, 0, 190), $_SERVER['REMOTE_ADDR'] ?? '']);
}

/**
 * Erfolg festhalten – nur, wenn die Schule das Login-Protokoll aktiviert und
 * Erfolge ausdrücklich einbezogen hat (Admin-Einstellung, Datenschutz-Absatz
 * der Hilfe). $grund: die Rolle bei der Anmeldung, sonst die Route
 * („sondierung“, „stammdaten-sync“).
 */
function anmeldung_erfolg_merken(PDO $pdo, string $benutzer, string $grund): void
{
    $logAktiv = (int)marke_wert($pdo, 'login_log_aktiv', '0') === 1;
    $logErfolge = (int)marke_wert($pdo, 'login_log_erfolge', '0') === 1;
    if ($logAktiv && $logErfolge) {
        $pdo->prepare('INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, ip)
                       VALUES (?, 1, ?, ?)')
            ->execute([$benutzer, $grund, $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}
