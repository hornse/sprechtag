# Befund 10.10.2026: zwei Felder im Kalender-Abo (LOCATION, Zeitzone)

**Anlass:** Der Betreiber hat nach v0.9.77 eine Abo-Datei angesehen.
**Auftrag: nur notieren, nicht ändern.** Diese Datei ist die Notiz für das
nächste Anfassen des Abos.

---

## K1 — LOCATION: im Code vorhanden, im Betrieb nicht gesehen

**Beobachtet (Betreiber):** Die Abo-Datei enthält Datum, Uhrzeit,
Lehrkraft und Kind, aber kein `LOCATION`. Dem geprüften Termin war kein
Raum zugeordnet. Ob das Feld bei einem zugeordneten Raum erscheint, ist im
Betrieb damit **nicht geklärt**.

**Im Code gelesen (Stand v0.9.78, nicht ausgeführt):**
- `kal_vevents()` (Eltern) und `kal_vevents_lehrer()` (Lehrkraft) setzen
  `LOCATION:<Raumkürzel>`, aber nur, wenn das Raumkürzel nicht leer ist
  (`backend/api/kalender.php`, die Zeile mit `if ($raum !== '')`).
- Der Raum steht zusätzlich in `DESCRIPTION` („Raum: …“).
- Das Kürzel kommt über `sprechtag_lehrer.raum_id` aus `raeume.kuerzel`
  (LEFT JOIN in `kal_buchungen_laden()` und `kal_lehrer_buchungen()`). Ohne
  Raumzuordnung der Lehrkraft für diesen Sprechtag fehlt das Feld. Das
  passt zur Beobachtung.

**Geprüft ist das nicht:** Keine Suite enthält `LOCATION`, und der Fall
„Raum zugeordnet“ steht in keinem Testdatensatz (`run_kalender_abo.php`
legt Räume ohne Zuordnung an).

**Beim nächsten Anfassen:**
1. Eine Prüfung mit zugeordnetem Raum ergänzen. Sie muss `LOCATION` in
   beiden Abos verlangen, und eine Gegenprobe ohne Raum darf kein leeres
   `LOCATION:` erzeugen.
2. Am Gerät ansehen, ob die Kalender-App den Ort anzeigt.

Sollte das Feld wider Erwarten fehlen, wäre es die Verbesserung, die der
Betreiber nennt: Eltern mit mehreren Terminen in verschiedenen Räumen sähen
direkt, wohin sie müssen.

## K2 — DTSTART/DTEND ohne Zeitzone

**Beobachtet (Betreiber):** `DTSTART:20270226T150000`, ohne `Z` und ohne
`TZID`. Nach RFC 5545 ist das „floating time“, also die Ortszeit des
Betrachters. Wer in einer anderen Zeitzone sitzt, sieht den Termin zu
seiner Ortszeit.

**Im Code gelesen:** Beide Abos bilden die Zeit mit
`date('Ymd\THis', mktime(…))`. `mktime` und `date` benutzen dieselbe
Zeitzone des PHP-Dienstes, also kommt die Uhrzeit des Sprechtags
unverändert heraus, egal, worauf der Server steht. `DTSTAMP` steht dagegen
mit `Z` (UTC, `gmdate`). Das ist richtig so und betrifft die Anzeige nicht.

**Einordnung (Betreiber):** Für einen Sprechtag an einem festen Ort ist das
meist richtig und für diesen Betrieb unproblematisch. Es war bisher nur
nirgends vermerkt. Eine Änderung wäre `TZID=Europe/Berlin` samt
`VTIMEZONE`-Block; das ist nicht beauftragt.

## Nebenbei bestätigt (Betreiber, Betrieb, 10.10.2026)

- **Schritt 2 (v0.9.76) wirkt:** Eine neue Buchung trägt Name und Klasse.
  Ältere Buchungen haben die Klasse leer, wie die Migration es vorsah.
  Werte bewusst nicht wiedergegeben.
- **Datumsfilter (v0.9.77):** Das Abo liefert den Februar-Termin
  richtig. Dass er in der App zunächst fehlte, lag an deren
  Aktualisierungsrhythmus, nicht am Filter.
