# Idee 11.10.2026 — Kindauswahl in der Elternansicht

**Art:** Notiz des Betreibers. **Nicht jetzt bauen.**

Betrifft die Auswahl „Kind“ in der Buchungsansicht der Eltern
(`frontend/app.js`, Abschnitt „Kind wählen“, Feld `kind-wahl`).

## 1. Bei genau einem Kind: Name als Text statt Auswahl

Ein Auswahlfeld mit einer einzigen Option ist sinnlos. Bei genau einem
Kind entfällt die Auswahl; stattdessen steht der Name als Text da.

**Der Name bleibt aber sichtbar, als Kontrolle.** Begründung des
Betreibers: Eltern können weitere, inzwischen volljährige Kinder haben, für
die früher gebucht wurde. Dann soll erkennbar sein, für wen gerade gebucht
wird.

Am Code gelesen, für den Bau: Fehlt der Name, zeigt die Auswahl heute
„Schüler-ID “ und die Kennung. Ein Name als Text bräuchte für diesen Fall
eine eigene Antwort. Eine Kennung als Anzeige für die Eltern wäre keine.

## 2. Kacheln statt Auswahlliste bei mehreren Kindern: erwogen, vorerst NICHT

Die Lehrkraft-Kacheln tragen Zusatzinformationen: Fach, Klassenleitung,
„hat Sie eingeladen“, Anwesenheit. Dort vergleicht man und entscheidet.
Bei Kindern gibt es nichts zu vergleichen: Es sind ein bis drei Namen, und
man weiß, welches man meint. Eine Kachelreihe wäre viel Fläche für eine
Entscheidung, die niemand abwägt, und schöbe den Inhalt nach unten, gerade
auf dem Handy.

**Mittelweg, falls die Optik später stört:** flache Knöpfe nebeneinander
statt Kacheln. Das ist Geschmack, keine Sachfrage.
