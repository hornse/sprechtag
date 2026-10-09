# Bestandsaufnahme 09.10.2026 — wofür das Dienstkonto heute gebraucht wird

Am Code gelesen, Stand v0.9.70 (`51cd10b`). **Nur Lage, keine Empfehlung** –
die Entscheidung fällt danach. Anlass: Fiele das Dienstkonto weg, entfiele ein
dauerhaft gespeicherter WebUntis-Zugang im Zugriff des Webservers.

Ermittelt über alle Aufrufe von `dk_lesen()` (13 Stellen in sieben Dateien)
und die Funktionen dahinter bis zur Route. Die entscheidende Spalte ist „Wer
ist angemeldet“: Wo jemand angemeldet ist, könnte dessen Sitzung an die Stelle
treten.

**Zum Versandweg vorweg** (`mit_einreihen_und_senden()`, `mitteilungen.php`):
1. Wahl ist die **Sitzung der angemeldeten Person**; das Dienstkonto greift nur,
wenn es **keine** nutzbare Sitzung gibt (abgelaufen, rund 25–30 Minuten, oder
nicht festgehalten). Scheitert der Versand **mit** Sitzung, gibt es **keinen**
Rückfall aufs Dienstkonto – die Mitteilung steht dann auf „fehler“. Das gilt
für alle Mitteilungsstellen 2–5 gleich.

## Die Stellen

| # | Route · Funktion | fachlich | wer ist angemeldet | Dienstkonto heute | ohne Dienstkonto – und der Preis |
|---|---|---|---|---|---|
| 1 | `POST /api/buchungen` (`buchungen.php`, Bestätigung nach Elternbuchung) | Bestätigung an die buchenden Eltern | **Eltern** (oder volljährige Schüler) | **nicht genutzt** – gerufen ohne Zugangsdaten; erst die Sitzung der Eltern, sonst „offen“ | schon heute so. Ob Eltern über ihre Sitzung an sich selbst senden dürfen: **nicht gemessen**, aber **ohne neue Messung ablesbar** am Status der bisherigen Bestätigungen in der Mitteilungsansicht |
| 2 | `POST /api/buchungen/stellvertretend` | Bestätigung an alle Eltern | Lehrkraft | Rückfall bei fehlender Sitzung | Lehrkraft-Sitzung; ist sie abgelaufen, bleibt die Mitteilung offen bis zur neuen Anmeldung |
| 3 | `DELETE /api/buchungen/{id}` | Absage an die Eltern | Lehrkraft / Verwaltung | Rückfall bei fehlender Sitzung | wie 2; PARENTS über die Lehrkraft-Sitzung gemessen (Befund 16) |
| 4 | `POST /api/einladungen` | Einladung an die Eltern | Lehrkraft / Verwaltung | Rückfall bei fehlender Sitzung, dazu 7 | wie 2 |
| 5 | `POST …/lehrer/{lid}/ausfall` (`index.php`) | Absage an alle Betroffenen bei Krankheitsausfall | **Verwaltung** | Rückfall bei fehlender Sitzung | Verwaltungs-Sitzung – ob sie senden darf, **nicht gemessen** |
| 6 | `POST /api/mitteilungen/senden` (`index.php`) | offene Mitteilungen gesammelt versenden | Lehrkraft / Verwaltung | **einziger Weg** neben eingetippten Zugangsdaten; Sitzung hier **nicht** genutzt | Passwort je Versand eintippen – oder auf die Sitzung umstellen (Rechte der Verwaltung: siehe unten) |
| 7 | `mit_eltern_ids_ermitteln()` (`mitteilungen.php`), gerufen von 2 und 4 | Elternkonten über Empfängersuche nach dem Kindnamen | Lehrkraft | **einziger Weg** für die Suche (eigene Anmeldung mit Passwort); Rückfall frühere Buchungen | **entfällt mit E16** (PARENTS) |
| 8 | `GET /api/buchbare-lehrer` (`buchungen.php`) | Lehrkräfte des Kindes aus dem Stundenplan, einmal je Kind und Sprechtag bei leerem Speicher | **Eltern**, volljährige Schüler, Verwaltung | **einziger Weg** | Eltern-Sitzung **gemessen** (08.10.: Stundenplan des eigenen Kindes, 10 Lehrkräfte). Volljährige Schüler und Verwaltung für beliebige Kinder: **nicht gemessen**. Preis bei Fehlschlag: für dieses Kind keine Kacheln |
| 9 | `POST /api/lehrer-ermitteln` (`buchungen.php`) | dasselbe, von Hand | jede Rolle (eigene Kinder) / Verwaltung | Vorgabe; sonst eingetippte Zugangsdaten | eintippen oder Sitzung |
| 10 | Erinnerungen – `erinnerung_empfaenger_ermitteln()`, `erinnerung_versenden()` (`erinnerungen.php`, Route `/api/erinnerungen/…`) | WebUntis-Liste auflösen und an sie senden (CUSTOM, `recipientUserIds`) | **Verwaltung** | **einziger Weg** (weder Sitzung noch Eintippen) | Verwaltungs-Sitzung – ob sie Listen auflösen und senden darf, **nicht gemessen** |
| 11 | `POST /api/schueler/sync` (`index.php`) | Schülerliste über `getStudents` (JSON-RPC) | Verwaltung | Vorgabe; sonst eingetippte Zugangsdaten | **entfällt mit Zug 4** (`pageconfig` über die Sitzung, gemessen) |
| 12 | `GET /api/dienstkonto` (`dk_status()`) | nur Statusanzeige | Verwaltung | Anzeige | entfällt mit dem Dienstkonto |
| 13 | `messung_dienstkonto_sitzung()` (v0.9.70) | Messung | Verwaltung | Messung | entfällt nach der Messung |

