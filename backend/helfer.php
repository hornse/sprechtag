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

if (!function_exists('markdown_sicher')) {
    /**
     * Wandelt eine kleine, bewusst eingeschränkte Markdown-Teilmenge in HTML um.
     *
     * SICHERHEIT: Der komplette Eingabetext wird ZUERST HTML-escaped. Dadurch
     * kann kein vom Nutzer eingegebenes HTML/JavaScript durchrutschen (XSS).
     * Erst danach werden AUSSCHLIESSLICH die erlaubten Markdown-Muster in
     * sichere HTML-Tags übersetzt. Es gibt keinen Pfad, über den Roh-HTML
     * ins Ergebnis gelangt.
     *
     * Unterstützt: Überschriften (##, ###), Absätze, Leerzeilen, **fett**,
     * *kursiv*, Aufzählungen (- / *), nummerierte Listen (1.),
     * Links [Text](https://…) – nur http/https/mailto.
     */
    function markdown_sicher(string $text): string
    {
        // 1) Alles neutralisieren. Ab hier ist der Text reiner, sicherer Text.
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // 2) Zeilenweise verarbeiten (Listen/Überschriften/Absätze).
        $zeilen = explode("\n", $escaped);
        $html = '';
        $inListe = null;   // 'ul' | 'ol' | null
        $absatz = [];

        $absatzSchliessen = function () use (&$html, &$absatz) {
            if ($absatz) {
                $html .= '<p>' . markdown_inline_sicher(implode(' ', $absatz)) . '</p>';
                $absatz = [];
            }
        };
        $listeSchliessen = function () use (&$html, &$inListe) {
            if ($inListe) { $html .= '</' . $inListe . '>'; $inListe = null; }
        };

        foreach ($zeilen as $z) {
            $t = trim($z);
            if ($t === '') { $absatzSchliessen(); $listeSchliessen(); continue; }

            // Überschriften
            if (preg_match('/^###\s+(.+)$/', $t, $m)) {
                $absatzSchliessen(); $listeSchliessen();
                $html .= '<h4>' . markdown_inline_sicher($m[1]) . '</h4>';
                continue;
            }
            if (preg_match('/^##\s+(.+)$/', $t, $m)) {
                $absatzSchliessen(); $listeSchliessen();
                $html .= '<h3>' . markdown_inline_sicher($m[1]) . '</h3>';
                continue;
            }
            // Aufzählung
            if (preg_match('/^[-*]\s+(.+)$/', $t, $m)) {
                $absatzSchliessen();
                if ($inListe !== 'ul') { $listeSchliessen(); $html .= '<ul>'; $inListe = 'ul'; }
                $html .= '<li>' . markdown_inline_sicher($m[1]) . '</li>';
                continue;
            }
            // Nummerierte Liste
            if (preg_match('/^\d+\.\s+(.+)$/', $t, $m)) {
                $absatzSchliessen();
                if ($inListe !== 'ol') { $listeSchliessen(); $html .= '<ol>'; $inListe = 'ol'; }
                $html .= '<li>' . markdown_inline_sicher($m[1]) . '</li>';
                continue;
            }
            // Normale Zeile → Teil eines Absatzes
            $listeSchliessen();
            $absatz[] = $t;
        }
        $absatzSchliessen();
        $listeSchliessen();
        return $html;
    }

    /**
     * Inline-Auszeichnungen auf bereits escapetem Text: **fett**, *kursiv*,
     * [Text](url). Links nur mit http/https/mailto; alles andere bleibt Text.
     */
    function markdown_inline_sicher(string $s): string
    {
        // Links [Text](url) – url wird streng geprüft.
        $s = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            function ($m) {
                $ziel = $m[2];
                // Nur sichere Schemata zulassen (der Text ist bereits escaped,
                // daher &amp; etc. berücksichtigen).
                $zielRein = html_entity_decode($ziel, ENT_QUOTES, 'UTF-8');
                if (!preg_match('#^(https?://|mailto:)#i', $zielRein)) {
                    return $m[0];   // kein erlaubtes Schema → als Text belassen
                }
                $hrefAttr = htmlspecialchars($zielRein, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<a href="' . $hrefAttr . '" target="_blank" rel="noopener noreferrer">'
                    . $m[1] . '</a>';
            },
            $s
        );
        // **fett**
        $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
        // *kursiv*
        $s = preg_replace('/(?<!\*)\*(?!\*)([^*]+)\*(?!\*)/', '<em>$1</em>', $s);
        return $s;
    }
}

if (!function_exists('platzhalter_ersetzen')) {
    /**
     * Ersetzt {{name}}-Platzhalter durch die übergebenen Werte. Unbekannte
     * Platzhalter bleiben unverändert stehen (sichtbar für den Admin als
     * Hinweis auf einen Tippfehler). Wird VOR markdown_sicher() angewendet.
     */
    function platzhalter_ersetzen(string $text, array $werte): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i',
            function ($m) use ($werte) {
                $key = strtolower($m[1]);
                return array_key_exists($key, $werte) ? (string)$werte[$key] : $m[0];
            }, $text);
    }
}
