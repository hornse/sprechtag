<?php
// ============================================================
// kalender.php – iCal-Export (Abo-Link + Einzeldatei)
//
// Datenschutz:
//  - Der Abo-Link nutzt einen zufälligen Token statt der WebUntis user.id.
//  - Im Kalendereintrag stehen nur: Lehrkraft, Raum, betroffenes Kind.
//    Keine Elternnamen, keine fremden Buchungen.
//  - Der Token ist geheim (wer ihn hat, sieht die eigenen Termine) und
//    lässt sich neu erzeugen.
// ============================================================

declare(strict_types=1);

/** Basis-URL der Anwendung (Schema + Host), ohne abschließenden Slash. */
function kal_basis_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return 'https://' . $host;
}

/** Holt den Abo-Token eines Elternkontos, erzeugt ihn bei Bedarf. */
function kal_token_holen(PDO $pdo, int $elternUserId): string
{
    $st = $pdo->prepare('SELECT token FROM kalender_abo WHERE eltern_user_id = ?');
    $st->execute([$elternUserId]);
    $token = $st->fetchColumn();
    if ($token) return (string)$token;
    return kal_token_neu($pdo, $elternUserId);
}

/** Erzeugt einen neuen Token (ersetzt einen bestehenden -> alter Link ungültig). */
function kal_token_neu(PDO $pdo, int $elternUserId): string
{
    $token = bin2hex(random_bytes(24));   // 48 Hex-Zeichen
    $pdo->prepare('INSERT INTO kalender_abo (eltern_user_id, token)
                   VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE token = VALUES(token),
                                           erstellt_am = CURRENT_TIMESTAMP')
        ->execute([$elternUserId, $token]);
    return $token;
}

/** Escapet einen Text für iCal (RFC 5545). */
function kal_escape(string $s): string
{
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace(["\r\n", "\n", "\r"], '\\n', $s);
    $s = str_replace([',', ';'], ['\\,', '\\;'], $s);
    return $s;
}

/** Faltet lange iCal-Zeilen auf 75 Oktetts (RFC 5545). */
function kal_falten(string $zeile): string
{
    if (strlen($zeile) <= 73) return $zeile;
    $teile = [];
    $rest = $zeile;
    $teile[] = substr($rest, 0, 73);
    $rest = substr($rest, 73);
    while (strlen($rest) > 0) {
        $teile[] = ' ' . substr($rest, 0, 72);
        $rest = substr($rest, 72);
    }
    return implode("\r\n", $teile);
}

/**
 * Baut die VEVENT-Blöcke für eine Menge Buchungen.
 * $buchungen: Zeilen mit datum, slot_beginn, slot_minuten, lehrer_name,
 *             lehrer_kuerzel, raum_kuerzel, kind_name, id, sprechtag_name.
 */
function kal_vevents(array $buchungen): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'sprechtag';
    $jetzt = gmdate('Ymd\THis\Z');
    $out = '';
    foreach ($buchungen as $b) {
        $datum = (string)$b['datum'];                 // YYYY-MM-DD
        $von   = substr((string)$b['slot_beginn'], 0, 5);
        $laenge = max(1, (int)($b['slot_minuten'] ?? 10));
        [$jh, $mo, $tg] = array_map('intval', explode('-', $datum));
        [$sh, $sm] = array_map('intval', explode(':', $von));
        $startTs = mktime($sh, $sm, 0, $mo, $tg, $jh);
        $endeTs  = $startTs + $laenge * 60;

        $lehrer = trim((string)($b['lehrer_name'] ?: $b['lehrer_kuerzel']));
        $raum   = trim((string)($b['raum_kuerzel'] ?? ''));
        $kind   = trim((string)($b['kind_name'] ?? ''));

        $titel = 'Sprechtag: ' . $lehrer;
        $beschr = [];
        if ($kind !== '')   $beschr[] = 'Kind: ' . $kind;
        if ($raum !== '')   $beschr[] = 'Raum: ' . $raum;
        $uid = 'buchung-' . (int)$b['id'] . '@' . $host;

        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $jetzt,
            'DTSTART:' . date('Ymd\THis', $startTs),
            'DTEND:'   . date('Ymd\THis', $endeTs),
            'SUMMARY:' . kal_escape($titel),
        ];
        if ($beschr) $lines[] = 'DESCRIPTION:' . kal_escape(implode(' · ', $beschr));
        if ($raum !== '') $lines[] = 'LOCATION:' . kal_escape($raum);
        $lines[] = 'END:VEVENT';
        foreach ($lines as $l) $out .= kal_falten($l) . "\r\n";
    }
    return $out;
}

/** Umschließt VEVENTs mit dem VCALENDAR-Rahmen. */
function kal_kalender(string $vevents, string $name): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'sprechtag';
    $k  = "BEGIN:VCALENDAR\r\n";
    $k .= "VERSION:2.0\r\n";
    $k .= 'PRODID:-//' . kal_falten($host) . "//sprechtag//DE\r\n";
    $k .= "CALSCALE:GREGORIAN\r\n";
    $k .= "METHOD:PUBLISH\r\n";
    $k .= 'X-WR-CALNAME:' . kal_escape($name) . "\r\n";
    $k .= $vevents;
    $k .= "END:VCALENDAR\r\n";
    return $k;
}

/**
 * Lädt die (zukünftigen) Buchungen eines Elternkontos für den iCal-Export.
 * $nurBuchung: optionale einzelne Buchungs-ID (für die Einzeldatei).
 */
function kal_buchungen_laden(PDO $pdo, int $elternUserId, ?int $nurBuchung = null): array
{
    $sql =
        'SELECT b.id, b.slot_beginn, sp.datum, sp.slot_minuten, sp.name AS sprechtag_name,
                l.name AS lehrer_name, l.kuerzel AS lehrer_kuerzel,
                r.kuerzel AS raum_kuerzel,
                TRIM(CONCAT(COALESCE(s.nachname,""),
                     IF(s.vorname IS NULL OR s.vorname = "", "",
                        CONCAT(", ", s.vorname)))) AS kind_name
         FROM buchungen b
         JOIN sprechtage sp ON sp.id = b.sprechtag_id
         JOIN lehrer l ON l.id = b.lehrer_id
         LEFT JOIN sprechtag_lehrer sl
                ON sl.sprechtag_id = b.sprechtag_id AND sl.lehrer_id = b.lehrer_id
         LEFT JOIN raeume r ON r.id = sl.raum_id
         LEFT JOIN schueler s ON s.webuntis_id = b.schueler_id
         WHERE b.eltern_user_id = ?';
    $args = [$elternUserId];
    if ($nurBuchung !== null) {
        $sql .= ' AND b.id = ?';
        $args[] = $nurBuchung;
    }
    $sql .= ' ORDER BY sp.datum, b.slot_beginn';
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/** Sendet eine .ics-Datei und beendet das Skript. */
function kal_ausliefern(string $ics, string $dateiname): never
{
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $dateiname . '"');
    header('Cache-Control: no-cache');
    echo $ics;
    exit;
}
