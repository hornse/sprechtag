<?php
// ============================================================
// mitteilungen.php – WebUntis-Mitteilungen an Erziehungsberechtigte
//
// STAND DER ERKENNTNIS (Sondierung 07/2026):
//   * GET /api/rest/view/v1/messages          -> 200 (Eltern und Lehrkräfte)
//   * GET /api/rest/view/v1/messages/status   -> 200
//   * GET /api/rest/view/v1/messages/recipients
//         -> 400 "parameter 'recipients' is no valid value for the
//            expected type: 'class java.lang.Long'"
//     => Endpunkt existiert und erwartet eine USER-ID (Long), nicht personId.
//   * Der VERSANDWEG (POST) ist NICHT dokumentiert und war zum Zeitpunkt
//     der Entwicklung nicht erprobbar.
//
// DESHALB: Der Versand probiert mehrere plausible Feldstrukturen durch
// und meldet ehrlich, welche (falls überhaupt) funktioniert hat. Die
// erfolgreiche Variante wird in den Einstellungen gemerkt, sodass ab
// dann direkt der richtige Weg genommen wird.
//
// FALLBACK: Schlägt der Versand fehl, wird die Mitteilung in der
// Tabelle `mitteilungen` als 'offen' gespeichert. Die Lehrkraft sieht
// sie in ihrer Ansicht und kann sie manuell in WebUntis versenden –
// der Termin ist trotzdem korrekt storniert.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../helfer.php';
require_once __DIR__ . '/../auth/WebUntisAuth.php';
require_once __DIR__ . '/../auth/WebUntisRest.php';

/**
 * Kandidaten-Varianten für den Versand. Jede beschreibt Pfad und eine
 * Funktion, die den Body baut. Reihenfolge = Reihenfolge der Versuche.
 *
 * Reine Funktion (keine Netzzugriffe) – offline testbar.
 */
function mit_varianten(): array
{
    return [
        'v1_recipients_ids' => [
            'pfad' => '/WebUntis/api/rest/view/v1/messages',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'    => $betreff,
                'content'    => $text,
                'recipients' => [$empfaenger],
            ],
        ],
        'v1_recipients_objekte' => [
            'pfad' => '/WebUntis/api/rest/view/v1/messages',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'    => $betreff,
                'content'    => $text,
                'recipients' => [['userId' => $empfaenger]],
            ],
        ],
        'v1_recipientoption' => [
            'pfad' => '/WebUntis/api/rest/view/v1/messages',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'          => $betreff,
                'content'          => $text,
                'recipientOption'  => 'SPECIFIC',
                'recipientUserIds' => [$empfaenger],
            ],
        ],
        // Befund 07/2026: /messages/recipients erwartet einen Long –
        // vermutlich adressiert die API Empfänger über 'recipientIds'.
        'v1_recipientids' => [
            'pfad' => '/WebUntis/api/rest/view/v1/messages',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'      => $betreff,
                'content'      => $text,
                'recipientIds' => [$empfaenger],
            ],
        ],
        // Manche Untis-Endpunkte kapseln die Nutzlast in 'message'
        'v1_message_wrapper' => [
            'pfad' => '/WebUntis/api/rest/view/v1/messages',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'message' => [
                    'subject'    => $betreff,
                    'content'    => $text,
                    'recipients' => [['id' => $empfaenger, 'type' => 'USER']],
                ],
            ],
        ],
        // Vollständigeres Objekt mit den Feldern, die GET /messages zeigt
        'v1_voll' => [
            'pfad' => '/WebUntis/api/rest/view/v1/messages',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'            => $betreff,
                'content'            => $text,
                'recipients'         => [['userId' => $empfaenger,
                                          'displayName' => '']],
                'isReplyAllowed'     => false,
                'requestReadConfirmation' => false,
                'hasAttachments'     => false,
            ],
        ],
        'v2_messages' => [
            'pfad' => '/WebUntis/api/rest/view/v2/messages',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'    => $betreff,
                'content'    => $text,
                'recipients' => [['id' => $empfaenger, 'type' => 'USER']],
            ],
        ],
    ];
}

