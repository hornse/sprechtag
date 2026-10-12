#!/bin/bash
# ============================================================
# ersatz_belegen.sh – belegt, dass ersetzte Testdaten dasselbe prüfen
#
# Aufruf: tests/werkzeuge/ersatz_belegen.sh BASIS [SATZ]
#   BASIS: der Commit mit den alten Testdaten (z. B. 308c09a)
#   SATZ:  Datei, die SUITEN, EINGRIFFE und ALT_AUCH setzt (seit v0.9.97,
#          z. B. tests/werkzeuge/ersatz_v0997.sh). Ohne SATZ gilt der Satz
#          von v0.9.94 unten.
#
# Baut zwei Probebäume außerhalb des Repos: "neu" ist der heutige
# Arbeitsbaum, "alt" derselbe mit den Testdateien aus BASIS. In beiden
# wird derselbe Eingriff am geprüften Code gefahren; die roten Positionen
# (laufende Nummer der Prüfzeile, nicht ihr Name) müssen je Suite gleich
# sein. Namen taugen nicht zum Vergleich, weil manche den Wert enthalten.
#
# Je Eingriff wird belegt: dass er angekommen ist (Prüfsumme vorher und
# nachher verschieden), dass er an der gemeinten Stelle sitzt (genau ein
# Treffer seines Suchmusters, in alt und neu; seit v0.9.97) und dass die
# Rücknahme angekommen ist (cmp gegen die Sicherung). Ein Eingriff ohne
# rote Zeile ist ein Fehler, kein Befund.
#
# ALT_AUCH: weitere Dateien, die im Baum "alt" aus BASIS kommen, weil eine
# Suite sie liest und sie im selben Zug geändert sind (v0.9.97:
# config.example.php, deren Beispiel run_admin_kuerzel prüft).
#
# Erstellt für v0.9.94 (Kontodaten in Testdaten). Gibt keine Werte aus.
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -euo pipefail
export LC_ALL=C

BASIS="${1:?Aufruf: $0 BASIS-Commit}"
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

SUITEN=(tests/run_elternzuordnung.php tests/run_slots.php tests/run_mitteilungen.php
        tests/run_sitzung_versand.php tests/run_varianten.php tests/run_warteschlange.php
        tests/run.php)

ALT_AUCH=()

# Eingriffe: [N@]Datei | Perl-Ersetzung. N ist die erwartete Trefferzahl
# des Suchmusters (ohne Angabe 1); ersetzt wird immer nur der erste. Jeder trifft Code, der die ersetzten
# Werte verarbeitet (Kennung des Kontos, Personen-ID, Name).
EINGRIFFE=(
  "backend/api/mitteilungen.php|s/mit_name_normieren\(\(string\)\\\$tag\) !== /(string)\\\$tag !== /"
  "backend/api/mitteilungen.php|s/\\\$konten\[\\\$id\] = \['id' => \\\$id,/\\\$konten[\\\$id] = ['id' => \\\$id + 1,/"
  "backend/auth/extractors.php|s/'userId'   => isset\(\\\$user\['id'\]\) \? \(int\)\\\$user\['id'\]/'userId'   => isset(\\\$user['id']) ? (int)\\\$user['person']['id']/"
  "backend/auth/extractors.php|s/\(int\)\\\$user\['person'\]\['id'\] : null,/(int)\\\$user['id'] : null,/"
  "2@backend/api/mitteilungen.php|s/'recipientUserIds'    => \[\\\$empfaenger\],/'recipientUserIds'    => [\\\$empfaenger + 1],/"
  "backend/api/mitteilungen.php|s/\\\$empfaengerArt === 'eltern' \? 0 : \\\$empfaengerUserId,/\\\$empfaengerArt === 'eltern' ? 0 : 0,/"
  "backend/api/sondierung.php|s#/student\|child\|kind\|pupil\|ward/i#/student|child|kind|pupil|ward|person/i#"
)

if [ -n "${2:-}" ]; then
  # shellcheck source=/dev/null
  source "$2"
fi

# ---------- Probebäume ----------
mkdir -p "$TMP/neu" "$TMP/alt"
(cd "$REPO" && git ls-files -z --cached --others --exclude-standard \
  | xargs -0 -I{} sh -c 'mkdir -p "$1/$(dirname "{}")" && cp "{}" "$1/{}"' _ "$TMP/neu")
[ -f "$REPO/backend/config.php" ] && cp "$REPO/backend/config.php" "$TMP/neu/backend/config.php"
cp -R "$TMP/neu/." "$TMP/alt/"
VERSCHIEDEN=0
for s in "${SUITEN[@]}"; do
  (cd "$REPO" && git show "${BASIS}:${s}") > "$TMP/alt/$s"
  cmp -s "$TMP/alt/$s" "$TMP/neu/$s" || VERSCHIEDEN=$((VERSCHIEDEN + 1))
