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
mut K6 $BU "s/fn\(\\\$z\) => bu_teilnehmend\(\\\$z\['teilnahme'\]\)/fn(\\\$z) => true/" \
  $K "Kacheln: genau die Eingeladenen, die teilnehmen (1, 4)"
mut K7 $BU 's/    if \(slot_nur_eingeladene\(\$phase, \$rolle\)\) \{\n        return \[/    if (false) {\n        return [/' \
  $K "Kacheln: keine Unterrichtenden daneben"
mut K8 $BU 's/fn\(\$z\) => !in_array\(\(int\)\$z\[.lehrer_id.\], \$bekannt, true\)/fn(\$z) => true/' \
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
# KF4, KF5, Z41 seit v0.9.61 auf buchenLehrerAbschnitte() bzw. die Schleife
# über die Abschnitte – die Reihenfolge steht jetzt dort (gleiche Absicht).
mut KF4 frontend/app.js 's/lehrer: \(liste\.eingeladen \|\| \[\]\)\.concat\(liste\.unterrichtend \|\| \[\]\)/lehrer: (liste.unterrichtend || []).concat(liste.eingeladen || [])/' \
  $KF "Reihenfolge: eingeladen, unterrichtend, Sonderrolle"
mut KF5 frontend/app.js 's/lehrer: \(liste\.eingeladen \|\| \[\]\)\.concat/lehrer: ([]).concat/' \
  $KF "Phase 1: genau die Eingeladene erscheint"
mut KF6 frontend/app.js 's/const alle = buchenLehrerAlle\(S\.lehrerListe\);/const alle = (S.lehrerListe.unterrichtend || []).concat(S.lehrerListe.sonderlehrer || []);/' \
  $KF "ansichtBuchen() bildet die Liste über buchenLehrerAlle()"
mut KF7 frontend/app.js 's/\} else if \(!S\.lehrerListe\.nur_eingeladene\n\s*&& /} else if (/' \
  $KF "„keine Lehrkräfte hinterlegt“ nicht in Phase 1"

S=tests/run_messung_sitzung.php
MI=backend/api/mitteilungen.php
MS=backend/api/messung_sitzung.php
IX=backend/api/index.php

# S1–S3 und S12 (Gründe aus mit_rest_aus_sitzung) sind seit v0.9.72 die
# Serie SZ unten: Die Funktion ist durch wu_sitzung() im Adapter ersetzt.

echo "== Messung Sitzung: Deutung und Nachprobe"
mut S4 $MS "s/\\\$status === 0 \? 'netz'/\\\$status === 0 ? 'anmeldeseite'/" \
  $S "Nachprobe: unerreichbar ist art netz, Status 0"
mut S5 $MS "s/\\\$fehler = \\\$grund !== null && str_starts_with\(\\\$grund, 'fehler: '\);/\\\$fehler = false;/" \
  $S "Ausnahme wird als Fehler gedeutet, nicht als Ablauf"
mut S6 $MS "s/: \(!\\\$z\['liste_gefunden'\] \?/: (false ?/" \
  $S "Status 200 ohne Liste: „KEIN Befund“"
mut S7 $MS "s/'kind'        => 'Kind ' \. \(\\\$i \+ 1\),/'kind' => 'Kind ' . (\\\$i + 1), 'id' => \\\$kid,/" \
  $S "Antwort ohne Kennungen der Kinder"
mut S8 $MS "s/if \(\(\\\$u\['rolle'\] \?\? ''\) !== 'eltern'\) \{/if (false) {/" \
  $S "Lehrkraft: Stundenplan entfällt, kein Abruf"

echo "== Messung Sitzung: Route"
# S9 seit v0.9.68 verankert an „$probe“: dasselbe Muster steht jetzt auch in
# der Route /api/schueler-gruppen (weiter oben) – ohne Anker traf es dort.
mut S9 $IX 's/\$grund = \$sitzung\[\x27grund\x27\];/\$grund = null;/' \
  $S "Route reicht den Grund aus wu_sitzung() durch"
mut S10 $IX "s/\\\$probe = \\\$grund === 'kein_token'/\\\$probe = \\\$grund === 'nie'/" \
  $S "Route fährt die Nachprobe genau bei kein_token"
mut S11 $IX 's/\$u = auth_require\(\);\n    \$sitzung = wu_sitzung\(\$cfg\);/\$u = auth_user() ?? [];\n    \$sitzung = wu_sitzung(\$cfg);/' \
  $S "Route verlangt eine Anmeldung"

echo "== Messung Klassenleitung (Zug 3)"
mut S13 $MS 's/fn\(\$i\) => isset\(\$ids\[\$i\]\)/fn(\$i) => false/' \
  $S "Kind 1: Kennung passt zu lehrer.webuntis_id, Text zu kuerzel"
mut S14 $MS "s/'gefuellt'       => messung_format\(\\\$v\) !== 'leer',/'gefuellt' => true,/" \
  $S "Kind 2 ohne Klasse: Feld fehlt, nicht gefüllt"
mut S15 $MS "s/return 'Objekt\{' \. implode\(',', \\\$k\) \. '\}';/return json_encode(\\\$v);/" \
  $S "weder Kennung noch Name der Klassenleitung in der Antwort"
# S16 seit v0.9.82 ohne $alt (der Vergleichsmaßstab ist fort) – derselbe
# Eingriff, dieselbe Zeile (run_messung_sitzung.php prüft schon ohne $alt).
mut S16 $IX 's/\$probe, \$lehrer, \$ferien\)\]\);/\$probe, [], \$ferien)]);/' \
  $S "Route reicht den Grund aus wu_sitzung() durch"
mut S17 $MS "s/\\\$nachId\[\(int\)\(\\\$k\['id'\] \?\? 0\)\] \?\? null/\\\$nachId[0] ?? null/" \
  $S "Kind 1: Klassenleitung gefüllt, Format Objekt{id,name}"
mut S18 $MS "s/\(\\\$u\['rolle'\] \?\? ''\) === 'eltern' \? \(array\)\(\\\$u\['kinder'\] \?\? \[\]\) : null/null/" \
  $S "Bericht enthält die Klassenleitung je Kind"

echo "== Messung timetable/filter (Zug 3)"
mut S19 $MS "s/\(string\)\(\\\$paare\[\\\$id\] \?\? ''\) === \\\$kz,/true,/" \
  $S "classTeacher1: beide auf dieselbe Lehrkraft nur 1 (Kennung 8 ist Cd, nicht Xx)"
mut S20 $MS "s/'kuerzel_passt'  => \\\$kz !== '' && isset\(\\\$kuerz\[\\\$kz\]\),/'kuerzel_passt' => false,/" \
  $S "classTeacher1: id passt 2, Kürzel passt 1"
mut S21 $IX 's/(\$ferien = preg_match\([^;]*?\$fv\)) && (preg_match)/$1 || $2/s' \
  $S "Route nimmt den Ferienzeitraum nur mit zwei gültigen Daten"
mut S22 $MS 's/if \(\$sigB\[\$kid\] === \$paar\) \$gleich\+\+;/if (true) \$gleich++;/' \
  $S "Zeitraumvergleich: 2 in beiden, 1 gleich, 1 anders, 1 nur Ferien"
mut S23 $MS "s/'hat_klasse' => \\\$klasse > 0\];/'hat_klasse' => \\\$klasse > 0, 'k' => \\\$klasse];/" \
  $S "keine Kennung (Klasse, Lehrkraft, Kind) und kein Name in der Antwort"
mut S24 $MS "s/\n    if \(\\\$ferien !== null\) \\\$fenster\['ferien'\] = \\\$ferien;//" \
  $S "mit Ferienzeitraum: Schulzeit und Ferien abgefragt"

echo "== Dreiteilung (Zug 3, v0.9.57)"
D=tests/run_dreiteilung.php
FD=tests/frontend_dreiteilung_test.js
SL=backend/api/slots.php
KL=backend/api/klassenleitung.php
APP=frontend/app.js
CSS=frontend/style.css
mut Z1 $SL "s/return \\\$phase === 'phase2';/return \\\$phase !== 'phase1';/" \
  $D "„vorbereitung“: nicht"
mut Z2 $BU 's/"\(\$alias\.teilnahme IS NULL OR \$alias\.teilnahme <> 0\)"/"(\$alias.teilnahme <> 0)"/' \
  $D "teilnahme NULL: teilnehmend"
mut Z3 $BU 's/return \$teilnahme === null \|\| \(int\)\$teilnahme !== 0;/return (int)\$teilnahme !== 0;/' \
  $D "teilnahme NULL: teilnehmend"
mut Z4 $BU 's/WHERE l\.aktiv = 1 AND "/WHERE "/' \
  $D "alle Teilnehmenden (1–7), ohne teilnahme = 0 (8) und ohne Inaktive (9)"
mut Z5 $BU 's/if \(slot_alle_teilnehmenden_buchbar\(\$phase\)\n        &&/if (false\n        \&\&/' \
  $D "jede gezeigte Lehrkraft ist erlaubt (auch Klassenleitung 4 und Weitere 6, 7)"
mut Z6 $BU 's/&& bu_teilnehmende_lehrer\(\$pdo, \$sprechtagId, \[\$lehrerId\]\) !== \[\]\) return true;/\&\& true) return true;/' \
  $D "teilnahme = 0 bleibt abgewiesen (8)"
mut Z7 $BU 's/if \(\$alleBuchbar\) \{\n        \$weitere =/if (true) {\n        \$weitere =/' \
  $D "Vorbereitung: Weitere leer"
mut Z8 $BU 's/(bu_teilnehmende_lehrer\(\$pdo, \$sid\),\n\s*)fn\(\$z\) => !in_array\(\(int\)\$z\[.lehrer_id.\], \$bekannt, true\)/$1fn(\$z) => true/' \
  $D "jede Lehrkraft genau einmal"
mut Z9 $BU 's/array_merge\(\$klVorn, \$rest\)/array_merge(\$rest, \$klVorn)/' \
  $D "2. Klassenleitung zuerst, dann Unterrichtende (4, 5, 2)"
mut Z10 $BU 's/if \(\$alleBuchbar\) \{\n        \$schon/if (true) {\n        \$schon/' \
  $D "Vorbereitung: nicht unterrichtende Klassenleitung fehlt (sonst Kachel ohne Recht)"
mut Z11 $BU 's/in_array\(\(int\)\$z\[.lehrer_id.\], \$kl, true\) \? 1 : 0/0/' \
  $D "Klassenleitung gekennzeichnet (4 und 5)"
mut Z12 $BU 's/\n        \$bekannt\[\] = \(int\)\$z\[.lehrer_id.\];//' \
  $D "jede Lehrkraft genau einmal"
mut Z13 $KL 's/\(int\)\(\$e\[.id.\] \?\? 0\) === \$kindId/(int)(\$e["id"] ?? 0) !== \$kindId/' \
  $D "Kind mit Klasse"
mut Z14 $KL "s/\['classTeacher1', 'classTeacher2'\]/['classTeacher1']/" \
  $D "beide Leitungen"
mut Z15 $KL 's/if \(\$id > 0 && !in_array/if (!in_array/' \
  $D "leeres Objekt und Kennung 0: keine"
mut Z16 $KL 's/catch \(Exception \$e\)/catch (Throwable \$e)/' \
  $D "Programmfehler (Error) wird NICHT verschluckt (catch Exception, nicht Throwable)"
mut Z17 $KL 's/if \(!is_array\(\$json\[.data.\]\[.elements.\] \?\? \$json\[.data.\] \?\? null\)\) \{/if (false) {/' \
  $D "pageconfig ohne Liste ist ein Fehler, nicht „keine Klasse“"
mut Z18 $KL 's/if \(!is_array\(\$tj\[.classes.\] \?\? \$tj\[.data.\]\[.classes.\] \?\? null\)\) \{/if (false) {/' \
  $D "timetable/filter ohne classes[] ist ein Fehler, nicht „keine Leitung“"
mut Z19 $KL "s/error_log\('sprechtag: Klassenleitung nicht ermittelt: ' \. \\\$e\['grund'\]\);/\\\$_SESSION['klassenleitung'][\\\$kindId] = [];/" \
  $D "fehlgeschlagener Abruf wird nicht gemerkt"
mut Z20 $KL 's/if \(isset\(\$_SESSION\[.klassenleitung.\]\[\$kindId\]\)/if (false \&\& isset(\$_SESSION["klassenleitung"][\$kindId])/' \
  $D "zweiter Aufruf kommt aus der Sitzung, ohne WebUntis"
mut Z21 $KL 's/fn\(\$i\) => \$i > 0/fn(\$i) => true/' \
  $D "Kennung 0 trifft nichts, auch wenn webuntis_id 0 im Bestand stünde"
mut Z22 $KL "s/' -27 days'/' -28 days'/" \
  $D "Zeitraum wie gemessen: vier Wochen bis heute, JJJJ-MM-TT"
mut Z23 $BU "s/if \(\\\$u\['rolle'\] === 'eltern' && slot_alle/if (true || \\\$u['rolle'] === 'eltern' \&\& slot_alle/" \
  $D "… nur für Eltern und nur, wenn alle Teilnehmenden buchbar sind"
mut Z24 $BU "s/\?\? ''\)\), \\\$klassenleitung\);/?? '')));/" \
  $D "… und reicht sie an bu_buchbare_lehrer() weiter"
mut Z25 $BU "s/\(string\)\(\\\$body\['jahrgang'\] \?\? ''\), \(string\)\\\$s\['phase'\]\),/(string)(\\\$body['jahrgang'] ?? '')),/" \
  $D "Buchungsroute gibt die Phase an bu_lehrer_erlaubt()"
mut Z26 $BU "s/' \. bu_teilnehmend_sql\('sl'\) \. '/1 = 1/" \
  $D "unterrichtende Lehrkraft mit teilnahme = 0 erscheint nicht (Kind 503, Lehrkraft 8)"
# Z27, Z33, Z37 seit v0.9.58 umgewidmet (Zug 3b): Rand und oberes Suchfeld
# sind entfallen; die Mutation baut jetzt die alte Fassung wieder ein.
mut Z27 $APP "s/const karte = el\('div', 'buchen-kachel'\n/const karte = el('div', 'buchen-kachel' + (istKl ? ' klassenleitung' : '')\n/" \
  $FD "… und keine Randklasse (das Abzeichen genügt)"
mut Z28 $APP "s/    if \(istKl\) karte\.appendChild\(el\('span', 'rolle-badge', 'Klassenleitung'\)\);\n//" \
  $FD "Klassenleitung trägt „Klassenleitung“ genau einmal"
mut Z29 $APP 's/const istKl = Number\(l\.klassenleitung\) === 1;/const istKl = !!l.klassenleitung;/' \
  $FD "klassenleitung 0 als Zahl, als Text oder fehlend: keine Kennzeichnung"
mut Z30 $APP 's/  if \(!q\) \{\n    gitter\.textContent/  if (false) {\n    gitter.textContent/' \
  $FD "ohne Eingabe: keine Kachel"
mut Z31 $APP "s/const q = \(S\.weitereSuche \|\| ''\)\.trim\(\);/const q = (S.weitereSuche || '');/" \
  $FD "nur Leerzeichen gilt als keine Eingabe"
mut Z32 $APP 's/zeichneBuchenKacheln\(gitter, weitere, q\);/zeichneBuchenKacheln(gitter, weitere);/' \
  $FD "nur der Suchtext der Weiteren wirkt auf die Weiteren"
mut Z33 $APP "s/const q = \(suche \|\| ''\)\.trim/const q = (suche || S.buchenSuche || '').trim/" \
  $FD "ohne Suchtext: alle Kacheln – ein Rest von S.buchenSuche filtert nicht"
mut Z34 $APP 's/  if \(weitere\.length > 0\) zeichneWeitereLehrkraefte\(ziel, weitere\);\n//' \
  $FD "ansichtBuchen() zeichnet die Weiteren"
mut Z35 $APP 's/if \(alle\.length === 0 && weitere\.length === 0\) \{/if (alle.length === 0) {/' \
  $FD "leere Gruppen 1–2 brechen nicht ab, wenn es Weitere gibt"
mut Z36 $APP "s/\n    S\.weitereSuche = '';\n    S\.gewaehlteLehrkraft = null;/\n    S.gewaehlteLehrkraft = null;/" \
  $FD "Kindwechsel leert die Suche der Weiteren"
mut Z37 $CSS 's/(\.bk-raum \{)/.buchen-kachel.klassenleitung { border-left: 4px solid var(--akzent); }\n$1/' \
  $FD "kein Rand an der Klassenleitung – keine Regel für .klassenleitung"
mut Z38 $APP "s/block\('buchen-weitere',/block('buchen-weitere-x',/" \
  $FD "ein Block (details), über block() – also eingeklappt, Zustand gemerkt"
mut Z39 $APP 's/S\.weitereSuche = e\.target\.value;\n    zeichneWeitereKacheln\(gitter, weitere\);/S.weitereSuche = e.target.value;/' \
  $FD "Eingabe zeichnet die Treffer im Block"


echo "== Zug 3b: oberes Suchfeld entfallen (v0.9.58)"
KA=tests/frontend_buchen_kacheln_test.js
# Z40 seit v0.9.61 vor der Schleife über die Abschnitte (vorher vor dem
# einen gemeinsamen Gitter, das es nicht mehr gibt).
mut Z40 $APP "s/(    for \(const a of buchenLehrerAbschnitte\(S\.lehrerListe\)\) \{)/    ziel.appendChild(feld('Suchen', 'buchen-suche', 'text', ''));\n\$1/" \
  $FD "Phase 2: genau ein Suchfeld, und es ist das der Weiteren"
mut Z41 $APP 's/      zeichneBuchenKacheln\(gitter, a\.lehrer\);/      zeichneBuchenKacheln(gitter, a.lehrer, S.buchenSuche);/' \
  $FD "Phase 2: alle Kacheln der Gruppen 1–2 stehen ungefiltert da"
mut Z42 $APP "s/S\.lehrerListe = null; S\.lehrerLaedt = false;\n/S.lehrerListe = null; S.lehrerLaedt = false; S.buchenSuche = '';\n/" \
  $KA "kein oberes Suchfeld mehr"

echo "== Zug 3b: mobile Ansicht (v0.9.58)"
MO=tests/frontend_mobil_test.js
HT=frontend/index.html
mut MO1 $CSS 's/z-index: 32; cursor: pointer;/z-index: 20; cursor: pointer;/' \
  $MO "Schleier liegt über der Kopfleiste – auch dort schließt ein Tippen"
mut MO2 $CSS 's/left: 0; z-index: 35;/left: 0; z-index: 31;/' \
  $MO "Menü liegt über dem Schleier"
mut MO3 $CSS 's/\n    width: min\(18rem, 85vw\);\n/\n    width: 240px;\n/' \
  $MO "Menübreite begrenzt, damit daneben Platz zum Tippen bleibt"
mut MO4 $CSS 's/  \.shell\.leiste-zu \.seitenleiste \{ width: min\(18rem, 85vw\); \}\n//' \
  $MO "… auch nach Einklappen am Rechner"
mut MO5 $CSS 's/z-index: 32; cursor: pointer;/z-index: 32;/' \
  $MO "Schleier trägt cursor: pointer"
mut MO6 $CSS 's/\n    height: 100dvh;//' \
  $MO "Menühöhe folgt der sichtbaren Höhe"
mut MO7 $APP "s/\\\$\('#menue-overlay'\)\?\.addEventListener\('click', \(\) => menueSchliessen\(\)\);\n//" \
  $MO "Tippen auf den Schleier ruft menueSchliessen()"
