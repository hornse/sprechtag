<!-- VENDORED aus hornse/koordination v1.0.0 – dort ändern, hierher kopieren! -->
# Regeln der Reihe

Gilt für alle Projekte, die diese Datei führen. **Quelle ist
`hornse/koordination`**; die Kopien in den Projekten sind vendored und
werden vom Bestandslauf gemessen.

**Änderungen gehören in die Quelle** und werden von dort zurückkopiert,
nie umgekehrt. Wer hier etwas ändert, ohne die Version zu erhöhen und
neu zu verteilen, erzeugt genau die Drift, wegen der es diese Datei
gibt.

**Kein Projektname steht in dieser Datei.** Der Geltungsbereich ist:
wer sie führt.

**Was hier nicht steht:** alles Projekteigene — Port, Datenmodell,
Fachfragen, der eigene Stack — sowie die technischen Fallstricke von
PHP, Router und WebUntis. Die stehen in `FALLSTRICKE.md`, die nur die
Projekte führen, die sie brauchen.

---

## 1 — Arbeitsweise

**„Ich weiß es nicht" statt einer plausiblen Vermutung.** Befunde müssen
aus gelesenem Code oder einem echten Lauf stammen, nicht aus Suchtreffern
oder Mustererkennung.

**Vermutungen aussprechen und als solche benennen** — mit dem Versuch
dazu, der sie entscheiden würde. Etwa die Hälfte der so gekennzeichneten
Vermutungen war falsch, und jede hat trotzdem einen Versuch veranlasst,
der eine Antwort brachte. Eine unausgesprochene Annahme wäre in die
Umsetzung gewandert.

**Was nicht geprüft werden kann, gehört nicht in die Umsetzung.**

**Ein Versuch ändert eine Sache.** Lässt sich das nicht einrichten, weil
das Werkzeug die eine nur zusammen mit der anderen kann, ist **das** das
Problem, das zuerst gelöst wird. Zwei Änderungen auf einmal machen jedes
Ergebnis unlesbar.

**Rückfragen vor Entscheidungen mit Tragweite, nicht danach.**

**Paketweise arbeiten, nicht alles auf einmal.**

**Beobachten schlägt raten.** Wo eine Oberfläche dieselbe Sache kann, die
gesucht wird: zuerst mitschneiden. Kandidatenlisten sind ein
Ersatzvorgehen für den Fall, dass es nichts zu sehen gibt.

**Gefundene Fehler werden immer gemeldet.** Behoben werden sie, wenn sie
im Auftrag liegen oder der Auftrag sonst nicht ausführbar ist —
andernfalls gehören sie in den Bericht, nicht in denselben Commit.

**Bei einem roten Testlauf anhalten und melden, nicht reparieren.** Das
ist die Grenze der vorigen Regel: Ein Fund nebenbei darf mitbehoben
werden, ein rotes Testergebnis nicht.

---

## 2 — Prüfungen und Gegenproben

**Jede neue Prüfung braucht eine Gegenprobe.** Fehlerfall künstlich
herstellen, die Prüfung muss anschlagen. Sonst weiß niemand, ob sie
überhaupt etwas prüft.

**Die Gegenprobe wird gegen die echte, belegte Struktur gebaut**, nicht
gegen eine nachgebildete. Sobald eine fremde Struktur belegt ist, gehört
sie als Testfall ins Repo. Ausgedachte Beispiele prüfen nur die eigenen
Annahmen.

**Und die Gegenprobe selbst wird geprüft**, indem der geprüfte Code
absichtlich beschädigt wird. Schlägt sie dann nicht an, prüft sie nichts
— das ist bereits vorgekommen, bei Gegenproben, die ein Auftrag
ausdrücklich verlangt hatte.

**Die Form des Ergebnisses prüfen, nicht nur das Verschwinden des
Falschen.** Ein Ausdruck, der aus `?v=DEV` ein `?v=PROBEDEV` macht, hat
`?v=DEV` auch beseitigt — und trotzdem alles falsch gemacht.

**Eine Prüfung ohne ihre Voraussetzung gilt nicht als bestanden, sie
sagt es.** Null Funde sind ein Fehler, kein Ergebnis; ein leerer Lauf
sieht sonst aus wie ein sauberer.

