<!-- VENDORED aus hornse/koordination v2.2.0 – dort ändern, hierher kopieren! -->
# Regeln der Reihe

Gilt für alle Projekte, die diese Datei führen. **Quelle ist
`hornse/koordination`**; die Kopien in den Projekten sind vendored und
werden vom Bestandslauf gemessen.

**Fällt beim Arbeiten in einem Projekt auf, dass eine Regel falsch,
unvollständig oder überholt ist: die Kopie wird nicht geändert.** Der
Befund gehört nach `koordination` — als Eintrag im
Entscheidungsprotokoll oder als Auftragsdatei. Wer die Kopie ändert,
bekommt beim nächsten Bestandslauf einen Befund und hat bis dahin eine
zweite Wahrheit geschaffen.

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
dazu, der sie entscheiden würde. Eine unausgesprochene Annahme wäre in
die Umsetzung gewandert.

**Was nicht geprüft werden kann, gehört nicht in die Umsetzung.**

**Ein Versuch ändert eine Sache.** Lässt sich das nicht einrichten, weil
das Werkzeug die eine nur zusammen mit der anderen kann, ist **das** das
Problem, das zuerst gelöst wird. Zwei Änderungen auf einmal machen jedes
Ergebnis unlesbar.

**Bleibt eine Behebung ohne sichtbare Wirkung, suche eine zweite
Ursache, bevor du die erste verwirfst.** Zwei Fehler mit demselben
Symptom sind der Fall, in dem eine richtige Korrektur wie ein Irrtum
aussieht — und dann rückgängig gemacht wird.

**Rückfragen vor Entscheidungen mit Tragweite, nicht danach.**

**Paketweise arbeiten, nicht alles auf einmal.**

**Beobachten schlägt raten.** Wo eine Oberfläche dieselbe Sache kann, die
gesucht wird: zuerst mitschneiden. Kandidatenlisten sind ein
Ersatzvorgehen für den Fall, dass es nichts zu sehen gibt.

**Gefundene Fehler werden immer gemeldet.** Behoben werden sie, wenn sie
im Auftrag liegen oder der Auftrag sonst nicht ausführbar ist —
andernfalls gehören sie in den Bericht, nicht in denselben Commit.

**Was geschrieben ist, ist nicht, was angekommen ist.** Einmal wurde ein
Auftrag formuliert, **nicht abgeschickt** und trotzdem als erteilt
behandelt. Dasselbe gilt für eine Datei, die bereitliegt und nicht
kopiert wurde, und für einen Befehlsblock, von dem nur die Hälfte lief.

**Wer einen Auftrag erteilt, nennt im nächsten Schritt etwas, das nur
nach seiner Ausführung dasteht** — eine Zahl, eine Zeile, einen Commit.
Das kostet nichts und trennt „gesagt" von „geschehen".

**Eine örtliche Behebung ist keine Behebung.** Wo ein Fehler eine *Art*
hat, gehört die Frage dazu, wie viele Stellen dieser Art es gibt — und
ob sich eine **Engstelle** bauen lässt, an der es nur noch eine gibt.

**Und eine Engstelle ist statisch prüfbar, wo ein Datenfluss es nicht
ist.** „Läuft irgendwo jemand über eine ungeprüfte Antwort" bräuchte eine
Datenflussanalyse. „In keiner Anwendungsdatei steht ein unmittelbarer
Aufruf" ist ein `grep` und gibt dieselbe Zusicherung. **Wo eine Regel
über Werte nicht entscheidbar ist, ist die Regel über Aufrufstellen oft
gleichwertig und billig.**

**Bei einem roten Testlauf anhalten und melden, nicht reparieren.** Das
ist die Grenze der vorigen Regel: Ein Fund nebenbei darf mitbehoben
werden, ein rotes Testergebnis nicht.

**Drei Einzelfälle sind ein Muster.** Wer denselben Fall zum dritten Mal
einzeln entscheidet, entscheidet ihn zum dritten Mal falsch. Die Frage
gehört dazu: Braucht es eine Regel?

---

## 2 — Prüfungen und Gegenproben

**Jede neue Prüfung braucht eine Gegenprobe.** Fehlerfall künstlich
herstellen, die Prüfung muss anschlagen. Sonst weiß niemand, ob sie
überhaupt etwas prüft.

**Die Gegenprobe wird gegen die echte, belegte Struktur gebaut**, nicht
gegen eine nachgebildete. Sobald eine fremde Struktur belegt ist, gehört
sie als Testfall ins Repo. Ausgedachte Beispiele prüfen nur die eigenen
Annahmen.

**Das gilt auch für jede Ausprägung einer bekannten Struktur.** **Eine
Prüfung ist nur so gut wie die Fälle in ihren Daten.**

**Und welche Fälle es gibt, entscheidet der Kenntnisstand, nicht die
Sorgfalt.**

**Deshalb bekommt ein neuer Beleg über ein fremdes System eine zweite
Frage**, nicht nur einen Eintrag in der Befunddatei:

> **Was ändert dieser Beleg an dem, was wir prüfen?**

Sie ist billig: In vier von fünf Fällen lautet die Antwort „nichts" — im
fünften findet sie einen Fehler, der im Betrieb steht.

**Eine Herkunftsangabe in Testdaten ist eine Behauptung.** Wer Testdaten
beschriftet, belegt jede Angabe einzeln oder nennt sie **erfunden**. Und
eine Prüfung hält die Angabe fest, sonst driftet sie wie jede andere
ungepflegte Angabe.

**Und die Gegenprobe selbst wird geprüft**, indem der geprüfte Code
absichtlich beschädigt wird. Schlägt sie dann nicht an, prüft sie
nichts.

**Die Beschädigung muss belegt angekommen sein.** Trefferzahl vorher und
nachher, oder eine Zahl, die sich verändern muss. **Dreimal war die
Ausgabe grün oder still, und „keine rote Zeile" nach einem
fehlgeschlagenen Eingriff liest sich wie Bestehen.**

Ein Eingriff ohne Wirkungsnachweis belegt nichts — auch nicht, dass die
Prüfung greift.

**Und wo eine Gegenprobe zwei Ergebnisse vergleicht, gehört der Nachweis
dazu, dass sie sich überhaupt unterscheiden.** Besser als eine
Ungleichheit ist eine eigene Prüfung mit dem **erwarteten Wert**.

**Ein Versuch, der gutgeht, sagt nichts über den Fall, den er nicht
enthielt.**

**Und ein Beleg, dessen Werkzeug fehlt, ist nicht wiederholbar.** Das
Werkzeug, mit dem Gegenproben gefahren werden, gehört zu den Belegen.

**Ein sauberer Ausgangszustand belegt nur, dass die Prüfungen grün sind
— nicht, dass sie hinsehen.**

