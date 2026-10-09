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
//   * VERSANDWEG (belegt am 24.07.2026 durch Mitschnitt der
//     WebUntis-Weboberfläche):
//         POST /WebUntis/api/rest/view/v2/messages/users
//         Content-Type: multipart/form-data
//         Teil name="request", filename="blob", Content-Type: application/json
//         {"subject":"…","content":"…","requestConfirmation":false,
//          "recipientUserIds":[5984],"oneDriveAttachments":[],
//          "forbidReply":false}
//     Entscheidend: KEIN reiner JSON-Body, sondern ein Multipart-Teil.
//     Empfänger werden über recipientUserIds (user.id) adressiert.
//
// Die übrigen Varianten bleiben als Rückfall erhalten, falls eine
// andere Instanz oder WebUntis-Version abweicht. Die erfolgreiche
// Variante wird in den Einstellungen gemerkt.
//
// FALLBACK: Schlägt der Versand fehl, wird die Mitteilung in der
// Tabelle `mitteilungen` als 'offen' gespeichert. Die Lehrkraft sieht
// sie in ihrer Ansicht und kann sie manuell in WebUntis versenden –
// der Termin ist trotzdem korrekt storniert.
//
// SEIT v0.9.72 (E17): kein Dienstkonto mehr. Versendet wird nur über die
// Sitzung der handelnden Person (wu_sitzung()); ist sie abgelaufen, bleibt
// die Mitteilung stehen, und die Antwort sagt es. An die Eltern eines
// Kindes geht es über recipientOption PARENTS (mit_senden_eltern()).
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../helfer.php';
require_once __DIR__ . '/../auth/WebUntisAuth.php';
require_once __DIR__ . '/../auth/WebUntisRest.php';
require_once __DIR__ . '/webuntis_adapter.php';   // wu_sitzung_meldung()

/**
 * Kandidaten-Varianten für den Versand. Jede beschreibt Pfad und eine
 * Funktion, die den Body baut. Reihenfolge = Reihenfolge der Versuche.
 *
 * Reine Funktion (keine Netzzugriffe) – offline testbar.
 */
function mit_varianten(): array
{
    return [
        // ---- BELEGT (Mitschnitt der Weboberfläche, 24.07.2026) --------
        'v2_users_multipart' => [
            'pfad'      => '/WebUntis/api/rest/view/v2/messages/users',
            'multipart' => true,
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'             => $betreff,
                'content'             => $text,
                'requestConfirmation' => false,
                'recipientUserIds'    => [$empfaenger],
                'oneDriveAttachments' => [],
                'forbidReply'         => false,
            ],
        ],

        // ---- Rückfälle für abweichende Instanzen/Versionen ------------
        'v2_users_json' => [
            'pfad' => '/WebUntis/api/rest/view/v2/messages/users',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'             => $betreff,
                'content'             => $text,
                'requestConfirmation' => false,
                'recipientUserIds'    => [$empfaenger],
                'oneDriveAttachments' => [],
                'forbidReply'         => false,
            ],
        ],
        'v1_messages_multipart' => [
            'pfad'      => '/WebUntis/api/rest/view/v1/messages',
            'multipart' => true,
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'          => $betreff,
                'content'          => $text,
                'recipientUserIds' => [$empfaenger],
            ],
        ],
        'v1_recipientids' => [
            'pfad' => '/WebUntis/api/rest/view/v1/messages',
            'body' => fn(int $empfaenger, string $betreff, string $text) => [
                'subject'      => $betreff,
                'content'      => $text,
                'recipientIds' => [$empfaenger],
            ],
        ],
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
        $daten = ($v['body'])($empfaengerUserId, $betreff, $text);
        $antwort = ($v['multipart'] ?? false)
            ? $rest->postMultipart($v['pfad'], $daten)
            : $rest->post($v['pfad'], $daten);
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
                               array $termine, string $schule = 'Ihre Schule'): array
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
        . $schule;

    return ['betreff' => 'Ihre Termine: ' . $sprechtagName, 'text' => $text];
}

