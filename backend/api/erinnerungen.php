<?php
// ============================================================
// backend/api/erinnerungen.php
// „Erinnerungen vor dem Sprechtag" (Weg B: Admin löst bewusst aus).
//
// Sendet EINE allgemeine Erinnerung an eine benannte WebUntis-Empfängerliste
// (z. B. „alle Eltern"), die über typ + referenceId angesprochen wird. Der
// Versand läuft seit v0.9.72 über die Sitzung der angemeldeten Verwaltung
// (E17; gemessen 09.10.2026: Admin und Lehrkraft lösen die Testliste auf und
// senden an sie) und den messages/users-Endpunkt mit
// einem recipientUserIds-Array – blockweise, damit auch sehr große Listen
// (mehrere tausend) robust durchlaufen.
//
// Kein Cron: Der Admin ruft die Vorschau auf (wie viele Empfänger?) und löst
// den Versand dann selbst aus.
// ============================================================

/** Standard-Betreff, falls keiner konfiguriert ist. */
function erinnerung_standard_betreff(string $schulname): string
{
    return 'Erinnerung: Elternsprechtag';
}

/**
 * Standard-Erinnerungstext (Markdown). Bewusst allgemein gehalten – die
 * persönlichen Termine holt sich jede/r über die eigene Terminübersicht bzw.
 * das Kalender-Abo. Platzhalter {{schulname}}/{{titel}} werden ersetzt.
 */
function erinnerung_standard_text(): string
{
    return "Guten Tag,\n\n"
        . "wir möchten Sie an den bevorstehenden Elternsprechtag erinnern.\n\n"
        . "Ihre gebuchten Termine finden Sie jederzeit in **{{titel}}** unter "
        . "„Meine Termine\" – dort können Sie sie auch ausdrucken oder in Ihren "
        . "Kalender übernehmen.\n\n"
        . "Falls Sie noch keine Termine gebucht haben, holen Sie dies gern "
        . "rechtzeitig nach.\n\n"
        . "Mit freundlichen Grüßen\n"
        . "{{schulname}}";
}

/**
 * Löst die konfigurierte Empfängerliste auf (nur Auflösung, kein Versand).
 * Für die Vorschau im Admin: „An wie viele Empfänger würde gesendet?"
 *
 * Rückgabe: ['ok'=>bool, 'anzahl'=>int, 'vollstaendig'=>bool, 'grund'=>string]
 */
function erinnerung_empfaenger_ermitteln(PDO $pdo, array $sitzung): array
{
    $typ = marke_wert($pdo, 'erinnerung_liste_typ', 'DYNAMIC');
    $id  = (int)marke_wert($pdo, 'erinnerung_liste_id', '0');
    if ($id <= 0) {
        return ['ok' => false, 'anzahl' => 0, 'vollstaendig' => false,
                'grund' => 'Es ist keine Empfängerliste konfiguriert. Bitte '
                    . 'Listen-Typ und Listen-ID im Admin eintragen.'];
    }
    $rest = $sitzung['rest'] ?? null;
    if (!$rest instanceof WebUntisRest) {
        return ['ok' => false, 'anzahl' => 0, 'vollstaendig' => false,
                'sitzung' => $sitzung['art'] ?? 'kaputt',
                'grund' => wu_sitzung_meldung($sitzung['art'] ?? 'kaputt')];
    }

    try {
        $res = $rest->listeAufloesen($typ, $id);
        $ids = erinnerung_ids_aus_users($res['users']);
        return ['ok' => $ids !== [], 'anzahl' => count($ids),
                'vollstaendig' => (bool)$res['vollstaendig'],
                'grund' => $ids === []
                    ? 'Die Liste lieferte keine Empfänger (Typ/ID prüfen).'
                    : ''];
    } catch (Throwable $e) {
        error_log('sprechtag: Erinnerungs-Auflösung fehlgeschlagen: ' . $e->getMessage());
        return ['ok' => false, 'anzahl' => 0, 'vollstaendig' => false,
                'grund' => 'Auflösung fehlgeschlagen: ' . $e->getMessage()];
    }
}

