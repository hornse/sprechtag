<!-- VENDORED aus hornse/koordination v1.20.0 – dort ändern, hierher kopieren! -->
# Regeln der Reihe

Gilt für alle Projekte, die diese Datei führen. **Quelle ist
`hornse/koordination`**; die Kopien in den Projekten sind vendored und
werden vom Bestandslauf gemessen.

**Fällt beim Arbeiten in einem Projekt auf, dass eine Regel falsch,
unvollständig oder überholt ist: die Kopie wird nicht geändert.** Der
Befund gehört nach `koordination` — als Eintrag im Entscheidungsprotokoll
oder als Auftragsdatei. Wer die Kopie ändert, bekommt beim nächsten
Bestandslauf einen Befund und hat bis dahin eine zweite Wahrheit
geschaffen. Genau so sind vier Fassungen desselben Moduls entstanden.

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

**Bleibt eine Behebung ohne sichtbare Wirkung, suche eine zweite
Ursache, bevor du die erste verwirfst.** Zwei Fehler mit demselben
Symptom sind der Fall, in dem eine richtige Korrektur wie ein Irrtum
aussieht — und dann rückgängig gemacht wird. Einmal traf ein Selektor nie
und ein Mengenvergleich ebenso wenig; beide erzeugten „nichts ist
ausgewählt", und wer nur den ersten kannte, suchte nach seiner Behebung
am falschen Ort weiter.

Das ist die Rückseite der vorigen Regel: Sie sorgt dafür, dass ein
Ergebnis lesbar ist. Diese sorgt dafür, dass ein **ausbleibendes**
Ergebnis richtig gelesen wird.

**Rückfragen vor Entscheidungen mit Tragweite, nicht danach.**

**Paketweise arbeiten, nicht alles auf einmal.**

**Beobachten schlägt raten.** Wo eine Oberfläche dieselbe Sache kann, die
gesucht wird: zuerst mitschneiden. Kandidatenlisten sind ein
Ersatzvorgehen für den Fall, dass es nichts zu sehen gibt.

**Gefundene Fehler werden immer gemeldet.** Behoben werden sie, wenn sie
im Auftrag liegen oder der Auftrag sonst nicht ausführbar ist —
andernfalls gehören sie in den Bericht, nicht in denselben Commit.

**Eine örtliche Behebung ist keine Behebung.** Wo ein Fehler eine *Art*
hat, gehört die Frage dazu, wie viele Stellen dieser Art es gibt — und
ob sich eine **Engstelle** bauen lässt, an der es nur noch eine gibt.
Einmal wurde „null Räume gelesen" an einer Stelle behoben; zwei Wochen
später stand dieselbe Sorte Fehler an der nächsten und beendete einen
Aufruf mit einem Fatal.

**Und eine Engstelle ist statisch prüfbar, wo ein Datenfluss es nicht
ist.** „Läuft irgendwo jemand über eine ungeprüfte Antwort" bräuchte eine
Datenflussanalyse. „In keiner Anwendungsdatei steht ein unmittelbarer
Aufruf" ist ein `grep` und gibt dieselbe Zusicherung. **Wo eine Regel
über Werte nicht entscheidbar ist, ist die Regel über Aufrufstellen oft
gleichwertig und billig.**

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

**Das gilt auch für jede Ausprägung einer bekannten Struktur.** Einmal
war ein Zusammenbau geprüft — mit Daten, die nur Einzelstunden enthielten.
Doppelstunden erschienen danach mit ihrer ersten Stundennummer. **Eine
Prüfung ist nur so gut wie die Fälle in ihren Daten.**

**Eine Herkunftsangabe in Testdaten ist eine Behauptung.** Einmal gab
eine Testdatei „Zeiten aus der Auskunft der Schule" an — eine solche
Auskunft gab es nicht, und die Zeiten widersprachen dem belegten
Zeitraster. Wer Testdaten beschriftet, belegt jede Angabe einzeln oder
nennt sie **erfunden**. Und eine Prüfung hält die Angabe fest, sonst
driftet sie wie jede andere ungepflegte Angabe.

