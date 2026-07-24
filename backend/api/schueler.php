<?php
// ============================================================
// schueler.php – Schülerliste für die Einladungsauswahl
//
// Zwei Quellen, beide optional und kombinierbar:
//   * WebUntis getStudents() – liefert IDs und Namen, aber KEINE
//     Klasse (Befund 07/2026: kein idOfClass-Feld bei dieser Instanz)
//   * CSV aus Schild-NRW – liefert die Klasse zuverlässig
//
// Verknüpft werden beide über die Schild-ID: In WebUntis steht sie
// im Feld 'key' des Schülerdatensatzes.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../helfer.php';
require_once __DIR__ . '/../auth/WebUntisAuth.php';

/**
 * Zerlegt CSV-Text in Schülerzeilen. Reine Funktion – offline testbar.
 *
 * Erwartetes Format je Zeile (Trenner ; , oder Tab):
 *   Nachname;Vorname;Klasse[;Schild-ID[;Austrittsdatum]]
 *
 * Das Austrittsdatum ist optional. Liegt es in der Vergangenheit, gilt
 * der Schüler als nicht mehr an der Schule und erscheint nicht in der
 * Auswahlliste. Grund: Der Schild-Export enthält immer ALLE Schüler,
 * auch ehemalige – ohne dieses Feld wäre die Liste unbrauchbar lang.
 * Akzeptierte Formate: 31.07.2029, 2029-07-31.
 *
 * Leerzeilen und Zeilen mit # werden übersprungen, eine Kopfzeile
 * wird erkannt (wenn das dritte Feld "klasse" heißt).
 *
 * Rückgabe: ['zeilen' => [...], 'uebersprungen' => ['Zeile 3: ...']]
 */
function schueler_csv_parsen(string $csv): array
{
    $zeilen = [];
    $uebersprungen = [];

    foreach (preg_split('/\r\n|\r|\n/', $csv) ?: [] as $nr => $roh) {
        $roh = trim($roh);
        if ($roh === '' || str_starts_with($roh, '#')) continue;

        $teile = array_map('trim', preg_split('/[;,\t]/', $roh) ?: []);
        if (count($teile) < 3) {
            $uebersprungen[] = 'Zeile ' . ($nr + 1)
                . ': mindestens Nachname, Vorname und Klasse nötig';
            continue;
        }

        // Kopfzeile erkennen (erste Zeile, drittes Feld heißt "klasse")
        if ($nr === 0 && preg_match('/^klasse$/i', $teile[2])) continue;

        $nachname = $teile[0];
        $vorname  = $teile[1];
        $klasse   = $teile[2];
        $schildId = $teile[3] ?? '';
        $austritt = schueler_datum_normieren($teile[4] ?? '');

        if ($nachname === '' || $klasse === '') {
            $uebersprungen[] = 'Zeile ' . ($nr + 1) . ': Nachname oder Klasse leer';
            continue;
        }

        $zeilen[] = [
            'nachname'  => kuerze($nachname, 80),
            'vorname'   => kuerze($vorname, 80),
            'klasse'    => kuerze(schueler_klasse_normieren($klasse), 20),
            'schild_id' => kuerze($schildId, 30),
            'austritt'  => $austritt,
            'aktiv'     => schueler_ist_aktiv($austritt) ? 1 : 0,
        ];
    }

    return ['zeilen' => $zeilen, 'uebersprungen' => $uebersprungen];
}

/**
 * Wandelt ein Datum in das Format JJJJ-MM-TT oder liefert '' zurück.
 * Akzeptiert 31.07.2029 und 2029-07-31. Reine Funktion – testbar.
 */
function schueler_datum_normieren(string $datum): string
{
    $d = trim($datum);
    if ($d === '') return '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d)) return $d;
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $d, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    return '';
}

/**
 * Ist ein Schüler noch an der Schule? Ohne Austrittsdatum: ja.
 * Mit Datum in der Zukunft: ja (WebUntis trägt oft das planmäßige
 * Schulende ein, z. B. 31.07.2029 für einen aktuellen Fünftklässler).
 * Reine Funktion – testbar.
 */
function schueler_ist_aktiv(string $austritt, ?string $heute = null): bool
{
    if ($austritt === '') return true;
    $stichtag = $heute ?? date('Y-m-d');
    return $austritt >= $stichtag;
}

/**
 * Vereinheitlicht Klassenbezeichnungen: "06B" und "6b" sollen dieselbe
 * Klasse sein, "EF"/"Q1"/"Q2" bleiben unverändert (Großschreibung).
 * Reine Funktion – offline testbar.
 */
function schueler_klasse_normieren(string $klasse): string
{
    $k = trim($klasse);
    if ($k === '') return '';

    // Jahrgangsstufe + Buchstabe: führende Nullen weg, Buchstabe klein
    if (preg_match('/^0*(\d{1,2})\s*([a-zA-Z]?)$/', $k, $m)) {
        return $m[1] . strtolower($m[2]);
    }
    // Oberstufe und alles andere: Großschreibung, Leerzeichen weg
    return strtoupper(preg_replace('/\s+/', '', $k) ?? $k);
}

/**
 * Übernimmt geparste CSV-Zeilen in die Datenbank.
 * Vorhandene Einträge werden über die Schild-ID erkannt und ergänzt,
 * sonst über Nachname+Vorname+Klasse.
 *
 * Rückgabe: ['neu' => int, 'aktualisiert' => int]
 */