**Wird eine Prüfung erweitert, muss die Prüfungszahl um den erwarteten
Betrag steigen.** Bleibt sie gleich oder steigt sie um weniger, ist die
Erweiterung nicht wirksam geworden. Diese Zahl hat mehrfach einen Fehler
aufgedeckt, den sonst niemand gesehen hätte.

**Die erwartete Zahl wird genannt, nachdem feststeht, welche Prüfungen
geschrieben werden** — nicht als Schätzung vorab. Eine Ankündigung, die
regelmäßig zu niedrig ausfällt, ist keine Erwartung mehr, sondern eine
Formalie, und fängt dann auch den Fall nicht, für den sie da ist.

**Eine Prüfung kann selbst regredieren.** Sie lebt in denselben Dateien
wie der Code; ein Rückschritt nimmt sie mit. „Alles grün" ist eine
Aussage über den Code **und** die Prüfung. Prüfungszahlen deshalb in der
Commit-Meldung festhalten, bei einem Modul zusätzlich im Changelog.

**Wo eine Prüfung erweitert und ein Fehler behoben wird: die Prüfung
zuerst.** Die rote Meldung dazwischen belegt, dass die Behebung nötig war
und die Prüfung greift.

**Ein Test auf Vorhandensein ist kein Test auf Richtigkeit.** Wo ein Wert
von woanders stammt, prüft der Test die **Verbindung** zur Quelle. Und wo
sich die Verbindung strukturell herstellen lässt — eine Referenz statt
einer Kopie —, ist das besser als jede Prüfung.

**Prüfe das Eindeutige gründlich, das Uneindeutige gar nicht.** Wo ein
Zwischenzustand legitim ist, schlägt eine Rot/Grün-Prüfung grundlos an —
und wird dann abgeschaltet oder ignoriert. Dann fehlt sie auch dort, wo
sie recht hätte.

**Wo eine Prüfung nur durch Gegenproben belegt ist, wird das
dazugesagt.** Eine Gegenprobe zeigt, dass eine Prüfung funktioniert. Sie
zeigt nicht, dass sie die Wirklichkeit trifft.

**Vor jeder Auslieferung: nicht behaupten, sondern ausführen.**

---

## 3 — Shell

**`export LC_ALL=C`** in jedem Skript mit Zahlenvergleichen. Ohne das
kann ein Zahlenvergleich je nach Locale unterschiedlich ausfallen.

**`grep` mit Exit-Code 1 bricht unter `set -e` ab**, auch wenn das der
Erfolgsfall ist. `|| true` anhängen — aber **nur** um den echten
Erfolgsfall abzufangen, nie um einen Werkzeugfehler.

**Prüfskripte schlagen auf ihre eigenen Suchmuster an.** `--exclude` für
das Skript selbst.

**Trocken ist der Standard.** Wo ein Skript etwas Zerstörendes tun kann,
verlangt der echte Lauf einen ausdrücklichen Schalter
(`TROCKEN=${TROCKEN:-1}`). Ein Probelauf darf nie davon abhängen, dass
eine Textersetzung greift — genau daran ist einmal ein Probelauf zum
echten Lauf über sechs Repos geworden.

**Vor der Verwendung nachsehen, nicht annehmen** — und im Zweifel die
portable Form wählen. Die Werkzeuge auf dem Arbeitsrechner sind nicht die
auf dem Server.

**Anwesenheit einer Datei wird durch Auflisten geprüft, nicht durch
`grep`.** `grep` über eine vorhandene Datei prüft ihren Inhalt, nicht die
Anwesenheit der Datei daneben. `ls -1` und `git status --short` vor jedem
Commit, der Dateien von außen übernimmt.

---

## 4 — Ermittlung statt Aufzählung

**Kein Projektname in einem Skript.** Weder in einer Liste noch in einer
Fallunterscheidung. Der Bestand wird gefunden, nicht aufgezählt. Eine
feste Liste hat mehrfach ein Projekt übersehen.

**Auftragsdateien zählen keine Projekte auf, sie ermitteln den Bestand.**
Eine feste Liste vergisst das nächste Projekt — genau der Fehler, vor dem
sie warnt.

**Fest verdrahtete Projektlisten sind die gefährlichste Stelle einer
gewachsenen Reihe.** Sie fallen unbemerkt auseinander, weil nichts warnt.
Kommt ein Projekt dazu, gehört ein `grep` über alle Aufzählungen dazu —
Skripte, Doku, Demoseiten.

