# Changelog – sprechtag

## v0.3.0 (Juli 2026) – Paket 2: Datenmodell, Adapter, Buchung, Adminseite

### Neu
- **Datenmodell** (`sql/02_sprechtag.sql`, idempotent): Sprechtage mit allen
  Adminparametern, teilnehmende Lehrkräfte mit Anwesenheitsfenster und Raum,
  Sonderlehrkräfte mit Rollen und optionaler Jahrgangsbindung, Buchungen
  (UNIQUE-Constraint gegen Doppelbuchung), Einladungen für Phase 1,
  Kind-Lehrkraft-Cache, App-Administratoren, sieben Standard-Sonderrollen
- **WebUntis-Adapter** (`webuntis_adapter.php`): Login mit Rollenerkennung
  (personType 2/5/12/16), Kinder aus `app/data`, Lehrkraft-Ermittlung je Kind
  über den Referenzzeitraum mit DB-Cache, Stammdaten-Sync (Lehrkräfte, Räume)
- **Buchungslogik** als reine Funktionen (`slots.php`): Zeitraster mit
  Slotlänge, automatischen Pausen und Teilzeitfenstern, Buchungs- und
  Stornoregeln, Raumkonflikte, Jahrgangsfilter für Sonderlehrkräfte
- **Adminseite**: Sprechtage anlegen/ändern/kopieren/archivieren, Lehrkräfte
  mit Zeitfenster und Raum (Doppelbelegung rot markiert), Sonderlehrkräfte,
  Stammdaten-Sync, Phasensteuerung
- **Eltern-Ansicht**: Kind wählen, Lehrkräfte nach Wochenstunden sortiert
  (Hauptfächer zuerst) mit Fach, Raum und Anwesenheitszeit, Zeitraster mit
  freien/belegten/eigenen Slots, persönliche Terminübersicht
- **Lehrkraft-Ansicht**: eigene Termine, Einladungen für Phase 1,
  stellvertretende Buchung, Absage mit Nachrichtentext (Versand folgt)
- **Modul-Erweiterung** für `hornse/webuntis-client-php` v1.2.0:
  `rest_lehrkraefte_aus_entries()` (Lehrkräfte je Schüler, mit denselben
  Filtern wie `rest_unterricht_aus_entries`: nur TEACHING-Einträge, nur
  REGULAR/CANCELLED, nur `current`) und `rest_konto_aus_appdata()`

### Datenschutz
- Von Eltern wird ausschließlich die WebUntis-`user.id` gespeichert, kein
  Name und keine E-Mail-Adresse; Namen leben nur in der Sitzung
- Eltern sehen nur eigene Buchungen; belegte Slots zeigen keine Fremddaten
- Archivieren löscht Buchungen, Einladungen und Kind-Lehrkraft-Zuordnung,
  behält aber die wiederverwendbare Struktur

### Behoben
- DB-Verbindungsfehler führten zu einem nackten 500er; jetzt sauberes JSON
  mit Status 503, Details im Server-Log
- Guards standen teilweise nach `db()`; nicht angemeldete Zugriffe liefern
  jetzt zuverlässig 401 statt 503 bei DB-Ausfall
- `lastInsertId()` wurde nach Folge-Statements gelesen und lieferte 0;
  Buchungs-ID wird jetzt sofort gesichert

### Tests
- `tests/run_slots.php`: 68 Offline-Prüfungen (Raster inkl. Pausen und
  Teilzeit, Buchungs- und Stornoregeln je Rolle und Phase, Raumkonflikte,
  Jahrgangsfilter, Extraktoren inkl. Vertretungs-/Aufsichts-Ausschluss,
  app/data-Auswertung, Referenzzeitraum)
- `tests/run.php`: 23 Prüfungen der Sondierungslogik (unverändert grün)
- End-to-End gegen MariaDB: Rollen, Phase-1-Ablauf, Doppelbuchung,
  Datenschutzgrenzen, Kopieren, Archivierung

## v0.2.0 (Juli 2026) – Sondierungsbefunde eingearbeitet

