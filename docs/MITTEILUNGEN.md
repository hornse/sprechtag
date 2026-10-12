# WebUntis-Mitteilungen

Wie `sprechtag` Nachrichten über WebUntis verschickt: an wen, wann, auf
welchem Weg, und was geschieht, wenn es nicht klappt. Stand v0.9.93,
12.10.2026, am Code gelesen; Messungen mit Datum. Die Fassung vom
24.07.2026 steht in der Git-Geschichte (bis `04251e5`). Was WebUntis dafür
bereitstellen muss, steht in `docs/WEBUNTIS.md`.

## Zwei Wege

**An die Erziehungsberechtigten eines Kindes – `recipientOption: PARENTS`**
(E16, E17). Das ist der Normalfall.

```
POST /WebUntis/api/rest/view/v2/messages      (Multipart, Teil "request")
{"subject":"…","content":"…","recipientOption":"PARENTS",
 "recipientPersonIds":[<Kennung des Kindes>],"recipientGroupIds":[],
 "copyToStudent":false,"requestConfirmation":false,
 "oneDriveAttachments":[],"forbidReply":false}
```

- Adressiert wird **das Kind**; WebUntis löst daraus alle mit ihm
  verknüpften Elternkonten auf. `sprechtag` braucht dafür keine Kennungen
  der Eltern. Ohne Verknüpfung in WebUntis kommt nichts an.
- `copyToStudent` ist aus: Eine Nachricht an die Eltern landet nicht beim
  Kind.
- **Erfolg heißt mindestens ein Empfänger** (`numberOfRecipients ≥ 1`),
  nicht Status 200. Ein 2xx mit 0 Empfängern hat niemanden erreicht; einer
  ohne Zahl ist unklar – beides bleibt offen, mit Grund. Bei „unklar“ sagt
  die Meldung, vor erneutem Senden in WebUntis unter „Gesendet“
  nachzusehen.
- `/v2/messages/users` lehnt PARENTS ab (500); der Pfad ist `/v2/messages`.
  Gemessen am 09.10.2026 an der Referenzschule: angekommen.

**An ein einzelnes Konto – `recipientUserIds`.** Der Weg vom 24.07.2026,
belegt durch Mitschnitt der WebUntis-Oberfläche:

```
POST /WebUntis/api/rest/view/v2/messages/users   (Multipart, Teil "request", filename "blob")
{"subject":"…","content":"…","requestConfirmation":false,
 "recipientUserIds":[<user.id>],"oneDriveAttachments":[],"forbidReply":false}
```

- Der JSON-Block ist ein **Multipart-Teil**, kein Request-Body; reine
  JSON-POSTs scheitern.
- Adressiert wird die **`user.id`** aus `app/data`, nicht die `personId`
  aus `authenticate`.
- Gebraucht wird der Weg nur noch für die freie Mitteilung (unten) und für
  eine Absage, zu der keine Kennung des Kindes vorliegt.
- **Rückfall-Varianten:** Schlägt der belegte Weg fehl, folgen sechs weitere
  (andere Pfade, JSON statt Multipart, andere Feldnamen) – für eine
  WebUntis-Fassung, die abweicht (`mit_varianten()`). Die erfolgreiche wird
  gemerkt und künftig zuerst probiert. 401 und 403 brechen ab
  (Rechteproblem, weitere Varianten wären sinnlos), ebenso „nicht
  erreichbar“; bei jedem anderen Fehler kommt die nächste Variante.
  Für PARENTS gibt es keine Varianten.

**Erinnerungen** gehen einen dritten Weg: an eine WebUntis-Empfängerliste
(Art und Kennung in der Oberfläche der Verwaltung eingestellt). Die Liste
wird aufgelöst und über `/v2/messages/users` in Blöcken zu 500 Empfängern
beschickt. Kein Zeitplan: Die Verwaltung sieht die Zahl der Empfänger und
löst den Versand selbst aus.

## Wann Mitteilungen entstehen

| Anlass | Auslöser | an |
|---|---|---|
| `einladung` | eine Lehrkraft lädt zu einem Kind ein | die Eltern des Kindes (PARENTS) |
| `bestaetigung` | eine Lehrkraft bucht **stellvertretend** | die Eltern des Kindes (PARENTS) |
| `absage` | eine Lehrkraft oder die Verwaltung sagt einen Termin ab | die Eltern des Kindes (PARENTS); ohne Kennung des Kindes das buchende Konto |
| `absage` | Krankheitsausfall: die Verwaltung gibt die Termine einer Lehrkraft frei | je Termin wie oben; alle zusammen in einem Versand |
| `hinweis` | freie Mitteilung über `POST /api/mitteilungen` | ein Konto |