mut MO8 $APP 's/  r\.appendChild\(tab\);\n  return r;/  return tab;/' \
  $MO "tabelleRahmen() legt die Tabelle als einziges Kind in div.tabelle-rahmen"
# MO9 seit v0.9.60 auf die Sonderlehrkräfte-Tabelle: Die bisherige Stelle
# (Login-Protokoll) geht jetzt durch kartenTabelle(); MO35 deckt sie ab.
mut MO9 $APP 's/neu\.appendChild\(tabelleRahmen\(tab\)\);/neu.appendChild(tab);/' \
  $MO "jede Tabelle bekommt ihren Rahmen"
mut MO10 $APP 's/    return tabelleRahmen\(tab\);/    tabelleRahmen(tab);\n    return tab;/' \
  $MO "keine Tabelle wird ohne Rahmen eingehängt"
mut MO11 $CSS 's/\.tabelle-rahmen \{ overflow-x: auto;/.tabelle-rahmen { overflow-x: visible;/' \
  $MO "Rahmen rollt waagrecht"
mut MO12 $CSS 's/\n       min-width: 0; \}/ }/' \
  $MO "Inhalt wächst nicht mit der breitesten Tabelle mit"
mut MO13 $HT 's/, viewport-fit=cover//' \
  $MO "viewport-fit=cover"
mut MO14 $CSS 's/\n         padding-bottom: calc\(3rem \+ env\(safe-area-inset-bottom, 0px\)\);//' \
  $MO "Inhalt hat unten Abstand für Safaris Leiste"
mut MO15 $CSS 's/\n {9}padding-left: max\(1rem, env\(safe-area-inset-left, 0px\)\);//' \
  $MO "… und links/rechts für die Kamera-Aussparung im Querformat"
mut MO16 $CSS 's/\n    padding-bottom: env\(safe-area-inset-bottom, 0px\);//' \
  $MO "Menü unten (Abmelden) nicht unter der Leiste"
mut MO17 $CSS 's/\n {18}padding-left: max\(1rem, env\(safe-area-inset-left, 0px\)\);//' \
  $MO "Kopfleiste links im Querformat nicht unter der Aussparung"
mut MO18 $CSS 's/\n         bottom: calc\(1\.25rem \+ env\(safe-area-inset-bottom, 0px\)\);//' \
  $MO "Kurzmeldung unten über der Leiste"
mut MO19 $CSS 's/\n         padding-bottom: 3rem;//' \
  $MO "jede env()-Deklaration hat davor einen Rückfall ohne env()"
mut MO20 $CSS 's/\@media \(max-width: 760px\) \{\n  \/\* Querformat/\@media (max-width: 761px) {\n  \/* Querformat/' \
  $MO "Voraussetzung: Medienabfrage für schmale Bildschirme gefunden"
# v0.9.59: Grundbreite – das Auswahlfeld schneidet seinen Inhalt ab.
# Entfernt; nur noch im Kommentar; in eine andere Regel verschoben.
mut MO21 $CSS 's/ border-radius: 6px;\n         overflow: hidden; \}/ border-radius: 6px; }/' \
  $MO "select schneidet seinen Inhalt ab"
mut MO22 $CSS 's/\n         overflow: hidden; \}/ \/* overflow: hidden; *\/ }/' \
  $MO "select schneidet seinen Inhalt ab"
mut MO23 $CSS 's/\n         overflow: hidden; \}\nselect\.konflikt \{ border-width: 2px; \}/ }\nselect.konflikt { border-width: 2px; overflow: hidden; }/' \
  $MO "select schneidet seinen Inhalt ab"

# v0.9.60 – Meine Termine gibt keine falsche Auskunft (C1)
MT=tests/frontend_meine_termine_test.js
mut MT1 $APP 's/  meineBuchungen: null,\n/  meineBuchungen: [],\n/' \
  $MT "Startwert der eigenen Termine ist „nicht geladen“"
mut MT2 $APP 's/(function zeichneTermineKompakt\(ziel\) \{\n(?:  \/\/[^\n]*\n)*)  if \(S\.meineBuchungen === null\) \{/$1  if (false) {/' \
  $MT "Startzustand: Übersicht lädt die Termine"
mut MT3 $APP 's/    S\.meineBuchungen = null; S\.meineLaedt = false;\n    if \(beiWechsel\)/    if (beiWechsel)/' \
  $MT "Wechsel des Sprechtags verwirft die Termine des vorigen"
mut MT4 $APP 's/if \(!S\.aktiverSprechtag \|\| S\.aktiverSprechtag\.id !== sid\) return;/if (false) return;/' \
  $MT "Antwort für einen inzwischen abgewählten Sprechtag wird verworfen"
mut MT5 $APP 's/  location\.replace\(location\.pathname\);/  S.user = null; S.ansicht = \x27login\x27;\n  zeichne();/' \
  $MT "Abmelden lädt die Seite ohne Hash neu"
mut MT6 $APP 's/  location\.replace\(location\.pathname\);/  location.replace(location.pathname + location.hash);/' \
  $MT "Abmelden lädt die Seite ohne Hash neu"

# v0.9.60 – halbtags in GET /api/sprechtage/{id}/lehrer (C2). Der Kommentar
# im Zweig nennt „l.halbtags“ weiter: SL1 belegt, dass er nicht zählt.
# Verankert an sl.id AS zuweisung_id: „l.name, l.halbtags,“ steht auch in
# der Abfrage von /api/anzeige – ohne Anker traf die Ersetzung DORT (erster
# Lauf: SL1/SL2 nicht angeschlagen, weil die Route unverändert war).
LS=tests/run_sprechtag_lehrer.php
IDX=backend/api/index.php
mut SL1 $IDX 's/l\.name, l\.halbtags,(\n\s+sl\.id AS zuweisung_id)/l.name,$1/' \
  $LS "jede Zeile führt halbtags"
mut SL2 $IDX 's/l\.name, l\.halbtags,(\n\s+sl\.id AS zuweisung_id)/l.name, 0 AS halbtags,$1/' \
  $LS "Halbtagskraft 1, andere 0"

# v0.9.60 – gemessene Überläufe (Teil B)
mut MO24 $CSS 's/;\n            flex-wrap: wrap; \}/; }/' \
  $MO "Knopfzeile bricht um"
mut MO25 $CSS 's/\n  max-width: 100%;   \/\* sonst bei 320 px[^\n]*//' \
  $MO "Dateifeld nie breiter als sein Platz"
mut MO26 $CSS 's/minmax\(min\(15rem, 100%\), 1fr\)/minmax(15rem, 1fr)/' \
  $MO "Schülerliste: Spaltenmindestbreite nie über dem Platz"

# v0.9.60 – Karten statt Rollen (Teil A)
mut MO27 $APP 's/if \(titel\[i\]\) td\.setAttribute\(\x27data-label\x27, titel\[i\]\);/if (titel[i]) td.title = titel[i];/' \
  $MO "kartenTabelle(): jede Zelle trägt die Überschrift ihrer Spalte als data-label"
mut MO28 $APP 's/\n      else td\.classList\.add\(\x27karte-aktion\x27\);//' \
  $MO "… die Spalte ohne Überschrift (Knöpfe) wird Fußzeile der Karte"
mut MO29 $APP 's/\n  return tabelleRahmen\(tab\);\n\}/\n  return tab;\n}/' \
  $MO "… Kopfzeile gekennzeichnet, Tabelle als „karten“, im rollenden Rahmen"
mut MO30 $APP 's/ziel\.appendChild\(kartenTabelle\(tab\)\);/ziel.appendChild(tabelleRahmen(tab));/' \
  $MO "Karten genau in Meine Termine, Einladungen, Login-Protokoll, Mitteilungen"
mut MO31 $CSS 's/\.tabelle\.karten tr \{ display: block;/.tabelle.karten tr { display: table-row;/' \
  $MO "schmal: jede Zeile ein Block"
mut MO32 $CSS 's/\.tabelle\.karten tr\.kopfzeile \{ display: none; \}/.tabelle.karten tr.kopfzeile { }/' \
  $MO "schmal: Kopfzeile ausgeblendet"
mut MO33 $CSS 's/content: attr\(data-label\);/content: "";/' \
  $MO "schmal: Beschriftung aus data-label vor dem Wert"
mut MO35 $APP 's/box\.appendChild\(kartenTabelle\(tab\)\);/box.appendChild(tab);/' \
  $MO "keine Tabelle wird ohne Rahmen eingehängt"
mut MO34 $CSS 's/\z/\n.tabelle.karten tr { display: block; }\n/' \
  $MO "Kartenregeln nur in der Medienabfrage der Telefonansicht"

# v0.9.61 – Teil A: Rolle an der Lehrkraft-Liste, kein halbtags in der Anzeige
mut R1 $IDX 's/(Verwaltung \(Lehrkräfte & Räume\)\.\n        if \(\$methode === \x27GET\x27\) \{\n            )auth_require_admin\(\);/$1auth_require();/' \
  $LS "Eltern: abgewiesen (403), keine Lehrkraftdaten"
mut R2 $IDX 's/(Verwaltung \(Lehrkräfte & Räume\)\.\n        if \(\$methode === \x27GET\x27\) \{\n            )auth_require_admin\(\);/$1if (false) auth_require_admin();/' \
  $LS "Lehrkraft: abgewiesen (403)"
mut R3 $IDX 's/\x27SELECT l\.kuerzel, l\.name,\n                sl\.anwesend_von/\x27SELECT l.kuerzel, l.name, l.halbtags,\n                sl.anwesend_von/' \
  $LS "Anzeige (öffentlich) liefert kein halbtags"
mut R4 $IDX 's/(\x27SELECT l\.kuerzel, l\.name,\n                )sl\.anwesend_von, sl\.anwesend_bis,/$1/' \
  $LS "… aber alles, was die Anzeige liest"

# v0.9.61 – Teil B: Vierteilung, Sonderrollen abgesetzt
mut V1 $APP 's/lehrer: \(liste\.eingeladen \|\| \[\]\)\.concat\(liste\.unterrichtend \|\| \[\]\) \}/lehrer: (liste.eingeladen || []).concat(liste.unterrichtend || []).concat(liste.sonderlehrer || []) }/' \
  $FD "buchenLehrerAbschnitte(): zwei Abschnitte"
mut V2 $APP 's/  \]\.filter\(\(a\) => a\.lehrer\.length > 0\);/  ];/' \
  $FD "… leere Abschnitte entfallen"
mut V3 $APP 's/\n        \+ \(a\.art === \x27sonderrollen\x27 \? \x27 buchen-sonderrollen\x27 : \x27\x27\)\);/);/' \
  $FD "ansichtBuchen(): Sonderrollen in eigenem Gitter NACH den Unterrichtenden"
mut V4 $APP 's/    for \(const a of buchenLehrerAbschnitte\(S\.lehrerListe\)\) \{\n.*?\n    \}\n/    const gitter = el(\x27div\x27, \x27buchen-gitter\x27);\n    zeichneBuchenKacheln(gitter, alle);\n    ziel.appendChild(gitter);\n/s' \
  $FD "ansichtBuchen(): Sonderrollen in eigenem Gitter NACH den Unterrichtenden"
mut V5 $APP 's/(      zeichneBuchenKacheln\(gitter, a\.lehrer\);\n      ziel\.appendChild\(gitter\);\n    \}\n)/$1    const g2 = el(\x27div\x27, \x27buchen-gitter\x27);\n    zeichneBuchenKacheln(g2, alle);\n    ziel.appendChild(g2);\n/' \
  $FD "… jede Lehrkraft genau einmal"
mut V6 $APP 's/return buchenLehrerAbschnitte\(liste\)\.flatMap\(\(a\) => a\.lehrer\);/return buchenLehrerAbschnitte(liste)[0].lehrer;/' \
  $KF "Reihenfolge: eingeladen, unterrichtend, Sonderrolle"

# v0.9.62 – Teil B: Zeile der Lehrkraft-Tabelle behält ihre Ausrichtung
LZ=tests/frontend_lehrer_zeilen_test.js
mut LZ1 $CSS 's/\ninput\.zeit-feld \{ display: inline-block;/\n.zeit-feld { display: inline-block;/' \
  $LZ "Zeitfeld-Regel ist so spezifisch wie die allgemeine (input.zeit-feld) und steht DANACH"
mut LZ2 $CSS 's/input\.zeit-feld \{ display: inline-block;/input.zeit-feld { display: block;/' \
  $LZ "… Zeitfeld bleibt in der Zeile und knapp"
mut LZ3 $CSS 's/\.zeitfenster-felder \{ display: inline-flex;/.zeitfenster-felder {/' \
  $LZ "Zeitfelder stehen neben dem Uhr-Knopf"
mut LZ4 $APP 's/const td = el\(\x27td\x27, \x27anwesenheit-zelle\x27\);/const td = el(\x27td\x27);/' \
  $LZ "Anwesenheitszelle trägt ihre Klasse"
mut LZ5 $CSS 's/\.anwesenheit-zelle \{ white-space: nowrap; \}/.anwesenheit-zelle { }/' \
  $LZ "… und bricht nicht um"

# v0.9.63 – Abstände zwischen Abschnitten: zwei Werte, eine Regel
AB=tests/frontend_abstaende_test.js
mut AB1 $CSS 's/--abstand-abschnitt: 2rem;/--abstand-abschnitt: 1rem;/' \
  $AB "zwei Werte an einer Stelle"
mut AB2 $CSS 's/\.block \+ \.block \{ margin-top: var\(--abstand-innen\); \}/.block + .block { margin-top: var(--abstand-abschnitt); }/' \
  $AB "genau eine Regel setzt den Abstand zwischen Abschnitten"
mut AB3 $CSS 's/h3, button\):not\(:first-child\) \{\n  margin-top: var\(--abstand-abschnitt\);/h3):not(:first-child) {\n  margin-top: var(--abstand-abschnitt);/' \
  $AB "… sie gilt in Ansicht, Sektion und Block"
mut AB4 $CSS 's/\n:where\(#ansicht, \.sektion, details\.block, form\) > :where\(\.block/\n:is(#ansicht, .sektion, details.block, form) > :where(.block/' \
  $AB "… ohne Spezifität aus Behälter oder Abschnitt"
mut AB5 $CSS 's/\n:where\(#ansicht, \.sektion, details\.block, form\) > :where\(h2, h3, h4\) \+ [^\n]*\n  margin-top: var\(--abstand-innen\);\n\}//' \
  $AB "nach einer Überschrift der kleinere Wert"
mut AB6 $CSS 's/\.block > summary \+ \* \{ padding-top: 1rem; margin-top: 0; \}/.block > summary + * { padding-top: 1rem; }/' \
  $AB "nach der Titelzeile eines Blocks trägt die Polsterung"
mut AB7 $CSS 's/\n\.block \+ \.block \{ margin-top: var\(--abstand-innen\); \}//' \
  $AB "gleichartige Blöcke bleiben eine Liste"
mut AB8 $CSS 's/           padding: 1\.1rem 1\.3rem; \}/           padding: 1.1rem 1.3rem; margin: 0 0 1.1rem; }/' \
  $AB "keine Bausteine mit eigenem Außenabstand"
mut AB9 $CSS 's/\z/\n.buchen-sonderrollen { margin-top: 2rem; }\n/' \
  $AB "keine Sonderregel mehr für die Sonderrollen"

# v0.9.64 – Treffer gehören zum Suchfeld; Feld nach Feld (Abstandsregel)
mut AB10 $CSS 's/\n:where\(#ansicht, \.sektion, details\.block, form\) > \.suchtreffer:not\(:first-child\) \{\n  margin-top: var\(--abstand-innen\);\n\}//' \
  $AB "Treffer unter dem Suchfeld: der kleinere Wert"
mut AB11 $APP 's/el\(\x27div\x27, \x27sv-treffer suchtreffer\x27\)/el(\x27div\x27, \x27sv-treffer\x27)/' \
  $AB "… an beiden Stellen gekennzeichnet"
mut AB12 $CSS 's/> :where\(label, \.zeile\):not\(:first-child\) \{/> :where(label, .zeile) {/' \
  $AB "Feld nach Feld: der kleinere Wert"
mut AB13 $CSS 's/\.zeile > label \{ margin: 0; \}/.zeile > label { margin-bottom: 0; }/' \
  $AB "… in der Eingabezeile tragen Labels keinen eigenen Abstand"
mut AB14 $CSS 's/\.sv-treffer-liste \{/.sv-treffer { margin: .3rem 0 .6rem; }\n.sv-treffer-liste {/' \
  $AB "keine Bausteine mit eigenem Außenabstand"

# v0.9.64 – Messung profile/general (Gruppe der angemeldeten Person)
# Suite und Datei wie oben: $S, $MS.
mut PF1 $MS 's/\n    \$bericht\[\x27profil\x27\] = messung_profil\([^\n]*\n/\n/' \
  $S "Bericht: Profil für jede Rolle gemessen"
mut PF2 $MS 's/    \$bericht\[\x27profil\x27\] = messung_profil\(/    if ((\$u[\x27rolle\x27] ?? \x27\x27) === \x27eltern\x27) \$bericht[\x27profil\x27] = messung_profil(/' \
  $S "Bericht: Profil für jede Rolle gemessen"
mut PF3 $MS 's/if \(\$person && !\$gruppenschluessel && [^\n]*\) continue;/if (false) continue;/' \
  $S "Personenangabe direkt an einer Gruppe: übersprungen"
mut PF4 $MS 's/\$mitWert = messung_ist_gruppe\(\$pfad\) \|\| messung_ist_gruppe\(\$eltern\);/\$mitWert = true;/' \
  $S "… auch in einer Gruppe keine Personennamen und keine Kennung eines Mitglieds"
mut PF5 $MS 's/\$aus\[\x27schluessel\x27\] = \$schluessel;/\$aus[\x27schluessel\x27] = \$p;/' \
  $S "keine Personenangaben"
mut PF6 $MS 's/\x27Status 200, aber kein data\.profile – Antwortform prüfen \(z\. B\. Anmeldeseite\)\. KEIN Befund\.\x27/\x27Status 200 – kein Zugriff über diese Sitzung.\x27/' \
  $S "ohne data.profile: KEIN Befund"
mut PF7 $MS 's/\x27gefuellt\x27  => \$ug !== null && \$ug !== \x27\x27 && \$ug !== \[\],/\x27gefuellt\x27  => \$ug !== null,/' \
  $S "leere userGroup: vorhanden, aber nicht gefüllt"

# v0.9.65 – wer als Schüler:in selbst buchen darf (E15)
SG=tests/run_schueler_gruppe.php
FSG=tests/frontend_schueler_gruppe_test.js
SLP=backend/api/slots.php
BUP=backend/api/buchungen.php
mut SG1 $SLP 's/\n    if \(\(\$u\[\x27rolle\x27\] \?\? \x27\x27\) !== \x27schueler\x27\) return null;//' \
  $SG "Eltern mit eigener Gruppe"
mut SG2 $SLP 's/\x27\/\^\.\{0,20\}\/us\x27/\x27\/^.*\/us\x27/' \
  $SG "„SuS über 18 mit Attest“ → „SuS über 18 mit Atte“"
mut SG3 $SLP 's/\x27\/\^\.\{0,20\}\/us\x27/\x27\/^.{0,19}\/us\x27/' \
  $SG "Grenze: genau 20 Zeichen bleiben"
mut SG4 $SLP 's/if \(\$g === \x27\x27\) return \x27gruppe_unbekannt\x27;/if (\$g === \x27\x27) return null;/' \
  $SG "Schüler:in ohne ermittelte Gruppe: gesperrt"