**Ein Ablageort ist eine Konvention, kein Merkmal.** Er trägt, solange
alle Projekte dieselbe Konvention haben — eine gewachsene Reihe hat sie
nicht. Herkunft wird am Inhalt erkannt, nicht am Pfad.

**Konkrete Befehle in einer Auftragsdatei sind Vermutungen**, solange sie
nicht gelaufen sind. Entweder etwas schreiben, das **nachsieht** (`find`
statt Liste, `ls` vor dem Aufruf eines Skripts), oder den Befehl
ausdrücklich als ungeprüft kennzeichnen.

---

## 5 — Aufträge über mehrere Repos

**Ausgangsstand zuerst.** Vor jeder Änderung die betroffenen Projekte
testen und das Ergebnis notieren.

**Vor dem Lauf: `git status` in allen betroffenen Repos.** Sonst ist
hinterher nicht zu trennen, was das Werkzeug getan hat.

**Der letzte Schritt bleibt manuell.** Ein Deploy über mehrere Repos
macht eine Änderung wirksam, die niemand einzeln zurücknimmt — dabei soll
ein Mensch die Ausgabe sehen. Innerhalb eines Projekts gehört
`deploy.sh` weiterhin zum Auftrag.

**Der Bericht jedes Laufs wird ins Repo committet.** Ohne abgelegten
Vorbericht ist keine Vorher-Nachher-Aussage prüfbar — und darauf beruht
die Regel über Prüfungszahlen.

**Ein Eintrag, der eine Auftragsdatei benennt, wird nicht ohne sie
committet.** Ein angekündigter Auftrag, der fehlt, verleitet dazu, ihn
aus seiner eigenen Ankündigung abzuleiten. Dann ist der Beleg ein Zirkel
und die Stelle verloren, an der stand, was verlangt war.

---

## 6 — Vendoring, Module, Versionen

**Vendored heißt kopiert, nicht abgetippt.** Änderungen gehören ins
Modul-Repo und werden von dort zurückkopiert, nie umgekehrt.

**Jede vendorte Datei trägt einen Kopfvermerk der Form**

```
VENDORED aus hornse/<modul> vX.Y.Z – dort ändern, hierher kopieren!
```

Der Bestandslauf liest ihn; die Form ist deshalb keine Geschmacksfrage.

**Bewährtes gehört ins Modul — nicht kopieren.** Was sich in einem
Projekt bewährt hat und allgemein nützlich ist, wandert ins Modul-Repo,
und die anderen bedienen sich daraus.

**Vor dem Vergeben einer Versionsnummer den Bestand ansehen:** Ist sie
frei, und ist der eigene Ausgangsstand der neueste? Mehrfach hat eine
Nummer etwas anderes behauptet als der Inhalt.

**Wo eine Versionsnummer vergeben wird, wird sie getaggt.** Eine Nummer
in einer Commit-Meldung, einem Changelog oder einem Kopfvermerk ist eine
Behauptung; ein Tag ist der Beleg. Fünfmal hintereinander ist das Taggen
unterblieben, ohne dass es jemandem auffiel.

**Eine Angabe, die nicht gepflegt wird, ist schlechter als keine.** Sie
sieht aus wie eine Auskunft und ist eine Falle. Wo sich ein Zustand
ableiten lässt, wird er nicht aufgeschrieben.

**`deploy.sh` muss den Push auch dann erreichen, wenn es nichts zu
committen gibt.** `git commit` bricht sonst unter `set -e` ab. Muster:
`git add -A`, dann `if ! git diff --cached --quiet; then git commit …`.

**Cache-Busting mit einem Ausdruck über beliebige Zeichen**, nicht nur
Ziffern. `?v=DEV` ändert sich sonst nie.

**Nichts Ausgeliefertes an der Projektwurzel** — es sei denn, die
Projektwurzel **ist** der Docroot. Der Dienst läuft mit Arbeitsverzeichnis
gleich Projektwurzel; liegt dort ein Verzeichnis, dessen Name am Anfang
einer ausgelieferten URL steht, wird die Datei am Router vorbei
behandelt. Maßgeblich ist der Docroot, nicht ein bestimmter
Verzeichnisname — die Reihe kennt dafür mehrere gleichwertige
Anordnungen.