**Und die Gegenprobe selbst wird geprüft**, indem der geprüfte Code
absichtlich beschädigt wird. Schlägt sie dann nicht an, prüft sie nichts.
Das ist mehrfach vorgekommen — zuletzt bei einer Prüfung, deren einziger
Zweck es war, Verluste beim Aufräumen zu fangen: Sie las nur Backticks
und keine Code-Blöcke und meldete „OK" für eine Datei, aus der absichtlich
etwas entfernt worden war.

**Die Beschädigung muss belegt angekommen sein.** Trefferzahl vorher und
nachher, oder eine Zahl, die sich verändern muss. Viermal in einer
Sitzung hat nicht der geprüfte Code versagt, sondern das Prüfwerkzeug —
ein Suchmuster traf nicht, eine Ersetzung benannte zwei Stellen gleich,
ein mehrzeiliges Muster brach beim Kompilieren ab, eine Variable wurde
überschrieben. **Dreimal war die Ausgabe grün oder still, und „keine rote
Zeile" nach einem fehlgeschlagenen Eingriff liest sich wie Bestehen.**

Ein Eingriff ohne Wirkungsnachweis belegt nichts — auch nicht, dass die
Prüfung greift.

**Ein sauberer Ausgangszustand belegt nur, dass die Prüfungen grün sind
— nicht, dass sie hinsehen.** Einmal nahm eine Prüfung den
Verzeichnisnamen als Bezugspunkt; im echten Repo stimmte er, in jeder
Kopie und jedem Probebaum fiel die Kette stillschweigend ins `else` und
prüfte nichts. Die Zeile „Ausgang: 0 rot" stand in allen vorherigen
Läufen da und war für diesen Zweig bedeutungslos.

**Mutationen werden in Serie gefahren, nicht einzeln.** Mehrere
Nichttreffer sind ein Muster, einer ist Rauschen — und ein einzelner
Durchrutscher verleitet zur Einzelfallerklärung. Der Fall oben fiel nur
auf, weil zwei Mutationen nacheinander nicht anschlugen.

**Wonach gesucht wird, wird dort gesucht, wo es wirken soll** — im Rumpf
der Funktion, nicht in der Datei. Eine Suche über eine ganze Datei belegt
**Vorkommen, nicht Wirkung**: Einmal blieb eine Prüfung grün, weil
dieselbe Zeichenkette an einer zweiten Stelle derselben Datei stand,
während sie an der gemeinten entfernt worden war. Das ist die subtilere
Verwandte des Treffers im Kommentar — dort war er unecht, hier ist er
echt und nur am falschen Ort.

**Dasselbe gilt für Stilregeln und für doppelte Darstellungen.** Steht
eine CSS-Eigenschaft in mehreren Regeln, wird **in der Regel** gesucht,
nicht in der Datei. Und wo eine Oberfläche dieselbe Sache zweimal
darstellt, **zählt** die Prüfung die Vorkommen, statt eines zu suchen —
sonst übersteht das Entfernen aus einer der beiden jede Prüfung.

**Eine Prüfung an einem Intervall braucht einen Fall genau auf der
Grenze.** Einmal schlug die Mutation „das Ende zählt mit" nicht an — kein
Testfall berührte die Grenze. Ohne einen solchen Fall ist die Gegenprobe
zur Grenzbedingung wirkungslos, und das fällt nur auf, wenn man sie
fährt.

**Wo zwei Stufen dieselbe Gefahr abwehren, wird jede einzeln belegt.**
Für die Dauer der Gegenprobe wird die andere ausgehängt. Sonst prüft man
die Redundanz statt der Stufen: Eine Mutation kann nur eine Stufe
entfernen, die zweite fängt, und alles meldet grün — vier Prüfungen
standen so grün und belegten keine der beiden.

**Die Form des Ergebnisses prüfen, nicht nur das Verschwinden des
Falschen.** Ein Ausdruck, der aus `?v=DEV` ein `?v=PROBEDEV` macht, hat
`?v=DEV` auch beseitigt — und trotzdem alles falsch gemacht.

**Eine Prüfung ohne ihre Voraussetzung gilt nicht als bestanden, sie
sagt es.** Null Funde sind ein Fehler, kein Ergebnis; ein leerer Lauf
sieht sonst aus wie ein sauberer.

