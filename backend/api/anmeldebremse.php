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
// v0.9.107 (E35, Review koordination 14.10.2026, Befund 5):
//   * WARTEZEIT STATT SPERRE, je Benutzername. Bis v0.9.106 sperrte die
//     Bremse nach 5 Fehlversuchen 15 Minuten lang – damit konnte ein Fremder
//     jede Lehrkraft aussperren, deren Kürzel er kennt. Jetzt: ab der
//     Schwelle 5 s Wartezeit nach dem letzten Fehlversuch, je weiterem
//     verdoppelt, gedeckelt (BREMSE_DECKEL, Begründung in E35).
//   * ABGEWIESEN WIRD MIT 429 UND RETRY-AFTER, nicht durch Warten im Prozess:
//     Ein wartender Prozess hielte bei einem einzigen PHP-Prozess alle auf.
//   * ZUERST EINTRAGEN, DANN ZÄHLEN, DANN WEBUNTIS FRAGEN: Der Versuch steht
//     als „(läuft)“ im Protokoll, bevor gezählt wird – gleichzeitige Anfragen
//     sehen einander. Ein abgewiesener Versuch wird wieder entfernt: Sonst
//     verlängerte jeder Klick die Wartezeit.
//   * Die Zeit rechnet PHP aus den Zeitstempeln der Datenbank (eigene Zeile
//     gegen die anderen) – dasselbe SQL läuft in MariaDB und SQLite, und
//     tests/run_bremse.php führt die Bremse aus.
// ============================================================

declare(strict_types=1);

/** Erste Wartezeit an der Schwelle, in Sekunden; je weiterem Fehlversuch verdoppelt. */
const BREMSE_BASIS = 5;
/** Längste Wartezeit in Sekunden (Begründung: docs/ENTSCHEIDUNGEN.md, E35). */
const BREMSE_DECKEL = 60;
/** Grund eines Versuchs, der gerade läuft. */
const BREMSE_LAEUFT = '(läuft)';

/** Sekunden, die noch zu warten sind: $fehl Fehlversuche im Fenster, der letzte vor $seit Sekunden. */
function bremse_warte_sekunden(int $fehl, int $seit, array $cfg): int
{
    $schwelle = (int)($cfg['security']['max_failed_logins'] ?? 5);
    if ($fehl < $schwelle) return 0;
    $stufe = min($fehl - $schwelle, 16);   // 2^16 · 5 s liegt weit über jedem Deckel
    $dauer = min(BREMSE_DECKEL, BREMSE_BASIS * (2 ** $stufe));
    return max(0, $dauer - max(0, $seit));
}

/**
 * Beginnt einen Anmeldeversuch: zuerst eintragen, dann zählen. Muss
 * gewartet werden, wird der Eintrag entfernt und mit 429 geantwortet
 * (Ende des Aufrufs). Rückgabe: die Kennung des Versuchs für
 * bremse_fehlschlag() bzw. anmeldung_erfolg_merken().
 */
function bremse_beginnen(PDO $pdo, array $cfg, string $benutzer): int
{
    $pdo->prepare('INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, ip) VALUES (?, 0, ?, ?)')
        ->execute([$benutzer, BREMSE_LAEUFT, $_SERVER['REMOTE_ADDR'] ?? '']);
    $id = (int)$pdo->lastInsertId();
    $st = $pdo->prepare('SELECT zeitpunkt FROM login_log WHERE id = ?');
    $st->execute([$id]);
    $jetzt = new DateTimeImmutable((string)$st->fetchColumn());
    $fenster = max(1, (int)($cfg['security']['lockout_minutes'] ?? 15));
    $st = $pdo->prepare('SELECT COUNT(*) AS n, MAX(zeitpunkt) AS letzter FROM login_log
                         WHERE webuntis_benutzer = ? AND erfolgreich = 0 AND id <> ? AND zeitpunkt >= ?');
    $st->execute([$benutzer, $id, $jetzt->modify('-' . $fenster . ' minutes')->format('Y-m-d H:i:s')]);
    $z = $st->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 'letzter' => null];
    $warten = 0;
    if ((int)$z['n'] > 0 && $z['letzter'] !== null) {
        $seit = $jetzt->getTimestamp() - (new DateTimeImmutable((string)$z['letzter']))->getTimestamp();
        $warten = bremse_warte_sekunden((int)$z['n'], $seit, $cfg);
    }
    if ($warten > 0) {
        $pdo->prepare('DELETE FROM login_log WHERE id = ?')->execute([$id]);
        if (!headers_sent()) header('Retry-After: ' . $warten);
        json_ok(['fehler' => 'Zu viele Fehlversuche – bitte in ' . $warten . ' Sekunden erneut versuchen.',
                 'warten_sekunden' => $warten], 429);
    }
    return $id;
}

/** Fehlschlag festhalten – immer: Die Bremse zählt ihn. Derselbe Eintrag wie der Versuch. */
function bremse_fehlschlag(PDO $pdo, int $versuch, string $benutzer, string $grund): void
{
    $pdo->prepare('UPDATE login_log SET grund = ? WHERE id = ? AND webuntis_benutzer = ?')
        ->execute([substr($grund, 0, 190), $versuch, $benutzer]);
}

/**
 * Erfolg festhalten – nur, wenn die Schule das Login-Protokoll aktiviert und
 * Erfolge ausdrücklich einbezogen hat (Admin-Einstellung, Datenschutz-Absatz
 * der Hilfe). $grund: die Rolle bei der Anmeldung, sonst die Route
 * („sondierung“, „stammdaten-sync“). Der laufende Eintrag des Versuchs
 * entfällt in jedem Fall – er war kein Fehlschlag.
 */
function anmeldung_erfolg_merken(PDO $pdo, int $versuch, string $benutzer, string $grund): void
{
    $pdo->prepare('DELETE FROM login_log WHERE id = ? AND webuntis_benutzer = ?')->execute([$versuch, $benutzer]);
    $logAktiv = (int)marke_wert($pdo, 'login_log_aktiv', '0') === 1;
    $logErfolge = (int)marke_wert($pdo, 'login_log_erfolge', '0') === 1;
    if ($logAktiv && $logErfolge) {
        $pdo->prepare('INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, ip)
                       VALUES (?, 1, ?, ?)')
            ->execute([$benutzer, $grund, $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}
