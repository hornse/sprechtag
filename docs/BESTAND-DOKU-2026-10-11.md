# Bestandsaufnahme 11.10.2026 — README und Fachdokumente gegen den Stand v0.9.92

Erhoben am 11.10.2026 am Stand v0.9.92 (`7d3cbcb`). **Nur aufgenommen,
nichts geändert** – der Umbau soll sich dagegen prüfen lassen wie die Hilfe
gegen `HILFE-ABGLEICH-2026-10-09.md`. Je Stelle: was dort steht, was heute
gilt.

Die Fakten zum Code sind am Code erhoben, mit Zeilenbeleg (die beiden
gewichtigsten – Sondierung und Mitteilungswege – zusätzlich von Hand
nachgelesen). Befund- und Ideendateien, `ENTSCHEIDUNGEN.md`, `AUFRAEUMEN.md`,
`HILFE-ABGLEICH-2026-10-09.md`, `BESTAND-DIENSTKONTO-2026-10-09.md` und
`docs/beilagen/` sind Protokoll und bleiben, wie sie sind.

Keine Personennamen, keine Kennungen.

---

## Vorweg: Sicherheitsfund, behoben in v0.9.93

Bei der Durchsicht von `SONDIERUNG.md` fiel auf, dass die dort behauptete
Brute-Force-Bremse nicht existiert. `POST /api/sondierung` prüfte nur den
Schalter, keine Anmeldung, keine Rolle; der Schalter stand im Betrieb auf
`true` (gemessen). Behoben in v0.9.93 (Schalter **und** Verwaltung, Vorlage
`false`, Bremse an allen drei Passwortstellen); gemeldet an `koordination`
(`docs/BEFUND-2026-10-11-sprechtag-sondierung-offen.md`).

**Für den Doku-Umbau offen:** `SONDIERUNG.md` sagt „Lediglich der Aufruf
selbst wird in `login_log` protokolliert“. Seit v0.9.93 stimmt das nur
halb: Fehlschläge immer, Erfolge nur nach den Einstellungen der Schule – wie
bei der Anmeldung und wie es der Datenschutz-Absatz der Hilfe zusagt. **Der
Satz wird berichtigt, nicht der Code an ihn angepasst** (Betreiber).

---

## README.md — überarbeitungswürdig; Umbau entschieden

Entscheidung des Betreibers: weg von der Betriebsanleitung für die eine
Installation, hin zu etwas, mit dem auch andere Schulen arbeiten können. Die
eigene Anleitung wird nach `docs/BETRIEB.md` verschoben, nicht gelöscht.
Darin müssen stehen: Die WebUntis-Anbindung nutzt teils undokumentierte
Endpunkte und kann sich mit Updates ändern; vieles ist schulspezifisch zu
konfigurieren.

**Überholt:**

| Stelle | steht dort | gilt heute |
|---|---|---|
| Kopf | „Stand: v0.9.46“ | 0.9.92; eine ungepflegte Angabe |
| Kopf | Name der Referenzschule | für andere Schulen allgemein (Frage 1) |
| Lehrkräfte | „Einladungen in Phase 1“ | in jeder Phase, über die Kind-Suche |
| Lehrkräfte | „Export der eigenen Termine“ | Tischvorlage (Druck), Tagesliste `.ics`, Kalender-Abo; kein CSV |
| Administration | „Dienstkonto-Verwaltung (Zugangsdaten verschlüsselt)“ | abgeschafft (E17) |
| Querschnitt | „für Eltern wird nur die `user.id` gespeichert, Klarnamen nur zur Laufzeit“ | Name und Klasse des Kindes stehen am Vorgang (E20) |
| Hinweis WebUntis | „probiert mehrere Feldstrukturen“ | gilt für den Kontoweg (sieben Varianten); an Eltern geht es über PARENTS (E16) |
| Erstinstallation, Schritt 3 | `dienstkonto_schluessel` erzeugen | gibt es nicht mehr |
| Erstinstallation, Schritt 2 | „Migrationen idempotent“, Schleife über `sql/[0-9]*.sql` | die Schleife sortiert `26_ein_termin_je_kind` vor `26_pruefung`; dazu Migrationen mit festem Zeitpunkt (24 vor, 25 nach dem Deploy). Auf leerer Datenbank vermutlich folgenlos – nicht ausprobiert |
| Dateistruktur | „Siehe `NEUES_PROJEKT_PROMPT.md`“ | die Datei gibt es nicht (auch der Kopf von `backend/router.php` verweist darauf) |

