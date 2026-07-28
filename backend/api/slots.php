<?php
// ============================================================
// slots.php – Zeitraster- und Regel-Logik (REINE FUNKTIONEN)
//
// Bewusst ohne DB- oder Netzzugriff, damit die Logik offline
// testbar ist (tests/run.php). Die API-Schicht lädt die Daten
// und ruft diese Funktionen auf.
// ============================================================

declare(strict_types=1);

/** "HH:MM" oder "HH:MM:SS" -> Minuten seit 00:00. */
function slot_min(string $zeit): int
{
    $t = explode(':', $zeit);
    return ((int)($t[0] ?? 0)) * 60 + (int)($t[1] ?? 0);
}

/** Minuten seit 00:00 -> "HH:MM". */
function slot_zeit(int $minuten): string
{
    return sprintf('%02d:%02d', intdiv($minuten, 60), $minuten % 60);
}

/**
 * Erzeugt das Zeitraster einer Lehrkraft für einen Sprechtag.
 *
 * Berücksichtigt:
 *  - Sprechtag-Rahmen (beginn/ende, slot_minuten)
 *  - individuelles Anwesenheitsfenster (Teilzeit): anwesend_von/bis;
 *    NULL = ganzer Rahmen
 *  - automatische Pausen: nach je pause_nach_terminen BUCHBAREN Slots
 *    wird pause_minuten Pause eingeschoben (0 = keine Pausen)
 *
 * Rückgabe: Liste von ['beginn' => 'HH:MM', 'ende' => 'HH:MM',
 *                      'typ' => 'slot'|'pause']
 */
function slot_raster(array $sprechtag, ?string $von = null, ?string $bis = null): array
{
    $laenge = max(1, (int)($sprechtag['slot_minuten'] ?? 10));
    $start  = slot_min($von ?? (string)$sprechtag['beginn']);
    $stop   = slot_min($bis ?? (string)$sprechtag['ende']);

    // Anwesenheitsfenster darf den Sprechtag-Rahmen nicht verlassen
    $start = max($start, slot_min((string)$sprechtag['beginn']));
    $stop  = min($stop,  slot_min((string)$sprechtag['ende']));
    if ($stop <= $start) return [];

    $pauseNach = (int)($sprechtag['pause_nach_terminen'] ?? 0);
    $pauseLang = (int)($sprechtag['pause_minuten'] ?? 0);

    $raster = [];
    $zaehler = 0;
    $zeit = $start;

    while ($zeit + $laenge <= $stop) {
        $raster[] = ['beginn' => slot_zeit($zeit),
                     'ende'   => slot_zeit($zeit + $laenge),
                     'typ'    => 'slot'];
        $zeit += $laenge;
        $zaehler++;

        if ($pauseNach > 0 && $pauseLang > 0 && $zaehler % $pauseNach === 0) {
            // Pause nur einfügen, wenn danach noch mindestens ein Slot passt
            if ($zeit + $pauseLang + $laenge <= $stop) {
                $raster[] = ['beginn' => slot_zeit($zeit),
                             'ende'   => slot_zeit($zeit + $pauseLang),
                             'typ'    => 'pause'];
                $zeit += $pauseLang;
            }
        }
    }
    return $raster;
}

/**
 * Prüft, ob eine Buchung erlaubt ist. Reine Regelprüfung – der
 * Aufrufer hat die Daten bereits geladen.
 *
 * $kontext:
 *   phase              – aktuelle Phase des Sprechtags
 *   rolle              – 'eltern'|'lehrkraft'|'schueler'|'admin'
 *   eingeladen         – bool: existiert eine Einladung (Phase 1)
 *   darf_lehrkraft     – bool: unterrichtet die Lehrkraft das Kind
 *                        ODER ist als Sonderlehrkraft freigegeben
 *   slot_frei          – bool
 *   slot_im_raster     – bool: Slot existiert im Lehrkraft-Raster
 *   anzahl_termine     – bisherige Termine dieses Elternteils
 *   max_termine        – Maximum je Elternteil
 *
 * Rückgabe: ['ok' => bool, 'grund' => string]
 */
