# Entscheidungen – sprechtag

Chronologisch, neue Einträge unten angefügt. Alte Einträge werden nicht
geändert, sondern durch neue aufgehoben. Nummern werden nie neu vergeben.
Verweise aus anderen Projekten nennen das Projekt („sprechtag E3“).

Angelegt am 07.10.2026. E1 bis E5 halten Entscheidungen fest, die vorher
nur im Changelog oder in der Sitzung standen.

---

## E1 — Mitteilungen unter der Sitzung der Lehrkraft, nicht unter dem Dienstkonto

**Eingetragen:** 07.10.2026 · **wirksam seit:** v0.9.48 (07.10.2026),
Begründung berichtigt in v0.9.50

> **Teilweise überholt (E17, 09.10.2026):** Das Dienstkonto ist abgeschafft;
> es gibt keinen Rückfall mehr. Der erste Satz gilt weiter, der zweite nicht.
> Der Absatz bleibt als damaliger Stand stehen.

**Entschieden:** Eine Mitteilung geht unter dem WebUntis-Konto der
angemeldeten Lehrkraft hinaus. Das Dienstkonto bleibt Rückfall – für
abgelaufene Sitzungen und für Erinnerungen, bei denen zum Sendezeitpunkt
niemand angemeldet ist.

**Warum:** Die ursprüngliche Entscheidung für das Dienstkonto (v0.9.4,
Juli 2026, Changelog „Absender-Frage geklärt“) beruhte auf einem
**Fehlschluss**: Der JWT-Scope `mg:r` wurde als „darf nur lesen“
gedeutet. Der Scope sagt nichts über das Senderecht. Die
WebUntis-Oberfläche trägt denselben Scope und sendet trotzdem
(lernzeiten, 06.10.2026); sprechtag hat am 07.10.2026 mit einer
Lehrkraft-Sitzung gesendet. **Maßgeblich ist
`/WebUntis/api/rest/view/v1/messages/permissions`**, nicht der Scope.
Die Sondierung (`backend/api/sondierung.php`) fragt das seit v0.9.50 ab
und behauptet die alte Deutung nicht mehr.

**Daraus folgt:**
- Eine Einladung kommt bei den Eltern sichtbar von der Lehrkraft.
- Der WebUntis-Cookie liegt in der PHP-Sitzung, nicht in `auth_user()`,
  und gelangt nicht ans Frontend. Das Passwort wird nie gespeichert.
- Die Sitzung lebt 25–30 Minuten und verlängert sich nicht
  (lernzeiten, 29.09.2026). Der Ablauffall ist damit der Normalfall für
  alles, was später als eine halbe Stunde nach dem Login gesendet wird.
  Er ist **noch nicht abschließend behandelt**.
- Ein Rechte-Scope ist künftig kein Beleg für ein Recht. Belegt wird am
  Endpunkt, der das Recht auskunftet, oder durch einen Versuch.

**Nachtrag 11.10.2026 — Vermerk an der Quelle nachgetragen (Betreiber, aus
`koordination`, `docs/BEFUND-2026-10-11-webuntis-api-bestand.md`):** Die
Berichtigung stand hier, aber nicht an den Stellen, auf denen die alte
Entscheidung beruhte. Der CHANGELOG-Eintrag v0.9.4 „Absender-Frage geklärt“
und `docs/MITTEILUNGEN.md` („Rechte“) gaben `mg:r` weiter als „nur lesen“
aus, ebenso die CHANGELOG-Einträge v0.8.0 („Scope-Diagnose“), v0.9.3
(„Vorbereitung“) und v0.9.7 („vom Lehrerkonto senden ist endgültig
ausgeschlossen“). Wer sie las, fand den Grund für das Dienstkonto ohne
Anlass zu zweifeln. Alle fünf Stellen tragen jetzt einen Vermerk: was gilt,
seit wann, wo die Berichtigung steht. Gelöscht ist nichts. Weitere Stellen ohne Vermerk sind
gemeldet, nicht alle behoben.

---

## E2 — Versionsnummern werden nie neu vergeben

**Eingetragen:** 07.10.2026

**Entschieden:** Eine einmal vergebene Versionsnummer wird nie ein
zweites Mal vergeben – auch nicht, wenn die erste Vergabe keinen
Changelog-Eintrag hat. Fehlt eine Version im Changelog, wird sie nicht
nachgetragen, sondern die nächste freie Nummer genommen.

**Warum:** v0.9.47 ist doppelt vergeben: am 10.08.2026 für den CI-Umbau
(`6bc55bd`, `20aabd9`, `5672aa1`, ohne Changelog-Eintrag) und am
07.10.2026 für den Erinnerungsversand (`70f3811`). *Vermutung, nicht
belegt:* Die zweite Vergabe war möglich, weil die erste im Changelog
fehlte und dort nachgesehen wurde. Ebenso passt dazu, dass der Stand vom
02.08. (E3) als letzte Nummer v0.9.46 trug. Seitdem bezeichnet
„v0.9.47“ zwei verschiedene Stände. Die Behebung des daraus
entstandenen Rückschritts ist deshalb v0.9.51 und nicht ein Nachtrag
zu v0.9.47.

**Daraus folgt:**
- Vor dem Vergeben einer Nummer wird `git log --oneline | grep vX.Y.Z`
  geprüft, nicht nur der Changelog.
- Der Beleg für eine vergebene Nummer ist ein Tag. Bis heute ist in
  sprechtag **kein einziger** gesetzt; das ist ein eigener, offener
  Punkt.

---

## E3 — Der stille Rückschritt vom 07.10.2026

**Eingetragen:** 07.10.2026 · **behoben in:** v0.9.51

**Hergang:**
- Am 07.10.2026 um 10:51 entstand `70f3811` („v0.9.47: Erinnerungsversand
  wertet numberOfRecipients aus“). Grundlage war ein **veralteter Stand
  vom 02.08.2026**. Belegt: Der Cache-Stempel in index.html vor dem
  Commit lautete `?v=20260802101626`, das ist der Stand `a91d161`
  (v0.9.46). Außerdem gleichen router.php und deploy.sh im Commit Byte
  für Byte dem Initial commit, style.css dem Stand v0.9.42 und
  WebUntisRest.php dem Stand v0.9.45.
- Dieser Stand wurde per **überschreibendem rsync** in den Arbeitsbaum
  gespiegelt. *Diese Angabe stammt vom Auftraggeber und ist aus dem Repo
  nicht belegbar; das Repo zeigt nur das Ergebnis.*
- `deploy.sh` committete ihn mit `git add -A`, **ohne Sichtung**. Belegt:
  Der Cache-Stempel im Commit (`20261007105115`) liegt eine Sekunde vor
  der Commit-Zeit (`10:51:16`); es war also deploy.sh, nicht ein Commit
  von Hand.
- Damit gingen zwei Monate Arbeit verloren und wurden ausgeliefert: der
  CI-Umbau (Token-Einbindung, h1, Escape, Fokus, Symbole, Version aus
  `/api/health`), die Absicherung von deploy.sh bei leerem Commit
  (`9f6841d`), `WebUntisRest.php` v1.7.0 und die README vom 06.10.
- **Das Warnzeichen war da und wurde übersehen:** `11 files changed,
  395 insertions(+), 447 deletions(-)` – für eine Änderung am
  Erinnerungsversand mehr Löschungen als Einfügungen, über Dateien
  verteilt, die mit Erinnerungen nichts zu tun haben.
- `tests-sprechtag.sh` wäre mit 17 Fehlern rot gewesen. Es lief nicht.

**Behebung (v0.9.51):** Wiederherstellung aus `70f3811~1`. app.js per
Drei-Wege-Zusammenführung (August-Stand, Basis `a91d161`, HEAD) – das
ergab den August-Stand plus genau einen Oktober-Abschnitt in
`ansichtAdminErinnerungen`. index.html übernimmt die August-Form, die
Konflikte betrafen nur Cache-Stempel und die hochgezählte Versionszeile.
Alle übrigen Dateien trugen keinen Oktober-Anteil. Kontrolle: Der Diff
gegen `70f3811~1` enthält danach nur die Backend-Dateien der
Oktober-Commits, 21 Zeilen app.js und deploy.sh.

**Entschieden – die Vorkehrung:** Vor jedem Spiegeln (rsync, Entpacken
eines Bundles, Kopieren eines Stands von woanders) wird **der Stand
beider Seiten angesehen**: `git log -1` und `git status` im Ziel, Datum
bzw. Cache-Stempel der Quelle. Ist die Quelle älter als das Ziel, wird
nicht gespiegelt. Nach dem Spiegeln wird `git diff --stat` gelesen,
bevor irgendetwas committet wird.

**Daraus folgt:** deploy.sh nimmt nichts mehr still mit (E4) und zeigt
vor dem Commit die Statistik. Ein Stand wie der vom 07.10. wäre an den
roten Prüfungen angehalten worden.

---

## E4 — deploy.sh: namentlich, geprüft, mit Auskunft über die Gegenstellen

**Eingetragen:** 07.10.2026 · **wirksam seit:** v0.9.51

**Entschieden:**
- Aufruf `./deploy.sh "Nachricht" datei1 [datei2 …]`. Es wird nur
  addiert, was genannt ist; **kein `git add -A`**.
- Liegt im Baum eine unverfolgte oder eine geänderte, aber nicht genannte
  Datei, hält das Skript an und nennt sie. Damit ist das Geprüfte auch
  das Committete.
- Vor dem Commit laufen **`tests-sprechtag.sh` und alle Suiten in
  `tests/`** (PHP und JS), die aus dem Verzeichnis ermittelt werden. Eine
  rote hält an, ebenso null gefundene Suiten oder ein fehlendes `php`
  bzw. `node`.
- Ohne Änderung wird nicht committet, aber gepusht (Absicherung aus
  `9f6841d`, im August verloren, siehe E3).
- Scheitert ein Push, hält das Skript an und nennt, welche Gegenstelle
  welchen Stand hat. Ein Push wird nicht blind wiederholt.

**Warum:** Der Rückschritt aus E3 ist durch dieses Skript hinausgegangen.
Es nahm alles mit, prüfte nichts und hätte bei einem halben Push nichts
gesagt. Die Suiten in `tests/` rief bis dahin **nichts** auf: Drei davon
waren seit dem 10.08. rot, ohne dass es jemand sah.

**Und eine Annahme, die als Auskunft weiterlief:** Dass deploy.sh
`php -l` ausführt, **stand in keinem Code.** `git log -S"php -l" --
deploy.sh` findet in der ganzen Geschichte keinen Treffer. Die Annahme
lief trotzdem mehrfach als Auskunft weiter und floss in
Deploy-Anleitungen ein – im README ab `d019430` (06.10.2026) als
„Syntax (läuft auch in deploy.sh)“. Berichtigt in v0.9.51. `php -l`
läuft weiterhin **nicht** in deploy.sh. Die PHP-Suiten laden per
`require` die Dateien, die sie prüfen; ein Syntaxfehler darin dürfte ihren
Lauf scheitern lassen – *das ist nicht ausprobiert*. Für PHP-Dateien,
die keine Suite lädt, gibt es keine Prüfung.

**Daraus folgt:** Was deploy.sh tut, steht in deploy.sh. Eine Anleitung,
die etwas über das Skript behauptet, wird am Skript nachgesehen.

**Gegenproben (Probebaum mit lokalen Gegenstellen, 07.10.2026):**
nicht genannte Datei, unverfolgte Datei, rote `tests-sprechtag.sh`, nur
eine rote Suite in `tests/` bei grüner `tests-sprechtag.sh` – jeweils
angehalten, kein Push, HEAD unverändert. Voller Lauf: genau die
genannten Dateien committet, beide Gegenstellen auf dem neuen Stand.
Leerer Lauf: „nichts zu committen“, Push erreicht. Nicht erreichbare
zweite Gegenstelle: angehalten, mit Angabe „github hat X, uberspace
nicht“.

---

## E5 — Die Laufzeitprüfung deckt weniger ab, als sie verspricht

**Eingetragen:** 07.10.2026 · **Stand:** festgehalten, **nicht behoben**

**Befund:** `tests/frontend_laufzeit_test.js` gibt vor zu prüfen, dass
app.js „vollständig ausgeführt“ wird, ohne Fehler. Tatsächlich:
- **Ihr `catch` greift bei Fehlern in `start()` nie.** `start()` ist
  `async`; ein Fehler darin wird ein unbehandelter Promise-Fehler, nicht
  eine Ausnahme im `eval`. Rot wird die Prüfung nur, weil Node bei einem
  unbehandelten Promise-Fehler mit Exit 1 abbricht. Das ist Nodes
  Laufzeitverhalten, nicht die Logik der Prüfung. Die eigene
  Fehlermeldung („✗ Fehler beim Ausführen“) erscheint in diesen Fällen
  nie.
- **Sie spielt nur den nicht angemeldeten Weg durch** (`fetch` liefert
  `angemeldet: false`) und wartet 300 ms. „Vollständig“ heißt also: bis
  zur Anmeldemaske.

Belegt am 07.10.2026: Zwei Abstürze (`dataset` und
`document.addEventListener` im Ersatz-DOM) erschienen beide als
unbehandelte Fehler von Node, nicht als Meldung der Prüfung. Eine
Mutation (`nichtDefiniert()` am Anfang von `start()`) machte die
Prüfung rot – ebenfalls über Nodes Abbruch.

**Entschieden:** Nicht in v0.9.51 behoben, weil der Zug lang genug war.
Festgehalten, damit es nicht wieder zwei Monate liegt. Als Befund an
`koordination` gemeldet: Eine Laufzeitprüfung, deren Fehlerbehandlung
ins Leere greift, kann jedes Projekt mit asynchronem Einstieg treffen.

**Daraus folgt (für die Behebung):** Die Prüfung muss den Promise von
`start()` selbst abwarten und auswerten (oder `unhandledRejection`
abfangen und als eigenen Fehler melden). Wenigstens ein angemeldeter
Weg gehört dazu. Ihr Name sagt danach, was sie prüft.

**Nachtrag 11.10.2026 — zweite Ausprägung desselben Musters, in PHP
(Betreiber, beim Bau von E18/E19):** `tests/run_kalender_abo.php` führt den
Archivieren-Block aus `index.php` gegen eine eigene SQLite-Datenbank aus.
Seit dieser Block auch `abgesagte_termine` leert, fehlte ihr die Tabelle;
die Suite brach mit einem PHP-Fatal (`no such table: abgesagte_termine`)
**nach 10 von 16 Prüfungen ab**. Gemessen:
- **Keine Zeile mit ✗.** Die Suite hat für einen Abbruch keine eigene
  Meldung; ihre Schlusszeile („ALLE TESTS GRÜN“ oder „n ROT“) fehlte
  einfach. Was dastand, war PHPs eigene Fehlermeldung zwischen grünen
  Zeilen.
- **Erkannt hat es nur der Exit-Code** (255 durch den Fatal). Wer die
  Ausgabe gelesen hätte statt des Exit-Codes, hätte sechs ungelaufene
  Prüfungen übersehen, und nichts hätte es gesagt.

**Das ist dasselbe wie oben:** Eine Prüfung, die bei einem Abbruch nicht
sagt, dass sie abgebrochen ist. In Node greift das `catch` ins Leere und
rot wird es durch Nodes Abbruch; hier gibt es kein `catch`, und rot wird es
durch PHPs Abbruch. In beiden Fällen meldet die Laufzeit, nicht die
Prüfung.

**Behoben ist nur der Anlass** (die Testdatenbank kennt die Tabelle jetzt,
mit den Einschränkungen aus `sql/24`), **nicht das Muster.** Für die
Behebung zu überlegen: Ob jede Suite ihre **erwartete Prüfungszahl** kennt
und am Ende vergleicht – dann fiele ein Abbruch an der Zahl auf, auch ohne
Exit-Code. Dagegen steht der Pflegeaufwand (REIHENREGELN 2: eine
Mindestzahl je Suite muss selbst gepflegt werden). **Nicht jetzt gebaut.**

**Nachtrag 11.10.2026 — drittes Vorkommen, in der eigenen neuen Suite
(Betreiber):** Mutation AT11 schaltete in `bu_buchungen_entfernen()` die
Wache gegen eine unzulässige Bedingung ab. `tests/run_abgesagte_termine.php`
endete daraufhin **nach 23 Zeilen mit Exit 255, ohne eine Zeile mit ✗.**
Gemessen war die Ursache: Die Hilfsfunktion `$abgelehnt` fing nur
`InvalidArgumentException`. Ohne Wache wurde aus der leeren Bedingung
`WHERE  ORDER BY`, und die `PDOException` dazu ging ungefangen durch.

**Drei Fälle in drei Tagen, jedes Mal in einer anderen Schicht:**
- E5 (Node): Das `catch` greift ins Leere.
- `run_kalender_abo` (PHP): Die Suite bricht ohne Zeile ab.
- Jetzt eine Hilfsfunktion in einer Suite, die eigens für diesen Zug
  geschrieben wurde.

Drei Fälle sind kein Zufall mehr, sondern **ein Muster des Werkzeugs:
Prüfungen melden, was sie erwarten, aber nicht, dass sie aufgehört
haben.**

**Ein Abbruch verdeckt die wichtigere Hälfte.** Gegen die leere Bedingung
schützt neben der Wache auch der SQL-Syntaxfehler. Gegen „nur Sprechtag,
ohne Lehrkraft“ schützt **nur** die Wache, und genau diese Prüfung
erreichte die Suite nach dem Abbruch nicht mehr. Sichtbar blieb nur der
Fall, der doppelt gesichert ist.

**Behoben ist wieder nur der Anlass:** `$abgelehnt` fängt jetzt jede
Ausnahme und meldet sie als „nicht abgewiesen“, also als rote Zeile. Als
Abweisung zählt weiterhin nur `InvalidArgumentException`. AT11 schlägt
seitdem an der erwarteten Zeile an, und die Suite läuft bis zu ihrer
Schlusszeile.

**Ein Vorbild für die spätere Behebung:**
`tests/frontend_abgesagte_termine_test.js` (v0.9.83) meldet einen Abbruch
ihres asynchronen Teils als eigene rote Zeile („✗ Suite abgebrochen: …“),
nicht nur über Nodes Exit-Code. Hier ist das zum ersten Mal so gebaut, für
eine Suite, nicht als Muster.

---

## E6 — Das Logo im Kopf ist dekorativ

**Eingetragen:** 07.10.2026 · **wirksam seit:** v0.9.52

**Entschieden:** Das Logo im Seitenkopf trägt `alt=""` und bekommt zur
Laufzeit keinen Alt-Text. Die Zeile `logo.alt = 'Logo ' + schulname` in
`wendeMarkeAn()` ist entfernt.

