# WebUntis-Mitteilungen

## Was gesichert ist – und was nicht

Die Sondierung vom 19.07.2026 hat gezeigt:

| Endpunkt | Befund |
|---|---|
| `GET /api/rest/view/v1/messages` | 200 für Eltern **und** Lehrkräfte |
| `GET /api/rest/view/v1/messages/status` | 200, liefert Anzahl ungelesener |
| `GET /api/rest/view/v1/messages/recipients` | 400: `parameter 'recipients' is no valid value for the expected type: 'class java.lang.Long'` |

Der letzte Punkt ist der wichtigste Hinweis: Der Endpunkt existiert und
erwartet eine **User-ID** (`user.id` aus `app/data`, z. B. 5984) – **nicht**
die `personId` aus `authenticate`. Beide Werte liegen dem System vor.

**Nicht gesichert ist der Versandweg selbst.** Die Schnittstelle ist
undokumentiert, und ein Versandversuch hätte eine echte Nachricht an eine
echte Person erzeugt – das war ohne Testempfänger nicht verantwortbar.

## Wie das System damit umgeht

Statt einen geratenen Weg als sicher auszugeben, probiert der Versand
mehrere plausible Feldstrukturen der Reihe nach durch:

1. `recipients: [5984]` (IDs als Zahlen)
2. `recipients: [{userId: 5984}]` (Objekte)
3. `recipientOption: "SPECIFIC"` + `recipientUserIds: [...]`
4. `/v2/messages` mit `recipients: [{id, type: "USER"}]`

Die Antwort entscheidet, wie es weitergeht:

* **2xx** → Erfolg. Die funktionierende Variante wird in der Tabelle
  `einstellungen` gemerkt und künftig zuerst probiert (dann nur noch ein
  Aufruf je Mitteilung).
* **401/403** → Abbruch. Das ist ein Rechteproblem, kein Strukturproblem;
  weitere Varianten wären sinnlos. Meldung verweist auf das WebUntis-Recht
  „Mitteilungen senden".
* **400/404/405/500** → nächste Variante probieren.

**Schlägt alles fehl, geht nichts verloren:** Die Mitteilung bleibt mit
Status `offen` und der letzten Fehlermeldung in der Warteschlange stehen.
Die Administration sieht sie unter „Mitteilungen" und kann den Text
manuell in WebUntis versenden. Buchungen und Absagen sind davon unabhängig
– ein abgesagter Termin ist auch dann korrekt storniert, wenn die
Benachrichtigung nicht durchging.

## Wann Mitteilungen entstehen

| Anlass | Auslöser | Inhalt |
|---|---|---|
| `bestaetigung` | Eltern buchen einen Termin | **alle** aktuellen Termine dieses Kontos mit Zeit, Lehrkraft und Raum |
| `absage` | Lehrkraft/Administration sagt einen Termin ab | Zeit, Lehrkraft, optionaler Freitext, Hinweis auf Neubuchung |
| `hinweis` | manuell über die API | frei |

Bei mehrfachen Buchungen wird die **offene** Bestätigung ersetzt, nicht
ergänzt – es gilt immer der aktuelle Gesamtstand. Sagen Eltern selbst ab,
entsteht keine Mitteilung (sie wissen es ja).

## Versand auslösen

Administration → „Mitteilungen" → Sprechtag wählen → Zugangsdaten eingeben
→ „Alle offenen versenden". Die Zugangsdaten werden nur für diesen Vorgang
verwendet und nicht gespeichert; es wird **eine** WebUntis-Sitzung für alle
Mitteilungen geöffnet (maximal 200 je Durchgang).

Wer versendet, sollte in WebUntis das Recht zum Versenden von Mitteilungen
an Erziehungsberechtigte besitzen. Ein Dienstkonto ist dafür geeignet.

## Erster Versand – was zu erwarten ist

Der erste Durchgang ist zugleich der Beweis, ob der Versandweg funktioniert.
Sinnvoll ist ein Test mit **einer** Mitteilung an ein Testkonto:

1. Mit dem Testeltern-Konto einen Termin buchen (erzeugt eine Bestätigung).
2. Als Administration „Alle offenen versenden".
3. Ergebnis prüfen: Bei Erfolg nennt die Meldung die Variante (z. B.
   `v1_recipients_ids`). Im Testeltern-Konto sollte die Nachricht im
   WebUntis-Nachrichtenzentrum liegen.

Bleibt es bei „Kein Versand möglich", steht die genaue Fehlermeldung je
Mitteilung in der Tabelle. Diese Meldung ist die entscheidende Information
für den nächsten Schritt – am besten zusammen mit dem WebUntis-Netzwerk-Tab
(Nachricht in der Weboberfläche versenden, POST-Body ansehen) auswerten.
Die dort sichtbare Struktur lässt sich in `mit_varianten()` als weitere
Variante ergänzen.

## Datenschutz

Gespeichert werden Empfänger-**User-ID**, Betreff und Text – keine Namen
von Eltern, keine E-Mail-Adressen. Der Text enthält Lehrkraftnamen und
Uhrzeiten. Beim Archivieren eines Sprechtags werden alle Mitteilungen
gelöscht.
