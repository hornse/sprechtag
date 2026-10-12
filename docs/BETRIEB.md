# Betrieb der Referenzinstallation

Am 12.10.2026 wörtlich aus der `README.md` hierher verschoben. Hostname, Benutzername und Pfade werden in einem eigenen Schritt durch Platzhalter ersetzt.

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

## Migrationen nach dem Deploy

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
