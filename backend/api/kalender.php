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

/**
 * Lädt die Buchungen EINER Lehrkraft für einen Sprechtag – für Export/Tischvorlage.
 * Anders als der Eltern-Feed: hier IST der Kindname die zentrale Information.
 */
function kal_lehrer_buchungen(PDO $pdo, int $lehrerId, ?int $sprechtagId = null): array
{
    $sql =
        'SELECT b.id, b.slot_beginn, sp.datum, sp.slot_minuten, sp.name AS sprechtag_name,
                sp.beginn AS tag_beginn, sp.ende AS tag_ende,
                l.name AS lehrer_name, l.kuerzel AS lehrer_kuerzel,
                r.kuerzel AS raum_kuerzel,
                TRIM(CONCAT(COALESCE(s.nachname,""),
                     IF(s.vorname IS NULL OR s.vorname = "", "",
                        CONCAT(", ", s.vorname)))) AS kind_name,
                s.klasse AS kind_klasse
         FROM buchungen b
         JOIN sprechtage sp ON sp.id = b.sprechtag_id
         JOIN lehrer l ON l.id = b.lehrer_id
         LEFT JOIN sprechtag_lehrer sl
                ON sl.sprechtag_id = b.sprechtag_id AND sl.lehrer_id = b.lehrer_id
         LEFT JOIN raeume r ON r.id = sl.raum_id
         LEFT JOIN schueler s ON s.webuntis_id = b.schueler_id
         WHERE b.lehrer_id = ?';
    $args = [$lehrerId];
    if ($sprechtagId !== null) { $sql .= ' AND b.sprechtag_id = ?'; $args[] = $sprechtagId; }
    $sql .= ' ORDER BY sp.datum, b.slot_beginn';
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/**
 * VEVENTs aus Lehrkraft-Sicht: Titel „Sprechtag: <Kind>", damit die Lehrkraft
 * im eigenen Kalender sofort sieht, um welches Kind es geht.
 */
function kal_vevents_lehrer(array $buchungen): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'sprechtag';
    $jetzt = gmdate('Ymd\THis\Z');
    $out = '';
    foreach ($buchungen as $b) {
        $von = substr((string)$b['slot_beginn'], 0, 5);
        $laenge = max(1, (int)($b['slot_minuten'] ?? 10));
        [$jh, $mo, $tg] = array_map('intval', explode('-', (string)$b['datum']));
        [$sh, $sm] = array_map('intval', explode(':', $von));
        $startTs = mktime($sh, $sm, 0, $mo, $tg, $jh);
        $endeTs  = $startTs + $laenge * 60;

        $kind = trim((string)($b['kind_name'] ?: ''));
        $klasse = trim((string)($b['kind_klasse'] ?? ''));
        $raum = trim((string)($b['raum_kuerzel'] ?? ''));
        $titel = 'Sprechtag: ' . ($kind !== '' ? $kind : 'Termin')
               . ($klasse !== '' ? ' (' . $klasse . ')' : '');

        $lines = [
            'BEGIN:VEVENT',
            'UID:lehrer-buchung-' . (int)$b['id'] . '@' . $host,
            'DTSTAMP:' . $jetzt,
            'DTSTART:' . date('Ymd\THis', $startTs),
            'DTEND:'   . date('Ymd\THis', $endeTs),
            'SUMMARY:' . kal_escape($titel),
        ];
        if ($raum !== '') $lines[] = 'LOCATION:' . kal_escape($raum);
        $lines[] = 'END:VEVENT';
        foreach ($lines as $l) $out .= kal_falten($l) . "\r\n";
    }
    return $out;
}

/** Abo-Token einer Lehrkraft (eigene Tabelle-Nutzung mit negativer Kennung). */
function kal_lehrer_token_holen(PDO $pdo, int $lehrerId): string
{
    // Lehrkräfte teilen sich die kalender_abo-Tabelle; zur Unterscheidung von
    // Eltern-user.id nutzen wir einen eigenen Präfix-Schlüsselraum: die Spalte
    // eltern_user_id speichert für Lehrkräfte den Wert (1000000000 + lehrer_id).
    $key = 1000000000 + $lehrerId;
    return kal_token_holen($pdo, $key);
}

