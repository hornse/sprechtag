<?php
// ============================================================
// tests/run_sitzungszugang.php – der Zugang zu WebUntis über die Sitzung
// der angemeldeten Person (v0.9.72, E17)
// Aufruf: php tests/run_sitzungszugang.php   → Exit-Code 0 = alles grün
//
// Seit v0.9.72 gibt es kein Dienstkonto mehr; jede WebUntis-Aktion läuft
// über wu_sitzung(). Geprüft wird:
//   * die drei Ursachen – abgelaufen / nicht erreichbar / kaputt – melden
//     sich verschieden (Punkt 3 des Auftrags, E9). Netz weg sah bis v0.9.71
//     aus wie abgelaufen, weil tokenHolen() nur ja/nein sagt;
//   * die Grenze 499/500 der Nachprobe;
//   * Engstelle: Kein Dienstkonto mehr im Code, kein zweiter Sitzungszugang,
//     ein Token-Abruf nur noch im Adapter (und in der Sondierung, die mit
//     eingetippten Zugangsdaten arbeitet).
//
// Antworten ERFUNDEN in der Form, die messung_token_probe() seit v0.9.54
// liest (Status 0 = Netz, 200 mit JWT, 200 mit Anmeldeseite).
// ============================================================

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/api/auth.php';
require __DIR__ . '/../backend/api/webuntis_adapter.php';

$fehlt = array_filter(['wu_sitzung', 'wu_sitzung_meldung'], fn($f) => !function_exists($f));
pruefe('Voraussetzung: Funktionen vorhanden', $fehlt === []);
if ($fehlt !== []) { echo '    (fehlen: ' . implode(', ', $fehlt) . ")\n\n$fehler ROT\n"; exit(1); }

/** Echter WebUntisRest, nur die Netzaufrufe ersetzt. */
final class SitzungsRest extends WebUntisRest
{
    public int $tenant = 0;
    public array $abrufe = [];
    public function __construct(private mixed $token, private array $probe = [])
    { parent::__construct('http://127.0.0.1:9', 'x'); }
    public function tokenHolen(): bool
    {
        if ($this->token instanceof Throwable) throw $this->token;
        return (bool)$this->token;
    }
    public function tenantErmitteln(): void { $this->tenant++; }
    public function get(string $pfad, array $query = []): array
    { $this->abrufe[] = $pfad; return $this->probe; }
}
$cfg = ['webuntis' => ['base_url' => 'http://127.0.0.1:9', 'school' => 'x']];
$gebaut = 0;
$mit = function (SitzungsRest $r) use (&$gebaut): callable {
    return function () use ($r, &$gebaut) { $gebaut++; return $r; };
};
$jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ4In0.c2lnbmF0dXI';

// ------------------------------------------------------------
echo "Nutzbar\n";
$_SESSION = ['wu_cookie' => 'JSESSIONID=erfunden'];
$r = new SitzungsRest(true);
$s = wu_sitzung($cfg, $mit($r));
pruefe('Token da: Client zurück, art null, grund null, Tenant ermittelt',
    $s['rest'] === $r && $s['art'] === null && $s['grund'] === null && $r->tenant === 1);
pruefe('… ohne Nachprobe', $r->abrufe === []);

// ------------------------------------------------------------
echo "Abgelaufen – neu anmelden hilft\n";
$_SESSION = [];
$gebaut = 0;
$s = wu_sitzung($cfg, $mit(new SitzungsRest(true)));
pruefe('kein Cookie: abgelaufen, grund kein_cookie, kein Client gebaut',
    $s['rest'] === null && $s['art'] === 'abgelaufen' && $s['grund'] === 'kein_cookie' && $gebaut === 0);
$_SESSION = ['wu_cookie' => 'JSESSIONID=erfunden'];
$r = new SitzungsRest(false, ['status' => 200, 'text' => '<html>Anmeldung</html>']);
$s = wu_sitzung($cfg, $mit($r));
pruefe('kein Token, Nachprobe zeigt die Anmeldeseite: abgelaufen, grund kein_token',
    $s['rest'] === null && $s['art'] === 'abgelaufen' && $s['grund'] === 'kein_token'
    && $r->abrufe === ['/WebUntis/api/token/new']);
$s = wu_sitzung($cfg, $mit(new SitzungsRest(false, ['status' => 401, 'text' => ''])));
pruefe('kein Token, Nachprobe 401: abgelaufen', $s['art'] === 'abgelaufen');
$s = wu_sitzung($cfg, $mit(new SitzungsRest(false, ['status' => 499, 'text' => ''])));
pruefe('Grenze: Status 499 ist noch abgelaufen', $s['art'] === 'abgelaufen');

// ------------------------------------------------------------
echo "Nicht erreichbar – neu anmelden hilft NICHT\n";
$s = wu_sitzung($cfg, $mit(new SitzungsRest(false, ['status' => 0, 'text' => 'cURL: x'])));
pruefe('kein Token, Nachprobe Status 0: nicht_erreichbar (bis v0.9.71: kein_token)',
    $s['rest'] === null && $s['art'] === 'nicht_erreichbar' && str_contains((string)$s['grund'], 'Status 0'));
$s = wu_sitzung($cfg, $mit(new SitzungsRest(false, ['status' => 500, 'text' => ''])));
pruefe('Grenze: Status 500 ist nicht_erreichbar', $s['art'] === 'nicht_erreichbar');
$s = wu_sitzung($cfg, $mit(new SitzungsRest(false, ['status' => 200, 'text' => $jwt])));
pruefe('kein Token, bei der Nachprobe schon: flüchtig, nicht_erreichbar (kein Ablauf)',
    $s['art'] === 'nicht_erreichbar' && str_contains((string)$s['grund'], 'flüchtig'));
