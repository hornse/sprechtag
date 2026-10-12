# ============================================================
# ersatz_v0997.sh – Satz für tests/werkzeuge/ersatz_belegen.sh (v0.9.97)
# Aufruf: tests/werkzeuge/ersatz_belegen.sh 1277cac tests/werkzeuge/ersatz_v0997.sh
#
# Ersetzt sind in v0.9.97 echte Namen, Kürzel und Kennungen in Testdaten
# (Regel des Betreibers: alles außer Paulowski). Jeder Eingriff trifft Code,
# der die ersetzten Werte verarbeitet: Namen in Mitteilungstexten,
# Langnamen und Kürzel aus dem Stundenplan, Namensvergleich und Kennungen
# bei der Elternzuordnung, Kürzelvergleich der Verwaltung, Kennung in der
# Sondierung, das Beispiel in config.example.php.
#
# Nicht durch einen Eingriff belegbar, nur durch „ohne Eingriff grün in
# alt und neu“: run_stammsatz.php, frontend_reihenfolge_test.js und
# frontend_zustand_test.js bilden den geprüften Ablauf im Test selbst nach
# (Art 5, docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md). Sie stehen in
# SUITEN, damit der Vergleich ohne Eingriff sie mit erfasst.
#
# Wird gelesen (source), nicht ausgeführt. Gibt keine Werte aus.
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================

SUITEN=(tests/run_einladungstext.php tests/run_klausuren.php tests/run_mitteilungen.php
        tests/run_slots.php tests/run_stammsatz.php tests/run_elternzuordnung.php
        tests/run.php tests/run_admin_kuerzel.php
        tests/frontend_reihenfolge_test.js tests/frontend_zustand_test.js)

# run_admin_kuerzel prüft den Wortlaut des Beispiels in config.example.php;
# beide sind im selben Zug geändert.
ALT_AUCH=(backend/config.example.php)

EINGRIFFE=(
  # Name der Lehrkraft am Ende der Einladung
  'backend/api/mitteilungen.php|s/\. \$lehrkraft;(\n\n    return \[\x27betreff\x27 => \x27Einladung)/;$1/'
  # Name der Lehrkraft in der Absage: am Anfang der Funktion geleert. Er
  # steht im Text zweimal, die Prüfung fragt nur nach Vorkommen; ein
  # Eingriff an einer der beiden Stellen bleibt deshalb grün (gemeldet).
  'backend/api/mitteilungen.php|s/(string \$lehrkraft, string \$freitext = \x27\x27\): array\n\{\n)/$1    \$lehrkraft = \x27\x27;\n/'
  # Name der Lehrkraft in der Bestätigung
  'backend/api/mitteilungen.php|s/\(string\)\(\$t\[\x27name\x27\] \?\? \$t\[\x27kuerzel\x27\] \?\? \x27\x27\)/(string)(\$t[\x27kuerzel\x27] ?? \x27\x27)/'
  # Langname aus dem Stundenplan
  'backend/auth/extractors.php|s/\$lehrer\[\$kuerzel\] = \(string\)\(\$c\[\x27longName\x27\] \?\? \$kuerzel\);/\$lehrer[\$kuerzel] = \$kuerzel;/'
  # Vertretung (CHANGED) zählt als Unterricht
  'backend/auth/extractors.php|s/in_array\(\$status, \[\x27REGULAR\x27, \x27CANCELLED\x27\], true\);/in_array(\$status, [\x27REGULAR\x27, \x27CANCELLED\x27, \x27CHANGED\x27], true);/'
  # verlegte Klausur (EXAM/CHANGED) zählt als Klausur
  'backend/auth/extractors.php|s/&& \$status === \x27REGULAR\x27;/;/'
  # Namensvergleich bei der Elternzuordnung ohne Normierung
  'backend/api/mitteilungen.php|s/mit_name_normieren\(\(string\)\$tag\) !== /(string)\$tag !== /'
  # Kennung des Elternkontos verfälscht
  'backend/api/mitteilungen.php|s/\$konten\[\$id\] = \[\x27id\x27 => \$id,/\$konten[\$id] = [\x27id\x27 => \$id + 1,/'
  # Namensnormierung: Wortreihenfolge nicht mehr sortiert
  'backend/api/mitteilungen.php|s/    sort\(\$teile\);\n//'
  # Namensnormierung: keine Kleinschreibung (Umlaute)
  'backend/api/mitteilungen.php|s/if \(function_exists\(\x27mb_strtolower\x27\)\) return mb_strtolower\(\$s, \x27UTF-8\x27\);/if (true) return \$s;/'
  # Kürzelvergleich empfindlich
  'backend/api/webuntis_adapter.php|s/\? mb_strtolower\(trim\(\$k\), \x27UTF-8\x27\) : strtolower\(trim\(\$k\)\)/? trim(\$k) : trim(\$k)/'
  # Komma-Eintrag nicht mehr aufgeteilt
  'backend/api/webuntis_adapter.php|s/foreach \(explode\(\x27,\x27, \(string\)\$eintrag\) as \$k\)/foreach ([(string)\$eintrag] as \$k)/'
  # Kürzel des WebUntis-Admins: erstes Zeichen statt erstes Kürzel
  'backend/api/webuntis_adapter.php|s/return wu_kuerzel_liste\(\$wcfg\[\x27admin_kuerzel\x27\] \?\? \[\]\)\[0\] \?\? null;/return \$wcfg[\x27admin_kuerzel\x27][0] ?? null;/'
  # Kennung der Lehrkraft in der Sondierung fort
  'backend/api/sondierung.php|s/\(\$id !== null \? \x27 \(\x27 \. \$id \. \x27\)\x27 : \x27\x27\)/\x27\x27/'
  # Beispiel in config.example.php fort (gekoppelt an run_admin_kuerzel)
  'backend/config.example.php|s/        \/\/     \x27admin_kuerzel\x27 => \[[^\]]*\],\n//'
)
