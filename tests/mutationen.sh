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

echo "== Messung Sitzung (Frage 2): Grund aus mit_rest_aus_sitzung()"
mut S1 $MI 's/\n\s*\$grund = .fehler: . \. get_class\(\$e\) \. .: . \. \$e->getMessage\(\);//' \
  $S "Ausnahme: null, Grund nennt Klasse und Meldung"
mut S2 $MI 's/\{ \$grund = .kein_token.; return null; \}/return null;/' \
  $S "mit_rest_aus_sitzung(): unerreichbar ergibt ebenfalls kein_token"
mut S3 $MI 's/\{ \$grund = .kein_cookie.; return null; \}/return null;/' \
  $S "ohne Cookie: null und Grund kein_cookie"

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
mut S9 $IX 's/mit_rest_aus_sitzung\(\$cfg, \$grund\);/mit_rest_aus_sitzung(\$cfg);/' \
  $S "Route reicht den Grund aus mit_rest_aus_sitzung() durch"
mut S10 $IX "s/\\\$probe = \\\$grund === 'kein_token'/\\\$probe = \\\$grund === 'nie'/" \
  $S "Route fährt die Nachprobe genau bei kein_token"
mut S11 $IX 's/\$u = auth_require\(\);\n    \$grund = null;/\$u = auth_user() ?? [];\n    \$grund = null;/' \
  $S "Route verlangt eine Anmeldung"
mut S12 $MI "s/\{ \\\$grund = 'kein_token'; return null; \}/{ \\\$grund = 'kein_token'; }/" \
  tests/frontend_lehrersitzung_test.js "abgelaufene Sitzung gibt null"

echo "== Messung Klassenleitung (Zug 3)"
mut S13 $MS 's/fn\(\$i\) => isset\(\$ids\[\$i\]\)/fn(\$i) => false/' \
  $S "Kind 1: Kennung passt zu lehrer.webuntis_id, Text zu kuerzel"
mut S14 $MS "s/'gefuellt'       => messung_format\(\\\$v\) !== 'leer',/'gefuellt' => true,/" \
  $S "Kind 2 ohne Klasse: Feld fehlt, nicht gefüllt"
mut S15 $MS "s/return 'Objekt\{' \. implode\(',', \\\$k\) \. '\}';/return json_encode(\\\$v);/" \
  $S "weder Kennung noch Name der Klassenleitung in der Antwort"
mut S16 $IX 's/\$probe, \$lehrer, \$ferien\)\]\);/\$probe, [], \$ferien)]);/' \
  $S "Route reicht den Grund aus mit_rest_aus_sitzung() durch"
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
mut FSG4 $APP 's/  if \(\(d\.gruppen \|\| \[\]\)\.length === 0\) \{/  if (false) {/' \
  $FSG "leere Liste: Warnung"
mut FSG5 $APP 's/\n  zeichneSchuelerGruppen\(ziel\);\n/\n/' \
  $FSG "Aufrufstelle: „Dienstkonto & Schülerliste“"

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

echo ""
if [ "$FEHLT" -eq 0 ]; then echo "ALLE MUTATIONEN ANGESCHLAGEN"; exit 0; fi
echo "$FEHLT MUTATION(EN) OHNE BELEG"; exit 1