$s = wu_sitzung($cfg, $mit(new SitzungsRest(new RuntimeException('Verbindung abgebrochen'))));
pruefe('Ausnahme (Exception) im Abruf: nicht_erreichbar, Grund nennt Klasse und Meldung',
    $s['art'] === 'nicht_erreichbar' && $s['grund'] === 'fehler: RuntimeException: Verbindung abgebrochen');
$s = wu_sitzung($cfg);
pruefe('echter Client, geschlossener Port: nicht_erreichbar (Status 0)',
    $s['rest'] === null && $s['art'] === 'nicht_erreichbar' && str_contains((string)$s['grund'], 'Status 0'));

// ------------------------------------------------------------
echo "Kaputt – ein Programmierfehler sieht nicht mehr aus wie ein Ablauf (E9)\n";
$s = wu_sitzung(['webuntis' => ['base_url' => null, 'school' => null]]);
pruefe('TypeError im Konstruktor: kaputt, Grund nennt die Klasse',
    $s['rest'] === null && $s['art'] === 'kaputt' && str_starts_with((string)$s['grund'], 'fehler: TypeError: '));
$s = wu_sitzung($cfg, $mit(new SitzungsRest(new Error('erfunden'))));
pruefe('Error im Abruf: kaputt', $s['art'] === 'kaputt');
$_SESSION = [];

// ------------------------------------------------------------
echo "Die Meldungen – drei Ursachen, drei Sätze\n";
$m = [wu_sitzung_meldung('abgelaufen'), wu_sitzung_meldung('nicht_erreichbar'), wu_sitzung_meldung('kaputt')];
pruefe('drei verschiedene Sätze', count(array_unique($m)) === 3);
pruefe('abgelaufen sagt KLAR: neu anmelden', str_contains($m[0], 'abgelaufen') && str_contains($m[0], 'neu an'));
pruefe('nicht erreichbar sagt NICHT „neu anmelden“', str_contains($m[1], 'nicht erreichbar') && !str_contains($m[1], 'neu an'));
pruefe('kaputt verweist an die Administration', str_contains($m[2], 'Administration') && !str_contains($m[2], 'neu an'));

// ------------------------------------------------------------
echo "Engstelle: ein Zugang, kein Dienstkonto\n";
/** Nur Code – ohne Kommentare und Zeichenketten (REIHENREGELN 2). */
function nur_code(string $datei): string
{
    $aus = '';
    foreach (token_get_all((string)file_get_contents($datei)) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING,
                T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) continue;
        $aus .= is_array($t) ? $t[1] : $t;
    }
    return $aus;
}
// Gegenprobe der Suche selbst (REIHENREGELN 2): Kommentar und Zeichenkette
// zählen nicht, ein echter Aufruf schon.
$probe = tempnam(sys_get_temp_dir(), 'sz');
file_put_contents($probe, "<?php\n// dk_lesen(\$c)\n/* \$r->tokenHolen() */\n\$t = 'dk_lesen(';\n");
$leer = nur_code($probe);
file_put_contents($probe, "<?php\n\$z = dk_lesen(\$c, \$p);\n\$r->tokenHolen();\n");
$voll = nur_code($probe);
unlink($probe);
pruefe('Gegenprobe: Kommentar und Zeichenkette zählen nicht als Aufruf',
    !preg_match('/\bdk_[a-z]+\s*\(/', $leer) && !str_contains($leer, '->tokenHolen('));
pruefe('Gegenprobe: ein echter Aufruf zählt',
    preg_match('/\bdk_[a-z]+\s*\(/', $voll) === 1 && str_contains($voll, '->tokenHolen('));
$dateien = array_merge(glob(__DIR__ . '/../backend/api/*.php') ?: [], glob(__DIR__ . '/../backend/*.php') ?: []);
pruefe('Voraussetzung: Anwendungsdateien gefunden (mindestens 10)', count($dateien) >= 10);
$tokenStellen = []; $alt = []; $dk = [];
foreach ($dateien as $d) {
    $c = nur_code($d);
    if (str_contains($c, '->tokenHolen(')) $tokenStellen[] = basename($d);
    if (str_contains($c, 'mit_rest_aus_sitzung')) $alt[] = basename($d);
    if (preg_match('/\bdk_[a-z]+\s*\(/', $c)) $dk[] = basename($d);
}
sort($tokenStellen);
pruefe('Gegenprobe der Suche: im Adapter wird ein Token geholt', in_array('webuntis_adapter.php', $tokenStellen, true));
pruefe('Token-Abruf nur im Adapter und in der Sondierung (eingetippte Zugangsdaten)',
    $tokenStellen === ['sondierung.php', 'webuntis_adapter.php']);
pruefe('kein zweiter Sitzungszugang: mit_rest_aus_sitzung ist fort', $alt === []);
pruefe('kein Aufruf einer dk_-Funktion mehr', $dk === []);
pruefe('dienstkonto.php gibt es nicht mehr', !file_exists(__DIR__ . '/../backend/api/dienstkonto.php'));
pruefe('keine Route /api/dienstkonto mehr', !str_contains((string)file_get_contents(__DIR__ . '/../backend/api/index.php'), "=== 'dienstkonto'"));
$beispiel = (string)file_get_contents(__DIR__ . '/../backend/config.example.php');
pruefe('kein Schlüssel für das Dienstkonto in der Konfigurationsvorlage', !str_contains($beispiel, 'dienstkonto_schluessel'));

echo "\n" . ($fehler === 0 ? "ALLE TESTS GRÜN\n" : "$fehler ROT\n");
exit($fehler === 0 ? 0 : 1);
