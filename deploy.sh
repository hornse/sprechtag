#!/usr/bin/env bash
# ============================================================
# deploy.sh – sprechtag
# Aufruf: ./deploy.sh "Commit-Nachricht" datei1 [datei2 …]
#
# Ablauf (siehe docs/ENTSCHEIDUNGEN.md, E3 und E4):
#   1. Jede Änderung im Baum muss namentlich übergeben sein.
#      Unverfolgte oder nicht genannte Dateien halten den Lauf an –
#      kein `git add -A`. Damit ist das Geprüfte auch das Committete.
#   2. Alle Prüfungen laufen: ./tests-sprechtag.sh und sämtliche
#      Suiten in tests/ (PHP und JS). Eine rote hält den Lauf an.
#   3. Cache-Busting-Stempel in index.html, Commit der genannten
#      Dateien (entfällt, wenn nichts zu committen ist), Push nach
#      GitHub und Uberspace.
#
# DB-Migrationen werden NICHT übertragen – separat einspielen:
#   mysql hornse_sprechtag < sql/NN_*.sql
#
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -euo pipefail
export LC_ALL=C
cd "$(dirname "$0")"

NACHRICHT="${1:?Aufruf: ./deploy.sh \"Commit-Nachricht\" datei1 [datei2 …]}"
shift
DATEIEN=("$@")

genannt() {
  local d
  for d in ${DATEIEN[@]+"${DATEIEN[@]}"}; do
    [ "$d" = "$1" ] && return 0
  done
  return 1
}

# ---------- 1. Sichtung: nichts wird still mitgenommen ----------
UNVERFOLGT=()
NICHT_GENANNT=()
while IFS= read -r p; do
  [ -z "$p" ] && continue
  genannt "$p" || UNVERFOLGT+=("$p")
done < <(git ls-files --others --exclude-standard)
while IFS= read -r p; do
  [ -z "$p" ] && continue
  genannt "$p" || NICHT_GENANNT+=("$p")
done < <(git diff --name-only HEAD)

if [ "${#UNVERFOLGT[@]}" -gt 0 ] || [ "${#NICHT_GENANNT[@]}" -gt 0 ]; then
  echo "ANGEHALTEN: Der Baum enthält Änderungen, die nicht übergeben wurden."
  for p in ${UNVERFOLGT[@]+"${UNVERFOLGT[@]}"};       do echo "  unverfolgt:   $p"; done
  for p in ${NICHT_GENANNT[@]+"${NICHT_GENANNT[@]}"}; do echo "  nicht genannt: $p"; done
  echo "Entweder namentlich übergeben oder vorher aus dem Baum nehmen."
  exit 1
fi

# ---------- 2. Prüfungen ----------
for w in php node; do
  command -v "$w" > /dev/null 2>&1 \
    || { echo "ANGEHALTEN: $w fehlt – Suiten in tests/ nicht ausführbar."; exit 1; }
done

ROT=()
echo "Prüfung: tests-sprechtag.sh"
if ./tests-sprechtag.sh; then :; else ROT+=("tests-sprechtag.sh"); fi

SUITEN=0
echo ""
echo "Prüfung: Suiten in tests/"
for f in tests/run*.php tests/*_test.js; do
  [ -f "$f" ] || continue
  SUITEN=$((SUITEN + 1))
  case "$f" in
    *.php) w=php ;;
    *)     w=node ;;
  esac
  if AUSGABE=$("$w" "$f" 2>&1); then
    echo "  ✓ $f ($(printf '%s\n' "$AUSGABE" | grep -c '✓' || true) Prüfzeilen)"
  else
    echo "  ✗ $f – vollständige Ausgabe:"
    printf '%s\n' "$AUSGABE" | sed 's/^/      /'
    ROT+=("$f")
  fi
done
# Null Suiten ist ein Fehler, kein sauberer Lauf.
if [ "$SUITEN" -eq 0 ]; then
  echo "ANGEHALTEN: keine Suite in tests/ gefunden."
  exit 1
fi
echo "  $SUITEN Suiten gelaufen."

if [ "${#ROT[@]}" -gt 0 ]; then
  echo ""
  echo "ANGEHALTEN: ${#ROT[@]} Prüfung(en) rot – nichts committet, nichts ausgeliefert:"
  for r in "${ROT[@]}"; do echo "  $r"; done
  exit 1
fi

# ---------- 3. Stempel, Commit, Push ----------
STEMPEL="$(date +%Y%m%d%H%M%S)"
sed -i '' -E "s/\?v=[A-Za-z0-9]+/?v=${STEMPEL}/g" frontend/index.html 2>/dev/null \
  || sed -i -E "s/\?v=[A-Za-z0-9]+/?v=${STEMPEL}/g" frontend/index.html

git add -- ${DATEIEN[@]+"${DATEIEN[@]}"} frontend/index.html
echo ""
echo "Wird committet:"
git diff --cached --stat
if ! git diff --cached --quiet; then
  git commit -m "${NACHRICHT}"
else
  echo "  (nichts zu committen)"
fi
# Scheitert ein Push, wird angehalten und gesagt, welche Gegenstelle
# welchen Stand hat – nicht wiederholt. Ein halber Push ist sonst ein
# stiller Zustand: GitHub neu, Server alt, und niemand sieht es.
STAND="$(git rev-parse --short HEAD)"
if ! git push github main; then
  echo "ANGEHALTEN: Push nach github gescheitert. Keine Gegenstelle hat ${STAND}."
  exit 1
fi
if ! git push uberspace main; then
  echo "ANGEHALTEN: Push nach uberspace gescheitert."
  echo "  github hat ${STAND}, uberspace NICHT – der Server läuft auf dem alten Stand."
  echo "  Nicht blind wiederholen: erst Ursache klären."
  exit 1
fi

echo "Deploy fertig (v=${STEMPEL}). Offene DB-Schritte ggf. nicht vergessen!"
