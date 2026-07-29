<?php
// ============================================================
// tests/run_slots.php – Offline-Tests der Buchungslogik
// Aufruf: php tests/run_slots.php   → Exit-Code 0 = alles grün
// ============================================================

declare(strict_types=1);

require __DIR__ . '/../backend/api/slots.php';
require __DIR__ . '/../backend/auth/extractors.php';
require __DIR__ . '/../backend/api/webuntis_adapter.php';

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

// ------------------------------------------------------------
echo "slot_raster\n";
$st = ['beginn' => '15:00', 'ende' => '16:00', 'slot_minuten' => 10,
       'pause_nach_terminen' => 0, 'pause_minuten' => 10];
$r = slot_raster($st);
pruefe('6 Slots à 10 min in einer Stunde', count($r) === 6);
pruefe('erster Slot 15:00-15:10',
    $r[0]['beginn'] === '15:00' && $r[0]['ende'] === '15:10');
pruefe('letzter Slot endet 16:00', end($r)['ende'] === '16:00');

// ------------------------------------------------------------
echo "slot_haelfte_fenster\n";
$sp = ['beginn' => '15:00', 'ende' => '19:00', 'slot_minuten' => 10];
$e = slot_haelfte_fenster($sp, 'erste');
pruefe('erste Hälfte 15:00-17:00',
    $e['von'] === '15:00' && $e['bis'] === '17:00');
$z = slot_haelfte_fenster($sp, 'zweite');
pruefe('zweite Hälfte 17:00-19:00',
    $z['von'] === '17:00' && $z['bis'] === '19:00');
$g = slot_haelfte_fenster($sp, 'ganz');
pruefe('ganz = kein Fenster', $g['von'] === null && $g['bis'] === null);
pruefe('Hälften stoßen lückenlos aneinander', $e['bis'] === $z['von']);
// Ungerade Spanne: Mitte wird auf einen echten Slot-Anfang gerundet.
$sp2 = ['beginn' => '15:00', 'ende' => '18:30', 'slot_minuten' => 10];
$e2 = slot_haelfte_fenster($sp2, 'erste');
pruefe('ungerade Spanne rundet Mitte auf Slotgrenze (16:50)',
    $e2['bis'] === '16:50');
$z2 = slot_haelfte_fenster($sp2, 'zweite');
pruefe('zweite Hälfte beginnt an derselben Grenze', $z2['von'] === '16:50');

// Pausen
$st2 = $st + [];
$st2['pause_nach_terminen'] = 3;
$st2['pause_minuten'] = 5;
$r2 = slot_raster($st2);
$slots  = array_values(array_filter($r2, fn($z) => $z['typ'] === 'slot'));
$pausen = array_values(array_filter($r2, fn($z) => $z['typ'] === 'pause'));
pruefe('Pause nach 3 Slots eingefügt', count($pausen) >= 1);
pruefe('Pause beginnt 15:30', $pausen[0]['beginn'] === '15:30');
pruefe('Pause dauert 5 min', $pausen[0]['ende'] === '15:35');
pruefe('Slot nach Pause beginnt 15:35', $slots[3]['beginn'] === '15:35');
pruefe('weniger Slots durch Pausen', count($slots) < 6);

// Teilzeit-Fenster
$r3 = slot_raster($st, '15:20', '15:50');
pruefe('Teilzeitfenster: 3 Slots', count($r3) === 3);
pruefe('Teilzeit startet 15:20', $r3[0]['beginn'] === '15:20');

// Fenster außerhalb des Rahmens wird beschnitten
$r4 = slot_raster($st, '14:00', '20:00');
pruefe('Fenster auf Rahmen begrenzt',
    $r4[0]['beginn'] === '15:00' && end($r4)['ende'] === '16:00');
pruefe('ungültiges Fenster -> leer', slot_raster($st, '16:30', '15:00') === []);

// Keine Pause am Ende (wenn danach kein Slot mehr passt)
$st3 = ['beginn' => '15:00', 'ende' => '15:30', 'slot_minuten' => 10,
        'pause_nach_terminen' => 3, 'pause_minuten' => 10];
$r5 = slot_raster($st3);
pruefe('keine Pause ohne Folgeslot',
    count(array_filter($r5, fn($z) => $z['typ'] === 'pause')) === 0);

