# Befund 11.10.2026 — Verwaltung über ein Lehrerkonto: keine Gruppenliste

**Art:** Zielkonflikt aus E23, kein Fehler. **Gemeldet aus dem Betrieb**
(Betreiber). **Nicht jetzt bauen:** Die Meldung wird beim nächsten
Anfassen der Seite ergänzt, Hilfe und Dokumentation beim Nachziehen der
Texte.

## Beobachtet

Angemeldet mit einem Lehrerkonto, das in `admin_kuerzel` steht (also mit
der Verwaltungsansicht in sprechtag), erscheint unter „Volljährige
Schüler“:

> Die Gruppenliste aus WebUntis ließ sich nicht abrufen (Status 403).
> Gruppen bitte eintippen, eine je Zeile.

So gemeldet. Nach dem Code lautet der Satz: „Die Gruppenliste aus WebUntis
ließ sich nicht abrufen: Gruppenliste aus WebUntis nicht abrufbar (…).
Gruppen bitte eintippen, eine je Zeile.“ An der Klammer steht der Grund,
hier der Status 403. Der Code nennt die Gruppenliste also zweimal. Das ist
eine Kleinigkeit für dasselbe Anfassen.

## Warum das erwartbar ist

`/WebUntis/api/userrole/config` antwortet **nur der Verwaltung**. Gemessen
am 09.10.2026 (v0.9.67, `docs/BEFUND-2026-10-07-pageconfig-schuelerliste.md`,
Nachtrag „Messung über unsere Sitzung“): Admin 200, Lehrkraft 403, Eltern
403, Schülerin 403. In WebUntis bleibt das Konto ein Lehrerkonto. Dass
sprechtag ihm die Verwaltungsansicht gibt, ändert an den Rechten in
WebUntis nichts. Die Seite tut, was der E15-Nachtrag (v0.9.68) vorsieht:
Scheitert der Abruf, bleibt Eintippen als Rückfall, mit Grund.

## Der Zielkonflikt

E23 hat entschieden, die Verwaltung über ein Lehrerkonto zu erreichen,
damit PARENTS funktioniert, denn dem echten Admin-Konto fehlt diese
Empfängerart. **Dieselbe Person verliert damit den Zugriff auf die
Gruppenliste.** Das war bei E23 nicht bedacht.

**Die Verwaltungsansicht kann also je nach Konto verschieden viel:**

| Konto | PARENTS (Absagen, Ausfälle, Einladungen) | Gruppenliste (Volljährige) |
|---|---|---|
| WebUntis-Admin (personType 16) | nein (403, E23) | ja |
| Lehrerkonto mit `admin_kuerzel` | ja | nein (403), Eintippen als Rückfall |

**Praktisch verschmerzbar** (Betreiber): Die Gruppen werden einmal
eingetragen, und der Rückfall funktioniert.

## Entschieden (Betreiber): dokumentieren, an drei Stellen

Wer die Volljährigen-Gruppen auswählen will, meldet sich dafür **einmalig
mit einem WebUntis-Admin-Konto** an; danach reicht das Lehrerkonto.

1. **In der Meldung selbst – der wichtigste Ort.** Heute sagt sie nur
   „eintippen“, nicht, dass es auch anders geht. Wer sie liest, steht genau
   vor dem Problem. Ein Halbsatz dort erspart die Suche. Hilfe und
   Dokumentation findet man erst, wenn man weiß, wonach man sucht.
2. **In der Hilfe.**
3. **In der Dokumentation** (`docs/BEDIENUNG.md` oder was beim Nachziehen
   daraus wird).

**Zeitpunkt:** die Meldung beim nächsten Anfassen der Seite „Volljährige
Schüler“, die Texte beim Nachziehen.

## Am Code gelesen, nicht gemessen (für das Anfassen)

- „danach reicht das Lehrerkonto“: Gespeichert wird das `label` jeder
  gewählten Gruppe (E15-Nachtrag). Mit dem Lehrerkonto füllt die Seite das
  Textfeld mit den gespeicherten Gruppen vor (`ta.value =
  gewaehlt.join('\n')` in `frontend/app.js`). Am Gerät ist das nicht
  geprüft.
- Die Meldung erscheint als `hinweis-wichtig` **auch dann**, wenn schon
  Gruppen gespeichert sind, also bei jedem Besuch mit dem Lehrerkonto.
  Ob sie dann anders lauten soll, ist beim Anfassen als Richtungsfrage zu
  melden, nicht nebenbei zu entscheiden.
- Der Text der Meldung entsteht an zwei Stellen: der Grund in
  `backend/api/webuntis_adapter.php` (`auswahl_fehler`), der Satz drum
  herum in `frontend/app.js`. Der Halbsatz gehört in den Satz im Frontend.

## Nachtrag 11.10.2026 — jetzt gemessen, nicht mehr geschlossen (v0.9.90)

Der Abschnitt „Warum das erwartbar ist“ schloss aus der Messung vom
09.10.2026 auf das Lehrerkonto. **Beide Zeilen der Tabelle sind jetzt
gemessen:**

| Konto | Gruppenliste | gemessen |
|---|---|---|
| WebUntis-Admin (personType 16) | 200 | 09.10.2026, über unsere Sitzung (v0.9.67) |
| Lehrerkonto mit `admin_kuerzel` | 403 | 11.10.2026, im Betrieb (Betreiber), Meldung wie oben unter „Beobachtet“ |

Damit stimmt „nur einmal gebraucht“: Für die Gruppenliste braucht es das
Administrationskonto, sonst nicht. So steht es seit v0.9.90 in der Hilfe
(Teil „Für die Administration“, „Mit welchem Konto“), Stelle 2 von drei.

**Weiter nur am Code gelesen, nicht gemessen:** „danach reicht das
Lehrerkonto“, also dass die Seite mit dem Lehrerkonto das Textfeld mit den
gespeicherten Gruppen vorbefüllt (siehe oben, „Am Code gelesen“).

**Offen bleiben** Stelle 1 (der Halbsatz in der Meldung selbst, beim
nächsten Anfassen der Seite „Volljährige Schüler“) und Stelle 3 (die
Dokumentation).
