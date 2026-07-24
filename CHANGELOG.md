# Changelog – sprechtag

## v0.5.0 (Juli 2026) – Dienstkonto und automatische Lehrkraft-Ermittlung

### Neu
- **Verschlüsselte Dienstkonto-Ablage** (`backend/api/dienstkonto.php`):
  Zugangsdaten eines WebUntis-Kontos mit Leserecht auf Schülerstundenpläne
  werden mit libsodium (ersatzweise OpenSSL AES-256-GCM) verschlüsselt in
  `einstellungen` gespeichert. Der Schlüssel steht in `config.php`, also
  nicht in der Datenbank. Verwaltung über Administration → „Dienstkonto".
- **Automatische Ermittlung bei Bedarf**: Öffnen Eltern die Buchungsseite
  und ist der Cache für dieses Kind leer, ermittelt das System die
  Lehrkräfte im Hintergrund mit dem Dienstkonto – einmal je Kind und
  Sprechtag. Schlägt das fehl, bleibt die Ansicht bedienbar.
- `POST /api/lehrer-ermitteln` nutzt das Dienstkonto, wenn keine
  Zugangsdaten übergeben werden.
- Neue API-Routen: `GET/POST/DELETE /api/dienstkonto` (der Status gibt
  niemals das Passwort preis, nur Benutzername und Verfahren).

### Sondierung erweitert
- `getSchoolyears()` und `getCurrentSchoolyear()` im Client ergänzt;
  `getKlassen()` nimmt jetzt optional eine `schoolyearId`.
- Die Stammdaten-Sondierung probiert `getKlassen` bei Fehlschlag erneut
  mit expliziter Schuljahres-ID und prüft anschließend, ob ein
  Klassenstundenplan die Lehrkräfte liefert (Grundlage für die spätere
  Sammelvorbereitung).

### Befunde der Sondierung vom 24.07.2026
- In den Sommerferien ist **kein Schuljahr aktiv**; `getKlassen()` scheitert
  dann mit Fehler -8998. Die Sammelvorbereitung über Klassenstundenpläne
  ist deshalb erst nach Beginn des neuen Schuljahres testbar.
- `getStudents()` liefert 3563 Einträge, aber **keine Klassenzuordnung**
  (nur id, key, name, foreName, longName, gender) – eine Vorauswahl der
  Oberstufe ist damit nicht möglich.
- Kein Gruppenfeld für „SuS über 18" in der API sichtbar.

### Behoben
- `sondierung.php` nutzte `rest_lehrkraefte_aus_entries()`, ohne
  `extractors.php` einzubinden – wäre zur Laufzeit gescheitert.

### Tests
- `tests/run_dienstkonto.php` (12 Prüfungen): Verschlüsselung, Nonce-
  Variation, UTF-8, falscher Schlüssel, manipulierte Chiffre.
- `tests/run_dienstkonto_db.php` (14 Prüfungen): Speichern, Lesen, Status
  ohne Passwortpreisgabe, Schlüsselwechsel, Löschen.


## v0.4.4 (Juli 2026) – Seite blieb nach v0.4.3 komplett leer

### Behoben
- **Kritisch: Nach dem Deploy von v0.4.3 war die Anwendung nicht mehr
  bedienbar** – weiße Seite, kein Login. Beim Umbau der Sondierungsansicht
  in v0.4.3 wurde der Codeblock von `ansichtSondierung` bis
  `ladeMitteilungen` ersetzt und dabei die dazwischenliegende Funktion
  `ansichtMitteilungen` versehentlich mitgelöscht. Da sie in der
  Ansichten-Registrierung weiterhin referenziert wurde, brach das Skript
  beim Start mit einem ReferenceError ab, bevor irgendetwas gezeichnet
  werden konnte. Die Funktion ist wiederhergestellt.

### Tests
- `tests/frontend_vollstaendig_test.js`: statische Prüfung, dass jede in
  `ansichten` registrierte Funktion und jede aufgerufene Hilfs- und
  Ladefunktion tatsächlich definiert ist; prüft außerdem Klammerbilanz
  und Abschluss der IIFE.
- `tests/frontend_laufzeit_test.js`: führt `app.js` in einem minimalen
  DOM-Ersatz aus und stellt sicher, dass `start()` ohne ReferenceError
  durchläuft. Dieser Test hätte den Fehler sofort gefunden.


## v0.4.3 (Juli 2026) – Sondierungsansicht behält Eingaben und Bericht