/**
 * Filtert aus einer Empfängersuche die Erziehungsberechtigten heraus,
 * die zu einem bestimmten Kind gehören.
 *
 * ACHTUNG – die Verknüpfung läuft über NAMEN, nicht über IDs: Die
 * WebUntis-Antwort führt bei Erziehungsberechtigten unter 'tags' die
 * Namen ihrer Kinder auf (z. B. ["Paulowski Paul", "Paulowski Petra"]).
 * Eine Schüler-ID steht dort nicht. Deshalb:
 *
 *  - Der Vergleich ist bewusst STRENG (normalisiert, aber exakt), damit
 *    nicht versehentlich fremde Konten getroffen werden.
 *  - Bei gleichnamigen Kindern ist die Zuordnung grundsätzlich
 *    mehrdeutig; das meldet die Funktion über 'eindeutig' => false.
 *    Der Aufrufer muss dann entscheiden (im Zweifel: nicht versenden).
 *  - Einträge mit role 'STUDENT' werden ignoriert – eine Einladung an
 *    die Eltern darf nicht beim Kind landen.
 *
 * Reine Funktion – offline testbar.
 *
 * @param array  $users     users[] aus der Empfängersuche
 * @param string $kindName  "Nachname Vorname" oder "Vorname Nachname"
 * @return array{konten:array<int,array{id:int,name:string}>, eindeutig:bool,
 *               geprueft:int}
 */
function mit_eltern_zu_kind(array $users, string $kindName): array
{
    $gesucht = mit_name_normieren($kindName);
    if ($gesucht === '') {
        return ['konten' => [], 'eindeutig' => false, 'geprueft' => 0];
    }

    $konten = [];
    $geprueft = 0;
    foreach ($users as $u) {
        if (!is_array($u)) continue;
        if ((string)($u['role'] ?? '') !== 'LEGAL_GUARDIAN') continue;
        $geprueft++;

        foreach ((array)($u['tags'] ?? []) as $tag) {
            if (mit_name_normieren((string)$tag) !== $gesucht) continue;
            $id = (int)($u['id'] ?? 0);
            if ($id <= 0) continue;
            $konten[$id] = ['id' => $id,
                            'name' => (string)($u['displayName'] ?? '')];
            break;
        }
    }

    return ['konten' => array_values($konten), 'eindeutig' => true,
            'geprueft' => $geprueft];
}

/**
 * Ermittelt die WebUntis-USER-IDs der Erziehungsberechtigten eines Kindes –
 * seit v0.9.72 NUR noch für die stellvertretende Buchung, die den Termin
 * einem Konto zuordnen muss (Meine Termine, Kalender, Fall A, Absage). Die
 * Mitteilungen selbst gehen über PARENTS und brauchen keine Kennungen (E17).
 *   1. WebUntis-Empfängersuche über die Sitzung der handelnden Lehrkraft
 *      (gemessen v0.9.69: findet die Konten) – kein Dienstkonto mehr
 *   2. Rückfall: aus früheren Buchungen desselben Kindes
 *
 * $rest null (Sitzung nicht nutzbar): nur Weg 2. Der Aufrufer prüft die
 * Sitzung vorher und bucht bei abgelaufener nicht (E17).
 *
 * @return array{ids:int[], quelle:?string, kind_name:string}
 *         quelle: 'webuntis' | 'buchung' | null (nichts gefunden)
 */