**Mutationen werden in Serie gefahren, nicht einzeln.** Mehrere
Nichttreffer sind ein Muster, einer ist Rauschen — und ein einzelner
Durchrutscher verleitet zur Einzelfallerklärung.

**Und die Rücknahme braucht ihren eigenen Nachweis.**

**Eine Rücknahme, die nicht ankommt, ist gefährlicher als eine Mutation,
die nicht trifft** — die eine verschweigt einen Fehler, die andere
verdirbt jede folgende Messung. Nach jeder Rücknahme gehört deshalb der
Vergleich gegen den Sollstand: Prüfsumme oder leerer `git diff`.

**Eine Prüfumgebung darf nicht mehr kennen als der Betrieb.** **Wo die
Umgebung großzügiger ist als die Wirklichkeit, prüft sie die eigene
Großzügigkeit mit.**

**Wonach gesucht wird, wird dort gesucht, wo es wirken soll** — im Rumpf
der Funktion, nicht in der Datei. Eine Suche über eine ganze Datei
belegt **Vorkommen, nicht Wirkung**.

**Dasselbe gilt für Stilregeln und für doppelte Darstellungen.** Steht
eine CSS-Eigenschaft in mehreren Regeln, wird **in der Regel** gesucht,
nicht in der Datei. Und wo eine Oberfläche dieselbe Sache zweimal
darstellt, **zählt** die Prüfung die Vorkommen, statt eines zu suchen —
sonst übersteht das Entfernen aus einer der beiden jede Prüfung.

**Eine Quelle ist nicht eine Darstellung.** Eine Prüfung, die belegt,
dass zwei Ansichten aus **derselben Funktion** kommen, verhindert zwei
Wahrheiten — und sagt nichts darüber, ob auch nur **eine** erscheint.
**Zwei richtige Anzeigen desselben sind trotzdem eine zu viel.**

Neben „eine Quelle" gehört deshalb „genau eine Darstellung". Die erste
misst den Code, die zweite das Ergebnis — und nur die zweite braucht eine
ausgeführte Anzeige.

**Und die Entscheidung gehört an eine Stelle.** **Zwei Schwellen für
dieselbe Frage sind zwei Wahrheiten, auch wenn beide dieselbe Zahl
tragen.**

**Eine Prüfung an einem Intervall braucht einen Fall genau auf der
Grenze.** Ohne einen solchen Fall ist die Gegenprobe zur Grenzbedingung
wirkungslos, und das fällt nur auf, wenn man sie fährt.

**Wo zwei Stufen dieselbe Gefahr abwehren, wird jede einzeln belegt.**
Für die Dauer der Gegenprobe wird die andere ausgehängt. Sonst prüft man
die Redundanz statt der Stufen: Eine Mutation kann nur eine Stufe
entfernen, die zweite fängt, und alles meldet grün.

**Und wo sie sich nicht aushängen lassen, prüft die Gegenprobe den
Wortlaut der Meldung**, nicht nur dass eine kommt. Eine anschlagende
Mutation sagt sonst nicht, **welche** Stufe angeschlagen hat.

**Voraussetzung dafür ist, dass die Stufen sich unterschiedlich
melden** — zwei Sicherungen mit demselben Satz sind für die Gegenprobe
eine.

**Die Form des Ergebnisses prüfen, nicht nur das Verschwinden des
Falschen.** Ein Ausdruck, der aus `?v=DEV` ein `?v=PROBEDEV` macht, hat
`?v=DEV` auch beseitigt — und trotzdem alles falsch gemacht.

**Eine Prüfung ohne ihre Voraussetzung gilt nicht als bestanden, sie
sagt es.** Null Funde sind ein Fehler, kein Ergebnis; ein leerer Lauf
sieht sonst aus wie ein sauberer.

**Und ein Prüfschritt, dessen Voraussetzung von Hand hergestellt werden
muss, wird nie ausgeführt.** Das ist nicht derselbe Fall: Bei einer
fehlenden Voraussetzung meldet sich die Prüfung. Hier schweigt sie, und
**Schweigen ist von Bestehen nicht zu unterscheiden.**

Ein solcher Schritt stellt seine Voraussetzung selbst her, **oder er
läuft mit und meldet sichtbar, dass er übersprungen wurde** — oder er
ist keine Prüfung, sondern eine Anleitung. Alles drei ist zulässig; nur
muss dabeistehen, was davon es ist. **Nur von Hand gestartet, läuft er
nicht.**

**Wird eine Prüfung erweitert, muss die Prüfungszahl um den erwarteten
Betrag steigen.** Bleibt sie gleich oder steigt sie um weniger, ist die
Erweiterung nicht wirksam geworden.

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

**Ein Prüfausdruck darf nicht auf die Beschreibung der Regel
anschlagen.** Je besser eine Regel dokumentiert ist, desto
wahrscheinlicher steht ihr Wortlaut als Prosa in derselben Datei, die
geprüft wird — die Prüfung wird also gerade dort unzuverlässig, wo
sorgfältig gearbeitet wurde. **Die Gegenprobe dazu lautet: den Code
löschen, den Kommentar stehen lassen.**

Dasselbe gilt für jedes Werkzeug, das eine Datei liest, die ihr eigenes
Format erklärt: Beschreibung und Sache müssen trennbar sein.

**Und eine Zeichenkette ist so gefährlich wie ein Kommentar.** Die
Beschriftung einer Prüfung — `pruefe('lz_raumhinweis() wird gerufen',
…)` — sieht für jeden Suchausdruck wie ein Aufruf aus. **Gerade
sorgfältig benannte Suiten sind davon betroffen.** Für PHP trennt der
Tokenizer Kommentar, Zeichenkette und Code; für JavaScript und CSS fehlt
ein solches Werkzeug.

**Und umgekehrt: Wo ein Ausdruck escapt steht, findet ihn die Suche nach
seiner Klartextform nicht** — in einem Prüfmuster, einer Mutationsliste,
einem regulären Ausdruck. Beim Umzug einer Bedingung wäre einmal eine
Mutation verwaist, weil das Muster `m\.lehrer_id = \?` lautete; gefangen
hat es eine zweite Suche nach dem Kernwort. **Wer verschiebt, sucht
beide Formen.**

**Wo aus einem Vorfall bekannt ist, wie die falsche Fassung aussieht,
sucht die Prüfung ausdrücklich nach ihr.** Die Anwesenheit der richtigen
Form allein genügt nicht — sie schließt nicht aus, dass die falsche
danebensteht. Wer prüft, ob `$id > 0` vorkommt, muss zusätzlich prüfen,
dass `$id === -1` **nicht** vorkommt.

