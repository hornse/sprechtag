<?php
// ============================================================
// messung_sitzung.php – MESSUNG, KEIN FEATURE  (v0.9.54)
//
//   GET /api/messung/sitzung   (jede angemeldete Person, nur Zahlen)
//
// Zweck: Frage 2 aus docs/BEFUND-2026-10-07-pageconfig-schuelerliste.md –
// trägt der beim Login festgehaltene WebUntis-Cookie (über
// mit_rest_aus_sitzung(), denselben Weg wie der Mitteilungsversand)
//   1. pageconfig?type=5 und
//   2. bei Eltern den Stundenplan-Abruf für die eigenen Kinder?
// Die Sondierung hatte beides nur über eine EIGENE Sitzung gemessen.
//
// STATUS: Messung. Nach dem Befund wird entschieden, ob diese Datei
// VERSCHWINDET oder zur Grundlage von Zug 4 (Schülerliste aus WebUntis)
// wird. Bis dahin baut nichts darauf auf, und nichts hier schreibt –
// weder in WebUntis noch in die Datenbank.
//
// DATENSCHUTZ: Die Antwort enthält nur Zahlen, Statuscodes und
// Fehlermeldungen – keine Namen, keine Kennungen, auch nicht die der
// eigenen Kinder (sie heißen „Kind 1“, „Kind 2“).
//
// Bekannte Doppelung: Das Lesen der pageconfig-Liste
// (messung_pageconfig_zaehlen) steht ebenso in sondierung.php. Wird
// diese Datei zur Grundlage von Zug 4, gehört es an EINE Stelle
// (webuntis_adapter.php), die beide nutzen.
// ============================================================

declare(strict_types=1);

/**
 * Zählt eine pageconfig?type=5-Antwort. Liste unter data.elements, sonst
 * data (wie sondierung.php). Rückgabe nur Zahlen.
 */
function messung_pageconfig_zaehlen($json): array
{
    $liste = $json['data']['elements'] ?? $json['data'] ?? null;
    if (!is_array($liste)) {
        return ['eintraege' => 0, 'mit_klasse' => 0, 'liste_gefunden' => false];
    }
    $eintraege = 0;
    $mitKlasse = 0;
    foreach ($liste as $e) {
        if (!is_array($e)) continue;
        $eintraege++;
        if ((int)($e['klasseId'] ?? 0) > 0) $mitKlasse++;
    }
    return ['eintraege' => $eintraege, 'mit_klasse' => $mitKlasse, 'liste_gefunden' => true];
}

/**
 * Nachprobe für den Grund 'kein_token'. tokenHolen() liefert nur true/false
 * und meldet auch dann false, wenn WebUntis gar nicht erreicht wurde
 * (rohGet: cURL-Fehler → Status 0). „Abgelaufen“ und „Netz weg“ sähen
 * sonst gleich aus. Die Probe ruft denselben Pfad über get() ab, das den
 * Status zurückgibt; der vendorte Client bleibt unverändert.
 *
 * art: 'netz' (Status 0) | 'anmeldeseite' (200 ohne JWT → abgelaufen)
 *    | 'jwt' (200 mit JWT → jetzt gelungen, vorher nicht: flüchtig)
 *    | 'status' (anderer Status) | 'fehler' (Ausnahme)
 */
function messung_token_probe(array $cfg, string $cookie): array
{
    try {
        $wcfg  = $cfg['webuntis'];
        $probe = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $probe->mitSessionCookie($cookie);
        $probe->setzeTimeout(15);
        $r = $probe->get('/WebUntis/api/token/new');
        $status = (int)($r['status'] ?? 0);
        $text   = trim((string)($r['text'] ?? ''));
        $art = $status === 0 ? 'netz'
            : ($status !== 200 ? 'status'
            : (substr_count($text, '.') === 2 && !str_contains($text, '<') ? 'jwt' : 'anmeldeseite'));
        return ['status' => $status, 'art' => $art];
    } catch (Throwable $e) {
        return ['status' => null, 'art' => 'fehler', 'fehler' => get_class($e) . ': ' . $e->getMessage()];
    }
}

/** Deutung für 'kein_token' – je nach Nachprobe. */
function messung_deute_kein_token(?array $probe): string
{
    $art = $probe['art'] ?? null;
    if ($art === 'anmeldeseite') {
        return 'Kein Token, WebUntis antwortet mit der Anmeldeseite – Sitzung '
            . 'abgelaufen. Neu anmelden und sofort erneut messen.';
    }
    if ($art === 'netz') {
        return 'Kein Token, aber WebUntis war NICHT ERREICHBAR (Status 0) – '
            . 'das ist kein Ablauf. Später erneut messen.';
    }
    if ($art === 'jwt') {
        return 'Kein Token beim ersten Versuch, bei der Nachprobe schon – '
            . 'flüchtig. Erneut messen.';
    }
    return 'Kein Token; Nachprobe ohne klare Auskunft (' . (string)($art ?? 'keine')
        . ') – abgelaufen ODER ein anderer Fehler. KEIN Befund.';
}

/** Ein Abruf, der nie wirft: Fehler werden mit Klasse und Meldung berichtet. */
function messung_abruf(object $rest, string $pfad, array $query): array
{
    try {
        $r = $rest->get($pfad, $query);
        return ['status' => (int)($r['status'] ?? 0), 'json' => $r['json'] ?? null, 'fehler' => null];
    } catch (Throwable $e) {
        return ['status' => null, 'json' => null,
                'fehler' => get_class($e) . ': ' . $e->getMessage()];
    }
}

