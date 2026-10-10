# Abgleich 09.10.2026 — Hilfeseite und Fachdokumente gegen den Stand v0.9.64

Erhoben am 09.10.2026 am Stand v0.9.64 (`2aad249`). **Nur gesichtet, nichts
korrigiert** – was davon wann nachgezogen wird, entscheidet der Betreiber.
Die Liste dient dazu, die spätere Korrektur daran zu prüfen: je Stelle, was
heute dort steht und was stattdessen gilt.

Geprüft ist der **feste Teil** der Hilfe in `frontend/app.js`
(`ansichtHilfe()`, zuletzt geändert am 30.07.2026). Den von der Schule
gepflegten Zusatz (`hilfe_zusatz`) betrifft das nicht.

Keine Personennamen, keine Kennungen.

---

## Zuerst: Datenschutz (gewichtigster Punkt)

> **Vor allen übrigen Punkten zu korrigieren** (Betreiber, 09.10.2026):
> Eine Datenschutzaussage, die mehr verspricht, als sie hält, wiegt schwerer
> als ein veralteter Bedienhinweis.

**H9 — Handbuch, „Datenschutz“.**
- *Steht dort:* „Beim Archivieren eines Sprechtags werden alle persönlichen
  Daten (Buchungen, Einladungen, Mitteilungen) gelöscht; die
  wiederverwendbare Struktur bleibt erhalten.“
- *Gilt heute:* Das **Login-Protokoll** speichert WebUntis-Benutzernamen mit
  eigener Aufbewahrung (Einstellung der Verwaltung), nicht an das Archivieren
  gebunden; Fehlschläge werden immer festgehalten (Brute-Force-Bremse). Die
  **Schülerliste** der Einladungsauswahl trägt Namen und Klassen der Kinder
  und bleibt beim Archivieren erhalten. Richtig bleibt: Namen von
  Erziehungsberechtigten werden nur zur Laufzeit aus der Sitzung verwendet.
- Seit v0.9.65 zusätzlich: Die WebUntis-Benutzergruppe der angemeldeten
  Person wird in der Sitzung gehalten (nicht in der Datenbank).

---

## Hilfeseite: übrige Stellen

### Schnellanleitung – Erziehungsberechtigte

**H1 — „Bei der gewünschten Lehrkraft auf eine freie Uhrzeit tippen – der
Termin ist damit gebucht.“**
*Gilt heute:* Zuerst die Kachel der Lehrkraft antippen; darunter erscheint
ihr Zeitraster, darüber das freiwillige Feld „Optionaler Hinweis an die
Lehrkraft“. Dann eine freie Uhrzeit antippen – damit ist gebucht, ohne
weitere Rückfrage.

**H2 — fehlt: Gliederung der Kacheln.**
*Gilt heute (E10, Vierteilung):* zuerst Lehrkräfte, die eingeladen haben
(„hat Sie eingeladen“); dann Klassenleitung (Abzeichen „Klassenleitung“) und
die unterrichtenden Lehrkräfte; darunter abgesetzt die Sonderrollen
(z. B. Beratung, mit Abzeichen); ab Phase 2 alle weiteren teilnehmenden
Lehrkräfte über den aufklappbaren Block „Weitere Lehrkräfte suchen“ (Name,
Kürzel oder Raum).

**H3 — fehlt: Übersicht auf der Buchungsseite.**
*Gilt heute:* „Meine Termine: N Termine“, aufklappbar, mit Absagen und dem
Weg zur vollen Übersicht.

**H4 — „Unter ‚Meine Termine‘ … kann sie wieder absagen.“**
*Gilt heute:* nur Termine aus Phase 2. Termine auf Einladung (Phase 1) können
Eltern nicht selbst absagen; dort steht „auf Einladung – Absage nur durch
die Lehrkraft“.

### Schnellanleitung – Lehrkräfte