mut SG5 $SLP 's/(\n    return in_array\(gruppe_normalisieren\(\$g\), \$zugelassen, true\))/\n    if (\$zugelassen === []) return null;$1/' \
  $SG "leere Liste: Schüler:innen gesperrt"
mut SG6 $BUP 's/if \(\$sperre !== null\) bu_gesperrt_antwort\(\$sperre\);/if (false) bu_gesperrt_antwort(\$sperre);/' \
  $SG "Kacheln, nicht zugelassen"
mut SG7 $BUP 's/if \(\$sperre !== null\) json_err\(bu_sperre_text\(\$sperre\), 403\);/if (false) json_err(bu_sperre_text(\$sperre), 403);/' \
  $SG "Buchen, nicht zugelassen: 403"
mut SG8 $IDX 's/implode\("\\n", gruppen_liste\(\$roh\)\)/\$roh/' \
  $SG "POST: gespeichert wird, was verglichen wird"
mut SG9 backend/api/webuntis_adapter.php 's/\n            \$ergebnis\[\x27wu_gruppe\x27\] = wu_profil_gruppe\(\$rest\);//' \
  $SG "wu_login() liest die Gruppe"
mut SG10 backend/api/auth.php 's/\n        \x27wu_gruppe\x27 => isset\(\$_SESSION\[\x27wu_gruppe\x27\]\)[^\n]*\n[^\n]*\n/\n/' \
  $SG "Sitzung trägt die Gruppe"
mut FSG1 $APP 's/  if \(S\.lehrerListe\.buchen_gesperrt\) \{/  if (false) {/' \
  $FSG "Ansicht bei Sperre"
mut FSG2 $APP 's/\} else if \(S\.lehrerListe\.buchen_gesperrt\) \{\n      meldung\(null\);/} else if (false) {\n      meldung(null);/' \
  $FSG "Laden bei Sperre"
mut FSG3 $APP 's/\? \x27Ihr eigenes Konto trägt in WebUntis die Gruppe „\x27 \+ d\.eigene_gruppe \+ \x27“ – \x27/? \x27Ihr eigenes Konto trägt eine Gruppe – \x27/' \
  $FSG "nennt die Gruppe des eigenen Kontos"
mut FSG4 $APP 's/  if \(gewaehlt\.length === 0\) \{/  if (false) {/' \
  $FSG "leere Liste: Warnung"
mut FSG5 $APP 's/\n  zeichneSchuelerGruppen\(ziel\);\n/\n/' \
  $FSG "… zeigt genau einmal die Benutzergruppen"

# v0.9.66 – Ladereihenfolge in index.php; geladener Zustand der Ansicht
LR=tests/run_ladereihenfolge.php
mut LR1 $IDX 's/\x27gruppen\x27       => bu_zugelassene_gruppen\(\$pdo\),/\x27gruppen\x27       => bu_lehrer_fenster(\$pdo, 0, 0),/' \
  $LR "jeder Funktionsaufruf in index.php ist an seiner Stelle schon definiert"
mut LR2 $IDX 's/\x27gruppen\x27       => bu_zugelassene_gruppen\(\$pdo\),/\x27gruppen\x27       => bu_lehrer_fenster(\$pdo, 0, 0),/' \
  $LR "GET /api/schueler-gruppen in Betriebsladereihenfolge"
mut LR3 $IDX 's/(if \(\(\$seg\[0\] \?\? \x27\x27\) === \x27login-log\x27\) \{\n    auth_require_admin\(\);\n)/$1    bu_sprechtag(db(\$cfg), 0);\n/' \
  $LR "jeder Funktionsaufruf in index.php ist an seiner Stelle schon definiert"
mut FSG6 $APP 's/S\.sgFehler = String\(f\.message\) \|\| \x27unbekannter Fehler\x27; zeichne\(\);/toast(String(f.message), \x27fehler\x27);/' \
  $FSG "Abruf scheitert: Fehler steht da"
mut FSG7 $APP 's/  if \(S\.sgFehler\) \{/  if (false) {/' \
  $FSG "… kein erneuter Abruf von selbst"

# v0.9.67 – Messung userrole/config (Benutzergruppen der Schule)
mut UG1 $MS 's/\} elseif \(is_array\(\$w\)\) \{\n            \$rolle = null;/} elseif (false) {\n            \$rolle = null;/' \
  $S "userCountByUserRole als Liste (erfunden): ebenfalls erkannt"
mut UG2 $MS 's/if \(\$g\[\x27label\x27\] === \$eigene\) \{/if (\$angl(\$g[\x27label\x27]) === \$angl(\$eigene)) {/' \
  $S "… nur nach Angleichen gefunden"
mut UG3 $MS 's/\x27erste_abweichung\x27 => \$pos \+ 1,/\x27erste_abweichung\x27 => \$pos,/' \
  $S "… nur nach Angleichen gefunden"
mut UG4 $MS 's/count\(array_filter\(\$gruppen, fn\(\$g\) => \$g\[\x27schueler\x27\] > 0\)\)/count(\$gruppen)/' \
  $S "Gruppen mit Schülern gezählt"
mut UG5 $MS 's/    \$bericht\[\x27benutzergruppen\x27\] = messung_benutzergruppen\(/    if ((\$u[\x27rolle\x27] ?? \x27\x27) === \x27eltern\x27) \$bericht[\x27benutzergruppen\x27] = messung_benutzergruppen(/' \
  $S "Bericht: für jede Rolle gemessen, Abgleich"
mut UG6 $MS 's/(\x27schueler\x27  => \(int\)\(\$zahlen\[\x27STUDENT\x27\] \?\? 0\),)/$1 \x27roh\x27 => \$e,/' \
  $S "keine Personenangaben (Mitglieder einer Gruppe erscheinen nicht)"
mut UG7 $MS 's/: \x27Status 200, aber kein data\.userGroups – Antwortform prüfen\. KEIN Befund\.\x27\);/: \x27Status 200 – kein Zugriff über diese Sitzung.\x27);/' \
  $S "keine Liste: KEIN Befund"

# v0.9.68 – Auswahlliste aus userrole/config
mut AW1 $SLP 's/        return strcmp\(\(string\)\$a\[\x27label\x27\], \(string\)\$b\[\x27label\x27\]\);/        return 0;/' \
  $SG "Sortierung: Gruppen mit Schülern zuerst"
mut AW2 backend/api/webuntis_adapter.php 's/\x27schueler\x27  => \(int\)\(\$je\[\x27STUDENT\x27\] \?\? 0\),/\x27schueler\x27  => (int)(\$e[\x27userCount\x27] ?? 0),/' \
  $SG "Sortierung: Gruppen mit Schülern zuerst"
# AW3 seit v0.9.72: die Sitzung kommt als fn() => wu_sitzung($cfg)
mut AW3 $IDX 's/json_ok\(\$auskunft\(\) \+ schueler_gruppen_auswahl\(fn/json_ok(\$auskunft() + (fn(callable \$f): array => [])(fn/' \
  $SG "Route GET: Gruppen, eigene Gruppe UND Auswahl"
mut AW4 backend/api/webuntis_adapter.php 's/\n    if \(\$status !== 200\) return \[\x27gruppen\x27 => null, \x27fehler\x27 => \x27Status \x27 \. \$status\];//' \
  $SG "… nicht lesbar: 403, keine Liste, Ausnahme"
mut FAW1 $APP 's/kaestchen\(b, g\.label, g\.label \+ \x27 — \x27 \+ g\.schueler \+ \x27 Schüler\x27\);/kaestchen(b, g.label, g.label);/' \
  $FSG "Gruppen mit Schülern zuerst, mit Anzahl"
mut FAW2 $APP 's/for \(const name of gewaehlt\.filter\(\(n\) => !auswahl\.some\(\(g\) => g\.label === n\)\)\) \{/for (const name of []) {/' \
  $FSG "Auswahl statt Textfeld: je Gruppe ein Kästchen, keine ausgeblendet"
mut FAW3 $APP 's/\} else if \(g && g\.userRole !== -1\) \{/} else if (false) {/' \
  $FSG "systemeigene Gruppe gewählt"
mut FAW4 $APP 's/: kaesten\.filter\(\(\[cb\]\) => cb\.checked\)\.map/: kaesten.map/' \
  $FSG "Speichern schickt die angehakten Namen"
mut FAW5 $APP 's/Im persönlichen Bereich zeigt WebUntis den vollständigen Namen \x27/Im persönlichen Bereich zeigt WebUntis den Namen \x27/' \
  $FSG "Hinweis: WebUntis zeigt im persönlichen Bereich den vollständigen Namen"
mut FAW6 $APP 's/    if \(d\.auswahl_fehler\) \{/    if (false) {/' \
  $FSG "Abruf gescheitert: Grund steht da"

# v0.9.69 – Messung recipientOption PARENTS (verschickt eine echte Nachricht)
MP=tests/run_messung_parents.php
mut MP1 $MS 's/if \(\(\$eingabe\[\x27bestaetigt\x27\] \?\? null\) !== true\) \{/if (false) {/' \
  $MP "ohne Bestätigung, mit unbekanntem Pfad, ohne Kind-Kennung: KEIN Versand"
# MP2 seit v0.9.72 im Betriebskörper (mit_parents_koerper) – eine Quelle.
mut MP2 $MI 's/\x27copyToStudent\x27       => false,/\x27copyToStudent\x27       => true,/' \
  $MP "PARENTS, die Kennung des Kindes, ohne Kopie an das Kind"
mut MP3 $MS 's/\$aus\[\x27ergebnis\x27\] = is_int\(\$n\) \? \(\$n >= 1 \? \x27erreicht\x27 : \x27niemand\x27\) : \x27unklar\x27;/\$aus[\x27ergebnis\x27] = \x27erreicht\x27;/' \
  $MP "200 mit 0: angenommen, niemand erreicht"
mut MP4 $MS 's/(\x27konten\x27 => count\(\(array\)\(\$nw\[\x27ids\x27\] \?\? \[\]\)\),)/$1 \x27ids\x27 => \$nw[\x27ids\x27] ?? [],/' \
  $MP "keine Personenangaben"
mut MP5 $IDX 's/(\x27parents\x27\) \{\n    \$u = )auth_require_lehrkraft\(\);/$1auth_require();/' \
  $MP "Eltern können die Messung nicht auslösen"
mut MP6 $MS 's/    \$antwort = \$s\[\x27rest\x27\]->postMultipart\(/    \$s[\x27rest\x27]->postMultipart(MESSUNG_PARENTS_PFADE[\x27messages\x27], messung_parents_koerper(\$kind));\n    \$antwort = \$s[\x27rest\x27]->postMultipart(/' \
  $MP "Pfad users: genau EIN Versand"
mut MP7 $MS 's/(    \x27messages\x27 => \x27\/WebUntis\/api\/rest\/view\/v2\/messages\x27,)/$1\n    \x27beides\x27 => \x27\/WebUntis\/api\/rest\/view\/v2\/messages\/users\x27,/' \
  $MP "ohne Bestätigung, mit unbekanntem Pfad, ohne Kind-Kennung: KEIN Versand"
# v0.9.72 – nur noch die eigene Sitzung. MP8–MP11 der v0.9.70 entfallen mit
# dem Dienstkonto; ihre Nummern werden nicht neu vergeben.
mut MP12 $MS 's/if \(\$welche !== \x27eigene\x27\) \{/if (false) {/' \
  $MP "sitzung dienstkonto: kein Versand"
mut MP13 $MS 's/\$nw = \$namensweg\(\$kind, \$s\[\x27rest\x27\]\);/\$nw = \$namensweg(\$kind, null);/' \
  $MP "der Namensweg sucht über DIESELBE Sitzung"
mut MP14 $MS 's/    return mit_parents_koerper\(\$kind, /    return [\x27copyToStudent\x27 => true] + mit_parents_koerper(\$kind, /' \
  $MP "eine Quelle: der Messkörper ist der Betriebskörper"

# v0.9.71 – Messung Liste auflösen/senden über die eigene Sitzung (Testliste)
ML=tests/run_messung_liste.php
mut ML1 $MS 's/if \(count\(\$ids\) > MESSUNG_LISTE_HOECHSTENS\) \{/if (false) {/' \
  $ML "mehr als 5 Empfänger: KEIN Versand"
mut ML2 $MS 's/if \(\$typ !== \x27QUICK\x27\) return/if (false) return/' \
  $ML "nur QUICK, nur mit Listen-Kennung, nur bekannte Schritte"
mut ML3 $MS 's/if \(\$schritt === \x27senden\x27 && \(\$eingabe\[\x27bestaetigt\x27\] \?\? null\) !== true\) \{/if (false) {/' \
  $ML "ohne Bestätigung: kein Versand"
mut ML4 $MS 's/(    \$antwort = \$s\[\x27rest\x27\]->postMultipart\(\x27\/WebUntis\/api\/rest\/view\/v2\/messages\/users\x27, messung_liste_koerper\(\$ids\)\);)/    \$s[\x27rest\x27]->postMultipart(\x27\/WebUntis\/api\/rest\/view\/v2\/messages\/users\x27, messung_liste_koerper(\$ids));\n$1/' \
  $ML "bestätigt: genau EIN Versand"
mut ML5 $MS 's/(\x27seiten\x27 => \(int\)\$res\[\x27seiten\x27\])\];/$1, \x27ids\x27 => \$ids];/' \
  $ML "keine Personenangaben: weder Kennungen noch Namen der Empfänger"
mut ML6 $IDX 's/(\x27liste\x27\) \{\n    \$u = )auth_require_lehrkraft\(\);/$1auth_require();/' \
  $ML "Eltern können die Messung nicht auslösen"

# ============================================================
# v0.9.72 (E17) – Dienstkonto abgeschafft; die abgelaufene Sitzung
# ============================================================
AD=backend/api/webuntis_adapter.php
ER=backend/api/erinnerungen.php
SZ=tests/run_sitzungszugang.php
SV=tests/run_sitzung_versand.php
FS=tests/frontend_sitzung_abgelaufen_test.js

echo "== Sitzungszugang: drei Ursachen (wu_sitzung)"
mut SZ1 $AD 's/return \[\x27rest\x27 => null, \x27art\x27 => \x27abgelaufen\x27, \x27grund\x27 => \x27kein_cookie\x27\];/return [\x27rest\x27 => null, \x27art\x27 => null, \x27grund\x27 => \x27kein_cookie\x27];/' \
  $SZ "kein Cookie: abgelaufen"
mut SZ2 $AD 's/if \(\$status === 0 \|\| \$status >= 500\) \{/if (\$status >= 500) {/' \
  $SZ "kein Token, Nachprobe Status 0: nicht_erreichbar"
mut SZ3 $AD 's/\$status === 0 \|\| \$status >= 500/\$status === 0 || \$status > 500/' \
  $SZ "Grenze: Status 500 ist nicht_erreichbar"
mut SZ4 $AD 's/\$status === 0 \|\| \$status >= 500/\$status === 0 || \$status >= 499/' \
  $SZ "Grenze: Status 499 ist noch abgelaufen"
mut SZ5 $AD 's/if \(\$status === 200 && substr_count\(\$text/if (false && substr_count(\$text/' \
  $SZ "kein Token, bei der Nachprobe schon: flüchtig"
mut SZ6 $AD 's/\} catch \(Exception \$e\) \{\n        error_log\(\x27sprechtag: WebUntis über/} catch (Throwable \$e) {\n        error_log(\x27sprechtag: WebUntis über/' \
  $SZ "TypeError im Konstruktor: kaputt"
mut SZ7 $AD 's/(catch \(Error \$e\) \{\n[^\n]*\n        return \[\x27rest\x27 => null, \x27art\x27 => )\x27kaputt\x27/$1\x27abgelaufen\x27/' \
  $SZ "Error im Abruf: kaputt"
mut SZ8 $AD 's/        \$rest->tenantErmitteln\(\);\n        return \[\x27rest\x27 => \$rest/        return [\x27rest\x27 => \$rest/' \
  $SZ "Token da: Client zurück"
mut SZ9 $AD 's/Bitte melden Sie sich neu an\./Bitte erneut versuchen./' \
  $SZ "abgelaufen sagt KLAR"
mut SZ10 $MI 's/(function mit_einreihen\(PDO)/function sz_probe() { dk_lesen([], null); }\n$1/' \
  $SZ "kein Aufruf einer dk_-Funktion mehr"
mut SZ11 $MI 's/(function mit_einreihen\(PDO)/function sz_probe(\$r) { return \$r->tokenHolen(); }\n$1/' \
  $SZ "Token-Abruf nur im Adapter"

echo "== Versand ohne Dienstkonto: Warteschlange, PARENTS, Routen"
mut SV1 $MI 's/return \[\x27ids\x27 => \$ids, \x27status\x27 => \x27offen\x27, /return [\x27ids\x27 => \$ids, \x27status\x27 => \x27fehler\x27, /' \
  $SV "Status offen, Ursache abgelaufen"
mut SV2 $MI 's/\x27sitzung\x27 => \$art, \x27grund\x27 => wu_sitzung_meldung\(\$art\)\]/\x27sitzung\x27 => null, \x27grund\x27 => wu_sitzung_meldung(\$art)]/' \
  $SV "Status offen, Ursache abgelaufen"
mut SV3 $MI 's/\$empfaengerArt, \$schuelerId, \$lehrerId, \$anlass/\$empfaengerArt, \$schuelerId, null, \$anlass/' \
  $SV "der Text geht nicht verloren"
mut SV4 $MI 's/const MIT_PARENTS_PFAD = \x27\/WebUntis\/api\/rest\/view\/v2\/messages\x27;/const MIT_PARENTS_PFAD = \x27\/WebUntis\/api\/rest\/view\/v2\/messages\/users\x27;/' \
  $SV "genau ein Aufruf an /v2/messages mit PARENTS"
mut SV5 $MI 's/if \(is_int\(\$n\) && \$n >= 1\)/if (is_int(\$n) && \$n >= 0)/' \
  $SV "200 mit 0 Empfängern: NICHT gesendet"
mut SV6 $MI 's/if \(is_int\(\$n\) && \$n >= 1\)/if (is_int(\$n) && \$n > 1)/' \
  $SV "Grenze: 1 Empfänger ist erreicht"
mut SV7 $MI 's/if \(\(string\)\(\$m\[\x27empfaenger_art\x27\] \?\? \x27konto\x27\) === \x27eltern\x27\) \{/if (false) {/' \
  $SV "genau ein Aufruf an /v2/messages mit PARENTS"
mut SV8 $IDX 's/AND \(m\.lehrer_id = \?/AND (m.lehrer_id = -?/' \
  $SV "… die eigene Absage gehört zu „meinen Terminen“"
mut SV9 $IDX 's/AND s\.datum >= \?";/AND (s.datum >= ? OR 1)";/' \
  $SV "Hinweis: nur Sprechtage ab heute"
mut SV10 $IDX 's/            \$sql \.= \x27 AND m\.lehrer_id = \?\x27;\n            \$werte\[\] = \(int\)\(\$u\[\x27lehrer_id\x27\] \?\? 0\);\n//' \
  $SV "Hinweis nach der Anmeldung, Lehrkraft: nur die eigenen"
mut SV11 $IDX 's/\x27sitzung\x27 => \$sitzung\[\x27art\x27\], \x27grund\x27 => wu_sitzung_meldung/\x27sitzung\x27 => null, \x27grund\x27 => wu_sitzung_meldung/' \
  $SV "senden, Sitzung abgelaufen (kein Cookie)"
