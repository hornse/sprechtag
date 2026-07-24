# Dienstkonto und Lehrkraft-Ermittlung

## Wozu

Eltern sollen beim Buchen sofort sehen, welche Lehrkräfte ihr Kind
unterrichten. Diese Zuordnung steht nicht in der Datenbank, sondern muss
aus dem WebUntis-Stundenplan des Kindes ermittelt werden – und dafür
braucht es ein Konto mit Leserecht auf Schülerstundenpläne.

Das System speichert die Zugangsdaten eines Dienstkontos verschlüsselt und
ermittelt damit **beim ersten Aufruf je Kind** automatisch im Hintergrund.
Danach kommt alles aus dem Cache (`kind_lehrer_cache`).

## Einrichtung

1. **Schlüssel erzeugen** und in `backend/config.php` eintragen:

   ```bash
   php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'
   ```

   ```php
   'dienstkonto_schluessel' => '…die erzeugte Zeichenkette…',
   ```

   Ohne Schlüssel lassen sich keine Zugangsdaten speichern. Wird er später
   geändert, müssen sie neu eingetragen werden (das System meldet das).

2. **Dienstkonto eintragen**: Administration → „Dienstkonto für die
   Lehrkraft-Ermittlung" → Benutzername und Passwort → Speichern.
   Der Status zeigt anschließend, ob die Verschlüsselung funktioniert.

## Sicherheit – ehrliche Einordnung

Verschlüsselt wird mit libsodium (XSalsa20-Poly1305), ersatzweise mit
OpenSSL (AES-256-GCM). Der Schlüssel liegt in `config.php`, also **nicht**
in der Datenbank – ein reiner Datenbank-Dump reicht daher nicht aus, um an
das Passwort zu kommen.

Wer aber Lesezugriff auf **beides** hat – Dateisystem und Datenbank –, kann
die Zugangsdaten entschlüsseln. Das ist prinzipbedingt und nicht zu
umgehen: Der Server muss das Passwort im Klartext haben, um sich bei
WebUntis anzumelden. Daraus folgen zwei Empfehlungen:

* **Eigenes Dienstkonto** mit minimalen Rechten anlegen (Stundenpläne
  lesen, Mitteilungen senden) – nicht das persönliche Admin-Konto.
* Nach dem Sprechtag die Zugangsdaten über „Entfernen" löschen, wenn sie
  längere Zeit nicht gebraucht werden.

Ohne hinterlegtes Dienstkonto funktioniert das System weiterhin – die
Ermittlung muss dann über `POST /api/lehrer-ermitteln` mit übergebenen
Zugangsdaten ausgelöst werden.

## Was ermittelt wird

Für jedes Kind wird der Stundenplan des **Referenzzeitraums** abgefragt
(Standard: vier Wochen, endend eine Woche vor dem Sprechtag; je Sprechtag
einstellbar). Daraus werden die unterrichtenden Lehrkräfte samt Fächern und
Wochenstunden extrahiert – ohne Vertretungsstunden, ohne Pausenaufsichten,
ohne Klausuren. Ausgefallene Stunden zählen mit, weil sie nichts daran
ändern, wer das Fach regulär unterrichtet.

Gespeichert werden nur IDs: Schüler-ID, Lehrkraft-ID, Fachkürzel und
Stundenzahl. Keine Namen. Beim Archivieren des Sprechtags wird der Cache
gelöscht.

## Grenzen (Stand Juli 2026)

Eine Sammelvorbereitung für alle Kinder ist derzeit **nicht** möglich:

* `getKlassen()` scheitert zwischen zwei Schuljahren mit Fehler -8998
  („schoolyear is null"). Sobald das neue Schuljahr aktiv ist, sollte der
  Weg über Klassenstundenpläne funktionieren – die Sondierung prüft das
  inzwischen auch mit expliziter `schoolyearId`.
* `getStudents()` liefert keine Klassen- oder Jahrgangszuordnung
  (Felder: id, key, name, foreName, longName, gender). Damit lässt sich
  nicht bestimmen, wer zur Oberstufe gehört.
* Eine Gruppe „SuS über 18" ist über die API nicht sichtbar. Volljährige
  erkennt das System daran, dass sie sich selbst anmelden (personType 5).

Deshalb wird zunächst bei Bedarf ermittelt: einmal je Kind, ein bis zwei
Sekunden beim ersten Aufruf der Buchungsseite.
