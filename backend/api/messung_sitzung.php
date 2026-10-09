<?php
// ============================================================
// messung_sitzung.php – MESSUNG, KEIN FEATURE  (v0.9.54)
//
//   GET /api/messung/sitzung   (jede angemeldete Person, nur Zahlen)
//
// Seit v0.9.71 zusätzlich, als EIGENE Route (POST /api/messung/liste):
// Ersetzt die Sitzung einer Lehrkraft bzw. der Verwaltung das Dienstkonto bei
// den Erinnerungen – Liste auflösen (CUSTOM/filter) und an sie senden
// (/v2/messages/users, recipientUserIds)? Nur QUICK-Listen mit höchstens 5
// Empfängern, senden nur bestätigt, genau ein Versand.
//
// Seit v0.9.69 zusätzlich, als EIGENE Route (POST /api/messung/parents):
// Erreicht recipientOption „PARENTS“ mit der Kennung des Kindes die Eltern
// auch auf unserem Pfad? VERSCHICKT GENAU EINE Testnachricht – nur bestätigt,
// nur an einen benannten Pfad, mit festem Testbetreff. Vorlage: Beilage
// docs/beilagen/lernzeiten-mitteilung-parents.md.
//
// Seit v0.9.67 zusätzlich: /WebUntis/api/userrole/config – alle
// Benutzergruppen der Schule (data.userGroups: id, label, userCount, userRole,
// userCountByUserRole) als mögliche Auswahlliste für die zugelassenen Gruppen
// (E15). Je Rolle: Status, Einträge, Felder, Gruppen mit Schülern, und ob das
// label der eigenen Gruppe ZEICHENGENAU dem Text aus profile/general gleicht.
// Gruppennamen, -kennungen und Anzahlen gehen in die Antwort; Personen nicht.
//
// Seit v0.9.64 zusätzlich: /WebUntis/api/profile/general – trägt das Profil
// der angemeldeten Person ihre Benutzergruppe (userGroup), bei jeder Rolle,
// eindeutig, und mit Kennung? Zweck: volljährige Schüler erkennen (die
// Schule pflegt Volljährigkeit als Gruppe). Gruppen- und Rollennamen und
// -kennungen vergibt die Schule – sie gehen in die Antwort; Angaben zur
// Person nicht (nur die Schlüsselnamen des Profils).
//
// Seit v0.9.56 zusätzlich: timetable/filter?resourceType=CLASS (Klassen-
// leitung als Objekt mit id und shortName), Doppelabgleich gegen lehrer,
// Schulzeit gegen einen ANGEGEBENEN Ferienzeitraum (?ferien_von/_bis).
//
// Seit v0.9.55 zusätzlich (Zug 3): Trägt pageconfig die Klassenleitung
// (classteacher/classteacher2) – gefüllt, in welchem Format, und passt
// sie zu lehrer.webuntis_id oder lehrer.kuerzel? Nur Zahlen und Formate.
//
// Zweck: Frage 2 aus docs/BEFUND-2026-10-07-pageconfig-schuelerliste.md –
// trägt der beim Login festgehaltene WebUntis-Cookie (über
// wu_sitzung(), denselben Weg wie der Mitteilungsversand)
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

/**
 * Formatangabe eines Werts – ohne den Wert selbst. Objekte nennen nur
 * ihre Schlüssel, Listen ihre Länge und das Format des ersten Elements.
 */
function messung_format($v): string
{
    if ($v === null || $v === '' || $v === []) return 'leer';
    if (is_int($v)) return 'Zahl';
    if (is_float($v)) return 'Kommazahl';
    if (is_bool($v)) return 'Wahrheitswert';
    if (is_string($v)) return ctype_digit($v) ? 'Ziffernfolge (Text)' : 'Text';
    if (is_array($v)) {
        if (array_is_list($v)) {
            return 'Liste[' . count($v) . '] von ' . messung_format($v[0]);
        }
        $k = array_keys($v);
        sort($k);
        return 'Objekt{' . implode(',', $k) . '}';
    }
    return gettype($v);
}

/**
 * Kandidaten aus einem classteacher-Wert: Kennungen (Zahl, Ziffernfolge,
 * Objekt mit id) und Texte (Text, Objekt mit name/shortName). Listen
 * werden aufgefaltet.
 */
function messung_kandidaten($v): array
{
    $ids = []; $texte = [];
    $lauf = function ($x) use (&$lauf, &$ids, &$texte): void {
        if (is_int($x) && $x > 0) { $ids[] = $x; return; }
        if (is_string($x) && $x !== '') {
            if (ctype_digit($x)) $ids[] = (int)$x; else $texte[] = $x;
            return;
        }
        if (!is_array($x)) return;
        if (array_is_list($x)) { foreach ($x as $e) $lauf($e); return; }
        if (isset($x['id'])) $lauf($x['id']);
        foreach (['name', 'shortName', 'kuerzel'] as $f) {
            if (isset($x[$f]) && is_string($x[$f]) && $x[$f] !== '' && !ctype_digit($x[$f])) {
                $texte[] = $x[$f];
            }
        }
    };
    $lauf($v);
    return ['ids' => $ids, 'texte' => $texte];
}