function slot_buchung_erlaubt(array $kontext): array
{
    $nein = fn(string $g) => ['ok' => false, 'grund' => $g];

    $phase = (string)($kontext['phase'] ?? 'vorbereitung');
    $rolle = (string)($kontext['rolle'] ?? 'eltern');

    if (in_array($phase, ['vorbereitung', 'geschlossen', 'archiviert'], true)) {
        return $nein('Für diesen Sprechtag können derzeit keine Termine gebucht werden.');
    }
    if (!($kontext['slot_im_raster'] ?? false)) {
        return $nein('Dieser Zeitpunkt gehört nicht zum Raster der Lehrkraft.');
    }
    if (!($kontext['slot_frei'] ?? false)) {
        return $nein('Dieser Termin ist bereits vergeben.');
    }
    if (!($kontext['darf_lehrkraft'] ?? false)) {
        return $nein('Bei dieser Lehrkraft kann für dieses Kind kein Termin gebucht werden.');
    }

    // Phase 1: nur eingeladene Eltern; Lehrkräfte buchen stellvertretend
    if ($phase === 'phase1' && $rolle !== 'admin') {
        if ($rolle === 'eltern' || $rolle === 'schueler') {
            if (!($kontext['eingeladen'] ?? false)) {
                return $nein('In der aktuellen Phase können nur eingeladene '
                    . 'Erziehungsberechtigte einen Termin buchen.');
            }
        }
    }

    $max = (int)($kontext['max_termine'] ?? 0);
    $ist = (int)($kontext['anzahl_termine'] ?? 0);
    if ($max > 0 && $ist >= $max && $rolle !== 'admin' && $rolle !== 'lehrkraft') {
        return $nein('Die maximale Anzahl von ' . $max . ' Terminen ist erreicht.');
    }

    return ['ok' => true, 'grund' => ''];
}

/**
 * Prüft, ob eine Buchung storniert werden darf.
 * Regel: Eltern dürfen NICHT stornieren, wenn die Buchung in Phase 1
 * entstanden ist. Lehrkräfte und Admins dürfen immer.
 */
function slot_storno_erlaubt(array $buchung, string $rolle, int $eigeneUserId): array
{
    if ($rolle === 'admin' || $rolle === 'lehrkraft') {
        return ['ok' => true, 'grund' => ''];
    }
    if ((int)($buchung['eltern_user_id'] ?? 0) !== $eigeneUserId) {
        return ['ok' => false, 'grund' => 'Dieser Termin gehört zu einem anderen Konto.'];
    }
    if ((string)($buchung['phase'] ?? '') === 'phase1') {
        return ['ok' => false, 'grund' => 'Dieser Termin wurde auf Einladung der '
            . 'Lehrkraft vergeben und kann nur von ihr abgesagt werden.'];
    }
    return ['ok' => true, 'grund' => ''];
}

/**
 * Markiert doppelt belegte Räume. Eingabe: Liste aus
 * ['lehrer_id' => int, 'raum_id' => int|null, ...].
 * Rückgabe: raum_id => Anzahl der Lehrkräfte (nur > 1).
 */
function slot_raumkonflikte(array $zuweisungen): array
{
    $zaehler = [];
    foreach ($zuweisungen as $z) {
        $raum = $z['raum_id'] ?? null;
        if ($raum === null || $raum === '') continue;
        $zaehler[(int)$raum] = ($zaehler[(int)$raum] ?? 0) + 1;
    }
    return array_filter($zaehler, fn($n) => $n > 1);
}

/**
 * Prüft, ob eine Sonderlehrkraft für einen Jahrgang freigegeben ist.
 * Leere Jahrgangsliste = für alle.
 */
function slot_sonderlehrer_passt(string $jahrgaenge, string $jahrgangKind): bool
{
    $liste = array_filter(array_map('trim', explode(',', $jahrgaenge)));
    if ($liste === []) return true;
    if ($jahrgangKind === '') return false;
    foreach ($liste as $j) {
        if (strcasecmp($j, $jahrgangKind) === 0) return true;
    }
    return false;
}