// ------------------------------------------------------------
echo "slot_pausen_anwenden (dynamisch, Variante 1)\n";
// Dynamisches Gitter: 15:00–16:00, 10 min -> 6 gleichmäßige Slots, KEINE
// vorab eingefügten Pausen.
$stD = ['beginn' => '15:00', 'ende' => '16:00', 'slot_minuten' => 10,
        'pause_nach_terminen' => 3, 'pause_minuten' => 10, 'pause_dynamisch' => 1];
$gitter = slot_raster($stD);
pruefe('dynamisch: 6 reine Slots, keine festen Pausen',
    count($gitter) === 6
    && count(array_filter($gitter, fn($z) => $z['typ'] === 'pause')) === 0);

// Fall A: 3 zusammenhängend belegt -> Pause beim nächsten freien Slot (15:30)
$rA = slot_pausen_anwenden($gitter, ['15:00' => true, '15:10' => true, '15:20' => true], true, 3);
$pauseA = array_values(array_filter($rA, fn($z) => $z['typ'] === 'pause'));
pruefe('A: Pause nach 3 belegten bei 15:30',
    count($pauseA) === 1 && $pauseA[0]['beginn'] === '15:30');

// Fall B: Lücke unterbricht die Kette -> keine Pause
$rB = slot_pausen_anwenden($gitter, ['15:00' => true, '15:10' => true, '15:30' => true], true, 3);
pruefe('B: Lücke -> keine Pause',
    count(array_filter($rB, fn($z) => $z['typ'] === 'pause')) === 0);

// Fall C: gebuchte Zeit bleibt stabil -> 15:30 belegt bleibt Buchung,
// Pause rutscht auf den nächsten freien Slot (15:40)
$rC = slot_pausen_anwenden($gitter,
    ['15:00' => true, '15:10' => true, '15:20' => true, '15:30' => true], true, 3);
$slot330 = array_values(array_filter($rC, fn($z) => $z['beginn'] === '15:30'))[0];
$pauseC = array_values(array_filter($rC, fn($z) => $z['typ'] === 'pause'));
pruefe('C: belegte 15:30 bleibt Slot (keine Pause)', $slot330['typ'] === 'slot');
pruefe('C: Pause rutscht auf 15:40',
    count($pauseC) === 1 && $pauseC[0]['beginn'] === '15:40');

// Fall D: fester Modus (dynamisch=false) lässt das Raster unverändert
$rD = slot_pausen_anwenden($gitter, ['15:00' => true, '15:10' => true, '15:20' => true], false, 3);
pruefe('D: fester Modus verändert nichts',
    count(array_filter($rD, fn($z) => $z['typ'] === 'pause')) === 0);

// ------------------------------------------------------------
echo "slot_buchung_erlaubt\n";
$basis = ['phase' => 'phase2', 'rolle' => 'eltern', 'eingeladen' => false,
          'darf_lehrkraft' => true, 'slot_frei' => true, 'slot_im_raster' => true,
          'anzahl_termine' => 0, 'max_termine' => 6];
pruefe('Normalfall erlaubt', slot_buchung_erlaubt($basis)['ok'] === true);
pruefe('belegter Slot abgelehnt',
    slot_buchung_erlaubt(['slot_frei' => false] + $basis)['ok'] === false);
pruefe('Slot außerhalb Raster abgelehnt',
    slot_buchung_erlaubt(['slot_im_raster' => false] + $basis)['ok'] === false);
pruefe('fremde Lehrkraft abgelehnt',
    slot_buchung_erlaubt(['darf_lehrkraft' => false] + $basis)['ok'] === false);
pruefe('Maximum erreicht -> abgelehnt',
    slot_buchung_erlaubt(['anzahl_termine' => 6] + $basis)['ok'] === false);
pruefe('Maximum 0 = unbegrenzt',
    slot_buchung_erlaubt(['anzahl_termine' => 99, 'max_termine' => 0] + $basis)['ok'] === true);
pruefe('Vorbereitung: keine Buchung',
    slot_buchung_erlaubt(['phase' => 'vorbereitung'] + $basis)['ok'] === false);
pruefe('geschlossen: keine Buchung',
    slot_buchung_erlaubt(['phase' => 'geschlossen'] + $basis)['ok'] === false);
pruefe('archiviert: keine Buchung',
    slot_buchung_erlaubt(['phase' => 'archiviert'] + $basis)['ok'] === false);