/**
 * Klassenleitung in pageconfig: Sind classteacher/classteacher2 gefüllt,
 * in welchem Format, und wie viele Werte passen zu lehrer.webuntis_id
 * bzw. lehrer.kuerzel? Nur Zahlen und Formatangaben.
 *
 * $lehrer: ['webuntis_ids' => int[], 'kuerzel' => string[]]
 * $kinder: Kinder der Sitzung (Eltern) – dann je „Kind n“, zugeordnet
 *          über die Kennung (derselbe Kreis, Befund Abschnitt 6);
 *          sonst NULL – dann über die ganze Liste summiert.
 */
function messung_klassenleitung(array $liste, array $lehrer, ?array $kinder): array
{
    $ids    = array_fill_keys(array_map('intval', $lehrer['webuntis_ids'] ?? []), true);
    $kuerz  = array_fill_keys(array_map('strval', $lehrer['kuerzel'] ?? []), true);
    $werte  = function (array $e) use ($ids, $kuerz): array {
        $r = [];
        foreach (['classteacher', 'classteacher2'] as $f) {
            $v = $e[$f] ?? null;
            $k = messung_kandidaten($v);
            $r[$f] = [
                'vorhanden'      => array_key_exists($f, $e),
                'gefuellt'       => messung_format($v) !== 'leer',
                'format'         => messung_format($v),
                'kennungen'      => count($k['ids']),
                'passt_webuntis_id' => count(array_filter($k['ids'], fn($i) => isset($ids[$i]))),
                'texte'          => count($k['texte']),
                'passt_kuerzel'  => count(array_filter($k['texte'], fn($t) => isset($kuerz[$t]))),
            ];
        }
        return $r;
    };

    $bericht = ['lehrer_im_bestand' => count($ids)];
    if (count($ids) === 0) {
        $bericht['deutung'] = 'Keine Lehrkräfte mit webuntis_id im Bestand – Abgleich '
            . 'nicht möglich. KEIN Befund.';
    }

    if ($kinder !== null) {
        $nachId = [];
        foreach ($liste as $e) {
            if (is_array($e) && (int)($e['id'] ?? 0) > 0) $nachId[(int)$e['id']] = $e;
        }
        $bericht['kinder'] = [];
        foreach (array_values($kinder) as $i => $k) {
            $e = $nachId[(int)($k['id'] ?? 0)] ?? null;
            $bericht['kinder'][] = $e === null
                ? ['kind' => 'Kind ' . ($i + 1), 'in_pageconfig' => false]
                : ['kind' => 'Kind ' . ($i + 1), 'in_pageconfig' => true,
                   'hat_klasse' => (int)($e['klasseId'] ?? 0) > 0] + $werte($e);
        }
        return $bericht;
    }

    // Lehrkraft-Sicht: über die ganze Liste summiert, Formate gezählt.
    $summe = [];
    foreach (['classteacher', 'classteacher2'] as $f) {
        $summe[$f] = ['gefuellt' => 0, 'formate' => [], 'kennungen' => 0,
                      'passt_webuntis_id' => 0, 'texte' => 0, 'passt_kuerzel' => 0];
    }
    foreach ($liste as $e) {
        if (!is_array($e)) continue;
        foreach ($werte($e) as $f => $w) {
            if ($w['gefuellt']) $summe[$f]['gefuellt']++;
            $summe[$f]['formate'][$w['format']] = ($summe[$f]['formate'][$w['format']] ?? 0) + 1;
            foreach (['kennungen', 'passt_webuntis_id', 'texte', 'passt_kuerzel'] as $z) {
                $summe[$f][$z] += $w[$z];
            }
        }
    }
    return $bericht + ['summe' => $summe];
}

/**
 * timetable/filter?resourceType=CLASS – Klassenleitung je Klasse (v0.9.56).
 *
 * Liest classes[] (oben oder unter data). Je classTeacher1/2: vorhanden,
 * gefüllt, Format, und der Doppelabgleich: id → lehrer.webuntis_id,
 * shortName → lehrer.kuerzel, und ob beide auf DIESELBE Lehrkraft zeigen.
 *
 * Rückgabe: ['bericht' => nur Zahlen/Formate,
 *            'sig'     => [Klassen-ID => [id1, id2]]  – NUR intern, für den
 *                         Zeitraumvergleich; geht nie in die Antwort]
 * $lehrer['paare']: webuntis_id => kuerzel
 */