**Wo eine Prüfung die Nachbarschaft zweier Stellen misst statt eines
Vorkommens, braucht sie zwei Gegenproben:** die benachbarte Bedingung
entfernen, **und** sie entwerten, ohne sie zu entfernen — etwa zu
`if (true)`. Bleibt die Prüfung im zweiten Fall grün, misst sie Textnähe
und nicht Wirkung.

**Ein Test auf Vorhandensein ist kein Test auf Richtigkeit.** Wo ein Wert
von woanders stammt, prüft der Test die **Verbindung** zur Quelle. Und wo
sich die Verbindung strukturell herstellen lässt — eine Referenz statt
einer Kopie —, ist das besser als jede Prüfung.

**Eine Prüfung, die eine bestimmte Datei liest, prüft diese Datei —
nicht die Regel.** Wo eine Regel für ein ganzes Projekt gilt, sucht die
Prüfung im ganzen Projekt und nicht dort, wo der Fall zuerst auftrat.

**Das ist schwerer zu finden als eine fehlende Prüfung**, weil niemand
nachsieht, wo eine bestandene Prüfung hingeschaut hat. Bei einer
fehlenden fehlt wenigstens eine Zahl.

**Wird der Aufruf einer ganzen Suite gelöscht, wird nichts rot** — nur
die Prüfungszahl sinkt. Das trifft jede Anwendung, die ihre Suiten als
Liste von Aufrufen führt, **und es trifft genau die Zahl, auf die sich
diese Reihe stützt.**

**Zwei Abhilfen, beide mit benanntem Schwachpunkt:** Eine Mindestzahl je
Suite müsste selbst gepflegt werden; die Suiten aus dem Verzeichnis zu
ermitteln fängt eine vergessene Zeile, aber keine gelöschte Datei.

**Solange keine gebaut ist, gilt: Eine gesunkene Prüfungszahl wird
erklärt, nicht hingenommen.** Sie ist das einzige Signal.

**Eine Darstellung wird am Dargestellten geprüft, nicht am Wert im
Code.** **Eine Messgröße wirkt erst am bestimmenden Element.** Wo es um
Sichtbares geht, prüft die Prüfung den sichtbaren Text — dafür muss
dessen Bildung eine ausführbare Funktion sein —, und für Größen braucht
es einen Blick im Browser.

**Und was sich nicht messen lässt, wird nicht als grüne Prüfung
geführt.**

> **Was offline messbar ist, wird gemessen. Was nur im Browser sichtbar
> ist, gehört als Abnahmepunkt in die Ausrollanleitung — nicht als grüne
> Prüfung.**

Eine Prüfung, die eine Darstellung nicht messen kann, soll nicht so tun.
Sonst zählt sie mit und belegt nichts.

**Ein Skript, das aus der Suite genommen wird, wird geteilt, nicht
herausgenommen.** Einmal lief eine Testdatei nicht mit, weil ein Teil
davon eine Datenbank braucht — und verlor damit auch die Prüfungen, die
keine brauchen.

**Ein Weg, den niemand aufruft, ist eine zweite Wahrheit.** **Ein toter
Weg ist schlimmer als ein falscher** — ein falscher fällt auf. Wie viele
Funktionen niemand aufruft, ist billig zu zählen und gehört in den
Bericht; als Rot/Grün-Prüfung erzeugte es Fehlalarme bei Schnittstellen,
die bewusst für später gebaut sind.

**Eine geprüfte Regel sagt nichts darüber, ob sie gefragt wird.**

**Das ist die Kehrseite eines Vorteils:** Eine reine Funktion ist
prüfbar, und **genau deshalb** wandert die Aufmerksamkeit dorthin. Der
Aufruf bleibt in der Schicht zurück, die niemand ausführt.

**Wo eine Entscheidung als reine Funktion herausgezogen wird, prüft
mindestens eine Prüfung die Aufrufstelle.** Und wo die Entscheidung vor
einem Rücksprung fallen muss, prüft sie die **Reihenfolge** im
Quelltext, nicht das Vorkommen.

**Aber eine Prüfung auf Reihenfolge ist keine auf Wirkung.** Drei
Stufen, und nur die dritte misst die Sache:

| Stufe | Was gemessen wird |
|---|---|
| „das Wort steht da" | Quelltext |
| „die Zeile steht an der richtigen Stelle" | **immer noch Quelltext** |
| „ohne Zeitraum wird nichts abgefragt" | die Eigenschaft |

**Die mittlere Stufe ist die gefährlichere der beiden schwachen.** Die
erste fällt beim Lesen auf; die zweite nicht, weil sie wie eine
Strukturprüfung aussieht — und Strukturprüfungen gelten hier als das
stärkere Mittel.

**Wo sich die Eigenschaft ausführen lässt, wird sie ausgeführt.** Die
Reihenfolge ist der Rückfall für den Fall, dass das nicht geht, und
gehört dann als solcher gekennzeichnet.

**Ein Name ist eine Schnittstelle.** In JavaScript gewinnt die letzte
Deklaration einer Funktion im selben Geltungsbereich — ohne Warnung,
gültiger Code, `node --check` schweigt. **Eine Prüfung „kein
Funktionsname zweimal im selben Geltungsbereich" ist ein Zehnzeiler und
trifft jede künftige Funktion.**

**Dasselbe in CSS, und dort schweigt alles.** Ein Klassenname mit zwei
Grundregeln in derselben Datei: Die spätere gewinnt, kein Werkzeug sagt
etwas.

**Eine Grundregel je Klassenname — Varianten und Medienabfragen
ausgenommen**, sonst wäre die Prüfung sofort rot und würde
abgeschaltet.

**Prüfe das Eindeutige gründlich, das Uneindeutige gar nicht.** Wo ein
Zwischenzustand legitim ist, schlägt eine Rot/Grün-Prüfung grundlos an —
und wird dann abgeschaltet oder ignoriert. Dann fehlt sie auch dort, wo
sie recht hätte.

**Eine Sicherung, die aus einem Vorfall stammt, bekommt beim nächsten
Umbau der Umgebung eine Bedingung — oder sie wird erneut begründet.**
Als der Vorfall geschah, deckte sie genau einen Fall ab. Klärt sich die
Umgebung, deckt dieselbe Regel womöglich zwei, und einer davon ist der
Normalfall.

**Niemand hat die Regel geändert; die Wirklichkeit ist unter ihr
weitergegangen.** Sie ist dann nicht falsch, sondern ohne Bedingung.

**Wo ein Bild zwei Ursachen haben kann, trennt keine schärfere Zählung —
sondern eine zweite, unabhängige Quelle.** Dieselbe Lage, zwei
Deutungen: Aus einer Quelle allein ist das nicht auflösbar, aus zweien
oft in einem Blick.