// Phase 1: nur eingeladene Eltern
$p1 = ['phase' => 'phase1'] + $basis;
pruefe('Phase 1 ohne Einladung: abgelehnt', slot_buchung_erlaubt($p1)['ok'] === false);
pruefe('Phase 1 mit Einladung: erlaubt',
    slot_buchung_erlaubt(['eingeladen' => true] + $p1)['ok'] === true);
pruefe('Phase 1: Lehrkraft darf stellvertretend buchen',
    slot_buchung_erlaubt(['rolle' => 'lehrkraft'] + $p1)['ok'] === true);
pruefe('Phase 1: Admin darf buchen',
    slot_buchung_erlaubt(['rolle' => 'admin'] + $p1)['ok'] === true);
pruefe('Phase 1: volljähriger Schüler ohne Einladung abgelehnt',
    slot_buchung_erlaubt(['rolle' => 'schueler'] + $p1)['ok'] === false);
pruefe('Lehrkraft ignoriert Eltern-Maximum',
    slot_buchung_erlaubt(['rolle' => 'lehrkraft', 'anzahl_termine' => 99] + $basis)['ok'] === true);

// ------------------------------------------------------------
echo "slot_storno_erlaubt\n";
$b2 = ['eltern_user_id' => 5984, 'phase' => 'phase2'];
$b1 = ['eltern_user_id' => 5984, 'phase' => 'phase1'];
pruefe('Eltern stornieren eigene Phase-2-Buchung',
    slot_storno_erlaubt($b2, 'eltern', 5984)['ok'] === true);
pruefe('Eltern stornieren KEINE Phase-1-Buchung',
    slot_storno_erlaubt($b1, 'eltern', 5984)['ok'] === false);
pruefe('Lehrkraft storniert Phase-1-Buchung',
    slot_storno_erlaubt($b1, 'lehrkraft', 0)['ok'] === true);
pruefe('Admin storniert immer',
    slot_storno_erlaubt($b1, 'admin', 0)['ok'] === true);
pruefe('fremde Buchung abgelehnt',
    slot_storno_erlaubt($b2, 'eltern', 9999)['ok'] === false);

// ------------------------------------------------------------
echo "slot_raumkonflikte\n";
$zu = [['lehrer_id' => 1, 'raum_id' => 10], ['lehrer_id' => 2, 'raum_id' => 10],
       ['lehrer_id' => 3, 'raum_id' => 11], ['lehrer_id' => 4, 'raum_id' => null]];
$k = slot_raumkonflikte($zu);
pruefe('Raum 10 als Konflikt erkannt', ($k[10] ?? 0) === 2);
pruefe('Raum 11 kein Konflikt', !isset($k[11]));
pruefe('NULL-Räume ignoriert', count($k) === 1);
pruefe('leere Liste -> keine Konflikte', slot_raumkonflikte([]) === []);

// ------------------------------------------------------------
echo "slot_sonderlehrer_passt\n";
pruefe('leere Liste = alle Jahrgänge', slot_sonderlehrer_passt('', '7a') === true);
pruefe('Treffer in Liste', slot_sonderlehrer_passt('EF,Q1,Q2', 'Q1') === true);
pruefe('kein Treffer', slot_sonderlehrer_passt('EF,Q1', '7a') === false);
pruefe('Groß-/Kleinschreibung egal', slot_sonderlehrer_passt('ef,q1', 'EF') === true);
pruefe('Leerzeichen toleriert', slot_sonderlehrer_passt('EF, Q1 , Q2', 'Q2') === true);
pruefe('unbekannter Jahrgang bei Filter -> nein',
    slot_sonderlehrer_passt('EF', '') === false);

