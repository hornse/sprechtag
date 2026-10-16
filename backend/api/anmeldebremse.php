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
//     Schwelle eine Wartezeit nach dem letzten Fehlversuch (BREMSE_DECKEL,
//     Begründung in E35).
//   * v0.9.109: ab der Schwelle GLEICH der Deckel. Bis dahin 5 s, je weiterem
//     verdoppelt – im Tempo einer Person nie zu spüren: Bei der Abnahme vom
//     14.10.2026 kam auch der siebte Versuch von Hand ohne Wartezeit durch.
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

/** Wartezeit ab der Schwelle in Sekunden, nach dem letzten Fehlversuch (Begründung: docs/ENTSCHEIDUNGEN.md, E35). */
const BREMSE_DECKEL = 60;
/** Grund eines Versuchs, der gerade läuft. */
const BREMSE_LAEUFT = '(läuft)';
/**
 * Grund des Vermerks einer gelungenen Anmeldung (E37). Er ist ein Zähler,
 * kein Protokolleintrag: ohne Rolle, nie in login_log_eintraege(), und nach
 * dem Zählfenster gelöscht. Ab seiner Kennung zählt die Bremse neu.
 */
const BREMSE_ZURUECK = '(zurückgesetzt)';
/** Satz, wenn die Zählung scheitert – unterscheidbar von „Passwort falsch“ (E37). */
const BREMSE_OHNE_ZAEHLUNG = 'Die Anmeldung ist gerade nicht möglich – die Prüfung der '
    . 'Anmeldeversuche antwortet nicht. Bitte in ein paar Minuten noch einmal.';

/** Sekunden, die noch zu warten sind: $fehl Fehlversuche im Fenster, der letzte vor $seit Sekunden. */
function bremse_warte_sekunden(int $fehl, int $seit, array $cfg): int
{
    $schwelle = (int)($cfg['security']['max_failed_logins'] ?? 5);
    if ($fehl < $schwelle) return 0;
    return max(0, BREMSE_DECKEL - max(0, $seit));
}

/**
 * Beginnt einen Anmeldeversuch: zuerst eintragen, dann zählen. Muss
 * gewartet werden, wird der Eintrag entfernt und mit 429 geantwortet
 * (Ende des Aufrufs). Rückgabe: die Kennung des Versuchs für
 * bremse_fehlschlag() bzw. anmeldung_erfolg_merken().
 *
 * E37: Gezählt wird erst ab der Kennung der letzten gelungenen Anmeldung –
 * nach der Nummer, nicht nach der Uhrzeit, denn zwei Zeilen können dieselbe
 * Sekunde tragen. Zurücksetzen kann nur, wer das Passwort kennt. Scheitert
 * die Zählung, wird abgewiesen, mit eigenem Satz und 503.
 */
