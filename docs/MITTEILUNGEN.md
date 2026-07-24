# WebUntis-Mitteilungen

## Der Versandweg (belegt am 24.07.2026)

Der Weg wurde durch Mitschnitt der WebUntis-Weboberfläche ermittelt
(Entwicklerwerkzeuge → Netzwerk → Nachricht senden):

```
POST /WebUntis/api/rest/view/v2/messages/users
Content-Type: multipart/form-data; boundary=----…

------…
Content-Disposition: form-data; name="request"; filename="blob"
Content-Type: application/json

{"subject":"…","content":"…","requestConfirmation":false,
 "recipientUserIds":[5984],"oneDriveAttachments":[],"forbidReply":false}
------…--
```

Drei Punkte, die vorher falsch geraten wurden:

* Der Pfad lautet **`/v2/messages/users`**, nicht `/v1/messages`.
* Der JSON-Block ist **kein Request-Body**, sondern ein **Multipart-Teil**
  mit `name="request"` und `filename="blob"`. Reine JSON-POSTs scheitern.
* Empfänger werden über **`recipientUserIds`** adressiert – mit der
  `user.id` aus `app/data`, nicht der `personId` aus `authenticate`.

## Rechte: der wahrscheinlichste Stolperstein

Das JWT aus `/WebUntis/api/token/new` trägt einen Scope für Mitteilungen:

| Scope | Bedeutung |
|---|---|
| `mg:r` | nur **lesen** |
| `mg:rw` | lesen und **senden** |

Im Mitschnitt vom 24.07.2026 trug das Admin-Token nur `mg:r`. Falls der
Versand mit 403 scheitert, ist das die Ursache – das Konto braucht in
WebUntis das Recht, Mitteilungen zu senden. Das System liest den Scope aus
und weist in der Fehlermeldung darauf hin.

## Wie das System vorgeht

Der belegte Weg wird zuerst probiert. Schlägt er fehl, folgen sechs
Rückfall-Varianten (andere Pfade, JSON statt Multipart, abweichende
Feldnamen) – falls eine andere Instanz oder WebUntis-Version abweicht.
Die erfolgreiche Variante wird in `einstellungen` gemerkt und künftig
zuerst genutzt.

Die Antwort auf jede Variante steht danach unter „Mitteilungen" im
Bereich **Diagnose des letzten Versands**.

Bewertung der Antworten:

* **2xx** → Erfolg, Variante wird gemerkt.
* **401/403** → Abbruch. Rechteproblem, weitere Varianten wären sinnlos.
* **400/404/405/500** → nächste Variante probieren.

**Schlägt alles fehl, geht nichts verloren:** Die Mitteilung bleibt mit
Status `offen` und der Fehlermeldung stehen. Buchungen und Absagen sind
davon unabhängig und immer korrekt.

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
