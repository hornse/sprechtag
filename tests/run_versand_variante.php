<?php
// ============================================================
// tests/run_versand_variante.php – das Lesen der gemerkten Versandvariante
// in mit_versand_ausfuehren(), AUSGEFÜHRT
// Aufruf: php tests/run_versand_variante.php   → Exit-Code 0 = alles grün
//
// Anlass (Aufräumliste 9): Das Lesen stand in
// `catch (Throwable $e) { /* Tabelle ggf. noch leer */ }`. Jeder Fehler –
// auch ein Programmierfehler – wurde zu „keine Variante gemerkt“, und der
// Versand fiel still auf die Reihenfolge aller Varianten zurück
// (FALLSTRICKE 3: ein catch mit Rückfall meldet Erfolg).
//
// Geprüft wird an der echten Funktion: Die Datenbank ist SQLite im Speicher,
// das Netz eine Unterklasse von WebUntisRest, die jede Variante mit 400
// ablehnt – dann probiert mit_senden() alle, und die Reihenfolge der
// Versuche im Protokoll zeigt, ob die gemerkte Variante gelesen wurde.
// Alle Versuche scheitern mit Absicht: Das Merken nach einem Erfolg
// schreibt mit MariaDB-Syntax, die SQLite nicht kennt.
//
// Ein Programmierfehler beim Lesen wird über eine PDO-Unterklasse
// erzeugt, deren query() für genau diese Abfrage einen Error wirft.
//
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================
declare(strict_types=1);

require __DIR__ . '/../backend/helfer.php';
require __DIR__ . '/../backend/api/mitteilungen.php';

$f = 0;
function pruefe(string $n, bool $ok): void
{
    global $f;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n";
    if (!$ok) $f++;
}

/** Lehnt jede Variante mit 400 ab: nicht endgültig, also wird weiterprobiert. */
final class AblehnendesRest extends WebUntisRest
{
    public function __construct()
    {
        parent::__construct('https://attrappe.invalid', 'attrappe');
    }
    public function post(string $pfad, array $daten): array
    {
        return ['status' => 400, 'contentType' => '', 'text' => '', 'json' => null];
    }
    public function postMultipart(string $pfad, array $daten, string $feldname = 'request'): array
    {
        return ['status' => 400, 'contentType' => '', 'text' => '', 'json' => null];
    }
}

/** Wirft beim Lesen der gemerkten Variante einen Error; sonst normales PDO. */
final class KaputtesLesenPdo extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_contains($query, 'mitteilung_variante')) {
            throw new Error('Programmierfehler-Attrappe');
        }
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

/** Datenbank mit einer offenen Mitteilung an ein Konto; Tabelle einstellungen nach Wunsch. */
function db(string $klasse = PDO::class, bool $mitEinstellungen = true, ?string $gemerkt = null): PDO
{
    $pdo = new $klasse('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE mitteilungen (id INTEGER PRIMARY KEY AUTOINCREMENT,
      sprechtag_id INT, empfaenger_user_id INT, empfaenger_art TEXT DEFAULT "konto",
      schueler_id INT, lehrer_id INT, anlass TEXT, betreff TEXT, text TEXT,
      status TEXT DEFAULT "offen", grund TEXT DEFAULT "", versuche INT DEFAULT 0,
      angelegt_am TEXT DEFAULT CURRENT_TIMESTAMP, gesendet_am TEXT,
      kind_name TEXT NOT NULL DEFAULT "", kind_klasse TEXT NOT NULL DEFAULT "")');
    $pdo->exec("INSERT INTO mitteilungen (sprechtag_id, empfaenger_user_id, anlass, betreff, text)
                VALUES (1, 9001, 'hinweis', 'B', 'T')");
    if ($mitEinstellungen) {
        $pdo->exec('CREATE TABLE einstellungen (schluessel TEXT PRIMARY KEY, wert TEXT)');
        if ($gemerkt !== null) {
            $pdo->prepare("INSERT INTO einstellungen VALUES ('mitteilung_variante', ?)")
                ->execute([$gemerkt]);
        }
    }
    return $pdo;
}

/** Ein Lauf der echten Funktion. Liefert Ergebnis oder das, was herausflog. */
function lauf(PDO $pdo): array
{
    try {
        return ['e' => mit_versand_ausfuehren($pdo, [1], new AblehnendesRest()), 'geworfen' => null];
    } catch (Throwable $t) {
        return ['e' => null, 'geworfen' => $t];
    }
}

/** Reihenfolge der probierten Varianten aus dem Protokoll. */
function reihenfolge(?array $e): array
{
    return array_column($e['protokoll'][0]['versuche'] ?? [], 'variante');
}

$alle = array_keys(mit_varianten());
$gemerkt = $alle[3];   // nicht die erste – sonst sähe „gelesen“ aus wie „Rückfall“

// ------------------------------------------------------------
echo "Normalfall: gemerkte Variante wird gelesen und zuerst probiert\n";

$r = lauf(db(gemerkt: $gemerkt));
pruefe('Voraussetzung: Lauf ohne Ausnahme, eine Mitteilung probiert',
    $r['geworfen'] === null && count($r['e']['protokoll'] ?? []) === 1);
pruefe('Voraussetzung: alle Varianten probiert',
    count(reihenfolge($r['e'])) === count($alle));
pruefe('gemerkte Variante zuerst', (reihenfolge($r['e'])[0] ?? null) === $gemerkt);
pruefe('kein Lesefehler gemeldet',
    array_key_exists('variante_lesefehler', $r['e'] ?? []) && $r['e']['variante_lesefehler'] === null);

$r = lauf(db());
pruefe('nichts gemerkt (Tabelle leer): Reihenfolge aller Varianten, ohne Lesefehler',
    reihenfolge($r['e']) === $alle
    && array_key_exists('variante_lesefehler', $r['e'] ?? []) && $r['e']['variante_lesefehler'] === null);

// ------------------------------------------------------------
echo "Betriebsfehler beim Lesen: Rückfall, aber sichtbar\n";

$r = lauf(db(mitEinstellungen: false));
pruefe('fehlende Tabelle bricht den Versand nicht ab', $r['geworfen'] === null);
if ($r['geworfen'] !== null) echo '    (geworfen: ' . get_class($r['geworfen']) . ")\n";
pruefe('Rückfall: alle Varianten in ihrer Reihenfolge', reihenfolge($r['e']) === $alle);
$lf = $r['e']['variante_lesefehler'] ?? null;
pruefe('Lesefehler steht im Ergebnis, mit der Meldung der Datenbank',
    is_string($lf) && str_contains($lf, 'einstellungen'));
pruefe('Lesefehler steht im Grund, nicht nur in einem Feld',
    str_contains((string)($r['e']['grund'] ?? ''), 'gemerkte Versandvariante'));

// ------------------------------------------------------------
echo "Programmierfehler beim Lesen: nicht gefangen\n";

$r = lauf(db(KaputtesLesenPdo::class, gemerkt: $gemerkt));
pruefe('Error beim Lesen fliegt heraus',
    $r['geworfen'] instanceof Error && $r['geworfen']->getMessage() === 'Programmierfehler-Attrappe');

// ------------------------------------------------------------
echo "Leere Liste: dieselbe Form\n";

$e = mit_versand_ausfuehren(db(), [], new AblehnendesRest());
pruefe('auch ohne Versand ist variante_lesefehler da (null)',
    array_key_exists('variante_lesefehler', $e) && $e['variante_lesefehler'] === null);

echo "\n" . ($f === 0 ? "ALLES GRÜN\n" : "$f ROT\n");
exit($f === 0 ? 0 : 1);