function messung_klassenfilter_auswerten($json, array $lehrer): array
{
    $pfad = null;
    $klassen = null;
    if (is_array($json['classes'] ?? null)) { $klassen = $json['classes']; $pfad = 'classes'; }
    elseif (is_array($json['data']['classes'] ?? null)) { $klassen = $json['data']['classes']; $pfad = 'data.classes'; }
    if ($klassen === null) {
        return ['bericht' => ['liste_gefunden' => false, 'klassen' => 0], 'sig' => [], 'je_klasse' => []];
    }
    $paare = $lehrer['paare'] ?? [];
    $kuerz = array_fill_keys(array_map('strval', array_values($paare)), true);
    $leer  = fn() => ['vorhanden' => 0, 'gefuellt' => 0, 'formate' => [],
                      'id_passt' => 0, 'kuerzel_passt' => 0, 'beide_dieselbe' => 0];
    $summe = ['classTeacher1' => $leer(), 'classTeacher2' => $leer()];
    $sig = [];
    $jeKlasse = [];
    foreach ($klassen as $k) {
        if (!is_array($k)) continue;
        $kid = (int)($k['class']['id'] ?? 0);
        $eintrag = [];
        foreach (['classTeacher1', 'classTeacher2'] as $f) {
            $v = $k[$f] ?? null;
            $fmt = messung_format($v);
            $id  = is_array($v) ? (int)($v['id'] ?? 0) : 0;
            $kz  = is_array($v) ? (string)($v['shortName'] ?? '') : '';
            $w = [
                'vorhanden'      => array_key_exists($f, $k),
                'gefuellt'       => $fmt !== 'leer',
                'format'         => $fmt,
                'id_passt'       => $id > 0 && isset($paare[$id]),
                'kuerzel_passt'  => $kz !== '' && isset($kuerz[$kz]),
                'beide_dieselbe' => $id > 0 && $kz !== '' && (string)($paare[$id] ?? '') === $kz,
            ];
            $eintrag[$f] = $w;
            if ($w['vorhanden']) $summe[$f]['vorhanden']++;
            if ($w['gefuellt']) $summe[$f]['gefuellt']++;
            $summe[$f]['formate'][$fmt] = ($summe[$f]['formate'][$fmt] ?? 0) + 1;
            foreach (['id_passt', 'kuerzel_passt', 'beide_dieselbe'] as $z) {
                if ($w[$z]) $summe[$f][$z]++;
            }
            $sig[$kid][] = $id;
        }
        if ($kid > 0) $jeKlasse[$kid] = $eintrag;
    }
    return ['bericht' => ['liste_gefunden' => true, 'pfad' => $pfad,
                          'klassen' => count($jeKlasse)] + $summe,
            'sig' => $sig, 'je_klasse' => $jeKlasse];
}

/** Vergleicht die Klassenleitung zweier Zeiträume – nur Zahlen. */
function messung_zeitraum_vergleich(array $sigA, array $sigB): array
{
    $gleich = 0; $anders = 0;
    foreach ($sigA as $kid => $paar) {
        if (!array_key_exists($kid, $sigB)) continue;
        if ($sigB[$kid] === $paar) $gleich++; else $anders++;
    }
    return [
        'in_beiden'         => $gleich + $anders,
        'gleiche_leitung'   => $gleich,
        'andere_leitung'    => $anders,
        'nur_schulzeit'     => count(array_diff_key($sigA, $sigB)),
        'nur_ferien'        => count(array_diff_key($sigB, $sigA)),
    ];
}

/**
 * Gruppen- und Rollenfelder eines Profils (v0.9.64): Jeder Pfad, in dem
 * „group“ oder „role“ vorkommt, mit Format. Einen Wert bekommen nur das
 * Gruppen-/Rollenfeld selbst und seine direkten Angaben (z. B. id und name
 * einer Gruppe) – tiefer Liegendes, etwa Mitglieder, nur mit Format.
 * Personenfelder (Namen, E-Mail, Geburtsdatum, Anschrift, Telefon) werden
 * übersprungen; „name“ direkt an einer Gruppe ist der Gruppenname.
 * Listen: höchstens 10 Einträge.
 */
function messung_gruppenfelder($v, string $pfad, bool $imGruppenpfad, array &$aus): void
{
    if (!is_array($v)) {
        if (!$imGruppenpfad) return;
        $eltern = (string)substr($pfad, 0, (int)strrpos($pfad, '.'));
        $mitWert = messung_ist_gruppe($pfad) || messung_ist_gruppe($eltern);
        $aus[] = ['pfad' => $pfad, 'format' => messung_format($v)]
            + ($mitWert ? ['wert' => is_scalar($v) ? $v : null] : []);
        return;
    }
    if ($imGruppenpfad) $aus[] = ['pfad' => $pfad, 'format' => messung_format($v)];
    $n = 0;
    foreach ($v as $k => $w) {
        if (is_int($k) && ++$n > 10) break;
        $k = (string)$k;
        $gruppenschluessel = preg_match('/group|role/i', $k) === 1;
        $person = preg_match('/name|first|last|display|mail|birth|geburt|phone|telefon|street|strasse|address|adresse/i', $k) === 1;
        if ($person && !$gruppenschluessel && !($k === 'name' && messung_ist_gruppe($pfad))) continue;
        messung_gruppenfelder($w, $pfad . '.' . $k, $imGruppenpfad || $gruppenschluessel, $aus);
    }
}

