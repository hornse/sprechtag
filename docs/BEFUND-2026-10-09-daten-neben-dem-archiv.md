# Befund 09.10.2026: Daten, die das Archivieren nicht erfasst

**Anlass:** H9 aus `HILFE-ABGLEICH-2026-10-09.md` (behoben in v0.9.74).
Um den Datenschutz-Absatz richtig zu schreiben, wurde am Code gelesen,
was das Archivieren löscht und was bleibt. Dieser Befund hält fest, was
dabei **über H9 hinaus** auffiel. **Nichts davon ist behoben**, außer dass
die Hilfe es jetzt nennt, wo es Eltern betrifft (Entscheidung des Betreibers
zu D1).

**Dringlichkeit (Betreiber, 09.10.2026): D2 vor D1.** Die Nummern bleiben,
wie sie sind, die Reihenfolge der Dringlichkeit nicht. D2 kann einen
Gesundheitsgrund enthalten. Der überdauert nicht nur das Archivieren,
sondern wandert beim Kopieren in jeden folgenden Sprechtag mit und stünde
damit jahrelang in der Verwaltungsansicht. D1 ist eine Kennnummer ohne
Namen. Gebaut wird keines von beiden.

Alle Angaben sind am Code gelesen (Stand v0.9.74) und nicht im Betrieb
gemessen. Die Datenbank wurde nicht abgefragt; es gibt keine Zählwerte.

## Was das Archivieren tut, je Tabelle

`PATCH /api/sprechtage/{id}` mit `phase = archiviert`
(`backend/api/index.php`, Block `if ($archivieren)`):

| Tabelle | beim Archivieren | sonst |
|---|---|---|
| `buchungen` (mit `kommentar`, dem Hinweis an die Lehrkraft) | gelöscht, nur dieser Sprechtag | Absage löscht einzeln |
| `einladungen` | gelöscht | – |
| `kind_lehrer_cache` | gelöscht | – |
| `mitteilungen` (Texte mit Lehrkraft, Zeit, teils Kindname) | gelöscht | – |
| `sprechtag_lehrer` (Anwesenheit, Raum, **Bemerkung**) | **bleibt** (Struktur) | „Kopieren“ übernimmt sie |
| `sprechtag_sonderlehrer` | bleibt (Struktur) | „Kopieren“ übernimmt sie |
| `sprechtage` | bleibt, Phase „archiviert“, `archiviert_am` | „Löschen“ entfernt den Sprechtag samt allem (FK `ON DELETE CASCADE`) |
| `schueler` (Namen, Klassen, Austritt) | **bleibt** | nur „Gesamte Schülerliste löschen“ (`DELETE /api/schueler`) |
| `login_log` (Benutzername, IP, Grund, Zeit) | **bleibt** | Frist `login_log_tage` (1–365, voreingestellt 30), bereinigt bei erfolgreicher Anmeldung; „Protokoll leeren“ |
| `kalender_abo` (Kennnummer, Token) | **bleibt** | **kein Löschweg** |
| `lehrer`, `app_admins` | bleibt (Kollegium) | – |
| `raeume`, `sonderrollen`, `einstellungen` | bleibt (ohne Personenbezug zu Eltern/Kindern) | – |

Diese Tabelle ist seit v0.9.74 in `tests/run_archivieren.php` festgehalten.
Eine neue Tabelle macht die Suite rot, bis sie eingeordnet ist.

## D1 — Kalender-Abo ohne Löschweg

Wer „Meine Termine“ öffnet, bekommt **ohne eigenes Zutun** einen Eintrag
in `kalender_abo`: Die Seite fragt den Link selbsttätig ab
(`frontend/app.js`, `api('/api/kalender-link')`), und `kal_token_holen()`
legt ihn an. Gespeichert werden die WebUntis-`user.id` des Elternkontos
(bei Lehrkräften `1000000000 + lehrer_id`) und ein Token. Der Eintrag ist
nicht ans Archivieren gebunden und hat keine Frist. Im Code gibt es kein
`DELETE FROM kalender_abo`; „Neuen Link erzeugen“ ersetzt nur den Token.
Er bleibt also auch dann bestehen, wenn das Kind die Schule längst
verlassen hat.

Eine Kennnummer ist kein Name, aber sie ist ein Personenbezug: In der
Tabelle `buchungen` führt dieselbe `eltern_user_id`.

