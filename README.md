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
- Branding (Schulname, Logo, Farben) über die Oberfläche
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

## Eckdaten

| Was | Wert |
|---|---|
| Domain | `sprechtag.hornse.de` |
| Port | `8085` (PHP built-in Server via supervisord) |
| Datenbank | `hornse_sprechtag` (MariaDB) |
| Server | `hornse@halimede.uberspace.de` |
| Work-Tree | `/home/hornse/sprechtag` |
| Bare Repo | `/home/hornse/repos/sprechtag.git` |
| GitHub | `hornse/sprechtag` (öffentlich) |
| Stack | PHP 8.1+ ohne Framework, Vanilla JS, MariaDB/PDO |
| Lizenz | GPL-3.0-or-later (siehe `LICENSE`) |

## Erstinstallation (Server)

```bash
# 1. Bare Repo + Work-Tree
mkdir -p ~/repos && git init --bare ~/repos/sprechtag.git
git -C ~/repos/sprechtag.git symbolic-ref HEAD refs/heads/main
mkdir -p ~/sprechtag
cat > ~/repos/sprechtag.git/hooks/post-receive << 'EOF'
#!/bin/sh
GIT_WORK_TREE=/home/hornse/sprechtag git checkout -f main
EOF
chmod +x ~/repos/sprechtag.git/hooks/post-receive

# 2. Datenbank (nach erstem Push)
mysql -e "CREATE DATABASE IF NOT EXISTS hornse_sprechtag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# Schema und alle Migrationen in numerischer Reihenfolge einspielen.
# Die Migrationen sind idempotent und können gefahrlos erneut laufen.
for f in ~/sprechtag/sql/[0-9]*.sql; do
  echo "-> $f"; mysql hornse_sprechtag < "$f"
done

# 3. Konfiguration
cp ~/sprechtag/backend/config.example.php ~/sprechtag/backend/config.php
nano ~/sprechtag/backend/config.php   # DB-Passwort aus ~/.my.cnf
# dienstkonto_schluessel erzeugen (mind. 32 Zeichen):
#   php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'

# 4. Dienst (supervisord)
cat > ~/etc/services.d/sprechtag.ini << 'EOF'
[program:sprechtag]
command=php -S 0.0.0.0:8085 /home/hornse/sprechtag/backend/router.php
autostart=yes
autorestart=yes
EOF
supervisorctl reread && supervisorctl update

# 5. Domain + Backend
uberspace web domain add sprechtag.hornse.de
uberspace web backend set sprechtag.hornse.de/ --http --port 8085
```

> `backend/config.php` enthält Zugangsdaten und steht in `.gitignore` – sie darf
> **niemals** eingecheckt werden. Als Vorlage dient `backend/config.example.php`.

Lokal: Remotes `github` und `uberspace`
(`ssh://hornse@halimede.uberspace.de/home/hornse/repos/sprechtag.git`)
einrichten, danach `./deploy.sh "Nachricht"`.

## Entwicklung

Kein Build-Schritt: `frontend/index.html` und `frontend/app.js` werden direkt
ausgeliefert und bearbeitet.

Vor einem Release:

```bash
for t in tests/frontend_*.js; do node "$t"; done   # Frontend-/Struktur-Tests
php tests/run_markdown.php                          # Markdown-Renderer (XSS)
php -l backend/api/index.php                        # Syntax (läuft auch in deploy.sh)
```

Bei neuen Migrationen nach dem Deploy:

```bash
mysql hornse_sprechtag < sql/NN_name.sql
supervisorctl restart sprechtag
```

## Debug

```bash
supervisorctl tail sprechtag stderr | tail -20
curl -s https://sprechtag.hornse.de/api/health

# Deploy durchgekommen? (0 = nein, >=1 = ja; sonst Browser-Cache)
curl -s https://sprechtag.hornse.de/app.js | grep -c '<funktionsname>'
```

## Dateistruktur

Siehe `NEUES_PROJEKT_PROMPT.md`-Vorlage; WebUntis-Clients in
`backend/auth/` sind **vendored** aus `hornse/webuntis-client-php`
(dort ändern, hierher kopieren).

## Lizenz

GPL-3.0-or-later – siehe [`LICENSE`](LICENSE).
