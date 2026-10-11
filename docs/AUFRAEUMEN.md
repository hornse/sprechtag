# Aufräum-Zug – vorgemerkt

Punkte, die der Betreiber für einen eigenen Aufräum-Zug vorgesehen hat.
Nichts davon ist ein Fehler im Betrieb. Neue Punkte kommen ans Ende;
erledigte werden mit Version vermerkt, nicht gestrichen.

1. **Ältere Tags nachtragen** (vorgemerkt 11.10.2026). `v0.9.83` war der
   erste Tag im Repo. Die älteren Versionen werden über die Überschriften
   im CHANGELOG zugeordnet. Jeder Tag zeigt auf den Commit, der seine
   Überschrift trägt (REIHENREGELN 6), und geht nach github und uberspace.
2. **Unerreichbare Ausnahme in `slot_buchung_erlaubt()`** (vorgemerkt
   11.10.2026, gefunden mit v0.9.84). Die Funktion nimmt Lehrkraft und
   Verwaltung von der Obergrenze je Konto aus. Seit der tote Zweig im
   Elternweg fort ist (E24), erreicht diese Ausnahme nichts mehr. Sie steht
   da wie eine geltende Regel – dasselbe wie ein Feld, das niemand liest.
3. **Geteilte Kennung der Hälfte-Auswahl** (vorgemerkt 11.10.2026, eigener
   kleiner Schritt). `'haelfte-' + s.id + '-' + l.lehrer_id` wird in
   `anwesenheitZelle` gebildet und in `lehrerZeileDaten` gelesen, die das
   Speichern je Zeile und das Sammelspeichern nutzen; nichts hält die
   beiden Stellen zusammen. Benennt jemand eine um, brechen beide Wege still
   (REIHENREGELN 7: ein Name, den zwei Stellen teilen müssen).
4. **Andere Suiten durchsehen, Suite für Suite** (vorgemerkt 11.10.2026,
   eigener Schritt). Weg wie bei `frontend_halbtags_test.js`: Probebaum,
   Eingriff an der gemeinten Stelle gegen die Suite, wo nötig umstellen,
   wo möglich ausführen. Gesucht wird nach allen vier Arten von Schwäche
   aus `docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md`, nicht nur nach
   doppelten Fundstellen.
   - Ausgangslage (gezählt, nicht bewertet): 15 Suiten suchen 4- bis
     16-mal in ganz `app.js`, am häufigsten `frontend_sidebar` (16),
     `frontend_anzeige` (14), `frontend_hilfetext` (12) und
     `frontend_buchen_uebersicht` (12).
   - Darunter sind legitime Formen, nämlich Prüfungen auf Abwesenheit und
     absichtliche Zählungen. Die Zahl heißt nicht „15 kaputte Suiten“.
   - Ausdrücklich dazu: `frontend_datenschutz_test.js`, in v0.9.83 selbst
     erweitert, mit demselben Muster.
5. **Eintragsnummer von `koordination` nachtragen** (vorgemerkt 11.10.2026).
   Die Vermerke zur E-Mail-Adresse (`docs/SONDIERUNG.md`, CHANGELOG v0.9.88)
   und zum Kürzel (CHANGELOG v0.9.4) verweisen auf die Entscheidung von
   `koordination` zum Regelkonflikt REIHENREGELN 9 gegen 10 – über die
   Mitteilung des Betreibers vom 11.10.2026 und den Befund
   `docs/BEFUND-2026-10-11-sprechtag-modul-und-kontodaten.md`, nicht über
   eine Nummer, weil die Entscheidung dort noch nicht abgelegt ist. Sobald
   sie es ist: je ein Nachtrag mit der Nummer, alter Text unverändert.