**Wo eine Prüfung nur durch Gegenproben belegt ist, wird das
dazugesagt.** Eine Gegenprobe zeigt, dass eine Prüfung funktioniert. Sie
zeigt nicht, dass sie die Wirklichkeit trifft.

**Das erste Exemplar einer Sorte hat keinen Vergleich und braucht
deshalb mehr.** Wo eine Prüfung darin besteht, gegen einen bereits
geprüften Bestand zu vergleichen, fehlt sie beim ersten Mal ersatzlos —
und das ist der Normalfall, nicht die Ausnahme.

Daraus folgt keine Prüfung, sondern zweierlei: Der erste Datensatz einer
neuen Sorte wird von Hand gegengelesen, **und es wird vermerkt, dass hier
nichts geprüft hat.** Und wo die Reihenfolge frei ist, lohnt es, zwei
gleichartige nacheinander zu nehmen — das zweite prüft das erste
rückwirkend mit.

**Läuft ein Erzeuger neu, wird vollständig gegen den ausgelieferten
Stand verglichen.** Nicht stichprobenweise — vollständig. **Ein Eintrag,
der mitten im Satz auf einem heilen Wort abbricht, besteht jede
Einzelprüfung.** Er ist grammatisch in Ordnung, er ist plausibel, und er
ist falsch. Nur das Vorher zeigt, dass etwas fehlt.

Der Vergleich ist bei einem neu laufenden Erzeuger billig und immer
verfügbar — der ausgelieferte Stand ist der Vorgänger, den das erste
Exemplar sonst nicht hat.

**Eine gekürzte Ausgabe kann die einzige Zeile verlieren, die zählt.**
`tail -3` über einen Testlauf, `-A2` über eine Suche: Wo ein Ergebnis
beschnitten wird, ist das Fehlen einer Zeile keine Auskunft.

**Vor jeder Auslieferung: nicht behaupten, sondern ausführen.**

---

## 3 — Shell

**`export LC_ALL=C`** in jedem Skript mit Zahlenvergleichen. Ohne das
kann ein Zahlenvergleich je nach Locale unterschiedlich ausfallen.

**`grep` mit Exit-Code 1 bricht unter `set -e` ab**, auch wenn das der
Erfolgsfall ist. `|| true` anhängen — aber **nur** um den echten
Erfolgsfall abzufangen, nie um einen Werkzeugfehler.

**Prüfskripte, die über den eigenen Baum nach Textmustern suchen,
schlagen auf ihre eigenen Suchmuster an.** `--exclude` für das Skript
selbst. **Für Skripte ohne Suchmuster gilt das nicht** — eines, das
Klammern in einer Stilvorlage zählt, hat nichts, woran es anschlagen
könnte.

**Trocken ist der Standard.** Wo ein Skript etwas Zerstörendes tun kann,
verlangt der echte Lauf einen ausdrücklichen Schalter
(`TROCKEN=${TROCKEN:-1}`). Ein Probelauf darf nie davon abhängen, dass
eine Textersetzung greift.

**Vor der Verwendung nachsehen, nicht annehmen** — und im Zweifel die
portable Form wählen. Die Werkzeuge auf dem Arbeitsrechner sind nicht
die auf dem Server. **`grep -P` gibt es auf macOS nicht**.

**Eine Mutation wird aus einer Sicherungskopie zurückgenommen, nie aus
dem Index.** `git checkout <datei>` stellt den letzten Commit her und
verwirft dabei alles, was in derselben Sitzung korrigiert, aber noch
nicht committet war.

**Anwesenheit einer Datei wird durch Auflisten geprüft, nicht durch
`grep`.** `grep` über eine vorhandene Datei prüft ihren Inhalt, nicht die
Anwesenheit der Datei daneben. `ls -1` und `git status --short` vor jedem
Commit, der Dateien von außen übernimmt.

**Und nach dem Commit wird der Durchgang geleert.** Eine Datei, die im
Übergabeordner liegenbleibt, lässt Platte und Repo auseinanderlaufen,
ohne dass etwas warnt — einmal führte das zu der Frage, ob ein Auftrag
noch offen sei, und im schlechteren Fall zu einer doppelten Ausführung.
Das `rm` kommt **nach** dem Commit, nie davor, solange die Kopie im
Durchgang die einzige ist.

**Sechs Fallen der interaktiven zsh**, alle schon eingetreten — und für
zwei davon gibt es **eine** Vorbeugung: **nach einem Variablennamen immer
`${…}`.** Der Doppelpunkt und die eckige Klammer sind derselbe Fall.

* **`#` ist dort kein Kommentarzeichen.** Ein erklärender Zusatz am
  Zeilenende wird zum Argument. Der Schaden entsteht nicht durch die
  Falle allein, sondern durch ihr Zusammentreffen mit einem `|| true`,
  das mehr abfängt als den Erfolgsfall.
* **Glob-Muster für ein Werkzeug gehören in Anführungszeichen.**
  `--include=*.js` expandiert die Shell selbst und bricht ab;
  `--include="*.js"` erreicht `grep`.
* **Ein `:` nach einem Variablennamen wird als Modifikator gelesen** —
  auch in doppelten Anführungszeichen. Aus `"$t:FALLSTRICKE.md"` wurde
  einmal `…/v1.16.0LLSTRICKE.md`; `${t}:FALLSTRICKE.md` ist richtig.

  **Diese Falle ist gefährlicher als die fünf anderen**, weil sie nicht
  abbricht und keinen Unsinn liefert, sondern ein **plausibles Ergebnis**.

  **`da39a3ee5e6b` ist die Prüfsumme der leeren Eingabe.** Wer sie einmal
  kennt, erkennt sie wieder. Belegen lässt sie sich mit
  `printf '' | shasum`.
* **Eine unquotierte Variable wird nicht in Wörter zerlegt.**
  `for f in $dateien` läuft in zsh einmal mit der ganzen Zeichenkette
  statt je Datei. In bash wäre dasselbe richtig — und genau das macht es
  gefährlich, weil Beispiele von außen fast immer bash voraussetzen.
* **Ein führendes `=` wird als Befehlspfad gelesen.** `echo ======`
  bricht ab; in Anführungszeichen läuft es. Betrifft jede Trennlinie in
  einer Ausgabe.
* **`path` ist an `PATH` gebunden.** `for path in …` überschreibt den
  Suchpfad der Shell, und danach findet kein Befehl mehr sich selbst.
  **Keine Falle, die ein falsches Ergebnis liefert, sondern eine, die die
  Umgebung zerlegt.**

In einem Skript mit `#!/bin/bash` gilt keine der sechs.

**Ein Block zum Einfügen ist ein Skript ohne Kopfzeile.** Er läuft in
der Shell, die der Leser hat, und jede Annahme darüber kann fallen.

