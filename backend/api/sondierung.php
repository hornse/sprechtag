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
require_once __DIR__ . '/../auth/extractors.php';

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
        'mitteilungen' => [
            // Nur GET! Kein Versand. 405 "Method Not Allowed" wäre sogar ein
            // POSITIVER Befund (Endpunkt existiert, erwartet POST).
            ['pfad' => '/WebUntis/api/rest/view/v1/messages'],
            ['pfad' => '/WebUntis/api/rest/view/v1/messages/status'],
            ['pfad' => '/WebUntis/api/rest/view/v1/messages/recipients'],
            // Befund 07/2026: ohne Parameter -> 400 "parameter 'recipients'
            // expects Long" -> Probe mit eigener User-ID aus app/data:
            ['pfad' => '/WebUntis/api/rest/view/v1/messages/recipients',
             'query' => ['recipients' => '{USER_ID}']],
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

/**
 * Wählt Schüler-IDs aus den gefundenen Kind-Strukturen aus.
 * WICHTIG (Befund Sondierung 07/2026): app/data enthält AUCH user.person
 * (die eigene Person des angemeldeten Kontos!) – nur Pfade, die nach
 * Schüler/Kind aussehen, dürfen als Kind-IDs gelten, sonst landet die
 * Eltern-personId in der Stundenplan-Probe. Reine Funktion – testbar.
 */
function sondierung_schueler_ids(array $funde, string $manuell = '', int $max = 3): array
{
    if ($manuell !== '') return [$manuell];
    $ids = [];
    foreach ($funde as $f) {
        if (!preg_match('/student|child|kind|pupil|ward/i', (string)($f['pfad'] ?? ''))) continue;
        foreach (($f['ids'] ?? []) as $id) {
            $id = (string)$id;
            if ($id !== '' && $id !== '-1' && !in_array($id, $ids, true)) $ids[] = $id;
            if (count($ids) >= $max) return $ids;
        }
    }
    return $ids;
}

/**
 * Sondiert die Stammdaten für die Zuordnungsvorbereitung:
 * getKlassen (Weg A) und getStudents (Weg B), dazu die Frage, ob
 * WebUntis eine Gruppenzugehörigkeit (z. B. "SuS über 18") mitliefert.
 *
 * DATENSCHUTZ: Der Bericht enthält KEINE Klarnamen von Schüler:innen.
 * Ausgegeben werden nur Feldnamen, Anzahlen und – zur Strukturprüfung –
 * ein anonymisiertes Beispiel (Namensfelder durch "…" ersetzt).
 */
function sondierung_stammdaten(WebUntisAuth $wu, WebUntisRest $rest): array
{
    $bericht = [];

    // ---- Schuljahre: welche gibt es, ist gerade eines aktiv? ---------
    // Befund 24.07.2026: In den Sommerferien ist KEIN Schuljahr aktiv;
    // getKlassen() scheitert dann mit -8998 ("schoolyear is null").
    $schuljahre = [];
    try {
        $schuljahre = $wu->getSchoolyears();
        $bericht['getSchoolyears'] = [
            'anzahl'    => count($schuljahre),
            'felder'    => $schuljahre === [] ? [] : array_keys($schuljahre[0]),
            'eintraege' => array_map(fn($s) => [
                'id'        => $s['id'] ?? null,
                'name'      => $s['name'] ?? '',
                'startDate' => $s['startDate'] ?? null,
                'endDate'   => $s['endDate'] ?? null,
            ], array_slice($schuljahre, -4)),   // die vier jüngsten
        ];
    } catch (Throwable $e) {
        $bericht['getSchoolyears'] = ['fehler' => $e->getMessage()];
    }

    $aktuell = $wu->getCurrentSchoolyear();
    $bericht['getCurrentSchoolyear'] = $aktuell === []
        ? ['aktiv' => false,
           'hinweis' => 'Kein aktives Schuljahr (Ferien zwischen zwei '
               . 'Schuljahren) – Aufrufe ohne schoolyearId scheitern.']
        : ['aktiv' => true, 'id' => $aktuell['id'] ?? null,
           'name' => $aktuell['name'] ?? ''];

    // ---- Klassen (Grundlage für Weg A) ------------------------------
    // Erst ohne Parameter, dann – falls das scheitert – mit der ID des
    // jüngsten bekannten Schuljahres.
    try {
        $klassen = $wu->getKlassen();
        $bericht['getKlassen'] = [
            'variante'        => 'ohne schoolyearId',
            'anzahl'          => count($klassen),
            'felder'          => $klassen === [] ? [] : array_keys($klassen[0]),
            'beispiele'       => array_slice(array_map(
                fn($k) => ['id' => $k['id'] ?? null, 'name' => $k['name'] ?? ''],
                $klassen), 0, 8),
        ];
    } catch (Throwable $e) {
        $bericht['getKlassen'] = ['variante' => 'ohne schoolyearId',
                                  'fehler' => $e->getMessage()];

        // Rückfall: mit expliziter Schuljahres-ID erneut versuchen
        $ids = array_values(array_filter(array_map(
            fn($s) => isset($s['id']) ? (int)$s['id'] : null, $schuljahre)));
        $probeId = $ids === [] ? null : max($ids);
        if ($probeId !== null) {
            try {
                $klassen2 = $wu->getKlassen($probeId);
                $bericht['getKlassen_mit_id'] = [
                    'schoolyearId' => $probeId,
                    'anzahl'       => count($klassen2),
                    'felder'       => $klassen2 === [] ? [] : array_keys($klassen2[0]),
                    'beispiele'    => array_slice(array_map(
                        fn($k) => ['id' => $k['id'] ?? null, 'name' => $k['name'] ?? ''],
                        $klassen2), 0, 12),
                    'bewertung'    => $klassen2 === []
                        ? 'Leere Liste – Weg A so nicht nutzbar.'
                        : 'Weg A funktioniert mit expliziter Schuljahres-ID.',
                ];
            } catch (Throwable $e2) {
                $bericht['getKlassen_mit_id'] = ['schoolyearId' => $probeId,
                    'fehler' => $e2->getMessage(),
                    'bewertung' => 'Auch mit Schuljahres-ID nicht abrufbar – '
                        . 'Weg A erst nach Beginn des neuen Schuljahres testbar.'];
            }
        }
    }

    // ---- Klassenstundenplan: liefert er die Lehrkräfte? -------------
    // Entscheidende Probe für Weg A: eine Klasse, eine Woche des
    // vergangenen Schuljahres.
    $klasseId = null;
    foreach (['getKlassen', 'getKlassen_mit_id'] as $quelle) {
        $b = $bericht[$quelle] ?? null;
        if (is_array($b) && !empty($b['beispiele'][0]['id'])) {
            $klasseId = (int)$b['beispiele'][0]['id'];
            break;
        }
    }
    if ($klasseId !== null) {
        $r = $rest->get('/WebUntis/api/rest/view/v1/timetable/entries', [
            'start' => '2026-06-15', 'end' => '2026-06-19',
            'resourceType' => 'CLASS', 'resources' => $klasseId,
        ]);
        $probe = ['klasse_id' => $klasseId, 'status' => $r['status']];
        if ($r['status'] === 200 && $r['json'] !== null) {
            $ex = rest_lehrkraefte_aus_entries($r['json']);
            $probe['lehrkraefte_gefunden'] = count($ex['lehrkraefte']);
            $probe['beispiel_kuerzel'] = array_slice(
                array_keys($ex['lehrkraefte']), 0, 10);
            $probe['bewertung'] = $ex['lehrkraefte'] === []
                ? 'Keine Lehrkräfte im Zeitraum – anderen Zeitraum probieren.'
                : 'Weg A tragfähig: Klassenstundenplan liefert Lehrkräfte.';
        } else {
            $probe['roh_auszug'] = sondierung_kuerzen($r['text'], 300);
        }
        $bericht['klassenstundenplan'] = $probe;
    }

    // ---- Schüler:innen (Grundlage für Weg B) ------------------------
    try {
        $schueler = $wu->getStudents();
        $felder = $schueler === [] ? [] : array_keys($schueler[0]);

        // Anonymisiertes Struktur-Beispiel: Namensfelder ausblenden
        $namensfelder = ['name', 'foreName', 'longName', 'displayName', 'key'];
        $beispiel = [];
        if ($schueler !== []) {
            foreach ($schueler[0] as $feld => $wert) {
                $beispiel[$feld] = in_array($feld, $namensfelder, true)
                    ? '…' : $wert;
            }
        }

        // Gibt es Hinweise auf Gruppen-/Zusatzmerkmale?
        $gruppenfelder = array_values(array_filter($felder,
            fn($f) => preg_match('/group|gruppe|category|kategorie|type|typ|flag|klass|class|year|jahr/i', $f)));

        $bericht['getStudents'] = [
            'anzahl'            => count($schueler),
            'felder'            => $felder,
            'beispiel_anonym'   => $beispiel,
            'gruppen_verdacht'  => $gruppenfelder,
            'hinweis'           => $gruppenfelder === []
                ? 'Keine Gruppen-/Klassenfelder – Weg B kann Oberstufenschüler '
                    . 'nicht vorab bestimmen; Volljährigkeit nur über '
                    . 'personType 5 beim Login erkennbar.'
                : 'Mögliche Gruppen-/Klassenfelder gefunden – Werte prüfen.',
        ];
    } catch (Throwable $e) {
        $bericht['getStudents'] = ['fehler' => $e->getMessage()];
    }

    // ---- REST-Varianten: liefern sie mehr (z. B. aktiv/Austritt)? ----
    // Die WebUntis-Oberfläche zeigt in der Schülerverwaltung "aktiv",
    // "Austrittsdatum" und "Externe Id" – irgendein Endpunkt muss diese
    // Daten liefern. getStudents() (JSON-RPC) tut es nicht.
    foreach ([
        ['pfad' => '/WebUntis/api/rest/view/v1/students'],
        ['pfad' => '/WebUntis/api/rest/view/v1/students',
         'query' => ['start' => 0, 'limit' => 3]],
        ['pfad' => '/WebUntis/api/rest/view/v2/students'],
        ['pfad' => '/WebUntis/api/rest/view/v1/master-data/students'],
        ['pfad' => '/WebUntis/api/rest/view/v1/masterdata/students'],
        ['pfad' => '/WebUntis/api/rest/view/v1/students/list'],
        ['pfad' => '/WebUntis/api/students'],
        ['pfad' => '/WebUntis/api/public/timetable/weekly/pageconfig',
         'query' => ['type' => 5]],   // liefert oft die Schülerliste
        ['pfad' => '/WebUntis/api/rest/view/v1/persons'],
        ['pfad' => '/WebUntis/api/rest/view/v1/student-groups'],
        ['pfad' => '/WebUntis/api/rest/view/v1/groups'],
        ['pfad' => '/WebUntis/api/rest/view/v1/classes'],
    ] as $kandidat) {
        $r = $rest->get($kandidat['pfad'], $kandidat['query'] ?? []);
        $zeile = ['pfad' => $kandidat['pfad'],
                  'query' => $kandidat['query'] ?? null,
                  'status' => $r['status']];
        if ($r['json'] !== null) {
            $zeile['json_schluessel'] = array_slice(array_keys($r['json']), 0, 12);
            if ($r['status'] >= 400) {
                $zeile['fehler_details'] = [
                    'errorCode' => $r['json']['errorCode'] ?? null,
                    'errorMessage' => $r['json']['errorMessage'] ?? null,
                    'validationErrors' => $r['json']['validationErrors'] ?? null,
                ];
            } else {
                // Erste Datensatz-Struktur suchen und auf die gesuchten
                // Felder prüfen (aktiv, Austritt, externe ID)
                $zeile['gefundene_felder'] = sondierung_felder_finden($r['json']);
            }
        }
        // Roh-Auszug NUR bei Fehlern – bei Erfolg stünden hier Klarnamen.
        if ($r['status'] >= 400) {
            $zeile['roh_auszug'] = sondierung_kuerzen($r['text'], 400);
        } else {
            $zeile['hinweis'] = 'Antwort erfolgreich – Inhalt aus '
                . 'Datenschutzgründen nicht abgedruckt, nur Feldnamen oben.';
        }
        $bericht['rest_varianten'][] = $zeile;
    }

    return $bericht;
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

/**
 * Listet alle vorkommenden Eintragstypen samt Status und den daran
 * hängenden Lehrkräften auf. Dient der Kalibrierung des Filters in
 * rest_lehrkraefte_aus_entries(): Nur so ist belegbar, welche
 * type-Werte die Instanz tatsächlich verwendet (Kursunterricht,
 * Klassenunterricht, Vertretung …). Reine Funktion – testbar.
 */
function sondierung_eintragstypen(mixed $json): array
{
    $typen = [];
    $lauf = function ($knoten) use (&$lauf, &$typen): void {
        if (!is_array($knoten)) return;
        if (array_key_exists('position1', $knoten)) {
            $typ    = (string)($knoten['type'] ?? '(ohne)');
            $status = (string)($knoten['status'] ?? '(ohne)');
            $schluessel = $typ . ' / ' . $status;
            if (!isset($typen[$schluessel])) {
                $typen[$schluessel] = ['anzahl' => 0, 'lehrkraefte' => [],
                    'faecher' => [], 'wuerde_gewertet' =>
                        stripos($typ, 'TEACHING') !== false
                        && in_array($status, ['REGULAR', 'CANCELLED'], true)];
            }
            $typen[$schluessel]['anzahl']++;
            for ($i = 1; $i <= 7; $i++) {
                foreach ((array)($knoten['position' . $i] ?? []) as $el) {
                    $c = $el['current'] ?? null;
                    if (!is_array($c)) continue;
                    $kuerzel = (string)($c['shortName'] ?? '');
                    if ($kuerzel === '') continue;
                    if (($c['type'] ?? '') === 'TEACHER') {
                        $typen[$schluessel]['lehrkraefte'][$kuerzel] = true;
                    } elseif (($c['type'] ?? '') === 'SUBJECT') {
                        $typen[$schluessel]['faecher'][$kuerzel] = true;
                    }
                }
            }
            return;
        }
        foreach ($knoten as $wert) {
            if (is_array($wert)) $lauf($wert);
        }
    };
    $lauf($json);

    // Mengen in Listen wandeln (kompakter im Bericht)
    foreach ($typen as $k => $v) {
        $typen[$k]['lehrkraefte'] = array_keys($v['lehrkraefte']);
        $typen[$k]['faecher']     = array_keys($v['faecher']);
    }
    return $typen;
}

/**
 * Durchsucht eine REST-Antwort nach Datensatz-Strukturen und meldet,
 * welche Felder vorkommen – insbesondere die für die Schülerverwaltung
 * gesuchten: aktiv-Kennzeichen, Austrittsdatum, externe ID (Schild).
 *
 * Gibt NUR Feldnamen und Anzahlen zurück, KEINE Werte – die Antwort
 * enthält Klarnamen. Reine Funktion – offline testbar.
 */
function sondierung_felder_finden(mixed $json, int $tiefe = 0): array
{
    if (!is_array($json) || $tiefe > 4) return [];

    // Erste Liste von Objekten suchen
    foreach ($json as $wert) {
        if (!is_array($wert)) continue;
        if (array_is_list($wert) && isset($wert[0]) && is_array($wert[0])) {
            $felder = array_keys($wert[0]);
            return [
                'anzahl_datensaetze' => count($wert),
                'felder'             => array_slice($felder, 0, 25),
                'aktiv_feld'         => sondierung_feld_treffer($felder,
                    '/^(active|aktiv|isActive|enabled|status)$/i'),
                'austritt_feld'      => sondierung_feld_treffer($felder,
                    '/(exit|austritt|leaving|until|end).*(date|datum)?|^validTo$/i'),
                'externe_id_feld'    => sondierung_feld_treffer($felder,
                    '/(extern|schild|foreign).*(id)?|^key$/i'),
                'klassen_feld'       => sondierung_feld_treffer($felder,
                    '/klass|class|grade|jahrgang|year/i'),
            ];
        }
        $tiefer = sondierung_felder_finden($wert, $tiefe + 1);
        if ($tiefer !== []) return $tiefer;
    }
    return [];
}

/** Liefert alle Feldnamen, die auf ein Muster passen. */
function sondierung_feld_treffer(array $felder, string $muster): array
{
    return array_values(array_filter($felder,
        fn($f) => (bool)preg_match($muster, (string)$f)));
}

/**
 * Sammelt aus einer timetable/entries-Antwort alle TEACHER-Elemente
 * (defensiv-rekursiv: Positionsbedeutung ist formatabhängig, daher wird
 * NUR current.type gelesen – siehe Modul-README). Beweisführung für
 * "welche Lehrkräfte unterrichten dieses Kind". Reine Funktion – testbar.
 */
function sondierung_lehrer_aus_entries(mixed $json): array
{
    $lehrer = [];
    $lauf = function ($knoten) use (&$lauf, &$lehrer): void {
        if (!is_array($knoten)) return;
        $aktuell = $knoten['current'] ?? null;
        if (is_array($aktuell) && ($aktuell['type'] ?? '') === 'TEACHER') {
            $name = $aktuell['shortName'] ?? $aktuell['displayName']
                ?? $aktuell['longName'] ?? $aktuell['name'] ?? null;
            $id   = $aktuell['id'] ?? null;
            $kennung = ($name ?? '?') . ($id !== null ? ' (' . $id . ')' : '');
            $lehrer[$kennung] = true;
        }
        foreach ($knoten as $wert) $lauf($wert);
    };
    $lauf($json);
    return array_keys($lehrer);
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
 * ein zusätzlicher GET-Pfad (beginnend mit /WebUntis/…). $von/$bis
 * (YYYY-MM-DD) überschreiben den Probezeitraum – wichtig in Ferien:
 * normale Schulwoche wählen!
 */
function sondierung_ausfuehren(
    array $cfg, string $benutzer, string $passwort,
    array $gruppen, array $extraPfade = [], string $schuelerIdManuell = '',
    string $von = '', string $bis = ''
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

    // ---- 1. authenticate: personType-Befund -----------------------------
    $auth = $wu->authenticate($benutzer, $passwort);
    $bericht['authenticate'] = [
        'alle_schluessel' => array_keys($auth),
        'personType'      => $auth['personType'] ?? null,
        'personId'        => $auth['personId']   ?? null,
        'klasseId'        => $auth['klasseId']   ?? null,
        'rolle_bekannt'   => match ($auth['personType'] ?? -1) {
            2 => 'Lehrkraft', 5 => 'Schüler', 16 => 'WebUntis-Admin',
            12 => 'Erziehungsberechtigte(r) (LEGAL_GUARDIAN – Befund Sondierung 07/2026)',
            default => 'UNBEKANNT – Wert notieren!',
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

        // ---- 3. app/data IMMER intern abrufen (liefert die IDs für alle
        //         weiteren Proben); in den Bericht nur bei Gruppe "basis" ----
        $appData = $rest->get('/WebUntis/api/rest/view/v1/app/data');
        $funde   = $appData['json'] !== null
            ? sondierung_kind_strukturen($appData['json']) : [];
        $userId  = (string)($appData['json']['user']['id'] ?? '');
        $schuelerIds = sondierung_schueler_ids($funde, $schuelerIdManuell);

        if (in_array('basis', $gruppen, true)) {
            $bericht['app_data'] = [
                'status'          => $appData['status'],
                'json_schluessel' => $appData['json'] !== null
                    ? array_slice(array_keys($appData['json']), 0, 15) : [],
                'user_schluessel' => isset($appData['json']['user'])
                        && is_array($appData['json']['user'])
                    ? array_keys($appData['json']['user']) : [],
                'kind_strukturen' => $funde,
                'user_id'         => $userId,
                'schueler_ids_gewaehlt' => $schuelerIds,
                'roh_auszug'      => sondierung_kuerzen($appData['text'], 1500),
            ];
        }

        // ---- 4. Zeitraum: manuell > Standard (Vorwoche der Probe) -----------
        $datumOk = fn(string $d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        $start = $datumOk($von) ? $von : date('Y-m-d', strtotime('monday this week'));
        $ende  = $datumOk($bis) ? $bis : date('Y-m-d', strtotime('friday this week'));
        $ersetzen = static function (string $s) use ($start, $ende, $userId): string {
            return strtr($s, ['{START}' => $start, '{ENDE}' => $ende,
                '{USER_ID}' => $userId]);
        };

        // ---- 5. Stundenplan: je gefundenem/angegebenem Kind einzeln ---------
        if (in_array('stundenplan', $gruppen, true)) {
            $bericht['stundenplan'] = [];
            if ($schuelerIds === []) {
                $bericht['stundenplan'][] = ['pfad' => '/WebUntis/api/rest/view/v1/timetable/entries',
                    'uebersprungen' => 'Keine Schüler-ID gefunden (app/data ohne '
                        . 'user.students-Einträge) – unter „Erweitert" manuell angeben'];
            }
            foreach ($schuelerIds as $sid) {
                $query = ['start' => $start, 'end' => $ende,
                          'resourceType' => 'STUDENT', 'resources' => $sid];
                $voll  = $rest->get('/WebUntis/api/rest/view/v1/timetable/entries', $query);
                $probe = [
                    'pfad'        => '/WebUntis/api/rest/view/v1/timetable/entries',
                    'query'       => $query,
                    'status'      => $voll['status'],
                    'contentType' => $voll['contentType'],
                ];
                if ($voll['json'] !== null) {
                    $probe['json_schluessel'] = array_slice(array_keys($voll['json']), 0, 15);
                    if ($voll['status'] >= 400) {
                        $probe['fehler_details'] = [
                            'errorCode'        => $voll['json']['errorCode'] ?? null,
                            'validationErrors' => $voll['json']['validationErrors'] ?? null,
                            'message'          => $voll['json']['message'] ?? null,
                        ];
                    } else {
                        $probe['lehrer_gefunden'] =
                            sondierung_lehrer_aus_entries($voll['json']);
                        // Kalibrierung des Produktivfilters: welche
                        // Eintragstypen kommen vor, welche werden gewertet?
                        $probe['eintragstypen'] =
                            sondierung_eintragstypen($voll['json']);
                        $probe['produktiv_extraktor'] =
                            array_keys(rest_lehrkraefte_aus_entries(
                                $voll['json'])['lehrkraefte']);
                    }
                }
                $probe['roh_auszug'] = sondierung_kuerzen($voll['text']);
                $bericht['stundenplan'][] = $probe;
            }
            $klasseId = (string)($auth['klasseId'] ?? '0');
            if ($klasseId !== '' && $klasseId !== '0') {
                $bericht['stundenplan'][] = sondierung_probe($rest,
                    '/WebUntis/api/rest/view/v1/timetable/entries', [
                        'start' => $start, 'end' => $ende,
                        'resourceType' => 'CLASS', 'resources' => $klasseId,
                    ]);
            }
        }

        // ---- 5b. Stammdaten (Klassen, Schüler:innen, Gruppen) --------------
        if (in_array('stammdaten', $gruppen, true)) {
            $bericht['stammdaten'] = sondierung_stammdaten($wu, $rest);
        }

        // ---- 6. Statische Kandidaten-Gruppen --------------------------------
        foreach (sondierung_kandidaten() as $gruppe => $proben) {
            if (!in_array($gruppe, $gruppen, true)) continue;
            $bericht[$gruppe] = [];
            foreach ($proben as $p) {
                $query = array_map($ersetzen, $p['query'] ?? []);
                if (in_array('', $query, true)) {
                    $bericht[$gruppe][] = ['pfad' => $p['pfad'],
                        'uebersprungen' => 'ID-Platzhalter nicht auflösbar'];
                    continue;
                }
                $bericht[$gruppe][] = sondierung_probe($rest, $p['pfad'], $query);
            }
        }

        // ---- 7. Freie Zusatzpfade -------------------------------------------
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