done
for d in ${ALT_AUCH[@]+"${ALT_AUCH[@]}"}; do
  (cd "$REPO" && git show "${BASIS}:${d}") > "$TMP/alt/$d"
  cmp -s "$TMP/alt/$d" "$TMP/neu/$d" && echo "  Hinweis: $d in alt und neu gleich"
done
echo "Probebäume: ${#SUITEN[@]} Suiten, davon $VERSCHIEDEN in alt und neu verschieden; ${#ALT_AUCH[@]} weitere Datei(en) aus BASIS"
[ "$VERSCHIEDEN" -gt 0 ] || { echo "FEHLER: alt und neu gleich – nichts zu vergleichen"; exit 1; }

rote_positionen() {   # $1 Baum, $2 Suite → "3,7" oder "-"
  local w=php; case "$2" in *.js) w=node;; esac
  (cd "$1" && "$w" "$2" 2>&1 || true) | grep -E '^\s*(✓|✗)' \
    | awk '/✗/ {printf "%s%d", (n++ ? "," : ""), NR} END {if (!n) printf "-"}'
}

FEHLER=0
echo ""
echo "Ohne Eingriff:"
for s in "${SUITEN[@]}"; do
  a="$(rote_positionen "$TMP/alt" "$s")"; n="$(rote_positionen "$TMP/neu" "$s")"
  echo "  $s  alt=$a  neu=$n"
  [ "$a" = "-" ] && [ "$n" = "-" ] || FEHLER=$((FEHLER + 1))
done

nr=0
for e in "${EINGRIFFE[@]}"; do
  nr=$((nr + 1))
  datei="${e%%|*}"; ausdruck="${e#*|}"
  soll=1; case "$datei" in *@*) soll="${datei%%@*}"; datei="${datei#*@}";; esac
  echo ""
  echo "Eingriff E$nr ($datei):"
  for baum in alt neu; do
    cp "$TMP/$baum/$datei" "$TMP/sicherung"
    vor="$(shasum "$TMP/$baum/$datei" | cut -c1-12)"
    treffer="$(perl -0ne "print scalar(${ausdruck}g) || 0" "$TMP/$baum/$datei")"
    if [ "$treffer" != "$soll" ]; then
      echo "  FEHLER: Suchmuster in $baum trifft $treffer-mal, erwartet $soll"; FEHLER=$((FEHLER + 1))
    fi
    perl -0pi -e "$ausdruck" "$TMP/$baum/$datei"
    nach="$(shasum "$TMP/$baum/$datei" | cut -c1-12)"
    if [ "$vor" = "$nach" ]; then
      echo "  FEHLER: Eingriff in $baum nicht angekommen ($vor)"; FEHLER=$((FEHLER + 1))
    fi
    for s in "${SUITEN[@]}"; do
      eval "rot_${baum}_$(basename "$s" | tr -c 'A-Za-z0-9_\n' '_')=\"\$(rote_positionen \"\$TMP/\$baum\" \"\$s\")\""
    done
    cp "$TMP/sicherung" "$TMP/$baum/$datei"
    if ! cmp -s "$TMP/sicherung" "$TMP/$baum/$datei"; then
      echo "  FEHLER: Rücknahme in $baum nicht angekommen"; FEHLER=$((FEHLER + 1))
    fi
    echo "  $baum: Treffer $treffer, Prüfsumme $vor → $nach → zurück $(shasum "$TMP/$baum/$datei" | cut -c1-12)"
  done
  irgendwo_rot=0
  for s in "${SUITEN[@]}"; do
    k="$(basename "$s" | tr -c 'A-Za-z0-9_\n' '_')"
    eval "a=\$rot_alt_$k; n=\$rot_neu_$k"
    [ "$a" = "-" ] && [ "$n" = "-" ] && continue
    irgendwo_rot=1
    gleich="gleich"; [ "$a" = "$n" ] || { gleich="VERSCHIEDEN"; FEHLER=$((FEHLER + 1)); }
    echo "  $s  alt=$a  neu=$n  $gleich"
  done
  if [ "$irgendwo_rot" -eq 0 ]; then
    echo "  FEHLER: keine Suite rot – der Eingriff prüft nichts"; FEHLER=$((FEHLER + 1))
  fi
done

echo ""
if [ "$FEHLER" -eq 0 ]; then
  echo "ERGEBNIS: ${#EINGRIFFE[@]} Eingriffe, alle angekommen, alle zurückgenommen, rote Positionen alt = neu"
else
  echo "ERGEBNIS: $FEHLER Fehler"; exit 1
fi
