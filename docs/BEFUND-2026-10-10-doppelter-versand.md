# Befund A, 10.10.2026: doppelter Versand einer Einladung (einmal beobachtet)

**Herkunft:** Der Betreiber hatte den Befund formuliert, aber nicht
abgeschickt. Zuerst vermerkt wurde er im E17-Nachtrag vom 10.10.2026
(v0.9.79); diese Datei ist jetzt der Ort, an dem er geführt wird.

## Beobachtet (Betreiber, Betrieb, v0.9.77)

- Eine Einladung wurde ausgelöst. Sie kam beim Empfänger an, galt in
  sprechtag aber als „noch nicht verschickt“.
- Ein Klick auf „Jetzt senden“ schickte sie ein **zweites Mal**.
- **Spurenlage:** Im Server-Log steht kein Eintrag. Der ursprüngliche Grund
  an der Mitteilung wurde vom zweiten Versand überschrieben.
- **Nachträglich nicht klärbar.**

## Warum es zählt

lernzeiten hat gemessen, dass WebUntis keinen Schutz gegen doppelte
Mitteilungen bietet. Was sprechtag für „nicht verschickt“ hält und erneut
sendet, kommt also ein zweites Mal an.

## Zwei mögliche Erklärungen (beide Vermutung)

1. **Die Konstellation von Stelle 1** (E17-Nachtrag): Der erste Versand
   scheiterte tatsächlich, und der Empfänger sah eine **andere**
   Mitteilung, nicht diese. Erst der zweite Versand wäre dann diese
   Einladung gewesen.
   **Widerspricht dem Code in einem Punkt:** Laut Meldung wurde die
   Einladung „als Elternteil ausgelöst“. Einladen darf nach dem Code nur
   eine Lehrkraft oder die Verwaltung (`POST /api/einladungen`,
   `auth_require_lehrkraft()`); eine Eltern-Sitzung kann keine Einladung
   auslösen. Vielleicht ist „an ein Elternteil“ gemeint. Das ist offen.
2. **Die Konstellation von Fund 2** (`BEFUND-2026-10-10-verwaltung-parents.md`):
   Einladungen gehen seit v0.9.72 über PARENTS. War das echte
   WebUntis-Admin-Konto angemeldet, scheitert PARENTS mit 403, und die
   Einladung bleibt offen. „Jetzt senden“ aus einer Lehrkraft-Sitzung
   ginge dann hinaus. Dass die erste Mitteilung trotzdem ankam, erklärt
   das nicht. Es passt aber zu „offen“, ohne eine Eltern-Sitzung
   anzunehmen.

Beide lassen sich nicht nachträglich unterscheiden. Unterscheiden ließe
sich beim nächsten Mal an zwei Angaben: der Rolle, die ausgelöst hat, und
dem ursprünglichen Grund.

## Bei Wiederauftreten

**Sofort `status` und `grund` der Mitteilung auslesen, BEVOR nachgesendet
wird**, dazu notieren, mit welchem Konto ausgelöst wurde. Der Grund wird
beim nächsten Versuch überschrieben; danach ist er verloren.

**Gefunden, nicht behoben:** Der Grund des ersten Fehlschlags wird bei einem
neuen Versuch überschrieben, statt erhalten zu bleiben. Das hat die Spur
hier gelöscht. Eine Änderung (etwa: den ersten Grund aufbewahren) ist nicht
beauftragt.
