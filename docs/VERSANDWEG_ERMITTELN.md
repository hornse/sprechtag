# Versandweg für WebUntis-Mitteilungen ermitteln

Der POST-Weg der internen WebUntis-API ist nicht dokumentiert. Das System
probiert sieben plausible Feldstrukturen durch; akzeptiert die Instanz
keine davon, lässt sich der richtige Weg in fünf Minuten direkt ablesen.

## Vorgehen

1. In WebUntis anmelden (mit dem Konto, das später versenden soll).
2. Entwicklerwerkzeuge öffnen: **F12** (Chrome/Edge) oder **Cmd+Alt+I** (Safari).
3. Reiter **Netzwerk** wählen, Filter auf **Fetch/XHR** stellen.
4. Im Nachrichtenzentrum eine Testnachricht an ein Testkonto schreiben
   und absenden.
5. In der Liste erscheint eine Anfrage mit Methode **POST** – meist auf
   einen Pfad, der `messages` enthält. Diese anklicken.

Interessant sind zwei Dinge:

**Der Pfad** (oben unter „Allgemein" bzw. „Headers"):

```
POST https://frg-dusseldorf.webuntis.com/WebUntis/api/rest/view/v1/....
```

**Die Nutzlast** (Reiter „Payload" bzw. „Anfrage" → „Quelle anzeigen"):

```json
{
  "subject": "Test",
  "content": "Hallo",
  "recipients": [ ... ]
}
```

Genau dieser JSON-Block ist die gesuchte Struktur. Wichtig ist vor allem,
wie die **Empfänger** dargestellt werden – als Zahlen, als Objekte, unter
welchem Feldnamen.

## Was damit passiert

Pfad und Nutzlast in den Projekt-Chat einfügen (Empfängernamen dürfen
geschwärzt werden, nur die Struktur zählt). Daraus wird eine passende
Variante in `mit_varianten()` ergänzt – dann funktioniert der Versand
beim nächsten Versuch.

## Alternative: Diagnose im Tool

Nach einem fehlgeschlagenen Versand steht unter „Mitteilungen" ein Bereich
**„Diagnose des letzten Versands"**. Dort ist je probierter Variante die
Antwort der Instanz aufgeführt. Auch diese Angaben helfen weiter – etwa
wenn eine Variante nicht mit `404`, sondern mit einer Meldung über ein
fehlendes oder falsch typisiertes Feld antwortet. Solche Meldungen sind
der beste Hinweis auf die richtige Struktur.

## Falls kein Weg gefunden wird

Das System bleibt vollständig benutzbar: Buchungen und Absagen
funktionieren unabhängig vom Mitteilungsversand. Die Nachrichten stehen
mit Betreff und Text unter „Mitteilungen" bereit und lassen sich von dort
kopieren und im WebUntis-Nachrichtenzentrum manuell versenden. Der
Komfortverlust ist spürbar, aber nichts geht verloren.
