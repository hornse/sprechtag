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
        $kd = kd_eintrag($e, kd_klassen_namen($filter));
        return [
            'nachname'  => $kd['nachname'],
            'vorname'   => $kd['vorname'],
            'klasse_id' => $kd['klasse_id'],
            'klasse'    => $kd['klasse'],
            'leitung'   => kl_leitung_kennungen($filter, $kd['klasse_id']),
        ];
    }
    return null;
}

/** Klassennamen je Kennung aus timetable/filter: classes[].class.displayName (40/40, nicht longName). */
function kd_klassen_namen($filter): array
{
    $klassen = is_array($filter) ? ($filter['classes'] ?? $filter['data']['classes'] ?? null) : null;
    $namen = [];
    if (!is_array($klassen)) return $namen;
    foreach ($klassen as $k) {
        $id = is_array($k) ? (int)($k['class']['id'] ?? 0) : 0;
        if ($id > 0 && !isset($namen[$id])) $namen[$id] = trim((string)($k['class']['displayName'] ?? ''));
    }
    return $namen;
}

/**
 * Die eine Stelle, die die Felder eines pageconfig-Eintrags liest
 * (kd_aus_listen und kd_suchen). Ohne Klasse: klasse_id 0.
 *
 * @return array{id:int, nachname:string, vorname:string, klasse_id:int, klasse:string}
 */
function kd_eintrag(array $e, array $klassenNamen): array
{
    $klasseId = max(0, (int)($e['klasseId'] ?? 0));
    return [
        'id'        => (int)($e['id'] ?? 0),
        'nachname'  => trim((string)($e['longName'] ?? '')),
        'vorname'   => trim((string)($e['forename'] ?? '')),
        'klasse_id' => $klasseId,
        'klasse'    => $klasseId > 0 ? (string)($klassenNamen[$klasseId] ?? '') : '',
    ];
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
        $pc = kd_pageconfig_lesen($rest);
        if ($pc['grund'] !== null) return $nein($pc['grund']);
        $json = $pc['json'];
        $tj = null;
        $mitKlasse = array_filter(array_map('intval', $kindIds),
            fn($id) => kl_klasse_des_kindes($json, $id) > 0);
        if ($mitKlasse !== []) {
            $tf = kd_klassen_lesen($rest, $heute);
            if ($tf['grund'] !== null) return $nein($tf['grund']);
            $tj = $tf['json'];
        }
        $kinder = [];
        foreach ($kindIds as $id) $kinder[(int)$id] = kd_aus_listen($json, $tj, (int)$id);
        return ['kinder' => $kinder, 'grund' => null];
    } catch (Exception $e) {
        return $nein('Ausnahme ' . get_class($e));
    }
}

/**
 * pageconfig?type=5 über die Sitzung. Wirft bei Betriebsfehlern; die
 * Aufrufer fangen Exception (nicht Error).
 *
 * @return array{json:?array, grund:?string}
 */
function kd_pageconfig_lesen(object $rest): array
{
    $pc = $rest->get('/WebUntis/api/public/timetable/weekly/pageconfig', ['type' => 5]);
    if ((int)($pc['status'] ?? 0) !== 200) {
        return ['json' => null, 'grund' => 'pageconfig: Status ' . (int)($pc['status'] ?? 0)];
    }
    $json = $pc['json'] ?? null;
    if (!is_array($json['data']['elements'] ?? $json['data'] ?? null)) {
        return ['json' => null, 'grund' => 'pageconfig: keine Liste in der Antwort'];
    }
    return ['json' => $json, 'grund' => null];
}

/**
 * timetable/filter?resourceType=CLASS über die Sitzung, Zeitraum wie
 * gemessen (vier Wochen bis heute, gleichgültig). Wirft wie oben.
 *
 * @return array{json:?array, grund:?string}
 */
function kd_klassen_lesen(object $rest, ?string $heute = null): array
{
    $bis = $heute ?? date('Y-m-d');
    $von = date('Y-m-d', strtotime($bis . ' -27 days'));
    $tf = $rest->get('/WebUntis/api/rest/view/v1/timetable/filter', [
        'resourceType' => 'CLASS', 'timetableType' => 'STANDARD',
        'start' => $von, 'end' => $bis,
    ]);
    if ((int)($tf['status'] ?? 0) !== 200) {
        return ['json' => null, 'grund' => 'timetable/filter: Status ' . (int)($tf['status'] ?? 0)];
    }
    $tj = $tf['json'] ?? null;
    if (!is_array($tj['classes'] ?? $tj['data']['classes'] ?? null)) {
        return ['json' => null, 'grund' => 'timetable/filter: kein classes[] in der Antwort'];
    }
    return ['json' => $tj, 'grund' => null];
}

// ============================================================
// Kind-Suche für Einladungsauswahl und stellvertretendes Buchen
// (Zug 4, Schritt 3, v0.9.81; E20 A/D/E, Richtungsfragen R1–R4)
//
// Je Suche zwei Abrufe über die Sitzung der Lehrkraft, kein
// Zwischenspeicher (E20 A). Ohne Suchbegriff wird nichts geladen (R1).
// Teilstring ohne Groß/Klein in Nachname, Vorname, Klasse; höchstens 60
// Treffer, damit eine ganze Klasse hineinpasst (R2). Kinder ohne Klasse
// erscheinen nicht (E20 E) – dieselbe Regel wie kd_vorgang_pruefen.
// ============================================================