mut SV12 $ER 's/(\x27vollstaendig\x27 => false, \x27sitzung\x27 => )\$sitzung\[\x27art\x27\] \?\? \x27kaputt\x27/$1null/' \
  $SV "Versand bei abgelaufener Sitzung"
mut SV13 $BUP 's/\n        if \(\$sz\[\x27rest\x27\] === null\) json_sitzung_fehlt\(\$sz\);\n        \$aufl/\n        \$aufl/' \
  $SV "stellvertretend: ohne nutzbare Sitzung Abbruch"
mut SV14 $BUP 's/(\$t\[\x27betreff\x27\], \$t\[\x27text\x27\], \$kind, \$lid, \$sz), \x27eltern\x27,(\n\s+kd_name)/$1, \x27konto\x27,$2/' \
  $SV "Einladung: über PARENTS"
mut SV15 $BUP 's/\(int\)\$b\[\x27schueler_id\x27\], \(int\)\$b\[\x27lehrer_id\x27\], wu_sitzung/(int)\$b[\x27schueler_id\x27], null, wu_sitzung/' \
  $SV "Absage: über die Sitzung der absagenden Person"
mut SV16 $AD 's/\x27sitzung\x27 => \$art\], 409\);/\x27sitzung\x27 => \$art], 401);/' \
  $SV "409 mit Meldung und Ursache"
mut SV17 sql/21_dienstkonto_entfernen.sql 's/ADD COLUMN IF NOT EXISTS lehrer_id/ADD COLUMN lehrer_id/' \
  $SV "legt lehrer_id und empfaenger_art an"

echo "== Oberfläche: Kasten am Ort der Handlung, Hinweis nach der Anmeldung"
mut FS1 $APP 's/    f\.sitzung = daten\.sitzung \|\| null;\n//' \
  $FS "409 mit sitzung: Fehler trägt Meldung und Ursache"
mut FS2 $APP 's/  if \(sitzung === \x27abgelaufen\x27\) \{\n    zeigeSitzungsKasten/  if (true) {\n    zeigeSitzungsKasten/' \
  $FS "nicht erreichbar / kaputt: KEIN Kasten"
mut FS3 $APP 's/(\x27noch NICHT verschickt: \x27 \+ m\.grund,\n        \{ knopf: \x27Anmelden und senden\x27, aktion: \(\) => sendeVorgemerkte\()m\.ids/${1}[]/' \
  $FS "… nach der Anmeldung geht DIESELBE Mitteilung hinaus"
mut FS4 $APP 's/aktion: \(\) => stellvertretendBuchen\(lehrerId, slot\)/aktion: async () => {}/' \
  $FS "… nach der Anmeldung derselbe Auftrag"
mut FS5 $APP 's/      los\.disabled = false;\n      meldung\(String\(f\.message\), \x27fehler\x27\);\n      return;/      los.disabled = false;\n      meldung(String(f.message), \x27fehler\x27);/' \
  $FS "falsches Passwort: KEIN Auftrag"
mut FS6 $APP 's/knopf\(\x27Jetzt senden\x27, null, \(\) => sendeVorgemerkte\(h\.ids\)\)/knopf(\x27Jetzt senden\x27, null, () => sendeVorgemerkte([]))/' \
  $FS "„Jetzt senden“ schickt genau diese Kennungen"
mut FS7 $APP 's/\n             && S\.ansicht !== \x27mitteilungen\x27\) \{/) {/' \
  $FS "Hinweis nicht in „Mitteilungen“"
mut FS8 $APP 's/      await ladeSprechtage\(\);\n      ladeOffenHinweis\(\);/      await ladeSprechtage();/' \
  $FS "nach der Anmeldung wird der Hinweis geladen"
mut FS9 $APP 's/aktion: \(\) => sendeVorgemerkte\(ids, text\)/aktion: () => sendeVorgemerkte([], text)/' \
  $FS "… nach der Anmeldung derselbe Aufruf mit denselben Kennungen"
mut FS10 $APP 's/S\.sitzungsKasten && S\.sitzungsKasten\.ansicht === S\.ansicht/S.sitzungsKasten/' \
  $FS "der Kasten steht nur in der Ansicht"

echo "== v0.9.73: Absagen an alle Erziehungsberechtigten (PARENTS)"
mut SV18 $MI 's/return \$schuelerId > 0 \? \x27eltern\x27 : \x27konto\x27;/return \x27konto\x27;/' \
  $SV "mit_absage_art: mit Kind-Kennung an die Eltern"
mut SV19 $MI 's/return \$schuelerId > 0 \? /return \$schuelerId > 1 ? /' \
  $SV "mit_absage_art: mit Kind-Kennung an die Eltern"
mut SV20 $MI 's/return \$schuelerId > 0 \? /return \$schuelerId >= 0 ? /' \
  $SV "mit_absage_art: ohne Kind-Kennung (0)"
mut SV21 $BUP 's/wu_sitzung\(\$cfg\),\n                    mit_absage_art\(\(int\)\$b\[\x27schueler_id\x27\]\),/wu_sitzung(\$cfg), \x27konto\x27,/' \
  $SV "Absage: über die Sitzung der absagenden Person, mit der Lehrkraft der Buchung, an alle"
mut SV22 $IDX 's/\$lid,\n                        mit_absage_art\(\(int\)\$b\[\x27schueler_id\x27\]\),/\$lid, \x27konto\x27,/' \
  $SV "Ausfall: jede Absage an alle Erziehungsberechtigten"
# SV23 entfällt mit v0.9.79: Die Bestätigung nach einer Elternbuchung, die
# sie mutierte, gibt es nicht mehr (E17-Nachtrag). Die Nummer bleibt vergeben;
# die umgedrehte Prüfung deckt VS6 ab.

echo "== v0.9.74: Archivieren und Datenschutz-Absatz der Hilfe (H9)"
AR=tests/run_archivieren.php
DS=tests/frontend_datenschutz_test.js
mut AR1 $IDX 's/\n            \$pdo->prepare\(\x27DELETE FROM einladungen WHERE sprechtag_id = \?\x27\)->execute\(\[\$sid\]\);//' \
  $AR "jede Tabelle mit sprechtag_id wird geleert oder ist Struktur"
mut AR2 $IDX 's/\$archivieren = \(\$body\[\x27phase\x27\] \?\? \x27\x27\) === \x27archiviert\x27;/\$archivieren = (\$body[\x27phase\x27] ?? \x27\x27) !== \x27archiviert\x27;/' \
  $AR "Archivieren nur bei Phase „archiviert“"
mut AR3 $IDX 's/        if \(\$archivieren\) \{\n            \$pdo->prepare\(\x27DELETE FROM buchungen/        if (true) {\n            \$pdo->prepare(\x27DELETE FROM buchungen/' \
  $AR "Voraussetzung: genau ein Block"
mut AR4 $IDX 's/DELETE FROM mitteilungen WHERE sprechtag_id = \?\x27\)->execute\(\[\$sid\]\)/DELETE FROM mitteilungen\x27)->execute()/' \
  $AR "jede Löschung nur für DIESEN Sprechtag"
# AR5 (v0.9.83): erfundener Tabellenname statt abgesagte_termine – die gibt es
# seit sql/24 wirklich und sie wird geleert; der Eingriff bleibt derselbe.
mut AR5 sql/21_dienstkonto_entfernen.sql 's/\z/\nCREATE TABLE IF NOT EXISTS probe_ablage (\n    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,\n    sprechtag_id  INT UNSIGNED NOT NULL,\n    PRIMARY KEY (id)\n) ENGINE=InnoDB;\n/' \
  $AR "jede Tabelle mit sprechtag_id wird geleert oder ist Struktur"
mut AR6 sql/21_dienstkonto_entfernen.sql 's/\z/\nCREATE TABLE IF NOT EXISTS notizen (\n    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,\n    eltern_user_id INT UNSIGNED NOT NULL,\n    PRIMARY KEY (id)\n) ENGINE=InnoDB;\n/' \
  $AR "jede Tabelle ohne sprechtag_id ist eingeordnet"
mut AR7 $IDX 's/(            \$pdo->prepare\(\x27DELETE FROM kind_lehrer_cache WHERE sprechtag_id = \?\x27\)->execute\(\[\$sid\]\);)/${1}\n            \$pdo->prepare(\x27DELETE FROM sprechtag_lehrer WHERE sprechtag_id = ?\x27)->execute([\$sid]);/' \
  $AR "Struktur wird NICHT geleert"
mut AR8 backend/api/kalender.php 's/\z/\nfunction kal_abo_weg(PDO \$p): void { \$p->exec(\x27DELETE FROM kalender_abo\x27); }\n/' \
  $AR "Kalender-Abo: kein Löschweg im Code"
mut AR9 $IDX 's/DELETE FROM login_log WHERE zeitpunkt < NOW\(\) - INTERVAL \? DAY/DELETE FROM login_log WHERE zeitpunkt < NOW() - INTERVAL ? MONTH/' \
  $AR "Login-Protokoll: Bereinigung nach login_log_tage vorhanden"
# DS1, DS2 (v0.9.89): seit der Neugliederung heißt der Abschnitt
# `datenschutz`, nicht mehr `hb`; der Eingriff bleibt derselbe.
mut DS1 $APP 's/  for \(const a of datenschutzAbsaetze\(\)\) datenschutz\.appendChild\(el\(\x27p\x27, null, a\)\);\n//' \
  $DS "ansichtHilfe zeigt die Absätze im Abschnitt „Datenschutz“, je genau einmal (ausgeführt)"
mut DS2 $APP 's/(  for \(const a of datenschutzAbsaetze\(\)\) datenschutz\.appendChild\(el\(\x27p\x27, null, a\)\);\n)/${1}  datenschutz.appendChild(el(\x27p\x27, null, \x27Beim Archivieren werden alle persönlichen Daten gelöscht.\x27));\n/' \
  $DS "NICHT mehr: „alle persönlichen Daten“ werden gelöscht"
mut DS3 sql/16_login_log.sql 's/\(\x27login_log_tage\x27,    \x2730\x27\)/(\x27login_log_tage\x27,    \x2714\x27)/' \
  $DS "Login: Frist mit der Voreinstellung aus der Migration (14 Tage)"
mut DS4 $IDX 's/max\(1, min\(365, \(int\)marke_wert\(\$pdo, \x27login_log_tage\x27/max(1, min(180, (int)marke_wert(\$pdo, \x27login_log_tage\x27/' \
  $DS "Login: Obergrenze aus dem Backend (180)"
mut DS5 $APP 's/    \x27Für den persönlichen Kalender-Link.*?\n(    \x27|  \];)/$1/s' \
  $DS "Hilfe nennt, was beim Archivieren bleibt: kalender_abo"
# DS6 entfällt mit v0.9.82: Der Satz über die Schülerliste, den sie
# entfernte, ist fort (F6). Die Nummer bleibt vergeben; die Umkehrung
# belegt SA19.
mut DS7 $APP 's/WebUntis-Benutzernamen und der IP-Adresse, zum Schutz/WebUntis-Benutzernamen, zum Schutz/' \
  $DS "Login: Benutzername und IP-Adresse"
mut DS8 $APP 's/Dafür gibt es keine \x27\n      \+ \x27automatische Frist\. //' \
  $DS "… ohne automatische Frist"

echo "== v0.9.75: Zug 4, Schritt 1 – Messteil Schülerliste"
MSZ=backend/api/messung_sitzung.php
RMS=tests/run_messung_sitzung.php
# SU1–SU10 entfallen mit v0.9.82 (Zug 4, Schritt 4): Der Messteil
# messung_schuelerliste und der Vergleichsmaßstab $alt in der Route, die
# sie mutierten, sind fort. Die Nummern bleiben vergeben. Dass beides nicht
# zurückkommt, belegen SA5 und die Prüfung „Route übergibt keinen
# Vergleichsmaßstab …“.

echo "== v0.9.76: Zug 4, Schritt 2 – Kindname und Klasse am Vorgang"
KLP=backend/api/klassenleitung.php
ADP=backend/api/webuntis_adapter.php
AUP=backend/api/auth.php
KAL=backend/api/kalender.php
RKN=tests/run_kindname.php
# KN1 und KN5 seit v0.9.81 an der neuen Stelle (kd_klassen_namen,
# kd_ermitteln mit kd_klassen_lesen) – derselbe Eingriff, dieselbe Zeile.
mut KN1 $KLP 's/\$namen\[\$id\] = trim\(\(string\)\(\$k\[\x27class\x27\]\[\x27displayName\x27\] \?\? \x27\x27\)\);/\$namen[\$id] = trim((string)(\$k[\x27class\x27][\x27longName\x27] ?? \x27\x27));/' \
  $RKN "Klasse aus displayName"
mut KN2 $KLP 's/\x27nachname\x27  => trim\(\(string\)\(\$e\[\x27longName\x27\] \?\? \x27\x27\)\),/\x27nachname\x27  => trim((string)(\$e[\x27name\x27] ?? \x27\x27)),/' \
  $RKN "Nachname aus longName, Vorname aus forename"
mut KN3 $KLP 's/: \$n \. \x27, \x27 \. \$v\);/: \$v . \x27 \x27 . \$n);/' \
  $RKN "kd_name: „Nachname, Vorname“"
mut KN4 $KLP 's/(return \[\x27kinder\x27 => \$kinder, \x27grund\x27 => null\];\n    \} catch \()Exception/${1}Throwable/' \
  $RKN "Programmfehler (Error) geht weiter"
mut KN5 $KLP 's/        if \(\$mitKlasse !== \[\]\) \{\n            \$tf = kd_klassen_lesen/        if (true) {\n            \$tf = kd_klassen_lesen/' \
  $RKN "kein Kind mit Klasse: nur pageconfig"
mut KN6 $ADP 's/\$aus\[\$id\] = \[\x27name\x27 => \$eigenerName,/\$aus[\$id] = [\x27name\x27 => \$kd !== null ? kd_name(\$kd) : \$eigenerName,/' \
  $RKN "Schüler: Name aus der Anmeldung"
mut KN7 $AUP 's/    \$_SESSION\[\x27kind_daten\x27\] = \[\];\n//' \
  $RKN "erneute Anmeldung ohne Daten"
mut KN8 $AUP 's/    auth_kind_daten_merken\(\$daten\[\x27kind_daten\x27\] \?\? \[\]\);\n//' \
  $RKN "auth_login_speichern merkt sie"
mut KN9 $KAL 's/r\.kuerzel AS raum_kuerzel, b\.kind_name, b\.kind_klasse/r.kuerzel AS raum_kuerzel, "" AS kind_name, "" AS kind_klasse/' \
  $RKN "Kalender der Lehrkraft: Name und Klasse aus der Buchung"
mut KN10 $KAL 's/r\.kuerzel AS raum_kuerzel, b\.kind_name\n/r.kuerzel AS raum_kuerzel, "" AS kind_name\n/' \
  $RKN "Kalender der Eltern: Name aus der Buchung"
mut KN11 $BUP 's/        if \(\$sz\[\x27rest\x27\] === null\) json_sitzung_fehlt\(\$sz\);\n        \$ermittelt = /        \$ermittelt = /' \
  $RKN "Einladung: ohne Sitzung Abbruch"
# KN12 entfällt mit v0.9.81: Die Klassenprüfung beim Einladen, die sie
# mutierte, steht jetzt in kd_vorgang_pruefen (eine Regel für Einladen und
# stellvertretend, R4). Die Nummer bleibt vergeben; die Regel decken KS-
# Mutationen ab.
mut KN13 $MI 's/kuerze\(\$kindName, 170\), kuerze\(\$kindKlasse, 30\)/\x27\x27, kuerze(\$kindKlasse, 30)/' \
  $RKN "mit_einreihen speichert Name und Klasse"
mut KN14 $BUP 's/\(string\)\$b\[\x27kind_name\x27\], \(string\)\$b\[\x27kind_klasse\x27\]\);/\x27\x27, \x27\x27);/' \
  $RKN "Absage und Ausfall übernehmen Name und Klasse"
mut KN15 $MI 's/\$suche = \$kd\[\x27nachname\x27\] !== \x27\x27 \? \$kd\[\x27nachname\x27\] : \$kindName;/\$suche = \$kindName;/' \
  $RKN "sucht mit dem Nachnamen aus pageconfig"
mut KN16 $BUP 's/\$kd = wu_kind_daten_buchung\(\$cfg, \$kind, \$rolle, \(string\)\$u\[\x27name\x27\]\);/\$kd = [\x27name\x27 => \x27\x27, \x27klasse\x27 => \x27\x27];/' \
  $RKN "Elternbuchung: Name und Klasse über wu_kind_daten_buchung"
mut KN17 sql/22_kindname.sql 's/(UPDATE mitteilungen x .*?)\n WHERE x\.kind_name = \x27\x27;/$1;/s' \
  $RKN "übernimmt Namen vorhandener Zeilen"
mut KN18 $IDX 's/SELECT b\.slot_beginn, b\.kommentar, b\.kind_name, b\.kind_klasse/SELECT b.slot_beginn, b.kommentar, "" AS kind_name, b.kind_klasse/' \
  $RKN "Anzeigen: Tischvorlage"
mut KN19 $BUP 's/(b\.gebucht_von, b\.kommentar, b\.kind_name, b\.kind_klasse AS klasse\n         FROM buchungen b\n)/${1}         LEFT JOIN schueler s ON s.webuntis_id = b.schueler_id\n/' \
  $RKN "keine Datei unter backend/ nennt schueler als Tabelle in SQL"
mut KN20 $ADP 's/wu_kind_daten_login\(\$restOk \? \$rest : null,/wu_kind_daten_login(null,/' \
  $RKN "wu_login hält die Kinddaten fest"
mut EA1 $APP 's/const uebrig = ids\.slice\(i\);/const uebrig = ids.slice(i + 1);/' \
  $FS "… nach der Anmeldung genau die übrigen"
mut EA2 $APP 's/(\x27erneut auswählen\.\x27 \}\)\)) return;/${1} {}/' \
  $FS "hält beim ersten Kind ohne Sitzung an"
mut EA3 $APP 's/,\n          spaeter: \x27Nicht eingeladen\. Bitte die Kinder nach der nächsten Anmeldung \x27\n            \+ \x27erneut auswählen\.\x27 \}/ }/' \
  $FS "„Später“ sagt: nicht eingeladen"
mut EA4 $APP 's/meldung\(k\.spaeter \|\| \(/meldung((/' \
  $FS "Kasten mit eigenem „Später“-Text"
mut EA5 $APP 's/await einladenAusfuehren\(ids, hinweis\);/await einladenAusfuehren([], hinweis);/' \
  $FS "Knopf „Ausgewählte einladen“ ruft einladenAusfuehren"
mut EA6 $APP 's/stellvertretendBuchen\(lehrerId, slot\),\n        spaeter: \x27Nicht gebucht\. Der Termin ist nicht eingetragen\.\x27 \}/stellvertretendBuchen(lehrerId, slot) }/' \
  $FS "stellvertretend gibt „Nicht gebucht“"
mut DS9 $APP 's/die Termine mit Name und Klasse des \x27\n      \+ \x27Kindes und den Hinweisen/die Termine mit den \x27\n      + \x27Hinweisen/' \
  $DS "… genannt: Name und Klasse des Kindes am Termin"

# v0.9.76 Nachtrag: Elternbuchung ohne Kinddaten in der Sitzung – nachholen
# oder nicht buchen, nie still leer (Rückfrage des Betreibers vor dem Deploy).
mut KN21 $ADP 's/if \(\$kd\[\x27name\x27\] !== \x27\x27\) return \$kd;/if (false) return \$kd;/' \
  $RKN "Name in der Sitzung: genau der, kein WebUntis-Abruf"
