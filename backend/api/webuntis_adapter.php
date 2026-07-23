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

require_once __DIR__ . '/../auth/WebUntisAuth.php';
require_once __DIR__ . '/../auth/WebUntisRest.php';
require_once __DIR__ . '/../auth/extractors.php';

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
    ];

    try {
        // ---- app/data: user.id, Anzeigename, Kinder ----------------------
        $rest = new WebUntisRest($wcfg['base_url'], $wcfg['school']);
        $rest->mitSessionCookie((string)$wu->sessionCookie());
        $rest->setzeTimeout(10);
        if ($rest->tokenHolen()) {
            $rest->tenantErmitteln();
            $app = $rest->get('/WebUntis/api/rest/view/v1/app/data');
            if ($app['json'] !== null) {
                $konto = rest_konto_aus_appdata($app['json']);
                $ergebnis['user_id'] = $konto['userId'];
                $ergebnis['kinder']  = $konto['kinder'];
                $ergebnis['name']    =
                    (string)($app['json']['user']['person']['displayName'] ?? '');
            }
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
    } finally {
        $wu->logout();
    }

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
 * Rückgabe: Anzahl gefundener Lehrkräfte.
 */
function wu_kind_lehrer_ermitteln(
    array $cfg, PDO $pdo, WebUntisRest $rest,
    int $sprechtagId, int $schuelerId, string $von, string $bis
): int {
    $r = $rest->get('/WebUntis/api/rest/view/v1/timetable/entries', [
        'start' => $von, 'end' => $bis,
        'resourceType' => 'STUDENT', 'resources' => $schuelerId,
        // KEIN format-Parameter! (unbekannte Format-ID -> 404)
    ]);
    if ($r['status'] !== 200 || $r['json'] === null) return 0;

    $ex = rest_lehrkraefte_aus_entries($r['json']);
    if ($ex['lehrkraefte'] === []) return 0;

    // Kürzel -> lokale Lehrer-ID
    $stmtLehrer = $pdo->prepare('SELECT id FROM lehrer WHERE kuerzel = ? LIMIT 1');
    $stmtCache  = $pdo->prepare(
        'INSERT INTO kind_lehrer_cache
            (sprechtag_id, schueler_id, lehrer_id, faecher, stunden, ermittelt_am)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE faecher = VALUES(faecher),
            stunden = VALUES(stunden), ermittelt_am = NOW()');

    $anzahl = 0;
    foreach ($ex['lehrkraefte'] as $kuerzel => $info) {
        $stmtLehrer->execute([$kuerzel]);
        $lehrerId = $stmtLehrer->fetchColumn();
        if ($lehrerId === false) continue;   // Lehrkraft nicht in Stammdaten
        $faecher = implode(', ', array_slice(array_keys($info['faecher']), 0, 6));
        $stmtCache->execute([$sprechtagId, $schuelerId, (int)$lehrerId,
            mb_substr($faecher, 0, 190), (int)$info['stunden']]);
        $anzahl++;
    }
    return $anzahl;
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
