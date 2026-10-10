<?php
// ============================================================
// klassenleitung.php – Klassenleitung eines Kindes (E10, Zug 3)
//
// Quelle: timetable/filter?resourceType=CLASS über die WebUntis-Sitzung
// der angemeldeten Person – kein Dienstkonto, keine Tabelle. Zuordnung:
// klasseId aus pageconfig?type=5 → class.id, classTeacher1/2.id →
// lehrer.webuntis_id. Gemessen v0.9.56: Kennung und Kürzel passen 36/36
// bzw. 34/34 zur selben Lehrkraft, unabhängig vom Zeitraum (Befund
// pageconfig-Schülerliste, Abschnitt 11).
//
// Keine Klasse oder keine Leitung heißt: keine Hervorhebung, kein Fehler.
// ============================================================

declare(strict_types=1);

/** klasseId des Kindes aus pageconfig?type=5 (Liste unter data.elements, sonst data). 0 = keine. */
function kl_klasse_des_kindes($pageconfig, int $kindId): int
{
    $liste = is_array($pageconfig) ? ($pageconfig['data']['elements'] ?? $pageconfig['data'] ?? null) : null;
    if (!is_array($liste) || $kindId <= 0) return 0;
    foreach ($liste as $e) {
        if (is_array($e) && (int)($e['id'] ?? 0) === $kindId) {
            return max(0, (int)($e['klasseId'] ?? 0));
        }
    }
    return 0;
}

/**
 * WebUntis-Kennungen von classTeacher1/2 dieser Klasse, ohne Doppelte.
 * Leer, wenn die Klasse fehlt oder keine Leitung hat – wie eine fehlende
 * Leitung aussieht (Schlüssel fehlt, null, Objekt ohne Kennung), ist nicht
 * belegt; alle drei ergeben keine Kennung.
 */
function kl_leitung_kennungen($filter, int $klasseId): array
{
    $klassen = is_array($filter) ? ($filter['classes'] ?? $filter['data']['classes'] ?? null) : null;
    if (!is_array($klassen) || $klasseId <= 0) return [];
    foreach ($klassen as $k) {
        if (!is_array($k) || (int)($k['class']['id'] ?? 0) !== $klasseId) continue;
        $ids = [];
        foreach (['classTeacher1', 'classTeacher2'] as $f) {
            $id = is_array($k[$f] ?? null) ? (int)($k[$f]['id'] ?? 0) : 0;
            if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
        }
        return $ids;
    }
    return [];
}

