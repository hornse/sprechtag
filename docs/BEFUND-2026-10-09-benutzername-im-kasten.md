# Befund 09.10.2026: Benutzername im Anmeldekasten nicht vorausgefüllt

**Kein Fehler, sondern Absicht. Nicht beheben.**

**Beobachtet** (Abnahmetest zur abgelaufenen Sitzung, v0.9.72/73, vom
Betreiber gemeldet): Der Kasten „Anmelden und senden“ erschien nach über
30 Minuten. Das Feld „WebUntis-Benutzername“ war leer, obwohl der Kasten
so beschrieben war, dass er den Namen vorausfüllt.

**Ursache** (am Code gelesen, nicht nachgestellt; dass die Seite neu
geladen wurde, ist eine Vermutung): Der Kasten füllt das Feld aus
`S.benutzername` (`frontend/app.js`, `sitzungsKastenElement`). Dieser Wert
lebt nur im Browser. Er wird beim Anmelden auf derselben Seite gesetzt und
nirgends gespeichert. Wird die Seite neu geladen, gilt die Anmeldung über
die Sitzung weiter, der Name ist aber leer.

**Entschieden (Betreiber, 09.10.2026): so lassen.** Der Benutzername steht
bewusst nicht in der Sitzung und geht nicht an die Oberfläche. Ihn vom
Server nachzuliefern brächte eine Angabe in die Oberfläche, die dort nicht
hingehört, und sparte nur einen Handgriff. Das ist dieselbe
Datensparsamkeit wie an anderer Stelle.

**Für später:** Wer das Feld leer sieht, hat kein Versehen gefunden. Ein
„Nachliefern vom Server“ widerspräche dieser Entscheidung.
