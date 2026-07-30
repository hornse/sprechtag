<?php
// ============================================================
// einstellungen.php – Individualisierung (Branding)
//
// Erlaubt der Administration, das Erscheinungsbild anzupassen:
// Schulname, Titel, Untertitel, zwei Akzentfarben, Fußzeile und
// ein Logo. Alles landet in der bestehenden Key-Value-Tabelle
// `einstellungen` (Präfix „marke_"); das Logo liegt als Datei
// unter backend/data/logos/ und wird nur über einen eigenen
// Endpunkt ausgeliefert.
//
// Muster übernommen aus hornse/schulprozesse (einstellungen.php),
// angepasst an sprechtag: MariaDB statt SQLite, db()/json_*-Helfer,
// Base64-Upload (umgeht das 63-KB-Proxylimit für Formulardaten).
//
// Routen (Dispatch in api/index.php):
//   GET  /api/einstellungen        – Marke lesen (öffentlich, ohne Logo-Pfad)
//   POST /api/einstellungen        – Marke speichern (admin)
//   POST /api/einstellungen/logo   – Logo hochladen, Base64 (admin)
//   GET  /api/einstellungen/logo   – Logo ausliefern (öffentlich)
//   POST /api/einstellungen/zuruecksetzen – auf Standard (admin)
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../helfer.php';

/** Standardwerte – Rückfall, falls ein Schlüssel fehlt. Bewusst NEUTRAL, damit
 *  das Tool an jeder Schule ohne schulspezifische Reste startet. Die echten
 *  Werte (Schulname, Kontakt …) pflegt die Schule im Admin unter „Erscheinungsbild". */
function marke_standard(): array
{
    return [
        'marke_schulname'  => 'Ihre Schule',
        'marke_titel'      => 'Sprechtag',
        'marke_untertitel' => 'Elternsprechtag',
        'marke_farbe'      => '#1d4e89',
        'marke_farbe2'     => '#1e7d3e',
        'marke_fusszeile'  => 'sprechtag · GPL-3.0-or-later',
        // Kontakt für Rückfragen (E-Mail o. Ä.). Leer = neutraler Hinweistext.
        'marke_kontakt'    => '',
    ];
}

/** Liest alle Marke-Schlüssel (ohne Logo-Pfad) und ergänzt hat_logo. */
function marke_lesen(PDO $pdo): array
{
    $werte = marke_standard();
    $st = $pdo->query(
        "SELECT schluessel, wert FROM einstellungen
         WHERE schluessel LIKE 'marke_%'
           AND schluessel NOT IN ('marke_logo_pfad')");
    foreach ($st->fetchAll() as $r) {
        $werte[(string)$r['schluessel']] = (string)$r['wert'];
    }
    // Logo-Vorhandensein separat (Pfad selbst wird NIE ausgeliefert).
    $pfad = (string)($pdo->query(
        "SELECT wert FROM einstellungen WHERE schluessel = 'marke_logo_pfad'")
        ->fetchColumn() ?: '');
    $werte['hat_logo'] = ($pfad !== '' && is_file($pfad));
    // Version = Änderungszeit der Logodatei. Als stabiler URL-Parameter genutzt:
    // Die Logo-URL ändert sich NUR, wenn wirklich ein neues Logo hochgeladen
    // wurde – sonst bleibt sie gleich und der Browser nutzt seinen Cache.
    $werte['logo_version'] = $werte['hat_logo'] ? (string)(filemtime($pfad) ?: 0) : '0';
    unset($werte['marke_logo_mime']);   // im Frontend nicht gebraucht
    return $werte;
}

/** Schreibt einen Schlüssel (MariaDB-Upsert). */
function marke_schreiben(PDO $pdo, string $schluessel, string $wert): void
{
    $pdo->prepare(
        'INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE wert = VALUES(wert)')
        ->execute([$schluessel, $wert]);
}

/** Liest einen einzelnen Branding-Wert mit neutralem Fallback. */
function marke_wert(PDO $pdo, string $schluessel, string $fallback = ''): string
{
    $st = $pdo->prepare('SELECT wert FROM einstellungen WHERE schluessel = ?');
    $st->execute([$schluessel]);
    $w = $st->fetchColumn();
    return ($w !== false && (string)$w !== '') ? (string)$w : $fallback;
}

/** Schulname aus dem Branding (für E-Mail-Signaturen o. Ä.). */
function marke_schulname(PDO $pdo): string
{
    return marke_wert($pdo, 'marke_schulname', 'Ihre Schule');
}

/** #RRGGBB? */
function marke_ist_farbe(string $f): bool
{
    return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $f);
}

/** Kürzt und säubert einen Text (Steuerzeichen raus, Länge begrenzt). */
function marke_text(string $t, int $max): string
{
    $t = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $t) ?? '');
    return kuerze($t, $max);
}

/**
 * Prüft ein SVG auf offensichtlich gefährliche Inhalte. SVG kann
 * Skripte und externe Referenzen tragen – solche Dateien lehnen wir ab.
 */