// ------------------------------------------------------------
echo "rest_lehrkraefte_aus_entries (Vertretungen!)\n";
$eintrag = function (string $status, array $lehrer, string $fach = 'M',
                     string $typ = 'NORMAL_TEACHING_PERIOD'): array {
    $pos2 = [];
    foreach ($lehrer as $k => $lang) {
        $pos2[] = ['current' => ['type' => 'TEACHER', 'shortName' => $k,
                                 'longName' => $lang, 'status' => $status]];
    }
    return ['type' => $typ, 'status' => $status,
            'position1' => [['current' => ['type' => 'SUBJECT', 'shortName' => $fach]]],
            'position2' => $pos2,
            'position3' => [['current' => ['type' => 'ROOM', 'shortName' => 'A1']]]];
};
$plan = ['days' => [['gridEntries' => [
    $eintrag('REGULAR',      ['Gr' => 'Greitemann']),
    $eintrag('REGULAR',      ['Gr' => 'Greitemann'], 'D'),
    $eintrag('CANCELLED',    ['Kl' => 'Klein'], 'E'),
    $eintrag('SUBSTITUTION', ['Ve' => 'Vertretung'], 'M'),
    $eintrag('REGULAR',      ['Au' => 'Aufsicht'], '', 'BREAK_SUPERVISION'),
    $eintrag('REGULAR',      ['Ev' => 'Event'], '', 'EVENT'),
]]]];
$ex = rest_lehrkraefte_aus_entries($plan);
$kuerzel = array_keys($ex['lehrkraefte']);
pruefe('Vertretung NICHT gewertet', !in_array('Ve', $kuerzel, true));
pruefe('Pausenaufsicht NICHT gewertet', !in_array('Au', $kuerzel, true));
pruefe('Event NICHT gewertet', !in_array('Ev', $kuerzel, true));
pruefe('Ausfall (CANCELLED) GEWERTET', in_array('Kl', $kuerzel, true));
pruefe('reguläre Lehrkraft gewertet', in_array('Gr', $kuerzel, true));
pruefe('Stunden gezählt', $ex['lehrkraefte']['Gr']['stunden'] === 2);
pruefe('nach Stunden sortiert', $kuerzel[0] === 'Gr');
pruefe('Fächer gesammelt',
    array_keys($ex['lehrkraefte']['Gr']['faecher']) === ['M', 'D']);
pruefe('Langname übernommen', $ex['lehrkraefte']['Gr']['name'] === 'Greitemann');
pruefe('robust bei Unsinn', rest_lehrkraefte_aus_entries('x')['lehrkraefte'] === []);

// removed wird ignoriert (nur current zählt)
$mitRemoved = ['days' => [['gridEntries' => [[
    'type' => 'NORMAL_TEACHING_PERIOD', 'status' => 'REGULAR',
    'position1' => [['current' => ['type' => 'SUBJECT', 'shortName' => 'M']]],
    'position2' => [['current' => ['type' => 'TEACHER', 'shortName' => 'Ne'],
                     'removed' => ['type' => 'TEACHER', 'shortName' => 'Weg']]],
]]]]];
pruefe('removed ignoriert',
    array_keys(rest_lehrkraefte_aus_entries($mitRemoved)['lehrkraefte']) === ['Ne']);

// ------------------------------------------------------------
echo "rest_konto_aus_appdata\n";
$app = ['user' => ['id' => 5984, 'person' => ['id' => 476, 'displayName' => 'Horn Sebastian'],
    'roles' => ['LEGAL_GUARDIAN'],
    'students' => [['id' => 13914, 'displayName' => 'Paulowski Paul'],
                   ['id' => 14069, 'displayName' => 'Paulowski Petra']]]];
$k = rest_konto_aus_appdata($app);
pruefe('userId gelesen', $k['userId'] === 5984);
pruefe('personId gelesen', $k['personId'] === 476);
pruefe('Rolle gelesen', $k['rollen'] === ['LEGAL_GUARDIAN']);
pruefe('zwei Kinder', count($k['kinder']) === 2);
pruefe('Kind-ID korrekt', $k['kinder'][0]['id'] === 13914);
pruefe('Lehrkraft ohne Kinder',
    rest_konto_aus_appdata(['user' => ['id' => 730, 'students' => []]])['kinder'] === []);
pruefe('Admin -1 verworfen',
    rest_konto_aus_appdata(['user' => ['students' => [['id' => -1]]]])['kinder'] === []);
pruefe('robust ohne user', rest_konto_aus_appdata([])['userId'] === null);

// ------------------------------------------------------------
echo "wu_referenzzeitraum\n";
$ref = wu_referenzzeitraum('2026-11-20');
pruefe('endet 7 Tage vor Sprechtag', $ref['bis'] === '2026-11-13');
pruefe('umfasst 4 Wochen', $ref['von'] === '2026-10-17');
pruefe('von liegt vor bis', $ref['von'] < $ref['bis']);

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler TEST(S) ROT\n");
exit($fehler === 0 ? 0 : 1);
