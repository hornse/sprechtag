<?php
// ============================================================
// herkunft.php – kommt ein verändernder Aufruf aus der eigenen Oberfläche?
// (v0.9.105, E33; Review koordination 14.10.2026, Befund 1)
//
// KEIN TOKEN-SYSTEM. Geprüft wird eine FESTE Kopfzeile,
// X-Angefordert-Von: sprechtag – in jeder Sitzung und für jeden Benutzer
// derselbe Wert, und er steht offen in app.js. Entschieden für die Reihe in
// koordination M22; Vorbild kurse/backend/api/herkunft.php.
//
// WARUM DAS GENÜGT:
//   * Ein Formular auf einer fremden Seite kann keine eigene Kopfzeile
//     setzen. Es kann POSTen – aber ohne X-Angefordert-Von.
//   * Ein fetch() von fremder Herkunft KANN sie setzen, löst damit aber
//     einen Preflight aus. Den besteht er nur, wenn der Server
//     Access-Control-Allow-Origin für diese Herkunft ausliefert. Das tut
//     sprechtag nirgends (gemessen am Server nach der Auslieferung,
//     CHANGELOG v0.9.105).
//   * Der Sitzungs-Cookie steht zusätzlich auf SameSite=Lax (v0.9.101).
//
// ZWEITE STUFE: Ein nicht leerer Körper muss als application/json kommen.
// Ein fremdes Formular baut JSON mit enctype="text/plain" nach (Review); das
// lehnt spt_koerper_pruefen ab, auch wenn die erste Stufe einmal fehlen
// sollte. Die beiden Stufen melden sich verschieden (403 / 415).
//
// WANN ES NICHT MEHR GENÜGT (M22): wenn CORS gelockert wird oder die
// Anwendung auf eine Domain zieht, die sie mit anderen teilt. Dann ist ein
// Token je Sitzung fällig.
//
// NUR GET KOMMT OHNE DIE KOPFZEILE DURCH (das Logo lädt als <img>, der
// Kalender als Abo). Daraus folgt: Unter GET darf kein Zweig etwas ändern.
// ============================================================

declare(strict_types=1);

/** Name, $_SERVER-Schlüssel und Wert der Kopfzeile – an einer Stelle. */
function spt_herkunft_kopfzeile(): array
{
    return ['name' => 'X-Angefordert-Von', 'server' => 'HTTP_X_ANGEFORDERT_VON', 'wert' => 'sprechtag'];
}

/**
 * Darf dieser Aufruf weiter? Rückgabe ['ok' => bool, 'grund' => string];
 * der Grund ist fürs PROTOKOLL, nicht für die Antwort.
 *
 * Positivliste: Nur GET ist ausgenommen – auch HEAD, eine klein geschriebene
 * oder leere Methode braucht die Kopfzeile. Der Vergleich ist exakt.
 */
function spt_herkunft_pruefen(string $methode, ?string $wert): array
{
    if ($methode === 'GET') return ['ok' => true, 'grund' => ''];
    $k = spt_herkunft_kopfzeile();
    $m = $methode === '' ? '(leer)' : $methode;
    if ($wert === null || $wert === '') {
        return ['ok' => false, 'grund' => 'Kopfzeile ' . $k['name'] . ' fehlt (Methode ' . $m . ')'];
    }
    if ($wert !== $k['wert']) {
        // Der fremde Wert geht nicht ins Protokoll, nur seine Länge.
        return ['ok' => false, 'grund' => 'Kopfzeile ' . $k['name'] . ' hat einen falschen Wert ('
            . strlen($wert) . ' Zeichen, Methode ' . $m . ')'];
    }
    return ['ok' => true, 'grund' => ''];
}

/**
 * Zweite Stufe: Ein nicht leerer Körper muss application/json sein.
 * Rückgabe: null = in Ordnung, sonst der Grund (geht in die Antwort, 415).
 */
function spt_koerper_pruefen(?string $inhaltstyp, string $roh): ?string
{
    if ($roh === '') return null;
    $typ = strtolower(trim(explode(';', (string)$inhaltstyp)[0]));
    if ($typ !== 'application/json') {
        return 'Der Inhalt muss als JSON kommen (Content-Type: application/json).';
    }
    return null;
}

/** Liest den JSON-Körper, nach der zweiten Stufe. */
function spt_body_json(string $roh): array
{
    $grund = spt_koerper_pruefen(isset($_SERVER['CONTENT_TYPE']) ? (string)$_SERVER['CONTENT_TYPE'] : null, $roh);
    if ($grund !== null) json_err($grund, 415);
    $j = json_decode($roh, true);
    return is_array($j) ? $j : [];
}
