# Beilage: Mitteilung an die Eltern über die Kennung des Kindes (aus lernzeiten)

Kopiert am 09.10.2026 aus `hornse/lernzeiten`, Stand `430d98d`, als Vorlage
für die Messung „recipientOption PARENTS auf unserem Pfad“ (sprechtag
v0.9.69). Beigelegt statt über Verzeichnisgrenzen gelesen (REIHENREGELN 5).
Personennamen sind durch „das Testkind“ ersetzt.

## Der Körper (lernzeiten `backend/api/mitteilung.php`, `lz_mitteilung_koerper()`)

`POST /WebUntis/api/rest/view/v2/messages`, **multipart**, ein Teil
`request` (filename `blob`) mit JSON – laut lernzeiten **gemessen am
06.10.2026** aus der Oberfläche (dort `ENDPUNKTE.md`, E288):

```php
return [
    'subject'             => (string)($text['subject'] ?? ''),
    'content'             => (string)($text['content'] ?? ''),
    'recipientOption'     => $eltern ? 'PARENTS' : 'STUDENTS',
    'recipientPersonIds'  => [$personId],
    'recipientGroupIds'   => [],
    'copyToStudent'       => $eltern,
    'requestConfirmation' => false,
    'oneDriveAttachments' => [],
    'forbidReply'         => false,
];
```

Antwort: `numberOfRecipients` / `numberOfCCRecipients`.

## Was lernzeiten gemessen hat (dort `docs/BEFUNDE.md`, Frage 68, E289/E295)

- 06.10.2026, `recipientOption: "PARENTS"`, `copyToStudent: false` an das
  Testkind → **nur die Eltern** erreicht; `recipientOption: "STUDENTS"` →
  nur das Kind.
- Die Elternmitteilung an das Testkind erreichte **vier** Empfänger
  (`numberOfRecipients: 4`). Auskunft dort: die vier Erziehungsberechtigten
  sind Kolleginnen oder Kollegen, als **Testeltern** hinterlegt und
  informiert.

## Was davon für sprechtag NICHT gilt, bis gemessen

- lernzeiten nutzt `/v2/messages` mit `recipientPersonIds`; sprechtag nutzt
  `/v2/messages/users` mit `recipientUserIds`. Ob `/users` `recipientOption`
  kennt, ist offen.
- Welche Kennung lernzeiten als `$personId` einsetzt, steht hier nicht.
  Für Schüler fallen Personenkennung und Kind-Kennung zusammen (sprechtag,
  Befund 07.10., Abschnitt 12) – die Messung kann die beiden deshalb nicht
  trennen.