/**
 * Bewertet eine POST-Antwort. Reine Funktion – offline testbar.
 *
 * Rückgabe: ['erfolg' => bool, 'endgueltig' => bool, 'grund' => string]
 *   endgueltig = true bedeutet: weitere Varianten sind sinnlos
 *   (z. B. 401/403 – Rechteproblem, nicht Strukturproblem).
 */
function mit_antwort_bewerten(array $antwort): array
{
    $status = (int)($antwort['status'] ?? 0);

    if ($status >= 200 && $status < 300) {
        return ['erfolg' => true, 'endgueltig' => true, 'grund' => 'HTTP ' . $status];
    }
    if ($status === 401 || $status === 403) {
        return ['erfolg' => false, 'endgueltig' => true,
                'grund' => 'Keine Berechtigung zum Versenden von Mitteilungen (HTTP '
                    . $status . '). Bitte in WebUntis das Recht "Mitteilungen senden" prüfen.'];
    }
    if ($status === 0) {
        return ['erfolg' => false, 'endgueltig' => true,
                'grund' => 'WebUntis nicht erreichbar'];
    }

    // 400/404/405/415/500 -> andere Feldstruktur probieren
    $detail = '';
    if (is_array($antwort['json'] ?? null)) {
        $detail = (string)($antwort['json']['errorMessage']
            ?? $antwort['json']['message'] ?? '');
        if ($detail === '' && isset($antwort['json']['validationErrors'])) {
            $detail = json_encode($antwort['json']['validationErrors'],
                JSON_UNESCAPED_UNICODE) ?: '';
        }
    }
    return ['erfolg' => false, 'endgueltig' => false,
            'grund' => 'HTTP ' . $status . ($detail !== '' ? ': ' . $detail : '')];
}

/**
 * Versucht den Versand einer Mitteilung.
 *
 * $bevorzugt: zuvor erfolgreiche Variante (aus den Einstellungen) – wird
 * zuerst probiert, damit im Normalbetrieb nur EIN Aufruf nötig ist.
 *
 * Rückgabe: ['ok' => bool, 'variante' => string|null, 'grund' => string,
 *            'versuche' => [['variante' => …, 'grund' => …], …]]
 */
function mit_senden(WebUntisRest $rest, int $empfaengerUserId,
                    string $betreff, string $text, ?string $bevorzugt = null): array
{
    $varianten = mit_varianten();

    // Bevorzugte Variante nach vorn sortieren
    if ($bevorzugt !== null && isset($varianten[$bevorzugt])) {
        $varianten = [$bevorzugt => $varianten[$bevorzugt]] + $varianten;
    }

    $versuche = [];
    foreach ($varianten as $name => $v) {
        $antwort  = $rest->post($v['pfad'], ($v['body'])($empfaengerUserId, $betreff, $text));
        $bewertet = mit_antwort_bewerten($antwort);
        $versuche[] = ['variante' => $name, 'grund' => $bewertet['grund']];

        if ($bewertet['erfolg']) {
            return ['ok' => true, 'variante' => $name,
                    'grund' => $bewertet['grund'], 'versuche' => $versuche];
        }
        if ($bewertet['endgueltig']) {
            return ['ok' => false, 'variante' => null,
                    'grund' => $bewertet['grund'], 'versuche' => $versuche];
        }
    }

    // Alle Fehlermeldungen aufnehmen – nur so ist später erkennbar,
    // WARUM jede Variante abgelehnt wurde. Eine reine Sammelmeldung
    // ("keine akzeptiert") hilft bei der Diagnose nicht weiter.
    $details = [];
    foreach ($versuche as $v) {
        $details[] = $v['variante'] . ': ' . $v['grund'];
    }
    return ['ok' => false, 'variante' => null,
            'grund' => 'Keine Feldstruktur akzeptiert. ' . implode(' | ', $details),
            'versuche' => $versuche];
}

