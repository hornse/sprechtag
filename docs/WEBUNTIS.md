# WebUntis: was die Schule mitbringen muss

`sprechtag` hat keine eigene Benutzerverwaltung. Anmeldung, Kinder,
Stundenplan und Mitteilungen kommen aus WebUntis. Diese Datei sagt, was dort
eingerichtet und gepflegt sein muss, damit es funktioniert, und was sich
ohne Zutun der Schule ändern kann.

Entwickelt und im Einsatz ist es an **einer** Schule (im Folgenden
„Referenzschule“). Was hier als **gemessen** steht, ist dort gemessen, mit
Datum. Was **am Code gelesen** ist, beschreibt, was `sprechtag` tut, nicht
was WebUntis an einer anderen Schule antwortet. Was **nicht gemessen** ist,
steht so da.

Stand: 12.10.2026, Version 0.9.93. Die Messungen tragen keine
WebUntis-Version – was vor Wochen gemessen wurde, kann ein Update überholt
haben.

---

## 1 — Konten und Rechte

**Wer sich anmelden darf**, steht in `config.php` unter
`webuntis.allowed_person_types`. Voreingestellt sind:

| personType | Rolle in WebUntis | in `sprechtag` |
|---|---|---|
| 2 | Lehrkraft | Lehrkraft; zugleich Verwaltung, wenn unter „Administratoren“ eingetragen oder als Notzugang in `admin_kuerzel` (E29) |
| 5 | Schülerin oder Schüler | nur, wenn sie zu einer Gruppe für Volljährige gehören (Abschnitt 2) |
| 12 | Erziehungsberechtigte | Eltern |
| 16 | WebUntis-Admin | Verwaltung, immer (E29); trägt das erste Kürzel aus `admin_kuerzel` |

**Die Verwaltung arbeitet über ein Lehrerkonto, nicht über das Admin-Konto**
(E23, gemessen). Dem WebUntis-Admin-Konto fehlt die Empfängerart PARENTS
(403): Absagen, Ausfälle, Einladungen und Bestätigungen an Eltern gehen mit
ihm nicht hinaus. Das Lehrerkonto muss deshalb Administrator sein: über
die Seite „Administratoren“ oder als Notzugang in `admin_kuerzel` (E29).

**Wer Administrator ist (E29, am Code gelesen):** drei Wege, eine
Entscheidung (`adm_herkunft()` in `backend/api/admins.php`), geprüft bei
der Anmeldung und bei jedem Aufruf einer Admin-Sitzung.
- **Die Seite „Administratoren“** – der Normalweg; Einträge in
  `app_admins`. Nur Lehrkräfte mit Stammsatz.
- **Der Notzugang** – `admin_kuerzel` in `config.php`: ein oder zwei
  Kürzel, die immer gelten, damit sich niemand aussperren kann. Er braucht
  einen Serverzugang, den ein kompromittiertes WebUntis-Konto nicht hat.
- **Das WebUntis-Administrationskonto** (personType 16) – immer, ohne
  Bedingung: Notzugang für den Notzugang und gebraucht für die
  Gruppenliste (unten).

**Das Admin-Konto braucht man für die Gruppenliste:** Die Liste der
Benutzergruppen (`userrole/config`) bekommt nur das Admin-Konto – 200 am
09.10.2026, 403 für das Lehrerkonto am 11.10.2026 (beides gemessen an der
Referenzschule). Sie wird für die Auswahl der Gruppe der Volljährigen
gebraucht – beim Einrichten und wieder bei jeder neuen oder umbenannten
Gruppe. Sonst reicht das Lehrerkonto.

**Wer was senden darf (gemessen):**
- **Lehrkräfte senden**, obwohl ihr Token nur den Bereich `mg:r` trägt.
  Maßgeblich ist, was `messages/permissions` meldet (E1).
- **Elternkonten senden nicht** (403, v0.9.79). Deshalb gibt es nach der
  eigenen Buchung der Eltern keine Bestätigung über WebUntis.

**Die WebUntis-Sitzung läuft ab**, an der Referenzschule nach rund 25 bis 30
Minuten (E9). Mitteilungen, die dann nicht hinausgehen, bleiben liegen und
werden nach der nächsten Anmeldung verschickt.

**Nicht gemessen:** welche Rechte der Stammdaten-Sync braucht. Er nimmt im
Formular eingegebene Zugangsdaten und ruft `getTeachers` und `getRooms`.

## 2 — Was in WebUntis gepflegt sein muss

