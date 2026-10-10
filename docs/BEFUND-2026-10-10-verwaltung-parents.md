# Fund 2, 10.10.2026: Das echte WebUntis-Admin-Konto kann nicht über PARENTS senden

**Herkunft:** Beobachtet vom Betreiber im Betrieb (v0.9.77). Der Fund war
formuliert, ist in dieser Sitzung aber erst am 10.10. angekommen.
Entscheidung: E23.

## Beobachtet

- Eine Absage blieb offen, mit dem Grund „PARENTS: Keine Berechtigung zum
  Versenden von Mitteilungen (HTTP 403)“.
- Angemeldet war der Betreiber mit dem **echten WebUntis-Admin-Konto**
  (personType 16).
- Derselbe 403 trat am 09.10. beim Dienstkonto auf. Dem Admin-Konto fehlt
  die Empfängerart PARENTS; laut Oberfläche hat es nur STAFF und CUSTOM
  (Auskunft des Betreibers, `BESTAND-DIENSTKONTO-2026-10-09.md`, Lage nach
  der Aufnahme).
- **Nicht im Repo:** Das Ergebnis der Dienstkonto-Messung vom 09.10.
  `BEFUND-2026-10-07-pageconfig-schuelerliste.md` Abschnitt 17 enthält nur
  die Vorbereitung, kein Ergebnis. Dieser Absatz hält die Auskunft des
  Betreibers fest: 403 über PARENTS.

## Die Lücke im Entwurf

Absagen wurden in v0.9.73 auf PARENTS umgestellt, Einladungen und die
stellvertretende Bestätigung schon in v0.9.72. Dabei wurde nicht bedacht,
dass auch die Verwaltung diese Aktionen auslöst. Die Bestandsaufnahme nennt
es bei Stelle 3 („Lehrkraft / Verwaltung“); beim Krankheitsausfall
(Stelle 5) handelt **immer** die Verwaltung.

**Betroffen, wenn das echte Admin-Konto angemeldet ist** (am Code gelesen,
v0.9.79; die Rolle „admin“ besteht `auth_require_lehrkraft()`):

| Aktion | Empfängerart | Stand |
|---|---|---|
| Absage einer Buchung | PARENTS (`mit_absage_art`) | **gemessen**: 403 |
| Krankheitsausfall (Stelle 5) | PARENTS | erschlossen: 403 |
| Einladung | PARENTS (`'eltern'`) | erschlossen: 403 |
| Bestätigung nach stellvertretender Buchung | PARENTS (`'eltern'`) | erschlossen: 403 |
| offene PARENTS-Mitteilungen nachsenden | PARENTS | erschlossen: 403 |
| Erinnerungen | CUSTOM | **gemessen**: trägt (v0.9.72) |

In jedem dieser Fälle bleibt die Mitteilung offen, mit Grund. Gebucht,
abgesagt oder eingeladen ist trotzdem; nur die Nachricht an die Eltern
fehlt.

**Nebenbei am Code:** Das echte Admin-Konto bekommt als Kürzel das erste
aus `admin_kuerzel` (`wu_admin_eigenes_kuerzel()`) und damit dessen
`lehrer_id`. Was es tut, steht im Namen dieser Lehrkraft, etwa eine
stellvertretende Buchung bei ihr.

## Entscheidung (Betreiber, E23)

Die Verwaltungsansicht wird künftig über ein **Lehrerkonto mit
`admin_kuerzel`** erreicht, nicht über das echte Admin-Konto. Seit v0.9.78
funktioniert das, im Betrieb bestätigt. Eine Lehrkraft-Sitzung trägt PARENTS
(gemessen, Befund pageconfig Abschnitt 16).

## Offen: was mit dem echten Admin-Konto geschieht

Es bekommt weiterhin die Verwaltung über personType 16. Die Lücke bliebe,
nur unbenutzt. Die drei Möglichkeiten des Betreibers stehen in E23,
nicht entschieden.
