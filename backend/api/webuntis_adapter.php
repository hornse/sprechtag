<?php
// ============================================================
// webuntis_adapter.php – Brücke zwischen WebUntis und DB
//
// Trennt die Anwendungslogik von den API-Eigenheiten:
//   * Login + Rollenerkennung (personType 2/5/12/16)
//   * Kinder eines Eltern-Kontos (app/data -> user.students)
//   * Lehrkräfte je Kind (timetable/entries über Referenzzeitraum,
//     ohne Vertretungen) mit DB-Cache
//   * Stammdaten-Sync (Lehrkräfte, Räume)
//
// DATENSPARSAMKEIT: Es werden KEINE Namen von Eltern oder Kindern
// in die DB geschrieben. Namen leben nur in der Session.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../helfer.php';
require_once __DIR__ . '/../auth/WebUntisAuth.php';
require_once __DIR__ . '/../auth/WebUntisRest.php';
require_once __DIR__ . '/../auth/extractors.php';
require_once __DIR__ . '/klassenleitung.php';

// ---- Sitzungszugang (v0.9.72, E17) ----------------------------------------

/**
 * Der Zugang zu WebUntis über die Sitzung der ANGEMELDETEN PERSON – seit
 * v0.9.72 der einzige. Das Dienstkonto ist abgeschafft (E17): Jede Aktion
 * läuft unter dem Namen dessen, der sie auslöst, und eine abgelaufene
 * Sitzung wird gesagt, nicht still überbrückt.
 *
 * Grundlage ist der beim Login festgehaltene Sitzungscookie. Gemessen
 * (lernzeiten, 06.10.2026): Die Sitzung lebt 25–30 Minuten und verlängert
 * sich NICHT durch Nutzung.
 *
 * Rückgabe: ['rest' => ?WebUntisRest, 'art' => ?string, 'grund' => ?string]
 *   art null               – nutzbar
 *   art 'abgelaufen'       – neu anmelden hilft; grund 'kein_cookie' (keine
 *                            Sitzung festgehalten) oder 'kein_token'
 *   art 'nicht_erreichbar' – WebUntis antwortet nicht (Status 0, ab 500,
 *                            flüchtig) oder eine Ausnahme (Exception) im Abruf
 *   art 'kaputt'           – ein Programmierfehler (Error). Bis v0.9.71 sah
 *                            er aus wie ein Ablauf (E9); jetzt fällt er auf.
 *
 * Die Nachprobe: tokenHolen() sagt nur ja oder nein (vendort aus
 * webuntis-client-php). Bei nein wird derselbe Abruf einmal gelesen – wie
 * messung_token_probe() seit v0.9.54. Status 0 ist das Netz, nicht der
 * Ablauf. ab 500 als „nicht erreichbar“ ist abgeleitet, nicht gemessen.
 *
 * $client: nur für Prüfungen – baut den Client statt new WebUntisRest.
 */