### Behoben
- **Sondierung meldete „abgeschlossen", zeigte aber keinen Bericht, und
  alle Eingaben verschwanden.** Ursache ist dasselbe Muster wie in v0.4.1,
  nur an anderer Stelle: Der Bericht wurde in eine `<pre>`-Element-Referenz
  geschrieben, die vor dem `meldung('… läuft')`-Aufruf geholt worden war.
  `meldung()` ruft `zeichne()` auf und baut die Ansicht neu auf – die
  Referenz zeigte danach auf ein Element, das nicht mehr im Dokument hing.
  Der Bericht landete im Nichts, die Eingabefelder waren geleert.
- Die Sondierungsansicht hält Benutzername, Zeitraum, Schüler-ID,
  Gruppenauswahl und den Bericht jetzt in `S.sondierung` und stellt sie
  bei jedem Neuzeichnen wieder her. **Das Passwort wird bewusst nicht
  gemerkt** und muss je Lauf neu eingegeben werden.
- Neuer Knopf „Als Markdown kopieren" (der Bericht ist oft zu lang zum
  Markieren) und „Bericht verwerfen".

### Tests
- `tests/frontend_zustand_test.js`: prüft beide Fehlermuster (Eingabe vor
  Meldung sichern, Ergebnis in den Zustand statt ins DOM) und stellt
  sicher, dass kein Passwort im Zustand landet.


## v0.4.2 (Juli 2026) – Sondierung Klassen/Schüler:innen freigeschaltet

