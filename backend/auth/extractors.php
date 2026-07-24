<?php
// ============================================================
// extractors.php – Auswertung von WebUntis-REST-Antworten
// VENDORED aus hornse/webuntis-client-php v1.2.0
// -> dort ändern, hierher kopieren!
//
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
// ============================================================

declare(strict_types=1);

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