**Fehlt:** volljährige Schülerinnen und Schüler nach Gruppe (E15),
Kind-Suche, Kachel-Gliederung (E10), ein Termin je Kind (E24), abgesagte
Termine (E19), Krankheitsausfall, Halbtags und Zeitfenster, Login-Protokoll
mit Frist, Stammdaten-Sync, Sprechtag kopieren, Kartenansicht am Telefon –
**und die WebUntis-Voraussetzungen vollständig** (unten).

**Nach `BETRIEB.md`:** Eckdaten-Tabelle, Erstinstallation mit Pfaden des
Servers, Deploy, Debug. **Bleibt allgemein:** „Entwicklung“ (Testaufruf;
`php -l` läuft nirgends, E4).

## docs/BEDIENUNG.md (23.07.) — überarbeitungswürdig, Zuschnitt offen (Frage 3)

| Stelle | steht dort | gilt heute |
|---|---|---|
| Rollen | Administration über personType 16 | dazu: Verwaltung über ein Lehrerkonto mit `admin_kuerzel` (E23); das Admin-Konto sendet nicht an Eltern; `admin_kuerzel` ist ein Array (E22) |
| Phase 1 | Einladung „über Schüler-ID“, stellvertretend „mit Benutzer-ID der Eltern“ | beides über die Kind-Suche (Name oder Klasse) |
| Räume | „doppelt belegt … rot markiert“ | eine Farbe je mehrfach belegtem Raum |
| Eltern | Knopf „Lehrkräfte anzeigen“ | gibt es nicht; Kacheln nach E10 |
| Archivieren | „löscht Buchungen, Einladungen, Zuordnung“ | unvollständig (wie H9; maßgeblich ist der Datenschutz-Absatz der Hilfe) |
| Datenschutz | „nur die WebUntis-Schüler-ID“; „alle personenbezogenen Daten gelöscht“ | Name und Klasse am Vorgang; Login-Protokoll und Kalender-Abo bleiben |
| Fallstricke | der Cache „braucht WebUntis-Zugangsdaten“ | über die Sitzung der angemeldeten Person, automatisch beim Laden der Kacheln |

**Noch richtig:** Sortierung nach Wochenstunden (`buchungen.php`),
Maximalzahl je Elternteil (`max_termine_pro_eltern`, Standard 6),
Sonderrollen mit Jahrgängen, Kopieren, Referenzzeitraum (vier Wochen,
endend sieben Tage vor dem Sprechtag).

**Fehlt:** Mitteilungen, Erinnerungen, Kalender-Abo, Anzeige,
Login-Protokoll, Texte, Halbtags/Zeitfenster, Klausuren, Tischvorlage,
Karten, E15, E19, E23, E24. Den Teil für Eltern und Lehrkräfte deckt seit
v0.9.90 die Hilfe ab.

## docs/MITTEILUNGEN.md (24.07.) — überarbeitungswürdig

| Abschnitt | gilt heute |
|---|---|
| „Empfänger ermitteln: Kind → Elternkonto“ | an Eltern über `recipientOption: PARENTS` mit der Kind-Kennung, ohne Namen (E16). Der Namensabgleich über `tags` dient nur noch der Zuordnung des Elternkontos beim stellvertretenden Buchen |
| „Wann Mitteilungen entstehen“ | `bestaetigung` nur noch beim stellvertretenden Buchen – nach der eigenen Buchung der Eltern keine (Elternkonten senden nicht, 403); `einladung` fehlt; `absage` auch beim Ausfall |
| „Erster Versand“ | beschreibt den Knopf „Alle offenen versenden“, den es nicht mehr gibt |

**Noch richtig:** „Der Versandweg“ (`/v2/messages/users` gilt für Konten und
Erinnerungen; für Eltern fehlt PARENTS), „Wie das System vorgeht“ (sieben
Varianten, nur auf dem Kontoweg). „Rechte“ und „Versand auslösen“ tragen
bereits Vermerke.

## Gekennzeichnet, erledigt

