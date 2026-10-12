# sprechtag

Terminbuchung für den Elternsprechtag einer Schule, mit der Anmeldung über
WebUntis. Erziehungsberechtigte und volljährige Schülerinnen und Schüler
buchen Gesprächstermine bei Lehrkräften; Lehrkräfte laden ein und buchen
stellvertretend; die Verwaltung richtet den Sprechtag ein und führt ihn
durch.

Entwickelt für und im Einsatz am Friedrich-Rückert-Gymnasium Düsseldorf.
Es läuft also an einer echten Schule – und ist nicht generisch gebaut,
sondern an dieser einen gewachsen.

> **Zwei Dinge vorweg:**
>
> - **Die WebUntis-Anbindung nutzt zum großen Teil undokumentierte
>   Schnittstellen.** WebUntis kann sie mit jedem Update ändern, ohne
>   Ankündigung; dann ist Nacharbeit nötig. Welche es sind, steht in
>   `docs/WEBUNTIS.md`.
> - **Vieles ist schulspezifisch einzurichten** – in `config.php`, in der
>   Oberfläche der Verwaltung und in WebUntis selbst (Konten, Verknüpfung
>   der Eltern mit den Kindern, Benutzergruppen, Empfängerlisten). Ohne das
>   funktioniert wenig.

## Funktionen

**Für Erziehungsberechtigte**
- Anmeldung mit dem eigenen WebUntis-Elternzugang, nicht mit dem des Kindes
- Bei mehreren Kindern die Auswahl des Kindes, um dessen Termine es geht
- Kacheln der Lehrkräfte des Kindes – Einladende zuerst, dann
  Klassenleitung und Fachlehrkräfte, darunter Ansprechpersonen mit
  besonderer Aufgabe; eine freie Zeit antippen, und der Termin ist gebucht
- Ein optionaler Hinweis an die Lehrkraft beim Buchen
- „Meine Termine“ mit Absage, einzelne Termine als Kalendereintrag und ein
  persönlicher Abo-Link für den Kalender
- Je Kind bei jeder Lehrkraft ein Termin

**Für volljährige Schülerinnen und Schüler**
- Selbst buchen, wenn die Schule ihre WebUntis-Benutzergruppe freigeschaltet
  hat – sonst buchen die Erziehungsberechtigten

**Für Lehrkräfte**
- Eigenes Zeitraster mit gebuchten und freien Zeiten
- Einladungen über die Suche nach dem Kind (Name oder Klasse) – in der
  Vorbereitung, in Phase 1 als Weg zum Termin, in Phase 2 als Erinnerung
- Stellvertretend buchen, wenn Eltern verhindert sind; die Eltern bekommen
  eine Nachricht über WebUntis
- Druckbare Tischvorlage, Tagesliste als Kalenderdatei, Abo-Link

**Für die Verwaltung**
- Sprechtage anlegen, kopieren und archivieren; Phasen schalten
  (Vorbereitung, Phase 1 nur auf Einladung, Phase 2 offen für alle,
  geschlossen)
- Lehrkräfte, Räume und Anwesenheit in einer Tabelle; Zeitfenster,
  Halbtagskräfte mit erster oder zweiter Hälfte, Pausen, mehrfach belegte
  Räume farbig markiert
- Krankheitsausfall: Termine einer Lehrkraft freigeben, die Eltern werden
  benachrichtigt
- Erinnerungen vor dem Sprechtag an eine WebUntis-Empfängerliste, bewusst
  von Hand ausgelöst
- Stammdaten-Sync (Kürzel der Lehrkräfte und Räume aus WebUntis)
- Erscheinungsbild und Texte (Logo, Schulname, Hilfe- und Hinweistexte in
  Markdown, serverseitig gesäubert)
- Login-Protokoll mit Frist, Anzeige für Monitore im Foyer

**Für alle**
- Die Hilfe in der Anwendung, je Rolle, mit einem Abschnitt zum Datenschutz
- Auf dem Telefon werden Tabellen zu Karten
- Sichtbare Fokus-Rahmen, ein Sprunglink zum Inhalt

## Datenschutz

Es geht um Daten Minderjähriger. **Passwörter werden weder gespeichert noch
protokolliert**; ob sie stimmen, prüft WebUntis. Was zu einem Sprechtag
gespeichert wird (Termine mit Name und Klasse des Kindes, Hinweise an die
Lehrkraft, Einladungen, Benachrichtigungen) und was das Archivieren
überdauert (Login-Protokoll mit Frist, Kennnummer für den Kalender-Link),
steht im Abschnitt „Datenschutz“ der Hilfe. Fehlgeschlagene Anmeldungen
werden zum Schutz vor dem Durchprobieren von Passwörtern festgehalten,
erfolgreiche nur, wenn die Schule es einstellt.