**Die Erziehungsberechtigten müssen in WebUntis mit dem Kind verknüpft
sein.** Darüber kennt die Anmeldung die Kinder eines Elternkontos
(`app/data`, `user.students`), und darüber löst `recipientOption: PARENTS`
die Eltern auf, wenn eine Mitteilung an „die Eltern dieses Kindes“ geht
(E16). Ohne Verknüpfung sieht ein Elternkonto keine Kinder und bekommt keine
Mitteilungen.

> *Wie die Verknüpfung an der Referenzschule entsteht – Auskunft des
> Betreibers, nicht gemessen:* Beim Einpflegen neuer Eltern in WebUntis
> werden sie über die Schild-ID des **Kindes** zugeordnet; zwei Elternteile
> ergeben zwei CSV-Zeilen mit derselben Kind-ID. `sprechtag` selbst nutzt
> keine Schild-ID. Andere Schulen können es anders lösen; entscheidend ist
> die Verknüpfung, nicht der Weg.

**Eine Klasse am Kind.** Kinder ohne Klasse erscheinen weder in der Auswahl
für Einladungen noch in der Kind-Suche (E20). Die Klassen kommen aus
`pageconfig`.

**Ein Stundenplan im Referenzzeitraum.** Aus ihm ermittelt `sprechtag`, bei
welchen Lehrkräften ein Kind Unterricht hat (`timetable/entries`). Der
Zeitraum ist voreingestellt vier Wochen, endend eine Woche vor dem
Sprechtag (am Code gelesen); er lässt sich je Sprechtag ändern. Liegt er in
den Ferien, findet sich nichts.

**Für volljährige Schülerinnen und Schüler: eine oder mehrere
Benutzergruppen** (E15). Wer in einer davon ist, darf selbst buchen. WebUntis kürzt Gruppennamen auf 20
Zeichen (gemessen 09.10.2026); `sprechtag` vergleicht deshalb mit dem
gekürzten Namen.

**Für die Erinnerungen: eine Empfängerliste** in WebUntis (Art DYNAMIC oder
QUICK, mit ihrer Kennung). `sprechtag` löst sie auf und schickt an die
Mitglieder.

**Für den Stammdaten-Sync: Kürzel der Lehrkräfte und Räume.**

## 3 — Was sich ändern kann

**Der größte Teil der Anbindung nutzt undokumentierte Schnittstellen.**
WebUntis kann sie mit jedem Update ändern, ohne Ankündigung.

- **Offiziell dokumentiert** ist nur JSON-RPC: `authenticate`,
  `getTeachers`, `getRooms`.
- **Undokumentiert** sind alle REST-Pfade, die `sprechtag` im Betrieb ruft:
  der Token (`token/new`), `app/data`, `profile/general`,
  `userrole/config`, `timetable/filter`, `timetable/entries`, die
  Empfängersuche, die Auflösung von Empfängerlisten (`CUSTOM/filter`),
  `v2/messages/users` und `v2/messages`.
- **`pageconfig?type=5`** ist ein öffentlicher Pfad der WebUntis-Oberfläche,
  ohne Zusage.
- **Das Sondierwerkzeug** (nur für die Verwaltung, voreingestellt
  abgeschaltet) ruft darüber hinaus weitere Pfade zur Diagnose; im Betrieb
  wird es nicht gebraucht.

**Zwischen zwei Schuljahren ist in WebUntis keines aktiv** (FALLSTRICKE 6).
Die Anwendung setzt die Schuljahres-Kopfzeile nicht (am Code gelesen). **Ob
ihre Aufrufe in dieser Zeit scheitern, ist nicht gemessen** – belegt ist
nur, dass sie nichts dagegen tut, nicht, dass es sie nicht betrifft.
Entscheiden würde es ein Lauf im Sommer, zwischen dem Ende des einen und
dem Beginn des nächsten Schuljahrs in WebUntis.

**Feldnamen und Antwortformen** sind an der Referenzschule beobachtet, nicht
zugesagt. Wo `sprechtag` mehrere Formen probiert – etwa beim Versand an
Konten (sieben Varianten) –, liegt das daran.

## 4 — Was schulspezifisch einzustellen ist

In `config.php` (Vorlage `backend/config.example.php`): Adresse und Kennung
der Schule in WebUntis, zugelassene Rollen, die Kürzel des Notzugangs, die
Grenzen der Anmeldebremse, der Schalter des Sondierwerkzeugs.

In der Oberfläche der Verwaltung: die Administratoren, die Gruppen der Volljährigen, die
Empfängerliste für Erinnerungen, die Texte, Phasen und Zeitfenster jedes
Sprechtags.
