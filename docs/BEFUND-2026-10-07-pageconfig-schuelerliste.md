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