function marke_svg_sicher(string $inhalt): bool
{
    $muster = [
        '/<script/i', '/javascript:/i', '/on\w+\s*=/i', '/<iframe/i',
        '/<object/i', '/<embed/i', '/xlink:href\s*=\s*["\']\s*javascript/i',
        '/data:text\/html/i', '/<\?php/i',
    ];
    foreach ($muster as $m) {
        if (preg_match($m, $inhalt)) return false;
    }
    return true;
}

/** Verzeichnis für Logos (außerhalb der statisch ausgelieferten Pfade). */
function marke_logo_dir(): string
{
    // backend/data/logos – router.php liefert nur die Whitelist statisch aus,
    // data/ ist also nicht direkt erreichbar.
    return dirname(__DIR__) . '/data/logos';
}

/**
 * Verarbeitet den Branding-Bereich. Gibt true zurück, wenn eine Route
 * bedient wurde (dann wurde bereits mit json_* geantwortet und beendet).
 */
function marke_route(array $seg, string $methode, array $body, array $cfg): bool
{
    if (($seg[0] ?? '') !== 'einstellungen') return false;

    // ---- GET /api/einstellungen/logo : Logo ausliefern (öffentlich) ----
    if ($methode === 'GET' && ($seg[1] ?? '') === 'logo') {
        $pdo = db($cfg);
        $pfad = (string)($pdo->query(
            "SELECT wert FROM einstellungen WHERE schluessel = 'marke_logo_pfad'")
            ->fetchColumn() ?: '');
        $mime = (string)($pdo->query(
            "SELECT wert FROM einstellungen WHERE schluessel = 'marke_logo_mime'")
            ->fetchColumn() ?: '');
        if ($pfad === '' || !is_file($pfad)) {
            http_response_code(404);
            exit;
        }
        // Starkes Caching: Ein Schul-Logo ändert sich praktisch nie. Wir geben
        // einen langen Cache und einen ETag/Last-Modified mit, damit der Browser
        // ein unverändertes Logo nur einmal lädt und danach 304 „not modified"
        // bekommt (kein erneuter Byte-Transfer, keine DB-/Datei-Last).
        $mtime = filemtime($pfad) ?: time();
        $etag = '"' . md5($pfad . '|' . $mtime) . '"';
        header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('ETag: ' . $etag);
        // Wenn der Browser dieselbe Version schon hat -> 304 ohne Body.
        $inm = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        $ims = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
        if ($inm === $etag || ($ims !== false && $ims >= $mtime)) {
            http_response_code(304);
            exit;
        }
        readfile($pfad);
        exit;
    }

    // ---- GET /api/einstellungen : Marke lesen (öffentlich) ----
    if ($methode === 'GET' && !isset($seg[1])) {
        json_ok(marke_lesen(db($cfg)));
    }

    // ---- GET/POST /api/einstellungen/text/{schluessel} : editierbare Texte ----
    // Deckt Hilfe-Zusatz, Buchungs- und Login-Hinweis ab. Nur Schlüssel aus der
    // Whitelist sind erlaubt. GET ist öffentlich (liefert sicher gerendertes
    // HTML, für Admins zusätzlich den Rohtext); POST ist admin-only.
    if (($seg[1] ?? '') === 'text') {
        $erlaubt = ['hilfe_zusatz', 'buchung_hinweis', 'login_hinweis'];
        $schluessel = (string)($seg[2] ?? '');
        if (!in_array($schluessel, $erlaubt, true)) {
            json_err('Unbekannter Textbereich.', 404);
        }
        $pdo = db($cfg);
        $werte = [
            'kontakt'   => marke_wert($pdo, 'marke_kontakt', ''),
            'schulname' => marke_schulname($pdo),
            'titel'     => marke_wert($pdo, 'marke_titel', 'Sprechtag'),
        ];

        if ($methode === 'GET') {
            $roh = marke_wert($pdo, $schluessel, '');
            $html = $roh === '' ? '' : markdown_sicher(platzhalter_ersetzen($roh, $werte));
            $antwort = ['html' => $html];
            if (auth_ist_admin()) $antwort['roh'] = $roh;
            json_ok($antwort);
        }
        if ($methode === 'POST') {
            auth_require_admin();
            $roh = (string)($body['text'] ?? '');
            $roh = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $roh) ?? '';
            $roh = kuerze($roh, 8000);
            marke_schreiben($pdo, $schluessel, $roh);
            $html = $roh === '' ? '' : markdown_sicher(platzhalter_ersetzen($roh, $werte));
            json_ok(['ok' => true, 'html' => $html]);
        }
        json_err('Methode nicht unterstützt.', 405);
    }

    // ---- POST /api/einstellungen : Marke speichern (admin) ----
    if ($methode === 'POST' && !isset($seg[1])) {
        auth_require_admin();
        $pdo = db($cfg);

        $regeln = [
            'marke_schulname'  => ['text', 80],
            'marke_titel'      => ['text', 40],
            'marke_untertitel' => ['text', 120],
            'marke_fusszeile'  => ['text', 200],
            'marke_kontakt'    => ['text', 160],
            'marke_farbe'      => ['farbe', 0],
            'marke_farbe2'     => ['farbe', 0],
        ];
        $gespeichert = [];
        foreach ($regeln as $key => [$typ, $max]) {
            if (!array_key_exists($key, $body)) continue;
            $wert = (string)$body[$key];
            if ($typ === 'farbe') {
                if (!marke_ist_farbe($wert)) {
                    json_err("Ungültige Farbe bei $key (erwartet #RRGGBB).");
                }
            } else {
                $wert = marke_text($wert, $max);
            }
            marke_schreiben($pdo, $key, $wert);
            $gespeichert[$key] = $wert;
        }
        json_ok(['ok' => true, 'gespeichert' => $gespeichert]);
    }

    // ---- POST /api/einstellungen/logo : Logo hochladen (admin) ----
    if ($methode === 'POST' && ($seg[1] ?? '') === 'logo') {
        auth_require_admin();
        $pdo = db($cfg);

        // Base64 statt Multipart – umgeht das 63-KB-Proxylimit.
        $base64 = (string)($body['daten'] ?? '');
        if ($base64 === '') json_err('Keine Bilddaten übermittelt.');
        $binaer = base64_decode($base64, true);
        if ($binaer === false) json_err('Ungültige Base64-Daten.');

        if (strlen($binaer) > 500 * 1024) {
            json_err('Das Logo darf maximal 500 KB groß sein.');
        }

        // MIME per finfo bestimmen – dem Client nicht vertrauen.
        $tmp = tempnam(sys_get_temp_dir(), 'logo_');
        file_put_contents($tmp, $binaer);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        $erlaubt = ['image/png' => 'png', 'image/jpeg' => 'jpg',
                    'image/svg+xml' => 'svg'];
        if (!isset($erlaubt[$mime])) {
            @unlink($tmp);
            json_err('Nur PNG, JPG und SVG sind erlaubt (erkannt: '
                . htmlspecialchars($mime) . ').');
        }
        if ($mime === 'image/svg+xml' && !marke_svg_sicher($binaer)) {
            @unlink($tmp);
            json_err('Die SVG-Datei enthält potenziell gefährliche Inhalte '
                . 'und wurde abgelehnt.');
        }

        $dir = marke_logo_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            @unlink($tmp);
            json_err('Logo-Verzeichnis konnte nicht angelegt werden.', 500);
        }

        // Altes Logo entfernen.
        $alt = (string)($pdo->query(
            "SELECT wert FROM einstellungen WHERE schluessel = 'marke_logo_pfad'")
            ->fetchColumn() ?: '');
        if ($alt !== '' && is_file($alt)) @unlink($alt);

        $ziel = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $erlaubt[$mime];
        if (!@rename($tmp, $ziel)) {
            @unlink($tmp);
            json_err('Logo konnte nicht gespeichert werden.', 500);
        }
        @chmod($ziel, 0640);

        marke_schreiben($pdo, 'marke_logo_pfad', $ziel);
        marke_schreiben($pdo, 'marke_logo_mime', $mime);
        json_ok(['ok' => true, 'mime' => $mime]);
    }

    // ---- DELETE /api/einstellungen/logo : Logo entfernen (admin) ----
    if ($methode === 'DELETE' && ($seg[1] ?? '') === 'logo') {
        auth_require_admin();
        $pdo = db($cfg);
        $alt = (string)($pdo->query(
            "SELECT wert FROM einstellungen WHERE schluessel = 'marke_logo_pfad'")
            ->fetchColumn() ?: '');
        if ($alt !== '' && is_file($alt)) @unlink($alt);
        marke_schreiben($pdo, 'marke_logo_pfad', '');
        marke_schreiben($pdo, 'marke_logo_mime', '');
        json_ok(['ok' => true]);
    }

    // ---- POST /api/einstellungen/zuruecksetzen : Standard (admin) ----
    if ($methode === 'POST' && ($seg[1] ?? '') === 'zuruecksetzen') {
        auth_require_admin();
        $pdo = db($cfg);
        foreach (marke_standard() as $k => $v) marke_schreiben($pdo, $k, $v);
        $alt = (string)($pdo->query(
            "SELECT wert FROM einstellungen WHERE schluessel = 'marke_logo_pfad'")
            ->fetchColumn() ?: '');
        if ($alt !== '' && is_file($alt)) @unlink($alt);
        marke_schreiben($pdo, 'marke_logo_pfad', '');
        marke_schreiben($pdo, 'marke_logo_mime', '');
        json_ok(['ok' => true, 'marke' => marke_lesen($pdo)]);
    }

    json_err('Methode für /api/einstellungen nicht unterstützt.', 405);
}
