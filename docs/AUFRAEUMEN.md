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
   **Erledigt mit v0.9.96 (E27):** gefangen wird `PDOException`, der
   Rückfall steht in `variante_lesefehler` und im Grund.
10. **`app_admins` ohne Oberfläche** (vorgemerkt 12.10.2026). Die Anmeldung
    liest die Tabelle, und es gibt die Route `GET/POST/DELETE /api/admins`,
    aber `frontend/app.js` ruft sie nirgends – ein Weg, den niemand aufruft
    (REIHENREGELN 2), wie der tote Buchungszweig, der in v0.9.84 entfiel.
    **Offene Frage an den Betreiber (Funktionsfrage):** Soll die Verwaltung
    über die Oberfläche gepflegt werden können – dann bekommt die Route
    eine Oberfläche –, oder genügt `admin_kuerzel` in `config.php` – dann
    entfallen Route und Tabelle.
    **Entschieden 12.10.2026 (E29), anders als gefragt:** `admin_kuerzel`
    wird der Notzugang, gepflegt wird über die Oberfläche. Eigener Zug in
    drei Teilen. **Teil 1 (Backend) erledigt mit v0.9.98;** Teil 2
    (Oberfläche) und Teil 3 (Dokumentation) offen.
    **Teil 2 (Oberfläche) erledigt mit v0.9.99.** Teil 3 offen.
    **Teil 3 (Dokumentation) erledigt mit v0.9.100. Punkt 10 erledigt.**
11. **`run_elternzuordnung`: der Rollenfilter wird nie gefragt** (vorgemerkt
    12.10.2026). Die Prüfung „KEINE Schüler-Konten“ bleibt grün, auch wenn
    man in `mit_eltern_zu_kind()` den Rollenfilter entfernt: Die
    Schülerkonten in den Testdaten haben leere `tags` und können gar nicht
    zugeordnet werden. Es fehlt ein Schülerkonto mit passendem Tag. Gefunden
    durch einen Eingriff ohne rote Zeile beim Wirkungsnachweis für v0.9.94.
    **Erstes Beispiel zu Art 6** („Die Testdaten stellen den Fall nicht
    her“, `docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md`) – wer den
    Punkt behebt, hat zugleich ein Vorbild für die übrigen. Prüfung zuerst:
    den Eingriff (Rollenfilter fort) gegen die ergänzten Testdaten rot
    zeigen.
12. **Zweite Rückgabe des Sammelversands ohne `variante_lesefehler`**
    (vorgemerkt 12.10.2026, aus E27). `backend/api/index.php` baut im
    Sammelversand für die leere ID-Liste eine eigene Rückgabe in der Form
    von `mit_versand_ausfuehren()`, und ihr fehlt das Feld, das v0.9.96
    eingeführt hat. Eine Rückgabe derselben Form, der ein Feld fehlt,
    erscheint später jemandem als Fehler. Abhilfe ohne zweite Wahrheit:
    die Funktion auch für die leere Liste rufen, die ohnehin früh
    zurückkehrt, statt die Form nachzubauen.
13. **Absage: der Name der Lehrkraft steht zweimal, geprüft wird das
    Vorkommen** (vorgemerkt 12.10.2026, gefunden beim Wirkungsnachweis für
    v0.9.97). **Art 1** („zweites Vorkommen“,
    `docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md`). `mit_text_absage()`
    nennt die Lehrkraft in „… Uhr bei <Name>“ und im Gruß;
    `run_mitteilungen` prüft nur `str_contains`. Entfernt man den Namen an
    einer Stelle, bleibt die Prüfung grün. Suchfrage: Zählt die Prüfung die
    Vorkommen, oder sucht sie eines?
