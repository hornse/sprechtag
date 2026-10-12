<?php
// ============================================================
// extractors.php – Auswertung von WebUntis-REST-Antworten
// VENDORED aus hornse/webuntis-client-php v2.0.0 – dort ändern, hierher kopieren!
// ============================================================

declare(strict_types=1);

// ------------------------------------------------------------
// Moderner Endpunkt /api/rest/view/v1/timetable/entries
// (OHNE format-Parameter aufrufen -> Instanz-Standardformat).
// Jedes Positions-Element trägt current.type (CLASS/SUBJECT/
// ROOM/TEACHER/...) und beschreibt sich damit selbst – die
// Positionsnummern sind formatabhängig und werden IGNORIERT.
//
// Gewertet werden nur Unterrichts-Einträge (type enthält
// 'TEACHING') mit Status REGULAR oder CANCELLED (ein Ausfall
// ändert nichts daran, WER das Fach regulär unterrichtet).
// Vertretungen (Status SUBSTITUTION/CHANGED) werden übersprungen,
// damit der Vertreter das Fach nicht fälschlich zugeordnet bekommt.
//
// Rückgabe:
//   eintraege     – Zahl gewerteter Unterrichts-Einträge
//   fachKuerzel   – Menge der Fach-Kürzel (Schlüssel des Arrays)
//   paareExplizit – Menge "LehrerKrz|FachKrz" aus Einträgen, die
//                   selbst TEACHER-Elemente enthalten (Kopplungen)
// Die implizite Lehrkraft (die abgefragte Ressource) muss vom
// Aufrufer mit fachKuerzel kombiniert werden.
// ------------------------------------------------------------
function rest_unterricht_aus_entries($json): array
{
    $ergebnis = ['eintraege' => 0, 'fachKuerzel' => [], 'paareExplizit' => []];

    $lauf = function ($knoten) use (&$lauf, &$ergebnis): void {
        if (!is_array($knoten)) return;

        if (array_key_exists('position1', $knoten)) {
            $typ    = (string)($knoten['type'] ?? '');
            $status = (string)($knoten['status'] ?? 'REGULAR');
            if (stripos($typ, 'TEACHING') !== false
                && in_array($status, ['REGULAR', 'CANCELLED'], true)) {
                $faecher = []; $lehrer = [];
                for ($i = 1; $i <= 7; $i++) {
                    foreach ((array)($knoten['position' . $i] ?? []) as $el) {
                        $c = $el['current'] ?? null;
                        if (!is_array($c)) continue;   // removed wird bewusst ignoriert
                        $sn = (string)($c['shortName'] ?? '');
                        if ($sn === '') continue;
                        if (($c['type'] ?? '') === 'SUBJECT') $faecher[$sn] = true;
                        if (($c['type'] ?? '') === 'TEACHER') $lehrer[$sn] = true;
                    }
                }
                if ($faecher !== []) {
                    $ergebnis['eintraege']++;
                    // Werte = Anzahl Einträge (Stunden-Signal), array_keys bleibt kompatibel
                    foreach (array_keys($faecher) as $f) {
                        $ergebnis['fachKuerzel'][$f] = ($ergebnis['fachKuerzel'][$f] ?? 0) + 1;
                    }
                    foreach (array_keys($lehrer) as $l) {
                        foreach (array_keys($faecher) as $f) {
                            $ergebnis['paareExplizit']["$l|$f"] = ($ergebnis['paareExplizit']["$l|$f"] ?? 0) + 1;
                        }
                    }
                }
            }
            return;   // in Einträge nicht weiter absteigen
        }
        foreach ($knoten as $wert) {
            if (is_array($wert)) $lauf($wert);
        }
    };
    $lauf($json);
    return $ergebnis;
}

// ------------------------------------------------------------
// Liefert den ersten Eintrag mit position1 aus einer beliebig
// verschachtelten entries-Antwort (für Sondierung/Diagnose).
// ------------------------------------------------------------
function rest_erster_eintrag($json): ?array
{
    $treffer = null;
    $lauf = function ($knoten) use (&$lauf, &$treffer): void {
        if ($treffer !== null || !is_array($knoten)) return;
        if (isset($knoten['position1'])) { $treffer = $knoten; return; }
        foreach ($knoten as $wert) {
            if (is_array($wert)) $lauf($wert);
        }
    };
    $lauf($json);
    return $treffer;
}