**Warum:** Der Schulname steht direkt neben dem Logo als Text. Mit
Alt-Text liest ein Screenreader ihn zweimal. Der CI-Umbau vom August
hatte das richtig entschieden und im HTML begründet; die JS-Zeile aus
v0.9.40 überschrieb es zur Laufzeit, weil sie übersehen hatte, dass der
Titel daneben steht. Im Browser galt deshalb von August bis v0.9.51 das
Gegenteil dessen, was das HTML zusagte.

**Wie das zwei Monate stehen konnte:** Zwei Prüfungen, beide grün —
`tests-sprechtag.sh` las das HTML („dekorativ“), die
Barrierefreiheits-Suite las das JavaScript („beschreibender Alt-Text“).
Prüfungen, die verschiedene Schichten messen, können sich nicht
widersprechen; der Widerspruch entsteht erst dort, wo beide Schichten
zusammenkommen.

**Daraus folgt:** Die Barrierefreiheits-Suite prüft jetzt beides an
einer Stelle: `alt=""` im HTML **und** dass `wendeMarkeAn()` keinen
Alt-Text vergibt. Ein leerer oder umbenannter Funktionsrumpf zählt dabei
nicht als bestanden. Das Vorschaubild im Admin-Formular behält seinen
Alt-Text — dort steht kein Name daneben.

---

## E7 — Keine Farbfelder im Erscheinungsbild

**Eingetragen:** 07.10.2026 · **wirksam seit:** v0.9.52 (Migration
`sql/20_farbfelder_entfernen.sql` von Hand einzuspielen)

**Entschieden:** Akzent- und Sekundärfarbe sind aus dem Admin-Formular,
aus der Annahme im Backend, aus dem Seed `10_branding.sql` und — per
Migration — aus der Datenbank entfernt. Sie werden **nicht** wieder
wirksam gemacht. Das Formular sagt in einem Satz, warum es sie nicht
gibt.

**Warum:** Der CI-Umbau vom 10.08.2026 hat entschieden, dass die
Akzentfarbe die **Anwendung** kennzeichnet, nicht die Schule — damit
man bei mehreren offenen Tabs sieht, wo man ist. Seitdem setzte das
Branding keine Farbe mehr. Die Felder blieben stehen, ließen sich
ausfüllen und speichern und bewirkten nichts. **Ein Formularfeld, das
nichts tut, ist schlimmer als keines: Es verspricht etwas.** Die Farben
wieder wirksam zu machen hieße, eine bewusst getroffene Entscheidung
rückgängig zu machen.

**Warum es zwei Monate niemandem auffiel:** Die August-Entscheidung
stand **nur in einem Code-Kommentar** über `wendeMarkeAn()` — nicht in
der Commit-Meldung („ci-css, h1, Escape, Fokus, Symbole“), nicht im
Changelog, nicht in einem Entscheidungsprotokoll. Wer das Formular
pflegte, las diesen Kommentar nicht; wer den Kommentar schrieb, sah das
Formular nicht. Ihre Folge — fünf Stellen, die weiter Farben versprachen
(Formularfelder, Abschnittstitel, Hilfetext, README und
`docs/signage-wiederverwenden.md`) — stand nirgends. **Eine
Entscheidung, die nur am Ort ihrer Umsetzung steht, erreicht die Orte
ihrer Folgen nicht.** Dafür gibt es diese Datei.

**Die Datenbank gehört dazu:** `marke_lesen()` liefert jede
`marke_%`-Zeile über das öffentliche `GET /api/einstellungen` aus.
Stehengelassene Werte gingen weiter an jeden Aufrufer, als bedeuteten
sie etwas — dieselbe zweite Wahrheit wie das Formularfeld, eine Schicht
tiefer. Und die Migration allein genügte nicht: `10_branding.sql` legte
die Werte per `INSERT IGNORE` an und hätte sie bei jedem erneuten
Einspielen oder einer Neueinrichtung zurückgebracht. Beides ist
entfernt.

**Rückweg:** Vor dem Einspielen werden die gespeicherten Werte einmal
gelesen und im Bericht festgehalten (die Abfrage steht im Kopf der
Migration). Eine hinterlegte Schulfarbe ist dann nicht spurlos weg.

**Daraus folgt:**
- Die Rohfarben-Prüfung in `tests-sprechtag.sh` liest jetzt auch das
  JavaScript. Sie las bisher nur `style.css`, während die
  Voreinstellungen der Farbfelder als Hexwerte im JS standen. Bekannte,
  bewusste Grenze: ein Farbwert in einem Kommentar hinter Code in
  derselben Zeile wird rot.
- Die Marke-Suite prüft, dass weder Formular noch Hilfe noch Backend
  noch Seed die Farben zurückbringen, und dass die Migration beide
  Schlüssel entfernt.

---

## E8 — Schild-Import entfällt, Schülerliste kommt aus WebUntis

**Eingetragen:** 08.10.2026 · **wirksam seit:** noch nicht — der Umbau
ist ein eigener Zug. Beleg: `docs/BEFUND-2026-10-07-pageconfig-schuelerliste.md`.

**Entschieden:** Der Schild-CSV-Import wird nicht mehr gebraucht.
Klasse und aktive Auswahl kommen aus `pageconfig?type=5`.

**Belegt** (07.10.2026, Produktivsystem; Kreisprüfung am Elternkonto
gemeldet 08.10.2026):
- `pageconfig` liefert 1314 aktive Schüler, davon 1235 mit `klasseId`;
  `getStudents` liefert 3802 (alle je angelegten). Der
  Ehemaligen-Filter, für den bisher das Austrittsdatum aus Schild nötig
  war, ist eingebaut.
- Alle 1314 Kennungen kommen auch in `getStudents` vor: ein Kreis.
- Derselbe Kreis trägt `user.students[].id` im Eltern-Login und die
  Stundenplan-Kennung — an einem Elternkonto mit zwei Kindern geprüft.
  `buchungen.schueler_id` übernimmt diese Kennung im Eltern-Weg
  (`auth_kind_erlaubt()`, aus dem Code). **Nicht geprüft:** der Weg
  volljähriger Schüler, der die eigene `personId` als Kind-Kennung
  einsetzt.
- Die 79 ohne `klasseId` sind Beurlaubte, Externe, alte Backup-Konten
  und Testkonten (Auskunft, nicht gemessen). Sie gehören nicht in die
  Einladungsauswahl; das Fehlen der Klasse ist Merkmal, nicht Lücke.

**Folgt daraus:** Die lokale `schueler`-Tabelle verliert ihren Grund,
ist aber **nicht** ersatzlos entbehrlich — sieben Stellen zeigen daraus
Kindnamen an, die Einladungsprüfung und `mit_eltern_ids_ermitteln()`
greifen darauf zu. Der Umbau muss dafür Ersatz schaffen, sonst
entstehen leere Namen und eine still übersprungene Prüfung.

**Nicht gebraucht** wird der Empfängerfilter (`CUSTOM/filter` mit
`CLASS`+`ROLE`): Er liefert `user.id`s in einem anderen Kreis.
`pageconfig` liefert dieselbe Information im richtigen.

**Nachtrag 08.10.2026 — kein Schild-Import als Notfallweg:**

Die Schülerliste kommt aus WebUntis, `pageconfig?type=5`. **Fällt dieser
Weg weg**, wird der Schild-Import aus der Historie wiederhergestellt
(eingeführt in v0.7.0, Commit `d6c1266`; Austrittsdatum v0.7.2,
`40d49ba`) — dann aber **mit Prüfung** und mit einer Übersetzung in den
Kreis der Kind-Kennung.

Er bleibt **nicht** als schlafende Option im Code. Eine Option, die
niemand nutzt, wird nicht geprüft: Der Import steht seit dem 24.07.2026
im Code und ist nie gelaufen — alle 3801 Datensätze haben eine leere
Klasse, und das blieb rund zwei Monate unbemerkt, weil nichts darauf
zugriff. Als Notfallweg wäre er derselbe Zustand: vorhanden, ungeprüft,
im Ernstfall vermutlich kaputt. Dazu verknüpft er über die Schild-ID,
nicht über die Kind-Kennung des Normalwegs — ein anderer Nummernkreis.

**Nachtrag 08.10.2026 — der Kindname wird beim Buchen festgehalten:**
Für Zug 4 ist festgelegt: Der Name des Kindes wird **beim Buchen
gespeichert**, nicht zur Laufzeit aus WebUntis geholt. Grund: Die
Kalender-Abos (`GET /api/kalender/{token}.ics`) werden von einer
Kalender-App ohne Anmeldung abgerufen; dort steht weder `pageconfig`
noch `auth_user()` zur Verfügung. Ein Laufzeit-Abruf ließe „Kind: …“
still zu „Termin“ werden. Beleg:
`docs/BEFUND-2026-10-07-pageconfig-schuelerliste.md`, Abschnitte 9–10.

---

## E9 — Der Ablauffall der Lehrkraft-Sitzung ist gemessen (ergänzt E1)

**Eingetragen:** 08.10.2026 · **betrifft:** E1, letzter Spiegelpunkt
(„noch nicht abschließend behandelt“) · keine Codeänderung

> **Teilweise überholt (Nachtrag 08.10.2026, unten):** Die Ursachenangabe
> im folgenden Absatz — `tokenHolen()`, `mit_rest_aus_sitzung()` — ist aus
> dem Code geschlossen, nicht gemessen. Der Absatz bleibt als damaliger
> Stand stehen.

**Gemessen 08.10.2026:** Nach etwa 30 Minuten Wartezeit greift beim
Einladungsversand der Rückfall auf das Dienstkonto. Die
WebUntis-Sitzung der Lehrkraft war abgelaufen; `tokenHolen()` lieferte
kein Token, `mit_rest_aus_sitzung()` gab `null` zurück, und
`mit_einreihen_und_senden()` hat auf das Dienstkonto zurückgegriffen
(`backend/api/mitteilungen.php`, „2. Wahl“).

Das deckt sich mit der Messung aus lernzeiten vom 29.09.2026: Die
Sitzung lebt 25–30 Minuten und verlängert sich nicht durch Nutzung.

**Entschieden:** E1 bleibt, wie gebaut. Der Ablauffall ist für den Weg
**mit** hinterlegtem Dienstkonto belegt.

**Nicht geprüft:** der Fall **ohne** hinterlegtes Dienstkonto. Dann soll
die Mitteilung als `offen` stehenbleiben, mit dem Hinweis „bitte neu
anmelden und erneut senden“. Das ist im Code vorgesehen
(`mit_einreihen_und_senden()`, Rückgabe `status => 'offen'`), aber
ungemessen.

> **Teilweise überholt (E17, 09.10.2026):** Behoben in v0.9.72 –
> `mit_rest_aus_sitzung()` ist durch `wu_sitzung()` ersetzt, das abgelaufen,
> nicht erreichbar und kaputt (Programmierfehler) getrennt meldet; einen
> Rückfall gibt es nicht mehr. Der Absatz bleibt als damaliger Stand stehen.

**Daneben gefunden, nicht behoben:** `mit_rest_aus_sitzung()` fängt mit
`catch (Throwable …)` und gibt dann ebenfalls `null` zurück. Ein
Programmierfehler in diesem Weg sähe deshalb genau so aus wie eine
abgelaufene Sitzung — der Versand fiele still auf das Dienstkonto
zurück (FALLSTRICKE Abschnitt 3). Unterscheidbar ist es nur im
Server-Log (`sprechtag: Sitzung der Lehrkraft nicht nutzbar: …`).

**Nachtrag 08.10.2026 — gemessen und geschlossen getrennt:**

| | |
|---|---|
| **Gemessen** | Nach etwa 30 Minuten Wartezeit lief der Einladungsversand über das Dienstkonto. |
| **Aus dem Code geschlossen, nicht belegt** | dass die Sitzung abgelaufen war, `tokenHolen()` kein Token lieferte und `mit_rest_aus_sitzung()` deshalb `null` zurückgab. |

Die Ursache ist **nicht unterscheidbar**, ohne ins Server-Log zu sehen:
Wegen des `catch (Throwable …)` (oben) führt auch ein Fehler im
Sitzungsweg zu `null` und damit zum selben Rückfall. Der Abgleich mit
lernzeiten (29.09.2026) ist deshalb **verträglich, keine Bestätigung**.

Die Aussage „der Ablauffall ist für den Weg mit Dienstkonto belegt“
bleibt richtig: Belegt ist der **Rückfall**, nicht seine Ursache.

Warum diese Trennung hier steht: Dieselbe Vermischung — ein Schluss aus
Code oder Scope, behandelt wie eine Messung — hat beim Scope `mg:r`
monatelang in die Irre geführt (E1).

**Nachtrag 08.10.2026 (v0.9.54) — eine dritte Ursache für denselben
Rückfall:** Ist WebUntis nicht erreichbar, liefert `rohGet()` Status 0,
`tokenHolen()` `false` — und `mit_rest_aus_sitzung()` meldet das genau wie
eine abgelaufene Sitzung (aus dem Code; in `tests/run_messung_sitzung.php`
gegen einen geschlossenen Port ausgeführt). Der gemessene Rückfall kann
also drei Ursachen haben: Ablauf, Netz, Ausnahme. Seit v0.9.54 nennt
`mit_rest_aus_sitzung()` auf Wunsch den Grund (`kein_cookie`,
`kein_token`, `fehler: …`); das Verhalten ist unverändert, die Behebung
des `catch` bleibt offen.

---

## E10 — Gliederung der buchbaren Lehrkräfte für Eltern

**Eingetragen:** 08.10.2026 · **wirksam seit:** noch nicht — Teil des
Umbaus der Einladungsauswahl. Anlass:
`docs/BEFUND-2026-10-08-einladungs-kachel.md`.

**Entschieden** (Betreiber):

- **Phase 1:** In den Kacheln stehen **nur die Eingeladenen** — nicht
  zusätzlich zu den Unterrichtenden und Sonderrollen.
- **Ab Phase 2:** Dreiteilung —
  1. Eingeladene,
  2. Unterrichtende, die Klassenleitung darin hervorgehoben,
  3. weitere Lehrkräfte hinter einer **Suche** statt als Kacheln.

**Warum:** Heute speist sich die Liste nur aus Stundenplan und
Sonderrollen; eine Lehrkraft, die eingeladen hat, das Kind aber nicht
unterrichtet, fehlt — in Phase 1 genau die, bei der gebucht werden
soll. Phase 1 ist die Phase der Einladung, also zeigt sie die
Einladenden und nichts sonst.

**Warum es hier steht und nicht nur im Befund:** Eine Entscheidung, die
nur an einem Ort steht, an dem niemand sie sucht, erreicht die Orte
ihrer Folgen nicht (E7, Akzentfarbe).

**Daraus folgt für den Umbau:**
- Kachel und Buchungsrecht werden **zusammen** geändert.
  `GET /api/buchbare-lehrer` und `bu_lehrer_erlaubt()` fragen heute
  dieselben zwei Quellen an zwei Stellen ab; Eingeladene fehlen in
  beiden. Wird nur die Kachel ergänzt, lehnt die Buchung ab
  (`darf_lehrkraft`, vor der Phase-1-Prüfung in
  `slot_buchung_erlaubt()`).
- Die Klassenleitung kommt aus `getKlassen` bzw. `pageconfig`
  (`classteacher`, `classteacher2`), siehe E8.

**Nachtrag 08.10.2026:** Der Phase-1-Teil ist **wirksam seit v0.9.53**,
ebenso, dass Eingeladene ab Phase 2 sichtbar und buchbar bleiben. Die
Dreiteilung ab Phase 2 (Klassenleitung hervorgehoben, weitere hinter
einer Suche) ist **noch nicht gebaut**.

**Nachtrag 08.10.2026 — Entscheidungen für Zug 3 (Betreiber), noch nicht
gebaut:**

- **Weitere Lehrkräfte sind in Phase 2 buchbar**, alle teilnehmenden.
  Eine Suche, die Lehrkräfte zeigt, bei denen man nicht buchen kann,
  führt ins Leere — dasselbe Muster wie bei der Einladungs-Kachel. Und
  Eltern sollen auch Vertretungslehrkräfte erreichen können. Die Sperre,
  die zählt, ist die **Teilnahme**: bei jemandem zu buchen, der am
  Sprechtag nicht da ist, wäre der eigentliche Fehler. Kachel bzw. Suche
  und `bu_lehrer_erlaubt()` werden zusammen geändert.
- **Die Klassenleitung steht immer in Gruppe 2**, hervorgehoben, und ist
  buchbar — auch wenn sie das Kind nicht unterrichtet (Teilzeit,
  Oberstufe). Sie ist für Eltern oft die wichtigste Ansprechperson und
  gehört nicht hinter die eingeklappte Suche. Gruppe 2 heißt damit:
  Unterrichtende **und** Klassenleitung.
- **„Teilnehmend“ wie im Bestand:** alle außer `teilnahme = 0`; keine
  Zeile in `sprechtag_lehrer` gilt als teilnehmend. Eine zweite Regel
  für dieselbe Frage wäre der Fehler der Einladungs-Kachel noch einmal.
- **Woher die Klassenleitung kommt, wird erst gemessen** (v0.9.55,
  `GET /api/messung/sitzung`, Teil `klassenleitung`): Trägt die
  Eltern-Sicht von `pageconfig` `classteacher`/`classteacher2` gefüllt,
  in welchem Format, und passt der Wert zu `lehrer.webuntis_id`? Gebaut
  wird danach. Trägt sie nicht, wird zwischen Stammdaten-Sync und
  `getKlassen` über das Dienstkonto entschieden — und ob die personIds
  zu `lehrer.webuntis_id` passen, wird dann ebenfalls erst gemessen.

**Nachtrag 08.10.2026 (v0.9.56):** `pageconfig` trägt die Klassenleitung
nicht — `classteacher`/`classteacher2` sind leer (gemessen, Befund
pageconfig-Schülerliste, Abschnitt 11). Damit gilt die Bedingung des
vorigen Nachtrags; statt Stammdaten-Sync oder `getKlassen` über das
Dienstkonto ist aber ein dritter Weg gefunden:
`timetable/filter?resourceType=CLASS` liefert `classTeacher1/2` mit
Kennung und Kürzel in der Eltern-Sitzung. Gebaut wird erst, wenn über
unsere Sitzung gemessen ist, ob er trägt und ob er vom Zeitraum abhängt.

**Nachtrag 08.10.2026 — Quelle der Klassenleitung entschieden:**
`timetable/filter?resourceType=CLASS` über die Eltern-Sitzung. Die
Zuordnung läuft über `klasseId` aus `pageconfig` auf `class.id`, und der
Treffer auf die Lehrkraft geht über `classTeacher1/2.id` auf
`lehrer.webuntis_id`. Grundlage ist die Messung v0.9.56: Kennung und
Kürzel passen 36/36 bzw. 34/34 zur selben Lehrkraft, und das Ergebnis
hängt nicht vom Zeitraum ab (Befund pageconfig-Schülerliste,
Abschnitt 11). Stammdaten-Sync und `getKlassen` über das Dienstkonto
entfallen dafür. Eine Klasse ohne Leitung und ein Kind ohne Klasse
ergeben keine Hervorhebung und keinen Fehler.

