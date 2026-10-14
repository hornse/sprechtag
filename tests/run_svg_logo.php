<?php
// ============================================================
// tests/run_svg_logo.php – die SVG-Prüfung sieht das geparste Dokument an
// (v0.9.104, E32)
// Aufruf: php tests/run_svg_logo.php   → Exit-Code 0 = alles grün
//
// Anlass: Review aus koordination (BEFUND-2026-10-14-review-sprechtag-1.md,
// Commit a700ce2), Befund 4, dort gemessen an bff6a89: marke_svg_sicher()
// suchte im TEXT. Der XML-Parser löst &#x6A; zu j auf – „&#x6A;avascript:“
// bestand die Prüfung; ebenso <set attributeName="href" to="javascript:…">;
// foreignObject fehlte ganz. Das Logo wird unter /api/einstellungen/logo
// öffentlich vom eigenen Ursprung ausgeliefert; als Dokument geöffnet liefe
// Skript im Namen der angemeldeten Person (Betreiber, 14.10.2026: HttpOnly
// schützt das Cookie, nicht die Handlungen).
//
// Ausgeführt: marke_svg_grund() / marke_svg_sicher() aus einstellungen.php.
// Die Fälle, die abgelehnt werden MÜSSEN, stehen zuerst; die Fälle, die
// angenommen werden müssen, danach – eine Prüfung, die alles ablehnt, wäre
// keine. Fehlt der Parser (Erweiterung dom), wird abgelehnt: geschlossen
// scheitern. Alle SVG-Inhalte ERFUNDEN.
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
require __DIR__ . '/../backend/api/einstellungen.php';

pruefe('Voraussetzung: marke_svg_sicher vorhanden; Parser (dom) in dieser Umgebung vorhanden',
    function_exists('marke_svg_sicher') && class_exists('DOMDocument'));
// Eine Ausnahme ist weder „abgelehnt“ noch „angenommen“: Sie macht beide Arten
// von Prüfung rot, statt die Suite still abbrechen zu lassen (Art 7).
$sicher = function (string $s): ?bool {
    try { return marke_svg_sicher($s); }
    catch (Throwable $e) { echo '    (Ausnahme: ' . get_class($e) . ': ' . $e->getMessage() . ")\n"; return null; }
};
$grund = function (string $s): string {
    try { return (string)marke_svg_grund($s); } catch (Throwable $e) { return 'AUSNAHME'; }
};
$kopf = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">';
$svg = fn(string $innen): string => $kopf . $innen . '</svg>';

// ------------------------------------------------------------
echo "Abgelehnt werden muss\n";
$boese = [
    'Klartext <script> (Gegenprobe, schon bisher abgelehnt)'
        => $svg('<script>alert(1)</script>'),
    'Klartext javascript: im href (Gegenprobe)'
        => $svg('<a href="javascript:alert(1)"><rect width="1" height="1"/></a>'),
    'Entität &#x6A;avascript: im href (Review, Befund 4)'
        => $svg('<a href="&#x6A;avascript:alert(1)"><rect width="1" height="1"/></a>'),
    'Entität im xlink:href, mit Tabulator dazwischen (java&#x09;script:)'
        => $svg('<a xlink:href="java&#x09;script:alert(1)"><rect width="1" height="1"/></a>'),
    '<set attributeName="href" to="javascript:…"> (Review, Befund 4)'
        => $svg('<a><set attributeName="href" to="javascript:alert(1)"/><rect width="1" height="1"/></a>'),
    '<animate attributeName="xlink:href" values="…"> mit Entität'
        => $svg('<a><animate attributeName="xlink:href" values="&#106;avascript:alert(1)"/><rect width="1" height="1"/></a>'),
    'foreignObject (FALLSTRICKE 10, fehlte bisher)'
        => $svg('<foreignObject width="10" height="10"><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject>'),
    'Ereignisattribut onload'
        => str_replace('<svg ', '<svg onload="alert(1)" ', $svg('')),
    'Ereignisattribut in Großbuchstaben (ONCLICK)'
        => $svg('<rect width="1" height="1" ONCLICK="alert(1)"/>'),
    'externe Referenz (http) im href'
        => $svg('<image href="https://example.invalid/x.png" width="1" height="1"/>'),
    'Verarbeitungsanweisung <?xml-stylesheet?>'
        => '<?xml-stylesheet href="https://example.invalid/a.css"?>' . $svg(''),
    'Verarbeitungsanweisung <?php ?>'
        => $svg('<?php echo 1; ?>'),
    'DOCTYPE mit Entität'
        => '<!DOCTYPE svg [<!ENTITY a "javascript:alert(1)">]>' . $svg('<a href="&a;"><rect width="1" height="1"/></a>'),
    '<style> mit @import'
        => $svg('<style>@import url(https://example.invalid/a.css);</style>'),
    '<style> mit @import "…" ohne url() (nur die @import-Stufe greift)'
        => $svg('<style>@import "https://example.invalid/a.css";</style>'),
    'style-Attribut mit externer url()'
        => $svg('<rect width="1" height="1" style="fill:url(https://example.invalid/a.svg#g)"/>'),
    'iframe im SVG-Namensraum'
        => $svg('<iframe src="https://example.invalid/"/>'),
    'nicht wohlgeformt (geschlossen scheitern)'
        => $kopf . '<rect width="1"',
    'Wurzel ist kein svg (html)'
        => '<html xmlns="http://www.w3.org/1999/xhtml"><body>x</body></html>',
    'leer'
        => '',
];
foreach ($boese as $name => $inhalt) pruefe($name, $sicher($inhalt) === false);