// ------------------------------------------------------------
// Legacy-Endpunkt /api/public/timetable/weekly/data (formatId=1).
// Perioden unter data.result.data.elementPeriods, elements-Arrays
// mit {type, id, orgId}: type 2 = Lehrkraft, type 3 = Fach.
// Vertretung: orgId = reguläre Lehrkraft -> die zählt.
// Rückgabe: paare "lehrerWuId|fachWuId", perioden, namen[typ][id].
// ------------------------------------------------------------
function rest_paare_aus_weekly($json): array
{
    $paare = [];
    $perioden = 0;
    $namen = [2 => [], 3 => []];

    $daten = $json['data']['result']['data'] ?? null;
    if (!is_array($daten)) return ['paare' => [], 'perioden' => 0, 'namen' => $namen];

    foreach (($daten['elements'] ?? []) as $el) {
        $typ = (int)($el['type'] ?? 0);
        if (($typ === 2 || $typ === 3) && isset($el['id'])) {
            $namen[$typ][(int)$el['id']] = (string)($el['name'] ?? '');
        }
    }

    foreach (($daten['elementPeriods'] ?? []) as $periodenListe) {
        foreach ((array)$periodenListe as $periode) {
            $lehrer = []; $faecher = [];
            foreach (($periode['elements'] ?? []) as $el) {
                $typ = (int)($el['type'] ?? 0);
                $id  = (int)(($el['orgId'] ?? 0) > 0 ? $el['orgId'] : ($el['id'] ?? 0));
                if ($id <= 0) continue;
                if ($typ === 2) $lehrer[$id] = true;
                if ($typ === 3) $faecher[$id] = true;
            }
            if ($lehrer === [] || $faecher === []) continue;
            $perioden++;
            foreach (array_keys($lehrer) as $l) {
                foreach (array_keys($faecher) as $f) {
                    $paare["$l|$f"] = ($paare["$l|$f"] ?? 0) + 1;   // = Perioden-Zahl
                }
            }
        }
    }
    return ['paare' => $paare, 'perioden' => $perioden, 'namen' => $namen];
}

// ------------------------------------------------------------
// Abfrage JE SCHÜLER statt je Lehrkraft, und das eigene Konto.
// Zusammengeführt in v2.0.0 aus der Ergänzungsdatei v1.6.0 (bis
// dahin src/extractors_ergaenzung_v1.6.0.php, 24.07.2026) – der
// Code ist unverändert übernommen.
// ------------------------------------------------------------

/**
 * Ermittelt aus einer /timetable/entries-Antwort (resourceType=STUDENT)
 * alle unterrichtenden Lehrkräfte mit ihren Fächern.
 *
 * Filterregeln (identisch zu rest_unterricht_aus_entries):
 *  - nur Einträge, deren type 'TEACHING' enthält
 *    (schließt BREAK_SUPERVISION, EVENT etc. aus)
 *  - nur Status REGULAR oder CANCELLED; ein Ausfall ändert nichts
 *    daran, WER regulär unterrichtet
 *  - SUBSTITUTION/CHANGED werden übersprungen, damit Vertretungen
 *    keine falsche Lehrkraft-Zuordnung erzeugen
 *  - es wird ausschließlich 'current' gelesen, 'removed' ignoriert
 *  - Positionsnummern sind formatabhängig und werden IGNORIERT;
 *    die Elemente beschreiben sich über current.type selbst
 *
 * $mitKlausuren (ab v1.6.0): Wertet zusätzlich Einträge vom Typ EXAM
 * mit Status REGULAR. Hintergrund: In Unter- und Mittelstufe beaufsichtigen
 * die Fachlehrkräfte ihre eigenen Klassenarbeiten. Fällt der gesamte
 * reguläre Unterricht einer Lehrkraft im Abfragezeitraum aus oder wird
 * vertreten, ist der Klausurtermin der einzige Beleg für die Zuordnung.
 * EXAM-Stunden zählen NICHT in 'stunden' – die Sortierung richtet sich
 * weiter nach regulärem Unterricht; sie erscheinen in 'klausuren'.
 *
 * Rückgabe:
 *   eintraege   – Zahl gewerteter Unterrichts-Einträge
 *   lehrkraefte – Kürzel => ['name' => longName, 'stunden' => int,
 *                            'klausuren' => int, 'faecher' => [Kürzel => Anzahl]]
 */
