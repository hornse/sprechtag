# Betrieb der Referenzinstallation

Wie die Referenzinstallation eingerichtet ist und betrieben wird: ein
Uberspace mit supervisord, ausgeliefert über ein Bare-Repo mit Hook. Am
12.10.2026 aus der `README.md` hierher verschoben, danach mit Platzhaltern
versehen und an den Stand v0.9.93 angepasst. Für eine andere Umgebung ist
es ein Beispiel; die allgemeinen Schritte stehen in der `README.md`, was in
WebUntis vorliegen muss, in `docs/WEBUNTIS.md`.

**Platzhalter.** Die Großbuchstaben-Wörter sind keine echten Werte, sondern
durch die eigenen zu ersetzen – wie in `backend/config.example.php`:

| Platzhalter | steht für |
|---|---|
| `BENUTZER` | Benutzername auf dem Server |
| `SERVER` | Hostname des Servers |
| `DOMAIN` | Domain, unter der die Anwendung erreichbar ist |
| `DATENBANK` | Name der MariaDB-Datenbank |
| `DIENST` | Name des supervisord-Dienstes |

## Eckdaten

| Was | Wert |
|---|---|
| Domain | `DOMAIN` |
| Port | `8085` (PHP built-in Server via supervisord) |
| Datenbank | `DATENBANK` (MariaDB) |
| Server | `BENUTZER@SERVER` |
| Work-Tree | `/home/BENUTZER/sprechtag` |
| Bare Repo | `/home/BENUTZER/repos/sprechtag.git` |
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
GIT_WORK_TREE=/home/BENUTZER/sprechtag git checkout -f main
EOF
chmod +x ~/repos/sprechtag.git/hooks/post-receive

# 2. Datenbank (nach erstem Push)
mysql -e "CREATE DATABASE IF NOT EXISTS DATENBANK CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# Auf leerer Datenbank alle Dateien in Namensreihenfolge einspielen
# (zur Reihenfolge siehe README, „Installation“).
for f in ~/sprechtag/sql/[0-9]*.sql; do
  echo "-> $f"; mysql DATENBANK < "$f"
done

# 3. Konfiguration
cp ~/sprechtag/backend/config.example.php ~/sprechtag/backend/config.php
nano ~/sprechtag/backend/config.php   # DB-Passwort aus ~/.my.cnf

# 4. Dienst (supervisord)
cat > ~/etc/services.d/DIENST.ini << 'EOF'
[program:DIENST]
command=php -S 0.0.0.0:8085 /home/BENUTZER/sprechtag/backend/router.php
autostart=yes
autorestart=yes
EOF
supervisorctl reread && supervisorctl update

# 5. Domain + Backend
uberspace web domain add DOMAIN
uberspace web backend set DOMAIN/ --http --port 8085
```

> `backend/config.php` enthält Zugangsdaten und steht in `.gitignore` – sie darf
> **niemals** eingecheckt werden. Als Vorlage dient `backend/config.example.php`.

Lokal: Remotes `github` und `uberspace`
(`ssh://BENUTZER@SERVER/home/BENUTZER/repos/sprechtag.git`)
einrichten, danach `./deploy.sh "Nachricht" datei1 [datei2 …]`. Das Skript
nimmt nur namentlich genannte Dateien mit, führt alle Prüfungen aus und
hält bei einer roten an (E3, E4).

## Migrationen bei einer Auslieferung

`deploy.sh` spielt keine Migrationen ein. Ob eine neue Migration **vor oder
nach** dem Deploy kommt, steht im Kopf der Datei unter „REIHENFOLGE“ – es
gibt beides. Dateien mit `_pruefung` im Namen lesen nur und melden in der
letzten Zeile „OK …“ oder „ANSEHEN …“; sie gehören vor die Migration, die
sie absichern.

```bash
mysql DATENBANK < sql/NN_name.sql
supervisorctl restart DIENST
```

## Debug

```bash
supervisorctl tail DIENST stderr | tail -20
curl -s https://DOMAIN/api/health

# Deploy durchgekommen? (0 = nein, >=1 = ja; sonst Browser-Cache)
curl -s https://DOMAIN/app.js | grep -c '<funktionsname>'
```
