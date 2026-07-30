<?php
// ============================================================
// tests/run_markdown.php
// Prüft markdown_sicher(), markdown_inline_sicher(), platzhalter_ersetzen().
// Schwerpunkt: XSS-Sicherheit (kein Roh-HTML/JS darf durchkommen).
//
// Aufruf: php tests/run_markdown.php
// ============================================================
declare(strict_types=1);
require __DIR__ . '/../backend/helfer.php';

$fehler = 0;
function pruefe(string $name, bool $ok): void {
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . "\n";
    if (!$ok) $fehler++;
}

// ---- Sicherheit: kein Roh-HTML/JS ----
$x1 = markdown_sicher('<script>alert(1)</script>');
pruefe('Script-Tag wird escaped', strpos($x1, '<script>') === false && strpos($x1, '&lt;script&gt;') !== false);

$x2 = markdown_sicher('<img src=x onerror=alert(1)>');
pruefe('img/onerror wird escaped', strpos($x2, '<img') === false);

$x3 = markdown_sicher('[klick](javascript:alert(1))');
pruefe('javascript:-Link nicht verlinkt', strpos($x3, '<a ') === false && strpos($x3, 'javascript:') !== false);

$x4 = markdown_sicher('[klick](https://ok.de" onmouseover="alert(1))');
pruefe('kein Ausbruch aus href-Attribut', strpos($x4, 'onmouseover=') === false || strpos($x4, '&quot;') !== false);

$x5 = markdown_sicher('[ok](https://schule.de)');
pruefe('https-Link erlaubt', strpos($x5, '<a href="https://schule.de"') !== false && strpos($x5, 'rel="noopener') !== false);

$x6 = markdown_sicher('[mail](mailto:webuntis@schule.de)');
pruefe('mailto-Link erlaubt', strpos($x6, 'href="mailto:webuntis@schule.de"') !== false);

// ---- Formatierung ----
pruefe('Überschrift ##', strpos(markdown_sicher('## Titel'), '<h3>Titel</h3>') !== false);
pruefe('Überschrift ###', strpos(markdown_sicher('### Unter'), '<h4>Unter</h4>') !== false);
pruefe('fett', strpos(markdown_sicher('Das ist **wichtig**.'), '<strong>wichtig</strong>') !== false);
pruefe('kursiv', strpos(markdown_sicher('Das ist *kursiv* hier.'), '<em>kursiv</em>') !== false);

$liste = markdown_sicher("- eins\n- zwei");
pruefe('Aufzählung', strpos($liste, '<ul><li>eins</li><li>zwei</li></ul>') !== false);

$oliste = markdown_sicher("1. eins\n2. zwei");
pruefe('nummerierte Liste', strpos($oliste, '<ol><li>eins</li><li>zwei</li></ol>') !== false);

$absatz = markdown_sicher("Zeile eins\nZeile zwei\n\nNeuer Absatz");
pruefe('Absätze', substr_count($absatz, '<p>') === 2);

// ---- Platzhalter ----
$p = platzhalter_ersetzen('Kontakt: {{kontakt}}', ['kontakt' => 'webuntis@schule.de']);
pruefe('Platzhalter ersetzt', $p === 'Kontakt: webuntis@schule.de');

$p2 = platzhalter_ersetzen('Unbekannt: {{foo}}', ['kontakt' => 'x']);
pruefe('unbekannter Platzhalter bleibt stehen', $p2 === 'Unbekannt: {{foo}}');

// Platzhalterwert mit HTML wird beim späteren Rendern ebenfalls entschärft
$roh = platzhalter_ersetzen('Kontakt: {{kontakt}}', ['kontakt' => '<b>x</b>']);
$fertig = markdown_sicher($roh);
pruefe('Platzhalterwert kann kein HTML einschleusen', strpos($fertig, '<b>') === false);

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler FEHLER\n";
exit($fehler === 0 ? 0 : 1);