`DIENSTKONTO.md`, `SCHUELERLISTE.md` – Überholt-Vermerk vorhanden, bleiben
als Geschichte.

## Vermerk statt Überarbeitung

- **`SONDIERUNG.md`** (Arbeitsdatei, Juli): die Bremse-Behauptung (siehe
  oben); „Kind → Lehrkräfte: offen“ ist gelöst (`timetable/entries`); die
  Abschaltung nach der Sondierung ist bis 11.10.2026 nie erfolgt.
- **`VERSANDWEG_ERMITTELN.md`**: bleibt als Rückfall brauchbar (die
  Varianten gibt es noch); Vermerk „Weg belegt seit 24.07.; für Eltern gilt
  PARENTS (E16)“.

## Erledigt, nichts zu tun

`signage-wiederverwenden.md`: `signageStart` und `starteAnzeige` gibt es.

## Nebenbei im Code (Kommentare, keine Doku-Dateien)

- `backend/api/mitteilungen.php`, Kommentar über `mit_absage_art()`: „Die
  Bestätigung nach einer Elternbuchung bleibt beim buchenden Konto“ – seit
  v0.9.79 gibt es diese Bestätigung nicht mehr.
- `backend/api/buchungen.php`, Kopf von `POST /api/lehrer-ermitteln`: nennt
  `[benutzername, passwort]`; der Code liest beides nicht mehr (Rest aus der
  Dienstkonto-Zeit).
- `backend/router.php`, Kopf: Verweis auf `NEUES_PROJEKT_PROMPT.md`.

Wohin diese gehören (Doku-Zug oder Aufräumliste), ist Frage 6.

---

## WebUntis-Voraussetzungen — was vorliegt

Fehlt heute in jeder Datei. Für eine eigene Datei (Vorschlag
`docs/WEBUNTIS.md`).

**Konten und Rechte (gemessen):**
- Die Verwaltung arbeitet über ein **Lehrerkonto mit `admin_kuerzel`**
  (E23). Dem WebUntis-Admin-Konto (personType 16) fehlt die Empfängerart
  PARENTS (403): Absagen, Ausfälle, Einladungen und Bestätigungen gehen mit
  ihm nicht hinaus.
- Die **Gruppenliste** der Volljährigen (`userrole/config`) bekommt nur das
  Admin-Konto: 200 (09.10.2026) gegen 403 für das Lehrerkonto
  (11.10.2026, Betrieb). Es wird **einmalig** für die Auswahl der Gruppen
  gebraucht; danach reicht das Lehrerkonto (das Vorbefüllen des Textfelds
  ist nur am Code gelesen).
- **Elternkonten senden nicht** (403, v0.9.79): keine Bestätigung nach der
  eigenen Buchung.
- **Lehrkraft-Sitzungen senden** trotz Scope `mg:r`; maßgeblich ist
  `messages/permissions` (E1).
- Die WebUntis-**Sitzung läuft ab** (rund 25–30 Minuten, E9); was dann liegen
  bleibt, wird nach erneuter Anmeldung verschickt.
- Zugelassen sind personType 2 (Lehrkraft, mit Kürzel auch Verwaltung),
  5 (Schüler), 12 (Erziehungsberechtigte), 16 (Admin) – einstellbar in
  `allowed_person_types`.
- **Nicht gemessen:** welche Rechte der Stammdaten-Sync braucht (er nimmt im
  Formular eingegebene Zugangsdaten und ruft `getTeachers` und `getRooms`).

**Was in WebUntis gepflegt sein muss:**
- **Die Erziehungsberechtigten müssen in WebUntis mit dem Kind verknüpft
  sein**; darüber kennt die Anmeldung die Kinder (`app/data`,
  `user.students`) und löst `recipientOption: PARENTS` die Eltern auf.
  *Wie diese Verknüpfung an der Referenzschule entsteht – Auskunft des
  Betreibers, nicht gemessen:* Beim Einpflegen neuer Eltern in WebUntis
  erfolgt die Identifikation über die Schild-ID des **Kindes**; zwei
  Elternteile ergeben zwei CSV-Zeilen mit derselben Kind-ID. `sprechtag`
  selbst nutzt keine Schild-ID.
