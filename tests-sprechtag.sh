#!/usr/bin/env bash
# ============================================================
# tests-sprechtag.sh – Prüfung des CI-Umbaus
#
# Aufruf im Projektordner:  ./tests-sprechtag.sh
#
# Prüft, was beim Umbau schiefgehen kann: verbliebene Rohfarben,
# unausgeglichene Klammern, undefinierte Tokens, JS-Syntax, und ob
# die behobenen Mängel tatsächlich behoben sind.
#
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -uo pipefail
export LC_ALL=C
cd "$(dirname "$0")"

FEHLER=0
gruen() { echo "  ✓ $1"; }
rot()   { echo "  ✗ $1"; FEHLER=$((FEHLER + 1)); }
pruefe() { if [ "$1" = "ja" ]; then gruen "$2"; else rot "$2"; fi }

CSS=frontend/style.css
JS=frontend/app.js
HTML=frontend/index.html
TOK=frontend/vendor/ci-css/ci-tokens.css

echo "Dateien"
for D in "$CSS" "$JS" "$HTML" "$TOK" frontend/vendor/ci-css/ci-icons.svg; do
    [ -f "$D" ] && gruen "$D vorhanden" || rot "$D fehlt"
done

echo ""
echo "Aufbau"
AUF=$(tr -cd '{' < "$CSS" | wc -c | tr -d ' ')
ZU=$(tr -cd '}' < "$CSS" | wc -c | tr -d ' ')
[ "$AUF" -eq "$ZU" ] && gruen "Klammern ausgeglichen ($AUF)" \
    || rot "$AUF öffnende, $ZU schließende Klammern"

if command -v node > /dev/null 2>&1; then
    node --check "$JS" > /dev/null 2>&1 \
        && gruen "app.js ist syntaktisch fehlerfrei" \
        || rot "app.js hat einen Syntaxfehler"
else
    echo "  –  node nicht vorhanden, JS-Syntax nicht geprüft"
fi

echo ""
echo "Keine Rohfarben außerhalb des :root-Blocks"
# Kommentare und den :root-Block ausklammern: Dort stehen die
# Kennfarben und Transparenzen bewusst als Werte.
REST=$(perl -0777 -pe 's{/\*.*?\*/}{}gs' "$CSS" \
    | perl -0777 -pe 's{^.*?\* \{ box-sizing: border-box; \}}{}s')
TREFFER=$(printf '%s' "$REST" | grep -oE '#[0-9a-fA-F]{3,8}\b' | sort -u || true)
[ -z "$TREFFER" ] && gruen "keine Hexfarben" \
    || rot "Hexfarben: $(echo "$TREFFER" | tr '\n' ' ')"
TREFFER=$(printf '%s' "$REST" | grep -oE 'rgba?\([^)]*\)' | sort -u || true)
[ -z "$TREFFER" ] && gruen "keine rgb/rgba-Angaben" \
    || rot "rgba: $(echo "$TREFFER" | tr '\n' ' ')"

# Dasselbe im JavaScript (v0.9.52, E7): Die Prüfung oben las nur die
# Stilvorlage, während im JS zwei Hexwerte als Voreinstellung der
# Farbfelder standen. Die Dateien werden ermittelt, nicht aufgezählt;
# keine gefunden ist ein Fehler. Entfernt werden Blockkommentare und
# ganze Kommentarzeilen – nicht „//“ mitten in einer Zeile, sonst fiele
# 'http://…' in einer Zeichenkette mit weg.
#
# BEKANNTE GRENZE (bewusst so, v0.9.52): Ein Farbwert in einem Kommentar
# HINTER Code in derselben Zeile – `x(); // #1d4e89` – wird rot, obwohl
# er nur Kommentar ist. Lieber falsch-rot als ein stilles Loch. Abhilfe
# im Einzelfall: den Kommentar auf eine eigene Zeile setzen.
JSDATEIEN=$(find frontend -path frontend/vendor -prune -o -type f -name '*.js' -print | sort)
JSANZAHL=$(printf '%s\n' "$JSDATEIEN" | grep -c . || true)
JSREST=""
for D in $JSDATEIEN; do
    JSREST="$JSREST$(perl -0777 -pe 's{/\*.*?\*/}{}gs; s{^\s*//[^\n]*}{}mg' "$D")"
done
if [ "$JSANZAHL" -eq 0 ]; then
    rot "keine JS-Datei im Frontend gefunden – Rohfarben im JS nicht geprüft"
    rot "keine JS-Datei im Frontend gefunden – rgb/hsl im JS nicht geprüft"
