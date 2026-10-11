<?php
// ============================================================
// absagen.php – abgesagte Termine und Einladungsstatus (E18, E19)
//
// Was die Schule absagt – Lehrkraft oder Verwaltung, einzeln oder per
// Krankheitsausfall –, wird in abgesagte_termine abgelegt und dann aus
// buchungen gelöscht; der Slot ist sofort frei. Storniert eine Familie
// selbst, wird nur gelöscht (G2). Buchungen entfernt nur
// bu_buchungen_entfernen(), ausgenommen das Archivieren, das den ganzen
// Sprechtag leert (Engstelle: tests/run_abgesagte_termine.php).
//
// Steht in einer eigenen Datei, die index.php oben lädt: Die Ausfall-Route
// steht in index.php vor dem require von buchungen.php (vgl.
// bu_zugelassene_gruppen in slots.php, tests/run_ladereihenfolge.php).
//
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================

declare(strict_types=1);

/**
 * Anlass einer Absage nach der Rolle, die sie auslöst: Lehrkraft und
 * Verwaltung sagen ab ('absage'), Familie und volljährige Schüler
 * stornieren selbst (null – nichts abzulegen, niemand zu benachrichtigen).
 */
function bu_absage_anlass(string $rolle): ?string
{
    return in_array($rolle, ['lehrkraft', 'admin'], true) ? 'absage' : null;
}

/**
 * Entfernt Buchungen – die eine Stelle dafür. $wo ist ['id' => …] oder
 * ['sprechtag_id' => …, 'lehrer_id' => …]; alles andere wird abgewiesen,
 * auch eine leere Bedingung (sie träfe jede Buchung). Mit Anlass ('absage'
 * oder 'ausfall') werden die Zeilen vorher abgelegt, in derselben
 * Transaktion: Scheitert das Ablegen, bleibt die Buchung stehen.
 *
 * Gelöscht werden genau die gelesenen Zeilen (nach id), nicht erneut nach
 * der Bedingung – eine Buchung, die dazwischen entsteht, wird weder still
 * gelöscht noch ohne Benachrichtigung abgelegt. Liefert die entfernten
 * Zeilen (alle Spalten) für die Benachrichtigung.
 */
function bu_buchungen_entfernen(PDO $pdo, array $wo, ?string $anlass): array
{
    $schluessel = array_keys($wo);
    sort($schluessel);
    if ($schluessel !== ['id'] && $schluessel !== ['lehrer_id', 'sprechtag_id']) {
        throw new InvalidArgumentException('bu_buchungen_entfernen: nur nach id oder nach Sprechtag und Lehrkraft');
    }
    if ($anlass !== null && !in_array($anlass, ['absage', 'ausfall'], true)) {
        throw new InvalidArgumentException('bu_buchungen_entfernen: Anlass ist absage, ausfall oder null');
    }
    $bedingung = implode(' AND ', array_map(fn(string $k): string => "$k = ?", array_keys($wo)));
    $werte = array_map('intval', array_values($wo));

    $eigene = !$pdo->inTransaction();
    if ($eigene) $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT * FROM buchungen WHERE $bedingung ORDER BY slot_beginn");
        $st->execute($werte);
        $zeilen = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($zeilen !== []) {
            $ids = array_map(fn(array $z): int => (int)$z['id'], $zeilen);
            $in = implode(',', array_fill(0, count($ids), '?'));
            if ($anlass !== null) {
                $pdo->prepare("INSERT INTO abgesagte_termine
                        (sprechtag_id, lehrer_id, slot_beginn, schueler_id, kind_name, kind_klasse, kommentar, anlass)
                    SELECT sprechtag_id, lehrer_id, slot_beginn, schueler_id, kind_name, kind_klasse, kommentar, ?
                    FROM buchungen WHERE id IN ($in)")
                    ->execute(array_merge([$anlass], $ids));
            }
            $pdo->prepare("DELETE FROM buchungen WHERE id IN ($in)")->execute($ids);
        }
        if ($eigene) $pdo->commit();
        return $zeilen;
    } catch (Throwable $e) {
        // Zurückrollen und weiterreichen – nichts wird hier verschluckt.
        if ($eigene && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Status einer Einladung (E18, G1) als SQL-Ausdruck über den Alias der
 * Einladung – abgeleitet, nie gespeichert. Drei Stufen, die erste trifft:
 * 'gebucht', wenn das Kind bei dieser Lehrkraft an diesem Sprechtag einen
 * Termin hat; 'abgesagt', wenn die Schule ihm dort einen abgesagt hat
 * (Ablage, E19); sonst 'offen'. Gebucht geht vor abgesagt: Wer nach einer
 * Absage neu gebucht hat, ist erledigt. Eine Familienstornierung legt
 * nichts ab und ergibt 'offen'.
 */
function bu_einladung_status_sql(string $e): string
{
    $gleich = bu_selber_vorgang_sql('x', $e);
    return "(CASE WHEN EXISTS (SELECT 1 FROM buchungen x WHERE $gleich) THEN 'gebucht'"
        . " WHEN EXISTS (SELECT 1 FROM abgesagte_termine x WHERE $gleich) THEN 'abgesagt'"
        . " ELSE 'offen' END)";
}

/**
 * Dasselbe Kind bei derselben Lehrkraft am selben Sprechtag – die eine
 * Bedingung, nach der Einladung, Termin und abgesagter Termin zueinander
 * gehören. Status (oben) und „neu gebucht“ (unten) fragen hier.
 */
function bu_selber_vorgang_sql(string $a, string $b): string
{
    return "$a.sprechtag_id = $b.sprechtag_id AND $a.lehrer_id = $b.lehrer_id"
        . " AND $a.schueler_id = $b.schueler_id";
}

/**
 * Abgesagte Termine einer Lehrkraft an einem Sprechtag für ihre Ansicht
 * (G4), nach Uhrzeit. neu_gebucht ist die Uhrzeit eines Termins, den das
 * Kind danach bei derselben Lehrkraft hat (abgeleitet), sonst null. Ohne
 * Kennung von Kind oder Eltern – die Ansicht braucht sie nicht. Fragt die
 * Teilnahme nicht: Nach einem Ausfall (teilnahme = 0) muss die Lehrkraft
 * ihre abgesagten Termine gerade sehen.
 */
function bu_abgesagte_termine(PDO $pdo, int $sprechtagId, int $lehrerId): array
{
    $st = $pdo->prepare('SELECT a.slot_beginn, a.kind_name, a.kind_klasse AS klasse, a.kommentar,
               a.anlass, a.abgesagt_am,
               (SELECT MIN(x.slot_beginn) FROM buchungen x WHERE ' . bu_selber_vorgang_sql('x', 'a') . ') AS neu_gebucht
        FROM abgesagte_termine a
        WHERE a.sprechtag_id = ? AND a.lehrer_id = ?
        ORDER BY a.slot_beginn, a.abgesagt_am, a.id');
    $st->execute([$sprechtagId, $lehrerId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