function mit_eltern_ids_ermitteln(PDO $pdo, int $schuelerId, ?WebUntisRest $rest): array
{
    // Kindnamen aus der Schülerliste (für Suche und exakten Namensabgleich)
    $stK = $pdo->prepare(
        'SELECT vorname, nachname FROM schueler WHERE webuntis_id = ? LIMIT 1');
    $stK->execute([$schuelerId]);
    $kd = $stK->fetch() ?: [];
    $kindName = trim(((string)($kd['vorname'] ?? '')) . ' '
        . ((string)($kd['nachname'] ?? '')));

    $ids = [];
    $quelle = null;

    // ---- Weg 1: WebUntis-Empfängersuche über die eigene Sitzung ------------
    if ($kindName !== '' && $rest !== null) {
        try {
            $suche = (string)($kd['nachname'] ?? $kindName);
            $treffer = $rest->empfaengerSuchen($suche);
            $zuord = mit_eltern_zu_kind($treffer['users'], $kindName);
            $ids = array_map('intval', array_column($zuord['konten'], 'id'));
            if ($ids !== []) $quelle = 'webuntis';
        } catch (Exception $e) {
            error_log('sprechtag: Empfängersuche fehlgeschlagen: ' . $e->getMessage());
        }
    }

    // ---- Weg 2: Rückfall über frühere Buchungen --------------------------
    if ($ids === []) {
        $stE = $pdo->prepare(
            'SELECT DISTINCT eltern_user_id FROM buchungen
             WHERE schueler_id = ? AND eltern_user_id > 0');
        $stE->execute([$schuelerId]);
        $ids = array_map('intval',
            array_column($stE->fetchAll(), 'eltern_user_id'));
        if ($ids !== []) $quelle = 'buchung';
    }

    return ['ids' => $ids, 'quelle' => $quelle, 'kind_name' => $kindName];
}

/**
 * Normalisiert einen Personennamen für den Vergleich: Kleinschreibung,
 * Mehrfach-Leerzeichen zusammengefasst, Wortreihenfolge sortiert
 * (damit "Paulowski Paul" und "Paul Paulowski" gleich sind).
 * Reine Funktion – offline testbar.
 */
function mit_name_normieren(string $name): string
{
    $n = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    if ($n === '') return '';
    $n = str_replace(',', '', $n);
    $teile = array_filter(explode(' ', mb_strtolower_sicher($n)));
    sort($teile);
    return implode(' ', $teile);
}

/** Kleinschreibung auch ohne mbstring-Erweiterung. */
function mb_strtolower_sicher(string $s): string
{
    if (function_exists('mb_strtolower')) return mb_strtolower($s, 'UTF-8');
    // Umlaute von Hand, dann der Rest
    $s = strtr($s, ['Ä' => 'ä', 'Ö' => 'ö', 'Ü' => 'ü']);
    return strtolower($s);
}

/**
 * Baut Betreff und Text einer Einladung zum Sprechtag.
 * Reine Funktion – offline testbar.
 */
function mit_text_einladung(string $sprechtagName, string $datum,
                            string $lehrkraft, string $kind,
                            string $freitext = ''): array
{
    $text = "Guten Tag,\n\n"
        . "für den " . $sprechtagName . " am " . mit_datum_deutsch($datum)
        . " möchte ich Sie gern zu einem Gespräch"
        . ($kind !== '' ? " über " . $kind : "") . " einladen.\n";

    if (trim($freitext) !== '') {
        $text .= "\n" . trim($freitext) . "\n";
    }

    $text .= "\nBitte buchen Sie dafür im Buchungsportal einen Termin bei mir. "
        . "In dieser ersten Phase ist die Buchung den eingeladenen "
        . "Erziehungsberechtigten vorbehalten.\n\n"
        . "Mit freundlichen Grüßen\n" . $lehrkraft;

    return ['betreff' => 'Einladung zum ' . $sprechtagName, 'text' => $text];
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
 * Schreibt eine Mitteilung in die Warteschlange – nur die Datenbank, kein
 * Versand. Die Warteschlange ist der Ort, an dem eine Mitteilung stehen
 * bleibt, wenn die Sitzung abgelaufen ist (E17): Der Text geht nicht
 * verloren.
 *
 * $empfaengerArt 'konto':  an ein WebUntis-Konto (empfaenger_user_id).
 *                'eltern': an die Erziehungsberechtigten des Kindes über
 *                          recipientOption PARENTS (E16, E17) – ohne
 *                          Kennungen der Eltern; empfaenger_user_id ist 0.
 * $lehrerId: die Lehrkraft, um deren Termin es geht. Daran erkennt der
 *            Versand, ob eine Mitteilung „zu meinen Terminen“ gehört – auch
 *            nach einer Absage, wenn die Buchung schon gelöscht ist.
 */
function mit_einreihen(PDO $pdo, int $sprechtagId, int $empfaengerUserId,
                       string $anlass, string $betreff, string $text,
                       ?int $schuelerId = null, ?int $lehrerId = null,
                       string $empfaengerArt = 'konto'): int
{
    if (!in_array($empfaengerArt, ['konto', 'eltern'], true)) {
        throw new InvalidArgumentException('Unbekannte Empfängerart: ' . $empfaengerArt);
    }
    if ($empfaengerArt === 'eltern' && ($schuelerId === null || $schuelerId <= 0)) {
        throw new InvalidArgumentException('An die Eltern (PARENTS) nur mit der Kennung des Kindes.');
    }
    $pdo->prepare('INSERT INTO mitteilungen
        (sprechtag_id, empfaenger_user_id, empfaenger_art, schueler_id, lehrer_id,
         anlass, betreff, text, status, grund)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, "offen", "")')
        ->execute([$sprechtagId, $empfaengerArt === 'eltern' ? 0 : $empfaengerUserId,
            $empfaengerArt, $schuelerId, $lehrerId, $anlass, kuerze($betreff, 190), $text]);
    return (int)$pdo->lastInsertId();
}

/**
 * Versendet eingereihte Mitteilungen über die Sitzung der handelnden Person –
 * oder lässt sie stehen und sagt, warum. Es gibt keinen Rückfall mehr (E17):
 * Ist die Sitzung nicht nutzbar, bleiben die Mitteilungen 'offen', und die
 * Antwort nennt die Ursache. Bei 'abgelaufen' bietet die Oberfläche die
 * Neuanmeldung an, danach geht es mit einem Klick hinaus.
 *
 * $sitzung: Ergebnis von wu_sitzung().
 * Rückgabe: ['ids' => int[], 'status' => 'gesendet'|'teilweise'|'fehler'|'offen'|'keine',
 *            'gesendet' => int, 'offen' => int,
 *            'sitzung' => null|'abgelaufen'|'nicht_erreichbar'|'kaputt',
 *            'grund' => string]
 *   'offen'  – nicht versucht, weil die Sitzung nicht nutzbar ist ('sitzung' sagt warum)
 *   'fehler' – versucht, von WebUntis nicht angenommen ('grund' sagt warum)
 */
function mit_senden_oder_vormerken(PDO $pdo, array $ids, array $sitzung): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));
    if ($ids === []) {
        return ['ids' => [], 'status' => 'keine', 'gesendet' => 0, 'offen' => 0,
                'sitzung' => null, 'grund' => 'Keine Mitteilung zu senden.'];
    }

    $rest = $sitzung['rest'] ?? null;
    if (!$rest instanceof WebUntisRest) {
        $art = (string)($sitzung['art'] ?? 'kaputt');
        $platz = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE mitteilungen SET grund = ?
                       WHERE id IN ($platz) AND status = 'offen'")
            ->execute(array_merge(['Nicht versendet – WebUntis-Sitzung: ' . $art
                . ' (' . (string)($sitzung['grund'] ?? '') . ')'], $ids));
        return ['ids' => $ids, 'status' => 'offen', 'gesendet' => 0, 'offen' => count($ids),
                'sitzung' => $art, 'grund' => wu_sitzung_meldung($art)];
    }

    $e = mit_versand_ausfuehren($pdo, $ids, $rest);
    $offen = count($ids) - $e['gesendet'];
    return ['ids' => $ids,
            'status' => $offen === 0 ? 'gesendet' : ($e['gesendet'] === 0 ? 'fehler' : 'teilweise'),
            'gesendet' => $e['gesendet'], 'offen' => $offen,
            'sitzung' => null, 'grund' => $e['grund']];
}