**Keine Mitteilung entsteht,**
- wenn Eltern selbst buchen: Elternkonten dürfen in WebUntis nicht senden
  (403, gemessen 10.10.2026; v0.9.79). Der Termin steht in „Meine Termine“
  und im Kalender-Abo.
- wenn Eltern selbst absagen: Es wird nur gelöscht (E19).

## Versand und Warteschlange

Jede Mitteilung wird **zuerst gespeichert** (Status `offen`) und dann
sofort versucht – über die **WebUntis-Sitzung der Person, die handelt**
(E17). Ein Dienstkonto oder hinterlegte Zugangsdaten gibt es nicht mehr.

- **Ist die Sitzung abgelaufen** (an der Referenzschule nach rund 25 bis 30
  Minuten, E9), bleibt die Mitteilung offen, mit dem Grund. Die Handlung
  selbst – Absage, Freigabe, Einladung – gilt trotzdem.
- **Nach der nächsten Anmeldung** meldet die Seite „N Mitteilungen sind
  gespeichert, aber noch nicht verschickt“; „Jetzt senden“ holt es nach.
  Lehrkräfte sehen und senden nur Mitteilungen zu ihren eigenen Terminen,
  die Verwaltung alle. „Alle offenen eines Sprechtags“ sind je Durchgang
  höchstens 200.
- **Lehnt WebUntis ab,** bleibt die Mitteilung ebenfalls offen. Die Antwort
  nennt je Variante, was WebUntis gesagt hat – das ist die Auskunft, mit
  der sich ein geänderter Versandweg nachstellen lässt (Mitschnitt in der
  WebUntis-Oberfläche, dann als weitere Variante in `mit_varianten()`).

## Rechte (gemessen an der Referenzschule)

- **Lehrkraft-Sitzungen senden**, obwohl ihr Token nur den Bereich `mg:r`
  trägt. Maßgeblich ist `messages/permissions`, nicht der Bereich im Token
  (E1, berichtigt am 07.10.2026; die Fassung vom 24.07.2026 hielt `mg:r` für
  „nur lesen“).
- **Das WebUntis-Admin-Konto bekommt für PARENTS 403** (E23). Die
  Verwaltung arbeitet deshalb über ein Lehrerkonto, das Administrator ist
  (über „Administratoren“ oder als Notzugang in `admin_kuerzel`, E29).
- **Elternkonten senden nicht** (403, 10.10.2026).

## Empfängersuche – nur noch beim stellvertretenden Buchen

Bucht eine Lehrkraft für Eltern, muss der Termin einem **Elternkonto**
zugeordnet werden, damit er dort unter „Meine Termine“ und im Kalender-Abo
erscheint. Die Mitteilung selbst braucht das nicht (PARENTS).

- Gesucht wird über die Empfängersuche des Nachrichtenzentrums, mit der
  Sitzung der Lehrkraft: als Lehrkraft
  `GET …/v1/messages/recipients/PARENTS/search`, als Admin
  `POST …/v2/messages/recipients/CUSTOM/filter` (belegt am 24.07.2026;
  die Lehrkraft-Suche findet die Konten, gemessen v0.9.69).
- **Die Verknüpfung in der Antwort läuft über Namen:** Unter `tags` stehen
  die Namen der Kinder als Text. Der Abgleich ist deshalb streng –
  normalisiert (Groß- und Kleinschreibung, Leerraum, Wortreihenfolge), aber
  exakt; Teiltreffer zählen nicht, damit kein fremdes Konto zugeordnet
  wird.
- Konten mit `role: "STUDENT"` werden übergangen.
- **Rückfall:** das Elternkonto aus einer früheren Buchung desselben
  Kindes.

## Datenschutz

Gespeichert werden je Mitteilung: Anlass, Betreff, Text, Status und Grund,
die Empfängerart, bei einem Konto dessen `user.id`, bei PARENTS die
Kennung des Kindes, dazu Name und Klasse des Kindes für die Liste der
Lehrkraft (E20). Keine Namen oder Kennungen der Eltern bei PARENTS, keine
E-Mail-Adressen. Der Text nennt Lehrkraft und Uhrzeit, die Bestätigung
auch den Raum, die Einladung den Namen des Kindes.

Beim Archivieren eines Sprechtags werden seine Mitteilungen gelöscht.
