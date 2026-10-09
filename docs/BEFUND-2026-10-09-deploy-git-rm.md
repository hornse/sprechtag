# Befund 09.10.2026 — deploy.sh übernimmt keine mit `git rm` vorgemerkte Löschung

**Art:** Befund, keine Behebung. **Betrifft:** `deploy.sh`, E4 (namentliches
Addieren). **Vermutlich die ganze Reihe** – ein Fall für `koordination`,
aber nicht jetzt.

## Was geschah (beim Ausrollen von v0.9.72, gemessen)

Drei gelöschte Dateien (`backend/api/dienstkonto.php`, zwei Suiten) waren
mit `git rm` vorgemerkt und wurden `deploy.sh` namentlich übergeben. Die
Sichtung und alle 60 Suiten liefen durch. Dann brach das Skript ab:

    fatal: pathspec 'backend/api/dienstkonto.php' did not match any files

**Ursache:** `git add -- <pfad>` findet einen Pfad nicht, der weder im Baum
noch im Index steht. Die Löschung war schon vorgemerkt; es gab für `git add`
nichts mehr zu finden. Das ist eine Nebenwirkung des namentlichen Addierens
aus E4.

**Folgen:** keine. Der Abbruch kam vor dem Commit, nichts wurde gepusht.
Zurück blieb nur der Cache-Stempel in `frontend/index.html`, den das Skript
vorher setzt. Ohne Rücknahme hätte er den nächsten Lauf angehalten („nicht
genannt“).

## Umgehung, bis es behoben ist

Gelöschte Dateien mit `rm` aus dem Baum nehmen, **nicht** mit `git rm`. Eine
im Baum gelöschte, verfolgte Datei übernimmt `git add --` als Löschung. So
lief der zweite Versuch durch (`158bc25`).

## Offen

- Die Behebung im Skript, etwa `git add -A -- <genannte pfade>` statt
  `git add --` (ungeprüft).
- Ob die übrigen Projekte der Reihe dieselbe Stelle haben: nicht nachgesehen.