function rest_lehrkraefte_aus_entries($json, bool $mitKlausuren = true): array
{
    $ergebnis = ['eintraege' => 0, 'lehrkraefte' => []];

    $lauf = function ($knoten) use (&$lauf, &$ergebnis, $mitKlausuren): void {
        if (!is_array($knoten)) return;

        if (array_key_exists('position1', $knoten)) {
            $typ    = (string)($knoten['type'] ?? '');
            $status = (string)($knoten['status'] ?? 'REGULAR');

            $istUnterricht = stripos($typ, 'TEACHING') !== false
                && in_array($status, ['REGULAR', 'CANCELLED'], true);
            // Klausur nur bei Status REGULAR: bei CHANGED steht die
            // Lehrkraft unter 'removed' (Termin verlegt/ausgefallen).
            $istKlausur = $mitKlausuren
                && stripos($typ, 'EXAM') !== false
                && $status === 'REGULAR';

            if ($istUnterricht || $istKlausur) {
                $faecher = []; $lehrer = [];
                for ($i = 1; $i <= 7; $i++) {
                    foreach ((array)($knoten['position' . $i] ?? []) as $el) {
                        $c = $el['current'] ?? null;
                        if (!is_array($c)) continue;   // removed bewusst ignorieren
                        $kuerzel = (string)($c['shortName'] ?? '');
                        if ($kuerzel === '') continue;
                        $art = (string)($c['type'] ?? '');
                        if ($art === 'SUBJECT') {
                            $faecher[$kuerzel] = true;
                        } elseif ($art === 'TEACHER') {
                            $lehrer[$kuerzel] = (string)($c['longName'] ?? $kuerzel);
                        }
                    }
                }

                if ($lehrer !== []) {
                    $ergebnis['eintraege']++;
                    foreach ($lehrer as $kuerzel => $name) {
                        if (!isset($ergebnis['lehrkraefte'][$kuerzel])) {
                            $ergebnis['lehrkraefte'][$kuerzel] =
                                ['name' => $name, 'stunden' => 0,
                                 'klausuren' => 0, 'faecher' => []];
                        }
                        if ($istUnterricht) {
                            $ergebnis['lehrkraefte'][$kuerzel]['stunden']++;
                        } else {
                            $ergebnis['lehrkraefte'][$kuerzel]['klausuren']++;
                        }
                        foreach (array_keys($faecher) as $f) {
                            $ergebnis['lehrkraefte'][$kuerzel]['faecher'][$f] =
                                ($ergebnis['lehrkraefte'][$kuerzel]['faecher'][$f] ?? 0) + 1;
                        }
                    }
                }
            }
            return;   // in Einträge nicht weiter absteigen
        }
        foreach ($knoten as $wert) {
            if (is_array($wert)) $lauf($wert);
        }
    };
    $lauf($json);

    // Sortierung nach regulären Stunden; bei Gleichstand nach Klausuren.
    uasort($ergebnis['lehrkraefte'], fn($a, $b) =>
        [$b['stunden'], $b['klausuren']] <=> [$a['stunden'], $a['klausuren']]);
    return $ergebnis;
}

/**
 * Ermittelt die verknüpften Kinder eines Eltern-Kontos aus der
 * Antwort von GET /api/rest/view/v1/app/data.
 *
 * Befund Sondierung 07/2026 (frg-dusseldorf): Bei personType 12
 * (LEGAL_GUARDIAN) enthält user.students[] die Kinder. Bei
 * Lehrkräften/Admins ist das Array leer. ACHTUNG: user.person ist
 * die eigene Person des Kontos und darf NICHT als Kind gelten.
 *
 * Rückgabe: ['userId' => int|null, 'personId' => int|null,
 *            'rollen' => string[], 'kinder' => [['id'=>int,'name'=>string], …]]
 */
function rest_konto_aus_appdata($json): array
{
    $user = is_array($json) ? ($json['user'] ?? null) : null;
    if (!is_array($user)) {
        return ['userId' => null, 'personId' => null, 'rollen' => [], 'kinder' => []];
    }

    $kinder = [];
    foreach ((array)($user['students'] ?? []) as $s) {
        if (!is_array($s) || !isset($s['id'])) continue;
        $id = (int)$s['id'];
        if ($id <= 0) continue;   // -1 = Admin-Platzhalter
        $kinder[] = ['id' => $id, 'name' => (string)($s['displayName'] ?? '')];
    }

    return [
        'userId'   => isset($user['id']) ? (int)$user['id'] : null,
        'personId' => isset($user['person']['id']) ? (int)$user['person']['id'] : null,
        'rollen'   => array_values(array_filter((array)($user['roles'] ?? []), 'is_string')),
        'kinder'   => $kinder,
    ];
}
