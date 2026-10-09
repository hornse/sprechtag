#!/bin/bash
# ============================================================
# tests/mobil-messung/messen.sh – Breitenmessung der Oberfläche
#
# Aufruf im Projektordner:  ./tests/mobil-messung/messen.sh [STAND]
#   STAND: ein Git-Stand (z. B. v0.9.57, 92a25bc). Ohne Angabe: der
#   Arbeitsbaum. BREITE=390 (Vorgabe).
#
# Die echte Oberfläche läuft mit erfundenen Daten (mock.js) in Chrome
# ohne Kopf, in einem Rahmen der gewünschten Breite. Je Ansicht wird
# gemessen, ob etwas über den rechten Rand ragt (außer in einem eigenen
# Rollbereich), und wie breit jede Tabelle gegenüber ihrem Rahmen ist.
#
# WAS DAS NICHT IST: eine Messung auf dem iPhone. Es ist Chrome, nicht
# Safari, und es misst Breiten, nicht Bedienbarkeit oder Safaris Leisten.
# Messinstrument für die mobile Ansicht sind die Screenshots des
# Betreibers (E11). Läuft nicht in deploy.sh mit – es braucht Chrome.
#
# Startet und beendet seinen Server selbst; Ergebnisse und Bilder liegen
# außerhalb des Repos (Pfad wird ausgegeben).
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -u
export LC_ALL=C
H="$(cd "$(dirname "$0")" && pwd)"
R="$(cd "$H/../.." && pwd)"
C="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
[ -x "$C" ] || { echo "ANGEHALTEN: Chrome nicht gefunden ($C)."; exit 1; }
command -v php > /dev/null || { echo "ANGEHALTEN: php fehlt."; exit 1; }

STAND="${1:-}"
AUS="$(mktemp -d "${TMPDIR:-/tmp}/sprechtag-mobil.XXXXXX")"
FRONTEND="$R/frontend"
if [ -n "$STAND" ]; then
  git -C "$R" archive "$STAND" frontend | tar -x -C "$AUS" || { echo "ANGEHALTEN: Stand $STAND nicht lesbar."; exit 1; }
  FRONTEND="$AUS/frontend"
fi
PORT=$((20000 + RANDOM % 20000))
FRONTEND="$FRONTEND" AUS="$AUS" PHP_CLI_SERVER_WORKERS=4 \
  php -S "127.0.0.1:$PORT" "$H/router.php" > "$AUS/server.log" 2>&1 &
SP=$!
# Mit PHP_CLI_SERVER_WORKERS überleben die Arbeiter den Hauptprozess.
trap 'kill "$SP" 2>/dev/null; pkill -f "php -S 127.0.0.1:$PORT " 2>/dev/null' EXIT
sleep 1

# mess ID ROLLE ANSICHT [AKTIONEN] – Chrome höchstens 45 s, dann beendet.
mess() {
  local e="$AUS/$1.json" pr
  pr="$(mktemp -d)"
  "$C" --headless=new --disable-gpu --hide-scrollbars --user-data-dir="$pr" \
    --window-size=420,900 --virtual-time-budget=12000 --screenshot="$AUS/$1.png" \
    "http://127.0.0.1:$PORT/__rahmen?id=$1&rolle=$2&aktion=${4:-}&ansicht=$3&breite=${BREITE:-390}" \
    > /dev/null 2>&1 &
  local cp=$! i
  for i in $(seq 1 90); do [ -s "$e" ] && break; sleep 0.5; done
  sleep 1; kill "$cp" 2>/dev/null; wait "$cp" 2>/dev/null; rm -rf "$pr"
}

L=(
 "login gast login"
 "buchen eltern buchen kachel,suche"
 "meine eltern buchen meine"
 "hilfe eltern hilfe"
 "lehrkraft lehrkraft lehrkraft unten"
 "einladungen lehrkraft einladungen offen"
 "mitt-l lehrkraft mitteilungen offen"
 "mitt-a admin mitteilungen offen"
 "aktiv admin admin-aktiv offen"
 "sprechtage admin admin-sprechtage offen"
 "daten admin admin-daten offen"
 "loginlog admin admin-loginlog offen"
 "texte admin admin-texte offen"
 "erinnerungen admin admin-erinnerungen offen"
 "anzeige admin admin-anzeige offen"
 "marke admin admin-marke offen"
 "menue eltern buchen menue"
)
# Je drei gleichzeitig. Gewartet wird auf die Messläufe, nicht mit einem
# blanken wait – das wartete auch auf den eigenen Server, also ewig.
LAEUFE=()
for z in "${L[@]}"; do
  set -- $z; mess "$1" "$2" "$3" "${4:-}" & LAEUFE+=($!)
  if [ "${#LAEUFE[@]}" -eq 3 ]; then wait "${LAEUFE[@]}"; LAEUFE=(); fi
done
[ "${#LAEUFE[@]}" -gt 0 ] && wait "${LAEUFE[@]}"

FEHLT=0
echo "Stand: ${STAND:-Arbeitsbaum}, Breite ${BREITE:-390} px"
for z in "${L[@]}"; do
  set -- $z
  if [ -s "$AUS/$1.json" ]; then
    node -e '
      const e = JSON.parse(require("fs").readFileSync(process.argv[1], "utf8"));
      // Ohne App oder Stilvorlage ist die Messung keine – Fehler, nicht „0 über“.
      if (!e[0] || !e[0].app || !e[0].css) {
        console.log(process.argv[2].padEnd(14) + "VORAUSSETZUNG FEHLT (app " + (e[0] && e[0].app)
          + ", css " + (e[0] && e[0].css) + ")"); process.exit(3); }
      const t = e.map((x) => x.ueberstehend !== undefined
        ? x.stufe + ": Seite " + x.seitenbreite + ", über " + x.ueberstehend
          + (x.tabellen.length ? ", Tabellen " + x.tabellen.join(" ") : "")
        : x.stufe + ": " + JSON.stringify(Object.assign({}, x, { stufe: undefined })));
      console.log(process.argv[2].padEnd(14) + t.join(" | "));' "$AUS/$1.json" "$1" || FEHLT=$((FEHLT + 1))
  else
    echo "$(printf '%-14s' "$1")KEIN ERGEBNIS"; FEHLT=$((FEHLT + 1))
  fi
done
echo "Ergebnisse und Bilder: $AUS"
# Eine Ansicht ohne Ergebnis ist ein Fehler, kein sauberer Lauf.
[ "$FEHLT" -eq 0 ] || { echo "$FEHLT Ansicht(en) ohne gültiges Ergebnis."; exit 1; }