---

## 7 — Erscheinungsbild

**Kein Farbwert außerhalb von `ci-tokens.css`.** Ausgenommen sind
Kategorienpaletten, die nur ein Projekt braucht: im `:root`-Block des
Projekts, mit Kommentar, der die Entscheidung festhält. Diese Ausnahme
ist ausdrücklich beschlossen und kein Schlupfloch.

**Keine erfundenen Modulklassennamen.** Ein geratener Klassenname sieht
richtig aus und hat einfach keine Regel — so verschwanden einmal alle
Karten eines Projekts. Wo ein Name nicht belegt ist, wird projekteigen
gebaut, aus denselben Tokens.

**Keine Webfonts, keine externen Ressourcen.**

**`[hidden]` hat das letzte Wort.** Jede `display`-Regel im Projekt-CSS
berücksichtigt das Attribut; das Projekt-CSS wird nach dem Modul geladen
und überschriebe es sonst.

---

## 8 — Auswertung fremder Antworten

**Stimmigkeit ist nicht Plausibilität.** Eine Zahl, die formal passt und
sachlich unmöglich ist, muss eigens abgefangen werden — sonst ist die
Prüfkette lückenlos und trotzdem wertlos.

**Widersprüche werden als Widerspruch ausgegeben, nicht als Ergebnis.**
„Daten da, Treffer null" heißt *kein Befund*, nicht *negativer Befund*.

**Wo aus einer Liste auf den Aufbau geschlossen wird, genügt ein Eintrag
nicht.** Der erste ist oft der leere.

**Ein verbreiteter Feldname allein ist kein Erkennungsmerkmal.** Es
braucht einen eindeutigen Pfad oder eine Kombination.

**Wo die Struktur einer fremden Antwort geraten werden musste, liefert
der Bericht die Struktur mit** — Schlüsselpfade und Typen, keine Werte.

**Eine Regel, die einmal einen Fehler behoben hat, ist deshalb nicht
allgemeingültig.** Beides festhalten, nicht das eine durch das andere
ersetzen.

**Ein Befund nennt die zweite Erklärung mit.** Wo zwei Ursachen infrage
kommen, ist eine Meldung, die nur eine nennt, eine Auskunft in die
falsche Richtung.

---

## 9 — Datenschutz

**Es geht um Daten Minderjähriger.** Datensparsamkeit ist hier eine
Anforderung, keine Haltung.

**Passwörter werden nicht gespeichert und nicht protokolliert.**

**Berichte enthalten keine Personendaten** — weder Namen noch
Fremdschlüssel. Für die Frage „gibt es einen brauchbaren Schlüssel"
genügen Zählwerte. Was nicht gebraucht wird, wird nicht ausgegeben.

**Personenbezogene Endpunkte gehören nicht in eine Anwendung**, die
ohne sie auskommt — nicht in ein einzelnes Werkzeug, nicht in irgendein
anderes.

**Wird ein Mitschnitt zu Testdaten, werden zuerst alle Zugangsdaten
ersetzt**, dann der Rest übernommen. Platzhalter gleicher Länge, damit
Längenprüfungen weiter etwas prüfen. Nicht umgekehrt.

**Anonymisierung darf die Auskunft nicht zerstören.** Wo eine Antwortform
belegt ist und keine Personendaten enthält, tritt eine gezielte
Auswertung an die Stelle des Anonymisierers — sie gibt aus, was benannt
ist, statt zu verbergen, was verdächtig aussieht.

---

## 10 — Form der Projektdateien

**`CLAUDE.md` wird bei jedem Sessionstart gelesen.** Sie liegt im Repo
und wird per Push geteilt. Was dort steht, muss nicht mehr erklärt
werden.

**Keine Tagesaufgaben.** Dort stehen Dinge, die in sechs Monaten noch
gelten sollen.

**Entscheidungen mit Begründung stehen in `docs/ENTSCHEIDUNGEN.md`** und
werden per `@`-Import eingebunden. Chronologisch, neue Einträge unten
angefügt; alte werden nicht geändert, sondern durch neue aufgehoben.

**Sprache ist Deutsch** — Bezeichner, Kommentare, Ausgaben, Commits.

**Lizenz ist GPL-3.0-or-later.**

**Keine Frameworks, kein Build-Schritt**, weder für PHP noch für
JavaScript.
