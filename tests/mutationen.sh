#!/bin/bash
# ============================================================
# tests/mutationen.sh – prüft die Prüfungen
#
# Baut nacheinander bewusste Fehler in app.js, index.html und style.css
# ein und belegt je Fehler, dass die zuständige Suite rot wird – an der
# erwarteten Zeile, nicht nur irgendwo.
#
# Aufruf im Projektordner:  ./tests/mutationen.sh
#
# Je Mutation: Prüfsumme vorher → Sicherung → Eingriff → Nachweis, dass
# er angekommen ist (Prüfsumme anders) → Suite → Rücknahme aus der
# Sicherung (nie aus dem Index) → Prüfsumme gleich vorher. Misslingt eine
# Rücknahme, bricht der Lauf sofort ab; bei Abbruch von außen wird die
# offene Mutation zurückgenommen.
#
# Ergebnis je Zeile: „angeschlagen" (gut), „NICHT ANGESCHLAGEN" (die
# Prüfung prüft nichts) oder „MUTATION NICHT ANGEKOMMEN" (das Suchmuster
# trifft den Code nicht mehr – das Werkzeug ist veraltet, nicht die
# Prüfung belegt). Exit 0 nur, wenn alle angeschlagen haben.
#
# Entstanden mit v0.9.51: Die Serie fand fünf Prüfungen, die nur
# zufällig grün waren (docs/ENTSCHEIDUNGEN.md, E3–E5). Wird nicht von
# deploy.sh aufgerufen – sie verändert vorübergehend den Code.
#
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -u
export LC_ALL=C
R="$(cd "$(dirname "$0")/.." && pwd)"
SICH="$(mktemp -d)"
OFFEN=""          # Datei mit gerade eingebauter Mutation
FEHLT=0

summe() { shasum "$1" | cut -c1-12; }

zuruecknehmen() {
  if [ -n "$OFFEN" ]; then
    cp "$SICH/sicherung" "$OFFEN"
    echo "Abbruch: offene Mutation in $OFFEN zurückgenommen."
  fi
  rm -rf "$SICH"
}
trap zuruecknehmen EXIT

einbauen() {      # DATEI PERL-AUSDRUCK → setzt VORHER, liefert 0 wenn angekommen
  VORHER=$(summe "$1")
  cp "$1" "$SICH/sicherung"
  OFFEN="$1"
  perl -0777 -i -pe "$2" "$1"
  [ "$(summe "$1")" != "$VORHER" ]
}

ruecknahme() {    # NAME DATEI
  cp "$SICH/sicherung" "$2"
  OFFEN=""
  if [ "$(summe "$2")" != "$VORHER" ]; then
    echo "ABBRUCH: Rücknahme von $1 misslungen ($2)"; exit 2
  fi
}

# mut NAME DATEI PERL-AUSDRUCK SUITE ERWARTETE-ZEILE
mut() {
  local name=$1 datei="$R/$2" ausdruck=$3 suite=$4 erwartet=$5
  if ! einbauen "$datei" "$ausdruck"; then
    echo "$name: MUTATION NICHT ANGEKOMMEN – Suchmuster prüfen"; FEHLT=$((FEHLT + 1))
  else
    local aus e treffer
    aus=$(node "$R/$suite" 2>&1); e=$?
    treffer=$(printf '%s\n' "$aus" | grep -cF "✗ $erwartet" || true)
    if [ "$e" -ne 0 ] && [ "$treffer" -ge 1 ]; then
      echo "$name: angeschlagen („$erwartet“)"
    else
      echo "$name: NICHT ANGESCHLAGEN (exit=$e, „$erwartet“ rot: $treffer)"; FEHLT=$((FEHLT + 1))
    fi
    printf '%s\n' "$aus" | grep -F "(Voraussetzung" | sed 's/^/      /'
  fi
  ruecknahme "$name" "$datei"
}

# Laufzeitprüfung: zählt der Exit-Code, eine Zeile gibt es nicht.
mut_lauf() {
  local name=$1 datei="$R/$2" ausdruck=$3
  if ! einbauen "$datei" "$ausdruck"; then
    echo "$name: MUTATION NICHT ANGEKOMMEN – Suchmuster prüfen"; FEHLT=$((FEHLT + 1))
  else
    if node "$R/tests/frontend_laufzeit_test.js" > /dev/null 2>&1; then
      echo "$name: NICHT ANGESCHLAGEN"; FEHLT=$((FEHLT + 1))
    else
      echo "$name: angeschlagen (Exit ≠ 0 – über Nodes Abbruch, siehe E5)"
    fi
  fi
  ruecknahme "$name" "$datei"
}

B=tests/frontend_barrierefreiheit_test.js
M=tests/frontend_marke_test.js

echo "== Barrierefreiheit"
mut B1 frontend/app.js "s/(function symbol\(name\) \{.*?)\n  svg\.setAttribute\('aria-hidden', 'true'\);/\$1/s" \
  $B "Nav-Icon aria-hidden"
mut B2 frontend/app.js "s/(function symbol\(name\) \{.*?)\n  svg\.setAttribute\('aria-hidden', 'true'\);/\$1\n  \/\/ svg.setAttribute('aria-hidden', 'true');/s" \
  $B "Nav-Icon aria-hidden"
mut B3 frontend/app.js "s/b\.appendChild\(symbol\(icon \|\| 'datei'\)\);/b.appendChild(el('span', 'nv-icon', icon));/" \
  $B "navKnopf ruft symbol() auf"
mut B4 frontend/index.html 's/(id="toast"[^>]*?) aria-live="polite"/$1/' \
  $B "Toast ist Live-Region"
mut B5 frontend/app.js "s/    if \(S\.ansicht === ziel\) b\.setAttribute\('aria-current', 'page'\);/    \/\/ if (S.ansicht === ziel) b.setAttribute('aria-current', 'page');/" \
  $B "aktive Ansicht als aria-current"
mut B6 frontend/style.css 's/\n:focus-visible \{/\n.x-focus-visible {/' \
  $B "Sichtbarer Fokus-Rahmen (:focus-visible)"
mut B7 frontend/style.css 's/\.skip-link:focus \{ top: 0; \}/.skip-link:focus { top: -3rem; }/' \
  $B "Skip-Link-CSS (bei Fokus sichtbar)"
mut B8 frontend/app.js "s/(function menueSchliessen\(\) \{.*?)\n[^\n]*setAttribute\('aria-expanded', 'false'\);/\$1/s" \
  $B "Hamburger aria-expanded gepflegt"
mut B9 frontend/app.js 's/\z/\n\/\/ function toast( – zweiter Kopf\n/' \
  $B "Fehler-Toast assertive"

echo "== Marke"
mut M1 frontend/app.js "s/(function wendeMarkeAn\(m\) \{)/\$1\n  document.documentElement.style.setProperty('--akzent', m.marke_farbe);/" \
  $M "Branding setzt keine Akzentfarbe"
mut M2 frontend/app.js 's/(function wendeMarkeAn\(m\) \{)/$1\n  document.documentElement.style.setProperty("--akzent", m.marke_farbe);/' \
  $M "Branding setzt keine Akzentfarbe"

echo "== Laufzeit"
mut_lauf L1 frontend/app.js 's/(async function start\(\) \{)/$1\n  nichtDefiniert();/'

echo ""
if [ "$FEHLT" -eq 0 ]; then echo "ALLE MUTATIONEN ANGESCHLAGEN"; exit 0; fi
echo "$FEHLT MUTATION(EN) OHNE BELEG"; exit 1
