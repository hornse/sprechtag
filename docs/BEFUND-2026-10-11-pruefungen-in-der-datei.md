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

> **Nachtrag 12.10.2026:** Es sind inzwischen **sieben Arten**; 5 bis 7
> stehen im Nachtrag am Ende. „Vier“ oben und unten ist der Stand vom
> 11.10.2026.

**Dazu eine Grenze der Abhilfe selbst:** Auch die Suche im Rumpf (v0.9.85)
misst Text. Ein Rücksprung vor dem Block lässt Bedingung und Aufruf
unverändert dastehen. Die Rumpf-Prüfung blieb grün, und erst die
ausgeführte (v0.9.86) zählte null Aufrufe (HB14). Umgekehrt schlug die
Rumpf-Prüfung bei einer Umformulierung ohne Wirkung fälschlich an
(`Number(…)` statt `parseInt(…, 10)`); die ausgeführte blieb grün. **Wo
sich die Eigenschaft ausführen lässt, ist das die bessere Prüfung**
(REIHENREGELN 2: drei Stufen, nur die dritte misst die Sache).

**Zweiter Fall, v0.9.89, und zwar nachdem die Grenze bekannt war.** Die
Prüfung „ansichtHilfe zeigt den Absatz unter ‚Datenschutz‘“ in
`frontend_datenschutz_test.js` suchte im Rumpf, nicht in der Datei, und
verlangte die Schreibweise `el('h4', null, 'Datenschutz'));` vor der
Schleife über `datenschutzAbsaetze()`. Bei der Neugliederung der Hilfe nach
Rollen wurde sie rot, obwohl die Absätze weiter im Datenschutz-Abschnitt
standen. Vorhergesagt war, dass sie den Umbau übersteht: Gesehen war nur,
dass sie im Rumpf sucht, nicht, was sie dort sucht. Seit v0.9.89 wird
`ansichtHilfe` ausgeführt; geprüft ist, dass die Absätze im Abschnitt
„Datenschutz“ stehen, vollständig, in ihrer Reihenfolge, und auf der ganzen
Seite je genau einmal. **Die Gegenprobe, die den Unterschied zeigt, ist
dieselbe wie HB14:** `if (false)` vor der Schleife (DS10). Der Text steht
unverändert da, es wird nur nichts gezeigt; die alte Prüfung wäre grün
geblieben, die ausgeführte schlägt an. **Die Grenze greift also auch dann,
wenn sie bekannt ist** — „im Rumpf“ klingt nach der Abhilfe und ist nur
ihre erste Hälfte.

