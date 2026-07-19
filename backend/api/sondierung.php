<?php
// ============================================================
// sondierung.php – NUR-LESENDE Sondierung der WebUntis-Instanz
// für das Projekt "sprechtag".
//
// Beantwortet die drei offenen Machbarkeitsfragen:
//   1. Eltern-Login: welchen personType liefert authenticate
//      für Erziehungsberechtigte, welche Felder kommen mit?
//   2. Kind-Zuordnung: liefert die interne REST-API (app/data
//      bzw. die Sprechtag-Endpunkte der lizenzierten Instanz)
//      die verknüpften Schüler eines Eltern-Accounts?
//   3. Stundenplan je Schüler (Kurs-Ebene, Oberstufe!) und
//      Mitteilungs-Endpunkte (nur GET-Proben, kein Versand).
//
// Es wird NICHTS geschrieben: ausschließlich authenticate,
// GET-Aufrufe und logout. Die Proben laufen mit dem Konto,
// dessen Zugangsdaten eingegeben wurden (Testeltern!).
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../auth/WebUntisAuth.php';
require_once __DIR__ . '/../auth/WebUntisRest.php';

/** Kandidaten-Endpunkte je Probengruppe. {SCHUELER_ID} und {KLASSE_ID}
 *  werden zur Laufzeit ersetzt, sofern die Basis-Proben IDs gefunden haben. */
function sondierung_kandidaten(): array
{
    return [
        'sprechtag' => [
            // Sprechtag-Modul ist an frg-dusseldorf lizenziert – die
            // Weboberfläche muss also irgendeinen dieser Wege nutzen.
            ['pfad' => '/WebUntis/api/rest/view/v1/parent-days'],
            ['pfad' => '/WebUntis/api/rest/view/v1/parentdays'],
            ['pfad' => '/WebUntis/api/rest/view/v1/parent-teacher-days'],
            ['pfad' => '/WebUntis/api/rest/view/v2/parent-days'],
            ['pfad' => '/WebUntis/api/parentsday'],
            ['pfad' => '/WebUntis/api/parentsday/config'],
            ['pfad' => '/WebUntis/api/rest/view/v1/parent-days/appointments'],
        ],
        'stundenplan' => [
            ['pfad' => '/WebUntis/api/rest/view/v1/timetable/entries',
             'query' => ['start' => '{START}', 'end' => '{ENDE}',
                         'resourceType' => 'STUDENT', 'resources' => '{SCHUELER_ID}']],
            ['pfad' => '/WebUntis/api/rest/view/v1/timetable/entries',
             'query' => ['start' => '{START}', 'end' => '{ENDE}',
                         'resourceType' => 'CLASS', 'resources' => '{KLASSE_ID}']],
        ],
        'mitteilungen' => [
            // Nur GET! Kein Versand. 405 "Method Not Allowed" wäre sogar ein
            // POSITIVER Befund (Endpunkt existiert, erwartet POST).
            ['pfad' => '/WebUntis/api/rest/view/v1/messages'],
            ['pfad' => '/WebUntis/api/rest/view/v1/messages/status'],
            ['pfad' => '/WebUntis/api/rest/view/v1/messages/recipients'],
            ['pfad' => '/WebUntis/api/rest/view/v2/messages'],
        ],
    ];
}

/**
 * Durchsucht ein JSON rekursiv nach Strukturen, die nach Schüler-/
 * Kind-Zuordnung aussehen (Schlüssel wie students, children, persons, …).
 * Liefert je Fund: Pfad, Anzahl, Schlüssel des ersten Elements und
 * Kandidaten-IDs. Reine Funktion – offline testbar.
 */
function sondierung_kind_strukturen(mixed $json, string $pfad = ''): array
{
    $funde = [];
    if (!is_array($json)) return $funde;

    foreach ($json as $schluessel => $wert) {
        $hier = $pfad === '' ? (string)$schluessel : $pfad . '.' . $schluessel;
        if (is_string($schluessel)
            && preg_match('/student|child|kind|pupil|person|ward/i', $schluessel)
            && is_array($wert)) {
            $liste = array_is_list($wert) ? $wert : [$wert];
            $erstes = $liste[0] ?? null;
            $funde[] = [
                'pfad'              => $hier,
                'anzahl'            => array_is_list($wert) ? count($wert) : 1,
                'schluessel_erstes' => is_array($erstes)
                    ? array_slice(array_keys($erstes), 0, 15) : [],
                'ids' => array_values(array_filter(array_map(
                    fn($e) => is_array($e) ? ($e['id'] ?? $e['personId'] ?? $e['studentId'] ?? null) : null,
                    array_slice($liste, 0, 5)
                ), fn($v) => $v !== null)),
            ];
        }
        if (is_array($wert)) {
            $funde = array_merge($funde, sondierung_kind_strukturen($wert, $hier));
        }
    }
    return $funde;
}

/** Kürzt eine Roh-Antwort für den Bericht (kein Daten-Dump). */
function sondierung_kuerzen(string $text, int $max = 900): string
{
    $text = trim($text);
    $laenge = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($laenge <= $max) return $text;
    $kurz = function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
    return $kurz . ' …[gekürzt]';
}

