#!/usr/bin/env bash
# ============================================================
# deploy.sh – sprechtag
# Aufruf: ./deploy.sh "Commit-Nachricht"
# Setzt Cache-Busting-Timestamp in index.html, committet alles
# und pusht nach GitHub + Uberspace.
# DB-Migrationen werden NICHT übertragen – separat einspielen:
#   mysql hornse_sprechtag < sql/NN_*.sql
# ============================================================
set -euo pipefail

NACHRICHT="${1:?Aufruf: ./deploy.sh \"Commit-Nachricht\"}"
STEMPEL="$(date +%Y%m%d%H%M%S)"

# Cache-Busting: ?v=... in index.html aktualisieren
sed -i '' -E "s/\?v=[A-Za-z0-9]+/?v=${STEMPEL}/g" frontend/index.html 2>/dev/null \
  || sed -i -E "s/\?v=[A-Za-z0-9]+/?v=${STEMPEL}/g" frontend/index.html

git add -A
git commit -m "${NACHRICHT}"
git push github main
git push uberspace main

echo "Deploy fertig (v=${STEMPEL}). Offene DB-Schritte ggf. nicht vergessen!"