const KD_SUCHE_GRENZE = 60;
const KD_SATZ_NICHT_IN_LISTE = 'Dieses Kind steht nicht in der Klassenliste aus WebUntis.';
const KD_SATZ_LISTE_NICHT_LESBAR = 'Die Klassenliste aus WebUntis ließ sich gerade nicht lesen.';

/** Die Regel aus E20 E: ein Kind mit Klasse. null (nicht in pageconfig) ist keines. */
function kd_hat_klasse(?array $kd): bool
{
    return $kd !== null && (int)($kd['klasse_id'] ?? 0) > 0;
}

/** Kleinschreibung zum Vergleichen (mb_strtolower_sicher aus mitteilungen.php, sonst strtolower). */
function kd_klein(string $s): string
{
    return function_exists('mb_strtolower_sicher') ? mb_strtolower_sicher($s) : strtolower($s);
}

/** Sortierschlüssel: klein, Umlaute wie ihr Grundbuchstabe (Ä wie A). */
function kd_sortschluessel(string $s): string
{
    return strtr(kd_klein($s), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
}

/**
 * Treffer der Suche aus den beiden Antworten. Reine Funktion.
 *
 * @return array{kinder: list<array{id:int, name:string, klasse:string}>, anzahl:int, grenze:int}
 */
function kd_suchen($pageconfig, $filter, string $suche, int $grenze = KD_SUCHE_GRENZE): array
{
    $leer = ['kinder' => [], 'anzahl' => 0, 'grenze' => $grenze];
    $nadel = kd_klein(trim($suche));
    $liste = is_array($pageconfig) ? ($pageconfig['data']['elements'] ?? $pageconfig['data'] ?? null) : null;
    if ($nadel === '' || !is_array($liste)) return $leer;
    $namen = kd_klassen_namen($filter);
    $treffer = [];
    foreach ($liste as $e) {
        if (!is_array($e)) continue;
        $kd = kd_eintrag($e, $namen);
        if ($kd['id'] <= 0 || !kd_hat_klasse($kd)) continue;
        foreach (['nachname', 'vorname', 'klasse'] as $f) {
            if (str_contains(kd_klein($kd[$f]), $nadel)) { $treffer[] = $kd; break; }
        }
    }
    usort($treffer, fn($a, $b) => strnatcasecmp($a['klasse'], $b['klasse'])
        ?: strcmp(kd_sortschluessel($a['nachname']), kd_sortschluessel($b['nachname']))
        ?: strcmp(kd_sortschluessel($a['vorname']), kd_sortschluessel($b['vorname']))
        ?: $a['id'] <=> $b['id']);
    $kinder = array_map(fn($kd) => ['id' => $kd['id'], 'name' => kd_name($kd), 'klasse' => $kd['klasse']],
        array_slice($treffer, 0, $grenze));
    return ['kinder' => $kinder, 'anzahl' => count($treffer), 'grenze' => $grenze];
}

/**
 * Die Suche über die Sitzung. $sitzung liefert wu_sitzung()
 * (['rest','art','grund']) und wird ohne Suchbegriff nicht gefragt (R1).
 *
 * @return array Treffer wie kd_suchen, oder ['sitzung' => …] (keine nutzbare
 *               Sitzung), oder ['grund' => …] (Liste nicht lesbar).
 */
function kd_suche(string $suche, callable $sitzung, ?string $heute = null): array
{
    $q = trim(mb_substr_sicher($suche, 100));
    if ($q === '') return ['kinder' => [], 'anzahl' => 0, 'grenze' => KD_SUCHE_GRENZE];
    $sz = $sitzung();
    if (($sz['rest'] ?? null) === null) return ['sitzung' => $sz];
    try {
        $pc = kd_pageconfig_lesen($sz['rest']);
        if ($pc['grund'] !== null) return ['grund' => $pc['grund']];
        $tf = kd_klassen_lesen($sz['rest'], $heute);
        if ($tf['grund'] !== null) return ['grund' => $tf['grund']];
    } catch (Exception $e) {
        return ['grund' => 'Ausnahme ' . get_class($e)];
    }
    return kd_suchen($pc['json'], $tf['json'], $q);
}

/** Kürzen auf Zeichen, auch ohne mbstring (FALLSTRICKE 3). */
function mb_substr_sicher(string $s, int $n): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n);
}

/**
 * Die eine Regel für Einladen und stellvertretendes Buchen (E20 E, R4):
 * Ein Kind ohne Klasse – oder eines, das pageconfig nicht führt – wird
 * nicht eingeladen und nicht gebucht. Zwei Stufen, zwei Sätze: „Liste
 * nicht lesbar“ (502) ist nicht „steht nicht in der Liste“ (404).
 *
 * @param array  $ermittelt Ergebnis von kd_ermitteln()
 * @param string $vorgang   'eingeladen' | 'gebucht'
 * @return ?array{status:int, text:string, grund?:string} null = kein Einwand
 */
function kd_vorgang_pruefen(array $ermittelt, int $kind, string $vorgang): ?array
{
    if (($ermittelt['grund'] ?? null) !== null) {
        return ['status' => 502, 'grund' => (string)$ermittelt['grund'],
                'text' => KD_SATZ_LISTE_NICHT_LESBAR . ' Es wurde nicht ' . $vorgang . ' – bitte erneut versuchen.'];
    }
    if (!kd_hat_klasse($ermittelt['kinder'][$kind] ?? null)) {
        return ['status' => 404, 'text' => KD_SATZ_NICHT_IN_LISTE . ' Es wurde nicht ' . $vorgang . '.'];
    }
    return null;
}