/** Führt eine einzelne GET-Probe aus und fasst das Ergebnis zusammen. */
function sondierung_probe(WebUntisRest $rest, string $pfad, array $query = []): array
{
    $r = $rest->get($pfad, $query);
    $zeile = [
        'pfad'        => $pfad,
        'query'       => $query === [] ? null : $query,
        'status'      => $r['status'],
        'contentType' => $r['contentType'],
    ];
    if ($r['json'] !== null) {
        $zeile['json_schluessel'] = array_slice(array_keys($r['json']), 0, 15);
        if ($r['status'] >= 400) {
            $zeile['fehler_details'] = [
                'errorCode'        => $r['json']['errorCode'] ?? null,
                'validationErrors' => $r['json']['validationErrors'] ?? null,
                'message'          => $r['json']['message'] ?? null,
            ];
        } else {
            $zeile['kind_strukturen'] = sondierung_kind_strukturen($r['json']);
        }
    }
    $zeile['roh_auszug'] = sondierung_kuerzen($r['text']);
    return $zeile;
}

/**
 * Komplette Sondierung. $gruppen: Teilmenge von
 * ['basis','sprechtag','stundenplan','mitteilungen'], $extraPfade: je Zeile
 * ein zusätzlicher GET-Pfad (beginnend mit /WebUntis/…).
 */
function sondierung_ausfuehren(
    array $cfg, string $benutzer, string $passwort,
    array $gruppen, array $extraPfade = [], string $schuelerIdManuell = ''
): array {
    $wcfg = $cfg['webuntis'];
    $bericht = [
        'instanz'  => $wcfg['base_url'],
        'schule'   => $wcfg['school'],
        'zeit'     => date('c'),
        'gruppen'  => array_values($gruppen),
        'hinweis'  => 'Nur lesende Proben (authenticate, GET, logout). Konto: ' . $benutzer,
    ];

    $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);

    // ---- 1. authenticate: DIE Antwort auf die personType-Frage ----------
    $auth = $wu->authenticate($benutzer, $passwort);
    $bericht['authenticate'] = [
        'alle_schluessel' => array_keys($auth),
        'personType'      => $auth['personType'] ?? null,
        'personId'        => $auth['personId']   ?? null,
        'klasseId'        => $auth['klasseId']   ?? null,
        'rolle_bekannt'   => match ($auth['personType'] ?? -1) {
            2 => 'Lehrkraft', 5 => 'Schüler', 16 => 'WebUntis-Admin',
            default => 'UNBEKANNT – vermutlich Erziehungsberechtigte(r), Wert notieren!',
        },
    ];

    try {
        // ---- 2. REST-Zugang -------------------------------------------------
        $rest = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $rest->mitSessionCookie((string)$wu->sessionCookie());
        $rest->setzeTimeout(8);   // kurz: Uberspace-Proxy kappt bei ~60 s

        $bericht['rest_zugang'] = ['jwt' => $rest->tokenHolen()];
        if (!$bericht['rest_zugang']['jwt']) {
            $bericht['rest_zugang']['hinweis'] =
                'Kein JWT von /api/token/new – REST-Proben entfallen.';
            return $bericht;
        }
        $rest->tenantErmitteln();

        // ---- 3. Basis: app/data (Kind-Zuordnung!) ---------------------------
        $schuelerId = $schuelerIdManuell;
        $klasseId   = (string)($auth['klasseId'] ?? '');
        if (in_array('basis', $gruppen, true)) {
            $r = $rest->get('/WebUntis/api/rest/view/v1/app/data');
            $basis = [
                'status'          => $r['status'],
                'json_schluessel' => $r['json'] !== null
                    ? array_slice(array_keys($r['json']), 0, 15) : [],
                'user_schluessel' => isset($r['json']['user']) && is_array($r['json']['user'])
                    ? array_keys($r['json']['user']) : [],
                'kind_strukturen' => $r['json'] !== null
                    ? sondierung_kind_strukturen($r['json']) : [],
                'roh_auszug'      => sondierung_kuerzen($r['text'], 1500),
            ];
            // Erste gefundene Kind-ID automatisch für die Stundenplan-Probe nutzen
            foreach ($basis['kind_strukturen'] as $f) {
                if ($schuelerId === '' && !empty($f['ids'])) {
                    $schuelerId = (string)$f['ids'][0];
                    $basis['auto_schueler_id'] = $schuelerId;
                    break;
                }
            }
            $bericht['app_data'] = $basis;
        }

        // ---- 4. Kandidaten-Gruppen ------------------------------------------
        $start = date('Y-m-d', strtotime('monday this week'));
        $ende  = date('Y-m-d', strtotime('friday this week'));
        $ersetzen = static function (string $s) use ($start, $ende, $schuelerId, $klasseId): string {
            return strtr($s, ['{START}' => $start, '{ENDE}' => $ende,
                '{SCHUELER_ID}' => $schuelerId, '{KLASSE_ID}' => $klasseId]);
        };

        foreach (sondierung_kandidaten() as $gruppe => $proben) {
            if (!in_array($gruppe, $gruppen, true)) continue;
            $bericht[$gruppe] = [];
            foreach ($proben as $p) {
                $query = array_map($ersetzen, $p['query'] ?? []);
                // Proben mit nicht auflösbaren Platzhaltern überspringen
                if (in_array('', $query, true)) {
                    $bericht[$gruppe][] = ['pfad' => $p['pfad'],
                        'uebersprungen' => 'ID-Platzhalter nicht auflösbar '
                            . '(keine Schüler-/Klassen-ID gefunden – ggf. manuell angeben)'];
                    continue;
                }
                $bericht[$gruppe][] = sondierung_probe($rest, $p['pfad'], $query);
            }
        }

        // ---- 5. Freie Zusatzpfade -------------------------------------------
        foreach ($extraPfade as $pfad) {
            $pfad = trim($pfad);
            if ($pfad === '' || !str_starts_with($pfad, '/WebUntis/')) continue;
            $bericht['eigene_pfade'][] = sondierung_probe($rest, $ersetzen($pfad));
        }
    } finally {
        $wu->logout();   // WebUntis-Session immer freigeben
    }

    return $bericht;
}