mut KN22 $ADP 's/    auth_kind_daten_ergaenzen\(\$kindId, \$neu\[\$kindId\]\);\n//' \
  $RKN "… in die Sitzung ergänzt, samt Klassenleitung"
mut KN23 $AUP 's/(function auth_kind_daten_ergaenzen\(int \$kindId, array \$d\): void\n\{\n)/$1    \$_SESSION[\x27kind_daten\x27] = [];\n/' \
  $RKN "… die anderen Kinder der Sitzung bleiben stehen"
mut KN24 $ADP 's/if \(\(string\)\(\$neu\[\$kindId\]\[\x27name\x27\] \?\? \x27\x27\) === \x27\x27\) \{/if (!isset(\$neu[\$kindId])) {/' \
  $RKN "Schüler ohne eigenen Namen: Grund, nicht leer gebucht"
mut KN25 $ADP 's/    if \(\$sz\[\x27rest\x27\] === null\) return \[\x27sitzung\x27 => \$sz\];\n    \$neu = wu_kind_daten_login/    \$neu = wu_kind_daten_login/' \
  $RKN "ohne WebUntis-Sitzung: „abgelaufen“ (Kasten)"
mut KN26 $BU 's/        if \(isset\(\$kd\[\x27sitzung\x27\]\)\) json_sitzung_fehlt\(\$kd\[\x27sitzung\x27\]\);\n//' \
  $RKN "Elternbuchung: ohne Sitzung Kasten, ohne Name 502"
mut KN27 $BU 's/if \(!isset\(\$kd\[\x27name\x27\]\)\) json_err\(/if (false) json_err(/' \
  $RKN "Elternbuchung: ohne Sitzung Kasten, ohne Name 502"
mut KN28 $BU 's/\$kd = wu_kind_daten_buchung\(\$cfg, \$kind, \$rolle, \(string\)\$u\[\x27name\x27\]\);/\$kd = auth_kind_daten(\$kind);/' \
  $RKN "Elternbuchung: nicht mehr unmittelbar aus der Sitzung"
mut EA7 $APP 's/if \(sitzungAuswerten\(f\.sitzung, \x27Der Termin ist NICHT eingetragen: \x27/if (false && sitzungAuswerten(f.sitzung, \x27Der Termin ist NICHT eingetragen: \x27/' \
  $FS "abgelaufen: kein „gebucht“, Kasten „Anmelden und buchen“"
mut EA8 $APP 's/aktion: \(\) => buchen\(lehrerId, slot, kommentar\)/aktion: () => buchen(lehrerId, slot, \x27\x27)/' \
  $FS "… nach der Anmeldung derselbe Termin"
mut EA9 $APP 's/buchen\(lehrerId, slot, kommentar\),\n        spaeter: \x27Nicht gebucht\. Der Termin ist nicht eingetragen\.\x27 \}/buchen(lehrerId, slot, kommentar) }/' \
  $FS "„Später“ sagt: nicht gebucht"

# v0.9.77: Das Kalender-Abo liefert nur kommende Sprechtage (E21).
RKA=tests/run_kalender_abo.php
mut KO1 $KAL 's/    if \(\$abDatum !== null\) \{ \$sql \.= \x27 AND sp\.datum >= \?\x27; \$args\[\] = \$abDatum; \}\n//' \
  $RKA "vergangener Sprechtag (27.07.) fehlt"
mut KO2 $KAL 's/(\$args\[\] = \$sprechtagId; \}\n)    if \(\$abDatum !== null\) \{ \$sql \.= \x27 AND sp\.datum >= \?\x27; \$args\[\] = \$abDatum; \}\n/$1/' \
  $RKA "gestern und Juli fehlen, auch die Kindnamen von dort"
mut KO3 $KAL 's/AND sp\.datum >= \?/AND sp.datum > ?/' \
  $RKA "heute und die zwei künftigen Sprechtage drin"
mut KO4 $KAL 's/kal_buchungen_laden\(\$pdo, \(int\)\$uid, null, \$heute\)/kal_buchungen_laden(\$pdo, (int)\$uid, null, null)/' \
  $RKA "vergangener Sprechtag (27.07.) fehlt"
mut KO5 $KAL 's/kal_lehrer_buchungen\(\$pdo, \$lehrerId, null, \$heute\)/kal_lehrer_buchungen(\$pdo, \$lehrerId, null, null)/' \
  $RKA "gestern und Juli fehlen, auch die Kindnamen von dort"
mut KO6 $IX 's/kal_abo_ics\(\$pdo, \(string\)\$token, date\(\x27Y-m-d\x27\)\)/kal_abo_ics(\$pdo, (string)\$token, \x271970-01-01\x27)/' \
  $RKA "Abo-Route ruft kal_abo_ics mit dem heutigen Datum"
mut KO7 $IX 's/(    \$abo = kal_abo_ics\()/    \$alle = kal_buchungen_laden(\$pdo, 0);\n$1/' \
  $RKA "Abo-Route lädt keine Buchungen an kal_abo_ics vorbei"
mut KO8 $IX 's/            \$pdo->prepare\(\x27DELETE FROM buchungen WHERE sprechtag_id = \?\x27\)->execute\(\[\$sid\]\);\n//' \
  $RKA "nach dem Archivieren des Sprechtags vom 12.11."
mut KO9 $APP 's/liegt an der App\./entfernt sie ebenfalls./' \
  $DS "Kalender-Abo: verspricht nicht, dass die App sie löscht"
mut KO10 $APP 's/sie bleibt dort, bis man sie selbst löscht/sie verschwindet mit dem Abo/' \
  $DS "Grenze genannt: ein einzeln übernommener Termin"
mut KO11 $APP 's/Mit diesem persönlichen Link erscheinen Ihre kommenden Sprechtag-Termine/Mit diesem persönlichen Link erscheinen alle Ihre Sprechtag-Termine/' \
  $DS "Eltern: „Meine Termine“ und FAQ sagen „kommende“"
mut KO12 $APP 's/Abo-Link, mit dem Ihre kommenden Termine/Abo-Link, mit dem Ihre Termine/' \
  $DS "Lehrkraft: der Hinweis zum Abo-Link"
mut KO13 $APP 's/am Tag nach dem Sprechtag fallen sie/nach dem Archivieren fallen sie/' \
  $DS "Kalender-Abo: nur Termine kommender Sprechtage"

# v0.9.78: admin_kuerzel unempfindlich, Komma-Einträge aufgeteilt (E22).
RAK=tests/run_admin_kuerzel.php
CFE=backend/config.example.php
mut AK1 $ADP 's/\? mb_strtolower\(trim\(\$k\), \x27UTF-8\x27\) : strtolower\(trim\(\$k\)\)/? trim(\$k) : trim(\$k)/' \
  $RAK "hq, Hq, HQ, hQ treffen einander je paarweise"
mut AK2 $ADP 's/return \$a !== \x27\x27 && \$a === \$n\(\$b\);/return \$a !== \x27\x27 && \$a === trim(\$b);/' \
  $RAK "hq, Hq, HQ, hQ treffen einander je paarweise"
mut AK3 $ADP 's/\? mb_strtolower\(trim\(\$k\), \x27UTF-8\x27\) : strtolower\(trim\(\$k\)\)/? mb_strtolower(\$k, \x27UTF-8\x27) : strtolower(\$k)/' \
  $RAK "Leerzeichen am Rand zählen nicht"
mut AK4 $ADP 's/return \$a !== \x27\x27 && \$a === \$n\(\$b\);/return \$a === \$n(\$b);/' \
  $RAK "leer trifft nie"
mut AK5 $ADP 's/foreach \(explode\(\x27,\x27, \(string\)\$eintrag\) as \$k\)/foreach ([(string)\$eintrag] as \$k)/' \
  $RAK "['Hq, Mq'] wird aufgeteilt"
mut AK6 $ADP 's/            \$k = trim\(\$k\);\n//' \
  $RAK "gemischt, ohne Leerzeichen, Rand entfernt"
mut AK7 $ADP 's/if \(\$k !== \x27\x27\) \$aus\[\] = \$k;/\$aus[] = \$k;/' \
  $RAK "leere Teile fallen weg"
mut AK8 $ADP 's/if \(wu_kuerzel_gleich\(\$kuerzel, \$k\)\) return true;/if (\$kuerzel === \$k) return true;/' \
  $RAK "Lehrkraft HQ, config ['hq']: Admin"
mut AK9 $ADP 's/return wu_kuerzel_liste\(\$wcfg\[\x27admin_kuerzel\x27\] \?\? \[\]\)\[0\] \?\? null;/return \$wcfg[\x27admin_kuerzel\x27][0] ?? null;/' \
  $RAK "… aus der Zeichenkette 'Hq' das ganze Kürzel"
mut AK10 $ADP 's/\$ergebnis\[\x27kuerzel\x27\] = wu_admin_eigenes_kuerzel\(\$wcfg\);/\$ergebnis[\x27kuerzel\x27] = \$wcfg[\x27admin_kuerzel\x27][0] ?? null;/' \
  $RAK "wu_login, personType 16"
mut AK11 $ADP 's/\$ausConfig = wu_ist_config_admin\(\$ergebnis\[\x27kuerzel\x27\], \$wcfg\);/\$ausConfig = in_array(\$ergebnis[\x27kuerzel\x27], (array)(\$wcfg[\x27admin_kuerzel\x27] ?? []), true);/' \
  $RAK "wu_login, Lehrkraft"
mut AK12 $ADP 's/foreach \(wu_kuerzel_liste\(\$wcfg\[\x27admin_kuerzel\x27\] \?\? \[\]\) as \$k\)/foreach ((array)(\$wcfg[\x27admin_kuerzel\x27] ?? []) as \$k)/' \
  $RAK "Engstelle: admin_kuerzel wird nur über wu_kuerzel_liste gelesen"
mut AK13 $IX 's/wu_kuerzel_gleich\(\(string\)\$ziel, \(string\)\(\$u\[\x27kuerzel\x27\] \?\? \x27\x27\)\)/\$ziel === (\$u[\x27kuerzel\x27] ?? null)/' \
  $RAK "Selbst-Entfernen-Wache (app_admins) vergleicht unempfindlich"
mut AK14 $CFE 's/        \/\/     \x27admin_kuerzel\x27 => \[\x27Hq\x27, \x27Mq\x27, \x27Sq\x27\],\n//' \
  $RAK "Kommentar zeigt die Array-Form"
mut AK15 $CFE 's/Groß- und Kleinschreibung spielt/Die Schreibweise spielt/' \
  $RAK "Kommentar warnt vor"

# v0.9.79: keine Bestätigung nach Elternbuchung; Register der Versandstellen.
RVS=tests/run_versandstellen.php
FB=tests/frontend_bestaetigung_test.js
mut VS1 $BUP 's/(        json_ok\(\[\x27ok\x27 => true, \x27id\x27 => \$neueId\], 201\);)/        mit_einreihen_und_senden(\$pdo, \$sid, (int)\$elternUserId, \x27bestaetigung\x27, \x27B\x27, \x27T\x27, \$kind, null, wu_sitzung(\$cfg), \x27konto\x27);\n$1/' \
  $RVS "Elternbuchung reiht nichts ein und sendet nichts"
mut VS2 $BUP 's/(        json_ok\(\[\x27ok\x27 => true, \x27id\x27 => \$neueId\], 201\);)/        \$t = mit_text_bestaetigung(\x27x\x27, \x27\x27, []);\n$1/' \
  $RVS "Elternbuchung: kein Bestätigungstext"
mut VS3 $BUP 's/mit_einreihen_und_senden\(\$pdo, \$sid, 0, \x27bestaetigung\x27,/mit_einreihen_und_senden(\$pdo, \$sid, 0, \x27einladung\x27,/' \
  $RVS "stellvertretende Buchung behält ihre Bestätigung"
mut VS4 $IX 's/(    \$abo = kal_abo_ics\()/    if (false) mit_einreihen(\$pdo, 0, 0, \x27x\x27, \x27x\x27, \x27x\x27);\n$1/' \
  $RVS "jede Versandstelle ist eingeordnet"
mut VS5 $IX 's/json_ok\(erinnerung_versenden\(\$pdo, wu_sitzung\(\$cfg\)\)\);/json_ok([]);/' \
  $RVS "jede Versandstelle ist eingeordnet"
mut VS6 $BUP 's/(        json_ok\(\[\x27ok\x27 => true, \x27id\x27 => \$neueId\], 201\);)/        mit_einreihen_und_senden(\$pdo, \$sid, (int)\$elternUserId, \x27bestaetigung\x27, \x27B\x27, \x27T\x27, \$kind, null, wu_sitzung(\$cfg), \x27konto\x27);\n$1/' \
  $SV "keine Bestätigung nach Elternbuchung mehr"
mut VS7 $APP 's/Nach einer eigenen Buchung kommt keine Nachricht\./Bestätigungen kommen per WebUntis./' \
  $FB "FAQ: nach der eigenen Buchung kommt keine Nachricht"
mut VS8 $APP 's/Die Buchung gilt \x27\n     \+ \x27sofort/Ohne Dienstkonto gilt die Buchung \x27\n     + \x27sofort/' \
  $FB "FAQ verspricht kein Dienstkonto mehr"
mut VS9 $APP 's/steht unter „Meine Termine" und im Kalender-Abo\./steht unter „Meine Termine"./' \
  $FB "FAQ: die Buchung steht unter „Meine Termine“ und im Kalender-Abo"
mut VS10 $APP 's/Bestätigung per WebUntis-Nachricht kommt nur, wenn/Bestätigung per WebUntis-Nachricht kommt auch, wenn/' \
  $FB "FAQ: Bestätigung nur, wenn eine Lehrkraft gebucht hat"

# v0.9.80: echtes Admin-Konto erkannt, Warnung in der Oberfläche (E23).
RAO=tests/run_admin_konto.php
FAO=tests/frontend_admin_konto_test.js
mut AO1 $AUP 's/\(int\)\(\$daten\[\x27personType\x27\] \?\? 0\) === 16;/(int)(\$daten[\x27personType\x27] ?? 0) === 2;/' \
  $RAO "personType 16: admin_konto wahr"
mut AO2 $AUP 's/    \$_SESSION\[\x27wu_admin_konto\x27\] = \(int\)\(\$daten\[\x27personType\x27\] \?\? 0\) === 16;\n//' \
  $RAO "personType 16: admin_konto wahr"
mut AO3 $AUP 's/\(int\)\(\$daten\[\x27personType\x27\] \?\? 0\) === 16;/\$daten[\x27rolle\x27] === \x27admin\x27;/' \
  $RAO "Lehrerkonto mit admin_kuerzel"
mut AO4 $AUP 's/\x27admin_konto\x27 => \(\$_SESSION\[\x27wu_admin_konto\x27\] \?\? false\) === true,/\x27admin_konto\x27 => true,/' \
  $RAO "Eltern und Lehrkraft: falsch"
mut AO5 $AUP 's/    \$_SESSION\[\x27wu_admin_konto\x27\] = \(int\)\(\$daten\[\x27personType\x27\] \?\? 0\) === 16;/    if ((int)(\$daten[\x27personType\x27] ?? 0) === 16) \$_SESSION[\x27wu_admin_konto\x27] = true;/' \
  $RAO "erneute Anmeldung mit anderem Konto"
mut AO6 $APP 's/Absagen, Ausfälle, Einladungen und \x27/Absagen, Einladungen und \x27/' \
  $FAO "nennt, was nicht hinausgeht"
mut AO7 $APP 's/unter „Mitteilungen“ offen stehen\./unter „Mitteilungen“ verworfen./' \
  $FAO "sagt, dass die Nachrichten offen stehen bleiben"
mut AO8 $APP 's/\x27in admin_kuerzel steht\.\x27/\x27in der Verwaltung eingetragen ist.\x27/' \
  $FAO "nennt den Weg"
mut AO9 $APP 's/el\(\x27div\x27, \x27meldung fehler admin-konto-warnung\x27\)/el(\x27div\x27, \x27meldung info admin-konto-warnung\x27)/' \
  $FAO "ist eine Warnung"
mut AO10 $APP 's/if \(S\.user && S\.user\.admin_konto && S\.ansicht !== \x27login\x27\)/if (S.user && S.ansicht !== \x27login\x27)/' \
  $FAO "zeichne() zeigt sie nur mit admin_konto"
mut AO11 $APP 's/(  const box = el\(\x27div\x27, \x27meldung info offen-hinweis\x27\);\n)/$1  box.appendChild(adminKontoWarnungElement());\n/' \
  $FAO "genau eine Darstellung"
mut AO12 $APP 's/if \(S\.user && S\.user\.admin_konto && S\.ansicht !== \x27login\x27\)/if (true || S.user \&\& S.user.admin_konto \&\& S.ansicht !== \x27login\x27)/' \
  $FAO "zeichne() zeigt sie nur mit admin_konto"


# v0.9.81: Kind-Suche aus pageconfig (Zug 4, Schritt 3; E20 A/D/E, R1–R4).
# KS1 ersetzt KN12: Sie hebelt die eine Regel für Einladen UND
# stellvertretend aus (Betreiber, 10.10.2026).
RKS=tests/run_kind_suche.php
FKS=tests/frontend_kind_suche_test.js
FLR=tests/frontend_lehrkraft_raster_test.js
mut KS1 $KLP 's/(function kd_vorgang_pruefen\(array \$ermittelt, int \$kind, string \$vorgang\): \?array\n\{\n)/${1}    return null;\n/' \
  $RKS "ohne Klasse, Einladen: 404 mit dem bisherigen Satz"
mut KS2 $KLP 's/return \$kd !== null && \(int\)\(\$kd\[\x27klasse_id\x27\] \?\? 0\) > 0;/return \$kd !== null \&\& (int)(\$kd[\x27klasse_id\x27] ?? 0) >= 0;/' \
  $RKS "kd_hat_klasse: null und klasse_id 0 nein"
mut KS3 $KLP 's/if \(\$kd\[\x27id\x27\] <= 0 \|\| !kd_hat_klasse\(\$kd\)\) continue;/if (\$kd[\x27id\x27] <= 0) continue;/' \
  $RKS "Kind ohne Klasse erscheint auch bei genauem Namen nicht"
mut KS4 $KLP 's/const KD_SUCHE_GRENZE = 60;/const KD_SUCHE_GRENZE = 40;/' \
  $RKS "Grenze ist 60"
mut KS5 $KLP 's/array_slice\(\$treffer, 0, \$grenze\)/array_slice(\$treffer, 0, \$grenze + 1)/' \
  $RKS "eins darüber (61)"
mut KS6 $KLP 's/    if \(\$q === \x27\x27\) return \[\x27kinder\x27 => \[\], \x27anzahl\x27 => 0, \x27grenze\x27 => KD_SUCHE_GRENZE\];\n//' \
  $RKS "ohne Suchbegriff: Sitzung nicht einmal gefragt"
mut KS7 $KLP 's/if \(str_contains\(kd_klein\(\$kd\[\$f\]\), \$nadel\)\)/if (str_contains(\$kd[\$f], \$nadel))/' \
  $RKS "Name trifft ohne Groß/Klein"
mut KS8 $KLP 's/trim\(\(string\)\(\$k\[\x27class\x27\]\[\x27displayName\x27\] \?\? \x27\x27\)\)/trim((string)(\$k[\x27class\x27][\x27longName\x27] ?? \x27\x27))/' \
  $RKS "Klasse aus displayName, nicht aus longName"
