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
