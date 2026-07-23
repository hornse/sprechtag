<?php
// ============================================================
// helfer.php – kleine Hilfsfunktionen ohne Abhängigkeiten
// Wird sowohl von bootstrap.php als auch direkt von den
// Fachmodulen eingebunden (damit Tests ohne Bootstrap laufen).
// ============================================================

declare(strict_types=1);

if (!function_exists('kuerze')) {
    /**
     * Kürzt einen Text auf eine Maximallänge – auch ohne mbstring.
     * (mbstring ist auf Uberspace vorhanden, aber nicht überall; ein
     * fehlendes mb_substr darf keine Buchung scheitern lassen.)
     */
    function kuerze(string $text, int $max): string
    {
        if (function_exists('mb_substr')) return mb_substr($text, 0, $max);
        $kurz = substr($text, 0, $max);
        // Abgeschnittene UTF-8-Sequenz am Ende entfernen
        return preg_replace('/[\x80-\xBF]+$|[\xC0-\xFD]$/', '', $kurz) ?? $kurz;
    }
}