else
    TREFFER=$(printf '%s' "$JSREST" | grep -oE '#[0-9a-fA-F]{3,8}\b' | sort -u || true)
    [ -z "$TREFFER" ] && gruen "keine Hexfarben im JavaScript ($JSANZAHL Datei(en))" \
        || rot "Hexfarben im JavaScript: $(echo "$TREFFER" | tr '\n' ' ')"
    TREFFER=$(printf '%s' "$JSREST" | grep -oE '(rgba?|hsla?)\([^)]*\)' | sort -u || true)
    [ -z "$TREFFER" ] && gruen "keine rgb/hsl-Angaben im JavaScript" \
        || rot "rgb/hsl im JavaScript: $(echo "$TREFFER" | tr '\n' ' ')"
fi

echo ""
echo "Tokens vollständig"
UNBEKANNT=""
for V in $(grep -ohE 'var\(--ci-[a-z0-9-]+' "$CSS" | sed 's/var(//' | sort -u); do
    grep -qE "^[[:space:]]*$V:" "$TOK" || UNBEKANNT="$UNBEKANNT $V"
done
[ -z "$UNBEKANNT" ] && gruen "alle benutzten ci-Tokens sind definiert" \
    || rot "nicht definiert:$UNBEKANNT"

echo ""
echo "Einbindung"
grep -q 'data-projekt="sprechtag"' "$HTML" \
    && gruen "Projektfarbe gesetzt" || rot "data-projekt fehlt"
grep -q 'ci-tokens.css' "$HTML" \
    && gruen "Tokens eingebunden" || rot "Tokens nicht eingebunden"
grep -q 'data-ci-icons' "$HTML" \
    && gruen "Sprite-Pfad durchgereicht" || rot "data-ci-icons fehlt"

echo ""
echo "Behobene Mängel"
grep -q '<h1 class="marke-titel"' "$HTML" \
    && gruen "Seite hat ein h1" || rot "kein h1 – Gliederung beginnt bei h2"
grep -q 'id="marke-logo" class="marke-logo versteckt" alt=""' "$HTML" \
    && gruen "Logo ist als dekorativ ausgezeichnet" || rot "alt am Logo prüfen"
grep -q 'clip-path: inset(50%)' "$CSS" \
    && gruen ".sr-only nutzt clip-path" || rot ".sr-only nutzt noch clip"
grep -q 'clip: rect' "$CSS" \
    && rot "abgekündigtes clip ist noch vorhanden" \
    || gruen "kein abgekündigtes clip mehr"
grep -q "e.key !== 'Escape'" "$JS" \
    && gruen "Escape schließt das mobile Menü" || rot "keine Escape-Behandlung"
grep -q "\$('#mobil-menue')?.focus()" "$JS" \
    && gruen "Fokus kehrt zum Menüknopf zurück" || rot "kein Fokus-Rücksprung"
grep -q "setProperty('--akzent'" "$JS" \
    && rot "Branding setzt noch die Akzentfarbe" \
    || gruen "Branding setzt keine Farben mehr"
grep -q "versionAnzeigen" "$JS" \
    && gruen "Version kommt aus /api/health" || rot "Version steht noch fest im HTML"
# Eigenes Element: Sonst konkurrieren Branding und Version um dieselbe
# Stelle, und je nach Ladereihenfolge fällt eines still weg.
grep -q 'id="marke-version"' "$HTML" \
    && gruen "Version hat ein eigenes Element" \
    || rot "kein #marke-version – Version konkurriert mit der Fußzeile"
grep -q "\$('#marke-version')" "$JS" \
    && gruen "app.js schreibt in #marke-version" || rot "app.js schreibt in die Fußzeile"
grep -q "dataset.eigen" "$JS" \
    && rot "Rückzug vor dem Branding noch vorhanden" \
    || gruen "kein Rückzug mehr nötig"

echo ""
echo "Symbole statt Emoji"
EMOJI=$(grep -cE "navKnopf\([^)]*'[^a-zA-Z0-9 ,')]" "$JS" || true)
[ "$EMOJI" -eq 0 ] && gruen "keine Emoji mehr in der Navigation" \
    || rot "$EMOJI Emoji in navKnopf-Aufrufen"
ANZAHL=$(grep -c "symbol('" "$JS" || true)
[ "$ANZAHL" -ge 3 ] && gruen "symbol() wird benutzt ($ANZAHL Stellen)" \
    || rot "symbol() kaum benutzt"

# Jedes benutzte Symbol muss im Sprite existieren.
FEHLEND=""
for N in $(grep -oE "symbol\('[a-z]+'\)|navKnopf\([^)]*, '[a-z]+'\)" "$JS" \
           | grep -oE "'[a-z]+'\)?$" | tr -d "')" | sort -u); do
    grep -q "id=\"ci-i-$N\"" frontend/vendor/ci-css/ci-icons.svg || FEHLEND="$FEHLEND $N"
done
[ -z "$FEHLEND" ] && gruen "alle benutzten Symbole existieren im Sprite" \
    || rot "im Sprite fehlen:$FEHLEND"

echo ""
if [ "$FEHLER" -eq 0 ]; then echo "ALLES GRÜN"; exit 0; fi
echo "$FEHLER FEHLER"; exit 1
