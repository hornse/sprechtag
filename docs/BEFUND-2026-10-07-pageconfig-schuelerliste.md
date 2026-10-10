# Befund 07.10.2026 — Schülerliste aus WebUntis, Schild entbehrlich

Gemessen am 07.10.2026, abgelegt am 08.10.2026 (Stand des Codes:
ff3bc28, v0.9.52). Gemessen über die Sondierung (v0.9.50,
`backend/api/sondierung.php`) mit einem Lehrerkonto am Produktivsystem.

**Gemessen ist: Der Schild-Import ist entbehrlich.** Nicht gemessen ist,
dass die lokale `schueler`-Tabelle entfallen kann — sie trägt heute
mehr als den Import (Abschnitt 3).

Personendaten stehen hier keine: nur Zählwerte. Beispielkennungen aus
der Messung sind bewusst weggelassen (REIHENREGELN Abschnitt 9).

---

## 1 — Gemessen

`GET /WebUntis/api/public/timetable/weekly/pageconfig?type=5`

- 1314 Einträge. Felder u. a. `id`, `name`, `forename`, `longName`,
  `externKey`, `klasseId`, `klasseOrStudentgroupId`, `classteacher`,
  `classteacher2`.
- 1235 mit `klasseId`, 1307 mit `externKey`.
- 1314 von 1314 Kennungen kommen auch in `getStudents` vor
  → **derselbe Nummernkreis**, keine Zuordnung über Namen nötig.

Die 79 ohne `klasseId` sind Beurlaubte, Externe, alte Backup-Konten und
Testschüler (Auskunft des Betreibers, nicht gemessen). Sie gehören nicht
in die Einladungsauswahl — **das Fehlen der Klasse ist Merkmal, nicht
Lücke.**

`getKlassen` (JSON-RPC): 40 Klassen mit `id`, `name`, `longName`,
`active`, `teacher1`, `teacher2`; 36 mit Klassenleitung. Die
Lehrkraft-Kennungen liegen im Nummernkreis der `personId` (geprüft gegen
die `personId` des messenden Lehrerkontos).

Ehemalige filtert WebUntis selbst: `getStudents` liefert 3802 (alle je
angelegten), `pageconfig` nur die 1314 aktiven. Inaktive erscheinen auch
in der Empfängersuche nicht (geprüft).

**Folgt daraus:** Der Schild-CSV-Import wird weder für die Klasse noch
für das Austrittsdatum gebraucht. Die lokale `schueler`-Tabelle hat 3801
Einträge, alle ohne Klasse — der Import ist nie gelaufen; die Einträge
stammen aus `getStudents`.

## 2 — Zwei Nummernkreise

Der Empfängerfilter (`messages/recipients/CUSTOM/filter` mit
`CLASS`+`ROLE`) liefert `user.id`s in einem **anderen** Kreis als
`getStudents`: Dasselbe Kind trägt dort eine andere Kennung (an einem
Beispiel gemessen). `pageconfig` ist die Sicht, die zum Datenmodell
passt.

Beim Versand an Eltern wird weiterhin die `user.id` gebraucht — die
Übersetzung zwischen den Kreisen bleibt ein Thema (Abschnitt 5).

## 3 — Was die `schueler`-Tabelle heute trägt

Ermittelt mit `grep` über `backend/`, Stand ff3bc28.

**Sieben Anzeigestellen**, alle als `LEFT JOIN schueler s ON
s.webuntis_id = …`, um Kindnamen in Lehrkraft- und Verwaltungsansichten
zu zeigen:

| Stelle | verbunden über |
|---|---|
| `backend/api/buchungen.php:252` | `b.schueler_id` |
| `backend/api/buchungen.php:329` | `b.schueler_id` |
| `backend/api/buchungen.php:761` | `e.schueler_id` (einladungen) |
| `backend/api/kalender.php:147` | `b.schueler_id` |
| `backend/api/kalender.php:191` | `b.schueler_id` |
| `backend/api/index.php:396` | `b.schueler_id` |
| `backend/api/index.php:1024` | `m.schueler_id` (mitteilungen) |

Dazu zwei Stellen, die nicht anzeigen, sondern entscheiden:

| Stelle | Wofür |
|---|---|
| `backend/api/buchungen.php:799–806` | Plausibilitätsprüfung beim Einladen: Die ID muss in der Liste vorkommen — **aber nur, wenn die Liste nicht leer ist** |
| `backend/api/mitteilungen.php:300` `mit_eltern_ids_ermitteln()` | Kindname für die Empfängersuche (Abschnitt 5) |

**Der Kern:** Fiele die Tabelle weg oder würde geleert, käme **nirgends
ein Fehler.** Die sieben Anzeigen zeigten leere Namen, die
Einladungsprüfung würde still übersprungen, und die Empfängersuche
suchte mit leerem Namen. Schweigen, das wie Bestehen aussieht.

Entbehrlich ist dagegen tatsächlich, was nur dem Import dient:
`backend/api/schueler.php` (CSV-Teil, Austrittsdatum), `sql/05`,
`sql/07`, `tests/run_schueler.php`, `tests/run_austritt.php`.
`docs/SCHUELERLISTE.md` beschreibt den Import und wäre mit anzupassen.

**Für den Umbau heißt das:** Die Namensanzeige braucht einen Ersatz
(etwa aus `pageconfig` zur Laufzeit, oder beim Einladen festgehalten —
gespeichert wird nur, was eine konkrete Einladung oder Buchung braucht),
und die Einladungsprüfung eine neue Quelle (die 1235 mit Klasse).

## 4 — Offene Punkte

> **Frage 1 beantwortet (gemeldet 08.10.2026): ein Kreis.** Siehe
> Abschnitt 6. Der Text darunter bleibt als damaliger Stand stehen.

**Frage 1 — Welchen Nummernkreis trägt `user.students[].id` im
Eltern-Login?** Das ist die Kennung, die in `buchungen.schueler_id`
landet (`sql/02_sprechtag.sql:124`). Gemessen ist nur `pageconfig` gegen
`getStudents` — nicht gegen `user.students[].id`. Wäre es der Kreis des
Empfängerfilters, passten Einladungen (aus der Auswahl) und Buchungen
(aus dem Login) nicht zusammen.

*Vermutung (beide Seiten):* der `getStudents`-Kreis — sonst hätten die
JOINs oben nie Namen geliefert. **Das ist ein Schluss, kein Beleg.**

*Prüfweg:* Buchungen gibt es keine (`COUNT(*) = 0`, sauberer Schnitt am
07.10.2026), der naheliegende Abgleich entfällt deshalb. Stattdessen:
über `einladungen`, falls dort etwas steht — sonst über einen
Eltern-Login, an dem sich zeigt, welche Kennung `user.students[].id`
trägt. Gezählt bzw. verglichen wird, nicht ausgegeben.