Auswertung der Läufe vom 19.07.2026 (Eltern-, Lehrkraft-, Admin-Konto):
personType 12 = LEGAL_GUARDIAN, Kind-Zuordnung über `app/data` →
`user.students[]` bestätigt, Mitteilungs-Endpunkte erreichbar,
Sprechtag-Kandidaten durchweg 404 (Details in `docs/SONDIERUNG.md`).

- **Bugfix Schüler-ID-Erkennung**: nahm bisher die erste Kind-Struktur und
  damit `user.person` (die Person des Kontos selbst) statt `user.students`.
  Neue reine Funktion `sondierung_schueler_ids()` filtert auf
  Schüler-/Kind-Pfade, verwirft `-1` und liefert mehrere IDs.
- Stundenplan wird jetzt **je Kind einzeln** sondiert (statt einer Probe mit
  Platzhalter); CLASS-Probe nur noch bei vorhandener klasseId.
- Neu `sondierung_lehrer_aus_entries()`: extrahiert aus einer 200-Antwort
  alle `current.type == "TEACHER"`-Elemente (defensiv-rekursiv, keine
  Positionsannahmen) → Anzeige „🧑‍🏫 Lehrkräfte im Zeitraum".
- Neue Eingabefelder **Zeitraum von/bis** (Validierung JJJJ-MM-TT in API und
  Frontend) – nötig, weil Proben in den Ferien leere Pläne liefern.
- `app/data` wird immer intern abgerufen (liefert IDs für alle Gruppen),
  erscheint im Bericht aber nur bei Gruppe „Basis"; Bericht enthält jetzt
  zusätzlich `user_id` und `schueler_ids_gewaehlt`.
- Mitteilungs-Gruppe um Parameterprobe `recipients={USER_ID}` erweitert
  (Befund: Endpunkt erwartet User-ID vom Typ Long).
- personType 12 im Rollen-Mapping und in `allowed_person_types` der
  Konfigurationsvorlage; Vorlage außerdem auf Platzhalter umgestellt
  (öffentliches Repository).
- Testsuite auf 23 Prüfungen erweitert (Schüler-ID-Auswahl inkl.
  Bug-Regression, Lehrer-Extraktion, Kandidaten-Struktur).

## v0.1.0 (Juli 2026) – Paket 1: Projektgerüst + Sondierwerkzeug

- Projektgerüst nach Vorlage `hornse/schulprojekt-template`:
  router.php (HTTPS-Flag + session_name ganz oben), bootstrap.php,
  API-Router, SPA-Frontend (HTML ohne JS, app.js, style.css),
  deploy.sh, idempotentes Schema `01_schema.sql` (login_log)
- WebUntis-Clients vendored aus `hornse/webuntis-client-php`
  (WebUntisAuth JSON-RPC, WebUntisRest intern; Ergänzung:
  `setzeTimeout()` für kurze Sondierproben – ins Modul-Repo übernehmen)
- Sondierwerkzeug (`POST /api/sondierung`, nur lesend):
  - authenticate-Befund inkl. personType-Auswertung (Eltern-Konto!)
  - REST-Zugang (JWT), app/data mit automatischer Suche nach
    Kind-/Personen-Strukturen (Kandidaten für die Eltern→Kind-Zuordnung)
  - Kandidaten-Proben: Sprechtag-Endpunkte (Modul lizenziert),
    Stundenplan je STUDENT/CLASS, Mitteilungs-Endpunkte (nur GET,
    405 wird als Positiv-Befund gewertet)
  - freie Zusatzpfade + manuelle Schüler-ID („Erweitert")
  - Bericht im UI mit Ampel-Markierung und Markdown-Export
- Schutz: Brute-Force-Bremse über login_log (mit Ersatz-Wartezeit ohne
  DB), Abschaltbarkeit per `sondierung_freigeschaltet` in config.php,
  Proben-Timeout 8 s (Uberspace-Proxy-Limit), ignore_user_abort
- Offline-Testsuite `tests/run.php` (Strukturanalyse, Kürzung,
  Kandidaten-Platzhalter)
