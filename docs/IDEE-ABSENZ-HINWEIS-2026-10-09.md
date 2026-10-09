# Idee 09.10.2026 — Absenz-Hinweis (notiert, nicht gebaut)

**Stand:** Idee des Betreibers, kein Auftrag. Nichts davon ist gebaut.
Mitschnitte und Auskünfte sind gekennzeichnet; **gemessen hat sprechtag
selbst noch nichts davon.**

## Die Idee

Heute trägt die Verwaltung einen Krankheitsausfall von Hand ein
(`POST …/lehrer/{lid}/ausfall`). Stattdessen könnte sprechtag aus WebUntis
lesen, wer als abwesend geführt wird, und darauf **hinweisen**: „Diese
Lehrkraft ist für heute als abwesend geführt – Termine absagen?“ Die
Verwaltung entscheidet weiterhin.

**Hinweis, nie Automatik.** Zwei Einschränkungen (Betreiber):
- Fehlende Lehrkräfte werden in UNTIS eingetragen und nach WebUntis
  exportiert. Wie aktuell die Angabe ist, hängt am Export-Rhythmus.
- Eine Absenz betrifft den Unterricht, nicht zwingend einen
  Nachmittagstermin. Zu dieser Einschränkung siehe Befund 2 unten.

## Befunde (Auskunft des Betreibers aus Mitschnitten, in sprechtag nicht gemessen)

1. **Der Stundenplan zeigt Ausfälle.** `GET /v1/timetable/entries` mit
   `resourceType=TEACHER`: Betroffene Einträge tragen `status: "CANCELLED"`.
   Kompakter ist die **Tagesansicht aller Lehrkräfte** über denselben
   Endpunkt mit `timetableType=OVERVIEW_DAY` und leerem `resources`: eine
   Abfrage statt hundert.
   **Wichtig:** `CANCELLED` heißt „die Stunde fällt aus“, nicht „die
   Lehrkraft ist krank“. Im Mitschnitt standen darunter auch „Entfall mit
   Aufgaben“ und eine Bereitschaft. Zählen, wie viele Stunden einer
   Lehrkraft ausfallen, wäre also ein Schluss, keine Aussage.
2. **Am Elternsprechtag ist eine Veranstaltung als Block eingetragen.**
   Steht dieser Block auf `CANCELLED`, sagt das direkt: Diese Lehrkraft nimmt
   nicht teil. Damit erledigt sich die Sorge, nachmittags stehe nichts im
   Plan.
   **Ungemessen:** woran man den Sprechtags-Block in der Antwort erkennt.
   Das zeigt erst ein Mitschnitt von einem Tag mit eingetragenem Sprechtag.
   Bis dahin wäre jedes Erkennungsmerkmal geraten (REIHENREGELN 8: ein
   verbreiteter Feldname allein ist kein Erkennungsmerkmal).
3. **Zweite Quelle, geprüft und verworfen:** `lessonlist.do` nennt
   Abwesenheiten mit Art, Zeitraum und Grund. Es ist aber HTML, keine API,
   wie `usergrouplist.do`. Für einen Hinweis reicht der Stundenplan-Weg;
   den Grund muss sprechtag nicht kennen (Datensparsamkeit).
4. **Nebenbei:** `environment.json` nennt die WebUntis-Version. Geht nach
   einem Update etwas nicht mehr, wäre das die Stelle zum Nachsehen.

## Was ein Bau zuerst bräuchte

- Ein Mitschnitt der Tagesansicht an einem Tag mit eingetragenem Sprechtag,
  um das Erkennungsmerkmal des Blocks zu belegen. Er gehört als Testfall ins
  Repo.
- Die Messung, ob die **Sitzung der Verwaltung** `OVERVIEW_DAY` lesen darf.
  Ein Dienstkonto gibt es seit v0.9.72 nicht mehr (E17).
- Dieselbe Frage wie bei jedem fremden Beleg: Was ändert er an dem, was wir
  prüfen?
