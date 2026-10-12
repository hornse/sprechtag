<?php
// ============================================================
// admins.php – wer Administrator ist und woher (v0.9.98, E29)
//
// Drei Herkünfte, eine Entscheidung (adm_herkunft):
//   webuntis     – das WebUntis-Admin-Konto (personType 16), ohne Bedingung:
//                  Notzugang für den Notzugang, und nur ihm liefert WebUntis
//                  die Gruppenliste der Volljährigen (E29)
//   notzugang    – admin_kuerzel in config.php; braucht einen Serverzugang
//   oberflaeche  – Einträge in app_admins, gepflegt über /api/admins
//
// Kürzel werden in PHP mit wu_kuerzel_gleich() verglichen, nicht in SQL:
// sonst hinge das Ergebnis an der Kollation (MariaDB unempfindlich, SQLite
// der Tests empfindlich). app_admins wird nur in dieser Datei gelesen.
// ============================================================

declare(strict_types=1);

/** Aktive Lehrkräfte aus den Stammdaten: [['kuerzel' => …, 'name' => …], …]. */
function adm_lehrer(PDO $pdo): array
{
    return $pdo->query('SELECT kuerzel, name FROM lehrer WHERE aktiv = 1 ORDER BY kuerzel')
        ->fetchAll(PDO::FETCH_ASSOC);
}

/** Stammsatz zu einem Kürzel (unempfindlich, E22) oder null. */
function adm_lehrer_finden(array $lehrer, ?string $kuerzel): ?array
{
    foreach ($lehrer as $l) {
        if (wu_kuerzel_gleich((string)$l['kuerzel'], (string)$kuerzel)) return $l;
    }
    return null;
}

