# Ablauf eines Sprechtags – für die Verwaltung

In welcher Reihenfolge man vorgeht und worauf man achtet. Was ein Knopf
tut, steht in der Hilfe der Anwendung (Teil „Für die Administration“); was
WebUntis bereitstellen muss, in `docs/WEBUNTIS.md`. Stand v0.9.93,
12.10.2026, am Code gelesen; die Administratoren (E29) nachgezogen mit
v0.9.100. Die Fassung vom 23.07.2026, die auch Eltern
und Lehrkräfte beschrieb, steht in der Git-Geschichte; deren Teil deckt
heute die Hilfe ab.

## Einmal vorab

1. **Mit dem eigenen Lehrerkonto arbeiten**, das Administrator ist –
   nicht mit dem WebUntis-Admin-Konto. Mit diesem gehen keine Nachrichten
   an Eltern hinaus (E23). Administrator ist ein Lehrerkonto über die Seite
   „Administratoren“ oder als Notzugang (siehe Schritt 6).
2. **Volljährige Schüler:** Die WebUntis-Gruppen wählen, deren Mitglieder
   selbst buchen dürfen; es können mehrere sein. Die Liste der Gruppen
   bekommt nur das WebUntis-Admin-Konto – für diesen Schritt damit
   anmelden, danach wieder mit dem Lehrerkonto. **Wiederholen, wenn in
   WebUntis eine Gruppe dazukommt oder umbenannt wird** (E29).
3. **Stammdaten aus WebUntis übernehmen** (unter „Sprechtage“): mit
   WebUntis-Benutzername und Passwort; sie werden nur für diesen Abruf
   verwendet. Beim ersten Lauf prüfen, ob die Zahlen zum Kollegium passen.
   **Wiederholen, wenn Lehrkräfte neu dazukommen:** Wer im Stundenplan
   steht, aber keinen Stammsatz hat, ist für Eltern nicht buchbar, und die
   Seite nennt die fehlenden Kürzel. Eine Lehrkraft ohne Stammsatz hat auch
   keine eigene Ansicht.
4. **Erscheinungsbild, Texte, Anzeige, Login-Protokoll** einstellen. Beim
   Login-Protokoll entscheidet die Schule, ob auch erfolgreiche Anmeldungen
   festgehalten werden und wie lange (Fehlschläge werden immer
   festgehalten).
5. **Erinnerungen:** die WebUntis-Empfängerliste einstellen (Art und
   Kennung) und mit „Empfänger prüfen“ nachsehen, wie viele es sind.

6. **Administratoren** (unter „Administration“, E29): weitere Lehrkräfte
   eintragen, die die Verwaltung mitbenutzen. Eintragen wirkt ab ihrer
   nächsten Anmeldung, Entfernen sofort. Die Seite zeigt alle, die
   Administrator sind, mit Herkunft. Zwei Wege lassen sich dort nicht
   ändern: der **Notzugang** – ein oder zwei Kürzel in `admin_kuerzel` in
   `config.php`, die immer gelten, damit sich niemand aussperren kann;
   geändert wird er nur auf dem Server – und das
   **WebUntis-Administrationskonto**, das immer Administrator ist.

## Vor dem Sprechtag – Phase „in Vorbereitung“

1. **Sprechtag anlegen** oder einen früheren **kopieren**. Kopieren fragt
   nach dem neuen Datum und übernimmt die Lehrkräfte mit Anwesenheit, Raum
   und Bemerkung sowie die zusätzlich buchbaren Lehrkräfte – keine
   Buchungen. Der Referenzzeitraum wird zum neuen Datum neu berechnet.
2. **Einstellungen des Sprechtags prüfen:**
   - Slotlänge und **Max. Termine je Elternteil** (Vorgabe 6, gezählt je
     Elternteil, nicht je Kind);
   - **Pausen:** nach wie vielen Terminen und wie lang, wahlweise dynamisch;
   - **Referenzzeitraum:** Aus dem Stundenplan in diesem Zeitraum wird
     ermittelt, welche Lehrkräfte ein Kind unterrichten. Vorgabe sind vier
     Wochen, endend eine Woche vor dem Sprechtag. **Liegt er in den Ferien,
     findet sich nichts** – dann verschieben;
   - ob **Klausurtermine** bei dieser Ermittlung mitzählen.
3. **Lehrkräfte & Räume:** wer teilnimmt, in welchem Raum; ausnahmsweise
   ein Zeitfenster (⏱); Halbtagskräfte und Referendar:innen mit „½“, die
   dann die erste oder zweite Hälfte wählen. Ein Raum darf mehrfach belegt
   sein; jede Farbe markiert einen solchen Raum – nachsehen, ob es gewollt
   ist. „Alle speichern“ schreibt alle Zeilen auf einmal.
4. **Zusätzlich buchbare Lehrkräfte** (Schulleitung, Beratung und
   Ähnliches): unabhängig vom Stundenplan buchbar, wahlweise nur für
   bestimmte Jahrgänge.

**Darauf achten:** Lehrkräfte können schon jetzt einladen, und **die
Einladung geht sofort an die Eltern hinaus** – buchen können die Eltern
aber erst ab Phase 1. Wer das nicht will, sagt den Lehrkräften, wann sie
einladen sollen.

## Phase 1 – nur auf Einladung

Phase auf **Phase 1** stellen. Eltern sehen nur die Lehrkräfte, die sie
eingeladen haben, und buchen dort. Lehrkräfte laden weiter ein und buchen
stellvertretend. **Termine aus Phase 1 können Eltern nicht selbst absagen**
– nur die Lehrkraft.

## Phase 2 – offen für alle

Phase auf **Phase 2** stellen. Alle Eltern buchen bei allen teilnehmenden
Lehrkräften, begrenzt durch die Höchstzahl. Eigene Termine aus Phase 2
können Eltern wieder absagen.

**Erinnerungen** gehen nicht von selbst hinaus: Unter „Erinnerungen“ die
Zahl der Empfänger prüfen und den Versand zu einem selbst gewählten
Zeitpunkt auslösen.

## Während des Sprechtags

- **Fällt eine Lehrkraft aus,** gibt die Verwaltung ihre Termine über das
  ⊘-Symbol frei. Die Eltern werden benachrichtigt; die Freigabe gilt auch
  dann, wenn die Nachricht nicht gleich hinausgeht.
- **Gespeicherte Mitteilungen:** Ist die WebUntis-Sitzung abgelaufen
  (rund 25 bis 30 Minuten), bleiben Absagen und Einladungen gespeichert.
  Nach der nächsten Anmeldung meldet die Seite, wie viele es sind; „Jetzt
  senden“ holt es nach (`docs/MITTEILUNGEN.md`).

## Nach dem Sprechtag

1. **Phase „geschlossen“:** Es kann niemand mehr buchen, alles bleibt
   einsehbar.
2. **Vor dem Archivieren nachsehen, ob noch Mitteilungen gespeichert
   sind** – das Archivieren löscht sie mit.
3. **Archivieren** löscht zu diesem Sprechtag: Buchungen, Einladungen,
   die für die Kinder ermittelten Lehrkräfte, die Mitteilungen und die
   abgesagten Termine. Es bleiben Lehrkräfte, Räume, Anwesenheit und die
   zusätzlich buchbaren Lehrkräfte, damit sich der Sprechtag kopieren
   lässt. **Nicht** betroffen sind das Login-Protokoll (eigene Frist) und
   die Kennnummern der Kalender-Links (derzeit ohne Frist); maßgeblich ist
   der Abschnitt „Datenschutz“ der Hilfe.