function bremse_beginnen(PDO $pdo, array $cfg, string $benutzer): int
{
    $id = 0;
    // NUR BETRIEBSFEHLER (FALLSTRICKE 3): Ein Programmierfehler (Error) endet
    // weiter als 500 – geschlossen ist beides.
    try {
        // Keine Adresse (E36): REMOTE_ADDR ist hinter dem Proxy dessen Adresse.
        $pdo->prepare('INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund) VALUES (?, 0, ?)')
            ->execute([$benutzer, BREMSE_LAEUFT]);
        $id = (int)$pdo->lastInsertId();
        $st = $pdo->prepare('SELECT zeitpunkt FROM login_log WHERE id = ?');
        $st->execute([$id]);
        $jetzt = new DateTimeImmutable((string)$st->fetchColumn());
        $fenster = max(1, (int)($cfg['security']['lockout_minutes'] ?? 15));
        $ab = $jetzt->modify('-' . $fenster . ' minutes')->format('Y-m-d H:i:s');
        // Der Vermerk hält höchstens das Zählfenster (E37).
        $pdo->prepare('DELETE FROM login_log WHERE grund = ? AND zeitpunkt < ?')->execute([BREMSE_ZURUECK, $ab]);
        $st = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM login_log WHERE webuntis_benutzer = ? AND erfolgreich = 1');
        $st->execute([$benutzer]);
        $zurueck = (int)$st->fetchColumn();
        $st = $pdo->prepare('SELECT COUNT(*) AS n, MAX(zeitpunkt) AS letzter FROM login_log
                             WHERE webuntis_benutzer = ? AND erfolgreich = 0 AND id <> ? AND id > ? AND zeitpunkt >= ?');
        $st->execute([$benutzer, $id, $zurueck, $ab]);
        $z = $st->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 'letzter' => null];
        $warten = 0;
        if ((int)$z['n'] > 0 && $z['letzter'] !== null) {
            $seit = $jetzt->getTimestamp() - (new DateTimeImmutable((string)$z['letzter']))->getTimestamp();
            $warten = bremse_warte_sekunden((int)$z['n'], $seit, $cfg);
        }
    } catch (Exception $e) {
        // Der Grund ins Server-Log, ohne Benutzernamen.
        error_log('sprechtag: Bremse – Zählung gescheitert, Anmeldung abgewiesen ('
            . get_class($e) . ' ' . $e->getCode() . ')');
        if ($id > 0) {
            try {
                $pdo->prepare('DELETE FROM login_log WHERE id = ?')->execute([$id]);
            } catch (Exception $e2) {
                error_log('sprechtag: Bremse – abgewiesener Versuch nicht entfernt (' . get_class($e2) . ')');
            }
        }
        json_err(BREMSE_OHNE_ZAEHLUNG, 503);
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
 * Versuch ohne Entscheid beenden: WebUntis hat nicht geantwortet, kein
 * Passwort hat entschieden (v0.9.109, Entscheidung Betreiber 15.10.2026). Der
 * laufende Eintrag entfällt und zählt nicht – die Bremse bremst das Raten,
 * und zählte ein Ausfall, warteten Lehrkräfte für ihn.
 */
function bremse_ohne_entscheid(PDO $pdo, int $versuch, string $benutzer): void
{
    $pdo->prepare('DELETE FROM login_log WHERE id = ? AND webuntis_benutzer = ? AND grund = ?')
        ->execute([$versuch, $benutzer, BREMSE_LAEUFT]);
}

/**
 * Erfolg festhalten – nur, wenn die Schule das Login-Protokoll aktiviert und
 * Erfolge ausdrücklich einbezogen hat (Admin-Einstellung, Datenschutz-Absatz
 * der Hilfe). $grund: die Rolle bei der Anmeldung, sonst die Route
 * („sondierung“, „stammdaten-sync“).
 *
 * E37: Der laufende Eintrag wird nicht mehr gelöscht, sondern zur gelungenen
 * Anmeldung – sie setzt die Zählung der Bremse zurück. Mit Erfolgsprotokoll
 * trägt er die Rolle; ohne bleibt er als Vermerk „(zurückgesetzt)“ stehen,
 * ohne Rolle, außerhalb des Protokolls und höchstens für das Zählfenster.
 * Die Einstellung der Schule bleibt gewahrt.
 */
function anmeldung_erfolg_merken(PDO $pdo, int $versuch, string $benutzer, string $grund): void
{
    $logAktiv = (int)marke_wert($pdo, 'login_log_aktiv', '0') === 1;
    $logErfolge = (int)marke_wert($pdo, 'login_log_erfolge', '0') === 1;
    $pdo->prepare('UPDATE login_log SET erfolgreich = 1, grund = ? WHERE id = ? AND webuntis_benutzer = ?')
        ->execute([($logAktiv && $logErfolge) ? $grund : BREMSE_ZURUECK, $versuch, $benutzer]);
}

/**
 * Das Protokoll für die Verwaltung (E37 aus der Route gezogen, damit es
 * ausgeführt geprüft werden kann): neueste zuerst, optional gefiltert nach
 * Benutzername – OHNE die Vermerke der Bremse.
 */
function login_log_eintraege(PDO $pdo, string $filter, int $limit): array
{
    $sql = 'SELECT webuntis_benutzer, erfolgreich, grund, zeitpunkt FROM login_log WHERE grund <> ?'
         . ($filter !== '' ? ' AND webuntis_benutzer LIKE ?' : '')
         . ' ORDER BY zeitpunkt DESC LIMIT ?';
    $st = $pdo->prepare($sql);
    $n = 1;
    $st->bindValue($n++, BREMSE_ZURUECK);
    if ($filter !== '') $st->bindValue($n++, '%' . $filter . '%');
    $st->bindValue($n, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