**Nachtrag 08.10.2026 — Dreiteilung wirksam seit v0.9.57.** Mit dem Bau
hat der Betreiber vorab entschieden:
- **Zeitpunkt der Klassenleitung:** beim ersten Laden der Buchungsseite
  je Anmeldung, gemerkt in der PHP-Sitzung, und nur bei Erfolg. Nicht
  beim Login und nicht in einer Tabelle. Ist die WebUntis-Sitzung
  (25–30 Minuten) schon abgelaufen, gibt es keine Hervorhebung und keinen
  Fehler; buchbar bleibt die Klassenleitung über Gruppe 3.
- **Sonderrollen** bleiben sichtbare Kacheln in Gruppe 2, hinter den
  Unterrichtenden und der Klassenleitung, wie im Bestand.
- **Gruppe 3** zeigt Treffer erst bei Eingabe, damit keine Wand aus allen
  Lehrkräften entsteht.

Beim Bau festgelegt (Folgerungen, keine neuen Richtungen):
- **Gruppe 3, das Buchungsrecht ab Phase 2 und die nicht unterrichtende
  Klassenleitung verlangen `lehrer.aktiv = 1`.** Ausgeschiedene
  Lehrkräfte erscheinen in der Verwaltung nicht und können dort also
  nicht auf `teilnahme = 0` gesetzt werden. Ohne diese Bedingung stünden
  sie dauerhaft in der Suche. Für Unterrichtende, Eingeladene und
  Sonderrollen gilt sie nicht; dort bleibt der Bestand.
- **Die nicht unterrichtende Klassenleitung und Gruppe 3 erscheinen nur
  dort, wo sie buchbar sind**, also ab Phase 2
  (`slot_alle_teilnehmenden_buchbar()`). In der Vorbereitung oder für
  die Verwaltung in Phase 1 stünde sonst eine Kachel ohne Buchungsrecht
  da.
- **Hervorhebung nur für Eltern.** Gemessen ist nur die Eltern-Sicht;
  für volljährige Schüler ist der Weg nicht belegt.

**Nachtrag 09.10.2026 — Zug 3b, v0.9.58 (Entscheidung Betreiber):**
- **Das obere Suchfeld der Buchungsseite entfällt.** Es filterte nur die
  ohnehin sichtbaren Kacheln, höchstens ein Dutzend, und stand direkt
  über dem Block, der alle Teilnehmenden findet. Wer oben tippt und
  nichts findet, hält das für vollständig und sieht den unteren Block
  nicht mehr an. Zwei Suchfelder für verschiedene Mengen sind schlechter
  als eines. Es bleibt das Suchfeld der Gruppe 3; eine ausgeführte
  Prüfung zählt die Suchfelder der Seite.
- **Der Rand links an der Klassenleitung entfällt.** Er sah aus wie die
  Markierung „gewählt“ und verdoppelte, was das Abzeichen
  „Klassenleitung“ schon trägt. Das Abzeichen ist jetzt die einzige
  Kennzeichnung; die Kachel trägt keine eigene Klasse dafür. Damit ist
  der Abnahmepunkt aus v0.9.57 („Rand sichtbar und von ‚gewählt‘
  unterscheidbar“) beantwortet, mit Nein.

**Nachtrag 09.10.2026 — v0.9.61, aus der Dreiteilung wird eine
Vierteilung (Entscheidung Betreiber):**
Eingeladene / Klassenleitung + Unterrichtende / **Sonderrollen** /
Weitere hinter der Suche. Befund am Bild: Die Beratungslehrkraft stand
mitten zwischen den Fachlehrkräften; dass sie eine andere Art
Ansprechpartnerin ist, zeigte nur das Abzeichen. Fachlich sind es zwei
Fragen – „Wer unterrichtet mein Kind?“ und „An wen wende ich mich bei
einem Anliegen, das kein Fach betrifft?“ –, und die zweite ging in der
Kachelwand unter.
- Die Sonderrollen stehen **an derselben Stelle wie bisher**, nach den
  Unterrichtenden, in einem eigenen Gitter: Es beginnt eine neue Zeile,
  ein Abstand setzt es ab. Die Reihenfolge bleibt eine Rangfolge nach
  Nähe zum Kind; die Sonderrollen zwischen Klassenleitung und
  Fachlehrkräfte zu schieben, bräche sie.
- Gebaut ist die schlichte Fassung, ohne Überschrift. Ob es eine braucht,
  wird am Screenshot entschieden.
- Die Gliederung steht an einer Stelle (`buchenLehrerAbschnitte()`); die
  flache Reihenfolge (`buchenLehrerAlle()`) wird daraus abgeleitet.

## E11 — Mobile Ansicht: in Durchgängen, das Gerät des Betreibers misst

**Stand 09.10.2026, v0.9.58.**

Eltern buchen nach Einschätzung des Betreibers am Rechner und am Telefon.
Die mobile Ansicht wird deshalb **vor** der Schülerliste (bisher Zug 4)
überarbeitet: Die Schülerliste berührt Einladungsauswahl, Namensanzeige
und Datenmodell; würde die mobile Darstellung danach überarbeitet,
müsste man alles zweimal anfassen.

**Messinstrument sind die Screenshots des Betreibers** (iPhone, Safari,
etwa 390 px). Weder die Entwicklung noch der Betreiber sehen die mobile
Ansicht anders. Gebaut wird in Schritten, die einzeln prüfbar sind; was
nur vermutet werden kann, wird als Vermutung gemeldet, nicht als
behoben.

**Hilfsmessung, kein Ersatz:** Für v0.9.58 lief die echte Oberfläche mit
erfundenen Daten in Chrome (ohne Kopf, 390 px breit in einem Rahmen) und
maß, ob etwas über den rechten Rand ragt. Das ist Chrome, nicht Safari,
und es misst Breiten, nicht Bedienbarkeit. Das Werkzeug liegt in
`tests/mobil-messung/` (läuft nicht in `deploy.sh` mit, es braucht
Chrome); die Ergebnisse stehen im CHANGELOG zu v0.9.58.

**Tabellen rollen in einem Rahmen, sie werden nicht umgebaut.** Jede
Tabelle geht durch `tabelleRahmen()`; eine Prüfung zählt Tabellen gegen
Rahmen. Ob einzelne Tabellen auf dem Telefon besser als Karten
erscheinen (etwa „Meine Termine“ für Eltern), entscheidet ein späterer
Durchgang anhand der Screenshots.

## E12 — Mobile Ansicht: WebKit statt Chrome als Hilfsmessung; relativ statt fest, wo Breite den Platz bestimmt

**Stand 09.10.2026, v0.9.59.** Ergänzt E11, hebt nichts davon auf.

**Anlass.** Auf dem iPhone ließ sich unter v0.9.58 die ganze Seite seitlich
verschieben (Screenshot des Betreibers), während die Hilfsmessung aus E11
für dieselben Ansichten „390 px, passt“ meldete. Sie maß in Chrome, und
Chrome zeigt den Fehler nicht.

**Die Hilfsmessung läuft deshalb in WebKit mit iPhone-Nachbildung**
(Playwright, Viewport-Meta gilt, Touch), nicht mehr in Chrome. Unter
v0.9.58 zeigt sie den Fehler: 7 von 16 Ansichten 590 statt 390 px. Sie
misst die Seitenbreite gegen den Viewport und benennt das verursachende
Element durch Ausblenden, nicht durch die Lage von Kästen – der gefundene
Verursacher lag mit seinem Kasten ganz im Bild. Es bleibt eine
Hilfsmessung: WebKit auf dem Mac ist nicht Safari auf iOS, die Daten sind
erfunden. Messinstrument bleibt das Gerät (E11).

**Grundsatz (Entscheidung des Betreibers):** Wo eine Breite den Platz
bestimmt – Seitenleiste, Spaltenzahl der Kacheln, Mindestbreiten von
Tabellen und Kacheln, Eingabefelder –, relativ statt fest. Nicht
umgestellt wird, was nicht mitwachsen soll: Rahmenstärken, kleine
Abstände, Symbolgrößen. Umgestellt wird nach Liste und einzeln, nicht in
einem Umbau; die Liste steht im CHANGELOG zu v0.9.59.

## E13 — Karten statt seitlichem Rollen; Abmelden lädt die Seite neu

**Stand 09.10.2026, v0.9.60.**

**Karten (Entscheidung des Betreibers).** Die vier Tabellen, die auf dem
Telefon zu breit sind – Meine Termine, Einladungen, Login-Protokoll,
Mitteilungen –, werden auf schmalem Bildschirm zu Karten: je Zeile ein
Block, die Spaltenüberschriften als Beschriftung darin, nur senkrecht
rollen. Begründung des Betreibers: Seitliches Wischen ist eine Geste, die
man kennen muss, und eine halb sichtbare Tabelle sieht aus wie ein Fehler.
Ein Muster für alle vier, auch für die langen Listen der Verwaltung. Der
rollende Rahmen (E11) bleibt Rückfall für die übrigen Tabellen.

Zwei Festlegungen der Entwicklung:
- **Schwelle:** dieselbe wie die Telefonansicht (760 px), in derselben
  Medienabfrage. Keine zweite Grenze – zwei Schwellen für dieselbe Frage
  wären zwei Wahrheiten. Folge: Im Querformat (über 760 px, Rechneransicht
  mit Seitenleiste) bleiben die Tabellen Tabellen und rollen, wo sie nicht
  passen (gemessen: drei der vier). Ob das so bleibt, ist offen.
- **Knöpfe:** Die Spalte ohne Überschrift („Verwerfen“, „Absagen“,
  „Löschen“) wird die Fußzeile der Karte, ohne Beschriftung; leer entfällt
  sie.

Umgeschaltet wird allein im CSS: `kartenTabelle()` versieht die Zellen mit
der Überschrift ihrer Spalte (`data-label`) und gibt der Tabelle Rollen,
damit Bildschirmleser sie als Tabelle erkennen, wenn `display` die
Bedeutung aufhebt. Eine Tabelle, eine Darstellung je Breite.

**Abmelden lädt die Seite neu.** Bisher zeichnete Abmelden nur neu; der
Zustand des Kontos lebte weiter – eigene Termine, gewähltes Kind,
persönlicher Kalender-Link –, und wer sich danach im selben Browser
anmeldete, bekam ihn zu sehen. Eine Liste der zurückzusetzenden Felder
würde mit jedem neuen Feld veralten; ein Neuladen setzt alle zurück.

**Nachtrag 11.10.2026 (v0.9.83, Entscheidung Betreiber) — eine fünfte
Kartenansicht: Abgesagte Termine.** Die Tabelle „Abgesagte Termine“ in der
Ansicht der Lehrkraft (E19) wird auf schmalem Bildschirm ebenfalls zur
Karte. Sie hat sieben Spalten, mehr als jede der vier bisherigen. Den
Ausschlag gibt der Moment, in dem sie gelesen wird: Nach einem Ausfall
schaut eine Lehrkraft vermutlich unterwegs nach, wer bei ihr gebucht
hatte – am Telefon, wo seitliches Wischen am wenigsten taugt. Es gilt die
Begründung oben: Eine halb sichtbare Tabelle sieht aus wie ein Fehler.

Gefunden hat die Erweiterung die Prüfung in `frontend_mobil_test.js`, die
die Kartenansichten zählt: Die neue Tabelle war beim Bau mit
`kartenTabelle()` entstanden, die Prüfung wurde rot, und die Frage ging an
den Betreiber, statt die Entscheidung stillschweigend zu erweitern. Die
Prüfung bleibt so streng wie vorher – jetzt fünf Ansichten, je eine Karte,
gesamt fünf. Der rollende Rahmen bleibt Rückfall für die übrigen.

**Zweiter Nachtrag 12.10.2026 (v0.9.99, Entscheidung Betreiber) — eine
sechste Kartenansicht: Administratoren.** Die Liste der Administratoren
(E29) wird auf schmalem Bildschirm zur Karte. Begründung wie bei den
abgesagten Terminen: Vier Spalten mit einem ein- bis zweisätzigen Hinweis
und einem Knopf würden auf dem Telefon seitlich abgeschnitten – genau das,
was diese Entscheidung verhindern soll. Und der Hinweis ist kein Beiwerk:
Er sagt, warum jemand Administrator ist und ob er entfernt werden kann.
Gefunden wieder durch dieselbe Prüfung, die beim Bau rot wurde; die Frage
ging an den Betreiber. Die Prüfung zählt jetzt sechs Ansichten, gesamt
sechs.

## E14 — Abstände zwischen Abschnitten: zwei Werte, eine Regel

**Stand 09.10.2026, v0.9.63. Entscheidung des Betreibers.**

Bis v0.9.62 brachte jeder Baustein seinen Abstand selbst mit; ein Block
hatte oben keinen, ein frei stehender Knopf gar keinen. Gemessen: 0 px
zwischen Kachelgruppe und „Weitere Lehrkräfte suchen“, 0 px zwischen
„Aktualisieren“ und dem folgenden Block, 13 px zwischen Tabelle und
„Aktualisieren“ – der Knopf sah aus wie die nächste Tabellenzeile.

**Zwei Werte an einer Stelle** (`:root` in `style.css`):
- `--abstand-abschnitt: 2rem` – zwischen Abschnitten. Am Gerät bewährt bei
  den Sonderrollen.
- `--abstand-innen: .8rem` – innerhalb eines Abschnitts: nach einer
  Überschrift und zwischen gleichartigen Blöcken (FAQ, Klassenlisten
  bleiben eine Liste).

**Was ein Abschnitt ist:** Block, Sektion, Kachelgitter, Tabelle (im
Rahmen), Raster, Knopfzeile, Zwischenüberschrift und frei stehender Knopf.
- **Ein Knopf unter einer Liste ist ein eigener Abschnitt** (2rem): Er ist
  keine Fortsetzung der Tabelle, sondern eine Handlung, die man bewusst
  auslöst. Eine dritte Stufe (1rem für den Knopf) wäre feiner, ist aber
  schwerer zu halten.
- **Die Sektionen der Verwaltung bekommen ebenfalls 2rem**, obwohl Rahmen
  sie trennen – eine Ausnahme wäre eine dritte Regel.

Die Bausteine tragen keinen eigenen Außenabstand mehr. Wo ein Element einen
inneren Abstand unten mitbrachte, der nicht verschmilzt (Label in der
Eingabezeile, letzte Karte im Rollrahmen, frei stehender Knopf als
`inline-block`), ist er entfernt bzw. der Knopf steht als Block – sonst
stünden statt 32 bis zu 46 px da (gemessen).

**Nachtrag zu E14, 09.10.2026 (v0.9.64, Entscheidung Betreiber):**
- **Treffer unter einem Suchfeld gehören zum Suchfeld:** der kleinere
  Wert, an jeder Stelle gleich (Kennzeichnung `.suchtreffer` – „Weitere
  Lehrkräfte“ und stellvertretende Buchung; vorher 32 gegen 12 px).
- **Feld nach Feld: der kleinere Wert**, für jedes Feld gleich. Zwei
  Eingaben sind zwei Angaben innerhalb eines Abschnitts. Vorher standen
  10 px zwischen Labels, aber 19 px nach einer Eingabezeile – zufällig,
  weil Abstände in der Flex-Zeile nicht verschmolzen. Labels in der
  Eingabezeile tragen keinen eigenen Abstand mehr; den setzt die Regel an
  der Zeile.
- **„Anmelden“ mit 32 px bleibt:** Ein Knopf, der eine Handlung auslöst,
  steht abgesetzt – wie „Aktualisieren“.

## E15 — Wer als Schülerin oder Schüler selbst buchen darf

**Stand 09.10.2026, v0.9.65. Entscheidungen des Betreibers.**

**Anlass.** Ein Fünftklässler, der parallel zu seinen Eltern Termine belegt,
ist fachlich fragwürdig und kann Slots blockieren, ohne dass es jemand
merkt. WebUntis gibt die Volljährigkeit im `personType` nicht her (Schüler
sind 5, gleich welchen Alters); die Schule pflegt sie in Benutzergruppen.

**Anmelden und Buchen sind getrennt:**
- **Wer sich anmelden kann, entscheidet allein der Aktiv-Status in
  WebUntis.** sprechtag prüft das nicht noch einmal.
- **Wer als Schüler:in buchen darf, entscheidet die Benutzergruppe.**
  Wer nicht in einer zugelassenen Gruppe ist, wird angemeldet, sieht aber
  statt der Kacheln die Erklärung „Termine buchen die
  Erziehungsberechtigten“ – keine Fehlermeldung beim Anmelden, denn wer sie
  bekommt, weiß nicht, warum.

**Rolle vor Gruppe.** Die Gruppe wird nur bei der Rolle `schueler`
(personType 5) geprüft. Eltern tragen ebenfalls eine Gruppe („01_Eltern
Attest“); eine Prüfung allein über Gruppennamen träfe sie versehentlich.

> **Teilweise überholt (E17, 09.10.2026):** Die Seite heißt seit v0.9.72
> „Schülerliste“. Der Absatz bleibt als damaliger Stand stehen.

**Zugelassene Gruppen sind eine Einstellung der Verwaltung**, nicht fest
im Code („Dienstkonto & Schülerliste“ → „Volljährige Schülerinnen und
Schüler“). Heute sind es zwei Gruppen; ob eine dritte dazukommt, weiß
niemand. **Ist die Liste leer, kann keine Schülerin und kein Schüler selbst
buchen** (die Sperre schließt); die Seite sagt das.

**20 Zeichen.** WebUntis kürzt Gruppennamen auf 20 Zeichen, in
`profile/general` und im Empfängerfilter gleich. Verglichen wird deshalb
auf beiden Seiten nach dem Kürzen – wer „SuS über 18 mit Attest“ einträgt,
trifft „SuS über 18 mit Atte“. Gespeichert wird, was verglichen wird; die
Seite meldet, was gekürzt wurde, und nennt die Gruppe des eigenen Kontos.

**Quelle der Gruppe:** `/WebUntis/api/profile/general`
(`data.profile.userGroup`), gelesen bei der Anmeldung über die eigene
Sitzung, gehalten in der Sitzung, nicht in der Datenbank. Scheitert das
Lesen, gelingt die Anmeldung trotzdem; das Buchen bleibt für Schüler:innen
mit eigener Erklärung gesperrt („bitte erneut anmelden“).

**Kachel und Buchungsrecht** fragen dieselbe Funktion
(`bu_buchen_gesperrt()`), jeweils vor allem anderen.

