<?php
// ============================================================
// anmeldefehler.php – was die Anmeldung sagt, wenn sie scheitert
// (v0.9.109, Review koordination a700ce2, Befund 7)
//
// Bis v0.9.108 gab die Anmeldung den Text der Ausnahme weiter: Bei der
// Abnahme vom 14.10.2026 stand „WebUntis-Fehler -8504: bad credentials“ bei
// der Nutzerin, beim gesperrten Kontotyp ein eigener Satz. FALLSTRICKE
// Abschnitt 8: Wo ein Passwort über den Zugang entschied, ist die Ablehnung
// einheitlich – gleicher Wortlaut, gleicher Status –, der Grund gehört ins
// Protokoll (login_log, über bremse_fehlschlag).
//
// Ausgenommen ist nur der Fall, in dem WebUntis bei authenticate gar nicht
// antwortete: Dort hat kein Passwort entschieden, und „Passwort prüfen“
// schickte alle an einem Ausfallabend auf die falsche Spur.
// ============================================================

declare(strict_types=1);

/** Der eine Satz für jede Ablehnung, bei der ein Passwort im Spiel war. */
const ANMELDUNG_ABGELEHNT = 'Anmeldung fehlgeschlagen. Bitte Benutzername und Passwort prüfen.';

/** authenticate hat WebUntis nicht erreicht – kein Passwort hat entschieden. */
final class WuNichtErreichbar extends RuntimeException {}

/**
 * Ordnet eine Ausnahme aus WebUntisAuth::authenticate ein. Die vendorte Datei
 * unterscheidet nur am Text (tests/run_anmeldebremse.php hält die Texte
 * fest): Kein Netz und keine JSON-Antwort werden WuNichtErreichbar, alles
 * andere – auch eine unbekannte Meldung – bleibt, wie es ist, und bekommt
 * damit den einen Satz.
 */
function wu_anmeldefehler(RuntimeException $e): RuntimeException
{
    $m = $e->getMessage();
    if (str_starts_with($m, 'WebUntis nicht erreichbar: ')
        || $m === 'Unerwartete WebUntis-Antwort (kein JSON)') {
        return new WuNichtErreichbar($m, 0, $e);
    }
    return $e;
}
