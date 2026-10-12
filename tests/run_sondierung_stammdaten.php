<?php
// ============================================================
// tests/run_sondierung_stammdaten.php – die Stammdaten-Sondierung,
// AUSGEFÜHRT statt nachgebildet
// Aufruf: php tests/run_sondierung_stammdaten.php   → Exit-Code 0 = alles grün
//
// Anlass (v0.9.95): Das WebUntis-Modul v2.0.0 ändert zwei Dinge, auf die
// sondierung_stammdaten() baut. getCurrentSchoolyear() meldet bei einem
// Netzfehler nicht mehr „kein Schuljahr“, sondern wirft; getStudents() gibt
// es nicht mehr (personenbezogen, REIHENREGELN 9). Die beiden Abschnitte mit
// getStudents entfallen deshalb, und zwei catch fangen nur noch
// Betriebsfehler (RuntimeException), nicht jeden Throwable (FALLSTRICKE 3).
//
// WIE GEPRÜFT WIRD: Ausgeführt wird die ECHTE sondierung_stammdaten() aus
// backend/api/sondierung.php. Ersetzt ist nur die Außenwelt – die beiden
// Client-Klassen, als Unterklassen, die nichts ans Netz schicken. Jede
// Attrappe tut, was der einzelne Fall ihr vorgibt, und schreibt mit, was
// gerufen wurde. Bis v0.9.94 prüfte tests/run.php diesen Bereich an einer
// NACHGEBILDETEN Kopie des Codes (der Kommentar dort sagte es); eine solche
// Prüfung bleibt grün, gleich was der echte Code tut
// (docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md). Diese Suite ist der
// Gegenentwurf.
//
// Die Attrappe für getCurrentSchoolyear() verhält sich wie v2.0.0: [] heißt
// „kein Schuljahr aktiv“, ein Netzfehler wird geworfen. Die vendorte Kopie in
// backend/auth/ fängt bis zur Verteilung noch jeden Fehler selbst; geprüft
// wird hier, wie die Sondierung mit der neuen Form umgeht.
//
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================
declare(strict_types=1);

require __DIR__ . '/../backend/api/sondierung.php';

$f = 0;
function pruefe(string $n, bool $ok): void
{
    global $f;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $n . "\n";
    if (!$ok) $f++;
}

// ---------- Attrappen der Außenwelt ----------

/** Protokoll aller Aufrufe der Attrappen, je Lauf neu. */
final class Protokoll
{
    public static array $aufrufe = [];
    public static function zaehle(string $was): int
    {
        return count(array_filter(self::$aufrufe, fn($a) => $a === $was));
    }
}

/**
 * Schuljahr, Klassen, Schüler – je Methode eine Vorgabe: ein Wert, der
 * zurückkommt, oder ein Throwable, das geworfen wird.
 */
final class AttrappeAuth extends WebUntisAuth
{
    public function __construct(private array $vorgabe)
    {
        parent::__construct('https://attrappe.invalid', 'attrappe', 'test');
    }

    private function antwort(string $methode, mixed $sonst): mixed
    {
        Protokoll::$aufrufe[] = $methode;
        $v = $this->vorgabe[$methode] ?? $sonst;
        if ($v instanceof Throwable) throw $v;
        return $v;
    }

    public function getSchoolyears(): array
    {
        return $this->antwort('getSchoolyears', [['id' => 11, 'name' => '2025/2026']]);
    }
    public function getCurrentSchoolyear(): array
    {
        return $this->antwort('getCurrentSchoolyear', ['id' => 11, 'name' => '2025/2026']);
    }
    public function getKlassen(?int $schoolyearId = null): array
    {
        return $this->antwort('getKlassen', []);
    }
    /**
     * Gibt es in v2.0.0 nicht mehr; hier nur, um einen Aufruf zu bemerken.
     * Dieselben Kennungen wie pageconfig unten: Dann wählte der alte Code
     * die Bewertung, die getStudents nennt – sonst wäre die Prüfung darauf
     * nur zufällig grün.
     */
    public function getStudents(): array
    {
        return $this->antwort('getStudents', [['id' => 101], ['id' => 102], ['id' => 103]]);
    }
}

/**
 * REST: je Pfad eine Vorgabe (Antwort-Array oder Throwable). Ein Throwable
 * wird nur beim ERSTEN Aufruf des Pfads geworfen – pageconfig wird später
 * in der Variantenliste noch einmal gerufen, und der Fall soll im Abschnitt
 * pageconfig entschieden werden, nicht dort.
 */
final class AttrappeRest extends WebUntisRest
{
    private array $gesehen = [];

    public function __construct(private array $vorgabe)
    {
        parent::__construct('https://attrappe.invalid', 'attrappe');
    }

    public function get(string $pfad, array $query = []): array
    {
        Protokoll::$aufrufe[] = 'GET ' . $pfad;
        $erstes = !isset($this->gesehen[$pfad]);
        $this->gesehen[$pfad] = true;
        $v = $this->vorgabe[$pfad] ?? null;
        if ($v instanceof Throwable) {
            if ($erstes) throw $v;
            $v = null;
        }
        return $v ?? ['status' => 404, 'json' => null, 'text' => ''];
    }
}

const PAGECONFIG = '/WebUntis/api/public/timetable/weekly/pageconfig';