**Nachtrag zu E15, 09.10.2026 (v0.9.68): Auswahlliste statt Eintippen.**
Die Verwaltungsseite holt die Gruppen über `/WebUntis/api/userrole/config`
(gemessen: nur die Verwaltung darf, Admin 200, sonst 403) und bietet sie als
Kästchen an – Gruppen mit Schülern zuerst, mit Anzahl; die übrigen bleiben
wählbar, eingeklappt dahinter. Gespeichert wird das `label`, also genau der
Text, der gegen `profile.userGroup` verglichen wird. Eintippen bleibt
Rückfall, wenn der Abruf scheitert (Grund steht da).

Gemessen ist eine Einschränkung: Bei **systemeigenen** Gruppen lieferte die
Liste „Admin“, die Anmeldung „Administration“ – der Vergleich träfe dort
nicht. Bei schuleigenen Gruppen (die Volljährigen-Gruppen) ist der Abgleich
**nicht gemessen** (ein Schülerkonto darf die Liste nicht abrufen); nach
Augenschein stimmen die gekürzten Fassungen überein. Deshalb zeigt die Seite
bei den gewählten Gruppen, was verglichen wird, und warnt bei systemeigenen
Gruppen und bei Gruppen, die nicht in der Liste stehen.

## E16 — Eltern-Mitteilungen über recipientOption PARENTS; der Namensabgleich ist ein Zustand auf Zeit

**Stand 09.10.2026. Entscheidung des Betreibers; noch nicht gebaut.**

**Der Namensabgleich (`mit_eltern_ids_ermitteln()`) ist ein Zustand, der
behoben werden soll** – keine Ausnahme. Er weicht von FALLSTRICKE 6 ab („Über
den Namen wird nie zugeordnet“), weil es lange keinen anderen Weg von der
Kind-Kennung zu den Eltern gab: Schild führt keine Eltern-Kennung (Auskunft
Betreiber, Befund Abschnitt 12). Diesen Weg gibt es jetzt: `POST
/v2/messages` mit `recipientOption: "PARENTS"` und der Kind-Kennung erreicht
die Erziehungsberechtigten, ohne Suche, ohne Namen, ohne Kreiswechsel
(gemessen 09.10.2026, Befund Abschnitt 16).

**Bedingung, unter der der Zustand endet:** Der Mitteilungsversand an Eltern
ist auf `/v2/messages` mit PARENTS umgestellt. Dann entfällt
`mit_eltern_ids_ermitteln()` samt Namensabgleich. **Kein Eintrag in
`bestand-ausnahmen.md`** (Auskunft koordination: die ist für
Vendoring-Abweichungen).

**Zuschnitt der Umstellung:**
- **An Eltern** – Bestätigungen, Absagen, Einladungen, alles, was heute über
  `mit_eltern_ids_ermitteln()` läuft – wechselt auf `/v2/messages` mit
  PARENTS und der Kind-Kennung.
- **Die Erinnerungen bleiben** auf `/v2/messages/users` mit
  `recipientUserIds`: Sie gehen an eine Liste, nicht an die Eltern eines
  Kindes. `/v2/messages/users` kennt `recipientOption` nicht (gemessen:
  Status 500).

**Bis zur Umstellung:** Der Namensweg soll bei mehr als einem exakten Treffer
anhalten und melden statt zu senden (geschlossen scheitern statt raten). Ob
das noch gebaut wird, hängt davon ab, wie bald die Umstellung kommt – das
wird mit dem Zuschnitt entschieden.

> **Teilweise überholt (Nachtrag 09.10.2026, unten):** Der folgende Absatz
> setzt voraus, Bestätigungen und Absagen liefen über das Dienstkonto. Das war
> aus dem Ablauf **geschlossen, nicht am Code gelesen**, und stimmt so nicht.
> Der Absatz bleibt als damaliger Stand stehen.

**Offen vor dem Bau:** ob die Umstellung in Zug 4 aufgeht oder ein eigener Zug
wird; die Architekturfrage zu `mit_rest_aus_sitzung()`; ob PARENTS auch über
die Sitzung des Dienstkontos trägt; die Antwort bei einem Kind ohne
hinterlegte Eltern.

**Nachtrag zu E16, 09.10.2026 – Richtigstellung zum Versandweg:**
Der Versand an Eltern läuft heute **zuerst über die Sitzung der handelnden
Person**, das Dienstkonto nur als Rückfall, wenn keine nutzbare Sitzung da ist
(`mit_einreihen_und_senden()`); die Bestätigung nach einer Elternbuchung
bekommt gar keine Dienstkonto-Zugangsdaten. Die gegenteilige Angabe in Befund
16/17 und oben war geschlossen, nicht gelesen (Befund Abschnitt 18). Zuschnitt
und Endbedingung von E16 bleiben; die Frage „PARENTS über das Dienstkonto“
betrifft nur den Rückfall. Ob das Dienstkonto ganz entfallen kann, ist eigene
Lage: `docs/BESTAND-DIENSTKONTO-2026-10-09.md`.

---

## E17 — Das Dienstkonto ist abgeschafft; eine abgelaufene Sitzung wird gesagt, nicht überbrückt

**Eingetragen:** 09.10.2026 · **wirksam seit:** v0.9.72 · **betrifft:** E1,
E9, E16 · **benötigt:** `sql/21_dienstkonto_entfernen.sql`

> **Teilweise beantwortet (10.10.2026, v0.9.79):** Stelle 1, die Bestätigung
> nach einer Elternbuchung, ist gemessen: Elternkonten dürfen nicht senden
> (HTTP 403). Die Bestätigung entfällt. Siehe Nachtrag am Ende dieses
> Eintrags.

**Entschieden (Betreiber):** Es gibt kein Dienstkonto mehr. Jede
WebUntis-Aktion läuft über die Sitzung der handelnden Person. Die
Verwaltungsseite, die Verschlüsselung und die hinterlegten Zugangsdaten sind
entfernt. Gespeichert wird kein WebUntis-Passwort mehr. Nur der Schüler-Sync
nimmt bis Zug 4 eingetippte Zugangsdaten, für den einen Abgleich.

**Warum (Betreiber, über die Technik hinaus):** Der Rückfall erkauft
Bequemlichkeit mit hinterlegten Kontodaten, und er macht still rückgängig,
was v0.9.48 erreicht hat (E1). Springt das Dienstkonto ein, kommt eine Absage
bei den Eltern von einem anonymen Verwaltungskonto statt von der Lehrkraft,
die abgesagt hat. Niemand merkt es, weil die Nachricht ankommt. Eine Sitzung
läuft ab, das ist richtig so. Die Frage ist nur, ob das System es sagt oder
verdeckt.

**Grundlage, gemessen 09.10.2026 (Betreiber):**
- Erinnerungen über die eigene Sitzung: Admin **und** Lehrkraft lösen die
  Testliste (QUICK, 2 Personen) über `CUSTOM/filter` vollständig auf und
  senden über `/v2/messages/users` (`numberOfRecipients` 2). Beide
  Nachrichten sind angekommen. Die fachliche Frage aus v0.9.71 (wer die
  Erinnerungen auslöst) erledigt sich: Die Verwaltung kann es weiter.
- PARENTS über die Lehrkraft-Sitzung: 4 Empfänger, angekommen (E16).
- Die Bestandsaufnahme (`docs/BESTAND-DIENSTKONTO-2026-10-09.md`): Das
  Dienstkonto greift nirgends, während niemand angemeldet ist.

**Die abgelaufene Sitzung – die Form (Betreiber):**
- Die Mitteilung bleibt in der Warteschlange stehen, der Text geht nicht
  verloren.
- **Am Ort der Handlung** erscheint ein Kasten, etwa: „Die Absage ist
  gespeichert, aber noch NICHT verschickt: Ihre WebUntis-Anmeldung ist
  abgelaufen.“ Darin stehen Benutzername (vorausgefüllt) und Passwort, und der
  Knopf „Anmelden und senden“ meldet neu an und schickt **dieselben**
  Mitteilungen hinaus. Bei der stellvertretenden Buchung heißt er „Anmelden
  und buchen“, dort wird ohne Sitzung nicht gebucht (unten).
- **Nach jeder Anmeldung** steht oben „N Mitteilungen sind gespeichert, aber
  noch nicht verschickt“ mit „Jetzt senden“ und „Ansehen“. Das fängt den
  Fall, dass jemand Stunden später wiederkommt, was bei einer Absage am
  meisten zählt. Eine Lehrkraft sieht ihre eigenen, die Verwaltung alle. Es
  zählen nur Sprechtage ab heute. In der Ansicht „Mitteilungen“ steht der
  Hinweis nicht, dort ist derselbe Stand ein eigener Abschnitt.
- Erinnerungen: Kasten zum Anmelden, aber **kein** automatischer Versand
  danach. Ein Versand an alle bleibt eine bewusste zweite Handlung.

**Drei Ursachen, getrennt gemeldet (behebt E9 „Daneben gefunden“):**
`wu_sitzung()` im Adapter ist der einzige Zugang. Sie meldet:
- **abgelaufen**: keine Sitzung festgehalten, oder kein Token und die
  Nachprobe zeigt die Anmeldeseite bzw. einen Status unter 500. Nur hier
  hilft das Neuanmelden, nur hier kommt der Kasten.
- **nicht erreichbar**: Status 0, ab 500, flüchtig, oder eine `Exception`.
- **kaputt**: ein `Error`, also ein Programmierfehler. Die Meldung verweist an
  die Administration.

`tokenHolen()` (vendort aus webuntis-client-php) sagt nur ja oder nein. Die
Nachprobe liest deshalb den Status desselben Abrufs, wie
`messung_token_probe()` seit v0.9.54. „ab 500 = nicht erreichbar“ ist
**abgeleitet, nicht gemessen**.

**Die Stellen der Bestandsaufnahme danach:**

| # | Stelle | jetzt |
|---|---|---|
| 1 | Bestätigung nach Elternbuchung | Sitzung der Eltern, wie bisher; an das buchende Konto |
| 2 | stellvertretende Buchung | Elternkonto über die Namenssuche **mit der Sitzung der Lehrkraft**; Bestätigung über **PARENTS** an alle |
| 3 | Absage | Sitzung der absagenden Person; an das gebuchte Konto |
| 4 | Einladung | **PARENTS**, keine Elternkonten-Suche mehr |
| 5 | Ausfall | Sitzung der Verwaltung; alle Absagen eingereiht, ein Versand |
| 6 | Sammelversand | Sitzung der handelnden Person, keine Zugangsdaten |
| 7 | Elternkonten-Ermittlung | nur noch für die Zuordnung der stellvertretenden Buchung |
| 8 | Kacheln (Stundenplan des Kindes) | Sitzung der angemeldeten Person |
| 9 | Lehrkräfte-Ermittlung von Hand | Sitzung (Betreiber), ohne Zugangsdaten |
| 10 | Erinnerungen | Sitzung der Verwaltung |
| 11 | Schüler-Sync | eingetippte Zugangsdaten bis Zug 4 (Betreiber) |
| 12 | Statusanzeige | entfernt |
| 13 | Messung über das Dienstkonto | entfernt; `sitzung: dienstkonto` wird abgelehnt |

> **Teilweise überholt (Nachtrag zu E17, unten):** Absagen gehen seit v0.9.73
> über PARENTS an alle Erziehungsberechtigten. Der Absatz bleibt als damaliger
> Stand stehen.

**PARENTS – Zuschnitt nach E16:** E16 nennt „alles, was heute über
`mit_eltern_ids_ermitteln()` läuft“: Einladung und die Bestätigung der
stellvertretenden Buchung. Absage und Bestätigung nach Elternbuchung liefen
nie darüber. Sie kennen das Konto aus der Buchung und gehen weiter an dieses
eine Konto. Das ist **meine Lesart des Zuschnitts**; falls „Absagen“ in E16
auch die an alle Erziehungsberechtigten meinte, ist das ein eigener Schritt.

**Stellvertretende Buchung (Betreiber):** Der Termin braucht ein Elternkonto
(Meine Termine, Kalender, Kollisionsprüfung Fall A, spätere Absage). Es kommt
aus der Namenssuche über die Sitzung der Lehrkraft, mit Rückfall auf frühere
Buchungen. Ist die Sitzung nicht nutzbar, wird **nicht** gebucht. Die
Mehrdeutigkeits-Absicherung bleibt für diese eine Stelle offen.
**Berichtigung:** In der Rückfrage stand, die Namenssuche über die
Lehrkraft-Sitzung sei gemessen. Das stimmt nicht. Der Namensweg der Messung
v0.9.69 lief damals über das Dienstkonto. Seit v0.9.72 misst
`POST /api/messung/parents` die Suche über die eigene Sitzung
(`namensweg.quelle` = `webuntis`).

**Zugehörigkeit einer Mitteilung:** Die Warteschlange trägt jetzt die
Lehrkraft (`lehrer_id`). Eine Lehrkraft kann so ihre Absage nachsenden,
obwohl die Buchung gelöscht ist; über die Buchungen ging das vorher nicht.
Die Bestätigung nach einer Elternbuchung nennt alle Termine der Eltern und
trägt deshalb keine Lehrkraft. Dafür gilt die bisherige Regel über die
Buchungen, und im Hinweis nach der Anmeldung steht sie bei der Verwaltung.

**Nicht gemessen:**
- die Namenssuche über die Lehrkraft-Sitzung (oben);
- der Stundenplan beliebiger Kinder über die Sitzung der Verwaltung bzw.
  volljähriger Schüler (Stelle 8). Scheitert es, gibt es keine Kacheln, und
  die Seite sagt warum;
- ob Eltern über ihre Sitzung an sich selbst senden dürfen (Stelle 1);
- der Kasten auf dem Gerät. Die Breitenmessung (WebKit, 320 px) zeigt nur
  den Hinweis, nicht den Kasten. Das ist ein Abnahmepunkt.

**Beim Ausrollen:** `sql/21_dienstkonto_entfernen.sql` **vor** dem Code
einspielen. Die neuen Spalten sind für v0.9.71 unschädlich, v0.9.72 braucht
sie. Danach `dienstkonto_schluessel` aus `backend/config.php` auf dem Server
entfernen; die Datei ist nicht versioniert.

**Nachtrag zu E17, 09.10.2026 – Absagen an alle Erziehungsberechtigten
(Betreiber, v0.9.73):** Absagen gehen über PARENTS an **alle**
Erziehungsberechtigten des Kindes, nicht nur an das buchende Konto. Das gilt
für Stelle 3 (Absage) und Stelle 5 (Krankheitsausfall, je Termin eine
Mitteilung).
**Warum:** Oft wird nur ein Elternkonto aktiv genutzt; eine Absage an das
buchende Konto erreicht dann womöglich niemanden, der noch hinschaut. Teilen
sich die Eltern die Termine, sollen beide es erfahren.
**Bleibt:** Die Bestätigung nach einer Elternbuchung geht an das buchende
Konto. Nur eine Person hat gebucht, und sie hat es gerade selbst getan.
**Gebaut:** Eine Stelle entscheidet, `mit_absage_art()`. Mit Kind-Kennung
geht die Absage über PARENTS. Ohne Kind-Kennung geht sie an das gebuchte
Konto, damit es nie eine Absage ohne Empfänger gibt; nach dem Schema hat jede
Buchung eine Kennung.

---

**Nachtrag 10.10.2026 — Stelle 1 gemessen; die Bestätigung nach einer Elternbuchung entfällt (v0.9.79):**
- **Gemessen (Betreiber, Betrieb, v0.9.78):** Nach einer Elternbuchung
  blieb die Bestätigung offen. Anlass `bestaetigung`, Empfängerart
  `konto`, Grund „Keine Berechtigung zum Versenden von Mitteilungen (HTTP
  403)“. Damit ist **Stelle 1 der Bestandsaufnahme gemessen**
  (`docs/BESTAND-DIENSTKONTO-2026-10-09.md`, dort „nicht gemessen“).
  Elternkonten dürfen über die eigene Sitzung nicht senden, auch nicht an
  sich selbst.
- **Nicht derselbe Fall wie der PARENTS-403 vom Admin-Konto:** Hier geht
  der Weg an ein einzelnes Konto. Es scheitert nicht an der Empfängerart,
  sondern daran, dass das Konto gar nicht senden darf.
- **Entschieden (Betreiber, am 09.10. angelegt, jetzt belegt):** Die
  Bestätigung nach einer Elternbuchung entfällt. „Ich weiß ja, dass ich
  den Termin gebucht habe.“ Die Eltern sehen den Termin in „Meine
  Termine“ und im Kalender-Abo. Sie kann auch nicht funktionieren,
  solange Eltern selbst buchen: Es bliebe nur ein Konto mit Senderecht,
  also das abgeschaffte Dienstkonto. Dasselbe gilt für volljährige
  Schüler; sie buchen über denselben Weg.
- **Bleibt:** Die stellvertretende Buchung durch eine Lehrkraft behält
  ihre Bestätigung. Dort handelt jemand mit Senderecht, und die Eltern
  haben nicht selbst gebucht.
- **Weitere Stellen mit Eltern-Sitzung:** Nach Zug 4 am Code nachgesehen,
  gibt es keine. Jede andere Versandstelle liegt hinter einer Prüfung auf
  Lehrkraft oder Verwaltung. Die Eltern-Absage sendet nicht. Seit v0.9.79
  führt `tests/run_versandstellen.php` ein Register aller Versandstellen;
  eine neue macht die Suite rot, bis eingeordnet ist, wer dort angemeldet
  ist.
- **Die offene Mitteilung id 50** verwirft der Betreiber von Hand; sie kann
  nicht hinausgehen. Ältere offene Bestätigungen räumte bisher die nächste
  Elternbuchung desselben Kontos ab. Das entfällt mit; was noch offen
  steht, bleibt in der Mitteilungsansicht, bis es verworfen wird.
- **Möglichkeit für „Befund A“ (doppelter Versand):** Dort blieb eine
  Mitteilung offen, obwohl sie ankam. War es dieselbe Konstellation, kam
  dort womöglich nichts an, und der Empfänger sah eine **andere**
  Mitteilung. Das ist nachträglich nicht klärbar. „Befund A“ liegt in
  diesem Repo nicht vor; der Vermerk steht deshalb hier und muss dorthin
  übertragen werden, wo Befund A geführt wird.


## E18 — Der Einladungsstatus wird aus den Buchungen abgeleitet, nicht gespeichert

**Eingetragen:** 09.10.2026 · **noch nicht gebaut** · **Befund:**
`docs/BEFUND-2026-10-09-einladung-nach-absage.md`

**Lage:** `einladungen.erledigt` wird beim Buchen gesetzt und nie
zurückgesetzt. Nach einer Absage, und beim Krankheitsausfall für alle
Einladungen einer Lehrkraft auf einmal, steht weiter „Termin gebucht“,
obwohl der Slot frei ist.