/**
 * Deutet die Antwort von POST /v2/messages/users für EINEN Block.
 *
 * Gemessen am 07.10.2026 (Produktivsystem, Liste „testen", 2 Empfänger):
 *   POST .../v2/messages/users
 *   -> {"numberOfRecipients": 2, "numberOfCCRecipients": null}
 * Die Antwort trägt also dieselbe Erfolgsangabe wie /v2/messages.
 *
 * WICHTIG: Ein Status 2xx allein ist KEIN Beleg, dass etwas hinausging –
 * erst numberOfRecipients sagt, wie viele erreicht wurden. Fehler- und
 * Teilerfolgsfälle sind NICHT gemessen; alles Ungemessene gilt deshalb als
 * 'unklar' und NICHT als Erfolg (ein falscher Erfolg bliebe unbemerkt, ein
 * falsches „unklar" kostet nur einen Blick in WebUntis unter „Gesendet").
 *
 * Rückgabe: ['stand' => 'gesendet'|'fehler'|'unklar',
 *            'erreicht' => int, 'grund' => string]
 */
function erinnerung_antwort_deuten(array $r, int $erwartet): array
{
    $status = (int)($r['status'] ?? 0);
    $zahl   = $r['json']['numberOfRecipients'] ?? null;

    // Verbindung kam nicht zustande -> sicher nichts hinausgegangen.
    if ($status === 0) {
        // postMultipart() legt den curl-Text bei Status 0 in 'text' ab,
        // mit dem Präfix 'cURL: '.
        $text = (string)($r['text'] ?? '');
        if (strpos($text, 'cURL: Failed to connect to') === 0
            || strpos($text, 'cURL: Could not resolve host') === 0) {
            return ['stand' => 'fehler', 'erreicht' => 0,
                    'grund' => 'Keine Verbindung zu WebUntis – nichts gesendet.'];
        }
        // Zeitüberschreitung u. Ä.: die Nachricht KANN angekommen sein.
        return ['stand' => 'unklar', 'erreicht' => 0,
                'grund' => 'Keine Antwort von WebUntis erhalten. Ob die '
                    . 'Mitteilung hinausging, ist unklar – bitte in WebUntis '
                    . 'unter „Gesendet" nachsehen, bevor erneut gesendet wird.'];
    }
    if ($status === 401 || $status === 403) {
        return ['stand' => 'fehler', 'erreicht' => 0,
                'grund' => 'WebUntis hat den Zugang abgelehnt (HTTP ' . $status
                    . ') – Recht „Mitteilungen senden“ des angemeldeten Kontos prüfen.'];
    }
    if ($status < 200 || $status >= 300) {
        return ['stand' => 'fehler', 'erreicht' => 0,
                'grund' => 'WebUntis antwortete mit HTTP ' . $status . '.'];
    }

    // 2xx: jetzt entscheidet die Empfängerzahl.
    if (!is_int($zahl) && !(is_string($zahl) && ctype_digit($zahl))) {
        return ['stand' => 'unklar', 'erreicht' => 0,
                'grund' => 'WebUntis hat angenommen, nennt aber keine '
                    . 'Empfängerzahl – bitte in WebUntis unter „Gesendet" '
                    . 'nachsehen.'];
    }
    $zahl = (int)$zahl;
    if ($zahl === 0) {
        return ['stand' => 'fehler', 'erreicht' => 0,
                'grund' => 'WebUntis meldet 0 Empfänger – es ging nichts hinaus.'];
    }
    if ($zahl === $erwartet) {
        return ['stand' => 'gesendet', 'erreicht' => $zahl, 'grund' => ''];
    }
    // Weniger (oder mehr) als erwartet: angekommen, aber nicht wie geplant.
    return ['stand' => 'unklar', 'erreicht' => $zahl,
            'grund' => 'WebUntis meldet ' . $zahl . ' statt ' . $erwartet
                . ' Empfänger – bitte in WebUntis nachsehen.'];
}

/** Extrahiert eindeutige, gültige user.id-Werte aus der WebUntis-Antwort. */
function erinnerung_ids_aus_users(array $users): array
{
    $ids = [];
    $gesehen = [];
    foreach ($users as $u) {
        $id = (int)($u['id'] ?? 0);
        if ($id > 0 && !isset($gesehen[$id])) { $gesehen[$id] = true; $ids[] = $id; }
    }
    return $ids;
}

/**
 * Führt den Erinnerungsversand aus: Liste auflösen, Text/Betreff bestimmen,
 * dann blockweise an die Empfänger senden (eine WebUntis-Session für alles).
 *
 * Rückgabe: ['gesendet'=>int, 'empfaenger'=>int, 'bloecke'=>int,
 *            'vollstaendig'=>bool, 'grund'=>string]
 */