**E8 („Schild-Import entfällt") wird erst geschrieben, wenn Frage 1
beantwortet ist.**

**Frage 2 — Ist `pageconfig` über den beim Login festgehaltenen
Sitzungscookie erreichbar?** Die Sondierung baut eine eigene Sitzung
auf. Derselbe Mechanismus wie seit v0.9.48 für die Mitteilungen, also
wahrscheinlich unproblematisch — ungeprüft. Gehört vor den Umbau.

> **Frage 3 (nachgetragen 08.10.2026):** Liegt die `personId` eines
> Schülerkontos im `getStudents`-Kreis? Siehe Abschnitt 7.

## 5 — `mit_eltern_ids_ermitteln()` entscheidet über den Namen

`backend/api/mitteilungen.php`, ab Zeile 297. Die Funktion holt den
Kindnamen aus der `schueler`-Tabelle, sucht damit über die
WebUntis-Empfängersuche und **nimmt die Treffer mit exakt
übereinstimmendem Namen.** Der Abgleich schränkt also nicht ein — **er
entscheidet**, welche Eltern-`user.id`s eine Mitteilung bekommen.

FALLSTRICKE Abschnitt 6: „Über den Namen wird nie zugeordnet — im
Bestand stehen gleiche und fast gleiche Namen nebeneinander."

So gebaut, weil es keinen anderen Weg von der Kind-ID zu den
Eltern-`user.id`s gab. **Ob das gegen FALLSTRICKE 6 verstößt oder eine
begründete Ausnahme ist, gehört an `koordination`.**

Für sprechtag ist die Stelle doppelt wichtig: Hier findet der
Kreiswechsel aus Abschnitt 2 statt — und genau hier muss der Umbau der
Einladungsauswahl ansetzen.

---

## Nicht in diesem Zug

- Umbau der Einladungsauswahl — eigener Zug, nach dem Ablauffall der
  Lehrkraft-Sitzung.
- E8 — nach Frage 1.
- Meldung zu Abschnitt 5 an `koordination` — noch nicht geschrieben.

---

## 6 — Nachtrag: Frage 1 beantwortet

Gemeldet am 08.10.2026 vom Betreiber; das Datum der Messung selbst ist
nicht festgehalten.

**Prüfweg:** An einem Elternkonto mit zwei Kindern in sprechtag
angemeldet, `GET /api/auth/me` gelesen und die Kind-Kennungen aus
`user.students[].id` gegen `schueler.webuntis_id` gehalten. Keine
Kennungen in diesem Text.

**Ergebnis:** Beide Kind-Kennungen stehen als `webuntis_id` in der
`schueler`-Tabelle — also im `getStudents`-Kreis. Dieselbe Kennung
verwendet der Stundenplan-Abruf des Elternkontos (`resources=`). Die
Vermutung aus Abschnitt 4 ist damit **belegt**, nicht mehr nur
geschlossen.

**Die Kreise, soweit gemessen:**

| Kreis | Wo er vorkommt |
|---|---|
| Kind-Kennung | `getStudents`, `pageconfig`, `user.students[].id`, Stundenplan `resources=`, `buchungen.schueler_id` |
| Eltern-`user.id` | `recipientUserIds` beim Versand |
| `person_id` | Eltern-Login; bei lernzeiten `recipientPersonIds` |

**Nur ein Kreiswechsel ist nötig**, und zwar dort, wo er heute schon
passiert: `mit_eltern_ids_ermitteln()`, von der Kind-Kennung zu den
Eltern-`user.id`s (Abschnitt 5).

**Wie `buchungen.schueler_id` dazugehört** (aus dem Code, nicht
gemessen): Im Eltern-Weg muss die Kennung in den Kindern der Sitzung
stehen (`auth_kind_erlaubt()`, `backend/api/buchungen.php:557`), kommt
also aus `user.students[].id`. **Nicht geprüft** ist der Weg
volljähriger Schüler: Dort wird die eigene `personId` als Kind-Kennung
eingesetzt (`backend/api/webuntis_adapter.php:101`). Ob die `personId`
eines Schülerkontos im `getStudents`-Kreis liegt, ist nicht gemessen.

Daraufhin angelegt: E8 in `docs/ENTSCHEIDUNGEN.md`.

---

## 7 — Nachtrag: Frage 3 (offen)

Nachgetragen am 08.10.2026. Zum Datum in Abschnitt 6: Die Messungen
liefen am 07.10.2026 und wurden am 08.10.2026 gemeldet.

**Frage 3 — Liegt die `personId` eines Schülerkontos im
`getStudents`-Kreis?**

Volljährige Schüler melden sich selbst an; dabei wird ihre eigene
`personId` als Kind-Kennung eingesetzt
(`backend/api/webuntis_adapter.php:101`). Ob diese Kennung im selben
Kreis liegt wie `user.students[].id` bei Eltern, ist **ungemessen**.

**Warum es zählt:** Bei einem Elternkonto sind `person_id` und
`user_id` nachweislich verschieden (an einem Konto gesehen). Liegt die
Schüler-`personId` in einem anderen Kreis, trüge eine Buchung
volljähriger Schüler eine Kennung aus einem anderen Kreis als die der
Eltern — die JOINs auf `schueler` lieferten keinen Namen, der
Stundenplan-Abruf ginge ins Leere oder auf jemand anderen. **Still,
ohne Fehlermeldung.**

**Prüfweg:** Mit einem volljährigen Schülerkonto in sprechtag anmelden,
`GET /api/auth/me` aufrufen und die Kind-Kennung gegen
`schueler.webuntis_id` halten — derselbe Weg wie bei Frage 1.
Alternativ ein Testkonto mit `personType 5`. Keine Kennungen in den
Bericht.

---

## 8 — Nachtrag 08.10.2026: Messweg für Frage 2 (v0.9.54)

`GET /api/messung/sitzung` misst aus der **laufenden** Sitzung der
aufrufenden Person, über `mit_rest_aus_sitzung()` — denselben Weg wie der
Mitteilungsversand. Die Antwort enthält nur Zahlen:

1. `pageconfig?type=5`: Status, Einträge, Einträge mit `klasseId`;
2. bei Eltern je eigenem Kind („Kind 1“, „Kind 2“): Status des
   Stundenplan-Abrufs und Zahl der Lehrkraft-Kürzel — ausgewertet mit
   `rest_lehrkraefte_aus_entries()`, derselben Funktion wie der Betrieb
   (sie liest die Elemente nach `type`, nicht nach Positionsnummer).

Ist die Sitzung nicht nutzbar, sagt die Antwort warum: kein Cookie,
kein Token — mit Nachprobe, ob WebUntis eine Anmeldeseite lieferte
(abgelaufen) oder nicht erreichbar war —, oder eine Ausnahme mit Klasse
und Meldung.

Die Route ist eine **Messung, kein Feature** (Kopf von
`backend/api/messung_sitzung.php`). Nach dem Befund wird entschieden, ob
sie verschwindet oder Grundlage von Zug 4 wird.

**Frage 2 ist damit noch nicht beantwortet** — die Antwort sind die
Zahlen aus je einem Aufruf als Lehrkraft und als Elternteil.

---

## 9 — Nachtrag 08.10.2026: Frage 2 beantwortet

**Gemessen** am 08.10.2026 über `GET /api/messung/sitzung` (v0.9.54), je
direkt nach dem Login. Zahlen wie gemeldet; keine Kennungen.

| | Lehrkraft-Sitzung | Eltern-Sitzung |
|---|---|---|
| Sitzung | nutzbar, kein Grund | nutzbar, kein Grund |
| `pageconfig?type=5` | Status 200, **1314** Einträge, **1235** mit Klasse — identisch zur Sondierung | Status 200, **2** Einträge, 1 mit Klasse |
| Stundenplan (11.09.–08.10.2026) | entfällt | Kind 1: Status 200, 73 Einträge, 10 Lehrkräfte · Kind 2: Status 200, 0 Einträge, 0 Lehrkräfte — **KEIN Befund** |

**Antwort auf Frage 2: Ja.** Der beim Login festgehaltene Cookie trägt
beide Abrufe. `pageconfig` liefert in der Lehrkraft-Sitzung die volle
Liste; in der Eltern-Sitzung filtert WebUntis auf die eigenen Kinder —
eine Rechteprüfung, kein Fehler. Der Stundenplan eines eigenen Kindes ist
über die Eltern-Sitzung abrufbar; die Lehrkraft-Ermittlung kann damit
ohne Dienstkonto laufen.

**Grenzen dieser Antwort:**
- Belegt an **einem** Kind mit Ergebnis. Kind 2 lieferte 0 Einträge; das
  ordnet der Betreiber ein. Bis dahin bleibt es „KEIN Befund“. Hat das
  Kind einen Stundenplan, ist das ein eigener Fund.
- Die Lebensdauer der Sitzung (25–30 Minuten) ist an Lehrkräften
  gemessen (lernzeiten, 29.09.2026), nicht an Eltern. Nach Ablauf bleibt
  das Dienstkonto Rückfall.

### Einordnung des Betreibers — und wo sie nicht trägt

Die Einordnung lautete: `pageconfig` taugt als Quelle der Schülerliste
nur in einer Lehrkraft-Sitzung, und genau dort werden fremde Kindnamen
gebraucht — die sieben JOIN-Stellen (Abschnitt 3) dienen Lehrkraft- und
Verwaltungsansichten; im Eltern-Kontext stehen die eigenen Kinder in der
Sitzung (`auth_user()`); stellvertretende Buchung und
`mit_eltern_ids_ermitteln()` sind Lehrkraft-Kontext. **Eine Passung,
keine Einschränkung.**

**Nachgesehen im Code (Stand 2cca4c8), je Stelle:**

| Stelle | Route | Wer ruft | Namen gehen an | trägt die Einordnung? |
|---|---|---|---|---|
| `buchungen.php:336` | `GET /api/raster` | Eltern **und** Lehrkräfte | nur Lehrkraft/Verwaltung (`$istLehrkraft`) | ja — die Abfrage läuft auch für Eltern, die Namen gehen nicht hinaus |
| `buchungen.php:413` | `GET /api/buchungen?sicht=lehrkraft` | Lehrkraft/Verwaltung | Lehrkraft | ja |
| `buchungen.php:842` | `GET /api/einladungen` (Lehrkraftzweig) | Lehrkraft/Verwaltung | Lehrkraft | ja |
| `index.php:398` | `GET /api/lehrer-tischvorlage/…` | angemeldet | Lehrkraft | ja |
| `index.php:1037` | `GET /api/mitteilungen` | Lehrkraft/Verwaltung | Lehrkraft | ja |
| `kalender.php:147` | `kal_buchungen_laden()` ← `GET /api/kalender/{token}.ics` | **Kalender-App der Eltern, ohne Sitzung** | in die `.ics` („Kind: …“) | **nein** |
| `kalender.php:191` | `kal_lehrer_buchungen()` ← `GET /api/kalender/{token}.ics` (und `lehrer-kalender` mit Sitzung) | **Kalender-App der Lehrkraft, ohne Sitzung** | in die `.ics` (Titel mit Name und Klasse) | **nein** |

Die beiden Kalender-Abos werden über ein Token abgerufen, nicht über
eine Anmeldung (`index.php:117` ff., kein `auth_…`). Dort gibt es weder
eine Lehrkraft-Sitzung für `pageconfig` noch eine Eltern-Sitzung mit
`auth_user()`. **Für diese zwei Stellen muss der Name gespeichert
vorliegen** — etwa beim Buchen oder Einladen festgehalten, wie Abschnitt
1 es schon nennt („gespeichert wird nur, was eine konkrete Einladung oder
Buchung braucht“). Sonst stünde nach Zug 4 im Kalender „Termin“ statt des
Kindes, ohne Fehlermeldung — dieselbe stille Lücke wie in Abschnitt 3.

### Offene Punkte

- **Erinnerungen (Dienstkonto-Kontext):** Ob dort irgendwo ein Kindname
  gebraucht wird. Nach dem gebauten Stand ist die Erinnerung allgemein
  und nennt keine Namen — **geprüft ist das nicht.**
- **Kind 2:** siehe oben.
- Frage 3 (Abschnitt 7) bleibt offen.

---

## 10 — Nachtrag 08.10.2026: Kind 2 erklärt, Frage 2 geschlossen, Einordnung berichtigt

### Kind 2

Der Vermerk aus Abschnitt 9 bleibt: **KEIN Befund.** Erklärung
(Auskunft des Betreibers, nicht gemessen): Das Konto ist keiner Klasse
zugeordnet, also existiert kein Stundenplan. Die Zahlen passen dazu —
keine Klasse, 0 Einträge, 0 Lehrkräfte; im Eltern-`pageconfig` 1 mit
Klasse bei 2 Kindern. Das stützt die Einordnung der 79 Einträge ohne
`klasseId` aus Abschnitt 1: Konten ohne Klassenzuordnung gibt es, und
sie verhalten sich so.

**Frage 2 ist geschlossen.**

### Die ursprüngliche Einordnung war falsch

Gemeldet wurde am 08.10.2026 im Wortlaut:

> EINORDNUNG — die Eltern-Einschränkung ist keine:
> pageconfig taugt als Quelle für die Schülerliste nur in einer
> Lehrkraft-Sitzung. Das passt genau zum Bedarf:
> - Die sieben JOIN-Stellen auf schueler (buchungen.php,
>   kalender.php, index.php) dienen Lehrkraft- und
>   Verwaltungsansichten: Raster, Terminliste, Export, Kalender. Dort
>   ist eine Lehrkraft angemeldet, dort liefert pageconfig die volle
>   Liste.
> - Im Eltern-Kontext wird kein fremder Kindname gebraucht. Die
>   eigenen Kinder stehen mit Namen in der Sitzung (auth_user(),
>   sichtbar unter /api/auth/me).
> - Stellvertretende Buchung und mit_eltern_ids_ermitteln() sind
>   ebenfalls Lehrkraft-Kontext.
> Der Befund deckt also keine Einschränkung auf, sondern eine Passung.

**Das war an der entscheidenden Stelle falsch.** Die beiden Stellen in
`kalender.php` gehören zu den Kalender-Abos. Die Abo-Route hat **keine
Sitzung**: Eine Kalender-App ruft sie mit einem Token ab, ohne Anmeldung.
Dort ist keine Lehrkraft angemeldet, und es gibt auch kein
`auth_user()` — weder `pageconfig` noch die Namen aus der Sitzung stehen
zur Verfügung. Die Tabelle in Abschnitt 9 ist die berichtigte Fassung;
dieser Abschnitt hält fest, wovon sie abweicht, damit sie später nicht
für die ursprüngliche Überlegung gehalten wird.

**Was die falsche Einordnung angelegt hätte:** einen Umbau, nach dem im
Kalender „Termin“ statt „Kind: …“ stünde — ohne Fehlermeldung.

**Daraus festgelegt für Zug 4:** Der Kindname wird **beim Buchen
festgehalten**, nicht zur Laufzeit geholt. Eingetragen als Nachtrag zu
E8 in `docs/ENTSCHEIDUNGEN.md`.

Weiterhin offen: Erinnerungen (Abschnitt 9), Frage 3 (Abschnitt 7).

---

## 11 — Nachtrag 08.10.2026: Klassenleitung — pageconfig trägt sie nicht

**Gemessen** (v0.9.55, `GET /api/messung/sitzung`, Teil `klassenleitung`,
gemeldet vom Betreiber): `classteacher` und `classteacher2` in
`pageconfig?type=5` sind **leer** — 0 von 1314 in der Lehrkraft-Sicht,
0 von 2 in der Eltern-Sicht, Format jeweils „leer“. Die Felder stehen in
der Antwort, tragen aber keine Werte.

**Frage „Woher kommt die Klassenleitung?“ für `pageconfig` beantwortet:
gar nicht.** Dass ein Feld in der Feldliste steht (Abschnitt 1), hieß
nicht, dass es gefüllt ist. Darauf zu bauen hätte eine Buchungsseite
ergeben, die still ohne Klassenleitung bleibt — die Messung vorab
(E10, Nachtrag) hat genau das verhindert.

### Stattdessen gefunden (Betreiber, mit einem Elternkonto im Browser)

`GET /WebUntis/api/rest/view/v1/timetable/filter?resourceType=CLASS&timetableType=STANDARD&start=…&end=…`
liefert unter `classes[]` je Klasse:

- `class`: `{id, shortName, longName, displayName}`
- `classTeacher1` / `classTeacher2`: `{id, shortName, longName, displayName}`

Die Klassenleitung also als Objekt, mit Kennung **und** Kürzel.

**Quergeprüft (Betreiber):** Eine Klassen-ID und die Kennung ihrer
`classTeacher1` stimmen mit dem `CUSTOM/filter`-Mitschnitt und der
`getKlassen`-Sondierung vom 07.10.2026 überein — ein Kreis. (Die
Kennungen selbst stehen bewusst nicht hier.)

**Was das lösen würde — noch nicht gemessen über unsere Sitzung:**
- Kein Dienstkonto, keine neue Tabelle: Die Buchungsseite könnte es aus
  der Eltern-Sitzung holen. Stammdaten-Sync und `getKlassen` über das
  Dienstkonto entfielen.
- Über `shortName` gibt es einen zweiten Weg zu `lehrer.kuerzel`. Passen
  Kennung **und** Kürzel zur selben Lehrkraft, ist die Zuordnung doppelt
  belegt.
- Die Eltern-Sicht enthält nur die Klassen der eigenen Kinder — für die
  Buchungsseite richtig, als allgemeine Klassenliste untauglich.

**Offen — gemessen ab v0.9.56:** Trägt der Login-Cookie den Abruf? Sind
`classTeacher1/2` gefüllt, passen `id` und `shortName`, und **hängt die
Antwort vom Zeitraum ab** (Schulzeit gegen Ferien)?

### Gemessen über unsere Sitzung (v0.9.56, gemeldet vom Betreiber am 08.10.2026)

`GET /api/messung/sitzung`, Teil `klassenfilter`, einmal als Lehrkraft
und einmal als Elternteil, jeweils mit Ferienzeitraum. Die Zahlen hat
der Betreiber gemeldet. Ich habe sie nicht selbst abgelesen.

**Doppelabgleich geht restlos auf (Lehrkraft-Sicht):**

| | gefüllt | `id_passt` | `kuerzel_passt` | `beide_dieselbe` |
|---|---|---|---|---|
| `classTeacher1` | 36 | 36 | 36 | 36 |
| `classTeacher2` | 34 | 34 | 34 | 34 |

Bei keiner Klasse zeigen Kennung und Kürzel auf verschiedene Lehrkräfte.
**Damit ist die Frage „passen die Kennungen zu `lehrer.webuntis_id`“
beantwortet, und zwar doppelt belegt.**

**Der Zeitraum spielt keine Rolle:** 40 Klassen stehen in beiden
Zeiträumen. Bei allen 40 ist die Leitung gleich, bei keiner anders, und
keine Klasse kommt nur in einem Zeitraum vor. Das gilt auch über die
Herbstferien (19.–29.10., vom Betreiber angegeben). Die Buchungsseite
muss deshalb keinen Unterrichtszeitraum wählen.

**Die Eltern-Sicht trägt es, ohne Dienstkonto:**
- Kind 1: `klasse_gefunden` true, beide Leitungen gefüllt,
  `beide_dieselbe` true, `gleiche_leitung_in_beiden` true.
- Kind 2: keine Klasse. `klasse_gefunden` false,
  `gleiche_leitung_in_beiden` null. Kein Absturz, kein falsches
  Ergebnis.

**Klassen ohne Leitung:** 4 von 40 haben kein `classTeacher1`, 6 haben
kein `classTeacher2`. Das passt zu den gefüllten Zahlen oben
(40 − 4 = 36, 40 − 6 = 34).

**Was ändert dieser Beleg an dem, was wir prüfen?** Zwei Ausprägungen
müssen in die Testdaten der Buchungsseite:
- eine Klasse ohne Leitung bzw. mit nur einer Leitung. Erwartet: keine
  Hervorhebung, kein Fehler.
- ein Kind ohne Klasse.

Beide sind belegt und gehören als Fälle in die Prüfungen, nicht als
Annahme in den Code.

**Folge:** Zug 3 ist baubar. Die Quelle für die Klassenleitung ist
`timetable/filter?resourceType=CLASS` über die Eltern-Sitzung, die
Zuordnung läuft über `klasseId` (pageconfig) auf `class.id` (E10,
Nachtrag).

---

## 12 — Nachtrag 09.10.2026: Eltern-Zuordnung (Auskunft) und Frage 3 beantwortet

### Auskunft des Betreibers zur Quelle – nicht gemessen

1. **Schild führt eine Kennung für Schüler (Schild-ID), aber keine für
   Eltern.** Eltern stehen in der Quelle nicht als eigene Datensätze mit
   Kennung. Das erklärt, warum `mit_eltern_ids_ermitteln()` (Abschnitt 5)
   über den Namen geht: Von der Kind-Kennung zu den Eltern gibt es keinen
   Schlüssel. **Kein Versäumnis, sondern die Folge der Quelle.**
2. **Beim Einpflegen neuer Eltern wird über die Schild-ID des Kindes
   identifiziert:** zwei Elternteile ergeben zwei CSV-Zeilen mit derselben
   Kind-ID. Die Zuordnung Kind → Eltern ist in WebUntis damit **fest
   hinterlegt, nicht erschlossen.**

**Folgerung (geschlossen, nicht gemessen):** Diese hinterlegte Verknüpfung
nutzt vermutlich `recipientOption PARENTS`. Der Namensabgleich in
`mit_eltern_ids_ermitteln()` baut eine Brücke nach, die daneben bereits
besteht – schlechter, weil Namen mehrdeutig sein können und die
hinterlegte Verknüpfung nicht. Trägt `PARENTS`, würden bei zwei
Elternteilen beide erreicht, ohne Zutun (lernzeiten maß vier Empfänger bei
einer Kind-Kennung). Der Namensweg findet dagegen nur, wen die
Empfängersuche unter dem Kindnamen ausgibt.

Die `externKey`-Spalte aus `pageconfig` (1307 von 1314 gefüllt,
Abschnitt 1) ist die Schild-ID der **Schüler** und wäre als Brücke
nutzbar, falls je nötig. Für Eltern gibt es diese Möglichkeit nicht.

### Frage 3 beantwortet: ein Kreis

Gemeldet vom Betreiber am 09.10.2026. **Prüfweg:** mit einem volljährigen
Schülerkonto angemeldet, `GET /api/auth/me` gelesen. Keine Kennungen in
diesem Text.

**Ergebnis:** `person_id` und die Kind-Kennung in `kinder` sind identisch,
und es ist dieselbe Kennung, die im Elternkonto als zweites Kind stand und
gegen `schueler.webuntis_id` belegt wurde (Abschnitt 10). Die Kreise sind
also nicht „Erwachsene gegen Schüler“, sondern **„Person gegen
Benutzerkonto“**; bei Schülern fällt die Personenkennung mit der
Schülerkennung zusammen. Die Sorge aus Abschnitt 7 – eine Buchung
volljähriger Schüler trüge eine Kennung aus einem anderen Kreis – trifft
nicht zu.

**Vorbehalt:** Das Testkonto ist keiner Klasse zugeordnet und hat keinen
Stundenplan. Für den Nummernkreis spielt das keine Rolle; als Testfall
für einen volljährigen Schüler, der buchen will, taugt es nicht – ohne
Klasse gäbe es keine unterrichtenden Lehrkräfte und keine Kacheln.

**Gegenprobe gegen die Datenbank:** steht aus, der Betreiber reicht sie
nach.

**Was ändert dieser Beleg an dem, was wir prüfen?** Am Code nichts: Der
Weg setzt die `personId` schon heute als Kind-Kennung ein
(`backend/api/webuntis_adapter.php`), und die ist nun belegt im richtigen
Kreis. Für die Prüfdaten heißt es: Ein Testfall „volljähriger Schüler“
darf `person_id` und Kind-Kennung gleich setzen – das ist jetzt belegt,
nicht angenommen.

### Offene Punkte (neu)

- **Erreicht der Namensweg alle Elternteile?** Ob die Empfängersuche
  unter dem Kindnamen immer alle Elternteile ausgibt, hat nie jemand
  geprüft. Bei zwei Elternteilen mit verschiedenen Nachnamen oder bei
  Namensgleichheit ist das offen. Die Messung von `PARENTS` würde es
  beantworten (nicht in diesem Zug).
- **Volljährige Schüler mit Klasse:** Gibt es sie, und erscheinen ihre
  unterrichtenden Lehrkräfte? Separat zu prüfen; das vorhandene Testkonto
  hat keine Klasse.
- **Gegenprobe Frage 3 gegen die Datenbank** – nachgereicht vom Betreiber.

---

## 13 — Nachtrag 09.10.2026: Benutzergruppe über profile/general (gemessen)

Gemessen vom Betreiber am 09.10.2026 über `GET /api/messung/sitzung`
(v0.9.64), je Rolle, über den Sitzungscookie von sprechtag. Gruppennamen
sind von der Schule vergeben; keine Personenangaben.

| Rolle | Status | `userGroup` (Text) | `userRoleId` |
|---|---|---|---|
| Lehrkraft | 200 | „Lehrkräfte“ | 2 |
| Eltern | 200 | „01_Eltern Attest“ | 12 |
| Schülerin | 200 | „SuS über 18“ bzw. „SuS über 18 mit Atte“ | 5 |

- **Eine Person, ein Gruppenwert**, keine Liste – belegt durch Umsetzen
  desselben Kontos in die zweite Gruppe.
- **Keine Kennung**, nur der Text. Der Empfängerfilter kennt referenceId
  25 bzw. 45; `profile/general` liefert sie nicht.
- **Der Name ist auf 20 Zeichen gekürzt** („SuS über 18 mit Atte“ statt
  „…Attest“) – in beiden Quellen gleich: eine Feldbegrenzung in WebUntis,
  keine Eigenheit einer Ansicht.
- `userRoleId` 12 für Eltern war bisher unbekannt.

**Was ändert dieser Beleg an dem, was wir prüfen?** Drei Fälle gehören in
die Prüfdaten und stehen dort (`tests/run_schueler_gruppe.php`): Eltern
mit eigener Gruppe (dürfen nicht gesperrt werden), die gekürzte zweite
Gruppe (muss treffen, auch wenn der volle Name eingetragen ist), die
Grenze bei genau 20 Zeichen. Umgesetzt in v0.9.65 (E15).

---

## 14 — Nachtrag 09.10.2026: Quelle für eine Auswahlliste der Gruppen

Anlass: Die zugelassenen Gruppen (E15) werden von Hand eingetippt, mit der
Kürzungsfalle auf 20 Zeichen. Gesucht ist eine Quelle für eine Auswahlliste.

### Auskunft des Betreibers (Browser, Admin-Konto) – nicht über unsere Sitzung gemessen

**`GET /WebUntis/api/userrole/config`** liefert unter `data.userGroups` alle
22 Benutzergruppen, je Eintrag `id`, `label`, `userCount`, `userRole`,
`userCountByUserRole`.
- Die `id` ist dabei: 25 für „SuS über 18“, 45 für „SuS über 18 mit Atte“ –
  dieselben Kennungen wie in den Mitschnitten des Empfängerfilters.
- Das `label` ist **bereits gekürzt** („SuS über 18 mit Atte“), wie in
  `profile/general`.
- `userRole` trennt systemeigene Gruppen (2 Lehrkraft, 5 Schüler, 12
  Erziehungsberechtigte) von schuleigenen (−1).
- `userCountByUserRole` zeigt, wer in einer Gruppe ist: „SuS über 18“ 185
  Schüler, „01_Eltern Attest“ 7 Erziehungsberechtigte.

**`usergrouplist.do`: geprüft und verworfen** – eine HTML-Seite (Struts),
keine maschinenlesbare Antwort.

### Messung über unsere Sitzung – ausstehend

`GET /api/messung/sitzung` misst seit v0.9.67 je Rolle: Status, ob
`data.userGroups` kommt, Anzahl der Einträge, Felder eines Eintrags (mit
Format), Gruppen mit Schülern, und ob das `label` der eigenen Gruppe
**zeichengenau** dem Text aus `profile/general` gleicht. Nicht belegt ist die
Form von `userCountByUserRole` (Objekt oder Liste) – die Messung erkennt
beides und nennt das Format.

### Nachtrag 09.10.2026: Messung über unsere Sitzung (v0.9.67, Betreiber)

- `/api/userrole/config` antwortet **nur der Verwaltung**: Admin 200,
  Lehrkraft 403, Eltern 403, Schülerin 403.
- 21 Gruppen; Felder `id` (Zahl), `label` (Text), `userCount` (Zahl),
  `userRole` (Zahl), `userCountByUserRole` (**Objekt**).
- Vier Gruppen tragen Schüler: „Student“ (1755), „SuS über 18“ (185),
  „SuS über 18 mit Atte“ (13), „I-Helfer*in“ (1).
- Abgleich der eigenen Gruppe: **„nein“** – das Admin-Konto trägt in
  `profile/general` „Administration“, in der Liste heißt die Gruppe „Admin“.
  Vermutung (nicht gemessen): systemeigene Gruppen (`userRole` ≠ −1) kommen in
  der Liste mit dem englischen Schlüsselnamen, in `profile/general`
  übersetzt; schuleigene (−1) haben keine Übersetzung. Für die
  Volljährigen-Gruppen (schuleigen) ist der Abgleich **nicht gemessen** –
  ein Schülerkonto darf die Liste nicht abrufen.
- Drei Darstellungen desselben Namens (Auskunft Betreiber): `userrole/config`,
  `profile/general` und der Mitteilungs-Filter kürzen auf 20 Zeichen; nur der
  persönliche Bereich einer Person in WebUntis zeigt den vollen Namen.

Umgesetzt in v0.9.68 (E15-Nachtrag).

---

## 15 — Nachtrag 09.10.2026: Messung recipientOption PARENTS vorbereitet (v0.9.69)

**Frage:** Erreicht `recipientOption: "PARENTS"` mit der Kennung des Kindes die
Erziehungsberechtigten auch auf unserem Pfad? Trägt es, entfällt
`mit_eltern_ids_ermitteln()` samt Namensabgleich (Abschnitt 5) – und damit
die Abweichung von FALLSTRICKE 6 und ein Kreiswechsel.

**Vorlage:** lernzeiten, gemessen 06.10.2026 für `POST /v2/messages` mit
`recipientPersonIds` (Beilage `docs/beilagen/lernzeiten-mitteilung-parents.md`).
sprechtag nutzt `POST /v2/messages/users` mit `recipientUserIds` – ob dieser
Pfad `recipientOption` kennt, ist offen.

**Messweg:** `POST /api/messung/parents` (nur Lehrkraft/Verwaltung, über die
eigene Sitzung) verschickt **genau eine** Testnachricht mit festem Betreff –
nur mit `"bestaetigt": true`, nur an `users` oder `messages`, ohne Kopie an
das Kind. Antwort: Status, `numberOfRecipients`, Schlüsselnamen, bei
Ablehnung Meldung und Prüfpfade; daneben die Zahl der Konten, die der
**Namensweg** für dasselbe Kind fände, und ob die Kennung in
`schueler.webuntis_id` steht. Keine Kennungen, keine Namen.

**Zur Kennung (Frage 3 der Messung):** Für Schüler fallen Personenkennung und
Kind-Kennung zusammen (Abschnitt 12). Die Messung kann „personId oder
Kind-Kennung“ deshalb nicht trennen; sie meldet, ob die verwendete Kennung im
Kreis der Schülerliste liegt.

**Abweichung zum Auftrag:** lernzeiten maß beim Testkind **vier** Empfänger
(dort als Testeltern hinterlegte Kolleginnen und Kollegen); im Auftrag ist von
zwei Elternkonten die Rede. Die Messung zeigt die Zahl.

### Offene Punkte (neu)

- **Mehrdeutigkeit im Namensweg:** Liefert die Empfängersuche in
  `mit_eltern_ids_ermitteln()` mehr als einen exakten Treffer, soll **nicht**
  gesendet, sondern angehalten und gemeldet werden – geschlossen scheitern
  statt raten. Gilt, solange der Namensweg im Code steht, auch wenn PARENTS
  trägt. (Nicht gebaut; Auftrag 09.10.2026.)
- **Vermerk zum Namensabgleich:** kein Eintrag in `bestand-ausnahmen.md`
  (Auskunft koordination: die ist für Vendoring-Abweichungen), sondern ein
  eigener Eintrag in `docs/ENTSCHEIDUNGEN.md` mit der Bedingung, unter der er
  endet – sobald die Messung ausgewertet ist.

---

## 16 — Nachtrag 09.10.2026: recipientOption PARENTS trägt – auf /v2/messages

Gemessen vom Betreiber am 09.10.2026, Produktivsystem, über
`POST /api/messung/parents` (v0.9.69) mit der Sitzung einer **Lehrkraft**, am
Testkind mit vier (Test-)Elternkonten. Keine Kennungen in diesem Text.

| Pfad | Ergebnis |
|---|---|
| `POST /v2/messages/users`, `recipientOption: "PARENTS"` | **Status 500**, leere Standardmeldung („Es ist ein Fehler aufgetreten. {0}“), keine Prüfpfade – **der Pfad kennt das Feld nicht** |
| `POST /v2/messages`, `recipientOption: "PARENTS"`, Kind-Kennung | **Status 200, `numberOfRecipients` 4**, Ergebnis „erreicht“; die Nachricht lag im **Elternpostfach** – belegt, nicht nur über die Zahl |

- **`kennung_in_schuelerliste`: ja.** Die Kind-Kennung aus dem Kreis der
  Schülerliste (`getStudents`/`pageconfig`) wirkt direkt – **kein
  Kreiswechsel** nötig.
- **Vergleich:** Der Namensweg (`mit_eltern_ids_ermitteln()`) fand für
  dasselbe Kind **ebenfalls 4 Konten**. Der offene Punkt „Erreicht der
  Namensweg alle Elternteile?“ (Abschnitt 12) ist damit **entwarnt** – er
  findet dieselben. Er ist nicht falsch, aber umständlicher und bei
  Namensgleichheit anfällig, wo PARENTS es nicht ist.
- Die Messung schickte keine Kopie an das Kind (`copyToStudent: false`); die
  4 sind die Eltern.

**Was ändert dieser Beleg an dem, was wir prüfen?** Heute nichts – gebaut wird
noch nicht. Für den Umbau: Der Erfolg hängt an `numberOfRecipients` ≥ 1 und an
der Kind-Kennung; der Testfall „Kind mit mehreren Elternkonten“ ist belegt
(vier), der Fall „Kind ohne hinterlegte Eltern“ ist es **nicht**.

> **Teilweise überholt (Nachtrag 09.10.2026, Abschnitt 18):** Die Angabe,
> Bestätigungen und Absagen liefen heute über das Dienstkonto, ist aus dem
> Ablauf **geschlossen, nicht am Code gelesen** – und stimmt so nicht. Der
> Absatz bleibt als damaliger Stand stehen.

**Nicht gemessen:**
- ob `/v2/messages` mit PARENTS auch über die **Sitzung des Dienstkontos**
  trägt (gemessen ist die Lehrkraft-Sitzung); heute versendet sprechtag
  Bestätigungen und Absagen mit hinterlegtem Dienstkonto über dessen Sitzung;
- wie die Antwort bei einem Kind **ohne** hinterlegte Eltern aussieht.

Folge: E16 in `docs/ENTSCHEIDUNGEN.md`.

---

## 17 — Nachtrag 09.10.2026: Messung über die Sitzung des Dienstkontos vorbereitet (v0.9.70)

> **Teilweise überholt (Nachtrag 09.10.2026, Abschnitt 18):** Die Angabe,
> Bestätigungen und Absagen liefen heute über das Dienstkonto, ist aus dem
> Ablauf **geschlossen, nicht am Code gelesen** – und stimmt so nicht. Der
> Absatz bleibt als damaliger Stand stehen.

Lücke aus Abschnitt 16: PARENTS ist nur über die **Lehrkraft**-Sitzung
gemessen. Bestätigungen und Absagen laufen heute über das **Dienstkonto** –
notwendigerweise, denn bei einer Buchung durch Eltern ist keine Lehrkraft
angemeldet. Ohne Messung hinge der Umbau (E16) an einer ungemessenen Annahme.

`POST /api/messung/parents` nimmt jetzt `"sitzung": "dienstkonto"` (nur die
Verwaltung). Die Sitzung wird geöffnet wie im Betrieb für Bestätigungen und
Absagen (Zugang aus `dk_lesen()`, `authenticate`, Token) und nach dem Versand
abgemeldet. Dieselben Sicherungen wie bisher: nur mit `"bestaetigt": true`,
fester Testbetreff, genau ein Versand.

Zu messen: (1) Trägt PARENTS über die Dienstkonto-Sitzung (Status,
`numberOfRecipients`)? (2) Dieselbe Empfängerzahl wie über die
Lehrkraft-Sitzung (4)? Dazu, wenn sich ein Kind **ohne** hinterlegte Eltern
finden lässt: wie die Antwort dann aussieht – sonst bleibt das offen.

---

## 18 — Nachtrag 09.10.2026: Richtigstellung zum Versandweg

In Abschnitt 16, Abschnitt 17 und E16 stand – sinngemäß –, Bestätigungen und
Absagen liefen heute über das Dienstkonto. **Herkunft des Fehlers:** aus dem
Ablauf geschlossen („bei einer Buchung durch Eltern ist keine Lehrkraft
angemeldet, also muss das Dienstkonto senden“), **nicht am Code gelesen.**

**Am Code gelesen** (Stand v0.9.70; Bestandsaufnahme
`docs/BESTAND-DIENSTKONTO-2026-10-09.md`):
- `mit_einreihen_und_senden()` nimmt **zuerst die Sitzung der angemeldeten
  Person**; das Dienstkonto greift nur, wenn es keine nutzbare Sitzung gibt.
  Scheitert der Versand mit Sitzung, gibt es keinen Rückfall aufs Dienstkonto.
- Die **Bestätigung nach einer Elternbuchung** bekommt **keine**
  Dienstkonto-Zugangsdaten: Sie versucht die Sitzung der Eltern, sonst bleibt
  sie „offen“.
- Absagen durch die Lehrkraft, Einladungen und die Bestätigung nach
  stellvertretender Buchung laufen zuerst über die Sitzung der Lehrkraft, das
  Dienstkonto nur als Rückfall.

**Was das an der Messung aus Abschnitt 17 ändert:** Die Frage „trägt PARENTS
über die Dienstkonto-Sitzung?“ bleibt sinnvoll für den Rückfall (abgelaufene
Sitzung), ist aber nicht die Frage nach dem **Normalweg**, als die sie gestellt
war. Der Normalweg ist die Sitzung der handelnden Person.


---

## 19 — Nachtrag 09.10.2026: Messteil Schülerliste vorbereitet (v0.9.75, Zug 4 Schritt 1)

**Lücke:** Gemessen sind die **Feldnamen** von `pageconfig` (Abschnitt 1)
und von `timetable/filter` (Abschnitt 11), nicht ihr **Inhalt**. „`longName`
ist der Nachname“ ist von `getStudents` übertragen. Den Klassennamen trägt
`pageconfig` gar nicht, nur `klasseId`; der Name muss aus
`timetable/filter` kommen. Welches Feld dort „6b“ trägt, ist nicht
gemessen.

**Messweg:** `GET /api/messung/sitzung`, als **Lehrkraft** (oder
Verwaltung), Teil `schuelerliste`:
- **Vergleichsmaßstab:** Je Kennung wird die alte Tabelle `schueler`
  herangezogen; ihre Spalten stammen aus `getStudents` (`foreName` →
  `vorname`, `longName` → `nachname`). Für `name`, `forename`, `longName`,
  `displayName` und `externKey` wird gezählt: gefüllt; gleich Nachname;
  gleich Vorname; gleich „Vorname Nachname“, „Nachname Vorname“ und
  „Nachname, Vorname“.
- **Klassen** (Filter der Schulzeit, vier Wochen bis heute): wie viele
  `klasseId` im Filter gefunden werden, Klassen ohne Schüler, die
  **Kurznamen** (`shortName`; Klassennamen sind keine Personendaten), ob sie
  eindeutig sind, und ob `longName` bzw. `displayName` dem Kurznamen gleichen
  oder ihn enthalten.
- **Antwort:** nur Zählwerte und die Kurznamen der Klassen. Keine Namen,
  keine Kennungen, kein `externKey`. Die Eltern-Sicht misst diesen Teil
  nicht.

**Grenze dieser Messung:** Sie zeigt, welches `pageconfig`-Feld dem
`getStudents`-Feld gleicht. Dass `getStudents.longName` der Nachname ist,
sagt die Dokumentation der JSON-RPC-Schnittstelle; das ist eine Auskunft,
keine Messung. Gestützt wird es dadurch, dass die Kind-Suche beim
stellvertretenden Buchen die Namen in dieser Form anzeigt. Gemeldet hat
niemand, dass sie verdreht wären. **Ein Beleg ist das nicht.** Den Blick
auf die Wirklichkeit liefert erst Schritt 3: Dann zeigt die
Einladungsauswahl die Namen aus `pageconfig`.

**Was die Antwort entscheiden soll:** welches Feld im Bau Nachname und
Vorname liefert, welches den Klassennamen, und ob Klassennamen eindeutig
genug sind, um nach ihnen zu gruppieren.

---

## 20 — Nachtrag 09.10.2026: Messteil ausgewertet (Betreiber)

Gemessen vom Betreiber über `GET /api/messung/sitzung` (v0.9.75), Teil
`schuelerliste`, mit einer Lehrkraft-Sitzung. Zahlen wie gemeldet; die Antwort
enthielt keine Namen.

**Namensfelder** (1314 verglichen, alle aktiven):

| Feld | Ergebnis |
|---|---|
| `longName` | = Nachname bei **1314 von 1314** |
| `forename` | = Vorname bei **1313 von 1314** |
| `name` | gefüllt, gleicht **keiner** Namensform (vermutlich eine Kennung) |
| `displayName` | **leer** (0 gefüllt) |
| `externKey` | 1307 gefüllt, keine Namensübereinstimmung (die Schild-ID) |

**Entschieden:** Nachname aus `longName`, Vorname aus `forename`.

*Randnotiz, kein Problem:* Ein Kind trägt in `pageconfig` einen anderen
Vornamen als in der alten Tabelle, etwa wegen einer Namensänderung oder
eines Zweitnamens. Die alte Tabelle ist also nicht überall aktuell. Das
spricht eher für den Umbau. Die drei Fälle, in denen `forename` dem
Nachnamen gleicht, sind vermutlich Kinder mit gleichem Vor- und Nachnamen
(Vermutung des Betreibers).

**Klassen** (`timetable/filter`, Schulzeit):
- Alle **1235** `klasseId` werden im Filter gefunden.
- **`displayName`** = Kurzname bei **40 von 40**: Er ist der Klassenname.
  `longName` trifft nur 34 und wird **nicht** verwendet.
- Die Kurznamen sind eindeutig.
- **Vier der 40 „Klassen“ sind keine Klassen:** `Veranst1`, `Veranst2`,
  `Veranst3` und `RaN1`. Es sind zugleich die vier ohne Schüler, vermutlich
  Veranstaltungsblöcke (Einordnung des Betreibers). Für Zug 4 spielt das
  keine Rolle, denn ohne Schüler erscheinen sie in keiner Auswahl. Für die
  Absenz-Idee ist es ein Ansatzpunkt und dort nachgetragen.

**Was ändert dieser Beleg an dem, was wir prüfen?** In die Prüfdaten von
Schritt 2 gehören: eine „Klasse“ ohne Schüler, an deren Namen sich kein
Kind hängt; ein Kind, dessen `forename` vom gespeicherten Namen abweicht
(der gespeicherte Name stammt aus `pageconfig`, nicht aus der alten
Tabelle); ein Klassenname aus `displayName`, der von `longName` abweicht.

## 21 — Nachtrag 10.10.2026: Die alte Tabelle fällt (v0.9.82, Zug 4 Schritt 4)

Mit v0.9.82 liest und schreibt kein Code die Tabelle `schueler` mehr;
`sql/23_schueler_entfernen.sql` entfernt sie nach `sql/23_pruefung.sql`.
Damit ist der Vergleichsmaßstab dieses Befunds fort: Der Messteil
`schuelerliste` (Abschnitt 19, ausgewertet in Abschnitt 20) ist aus
`/api/messung/sitzung` entfernt, ebenso die Auskunft
`kennung_in_schuelerliste` der Elternmessung. Die Zahlen aus Abschnitt 20
bleiben als Messung vom 09.10.2026 gültig; wiederholen lassen sie sich
nicht mehr.

**Warum die Engstelle aus Schritt 2 die verbliebenen Lesestellen nicht
sah:** Sie prüfte eine Schreibweise, nicht die Sache. Sie suchte `JOIN`
mit der Tabelle in allen Dateien und `FROM` in vier benannten. Die beiden
Lesestellen dieser Messung standen in `index.php` als `FROM`, außerhalb
der vier — grün, und richtig für das, was sie las. Dasselbe beim
Schemaleser in `tests/run_archivieren.php`, der `DROP TABLE` nicht las.
Beide sind geschärft; die Begründung steht im E20-Nachtrag zu Schritt 4
(`docs/ENTSCHEIDUNGEN.md`).

**Was ändert das an dem, was wir prüfen?** Die Engstelle prüft jetzt jede
PHP-Datei unter `backend/` samt Unterordnern auf `FROM`, `JOIN`, `INTO`,
`UPDATE` und `TABLE` vor dem Tabellennamen, und ihr Suchausdruck wird an
Formen geprüft, die er treffen und die er nicht treffen darf. Die
Prüfdatei zählt vor dem Entfernen, wie viele Namen auch die alte Tabelle
nicht kennt (`NICHT_FUELLBAR`); die Zahlen meldet der Betreiber, sie
werden hier nachgetragen.