### Neu
- Sondiergruppe **„Klassen & Schüler:innen"** im Frontend auswählbar.
  Die zugehörige Backend-Funktion `sondierung_stammdaten()` war bereits
  vorhanden, aber über die Oberfläche nicht erreichbar. Sie prüft
  `getKlassen()` und `getStudents()` sowie vier REST-Kandidaten und
  meldet, ob ein Gruppen-/Kategoriefeld existiert (für „SuS über 18").
- Ausgabe ist datensparsam: nur Feldnamen, Anzahlen und ein
  anonymisiertes Struktur-Beispiel (Namensfelder durch „…" ersetzt).
  Klassennamen werden im Klartext gezeigt, da für die Zuordnung nötig.

### Hinweis zur Konfiguration
`admin_kuerzel` erwartet ein **Lehrkraft-Kürzel** aus der Tabelle `lehrer`
(z. B. `Ho`), nicht den WebUntis-Benutzernamen des Admin-Kontos
(`adminho`). Grund: Das WebUntis-Admin-Konto hat `personId -1` und
erscheint nicht in `getTeachers()`; über das Kürzel wird ihm der
Lehrkraft-Stammsatz zugeordnet, damit „Meine Termine" funktioniert.
Der Feldname wird in einer kommenden Version in `admin_lehrer_kuerzel`
umbenannt (mit Rückwärtskompatibilität).


## v0.4.2 (Juli 2026) – Sondierung der Stammdaten

### Neu
- Sondiergruppe **„Klassen & Schüler:innen"**: prüft `getKlassen`,
  `getStudents` und vier REST-Varianten daraufhin, welche Felder zur
  Verfügung stehen und ob WebUntis eine Gruppenzugehörigkeit
  (z. B. „SuS über 18") mitliefert. Grundlage für die Entscheidung
  zwischen Klassenweg (Sek I) und Einzelermittlung (Oberstufe).
- `WebUntisAuth::getKlassen()` und `::getStudents()` (Modul-Ergänzung,
  gehört nach `hornse/webuntis-client-php` v1.4.0)

### Datenschutz
- Der Sondierungsbericht enthält **keine Klarnamen**: Namensfelder werden
  im Struktur-Beispiel durch „…" ersetzt, erfolgreiche REST-Antworten
  werden nicht abgedruckt (nur Status und oberste Schlüssel).

### Tests
- 11 zusätzliche Prüfungen: Anonymisierung der Namensfelder,
  Sichtbarkeit der IDs, Erkennung möglicher Gruppenfelder

### Bekannte Einschränkung
- `admin_kuerzel` erfüllt zwei Aufgaben gleichzeitig (Admin-Rechte für
  Lehrkräfte **und** Ersatz-Kürzel für personType-16-Konten). Das führt
  dazu, dass ein WebUntis-Admin-Konto auf einen fremden Lehrkraft-
  Stammsatz zeigen kann. Trennung ist für das nächste Paket vorgesehen.


## v0.4.1 (Juli 2026) – Fehlerbehebungen Frontend

### Behoben
- **Stammdaten-Sync, Sondierung und Mitteilungsversand meldeten
  „Feld 'benutzername' fehlt"**: Die drei Aktionen setzten zuerst eine
  Statusmeldung („… läuft") und lasen erst danach die Eingabefelder aus.
  `meldung()` ruft aber `zeichne()` auf, wodurch die Ansicht komplett neu
  aufgebaut wird – die Felder samt eingetippten Werten waren zu diesem
  Zeitpunkt bereits verschwunden, es wurden leere Strings gesendet.
  Jetzt werden Werte grundsätzlich vor jeder Meldung gelesen; zusätzlich
  prüft das Frontend auf leere Eingaben, bevor es den Server fragt.
- **Aufgeklappte Bereiche schlossen sich nach jeder Meldung**: Neue
  Hilfsfunktion `block()` merkt den Zustand jedes `<details>`-Bereichs in
  `S.offeneBloecke` und stellt ihn beim Neuzeichnen wieder her.
- **Versionsnummer war seit Paket 3 auf 0.3.0 stehengeblieben**, obwohl
  die Mitteilungsfunktionen enthalten waren – irreführend beim Prüfen des
  Deployments. Die Nummer steht jetzt in `index.php` und `index.html`.

### Tests
- `tests/frontend_reihenfolge_test.js` (Node): weist nach, dass die alte
  Reihenfolge leere Werte liefert und die neue die eingetippten.

## v0.4.0 (Juli 2026) – Paket 3: Mitteilungen an Erziehungsberechtigte

### Neu
- **Mitteilungs-Warteschlange** (`sql/03_mitteilungen.sql`): Tabelle
  `mitteilungen` mit Status (offen/gesendet/verworfen), Fehlergrund und
  Versuchszähler; Schlüssel-Wert-Tabelle `einstellungen`
- **Automatische Terminbestätigung**: Bei jeder Buchung wird eine
  Bestätigung mit *allen* aktuellen Terminen des Kontos vorgemerkt
  (Zeit, Lehrkraft, Raum). Eine bereits offene Bestätigung wird ersetzt,
  nicht ergänzt – es gilt der aktuelle Gesamtstand.
- **Absage-Benachrichtigung**: Sagt eine Lehrkraft ab, entsteht eine
  Mitteilung mit Zeit, Lehrkraft und optionalem Freitext. Sagen Eltern
  selbst ab, entsteht keine.
- **Versand mit Variantenprobe**: Da der POST-Weg der WebUntis-API
  undokumentiert ist, werden vier plausible Feldstrukturen nacheinander
  probiert; die erfolgreiche wird gemerkt und künftig zuerst genutzt.
  401/403 brechen sofort ab (Rechteproblem), 400/404/405/500 führen zur
  nächsten Variante.
- **Fallback ohne Datenverlust**: Schlägt der Versand fehl, bleibt die
  Mitteilung mit Fehlermeldung stehen und kann manuell versendet werden.
  Buchung bzw. Absage sind davon unabhängig und immer korrekt.
- **Ansicht „Mitteilungen"** für Lehrkräfte und Administration: Liste mit
  Anlass, Betreff, Empfänger-ID, Status und Fehlergrund; Versand und
  Verwerfen für die Administration
- **API**: `GET /api/mitteilungen`, `POST /api/mitteilungen`,
  `POST /api/mitteilungen/senden`, `DELETE /api/mitteilungen/{id}`
- **Modul-Erweiterung** `WebUntisRest::post()` für schreibende Zugriffe
  (gehört nach `hornse/webuntis-client-php` v1.3.0)

### Behoben
- `mb_substr()` wurde ohne Prüfung genutzt; fehlte die mbstring-Erweiterung,
  scheiterte die Bestätigung stillschweigend. Neue Hilfsfunktion `kuerze()`
  in `backend/helfer.php` mit Fallback, der auch keine kaputten
  UTF-8-Sequenzen erzeugt
- Archivieren löscht jetzt auch die Mitteilungen (enthalten Lehrkraftnamen)

### Tests
- `tests/run_mitteilungen.php`: 39 Prüfungen (Antwortbewertung inkl.
  endgültig/weiterprobieren, Variantenstruktur, Textbausteine, Datumsformat)
- `tests/run_warteschlange.php`: 13 Prüfungen gegen SQLite (Einreihen,
  Fehlschlag ohne Datenverlust, kuerze-Fallback)

### Offen
- Der **Versandweg selbst ist noch nicht gegen die echte Instanz erprobt**.
  Vorgehen für den ersten Versuch: `docs/MITTEILUNGEN.md`

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