function erinnerung_versenden(PDO $pdo, array $sitzung, int $blockGroesse = 500): array
{
    $typ = marke_wert($pdo, 'erinnerung_liste_typ', 'DYNAMIC');
    $listeId = (int)marke_wert($pdo, 'erinnerung_liste_id', '0');
    if ($listeId <= 0) {
        return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0, 'unklar' => false,
                'vollstaendig' => false,
                'grund' => 'Keine Empfängerliste konfiguriert.'];
    }
    $rest = $sitzung['rest'] ?? null;
    if (!$rest instanceof WebUntisRest) {
        return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0, 'unklar' => false,
                'vollstaendig' => false, 'sitzung' => $sitzung['art'] ?? 'kaputt',
                'grund' => wu_sitzung_meldung($sitzung['art'] ?? 'kaputt')];
    }

    // Betreff und Text (mit Platzhaltern) bestimmen.
    $schulname = marke_schulname($pdo);
    $werte = [
        'kontakt'   => marke_wert($pdo, 'marke_kontakt', ''),
        'schulname' => $schulname,
        'titel'     => marke_wert($pdo, 'marke_titel', 'Sprechtag'),
    ];
    $betreff = marke_wert($pdo, 'erinnerung_betreff', '');
    if ($betreff === '') $betreff = erinnerung_standard_betreff($schulname);
    $betreff = platzhalter_ersetzen($betreff, $werte);

    $text = marke_wert($pdo, 'erinnerung_text', '');
    if ($text === '') $text = erinnerung_standard_text();
    $text = platzhalter_ersetzen($text, $werte);

    try {
        $rest->setzeTimeout(30);

        // 1) Empfänger auflösen
        $res = $rest->listeAufloesen($typ, $listeId);
        $ids = erinnerung_ids_aus_users($res['users']);
        if ($ids === []) {
            return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0, 'unklar' => false,
                    'vollstaendig' => (bool)$res['vollstaendig'],
                    'grund' => 'Keine Empfänger ermittelt.'];
        }

        // 2) Blockweise senden
        $bloecke = array_chunk($ids, max(1, $blockGroesse));
        $gesendet = 0; $blockNr = 0; $fehler = ''; $unklar = false;
        foreach ($bloecke as $block) {
            $blockNr++;
            $payload = [
                'subject'             => $betreff,
                'content'             => $text,
                'requestConfirmation' => false,
                'recipientUserIds'    => array_values($block),
                'oneDriveAttachments' => [],
                'forbidReply'         => false,
            ];
            $r = $rest->postMultipart(
                '/WebUntis/api/rest/view/v2/messages/users', $payload);
            $d = erinnerung_antwort_deuten($r, count($block));
            $gesendet += $d['erreicht'];

            if ($d['stand'] === 'gesendet') continue;

            // Weder sicherer Erfolg noch Weitermachen: In beiden Fällen
            // abbrechen und den Stand ehrlich melden. Bei 'unklar' ist
            // besonders wichtig, dass NICHT einfach weitergesendet wird –
            // es gibt keinen Schutz gegen doppelte Mitteilungen.
            $unklar = ($d['stand'] === 'unklar');
            $fehler = 'Block ' . $blockNr . ' von ' . count($bloecke) . ': '
                . $d['grund'];
            break;
        }

        // Vollständig heißt: alle Empfänger bestätigt UND die Liste war
        // vollständig aufgelöst.
        $vollstaendig = ($gesendet === count($ids))
            && !$unklar && $fehler === '' && (bool)$res['vollstaendig'];
        $grund = $fehler !== ''
            ? $fehler
            : ($res['vollstaendig'] ? '' : 'Hinweis: Empfängerliste evtl. '
                . 'nicht vollständig aufgelöst – bitte Anzahl prüfen.');
        return ['gesendet' => $gesendet, 'empfaenger' => count($ids),
                'bloecke' => $blockNr, 'vollstaendig' => $vollstaendig,
                'unklar' => $unklar, 'grund' => $grund];
    } catch (Throwable $e) {
        error_log('sprechtag: Erinnerungsversand fehlgeschlagen: ' . $e->getMessage());
        return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0, 'unklar' => false,
                'vollstaendig' => false,
                'grund' => 'Versand fehlgeschlagen: ' . $e->getMessage()];
    }
}