**Entschieden (Betreiber):** **Ableiten.** „Termin gebucht“, solange eine
Buchung desselben Kindes bei derselben Lehrkraft am selben Sprechtag
besteht, sonst „offen“. Kein eigener Status „Termin abgesagt“.

**Warum:** Ableiten behebt die Art des Fehlers, nicht den Einzelfall. Ein
gespeicherter Zustand, der von seiner Quelle abweichen kann, wird irgendwann
abweichen. Mitführen bräuchte zwei neue Schreibstellen (Absage, Ausfall).
Bei der nächsten Stelle, die Buchungen löscht, wären es drei, und eine davon
vergisst jemand. Dazu braucht Ableiten keine Migration und keine
Bereinigung: Die schon falschen Einträge stimmen von selbst.

**Beim Bauen zu entscheiden:** ob `einladungen.erledigt` entfällt (Migration)
oder stehen bleibt. Ein Feld, das niemand mehr liest, ist derselbe stille
Zustand.

**Zeitpunkt:** nach dem laufenden Strang (Abnahme der abgelaufenen Sitzung).

**Nachtrag 11.10.2026 — gebaut mit v0.9.83; Abweichung: ein eigener Status „Termin abgesagt“ (Betreiber, G1):**
Der Status einer Einladung wird abgeleitet, wie entschieden, aber in drei
Stufen statt zwei: **gebucht** vor **abgesagt** vor **offen**.
- „gebucht“, solange das Kind bei dieser Lehrkraft an diesem Sprechtag
  einen Termin hat, auch nach einer Absage neu gebucht;
- „abgesagt“, wenn die Schule ihm dort einen Termin abgesagt hat (Ablage
  aus E19);
- sonst „offen“, auch nach einer Stornierung durch die Familie, denn die
  legt nichts ab.

**Warum abweichend vom „kein eigener Status“ oben:** „Offen“ nach einer
Absage führt in die falsche Richtung. Die Lehrkraft liest es als
„die Familie hat sich nicht gemeldet“, tatsächlich hat die Schule den Termin
abgesagt, und die Familie wartet womöglich auf einen neuen. Das „kein
eigener Status“ war aus der Anzeige gedacht, nicht aus dem Handeln.

Ableiten bleibt Ableiten: Gespeichert wird kein Status. Die Ablage ist die
zweite Quelle, aus der abgeleitet wird; dieselbe Bedingung („dasselbe Kind
bei derselben Lehrkraft am selben Sprechtag“) steht einmal
(`bu_selber_vorgang_sql`) und gilt für Status und „neu gebucht“.

**`einladungen.erledigt` entfällt (G5)**, mit `sql/25`, NACH dem Deploy:
Der alte Code liest die Spalte, der neue fasst sie nicht mehr an. Eine
Engstelle hält fest, dass kein Code unter `backend/` und kein Skript unter
`frontend/` sie liest oder schreibt. Die Eltern sehen weiter keinen Status
(nicht in diesem Zug).

---

## E19 — Abgesagte Termine verschwinden nicht spurlos (zusammen mit E18 zu bauen)

**Eingetragen:** 09.10.2026 · **noch nicht gebaut** · **betrifft:** E18

**Lage:** Ein Krankheitsausfall löscht alle Buchungen der Lehrkraft. Danach
sieht sie nicht mehr, wer bei ihr gebucht hatte, und kann keine Gespräche
nachholen und niemanden ansprechen.

**Entschieden (Betreiber):** Bei einem Ausfall werden Buchungen nicht mehr
gelöscht, sondern als abgesagt festgehalten. Die Lehrkraft sieht sie in
ihrer Übersicht. Aufbewahrung 2 bis 4 Wochen, damit sie neue Termine
organisieren kann. Der Slot muss **sofort** wieder frei sein.

**Warum zusammen mit E18:** Beide Fehler entstehen daraus, dass eine
Löschung Spuren hinterlässt, die niemand nachzieht. Beide löst der Ausfall
gleichzeitig aus. Gedanke des Betreibers, keine Vorgabe: Die Ableitung des
Einladungsstatus könnte dann „gebucht / abgesagt / offen“ unterscheiden.

**Vorbefund (am Code gelesen, 09.10.2026):**
- `buchungen` trägt `UNIQUE KEY uniq_slot (sprechtag_id, lehrer_id,
  slot_beginn)`. Eine als abgesagt **markierte Zeile in `buchungen`**
  blockierte den Slot. Das widerspricht der Bedingung „sofort frei“, es sei
  denn, der Schlüssel wird umgebaut.
- `buchungen` wird an **22 Stellen** gelesen, in `buchungen.php`,
  `index.php`, `kalender.php` und `mitteilungen.php`. Eine Markierung in
  derselben Tabelle verlangte an jeder Stelle einen Filter „nur aktive“. Das
  ist dieselbe Art Fehler wie E18: die Stelle, die jemand vergisst.
- Das Archivieren eines Sprechtags löscht `buchungen`, `einladungen`, den
  Cache und `mitteilungen` ausdrücklich, Zeile für Zeile. Jede neue Ablage
  muss dort dazu, sonst bleiben Personendaten über das Archivieren hinaus
  liegen.

> **Teilweise beantwortet (Nachtrag 09.10.2026, unten):** Das Aufräumen
> übernimmt das Archivieren, eine eigene Frist gibt es nicht.

**Offen, vor dem Bau zu klären:** Markierung in `buchungen` oder eigene
Ablage; nur Ausfall oder auch die einzelne Absage; sehen die Eltern den
abgesagten Termin; wer räumt auf (Archivieren oder eigene Frist).

**Nachtrag 09.10.2026 — Aufräumen über das Archivieren (Betreiber):**
Abgesagte Termine werden beim Archivieren des Sprechtags gelöscht, eine
eigene Frist gibt es nicht. Begründung des Betreibers: Ein zweiter
Löschmechanismus wäre Aufwand für wenig Gewinn, und an der bestehenden
Lage ändert sich nichts, denn auch Buchungen stehen heute bis zum
Archivieren. Damit gilt die Aufbewahrung von „2 bis 4 Wochen“ oben nicht
als Frist. Sie ist die Zeit, die die Lehrkraft mindestens braucht. Wie
lange die Termine tatsächlich stehen, hängt davon ab, wann die Schule
archiviert, und eine automatische Frist gibt es dafür nicht.

Was das Archivieren löscht, steht seit v0.9.74 in
`tests/run_archivieren.php`, am ausgeführten Code geprüft. Jede Tabelle
mit `sprechtag_id` muss dort geleert werden oder ausdrücklich als Struktur
gelten. Die Ablage für abgesagte Termine macht die Suite deshalb rot, bis
sie mitgelöscht wird; Mutation AR5 belegt das mit einer nachgestellten
Ablage. Der Datenschutz-Absatz der Hilfe (`datenschutzAbsaetze()`) nennt
„die Termine“ allgemein und trägt den Bau ohne Änderung. Beim Bau ist nur
zu entscheiden, ob dort „auch abgesagte“ ausdrücklich dazugeschrieben wird.

**Nachtrag 11.10.2026 — gebaut mit v0.9.83 (Betreiber, G2–G7):**
- **Eigene Ablage, nicht eine Markierung in `buchungen`** (`sql/24`,
  `abgesagte_termine`). Der Slot ist sofort frei, und die 22 Lesestellen
  von `buchungen` brauchen keinen Filter.
- **Abgelegt wird nur, was die Schule absagt (G2):** Absage durch
  Lehrkraft oder Verwaltung (`absage`) und Krankheitsausfall (`ausfall`).
  Storniert die Familie selbst, wird wie bisher nur gelöscht. Buchungen
  entfernt eine Funktion mit dem Anlass als Parameter,
  `bu_buchungen_entfernen()`; Ablegen und Löschen stehen in einer
  Transaktion. Eine Engstelle hält fest, dass außer ihr nur das
  Archivieren Buchungen löscht.
- **Felder (G3):** Sprechtag, Lehrkraft, Uhrzeit, Kennung, Name und Klasse
  des Kindes, Hinweis der Eltern, Anlass, Zeitpunkt. Keine Kennung der
  Eltern und kein Absagetext; der Text steht in `mitteilungen`.
- **Ansicht (G4):** Abschnitt „Abgesagte Termine“ unter dem Raster, in der
  eigenen Ansicht der Lehrkraft und in der der Verwaltung, ohne Knöpfe.
  „neu gebucht um HH:MM“ ist abgeleitet. Er lädt auch bei Teilnahme 0,
  also gerade nach einem Ausfall. Ohne Einträge zeigt die Ansicht keinen
  Abschnitt. Auf dem Telefon wird die Tabelle zur Karte (E13-Nachtrag).
- **Archivieren:** Es leert die Ablage mit (Nachtrag oben).
- **Reihenfolge (G6):** `sql/24` VOR dem Deploy, denn der neue Code legt
  bei jeder Absage ab, und ohne Tabelle scheiterte sie. `sql/25` NACH dem
  Deploy, denn der alte Code liest `erledigt`.
- **Hilfe (G7):** Der Datenschutz-Absatz nennt „auch abgesagte Termine“.
  Die Hilfe zum Krankheitsausfall sagt, dass die Lehrkraft sie weiter
  sieht.
- **Nicht in diesem Zug:** Die Eltern sehen abgesagte Termine nicht.

**Nachtrag 11.10.2026 — E18 und E19 im Betrieb abgenommen (Betreiber):**
Nach einem Ausfall zeigt der Abschnitt „Abgesagte Termine“ beide Termine mit
Anlass, Zeitpunkt, Elternhinweis und „nicht neu gebucht“. Die Einladung
steht auf „Termin abgesagt“. Die Ansicht lädt bei Teilnahme 0, und auf dem
Telefon erscheint der Abschnitt als Karte. Kein Handlungsbedarf.

---

## E20 — Zug 4: keine Schülerliste mehr, Namen nur am Vorgang

**Eingetragen:** 09.10.2026 · **wirksam:** schrittweise ab v0.9.75 ·
**betrifft:** E8 (setzt es um), E17 (Kasten), E19 (Archivieren)

Grundlage: E8 und `docs/BEFUND-2026-10-07-pageconfig-schuelerliste.md`.
Entschieden vom Betreiber auf die Richtungsfragen vor dem Bau:

- **A — keine lokale Schülerliste mehr.** Die Einladungsauswahl und die
  Kind-Suche beim stellvertretenden Buchen lesen `pageconfig?type=5` je
  Aufruf über die Sitzung der Lehrkraft. Gespeichert wird ein Kindname nur
  dort, wo eine konkrete Buchung, Einladung oder Mitteilung ihn braucht,
  und das Archivieren löscht ihn mit (E19-Nachtrag,
  `tests/run_archivieren.php`). Es gibt keinen Sync und keine 1314 Namen
  auf Vorrat, nichts veraltet zwischen zwei Abgleichen. Die Tabelle
  `schueler` fällt mit Schritt 4, nach der Messung. Bis dahin bleiben die
  Altdatensätze als Vergleichsmaßstab stehen.
  Die ursprüngliche Frage („löschen oder bis zum nächsten Sync stehen
  lassen“) setzte eine bleibende Liste voraus. Mit A entfällt sie.
- **B — die Klasse wird festgehalten.** Sie kommt aus denselben zwei
  Abrufen, die die Klassenleitung (E10, Zug 3) einmal je Anmeldung macht:
  `pageconfig` liefert `klasseId`, `timetable/filter` den Namen. Ein
  zusätzlicher Abruf entsteht nicht. Tischvorlage und Kalender der
  Lehrkraft brauchen sie am Sprechtag.
- **C — Einladen ohne Sitzung:** Es wird nicht eingeladen, sondern der
  Kasten „Anmelden und einladen“ gezeigt, dasselbe Muster wie überall
  (E17). Ungeprüft und ohne Namen zu speichern wäre die stille Lücke aus
  dem Befund, Abschnitt 3.
- **D — die freie Eingabe einer Schüler-ID entfällt.** Sie war der Ersatz
  für eine leere Liste.
- **E — Kinder ohne Klasse** erscheinen weder in der Einladungsauswahl
  noch in der Kind-Suche des stellvertretenden Buchens. Eine Quelle, eine
  Regel.
- Ohne Rückfrage, ohne Einspruch: Die Admin-Seite behält nur die
  Benutzergruppen (E15). Der Datenschutz-Absatz der Hilfe verliert den
  Satz zur Schülerliste. `docs/SCHUELERLISTE.md` bekommt einen
  Überholt-Vermerk. `mit_eltern_ids_ermitteln()` nimmt den Kindnamen aus
  `pageconfig`.

**Zuerst gemessen, nicht angenommen:** Gemessen waren die Feldnamen, nicht
ihr Inhalt. Dass `longName` der Nachname ist, ist eine Vermutung, die von
`getStudents` übertragen wurde. Den Klassennamen liefert `pageconfig` gar
nicht. Schritt 1 (v0.9.75) misst deshalb, welches Feld was trägt. Erst
danach wird gebaut.

**Reihenfolge**, jeder Schritt einzeln geprüft und ausgeliefert:
1. Messteil. Der Betreiber misst.
2. Kindname und Klasse beim Buchen, Einladen und Einreihen festhalten; die
   Anzeigen lesen sie, die Kalender zuerst.
3. Einladungsauswahl und Kind-Suche aus `pageconfig`.
4. Abbau von CSV, Sync, Austrittsdatum, Admin-Teil und Suiten. Migration
   mit Übernahme der Namen vorhandener Buchungen und Wegfall der Tabelle.

**Nachtrag 09.10.2026 — Schritt 2 (v0.9.76): woher der Name beim Buchen kommt (Betreiber):**
- **Elternbuchung:** Name und Klasse der **eigenen** Kinder werden **bei
  der Anmeldung** einmal ermittelt, solange die WebUntis-Sitzung frisch
  ist (pageconfig und timetable/filter, `wu_kind_daten_login()`). Sie
  stehen in der PHP-Sitzung, solange die Anmeldung gilt, und gebucht wird
  ohne WebUntis. Die Klassenleitung (E10) wird aus derselben Antwort
  vorbelegt, ein zweiter Abruf entsteht nicht. Verworfen wurden ein
  Rückfall auf `students[].displayName`: zwei Quellen, und die Form ist
  nicht gemessen. Ebenso verworfen: eine Sitzungspflicht beim Buchen, an
  die Eltern nach 30 Minuten stießen.
- **Volljährige Schüler:** Der Name kommt aus der Anmeldung
  (`person.displayName`), die Klasse aus pageconfig, falls vorhanden. **Nicht
  gemessen** ist, ob pageconfig in einer Schülersitzung den eigenen
  Eintrag führt; das vorhandene Testkonto hat keine Klasse. Die Form von
  `displayName` ist ebenfalls nicht gemessen. Bei Schülern kann der Name
  deshalb anders geschrieben sein als „Nachname, Vorname“.
- **Stellvertretend und Einladen:** Name und Klasse kommen über die Sitzung
  der Lehrkraft, je Vorgang einmal (`kd_ermitteln()`). Die Klassenliste
  wird bewusst **nicht** in der Sitzung zwischengespeichert, denn das wären
  wieder 1314 Namen auf Vorrat (A). Der Preis: zwei Abrufe je Einladung.
- **Fehlen die Kinddaten beim Buchen, wird nachgeholt oder nicht
  gebucht** (Rückfrage des Betreibers vor der Auslieferung, entschieden
  beim Bauen auf seinen Auftrag „du entscheidest“).
  - **Wann sie fehlen:** wenn die Anmeldung vor v0.9.76 lag oder WebUntis
    sie beim Login nicht hergab.
  - **Was geschieht:** `wu_kind_daten_buchung()` holt sie dann über die
    WebUntis-Sitzung nach und ergänzt sie in der Sitzung.
  - **Wenn es nicht geht:** Ohne Sitzung kommt der Kasten „Anmelden und
    buchen“, sonst 502. Ein leerer Name wird nie still geschrieben.
  - **Was offen bleibt:** Die Klasse darf leer sein, weil es Kinder ohne
    Klasse gibt. Bei volljährigen Schülern bleibt die Klasse deshalb
    still leer, wenn pageconfig beim Login scheiterte. Ein solcher Fall
    ist von einem Schüler ohne Klasse nicht zu unterscheiden, solange
    ungemessen ist, ob pageconfig die eigene Person führt.

**Nachtrag 10.10.2026 — Schritt 2 abgenommen (Betreiber, Betrieb):** Eine
neue Buchung trägt Name und Klasse. Ältere haben die Klasse leer, wie die
Migration es vorsah. Das Kalender-Abo und die Tischvorlage zeigen Termine
mit Name und Klasse richtig.

**Nachtrag 10.10.2026 — Schritt 3 (v0.9.81): Einladungsauswahl und
Kind-Suche aus pageconfig.** Entschieden vom Betreiber auf die
Richtungsfragen vor dem Bau:
- **R1 — ohne Suchbegriff wird nichts geladen.** Kein WebUntis-Abruf und
  keine Namen an den Browser, nur die Aufforderung, Name oder Klasse
  einzugeben. Begründung: 1200 Namen von Minderjährigen an den Browser,
  nur damit jemand vielleicht sucht, sind nicht zu rechtfertigen. Die
  Suche nach einer Klasse liefert die ganze Klasse.
- **R2 — die Suchregel bleibt:** Teilstring ohne Groß/Klein in Nachname,
  Vorname und Klasse. Höchstens 60 Treffer, denn eine ganze Klasse muss
  hineinpassen. Eine genauere Regel (Klasse nur exakt) wäre geraten, weil
  die Klassennamen bewusst nicht ausgegeben sind.
- **R3 — gesucht wird über Knopf und Eingabetaste**, nicht je
  Tastendruck. Jede Suche kostet zwei WebUntis-Abrufe, und FALLSTRICKE
  belegt, wohin ein Dutzend Abrufe in wenigen Sekunden führt. Länger zu
  warten verschiebt das nur.
- **R4 — der Server lehnt Kinder ohne Klasse auch beim stellvertretenden
  Buchen ab**, mit demselben Satz wie beim Einladen. Eine Regel, die nur
  in der Oberfläche steht, ist keine. Zwei Stellen mit einer Antwort auf
  dieselbe Frage haben die Einladungs-Kachel gekostet. Umgesetzt als eine
  Funktion für beide Vorgänge (`kd_vorgang_pruefen()`).
- **Folge von R4, über ihren Wortlaut hinaus:** Ist die Klassenliste beim
  stellvertretenden Buchen nicht lesbar, wird nicht mehr gebucht (502).
  Bisher fand die Buchung das Elternkonto dann über frühere Buchungen
  desselben Kindes und schrieb einen leeren Namen. Das schließt denselben
  stillen Weg zu einem leeren Namen wie beim Buchen in Schritt 2.
  Bestätigt vom Betreiber.