function wu_sitzung(array $cfg, ?callable $client = null): array
{
    $cookie = function_exists('auth_wu_cookie') ? auth_wu_cookie() : null;
    if ($cookie === null) return ['rest' => null, 'art' => 'abgelaufen', 'grund' => 'kein_cookie'];

    try {
        $wcfg = $cfg['webuntis'];
        $rest = $client !== null ? $client() : new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $rest->mitSessionCookie($cookie);
        $rest->setzeTimeout(15);
        if (!$rest->tokenHolen()) {
            $probe  = $rest->get('/WebUntis/api/token/new');
            $status = (int)($probe['status'] ?? 0);
            $text   = trim((string)($probe['text'] ?? ''));
            if ($status === 0 || $status >= 500) {
                return ['rest' => null, 'art' => 'nicht_erreichbar',
                        'grund' => 'nicht_erreichbar: Status ' . $status];
            }
            if ($status === 200 && substr_count($text, '.') === 2 && !str_contains($text, '<')) {
                return ['rest' => null, 'art' => 'nicht_erreichbar',
                        'grund' => 'nicht_erreichbar: flüchtig (Token erst bei der Nachprobe)'];
            }
            return ['rest' => null, 'art' => 'abgelaufen', 'grund' => 'kein_token'];
        }
        $rest->tenantErmitteln();
        return ['rest' => $rest, 'art' => null, 'grund' => null];
    } catch (Exception $e) {
        error_log('sprechtag: WebUntis über die Sitzung nicht erreichbar: ' . $e->getMessage());
        return ['rest' => null, 'art' => 'nicht_erreichbar',
                'grund' => 'fehler: ' . get_class($e) . ': ' . $e->getMessage()];
    } catch (Error $e) {
        error_log('sprechtag: FEHLER im Sitzungszugang: ' . get_class($e) . ': ' . $e->getMessage());
        return ['rest' => null, 'art' => 'kaputt',
                'grund' => 'fehler: ' . get_class($e) . ': ' . $e->getMessage()];
    }
}

/** Was die handelnde Person liest – je Ursache ein eigener Satz. */
function wu_sitzung_meldung(?string $art): string
{
    return match ($art) {
        'abgelaufen'       => 'Ihre WebUntis-Anmeldung ist abgelaufen. Bitte melden Sie sich neu an.',
        'nicht_erreichbar' => 'WebUntis ist gerade nicht erreichbar. Bitte versuchen Sie es in einigen Minuten erneut.',
        'kaputt'           => 'Interner Fehler beim Zugang zu WebUntis. Bitte die Administration informieren.',
        default            => '',
    };
}

/**
 * Antwort einer Route, die ohne nutzbare Sitzung nichts tun kann: 409 mit
 * der Meldung und der Ursache in 'sitzung' – die Oberfläche bietet bei
 * 'abgelaufen' die Neuanmeldung an. Nie 401: Das hieße „bei sprechtag
 * nicht angemeldet“, und das stimmt hier nicht.
 */
function json_sitzung_fehlt(array $sitzung): never
{
    $art = (string)($sitzung['art'] ?? 'kaputt');
    json_ok(['fehler' => wu_sitzung_meldung($art), 'sitzung' => $art], 409);
}

/**
 * Benutzergruppe der angemeldeten Person aus /WebUntis/api/profile/general
 * (data.profile.userGroup, Text; gemessen 09.10.2026 für alle drei Rollen).
 * Scheitert der Abruf, kommt null – die Anmeldung gelingt trotzdem; nur das
 * Buchen volljähriger Schüler bleibt dann mit Erklärung gesperrt (E15).
 * catch (Exception), nicht Throwable: Ein Programmierfehler soll auffallen,
 * nicht als „Gruppe unbekannt“ erscheinen (FALLSTRICKE 3).
 */
function wu_profil_gruppe(object $rest): ?string
{
    try {
        $r = $rest->get('/WebUntis/api/profile/general');
    } catch (Exception $e) {
        error_log('sprechtag: Benutzergruppe nicht lesbar: ' . $e->getMessage());
        return null;
    }
    $g = $r['json']['data']['profile']['userGroup'] ?? null;
    return is_string($g) && trim($g) !== '' ? trim($g) : null;
}

/**
 * Benutzergruppen der Schule aus /WebUntis/api/userrole/config (v0.9.68) –
 * Auswahlliste für die zugelassenen Gruppen (E15). Gemessen 09.10.2026 über
 * unsere Sitzung: nur die Verwaltung darf (Admin 200, sonst 403); Einträge
 * mit id, label (wie profile/general auf 20 Zeichen gekürzt), userRole
 * (−1 = schuleigen), userCount und userCountByUserRole (Objekt; Schüler unter
 * STUDENT). Rückgabe ['gruppen' => [...]|null, 'fehler' => Grund|null]; je
 * Gruppe nur id, label, userRole, userCount, schueler – keine Personen.
 */