**Wer eine Anleitung zum Einfügen schreibt, schreibt stattdessen ein
Skript** — oder verweist auf eines.

**BSD-`grep` hält eine Datei mit Sonderzeichen für binär** und findet
dann still nichts. Einmal galt daraufhin „kommt in der Shell-Historie
nicht vor" als Befund; `-a` lieferte 44 Treffer. **Gefangen hat es die
Gegenprobe mit einem Muster, das treffen musste** — ohne sie wäre der
Werkzeugfehler zur Auskunft geworden.

**Ein Suchbefehl über mehrere Repos beginnt mit dem Nachweis, dass er
sie sieht.** Einmal lief eine Schleife über neun Projektnamen im falschen
Arbeitsverzeichnis: `grep` fand die Verzeichnisse nicht, `2>/dev/null`
verschluckte den Grund, **und neun Nullen sahen aus wie „kommt nirgends
vor".**

`pwd` und `ls -d` auf zwei der Namen kosten nichts und machen aus den
Nullen eine Messung. **Und `2>/dev/null` gehört dort nicht hin** — es
fängt genau den Fehler ab, der die Auskunft entwertet.

**Eine Sollzahl ist eine Vermutung über die Umgebung des Empfängers.**
Wer für einen anderen einen Auftrag schreibt und eine erwartete Zahl mit
einem Werkzeug ermittelt, hat sich auf dieses Werkzeug festgelegt — ohne
zu wissen, ob es dort vorhanden ist und in welcher Fassung. Der Auftrag
prüft deshalb zuerst, dass das Werkzeug existiert, oder er kennzeichnet
die Zahl als ungeprüft.

---

## 4 — Ermittlung statt Aufzählung

**Kein Projektname in einem Skript.** Weder in einer Liste noch in einer
Fallunterscheidung. Der Bestand wird gefunden, nicht aufgezählt.

**Auftragsdateien zählen keine Projekte auf, sie ermitteln den Bestand.**
Eine feste Liste vergisst das nächste Projekt — genau der Fehler, vor dem
sie warnt.

**Fest verdrahtete Projektlisten sind die gefährlichste Stelle einer
gewachsenen Reihe.** Sie fallen unbemerkt auseinander, weil nichts warnt.
Kommt ein Projekt dazu, gehört ein `grep` über alle Aufzählungen dazu —
Skripte, Doku, Demoseiten.

**Ein Ablageort ist eine Konvention, kein Merkmal.** Er trägt, solange
alle Projekte dieselbe Konvention haben — eine gewachsene Reihe hat sie
nicht. Herkunft wird am Inhalt erkannt, nicht am Pfad. **Auch nicht an
der Nachbarschaft:** Dass eine Datei neben einer vendorten liegt, macht
sie nicht zur Kopie.

**Konkrete Befehle in einer Auftragsdatei sind Vermutungen**, solange sie
nicht gelaufen sind. Entweder etwas schreiben, das **nachsieht** (`find`
statt Liste, `ls` vor dem Aufruf eines Skripts), oder den Befehl
ausdrücklich als ungeprüft kennzeichnen.

---

## 5 — Aufträge über mehrere Repos

**Ausgangsstand zuerst.** Vor jeder Änderung die betroffenen Projekte
testen und das Ergebnis notieren.

**Was ein Auftrag aus einem anderen Repo braucht, steht im
Ausgangsstand.** Verweist er auf eine bestimmte fremde Datei — als
Vorbild, als Vergleich, als Vorlage —, wird sie **beigelegt**: als Kopie
im eigenen Repo, nicht als Zugriff über Verzeichnisgrenzen. Der
Nebengewinn: Im Repo steht dann, welche Fassung als Vorbild diente.

**Wer dagegen den Bestand ermittelt, braucht Zugriff auf alle** und kann
nichts beilegen. Die Trennlinie ist: eine **benannte** Datei wird
beigelegt, ein **ermittelter** Bestand wird vorausgesetzt und im ersten
Schritt geprüft.

**Eine Aussage über einen Zustand wird mit Datum weitergegeben oder vor
der Verwendung geprüft.** Versionsstand, Prüfungszahl, welches Repo was
führt — das veraltet von selbst. **Eine Entscheidung gilt, bis sie
aufgehoben wird; ein Zustand nicht.** **Eine Messung geht in der Form
weiter, in der gemessen wurde** — Aufruf, Konto, Zeit, Ergebnis. Eine
Antwort ohne ihren Aufruf hat dreimal zu einem Fehlschluss geführt.

**Und ein Datum aus einem Auftrag ist keine Messung.** **Ein relatives
Wort ist dieselbe Falle** — „gestern" wird ausgerechnet, und wer es
ausrechnet, braucht einen Bezugspunkt, den er ebenfalls nicht gemessen
hat. **Ebenso eine Zahl:** Nennt ein Auftrag eine aus einem Bericht,
verlangt er ihre Prüfung an der Quelle.

Das ist dieselbe Einsicht wie bei einem Prüfschritt, dessen Voraussetzung
von Hand hergestellt werden muss — eine Ebene höher: **Eine
Voraussetzung, die erst mitten im Lauf auffällt, ist eine, die im
Ausgangsstand fehlte.**

**Und eine Auftragsdatei benennt, welche Abschnitte einer vorhandenen
Erhebung sie voraussetzt.** Nicht die Datei — den Abschnitt. Bei einer
Erhebung von einigem Umfang liest man den Anfang und arbeitet dann; wer
die Stelle nennt, zwingt zum Nachschlagen an der richtigen. **Eine
Erhebung, deren Ergebnis beim Bauen nicht gelesen wird, ist verlorene
Arbeit** — und schlimmer als keine, weil sie den Eindruck erzeugt, die
Frage sei geklärt.

**Es ist kein Aufmerksamkeitsproblem.** Wer eine Erhebung beauftragt,
hat sie beim Schreiben im Kopf und beim Bauen nicht mehr — deshalb steht
die Benennung in der Auftragsdatei und nicht im Gedächtnis.

**Die Umkehrung gehört dazu:** Ein Abschnitt, den nach mehreren Aufträgen
keiner voraussetzt, war entweder überflüssig oder ist es geworden. Das
ist der Anlass, ihn zu streichen oder zu schärfen — nicht ein Gefühl.

**Vor dem Lauf: `git status` in allen betroffenen Repos.** Sonst ist
hinterher nicht zu trennen, was das Werkzeug getan hat.

**Wer in einem fremden Repo committet, addiert namentlich — nie `git add
-A`.** Ein Auftrag weiß, welche Dateien er anfasst; wenn nicht, ist das
das eigentliche Problem.

**Und ein sauberer Ausgangsstand ist keine Zusicherung über die
Laufzeit.** An mehreren Repos wird selten allein gearbeitet, auch wenn
nur eine Person beteiligt ist.