**Und ein Prüfschritt, dessen Voraussetzung von Hand hergestellt werden
muss, wird nie ausgeführt.** Das ist nicht derselbe Fall: Bei einer
fehlenden Voraussetzung meldet sich die Prüfung. Hier schweigt sie, und
**Schweigen ist von Bestehen nicht zu unterscheiden.** Einmal verlangte
ein Prüfschritt einen Cookie aus einem fehlgeschlagenen Login — er lief
nie, also fiel er nie auf.

Ein solcher Schritt stellt seine Voraussetzung selbst her, oder er ist
keine Prüfung, sondern eine Anleitung. Beides ist zulässig; nur muss
dabeistehen, was von beidem es ist.

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

**Ein Prüfausdruck darf nicht auf die Beschreibung der Regel
anschlagen.** Je besser eine Regel dokumentiert ist, desto
wahrscheinlicher steht ihr Wortlaut als Prosa in derselben Datei, die
geprüft wird — die Prüfung wird also gerade dort unzuverlässig, wo
sorgfältig gearbeitet wurde. Einmal traf ein ungeankerter Ausdruck den
Kopfkommentar, der die Regel erklärt, statt der Zuweisung, die sie
umsetzt; die Prüfung wäre grün geblieben, wenn nur der Kommentar
übrig gewesen wäre. **Die Gegenprobe dazu lautet: den Code löschen, den
Kommentar stehen lassen.**

Dasselbe gilt für jedes Werkzeug, das eine Datei liest, die ihr eigenes
Format erklärt: Beschreibung und Sache müssen trennbar sein. Eine
Ausnahmedatei, deren Formvorschrift ein Beispiel enthielt, hat dieses
Beispiel einmal als Eintrag gelesen.

**Und eine Zeichenkette ist so gefährlich wie ein Kommentar.** Die
Beschriftung einer Prüfung — `pruefe('lz_raumhinweis() wird gerufen', …)` —
sieht für jeden Suchausdruck wie ein Aufruf aus. **Gerade sorgfältig
benannte Suiten sind davon betroffen.** Für PHP trennt der Tokenizer
Kommentar, Zeichenkette und Code; für JavaScript und CSS fehlt ein
solches Werkzeug, und dort schlugen zwei Prüfungen auf ihre eigenen
Kommentare an.

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

**Eine Prüfung, die eine bestimmte Datei liest, prüft diese Datei — nicht
die Regel.** Wo eine Regel für ein ganzes Projekt gilt, sucht die Prüfung
im ganzen Projekt und nicht dort, wo der Fall zuerst auftrat. Einmal las
die Prüfung auf Rohfarben eine Stilvorlage, während sieben Hexwerte im
JavaScript standen: Grün war eine Aussage über die Prüfstelle, nicht über
den Bestand.

**Das ist schwerer zu finden als eine fehlende Prüfung**, weil niemand
nachsieht, wo eine bestandene Prüfung hingeschaut hat. Bei einer
fehlenden fehlt wenigstens eine Zahl.

**Eine Darstellung wird am Dargestellten geprüft, nicht am Wert im
Code.** Zwei Fälle, beide grün und beide falsch: Ein Langname stand als
`title` im DOM — auf dem Telefon unerreichbar, am Monitor ein bis zwei
Zeichen breit als Ziel. Und ein `max-width: 90rem` war wirkungslos, weil
ein umgebendes Element auf 76rem begrenzte. **Eine Messgröße wirkt erst
am bestimmenden Element.** Wo es um Sichtbares geht, prüft die Prüfung
den sichtbaren Text — dafür muss dessen Bildung eine ausführbare Funktion
sein —, und für Größen braucht es einen Blick im Browser.

**Ein Skript, das aus der Suite genommen wird, wird geteilt, nicht
herausgenommen.** Einmal lief eine Testdatei nicht mit, weil ein Teil
davon eine Datenbank braucht — und verlor damit auch die Prüfungen, die
keine brauchen. Es fehlte ein `require`, und niemand hat es gemerkt.

**Ein Weg, den niemand aufruft, ist eine zweite Wahrheit.** Zwei
Projekte haben es unabhängig voneinander gemeldet: Eine Funktion baute
dieselbe Liste wie der lebende Weg, wurde nie aufgerufen, und eine dort
eingebaute Auskunft kam nie an. Im anderen war es eine **Sperre gegen das
Produktivsystem**, die definiert und nie aufgerufen war. **Ein toter Weg
ist schlimmer als ein falscher** — ein falscher fällt auf. Wie viele
Funktionen niemand aufruft, ist billig zu zählen und gehört in den
Bericht; als Rot/Grün-Prüfung erzeugte es Fehlalarme bei
Schnittstellen, die bewusst für später gebaut sind.

