# Sondierung – Anleitung und Interpretation

Das Sondierwerkzeug (Startseite der App) beantwortet die drei offenen
Machbarkeitsfragen des Projekts. Es arbeitet **ausschließlich lesend**
(authenticate, GET-Aufrufe, logout) und speichert weder Passwörter noch
WebUntis-Daten. Lediglich der Aufruf selbst wird in `login_log`
protokolliert (Brute-Force-Bremse).

## Empfohlene Durchläufe

1. **Testeltern-Konto** – Gruppen „Basis" + „Sprechtag" (der wichtigste Lauf!)
2. Testeltern-Konto – Gruppe „Stundenplan" (nachdem Lauf 1 eine Schüler-ID
   gefunden hat; sonst Schüler-ID unter „Erweitert" manuell eintragen)
3. Testeltern-Konto – Gruppe „Mitteilungen"
4. **Lehrkraft-Konto** – alle Gruppen (Vergleich: was sehen Lehrkräfte?)
5. Optional Admin-Konto – alle Gruppen

Nach jedem Lauf **„Bericht als Markdown kopieren"** und in den Projekt-Chat
einfügen – auf dieser Basis wird der Adapter implementiert.

## Was die Proben beantworten

### authenticate (JSON-RPC)
Liefert für das Eltern-Konto den bislang unbekannten **personType**
(Vermutung: 15) sowie personId und ggf. weitere Felder. Der Wert wandert
anschließend in `allowed_person_types` der config.php und ins
Rollen-Mapping.

### REST-Zugang (/api/token/new)
Nur wenn hier ein JWT kommt, ist die interne REST-API mit diesem Konto
nutzbar. Falls nicht: Eltern-Funktionen müssten komplett über einen
Dienst-/Admin-Kontext laufen.

### app/data (Basis)
Der Kandidat für die **Kind-Zuordnung**. Der Bericht durchsucht die Antwort
automatisch nach Strukturen mit Schlüsseln wie `students`, `children`,
`persons` und zeigt Pfad, Anzahl und IDs („👪 Kind-/Personen-Struktur").
Ein Treffer mit plausibler Anzahl (= Anzahl der Testkinder) beantwortet
Frage 2 positiv.

### Sprechtag-Endpunkte
Da das Sprechtag-Modul lizenziert ist, muss die WebUntis-Oberfläche einen
dieser Pfade (oder einen ähnlichen) nutzen. Interpretation:

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
`timetable/entries` mit `resourceType=STUDENT` klärt, ob wir je Kind die
unterrichtenden Lehrkräfte (Kurs-Ebene, wichtig für die Oberstufe!)
ableiten können – und ob das mit Eltern-Session für das eigene Kind
funktioniert. Die CLASS-Probe ist der Fallback für die Sekundarstufe I.

### Mitteilungs-Endpunkte
Nur GET-Proben. Ziel ist zunächst der Nachweis, dass die
Nachrichten-Endpunkte mit dem jeweiligen Konto erreichbar sind. Der
eigentliche Versandweg (POST) wird erst nach Auswertung dieser Befunde –
und dann gezielt mit einem Testempfänger – erprobt.

## Nach der Sondierung

`'sondierung_freigeschaltet' => false` in `config.php` setzen, sobald die
Berichte vorliegen – das Werkzeug nimmt Zugangsdaten entgegen und gehört
nicht dauerhaft offen ins Netz.
