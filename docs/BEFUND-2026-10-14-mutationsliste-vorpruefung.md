# Befund: Die Mutationsliste prüft ihre erwarteten Zeilen nicht (14.10.2026)

Aus `sprechtag`, v0.9.105, E34. Für `koordination` als Kandidat für die
Reihe – **jedes Projekt mit einer Mutationsliste hat dieselbe Lücke**.

## Was fehlte

Eine Mutationsserie baut einen Fehler ein und verlangt, dass eine
**bestimmte** Prüfung rot wird – gesucht als „✗ <erwartete Zeile>“. Ob es
diese Zeile überhaupt gibt, prüfte niemand. Ein Schreibfehler in der
erwarteten Zeile, ein Name, der sich geändert hat, oder ein Name, der zwei
Prüfungen trifft, fällt erst auf, wenn die Mutation läuft – als „NICHT
ANGESCHLAGEN“, das wie eine schwache Prüfung aussieht und eine schwache
Definition ist.

## Anlass

Dreimal in Folge (`sprechtag` v0.9.104/105): LG4 (falsche Zeile erwartet),
LG8 (Fall fehlte), XA4/XA7 (`\/` in der erwarteten Zeile – sie ist keine
Regex, gesucht wird mit `grep -F`). Drei Einzelfälle sind ein Muster
(REIHENREGELN 1).

## Die Vorprüfung

Vor dem Lauf läuft jede Suite einmal **unverändert**; jede erwartete Zeile
muss in deren Ausgabe als „✓ …“ **genau einmal** stehen. Null ist der
Schreibfehler, mehr als einer die Mehrdeutigkeit. Ist der Vorlauf rot,
wird nichts eingebaut. Gemessen an der **Ausgabe**, nicht am Quelltext:
Dort stehen Namen escapt (`\'Hq, Mq\'`), und so sucht auch der Lauf.

Umsetzung: `sprechtag/tests/mutationen.sh`, Block „Vorprüfung“ – das Skript
ruft sich im Modus „vor“ selbst auf; `mut` und `mut_sh` prüfen dort nur
die erwartete Zeile.

## Die Regel (Betreiber, 14.10.2026)

> **Der Name einer Prüfung beginnt mit einem festen Teil, und die
> erwartete Zeile ist dieser Teil – und trifft genau einen.**

## Was der erste Vorlauf in `sprechtag` fand (660 erwartete Zeilen)

| Art | Stellen | Was es war |
|---|---|---|
| Schreibfehler | 2 | `\/` statt `/` (XA4, XA7) |
| Name trägt den mutierten Wert | 2 | „(14 Tage)“, „(180)“ – rot heißt die Zeile anders als grün |
| Zeile nur in Rot | 1 | eine Tabelle, die erst die Mutation hinzufügt |
| Grün und Rot verschieden formuliert | 3 | „keine Hexfarben …“ / „Hexfarben …: …“ |
| **mehrdeutig, bisher unbemerkt** | 3 | zwei Prüfungen mit **gleichem Namen** (Phase 1/2); ein Teil, der zwei Namen anfängt |

Die letzte Zeile ist der eigentliche Fund: Drei Mutationen galten als
belegt, **ohne dass feststand, welche Prüfung sie belegt.** Gemessen:
alle drei trafen die gemeinte – aber das wusste vorher niemand.

## Gegenproben

- Mit dem Schreibfehler meldete der Vorlauf genau die zwei Stellen und
  baute nichts ein (Prüfsummen gleich).
- Eine auf den festen Teil gekürzte Zeile, die fünf Namen trifft, meldete
  er als mehrdeutig.

## Was es nicht fängt

Ob die Mutation an der erwarteten Zeile **anschlägt**, sagt weiterhin erst
der Lauf. Die Vorprüfung sagt nur, dass die Erwartung eindeutig
formulierbar ist.