/** Ist der Pfad eine Gruppe selbst (…group…, …groups.N), nicht etwas darin? */
function messung_ist_gruppe(string $pfad): bool
{
    $teile = explode('.', $pfad);
    $letzt = end($teile);
    if (preg_match('/group|role/i', (string)$letzt)) return true;
    $vor = $teile[count($teile) - 2] ?? '';
    return ctype_digit((string)$letzt) && preg_match('/group|role/i', $vor) === 1;
}

/**
 * /WebUntis/api/profile/general auswerten (v0.9.64). Nur Schlüsselnamen des
 * Profils, Zustand von userGroup und die Gruppen-/Rollenfelder.
 */
function messung_profil(array $r): array
{
    $p = $r['json']['data']['profile'] ?? null;
    $aus = ['status' => $r['status'], 'fehler' => $r['fehler'], 'profil_vorhanden' => is_array($p)];
    if (!is_array($p)) {
        $aus['deutung'] = $r['fehler'] !== null ? 'Abruf mit Ausnahme – kein Befund.'
            : ($r['status'] !== 200 ? 'Status ' . $r['status'] . ' – kein Zugriff über diese Sitzung.'
            : 'Status 200, aber kein data.profile – Antwortform prüfen (z. B. Anmeldeseite). KEIN Befund.');
        return $aus;
    }
    $schluessel = array_map('strval', array_keys($p));
    sort($schluessel);
    $aus['schluessel'] = $schluessel;
    $ug = $p['userGroup'] ?? null;
    $aus['userGroup'] = [
        'vorhanden' => array_key_exists('userGroup', $p),
        'gefuellt'  => $ug !== null && $ug !== '' && $ug !== [],
        'format'    => messung_format($ug),
    ];
    $felder = [];
    messung_gruppenfelder($p, 'profile', false, $felder);
    $aus['gruppenfelder'] = $felder;
    $aus['deutung'] = $aus['userGroup']['gefuellt']
        ? 'Profil gelesen, userGroup gefüllt.'
        : ($aus['userGroup']['vorhanden'] ? 'Profil gelesen, userGroup vorhanden, aber leer.'
            : 'Profil gelesen, kein Feld userGroup.');
    return $aus;
}

/**
 * Anzahl je Rolle aus userCountByUserRole – Form nicht belegt: Objekt
 * {ROLLE: Zahl} oder Liste von Objekten mit einem Rollen- und einem
 * Zahlfeld. Rückgabe [ROLLE => int]; Unbekanntes ergibt [].
 */
function messung_rollenzahlen($v): array
{
    $aus = [];
    if (!is_array($v)) return $aus;
    foreach ($v as $k => $w) {
        if (is_string($k) && (is_int($w) || (is_string($w) && ctype_digit($w)))) {
            $aus[strtoupper($k)] = (int)$w;
        } elseif (is_array($w)) {
            $rolle = null; $zahl = null;
            foreach ($w as $wk => $ww) {
                if (is_string($ww) && preg_match('/role|rolle|name|type/i', (string)$wk)) $rolle = strtoupper($ww);
                if (is_int($ww) && preg_match('/count|anzahl|zahl|value/i', (string)$wk)) $zahl = $ww;
            }
            if ($rolle !== null && $zahl !== null) $aus[$rolle] = $zahl;
        }
    }
    return $aus;
}

/**
 * /WebUntis/api/userrole/config auswerten (v0.9.67). $eigene: userGroup aus
 * profile/general (Text) oder null. Je Gruppe nur id, label, userRole,
 * userCount und die Schüleranzahl; die Feldnamen eines Eintrags mit Format.
 * Abgleich der eigenen Gruppe: 'zeichengenau' | 'nur_angeglichen' (erst nach
 * Trimmen und Groß-/Kleinschreibung gleich, mit erster abweichender Stelle,
 * 1-basiert) | 'nein' | 'nicht_messbar'.
 */