- **Eine nicht erreichbare Liste erscheint nie still leer.** Sie zeigt
  den Grund. Ohne Sitzung kommt der Kasten „Anmelden und suchen“, danach
  läuft dieselbe Suche.
- **Ohne Rückfrage, ohne Einspruch:** Der Satz „Steht das Kind in der
  Schülerliste?“ ist aus der Meldung beim stellvertretenden Buchen fort.
  Das Feld „Schüler-ID“ der Sondierung bleibt (Diagnosewerkzeug). Route
  und Admin-Seite der alten Liste bleiben bis Schritt 4. Ihre Texte und
  der Datenschutz-Absatz sagen jetzt, dass die Auswahl sie nicht mehr
  benutzt.
- **Ungemessen:** ob das echte Admin-Konto (personType 16) pageconfig
  lesen darf. Nach E23 verschmerzbar; scheitert es, nennt die Liste den
  Grund.


**Nachtrag 10.10.2026 — Schritt 4 (v0.9.82): die alte Schülerliste fällt.**
Entschieden vom Betreiber auf die Richtungsfragen vor dem Bau:
- **F1 — erst Deploy, dann Migration.** Der Code bis v0.9.81 liest die
  Tabelle (Admin-Seite, Abgleich, CSV, beide Messrouten); vorher entfernt,
  liefen diese Wege in einen 500. v0.9.82 liest und schreibt sie nicht
  mehr, also kann sie danach fallen.
- **F2 — zwei SQL-Dateien, ein Mensch als Wache, keine Wache im SQL.**
  `sql/23_pruefung.sql` liest nur und zählt je Tabelle `leer_mit_kind`,
  `noch_fuellbar`, `NICHT_FUELLBAR` und `ohne_kind`. Die dritte Zahl —
  Namen, die auch die alte Tabelle nicht kennt — ist die, auf die es
  ankommt, und steht deshalb groß im Spaltennamen und als Satz in der
  letzten Zeile („OK …“ oder „ANSEHEN – n Vorgänge …“). Diese Namen sind
  schon heute verloren; das Entfernen ändert daran nichts, aber danach gibt
  es keine Quelle mehr. `sql/23_schueler_entfernen.sql` füllt wie sql/22
  ein letztes Mal nach (eine gesetzte Klasse wird nicht überschrieben) und
  entfernt dann die Tabelle; zweimal einspielbar. Die Prüfdatei ist im
  gemeinsamen Teil von MariaDB und SQLite geschrieben und wird im Test
  gegen erfundene Zeilen ausgeführt; die Migration ist nur am Text geprüft.
- **F3 — nur die beiden Lesestellen in den Messrouten fallen**, und die
  Engstelle wird geschärft (siehe unten).
- **F4 — die Sondierung bleibt unverändert**, auch `austritt_feld`. Sie ist
  ein Diagnosewerkzeug und der einzige verbleibende Aufruf von
  `getStudents`; eine Prüfung hält genau das fest.
- **F5 — die Admin-Seite heißt „Volljährige Schüler“** und zeigt nur noch
  die Benutzergruppen (E15). Der interne Schlüssel `admin-daten` bleibt.
- **F6 — der Datenschutz-Satz zur Schülerliste entfällt**, und die Prüfung
  ist umgekehrt: Das Wort „Schülerliste“ darf in der Hilfe nicht mehr
  stehen.

**Ein Muster, kein Einzelfall: zwei Prüfungen sahen eine Schreibweise,
nicht die Sache.**
- **Die Engstelle aus Schritt 2** prüfte, dass keine Datei mit der Tabelle
  `schueler` verbindet (`JOIN`) und dass vier benannte Dateien nicht aus
  ihr lesen (`FROM`). Die Lesestellen in `index.php` (Messrouten) und die
  ganze `schueler.php` lagen außerhalb: Ihr erster Lauf in der geschärften
  Form meldete genau diese beiden Dateien. Jetzt prüft sie jede PHP-Datei
  unter `backend/` samt Unterordnern auf `FROM`, `JOIN`, `INTO`, `UPDATE`
  und `TABLE` vor dem Tabellennamen, und ihr Suchausdruck wird an Formen
  geprüft, die er treffen muss, und an solchen, die er nicht treffen darf
  (`schueler_id`, `schueler_gruppen`, `/api/schueler-gruppen`, die Rolle
  `'schueler'`). Belegt durch vier Mutationen, je eine Schreibweise in
  einer Datei, die die alte Fassung nicht sah (SA1–SA4).
- **Der Schemaleser in `run_archivieren.php`** (Engstelle aus v0.9.74, E19)
  las `CREATE TABLE` und `ALTER TABLE`, aber nicht `DROP TABLE`. Nach
  Migration 23 hätte er `schueler` weiter für vorhanden gehalten — die
  Einordnung „personenbezogen“ wäre stehen geblieben, und die Hilfe hätte
  eine gespeicherte Tabelle nennen müssen, die es nicht mehr gibt. Jetzt
  liest er `DROP TABLE` in der Reihenfolge der Migrationen; eine
  Voraussetzung hält fest, dass er `schueler` als entfernt erkennt (SA7
  beschädigt den Leser und belegt sie).

Beide waren grün und richtig für das, was sie lasen. Sie lasen eine
bestimmte Schreibweise der Sache — eine Klausel, eine Anweisungsart — und
schwiegen über jede andere. **Wo eine Prüfung eine Sache zusichern soll,
zählt sie deren Schreibweisen auf und prüft die Aufzählung selbst**: an
Fällen, die treffen müssen, und an solchen, die nicht treffen dürfen.

**Dasselbe Muster im Werkzeug.** Beim Entfernen des Vergleichsmaßstabs
`$alt` aus der Route wurden die Mutationen nach dem Thema durchsucht
(SU1–SU10), nicht nach dem Bezeichner. S16 aus Schritt 1 trug `$alt` im
Suchmuster und meldete „MUTATION NICHT ANGEKOMMEN“; nachgezogen auf
dieselbe Stelle ohne `$alt`, derselbe Eingriff, dieselbe erwartete Zeile.
**Wer einen Bezeichner entfernt, durchsucht alle Mutationsmuster nach
ihm — nicht nur die thematisch naheliegenden.** Für Schritt 4 nachgeholt:
alle entfernten Bezeichner gegen alle Muster, null Treffer außerhalb der
SA-Serie; die Gegenprobe gegen die Fassung vor Schritt 4 traf genau S16,
SU7, SU10 und DS6.

**Ohne Rückfrage, ohne Einspruch:** Route `/api/schueler` samt
`schueler.php`, Abgleich, CSV-Import und Austritt entfallen; ebenso der
Messteil `messung_schuelerliste` und die Auskunft
`kennung_in_schuelerliste` der Elternmessung. `kind_klasse` älterer
Vorgänge bleibt leer, `einladungen.erledigt` bleibt.

**Nachtrag 11.10.2026 (v0.9.90) zur Lehre „Wer einen Bezeichner entfernt,
durchsucht alle Mutationsmuster nach ihm“:** Nicht nur entfernte
Bezeichner brechen Muster, **auch verschobener Text und geänderte
Zeilenform.** Beim Nachziehen der Hilfe meldeten zwei Mutationen „MUTATION
NICHT ANGEKOMMEN“, ohne dass ein Bezeichner wegfiel: AT48 suchte einen Satz
mit Leerzeichen am Anfang einer Fortsetzungszeile, und der Satz begann nach
dem Glätten des Absatzes eine neue Zeile; HR1 suchte zwei Einträge von
`HILFE_TEILE` direkt untereinander, und dazwischen stand jetzt ein neuer.
Gesucht hatte ich nur nach dem Bezeichner, der wegfiel (HR5 traf das).
Beide sind umgestellt, derselbe Eingriff an derselben Stelle – wie DS1,
DS2, S16, KN1 und KN5. **Wer Text umbricht, verschiebt oder zwischen zwei
Zeilen einfügt, durchsucht die Muster nach diesem Text und nach seinen
Nachbarzeilen**, nicht nur nach Bezeichnern. Die Serie meldet es zwar
selbst („NICHT ANGEKOMMEN“), aber erst nach dem Umbau.

**Zweiter Nachtrag 11.10.2026 (v0.9.93) zur selben Lehre: auch die Suiten,
nicht nur die Mutationsmuster.** Vor dem Umzug der Brute-Force-Bremse aus
der Anmelderoute nach `backend/api/anmeldebremse.php` waren die
Mutationsmuster nach den verschobenen Zeilen durchsucht, die Suiten nicht.
Rot wurde danach `frontend_loginlog_test.js`, die denselben Text in
`index.php` suchte. **Wer Code verschiebt, durchsucht vorher die Suiten
nach seinem Text**, nicht nur die Muster in `tests/mutationen.sh`. Und wo
dabei eine Prüfung rot wird, gehören die übrigen derselben Suite mit
angesehen (`docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md`, sechstes
Beispiel).

**Dritter Nachtrag 12.10.2026 (v0.9.97) zur selben Lehre: auch escapte
Schreibweisen.** Vor dem Ersetzen echter Kürzel waren `tests/mutationen.sh`
und die Suiten nach den alten Werten durchsucht, mit Wortgrenzen. AK14
schreibt die Kürzel als `\x27…\x27`; zwischen der `7` und dem ersten
Buchstaben liegt keine Wortgrenze, also sah die Suche die Stelle nicht, und
die Serie meldete danach „MUTATION NICHT ANGEKOMMEN“. Gefunden waren nur
die erwarteten Zeilen, wo die Werte im Klartext standen. Nach den
entfernten Bezeichnern (E20) und dem verschobenen Text samt Zeilenform
(v0.9.90, v0.9.93) ist das die dritte Variante derselben Lehre. **Die
gemeinsame Form (Betreiber): Vor einem Umbau wird nach dem WERT gesucht,
nicht nach seiner erwarteten Schreibweise** – ohne Wortgrenzen und in den
escapten Formen, mit einer Gegenprobe, die eine solche Form treffen muss.

## E21 — Das Kalender-Abo liefert nur kommende Sprechtage

**Datum:** 10.10.2026 · **Version:** 0.9.77 · **Anlass:** Fehler aus dem
Betrieb, gemeldet vom Betreiber

**Fund:** `GET /api/kalender/{token}.ics` lieferte die Termine **aller**
Sprechtage, auch vergangener. Gemeldet war ein Termin vom 27.07.2026, der
noch in der Kalender-App stand. Der Kommentar über `kal_buchungen_laden()`
versprach „zukünftige“ Buchungen, gefiltert wurde aber nicht. Das betraf
das Eltern- und das Lehrkraft-Abo; das Lehrkraft-Abo trägt dabei Name und
Klasse jedes gebuchten Kindes.

**Warum es zählt:** Vergangene Termine wanderten so dauerhaft in fremde
Kalender-Apps, nicht nur bis zum Archivieren in die eigene Datenbank. Der
Datenschutztext (v0.9.74) sagte das nicht. Es gehört zum Komplex D1 (Befund
„Daten neben dem Archiv“); dort ist es als **D6** nachgetragen.

**Entscheidung (Betreiber):** Das Abo liefert nur noch den aktuellen
Sprechtag. Begründung: Ein Kalender zeigt, was kommt.

**Beim Bauen entschieden:**
- **„Aktuell“ heißt: Datum heute oder später.** Ein Sprechtag gilt bis zum
  Ende seines Tages und fällt am Tag danach heraus. Die Phase spielt keine
  Rolle. Ein künftiger, geschlossener Sprechtag hat gültige Termine und
  bleibt drin. Ein archivierter hat keine Buchungen mehr, denn das
  Archivieren löscht sie. Ein zweiter Filter auf „nicht archiviert“ hätte
  dieselbe Gefahr ein zweites Mal abgewehrt, ohne einzeln prüfbar zu sein.
- **Mehrere Sprechtage:** Alle künftigen sind drin.
- **Beide Abos:** Eltern und Lehrkraft. Die Entscheidung nennt „das Abo“,
  und das Lehrkraft-Abo trägt mehr Namen als das der Eltern.
- **Nicht betroffen sind die Einzeldatei** (`/api/buchung/{id}.ics`, „📅
  hinzufügen“) **und die Tagesliste der Lehrkraft**
  (`/api/lehrer-termine/{id}.ics`). Beide sind ausdrückliche Downloads,
  keine Abos. Was dort heruntergeladen wird, ist eine Kopie in der
  Kalender-App, und das nennt jetzt der Datenschutztext.
- **Die eine Stelle** ist `kal_abo_ics()` in `kalender.php`. Die Route
  übergibt `date('Y-m-d')` und lädt keine Buchungen an ihr vorbei.

**Grenzen (nicht gemessen):**
- **Ob eine Kalender-App einen Eintrag entfernt, der aus dem Abo
  verschwindet, entscheidet die App.** Die gängigen gleichen ein
  abonniertes Kalenderabo beim nächsten Abruf vollständig ab. Das ist
  Allgemeinwissen, hier nicht gemessen. Der Hilfetext verspricht es
  deshalb nicht („liegt an der App“). Belegen ließe es sich am Gerät: Das
  Abo nach dem Deploy aktualisieren und nachsehen, ob der Termin vom
  27.07. verschwindet.
- **Die Zeitzone des PHP-Dienstes auf dem Server ist nicht gemessen.**
  Läuft er auf UTC, bleibt ein Sprechtag bis zu zwei Stunden über
  Mitternacht hinaus im Abo. Kürzer wird es nie.
- **Das Archivieren leert den Inhalt des Abos.** Das ist jetzt ausgeführt
  belegt (`run_kalender_abo.php`). Vor dem Umbau ergab eine Probe mit
  nachgebauter Routenlogik dasselbe; die Nachbildung ist dabei die
  Schwachstelle der Probe, nicht der Befund.

## E22 — Kürzel unempfindlich vergleichen; admin_kuerzel ist ein Array

> **Ersetzt 12.10.2026 (v0.9.97):** Die Kürzel in den Beispielen unten standen für echte Lehrkräfte,
> darunter der Betreiber. Ein Beispiel lässt sich erfinden, ohne dass das
> Protokoll leidet. Sie sind durch erfundene
> gleicher Länge ersetzt, wie überall im heutigen Stand (E28).

**Datum:** 10.10.2026 · **Version:** 0.9.78 · **Anlass:** Ergänzung des
Betreibers zu einem „Fund 1“. Dieser Fund ist in der Sitzung, die das hier
gebaut hat, nicht angekommen; was er betraf, ist aus dem Code erschlossen.

**Stand bis v0.9.77:**
- `wu_login()` prüfte `admin_kuerzel` mit `in_array(…, true)`, also mit
  Rücksicht auf Groß- und Kleinschreibung. `['hq']` traf die Lehrkraft
  „Hq“ nicht.
- Ein WebUntis-Admin (personType 16) bekam `admin_kuerzel[0]`. Bei einer
  Zeichenkette statt eines Arrays war das nur der erste Buchstabe.
- Die Wache „nicht sich selbst als Administrator entfernen“ verglich
  `app_admins.lehrer_kuerzel` streng mit dem Kürzel der Sitzung. Bei „hq“
  gegen „Hq“ griff sie nicht. Das ist derselbe Fehler an einer anderen
  Stelle, deshalb mitbehoben.
- Die Datenbankvergleiche (`lehrer.kuerzel`, `app_admins`) laufen laut
  Schema unter `utf8mb4_unicode_ci` und waren schon unempfindlich. Am
  Server gemessen ist das nicht.

**Entscheidung (Betreiber):**
- **Der Vergleich ist unempfindlich, in beide Richtungen.** `['hq']`,
  `['Hq']` und `['HQ']` treffen dasselbe Kürzel. **Nicht** auf
  Kleinschreibung festlegen: Das bräche bestehende Konfigurationen und
  verlangte wieder Wissen über eine Konvention.
- **`admin_kuerzel` ist ein Array.** Ein Eintrag mit Komma (`['Hq, Mq']`)
  traf bisher still niemanden.

**Gebaut:**
- `wu_kuerzel_gleich()`: unempfindlich, ohne Rand-Leerzeichen; leer trifft
  nie. Für Kürzel mit Umlaut braucht der Vergleich mbstring.
- `wu_kuerzel_liste()`: Komma-Einträge und eine Zeichenkette werden
  aufgeteilt.
- `wu_ist_config_admin()` und `wu_admin_eigenes_kuerzel()`.
- **Engstelle:** `admin_kuerzel` wird nur über `wu_kuerzel_liste()`
  gelesen. Das prüft die Suite über alle Backend-Dateien.
- `config.example.php` erklärt die Schreibweise.

**Aufteilen statt melden:** Ein Komma-Eintrag wird aufgeteilt, ohne
Meldung. Eine Meldung hätte bei jeder Anmeldung im Protokoll gestanden.
Gemeint ist die Array-Form; die Aufteilung ist die Sicherung dahinter.

**Folge der Auslieferung:** Wer in `config.php` in irgendeiner Schreibweise
oder in einem Komma-Eintrag steht, ist ab v0.9.78 Admin. Bisher galt das
still nicht.

## E23 — Die Verwaltung arbeitet über ein Lehrerkonto mit admin_kuerzel

**Datum:** 10.10.2026 · **Version:** keine Codeänderung (Stand v0.9.79) ·
**betrifft:** E16, E17, E22 · **Befund:**
`docs/BEFUND-2026-10-10-verwaltung-parents.md`

**Anlass:** Mit dem echten WebUntis-Admin-Konto (personType 16) blieb eine
Absage offen: „PARENTS: Keine Berechtigung zum Versenden von Mitteilungen
(HTTP 403)“. Dem Admin-Konto fehlt die Empfängerart PARENTS. Absagen,
Ausfälle, Einladungen und die stellvertretende Bestätigung laufen aber über
PARENTS (v0.9.72/73), und auch die Verwaltung löst sie aus. Das ist eine
Lücke im Entwurf von E16/E17.

**Entschieden (Betreiber):** Die Verwaltungsansicht wird über ein
**Lehrerkonto mit `admin_kuerzel`** erreicht, nicht über das echte
Admin-Konto. Seit v0.9.78 (E22) funktioniert das, im Betrieb bestätigt. Die
Lehrkraft-Sitzung trägt PARENTS (gemessen).

**Offen, zu entscheiden (Betreiber):** Was geschieht mit dem echten
Admin-Konto? Es bekäme weiterhin die Verwaltung über personType 16. Die
Möglichkeiten:
1. **keine Verwaltung mehr** für personType 16;
2. **Verwaltung mit Warnung**;
3. **unverändert, nur dokumentiert.**

Folgen je Möglichkeit: siehe die Antwort an den Betreiber vom 10.10.2026,
sobald entschieden, hier als Nachtrag.