/** Einreihen und gleich versuchen – für die Stellen mit genau einer Mitteilung. */
function mit_einreihen_und_senden(PDO $pdo, int $sprechtagId, int $empfaengerUserId,
                                  string $anlass, string $betreff, string $text,
                                  ?int $schuelerId, ?int $lehrerId, array $sitzung,
                                  string $empfaengerArt = 'konto'): array
{
    $id = mit_einreihen($pdo, $sprechtagId, $empfaengerUserId, $anlass, $betreff, $text,
        $schuelerId, $lehrerId, $empfaengerArt);
    return mit_senden_oder_vormerken($pdo, [$id], $sitzung);
}

// ---- An die Eltern eines Kindes: recipientOption PARENTS (v0.9.72, E17) ----

/** Pfad wie in lernzeiten gemessen; /v2/messages/users lehnt PARENTS mit 500 ab (Befund 16). */
const MIT_PARENTS_PFAD = '/WebUntis/api/rest/view/v2/messages';

/**
 * Der Körper für recipientOption PARENTS – an die Eltern über die Kennung des
 * Kindes, OHNE Kopie an das Kind. Gemessen am 09.10.2026 auf unserem Pfad
 * (Befund 16: 4 Empfänger, angekommen). messung_parents_koerper() baut die
 * Testnachricht aus genau dieser Funktion – eine Quelle.
 */
function mit_parents_koerper(int $kind, string $betreff, string $text): array
{
    return [
        'subject'             => $betreff,
        'content'             => $text,
        'recipientOption'     => 'PARENTS',
        'recipientPersonIds'  => [$kind],
        'recipientGroupIds'   => [],
        'copyToStudent'       => false,
        'requestConfirmation' => false,
        'oneDriveAttachments' => [],
        'forbidReply'         => false,
    ];
}

