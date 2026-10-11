# Befund 11.10.2026 — Einladungsliste: Spalte „Hinweis“ ohne Urheber

**Art:** Beschriftung. **Gemeldet aus dem Betrieb** (Betreiber). **Nur
notiert, nicht bauen.**

## Beobachtet

In der Einladungsliste der Lehrkraft heißt die Spalte nur „Hinweis“. Nach
ein paar Tagen weiß man nicht mehr, ob der Text vom System stammt oder von
einem selbst. Der Betreiber hat seinen eigenen Hinweis („Frist ist
abgelaufen“) nicht wiedererkannt.

## Vorschlag (Betreiber)

„Ihr Hinweis“ oder „Hinweis an die Eltern“ wäre eindeutig. Das ist dieselbe
Beschriftung wie im Eingabefeld.

## Am Code gelesen, nicht gemessen

- Das Eingabefeld heißt „Hinweis an die Eltern (optional, gilt für alle)“
  (`frontend/app.js`, Feld `einl-hinweis`). Die Spalte der Liste heißt
  „Hinweis“ (Kopfzeile `['Kind', 'Hinweis', 'Status', '']`).
- **Dasselbe Wort steht in derselben Ansicht in einer zweiten Bedeutung.**
  Die Tabelle „Abgesagte Termine“ (v0.9.83) hat ebenfalls eine Spalte
  „Hinweis“. Dort steht der Hinweis *der Eltern an die Lehrkraft*
  (`kommentar`), also das Gegenstück von einem anderen Urheber. Beim
  Umbenennen gehört diese Spalte dazu, sonst bleibt das Wort mehrdeutig.
- Die Spalte „Hinweis“ ist auf dem Telefon die Beschriftung in der Karte
  (`data-label` aus der Kopfzeile, E13). Eine neue Überschrift ändert
  beide Darstellungen zugleich.