14. **Drei Suiten prüfen eine Nachbildung** (vorgemerkt 12.10.2026, gefunden
    beim Wirkungsnachweis für v0.9.97). **Art 5** („geprüft wird eine
    Kopie“). `run_stammsatz.php` baut `wu_kind_lehrer_ermitteln` im Test
    nach; `frontend_reihenfolge_test.js` und `frontend_zustand_test.js`
    spielen die Reihenfolge Lesen/Zeichnen mit eigenen Funktionen nach und
    laden `app.js` nicht. Kein Eingriff am Code macht sie rot. Suchfrage:
    Prüft die Suite den Code oder eine Kopie davon?
15. **`ladeStammdaten()` schluckt Ladefehler** (vorgemerkt 12.10.2026,
    gefunden beim Bau der Seite „Administratoren“, v0.9.99). `frontend/app.js`:
    `try { S.stammdaten = await api('/api/stammdaten'); } catch { }`.
    Scheitert der Abruf, bleibt die leere Liste stehen, und die Seite sagt
    „Keine Lehrkräfte in den Stammdaten“ statt „konnte nicht geladen
    werden“. **Dieselbe Art wie E9 und Punkt 9:** ein Fehlschlag, der wie
    ein gültiges Ergebnis aussieht. Hier besonders unangenehm, weil jemand
    daraufhin den Stammdaten-Sync anwirft, statt den Fehler zu suchen.
    Fünf Aufrufstellen. Älter als E29, deshalb nicht nebenbei geändert.
    Prüfung zuerst: einen scheiternden Abruf rot zeigen.
16. **Nach dem Entfernen hinkt die Anzeige** (vorgemerkt 12.10.2026,
    gefunden beim Schreiben der Abnahmepunkte für v0.9.99; Betreiber:
    nicht dringend). Wer als Administrator entfernt wird, während er
    angemeldet ist, verliert die Rechte sofort (E29, Nachprüfung je
    Aufruf). Die Leiste zeigt „Administration“ aber bis zum Neuladen
    weiter, weil die Oberfläche die Rolle nur beim Laden liest; jede
    Verwaltungsaktion antwortet „nur für die Sprechtags-Administration“.
    Kein stiller Fehler, aber eine Meldung ohne Grund. **Zusatz des
    Betreibers für die Behebung:** Die Meldung sagt, WARUM – dass die
    Rechte entzogen wurden und ein Neuladen die Anzeige richtigstellt.
    „Nur für die Sprechtags-Administration“ lässt jemanden rätseln, der
    eben noch Administrator war.
17. **Zwei Bedingungen für „gehört zu meinen Terminen“** (vorgemerkt
    14.10.2026, gefunden bei der Behebung der Review-Befunde 2 und 3,
    E31). Liste, Verwerfen und Versand fragen `mit_gehoert_bedingung()`
    (eigene Lehrkraft, oder bei Mitteilungen vor v0.9.72 eine eigene
    Buchung). `GET /api/mitteilungen/offen-eigene` – der Hinweis nach der
    Anmeldung – fragt nur `m.lehrer_id`, und `run_sitzung_versand.php`
    verlangt das („genau die eine, Anzahl 1“). Eine alte Mitteilung der
    Lehrkraft fehlt also im Hinweis, steht aber in ihrer Liste. Folge heute
    vermutlich keine (Mitteilungen ohne Lehrkraft sind älter als v0.9.72;
    der Hinweis zählt nur Sprechtage ab heute) – **nicht gemessen**.
    Entscheidung: angleichen, oder die engere Fassung begründen.
    **Erledigt mit v0.9.103 (E31, zweiter Nachtrag):** angeglichen; für die
    engere Fassung fand sich kein eigener Grund.
18. **Beim Neuladen blitzt kurz die Anmeldung auf** (vorgemerkt 14.10.2026,
    Betreiber; nicht dringend). Die Oberfläche zeichnet die Anmeldeseite,
    bevor `/api/auth/me` geantwortet hat. Keine Folge der Cookie-Behebung
    (v0.9.101): Die Oberfläche liest den Cookie nirgends. Antwort wäre ein
    neutraler Ladezustand bis zur Antwort.
