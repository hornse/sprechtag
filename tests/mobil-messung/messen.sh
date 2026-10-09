#!/bin/bash
# ============================================================
# tests/mobil-messung/messen.sh – Breitenmessung der Oberfläche
#
# Aufruf im Projektordner:  ./tests/mobil-messung/messen.sh [STAND]
#   STAND: ein Git-Stand (z. B. v0.9.57, 92a25bc). Ohne Angabe: der
#   Arbeitsbaum. GERAET="iPhone 13" (Vorgabe), auch „iPhone 13 landscape“,
#   „iPhone SE“ – jedes Gerät aus der Playwright-Liste. BREITE/HOEHE
#   überschreiben den Viewport (Querformat am Gerät: BREITE=844 HOEHE=340).
#
# Die echte Oberfläche läuft mit erfundenen Daten (mock.js) in WebKit mit
# iPhone-Nachbildung (Viewport-Meta gilt, Touch). Je Ansicht wird die
# Seitenbreite gegen den Viewport gemessen und, wenn sie größer ist, das
# Element benannt, das sie verursacht (stub.js). Dazu das Menü: Schließt
# ein Tippen daneben – oben und in der Mitte?
#
# WAS DAS NICHT IST: eine Messung auf dem iPhone. Es ist WebKit auf dem
# Mac, nicht Safari auf iOS, mit erfundenen Daten. Messinstrument für die
# mobile Ansicht sind die Screenshots des Betreibers (E11). Läuft nicht in
# deploy.sh mit – es braucht Playwright mit WebKit.
#
# Bis v0.9.58 lief hier Chrome in einem iframe; das meldete „390 px,
# passt“, während die Seite auf dem Gerät überlief (E12).
#
# Startet und beendet seinen Server selbst; Ergebnisse und Bilder liegen
# außerhalb des Repos (Pfad wird ausgegeben).
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -u
export LC_ALL=C
H="$(cd "$(dirname "$0")" && pwd)"
R="$(cd "$H/../.." && pwd)"
command -v php > /dev/null || { echo "ANGEHALTEN: php fehlt."; exit 1; }
command -v node > /dev/null || { echo "ANGEHALTEN: node fehlt."; exit 1; }

# playwright-core: ausdrücklich angegeben, sonst im npx-Zwischenspeicher.
PW="${PLAYWRIGHT_CORE:-}"
if [ -z "$PW" ]; then
  for d in "$HOME"/.npm/_npx/*/node_modules/playwright-core; do
    [ -f "$d/package.json" ] && PW="$d"
  done
fi
if [ -z "$PW" ] || [ ! -f "$PW/package.json" ]; then
  echo "ANGEHALTEN: playwright-core nicht gefunden. Einmalig: npx playwright install webkit"
  echo "            oder PLAYWRIGHT_CORE=/pfad/zu/playwright-core setzen."
  exit 1
fi

STAND="${1:-}"
AUS="$(mktemp -d "${TMPDIR:-/tmp}/sprechtag-mobil.XXXXXX")"
FRONTEND="$R/frontend"
if [ -n "$STAND" ]; then
  git -C "$R" archive "$STAND" frontend | tar -x -C "$AUS" || { echo "ANGEHALTEN: Stand $STAND nicht lesbar."; exit 1; }
  FRONTEND="$AUS/frontend"
fi
PORT=$((20000 + RANDOM % 20000))
FRONTEND="$FRONTEND" PHP_CLI_SERVER_WORKERS=4 \
  php -S "127.0.0.1:$PORT" "$H/router.php" > "$AUS/server.log" 2>&1 &
SP=$!
# Mit PHP_CLI_SERVER_WORKERS überleben die Arbeiter den Hauptprozess.
trap 'kill "$SP" 2>/dev/null; pkill -f "php -S 127.0.0.1:$PORT " 2>/dev/null' EXIT
sleep 1

echo "Stand: ${STAND:-Arbeitsbaum}, playwright-core $(node -p "require('$PW/package.json').version")"
PLAYWRIGHT_CORE="$PW" PORT="$PORT" AUS="$AUS" GERAET="${GERAET:-iPhone 13}" BREITE="${BREITE:-}" HOEHE="${HOEHE:-}" node "$H/messen.js"
RC=$?
echo "Ergebnisse und Bilder: $AUS"
# Eine Ansicht ohne Ergebnis ist ein Fehler, kein sauberer Lauf.
[ "$RC" -eq 0 ] || { echo "Mindestens eine Ansicht ohne gültiges Ergebnis (Ausgang $RC)."; exit 1; }