/** lehrer.id zu WebUntis-Kennungen; unbekannte fallen weg. */
function kl_lehrer_ids(PDO $pdo, array $kennungen): array
{
    $kennungen = array_values(array_filter(array_map('intval', $kennungen), fn($i) => $i > 0));
    if ($kennungen === []) return [];
    $platzhalter = implode(',', array_fill(0, count($kennungen), '?'));
    $st = $pdo->prepare("SELECT id FROM lehrer WHERE webuntis_id IN ($platzhalter) ORDER BY id");
    $st->execute($kennungen);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Zwei Abrufe über die Sitzung: pageconfig (klasseId), dann
 * timetable/filter (Leitung). Zeitraum wie gemessen: vier Wochen bis
 * heute – er ist gleichgültig (gemessen), aber nur dieser ist gemessen.
 *
 * Rückgabe: ['kennungen' => int[], 'grund' => null|string]. grund ist
 * null bei Erfolg – auch, wenn das Kind keine Klasse oder die Klasse
 * keine Leitung hat. Betriebsfehler (Exception) werden zum Grund;
 * Programmfehler (Error) gehen weiter (FALLSTRICKE 3).
 */
function kl_ermitteln(object $rest, int $kindId, ?string $heute = null): array
{
    $nein = fn(string $g) => ['kennungen' => [], 'grund' => $g];
    try {
        $pc = $rest->get('/WebUntis/api/public/timetable/weekly/pageconfig', ['type' => 5]);
        if ((int)($pc['status'] ?? 0) !== 200) {
            return $nein('pageconfig: Status ' . (int)($pc['status'] ?? 0));
        }
        $json = $pc['json'] ?? null;
        if (!is_array($json['data']['elements'] ?? $json['data'] ?? null)) {
            return $nein('pageconfig: keine Liste in der Antwort');
        }
        $klasse = kl_klasse_des_kindes($json, $kindId);
        if ($klasse === 0) return ['kennungen' => [], 'grund' => null];

        $bis = $heute ?? date('Y-m-d');
        $von = date('Y-m-d', strtotime($bis . ' -27 days'));
        $tf = $rest->get('/WebUntis/api/rest/view/v1/timetable/filter', [
            'resourceType' => 'CLASS', 'timetableType' => 'STANDARD',
            'start' => $von, 'end' => $bis,
        ]);
        if ((int)($tf['status'] ?? 0) !== 200) {
            return $nein('timetable/filter: Status ' . (int)($tf['status'] ?? 0));
        }
        $tj = $tf['json'] ?? null;
        if (!is_array($tj['classes'] ?? $tj['data']['classes'] ?? null)) {
            return $nein('timetable/filter: kein classes[] in der Antwort');
        }
        return ['kennungen' => kl_leitung_kennungen($tj, $klasse), 'grund' => null];
    } catch (Exception $e) {
        return $nein('Ausnahme ' . get_class($e));
    }
}

/**
 * Klassenleitung des Kindes, je Anmeldung einmal ermittelt und in der
 * Sitzung gemerkt (Entscheidung Betreiber, 08.10.2026). Gemerkt wird nur
 * ein Erfolg; ein Fehlschlag – meist eine abgelaufene WebUntis-Sitzung –
 * ergibt keine Hervorhebung, keinen Fehler, und beim nächsten Laden einen
 * neuen Versuch. Der Grund geht ins Protokoll, nicht in die Antwort.
 *
 * $restHolen liefert ['rest' => ?object, 'grund' => ?string].
 */
function kl_aus_sitzung(int $kindId, callable $restHolen): array
{
    if (isset($_SESSION['klassenleitung'][$kindId]) && is_array($_SESSION['klassenleitung'][$kindId])) {
        return $_SESSION['klassenleitung'][$kindId];
    }
    $h = $restHolen();
    if (($h['rest'] ?? null) === null) {
        error_log('sprechtag: Klassenleitung nicht ermittelt (Sitzung): ' . (string)($h['grund'] ?? '?'));
        return [];
    }
    $e = kl_ermitteln($h['rest'], $kindId);
    if ($e['grund'] !== null) {
        error_log('sprechtag: Klassenleitung nicht ermittelt: ' . $e['grund']);
        return [];
    }
    $_SESSION['klassenleitung'][$kindId] = $e['kennungen'];
    return $e['kennungen'];
}

// ============================================================
// Kinddaten: Name und Klasse (Zug 4, v0.9.76, E20)
//
// Die EINE Stelle, die Name und Klasse eines Kindes aus WebUntis liest.
// Gemessen (Befund pageconfig-Schülerliste, Abschnitt 20): Nachname aus
// pageconfig.longName (1314/1314), Vorname aus forename (1313/1314); die
// Klasse aus timetable/filter classes[].class.displayName (40/40) – NICHT
// longName (34/40). pageconfig.name ist keine Namensform.
//
// Festgehalten wird das Ergebnis am Vorgang (Buchung, Einladung,
// Mitteilung), nicht in einer Liste: Die Kalender-Abos haben keine Sitzung
// (E8-Nachtrag).
// ============================================================

/**
 * Name, Klasse und Klassenleitung eines Kindes aus den beiden Antworten.
 * null, wenn pageconfig das Kind nicht führt. Ohne Klasse: klasse_id 0.
 * Reine Funktion.
 *
 * @return ?array{nachname:string, vorname:string, klasse_id:int, klasse:string, leitung:int[]}
 */
function kd_aus_listen($pageconfig, $filter, int $kindId): ?array
{
    $liste = is_array($pageconfig) ? ($pageconfig['data']['elements'] ?? $pageconfig['data'] ?? null) : null;
    if (!is_array($liste) || $kindId <= 0) return null;
    foreach ($liste as $e) {
        if (!is_array($e) || (int)($e['id'] ?? 0) !== $kindId) continue;
        $klasseId = max(0, (int)($e['klasseId'] ?? 0));
        $klasse = '';
        $klassen = is_array($filter) ? ($filter['classes'] ?? $filter['data']['classes'] ?? null) : null;
        if ($klasseId > 0 && is_array($klassen)) {
            foreach ($klassen as $k) {
                if (is_array($k) && (int)($k['class']['id'] ?? 0) === $klasseId) {
                    $klasse = trim((string)($k['class']['displayName'] ?? ''));
                    break;
                }
            }
        }
        return [
            'nachname'  => trim((string)($e['longName'] ?? '')),
            'vorname'   => trim((string)($e['forename'] ?? '')),
            'klasse_id' => $klasseId,
            'klasse'    => $klasse,
            'leitung'   => kl_leitung_kennungen($filter, $klasseId),
        ];
    }
    return null;
}

/** „Nachname, Vorname“ – die Form, in der die Anzeigen Kinder nennen. */
function kd_name(array $kd): string
{
    $n = trim((string)($kd['nachname'] ?? ''));
    $v = trim((string)($kd['vorname'] ?? ''));
    return $v === '' ? $n : ($n === '' ? $v : $n . ', ' . $v);
}

/**
 * Kinddaten für mehrere Kinder über die Sitzung: pageconfig, und nur wenn
 * eines davon eine Klasse hat, timetable/filter (Zeitraum wie kl_ermitteln,
 * gemessen gleichgültig). Betriebsfehler (Exception) werden zum Grund,
 * Programmfehler (Error) gehen weiter (FALLSTRICKE 3).
 *
 * @return array{kinder: array<int, ?array>, grund: ?string}
 */
function kd_ermitteln(object $rest, array $kindIds, ?string $heute = null): array
{
    $nein = fn(string $g) => ['kinder' => [], 'grund' => $g];
    try {
        $pc = $rest->get('/WebUntis/api/public/timetable/weekly/pageconfig', ['type' => 5]);
        if ((int)($pc['status'] ?? 0) !== 200) {
            return $nein('pageconfig: Status ' . (int)($pc['status'] ?? 0));
        }
        $json = $pc['json'] ?? null;
        if (!is_array($json['data']['elements'] ?? $json['data'] ?? null)) {
            return $nein('pageconfig: keine Liste in der Antwort');
        }
        $tj = null;
        $mitKlasse = array_filter(array_map('intval', $kindIds),
            fn($id) => kl_klasse_des_kindes($json, $id) > 0);
        if ($mitKlasse !== []) {
            $bis = $heute ?? date('Y-m-d');
            $von = date('Y-m-d', strtotime($bis . ' -27 days'));
            $tf = $rest->get('/WebUntis/api/rest/view/v1/timetable/filter', [
                'resourceType' => 'CLASS', 'timetableType' => 'STANDARD',
                'start' => $von, 'end' => $bis,
            ]);
            if ((int)($tf['status'] ?? 0) !== 200) {
                return $nein('timetable/filter: Status ' . (int)($tf['status'] ?? 0));
            }
            $tj = $tf['json'] ?? null;
            if (!is_array($tj['classes'] ?? $tj['data']['classes'] ?? null)) {
                return $nein('timetable/filter: kein classes[] in der Antwort');
            }
        }
        $kinder = [];
        foreach ($kindIds as $id) $kinder[(int)$id] = kd_aus_listen($json, $tj, (int)$id);
        return ['kinder' => $kinder, 'grund' => null];
    } catch (Exception $e) {
        return $nein('Ausnahme ' . get_class($e));
    }
}