**Ein Name ist eine Schnittstelle.** In JavaScript gewinnt die letzte
Deklaration einer Funktion im selben Geltungsbereich — ohne Warnung,
gültiger Code, `node --check` schweigt. Einmal entstand eine zweite
Funktion gleichen Namens, und **die Anmeldung war zwölf Stunden lang
zu**: Ein Feld lieferte ein Element statt eines Werts. **Eine Prüfung
„kein Funktionsname zweimal im selben Geltungsbereich" ist ein
Zehnzeiler und trifft jede künftige Funktion.**

**Prüfe das Eindeutige gründlich, das Uneindeutige gar nicht.** Wo ein
Zwischenzustand legitim ist, schlägt eine Rot/Grün-Prüfung grundlos an —
und wird dann abgeschaltet oder ignoriert. Dann fehlt sie auch dort, wo
sie recht hätte.

**Eine Sicherung, die aus einem Vorfall stammt, bekommt beim nächsten
Umbau der Umgebung eine Bedingung — oder sie wird erneut begründet.** Als
der Vorfall geschah, deckte sie genau einen Fall ab. Klärt sich die
Umgebung, deckt dieselbe Regel womöglich zwei, und einer davon ist der
Normalfall: Einmal hielt eine Prüfung „Zeilen, aber kein Mitglied" für
einen Auswertungsfehler — bis feststand, dass eine frisch angelegte
Gruppe genau so aussieht.

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
und das ist der Normalfall, nicht die Ausnahme. Bei einem Datensatz fand
der Vergleich mit dem Vorgänger fünf Fehler, beim nächsten drei; beim
ersten seiner Art hätte er nichts gefunden, weil es ihn nicht gab.

Daraus folgt keine Prüfung, sondern zweierlei: Der erste Datensatz einer
neuen Sorte wird von Hand gegengelesen, **und es wird vermerkt, dass hier
nichts geprüft hat.** Und wo die Reihenfolge frei ist, lohnt es, zwei
gleichartige nacheinander zu nehmen — das zweite prüft das erste
rückwirkend mit.

**Läuft ein Erzeuger neu, wird vollständig gegen den ausgelieferten Stand
verglichen.** Nicht stichprobenweise — vollständig. Einmal fand dieser
Diff drei Fehler, die keine Prüfung und keine Stichprobe fangen konnte:
**Ein Eintrag, der mitten im Satz auf einem heilen Wort abbricht, besteht
jede Einzelprüfung.** Er ist grammatisch in Ordnung, er ist plausibel, und
er ist falsch. Nur das Vorher zeigt, dass etwas fehlt.

Der Vergleich ist bei einem neu laufenden Erzeuger billig und immer
verfügbar — der ausgelieferte Stand ist der Vorgänger, den das erste
Exemplar sonst nicht hat.

**Eine gekürzte Ausgabe kann die einzige Zeile verlieren, die zählt.**
`tail -3` über einen Testlauf, `-A2` über eine Suche: Wo ein Ergebnis
beschnitten wird, ist das Fehlen einer Zeile keine Auskunft. Bei einem
roten Lauf, der sich danach nicht mehr herstellen ließ, blieb dadurch
unbekannt, welche Prüfung gefallen war.

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
könnte. Die Regel ist bedingt und liest sich sonst als allgemeine
Vorschrift, die zwei von zehn Skripten scheinbar verletzen.

**Trocken ist der Standard.** Wo ein Skript etwas Zerstörendes tun kann,
verlangt der echte Lauf einen ausdrücklichen Schalter
(`TROCKEN=${TROCKEN:-1}`). Ein Probelauf darf nie davon abhängen, dass
eine Textersetzung greift — genau daran ist einmal ein Probelauf zum
echten Lauf über sechs Repos geworden.

**Vor der Verwendung nachsehen, nicht annehmen** — und im Zweifel die
portable Form wählen. Die Werkzeuge auf dem Arbeitsrechner sind nicht die
auf dem Server. **`grep -P` gibt es auf macOS nicht** — und ein
nachgestelltes `|| echo` verschluckte einmal genau diesen Werkzeugfehler:
Null Treffer sahen aus wie ein sauberer Lauf.

