# Befund 08.10.2026 — Eingeladene Lehrkraft fehlt in den Kacheln

Abgelegt am 08.10.2026, Stand des Codes: ff3bc28 (v0.9.52).
**Noch nichts behoben** — die Behebung ist Teil des Umbaus der
Einladungsauswahl (Abschnitt 5).

## 1 — Gemeldet aus dem Betrieb

Eine Lehrkraft hat in Phase 1 die Eltern eines Kindes eingeladen. Das
Elternkonto sieht die einladende Lehrkraft nicht in den Kacheln und kann
keinen Termin buchen. (Meldung, keine eigene Messung; keine Namen.)

## 2 — Ursache 1: Die Kacheln kennen keine Einladungen (aus dem Code)

`GET /api/buchbare-lehrer` (`backend/api/buchungen.php:61–166`) baut
die Liste aus zwei Quellen:

| Quelle | Stelle | Inhalt |
|---|---|---|
| `kind_lehrer_cache` | `buchungen.php:122–135` | Unterrichtende laut Stundenplan |
| `sprechtag_sonderlehrer` | `buchungen.php:139–158` | Sonderrollen, nach Jahrgang gefiltert |

`einladungen` kommt darin nicht vor. Wer eingeladen hat, das Kind aber
nicht unterrichtet und keine Sonderrolle trägt, erscheint nicht. Das
Frontend lädt die Kacheln ausschließlich aus dieser Route
(`frontend/app.js:1291` `ladeLehrerListe()`).

## 3 — Ursache 2: Auch die Buchung würde abgewiesen (aus dem Code)

**Die Annahme, die Buchungsprüfung lasse den Fall zu, trifft nicht zu.**
Richtig ist: `slot_buchung_erlaubt()` (`backend/api/slots.php:186`)
bekommt `eingeladen` aus der `einladungen`-Tabelle
(`buchungen.php:598–601`). Aber **vor** der Phase-1-Prüfung steht:

```
if (!($kontext['darf_lehrkraft'] ?? false)) {
    return $nein('Bei dieser Lehrkraft kann für dieses Kind kein Termin gebucht werden.');
}
```

(`slots.php:202–204`). `darf_lehrkraft` kommt aus `bu_lehrer_erlaubt()`
(`buchungen.php:43–58`, gerufen in `:607`), und die prüft **dieselben
zwei Quellen wie die Kacheln** — Cache und Sonderrollen, keine
Einladungen. Für die eingeladene, nicht unterrichtende Lehrkraft ist
`darf_lehrkraft` also `false`, und die Buchung scheitert mit dieser
Meldung, bevor `eingeladen` gelesen wird.

**Zwei Ursachen, dasselbe Symptom.** Würde nur die Kachel ergänzt, käme
das Elternkonto bis zum Buchen und bekäme dort eine Ablehnung. Die
Behebung der ersten Ursache sähe dann wie ein Irrtum aus (REIHENREGELN
Abschnitt 1: „suche eine zweite Ursache, bevor du die erste
verwirfst“).

*Nicht gemessen:* Es wurde kein Buchungsversuch gegen eine solche
Lehrkraft gefahren. Der Schluss stützt sich auf die Reihenfolge im
Code; die Eigenschaft ließe sich mit `tests/run_slots.php` ausführen
(dort wird `slot_buchung_erlaubt()` geprüft) — aber `bu_lehrer_erlaubt()`
braucht die Datenbank.

Die stellvertretende Buchung durch die Lehrkraft
(`buchungen.php:364` ff.) ruft `bu_lehrer_erlaubt()` nicht und ist
davon nicht betroffen.

## 4 — Daneben: ein Weg, den niemand aufruft

`GET /api/einladungen` hat einen Zweig für Eltern
(`buchungen.php:768–778`, „nur Einladungen für die eigenen Kinder“). Das
Frontend ruft `/api/einladungen` nur aus `ansichtEinladungen()`
(`app.js:1938`), und die erreichen nur Lehrkräfte und Verwaltung
(Navigation `app.js:712–716`). **Der Elternzweig wird nie aufgerufen** —
er liefert genau die Auskunft, die den Kacheln fehlt. Gemeldet, nicht
behoben.

## 5 — Entschiedene Gliederung (noch nicht gebaut)

Entscheidung des Betreibers, Teil des Umbaus:

- **Phase 1:** in den Kacheln **nur die Eingeladenen** — nicht zusätzlich
  zu den anderen.
- **Ab Phase 2:** Dreiteilung —
  1. Eingeladene,
  2. Unterrichtende (Klassenleitung darin hervorgehoben),
  3. weitere Lehrkräfte hinter einer Suche statt als Kacheln.

**Für den Umbau folgt aus Abschnitt 3:** Die Gliederung allein genügt
nicht — `bu_lehrer_erlaubt()` muss Eingeladene ebenfalls zulassen, sonst
zeigt die Kachel einen Termin, den die Buchung ablehnt. Kachel und
Buchungsrecht fragen heute **dieselben zwei Quellen an zwei Stellen** ab;
das ist die Engstelle, an der beides zusammen geändert werden muss.

---

## Nicht in diesem Zug

- die Behebung (Teil des Umbaus),
- ein Eintrag in `docs/ENTSCHEIDUNGEN.md` für die Gliederung aus
  Abschnitt 5 — entschieden ist sie, eingetragen noch nicht.

---

## 6 — Nachtrag 08.10.2026: Die ursprüngliche Diagnose war falsch

Gemeldet wurde der Fall mit der Diagnose: **„Die Buchungsprüfung selbst
ist korrekt: `slot_buchung_erlaubt` bekommt `eingeladen` aus der
`einladungen`-Tabelle und würde die Buchung zulassen. Man kommt nur nie
dorthin, weil die Kachel fehlt.“**

**Das war falsch.** `slot_buchung_erlaubt()` war nicht zu Ende gelesen:
Die Prüfung auf `darf_lehrkraft` steht vor der Phase-1-Prüfung und lehnt
ab, bevor `eingeladen` gelesen wird (Abschnitt 3). Abschnitt 3 ist die
berichtigte Fassung; diese hier hält fest, wovon sie abweicht.

**Was die falsche Diagnose angelegt hätte:** eine Behebung nur der
Kachel. Das Elternkonto wäre bis zum Buchen gekommen und dort
abgewiesen worden — und die richtige erste Behebung hätte wie ein
Irrtum ausgesehen.

Der Abschnitt „Nicht in diesem Zug“ oben ist in einem Punkt überholt:
Die Gliederung aus Abschnitt 5 steht seit dem 08.10.2026 als E10 in
`docs/ENTSCHEIDUNGEN.md`.