**Fünftes Beispiel, v0.9.90: Art 4 in einer Prüfung, die gegen Art 4
gebaut war.** Die Hilfe-Prüfung verlangt, dass jedes Zitat „…“ der Hilfe
als Text im Code steht. Sie suchte das Zitat irgendwo in einer
Zeichenkette. Die Mutation HZ7 machte in der Hilfe aus „Absagen“ das
Zitat „Termin absagen“, das es als Beschriftung nicht gibt. Die Prüfung
blieb grün: Sie fand es in `'Diesen Termin absagen'`, dem Tooltip eines
anderen Knopfs. Das ist Art 4, hier als **Teilstück zählt als Ganzes**.
Seit v0.9.90 muss das Zitat an Grenzen stehen (davor Anfang der
Zeichenkette oder „, danach ihr Ende, “, „(“, „.“, „:“ oder „,“).
**Die Art trifft also auch eine Prüfung, die gegen sie gerichtet ist** —
gefunden hat es die Mutationsserie, nicht das Lesen.

Die geschärfte Prüfung hat sofort einen echten Fehler gefunden, keine
Formalie: Die FAQ nannte das Feld „Hinweis an die Lehrkraft“. Es heißt
„Optionaler Hinweis an die Lehrkraft“. Wer nach dem genannten Namen
sucht, findet es nicht.

**Sechstes Beispiel, v0.9.93: eine Prüfung, die nur zufällig grün war.**
Beim Umzug der Brute-Force-Bremse nach `backend/api/anmeldebremse.php`
wurde in `frontend_loginlog_test.js` die Prüfung „Erfolge nur bei
aktiv+erfolge geloggt“ rot; ihr Text war mit dem Code gewandert. Die
Nachbarprüfung derselben Suite, „Protokoll nur bei aktiv sichtbar“, blieb
grün, und zwar ohne Grund: Sie suchte `marke_wert($pdo, 'login_log_aktiv'`
in ganz `index.php`, und dieser Text steht auch in der Einstellungsroute.
Belegt im Probebaum: Den eigentlichen Wächter vor dem Lesen des Protokolls
entfernt, und die alte Fassung der Prüfung bleibt grün (heute Mutation
LL1). Das ist Art 1, und zwar seit v0.9.37. Wäre nur die rote Prüfung
behoben worden, hätte die grüne weiter nichts bewacht.

**Die Lehre daraus:** Wird eine Prüfung einer Suite durch einen Umzug rot,
lohnt der Blick auf die übrigen Prüfungen **derselben** Suite. Sie suchen
oft auf dieselbe Weise, und eine davon ist vielleicht nur zufällig grün.

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

## Nachtrag 12.10.2026 — Arten 5 bis 7

Art 5 ist in v0.9.95 gefunden. Arten 6 und 7 waren schon gefunden und
besprochen, standen aber nur im Verlauf, nicht hier (Betreiber,
12.10.2026). Sie kommen in der Reihenfolge an, in der sie eingetragen
werden, nicht in der, in der sie gefunden wurden.

| Art | Woran sie scheitert | Fall | gefunden durch |
|---|---|---|---|
| **5. Geprüft wird eine Kopie** | Die Prüfung sucht richtig, die Testdaten stellen den Fall her – aber geprüft wird ein **Nachbau** des Codes, nicht das Original. Sie bleibt grün, gleich was der echte Code tut. | `tests/run.php`, elf Prüfungen zu Anonymisierung und Gruppenfeldern der Stammdaten-Sondierung (bis v0.9.94) | Lesen, beim Entfernen von Weg B (v0.9.95) |
| **6. Die Testdaten stellen den Fall nicht her** | Die Prüfung sucht richtig, aber den Fall, den sie ausschließen soll, gibt es in ihren Daten nicht. | `run_elternzuordnung`: „KEINE Schüler-Konten“ bleibt grün ohne Rollenfilter (v0.9.94) | Mutation |
| **7. Die Suite bricht ab und meldet es nicht** | Ein Abbruch erzeugt keine rote Zeile; die Prüfungen danach fehlen einfach. | E5 (Node), `run_kalender_abo` (PHP), AT11 (`run_abgesagte_termine`) | Laufzeitverhalten, Zählung, Mutation |

**Art 5.** Der Kommentar über den elf Prüfungen sagte es selbst: „Die
Funktion braucht Client-Objekte; hier wird nur die Logik der Feldauswahl
geprüft, indem der relevante Code nachgebildet wird.“ **Jemand wusste es
und hat es trotzdem so gebaut.** Das ist dieselbe Lage wie beim Kommentar
zum Kalender-Abo (E21), nur umgekehrt: Dort versprach der Kommentar mehr,
als der Code tat; hier gesteht er weniger ein, als man beim Lesen der
Prüfungsnamen annimmt („Vorname anonymisiert“ klingt nach der Funktion).
Die Prüfungen sind entfernt; der Gegenentwurf ist
`tests/run_sondierung_stammdaten.php`, die die echte Funktion mit Attrappen
der Außenwelt ausführt. **Elf Prüfungen, die nichts prüfen, sind schlechter
als keine, weil sie Sicherheit vortäuschen.**

**Art 6.** Die Schülerkonten in den Testdaten von `run_elternzuordnung`
haben leere `tags` und können gar nicht zugeordnet werden; der Rollenfilter
in `mit_eltern_zu_kind()` ist deshalb nie gefragt. Gefunden hat es ein
Eingriff ohne rote Zeile beim Wirkungsnachweis für v0.9.94, nicht das Lesen.
**Nicht behoben.** Zweites Beispiel, in einer Attrappe statt in Daten
(v0.9.95): Die erste Attrappe für `getStudents` in
`run_sondierung_stammdaten` lieferte Kennungen, die in pageconfig nicht
vorkamen. Der alte Code wählte daraufhin eine Bewertung ohne
„getStudents“, und die Prüfung „Bewertung nennt getStudents nicht“ war grün,
ohne hinzusehen. Behoben vor dem Einchecken; der Grund steht an der
Attrappe.

**Art 7.** Dreimal: In `frontend_laufzeit_test.js` greift das `catch`
nie, rot wird es nur durch Nodes Exit 1 (E5). `run_kalender_abo` endete
mit einem PHP-Fatal nach 10 von 16 Prüfungen, ohne eine Zeile mit ✗ (E5,
Nachtrag 11.10.2026). AT11: `run_abgesagte_termine` endete nach 23 Zeilen
mit Exit 255, weil die eigene Hilfsfunktion nur
`InvalidArgumentException` fing (E5, drittes Vorkommen). **Vorbild für die
Behebung:** `tests/frontend_abgesagte_termine_test.js` meldet einen Abbruch
als eigene rote Zeile („✗ Suite abgebrochen: …“).

### Wonach in anderen Suiten zu suchen ist (Fortsetzung)

- **Art 5:** Prüft die Suite den Code oder eine Kopie davon?
- **Art 6:** Stellen die Testdaten den Fall her, den die Prüfung
  ausschließen soll? – Das gilt für Attrappen ebenso wie für Daten.
- **Art 7:** Sagt die Suite, wenn sie aufgehört hat?
