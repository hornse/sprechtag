# Projektgedächtnis: sprechtag

Diese Datei wird von Claude Code bei **jedem** Sessionstart automatisch
gelesen. Sie liegt im Repo und wird per Push geteilt. Was hier steht,
muss nicht mehr erklärt werden.

Nicht hier hinein gehören Tagesaufgaben. Hier stehen Dinge, die in sechs
Monaten noch gelten sollen.

Ergänzend:
- Regeln der Reihe: @REIHENREGELN.md
- Fallstricke PHP/Router/WebUntis: @FALLSTRICKE.md

---

## Was hier nicht steht

**Die Regeln der Reihe stehen in `REIHENREGELN.md`**, die technischen
Fallstricke in `FALLSTRICKE.md`. Beide sind vendorte Kopien aus
`hornse/koordination` und oben importiert — sie gelten hier, ohne dass
diese Datei sie wiederholt. Wer sie ändern will, ändert die Quelle und
verteilt neu; der Bestandslauf misst die Kopien.

In diese Datei gehört nur, was **dieses Projekt** ausmacht.

## Der Stack

Ermittelt aus dem Repo, nicht aus dem Gedächtnis.

| | |
|---|---|
| Domain | `sprechtag.hornse.de` |
| Dienst | PHP built-in Server via supervisord, **Port 8085** |
| Backend | PHP 8.1+, eigener Router in `backend/router.php` |
| Datenbank | MariaDB über PDO, DSN aus `backend/config.php` (Vorlage: `config.example.php`) |
| Frontend | Vanilla JS, HTML, CSS — kein Build-Schritt |
| Gerüst | **eigenes**, nicht `ci-huelle` |
| Module | nur `ci-tokens.css` und `ci-icons.svg` aus `ci-css`, dazu WebUntis unter `backend/auth/` |
| Auslieferung | `deploy.sh` pusht auf `github` **und** `uberspace` |
| Testskript | `tests-sprechtag.sh` |
| Lizenz | GPL-3.0-or-later |

## Das eigene Gerüst ist Absicht

Dieses Projekt benutzt **nicht** die Schale aus `ci-css`, sondern ein
eigenes Gerüst mit eigenen Klassen. Deshalb führt es von den fünf
Moduldateien nur die beiden, die keine Schale voraussetzen — die
Farbtoken und die Symbole.

**Das ist kein Rückstand.** Eine fehlende Datei und eine absichtlich
fehlende Datei sehen im Dateisystem gleich aus — der Bestandslauf in
`hornse/koordination` kann den Unterschied nur kennen, wenn er in dessen
`docs/bestand-ausnahmen.md` steht. Wer hier die Schale nachrüstet, macht
die Oberfläche kaputt, nicht heil.