function kal_lehrer_token_neu(PDO $pdo, int $lehrerId): string
{
    return kal_token_neu($pdo, 1000000000 + $lehrerId);
}

/** Prüft, ob ein Token zu einer Lehrkraft gehört, und gibt die lehrer_id zurück. */
function kal_lehrer_aus_token(PDO $pdo, string $token): ?int
{
    $st = $pdo->prepare('SELECT eltern_user_id FROM kalender_abo WHERE token = ?');
    $st->execute([$token]);
    $key = $st->fetchColumn();
    if ($key === false) return null;
    $key = (int)$key;
    return $key >= 1000000000 ? $key - 1000000000 : null;
}

/**
 * Baut eine druckbare HTML-Tischvorlage der Termine einer Lehrkraft für einen
 * Sprechtag. Bewusst als HTML (kein PDF-Modul): Die Lehrkraft druckt über den
 * Browser bzw. speichert als PDF. Enthält freie Slots als Lücken, damit man
 * den Ablauf sieht.
 */
function kal_tischvorlage_html(array $kopf, array $zeilen): string
{
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $titel = $h($kopf['lehrer'] . ' – ' . $kopf['sprechtag'] . ' (' . $kopf['datum'] . ')');

    $rows = '';
    foreach ($zeilen as $z) {
        $frei = empty($z['kind_name']);
        $klasse = $frei ? ' class="frei"' : '';
        $kind = $frei ? '<span class="leer">frei</span>' : $h($z['kind_name']);
        $kl = $h($z['kind_klasse'] ?? '');
        $rows .= '<tr' . $klasse . '><td class="z">' . $h(substr((string)$z['slot_beginn'], 0, 5))
              . '</td><td>' . $kind . '</td><td>' . $kl . '</td><td class="n"></td></tr>';
    }

    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
      . '<title>' . $titel . '</title><style>'
      . 'body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;margin:2cm;color:#111}'
      . 'h1{font-size:18pt;margin:0 0 .2cm}h2{font-size:12pt;font-weight:400;color:#555;margin:0 0 .6cm}'
      . 'table{width:100%;border-collapse:collapse;font-size:11pt}'
      . 'th,td{border:1px solid #bbb;padding:.25cm .3cm;text-align:left;vertical-align:top}'
      . 'th{background:#f0f0f0;font-size:10pt}'
      . 'td.z{width:2.5cm;font-weight:600}td.n{width:6cm}'
      . 'tr.frei td{color:#999}.leer{font-style:italic}'
      . '.raum{font-size:11pt;color:#333;margin:0 0 .4cm}'
      . '@media print{body{margin:1.2cm}.druck{display:none}}'
      . '.druck{margin:.6cm 0;padding:.3cm .6cm;font-size:11pt;cursor:pointer}'
      . '</style></head><body>'
      . '<h1>' . $h($kopf['lehrer']) . '</h1>'
      . '<h2>' . $h($kopf['sprechtag']) . ' · ' . $h($kopf['datum'])
      . ' · ' . $h($kopf['zeit']) . '</h2>'
      . ($kopf['raum'] !== '' ? '<p class="raum">Raum: <strong>' . $h($kopf['raum']) . '</strong></p>' : '')
      . '<button class="druck" onclick="window.print()">Drucken / als PDF speichern</button>'
      . '<table><thead><tr><th>Zeit</th><th>Kind</th><th>Klasse</th><th>Notizen</th></tr></thead>'
      . '<tbody>' . $rows . '</tbody></table>'
      . '</body></html>';
}

/** Sendet eine HTML-Seite und beendet das Skript. */
function kal_html_ausliefern(string $html): never
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    echo $html;
    exit;
}
