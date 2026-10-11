# Befund 11.10.2026 — Prüfungen, die in der Datei statt in der Sache suchen

**Art:** Schwäche der Prüfungen, nicht der Anwendung. **Gefunden** beim
Neubau von `tests/frontend_halbtags_test.js` (v0.9.85, v0.9.86). **Für
später:** Die anderen Suiten werden Suite für Suite durchgesehen
(`docs/AUFRAEUMEN.md`, Punkt 4).

## Was gemessen ist

In einem Probebaum lief je ein Eingriff an der gemeinten Stelle gegen die
alte Fassung (bis v0.9.84) und die neue. Ausgangslauf und Rücknahme waren
je Eingriff belegt. Die Eingriffe stehen als Mutationen HB1–HB14 in
`tests/mutationen.sh`.

**Vier verschiedene Arten von Schwäche** ließen einen Eingriff ohne rote
Zeile durch. Wer andere Suiten durchsieht, sucht nach **allen vier**, nicht
nur nach doppelten Fundstellen:

| Art | Woran sie scheitert | Fall | Mutation |
|---|---|---|---|
| **1. Zweite Fundstelle** | Die Zeichenkette steht auch anderswo in der Datei; die gemeinte Stelle verschwindet, die Prüfung bleibt grün. | „halbtags“ 13-mal in `app.js`; die Definition von `lehrerAusfall` hielt die Prüfung grün, die den Aufruf prüfen sollte | HB1–HB7 |
| **2. Das Wesentliche nicht geprüft** | Die Prüfung sucht Begleitmerkmale (Beschriftung, Adresse), nicht das, worauf es ankommt. | „Halbtags-Markierung“ prüfte Titel und Adresse, nicht das Feld `halbtags` im PATCH | HB8 |
| **3. Kommentar zählt als Code** | Auskommentierter Code besteht die Prüfung. | „Schema idempotent“ (SQL-Kommentar), „Halbtags-Toggle-Route“ (PHP-Kommentar) | HB11, HB12 |
| **4. Namensanfang zählt als Name** | `includes('function x')` trifft auch `function x_alt(`. | „slot_haelfte_fenster vorhanden“ | HB13 |

**Dazu eine Grenze der Abhilfe selbst:** Auch die Suche im Rumpf (v0.9.85)
misst Text. Ein Rücksprung vor dem Block lässt Bedingung und Aufruf
unverändert dastehen. Die Rumpf-Prüfung blieb grün, und erst die
ausgeführte (v0.9.86) zählte null Aufrufe (HB14). Umgekehrt schlug die
Rumpf-Prüfung bei einer Umformulierung ohne Wirkung fälschlich an
(`Number(…)` statt `parseInt(…, 10)`); die ausgeführte blieb grün. **Wo
sich die Eigenschaft ausführen lässt, ist das die bessere Prüfung**
(REIHENREGELN 2: drei Stufen, nur die dritte misst die Sache).

## Wonach in anderen Suiten zu suchen ist

- **Art 1:** Wie oft steht die gesuchte Zeichenkette in der Datei? Mehr als
  einmal heißt: Der Eingriff an der gemeinten Stelle ist nötig, um zu
  wissen, ob die Prüfung hinsieht.
- **Art 2:** Prüft die Prüfung das, was ihr Name verspricht, oder nur
  etwas, das daneben steht?
- **Art 3:** Den Code löschen, den Kommentar stehen lassen – bleibt die
  Prüfung grün?
- **Art 4:** Wird ein Name mit einer Grenze gesucht (Klammer, Wortende),
  oder nur sein Anfang?

## Ausgangslage für die Durchsicht (gezählt, nicht bewertet)

Gezählt sind nur Suchen in ganz `app.js` (`js.includes(`, `.test(js)`,
`js.match(`, `js.indexOf(`), nicht ihre Wirkung. 15 Suiten tun das 4- bis
16-mal; die vier häufigsten:

| Suite | Suchen in ganz `app.js` |
|---|---|
| `frontend_sidebar_test.js` | 16 |
| `frontend_anzeige_test.js` | 14 |
| `frontend_hilfetext_test.js` | 12 |
| `frontend_buchen_uebersicht_test.js` | 12 |

**Darunter sind legitime Formen:** Prüfungen auf Abwesenheit („X kommt
nirgends mehr vor“) gehören in die ganze Datei, ebenso absichtliche
Zählungen (etwa die Kartenansichten in `frontend_mobil_test.js`). Die Zahl
heißt also nicht „15 kaputte Suiten“; wie viele Suchen eine der vier Arten
tragen, ist ungemessen.

**Ausdrücklich dazu: `frontend_datenschutz_test.js`.** Sie ist in v0.9.83
selbst erweitert worden und sucht einige Sätze in der ganzen Datei, etwa
„Abo-Link, mit dem Ihre kommenden Termine“. Dasselbe Muster, in einer
Suite dieses Strangs.