## Wo NIEMAND angemeldet ist

- **Kalender-Abo** (`/api/kalender/{token}.ics`): fragt WebUntis **nicht** an –
  nur die Datenbank.
- **Anzeige** (`/api/anzeige`): ebenso nur die Datenbank.
- **Cron, Kommandozeile:** gibt es nicht; das Backend kennt nur Routen.

**An keiner Stelle greift das Dienstkonto, während niemand angemeldet ist.**
Es ersetzt entweder eine fehlende WebUntis-Sitzung (1–5) oder ist der einzige
vorgesehene Weg, obwohl jemand angemeldet ist (6, 7, 10, 11).

## Lage nach der Aufnahme (Betreiber, 09.10.2026)

- 7 entfällt mit E16 (PARENTS), 11 mit Zug 4.
- 6 und 10 hängen an **derselben ungemessenen Frage**: Darf die
  **Verwaltungs-Sitzung** senden und Listen auflösen (auch 5)? Laut
  `MITTEILUNGEN.md` trug ein Admin-Token am 24.07. nur Leserecht – dieselbe
  Doku ist an anderen Stellen überholt. Aus der Oberfläche (Betreiber): Das
  Admin-Konto hat bei den Empfängerarten nur STAFF und CUSTOM; die
  Erinnerungen nutzen CUSTOM. Es könnte also gehen. **Nächste mögliche
  Messung:** Erinnerungsversand über die Verwaltungs-Sitzung an die Testliste
  – ob vor Zug 4, wird im Chat entschieden.
- Der durchgehende Preis eines Wegfalls: Die WebUntis-Sitzung lebt rund 25–30
  Minuten; danach müsste sich neu anmelden, wer eine WebUntis-Aktion auslöst.
  Heute fängt das Dienstkonto das an 2–5 still ab. Wie lange die
  sprechtag-Sitzung selbst lebt, ist nicht nachgesehen.

## Offene Punkte

- **Stelle 1:** Ob Eltern über ihre Sitzung an sich selbst senden dürfen –
  ablesbar am Status der bisherigen Bestätigungen in der Mitteilungsansicht.
- **Kein Rückfall bei gescheitertem Versand mit Sitzung:** Scheitert der
  Versand mit einer vorhandenen Sitzung (etwa ohne Senderecht), steht die
  Mitteilung auf „fehler“, ohne dass das Dienstkonto versucht wird. Im Auftrag
  als Abweichung von Stelle 5 vermutet – am Code ist es **dieselbe Logik an
  allen Stellen 2–5** (`mit_einreihen_und_senden()`), keine Ausnahme. Ob
  beabsichtigt, ist offen.
- Volljährige Schüler und Verwaltung: Stundenplan per eigener Sitzung (Stelle
  8) – nicht gemessen.

## Nachtrag 09.10.2026: Messung vorbereitet – ersetzt eine Lehrkraft-Sitzung das Dienstkonto überall? (v0.9.71)

**Auskunft des Betreibers (aus der Oberfläche, nicht gemessen):** Ein
**Lehrerkonto** hat dieselben Gruppen- und Rollenauswahlen wie das
Admin-Konto, dazu die vorgefertigten Gruppen Schüler (Einzel- und
Mehrfachauswahl), Eltern, Kollegen und Individuell. Damit verschiebt sich die
Frage von „trägt die Verwaltungs-Sitzung die Erinnerungen?“ zu „ersetzt eine
Lehrkraft-Sitzung das Dienstkonto überall?“.

**Messweg:** `POST /api/messung/liste` über die Sitzung der angemeldeten Person
(Lehrkraft oder Verwaltung – der Bericht nennt die Rolle), mit denselben
Aufrufen wie die Erinnerungen:
1. `schritt: "aufloesen"` – Liste über `CUSTOM/filter` auflösen (nur lesen);
2. `schritt: "senden"` – an die aufgelösten Empfänger über `/v2/messages/users`
   mit `recipientUserIds`; Erfolg heißt `numberOfRecipients` ≥ 1.

Sicherungen: nur `QUICK`-Listen, senden nur mit 1 bis 5 Empfängern (eine
falsche Listen-Kennung trifft so nie „alle Eltern“), nur mit
`"bestaetigt": true`, genau ein Versand, fester Testbetreff, keine Kennungen
und Namen im Bericht. Zu messen je mit Lehrkraft- und Verwaltungs-Sitzung an
der Testliste (QUICK, zwei Personen).

**Fachliche Frage, nicht technisch – offen:** Die Erinnerungen löst heute die
**Verwaltung** aus. Trägt nur die Lehrkraft-Sitzung, müsste entweder eine
Lehrkraft auslösen, oder das Admin-Konto reichte dafür nicht. Das ist zu
entscheiden, nicht zu lösen.