function wu_benutzergruppen(object $rest): array
{
    try {
        $r = $rest->get('/WebUntis/api/userrole/config');
    } catch (Exception $e) {
        error_log('sprechtag: Benutzergruppen nicht lesbar: ' . $e->getMessage());
        return ['gruppen' => null, 'fehler' => 'Ausnahme: ' . $e->getMessage()];
    }
    $status = (int)($r['status'] ?? 0);
    if ($status !== 200) return ['gruppen' => null, 'fehler' => 'Status ' . $status];
    $liste = $r['json']['data']['userGroups'] ?? null;
    if (!is_array($liste) || !array_is_list($liste)) {
        return ['gruppen' => null, 'fehler' => 'keine Gruppenliste in der Antwort'];
    }
    $aus = [];
    foreach ($liste as $e) {
        if (!is_array($e) || !is_string($e['label'] ?? null) || trim($e['label']) === '') continue;
        $je = is_array($e['userCountByUserRole'] ?? null) ? $e['userCountByUserRole'] : [];
        $aus[] = [
            'id'        => is_int($e['id'] ?? null) ? $e['id'] : null,
            'label'     => trim($e['label']),
            'userRole'  => is_int($e['userRole'] ?? null) ? $e['userRole'] : null,
            'userCount' => is_int($e['userCount'] ?? null) ? $e['userCount'] : null,
            'schueler'  => (int)($je['STUDENT'] ?? 0),
        ];
    }
    return ['gruppen' => $aus, 'fehler' => null];
}

/**
 * Auswahlliste für die Verwaltungsseite: über die WebUntis-Sitzung der
 * angemeldeten Person ($sitzung liefert ['rest' => ?Client, 'grund' => ?string]
 * wie wu_sitzung()). Scheitert es, kommt keine Liste, sondern ein
 * lesbarer Grund – die Seite fällt dann aufs Eintippen zurück.
 */
function schueler_gruppen_auswahl(callable $sitzung): array
{
    $s = $sitzung();
    if (($s['rest'] ?? null) === null) {
        $grund = (string)($s['grund'] ?? '');
        return ['auswahl' => null, 'auswahl_fehler' => $grund === 'kein_cookie'
            ? 'keine WebUntis-Sitzung festgehalten – bitte abmelden und neu anmelden'
            : ($grund === 'kein_token' ? 'WebUntis-Sitzung abgelaufen – bitte abmelden und neu anmelden'
            : 'WebUntis-Sitzung nicht nutzbar')];
    }
    $g = wu_benutzergruppen($s['rest']);
    if ($g['gruppen'] === null) {
        return ['auswahl' => null, 'auswahl_fehler' => 'Gruppenliste aus WebUntis nicht abrufbar (' . $g['fehler'] . ')'];
    }
    return ['auswahl' => gruppen_auswahl_sortieren($g['gruppen']), 'auswahl_fehler' => null];
}

/**
 * Meldet ein Konto an und ermittelt Rolle und Kontext.
 *
 * Rückgabe:
 *   rolle        – 'admin'|'lehrkraft'|'eltern'|'schueler'
 *   personType   – roher WebUntis-Wert
 *   person_id    – personId aus authenticate
 *   user_id      – user.id aus app/data (Adressat für Mitteilungen)
 *   name         – Anzeigename (nur Session, nicht DB!)
 *   kuerzel      – nur bei Lehrkräften
 *   lehrer_id    – lokale DB-ID der Lehrkraft (falls vorhanden)
 *   kinder       – [['id'=>int,'name'=>string], …] (nur Eltern)
 *   wu_gruppe    – Benutzergruppe aus profile/general (Text) oder null (E15)
 *
 * Wirft RuntimeException bei Anmeldefehlern.
 */