**Entschieden (Betreiber, 09.10.2026):** Die Hilfe nennt das jetzt ehrlich
(„derzeit keine Frist“). Eine Löschregel steht auf der Liste, gebaut ist
sie nicht. Mögliche Wege, keiner entschieden: mit dem Archivieren des
letzten offenen Sprechtags; nach einer Frist seit dem letzten Abruf (es
gibt kein Feld „zuletzt abgerufen“, nur `erstellt_am`); bei der
Login-Bereinigung mit erledigen. Die Prüfung „Kalender-Abo: kein Löschweg
im Code“ (`run_archivieren.php`) wird rot, sobald ein Löschweg kommt.
**Das ist Absicht:** Dann muss der Hilfesatz mitgeändert werden.

## D2 — Bemerkungen zur Teilnahme überdauern das Archivieren (dringlicher als D1)

`sprechtag_lehrer.bemerkung` bleibt beim Archivieren als Teil der
Struktur stehen und wird von „Kopieren“ in den neuen Sprechtag übernommen
(`index.php`, `SELECT ?, lehrer_id, … bemerkung`). Der Kommentar in
`index.php` (Teilnehmende Lehrkräfte, v0.9.61) sagt selbst, dass dort
„womöglich, warum jemand nicht teilnimmt“ steht. Das sind Daten des
Kollegiums, nicht der Eltern, und deshalb nicht im Hilfetext. Ob Bemerkungen
beim Kopieren mitwandern sollen, ist nicht entschieden.

## D3 — Hilfe-FAQ verspricht noch ein Dienstkonto

FAQ „Ich habe einen Termin gebucht, aber es kam keine Bestätigung“:
„Bestätigungen werden über WebUntis versendet, **sofern ein Dienstkonto
hinterlegt ist**.“ Seit v0.9.72 gibt es kein Dienstkonto mehr; die
Bestätigung geht über die Sitzung der buchenden Person. Der Satz ist
falsch, aber keine Datenschutzzusage. Er gehört zum Nachziehen der
Hilfeseite, das nicht beauftragt ist. Im Hilfe-Abgleich ist er als **H13**
nachgetragen.

## D4 — Bestätigungsdialog beim Archivieren nennt weniger, als gelöscht wird

`frontend/app.js` (Sprechtag bearbeiten): „Archivieren löscht alle
Buchungen, Einladungen und die Lehrkraft-Zuordnung“. Die Mitteilungen
fehlen. Er sagt weniger, als geschieht, nicht mehr, und richtet sich an
die Verwaltung. Nicht geändert.

## D5 — außerhalb der Datenbank, nicht vollständig geprüft

- **Server-Fehlerprotokoll:** `error_log()` schreibt an mehreren Stellen
  Fehlermeldungen, eine davon mit dem Kürzel einer Lehrkraft
  (`webuntis_adapter.php`). Ob eine Meldung Daten von Eltern oder Kindern
  tragen kann, wurde nicht Stelle für Stelle geprüft. Wie lange die Meldungen
  aufbewahrt werden, entscheidet der Server, nicht die Anwendung.
- **PHP-Sitzung:** Sie hält während der Anmeldung den WebUntis-Cookie und
  die Benutzergruppe. Das ist Laufzeit und entspricht dem Hilfesatz „nur zur
  Laufzeit aus der aktuellen Sitzung“.

## D6 — Nachtrag 10.10.2026: Der Inhalt des Abos kannte keine zeitliche Grenze

Neben dem Eintrag (D1) hatte auch der **Inhalt** des Kalender-Abos keine
Grenze. `GET /api/kalender/{token}.ics` lieferte die Termine aller
Sprechtage, auch vergangener. Gemeldet hat das der Betreiber: Ein Termin
vom 27.07.2026 stand noch in der Kalender-App. So lagen vergangene
Termine samt Kindnamen auch in fremden Kalender-Apps.

**Behoben in v0.9.77 (E21):** Beide Abos, Eltern und Lehrkraft, liefern
nur noch Sprechtage ab heute. Der Datenschutztext nennt das und seine
Grenze: Ob die App einen Eintrag entfernt, liegt an ihr, und ein einzeln
übernommener Termin bleibt dort als Kopie.

**Das Archivieren leert den Inhalt:** Es löscht die Buchungen des
Sprechtags, und das Abo liest nur Buchungen. Das ist ausgeführt belegt
(`tests/run_kalender_abo.php`).

**D1 bleibt offen:** Der Eintrag in `kalender_abo` hat weiterhin keine
Frist und keinen Löschweg.

