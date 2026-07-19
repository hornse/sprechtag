# Changelog – sprechtag

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