**Eine Mutation wird aus einer Sicherungskopie zurückgenommen, nie aus
dem Index.** `git checkout <datei>` stellt den letzten Commit her und
verwirft dabei alles, was in derselben Sitzung korrigiert, aber noch
nicht committet war. Zweimal aufgetreten.

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

**Vier Fallen der interaktiven zsh**, alle schon eingetreten:

* **`#` ist dort kein Kommentarzeichen.** Ein erklärender Zusatz am
  Zeilenende wird zum Argument. Einmal hat er eine Zählprüfung
  stillschweigend übersprungen — **und die `||`-Absicherung daneben
  meldete daraufhin Erfolg, weil ein leerer Lauf wie ein sauberer
  aussah.** Der Schaden entsteht nicht durch die Falle allein, sondern
  durch ihr Zusammentreffen mit einem `|| true`, das mehr abfängt als den
  Erfolgsfall.
* **Glob-Muster für ein Werkzeug gehören in Anführungszeichen.**
  `--include=*.js` expandiert die Shell selbst und bricht ab;
  `--include="*.js"` erreicht `grep`.
* **Ein `:` nach einem Variablennamen wird als Modifikator gelesen** —
  auch in doppelten Anführungszeichen. Aus `"$t:FALLSTRICKE.md"` wurde
  einmal `…/v1.16.0LLSTRICKE.md`; `${t}:FALLSTRICKE.md` ist richtig.

  **Diese Falle ist gefährlicher als die drei anderen**, weil sie nicht
  abbricht und keinen Unsinn liefert, sondern ein **plausibles Ergebnis**:
  Die nachfolgende Prüfsumme bekam leere Eingabe und lieferte dreimal
  denselben Wert — was wie „an allen Ständen identisch" aussah und
  zufällig die richtige Antwort vorgetäuscht hätte.

  **`da39a3ee5e6b` ist die Prüfsumme der leeren Eingabe.** Wer sie einmal
  kennt, erkennt sie wieder. Belegen lässt sie sich mit
  `printf '' | shasum`.
* **Eine unquotierte Variable wird nicht in Wörter zerlegt.**
  `for f in $dateien` läuft in zsh einmal mit der ganzen Zeichenkette
  statt je Datei. In bash wäre dasselbe richtig — und genau das macht es
  gefährlich, weil Beispiele von außen fast immer bash voraussetzen.

In einem Skript mit `#!/bin/bash` gilt beides nicht. Der Unterschied
zwischen dem, was in einem Skript steht, und dem, was von Hand
eingetippt wird, ist hier keine Formalie.

**Eine Sollzahl ist eine Vermutung über die Umgebung des Empfängers.**
Wer für einen anderen einen Auftrag schreibt und eine erwartete Zahl mit
einem Werkzeug ermittelt, hat sich auf dieses Werkzeug festgelegt —
ohne zu wissen, ob es dort vorhanden ist und in welcher Fassung. Der
Auftrag prüft deshalb zuerst, dass das Werkzeug existiert, oder er
kennzeichnet die Zahl als ungeprüft. Das ist derselbe Fall wie ein
Befehl aus dem Gedächtnis, nur in Zahlenform.

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
im eigenen Repo, nicht als Zugriff über Verzeichnisgrenzen. Einmal blieb
ein Lauf genau daran stehen, mitten in der Arbeit. Der Nebengewinn: Im
Repo steht dann, welche Fassung als Vorbild diente.

**Wer dagegen den Bestand ermittelt, braucht Zugriff auf alle** und kann
nichts beilegen. Die Trennlinie ist: eine **benannte** Datei wird
beigelegt, ein **ermittelter** Bestand wird vorausgesetzt und im ersten
Schritt geprüft.

**Eine Aussage über einen Zustand wird mit Datum weitergegeben oder vor
der Verwendung geprüft.** Versionsstand, Prüfungszahl, welches Repo was
führt — das veraltet von selbst. **Eine Entscheidung gilt, bis sie
aufgehoben wird; ein Zustand nicht.** Einmal wurde ein Befund über eine
unmarkierte Kopie siebzehn Tage später weitergereicht — das betroffene
Repo war inzwischen archiviert. Und einmal war eine genannte
Prüfungszahl schon am Tag ihrer Entstehung überholt, während die
Zeilenangaben daneben stimmten. Das machte es tückisch.