## Voraussetzungen

**Server:** PHP 8.1 oder neuer, MariaDB, HTTPS davor. Die Anwendung läuft
als PHP built-in Server mit `backend/router.php` hinter einem Proxy, der
HTTPS terminiert; der Router geht von HTTPS aus (Sitzungs-Cookie mit
`Secure`). Andere Webserver sind nicht ausprobiert. Kein Framework, kein
Build-Schritt.

**WebUntis:** Was dort eingerichtet und gepflegt sein muss – Konten und
Rechte, die Verknüpfung der Eltern mit den Kindern, Klassen, Stundenplan,
Benutzergruppen, Empfängerlisten –, steht in **`docs/WEBUNTIS.md`**. Das
ist der Teil, an dem es an einer neuen Schule am ehesten hängt.

## Installation

Allgemein, mit Platzhaltern in Großbuchstaben. Wie die Referenzinstallation
eingerichtet ist (Uberspace, supervisord, Auslieferung über ein Bare-Repo),
steht als Beispiel in `docs/BETRIEB.md`.

```bash
# 1. Code holen
git clone https://github.com/hornse/sprechtag.git
cd sprechtag

# 2. Datenbank anlegen und alle Dateien aus sql/ in Namensreihenfolge einspielen
mysql -e "CREATE DATABASE IF NOT EXISTS DATENBANK CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
for f in sql/[0-9]*.sql; do echo "-> $f"; mysql DATENBANK < "$f"; done

# 3. Konfiguration
cp backend/config.example.php backend/config.php
# config.php befüllen: WebUntis-Adresse und Schulkennung, zugelassene Rollen,
# Kürzel der Verwaltung, Datenbankzugang

# 4. Starten (hinter einem HTTPS-Proxy)
php -S 127.0.0.1:8085 backend/router.php
```

**Zur Datenbank:** Die Dateien in `sql/` sind über die Zeit entstanden. Bei
einer **bestehenden** Installation kommt jede einzeln, teils vor, teils
nach einer Auslieferung (siehe Kopf jeder Datei). Auf einer **leeren**
Datenbank werden sie alle in Namensreihenfolge eingespielt; Dateien mit
`_pruefung` im Namen lesen dabei nur. Gegen eine leere MariaDB ist diese
Schleife **nicht ausprobiert** – bitte die Ausgabe ansehen.

`backend/config.php` enthält Zugangsdaten und steht in `.gitignore`; sie
darf nie eingecheckt werden.

**Danach in der Oberfläche,** angemeldet mit einem Lehrerkonto, dessen
Kürzel in `admin_kuerzel` steht: Gruppen der Volljährigen (einmal mit dem
WebUntis-Admin-Konto, siehe `docs/WEBUNTIS.md`), Empfängerliste für
Erinnerungen, Erscheinungsbild und Texte, dann der erste Sprechtag.

## Entwicklung

Kein Build-Schritt: `frontend/index.html`, `frontend/app.js` und
`frontend/style.css` werden direkt ausgeliefert und bearbeitet.

Prüfungen: `./tests-sprechtag.sh` und jede Suite in `tests/`
(`php tests/run_*.php`, `node tests/*_test.js`). Die Tests laufen gegen
SQLite, der Betrieb gegen MariaDB. `deploy.sh` führt alle aus und hält bei
einer roten an; `php -l` gehört nicht dazu (`docs/ENTSCHEIDUNGEN.md`, E4).

**Vendored, nicht hier ändern:** die WebUntis-Clients in `backend/auth/`
(aus `hornse/webuntis-client-php`) sowie `ci-tokens.css` und
`ci-icons.svg` in `frontend/vendor/ci-css/` (aus `hornse/ci-css`).
Änderungen gehören in die Quelle und werden von dort kopiert.

## Dokumentation

| Datei | Inhalt |
|---|---|
| `docs/WEBUNTIS.md` | was WebUntis bereitstellen muss, was sich ändern kann |
| `docs/BETRIEB.md` | Einrichtung und Betrieb der Referenzinstallation |
| `docs/ENTSCHEIDUNGEN.md` | Entscheidungen mit Begründung (E1, E2, …) |
| `CHANGELOG.md` | was sich je Version geändert hat |
| `docs/BEFUND-*.md` | einzelne Befunde mit Datum; Protokoll |

Die Anleitung für Eltern, Schülerinnen und Schüler und Lehrkräfte ist die
Hilfe in der Anwendung.

## Lizenz

GPL-3.0-or-later – siehe [`LICENSE`](LICENSE).