mut KS9 $KLP 's/\} catch \(Exception \$e\) \{\n        return \[\x27grund\x27 => \x27Ausnahme \x27/} catch (Throwable \$e) {\n        return [\x27grund\x27 => \x27Ausnahme \x27/' \
  $RKS "Programmfehler (Error) geht weiter"
mut KS10 $KLP 's/(function kd_vorgang_pruefen.*?\n)    if \(\(\$ermittelt\[\x27grund\x27\] \?\? null\) !== null\) \{/${1}    if (false) {/s' \
  $RKS "Liste nicht lesbar: 502 mit dem bisherigen Satz"
mut KS11 $BUP 's/        \$einwand = kd_vorgang_pruefen\(\$aufl\[\x27ermittelt\x27\], \$kind, \x27gebucht\x27\);\n/        \$einwand = null;\n/' \
  $RKS "stellvertretend: fragt kd_vorgang_pruefen"
mut KS12 $BUP 's/if \(\$einwand !== null\) \{/if (false \&\& \$einwand !== null) {/' \
  $RKS "stellvertretend: Einwand führt zum Abbruch"
mut KS13 $BUP 's/\$einwand = kd_vorgang_pruefen\(\$ermittelt, \$kind, \x27eingeladen\x27\);/\$einwand = null;/' \
  $RKS "Einladen: fragt kd_vorgang_pruefen vor dem Speichern"
mut KS14 $IX 's/(if \(\(\$seg\[0\] \?\? \x27\x27\) === \x27kinder\x27\) \{\n)    auth_require_lehrkraft\(\);\n/${1}/' \
  $RKS "Route: Lehrkraft/Verwaltung geprüft"
mut KS15 $IX 's/json_err\(KD_SATZ_LISTE_NICHT_LESBAR \. \x27 Bitte erneut suchen\.\x27, 502\);/json_ok([\x27kinder\x27 => []]);/' \
  $RKS "Route: nicht lesbar → 502"
mut KS16 $MI 's/\x27kind\x27 => \$kd, \x27ermittelt\x27 => \$e\]/\x27kind\x27 => \$kd, \x27ermittelt\x27 => [\x27kinder\x27 => [], \x27grund\x27 => null]]/' \
  $RKS "ermittelt: Grund, wenn die Liste nicht lesbar war"
mut KS17 $APP 's/  if \(begriff === \x27\x27\) return \{ kinder: \[\], anzahl: 0, grenze: 60, leer: true \};\n//' \
  $FKS "leerer Suchbegriff: kein Abruf"
mut KS18 $APP 's/if \(t\.fehler\) return el\(\x27p\x27, \x27meldung fehler\x27, t\.fehler\);/if (t.fehler) return el(\x27p\x27, \x27hinweis-klein\x27, \x27Keine Treffer.\x27);/' \
  $FKS "Liste nicht erreichbar: der Grund steht als Fehlermeldung da"
mut KS19 $APP 's/aktion: \(\) => einlSuchen\(q\),/aktion: () => zeichne(),/' \
  $FKS "Einladung: abgelaufen → Kasten „Anmelden und suchen“"
mut KS20 $APP 's/(async function svKindSuchen\(q\) \{.*?)S\.svTreffer = \{ fehler: String\(f\.message\) \};/${1}S.svTreffer = { kinder: [], anzahl: 0, grenze: 60 };/s' \
  $FKS "stellvertretend: abgelaufen → der Zustand trägt den Fehler"
mut KS21 $APP 's/const status = kinderStatusElement\(S\.einlTreffer\);/const status = null;/' \
  $FKS "beide Ansichten zeigen den Zustand über kinderStatusElement"
mut KS22 $APP 's/!\(d\.anzahl > d\.kinder\.length\)/!(d.anzahl >= d.kinder.length)/' \
  $FKS "genau auf der Grenze (60 von 60): kein Hinweis"
mut KS23 $APP 's/(  aus\.appendChild\(form\);\n  einlTrefferZeichnen\(aus\);\n)/${1}  aus.appendChild(feld(\x27Schüler-ID\x27, \x27einl-schueler\x27));\n/' \
  $FKS "freie Eingabe einer Schüler-ID entfällt"
mut KS24 $APP 's/  const form = document\.createElement\(\x27form\x27\);\n  form\.className = \x27zeile\x27;\n  form\.appendChild\(feld\(\x27Suche/  const form = el(\x27div\x27, \x27zeile\x27);\n  form.appendChild(feld(\x27Suche/' \
  $FKS "Einladung: Suche ist ein Formular"
mut KS25 $APP 's/(      S\.svKindSuche = e\.target\.value;\n)(    \}\);)/${1}      svKindSuchen(e.target.value);\n${2}/' \
  $FLR "kein Abruf je Tastendruck"
mut KS26 $APP 's/return api\(\x27\/api\/kinder\?suche=\x27/return api(\x27\/api\/schueler?suche=\x27/' \
  $FLR "Suche fragt das Backend ab"
mut KS27 $APP 's/  const hinweis = kinderTrefferHinweis\(S\.svTreffer\);\n  if \(hinweis\) ziel\.appendChild\(el\(\x27p\x27, \x27hinweis-klein\x27, hinweis\)\);\n//' \
  $FLR "Trefferliste ist begrenzt"
# KS28 und KS29 entfallen mit v0.9.82: Ihre Sätze (Admin-Seite, Hilfe)
# sind mit der Schülerliste fort, ihre Prüfungen ebenso. Die Nummern
# bleiben vergeben; die Hilfe belegt SA19, die Admin-Seite SA8.

echo "== v0.9.82: Zug 4, Schritt 4 – die alte Schülerliste fällt"
RSA=tests/run_schuelerliste_abbau.php
RMS=tests/run_messung_sitzung.php
P23=sql/23_pruefung.sql
M23=sql/23_schueler_entfernen.sql
# SA1–SA4: Die Engstelle sieht die Sache, nicht eine Schreibweise – jede
# der vier Mutationen nennt die Tabelle auf eine andere Art, in einer
# Datei, die die alte Prüfung (JOIN überall, FROM in vier Dateien) nicht
# gesehen hätte: index.php, sondierung.php, backend/ außerhalb von api,
# und ein Unterordner (auth).
mut SA1 $IDX 's/(mit_eltern_ids_ermitteln\(\$pdo, \$kind, \$rest\)\)\]\);\n)/$1\$alteZahl = (int)\$pdo->query("SELECT COUNT(*) FROM schueler")->fetchColumn();\n/' \
  $RKN "keine Datei unter backend/ nennt schueler als Tabelle in SQL"
mut SA2 backend/api/sondierung.php 's/(        \$pcIds = \[\];\n)/$1        \$pdo->exec("INSERT INTO schueler (webuntis_id) VALUES (1)");\n/' \
  $RKN "keine Datei unter backend/ nennt schueler als Tabelle in SQL"
mut SA3 backend/helfer.php 's/(declare\(strict_types=1\);\n)/$1\nfunction alt_klasse_leeren(PDO \$pdo): void { \$pdo->exec("UPDATE schueler SET klasse = \x27\x27"); }\n/' \
  $RKN "keine Datei unter backend/ nennt schueler als Tabelle in SQL"
mut SA4 backend/auth/WebUntisRest.php 's/(class WebUntisRest\n\{\n)/$1    private const ALT = "CREATE TABLE IF NOT EXISTS schueler (id INT)";\n/' \
  $RKN "keine Datei unter backend/ nennt schueler als Tabelle in SQL"
mut SA5 $MSZ 's/(    \/\/ ---- 1d\. Sch)/    \$bericht[\x27schuelerliste\x27] = \x27nicht gemessen\x27;\n$1/' \
  $RMS "Bericht ohne Teil schuelerliste"
mut SA6 $MSZ 's/(        \x27namensweg\x27 => \[)/        \x27kennung_in_schuelerliste\x27 => null,\n$1/' \
  $MP "Ergebnis: Empfänger von WebUntis"
# SA7 beschädigt das Prüfwerkzeug, nicht den Code: Der Schemaleser in
# run_archivieren vergisst DROP TABLE wieder. Die Voraussetzung muss es
# merken – sonst hielte die Einordnung schueler still für vorhanden.
mut SA7 tests/run_archivieren.php 's/foreach \(\$dm\[1\] as \$t\) \{ unset\(\$spalten\[\$t\]\); \$entfernt\[\$t\] = true; \}/foreach (\$dm[1] as \$t) { \$entfernt[\$t] = true; }/' \
  tests/run_archivieren.php "Voraussetzung: entfernte Tabelle erkannt"
mut SA8 $APP 's/(  ziel\.appendChild\(el\(\x27h2\x27, null, \x27Vollj\S+ Sch\S+\x27\)\);\n)/$1  ziel.appendChild(feld(\x27WebUntis-Passwort\x27, \x27sync-passwort\x27, \x27password\x27));\n/' \
  $FSG "… kein Abruf, kein Feld, kein Knopf"
mut SA9 $P23 's/\)\) <> \x27\x27/)) IS NOT NULL/g' \
  $RSA "Einladungen: in der alten Liste, aber ohne Namen"
mut SA10 $P23 's/COALESCE\(schueler_id, 0\) = 0/schueler_id = 0/g' \
  $RSA "Mitteilungen ohne Kind (Kennung leer oder 0)"
mut SA11 $M23 's/SET \@sql_einladungen := IF\(\@schueler_da = 1,/SET \@sql_einladungen := IF(1 = 1,/' \
  $RSA "jedes Nachfüllen nur, wenn die Tabelle noch da ist"
mut SA12 $M23 's/(SET \@sql_buchungen.*)DROP TABLE IF EXISTS schueler;\n/DROP TABLE IF EXISTS schueler;\n\n$1/s' \
  $RSA "entfernt die Tabelle mit DROP TABLE IF EXISTS"
mut SA13 $M23 's/(UPDATE mitteilungen x .*?x\.kind_klasse = )CASE WHEN x\.kind_klasse = \x27\x27 THEN s\.klasse ELSE x\.kind_klasse END/${1}s.klasse/s' \
  $RSA "füllt alle drei Tabellen nach"
mut SA14 $M23 's/(UPDATE einladungen x .*?)\n      WHERE x\.kind_name = \x27\x27"/$1"/s' \
  $RSA "füllt alle drei Tabellen nach"
mut SA15 $M23 's/CONCAT\(\x27, \x27, s\.vorname\)/CONCAT(\x27 \x27, s.vorname)/' \
  $RSA "Prüfdatei und Migration benutzen denselben Namensausdruck"
mut SA16 $IDX 's/(require_once __DIR__ \. \x27\/sondierung\.php\x27;\n)/$1require_once __DIR__ . \x27\/schueler.php\x27;\n/' \
  $RSA "index.php lädt schueler.php nicht mehr"
mut SA17 $IDX 's/(if \(\(\$seg\[0\] \?\? \x27\x27\) === \x27schueler-gruppen\x27\) \{\n)/if ((\$seg[0] ?? \x27\x27) === \x27schueler\x27) { json_antwort([]); }\n$1/' \
  $RSA "kein Routenzweig für /api/schueler"
mut SA18 backend/api/buchungen.php 's/(declare\(strict_types=1\);\n)/$1\$altAbgleich = fn(\$wu) => \$wu->getStudents();\n/' \
  $RSA "getStudents wird unter backend/ nirgends gerufen"
mut SA19 $APP 's/(function datenschutzAbsaetze\(\) \{\n  return \[\n)/$1    \x27Die Sch\xc3\xbclerliste mit Namen und Klassen bleibt gespeichert.\x27,\n/' \
  $DS "Hilfe nennt keine Schülerliste mehr als gespeichert"
mut SA20 $APP 's/(  svTreffer: null,)/  schuelerAnzahl: 0,\n$1/' \
  $FKS "der Zustand der alten Liste ist fort"

echo "== v0.9.83: E18 + E19 – abgesagte Termine, abgeleiteter Einladungsstatus"
RAT=tests/run_abgesagte_termine.php
FAT=tests/frontend_abgesagte_termine_test.js
ABP=backend/api/absagen.php
M24=sql/24_abgesagte_termine.sql
M25=sql/25_einladung_erledigt_entfernen.sql
# Migration 24 (am Text)
mut AT1 $M24 's/CREATE TABLE IF NOT EXISTS abgesagte_termine/CREATE TABLE abgesagte_termine/' \
  $RAT "legt abgesagte_termine an, zweimal einspielbar"
mut AT2 $M24 's/(    kommentar       VARCHAR)/    eltern_user_id  INT UNSIGNED NOT NULL,\n$1/' \
  $RAT "keine Kennung der Eltern und kein Absagetext"
mut AT3 $M24 's/ENUM\(\x27absage\x27,\x27ausfall\x27\)/ENUM(\x27absage\x27,\x27ausfall\x27,\x27storno\x27)/' \
  $RAT "Anlass ist absage oder ausfall"
mut AT4 $M24 's/kind_name       VARCHAR\(170\)/kind_name       VARCHAR(100)/' \
  $RAT "Name, Klasse und Hinweis so breit wie in buchungen"
mut AT5 $M24 's/(REFERENCES lehrer \(id\)) ON DELETE CASCADE/$1/' \
  $RAT "hängt am Sprechtag und an der Lehrkraft"
mut AT6 $M24 's/(    KEY idx_ab_kind)/    UNIQUE KEY uniq_ab_slot (sprechtag_id, lehrer_id, slot_beginn),\n$1/' \
  $RAT "kein eindeutiger Schlüssel außer id"
mut AT7 $M24 's/\z/\nDELETE FROM buchungen WHERE sprechtag_id = 0;\n/' \
  $RAT "rührt sonst nichts an"
# Die eine Funktion (ausgeführt)
mut AT8 $ABP 's/in_array\(\$rolle, \[\x27lehrkraft\x27, \x27admin\x27\], true\)/in_array(\$rolle, [\x27lehrkraft\x27], true)/' \
  $RAT "Anlass: Lehrkraft und Verwaltung sagen ab"
mut AT9 $ABP 's/            if \(\$anlass !== null\) \{/            if (false) {/' \
  $RAT "einzelne Absage: abgelegt mit"
mut AT10 $ABP 's/        if \(\$eigene && \$pdo->inTransaction\(\)\) \$pdo->rollBack\(\);\n        throw \$e;/        if (\$eigene \&\& \$pdo->inTransaction()) \$pdo->rollBack();\n        return [];/' \
  $RAT "scheitert das Ablegen (Tabelle fehlt), bleibt die Buchung stehen und der Fehler geht weiter"
mut AT11 $ABP 's/if \(\$schluessel !== \[\x27id\x27\] && \$schluessel !== \[\x27lehrer_id\x27, \x27sprechtag_id\x27\]\) \{/if (false) {/' \
  $RAT "nur Sprechtag ohne Lehrkraft, oder ein fremder Schlüssel: abgewiesen"
# Status (ausgeführt)
mut AT12 $ABP 's/(" WHEN EXISTS \(SELECT 1 FROM )abgesagte_termine( x WHERE \$gleich\) THEN \x27)abgesagt\x27"/${1}buchungen${2}gebucht\x27"/; s/(CASE WHEN EXISTS \(SELECT 1 FROM )buchungen( x WHERE \$gleich\) THEN \x27)gebucht\x27"/${1}abgesagte_termine${2}abgesagt\x27"/' \
  $RAT "neu gebucht nach der Absage: gebucht geht vor abgesagt"
mut AT13 $ABP 's/ AND \$a\.lehrer_id = \$b\.lehrer_id"/"/' \
  $RAT "… die Ablage zählt nur bei dieser Lehrkraft und an diesem Sprechtag"
mut AT14 $ABP 's/return "\$a\.sprechtag_id = \$b\.sprechtag_id AND /return "/' \
  $RAT "einzelne Absage an Sprechtag 2: dort abgesagt, an Sprechtag 1 bleibt dasselbe Kind offen"
# Abgesagte Termine für die Lehrkraft (ausgeführt)
mut AT15 $ABP 's/SELECT a\.slot_beginn, a\.kind_name,/SELECT a.slot_beginn, a.schueler_id, a.kind_name,/' \
  $RAT "nach dem Ausfall: alle drei, nach Uhrzeit, mit genau den Feldern"
mut AT16 $ABP 's/WHERE a\.sprechtag_id = \? AND a\.lehrer_id = \?/WHERE a.sprechtag_id = ? AND ? > 0/' \
  $RAT "andere Lehrkraft und anderer Sprechtag: nichts"
mut AT17 $ABP 's/(\(SELECT MIN\(x\.slot_beginn\) FROM buchungen x WHERE \x27 \. )bu_selber_vorgang_sql\(\x27x\x27, \x27a\x27\)/${1}\x27x.sprechtag_id = a.sprechtag_id AND x.schueler_id = a.schueler_id\x27/' \
  $RAT "neu gebucht: die Uhrzeit des neuen Termins bei DIESER Lehrkraft"
# Aufrufstellen (Quelltext)
mut AT18 $BUP 's/\$anlass = bu_absage_anlass\(\(string\)\$u\[\x27rolle\x27\]\);/\$anlass = \x27absage\x27;/' \
  $RAT "Absage-Route: Anlass aus der Rolle"
mut AT19 $BUP 's/        \$mitteilung = null;\n        if \(\$anlass !== null\) \{/        \$mitteilung = null;\n        if (in_array(\$u[\x27rolle\x27], [\x27lehrkraft\x27, \x27admin\x27], true)) {/' \
  $RAT "… benachrichtigt genau dann, wenn abgelegt wird"
mut AT20 $IDX 's/\[\x27sprechtag_id\x27 => \$sid, \x27lehrer_id\x27 => \$lid\], \x27ausfall\x27\);/[\x27sprechtag_id\x27 => \$sid, \x27lehrer_id\x27 => \$lid], \x27absage\x27);/' \
  $RAT "Ausfall: alle Termine der Lehrkraft über die eine Funktion, Anlass ausfall"
mut AT21 $IDX 's/\[\x27sprechtag_id\x27 => \$sid, \x27lehrer_id\x27 => \$lid\], \x27ausfall\x27\);/[\x27sprechtag_id\x27 => \$sid, \x27lehrer_id\x27 => \$lid], \x27absage\x27);/' \
  tests/frontend_halbtags_test.js "Ausfall-Route gibt Termine frei und benachrichtigt"
mut AT22 $BUP 's/(\$abgesagt = )\$istLehrkraft \? (bu_abgesagte_termine\(\$pdo, \$sid, \$lid\)) : \[\];/$1$2;/' \
  $RAT "Raster-Route: Lehrkraft und Verwaltung bekommen die abgesagten Termine, Eltern nicht"
mut AT23 $BUP 's/(    \$abgesagt = \$istLehrkraft \?)/    if (empty(\$teilnahme)) \$istLehrkraft = false;\n$1/' \
  $RAT "… lädt auch bei Teilnahme 0"
mut AT24 $IDX 's/(require_once __DIR__ \. \x27\/absagen\.php\x27;\n)//' \
  $RAT "index.php lädt absagen.php vor der ersten Route"
mut AT25 $IDX 's/(            \$stL = \$pdo->prepare\(\x27SELECT kuerzel, name FROM lehrer WHERE id = \?\x27\);\n            \$stL->execute\(\[\$lid\]\);)/            \$pdo->prepare(\x27DELETE b FROM buchungen b WHERE b.lehrer_id = ?\x27)->execute([\$lid]);\n$1/' \
  $RAT "keine Datei löscht Buchungen außer bu_buchungen_entfernen und dem Archivieren"
mut AT26 $IDX 's/\n            \$pdo->prepare\(\x27DELETE FROM abgesagte_termine WHERE sprechtag_id = \?\x27\)->execute\(\[\$sid\]\);//' \
  $AR "Archivieren leert abgesagte_termine"