function wu_login(array $cfg, PDO $pdo, string $benutzer, string $passwort): array
{
    $wcfg = $cfg['webuntis'];
    $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);
    $auth = $wu->authenticate($benutzer, $passwort);

    $personType = (int)($auth['personType'] ?? 0);
    $personId   = (int)($auth['personId'] ?? 0);

    if (!in_array($personType, $wcfg['allowed_person_types'], true)) {
        $wu->logout();
        throw new RuntimeException('Dieser Kontotyp ist für die Terminbuchung nicht freigeschaltet');
    }

    $ergebnis = [
        'rolle'      => 'eltern',
        'personType' => $personType,
        'person_id'  => $personId,
        'user_id'    => null,
        'name'       => '',
        'kuerzel'    => null,
        'lehrer_id'  => null,
        'kinder'     => [],
        'wu_gruppe'  => null,
    ];

    try {
        // ---- app/data: user.id, Anzeigename, Kinder ----------------------
        $rest = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $rest->mitSessionCookie((string)$wu->sessionCookie());
        $rest->setzeTimeout(10);
        $restOk = $rest->tokenHolen();
        if ($restOk) {
            $rest->tenantErmitteln();
            $app = $rest->get('/WebUntis/api/rest/view/v1/app/data');
            if ($app['json'] !== null) {
                $konto = rest_konto_aus_appdata($app['json']);
                $ergebnis['user_id'] = $konto['userId'];
                $ergebnis['kinder']  = $konto['kinder'];
                $ergebnis['name']    =
                    (string)($app['json']['user']['person']['displayName'] ?? '');
            }
            $ergebnis['wu_gruppe'] = wu_profil_gruppe($rest);
        }

        // ---- Rollenbestimmung --------------------------------------------
        if ($personType === 2) {
            $ergebnis['rolle'] = 'lehrkraft';
            // Kürzel + Name aus getTeachers (JSESSIONID nötig)
            foreach ($wu->getTeachers() as $t) {
                if ((int)($t['id'] ?? -999) === $personId) {
                    $ergebnis['kuerzel'] = (string)($t['name'] ?? '');
                    $langname = trim(((string)($t['foreName'] ?? '')) . ' '
                        . ((string)($t['longName'] ?? '')));
                    if ($langname !== '') $ergebnis['name'] = $langname;
                    break;
                }
            }
        } elseif ($personType === 16) {
            // WebUntis-Admin: personId = -1, KEIN Eintrag in getTeachers()
            $ergebnis['rolle']   = 'admin';
            $ergebnis['kuerzel'] = $wcfg['admin_kuerzel'][0] ?? null;
            if ($ergebnis['name'] === '') $ergebnis['name'] = 'WebUntis-Administration';
        } elseif ($personType === 5) {
            $ergebnis['rolle'] = 'schueler';
            // Volljährige Schüler buchen für sich selbst
            $ergebnis['kinder'] = [['id' => $personId, 'name' => $ergebnis['name']]];
        } else {
            $ergebnis['rolle'] = 'eltern';   // personType 12 = LEGAL_GUARDIAN
        }

        // ---- Name und Klasse der eigenen Kinder (Zug 4, E20) -------------
        // Einmal bei der Anmeldung, solange die WebUntis-Sitzung frisch ist;
        // gebucht wird später ohne WebUntis (Entscheidung Betreiber,
        // 09.10.2026). Nur Eltern und volljährige Schüler.
        $ergebnis['kind_daten'] = wu_kind_daten_login($restOk ? $rest : null, $ergebnis['rolle'],
            $ergebnis['kinder'], $ergebnis['name']);
    } catch (Throwable $e) {
        // Bei einem Fehler die WebUntis-Sitzung freigeben und weiterwerfen.
        try { $wu->logout(); } catch (Throwable $e2) { /* egal */ }
        throw $e;
    }

    // Die WebUntis-Sitzung bleibt bei erfolgreicher Anmeldung BEWUSST offen.
    // Der Cookie wandert in die PHP-Session, damit die Lehrkraft während
    // ihrer Sitzung unter EIGENEM Namen Mitteilungen senden kann – ohne dass
    // ihr Passwort je gespeichert wird.
    //
    // Gemessen (lernzeiten, 29.09.2026, Produktivsystem): Die Sitzung lebt
    // 25–30 Minuten (Lehrkraft) und verlängert sich NICHT durch Nutzung.
    // Danach ist ein erneutes Anmelden nötig; der Versand meldet das als
    // „nicht gesendet – bitte neu anmelden", die Mitteilung bleibt offen.
    $ergebnis['wu_cookie'] = (string)$wu->sessionCookie();

    // ---- Lehrkraft in lokaler DB nachschlagen + Admin per Kürzel ---------
    if ($ergebnis['kuerzel'] !== null && $ergebnis['kuerzel'] !== '') {
        $st = $pdo->prepare('SELECT id FROM lehrer WHERE kuerzel = ? LIMIT 1');
        $st->execute([$ergebnis['kuerzel']]);
        $treffer = $st->fetchColumn();
        if ($treffer !== false) $ergebnis['lehrer_id'] = (int)$treffer;

        if ($ergebnis['rolle'] === 'lehrkraft') {
            // Admin über config-Liste ODER app_admins-Tabelle
            $ausConfig = in_array($ergebnis['kuerzel'],
                (array)($wcfg['admin_kuerzel'] ?? []), true);
            $st = $pdo->prepare('SELECT COUNT(*) FROM app_admins WHERE lehrer_kuerzel = ?');
            $st->execute([$ergebnis['kuerzel']]);
            if ($ausConfig || (int)$st->fetchColumn() > 0) {
                $ergebnis['rolle'] = 'admin';
            }
        }
    }

    return $ergebnis;
}