/**
 * Der Bericht. $rest ist der Client aus mit_rest_aus_sitzung() oder NULL,
 * $grund dessen Grund (siehe dort), $probe das Ergebnis von
 * messung_token_probe() bei 'kein_token'. $heute ist einstellbar für
 * Prüfungen.
 */
function messung_sitzung_bericht(array $u, ?object $rest, ?string $grund,
                                 ?string $heute = null, ?array $probe = null): array
{
    $bericht = [
        'messung' => 'Frage 2 (BEFUND-2026-10-07-pageconfig-schuelerliste) – '
            . 'trägt der Login-Cookie pageconfig und den Stundenplan?',
        'rolle'   => (string)($u['rolle'] ?? ''),
    ];

    if ($rest === null) {
        $fehler = $grund !== null && str_starts_with($grund, 'fehler: ');
        $bericht['sitzung'] = [
            'nutzbar' => false,
            'grund'   => $grund ?? 'unbekannt',
            'deutung' => $grund === 'kein_cookie'
                ? 'Keine WebUntis-Sitzung festgehalten – neu anmelden und erneut messen.'
                : ($grund === 'kein_token'
                    ? messung_deute_kein_token($probe)
                    : ($fehler
                        ? 'AUSNAHME im Sitzungsweg – das ist NICHT der Ablauf, '
                            . 'sondern ein Fehler. Meldung steht im Grund.'
                        : 'Grund unbekannt – die Messung sagt hier nichts.')),
        ];
        if ($probe !== null) $bericht['sitzung']['token_probe'] = $probe;
        $bericht['pageconfig']  = 'nicht gemessen (keine nutzbare Sitzung)';
        $bericht['stundenplan'] = 'nicht gemessen (keine nutzbare Sitzung)';
        return $bericht;
    }
    $bericht['sitzung'] = ['nutzbar' => true, 'grund' => null];

    // ---- 1. pageconfig?type=5 ------------------------------------------
    $pc = messung_abruf($rest, '/WebUntis/api/public/timetable/weekly/pageconfig', ['type' => 5]);
    $z  = messung_pageconfig_zaehlen($pc['json']);
    $bericht['pageconfig'] = [
        'status'     => $pc['status'],
        'fehler'     => $pc['fehler'],
        'eintraege'  => $z['eintraege'],
        'mit_klasse' => $z['mit_klasse'],
        'deutung'    => $pc['fehler'] !== null ? 'Abruf mit Ausnahme – kein Befund.'
            : ($pc['status'] !== 200 ? 'Status ' . $pc['status'] . ' – kein Zugriff über diese Sitzung.'
            : (!$z['liste_gefunden'] ? 'Status 200, aber keine Liste in der Antwort – '
                . 'Antwortform prüfen (z. B. Anmeldeseite statt JSON). KEIN Befund.'
            : ($z['eintraege'] === 0 ? 'Liste leer – null Einträge sind kein Befund.'
            : 'Zugriff über die Login-Sitzung gelingt.'))),
    ];

    // ---- 2. Stundenplan der eigenen Kinder (nur Eltern) ------------------
    if (($u['rolle'] ?? '') !== 'eltern') {
        $bericht['stundenplan'] = 'entfällt – nur für Eltern gemessen (Rolle: '
            . (string)($u['rolle'] ?? '') . ')';
        return $bericht;
    }
    // Zeitraum: die letzten vier Wochen bis heute (Form wie wu_referenzzeitraum).
    $bis = $heute ?? date('Y-m-d');
    $von = date('Y-m-d', strtotime($bis . ' -27 days'));
    $kinder = array_values((array)($u['kinder'] ?? []));
    if ($kinder === []) {
        $bericht['stundenplan'] = 'Eltern-Sitzung ohne Kinder – nichts zu messen. KEIN Befund.';
        return $bericht;
    }
    $bericht['stundenplan'] = ['zeitraum' => ['von' => $von, 'bis' => $bis], 'kinder' => []];
    foreach ($kinder as $i => $k) {
        $kid = (int)($k['id'] ?? 0);
        if ($kid <= 0) {
            $bericht['stundenplan']['kinder'][] = ['kind' => 'Kind ' . ($i + 1),
                'deutung' => 'Kind ohne Kennung in der Sitzung – nicht abgefragt.'];
            continue;
        }
        $r = messung_abruf($rest, '/WebUntis/api/rest/view/v1/timetable/entries', [
            'start' => $von, 'end' => $bis,
            'resourceType' => 'STUDENT', 'resources' => $kid,
        ]);
        // Dieselbe Auswertung wie der Betrieb (wu_kind_lehrer_ermitteln).
        $ex = ($r['json'] !== null) ? rest_lehrkraefte_aus_entries($r['json'], true)
                                    : ['eintraege' => 0, 'lehrkraefte' => []];
        $anzahl = count($ex['lehrkraefte']);
        $bericht['stundenplan']['kinder'][] = [
            'kind'        => 'Kind ' . ($i + 1),
            'status'      => $r['status'],
            'fehler'      => $r['fehler'],
            'eintraege'   => (int)$ex['eintraege'],
            'lehrkraefte' => $anzahl,
            'deutung'     => $r['fehler'] !== null ? 'Abruf mit Ausnahme – kein Befund.'
                : ($r['status'] !== 200 ? 'Status ' . $r['status'] . ' – kein Zugriff über diese Sitzung.'
                : ($anzahl === 0 ? 'Status 200, aber keine Lehrkraft gefunden – '
                    . 'Ferien im Zeitraum oder andere Antwortform. KEIN Befund.'
                : 'Zugriff über die Login-Sitzung gelingt.')),
        ];
    }
    return $bericht;
}