function schueler_csv_importieren(PDO $pdo, array $zeilen): array
{
    $neu = 0; $aktualisiert = 0;

    $findeSchild = $pdo->prepare(
        'SELECT id FROM schueler WHERE schild_id = ? AND schild_id <> "" LIMIT 1');
    $findeName = $pdo->prepare(
        'SELECT id FROM schueler WHERE nachname = ? AND vorname = ? LIMIT 1');
    $update = $pdo->prepare(
        'UPDATE schueler SET nachname = ?, vorname = ?, klasse = ?,
                schild_id = IF(? <> "", ?, schild_id),
                austritt = ?, aktiv = ?
         WHERE id = ?');
    $insert = $pdo->prepare(
        'INSERT INTO schueler (schild_id, vorname, nachname, klasse,
                               austritt, quelle, aktiv)
         VALUES (?, ?, ?, ?, ?, "csv", ?)');

    foreach ($zeilen as $z) {
        $id = false;
        if ($z['schild_id'] !== '') {
            $findeSchild->execute([$z['schild_id']]);
            $id = $findeSchild->fetchColumn();
        }
        if ($id === false) {
            $findeName->execute([$z['nachname'], $z['vorname']]);
            $id = $findeName->fetchColumn();
        }

        if ($id === false) {
            $insert->execute([$z['schild_id'], $z['vorname'], $z['nachname'],
                $z['klasse'], $z['austritt'] ?: null, $z['aktiv']]);
            $neu++;
        } else {
            $update->execute([$z['nachname'], $z['vorname'], $z['klasse'],
                $z['schild_id'], $z['schild_id'],
                $z['austritt'] ?: null, $z['aktiv'], (int)$id]);
            $aktualisiert++;
        }
    }

    $inaktiv = count(array_filter($zeilen, fn($z) => $z['aktiv'] === 0));
    return ['neu' => $neu, 'aktualisiert' => $aktualisiert,
            'inaktiv' => $inaktiv];
}

/**
 * Holt die Schülerliste aus WebUntis (getStudents) und schreibt sie in
 * die Datenbank. Liefert KEINE Klassen – die müssen aus dem CSV-Import
 * oder (später) aus Klassenstundenplänen kommen.
 *
 * Rückgabe: ['gelesen' => int, 'neu' => int, 'aktualisiert' => int]
 */
function schueler_webuntis_sync(array $cfg, PDO $pdo,
                                string $benutzer, string $passwort): array
{
    $wcfg = $cfg['webuntis'];
    $wu = new WebUntisAuth($wcfg['base_url'], $wcfg['school'], $wcfg['client']);
    $wu->authenticate($benutzer, $passwort);

    $neu = 0; $aktualisiert = 0; $gelesen = 0;
    try {
        $liste = $wu->getStudents();
        $gelesen = count($liste);

        $finde = $pdo->prepare('SELECT id FROM schueler WHERE webuntis_id = ? LIMIT 1');
        $findeSchild = $pdo->prepare(
            'SELECT id FROM schueler WHERE schild_id = ? AND schild_id <> "" LIMIT 1');
        // Namen aus WebUntis übernehmen, Klasse NICHT anfassen
        // (die kommt aus dem CSV-Import und ist dort verlässlicher)
        $update = $pdo->prepare(
            'UPDATE schueler SET webuntis_id = ?, vorname = ?, nachname = ?,
                    schild_id = IF(? <> "", ?, schild_id), aktiv = 1 WHERE id = ?');
        $insert = $pdo->prepare(
            'INSERT INTO schueler (webuntis_id, schild_id, vorname, nachname,
                                   klasse, quelle, aktiv)
             VALUES (?, ?, ?, ?, "", "webuntis", 1)');

        $gesehen = [];
        foreach ($liste as $s) {
            if (!array_key_exists('id', $s)) continue;
            $wid = (int)$s['id'];
            if ($wid <= 0 || isset($gesehen[$wid])) continue;
            $gesehen[$wid] = true;

            $schild   = kuerze((string)($s['key'] ?? ''), 30);
            $vorname  = kuerze((string)($s['foreName'] ?? ''), 80);
            $nachname = kuerze((string)($s['longName'] ?? ''), 80);

            $finde->execute([$wid]);
            $id = $finde->fetchColumn();
            if ($id === false && $schild !== '') {
                $findeSchild->execute([$schild]);
                $id = $findeSchild->fetchColumn();
            }

            if ($id === false) {
                $insert->execute([$wid, $schild, $vorname, $nachname]);
                $neu++;
            } else {
                $update->execute([$wid, $vorname, $nachname, $schild, $schild, (int)$id]);
                $aktualisiert++;
            }
        }
    } finally {
        $wu->logout();
    }

    return ['gelesen' => $gelesen, 'neu' => $neu, 'aktualisiert' => $aktualisiert];
}

/**
 * Liefert die Schülerliste, nach Klassen gruppiert.
 * $suche filtert über Name oder Klasse.
 */
function schueler_liste(PDO $pdo, string $suche = '', int $grenze = 2000,
                        bool $auchEhemalige = false): array
{
    // Standardmäßig nur Schüler, die noch an der Schule sind – der
    // Schild-Export enthält auch alle ehemaligen.
    $sql = 'SELECT id, webuntis_id, schild_id, vorname, nachname, klasse, austritt
            FROM schueler WHERE 1=1';
    if (!$auchEhemalige) $sql .= ' AND aktiv = 1';
    $werte = [];
    if ($suche !== '') {
        $sql .= ' AND (nachname LIKE ? OR vorname LIKE ? OR klasse LIKE ?)';
        $muster = '%' . $suche . '%';
        $werte = [$muster, $muster, $muster];
    }
    $sql .= ' ORDER BY klasse, nachname, vorname LIMIT ' . (int)$grenze;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($werte);

    $klassen = [];
    foreach ($stmt->fetchAll() as $s) {
        $k = (string)$s['klasse'];
        if ($k === '') $k = '(ohne Klasse)';
        $klassen[$k][] = $s;
    }
    return $klassen;
}