**Eine Änderung an einem Werkzeug, das über mehrere Repos läuft, wird im
Probebaum durchgespielt.** Den Baum kopieren, das Werkzeug daraufsetzen,
und **zuerst belegen, dass der Ausgangslauf dort Zahl für Zahl derselbe
ist** — ohne diesen Beleg ist der Probebaum eine Annahme. Danach lässt
sich die Änderung vollständig ausprobieren, ohne ein einziges Repo
anzufassen.

**Der letzte Schritt bleibt manuell.** Ein Deploy über mehrere Repos
macht eine Änderung wirksam, die niemand einzeln zurücknimmt — dabei soll
ein Mensch die Ausgabe sehen. Innerhalb eines Projekts gehört
`deploy.sh` weiterhin zum Auftrag.

**Remote und Zweig werden ausdrücklich genannt.** Die Reihe ist
gewachsen; die Upstreams zeigen je Repo verschieden mal auf die eine,
mal auf die andere Gegenstelle. `git push` ohne Argument bedient deshalb
nicht überall dieselbe Seite.

**Ein Push auf die Server-Gegenstelle ist eine Auslieferung**, keine
Vorbereitung: Am Bare-Repo hängt ein Hook, der auscheckt oder ausrollt.
Was mit hochgeht, geht damit in den Betrieb — auch Commits aus einem
anderen Vorhaben, die zufällig darunter liegen. **Und umgekehrt:**
Namentliches Addieren trennt Commits, nicht Pushes. Ein fremder Push
liefert eigene Commits mit aus — beim eigenen sieht man, was mitgeht,
beim fremden nicht.

**Ein Verteillauf pusht deshalb nur nach GitHub.** Die Auslieferung macht
die Anwendung selbst, mit ihrem Auslieferungsskript — dort läuft die
Prüfung, dort werden die Cache-Werte gesetzt, dort wird getaggt. **Muss
er doch nach der Server-Gegenstelle pushen, sieht er vorher nach, ob
lokale Commits darunterliegen, und hält an.**

**Der Bericht jedes Laufs wird ins Repo committet.** Ohne abgelegten
Vorbericht ist keine Vorher-Nachher-Aussage prüfbar — und darauf beruht
die Regel über Prüfungszahlen.

**Und der Bericht eines Laufs ist nicht die Ausgabe seines Werkzeugs.**
Einmal lag nur die Ausgabe des Bestandslaufs im Repo; der Bericht, der
sie deutete, stand in der Sitzung. Ein späterer Auftrag berief sich auf
ihn — und nannte damit eine Grundlage, die es nicht gab.

**Ein Eintrag, der eine Auftragsdatei benennt, wird nicht ohne sie
committet.** Ein angekündigter Auftrag, der fehlt, verleitet dazu, ihn
aus seiner eigenen Ankündigung abzuleiten. Dann ist der Beleg ein Zirkel
und die Stelle verloren, an der stand, was verlangt war.

---

## 6 — Vendoring, Module, Versionen

**Vendored heißt kopiert, nicht abgetippt.** Änderungen gehören ins
Quell-Repo und werden von dort zurückkopiert, nie umgekehrt.

**Jede vendorte Datei trägt einen Kopfvermerk der Form**

```
VENDORED aus hornse/<quelle> vX.Y.Z – dort ändern, hierher kopieren!
```

Der Bestandslauf liest ihn; die Form ist deshalb keine Geschmacksfrage.
In Markdown steht er als HTML-Kommentar.

**Bewährtes gehört ins Modul — nicht kopieren.** Was sich in einem
Projekt bewährt hat und allgemein nützlich ist, wandert ins Modul-Repo,
und die anderen bedienen sich daraus.

**Fehlt eine Modulvariante, bleibt der bisherige Zustand — oder es kommt
eine örtliche, vorläufige Zeile mit Verweis auf den Befund. Nie ein
schlechterer Zustand.** Die Vorgabe „nicht selbst bauen" hatte keine
Bedingung für den Fall, dass das Modul noch nicht liefert. **Die
vorläufige Zeile ist die Bedingung, die fehlte**, und sie fällt weg,
sobald das Modul liefert.

**Vor dem Vergeben einer Versionsnummer den Bestand ansehen:** Ist sie
frei, und ist der eigene Ausgangsstand der neueste?

**Wo eine Versionsnummer vergeben wird, wird sie getaggt.** Eine Nummer
in einer Commit-Meldung, einem Changelog oder einem Kopfvermerk ist eine
Behauptung; ein Tag ist der Beleg.

**Und ein Tag zeigt auf den Commit, der seinen Changelog-Eintrag
trägt.**

**Das ist mechanisch prüfbar:** Für jeden Tag `vX.Y.Z` führt der
getaggte Commit die Überschrift `[X.Y.Z]` im Changelog. **Ein Tag, der
etwas anderes trägt, als er benennt, ist dieselbe Falle wie eine
ungepflegte Angabe** — nur schwerer zu sehen, weil er nach Beleg
aussieht.

**Eine Angabe, die nicht gepflegt wird, ist schlechter als keine.** Sie
sieht aus wie eine Auskunft und ist eine Falle. Wo sich ein Zustand
ableiten lässt, wird er nicht aufgeschrieben.

**Und bevor eine Eigenschaft ins Modul wandert, ist zu prüfen, ob sie
gesetzt werden muss.**

Beim nächsten Umbau steht sie da, und niemand weiß, ob sie eine Absicht
trägt oder ein Rest ist.

**Eine nicht versionierte Konfigurationsdatei driftet unbemerkt in zwei
Richtungen** — gegenüber ihrer Vorlage im Repo und gegenüber der Fassung
auf dem Server. Sie steht aus gutem Grund in `.gitignore`; die Folge ist,
dass eine Korrektur auf dem Server monatelang neben einer unkorrigierten
lokalen Fassung stehen kann, ohne dass etwas warnt. Kein Bestandslauf
sieht sie, kein `git diff`, kein Test.

**Vor der Auslieferung wird deshalb die Struktur verglichen, nicht die
Werte:** Schlüssel, Aufbau, Vorhandensein. Werte müssen abweichen — das
ist ihr Zweck.

**Ein Auslieferungsskript addiert namentlich und lässt vor dem Commit
die Prüfungen laufen.** Pauschales Addieren hätte einmal eine Datei mit
einer Personenkennung ausgeliefert und hat einmal einen stillen
Rückschritt über vier Auslieferungen getragen; ohne Prüfung ging ein
roter Stand mit 17 Fehlern hinaus. **Bei unverfolgten Dateien hält es an
und nennt sie** — das fängt auch die vergessene Datei, die hineingehört.