/** Ein Lauf der echten Funktion. Liefert Bericht oder das, was herausflog. */
function lauf(array $auth = [], array $rest = []): array
{
    Protokoll::$aufrufe = [];
    try {
        $bericht = sondierung_stammdaten(new AttrappeAuth($auth), new AttrappeRest($rest), 0);
        return ['bericht' => $bericht, 'geworfen' => null];
    } catch (Throwable $t) {
        return ['bericht' => null, 'geworfen' => $t];
    }
}

// pageconfig mit drei Erfundenen: zwei mit Klasse, einer mit externKey
$pageconfig = ['status' => 200, 'text' => '', 'json' => ['data' => ['elements' => [
    ['id' => 101, 'klasseId' => 7, 'externKey' => ''],
    ['id' => 102, 'klasseId' => 7, 'externKey' => 'X1'],
    ['id' => 103, 'klasseId' => 0, 'externKey' => ''],
]]]];

// ------------------------------------------------------------
echo "Schuljahr: ein Netzfehler ist kein Ferienzustand\n";

$r = lauf(['getCurrentSchoolyear' => new RuntimeException('nicht erreichbar')],
          [PAGECONFIG => $pageconfig]);
$sj = $r['bericht']['getCurrentSchoolyear'] ?? null;
pruefe('Netzfehler (RuntimeException) bricht die Sondierung nicht ab',
    $r['geworfen'] === null);
if ($r['geworfen'] !== null) echo '    (geworfen: ' . get_class($r['geworfen']) . ")\n";
pruefe('Netzfehler steht als Fehler im Bericht, mit der Meldung',
    is_array($sj) && ($sj['fehler'] ?? null) === 'nicht erreichbar');
pruefe('Netzfehler heißt NICHT „kein aktives Schuljahr“',
    is_array($sj) && !array_key_exists('aktiv', $sj));

$r = lauf(['getCurrentSchoolyear' => []], [PAGECONFIG => $pageconfig]);
$sj = $r['bericht']['getCurrentSchoolyear'] ?? null;
pruefe('[] heißt „kein aktives Schuljahr“',
    is_array($sj) && ($sj['aktiv'] ?? null) === false);

$r = lauf([], [PAGECONFIG => $pageconfig]);
$sj = $r['bericht']['getCurrentSchoolyear'] ?? null;
pruefe('aktives Schuljahr mit Kennung',
    is_array($sj) && ($sj['aktiv'] ?? null) === true && ($sj['id'] ?? null) === 11);

$r = lauf(['getCurrentSchoolyear' => new Error('Programmierfehler-Attrappe')],
          [PAGECONFIG => $pageconfig]);
pruefe('Programmierfehler (Error) beim Schuljahr wird nicht gefangen',
    $r['geworfen'] instanceof Error
    && $r['geworfen']->getMessage() === 'Programmierfehler-Attrappe');

// ------------------------------------------------------------
echo "getStudents: nicht mehr gerufen, nicht mehr im Bericht\n";

$r = lauf([], [PAGECONFIG => $pageconfig]);
$b = $r['bericht'] ?? [];
pruefe('Voraussetzung: Lauf ohne Ausnahme, Bericht da',
    $r['geworfen'] === null && is_array($r['bericht']));
pruefe('Voraussetzung: die Attrappen wurden gerufen (Schuljahre, pageconfig)',
    Protokoll::zaehle('getSchoolyears') === 1 && Protokoll::zaehle('GET ' . PAGECONFIG) >= 1);
pruefe('getStudents wird nicht gerufen', Protokoll::zaehle('getStudents') === 0);
pruefe('kein Abschnitt „getStudents“ im Bericht', !array_key_exists('getStudents', $b));
$pc = $b['pageconfig_schueler'] ?? [];
pruefe('pageconfig ohne getStudents_anzahl und ids_in_beiden',
    is_array($pc) && !array_key_exists('getStudents_anzahl', $pc)
    && !array_key_exists('ids_in_beiden', $pc));
pruefe('Bewertung von pageconfig nennt getStudents nicht',
    is_string($pc['bewertung'] ?? null) && !str_contains($pc['bewertung'], 'getStudents'));

// ------------------------------------------------------------
echo "pageconfig: die drei Zählwerte bleiben\n";

pruefe('pageconfig: Anzahl, mit Klasse, mit externKey',
    ($pc['anzahl'] ?? null) === 3 && ($pc['mit_klasse'] ?? null) === 2
    && ($pc['mit_externkey'] ?? null) === 1 && ($pc['status'] ?? null) === 200);

$leer = ['status' => 200, 'text' => '', 'json' => ['data' => ['elements' => []]]];
$r = lauf([], [PAGECONFIG => $leer]);
pruefe('pageconfig leer: Bewertung sagt „keine Datensätze“',
    str_starts_with((string)($r['bericht']['pageconfig_schueler']['bewertung'] ?? ''),
        'Keine Datensätze'));

$r = lauf([], [PAGECONFIG => new RuntimeException('Verbindung abgebrochen')]);
pruefe('Netzfehler bei pageconfig steht als Fehler im Bericht',
    $r['geworfen'] === null
    && ($r['bericht']['pageconfig_schueler']['fehler'] ?? null) === 'Verbindung abgebrochen');

$r = lauf([], [PAGECONFIG => new Error('Programmierfehler-Attrappe')]);
pruefe('Programmierfehler (Error) bei pageconfig wird nicht gefangen',
    $r['geworfen'] instanceof Error
    && $r['geworfen']->getMessage() === 'Programmierfehler-Attrappe');

echo "\n" . ($f === 0 ? "ALLES GRÜN\n" : "$f ROT\n");
exit($f === 0 ? 0 : 1);
