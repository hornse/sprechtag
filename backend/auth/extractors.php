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
 *    (schließt BREAK_SUPERVISION, EVENT, EXAM etc. aus)
 *  - nur Status REGULAR oder CANCELLED; ein Ausfall ändert nichts
 *    daran, WER regulär unterrichtet
 *  - SUBSTITUTION/CHANGED werden übersprungen, damit Vertretungen
 *    keine falsche Lehrkraft-Zuordnung erzeugen
 *  - es wird ausschließlich 'current' gelesen, 'removed' ignoriert
 *  - Positionsnummern sind formatabhängig und werden IGNORIERT;
 *    die Elemente beschreiben sich über current.type selbst
 *
 * Rückgabe:
 *   eintraege   – Zahl gewerteter Unterrichts-Einträge
 *   lehrkraefte – Kürzel => ['name' => longName, 'stunden' => int,
 *                            'faecher' => [Fachkürzel => Anzahl]]
 *
 * Sortierung nach 'stunden' (absteigend) ergibt eine sinnvolle
 * Reihenfolge für Buchungslisten: Hauptfachlehrkräfte zuerst.
 */
function rest_lehrkraefte_aus_entries($json): array
{
    $ergebnis = ['eintraege' => 0, 'lehrkraefte' => []];

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
                                ['name' => $name, 'stunden' => 0, 'faecher' => []];
                        }
                        $ergebnis['lehrkraefte'][$kuerzel]['stunden']++;
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

    uasort($ergebnis['lehrkraefte'], fn($a, $b) => $b['stunden'] <=> $a['stunden']);
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