# erledigt entfällt (G5): Engstelle in drei Dateien, eine davon im Unterordner
mut AT27 $BUP 's/(        \/\/ Keine Bestätigung \(v0\.9\.79)/        \$pdo->prepare(\x27UPDATE einladungen SET erledigt = 1 WHERE sprechtag_id = ? AND lehrer_id = ? AND schueler_id = ?\x27)\n            ->execute([\$sid, \$lid, \$kind]);\n\n$1/' \
  $RAT "kein Code unter backend/ liest oder schreibt einladungen.erledigt"
mut AT28 $BUP 's/bu_einladung_status_sql\(\x27e\x27\) \. \x27 AS status,/bu_einladung_status_sql(\x27e\x27) . \x27 AS status, e.erledigt,/' \
  $RAT "Lehrkraft-Zweig: Status über die eine Ableitung, erledigt wird nicht gelesen"
mut AT29 backend/auth/extractors.php 's/(declare\(strict_types=1\);\n)/$1\$probeSpalten = "SELECT id, erledigt, hinweis FROM einladungen";\n/' \
  $RAT "kein Code unter backend/ liest oder schreibt einladungen.erledigt"
mut AT30 $M25 's/DROP COLUMN IF EXISTS erledigt/DROP COLUMN erledigt/' \
  $RAT "entfernt genau die Spalte, zweimal einspielbar"
mut AT31 $M25 's/\z/UPDATE einladungen SET hinweis = \x27\x27;\n/' \
  $RAT "entfernt genau die Spalte, zweimal einspielbar (DROP COLUMN IF EXISTS), sonst nichts"
mut AT32 $M25 's/Diese Datei NACH dem Deploy einspielen\./Diese Datei VOR dem Deploy einspielen./' \
  $RAT "Reihenfolge im Kopf: 24 VOR dem Deploy, 25 NACH dem Deploy"
# Frontend: am angezeigten Text, Verbindung zum Backend über Alias und AB_FELDER
mut AT33 $APP 's/t\[e\.status\] : \x27unbekannt\x27/t[e.status] : \x27offen\x27/' \
  $FAT "fehlt der Status (etwa nur das alte erledigt)"
mut AT34 $APP 's/abgesagt: \x27Termin abgesagt\x27, offen/abgesagt: \x27offen\x27, offen/' \
  $FAT "angezeigt: „Termin gebucht“, „Termin abgesagt“, „offen“"
mut AT35 $BUP 's/bu_einladung_status_sql\(\x27e\x27\) \. \x27 AS status,/bu_einladung_status_sql(\x27e\x27) . \x27 AS zustand,/' \
  $FAT "angezeigt: „Termin gebucht“, „Termin abgesagt“, „offen“"
mut AT36 $APP 's/(function zeichneAbgesagte\(ziel, liste\) \{\n)/$1  if (liste.erledigt) return;\n/' \
  $FAT "kein Skript unter frontend/ liest erledigt"
mut AT37 tests/mobil-messung/mock.js 's/hinweis, status,/hinweis, erledigt: status,/' \
  $FAT "Mobil-Messung (mock.js): kein erledigt"
mut AT38 $APP 's/\x27neu gebucht um \x27 \+ String\(a\.neu_gebucht\)\.slice\(0, 5\)/\x27neu gebucht\x27/' \
  $FAT "einzelne Absage, neu gebucht: „neu gebucht um 16:00“"
mut AT39 $APP 's/    a\.klasse \|\| \x27–\x27,\n    anlass\[a\.anlass\]/    a.kind_klasse || \x27–\x27,\n    anlass[a.anlass]/' \
  $FAT "Krankheitsausfall: Uhrzeit, Kind, Klasse"
mut AT40 $ABP 's/a\.kind_klasse AS klasse, a\.kommentar/a.kind_klasse, a.kommentar/' \
  $RAT "nach dem Ausfall: alle drei, nach Uhrzeit, mit genau den Feldern"
mut AT41 $RAT 's/(const AB_FELDER = \[\x27slot_beginn\x27, \x27kind_name\x27, )\x27klasse\x27/${1}\x27kind_klasse\x27/' \
  $FAT "Krankheitsausfall: Uhrzeit, Kind, Klasse"
mut AT42 $APP 's/  if \(!liste \|\| liste\.length === 0\) return;/  if (!liste) return;/' \
  $FAT "keine abgesagten Termine: kein Abschnitt"
mut AT43 $APP 's/(  ziel\.appendChild\(el\(\x27h3\x27, null, \x27Abgesagte Termine\x27\)\);\n)/$1  ziel.appendChild(knopf(\x27Neu buchen\x27, \x27klein\x27, () => {}));\n/' \
  $FAT "… ohne Knöpfe und ohne Klickziele"
mut AT44 $APP 's/  zeichneAbgesagte\(ziel, S\.svAbgesagt \|\| \[\]\);\n//' \
  $FAT "unter dem Raster, genau einmal"
mut AT45 $APP 's/  zeichneAbgesagte\(ziel, S\.svAbgesagt \|\| \[\]\);/  if (S.user.lehrer_id === lehrerId) zeichneAbgesagte(ziel, S.svAbgesagt || []);/' \
  $FAT "unter dem Raster, genau einmal"
mut AT46 $APP 's/  zeichneAbgesagte\(ziel, S\.svAbgesagt \|\| \[\]\);\n//; s/(  \/\/ ---- Das Raster -+\n)/  zeichneAbgesagte(ziel, S.svAbgesagt || []);\n$1/' \
  $FAT "unter dem Raster, genau einmal"
mut AT47 $APP 's/S\.svAbgesagt = d\.abgesagt \|\| \[\];/S.svAbgesagt = d.abgesagt;/' \
  $FAT "ladeSvRaster übernimmt die abgesagten Termine"
# AT48 (v0.9.90): Der Satz beginnt jetzt eine Fortsetzungszeile; derselbe Eingriff.
mut AT48 $APP 's/\n    \+ \x27Die abgesagten Termine sieht die Lehrkraft weiter in ihrer \x27\n    \+ \x27Ansicht unter „Abgesagte Termine“\.\x27//' \
  $FAT "Krankheitsausfall: die Lehrkraft sieht die Termine weiter"
# Datenschutz: jede geleerte Tabelle genannt; eine künftige ohne Nennung wird rot
mut AT49 $APP 's/, auch abgesagte Termine, die Einladungen/, die Einladungen/' \
  $DS "Hilfe nennt, was beim Archivieren gelöscht wird: abgesagte_termine"
mut AT50 $AR 's/\x27mitteilungen\x27, \x27abgesagte_termine\x27\] as \$t\)/\x27mitteilungen\x27, \x27abgesagte_termine\x27, \x27probe_tabelle\x27] as \$t)/' \
  $DS "Hilfe nennt, was beim Archivieren gelöscht wird: probe_tabelle"
# Mobil (E13-Nachtrag): fünf Kartenansichten, nicht still eine mehr oder weniger
mut AT51 $APP 's/  ziel\.appendChild\(kartenTabelle\(tab\)\);\n\}\n\n\/\/ Zeilentexte der abgesagten Termine/  ziel.appendChild(tabelleRahmen(tab));\n}\n\n\/\/ Zeilentexte der abgesagten Termine/' \
  $MO "Karten genau in Meine Termine, Einladungen, Login-Protokoll, Mitteilungen, Abgesagte Termine"

echo "== v0.9.84: E24 – ein Termin je Kind und Lehrkraft"
RET=tests/run_ein_termin_je_kind.php
P26=sql/26_pruefung.sql
M26=sql/26_ein_termin_je_kind.sql
# Die Regel (ausgeführt)
mut ET1 $SLP 's/WHERE sprechtag_id = \? AND lehrer_id = \? AND schueler_id = \?\n        ORDER BY slot_beginn LIMIT 1/WHERE sprechtag_id = ? AND ? > 0 AND schueler_id = ?\n        ORDER BY slot_beginn LIMIT 1/' \
  $RET "anderes Kind, andere Lehrkraft, anderer Sprechtag: kein Termin"
mut ET2 $SLP 's/(\$st = \$pdo->prepare\(\x27SELECT slot_beginn, eltern_user_id )FROM buchungen\n/${1}FROM (SELECT sprechtag_id, lehrer_id, schueler_id, slot_beginn, 0 AS eltern_user_id FROM abgesagte_termine UNION ALL SELECT sprechtag_id, lehrer_id, schueler_id, slot_beginn, eltern_user_id FROM buchungen) x\n/' \
  $RET "nach einer Absage: kein Termin mehr"
# Die Meldung (ausgeführt)
mut ET3 $SLP 's/return \x27Für dieses Kind besteht bei dieser Lehrkraft bereits ein Termin, gebucht über ein \x27/return \x27Für dieses Kind besteht bei dieser Lehrkraft bereits ein Termin um \x27 . \$uhr . \x27 Uhr, gebucht über ein \x27/' \
  $RET "anderes Konto: ohne Uhrzeit"
mut ET4 $SLP 's/if \(\(int\)\$termin\[\x27eltern_user_id\x27\] === \$konto\) \{/if (false) {/' \
  $RET "eigenes Konto: mit Uhrzeit"
mut ET5 $SLP 's/if \(\$wer === \x27lehrkraft\x27\) \{/if (false) {/' \
  $RET "Lehrkraft, die stellvertretend bucht: mit Uhrzeit"
mut ET6 $SLP 's/return \$t === null \? \$sonst :/return true ? \$sonst :/' \
  $RET "nach der Verletzung: Meldung zum Kind"
# Schlüssel und Prüfdatei
mut ET7 $M26 's/\(sprechtag_id, lehrer_id, schueler_id\);/(sprechtag_id, lehrer_id, schueler_id, eltern_user_id);/' \
  $RET "Migration legt genau den Schlüssel an"
# ET8: am ALTER verankert – „ADD UNIQUE KEY IF NOT EXISTS“ steht auch im
# Kopfkommentar, und der erste Lauf traf nur ihn (Werkzeugfehler, v0.9.84).
mut ET8 $M26 's/ALTER TABLE buchungen ADD UNIQUE KEY IF NOT EXISTS/ALTER TABLE buchungen ADD UNIQUE KEY/' \
  $RET "Migration legt genau den Schlüssel an"
mut ET9 $P26 's/(  FROM \(SELECT COUNT\(\*\) AS n FROM buchungen\n         GROUP BY sprechtag_id, lehrer_id, schueler_id HAVING COUNT\(\*\) )> 1/${1}>= 1/' \
  $RET "Prüfdatei zählt: 2 Fälle, 3 überzählige Termine"
mut ET10 $P26 's/(SELECT COUNT\(\*\) AS DOPPELT_FAELLE,)/$1 MIN(d.k) AS kind,/; s/\(SELECT COUNT\(\*\) AS n FROM buchungen/(SELECT COUNT(*) AS n, MIN(schueler_id) AS k FROM buchungen/' \
  $RET "… gibt nur Zahlen aus, keine Kennungen und keine Namen"
mut ET11 $P26 's/\z/\nDELETE FROM buchungen WHERE id = 0;\n/' \
  $RET "Prüfdatei liest nur"
mut ET12 $P26 's/CASE WHEN COUNT\(\*\) = 0/CASE WHEN COUNT(*) >= 0/' \
  $RET "… letzte Zeile ein Satz: ANSEHEN"
mut ET13 $P26 's/Deploy, dann diese Prüfdatei, dann sql\/26_ein_termin_je_kind\.sql\./Diese Prüfdatei, dann sql\/26_ein_termin_je_kind.sql, dann Deploy./' \
  $RET "Reihenfolge im Kopf beider Dateien"
# Aufrufstellen: entfernen UND entwerten (Nachbarschaft zweier Stellen)
mut ET14 $BUP 's/\n            \/\/ Ein Termin je Kind und Lehrkraft \(E24\) – je Kind, nicht je Konto:\n.*?\n            \}\n//s' \
  $RET "Eltern: fragt bu_kind_termin vor dem Schreiben"
mut ET15 $BUP 's/(\$vorhanden = bu_kind_termin\(\$pdo, \$sid, \$lid, \$kind\);\n            if \()\$vorhanden !== null(\) \{\n                \$pdo->rollBack\(\);\n                json_err\(bu_kind_termin_text\(\$vorhanden, \x27eltern\x27)/${1}false${2}/' \
  $RET "Eltern: fragt bu_kind_termin vor dem Schreiben"
mut ET16 $BUP 's/(\$vorhanden = bu_kind_termin\(\$pdo, \$sid, \$lid, \$kind\);\n            if \()\$vorhanden !== null(\) \{\n                \$pdo->rollBack\(\);\n                json_err\(bu_kind_termin_text\(\$vorhanden, \x27lehrkraft\x27)/${1}false${2}/' \
  $RET "stellvertretend: fragt bu_kind_termin vor dem Schreiben"
mut ET17 $BUP 's/json_err\(bu_konflikt_text\(\$pdo, \$sid, \$lid, \$kind, \x27eltern\x27, \(int\)\$elternUserId,\n                    (\x27Dieser Termin wurde soeben von jemand anderem gebucht\x27)\), 409\);/json_err($1, 409);/' \
  $RET "beide: nach einer Schlüsselverletzung"
mut ET18 $BUP 's/(            \$vorhanden = bu_kind_termin\(\$pdo, \$sid, \$lid, \$kind\);\n            if \(\$vorhanden !== null\) \{\n                \$pdo->rollBack\(\);\n                json_err\(bu_kind_termin_text\(\$vorhanden, \x27lehrkraft\x27)/            \$stKind = \$pdo->prepare(\x27SELECT slot_beginn FROM buchungen\n                 WHERE sprechtag_id = ? AND lehrer_id = ? AND schueler_id = ?\x27);\n$1/' \
  $RET "die Regel steht einmal"
mut ET19 $BUP 's/(        \$elternUserId = \$u\[\x27user_id\x27\];\n)/$1        if (isset(\$body[\x27eltern_user_id\x27])) \$elternUserId = (int)\$body[\x27eltern_user_id\x27];\n/' \
  $RET "kein Code übernimmt eltern_user_id aus dem Request"
mut ET20 $BUP 's/        if \(in_array\(\$rolle, \[\x27lehrkraft\x27, \x27admin\x27\], true\)\) \{\n            json_err\(\x27Lehrkräfte buchen stellvertretend in ihrer eigenen Ansicht\.\x27, 403\);\n        \}\n//' \
  $RET "Elternweg weist Lehrkraft und Verwaltung ab"
mut ET21 $IDX 's/(require_once __DIR__ \. \x27\/absagen\.php\x27;\n)/$1\$nachtrag = fn(PDO \$p) => \$p->exec(\x27INSERT INTO buchungen (sprechtag_id) VALUES (0)\x27);\n/' \
  $RET "genau zwei Stellen unter backend/ schreiben in buchungen"

echo "== v0.9.85: Halbtags-Prüfungen im Rumpf statt in der Datei"
HBT=tests/frontend_halbtags_test.js
# HB1–HB7: die sieben Eingriffe aus der Messung zu v0.9.83 (CHANGELOG, „Gefunden,
# nicht behoben“). Gegen die alte Fassung blieben alle sieben grün.
mut HB1 $APP 's/sel\.id = \x27haelfte-\x27 \+ s\.id \+ \x27-\x27 \+ l\.lehrer_id;/sel.id = \x27h-\x27 + l.lehrer_id;/' \
  $HBT "Lehrer-Zuweisung: Hälfte-Dropdown für Halbtagskräfte"
mut HB2 $APP 's/\[\x27erste\x27, \x27erste Hälfte\x27\]/[\x27erste\x27, \x27vormittags\x27]/' \
  $HBT "Lehrer-Zuweisung: Hälfte-Dropdown für Halbtagskräfte"
mut HB3 $APP 's/\(\) => lehrerAusfall\(s, l\)\)/() => null)/' \
  $HBT "Lehrer-Zuweisung: Ausfall-Knopf"
mut HB4 $APP 's/^(\s+)zeichneHaelfteWahl\(ziel, lehrerId\);/${1}void 0;/m' \
  $HBT "Selbstbedienung: eigene Hälfte wählbar"
mut HB5 $APP 's/if \(eigenesRaster && S\.svLehrer && parseInt\(S\.svLehrer\.halbtags, 10\) === 1\)/if (eigenesRaster)/' \
  $HBT "Selbstbedienung nur bei halbtags und eigenem Raster"
mut HB6 $IDX 's/(\$s = \$sprechtagHolen\(\$pdo, \$sid\);\n\s*)\$f = slot_haelfte_fenster\(\$s, \$h\);/${1}\$f = [\x27von\x27 => null, \x27bis\x27 => null];/' \
  $HBT "PATCH akzeptiert haelfte erste/zweite/ganz"
mut HB7 $IDX 's/(PATCH\x27 && \(\$seg\[1\] \?\? \x27\x27\) === )\x27lehrer\x27/${1}\x27lehrerX\x27/' \
  $HBT "Halbtags-Toggle-Route"
# HB8–HB13: die übrigen Prüfungen, jetzt ebenfalls im Rumpf. HB11 und HB12
# löschen den Code und lassen ihn als Kommentar stehen.
mut HB8 $APP 's/body: \{ halbtags: cbH\.checked/body: { teilzeit: cbH.checked/' \
  $HBT "Halbtags-Markierung in der Lehrer-Tabelle"
mut HB9 $IDX 's/\$eigene = \(\$u\[\x27rolle\x27\] === \x27lehrkraft\x27/\$eigene = (\$u[\x27rolle\x27] === \x27admin\x27/' \
  $HBT "Lehrkraft darf eigenen Eintrag ändern"
mut HB10 $BUP 's/\x27halbtags\x27 => \$halbtags,/\x27halbtags\x27 => 0,/' \
  $HBT "Raster liefert halbtags-Flag"
mut HB11 sql/11_halbtags.sql 's/ALTER TABLE lehrer\n    ADD COLUMN IF NOT EXISTS halbtags/-- früher: ALTER TABLE lehrer ADD COLUMN IF NOT EXISTS halbtags\nALTER TABLE lehrer\n    ADD COLUMN halbtags/' \
  $HBT "Schema idempotent (ADD COLUMN IF NOT EXISTS)"
mut HB12 $IDX 's/        \$pdo->prepare\(\x27UPDATE lehrer SET halbtags = \? WHERE id = \?\x27\)\n            ->execute/        \/\/ \$pdo->prepare(\x27UPDATE lehrer SET halbtags = ? WHERE id = ?\x27)\n        \/\/ ->execute/' \
  $HBT "Halbtags-Toggle-Route"
mut HB13 $SLP 's/^function slot_haelfte_fenster\(/function slot_haelfte_fenster_alt(/m' \
  $HBT "slot_haelfte_fenster vorhanden"
# HB14 (v0.9.86): ein Rücksprung vor dem Halbtags-Block. Bedingung und Aufruf
# stehen unverändert da – die Rumpf-Prüfung von v0.9.85 blieb grün, die
# ausgeführte zählt null Aufrufe.
mut HB14 $APP 's/(  \/\/ Halbtagskräfte wählen im eigenen Raster ihre Hälfte selbst\.\n)/  if (S.svLehrer \&\& S.svLehrer.halbtags) return;\n$1/' \
  $HBT "Selbstbedienung: eigene Hälfte wählbar"