Das ist dieselbe Einsicht wie bei einem Prüfschritt, dessen Voraussetzung
von Hand hergestellt werden muss — eine Ebene höher: **Eine
Voraussetzung, die erst mitten im Lauf auffällt, ist eine, die im
Ausgangsstand fehlte.**

**Und eine Auftragsdatei benennt, welche Abschnitte einer vorhandenen
Erhebung sie voraussetzt.** Nicht die Datei — den Abschnitt. Bei einer
Erhebung von einigem Umfang liest man den Anfang und arbeitet dann; wer
die Stelle nennt, zwingt zum Nachschlagen an der richtigen.

Einmal wurde eine Strukturerhebung eigens beauftragt, weil dreimal auf
Stichproben entschieden worden war — und bei den **beiden** Vorgängen
danach stand die entscheidende Auskunft ungelesen darin. Ein Datensatz
ging beschädigt in Betrieb. **Eine Erhebung, deren Ergebnis beim Bauen
nicht gelesen wird, ist verlorene Arbeit** — und schlimmer als keine,
weil sie den Eindruck erzeugt, die Frage sei geklärt.

**Es ist kein Aufmerksamkeitsproblem.** Dieselbe Person hatte den Auftrag
geschrieben und die Zeile beim Bauen nicht gelesen. Wer eine Erhebung
beauftragt, hat sie beim Schreiben im Kopf und beim Bauen nicht mehr —
deshalb steht die Benennung in der Auftragsdatei und nicht im Gedächtnis.

**Die Umkehrung gehört dazu:** Ein Abschnitt, den nach mehreren Aufträgen
keiner voraussetzt, war entweder überflüssig oder ist es geworden. Das
ist der Anlass, ihn zu streichen oder zu schärfen — nicht ein Gefühl.

**Vor dem Lauf: `git status` in allen betroffenen Repos.** Sonst ist
hinterher nicht zu trennen, was das Werkzeug getan hat.

**Wer in einem fremden Repo committet, addiert namentlich — nie
`git add -A`.** Ein Auftrag weiß, welche Dateien er anfasst; wenn nicht,
ist das das eigentliche Problem. Einmal lagen hundert Sekunden zwischen
einem Vendoring-Commit und zwei Dateien aus einer parallelen Sitzung.
Wären sie früher da gewesen, hätte `-A` sie mitgenommen, und ein Push auf
die Server-Gegenstelle hätte eine halbfertige Migration ausgeliefert.

**Und ein sauberer Ausgangsstand ist keine Zusicherung über die
Laufzeit.** Der `git status` vor dem Lauf hätte diesen Fall nicht
gefangen — die Dateien entstanden danach. An mehreren Repos wird selten
allein gearbeitet, auch wenn nur eine Person beteiligt ist.

**Eine Änderung an einem Werkzeug, das über mehrere Repos läuft, wird im
Probebaum durchgespielt.** Den Baum kopieren, das Werkzeug daraufsetzen,
und **zuerst belegen, dass der Ausgangslauf dort Zahl für Zahl derselbe
ist** — ohne diesen Beleg ist der Probebaum eine Annahme. Danach lässt
sich die Änderung vollständig ausprobieren, ohne ein einziges Repo
anzufassen. So wurde ein Fehler gefunden, der vierzehn falsche Befunde
erzeugt hätte.

**Der letzte Schritt bleibt manuell.** Ein Deploy über mehrere Repos
macht eine Änderung wirksam, die niemand einzeln zurücknimmt — dabei soll
ein Mensch die Ausgabe sehen. Innerhalb eines Projekts gehört
`deploy.sh` weiterhin zum Auftrag.

**Remote und Zweig werden ausdrücklich genannt.** Die Reihe ist
gewachsen; die Upstreams zeigen je Repo verschieden mal auf die eine, mal
auf die andere Gegenstelle. `git push` ohne Argument bedient deshalb
nicht überall dieselbe Seite — in drei von vier Fällen bediente es die
falsche, und das Repo sah hinterher aus wie erledigt.

