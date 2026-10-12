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
| 2 | Lehrkraft | Lehrkraft; mit einem Kürzel aus `admin_kuerzel` zugleich Verwaltung |
| 5 | Schülerin oder Schüler | nur, wenn sie zu einer Gruppe für Volljährige gehören (Abschnitt 2) |
| 12 | Erziehungsberechtigte | Eltern |
| 16 | WebUntis-Admin | Verwaltung, mit dem ersten Kürzel aus `admin_kuerzel` |

**Die Verwaltung arbeitet über ein Lehrerkonto, nicht über das Admin-Konto**
(E23, gemessen). Dem WebUntis-Admin-Konto fehlt die Empfängerart PARENTS
(403): Absagen, Ausfälle, Einladungen und Bestätigungen an Eltern gehen mit
ihm nicht hinaus. Das Kürzel des Lehrerkontos gehört deshalb in
`admin_kuerzel`.

**Das Admin-Konto braucht man einmal:** Die Liste der Benutzergruppen
(`userrole/config`) bekommt nur das Admin-Konto – 200 am 09.10.2026, 403 für
das Lehrerkonto am 11.10.2026 (beides gemessen an der Referenzschule). Sie
wird für die Auswahl der Gruppe der Volljährigen gebraucht. Danach reicht
das Lehrerkonto.

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
Die Anwendung setzt die Schuljahres-Kopfzeile nicht (am Code gelesen); was
in diesem Zeitraum geschieht, ist nicht gemessen.

**Feldnamen und Antwortformen** sind an der Referenzschule beobachtet, nicht
zugesagt. Wo `sprechtag` mehrere Formen probiert – etwa beim Versand an
Konten (sieben Varianten) –, liegt das daran.

## 4 — Was schulspezifisch einzustellen ist

In `config.php` (Vorlage `backend/config.example.php`): Adresse und Kennung
der Schule in WebUntis, zugelassene Rollen, Kürzel der Verwaltung, die
Grenzen der Anmeldebremse, der Schalter des Sondierwerkzeugs.

In der Oberfläche der Verwaltung: die Gruppen der Volljährigen, die
Empfängerliste für Erinnerungen, die Texte, Phasen und Zeitfenster jedes
Sprechtags.
