# Schülerliste und Einladungsauswahl

## Wozu

Lehrkräfte laden in Phase 1 gezielt Eltern ein. Ohne Schülerliste geht das
nur über die Eingabe einer WebUntis-Schüler-ID – unpraktikabel im Alltag.
Mit Liste erscheint eine nach Klassen sortierte Abhakliste.

## Zwei Quellen, die sich ergänzen

| Quelle | Liefert | Liefert **nicht** |
|---|---|---|
| WebUntis (`getStudents`) | WebUntis-ID, Namen, Schild-ID (Feld `key`) | **keine Klasse** |
| Schild-NRW (CSV) | Namen, **Klasse**, Schild-ID | keine WebUntis-ID |

Verknüpft werden beide über die **Schild-ID**: In WebUntis steht sie im
Feld `key` des Schülerdatensatzes, im Schild-Export ist sie die interne
Schüler-ID. Sind beide Quellen eingespielt, hat jeder Eintrag sowohl die
WebUntis-ID (nötig für Einladungen und Buchungen) als auch die Klasse
(nötig für die Sortierung).

Der Befund, dass `getStudents()` keine Klassenzuordnung liefert, ist für
diese Instanz belegt (Sondierung 07/2026, Felder: id, key, name, foreName,
longName, gender) und deckt sich mit der Notiz in `CLAUDE.md`.

## Einrichtung

Administration → „Schülerliste für die Einladungsauswahl":

1. **Aus WebUntis übernehmen** – nutzt das hinterlegte Dienstkonto.
2. **CSV aus Schild-NRW ergänzen** – Format je Zeile:

   ```
   Nachname;Vorname;Klasse;Schild-ID
   Paulowski;Paul;06B;007
   ```

   Trennzeichen `;`, `,` oder Tab. Kopfzeile und Kommentarzeilen (`#`)
   werden übersprungen. Die Schild-ID ist optional, aber ohne sie können
   WebUntis- und Schild-Daten nur über Namensgleichheit verknüpft werden.

Reihenfolge egal – der Import erkennt vorhandene Einträge und ergänzt sie.

## Ehemalige Schüler ausblenden

Der Schild-Export enthält **alle** Schüler, auch längst abgegangene – bei
einem Gymnasium schnell das Drei- bis Vierfache der aktuellen Zahl. Ohne
Filter wäre die Auswahlliste unbrauchbar.

Deshalb wertet der Import das **Austrittsdatum** aus (fünfte Spalte):
Liegt es in der Vergangenheit, erscheint der Schüler nicht in der
Auswahlliste. Fehlt die Angabe, gilt der Schüler als aktuell.

WebUntis pflegt dieselbe Information in der Schülerverwaltung („aktiv"
und „Austrittsdatum"), gibt sie über `getStudents()` aber nicht heraus –
dort kommen nur id, key, name, foreName, longName, gender. Ob ein
REST-Endpunkt mehr liefert, prüft die Sondierungsgruppe
„Klassen & Schüler:innen"; sie meldet gezielt, ob ein Feld für
Aktiv-Status, Austrittsdatum oder externe ID gefunden wurde.

Beachten: WebUntis trägt oft das **planmäßige** Schulende ein (etwa
31.07.2029 für einen aktuellen Fünftklässler). Solche Daten liegen in der
Zukunft und gelten korrekt als aktiv.

## Klassenbezeichnungen

Damit `06B` aus WebUntis und `6b` aus Schild dieselbe Klasse sind, werden
Bezeichnungen vereinheitlicht: führende Nullen entfallen, der Buchstabe
wird kleingeschrieben (`06B` → `6b`, `05A` → `5a`). Oberstufenkürzel
bleiben in Großschreibung (`EF`, `Q1`, `Q2`).

## Datenschutz

Diese Tabelle ist die **einzige Stelle im System mit personenbezogenen
Schülerdaten** (Namen und Klassen). Alles andere arbeitet mit IDs.

* Die Liste dient ausschließlich der Auswahl durch Lehrkräfte.
* Erziehungsberechtigte haben keinen Zugriff (403).
* Sie lässt sich jederzeit vollständig löschen – das Tool funktioniert
  danach weiter, die Auswahl erfolgt dann wieder über Schüler-IDs.
* Beim Archivieren eines Sprechtags bleibt sie erhalten (sie gehört nicht
  zum Sprechtag, sondern zur Schule). Wer sie nach dem Sprechtag nicht
  mehr braucht, löscht sie über die Adminseite.

## Grenzen

Einträge ohne WebUntis-ID (nur aus dem CSV, ohne passende Schild-ID)
erscheinen in der Liste, sind aber nicht auswählbar – für eine Einladung
wird die WebUntis-ID gebraucht. Solche Einträge sind gekennzeichnet.

Sobald das neue Schuljahr aktiv ist, ließe sich die Klassenzuordnung auch
direkt aus WebUntis gewinnen (über `getKlassen` und die
Klassenstundenpläne). Der CSV-Weg bleibt als Fallback bestehen.