/** Alle Einträge der Oberfläche, in der Reihenfolge ihrer Kürzel. */
function adm_tabelle(PDO $pdo): array
{
    return $pdo->query('SELECT id, lehrer_kuerzel, anzeigename, angelegt_von, angelegt_am
        FROM app_admins ORDER BY lehrer_kuerzel')->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Herkunft des Admin-Rechts: 'webuntis' | 'notzugang' | 'oberflaeche' | null.
 * Die einzige Stelle, die das entscheidet – bei der Anmeldung (wu_login) und
 * bei jedem Aufruf einer Admin-Sitzung (adm_sitzung_nachpruefen).
 */
function adm_herkunft(PDO $pdo, array $wcfg, ?string $kuerzel, bool $adminKonto): ?string
{
    if ($adminKonto) return 'webuntis';
    if (wu_ist_config_admin($kuerzel, $wcfg)) return 'notzugang';
    if ($kuerzel === null || trim($kuerzel) === '') return null;
    foreach (adm_tabelle($pdo) as $z) {
        if (wu_kuerzel_gleich((string)$z['lehrer_kuerzel'], $kuerzel)) return 'oberflaeche';
    }
    return null;
}

/**
 * Rolle einer Admin-Sitzung bei jedem Aufruf neu prüfen (E29). Wer
 * entfernt wurde, ist ab dem nächsten Aufruf Lehrkraft – nicht erst nach
 * der nächsten Anmeldung. Andere Sitzungen fassen die Datenbank nicht an.
 * Befördert wird hier nie; das bleibt der Anmeldung vorbehalten.
 */
function adm_sitzung_nachpruefen(array $cfg): void
{
    if (($_SESSION['rolle'] ?? null) !== 'admin') return;
    $kuerzel = isset($_SESSION['kuerzel']) ? (string)$_SESSION['kuerzel'] : null;
    $herkunft = adm_herkunft(db($cfg), $cfg['webuntis'] ?? [], $kuerzel,
        ($_SESSION['wu_admin_konto'] ?? false) === true);
    if ($herkunft === null) {
        // Admin über die Liste gab es nur für Lehrerkonten (personType 2).
        $_SESSION['rolle'] = 'lehrkraft';
        error_log('sprechtag: Admin-Recht entzogen, Sitzung auf Lehrkraft zurückgestuft');
    }
}

/**
 * Alle Administratoren für die Anzeige, je mit Herkunft. Ein Kürzel, das in
 * beiden Listen steht, erscheint einmal, als Notzugang, mit der Id seines
 * Tabelleneintrags (auch_oberflaeche): Der Eintrag lässt sich entfernen,
 * das Recht bleibt.
 */
function adm_liste(PDO $pdo, array $wcfg, ?string $eigenes): array
{
    $lehrer = adm_lehrer($pdo);
    $tabelle = adm_tabelle($pdo);
    $selbst = fn(string $k): bool => $eigenes !== null && wu_kuerzel_gleich($k, $eigenes);
    $aus = [['herkunft' => 'webuntis', 'kuerzel' => null, 'name' => '', 'id' => null,
             'selbst' => false, 'entfernbar' => false, 'auch_oberflaeche' => false, 'ohne_stammsatz' => false]];
    $vergeben = [];
    foreach (wu_kuerzel_liste($wcfg['admin_kuerzel'] ?? []) as $k) {
        $l = adm_lehrer_finden($lehrer, $k);
        $id = null;
        foreach ($tabelle as $i => $z) {
            if (wu_kuerzel_gleich((string)$z['lehrer_kuerzel'], $k)) { $id = (int)$z['id']; $vergeben[$i] = true; }
        }
        $kz = $l !== null ? (string)$l['kuerzel'] : $k;
        $aus[] = ['herkunft' => 'notzugang', 'kuerzel' => $kz, 'name' => $l !== null ? (string)$l['name'] : '',
                  'id' => $id, 'selbst' => $selbst($kz), 'entfernbar' => $id !== null && !$selbst($kz),
                  'auch_oberflaeche' => $id !== null, 'ohne_stammsatz' => $l === null];
    }
    foreach ($tabelle as $i => $z) {
        if (isset($vergeben[$i])) continue;
        $l = adm_lehrer_finden($lehrer, (string)$z['lehrer_kuerzel']);
        $kz = $l !== null ? (string)$l['kuerzel'] : (string)$z['lehrer_kuerzel'];
        $aus[] = ['herkunft' => 'oberflaeche', 'kuerzel' => $kz,
                  'name' => $l !== null ? (string)$l['name'] : (string)$z['anzeigename'],
                  'id' => (int)$z['id'], 'selbst' => $selbst($kz), 'entfernbar' => !$selbst($kz),
                  'auch_oberflaeche' => false, 'ohne_stammsatz' => $l === null,
                  'angelegt_von' => (string)$z['angelegt_von'], 'angelegt_am' => (string)$z['angelegt_am']];
    }
    return $aus;
}

/** Eintrag über die Oberfläche. Rückgabe: ['status' => …] plus Antwortdaten. */
function adm_eintragen(PDO $pdo, array $wcfg, string $kuerzel, string $von): array
{
    $kuerzel = trim($kuerzel);
    if (wu_ist_config_admin($kuerzel, $wcfg)) {
        return ['status' => 409, 'fehler' => '„' . $kuerzel . '“ ist bereits Administrator über den Notzugang '
            . '(admin_kuerzel in config.php) und braucht hier keinen Eintrag.'];
    }
    $l = adm_lehrer_finden(adm_lehrer($pdo), $kuerzel);
    if ($l === null) {
        return ['status' => 404, 'fehler' => 'Eine Lehrkraft mit dem Kürzel „' . $kuerzel . '“ gibt es in den '
            . 'Stammdaten nicht. Gegebenenfalls zuerst die Stammdaten aus WebUntis übernehmen.'];
    }
    foreach (adm_tabelle($pdo) as $z) {
        if (wu_kuerzel_gleich((string)$z['lehrer_kuerzel'], (string)$l['kuerzel'])) {
            return ['status' => 200, 'ok' => true, 'hinweis' => '„' . $l['kuerzel'] . '“ ist bereits eingetragen.'];
        }
    }
    $pdo->prepare('INSERT INTO app_admins (lehrer_kuerzel, anzeigename, angelegt_von) VALUES (?, ?, ?)')
        ->execute([(string)$l['kuerzel'], (string)$l['name'], $von]);
    return ['status' => 200, 'ok' => true];
}

/** Eintrag entfernen. Rückgabe: ['status' => …] plus Antwortdaten mit 'hinweise'. */
function adm_entfernen(PDO $pdo, array $wcfg, int $id, ?string $eigenes): array
{
    $ziel = null;
    foreach (adm_tabelle($pdo) as $z) {
        if ((int)$z['id'] === $id) $ziel = (string)$z['lehrer_kuerzel'];
    }
    if ($ziel === null) return ['status' => 404, 'fehler' => 'Diesen Eintrag gibt es nicht (mehr).'];
    // Unempfindlich wie die Anmeldung (E22): 'hq' in app_admins ist 'Hq'.
    if (wu_kuerzel_gleich($ziel, (string)($eigenes ?? ''))) {
        return ['status' => 403, 'fehler' => 'Du kannst dich nicht selbst als Administrator entfernen'];
    }
    $pdo->prepare('DELETE FROM app_admins WHERE id = ?')->execute([$id]);

    $hinweise = [];
    $notzugang = wu_kuerzel_liste($wcfg['admin_kuerzel'] ?? []);
    if (wu_ist_config_admin($ziel, $wcfg)) {
        $hinweise[] = '„' . $ziel . '“ bleibt Administrator: Das Kürzel steht als Notzugang in config.php '
            . '(admin_kuerzel). Entfernt ist nur der Eintrag hier.';
    } else {
        $rest = array_filter(adm_tabelle($pdo), fn($z) => !wu_ist_config_admin((string)$z['lehrer_kuerzel'], $wcfg));
        if ($rest === []) {
            $hinweise[] = 'Über die Oberfläche ist jetzt niemand mehr Administrator. ' . ($notzugang !== []
                ? 'Zugang bleibt über den Notzugang (admin_kuerzel in config.php) und das WebUntis-Administrationskonto.'
                : 'Zugang bleibt nur über das WebUntis-Administrationskonto; in admin_kuerzel steht kein Kürzel.');
        }
    }
    return ['status' => 200, 'ok' => true, 'hinweise' => $hinweise];
}

/** Antwortet mit einem Ergebnis von adm_eintragen/adm_entfernen. */
function adm_antworten(array $e): never
{
    $status = (int)$e['status'];
    unset($e['status']);
    if ($status >= 400) json_err((string)$e['fehler'], $status);
    json_ok($e, $status);
}
