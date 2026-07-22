# Changelog – sprechtag

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
