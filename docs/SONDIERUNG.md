# Sondierung – Anleitung und Interpretation

Das Sondierwerkzeug (Startseite der App) beantwortet die drei offenen
Machbarkeitsfragen des Projekts. Es arbeitet **ausschließlich lesend**
(authenticate, GET-Aufrufe, logout) und speichert weder Passwörter noch
WebUntis-Daten. Lediglich der Aufruf selbst wird in `login_log`
protokolliert (Brute-Force-Bremse).

## Empfohlene Durchläufe

**Nachlauf v0.2.0 (offen):** Testeltern-Konto, Gruppen „Basis" + „Stundenplan",
Zeitraum **einer normalen Schulwoche** (z. B. 2026-06-15 bis 2026-06-19).
Das Werkzeug wählt jetzt automatisch die Kind-IDs aus `user.students` und
listet je Kind die gefundenen Lehrkräfte („🧑‍🏫 Lehrkräfte im Zeitraum").

Zum Vergleich anschließend derselbe Lauf mit dem Lehrkraft-Konto – dort ist
`user.students` leer, die Stundenplan-Probe wird also übersprungen; das ist
das erwartete Verhalten.

Erledigt (19.07.2026): Basis + Sprechtag + Mitteilungen mit Eltern-,
Lehrkraft- und Admin-Konto – Ergebnisse siehe unten.

Nach jedem Lauf **„Bericht als Markdown kopieren"** und in den Projekt-Chat
einfügen – auf dieser Basis wird der Adapter implementiert.

## Was die Proben beantworten

### authenticate (JSON-RPC)
Liefert den **personType**. Befund 07/2026: Erziehungsberechtigte = **12**
(LEGAL_GUARDIAN), Lehrkraft = 2, WebUntis-Admin = 16 (personId -1),
Schüler = 5. Diese Werte stehen in `allowed_person_types` der config.php.

### REST-Zugang (/api/token/new)
Nur wenn hier ein JWT kommt, ist die interne REST-API mit diesem Konto
nutzbar. Falls nicht: Eltern-Funktionen müssten komplett über einen
Dienst-/Admin-Kontext laufen.

### app/data (Basis)
Die Quelle der **Kind-Zuordnung** (bestätigt 07/2026): `user.students[]`
enthält beim Eltern-Konto die verknüpften Kinder. Der Bericht zeigt alle
gefundenen Strukturen („👪") plus die daraus gewählten IDs
(`schueler_ids_gewaehlt`) und die `user_id` für Mitteilungen.
Achtung: `user.person` ist die eigene Person des Kontos, **kein Kind**.

### Sprechtag-Endpunkte
Am 19.07.2026 lieferten alle Kandidaten 404 (siehe Befunde unten) – die
Gruppe ist nur noch optional. Interpretation der Statuscodes:

| Status | Bedeutung |
|---|---|
| 200 | Endpunkt existiert – JSON-Schlüssel + Roh-Auszug zeigen das Datenmodell |
| 401/403 | existiert, aber Rechte fehlen mit diesem Konto (gelb) |
| 404 mit `validationErrors` | existiert, erwartet andere Parameter – Fehlertext lesen! |
| 404 „Not Found" ohne Details | Pfad existiert vermutlich nicht |
| 405 | existiert, erwartet andere HTTP-Methode (bei Mitteilungen positiv!) |

Wenn keiner der Kandidaten trifft: In der WebUntis-Weboberfläche als
Testeltern den Sprechtag öffnen und in den Browser-Entwicklerwerkzeugen
(Netzwerk-Tab, Filter „rest") die echten Pfade ablesen; diese dann unter
„Erweitert → Eigene Zusatzpfade" nachsondieren.

### Stundenplan-Proben
`timetable/entries` mit `resourceType=STUDENT` wird **je Kind einzeln**
abgefragt (IDs automatisch aus `user.students`). Bei Status 200 extrahiert
das Werkzeug alle `current.type == "TEACHER"`-Elemente und zeigt sie als
„🧑‍🏫 Lehrkräfte im Zeitraum" – das ist der Nachweis für die Kurs-Ebene
(wichtig für die Oberstufe). Leere Liste trotz 200 heißt meist: Zeitraum
liegt in den Ferien.

### Mitteilungs-Endpunkte
Nur GET-Proben. Befund 07/2026: `/messages` und `/messages/status` sind für
Eltern und Lehrkräfte erreichbar (200), `/messages/recipients` erwartet eine
User-ID vom Typ Long. Der Versandweg (POST) wird in einem späteren Paket
gezielt mit einem Testempfänger erprobt – nicht mit diesem Werkzeug.

## Befunde der Sondierung vom 19.07.2026 (frg-dusseldorf)

Drei Läufe: Testeltern (`hornse@icloud.com`), Lehrkraft (`ho`), Admin (`adminho`).

| Frage | Ergebnis |
|---|---|
| Eltern-Login | **GELÖST**: personType **12**, Rolle `LEGAL_GUARDIAN`, eigene personId. JWT für REST funktioniert auch mit Eltern-Konto. |
| Kind-Zuordnung | **GELÖST**: `app/data` → `user.students[]` liefert beim Eltern-Konto die verknüpften Kinder (id, displayName). Bei Lehrkraft/Admin ist das Array leer. Kein Schild-Import nötig. |
| Kind → Lehrkräfte | offen – Probe lief wegen eines Werkzeug-Bugs (siehe unten) nie mit echter Kind-ID. Nachlauf erforderlich. |
| Sprechtag-Endpunkte | Alle sieben Kandidaten liefern über alle Konten 404 `NOT_FOUND` **ohne** validationErrors → Pfade existieren nicht. Für das Projekt irrelevant, da Kind- und Lehrkraftzuordnung anders gelöst werden. |
| Mitteilungen | vielversprechend: `/messages` und `/messages/status` antworten für Eltern **und** Lehrkräfte mit 200. `/messages/recipients` liefert 400 mit `parameter 'recipients' … expected type Long` → Endpunkt existiert und erwartet eine **User-ID** (nicht personId!). |

### Wichtige ID-Unterscheidung

`app/data` liefert zwei verschiedene Nummern, die nicht verwechselt werden dürfen:

* `user.id` – **User-ID** (Login-Konto), Adressat für Mitteilungen
* `user.person.id` – **Person-ID**, identisch mit `personId` aus authenticate
* `user.students[].id` – IDs der **Kinder** (Schüler-Person-IDs)

### Behobener Werkzeug-Bug

Die automatische Schüler-ID-Erkennung nahm die *erste* gefundene Struktur –
und das war `user.person`, also die Person des angemeldeten Elternteils
selbst (476). Die STUDENT-Stundenplanprobe lief damit gegen die falsche ID
und meldete folgerichtig `NOT_FOUND`. Seit v0.2.0 wählt
`sondierung_schueler_ids()` nur Pfade, die nach Schüler/Kind aussehen,
verwirft `-1` und sondiert **je Kind einzeln**.

### Zweiter Fallstrick: Zeitraum

Die Läufe vom 19.07. fielen in die Sommerferien (Schuljahr endete am
19.07.2026) – ein leerer Stundenplan wäre auch bei korrekter ID zu
erwarten gewesen. Deshalb gibt es jetzt die Felder **Zeitraum von/bis**:
für den Nachlauf eine normale Schulwoche des laufenden Schuljahres wählen,
z. B. 2026-06-15 bis 2026-06-19.


## Gruppe „Klassen & Schüler:innen" (v0.4.2)

Klärt die Grundlage für die Zuordnungsvorbereitung:

* **`getKlassen`** – Klassenliste (Grundlage für den Klassenweg, Sek I)
* **`getStudents`** – alle Schüler:innen mit ihren Feldern; entscheidend
  ist, **welche** Felder mitkommen und ob eine Gruppenzugehörigkeit
  (z. B. „SuS über 18") darunter ist
* **REST-Varianten** `students`, `persons`, `student-groups`, `groups` –
  liefern sie mehr als JSON-RPC?

**Datenschutz:** Der Bericht enthält **keine Klarnamen**. Ausgegeben werden
Feldnamen, Anzahlen und ein Struktur-Beispiel, in dem alle Namensfelder
(`name`, `foreName`, `longName`, `displayName`, `key`) durch „…" ersetzt
sind. Bei erfolgreichen REST-Antworten wird der Inhalt bewusst nicht
abgedruckt, nur der Statuscode und die obersten JSON-Schlüssel.

Der Lauf braucht ein Konto mit Leserecht auf die Stammdaten – in der Regel
das Admin- oder ein Dienstkonto.

### Was der Befund entscheidet

| Ergebnis | Konsequenz |
|---|---|
| `getStudents` liefert ein Gruppenfeld mit „über 18" o. ä. | Volljährige lassen sich direkt erkennen und getrennt behandeln |
| kein Gruppenfeld | Volljährigkeit wird über `personType 5` beim Login erkannt (Schüler:innen buchen dann selbst) |
| `getKlassen` liefert Klassenliste | Klassenweg für Sek I umsetzbar |
| REST-Variante liefert mehr Felder | ggf. bessere Datenquelle als JSON-RPC |

## Nach der Sondierung

`'sondierung_freigeschaltet' => false` in `config.php` setzen, sobald die
Berichte vorliegen – das Werkzeug nimmt Zugangsdaten entgegen und gehört
nicht dauerhaft offen ins Netz.