**H5 — „In Phase 1 können Eltern per ‚Einladungen‘ gezielt eingeladen
werden.“**
*Gilt heute:* Einladungen gehen in jeder Phase – in der Vorbereitung
vorbereitet und beim Anlegen versendet, in Phase 2 zur gezielten Erinnerung.
Auswahl über eine Klassenliste mit Suche (Name oder Klasse). Bei den Eltern
steht die einladende Lehrkraft zuerst („hat Sie eingeladen“).

**H6 — „… kann die Lehrkraft stellvertretend für einen freien Slot buchen.“**
*Gilt heute:* nicht falsch, aber unvollständig – die Lehrkraft sucht das Kind
über ein Suchfeld (Name oder Klasse) in ihrem Zeitraster und bucht dann.

### Handbuch

**H7 — „In Phase 1 können nur Erziehungsberechtigte buchen, die von einer
Lehrkraft eingeladen wurden.“**
*Gilt heute:* In Phase 1 buchen eingeladene Eltern **nur bei der Lehrkraft,
die eingeladen hat** (nur deren Kachel erscheint). Ab Phase 2 ist jede
teilnehmende Lehrkraft buchbar, auch ohne Unterrichtsbezug.

**H8 — „Krankheitsausfall: … werden automatisch benachrichtigt.“**
*Gilt heute:* sofort nur mit hinterlegtem Dienstkonto; sonst wird die
Absage-Mitteilung vorgemerkt und unter „Mitteilungen“ versendet.

(H9 Datenschutz: oben.)

### Häufige Fragen

**H10 — „Wie sage ich einen Termin ab? … jeder Termin absagen.“**
*Gilt heute:* wie H4 – Termine auf Einladung nur über die Lehrkraft.

**H11 — fehlt: „Ich finde eine Lehrkraft nicht.“**
*Gilt heute:* ab Phase 2 über „Weitere Lehrkräfte suchen“; in Phase 1
erscheinen nur Lehrkräfte, die eingeladen haben.

**H12 — „Warum sehe ich keine freien Termine?“**
*Gilt heute:* stimmt noch; häufiger ist aber, dass in Phase 1 ohne Einladung
gar keine Kachel erscheint (die Seite sagt das dann selbst).

**Neu seit dem Abgleich (v0.9.65, E15) – fehlt ebenfalls:** Volljährige
Schülerinnen und Schüler buchen nur selbst, wenn die Schule ihre
WebUntis-Benutzergruppe zugelassen hat; sonst steht „Termine buchen die
Erziehungsberechtigten“.

### Geprüft und weiterhin richtig

Anmeldung; Doppelbuchung zur selben Uhrzeit wird verhindert; Zeitfenster per
⏱; Halbtags „½“ mit Hälftenwahl; Raumfarben; ⊘ für Ausfall; „Alle
speichern“; „Erscheinungsbild (Logo, Texte)“; Kalender „📅 hinzufügen“ und
Abo-Link (auch in der Kartenansicht so); Bestätigungen „sofern ein
Dienstkonto hinterlegt ist“.

Nichts zu korrigieren für die entfallenen **Farbfelder** (die Hilfe erwähnt
keine Farben) und die **Kartenansicht** (kein Satz beschreibt Tabellenspalten
oder seitliches Rollen).

---

## Fachdokumente in docs/

Ausführlich im CHANGELOG unter v0.9.64, Teil C. Kurz:

- **`SCHUELERLISTE.md`** (30.07.): beschreibt den Schild-Import als Weg;
  „`getStudents` liefert keine Klasse“ und der Ausblick über `getKlassen`
  sind durch den Befund vom 07.10. überholt (`pageconfig?type=5` trägt Klasse
  und `externKey`); im CSV-Beispiel fehlt die Spalte Austrittsdatum. Der
  Import ist noch im Code (Zug 4).
- **`BEDIENUNG.md`** (23.07.): kennt die Gliederung der Kacheln nicht; nennt
  einen Knopf „Lehrkräfte anzeigen“, den es nicht mehr gibt; Einladungen
  „über Schüler-ID“ (heute Klassenliste); stellvertretendes Buchen „mit
  Benutzer-ID der Eltern“ (heute Kind-Suche). Es fehlen Mitteilungen,
  Kalender-Abo, Anzeige, Erinnerungen, Login-Protokoll, Texte,
  Halbtags/Zeitfenster, Karten am Telefon, die Gruppenprüfung (E15).
  Datenschutz wie H9.