**Ein Push auf die Server-Gegenstelle ist eine Auslieferung**, keine
Vorbereitung: Am Bare-Repo hängt ein Hook, der auscheckt oder ausrollt.
Was mit hochgeht, geht damit in den Betrieb — auch Commits aus einem
anderen Vorhaben, die zufällig darunter liegen.

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
Bedingung für den Fall, dass das Modul noch nicht liefert: Einmal wurde
deshalb eine bestehende Begrenzung entfernt und keine neue gesetzt — eine
Anmeldekarte wurde bildschirmbreit. **Die vorläufige Zeile ist die
Bedingung, die fehlte**, und sie fällt weg, sobald das Modul liefert.

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

**Eine nicht versionierte Konfigurationsdatei driftet unbemerkt in zwei
Richtungen** — gegenüber ihrer Vorlage im Repo und gegenüber der Fassung
auf dem Server. Sie steht aus gutem Grund in `.gitignore`; die Folge ist,
dass eine Korrektur auf dem Server monatelang neben einer unkorrigierten
lokalen Fassung stehen kann, ohne dass etwas warnt. Kein Bestandslauf
sieht sie, kein `git diff`, kein Test.

**Vor der Auslieferung wird deshalb die Struktur verglichen, nicht die
Werte:** Schlüssel, Aufbau, Vorhandensein. Werte müssen abweichen — das
ist ihr Zweck.

**`deploy.sh` muss den Push auch dann erreichen, wenn es nichts zu
committen gibt.** `git commit` bricht sonst unter `set -e` ab. Muster:
`git add -A`, dann `if ! git diff --cached --quiet; then git commit …`.

**Cache-Busting mit einem Ausdruck über beliebige Zeichen**, nicht nur
Ziffern. `?v=DEV` ändert sich sonst nie.

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
etwa: Der Hover ist heller als der Akzent. Ein rein kontrastoptimierter
Wert war einmal dunkler und fiel aus der Reihe, obwohl er jede Prüfung
bestand.

**Keine erfundenen Modulklassennamen.** Ein geratener Klassenname sieht
richtig aus und hat einfach keine Regel — so verschwanden einmal alle
Karten eines Projekts. Wo ein Name nicht belegt ist, wird projekteigen
gebaut, aus denselben Tokens.

**Keine Webfonts, keine externen Ressourcen.** Das ist keine Vorliebe:
Das Landgericht München I hat am 20.01.2022 (3 O 17493/20) entschieden,
dass die Einbindung von Google Fonts über deren Server ohne Einwilligung
das Persönlichkeitsrecht des Besuchers verletzt. Schriften werden
mitgeliefert, oder es werden Systemschriften verwendet.

**`[hidden]` hat das letzte Wort.** Jede `display`-Regel im Projekt-CSS
berücksichtigt das Attribut; das Projekt-CSS wird nach dem Modul geladen
und überschriebe es sonst. **Das gilt für die Modulklassen ebenso** — und
das Modul hat es zweimal selbst verletzt, bei der Navigation und beim
Knopf. Die Prüfungen der Projekte fingen es nicht, weil sie nur
projekteigene Klassen darauf prüften.

**Ein Modulmodifikator wirkt nur auf der Modulklasse.** `ci-knopf--leise`
auf einem projekteigenen Knopf verliert: gleiche Spezifität, und das
Projekt-CSS wird danach geladen. Der Knopf sah aus wie der kräftige
daneben. Die Regel gegen erfundene Klassennamen deckt das nicht ab — der
Name existiert. **Ein Modifikator wird nur zusammen mit seiner
Grundklasse benutzt**, und das ist prüfbar: Jeder `ci-*--*`-Name
verlangt den zugehörigen `ci-*`-Namen am selben Element.

**Eine Rückmeldung erscheint dort, wo gehandelt wurde.** Einmal hieß ein
Befund „Buchen tut nichts" — der Server antwortete richtig, die Meldung
erschien am Seitenkopf, der angeklickte Termin stand weit darunter.
**„Es passiert nichts" ist ein Befund über die Oberfläche**, und die
naheliegende Suche — Endpunkt, Fehlerbehandlung — geht daran vorbei.

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
trifft. Einmal beruhten drei Fehler auf einem falschen Modell der fremden
Daten — **kein Wächter hätte angeschlagen**, weil der gebaute Körper zum
gelesenen Bestand passte. Er meinte nur etwas anderes.