- Eine **Benutzergruppe für volljährige Schülerinnen und Schüler**; WebUntis
  kürzt Gruppennamen auf 20 Zeichen (E15).
- Eine **Klasse am Kind** (`pageconfig` → `klasseId`): Kinder ohne Klasse
  erscheinen weder in der Einladungsauswahl noch in der Kind-Suche (E20).
- Der **Stundenplan im Referenzzeitraum**: Aus ihm werden die Lehrkräfte
  eines Kindes ermittelt (`timetable/entries`); in den Ferien findet sich
  nichts.
- Eine **Empfängerliste** (DYNAMIC oder QUICK, `referenceId`) für die
  Erinnerungen.
- **Kürzel der Lehrkräfte und Räume** für den Stammdaten-Sync.

**Was sich ändern kann:**
- Alle REST-Pfade sind undokumentiert (Kopf von `WebUntisRest.php`): Token,
  `app/data`, `profile/general`, `userrole/config`, `timetable/entries`,
  `timetable/filter`, `v2/messages/users`, `v2/messages`, `v1/messages`,
  Empfängersuche und `CUSTOM/filter`.
- Offiziell ist nur JSON-RPC (`authenticate`, `getTeachers`, `getRooms`).
- `pageconfig?type=5` ist ein öffentlicher Pfad der Oberfläche, ohne Zusage.
- Zwischen zwei Schuljahren ist keines aktiv (FALLSTRICKE 6).

---

## Zuschnitt (Vorschlag)

1. **WebUntis-Voraussetzungen** als eigene Datei – das Einzige, was ganz
   fehlt; README und BETRIEB.md verweisen darauf.
2. **README in zwei Schritten** wie bei der Hilfe: erst den Betriebsteil
   wörtlich nach `BETRIEB.md` verschieben (mechanisch geprüft, jeder Satz
   genau einmal), dann die neue README schreiben.
3. **MITTEILUNGEN.md und BEDIENUNG.md**, zuletzt die Vermerke.

## Offene Fragen (Betreiber, Stand 11.10.2026)

1. Schulname in der README: allgemein, mit der Referenzschule als Einsatz –
   oder gar nicht?
2. `BETRIEB.md` mit den echten Pfaden und Hostnamen oder mit Platzhaltern?
3. `BEDIENUNG.md`: zum Ablauf eines Sprechtags für die Verwaltung umbauen,
   oder kennzeichnen und den Ablauf anderswo führen?
4. *Beantwortet am 11.10.2026* (siehe oben, Voraussetzungen).
5. *Beantwortet und umgesetzt am 11.10.2026* (v0.9.93).
6. Die drei veralteten Code-Kommentare: Doku-Zug oder Aufräumliste?
7. *Beantwortet*: diese Datei.

---

## Nachtrag 12.10.2026 — Fragen 1, 2, 3, 6 beantwortet (Betreiber)

1. **Schulname:** allgemein halten, die Referenzschule als Einsatz nennen –
   „entwickelt für und im Einsatz an …“, im Kopf und allenfalls am Ende,
   nicht durch den Text hindurch. Es sagt zweierlei: dass es an einer
   echten Schule läuft, und dass es nicht generisch gebaut wurde.
2. **`BETRIEB.md` mit Platzhaltern.** Das Repo ist öffentlich; Hostname und
   Benutzername nützen niemandem außer dem Betreiber und erleichtern
   Dritten die Zuordnung. Dass sie heute in der README stehen, ist der
   Grund, sie beim Umbau loszuwerden.
3. **`BEDIENUNG.md` umbauen** zum Ablauf eines Sprechtags für die
   Verwaltung (anlegen, Phasen schalten, Räume zuordnen, Erinnerungen,
   archivieren) – in welcher Reihenfolge man vorgeht und worauf man achtet.
   Was ein Knopf tut, bleibt der Hilfe.
6. **Aufräumliste**, nicht Doku-Zug: die Kommentare in `mitteilungen.php`,
   `router.php` und `buchungen.php` (`docs/AUFRAEUMEN.md`, Punkt 6). Den
   Verweis auf `NEUES_PROJEKT_PROMPT.md` in der README nimmt der Umbau mit.

Zuschnitt wie vorgeschlagen bestätigt. Schritt 1 erledigt:
`docs/WEBUNTIS.md`.