- **`DIENSTKONTO.md`** (24.07.): „Gruppe ‚SuS über 18‘ ist über die API
  nicht sichtbar“ überholt (`profile/general`, Befund Abschnitt 13; genutzt
  seit v0.9.65); „ohne Klausuren“ überholt (je Sprechtag einstellbar);
  Grenzen zu `getKlassen`/`getStudents` überholt.
- **`MITTEILUNGEN.md`** (24.07.): Anlass „Einladung“ fehlt; „Versand
  auslösen“ kennt nur die Verwaltung mit Passworteingabe (die Ansicht gibt
  es auch für Lehrkräfte; mit Dienstkonto entfällt die Eingabe).
  **Beispiele mit Namen und Kennungen: GEKLÄRT** – nach Auskunft des
  Betreibers (09.10.2026) sind es Testkonten, keine echten Personen. Kein
  offener Punkt; nicht erneut prüfen.
- **`SONDIERUNG.md`, `VERSANDWEG_ERMITTELN.md`** (Juli): Anleitungen aus der
  Anfangszeit; der Versandweg ist seit 24.07. belegt, die Ermittlungsanleitung
  ist Rückfall.
- Aktuell: `ENTSCHEIDUNGEN.md`, die beiden `BEFUND-*`,
  `signage-wiederverwenden.md`.

---

## Nachtrag 09.10.2026

**H9 behoben in v0.9.74.** Der feste Datenschutz-Absatz steht jetzt in
`datenschutzAbsaetze()` (`frontend/app.js`). Er nennt, was das Archivieren
löscht, was bleibt (Schülerliste, fehlgeschlagene Anmeldeversuche mit
Frist, Kalender-Link ohne Frist) und dass es für das Archivieren selbst
keine automatische Frist gibt. Geprüft wird das in
`tests/frontend_datenschutz_test.js` und `tests/run_archivieren.php`;
Einzelheiten stehen in `BEFUND-2026-10-09-daten-neben-dem-archiv.md`.
`hilfe_zusatz` ist nicht angefasst.

**H13 — FAQ „… es kam keine Bestätigung.“** *Steht dort:* „sofern ein
Dienstkonto hinterlegt ist“. *Gilt heute:* Seit v0.9.72 gibt es kein
Dienstkonto mehr. Die Bestätigung geht über die Sitzung der buchenden
Person; ist sie abgelaufen, wird die Bestätigung vorgemerkt. Oben unter
„Geprüft und weiterhin richtig“ stand der Satz noch als richtig, und zwar
für den Stand vor v0.9.72. Nicht behoben, weil das Nachziehen der Hilfeseite
nicht beauftragt ist.

**Nachtrag 09.10.2026 — README auf die Liste (Betreiber):** Die `README.md`
(auf GitHub die Startseite) beschreibt einen Stand von vor diesem Tag:
Dienstkonto, Schild-Import, die alte Kachel-Gliederung. Sie wird zusammen
mit der Hilfeseite und den Fachdokumenten nachgezogen, **nicht** in Zug 4
selbst.

**Nachtrag 10.10.2026 — H13 erledigt (v0.9.79):** Die Bestätigung nach
einer Elternbuchung entfällt, denn Elternkonten dürfen nicht senden (403,
gemessen; E17-Nachtrag). Die FAQ-Antwort sagt jetzt: Nach einer eigenen
Buchung kommt keine Nachricht; die Buchung steht unter „Meine Termine“ und
im Kalender-Abo; eine Bestätigung kommt nur, wenn eine Lehrkraft gebucht
hat. Mitgezogen, weil die Antwort sonst etwas versprochen hätte, das es
nicht mehr gibt. Die übrige Hilfeseite bleibt auf der Nachzieh-Liste.

