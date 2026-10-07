# sprechtag

Elternsprechtag-Organisation für das Friedrich-Rückert-Gymnasium Düsseldorf
auf Basis der WebUntis-Logins. Erziehungsberechtigte und volljährige
Schüler:innen buchen Termine bei Lehrkräften über ein Zeitraster – mit
zweiphasigem Ablauf (Phase 1: nur eingeladene Eltern, buchbar auch
stellvertretend durch Lehrkräfte), Raumverteilung, Pausenautomatik,
Teilzeit-Anwesenheitsfenstern und Archivierung.

**Stand: Paket 3 (v0.4.0)** – vollständige Buchung, Adminseite,
Rollenmodell und Mitteilungen an Erziehungsberechtigte. Der Versandweg
der WebUntis-Schnittstelle ist undokumentiert; das System probiert
mehrere Feldstrukturen und hält bei Fehlschlag die Mitteilungen zum
manuellen Versand bereit (siehe `docs/MITTEILUNGEN.md`).

Bedienung: `docs/BEDIENUNG.md` · Mitteilungen: `docs/MITTEILUNGEN.md`
· Sondierungsbefunde: `docs/SONDIERUNG.md`

## Eckdaten

| Was | Wert |
|---|---|
| Domain | `sprechtag.hornse.de` |
| Port | `8085` (PHP built-in Server via supervisord) |
| Datenbank | `hornse_sprechtag` (MariaDB) |
| Server | `hornse@halimede.uberspace.de` |
| Work-Tree | `/home/hornse/sprechtag` |
| Bare Repo | `/home/hornse/repos/sprechtag.git` |
| GitHub | `hornse/sprechtag` (privat) |
| Stack | PHP 8.1+ ohne Framework, Vanilla JS, MariaDB/PDO |
| Lizenz | GPL-3.0-or-later |

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

# 2. Datenbank
mysql -e "CREATE DATABASE IF NOT EXISTS hornse_sprechtag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql hornse_sprechtag < ~/sprechtag/sql/01_schema.sql   # nach erstem Push
mysql hornse_sprechtag < ~/sprechtag/sql/02_sprechtag.sql
mysql hornse_sprechtag < ~/sprechtag/sql/03_mitteilungen.sql
mysql hornse_sprechtag < ~/sprechtag/sql/04_klausuren.sql
mysql hornse_sprechtag < ~/sprechtag/sql/05_schueler.sql
mysql hornse_sprechtag < ~/sprechtag/sql/06_diagnose.sql
mysql hornse_sprechtag < ~/sprechtag/sql/07_austritt.sql
mysql hornse_sprechtag < ~/sprechtag/sql/08_mitteilung_kind.sql
mysql hornse_sprechtag < ~/sprechtag/sql/09_grund_null.sql

# 3. Konfiguration
cp ~/sprechtag/backend/config.example.php ~/sprechtag/backend/config.php
nano ~/sprechtag/backend/config.php   # DB-Passwort aus ~/.my.cnf

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

Lokal: Remotes `github` und `uberspace`
(`ssh://hornse@halimede.uberspace.de/home/hornse/repos/sprechtag.git`)
einrichten, danach `./deploy.sh "Nachricht"`.

## Debug

```bash
supervisorctl tail sprechtag stderr | tail -20
curl -s https://sprechtag.hornse.de/api/health
```

## Dateistruktur

Siehe `NEUES_PROJEKT_PROMPT.md`-Vorlage; WebUntis-Clients in
`backend/auth/` sind **vendored** aus `hornse/webuntis-client-php`
(dort ändern, hierher kopieren).