/**
 * Baut Betreff und Text einer Terminbestätigung.
 * Reine Funktion – offline testbar.
 */
function mit_text_bestaetigung(string $sprechtagName, string $datum,
                               array $termine): array
{
    $zeilen = [];
    foreach ($termine as $t) {
        $zeilen[] = '- ' . substr((string)($t['slot_beginn'] ?? ''), 0, 5) . ' Uhr: '
            . (string)($t['name'] ?? $t['kuerzel'] ?? '')
            . (($t['raum_kuerzel'] ?? '') !== '' ? ' (Raum ' . $t['raum_kuerzel'] . ')' : '');
    }
    $text = "Guten Tag,\n\n"
        . "hiermit bestätigen wir Ihre Termine für den " . $sprechtagName
        . " am " . mit_datum_deutsch($datum) . ":\n\n"
        . implode("\n", $zeilen)
        . "\n\nBitte finden Sie sich einige Minuten vorher am jeweiligen Raum ein.\n\n"
        . "Mit freundlichen Grüßen\n"
        . "Friedrich-Rückert-Gymnasium Düsseldorf";

    return ['betreff' => 'Ihre Termine: ' . $sprechtagName, 'text' => $text];
}

/**
 * Baut Betreff und Text einer Absage.
 * $freitext: optionale eigene Nachricht der Lehrkraft.
 */
function mit_text_absage(string $sprechtagName, string $datum, string $zeit,
                         string $lehrkraft, string $freitext = ''): array
{
    $text = "Guten Tag,\n\n"
        . "leider muss Ihr Termin am " . mit_datum_deutsch($datum)
        . " um " . substr($zeit, 0, 5) . " Uhr bei " . $lehrkraft
        . " (" . $sprechtagName . ") entfallen.\n";

    if (trim($freitext) !== '') {
        $text .= "\n" . trim($freitext) . "\n";
    }

    $text .= "\nSofern noch Termine frei sind, können Sie über das "
        . "Buchungsportal einen neuen Termin wählen.\n\n"
        . "Mit freundlichen Grüßen\n" . $lehrkraft;

    return ['betreff' => 'Terminabsage: ' . $sprechtagName, 'text' => $text];
}

/** "2026-11-20" -> "20.11.2026"; unbekannte Formate bleiben unverändert. */
function mit_datum_deutsch(string $iso): string
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
        return $m[3] . '.' . $m[2] . '.' . $m[1];
    }
    return $iso;
}

/**
 * Schreibt eine Mitteilung in die Warteschlange und versucht den Versand,
 * sofern Zugangsdaten übergeben wurden. Ohne Zugangsdaten bleibt sie
 * 'offen' und kann später gesammelt versendet werden.
 *
 * Rückgabe: ['id' => int, 'status' => 'gesendet'|'offen'|'fehler', 'grund' => string]
 */