/**
 * Versand an die Eltern eines Kindes. Erfolg heißt numberOfRecipients ≥ 1,
 * nicht Status 200 (Befund 16): Ein 2xx mit 0 Empfängern hat niemanden
 * erreicht, einer ohne Zahl ist unklar – beides bleibt offen, mit Grund.
 *
 * Rückgabe: ['ok' => bool, 'grund' => string]
 */
function mit_senden_eltern(WebUntisRest $rest, int $kind, string $betreff, string $text): array
{
    $r = $rest->postMultipart(MIT_PARENTS_PFAD, mit_parents_koerper($kind, $betreff, $text));
    $st = (int)($r['status'] ?? 0);
    if ($st < 200 || $st >= 300) {
        return ['ok' => false, 'grund' => 'PARENTS: ' . mit_antwort_bewerten($r)['grund']];
    }
    $n = is_array($r['json'] ?? null) ? ($r['json']['numberOfRecipients'] ?? null) : null;
    if (is_int($n) && $n >= 1) {
        return ['ok' => true, 'grund' => 'PARENTS: HTTP ' . $st . ', ' . $n . ' Empfänger'];
    }
    return ['ok' => false, 'grund' => is_int($n)
        ? 'PARENTS: angenommen, aber niemand erreicht (0 Empfänger)'
        : 'PARENTS: angenommen, Empfängerzahl unklar – vor erneutem Senden in '
            . 'WebUntis unter „Gesendet“ nachsehen'];
}

/**
 * Versendet Mitteilungen über eine nutzbare Sitzung. Seit v0.9.72 nur noch so
 * – keine eigene Anmeldung mit Zugangsdaten mehr (E17).
 *
 * Rückgabe: ['gesendet' => int, 'fehler' => int, 'grund' => string,
 *            'variante' => string|null, 'protokoll' => [...]]
 */
function mit_versand_ausfuehren(PDO $pdo, array $ids, WebUntisRest $rest): array
{
    if ($ids === []) {
        return ['gesendet' => 0, 'fehler' => 0, 'grund' => 'Nichts zu senden.',
                'variante' => null, 'protokoll' => []];
    }

    $gesendet = 0; $fehlgeschlagen = 0; $protokoll = []; $letzteVariante = null;

    // Zuvor erfolgreiche Variante aus den Einstellungen holen
    $bevorzugt = null;
    try {
        $st = $pdo->query("SELECT wert FROM einstellungen
                           WHERE schluessel = 'mitteilung_variante'");
        $bevorzugt = $st->fetchColumn() ?: null;
    } catch (Throwable $e) { /* Tabelle ggf. noch leer */ }

    try {
        $platzhalter = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT * FROM mitteilungen
                             WHERE id IN ($platzhalter) AND status <> 'gesendet'");
        $st->execute($ids);

        $update = $pdo->prepare('UPDATE mitteilungen
            SET status = ?, grund = ?, gesendet_am = ?, versuche = versuche + 1
            WHERE id = ?');

        foreach ($st->fetchAll() as $m) {
            if ((string)($m['empfaenger_art'] ?? 'konto') === 'eltern') {
                $e = mit_senden_eltern($rest, (int)$m['schueler_id'],
                    (string)$m['betreff'], (string)$m['text']);
                $e['variante'] = null;
                $e['versuche'] = [['variante' => 'parents', 'grund' => $e['grund']]];
            } else {
                $e = mit_senden($rest, (int)$m['empfaenger_user_id'],
                    (string)$m['betreff'], (string)$m['text'], $bevorzugt);
            }

            if ($e['ok']) {
                $gesendet++;
                if ($e['variante'] !== null) $letzteVariante = $bevorzugt = $e['variante'];
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
        return ['gesendet' => $gesendet, 'fehler' => count($ids) - $gesendet,
                'grund' => 'WebUntis-Fehler beim Versand: ' . $e->getMessage(),
                'variante' => null, 'protokoll' => $protokoll];
    }

    $grund = $gesendet > 0
        ? ($gesendet . ' Mitteilung(en) versendet'
            . ($fehlgeschlagen > 0 ? ', ' . $fehlgeschlagen . ' offen geblieben' : '') . '.')
        : 'Kein Versand möglich – die Mitteilungen bleiben vorgemerkt.'
            . ($protokoll !== [] ? ' Grund: ' . $protokoll[0]['grund'] : '');

    return ['gesendet' => $gesendet, 'fehler' => $fehlgeschlagen, 'grund' => $grund,
            'variante' => $letzteVariante, 'protokoll' => $protokoll];
}
