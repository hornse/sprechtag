# Befund 09.10.2026 — Einladung zeigt nach einer Absage weiter „Termin gebucht“

**Art:** Fehler, auf der Liste. **Nicht jetzt bauen**, erst wenn der
laufende Strang durch ist (Betreiber).

> **Der gewichtigere Teil (Nachtrag unten):** der **Krankheitsausfall**.
> Er löscht ALLE Buchungen einer Lehrkraft, und alle zugehörigen Einladungen
> bleiben auf „Termin gebucht“. Auf einen Schlag zeigt so eine ganze
> Sprechtagsliste eine falsche Auskunft. **Entschieden:** ableiten (E18).

## Gemeldet aus dem Betrieb (v0.9.73)

Eine Lehrkraft sagt einen Termin ab. Der Slot ist danach frei, unter
„Angelegte Einladungen“ steht bei dem Kind aber weiter „Termin gebucht“, auch
nach dem Neuladen. Es ist also nicht die Ansicht, sondern der Status selbst.

**Warum es zählt:** Die Lehrkraft sieht „Termin gebucht“ und hält die
Einladung für erledigt. Tatsächlich ist der Slot frei, und niemand kommt.
Das ist eine falsche Auskunft genau an der Stelle, an der die Lehrkraft
entscheidet, ob sie nachfassen muss.

## Woher der Status kommt (am Code gelesen, nicht gemessen)

**Gespeichert, nicht abgeleitet.** `einladungen.erledigt`
(`sql/02_sprechtag.sql`, „1 = Termin gebucht“):
- **gesetzt** beim Buchen, an zwei Stellen in `buchungen.php`: Elternbuchung
  und stellvertretende Buchung (`UPDATE einladungen SET erledigt = 1`);
- **nie zurückgesetzt**: Weder die Absage (`DELETE /api/buchungen/{id}`) noch
  der Krankheitsausfall (`…/ausfall`, löscht alle Buchungen der Lehrkraft)
  fassen `einladungen` an;
- **angezeigt** in `frontend/app.js` („Termin gebucht“ / „offen“), und nur
  dort. `bu_einladende_lehrer()` liest das Feld mit, die Oberfläche nutzt es
  in den Eltern-Kacheln aber nicht (nur gesucht, nicht ausgeführt).

Mitbetroffen ist also auch der **Krankheitsausfall**, nicht nur die
einzelne Absage. Das ist aus dem Code geschlossen; gemeldet ist nur die
Absage.

## Für die Behebung

- Zwei Wege:
  - **Ableiten** statt speichern: „gebucht“, wenn es eine Buchung desselben
    Kindes bei derselben Lehrkraft am selben Sprechtag gibt. Eine Quelle,
    keine Migration. Das Feld bliebe dann als tote Angabe stehen, mit der
    Frage, ob es entfällt.
  - **Mitführen:** Absage und Ausfall setzen `erledigt = 0` zurück. Das sind
    zwei Schreibstellen mehr, und **bestehende** falsche Einträge brauchten
    eine einmalige Bereinigung (Migration).
  
  Gefunden ist ein Fehler der Art „gespeicherter Zustand läuft der Quelle
  davon“. Ableiten beseitigt die Art, Mitführen nur diese Stelle
  (REIHENREGELN 1, Engstelle).
- **Fachlich:** Nach einer Absage gilt die Einladung wieder als offen; sie
  ist nicht erfüllt. Eindruck des Betreibers: „offen“ reicht, weil die
  Lehrkraft gerade sehen soll, dass noch etwas aussteht. Ein eigener Status
  („Termin abgesagt“) wäre beim Bauen als Richtungsfrage zu melden.
- **Prüfungen:** Buchen → „Termin gebucht“, Absage → „offen“, Ausfall →
  „offen“, und erneut buchen → „Termin gebucht“.

## Nachtrag 09.10.2026 — entschieden (Betreiber), noch nicht gebaut

- **Ableiten, nicht mitführen** (E18). Der Status kommt aus den Buchungen:
  „Termin gebucht“, solange eine Buchung desselben Kindes bei derselben
  Lehrkraft am selben Sprechtag besteht, sonst „offen“.
- **Gewichtung:** Der Krankheitsausfall ist der schwerere Fall, nicht ein
  Nebenfall. Er stellt auf einen Schlag alle Einladungen einer Lehrkraft
  falsch; die einzelne Absage stellt nur eine falsch. Die Prüfung zum
  Ausfall gehört deshalb ausdrücklich dazu, mit **mehreren** Einladungen
  derselben Lehrkraft.
- **Keine Migration, keine Bereinigung:** Die schon falschen Einträge stimmen
  von selbst, sobald der Status abgeleitet wird.
- **Fachlich:** „offen“. Kein eigener Status „Termin abgesagt“; die Lehrkraft
  soll sehen, dass noch etwas aussteht, nicht warum.
- **`einladungen.erledigt`** wird überflüssig. Ob das Feld entfällt oder
  stehen bleibt, wird beim Bauen entschieden. Ein Feld, das niemand mehr
  liest, ist derselbe stille Zustand.
- **Zeitpunkt:** erst, wenn der laufende Strang durch ist. Vorher steht noch
  die Abnahme der abgelaufenen Sitzung aus (rund 30 Minuten Wartezeit).