**Mehrere Prüfungen, die dieselbe Eingabe teilen, sind eine Prüfung.**
Vier Sicherungen ließen einmal einen leeren Bestand durch: Der
Eingangswächter prüfte den falschen Fall, der Körperwächter hielt null
gegen null, das Gegenlesen verglich gegen eine ebenfalls leere Erwartung —
und der Rückweg, der den Schaden hätte rückgängig machen sollen, entstand
aus demselben leeren Bestand und hätte ihn bestätigt.

**Eine Prüfkette ist nur so gut wie ihr unabhängigster Teil.**

**Ein Vergleich prüft vorher seine Operanden.** Null Überschneidung in
**beide** Richtungen ist fast immer ein leerer Operand, kein Befund.
Einmal meldeten drei Proben null, weil eine Antwortdatei 79 Byte
Fehlermeldung enthielt statt der erwarteten Daten. Die Prüfung war
stimmig — sie verglich korrekt, nur nichts mit nichts.

**Wo mehrere fremde Aufrufe hintereinander laufen, nennt die
Fehlermeldung, welcher gescheitert ist** — mit Methode, Adresse und
Status. Ohne das kostet ein Fehlercode Zeit, der etwas anderes bedeutet
als er sagt: `-32601 Method not found` beantwortete einmal eine falsche
**Signatur**, nicht einen falschen Namen, und war eine halbe Stunde lang
nicht zuzuordnen.

**Widersprüche werden als Widerspruch ausgegeben, nicht als Ergebnis.**
„Daten da, Treffer null" heißt *kein Befund*, nicht *negativer Befund*.

**Wo aus einer Liste auf den Aufbau geschlossen wird, genügt ein Eintrag
nicht.** Der erste ist oft der leere.

**Ein verbreiteter Feldname allein ist kein Erkennungsmerkmal.** Es
braucht einen eindeutigen Pfad oder eine Kombination.

**Und dieselbe Vorsicht gilt für die Menge der Felder.** Dass eine
Abfrage ein Merkmal führt, heißt nicht, dass die Nachbarabfrage es auch
führt — zwei Quellen, zwei Feldmengen. Einmal wurde ein Merkmal aus einer
Listenseite mit einer Stammdatenabfrage zu einer Frage verbunden, die
sich so gar nicht stellen ließ: Die eine führt es, die andere nicht.

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
Längenprüfungen weiter etwas prüfen. Nicht umgekehrt.

**Anonymisierung darf die Auskunft nicht zerstören.** Wo eine Antwortform
belegt ist und keine Personendaten enthält, tritt eine gezielte
Auswertung an die Stelle des Anonymisierers — sie gibt aus, was benannt
ist, statt zu verbergen, was verdächtig aussieht.

**Und das Schwärzwerkzeug darf nicht das Untersuchungswerkzeug sein.**
Ein `sed`, das jeden Text zwischen Tags entfernt, hält Personendaten aus
der Ausgabe — und entfernt zugleich die Spaltenüberschriften, wegen derer
abgefragt wurde. Einmal ist daraus eine falsch begründete Entscheidung
entstanden, die erst Tage später berichtigt wurde.

Wer fremdes HTML untersucht, braucht zwei Durchläufe: einen, der die
Struktur zeigt, und einen, der die Werte schwärzt. Ein Werkzeug, das
beides zugleich tut, tut keines von beiden zuverlässig.

---

## 10 — Form der Projektdateien

**Entscheidungen mit Begründung stehen in `docs/ENTSCHEIDUNGEN.md`** und
werden per `@`-Import eingebunden. Chronologisch, neue Einträge unten
angefügt; alte werden nicht geändert, sondern durch neue aufgehoben.

**Vergebene Nummern werden nie neu vergeben und nie verschoben** — E, M,
und jede Nummerierung in einer Befunddatei. Neue kommen ans Ende. Ein
Zusatzbuchstabe (`B15b`) ist keine Lösung, sondern eine zweite Nummer für
dieselbe Stelle. **Eine Nummer ist ein Name:** Einmal wurde eine
Befunddatei am selben Tag umnummeriert, und dieselbe Nummer bedeutete an
zwei Orten Verschiedenes. Anders als im Code fällt es nicht auf — beide
Dokumente bleiben lesbar, nur der Verweis stimmt nicht mehr.

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