function messung_benutzergruppen(array $r, ?string $eigene): array
{
    $liste = $r['json']['data']['userGroups'] ?? null;
    $aus = ['status' => $r['status'], 'fehler' => $r['fehler'],
            'liste_vorhanden' => is_array($liste) && array_is_list($liste)];
    if (!$aus['liste_vorhanden']) {
        $aus['deutung'] = $r['fehler'] !== null ? 'Abruf mit Ausnahme – kein Befund.'
            : ($r['status'] !== 200 ? 'Status ' . $r['status'] . ' – kein Zugriff über diese Sitzung.'
            : 'Status 200, aber kein data.userGroups – Antwortform prüfen. KEIN Befund.');
        $aus['eigene_gruppe'] = ['ergebnis' => 'nicht_messbar'];
        return $aus;
    }
    $felder = [];
    $gruppen = [];
    foreach ($liste as $e) {
        if (!is_array($e)) continue;
        foreach ($e as $k => $w) {
            if (!isset($felder[$k]) || $felder[$k] === 'leer') $felder[(string)$k] = messung_format($w);
        }
        $zahlen = messung_rollenzahlen($e['userCountByUserRole'] ?? null);
        $gruppen[] = [
            'id'        => isset($e['id']) && is_int($e['id']) ? $e['id'] : null,
            'label'     => is_string($e['label'] ?? null) ? $e['label'] : null,
            'userRole'  => isset($e['userRole']) && is_int($e['userRole']) ? $e['userRole'] : null,
            'userCount' => isset($e['userCount']) && is_int($e['userCount']) ? $e['userCount'] : null,
            'schueler'  => (int)($zahlen['STUDENT'] ?? 0),
        ];
    }
    ksort($felder);
    $aus['eintraege'] = count($gruppen);
    $aus['felder'] = $felder;
    $aus['gruppen'] = $gruppen;
    $aus['mit_schuelern'] = count(array_filter($gruppen, fn($g) => $g['schueler'] > 0));
    // Abgleich der eigenen Gruppe
    if ($eigene === null || $eigene === '') {
        $aus['eigene_gruppe'] = ['ergebnis' => 'nicht_messbar'];
    } else {
        $ergebnis = ['ergebnis' => 'nein'];
        $angl = fn(string $x) => function_exists('mb_strtolower') ? mb_strtolower(trim($x)) : strtolower(trim($x));
        foreach ($gruppen as $g) {
            if ($g['label'] === null) continue;
            if ($g['label'] === $eigene) { $ergebnis = ['ergebnis' => 'zeichengenau', 'id' => $g['id']]; break; }
            if ($angl($g['label']) === $angl($eigene)) {
                $a = preg_split('//u', $g['label'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $b = preg_split('//u', $eigene, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $pos = 0;
                while ($pos < count($a) && $pos < count($b) && $a[$pos] === $b[$pos]) $pos++;
                $ergebnis = ['ergebnis' => 'nur_angeglichen', 'id' => $g['id'], 'erste_abweichung' => $pos + 1,
                             'laenge_label' => count($a), 'laenge_profil' => count($b)];
            }
        }
        $aus['eigene_gruppe'] = $ergebnis;
    }
    $aus['deutung'] = 'Liste gelesen: ' . $aus['eintraege'] . ' Gruppen, davon ' . $aus['mit_schuelern'] . ' mit Schülern.';
    return $aus;
}

// ---- Messung recipientOption PARENTS (v0.9.69) ----------------------------

/** Die Pfade, zwischen denen gemessen wird – sonst keiner. */
const MESSUNG_PARENTS_PFADE = [
    'users'    => '/WebUntis/api/rest/view/v2/messages/users',   // heute im Betrieb
    'messages' => '/WebUntis/api/rest/view/v2/messages',         // lernzeiten
];

/**
 * Der Körper wie in lernzeiten gemessen (Beilage), an die Eltern über die
 * Kennung des Kindes, OHNE Kopie an das Kind, mit festem Testbetreff.
 */
function messung_parents_koerper(int $kind): array
{
    return mit_parents_koerper($kind, 'sprechtag – Testnachricht (Messung), bitte ignorieren',
        'Dies ist eine Testnachricht des Sprechtag-Systems. '
        . 'Sie prüft einen Versandweg und kann gelöscht werden.');
}

/**
 * Deutet die Antwort. Erfolg heißt numberOfRecipients ≥ 1 – nicht Status
 * 200 (ein 2xx ohne Zahl ist „unklar“, mit 0 „niemand“). Ohne Personen:
 * nur Status, Schlüsselnamen der Antwort, Zahlen, Meldung und die Pfade
 * von Prüffehlern.
 */
function messung_parents_deuten(array $r): array
{
    $st = (int)($r['status'] ?? 0);
    $j = is_array($r['json'] ?? null) ? $r['json'] : null;
    $schluessel = $j !== null ? array_map('strval', array_keys($j)) : [];
    sort($schluessel);
    $aus = ['status' => $st, 'schluessel' => $schluessel, 'empfaenger' => null, 'cc' => null,
            'meldung' => '', 'pruef_pfade' => []];
    if ($st >= 200 && $st < 300) {
        $n = $j['numberOfRecipients'] ?? null;
        $aus['empfaenger'] = is_int($n) ? $n : null;
        $aus['cc'] = is_int($j['numberOfCCRecipients'] ?? null) ? $j['numberOfCCRecipients'] : null;
        $aus['ergebnis'] = is_int($n) ? ($n >= 1 ? 'erreicht' : 'niemand') : 'unklar';
    } elseif ($st === 401 || $st === 403) {
        $aus['ergebnis'] = 'keine_rechte';
    } elseif ($st === 0) {
        $aus['ergebnis'] = 'unklar';   // Zeitüberschreitung: kann angekommen sein
        $aus['meldung'] = substr((string)($r['text'] ?? ''), 0, 200);
    } else {
        $aus['ergebnis'] = 'abgelehnt';
        $aus['meldung'] = substr((string)($j['errorMessage'] ?? $j['message'] ?? ''), 0, 200);
        foreach ((array)($j['validationErrors'] ?? []) as $v) {
            if (is_array($v) && is_string($v['path'] ?? null)) $aus['pruef_pfade'][] = $v['path'];
        }
    }
    $aus['deutung'] = [
        'erreicht'     => 'Angenommen, ' . $aus['empfaenger'] . ' Empfänger erreicht.',
        'niemand'      => 'Angenommen, aber niemand erreicht – der Pfad kennt die Angabe womöglich nicht.',
        'unklar'       => 'Unklar, ob etwas hinausging – in WebUntis unter „Gesendet“ nachsehen.',
        'keine_rechte' => 'Keine Rechte zum Senden – mit einem Konto mit Mitteilungsrecht messen.',
        'abgelehnt'    => 'Abgelehnt – Meldung und Prüffehler nennen den Grund.',
    ][$aus['ergebnis']];
    return $aus;
}

/**
 * Führt die Messung aus – GENAU EIN Versand, nur wenn alles stimmt:
 * $eingabe: ['kind_id' => int, 'pfad' => 'users'|'messages', 'bestaetigt' => true].
 * Die Sitzung des Dienstkontos (v0.9.70) gibt es seit v0.9.72 nicht mehr
 * (E17); 'sitzung' => 'dienstkonto' wird abgelehnt.
 * $sitzungEigene: liefert ['rest' => ?Client, 'grund' => ?string] wie
 * wu_sitzung(); $namensweg(int, ?Client): Ergebnis von
 * mit_eltern_ids_ermitteln() über DIESELBE Sitzung – zum Vergleich, sendet
 * nichts (misst seit v0.9.72 die Namenssuche über die eigene Sitzung);
 * $inSchuelerliste(int): steht die Kennung in schueler.webuntis_id?
 * Die Antwort nennt weder Kind-Kennung noch Namen noch Eltern-Kennungen.
 */
function messung_parents_ausfuehren(array $eingabe, string $rolle, callable $sitzungEigene,
                                    callable $namensweg, callable $inSchuelerliste): array
{
    $kind = (int)($eingabe['kind_id'] ?? 0);
    $pfad = (string)($eingabe['pfad'] ?? '');
    $welche = (string)($eingabe['sitzung'] ?? 'eigene');
    if (($eingabe['bestaetigt'] ?? null) !== true) {
        return ['gesendet' => false, 'grund' => 'Nicht bestätigt – die Messung verschickt eine echte '
            . 'Nachricht und braucht "bestaetigt": true.'];
    }
    if (!isset(MESSUNG_PARENTS_PFADE[$pfad])) {
        return ['gesendet' => false, 'grund' => 'Pfad muss "users" oder "messages" sein.'];
    }
    if ($kind <= 0) return ['gesendet' => false, 'grund' => 'kind_id fehlt.'];
    if ($welche !== 'eigene') {
        return ['gesendet' => false, 'grund' => 'Nur über die eigene Sitzung – das Dienstkonto gibt es '
            . 'seit v0.9.72 nicht mehr (E17).'];
    }
    $s = $sitzungEigene();
    if (($s['rest'] ?? null) === null) {
        return ['gesendet' => false, 'grund' => 'Keine nutzbare WebUntis-Sitzung (' . (string)($s['grund'] ?? '')
            . ') – neu anmelden und erneut messen.'];
    }
    $nw = $namensweg($kind, $s['rest']);
    $liste = $inSchuelerliste($kind);
    $antwort = $s['rest']->postMultipart(MESSUNG_PARENTS_PFADE[$pfad], messung_parents_koerper($kind));
    return [
        'gesendet' => true,
        'sitzung' => $welche,
        'pfad' => $pfad,
        'antwort' => messung_parents_deuten($antwort),
        'namensweg' => ['konten' => count((array)($nw['ids'] ?? [])), 'quelle' => $nw['quelle'] ?? null],
        'kennung_in_schuelerliste' => $liste,
    ];
}

// ---- Messung: Liste auflösen und an sie senden (v0.9.71) ------------------

/** Höchstens so viele Empfänger – die Messung geht nur an eine Testliste. */
const MESSUNG_LISTE_HOECHSTENS = 5;

/** Körper wie erinnerung_versenden(), mit festem Testbetreff. */
function messung_liste_koerper(array $ids): array
{
    return [
        'subject'             => 'sprechtag – Testnachricht (Messung), bitte ignorieren',
        'content'             => 'Dies ist eine Testnachricht des Sprechtag-Systems. '
            . 'Sie prüft einen Versandweg und kann gelöscht werden.',
        'requestConfirmation' => false,
        'recipientUserIds'    => array_values($ids),
        'oneDriveAttachments' => [],
        'forbidReply'         => false,
    ];
}

/**
 * Führt die Messung aus. $eingabe: ['schritt' => 'aufloesen'|'senden',
 * 'liste_typ' => 'QUICK', 'liste_id' => int, 'bestaetigt' => true (senden)].
 * $sitzung liefert ['rest' => ?Client, 'grund' => ?string] (die Sitzung der
 * angemeldeten Person – Lehrkraft oder Verwaltung, $rolle). Senden nur, wenn
 * die Liste 1 bis MESSUNG_LISTE_HOECHSTENS Empfänger hat; genau ein Versand.
 * Die Antwort nennt Zahlen und Status, keine Kennungen und keine Namen.
 */
function messung_liste_ausfuehren(array $eingabe, string $rolle, callable $sitzung): array
{
    $schritt = (string)($eingabe['schritt'] ?? '');
    $typ = (string)($eingabe['liste_typ'] ?? '');
    $id = (int)($eingabe['liste_id'] ?? 0);
    $basis = ['gesendet' => false, 'rolle' => $rolle, 'schritt' => $schritt];
    if (!in_array($schritt, ['aufloesen', 'senden'], true)) {
        return $basis + ['grund' => 'schritt muss "aufloesen" oder "senden" sein.'];
    }
    if ($typ !== 'QUICK') return $basis + ['grund' => 'Nur QUICK-Listen (Testliste).'];
    if ($id <= 0) return $basis + ['grund' => 'liste_id fehlt.'];
    if ($schritt === 'senden' && ($eingabe['bestaetigt'] ?? null) !== true) {
        return $basis + ['grund' => 'Nicht bestätigt – senden verschickt eine echte Nachricht und braucht "bestaetigt": true.'];
    }
    $s = $sitzung();
    if (($s['rest'] ?? null) === null) {
        return $basis + ['grund' => 'Keine nutzbare WebUntis-Sitzung (' . (string)($s['grund'] ?? '')
            . ') – neu anmelden und erneut messen.'];
    }
    $res = $s['rest']->listeAufloesen('QUICK', $id, 3);
    $ids = erinnerung_ids_aus_users((array)$res['users']);
    $basis['aufloesung'] = ['status' => (int)$res['status'], 'anzahl' => count($ids),
                            'vollstaendig' => (bool)$res['vollstaendig'], 'seiten' => (int)$res['seiten']];
    if ($schritt === 'aufloesen') return $basis;
    if ($ids === []) return $basis + ['grund' => 'Keine Empfänger – nichts gesendet.'];
    if (count($ids) > MESSUNG_LISTE_HOECHSTENS) {
        return $basis + ['grund' => 'Die Liste hat ' . count($ids) . ' Empfänger – die Messung sendet an '
            . 'höchstens ' . MESSUNG_LISTE_HOECHSTENS . ' (nur an eine Testliste). Nichts gesendet.'];
    }
    $antwort = $s['rest']->postMultipart('/WebUntis/api/rest/view/v2/messages/users', messung_liste_koerper($ids));
    return ['gesendet' => true] + $basis + ['antwort' => messung_parents_deuten($antwort)];
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
 * Der Bericht. $rest ist der Client aus wu_sitzung() oder NULL,
 * $grund dessen Grund (siehe dort), $probe das Ergebnis von
 * messung_token_probe() bei 'kein_token'. $heute ist einstellbar für
 * Prüfungen.
 */
function messung_sitzung_bericht(array $u, ?object $rest, ?string $grund,
                                 ?string $heute = null, ?array $probe = null,
                                 array $lehrer = [], ?array $ferien = null): array
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

    // ---- 0. profile/general: Gruppe der angemeldeten Person (v0.9.64) ----
    // Für JEDE Rolle – deshalb vor allem, was für Nicht-Eltern früh endet.
    $profilAbruf = messung_abruf($rest, '/WebUntis/api/profile/general', []);
    $bericht['profil'] = messung_profil($profilAbruf);

    // ---- 0b. userrole/config: alle Benutzergruppen (v0.9.67) ------------
    // Ebenfalls für jede Rolle; Abgleich mit der eigenen Gruppe von oben.
    $eigeneGruppe = $profilAbruf['json']['data']['profile']['userGroup'] ?? null;
    $bericht['benutzergruppen'] = messung_benutzergruppen(
        messung_abruf($rest, '/WebUntis/api/userrole/config', []),
        is_string($eigeneGruppe) ? $eigeneGruppe : null);

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

    // ---- 1b. Klassenleitung in pageconfig (Zug 3, v0.9.55) ---------------
    $liste = $pc['json']['data']['elements'] ?? $pc['json']['data'] ?? null;
    if (is_array($liste) && $liste !== []) {
        $bericht['klassenleitung'] = messung_klassenleitung($liste, $lehrer,
            ($u['rolle'] ?? '') === 'eltern' ? (array)($u['kinder'] ?? []) : null);
    } else {
        $bericht['klassenleitung'] = 'nicht gemessen (keine pageconfig-Liste)';
    }

    // ---- 1c. timetable/filter: Klassenleitung, zwei Zeiträume (v0.9.56) --
    // Schulzeit: dieselben vier Wochen wie der Stundenplan unten (dort ist
    // Unterricht belegt). Ferien: nur, wenn ausdrücklich angegeben – ein
    // geratener Zeitraum entwertete den Vergleich.
    $bisS = $heute ?? date('Y-m-d');
    $vonS = date('Y-m-d', strtotime($bisS . ' -27 days'));
    $fenster = ['schulzeit' => ['von' => $vonS, 'bis' => $bisS]];
    if ($ferien !== null) $fenster['ferien'] = $ferien;
    $auswertung = [];
    $bericht['klassenfilter'] = [];
    foreach ($fenster as $name => $f) {
        $r = messung_abruf($rest, '/WebUntis/api/rest/view/v1/timetable/filter', [
            'resourceType' => 'CLASS', 'timetableType' => 'STANDARD',
            'start' => $f['von'], 'end' => $f['bis'],
        ]);
        $a = messung_klassenfilter_auswerten($r['json'], $lehrer);
        $auswertung[$name] = $a;
        $bericht['klassenfilter'][$name] = ['zeitraum' => $f, 'status' => $r['status'],
            'fehler' => $r['fehler']] + $a['bericht'] + [
            'deutung' => $r['fehler'] !== null ? 'Abruf mit Ausnahme – kein Befund.'
                : ($r['status'] !== 200 ? 'Status ' . $r['status'] . ' – kein Zugriff oder falsche Parameter.'
                : (!$a['bericht']['liste_gefunden'] ? 'Status 200, aber kein classes[] – Antwortform prüfen. KEIN Befund.'
                : ($a['bericht']['klassen'] === 0 ? 'classes[] leer – kein Befund.'
                : 'Klassenliste gelesen.'))),
        ];
    }
    if (!isset($fenster['ferien'])) {
        $bericht['klassenfilter']['ferien'] = 'nicht gemessen – Ferienzeitraum als '
            . '?ferien_von=JJJJ-MM-TT&ferien_bis=JJJJ-MM-TT angeben';
        $bericht['klassenfilter']['vergleich'] = 'nicht gemessen (kein Ferienzeitraum)';
    } elseif ($auswertung['schulzeit']['sig'] === [] || $auswertung['ferien']['sig'] === []) {
        $bericht['klassenfilter']['vergleich'] = 'mindestens ein Zeitraum ohne Klassen – '
            . 'Vergleich nicht möglich. KEIN Befund.';
    } else {
        $bericht['klassenfilter']['vergleich'] = messung_zeitraum_vergleich(
            $auswertung['schulzeit']['sig'], $auswertung['ferien']['sig']);
    }

    // Eltern: je eigenem Kind über klasseId aus pageconfig (gleicher Kreis
    // wie class.id – an einem Fall vom Betreiber quergeprüft, hier gezählt).
    if (($u['rolle'] ?? '') === 'eltern') {
        $nachId = [];
        foreach ((is_array($liste) ? $liste : []) as $e) {
            if (is_array($e) && (int)($e['id'] ?? 0) > 0) $nachId[(int)$e['id']] = $e;
        }
        $bericht['klassenfilter']['kinder'] = [];
        foreach (array_values((array)($u['kinder'] ?? [])) as $i => $k) {
            $klasse = (int)($nachId[(int)($k['id'] ?? 0)]['klasseId'] ?? 0);
            $z = ['kind' => 'Kind ' . ($i + 1), 'hat_klasse' => $klasse > 0];
            foreach ($auswertung as $name => $a) {
                $z[$name] = $klasse > 0 && isset($a['je_klasse'][$klasse])
                    ? ['klasse_gefunden' => true] + $a['je_klasse'][$klasse]
                    : ['klasse_gefunden' => false];
            }
            if (isset($auswertung['ferien'])) {
                $sa = $auswertung['schulzeit']['sig'][$klasse] ?? null;
                $sb = $auswertung['ferien']['sig'][$klasse] ?? null;
                $z['gleiche_leitung_in_beiden'] = $sa !== null && $sb !== null ? $sa === $sb : null;
            }
            $bericht['klassenfilter']['kinder'][] = $z;
        }
    }

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