**Wer eine Arbeitskopie aus einer Lieferung spiegelt, sieht vorher den
Stand beider Seiten an.** Ein Protokoll der Arbeitskopie allein sagt
nicht, dass die Lieferung älter ist.

**`deploy.sh` muss den Push auch dann erreichen, wenn es nichts zu
committen gibt.** `git commit` bricht sonst unter `set -e` ab. Muster:
namentlich stagen, dann `if ! git diff --cached --quiet; then git commit
…`.

**Cache-Busting mit einem Ausdruck über beliebige Zeichen**, nicht nur
Ziffern. `?v=DEV` ändert sich sonst nie.

**Wer eine Anwendung ohne ihr Auslieferungsskript pusht, liefert neuen
Servercode mit altem Browsercode aus.** Die Cache-Werte bleiben, und
Dateien mit langer Haltbarkeit (`immutable`) halten den alten Stand fest,
bis jemand neue Werte nachschiebt — **ein Zustand, den niemand getestet
hat.**

**Eine Ablage, die in der Summe klein ist, kann einen einzelnen Vorgang
sprengen.** Sechzehn Megabyte sind für ein Repo unerheblich, für einen
Push über HTTPS nicht: `HTTP 400`, Abbruch, und die Meldung nennt die
Ursache nicht. Abhilfe ist `http.postBuffer`. Beim erstmaligen
Hinzufügen größerer Bestände gehört das dazugesagt.

**Nichts Ausgeliefertes an der Projektwurzel** — es sei denn, die
Projektwurzel **ist** der Docroot. Der Dienst läuft mit
Arbeitsverzeichnis gleich Projektwurzel; liegt dort ein Verzeichnis,
dessen Name am Anfang einer ausgelieferten URL steht, wird die Datei am
Router vorbei behandelt und läuft auf dem Server in einen Fatal, weil
`doc_root` auf `/var/www/virtual/<benutzer>/` beschränkt ist. Maßgeblich
ist der Docroot, nicht ein bestimmter Verzeichnisname — die Reihe kennt
dafür mehrere gleichwertige Anordnungen.

**Dieselbe Beschränkung schützt, was nicht ausgeliefert werden soll — und
zwar nur nebenbei.** Ein Abruf auf eine Konfigurationsdatei außerhalb des
Docroots wird von PHP-FPM abgelehnt (`Primary script unknown`), bevor die
Datei geöffnet wird; nach außen erscheint das als `500`. Das ist ein
echter Schutz, aber niemand hat ihn zu diesem Zweck eingerichtet: Ändert
sich `doc_root` oder der Ort des Dienstes, fällt er weg, und nichts
warnt.

**Geprüft wird deshalb der Inhalt, nicht der Statuscode.** Ein `500` sagt
nichts darüber, ob die Datei gelesen wurde — es ist derselbe Code für
„abgelehnt, nie geöffnet" und für „ausgeführt und abgestürzt". Die
brauchbare Prüfung ist: Der Abruf gibt **keine Zeichenfolge aus der
Datei** zurück. Das gilt unabhängig davon, welchen Code der Server
liefert.

---

## 7 — Erscheinungsbild

**Kein Farbwert außerhalb von `ci-tokens.css`.** Ausgenommen sind
Kategorienpaletten, die nur ein Projekt braucht: im `:root`-Block des
Projekts, mit Kommentar, der die Entscheidung festhält. Diese Ausnahme
ist ausdrücklich beschlossen und kein Schlupfloch.

**Token-Werte werden gegen den Bestand geprüft**, nicht nur gegen die
Rechenregel. Ein neuer Wert muss zu den bestehenden Einträgen passen —
etwa: Der Hover ist heller als der Akzent.

**Kein erfundener Name aus dem Namensraum des Moduls** — weder ein
Klassenname noch ein Symbolname noch ein Token. Ein geratener Name sieht
richtig aus und hat einfach keine Regel. **Und dieselbe Frage stellt sich
für jeden Namen, den zwei Stellen teilen müssen** — auch für eine
Kennung im HTML, die das JavaScript erwartet. Vier Auflagen desselben
Falls:

| | Wirkung |
|---|---|
| Klassenname | hat keine Regel |
| Symbolname | zeigt ein leeres Feld |
| **Tokenname** | macht die **ganze Deklaration** ungültig |
| Kennung im DOM | das Element wird nicht gefunden — und ein DOM-Ersatz, der sie selbst anlegt, verdeckt es |

**Der Tokenname ist der stillste.** `var(--name)` ohne Ersatzwert ist
*invalid at computed-value time*: Nicht der Wert fällt aus, sondern die
Deklaration.

**Wer einen Namensraum des Moduls benutzt, bringt die Prüfung dazu
mit.** Sie **ermittelt den Bestand** aus den vendorten Dateien, statt ihn
aufzuzählen — sonst veraltet sie mit der nächsten Modulfassung.

**Und die zweite Gegenprobe ist die wichtigere:** `var(--name, 4px)`
**mit** Ersatzwert ist zulässig und darf **nicht** anschlagen. Eine
Prüfung, die jedes `var()` mit unbekanntem Namen meldet, schlüge bei der
richtigen Form an — und würde abgeschaltet.

Wo ein Name nicht belegt ist, wird projekteigen gebaut, aus denselben
Tokens.

**Keine Webfonts, keine externen Ressourcen.** Das ist keine Vorliebe:
Das Landgericht München I hat am 20.01.2022 (3 O 17493/20) entschieden,
dass die Einbindung von Google Fonts über deren Server ohne Einwilligung
das Persönlichkeitsrecht des Besuchers verletzt. Schriften werden
mitgeliefert, oder es werden Systemschriften verwendet.

**`[hidden]` hat das letzte Wort.** Jede `display`-Regel im Projekt-CSS
berücksichtigt das Attribut; das Projekt-CSS wird nach dem Modul geladen
und überschriebe es sonst. **Das gilt für die Modulklassen ebenso**.

**Ein Modulmodifikator wirkt nur auf der Modulklasse.**
`ci-knopf--leise` auf einem projekteigenen Knopf verliert: gleiche
Spezifität, und das Projekt-CSS wird danach geladen. Die Regel gegen
erfundene Klassennamen deckt das nicht ab — der Name existiert. **Ein
Modifikator wird nur zusammen mit seiner Grundklasse benutzt**, und das
ist prüfbar: Jeder `ci-*--*`-Name verlangt den zugehörigen `ci-*`-Namen
am selben Element.

**Eine Rückmeldung erscheint dort, wo gehandelt wurde.** **„Es passiert
nichts" ist ein Befund über die Oberfläche**, und die naheliegende Suche
— Endpunkt, Fehlerbehandlung — geht daran vorbei.

