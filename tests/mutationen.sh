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
# zufällig grün waren (docs/ENTSCHEIDUNGEN.md, E3–E5). Seit v0.9.53
# auch PHP-Suiten (Endung .php) und backend/. Wird nicht von
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

# Suite ausführen: PHP-Suiten mit php, alle anderen mit node.
suite_lauf() {
  case "$1" in
    *.php) php "$R/$1" ;;
    *)     node "$R/$1" ;;
  esac
}

# mut NAME DATEI PERL-AUSDRUCK SUITE ERWARTETE-ZEILE
mut() {
  local name=$1 datei="$R/$2" ausdruck=$3 suite=$4 erwartet=$5
  if ! einbauen "$datei" "$ausdruck"; then
    echo "$name: MUTATION NICHT ANGEKOMMEN – Suchmuster prüfen"; FEHLT=$((FEHLT + 1))
  else
    local aus e treffer
    aus=$(suite_lauf "$suite" 2>&1); e=$?
    treffer=$(printf '%s\n' "$aus" | grep -cF "✗ $erwartet" || true)
    if [ "$e" -ne 0 ] && [ "$treffer" -ge 1 ]; then
      echo "$name: angeschlagen („$erwartet“)"
    else
      echo "$name: NICHT ANGESCHLAGEN (exit=$e, „$erwartet“ rot: $treffer)"; FEHLT=$((FEHLT + 1))
    fi
    printf '%s\n' "$aus" | grep -F "Voraussetzung" | sed 's/^/      /'
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

# tests-sprechtag.sh: erwartete Zeile rot
mut_sh() {
  local name=$1 datei="$R/$2" ausdruck=$3 erwartet=$4
  if ! einbauen "$datei" "$ausdruck"; then
    echo "$name: MUTATION NICHT ANGEKOMMEN – Suchmuster prüfen"; FEHLT=$((FEHLT + 1))
  else
    local aus e treffer
    aus=$("$R/tests-sprechtag.sh" 2>&1); e=$?
    treffer=$(printf '%s\n' "$aus" | grep -cF "✗ $erwartet" || true)
    if [ "$e" -ne 0 ] && [ "$treffer" -ge 1 ]; then
      echo "$name: angeschlagen („$erwartet“)"
    else
      echo "$name: NICHT ANGESCHLAGEN (exit=$e, „$erwartet“ rot: $treffer)"; FEHLT=$((FEHLT + 1))
    fi
  fi
  ruecknahme "$name" "$datei"
}

# Gegenprobe der anderen Richtung: Diese Änderung ist zulässig und darf
# NICHT anschlagen – sonst wäre die Prüfung bei jeder Erklärung rot.
mut_sh_gruen() {
  local name=$1 datei="$R/$2" ausdruck=$3
  if ! einbauen "$datei" "$ausdruck"; then
    echo "$name: MUTATION NICHT ANGEKOMMEN – Suchmuster prüfen"; FEHLT=$((FEHLT + 1))
  else
    if "$R/tests-sprechtag.sh" > /dev/null 2>&1; then
      echo "$name: bleibt grün, wie verlangt"
    else
      echo "$name: SCHLÄGT FÄLSCHLICH AN"; FEHLT=$((FEHLT + 1))
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

echo "== Logo dekorativ (E6)"
mut G1 frontend/app.js "s/(function wendeMarkeAn\(m\) \{)/\$1\n  \$('#marke-logo').alt = 'Logo ' + m.marke_schulname;/" \
  $B "wendeMarkeAn() vergibt dem Logo keinen Alt-Text"
mut G2 frontend/app.js "s/(function wendeMarkeAn\(m\) \{)/\$1\n  \$('#marke-logo').setAttribute(\"alt\", 'Logo');/" \
  $B "wendeMarkeAn() vergibt dem Logo keinen Alt-Text"
mut G3 frontend/index.html 's/(id="marke-logo" class="marke-logo versteckt") alt=""/$1 alt="Logo"/' \
  $B "Logo im Kopf ist im HTML dekorativ (alt=\"\")"
mut G4 frontend/app.js 's/function wendeMarkeAn\(m\) \{/function wendeMarkeAnUmbenannt(m) {/' \
  $B "wendeMarkeAn() vergibt dem Logo keinen Alt-Text"

echo "== Farbfelder (E7)"
mut F1 frontend/app.js "s/(function zeichneMarkeBlock\(ziel\) \{)/\$1\n  feld('Akzentfarbe', 'f-marke-farbe', 'color', '');/" \
  $M "Admin-Formular bietet keine Farbfelder"
mut F2 frontend/app.js "s/'Das Erscheinungsbild \(Logo, Texte\)/'Das Erscheinungsbild (Logo, Farben, Texte)/" \
  $M "Admin-Formular bietet keine Farbfelder"
mut F3 frontend/app.js "s/kennzeichnet '\n    \+ 'die Anwendung/kennzeichnet '\n    + 'das Programm/" \
  $M "Formular sagt, warum es keine Farbfelder gibt"
mut F4 backend/api/einstellungen.php "s/('marke_kontakt'    => \['text', 160\],)/\$1\n            'marke_farbe'      => ['text', 7],/" \
  $M "Backend kennt keine Farbfelder mehr"

echo "== Datenbank (E7)"
mut D1 sql/10_branding.sql "s/(    \('marke_untertitel', 'Elternsprechtag'\),)/\$1\n    ('marke_farbe',      '#1d4e89'),/" \
  $M "Branding-Seed legt keine Farbfelder an"
mut D2 sql/20_farbfelder_entfernen.sql "s/IN \('marke_farbe', 'marke_farbe2'\)/IN ('marke_farbe')/" \
  $M "Migration entfernt beide Farbfelder"
mut D3 sql/20_farbfelder_entfernen.sql 's/\nDELETE FROM einstellungen/\n-- DELETE FROM einstellungen/' \
  $M "Migration entfernt beide Farbfelder"

echo "== Rohfarben im JavaScript (E7)"
mut_sh H1 frontend/app.js "s/(function zeichneMarkeBlock\(ziel\) \{)/\$1\n  const vorgabe = '#1d4e89';/" \
  "Hexfarben im JavaScript"
mut_sh H2 frontend/app.js "s/(function zeichneMarkeBlock\(ziel\) \{)/\$1\n  const schatten = 'rgba(0,0,0,.2)';/" \
  "rgb/hsl im JavaScript"
mut_sh H3 frontend/app.js "s/(function zeichneMarkeBlock\(ziel\) \{)/\$1\n  const ton = 'hsl(210, 60%, 30%)';/" \
  "rgb/hsl im JavaScript"
mut_sh_gruen H4 frontend/app.js "s/(function zeichneMarkeBlock\(ziel\) \{)/\$1\n  \/\/ früher Voreinstellung #1d4e89 – entfernt, E7/"

K=tests/run_einladung_kachel.php
KF=tests/frontend_einladung_kachel_test.js
BU=backend/api/buchungen.php

echo "== Einladungs-Kachel: Buchungsrecht (Ursache 2)"
mut K1 $BU 's/\n    if \(bu_eingeladen\(\$pdo, \$sprechtagId, \$schuelerId, \$lehrerId\)\) return true;\n//' \
  $K "Buchung: eingeladene, nicht unterrichtende Lehrkraft geht durch"
mut K2 $BU 's/if \(bu_eingeladen\(\$pdo, \$sprechtagId, \$schuelerId, \$lehrerId\)\) return true;/if (false \&\& bu_eingeladen(\$pdo, \$sprechtagId, \$schuelerId, \$lehrerId)) return true;/' \
  $K "Buchung: eingeladene, nicht unterrichtende Lehrkraft geht durch"
mut K3 $BU 's/(    )(if \(bu_eingeladen\(\$pdo, \$sprechtagId, \$schuelerId, \$lehrerId\)\) return true;)/$1\/\/ $2/' \
  $K "eingeladene, nicht unterrichtende Lehrkraft ist erlaubt"
mut K4 $BU 's/(function bu_eingeladen\(.*?)return \(int\)\$st->fetchColumn\(\) > 0;/$1return (int)\$st->fetchColumn() >= 0;/s' \
  $K "Einladung für ein anderes Kind erlaubt nichts"

echo "== Einladungs-Kachel: Kacheln (Ursache 1) und Phase 1"
mut K5 $BU 's/bu_einladende_lehrer\(\$pdo, \$sid, \[\$kind\]\)/bu_einladende_lehrer(\$pdo, \$sid, [])/' \
  $K "Kacheln: genau die Eingeladenen, die teilnehmen (1, 4)"
mut K6 $BU "s/fn\(\\\$z\) => \\\$z\['teilnahme'\] === null \|\| \(int\)\\\$z\['teilnahme'\] === 1/fn(\\\$z) => true/" \
  $K "Kacheln: genau die Eingeladenen, die teilnehmen (1, 4)"
mut K7 $BU 's/    if \(slot_nur_eingeladene\(\$phase, \$rolle\)\) \{\n        return \[/    if (false) {\n        return [/' \
  $K "Kacheln: keine Unterrichtenden daneben"
mut K8 $BU 's/fn\(\$z\) => !in_array\(\(int\)\$z\[.lehrer_id.\], \$eingeladenIds, true\)/fn(\$z) => true/' \
  $K "jede Lehrkraft genau einmal"
mut K9 backend/api/slots.php "s/\\\$phase === 'phase1' && \(\\\$rolle === 'eltern' \|\| \\\$rolle === 'schueler'\)/\\\$phase === 'phase1' \&\& \\\$rolle === 'eltern'/" \
  $K "Phase 1, volljährige Schüler: nur Eingeladene"
mut K10 backend/api/slots.php 's/if \(slot_nur_eingeladene\(\$phase, \$rolle\) && !/if (false \&\& slot_nur_eingeladene(\$phase, \$rolle) \&\& !/' \
  $K "Buchung: Unterrichtende ohne Einladung wird abgewiesen"

echo "== Einladungs-Kachel: Aufrufstellen"
mut K11 $BU 's/\$eingeladen = bu_eingeladen\(\$pdo, \$sid, \$kind, \$lid\);/\$eingeladen = false;/' \
  $K "Buchungsroute: eingeladen kommt aus bu_eingeladen()"
mut K12 $BU 's/\$liste = bu_buchbare_lehrer\(/\$liste = bu_buchbare_lehrer_alt(/' \
  $K "Kachel-Route ruft bu_buchbare_lehrer()"
mut K13 $BU "s/json_ok\(\['einladungen' => bu_einladende_lehrer\(\\\$pdo, \\\$sid, \\\$kinder\)\]\);/json_ok(['einladungen' => []]);/" \
  $K "Elternzweig von GET /api/einladungen nutzt dieselbe Abfrage"

echo "== Einladungs-Kachel: Frontend"
mut KF1 frontend/app.js 's/if \(Number\(l\.eingeladen\) === 1\) \{/if (false) {/' \
  $KF "Kachel der Eingeladenen trägt „hat Sie eingeladen“ genau einmal"
mut KF2 frontend/app.js 's/if \(Number\(l\.eingeladen\) === 1\) \{/if (true) {/' \
  $KF "Kacheln der anderen tragen es nicht"
mut KF3 frontend/app.js 's/if \(Number\(l\.eingeladen\) === 1\) \{/if (l.eingeladen === 1) {/' \
  $KF "Kachel der Eingeladenen trägt „hat Sie eingeladen“ genau einmal"
mut KF4 frontend/app.js 's/return \(liste\.eingeladen \|\| \[\]\)\n    \.concat\(liste\.unterrichtend \|\| \[\]\)/return (liste.unterrichtend || [])\n    .concat(liste.eingeladen || [])/' \
  $KF "Reihenfolge: eingeladen, unterrichtend, Sonderrolle"
mut KF5 frontend/app.js 's/return \(liste\.eingeladen \|\| \[\]\)/return ([])/' \
  $KF "Phase 1: genau die Eingeladene erscheint"
mut KF6 frontend/app.js 's/const alle = buchenLehrerAlle\(S\.lehrerListe\);/const alle = (S.lehrerListe.unterrichtend || []).concat(S.lehrerListe.sonderlehrer || []);/' \
  $KF "ansichtBuchen() bildet die Liste über buchenLehrerAlle()"
mut KF7 frontend/app.js 's/\} else if \(!S\.lehrerListe\.nur_eingeladene\n\s*&& /} else if (/' \
  $KF "„keine Lehrkräfte hinterlegt“ nicht in Phase 1"

echo ""
if [ "$FEHLT" -eq 0 ]; then echo "ALLE MUTATIONEN ANGESCHLAGEN"; exit 0; fi
echo "$FEHLT MUTATION(EN) OHNE BELEG"; exit 1
