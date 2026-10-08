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
