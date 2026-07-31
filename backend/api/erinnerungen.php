<?php
// ============================================================
// backend/api/erinnerungen.php
// „Erinnerungen vor dem Sprechtag" (Weg B: Admin löst bewusst aus).
//
// Sendet EINE allgemeine Erinnerung an eine benannte WebUntis-Empfängerliste
// (z. B. „alle Eltern"), die über typ + referenceId angesprochen wird. Der
// Versand nutzt das bestehende Dienstkonto und den messages/users-Endpunkt mit
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
function erinnerung_empfaenger_ermitteln(array $cfg, PDO $pdo): array
{
    $typ = marke_wert($pdo, 'erinnerung_liste_typ', 'DYNAMIC');
    $id  = (int)marke_wert($pdo, 'erinnerung_liste_id', '0');
    if ($id <= 0) {
        return ['ok' => false, 'anzahl' => 0, 'vollstaendig' => false,
                'grund' => 'Es ist keine Empfängerliste konfiguriert. Bitte '
                    . 'Listen-Typ und Listen-ID im Admin eintragen.'];
    }
    $zugang = dk_lesen($cfg, $pdo);
    if ($zugang === null) {
        return ['ok' => false, 'anzahl' => 0, 'vollstaendig' => false,
                'grund' => 'Kein Dienstkonto hinterlegt – Auflösung nicht möglich.'];
    }

    $wcfg = $cfg['webuntis'];
    $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);
    try {
        $wu->authenticate($zugang['benutzer'], $zugang['passwort']);
        $rest = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $rest->mitSessionCookie((string)$wu->sessionCookie());
        $rest->setzeTimeout(20);
        if (!$rest->tokenHolen()) {
            return ['ok' => false, 'anzahl' => 0, 'vollstaendig' => false,
                    'grund' => 'Kein REST-Zugang (JWT) über das Dienstkonto.'];
        }
        $rest->tenantErmitteln();
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
    } finally {
        $wu->logout();
    }
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
function erinnerung_versenden(array $cfg, PDO $pdo, int $blockGroesse = 500): array
{
    $typ = marke_wert($pdo, 'erinnerung_liste_typ', 'DYNAMIC');
    $listeId = (int)marke_wert($pdo, 'erinnerung_liste_id', '0');
    if ($listeId <= 0) {
        return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0,
                'vollstaendig' => false,
                'grund' => 'Keine Empfängerliste konfiguriert.'];
    }
    $zugang = dk_lesen($cfg, $pdo);
    if ($zugang === null) {
        return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0,
                'vollstaendig' => false, 'grund' => 'Kein Dienstkonto hinterlegt.'];
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

    $wcfg = $cfg['webuntis'];
    $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);
    try {
        $wu->authenticate($zugang['benutzer'], $zugang['passwort']);
        $rest = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $rest->mitSessionCookie((string)$wu->sessionCookie());
        $rest->setzeTimeout(30);
        if (!$rest->tokenHolen()) {
            return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0,
                    'vollstaendig' => false,
                    'grund' => 'Kein REST-Zugang (JWT) über das Dienstkonto.'];
        }
        $rest->tenantErmitteln();

        // 1) Empfänger auflösen
        $res = $rest->listeAufloesen($typ, $listeId);
        $ids = erinnerung_ids_aus_users($res['users']);
        if ($ids === []) {
            return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0,
                    'vollstaendig' => (bool)$res['vollstaendig'],
                    'grund' => 'Keine Empfänger ermittelt.'];
        }

        // 2) Blockweise senden
        $bloecke = array_chunk($ids, max(1, $blockGroesse));
        $gesendet = 0; $blockNr = 0; $fehler = '';
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
            if ($r['status'] >= 200 && $r['status'] < 300) {
                $gesendet += count($block);
            } else {
                $fehler = 'Block ' . $blockNr . ' fehlgeschlagen (HTTP '
                    . $r['status'] . ').';
                break;   // bei Fehler abbrechen – lieber melden als blind weiter
            }
        }

        $vollstaendig = ($gesendet === count($ids)) && (bool)$res['vollstaendig'];
        $grund = $fehler !== '' ? $fehler
            : ($res['vollstaendig'] ? '' : 'Hinweis: Empfängerliste evtl. '
                . 'nicht vollständig aufgelöst – bitte Anzahl prüfen.');
        return ['gesendet' => $gesendet, 'empfaenger' => count($ids),
                'bloecke' => $blockNr, 'vollstaendig' => $vollstaendig,
                'grund' => $grund];
    } catch (Throwable $e) {
        error_log('sprechtag: Erinnerungsversand fehlgeschlagen: ' . $e->getMessage());
        return ['gesendet' => 0, 'empfaenger' => 0, 'bloecke' => 0,
                'vollstaendig' => false,
                'grund' => 'Versand fehlgeschlagen: ' . $e->getMessage()];
    } finally {
        $wu->logout();
    }
}
