# sprechtag

Elternsprechtag-Organisation für das Friedrich-Rückert-Gymnasium Düsseldorf
auf Basis der WebUntis-Logins. Erziehungsberechtigte und volljährige
Schüler:innen buchen Termine bei Lehrkräften über ein Zeitraster – mit
zweiphasigem Ablauf (Phase 1: nur eingeladene Eltern, buchbar auch
stellvertretend durch Lehrkräfte), Raumverteilung, Pausenautomatik,
Teilzeit-Anwesenheitsfenstern und Archivierung.

**Stand: v0.9.46** – im produktiven Einsatz.

Bedienung: `docs/BEDIENUNG.md` · Mitteilungen: `docs/MITTEILUNGEN.md`
· Sondierungsbefunde: `docs/SONDIERUNG.md`

Betrieb der Referenzinstallation: `docs/BETRIEB.md` · WebUntis-Voraussetzungen: `docs/WEBUNTIS.md`

## Funktionsumfang

**Für Erziehungsberechtigte / volljährige Schüler:innen**
- Anmeldung mit dem eigenen WebUntis-Zugang (nicht dem des Kindes)
- Terminbuchung über ein Zeitraster, Übersicht „Meine Termine", Absage
- Terminwunsch-Kommentar an die Lehrkraft
- Kalender-Abo (iCal) für die gebuchten Termine

**Für Lehrkräfte**
- Eigenes Raster mit Anwesenheitsfenstern, Pausenautomatik, Teilzeit-Zeiten
- Einladungen in Phase 1, stellvertretende Buchung für Eltern
- Mitteilungen an Erziehungsberechtigte über WebUntis
- Export der eigenen Termine

**Für die Administration**
- Sprechtage anlegen, Phasen steuern, Räume verteilen, archivieren
- Branding (Schulname, Logo, Texte) über die Oberfläche
- Editierbare Texte in Markdown (Hilfe, Buchungs- und Login-Hinweis),
  serverseitig gegen XSS gesäubert, mit Platzhaltern (`{{kontakt}}`,
  `{{schulname}}`, `{{titel}}`)
- **Erinnerungen vor dem Sprechtag**: allgemeine Nachricht an eine
  WebUntis-Empfängerliste (Typ + `referenceId` konfigurierbar). Die Liste wird
  aufgelöst, der Versand erfolgt blockweise und wird **bewusst vom Admin
  ausgelöst** – kein automatischer Hintergrundversand
- Login-Protokoll, Dienstkonto-Verwaltung (Zugangsdaten verschlüsselt in der DB)
- Anzeige-/Signage-Ansicht für Monitore im Foyer

**Querschnitt**
- Barrierefreiheit: sichtbare Fokus-Rahmen, Skip-Link, Screenreader-Unterstützung
- Datenschutz by design: für Eltern wird nur die WebUntis-`user.id` gespeichert,
  Klarnamen werden ausschließlich zur Laufzeit geholt

> **Hinweis zur WebUntis-Schnittstelle:** Der Versandweg ist undokumentiert; das
> System probiert mehrere Feldstrukturen und hält bei Fehlschlag die Mitteilungen
> zum manuellen Versand bereit (siehe `docs/MITTEILUNGEN.md`). Gleiches gilt für
> das Auflösen von Empfängerlisten – nach WebUntis-Updates kann hier Nacharbeit
> nötig sein.

## Entwicklung

Kein Build-Schritt: `frontend/index.html` und `frontend/app.js` werden direkt
ausgeliefert und bearbeitet.

Vor einem Release:

```bash
for t in tests/frontend_*.js; do node "$t"; done   # Frontend-/Struktur-Tests
php tests/run_markdown.php                          # Markdown-Renderer (XSS)
php -l backend/api/index.php                        # Syntax – NICHT Teil von deploy.sh
```

`deploy.sh` führt seit v0.9.51 `tests-sprechtag.sh` und sämtliche Suiten in
`tests/` aus und hält bei einer roten an. `php -l` lief dort nie – siehe
`docs/ENTSCHEIDUNGEN.md`, E4.

## Dateistruktur

Siehe `NEUES_PROJEKT_PROMPT.md`-Vorlage; WebUntis-Clients in
`backend/auth/` sind **vendored** aus `hornse/webuntis-client-php`
(dort ändern, hierher kopieren).

## Lizenz

GPL-3.0-or-later – siehe [`LICENSE`](LICENSE).
