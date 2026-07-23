# Bedienung – sprechtag

## Rollen und was sie dürfen

| Rolle | WebUntis | Rechte |
|---|---|---|
| Administration | personType 16, Kürzel in `admin_kuerzel` oder Eintrag in `app_admins` | alles |
| Lehrkraft | personType 2 | eigene Termine, Einladungen, stellvertretend buchen, absagen |
| Erziehungsberechtigt | personType 12 (LEGAL_GUARDIAN) | für die eigenen Kinder buchen und absagen |
| Schüler:in (volljährig) | personType 5 | für sich selbst buchen |

## Ablauf eines Sprechtags

### 1. Vorbereitung (Administration)

1. **Stammdaten übernehmen** – Administration → „Stammdaten aus WebUntis
   übernehmen". Holt Lehrkräfte und Räume. Beim ersten Lauf prüfen, ob die
   Zahlen zum Kollegium passen.
2. **Sprechtag anlegen** – Datum, Zeitrahmen, Slotlänge, Maximalzahl der
   Termine je Elternteil, optional automatische Pausen.
3. **Lehrkräfte und Räume** – je Sprechtag festlegen, wer teilnimmt, in
   welchem Raum, und (für Teilzeitkräfte) in welchem Zeitfenster. Leere
   Zeitfelder = ganzer Zeitraum. Doppelt belegte Räume sind erlaubt und
   werden rot markiert.
4. **Sonderlehrkräfte** – Schulleitung, Beratung, SV usw. sind unabhängig
   vom Stundenplan buchbar. Jahrgänge leer lassen = für alle.

Die Phase bleibt in dieser Zeit auf **in Vorbereitung** – es kann noch
niemand buchen.

### 2. Phase 1 – nur auf Einladung

Phase auf **Phase 1** stellen. Jetzt gilt:

* Lehrkräfte legen unter „Einladungen" gezielt Einladungen an (Schüler-ID
  des Kindes). Nur diese Eltern können bei ihnen buchen.
* Lehrkräfte können **stellvertretend buchen** – für Eltern, die es selbst
  nicht schaffen. Dafür wird die WebUntis-Benutzer-ID der Eltern benötigt.
* Eltern können Phase-1-Termine **nicht selbst absagen** – nur die Lehrkraft.

### 3. Phase 2 – offen für alle

Phase auf **Phase 2** stellen. Alle Eltern können nun buchen, begrenzt durch
die Maximalzahl. Eltern können eigene Phase-2-Termine wieder absagen.

### 4. Nach dem Sprechtag

* **geschlossen** – keine Buchungen mehr möglich, alles bleibt einsehbar.
* **archiviert** – löscht Buchungen, Einladungen und die Kind-Lehrkraft-
  Zuordnung. Lehrkräfte, Räume, Zeitfenster und Sonderrollen bleiben
  erhalten, damit der Sprechtag wiederverwendet werden kann.
* **Kopieren** – legt einen neuen Sprechtag mit derselben Struktur an
  (ohne personenbezogene Daten).

## Für Erziehungsberechtigte

Anmeldung mit dem **eigenen** WebUntis-Zugang (nicht dem des Kindes).
Danach: Kind wählen → „Lehrkräfte anzeigen" → Lehrkraft anklicken → freien
Zeitpunkt (grün) anklicken. Unter „Meine Termine" stehen alle Buchungen mit
Uhrzeit, Lehrkraft und Raum.

Buchbar sind Lehrkräfte, die das Kind unterrichten (aus dem Stundenplan des
Referenzzeitraums, ohne Vertretungsstunden), sortiert nach Wochenstunden –
Hauptfächer stehen also oben. Dazu die freigegebenen Sonderlehrkräfte.

## Datenschutz

* Von Erziehungsberechtigten wird **nur die WebUntis-Benutzer-ID**
  gespeichert – kein Name, keine E-Mail-Adresse. Namen kommen zur Laufzeit
  aus der Anmeldung und stehen nur in der Sitzung.
* Von Kindern wird nur die WebUntis-Schüler-ID gespeichert.
* Eltern sehen ausschließlich die eigenen Termine. Wer einen belegten Slot
  gebucht hat, ist für andere Eltern nicht erkennbar.
* Lehrkräfte sehen nur Buchungen bei sich selbst.
* Beim Archivieren werden alle personenbezogenen Daten gelöscht.

## Fallstricke

* **Kein Lehrkraft-Stammsatz zugeordnet**: Die Lehrkraft-Ansicht braucht
  einen Eintrag in der Tabelle `lehrer` mit passendem Kürzel → Stammdaten
  synchronisieren.
* **Keine Lehrkräfte für ein Kind**: Der Cache `kind_lehrer_cache` ist noch
  leer. Er wird über `POST /api/lehrer-ermitteln` gefüllt (braucht
  WebUntis-Zugangsdaten mit Leserecht auf den Stundenplan des Kindes).
* **Referenzzeitraum in den Ferien**: Dann findet die Ermittlung keine
  Stunden. Der Zeitraum ist je Sprechtag einstellbar (Standard: vier Wochen,
  endend eine Woche vor dem Sprechtag).