**Nachtrag 10.10.2026 — entschieden: Option 2, Verwaltung mit Warnung (v0.9.80):**
- **Begründung (Betreiber):** Der Notzugang entscheidet. Nach einer
  Neuinstallation steht in `admin_kuerzel` `'XYZ'`. Mit Option 1 wäre eine
  Schule ausgesperrt, bevor sie die Konfiguration anpassen kann, und das
  wäre schlechter als die Lücke. Gegen Option 3 spricht der Zeitpunkt: Die
  Lücke zeigte sich erst hinterher, als offene Mitteilung. Eine Warnung
  vorher ist besser als ein Befund nachher.
- **Gebaut:**
  - Die Anmeldung merkt sich personType 16 in der Sitzung;
    `auth_user()['admin_konto']`.
  - `adminKontoWarnungElement()` erscheint in jeder Ansicht außer der
    Anmeldung. Sie nennt Absagen, Ausfälle, Einladungen und Bestätigungen
    an Eltern und sagt, dass die Nachrichten offen stehen bleiben. Als Weg
    nennt sie ein Lehrerkonto, dessen Kürzel in `admin_kuerzel` steht.
  - Eine Sitzung von vor v0.9.80 zeigt die Warnung erst nach der nächsten
    Anmeldung.
- **Nicht gebaut, als Möglichkeit notiert:** die schärfere Spielart, mit
  diesem Konto die Aktionen abzuweisen, die an Eltern senden. FALLSTRICKE 8
  spräche dafür. Aber ein Notzugang, der die Hälfte abweist, ist im Notfall
  womöglich genau die Hälfte, die man braucht. **Zeigt sich, dass jemand die
  Warnung übersieht und trotzdem handelt, wird nachgeschärft.**

---

## E24 — Ein Termin je Kind und Lehrkraft

**Datum:** 11.10.2026 · **Version:** v0.9.84 · **Fehler aus dem Betrieb**

**Lage:** Ein Elternteil konnte bei derselben Lehrkraft für dasselbe Kind
mehrere Termine buchen; gesehen wurden zwei, um 15:00 und 15:10. Der Weg
der Eltern (`POST /api/buchungen`) prüfte nur das Konto zur selben Uhrzeit
(„Fall A“) und die Obergrenze je Konto. Die stellvertretende Buchung
prüfte das Kind schon („Fall B“), aber **keine einzige Prüfung hielt diese
Regel fest.** Eine Regel, die lange im Code stand und nie geprüft wurde,
ist dasselbe wie eine, die nur im Frontend steht: Man weiß nicht, ob sie
noch tut, was sie soll.

**Entschieden (Betreiber):** Das wird verhindert. Begründung, wörtlich: Es
bindet Kapazitäten, die es nicht gibt. Und: „Auch getrennt lebende Eltern
müssen miteinander reden, und als Lehrer führe ich nicht dasselbe Gespräch
zweimal.“

- **Je Kind und Lehrkraft, nicht je Elternkonto.** Getrennt lebende Eltern
  buchen über zwei Konten. Eine Prüfung je Konto ließe genau diesen Fall
  offen, und er ist der wahrscheinlichste.
- **Auch für die stellvertretende Buchung** (1): Sie lehnt weiter ab. Wer
  die Lage kennt, kann den vorhandenen Termin absagen und neu buchen.
- **Die Meldung nennt die Uhrzeit nur, wo sie beim Handeln hilft** (2b):
  - beim eigenen Termin;
  - für die Lehrkraft, die stellvertretend bucht; sie sieht ihr Raster
    ohnehin.
  
  Liegt der Termin an einem anderen Konto, findet man ihn in „Meine
  Termine“ nicht und kann ihn nicht absagen. Die Uhrzeit hülfe also nicht
  beim Handeln, sie verriete nur, wo jemand zu einer bestimmten Zeit ist.
  Bei einem Kontaktverbot wäre das genau die falsche Auskunft; eine
  Schulanwendung muss sie nicht preisgeben.
- **Ein abgesagter Termin zählt nicht:** Nach einer Absage soll neu gebucht
  werden können. Die Ablage aus E19 steht in einer eigenen Tabelle und
  blockiert nicht.
- **Eine Stelle, serverseitig** (3): `bu_kind_termin()` in `slots.php`.
  Beide Buchungswege fragen sie in ihrer Transaktion vor dem Schreiben.
- **Zweite Stufe: der Schlüssel** `uniq_kind_termin` (sprechtag_id,
  lehrer_id, schueler_id) auf `buchungen` (`sql/26`). Er ist die einzige
  Sicherung, die zwei gleichzeitige Buchungen beider Elternteile fängt; ob
  `FOR UPDATE` auf eine noch nicht vorhandene Zeile das in MariaDB täte,
  ist nicht bekannt. Vorweg zählt `sql/26_pruefung.sql`, wie bei sql/23.
  Reihenfolge: Deploy, dann zählen, dann Migration. Welche der doppelten
  Buchungen weicht, entscheidet der Betreiber.
- **Der tote Zweig entfällt** (4): Über den Weg der Eltern konnten
  Lehrkraft und Verwaltung mit einer Konto-Kennung aus dem Request buchen.
  Den Zweig rief nichts auf, und ihm fehlte dieselbe Regel. Toter Code mit
  einer Lücke ist schlechter als toter Code.

**Eigener Schritt danach, nicht in diesem Zug:** Die Kachel einer Lehrkraft
zeigt schon vor dem Klick, dass für das Kind dort ein Termin besteht, und
das Raster ist nicht buchbar. Eine Lehrkraft, bei der man ohnehin nicht
buchen kann, erst nach dem Klick als gesperrt zu erfahren, ist unnötig.
Die Anzeige fragt dann dieselbe Funktion.

**Festlegung der Entwicklung:** Die Abfrage in `bu_kind_termin()` liest ohne
`FOR UPDATE`. Gegen gleichzeitige Buchungen steht der Schlüssel; nach
seiner Verletzung sagt `bu_konflikt_text()`, ob das Kind oder der Slot es
war. Bis `sql/26` eingespielt ist, fehlt diese zweite Stufe im Betrieb –
wie vor v0.9.84 für alle Fälle.

**Nachtrag 11.10.2026 — im Betrieb vollständig (Betreiber):**
- `sql/26` ist eingespielt. `SHOW INDEX` zeigt `uniq_kind_termin` über
  sprechtag_id, lehrer_id, schueler_id mit Non_unique 0; `uniq_slot` steht
  unverändert daneben. Damit gilt die Regel an einer Stelle im Code, und
  der Schlüssel in der Datenbank ist die zweite Stufe.
- `sql/25` aus v0.9.83 ist ebenfalls drin: `einladungen` führt `erledigt`
  nicht mehr.
- Die Tags `v0.9.83` und `v0.9.84` liegen auf github **und** uberspace.
  Auf dem Server zeigt ein Tag die laufende Version besser als eine
  Commit-Kennung. Der Hook checkt `main` aus, und ein Tag ändert daran
  nichts; gemessen beim Push: „Bereits auf 'main'“.

**Der wichtigste Teil des Zuges (Betreiber):** Die Prüfung der Aufrufstellen
verlangt den ganzen Wachblock zusammenhängend vor dem `INSERT` – Abfrage,
Bedingung, Zurückrollen, Meldung –, nicht nur den Aufruf irgendwo davor.
Ein Aufruf, dessen Ergebnis niemand auswertet, wäre sonst durchgegangen.
ET15 und ET16 belegen es mit `if (false)`.

**ET8 – viertes Vorkommen desselben Musters (Betreiber):** Das Suchmuster
der Mutation traf zuerst den Kopfkommentar der Migration, in dem derselbe
Wortlaut steht wie in der Anweisung. Die Prüfsumme änderte sich, der
Eingriff galt als „angekommen“, und die Prüfung, die Kommentare
ausblendet, blieb zu Recht grün. Es ist derselbe Fehler wie bei den
Wortlaut-Prüfungen (REIHENREGELN 2: „Ein Prüfausdruck darf nicht auf die
Beschreibung der Regel anschlagen“), nur im Werkzeug statt im Code.
**Die geänderte Prüfsumme belegt, dass sich die Datei geändert hat, nicht,
dass der Eingriff an der gemeinten Stelle sitzt.** Gefunden hat es der
Nichttreffer in der Serie, nicht eine Prüfung. Behoben ist der Anlass:
ET8 ist an `ALTER TABLE` verankert.

---

## E25 — Kontodaten raus aus dem heutigen Stand; Betriebswerte nur, wo nichts kopiert wird

**Datum:** 12.10.2026 · **Version:** v0.9.94 · **Entschieden vom Betreiber**

**Lage:** Die Bestandsaufnahme der Doku (12.10.2026) fand in den Testdaten
die `user.id` und die `person.id` des Elternkontos des Betreibers, dazu
seinen Namen als Anzeigenamen – beide Kennungen gemeldet am 10.10.2026 aus
`/api/auth/me`. Die `user.id` stand außerdem in einem Code-Kommentar, in
einer Migration und im CHANGELOG (v0.9.0), die `person.id` in
`docs/SONDIERUNG.md`. Das Repo ist öffentlich. Daneben trug der Kopf von
`deploy.sh` den echten Datenbanknamen, während `docs/BETRIEB.md` seit dem
Doku-Umbau `DATENBANK` schreibt.

**Entschieden:**

- **Kennungen und Name werden überall im heutigen Stand ersetzt**, durch
  erfundene Werte gleicher Länge, wie in v0.9.87 – auch im CHANGELOG, mit
  Vermerk. Maßstab ist die **Adressierbarkeit**, nicht die Unterscheidung
  Arbeitsdatei gegen Protokolleintrag (Entscheidung von `koordination` zu
  REIHENREGELN 9 gegen 10, mitgeteilt am 11.10.2026): Allein sagt eine
  WebUntis-Kennung nichts, zusammen mit dem Autor des Repos und der Schule
  wird sie zuordenbar. Das Kürzel unter v0.9.4 ist außerhalb der Schule
  bedeutungslos und bleibt. Begründung des Betreibers für das CHANGELOG:
  Eine Kennung, die überall sonst verschwindet und nur dort stehen bleibt,
  wäre eine seltsame Hälfte.
- **Der Datenbankname wird nur in `deploy.sh` ersetzt, nicht in den 16
  Köpfen unter `sql/`.** Begründung des Betreibers: Der Datenbankname ist
  kein personenbezogener Wert; er gehört zu Hostname und Benutzername –
  unschön im öffentlichen Repo, aber nicht schützenswert. Die Köpfe unter
  `sql/` sind das Arbeitswerkzeug beim Einspielen, dort steht ein
  kopierbarer Befehl, und **ein Befehl, den man ersetzen muss, bevor er
  läuft, ist fehleranfälliger als einer, der läuft.** In `deploy.sh` stand
  ohnehin `sql/NN_*.sql`, nicht kopierbar; dort kostet ein Platzhalter
  nichts, und der Satz daneben sagt, dass er zu ersetzen ist.

**Was es nicht leistet:** Das Ersetzen nimmt die Werte aus dem heutigen
Stand, nicht aus der Historie; das Repo ist öffentlich. Wer sie wirklich
entfernen wollte, müsste die Historie umschreiben, und das bricht jeden
Klon. (Wortlaut aus dem Nachtrag von `koordination` zu v0.9.87.)

**Belegt:** dass die Prüfungen dasselbe prüfen wie vorher – sieben
Eingriffe am geprüften Code im Probebaum, alte und neue Testfassung mit
denselben roten Positionen (`tests/werkzeuge/ersatz_belegen.sh`, CHANGELOG
v0.9.94).

**Offen beim Betreiber:** die übrigen Namen und Kennungen in den Testdaten
(Bestandsaufnahme, Nachtrag 12.10.2026).

---

## E26 — Die Sondierung ruft getStudents nicht mehr; zwei catch fangen nur Betriebsfehler

**Datum:** 12.10.2026 · **Version:** v0.9.95 · **Entschieden vom Betreiber**,
auf eine Mitteilung von `koordination`

**Lage:** Das WebUntis-Client-Modul ist in `koordination` zusammengeführt
und auf v2.0.0 getaggt (`hornse/webuntis-client-php`, gelesen in
`src/WebUntisAuth.php`). Zwei Änderungen treffen `sondierung.php`:
`getStudents()` ist nicht mehr im Modul (personenbezogen, REIHENREGELN 9;
`rpc()` ist privat), und `getCurrentSchoolyear()` liefert `[]` nur noch für
„kein Schuljahr aktiv“ und wirft bei jedem anderen Fehler. `koordination`
verlangte vor der Verteilung eine Entscheidung: Abfrage in die Anwendung
oder die Abschnitte fallen weg – nicht „bleibt im Modul“.

**Entschieden:**

- **Beide Stellen mit `getStudents` fallen weg** – der Abschnitt „Weg B“ und
  im Abschnitt pageconfig der Abgleich. Begründung: Ein Nachbau wäre eine
  zweite Wahrheit für ein abgeschaltetes Werkzeug, und dass `rpc()` privat
  ist, sagt zusätzlich, dass der Weg nicht von außen gegangen werden soll.
  Der Abgleich hatte genau einen Zweck – derselbe Nummernkreis –, und der
  ist beantwortet (E8). **pageconfig bleibt** mit seinen drei Zählwerten;
  sie sagen weiter, ob pageconfig liefert und wie vollständig.
- **Aktuelles Schuljahr und pageconfig fangen `RuntimeException`, nicht
  `Throwable`** (FALLSTRICKE 3). Ein Netzfehler steht als Fehler im
  Bericht und nicht mehr als Sommerferien; ein Programmierfehler fliegt
  durch.
- **Punkt 9 der Aufräumliste bleibt getrennt** (`mitteilungen.php`).

**Belegt:** `tests/run_sondierung_stammdaten.php` führt die echte Funktion
mit Attrappen der Außenwelt aus; vor der Änderung 8 von 16 rot. Die elf
Prüfungen in `tests/run.php`, die eine nachgebildete Kopie von Weg B
prüften, sind entfernt (CHANGELOG v0.9.95).

**Was bleibt:** Bei Schuljahren und Klassen fängt dieselbe Funktion noch
`Throwable` (drei Stellen). Das Modul selbst ist hier noch nicht verteilt;
`backend/auth/` ist unverändert.

---

## E27 — Die gemerkte Versandvariante: nur der Datenbankfehler wird gefangen, und der Rückfall steht im Ergebnis

**Datum:** 12.10.2026 · **Version:** v0.9.96 · **Aufräumliste 9**, Vorrang
und Form bestätigt vom Betreiber

**Lage:** `mit_versand_ausfuehren()` las die zuletzt erfolgreiche
Versandvariante in `catch (Throwable $e) { /* Tabelle ggf. noch leer */ }`.
Jeder Fehler wurde zu „nichts gemerkt“, und der Versand probierte still
alle Varianten. Der Kommentar nannte einen Fall, der das `catch` gar nicht
braucht: Ist nichts gemerkt, liefert die Abfrage `false`, keinen Fehler
(gemessen: Prüfung „nichts gemerkt“ in `tests/run_versand_variante.php`).

**Warum es so lange stand:** Das ist die Steigerung von FALLSTRICKE 3. Dort
steht ein leeres `catch` mit einer Begründung daneben; hier stand eine
Begründung, die **nicht zutraf**. Wer den Kommentar las, hielt das `catch`
für nötig und sah nicht hin. Ein begründender Kommentar an einem `catch`
ist deshalb eine Behauptung, die sich prüfen lässt: Den genannten Fall
herstellen und nachsehen, ob er überhaupt wirft.

**Entschieden:**

- **Gefangen wird `PDOException`**, also das Scheitern der Datenbank, etwa
  eine fehlende Tabelle. Ein Programmierfehler (`Error`) fliegt durch
  (FALLSTRICKE 3). `PDOException` ist enger als die `RuntimeException`, die
  der umgebende Versandblock fängt.
- **Der Rückfall bleibt:** Alle Varianten werden der Reihe nach probiert, wie
  bisher. Die Folge ist mild (Aufräumliste 9), Nachrichten gehen weiter
  hinaus.
- **Er ist aber unterscheidbar,** an zwei Stellen: im neuen Feld
  `variante_lesefehler` (sonst `null`, in jeder Rückgabe der Funktion) und
  als Zusatz im `grund`. Der `grund` erreicht auch die Wege, die das Feld
  nicht weitergeben (`mit_senden_oder_vormerken()` gibt nur den Grund
  zurück).

**Belegt:** `tests/run_versand_variante.php` führt die echte Funktion gegen
SQLite und eine REST-Attrappe aus, die jede Variante ablehnt; vor der
Änderung 6 von 11 rot. Mutationen PV1–PV6.

**Was bleibt:** `backend/api/index.php` baut für die leere ID-Liste eine
eigene Rückgabe derselben Form (`POST /api/mitteilungen/senden`); sie führt
das neue Feld nicht. Das ist eine zweite Darstellung derselben Antwort,
nicht behoben, gemeldet, und als Aufräumliste 12 vorgemerkt.

---

## E28 — Alle Personennamen außer Paulowski sind echt und kommen heraus, mit Kürzeln und Kennungen

**Datum:** 12.10.2026 · **Version:** v0.9.97 · **Entschieden vom Betreiber**

**Lage:** Die Bestandsaufnahme (`docs/BESTAND-DOKU-2026-10-11.md`,
Nachträge vom 12.10.2026) fand in Testdaten Namen und Kennungen ohne
Herkunftsvermerk. Stelle für Stelle nachzufragen hätte jede neue Fundstelle
zu einer neuen Frage gemacht.

**Entschieden:**

- **Alle Paulowski-Namen sind erfunden und bleiben,** gleich ob Eltern oder
  Schüler, gleich welcher Vorname; ebenso ihre Kennungen.
- **Alle anderen Personennamen sind echt und kommen heraus,** mit ihren
  Kürzeln und den Kennungen, die zu ihnen gehören. **Das gilt auch für den
  Betreiber selbst**, wie schon bei seiner `user.id` und `person_id` (E25).
- **Ein Platzhalter, der als solcher lesbar ist, bleibt** (Erfunden…,
  Muster…, Beispiel…, Testkind). Ein Name, der echt wirkt, wird ersetzt, auch
  wenn er erfunden sein könnte: Wer die Datei liest, kann es nicht
  unterscheiden.
- **Außerhalb der Tests gilt die Abwägung aus v0.9.88, Adressierbarkeit:**
  - **Ein Anmeldename ist adressierbar:** Gegen ihn lassen sich Passwörter
    probieren. Er wird ersetzt, auch im CHANGELOG, mit Vermerk.
  - **Ein Beispiel wird ersetzt:** Es steht nicht als Zeugnis eines
    Vorgangs, und es lässt sich erfinden, ohne dass das Protokoll leidet
    (CHANGELOG v0.9.78, E22, `config.example.php`).
  - **Ein Kürzel als Zeugnis eines Vorgangs bleibt:** die Befundtabelle
    unter CHANGELOG v0.6.0. Ohne die Namen in den Tests verweist es auf
    niemanden mehr.
  - **Die Urheberangabe bleibt** (`frontend/index.html`): Sie ist kein
    Testdatum.