/**
 * Ermittelt die unterrichtenden Lehrkräfte eines Kindes und schreibt
 * sie in kind_lehrer_cache. Nutzt den Referenzzeitraum des Sprechtags
 * (Standard: vier Wochen vor dem Sprechtag-Datum).
 *
 * Braucht eine ANGEMELDETE REST-Session mit Leseberechtigung für den
 * Stundenplan des Kindes (Eltern-Session reicht für das eigene Kind).
 *
 * Rückgabe: ['anzahl' => int, 'uebersprungen' => string[]]
 * 'uebersprungen' enthält Kürzel aus dem Stundenplan, zu denen kein
 * Stammsatz existiert – fast immer ein Zeichen für veraltete Stammdaten.
 */
function wu_kind_lehrer_ermitteln(
    array $cfg, PDO $pdo, WebUntisRest $rest,
    int $sprechtagId, int $schuelerId, string $von, string $bis,
    bool $mitKlausuren = true
): array {
    $r = $rest->get('/WebUntis/api/rest/view/v1/timetable/entries', [
        'start' => $von, 'end' => $bis,
        'resourceType' => 'STUDENT', 'resources' => $schuelerId,
        // KEIN format-Parameter! (unbekannte Format-ID -> 404)
    ]);
    if ($r['status'] !== 200 || $r['json'] === null) {
        return ['anzahl' => 0, 'uebersprungen' => []];
    }

    $ex = rest_lehrkraefte_aus_entries($r['json'], $mitKlausuren);
    if ($ex['lehrkraefte'] === []) return ['anzahl' => 0, 'uebersprungen' => []];

    // Kürzel -> lokale Lehrer-ID
    $stmtLehrer = $pdo->prepare('SELECT id FROM lehrer WHERE kuerzel = ? LIMIT 1');
    $stmtCache  = $pdo->prepare(
        'INSERT INTO kind_lehrer_cache
            (sprechtag_id, schueler_id, lehrer_id, faecher, stunden,
             klausuren, ermittelt_am)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE faecher = VALUES(faecher),
            stunden = VALUES(stunden), klausuren = VALUES(klausuren),
            ermittelt_am = NOW()');

    $anzahl = 0;
    $uebersprungen = [];
    foreach ($ex['lehrkraefte'] as $kuerzel => $info) {
        $stmtLehrer->execute([$kuerzel]);
        $lehrerId = $stmtLehrer->fetchColumn();
        if ($lehrerId === false) {
            // Lehrkraft nicht in den Stammdaten – NICHT stillschweigend
            // verwerfen, sonst fehlt sie später kommentarlos in der
            // Buchungsliste. Ursache ist meist ein veralteter Sync.
            $uebersprungen[] = $kuerzel;
            error_log('sprechtag: Lehrkraft "' . $kuerzel . '" aus dem Stundenplan '
                . 'von Schüler ' . $schuelerId . ' fehlt in der Tabelle lehrer – '
                . 'Stammdaten synchronisieren.');
            continue;
        }
        $faecher = implode(', ', array_slice(array_keys($info['faecher']), 0, 6));
        $stmtCache->execute([$sprechtagId, $schuelerId, (int)$lehrerId,
            kuerze($faecher, 190), (int)$info['stunden'],
            (int)($info['klausuren'] ?? 0)]);
        $anzahl++;
    }
    return ['anzahl' => $anzahl, 'uebersprungen' => $uebersprungen];
}

/**
 * Synchronisiert Lehrkräfte und Räume aus WebUntis in die lokale DB.
 * Vorsicht: WebUntis-IDs können 0 sein – nie empty() verwenden!
 *
 * Rückgabe: ['lehrer' => int, 'raeume' => int]
 */
function wu_stammdaten_sync(array $cfg, PDO $pdo, string $benutzer, string $passwort): array
{
    $wcfg = $cfg['webuntis'];
    $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);
    $wu->authenticate($benutzer, $passwort);

    $zahl = ['lehrer' => 0, 'raeume' => 0];
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO lehrer (webuntis_id, kuerzel, name, aktiv, zuletzt_sync)
             VALUES (?, ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE kuerzel = VALUES(kuerzel),
                name = VALUES(name), aktiv = 1, zuletzt_sync = NOW()');
        $gesehen = [];   // Duplikate im selben Lauf abfangen
        foreach ($wu->getTeachers() as $t) {
            if (!array_key_exists('id', $t)) continue;
            $id = (int)$t['id'];
            if (isset($gesehen[$id])) continue;
            $gesehen[$id] = true;
            $stmt->execute([$id, (string)($t['name'] ?? ''),
                trim(((string)($t['foreName'] ?? '')) . ' ' . ((string)($t['longName'] ?? '')))]);
            $zahl['lehrer']++;
        }

        try {
            $stmtR = $pdo->prepare(
                'INSERT INTO raeume (webuntis_id, kuerzel, name, aktiv)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE kuerzel = VALUES(kuerzel),
                    name = VALUES(name), aktiv = 1');
            $gesehenR = [];
            foreach ($wu->getRooms() as $r) {
                if (!array_key_exists('id', $r)) continue;
                $id = (int)$r['id'];
                if (isset($gesehenR[$id])) continue;
                $gesehenR[$id] = true;
                $stmtR->execute([$id, (string)($r['name'] ?? ''),
                    (string)($r['longName'] ?? '')]);
                $zahl['raeume']++;
            }
        } catch (Throwable $e) { /* Räume sind optional */ }
    } finally {
        $wu->logout();
    }
    return $zahl;
}

/**
 * Standard-Referenzzeitraum: vier Wochen, endend eine Woche vor dem
 * Sprechtag (damit keine Ferien-/Prüfungswoche direkt davor stört).
 * Rückgabe: ['von' => 'YYYY-MM-DD', 'bis' => 'YYYY-MM-DD']
 */
function wu_referenzzeitraum(string $sprechtagDatum): array
{
    $ende  = strtotime($sprechtagDatum . ' -7 days');
    $start = strtotime('-27 days', $ende);
    return ['von' => date('Y-m-d', $start), 'bis' => date('Y-m-d', $ende)];
}

/**
 * Name und Klasse der EIGENEN Kinder bei der Anmeldung (Zug 4, E20) – über
 * kd_ermitteln(), die eine Lesestelle. Eltern: nur Kinder, die pageconfig
 * führt; ein fehlendes bekommt keinen geratenen Namen. Volljährige Schüler:
 * der Name aus der Anmeldung (person.displayName; ob pageconfig in einer
 * Schülersitzung den eigenen Eintrag führt, ist NICHT gemessen –
 * Entscheidung Betreiber, 09.10.2026), die Klasse aus pageconfig, falls
 * vorhanden. Scheitert der Abruf, steht der Grund im Protokoll.
 *
 * @return array<int, array{name:string, klasse:string, leitung:int[]}>
 */
function wu_kind_daten_login(?object $rest, string $rolle, array $kinder, string $eigenerName): array
{
    if (!in_array($rolle, ['eltern', 'schueler'], true)) return [];
    $ids = array_values(array_filter(array_map(fn($k) => (int)($k['id'] ?? 0), $kinder),
        fn(int $i) => $i > 0));
    if ($ids === []) return [];
    $e = $rest !== null ? kd_ermitteln($rest, $ids) : ['kinder' => [], 'grund' => 'keine Sitzung'];
    if ($e['grund'] !== null) {
        error_log('sprechtag: Kinddaten bei der Anmeldung nicht ermittelt: ' . $e['grund']);
    }
    $aus = [];
    foreach ($ids as $id) {
        $kd = $e['kinder'][$id] ?? null;
        if ($rolle === 'schueler') {
            $aus[$id] = ['name' => $eigenerName, 'klasse' => (string)($kd['klasse'] ?? ''),
                         'leitung' => $kd['leitung'] ?? []];
        } elseif ($kd !== null) {
            $aus[$id] = ['name' => kd_name($kd), 'klasse' => $kd['klasse'], 'leitung' => $kd['leitung']];
        }
    }
    return $aus;
}

/**
 * Name und Klasse eines eigenen Kindes für die Elternbuchung (v0.9.76).
 * Sie stehen seit der Anmeldung in der Sitzung. Fehlt der Name – Anmeldung
 * vor v0.9.76, oder WebUntis gab ihn beim Login nicht her –, wird er über
 * die WebUntis-Sitzung nachgeholt und in die Sitzung ergänzt. Gelingt das
 * nicht, kommt KEIN Name: Die Route bucht dann nicht, statt still einen
 * leeren Namen ins Kalender-Abo zu schreiben.
 *
 * Liefert ['name','klasse'], ['sitzung' => wu_sitzung()-Ergebnis] (keine
 * nutzbare Sitzung) oder ['grund' => …] (Liste nicht lesbar, Kind nicht
 * darin, kein eigener Name). Die Klasse darf leer sein: Kinder ohne Klasse
 * gibt es (Befund pageconfig, Abschnitt 10).
 */
function wu_kind_daten_buchung(array $cfg, int $kindId, string $rolle, string $eigenerName,
                               ?callable $client = null): array
{
    $kd = auth_kind_daten($kindId);
    if ($kd['name'] !== '') return $kd;
    $sz = wu_sitzung($cfg, $client);
    if ($sz['rest'] === null) return ['sitzung' => $sz];
    $neu = wu_kind_daten_login($sz['rest'], $rolle, [['id' => $kindId]], $eigenerName);
    if ((string)($neu[$kindId]['name'] ?? '') === '') {
        error_log('sprechtag: Kinddaten beim Buchen nicht nachgeholt (Rolle ' . $rolle . ')');
        return ['grund' => 'nicht_ermittelt'];
    }
    auth_kind_daten_ergaenzen($kindId, $neu[$kindId]);
    return auth_kind_daten($kindId);
}