**Eingabemasken sind `<form>`-Elemente** mit einem `submit`-Ereignis und
einem Knopf vom Typ `submit`. Eine Anmeldung aus `<div>` und einem Knopf
mit `click` kannte die Eingabetaste nicht — und auf einem Telefon ist sie
der Weg, den die Tastatur anbietet.

---

## 8 — Auswertung fremder Antworten

**Stimmigkeit ist nicht Plausibilität.** Eine Zahl, die formal passt und
sachlich unmöglich ist, muss eigens abgefangen werden — sonst ist die
Prüfkette lückenlos und trotzdem wertlos.

**Und Stimmigkeit ist nicht Bedeutung.** Eine Prüfung, die ein Ergebnis
gegen seine Eingabe hält, prüft nicht, ob die Eingabe die Wirklichkeit
trifft.

**Mehrere Prüfungen, die dieselbe Eingabe teilen, sind eine Prüfung.**

**Eine Prüfkette ist nur so gut wie ihr unabhängigster Teil.**

**Ein Vergleich prüft vorher seine Operanden.** Null Überschneidung in
**beide** Richtungen ist fast immer ein leerer Operand, kein Befund.

**Wo mehrere fremde Aufrufe hintereinander laufen, nennt die
Fehlermeldung, welcher gescheitert ist** — mit Methode, Adresse und
Status. Ohne das kostet ein Fehlercode Zeit, der etwas anderes bedeutet
als er sagt: `-32601 Method not found` beantwortete einmal eine falsche
**Signatur**, nicht einen falschen Namen.

**Widersprüche werden als Widerspruch ausgegeben, nicht als Ergebnis.**
„Daten da, Treffer null" heißt *kein Befund*, nicht *negativer Befund*.

**Wo aus einer Liste auf den Aufbau geschlossen wird, genügt ein Eintrag
nicht.** Der erste ist oft der leere.

**Ein verbreiteter Feldname allein ist kein Erkennungsmerkmal.** Es
braucht einen eindeutigen Pfad oder eine Kombination.

**Und dieselbe Vorsicht gilt für die Menge der Felder.** Dass eine
Abfrage ein Merkmal führt, heißt nicht, dass die Nachbarabfrage es auch
führt — zwei Quellen, zwei Feldmengen.

**Ein Auftrag, der ein Merkmal aus einem anderen Projekt heranzieht,
nennt den Aufruf, aus dem es stammt** — nicht den Protokolleintrag, der
es erwähnt. Steht der Aufruf da, fällt der Fehler beim Schreiben auf;
steht nur die Nummer da, erst beim Ausführen.

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
Längenprüfungen weiter etwas prüfen. Nicht umgekehrt. **Und übernommen
wird die Struktur, nicht die Werte:** Namen, Kennungen und Adressen
werden ebenso ersetzt, **vor dem ersten Commit** — danach stehen sie in
der Historie, und die ist öffentlich. Die Platzhalter haben gleiche
Länge und Form und **gleichen keiner echten Kennung**: Eine erfundene
dreistellige Nummer hat einmal eine Prüfung ausgelöst und eine ganze
Klasse blockiert.

**Der Vermerk „aus Mitschnitt" ist ein Signal zum Nachsehen, nicht zum
Vertrauen.** Wo ein Wert adressierbar ist, geht das Ersetzen der Regel
vor, alte Protokolleinträge nicht zu ändern (Abschnitt 10) — eine
Adresse kann jemand benutzen, ein Kürzel sagt außerhalb der Schule
niemandem etwas. Eine Arbeitsdatei ist ohnehin kein Protokolleintrag.

**Anonymisierung darf die Auskunft nicht zerstören.** Wo eine Antwortform
belegt ist und keine Personendaten enthält, tritt eine gezielte
Auswertung an die Stelle des Anonymisierers — sie gibt aus, was benannt
ist, statt zu verbergen, was verdächtig aussieht.

**Und das Schwärzwerkzeug darf nicht das Untersuchungswerkzeug sein.**
Ein `sed`, das jeden Text zwischen Tags entfernt, hält Personendaten aus
der Ausgabe — und entfernt zugleich die Spaltenüberschriften, wegen
derer abgefragt wurde.

Wer fremdes HTML untersucht, braucht zwei Durchläufe: einen, der die
Struktur zeigt, und einen, der die Werte schwärzt. Ein Werkzeug, das
beides zugleich tut, tut keines von beiden zuverlässig.

---

## 10 — Form der Projektdateien

**Entscheidungen mit Begründung stehen in `docs/ENTSCHEIDUNGEN.md`.**
Chronologisch, neue Einträge unten angefügt; alte werden nicht geändert,
sondern durch neue aufgehoben.

**Was wächst, wird nicht bei jedem Start geladen.** Einmal waren die
`@`-Importe einer `CLAUDE.md` auf 1,1 MB angewachsen, bei einer Grenze
von 150k — **also wurde seit Wochen nicht geladen, was dort stand.**

**Die Unterscheidung, die es löst:**

| | wächst | wird geladen |
|---|---|---|
| **Protokoll** | nur, nie gekürzt | bei Bedarf, genannt statt importiert |
| **Gedächtnis** | gepflegt, darf schrumpfen | bei jedem Start |

**Geladen wird, was gepflegt wird.** Ein Protokoll wird im Kopf genannt,
nicht importiert — wer es braucht, liest es.

**Und eine Prüfung zählt die Importe und wird über der Grenze rot.** Ohne
sie fällt nur auf, dass etwas nicht ankommt — und das sieht aus wie
Vergesslichkeit, nicht wie ein Fehler.

**Vergebene Nummern werden nie neu vergeben und nie verschoben** — E, M,
und jede Nummerierung in einer Befunddatei. Neue kommen ans Ende. Ein
Zusatzbuchstabe (`B15b`) ist keine Lösung, sondern eine zweite Nummer
für dieselbe Stelle. **Eine Nummer ist ein Name:** Anders als im Code
fällt es nicht auf — beide Dokumente bleiben lesbar, nur der Verweis
stimmt nicht mehr.

**Und ein Verweis auf eine Nummer nennt das Projekt**, aus dem sie
stammt. Mehrere Projekte führen eigene E-Reihen mit denselben Zahlen.

**Sprache ist Deutsch** — Bezeichner, Kommentare, Ausgaben, Commits.

**Lizenz ist GPL-3.0-or-later.**

**Keine Frameworks, kein Build-Schritt**, weder für PHP noch für
JavaScript.

*Was eine `CLAUDE.md` über sich selbst sagt — dass sie bei jedem
Sessionstart gelesen wird und keine Tagesaufgaben enthält — steht in ihr
selbst und nicht hier. Sie muss lesbar sein, bevor ihre Importe geladen
sind.*