// ------------------------------------------------------------
echo "Angenommen werden muss\n";
$gut = [
    'einfaches Logo mit XML-Deklaration'
        => '<?xml version="1.0" encoding="UTF-8"?>' . $svg('<circle cx="5" cy="5" r="4" fill="#123456"/>'),
    'mit <style> und Klassen (wie aus Grafikprogrammen)'
        => $svg('<style>.a{fill:#123456}.b{stroke:url(#g)}</style><linearGradient id="g"/><rect class="a" width="1" height="1"/>'),
    'interne Referenz <use href="#a"> und xlink:href="#a"'
        => $svg('<defs><path id="a" d="M0 0h1"/></defs><use href="#a"/><use xlink:href="#a"/>'),
    'eingebettetes Bild data:image/png;base64'
        => $svg('<image width="1" height="1" href="data:image/png;base64,iVBORw0KGgo="/>'),
    'Text mit dem Wort „javascript“ als Inhalt, nicht als Adresse'
        => $svg('<text x="0" y="5">javascript ist hier nur ein Wort</text>'),
];
foreach ($gut as $name => $inhalt) pruefe($name, $sicher($inhalt) === true);

// ------------------------------------------------------------
// Zwei Stufen lehnen eine Skript-Adresse im href ab: die Prüfung auf
// javascript:/vbscript:/data:text und die Liste erlaubter Adressen. Welche
// angeschlagen hat, sagt nur der Wortlaut (REIHENREGELN 2).
echo "Welche Stufe ablehnt (Wortlaut des Grundes)\n";
$fG = $grund($boese['Entität &#x6A;avascript: im href (Review, Befund 4)']);
pruefe('Stufe Skript-Adresse: Entität im href – ' . $fG, str_contains($fG, 'Skript-Adresse'));
$fG = $grund($boese['Entität im xlink:href, mit Tabulator dazwischen (java&#x09;script:)']);
pruefe('Stufe Skript-Adresse: Tabulator zwischen java und script – ' . $fG, str_contains($fG, 'Skript-Adresse'));
$fG = $grund($boese['externe Referenz (http) im href']);
pruefe('Stufe Adressliste: externe Referenz – ' . $fG, str_contains($fG, 'verweist nach außen'));
$fG = $grund($boese['<animate attributeName="xlink:href" values="…"> mit Entität']);
pruefe('Stufe Animation: href per animate – ' . $fG, str_contains($fG, 'Animation'));
// Zwei Stufen auch im CSS: @import und url() nach außen (v0.9.104, nach LG8).
$fG = $grund($boese['<style> mit @import "…" ohne url() (nur die @import-Stufe greift)']);
pruefe('Stufe @import: @import ohne url() – ' . $fG, str_contains($fG, '(@import)'));
$fG = $grund($boese['<style> mit @import']);
pruefe('Stufe @import: auch mit url() schlägt @import an, nicht url() – ' . $fG, str_contains($fG, '(@import)'));

// ------------------------------------------------------------
echo "Ohne Parser: geschlossen scheitern\n";
$hatGrund = function_exists('marke_svg_grund');
pruefe('marke_svg_grund nennt den Grund; ohne Parser wird auch ein harmloses SVG abgelehnt, mit Hinweis auf PNG',
    $hatGrund && is_string(marke_svg_grund($gut['einfaches Logo mit XML-Deklaration'], false))
    && str_contains((string)marke_svg_grund($gut['einfaches Logo mit XML-Deklaration'], false), 'PNG'));
pruefe('mit Parser: kein Grund für das harmlose, ein Grund für das böse',
    $hatGrund && marke_svg_grund($gut['einfaches Logo mit XML-Deklaration']) === null
    && is_string(marke_svg_grund($boese['foreignObject (FALLSTRICKE 10, fehlte bisher)'])));

echo "Aufrufstelle (Quelltext)\n";
$q = (string)file_get_contents(__DIR__ . '/../backend/api/einstellungen.php');
pruefe('der Upload fragt marke_svg_grund und nennt den Grund in der Ablehnung',
    preg_match('/\$grund = marke_svg_grund\(\$binaer\)/', $q) === 1
    && str_contains($q, "json_err('Die SVG-Datei wurde abgelehnt: ' . \$grund"));

echo $fehler === 0 ? "\nALLE TESTS GRÜN\n" : "\n$fehler ROT\n";
exit($fehler === 0 ? 0 : 1);