function mit_einreihen_und_senden(
    array $cfg, PDO $pdo, int $sprechtagId, int $empfaengerUserId,
    string $anlass, string $betreff, string $text,
    ?string $benutzer = null, ?string $passwort = null
): array {
    $pdo->prepare('INSERT INTO mitteilungen
        (sprechtag_id, empfaenger_user_id, anlass, betreff, text, status)
        VALUES (?, ?, ?, ?, ?, "offen")')
        ->execute([$sprechtagId, $empfaengerUserId, $anlass,
            kuerze($betreff, 190), $text]);
    $id = (int)$pdo->lastInsertId();

    if ($benutzer === null || $passwort === null || $benutzer === '' || $passwort === '') {
        return ['id' => $id, 'status' => 'offen',
                'grund' => 'Kein Versand angefordert – Mitteilung vorgemerkt.'];
    }

    $ergebnis = mit_versand_ausfuehren($cfg, $pdo, [$id], $benutzer, $passwort);
    return ['id' => $id,
            'status' => $ergebnis['gesendet'] > 0 ? 'gesendet' : 'fehler',
            'grund'  => $ergebnis['grund']];
}

/**
 * Versendet offene Mitteilungen. Öffnet EINE WebUntis-Session für alle.
 *
 * Rückgabe: ['gesendet' => int, 'fehler' => int, 'grund' => string,
 *            'variante' => string|null, 'protokoll' => [...]]
 */
function mit_versand_ausfuehren(array $cfg, PDO $pdo, array $ids,
                                string $benutzer, string $passwort): array
{
    if ($ids === []) {
        return ['gesendet' => 0, 'fehler' => 0, 'grund' => 'Nichts zu senden.',
                'variante' => null, 'protokoll' => []];
    }

    $wcfg = $cfg['webuntis'];
    $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);
    $gesendet = 0; $fehlgeschlagen = 0; $protokoll = []; $letzteVariante = null;

    // Zuvor erfolgreiche Variante aus den Einstellungen holen
    $bevorzugt = null;
    try {
        $st = $pdo->query("SELECT wert FROM einstellungen
                           WHERE schluessel = 'mitteilung_variante'");
        $bevorzugt = $st->fetchColumn() ?: null;
    } catch (Throwable $e) { /* Tabelle ggf. noch leer */ }

    try {
        $wu->authenticate($benutzer, $passwort);
        $rest = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $rest->mitSessionCookie((string)$wu->sessionCookie());
        $rest->setzeTimeout(15);
        if (!$rest->tokenHolen()) {
            return ['gesendet' => 0, 'fehler' => count($ids),
                    'grund' => 'Kein REST-Zugang (JWT) – Versand nicht möglich.',
                    'variante' => null, 'protokoll' => []];
        }
        $rest->tenantErmitteln();

        $platzhalter = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT * FROM mitteilungen
                             WHERE id IN ($platzhalter) AND status <> 'gesendet'");
        $st->execute($ids);

        $update = $pdo->prepare('UPDATE mitteilungen
            SET status = ?, grund = ?, gesendet_am = ?, versuche = versuche + 1
            WHERE id = ?');

        foreach ($st->fetchAll() as $m) {
            $e = mit_senden($rest, (int)$m['empfaenger_user_id'],
                (string)$m['betreff'], (string)$m['text'], $bevorzugt);

            if ($e['ok']) {
                $gesendet++;
                $letzteVariante = $bevorzugt = $e['variante'];
                $update->execute(['gesendet', $e['grund'], date('Y-m-d H:i:s'), (int)$m['id']]);
            } else {
                $fehlgeschlagen++;
                $update->execute(['offen', kuerze($e['grund'], 4000), null, (int)$m['id']]);
            }
            $protokoll[] = ['id' => (int)$m['id'], 'ok' => $e['ok'],
                            'grund' => $e['grund'], 'versuche' => $e['versuche']];
        }

        // Erfolgreiche Variante merken -> künftig nur noch ein Aufruf
        if ($letzteVariante !== null) {
            $pdo->prepare("INSERT INTO einstellungen (schluessel, wert)
                VALUES ('mitteilung_variante', ?)
                ON DUPLICATE KEY UPDATE wert = VALUES(wert)")
                ->execute([$letzteVariante]);
        }
    } catch (RuntimeException $e) {
        return ['gesendet' => 0, 'fehler' => count($ids),
                'grund' => 'WebUntis-Anmeldung fehlgeschlagen: ' . $e->getMessage(),
                'variante' => null, 'protokoll' => []];
    } finally {
        $wu->logout();
    }

    $grund = $gesendet > 0
        ? ($gesendet . ' Mitteilung(en) versendet'
            . ($fehlgeschlagen > 0 ? ', ' . $fehlgeschlagen . ' offen geblieben' : '') . '.')
        : 'Kein Versand möglich – die Mitteilungen bleiben zum manuellen '
            . 'Versand vorgemerkt.';

    return ['gesendet' => $gesendet, 'fehler' => $fehlgeschlagen, 'grund' => $grund,
            'variante' => $letzteVariante, 'protokoll' => $protokoll];
}