- **Wo eine Stelle schon unter einer Entscheidung von `koordination` steht,
  wartet sie auf `koordination`:** der Anmeldename des Lehrkraftkontos in
  CHANGELOG v0.9.4 (Aufräumliste 5).

**Wie:** wie v0.9.94 – erfundene Werte gleicher Länge, Sortierstellen
erhalten, im heutigen Stand, nicht in der Historie. Wirkungsnachweis mit
`tests/werkzeuge/ersatz_belegen.sh` und dem Satz
`tests/werkzeuge/ersatz_v0997.sh` (CHANGELOG v0.9.97).

**Was bleibt:** Drei Suiten bilden den geprüften Ablauf selbst nach und sind
deshalb durch keinen Eingriff belegbar (`run_stammsatz.php`,
`frontend_reihenfolge_test.js`, `frontend_zustand_test.js`; Art 5). Und die
Absage nennt die Lehrkraft zweimal, geprüft wird nur das Vorkommen (Art 1).
Beides gemeldet, nicht behoben.

## E29 — Admin-Rechte: admin_kuerzel ist der Notzugang, gepflegt wird über die Oberfläche

**Datum:** 12.10.2026 · **Version:** v0.9.98 (Teil 1 von 3) · **Entschieden
vom Betreiber** · **Anlass:** Aufräumliste 10 (`app_admins` ohne Oberfläche)

**Lage (am Code gelesen):** Admin wurde man auf **drei** Wegen, nicht auf
zwei: über `admin_kuerzel` in `config.php`, über einen Eintrag in
`app_admins` – gleichwertig daneben, mit Route, aber ohne Oberfläche – und
über das WebUntis-Admin-Konto (personType 16), ohne Bedingung. Die Rolle
wurde bei der Anmeldung in die Sitzung geschrieben und danach nicht mehr
geprüft. POST nahm jedes Kürzel an, auch eines ohne Stammsatz und eines aus
`admin_kuerzel`. Die Anmeldung verglich `app_admins` in SQL, das Ergebnis
hing also an der Kollation.

**Entschieden – Arbeitsteilung statt zweier gleichwertiger Wege:**

- **`admin_kuerzel` schrumpft auf den Notzugang:** ein oder mehrere Kürzel,
  die immer gelten, damit sich niemand aussperren kann. Das ist der Weg,
  der einen Serverzugang braucht und den ein kompromittiertes
  WebUntis-Konto nicht hat.
- **Alles Weitere läuft über die Oberfläche,** gespeichert in `app_admins`.

**Begründung (Betreiber):** Admin-Rechte sind das, womit man alle anderen
Rechte vergibt. Wer sie über die Oberfläche vergeben kann, kann sie auch
sich selbst erweitern. Eine Datei auf dem Server ist langsamer zu ändern,
aber sie braucht einen Zugang anderer Art. Und: Es gab zwei Wege, Admin zu
werden, gleichwertig nebeneinander – dasselbe Muster, das die Reihe seit
Tagen beschäftigt. Eine Oberfläche nur für den zweiten Weg hätte ihn
festgeschrieben, statt die Frage zu klären. Die Arbeitsteilung klärt sie.

**Entschieden auf die Rückfragen beim Bauen:**

- **A – Das WebUntis-Admin-Konto bleibt ohne Bedingung Admin.** Es ist der
  Notzugang für den Notzugang: Steht in `admin_kuerzel` nach einer
  Neuinstallation `'XYZ'`, erreicht sonst niemand die Verwaltung. Und es
  wird regelmäßig gebraucht: Die Gruppenliste für die Volljährigen liefert
  WebUntis nur diesem Konto (gemessen 09./11.10.2026, Admin 200,
  Lehrkraft 403), bei jeder neuen Gruppe und jeder Umbenennung wieder.
  Dass es damit ein dritter Weg bleibt, ist unschön; er setzt aber
  Admin-Rechte in WebUntis voraus, und wer die hat, braucht `sprechtag`
  nicht. **In der Oberfläche ist er sichtbar,** als dritte Herkunft neben
  Notzugang und Oberfläche – sonst wundert sich jemand, warum ein Konto
  Admin ist, das nirgends steht.
- **B – Die Rolle wird bei jedem Aufruf einer Admin-Sitzung neu geprüft.**
  Wer entfernt wird, blieb bis zum Sitzungsende Admin, und Rechte entzieht
  man meist nicht aus Gefälligkeit. Eine Meldung „wirkt ab der nächsten
  Anmeldung“ wäre eine Entschuldigung für ein Verhalten, das man nicht
  will. Der Preis ist eine Abfrage mehr je Aufruf.
- **C – POST lehnt zweierlei ab:** ein Kürzel ohne Stammsatz (ein Eintrag
  mit leerem Namen sieht aus wie ein Admin und ist keiner, solange sich
  niemand mit dem Kürzel anmeldet) und ein Kürzel, das schon in
  `admin_kuerzel` steht (doppelte Einträge für dasselbe Recht sind die
  Unordnung, die dieser Zug beseitigt).
- **Kürzel in beiden Listen** (nur noch aus der Zeit vor C): einmal
  angezeigt, als Notzugang; der Tabelleneintrag lässt sich entfernen, mit
  dem Hinweis, dass das Recht bleibt. Ob es solche Fälle im Betrieb gibt,
  zählt `tests/werkzeuge/admin_bestand.php`.
- **D – Verglichen wird in PHP mit `wu_kuerzel_gleich()`, nicht in SQL.**
  Dann prüft der Test dasselbe wie der Betrieb, und der Unterschied
  zwischen MariaDB und SQLite fällt weg, statt zwischen beiden zu
  schwanken.
- **In Teilen, die einzeln prüfbar sind,** jeder als eigene Version:
  1. Backend (v0.9.98), 2. Oberfläche, 3. Dokumentation (BEDIENUNG,
  WEBUNTIS, Hilfe, Warnkasten, `config.example.php`).

**Gebaut in Teil 1 (v0.9.98):**

- `backend/api/admins.php`: **`adm_herkunft()` ist die eine Stelle,** die
  entscheidet – bei der Anmeldung (`wu_login`) und bei jedem Aufruf. Der
  Notzugang geht vor der Tabelle. `app_admins` steht nur in dieser Datei
  (geprüft über alle Backend-Dateien).
- **Die Nachprüfung liegt nicht in `auth_require_admin()`,** wie der
  Betreiber zu prüfen bat, **sondern im Vorspann von `index.php`, vor der
  ersten Route** (`adm_sitzung_nachpruefen`). Grund: Neben
  `auth_require_admin()` lesen gut ein Dutzend Stellen `$u['rolle']`
  unmittelbar (`index.php`, `buchungen.php`, `slots.php`, `absagen.php`,
  `einstellungen.php`); eine Prüfung nur in `auth_require_admin()` hätte
  sie ausgelassen. Im Vorspann wird die Sitzung selbst zurückgestuft, und
  alle Stellen sehen dasselbe. Andere Sitzungen fassen die Datenbank
  dabei nicht an; befördert wird hier nie.
- **Zurückgestuft wird auf `lehrkraft`:** Admin über eine der beiden Listen
  gibt es nur für Lehrerkonten (personType 2). Das WebUntis-Konto wird nie
  zurückgestuft.
- `GET /api/admins` liefert alle drei Herkünfte mit `herkunft`, `selbst`,
  `entfernbar`, `auch_oberflaeche` und `ohne_stammsatz`, ohne Fließtext –
  den bildet Teil 2.
- `POST` trägt in der Schreibweise des Stammsatzes ein; ein zweites Mal
  dasselbe Kürzel antwortet „bereits eingetragen“ statt
  `ON DUPLICATE KEY UPDATE` (MariaDB-Syntax, in SQLite nicht ausführbar).
  Als Stammsatz zählt ein aktiver (`aktiv = 1`), wie in der Auswahl der
  Stammdaten; gesetzt wird `aktiv = 0` heute nirgends im Code.
- `DELETE`: Die Wache gegen das Selbst-Entfernen bleibt, wie sie war. Neu
  sind 404 für eine unbekannte Id (bisher still „ok“) und die Hinweise
  „bleibt Administrator … Notzugang“ und „Über die Oberfläche ist jetzt
  niemand mehr Administrator“, je nachdem, ob `admin_kuerzel` etwas
  enthält.

## E30 — Versionsnummern: 1.0.0 erst nach dem Praxistest

**Datum:** 12.10.2026 · **Version:** v0.9.100 · **Entschieden vom
Betreiber**

**Lage:** Nach v0.9.99 stand die nächste Nummer an. Vergeben ist v0.9.100
als mechanische Fortsetzung; 1.0.0 wäre eine Aussage über den Stand
gewesen.

**Entschieden:** 1.0.0 vergibt der Betreiber, **nach dem Praxistest, nicht
davor.** Eine Nummer, die Reife behauptet, steht erst, wenn jemand anderes
das System benutzt hat. Bis dahin zählt die dritte Stelle weiter (0.9.101,
…).

**Folge für Werkzeuge:** Ab 0.9.100 hat die dritte Stelle drei Ziffern.
`git tag | sort -V` ordnet richtig; ein einfaches `sort` stellt 0.9.100
zwischen 0.9.10 und 0.9.11. Wer Versionen sortiert, sortiert nach Version.

## E31 — Mitteilungen: Lehrkräfte sehen und verwerfen nur die eigenen, die Verwaltung alle

**Datum:** 14.10.2026 · **Version:** v0.9.102 · **Anlass:** Review aus
`koordination` (`docs/BEFUND-2026-10-14-review-sprechtag-1.md`, Commit
`a700ce2`), Befunde 2 und 3, dort gemessen an `bff6a89`.

**Lage (am Code bestätigt, ausgeführt in `run_mitteilungen_rechte.php`):**
- **Befund 2:** `GET /api/mitteilungen` filterte nur nach Sprechtag und
  Status. Jede Lehrkraft las alle Mitteilungen aller Lehrkräfte – mit
  Kindname, Klasse, Betreff, Anlass und Empfängerkennung. Gegen den alten
  Stand: Lehrkraft 7 bekam alle vier Mitteilungen der Testdaten.
  Datenschutzfall im Betrieb.
- **Befund 3:** `DELETE /api/mitteilungen/{id}` verwarf ohne
  Eigentumsprüfung, mit 200. Eine offene Absage einer anderen Lehrkraft
  ginge nie an die Eltern.
- **Eine Ursache:** Die Frage „gehört die Mitteilung zu meinen Terminen?“
  stellte nur der Versand (`senden`), mit eigener Bedingung im Zweig.

**Behoben:**
- `mit_gehoert_bedingung()` in `mitteilungen.php` ist die eine Bedingung
  für Liste, Verwerfen und Versand: die eigene Lehrkraft an der
  Mitteilung, oder – für Mitteilungen vor v0.9.72 ohne Lehrkraft – eine
  eigene Buchung beim selben Empfänger.
- Liste: Lehrkräfte bekommen nur die eigenen; eine Lehrkraft ohne
  Stammsatz eine leere Liste.
- Verwerfen: fremde 403, unbekannte Kennung 404 (bisher still 200).

**Benannt, nicht nebenbei erhalten: Wer alles sieht und verwerfen darf,
ist die Verwaltung (Rolle `admin`).** Die Verwaltung hat das bisher
gekonnt, und daran hängen der Sammelversand (`senden` ohne Kennungen) und
der Hinweis auf offene Mitteilungen nach der Anmeldung (E17). Der
Betreiber hat es in seinem Auftrag vermutet („vermutlich die
Verwaltung“); **die Bestätigung steht aus.** Eine Einschränkung – etwa
nur lesen, nicht verwerfen – wäre eine Zeile in jedem der beiden Zweige.

**Nicht angeglichen (Aufräumliste 17):** `GET /api/mitteilungen/offen-eigene`
fragt eine eigene, engere Bedingung (nur `m.lehrer_id`, ohne die Buchungen
für ältere Mitteilungen), und `run_sitzung_versand.php` verlangt genau
das. Zwei Bedingungen für dieselbe Frage – gemeldet, nicht nebenbei
geändert.

**Nachtrag 14.10.2026 (Betreiber) — die Verwaltung sieht und verwirft
alle: bestätigt, mit Grund.** Der Sammelversand braucht das Sehen, und wer
sammelt, muss eine fehlerhafte Mitteilung auch zurückziehen können. Damit
ist es eine Entscheidung, kein übriggebliebener Zustand. Die Abnahme im
Betrieb macht der Betreiber mit einer Lehrersitzung.

**Zweiter Nachtrag 14.10.2026 (v0.9.103) — der Hinweis nach der Anmeldung
fragt dieselbe Bedingung (Aufräumliste 17).** Betreiber: Zwei Bedingungen
für dieselbe Frage sind zwei Wahrheiten, auch ohne sichtbare Folge
(REIHENREGELN 2); der Hinweis gehört an `mit_gehoert_bedingung()`, wenn die
engere Fassung keinen eigenen Grund hat. **Gesucht, keinen gefunden:**
Beide Fassungen entstanden im selben Commit (`158bc25`, v0.9.72) – die
weitere im Versand, die engere in `offen-eigene` –, und E17 sagt nur „Eine
Lehrkraft sieht ihre eigenen, die Verwaltung alle“. Dazu ein Grund
**gegen** die engere: Der Knopf „Jetzt senden“ im Hinweis schickt dessen
Kennungen an den Versand, und der fragt die weitere. Angeglichen; die
Erwartung in `run_sitzung_versand.php` („genau die eine“) ist
entsprechend geändert.

## E32 — Das SVG-Logo wird am geparsten Dokument geprüft, nicht am Text

**Datum:** 14.10.2026 · **Version:** v0.9.104 · **Anlass:** Review aus
`koordination` (`BEFUND-2026-10-14-review-sprechtag-1.md`, Commit
`a700ce2`), Befund 4. **Gewichtung des Betreibers (14.10.2026):** bleibt
wichtig trotz HttpOnly – ein Skript auf derselben Domain kann das Cookie
nicht mehr lesen, aber im Namen der Lehrkraft handeln; die Anfragen gehen
als gleiche Seite mit. Reihenfolge der offenen Befunde: 4, dann 1, dann
5 bis 9.

**Lage:** `marke_svg_sicher()` suchte mit Mustern im Text. Der XML-Parser
löst Zeichenreferenzen auf – `&#x6A;avascript:` bestand; `foreignObject`
fehlte; ein nicht wohlgeformtes oder leeres Dokument ging durch. Das Logo
wird öffentlich unter `/api/einstellungen/logo` mit `image/svg+xml` vom
eigenen Ursprung ausgeliefert.

**Entschieden (Betreiber):** Die Prüfung gehört an das geparste Dokument.

**Gebaut:** `marke_svg_grund()` lädt das SVG mit `DOMDocument`
(`LIBXML_NONET`, ohne Entitätsersetzung) und sieht Knoten und
**aufgelöste** Attributwerte an. Abgelehnt: kein Parser, nicht
wohlgeformt, Wurzel nicht `svg`, DOCTYPE, Verarbeitungsanweisungen,
`script`/`foreignObject`/`iframe`/`object`/`embed`, Ereignisattribute,
Animationen auf `href`, Skript-Adressen in irgendeinem Attribut (nach
Entfernen von Leer- und Steuerzeichen), Adressen außer `#…` und
`data:image/(png|jpeg|gif)`, CSS mit `@import` oder `url()` nach außen.
Die Ablehnung nennt den Grund.

**Zwei Festlegungen der Entwicklung:**
- **Geschlossen scheitern:** Fehlt die Erweiterung `dom`, wird jedes SVG
  abgelehnt, mit dem Hinweis auf PNG. Ob der Server `dom` hat, ist **nicht
  gemessen** (`ssh` vom Arbeitsrechner abgewiesen); fehlt sie, sagt es
  der erste SVG-Upload.
- **Externe Adressen sind abgelehnt,** nicht nur Skript-Adressen: REIHEN-
  REGELN 7 „keine externen Ressourcen“, und eine externe Adresse in einem
  Logo lädt beim Betrachter von fremdem Server.

**Nicht gebaut, gemeldet:** eine zweite Sicherung an der Auslieferung
(`Content-Security-Policy` und `X-Content-Type-Options: nosniff` beim
Logo, oder Auslieferung als Download). Die Review nennt sie; der Auftrag
nannte die Prüfung am geparsten Dokument. **Ein bereits gespeichertes
Logo wird nicht nachgeprüft** – ob im Betrieb ein SVG-Logo liegt, ist
nicht gemessen.

**Nachtrag 14.10.2026 (v0.9.104, vor der Auslieferung) — zweimal dieselbe
Lage übersehen, nachdem sie an einer dritten Stelle gerade getrennt
worden war.** Beim Bau hatte ich erkannt, dass eine Skript-Adresse im
`href` an **zwei Stufen** hängen bleibt (Skriptprüfung und Liste erlaubter
Adressen), und die Prüfung deshalb auf den **Wortlaut des Grundes**
gestellt. Dieselbe Lage stand zweimal daneben, und ich sah sie nicht:
- **Animation:** `<animate attributeName="xlink:href" values="&#106;…">`
  lehnen die Animationsregel **und** die Skriptprüfung (am Attribut
  `values`) ab. LG4 nahm die Animationsregel heraus, rot wurde die
  Wortlautprüfung – nicht die Zeile, die ich als erwartete eingetragen
  hatte. Die Mutation war richtig, die Erwartung falsch.
- **CSS:** `@import url(https://…)` lehnen die `@import`-Regel **und** die
  `url()`-Regel ab, und beide meldeten **denselben Satz**. LG8 nahm
  `@import` heraus und blieb grün. Ein Fall, den nur `@import` abwehrt
  (`@import "…"` ohne `url()`), fehlte in den Daten (Art 6, Befund
  11.10.).

Gefunden hat beides die Mutationsserie, nicht das Lesen – und erst, als
sie lief. **Was gefehlt hat, war die Frage an jede Regel, nicht nur an die
erste:** Lehnt eine andere Regel denselben Fall ebenfalls ab? Das
Regelwerk sagt seit v1.25.0 (REIHENREGELN 2), was dann folgt: die Stufen
melden sich verschieden, und die Gegenprobe prüft den Wortlaut. Ich hatte
es an einer Stelle angewandt und für die Datei als erledigt behandelt.

**Behoben (Betreiber, alle drei Punkte):** LG4 erwartet „Stufe
Animation“. Die CSS-Stufen melden sich verschieden („importieren eine
andere Datei (@import)“ / „laden etwas von außen (url())“). Neuer Fall
`@import "…"` ohne `url()`, gegen LG8 rot gezeigt, mit Prüfung des
Grundes; und für `@import url(…)` eine Prüfung, dass `@import` anschlägt.
