# Aufräum-Zug – vorgemerkt

Punkte, die der Betreiber für einen eigenen Aufräum-Zug vorgesehen hat.
Nichts davon ist ein Fehler im Betrieb. Neue Punkte kommen ans Ende;
erledigte werden mit Version vermerkt, nicht gestrichen.

**Vorrang (12.10.2026, Betreiber): Punkt 9 zuerst.** Er steht trotzdem
unter 9, weil vergebene Nummern nicht verschoben werden.

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
   wo möglich ausführen. Gesucht wird nach allen sieben Arten von Schwäche
   aus `docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md` (bis 12.10.2026
   vier; 5 bis 7 im Nachtrag dort), nicht nur nach doppelten Fundstellen.
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
6. **Veraltete Code-Kommentare** (vorgemerkt 12.10.2026, aus
   `docs/BESTAND-DOKU-2026-10-11.md`, Frage 6: Codeänderungen, nicht
   Doku-Zug).
   - `backend/api/mitteilungen.php`, Kommentar über `mit_absage_art()`:
     „Die Bestätigung nach einer Elternbuchung bleibt beim buchenden
     Konto“ – diese Bestätigung gibt es seit v0.9.79 nicht mehr.
   - `backend/api/buchungen.php`, Kopf von `POST /api/lehrer-ermitteln`:
     nennt `[benutzername, passwort]`; der Code liest beides nicht mehr.
   - `backend/router.php`, Kopf: Verweis auf `NEUES_PROJEKT_PROMPT.md`, die
     es nicht gibt.
   - `backend/config.example.php`, Abschnitt `security`: „für das
     Sondierwerkzeug (und später den Login)“ – seit v0.9.93 gilt die Bremse
     an Anmeldung, Sondierung und Stammdaten-Sync (gefunden 12.10.2026,
     nach der Bestandsaufnahme).
7. **`PORT` in `docs/BETRIEB.md`** (vorgemerkt 12.10.2026, Betreiber).
   `8085` ist ein Wert dieser Installation; in einer Anleitung mit
   Platzhaltern wäre `PORT` konsequent. Mitnehmen, wenn `BETRIEB.md`
   ohnehin angefasst wird.
8. **Migrationsschleife auf leerer Datenbank messen** (vorgemerkt
   12.10.2026, offener Punkt, keine Codeänderung). README „Installation“
   und `docs/BETRIEB.md` sagen: auf leerer Datenbank alle Dateien in
   Namensreihenfolge – am Text gelesen, nicht gelaufen, weil hier keine
   MariaDB läuft. Messen lässt es sich, sobald jemand eine frische
   Datenbank aufsetzt: Ausgabe der Schleife ansehen, danach den Testlauf
   gegen das Schema halten. Bis dahin bleibt der Vermerk „nicht
   ausprobiert“ stehen.
9. **Leeres `catch (Throwable)` in `mit_versand_ausfuehren()`** (vorgemerkt
   12.10.2026, **Vorrang**). `backend/api/mitteilungen.php`, beim Lesen der
   gemerkten Versandvariante: `catch (Throwable $e) { /* Tabelle ggf. noch
   leer */ }`. Jeder Fehler – auch ein Programmierfehler – wird zu „keine
   Variante gemerkt“, und der Versand fällt still auf die Reihenfolge aller
   Varianten zurück. Das ist das Muster aus E9 („Daneben gefunden“: dort
   verschluckte `catch (Throwable …)` die Ursachen, und der Rückfall auf das
   Dienstkonto sah aus wie ein geordneter Ablauf) und aus FALLSTRICKE 3
   (leeres `catch`, begründender Kommentar daneben). `koordination` hat es
   in M47 festgehalten: „Ein Rückfall macht den Fehler unsichtbar, nicht
   harmlos … Ein `catch` mit Rückfall meldet Erfolg.“ Wo ein Fehlerfall
   einen Rückfall auslöst, muss der Rückfall unterscheidbar bleiben. **Die
   Folge ist hier mild:** Der Rückfall probiert alle Varianten der Reihe
   nach, Nachrichten gehen weiter hinaus; unsichtbar bleibt nur, dass das
   Lesen scheiterte. Betroffen ist nur der Weg an ein Konto, nicht PARENTS
   (am Code gelesen). Der Vorrang gilt dem Muster, nicht der Wirkung.
   Prüfung zuerst (rot zeigen), dann die Behebung.
10. **`app_admins` ohne Oberfläche** (vorgemerkt 12.10.2026). Die Anmeldung
    liest die Tabelle, und es gibt die Route `GET/POST/DELETE /api/admins`,
    aber `frontend/app.js` ruft sie nirgends – ein Weg, den niemand aufruft
    (REIHENREGELN 2), wie der tote Buchungszweig, der in v0.9.84 entfiel.
    **Offene Frage an den Betreiber (Funktionsfrage):** Soll die Verwaltung
    über die Oberfläche gepflegt werden können – dann bekommt die Route
    eine Oberfläche –, oder genügt `admin_kuerzel` in `config.php` – dann
    entfallen Route und Tabelle.