echo "== v0.9.89: Hilfe nach Rollen gegliedert"
HIT=tests/frontend_hilfe_test.js
# DS10: die Schleife steht unverändert da und läuft nie – die Prüfung bis
# v0.9.88 (Schreibweise im Rumpf) wäre grün geblieben.
mut DS10 $APP 's/^  for \(const a of datenschutzAbsaetze\(\)\) datenschutz\./  if (false) for (const a of datenschutzAbsaetze()) datenschutz./m' \
  $DS "ansichtHilfe zeigt die Absätze im Abschnitt „Datenschutz“, je genau einmal (ausgeführt)"
# DS11: die Absätze erscheinen, aber im falschen Abschnitt.
mut DS11 $APP 's/^  for \(const a of datenschutzAbsaetze\(\)\) datenschutz\.appendChild/  for (const a of datenschutzAbsaetze()) faq.appendChild/m' \
  $DS "ansichtHilfe zeigt die Absätze im Abschnitt „Datenschutz“, je genau einmal (ausgeführt)"
# HR1: die Reihenfolge steht nur in HILFE_TEILE (Engstelle, v0.9.89); die
# Abschnitte wandern mit, nicht nur die Sprungmarken. Seit v0.9.90 steht der
# Schülerteil an zweiter Stelle; vertauscht werden die ersten beiden.
mut HR1 $APP 's/^(  \[\x27hilfe-eltern\x27, [^\n]*\n)(  \[\x27hilfe-schueler\x27, [^\n]*\n)/$2$1/m' \
  $HIT "Abschnitte nach Rollen, in dieser Reihenfolge, je einmal"
mut HR2 $APP 's/  return i <= 0 \? marken : \[marken\[i\]\]\.concat\(marken\.slice\(0, i\), marken\.slice\(i \+ 1\)\);/  return marken;/' \
  $HIT "Sprungmarke der eigenen Rolle zuerst, keine fehlt"
mut HR3 $APP 's/^  \[\x27hilfe-lehrkraefte\x27, [^\n]*\n//m' \
  $HIT "Abschnitte nach Rollen, in dieser Reihenfolge, je einmal"
mut HR4 $APP 's/    a\.href = \x27#\x27 \+ ziel2;/    a.href = \x27#\x27 + ziel2 + \x27x\x27;/' \
  $HIT "eine Sprungmarke je Abschnitt, jede auf einen vorhandenen Abschnitt"
# HR5 (v0.9.90): Volljährige haben einen eigenen Teil; derselbe Eingriff.
mut HR5 $APP 's/schueler: \x27hilfe-schueler\x27,/schueler: \x27hilfe-faq\x27,/' \
  $HIT "Sprungmarke der eigenen Rolle zuerst, keine fehlt"

echo "== v0.9.90: Hilfe nachgezogen (Schritt 2, Inhalt)"
# HZ1–HZ4: Ändert sich der Code, den die Hilfe beschreibt, wird die
# Hilfe-Prüfung rot – nicht nur der Test des Codes.
mut HZ1 $SLP 's/return \x27Termine buchen die Erziehungsberechtigten\. Ob Sie/return \x27Termine buchen Ihre Eltern. Ob Sie/' \
  $HIT "E15: der zitierte Satz ist der Anfang beider Sperr-Meldungen aus bu_sperre_text (ausgeführt)"
mut HZ2 $SLP 's/bereits ein Termin, gebucht über ein \x27/bereits ein Termin um \x27 . \$uhr . \x27 Uhr, gebucht über ein \x27/' \
  $HIT "E24: ein Termin je Kind und Lehrkraft; Uhrzeit nur beim eigenen Konto – wie bu_kind_termin_text (ausgeführt)"
mut HZ3 $SLP 's/\(\(string\)\(\$buchung\[\x27phase\x27\] \?\? \x27\x27\) === \x27phase1\x27\)/((string)(\$buchung[\x27phase\x27] ?? \x27\x27) === \x27phaseX\x27)/' \
  $HIT "Absagen: Termin aus Phase 1 auf Einladung sagt nur die Lehrkraft ab – wie slot_storno_erlaubt (H4, H10; ausgeführt)"
mut HZ4 $APP 's/lehrer: \(liste\.eingeladen \|\| \[\]\)\.concat\(liste\.unterrichtend \|\| \[\]\)/lehrer: (liste.unterrichtend || []).concat(liste.eingeladen || [])/' \
  $HIT "Eltern: Gliederung der Kacheln in der Reihenfolge der Seite (H2, E10; Reihenfolge aus buchenLehrerAbschnitte ausgeführt)"
# HZ5–HZ7: die Zitat-Prüfung. HZ6 lässt den alten Text als Kommentar stehen
# (Art 3 im Befund vom 11.10.); HZ7 zitiert in der Hilfe falsch.
mut HZ5 $APP 's/knopf\(\x27Jetzt senden\x27, null,/knopf(\x27Senden\x27, null,/' \
  $HIT "jedes Zitat der Hilfe steht als Text im Code, außerhalb der Hilfe, an Grenzen (mind. 15 Zitate)"
mut HZ6 $APP 's/  const b = block\(\x27buchen-weitere\x27, \x27Weitere Lehrkräfte suchen \(\x27/  \/\/ früher: \x27Weitere Lehrkräfte suchen (\x27\n  const b = block(\x27buchen-weitere\x27, \x27Mehr Lehrkräfte suchen (\x27/' \
  $HIT "jedes Zitat der Hilfe steht als Text im Code, außerhalb der Hilfe, an Grenzen (mind. 15 Zitate)"
mut HZ7 $APP 's/Knöpfe wie „Absagen“ stehen/Knöpfe wie „Termin absagen“ stehen/' \
  $HIT "jedes Zitat der Hilfe steht als Text im Code, außerhalb der Hilfe, an Grenzen (mind. 15 Zitate)"
mut HZ8 $APP 's/    \[\x27Warum kann ich einen Termin nicht absagen\?\x27,/    [\x27Wie sage ich einen Termin ab?\x27,/' \
  $HIT "FAQ ohne Doppelung zur Schritt-Liste: kein Ablauf des Absagens, keine Lehrkraft-Frage zum stellvertretenden Buchen"
mut HZ9 $APP 's/\x27benachrichtigt; ist die Anmeldung der Verwaltung abgelaufen/\x27automatisch benachrichtigt; ist die Anmeldung der Verwaltung abgelaufen/' \
  $HIT "Krankheitsausfall: freigegebener Wortlaut, kein „automatisch“ (H8)"
mut HZ10 $APP 's/(    \x27Ein Sprechtag durchläuft in der Regel zwei Phasen\. )/    \x27Auf schmalen Bildschirmen werden Tabellen zu Karten. \x27\n    + $1/' \
  $HIT "FAQ: Karten auf dem Telefon – einmal, nicht in jedem Teil"
mut HZ11 $APP 's/schueler: \x27hilfe-schueler\x27,/schueler: \x27hilfe-eltern\x27,/' \
  $HIT "Sprungmarke der eigenen Rolle zuerst, keine fehlt"

echo "== v0.9.91: Sprungziele der Hilfe nicht unter der Kopfleiste"
HS_P="Sprungziele: jeder Abschnitt trägt .hilfe-teil, und die Telefon-Abfrage gibt ihm scroll-margin-top (v0.9.91)"
mut HS1 $CSS 's/\n  \.hilfe-teil \{ scroll-margin-top: 3\.5rem; \}//' $HIT "$HS_P"
# HS2: dieselbe Regel, aber außerhalb der Telefon-Abfrage
mut HS2 $CSS 's/\n  \.hilfe-teil \{ scroll-margin-top: 3\.5rem; \}//; s/\z/\n.hilfe-teil { scroll-margin-top: 3.5rem; }\n/' $HIT "$HS_P"
mut HS3 $APP 's/    s\.className \+= \x27 hilfe-teil\x27;[^\n]*\n//' $HIT "$HS_P"
# HS4: die Regel als Kommentar stehen gelassen (Art 3)
mut HS4 $CSS 's/  \.hilfe-teil \{ scroll-margin-top: 3\.5rem; \}/  \/* .hilfe-teil { scroll-margin-top: 3.5rem; } *\//' $HIT "$HS_P"

echo "== v0.9.92: Hilfe – breit klebende Sprungmarken, auf dem Telefon ein Knopf"
HK_B="breit: Sprungmarken kleben oben, mit Fläche dahinter; Sprungziele halten ihren Abstand (CSS ab 761 px)"
HK_T="Telefon: Knopf unten links, über Safaris Leiste (safe-area), unter dem Menü-Schleier; breit ausgeblendet (CSS)"
HK_Z="Knopf führt zu den Sprungmarken, nicht an den Seitenanfang (ausgeführt)"
HK_S="Knopf erscheint erst, wenn die Sprungmarken aus dem Bild sind; beobachtet sie und hört auf, wenn sie fort sind (ausgeführt)"
HK_A="ansichtHilfe hängt den Knopf genau einmal an, mit der Sprungmarken-Leiste als Ziel (ausgeführt)"
mut HK1 $CSS 's/  \.hilfe-nav \{ position: sticky; top: 0;/  .hilfe-nav { top: 0;/' $HIT "$HK_B"
mut HK2 $CSS 's/  \.hilfe-teil \{ scroll-margin-top: 9rem; \}\n//' $HIT "$HK_B"
mut HK3 $CSS 's/\n\.hilfe-hoch \{ display: none; \}//' $HIT "$HK_T"
mut HK4 $CSS 's/(  \.hilfe-hoch \{ display: block; position: fixed; z-index: )31;/${1}33;/' $HIT "$HK_T"
mut HK5 $CSS 's/\n                left: 1rem;\n                left: max\(1rem, env\(safe-area-inset-left, 0px\)\);/\n                right: 1rem;/' $HIT "$HK_T"
mut HK6 $CSS 's/\n                bottom: calc\(1\.25rem \+ env\(safe-area-inset-bottom, 0px\)\);//' $HIT "$HK_T"
mut HK7 $APP 's/k\.addEventListener\(\x27click\x27, \(\) => nav\.scrollIntoView\(\{ block: \x27start\x27 \}\)\);/k.addEventListener(\x27click\x27, () => scrollTo(0, 0));/' $HIT "$HK_Z"
mut HK8 $APP 's/k\.classList\.toggle\(\x27versteckt\x27, eintraege\[0\]\.isIntersecting\);/k.classList.toggle(\x27versteckt\x27, !eintraege[0].isIntersecting);/' $HIT "$HK_S"
mut HK9 $APP 's/      if \(!nav\.isConnected\) \{ beobachter\.disconnect\(\); return; \}\n//' $HIT "$HK_S"
mut HK10 $APP 's/  hilfeKnopfZuDenMarken\(ziel, nav\);\n//' $HIT "$HK_A"
mut HK11 $APP 's/const nav = el\(\x27div\x27, \x27hilfe-nav hilfe-teil\x27\);/const nav = el(\x27div\x27, \x27hilfe-nav\x27);/' $HIT "$HK_A"

echo "== v0.9.93: Anmeldebremse an jeder Stelle, die ein WebUntis-Passwort entgegennimmt"
BRT=tests/run_anmeldebremse.php
LLT=tests/frontend_loginlog_test.js
BRS=backend/api/anmeldebremse.php
# SD1–SD3: die Verwaltungsprüfung der Sondierung – entfernt, abgeschwächt,
# als Kommentar stehen gelassen (Art 3).
mut SD1 $IDX 's/    auth_require_admin\(\);\n(    \$benutzer = req\(\$body, \x27benutzername\x27\);\n    \$passwort = req\(\$body, \x27passwort\x27\);\n    \$gruppen)/$1/' \
  $BRT "eingeschaltet, nicht angemeldet: 401, WebUntis nicht gerufen, Bremse nicht befragt"
mut SD2 $IDX 's/    auth_require_admin\(\);\n(    \$benutzer = req\(\$body, \x27benutzername\x27\);\n    \$passwort = req\(\$body, \x27passwort\x27\);\n    \$gruppen)/    auth_require();\n$1/' \
  $BRT "eingeschaltet, Lehrkraft: 403 „nur für die Sprechtags-Administration“, WebUntis nicht gerufen"
mut SD3 $IDX 's/    auth_require_admin\(\);\n(    \$benutzer = req\(\$body, \x27benutzername\x27\);\n    \$passwort = req\(\$body, \x27passwort\x27\);\n    \$gruppen)/    \/\/ auth_require_admin();\n$1/' \
  $BRT "eingeschaltet, nicht angemeldet: 401, WebUntis nicht gerufen, Bremse nicht befragt"
mut SD4 $IDX 's/(    \$pdo = db\(\$cfg\);\n)    if \(bremse_greift\(\$pdo, \$cfg, \$benutzer\)\) \{\n        json_err\([^\n]*\n    \}\n/$1/' \
  $BRT "Verwaltung, Bremse greift: 429, WebUntis nicht gerufen"
mut SD5 $IDX 's/        bremse_fehlschlag\(\$pdo, \$benutzer, \x27Sondierung: \x27 \. \$e->getMessage\(\)\);\n//' \
  $BRT "Verwaltung, WebUntis lehnt ab: 502, der Fehlschlag steht im Protokoll"
# SD6, SD7: der Stammdaten-Sync – Bremse fort; ein zweiter Aufruf außerhalb
# einer Route mit Bremse (die Engstelle).
mut SD6 $IDX 's/(        \$passwort = req\(\$body, \x27passwort\x27\);\n)        if \(bremse_greift\(\$pdo, \$cfg, \$benutzer\)\) \{\n[^\n]*\n        \}\n/$1/' \
  $BRT "Sync, Bremse greift: 429, WebUntis nicht gerufen"
mut SD7 backend/api/buchungen.php 's/\z/\nfunction probe_sync(array \$c, PDO \$p): array { return wu_stammdaten_sync(\$c, \$p, \x27a\x27, \x27b\x27); }\n/' \
  $BRT "jede Stelle, die authenticate mit übergebenen Zugangsdaten ruft, fragt vorher die Bremse"
mut SD8 backend/config.example.php 's/\x27sondierung_freigeschaltet\x27 => false,/\x27sondierung_freigeschaltet\x27 => true,/' \
  $BRT "config.example.php: die Sondierung ist abgeschaltet voreingestellt"
mut SD9 $IDX 's/            bremse_fehlschlag\(\$pdo, \$benutzer, \$e->getMessage\(\)\);/            \$pdo->prepare(\x27INSERT INTO login_log (webuntis_benutzer, erfolgreich, grund, ip) VALUES (?, 0, ?, ?)\x27)->execute([\$benutzer, \$e->getMessage(), \x27\x27]);/' \
  $BRT "in login_log schreibt nur anmeldebremse.php"
mut SD10 $BRS 's/return \(int\)\$st->fetchColumn\(\) >= \(int\)/return (int)\$st->fetchColumn() > (int)/' \
  $BRT "die Bremse zählt Fehlschläge je Benutzername im Zeitfenster"
# LL1, LL2: die umgestellten Prüfungen der Login-Protokoll-Suite. LL1 hätte
# die Prüfung bis v0.9.92 bestanden – derselbe Text stand in der
# Einstellungsroute (Art 1).
mut LL1 $IDX 's/        if \(\(int\)marke_wert\(\$pdo, \x27login_log_aktiv\x27, \x270\x27\) !== 1\) \{\n            json_ok\(\[\x27aktiv\x27 => false, \x27eintraege\x27 => \[\]\]\);\n        \}\n//' \
  $LLT "Protokoll nur bei aktiv sichtbar"
mut LL2 $BRS 's/if \(\$logAktiv && \$logErfolge\)/if (\$logAktiv || \$logErfolge)/' \
  $LLT "Erfolge nur bei aktiv+erfolge geloggt"

# ------------------------------------------------------------
# SS1–SS5 (v0.9.95): die Stammdaten-Sondierung, ausgeführt mit Attrappen der
# Außenwelt (Modul v2.0.0: getStudents fort, getCurrentSchoolyear wirft bei
# Netzfehlern). SS1 und SS2 trennen „fängt nur Betriebsfehler“ von „fängt
# alles“; die Prüfung zu SS1 war vor und nach der Änderung grün und ist nur
# durch diese Mutation belegt.
RSS=tests/run_sondierung_stammdaten.php
SON=backend/api/sondierung.php
mut SS1 $SON 's/    \} catch \(RuntimeException \$e\) \{\n        \$bericht\[\x27getCurrentSchoolyear\x27\]/    } catch (Throwable \$e) {\n        \$bericht[\x27getCurrentSchoolyear\x27]/' \
  $RSS "Programmierfehler (Error) beim Schuljahr wird nicht gefangen"
mut SS2 $SON 's/    \} catch \(RuntimeException \$e\) \{\n        \$bericht\[\x27pageconfig_schueler\x27\]/    } catch (Throwable \$e) {\n        \$bericht[\x27pageconfig_schueler\x27]/' \
  $RSS "Programmierfehler (Error) bei pageconfig wird nicht gefangen"
mut SS3 $SON 's/    \} catch \(RuntimeException \$e\) \{\n        \$bericht\[\x27getCurrentSchoolyear\x27\]/    } catch (LogicException \$e) {\n        \$bericht[\x27getCurrentSchoolyear\x27]/' \
  $RSS "Netzfehler (RuntimeException) bricht die Sondierung nicht ab"
mut SS4 $SON 's/(        \$pcIds = \[\];\n)/$1        \$wu->getStudents();\n/' \
  $RSS "getStudents wird nicht gerufen"
mut SS5 $SON 's/if \(\(int\)\(\$e\[\x27klasseId\x27\] \?\? 0\) > 0\) \$mitKlasse\+\+;//' \
  $RSS "pageconfig: Anzahl, mit Klasse, mit externKey"

# ------------------------------------------------------------
# PV1–PV6 (v0.9.96, Aufräumliste 9): das Lesen der gemerkten Versandvariante
# in mit_versand_ausfuehren(), ausgeführt gegen SQLite und eine ablehnende
# REST-Attrappe. PV1 und PV2 trennen „fängt nur den Datenbankfehler“ von
# „fängt alles“ und „fängt nichts“; PV3 und PV4 belegen, dass der Rückfall
# sichtbar bleibt – im Feld und im Grund.
RVV=tests/run_versand_variante.php
MIT=backend/api/mitteilungen.php
mut PV1 $MIT 's/    \} catch \(PDOException \$e\) \{\n        \$lesefehler/    } catch (Throwable \$e) {\n        \$lesefehler/' \
  $RVV "Error beim Lesen fliegt heraus"
mut PV2 $MIT 's/    \} catch \(PDOException \$e\) \{\n        \$lesefehler/    } catch (LogicException \$e) {\n        \$lesefehler/' \
  $RVV "fehlende Tabelle bricht den Versand nicht ab"
mut PV3 $MIT 's/        \$lesefehler = \$e->getMessage\(\);\n//' \
  $RVV "Lesefehler steht im Ergebnis, mit der Meldung der Datenbank"
mut PV4 $MIT 's/\x27grund\x27 => \$grund \. \$hinweis,/\x27grund\x27 => \$grund,/' \
  $RVV "Lesefehler steht im Grund, nicht nur in einem Feld"
mut PV5 $MIT 's/\(string\)\$m\[\x27text\x27\], \$bevorzugt\);/(string)\$m[\x27text\x27], null);/' \
  $RVV "gemerkte Variante zuerst"
mut PV6 $MIT 's/\x27protokoll\x27 => \[\], \x27variante_lesefehler\x27 => null\];/\x27protokoll\x27 => []];/' \
  $RVV "auch ohne Versand ist variante_lesefehler da (null)"

echo ""
if [ "$FEHLT" -eq 0 ]; then echo "ALLE MUTATIONEN ANGESCHLAGEN"; exit 0; fi
echo "$FEHLT MUTATION(EN) OHNE BELEG"; exit 1
