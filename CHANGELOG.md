# Changelog – sprechtag

## v0.9.3 (Juli 2026) – Slot-Ansicht für Lehrkräfte, Scope-Sondierung

### Neu
- **„Meine Termine" ist jetzt eine Zeitraster-Ansicht** – dieselbe optische
  Sprache wie bei den Eltern. Belegte Slots zeigen Zeit + Kind (+ Klasse)
  und lassen sich direkt absagen; freie Slots sind anklickbar und werden
  stellvertretend gebucht. Die frühere Tabelle und das separate
  Buchungsformular entfallen – ein Raster für Übersicht und Notfallbuchung.
  - Erst oben das Kind wählen, dann auf einen freien Slot tippen.
  - `/api/raster` liefert für Lehrkräfte/Admins nun zusätzlich `buchung_id`,
    `kind_name`, `klasse` und `gebucht_von` je belegtem Slot. Für Eltern
    bleibt es bei „frei/belegt" ohne Namen (Datensparsamkeit unverändert).

### Vorbereitung (Absender von Mitteilungen)
- **Sondierung meldet jetzt die JWT-Scopes.** Der Bericht zeigt unter
  `rest_zugang`, ob das angemeldete Konto Mitteilungen senden darf
  (`mg:rw`) oder nur lesen (`mg:r`). Das beantwortet die offene Frage,
  ob Mitteilungen unter dem eigenen Lehrer-Konto versendet werden können
  oder das Dienstkonto nötig bleibt – zu prüfen mit einem **Lehrer-Konto**,
  nicht mit dem Admin (dessen Token trug im Mitschnitt nur `mg:r`).

## v0.9.2 (Juli 2026) – Zeitstempel, phasengerechte Einladungen, Notfallbuchung

### Neu
- **Stellvertretende Buchung durch die Lehrkraft.** Für Eltern, die nicht
  selbst buchen können, trägt die Lehrkraft unter „Meine Termine" einen
  Termin bei sich selbst ein: Kind auswählen, freien Zeitpunkt wählen –
  das Elternkonto wird automatisch ermittelt (dieselbe Logik wie bei der
  Einladung). Der Slot ist danach über den UNIQUE-Key für alle anderen
  gesperrt; alle Erziehungsberechtigten erhalten eine Bestätigung.
  - Neuer Endpunkt `POST /api/buchungen/stellvertretend`.
  - Die Eltern-Ermittlung ist in `mit_eltern_ids_ermitteln()`
    zusammengefasst und wird von Einladung und Notfallbuchung geteilt
    (keine doppelte Logik mehr).
- **Zeitpunkt in der Mitteilungsliste.** Jede Mitteilung zeigt jetzt, wann
  sie versendet (`gesendet_am`) bzw. angelegt (`angelegt_am`) wurde – bisher
  war unklar, von wann ein Eintrag ist. Die Werte lagen bereits in der DB
  und wurden von der API geliefert; es fehlte nur die Anzeige.

### Geändert
- **Einladungsansicht ist phasengerecht.** In Phase 2 (offen für alle)
  weist ein Hinweis darauf hin, dass Einladungen nicht nötig, aber weiter
  möglich sind; in Phase 1 bleibt der bisherige Text. Die Überschrift ist
  von „Einladungen für Phase 1" auf „Einladungen" neutralisiert. Die
  Funktion wird nicht mehr entfernt/eingefügt, sondern passt sich an – so
  ist sie bei Phasenwechsel sofort wieder im passenden Zustand.

### Tests
- `tests/frontend_zeitstempel_test.js` sichert die Zeitstempel-Formatierung.

## v0.9.1 (Juli 2026) – Elternkonten automatisch ermitteln

### Neu
- **Die Zuordnung Kind → Elternkonto ist gelöst.** Über die
  Empfängersuche des WebUntis-Nachrichtenzentrums (belegt durch
  Mitschnitt am 24.07.2026) werden die Erziehungsberechtigten eines
  Kindes gefunden – auch dann, wenn noch niemand gebucht hat. Damit
  funktionieren Einladungen ab dem ersten Sprechtag.
  - Lehrkraft: `GET /v1/messages/recipients/PARENTS/search?searchText=…`
  - Admin: `POST /v2/messages/recipients/CUSTOM/filter`
- Neue Methode `WebUntisRest::empfaengerSuchen()` (probiert beide Wege).
- Neue Funktion `mit_eltern_zu_kind()` wertet die Antwort aus.

### Wichtige Einschränkungen, bewusst behandelt
- **Die Verknüpfung läuft über Namen**, nicht über IDs: WebUntis führt
  unter `tags` die Namen der Kinder auf. Der Abgleich ist deshalb streng
  (normalisiert, aber exakt) – Teiltreffer wie „Paulowski" allein werden
  nicht gewertet, damit keine fremden Konten angeschrieben werden.
- **Mehrere Erziehungsberechtigte pro Kind** sind der Normalfall (im
  Testfall drei für ein Kind). Alle werden benachrichtigt.
- **Konten mit `role: "STUDENT"` werden ausgeschlossen** – eine an die
  Eltern gerichtete Einladung darf nicht beim Kind landen.

### Tests
- `tests/run_elternzuordnung.php` (21 Prüfungen) gegen die **echten**
  Antwortdaten aus dem Mitschnitt: korrekte Zuordnung beider Kinder,
  Ausschluss von Schülerkonten, keine Fehltreffer bei Teilnamen,
  Schreibweisen-Toleranz (Reihenfolge, Komma, Groß-/Kleinschreibung).


## v0.9.0 (Juli 2026) – Einladungen benachrichtigen, Kind statt User-ID

### Behoben
- **Einladungen erzeugten überhaupt keine Benachrichtigung.** Das war
  eine Lücke, kein Fehler im Detail: Der Endpunkt legte nur den
  Datenbankeintrag an. Eingeladene Eltern erfuhren nichts von ihrer
  Einladung – obwohl Phase 1 genau darauf beruht. Jetzt entsteht beim
  Einladen eine Mitteilung, die mit hinterlegtem Dienstkonto **sofort**
  versendet wird. Der Umweg „erst anlegen, dann versenden" entfällt.
- **Die Mitteilungsliste zeigte nur die Eltern-User-ID** („5984"), was
  für Lehrkräfte wertlos ist. Jetzt steht dort das betroffene **Kind mit
  Klasse**. Das ist die fachlich relevantere Angabe und kommt ohne
  zusätzliche personenbezogene Speicherung aus – der Name stammt aus der
  Schülerliste, von Eltern wird weiterhin nur die User-ID gespeichert.
- **Veraltete Anzeige nach Ansichtswechsel**: Gelöschte Einladungen
  erschienen weiter, bis die Seite neu geladen wurde. Beim Wechsel
  werden geladene Listen jetzt verworfen.
- **`sql/06_diagnose.sql` setzte die Spalte `grund` auf TEXT NOT NULL.**
  TEXT-Spalten können in MySQL keinen Standardwert haben, deshalb
  scheiterte **jeder** neue Mitteilungs-Eintrag mit Fehler 1364.
  Korrektur in `sql/09_grund_null.sql`; der Insert setzt den Wert jetzt
  ausdrücklich.

### Bekannte Grenze
Die Benachrichtigung braucht die WebUntis-**User-ID** der Eltern. Diese
kennt das System nur, wenn sich das Elternkonto bereits angemeldet und
gebucht hat – eine Auflösung Kind → Elternkonto bietet die API nach
bisherigem Kenntnisstand nicht. Ist kein Konto bekannt, wird die
Einladung trotzdem angelegt, und die Lehrkraft erhält den ausdrücklichen
Hinweis, die Eltern auf anderem Weg zu informieren. Ob ein REST-Endpunkt
diese Auflösung erlaubt, ist noch zu klären.

### Tests
- `tests/run_einladungstext.php` (10 Prüfungen): Textbaustein der
  Einladung mit und ohne Kindnamen, mit und ohne Freitext.


## v0.8.1 (Juli 2026) – Einladungen prüfen, Rückmeldungen verbessern

### Behoben
- **Einladungen mit ungültiger Schüler-ID wurden kommentarlos angelegt.**
  So entstand eine Einladung für „Schüler-ID 7" – ein Tippfehler, der
  erst beim Buchen aufgefallen wäre. Der Endpunkt prüft jetzt: Existiert
  der Sprechtag? Ist die Schüler-ID plausibel (sofern eine Schülerliste
  gepflegt ist)? Ist dem Konto eine Lehrkraft zugeordnet? Jeder Fall
  liefert eine eigene, verständliche Meldung.
- **Fehler beim Sammel-Einladen wurden verschluckt.** Der Ablauf zählte
  Fehlschläge nur, ohne den Grund zu zeigen (`catch { fehler++ }`).
  Jetzt erscheinen die Meldungen in der Oberfläche.
- **„Aktualisieren" bei den Mitteilungen wirkte folgenlos.** Der Knopf
  lud zwar neu, gab aber keine Rückmeldung – bei bereits versendeten
  Mitteilungen sah es aus, als passiere nichts. Jetzt meldet er den
  Stand, und die Ansicht sagt ausdrücklich, wenn alles versendet ist.
- Veralteter Hinweistext bei den Mitteilungen: Mit hinterlegtem
  Dienstkonto sind keine Zugangsdaten mehr nötig.

### Bestätigt
Der Mitteilungsversand funktioniert: `v2_users_multipart` → HTTP 200.
Der am 24.07.2026 aus der Weboberfläche ermittelte Weg ist damit im
Echtbetrieb belegt.


## v0.8.0 (Juli 2026) – Mitteilungsversand: der echte Weg

### Neu
- **Der Versandweg ist belegt** (Mitschnitt der WebUntis-Weboberfläche
  vom 24.07.2026). Drei Punkte, die vorher falsch geraten wurden:
  - Pfad ist `/WebUntis/api/rest/view/v2/messages/users`, nicht `/v1/messages`
  - Der JSON-Block ist **kein Request-Body**, sondern ein
    **Multipart-Teil** mit `name="request"` und `filename="blob"` –
    deshalb konnte kein einziger reiner JSON-POST funktionieren
  - Empfänger über `recipientUserIds` (user.id)
- Neue Methode `WebUntisRest::postMultipart()`. Der Aufbau wurde gegen
  einen echten HTTP-Server geprüft: PHP erkennt den Teil als Datei
  `request` mit Dateiname `blob` und Typ `application/json`, die Nutzlast
  kommt zeichengleich an (inklusive Umlauten und Zeilenumbrüchen).
- Der belegte Weg steht an erster Stelle der Variantenliste; sechs
  Rückfälle bleiben für abweichende Instanzen erhalten.
- **Scope-Diagnose**: `WebUntisRest::jwtScopes()` liest die Rechte aus
  dem Token. Trägt es nur `mg:r` (nur lesen), weist die Fehlermeldung
  ausdrücklich darauf hin – im Mitschnitt hatte das Admin-Token genau
  diesen Scope, was ein wahrscheinlicher Stolperstein ist.

### Tests
- `tests/run_jwt_scopes.php` (7 Prüfungen): JWT-Auswertung mit dem
  Aufbau aus dem echten Mitschnitt, Erkennung von Lese- und
  Schreibrechten.
- `tests/run_varianten.php` erweitert: belegter Weg an erster Stelle,
  Multipart-Kennzeichnung, alle Pflichtfelder des Mitschnitts.


## v0.7.2 (Juli 2026) – Ehemalige Schüler ausblenden

### Neu
- **Austrittsdatum im CSV-Import** (fünfte Spalte, optional). Liegt es in
  der Vergangenheit, erscheint der Schüler nicht in der Auswahlliste.
  Hintergrund: Der Schild-Export enthält immer alle Schüler, auch längst
  abgegangene – rund 3500 statt der etwa 1000 aktuellen. Ohne Filter wäre
  die Auswahl unbrauchbar.
- Akzeptierte Datumsformate: `31.07.2029` und `2029-07-31`. Fehlt die
  Angabe, gilt der Schüler als aktuell. Planmäßige Schulenden in der
  Zukunft (WebUntis trägt sie oft ein) gelten korrekt als aktiv.
- Der Import meldet zurück, wie viele Einträge als ausgetreten erkannt
  wurden. Gespeichert werden alle; ausgeblendet wird nur in der Auswahl.
- Neue Spalte `schueler.austritt` und Index `idx_aktiv_klasse`
  (`sql/07_austritt.sql`, idempotent).

### Sondierung erweitert
- Die Gruppe „Klassen & Schüler:innen" prüft jetzt zwölf REST-Kandidaten
  darauf, ob einer die Felder liefert, die die WebUntis-Oberfläche in der
  Schülerverwaltung zeigt: **aktiv**, **Austrittsdatum** und
  **Externe Id** (= Schild-ID). `getStudents()` (JSON-RPC) tut das nicht.
- Neue Funktion `sondierung_felder_finden()` meldet je Antwort, welche
  Felder auf diese Kategorien passen – und gibt dabei **nur Feldnamen
  aus, keine Werte**, weil die Antworten Klarnamen enthalten.

### Tests
- `tests/run_austritt.php` (18 Prüfungen): Datumsnormierung, Aktiv-Logik
  am Stichtag, CSV mit gemischten Austrittsdaten.
- `tests/run_felderfindung.php` (13 Prüfungen): Erkennung der Feldnamen
  in verschiedenen Schreibweisen, Nachweis, dass keine Werte im Bericht
  landen.


## v0.7.1 (Juli 2026) – Diagnose des Mitteilungsversands

### Behoben
- **Bei fehlgeschlagenem Versand ging die eigentliche Information
  verloren.** Gespeichert wurde nur „Keine der bekannten Feldstrukturen
  wurde akzeptiert" – die Antworten der einzelnen Varianten, also genau
  das, was man zur Kalibrierung braucht, wurden verworfen. Jetzt steht je
  Variante die Antwort der Instanz im Feld `grund`.
- Spalte `mitteilungen.grund` von VARCHAR(500) auf TEXT erweitert
  (`sql/06_diagnose.sql`, idempotent) – vier bis sieben Fehlermeldungen
  passen sonst nicht hinein.

### Neu
- **Diagnoseansicht** unter „Mitteilungen": Nach einem Versand steht dort
  je Mitteilung und Variante, was WebUntis geantwortet hat, mit Knopf zum
  Kopieren.
- **Drei zusätzliche Feldstrukturen** (jetzt sieben statt vier),
  abgeleitet aus dem Befund, dass `/messages/recipients` einen Long
  erwartet: `recipientIds`, ein `message`-Wrapper und eine vollständigere
  Variante mit den Feldern, die `GET /messages` zeigt.
- `docs/VERSANDWEG_ERMITTELN.md`: Anleitung, wie sich der echte
  Versandweg in fünf Minuten aus den Browser-Entwicklerwerkzeugen ablesen
  lässt. Das ist der verlässlichste Weg – Raten hat bisher nicht
  funktioniert.

### Tests
- `tests/run_varianten.php` (10 Prüfungen): Struktur aller sieben
  Varianten, Empfänger-ID in jedem Body, Unterscheidbarkeit der
  Fehlermeldungen.


## v0.7.0 (Juli 2026) – Schülerliste, Lehrkraft-Auswahl, Versand ohne Zugangsdaten

### Neu
- **Schülerliste für die Einladungsauswahl** (`sql/05_schueler.sql`):
  Lehrkräfte wählen Kinder jetzt über eine nach Klassen sortierte
  Abhakliste statt über die Eingabe einer Schüler-ID. Zwei Quellen, die
  sich ergänzen und über die Schild-ID verknüpft werden: WebUntis
  (`getStudents` – IDs und Namen) und ein CSV-Import aus Schild-NRW
  (Klassen). Details in `docs/SCHUELERLISTE.md`.
- Klassenbezeichnungen werden vereinheitlicht (`06B` und `6b` sind
  dieselbe Klasse), damit WebUntis- und Schild-Daten zusammenpassen.
- Einladungs- und Terminlisten zeigen Namen und Klasse statt Schüler-IDs.
- Die Eingabe einer Schüler-ID bleibt als Rückfall erhalten, falls keine
  Liste eingerichtet ist.

### Behoben
- **Admins sahen in „Meine Termine" immer nur die Termine der Lehrkraft
  aus `admin_kuerzel`** – ohne Hinweis darauf, wessen Termine das sind.
  Das wirkte, als würden Buchungen falsch zugeordnet. Es gibt jetzt eine
  Lehrkraft-Auswahl und eine passende Überschrift. (Die Zuordnung selbst
  war immer korrekt, wie eine Prüfung der Buchungstabelle bestätigt hat.)
- **Lehrkräfte konnten keine Mitteilungen versenden**, der Versand war der
  Administration vorbehalten. Jetzt dürfen Lehrkräfte die Mitteilungen
  versenden, die zu ihren eigenen Terminen gehören; Admins alles.
- **Zugangsdaten mussten für jeden Versand neu eingegeben werden.** Ist ein
  Dienstkonto hinterlegt, entfällt das vollständig – Absagen werden direkt
  beim Auslösen versendet.
- Der Dienstkonto-Status ist für Lehrkräfte lesbar (nur ob eines nutzbar
  ist, nicht welches), damit die Oberfläche weiß, ob sie Zugangsdaten
  abfragen muss.

### Tests
- `tests/run_schueler.php` (27 Prüfungen): Klassennormierung,
  CSV-Parser mit drei Trennzeichen, Kopf- und Kommentarzeilen,
  Umlaute, Windows-Zeilenenden, Fehlermeldungen je Zeile.


## v0.6.0 (Juli 2026) – Klausurtermine als Beleg für die Lehrkraft-Zuordnung

### Neu
- **Einträge vom Typ `EXAM` mit Status `REGULAR` werden mitgewertet.**
  Hintergrund (Sondierung 24.07.2026): In Unter- und Mittelstufe
  beaufsichtigen die Fachlehrkräfte ihre eigenen Klassenarbeiten. Fällt
  der reguläre Unterricht einer Lehrkraft im Referenzzeitraum aus oder
  wird vertreten, ist der Klausurtermin der einzige Beleg dafür, dass sie
  das Kind unterrichtet – ohne ihn fehlt sie in der Buchungsliste.
- **Klausurstunden zählen nicht als Unterrichtsstunden.** Die Sortierung
  richtet sich weiter nach regulärem Unterricht; Hauptfachlehrkräfte
  stehen also weiterhin oben. Lehrkräfte, die nur über einen Klausurtermin
  gefunden wurden, sind in der Auswahl gekennzeichnet.
- **Schalter je Sprechtag** („Klausurtermine bei der Lehrkraftermittlung
  mitwerten", Voreinstellung an). Wo Aufsichten fachfremd verteilt werden,
  lässt sich das abschalten.
- `EXAM / CHANGED` bleibt ausgeschlossen: Bei verlegten Arbeiten steht die
  Lehrkraft nur unter `removed`.
- Neue Spalten `sprechtage.klausuren_werten` und
  `kind_lehrer_cache.klausuren` (`sql/04_klausuren.sql`, idempotent).

### Befunde der Sondierung vom 24.07.2026
Drei Läufe mit wachsendem Referenzzeitraum für dasselbe Kind zeigten,
warum der Zeitraum großzügig gewählt sein sollte:

| Zeitraum | Informatik-Lehrkraft im Plan | Extraktor findet |
|---|---|---|
| 1 Woche | nur Klassenarbeit | Gr, Kl |
| 2 Wochen | Vertretung + Arbeit | Gr, Kl |
| 4 Wochen | regulärer Unterricht dabei | Gr, Kl, **Ew** |

Vertretungen (`NORMAL_TEACHING_PERIOD / CHANGED`) wurden in allen Läufen
korrekt ignoriert – die Vertretungslehrkräfte tauchen nirgends auf.

### Tests
- `tests/run_klausuren.php` (14 Prüfungen): Nachbau der echten
  Instanzdaten, Klausur-Wertung an und aus, Vertretungsausschluss,
  Sortierung, Rückwärtskompatibilität der Extraktor-Signatur.


## v0.5.1 (Juli 2026) – Fehlende Lehrkräfte werden gemeldet

### Behoben
- **Lehrkräfte aus dem Stundenplan, zu denen kein Stammsatz existiert,
  verschwanden stillschweigend.** `wu_kind_lehrer_ermitteln()` übersprang
  sie mit einem kommentarlosen `continue`. Für Eltern sah es so aus, als
  unterrichte die Lehrkraft das Kind gar nicht. Aufgefallen bei einem
  Testschüler, dessen Informatik-Lehrkraft in der Buchungsliste fehlte,
  obwohl die Sondierung sie fand.
- Die Funktion liefert jetzt `['anzahl' => int, 'uebersprungen' => string[]]`.
  Übersprungene Kürzel werden ins Server-Log geschrieben **und** den
  Eltern angezeigt, mit Hinweis auf die Stammdaten-Synchronisierung.

### Neu
- Sondierung: `sondierung_eintragstypen()` listet je Stundenplan-Abfrage
  alle vorkommenden Kombinationen aus `type` und `status` auf, samt der
  daran hängenden Lehrkräfte und der Angabe, ob der Produktivfilter sie
  werten würde. Damit lässt sich belegen statt vermuten, welche
  Eintragstypen eine Instanz verwendet (Klassen- vs. Kursunterricht).
- Der Stundenplan-Bericht zeigt zusätzlich, was der Produktiv-Extraktor
  findet – direkt vergleichbar mit der ungefilterten Sondier-Liste.

### Tests
- `tests/run_stammsatz.php`: bildet den Fehlerfall nach (drei Lehrkräfte
  im Stundenplan, eine ohne Stammsatz) und prüft, dass die fehlende
  gemeldet und nicht verschluckt wird.


## v0.5.0 (Juli 2026) – Dienstkonto und automatische Lehrkraft-Ermittlung

### Neu
- **Verschlüsselte Dienstkonto-Ablage** (`backend/api/dienstkonto.php`):
  Zugangsdaten eines WebUntis-Kontos mit Leserecht auf Schülerstundenpläne
  werden mit libsodium (ersatzweise OpenSSL AES-256-GCM) verschlüsselt in
  `einstellungen` gespeichert. Der Schlüssel steht in `config.php`, also
  nicht in der Datenbank. Verwaltung über Administration → „Dienstkonto".
- **Automatische Ermittlung bei Bedarf**: Öffnen Eltern die Buchungsseite
  und ist der Cache für dieses Kind leer, ermittelt das System die
  Lehrkräfte im Hintergrund mit dem Dienstkonto – einmal je Kind und
  Sprechtag. Schlägt das fehl, bleibt die Ansicht bedienbar.
- `POST /api/lehrer-ermitteln` nutzt das Dienstkonto, wenn keine
  Zugangsdaten übergeben werden.
- Neue API-Routen: `GET/POST/DELETE /api/dienstkonto` (der Status gibt
  niemals das Passwort preis, nur Benutzername und Verfahren).

### Sondierung erweitert
- `getSchoolyears()` und `getCurrentSchoolyear()` im Client ergänzt;
  `getKlassen()` nimmt jetzt optional eine `schoolyearId`.
- Die Stammdaten-Sondierung probiert `getKlassen` bei Fehlschlag erneut
  mit expliziter Schuljahres-ID und prüft anschließend, ob ein
  Klassenstundenplan die Lehrkräfte liefert (Grundlage für die spätere
  Sammelvorbereitung).

### Befunde der Sondierung vom 24.07.2026
- In den Sommerferien ist **kein Schuljahr aktiv**; `getKlassen()` scheitert
  dann mit Fehler -8998. Die Sammelvorbereitung über Klassenstundenpläne
  ist deshalb erst nach Beginn des neuen Schuljahres testbar.
- `getStudents()` liefert 3563 Einträge, aber **keine Klassenzuordnung**
  (nur id, key, name, foreName, longName, gender) – eine Vorauswahl der
  Oberstufe ist damit nicht möglich.
- Kein Gruppenfeld für „SuS über 18" in der API sichtbar.

### Behoben
- `sondierung.php` nutzte `rest_lehrkraefte_aus_entries()`, ohne
  `extractors.php` einzubinden – wäre zur Laufzeit gescheitert.

### Tests
- `tests/run_dienstkonto.php` (12 Prüfungen): Verschlüsselung, Nonce-
  Variation, UTF-8, falscher Schlüssel, manipulierte Chiffre.
- `tests/run_dienstkonto_db.php` (14 Prüfungen): Speichern, Lesen, Status
  ohne Passwortpreisgabe, Schlüsselwechsel, Löschen.


## v0.4.4 (Juli 2026) – Seite blieb nach v0.4.3 komplett leer

### Behoben
- **Kritisch: Nach dem Deploy von v0.4.3 war die Anwendung nicht mehr
  bedienbar** – weiße Seite, kein Login. Beim Umbau der Sondierungsansicht
  in v0.4.3 wurde der Codeblock von `ansichtSondierung` bis
  `ladeMitteilungen` ersetzt und dabei die dazwischenliegende Funktion
  `ansichtMitteilungen` versehentlich mitgelöscht. Da sie in der
  Ansichten-Registrierung weiterhin referenziert wurde, brach das Skript
  beim Start mit einem ReferenceError ab, bevor irgendetwas gezeichnet
  werden konnte. Die Funktion ist wiederhergestellt.

### Tests
- `tests/frontend_vollstaendig_test.js`: statische Prüfung, dass jede in
  `ansichten` registrierte Funktion und jede aufgerufene Hilfs- und
  Ladefunktion tatsächlich definiert ist; prüft außerdem Klammerbilanz
  und Abschluss der IIFE.
- `tests/frontend_laufzeit_test.js`: führt `app.js` in einem minimalen
  DOM-Ersatz aus und stellt sicher, dass `start()` ohne ReferenceError
  durchläuft. Dieser Test hätte den Fehler sofort gefunden.


## v0.4.3 (Juli 2026) – Sondierungsansicht behält Eingaben und Bericht

### Behoben
- **Sondierung meldete „abgeschlossen", zeigte aber keinen Bericht, und
  alle Eingaben verschwanden.** Ursache ist dasselbe Muster wie in v0.4.1,
  nur an anderer Stelle: Der Bericht wurde in eine `<pre>`-Element-Referenz
  geschrieben, die vor dem `meldung('… läuft')`-Aufruf geholt worden war.
  `meldung()` ruft `zeichne()` auf und baut die Ansicht neu auf – die
  Referenz zeigte danach auf ein Element, das nicht mehr im Dokument hing.
  Der Bericht landete im Nichts, die Eingabefelder waren geleert.
- Die Sondierungsansicht hält Benutzername, Zeitraum, Schüler-ID,
  Gruppenauswahl und den Bericht jetzt in `S.sondierung` und stellt sie
  bei jedem Neuzeichnen wieder her. **Das Passwort wird bewusst nicht
  gemerkt** und muss je Lauf neu eingegeben werden.
- Neuer Knopf „Als Markdown kopieren" (der Bericht ist oft zu lang zum
  Markieren) und „Bericht verwerfen".

### Tests
- `tests/frontend_zustand_test.js`: prüft beide Fehlermuster (Eingabe vor
  Meldung sichern, Ergebnis in den Zustand statt ins DOM) und stellt
  sicher, dass kein Passwort im Zustand landet.


## v0.4.2 (Juli 2026) – Sondierung Klassen/Schüler:innen freigeschaltet

### Neu
- Sondiergruppe **„Klassen & Schüler:innen"** im Frontend auswählbar.
  Die zugehörige Backend-Funktion `sondierung_stammdaten()` war bereits
  vorhanden, aber über die Oberfläche nicht erreichbar. Sie prüft
  `getKlassen()` und `getStudents()` sowie vier REST-Kandidaten und
  meldet, ob ein Gruppen-/Kategoriefeld existiert (für „SuS über 18").
- Ausgabe ist datensparsam: nur Feldnamen, Anzahlen und ein
  anonymisiertes Struktur-Beispiel (Namensfelder durch „…" ersetzt).
  Klassennamen werden im Klartext gezeigt, da für die Zuordnung nötig.

### Hinweis zur Konfiguration
`admin_kuerzel` erwartet ein **Lehrkraft-Kürzel** aus der Tabelle `lehrer`
(z. B. `Ho`), nicht den WebUntis-Benutzernamen des Admin-Kontos
(`adminho`). Grund: Das WebUntis-Admin-Konto hat `personId -1` und
erscheint nicht in `getTeachers()`; über das Kürzel wird ihm der
Lehrkraft-Stammsatz zugeordnet, damit „Meine Termine" funktioniert.
Der Feldname wird in einer kommenden Version in `admin_lehrer_kuerzel`
umbenannt (mit Rückwärtskompatibilität).


## v0.4.2 (Juli 2026) – Sondierung der Stammdaten

### Neu
- Sondiergruppe **„Klassen & Schüler:innen"**: prüft `getKlassen`,
  `getStudents` und vier REST-Varianten daraufhin, welche Felder zur
  Verfügung stehen und ob WebUntis eine Gruppenzugehörigkeit
  (z. B. „SuS über 18") mitliefert. Grundlage für die Entscheidung
  zwischen Klassenweg (Sek I) und Einzelermittlung (Oberstufe).
- `WebUntisAuth::getKlassen()` und `::getStudents()` (Modul-Ergänzung,
  gehört nach `hornse/webuntis-client-php` v1.4.0)

### Datenschutz
- Der Sondierungsbericht enthält **keine Klarnamen**: Namensfelder werden
  im Struktur-Beispiel durch „…" ersetzt, erfolgreiche REST-Antworten
  werden nicht abgedruckt (nur Status und oberste Schlüssel).

### Tests
- 11 zusätzliche Prüfungen: Anonymisierung der Namensfelder,
  Sichtbarkeit der IDs, Erkennung möglicher Gruppenfelder

### Bekannte Einschränkung
- `admin_kuerzel` erfüllt zwei Aufgaben gleichzeitig (Admin-Rechte für
  Lehrkräfte **und** Ersatz-Kürzel für personType-16-Konten). Das führt
  dazu, dass ein WebUntis-Admin-Konto auf einen fremden Lehrkraft-
  Stammsatz zeigen kann. Trennung ist für das nächste Paket vorgesehen.


## v0.4.1 (Juli 2026) – Fehlerbehebungen Frontend

### Behoben
- **Stammdaten-Sync, Sondierung und Mitteilungsversand meldeten
  „Feld 'benutzername' fehlt"**: Die drei Aktionen setzten zuerst eine
  Statusmeldung („… läuft") und lasen erst danach die Eingabefelder aus.
  `meldung()` ruft aber `zeichne()` auf, wodurch die Ansicht komplett neu
  aufgebaut wird – die Felder samt eingetippten Werten waren zu diesem
  Zeitpunkt bereits verschwunden, es wurden leere Strings gesendet.
  Jetzt werden Werte grundsätzlich vor jeder Meldung gelesen; zusätzlich
  prüft das Frontend auf leere Eingaben, bevor es den Server fragt.
- **Aufgeklappte Bereiche schlossen sich nach jeder Meldung**: Neue
  Hilfsfunktion `block()` merkt den Zustand jedes `<details>`-Bereichs in
  `S.offeneBloecke` und stellt ihn beim Neuzeichnen wieder her.
- **Versionsnummer war seit Paket 3 auf 0.3.0 stehengeblieben**, obwohl
  die Mitteilungsfunktionen enthalten waren – irreführend beim Prüfen des
  Deployments. Die Nummer steht jetzt in `index.php` und `index.html`.

### Tests
- `tests/frontend_reihenfolge_test.js` (Node): weist nach, dass die alte
  Reihenfolge leere Werte liefert und die neue die eingetippten.

## v0.4.0 (Juli 2026) – Paket 3: Mitteilungen an Erziehungsberechtigte

### Neu
- **Mitteilungs-Warteschlange** (`sql/03_mitteilungen.sql`): Tabelle
  `mitteilungen` mit Status (offen/gesendet/verworfen), Fehlergrund und
  Versuchszähler; Schlüssel-Wert-Tabelle `einstellungen`
- **Automatische Terminbestätigung**: Bei jeder Buchung wird eine
  Bestätigung mit *allen* aktuellen Terminen des Kontos vorgemerkt
  (Zeit, Lehrkraft, Raum). Eine bereits offene Bestätigung wird ersetzt,
  nicht ergänzt – es gilt der aktuelle Gesamtstand.
- **Absage-Benachrichtigung**: Sagt eine Lehrkraft ab, entsteht eine
  Mitteilung mit Zeit, Lehrkraft und optionalem Freitext. Sagen Eltern
  selbst ab, entsteht keine.
- **Versand mit Variantenprobe**: Da der POST-Weg der WebUntis-API
  undokumentiert ist, werden vier plausible Feldstrukturen nacheinander
  probiert; die erfolgreiche wird gemerkt und künftig zuerst genutzt.
  401/403 brechen sofort ab (Rechteproblem), 400/404/405/500 führen zur
  nächsten Variante.
- **Fallback ohne Datenverlust**: Schlägt der Versand fehl, bleibt die
  Mitteilung mit Fehlermeldung stehen und kann manuell versendet werden.
  Buchung bzw. Absage sind davon unabhängig und immer korrekt.
- **Ansicht „Mitteilungen"** für Lehrkräfte und Administration: Liste mit
  Anlass, Betreff, Empfänger-ID, Status und Fehlergrund; Versand und
  Verwerfen für die Administration
- **API**: `GET /api/mitteilungen`, `POST /api/mitteilungen`,
  `POST /api/mitteilungen/senden`, `DELETE /api/mitteilungen/{id}`
- **Modul-Erweiterung** `WebUntisRest::post()` für schreibende Zugriffe
  (gehört nach `hornse/webuntis-client-php` v1.3.0)

### Behoben
- `mb_substr()` wurde ohne Prüfung genutzt; fehlte die mbstring-Erweiterung,
  scheiterte die Bestätigung stillschweigend. Neue Hilfsfunktion `kuerze()`
  in `backend/helfer.php` mit Fallback, der auch keine kaputten
  UTF-8-Sequenzen erzeugt
- Archivieren löscht jetzt auch die Mitteilungen (enthalten Lehrkraftnamen)

### Tests
- `tests/run_mitteilungen.php`: 39 Prüfungen (Antwortbewertung inkl.
  endgültig/weiterprobieren, Variantenstruktur, Textbausteine, Datumsformat)
- `tests/run_warteschlange.php`: 13 Prüfungen gegen SQLite (Einreihen,
  Fehlschlag ohne Datenverlust, kuerze-Fallback)

### Offen
- Der **Versandweg selbst ist noch nicht gegen die echte Instanz erprobt**.
  Vorgehen für den ersten Versuch: `docs/MITTEILUNGEN.md`

## v0.3.0 (Juli 2026) – Paket 2: Datenmodell, Adapter, Buchung, Adminseite

### Neu
- **Datenmodell** (`sql/02_sprechtag.sql`, idempotent): Sprechtage mit allen
  Adminparametern, teilnehmende Lehrkräfte mit Anwesenheitsfenster und Raum,
  Sonderlehrkräfte mit Rollen und optionaler Jahrgangsbindung, Buchungen
  (UNIQUE-Constraint gegen Doppelbuchung), Einladungen für Phase 1,
  Kind-Lehrkraft-Cache, App-Administratoren, sieben Standard-Sonderrollen
- **WebUntis-Adapter** (`webuntis_adapter.php`): Login mit Rollenerkennung
  (personType 2/5/12/16), Kinder aus `app/data`, Lehrkraft-Ermittlung je Kind
  über den Referenzzeitraum mit DB-Cache, Stammdaten-Sync (Lehrkräfte, Räume)
- **Buchungslogik** als reine Funktionen (`slots.php`): Zeitraster mit
  Slotlänge, automatischen Pausen und Teilzeitfenstern, Buchungs- und
  Stornoregeln, Raumkonflikte, Jahrgangsfilter für Sonderlehrkräfte
- **Adminseite**: Sprechtage anlegen/ändern/kopieren/archivieren, Lehrkräfte
  mit Zeitfenster und Raum (Doppelbelegung rot markiert), Sonderlehrkräfte,
  Stammdaten-Sync, Phasensteuerung
- **Eltern-Ansicht**: Kind wählen, Lehrkräfte nach Wochenstunden sortiert
  (Hauptfächer zuerst) mit Fach, Raum und Anwesenheitszeit, Zeitraster mit
  freien/belegten/eigenen Slots, persönliche Terminübersicht
- **Lehrkraft-Ansicht**: eigene Termine, Einladungen für Phase 1,
  stellvertretende Buchung, Absage mit Nachrichtentext (Versand folgt)
- **Modul-Erweiterung** für `hornse/webuntis-client-php` v1.2.0:
  `rest_lehrkraefte_aus_entries()` (Lehrkräfte je Schüler, mit denselben
  Filtern wie `rest_unterricht_aus_entries`: nur TEACHING-Einträge, nur
  REGULAR/CANCELLED, nur `current`) und `rest_konto_aus_appdata()`

### Datenschutz
- Von Eltern wird ausschließlich die WebUntis-`user.id` gespeichert, kein
  Name und keine E-Mail-Adresse; Namen leben nur in der Sitzung
- Eltern sehen nur eigene Buchungen; belegte Slots zeigen keine Fremddaten
- Archivieren löscht Buchungen, Einladungen und Kind-Lehrkraft-Zuordnung,
  behält aber die wiederverwendbare Struktur

### Behoben
- DB-Verbindungsfehler führten zu einem nackten 500er; jetzt sauberes JSON
  mit Status 503, Details im Server-Log
- Guards standen teilweise nach `db()`; nicht angemeldete Zugriffe liefern
  jetzt zuverlässig 401 statt 503 bei DB-Ausfall
- `lastInsertId()` wurde nach Folge-Statements gelesen und lieferte 0;
  Buchungs-ID wird jetzt sofort gesichert

### Tests
- `tests/run_slots.php`: 68 Offline-Prüfungen (Raster inkl. Pausen und
  Teilzeit, Buchungs- und Stornoregeln je Rolle und Phase, Raumkonflikte,
  Jahrgangsfilter, Extraktoren inkl. Vertretungs-/Aufsichts-Ausschluss,
  app/data-Auswertung, Referenzzeitraum)
- `tests/run.php`: 23 Prüfungen der Sondierungslogik (unverändert grün)
- End-to-End gegen MariaDB: Rollen, Phase-1-Ablauf, Doppelbuchung,
  Datenschutzgrenzen, Kopieren, Archivierung

## v0.2.0 (Juli 2026) – Sondierungsbefunde eingearbeitet

Auswertung der Läufe vom 19.07.2026 (Eltern-, Lehrkraft-, Admin-Konto):
personType 12 = LEGAL_GUARDIAN, Kind-Zuordnung über `app/data` →
`user.students[]` bestätigt, Mitteilungs-Endpunkte erreichbar,
Sprechtag-Kandidaten durchweg 404 (Details in `docs/SONDIERUNG.md`).

- **Bugfix Schüler-ID-Erkennung**: nahm bisher die erste Kind-Struktur und
  damit `user.person` (die Person des Kontos selbst) statt `user.students`.
  Neue reine Funktion `sondierung_schueler_ids()` filtert auf
  Schüler-/Kind-Pfade, verwirft `-1` und liefert mehrere IDs.
- Stundenplan wird jetzt **je Kind einzeln** sondiert (statt einer Probe mit
  Platzhalter); CLASS-Probe nur noch bei vorhandener klasseId.
- Neu `sondierung_lehrer_aus_entries()`: extrahiert aus einer 200-Antwort
  alle `current.type == "TEACHER"`-Elemente (defensiv-rekursiv, keine
  Positionsannahmen) → Anzeige „🧑‍🏫 Lehrkräfte im Zeitraum".
- Neue Eingabefelder **Zeitraum von/bis** (Validierung JJJJ-MM-TT in API und
  Frontend) – nötig, weil Proben in den Ferien leere Pläne liefern.
- `app/data` wird immer intern abgerufen (liefert IDs für alle Gruppen),
  erscheint im Bericht aber nur bei Gruppe „Basis"; Bericht enthält jetzt
  zusätzlich `user_id` und `schueler_ids_gewaehlt`.
- Mitteilungs-Gruppe um Parameterprobe `recipients={USER_ID}` erweitert
  (Befund: Endpunkt erwartet User-ID vom Typ Long).
- personType 12 im Rollen-Mapping und in `allowed_person_types` der
  Konfigurationsvorlage; Vorlage außerdem auf Platzhalter umgestellt
  (öffentliches Repository).
- Testsuite auf 23 Prüfungen erweitert (Schüler-ID-Auswahl inkl.
  Bug-Regression, Lehrer-Extraktion, Kandidaten-Struktur).

## v0.1.0 (Juli 2026) – Paket 1: Projektgerüst + Sondierwerkzeug

- Projektgerüst nach Vorlage `hornse/schulprojekt-template`:
  router.php (HTTPS-Flag + session_name ganz oben), bootstrap.php,
  API-Router, SPA-Frontend (HTML ohne JS, app.js, style.css),
  deploy.sh, idempotentes Schema `01_schema.sql` (login_log)
- WebUntis-Clients vendored aus `hornse/webuntis-client-php`
  (WebUntisAuth JSON-RPC, WebUntisRest intern; Ergänzung:
  `setzeTimeout()` für kurze Sondierproben – ins Modul-Repo übernehmen)
- Sondierwerkzeug (`POST /api/sondierung`, nur lesend):
  - authenticate-Befund inkl. personType-Auswertung (Eltern-Konto!)
  - REST-Zugang (JWT), app/data mit automatischer Suche nach
    Kind-/Personen-Strukturen (Kandidaten für die Eltern→Kind-Zuordnung)
  - Kandidaten-Proben: Sprechtag-Endpunkte (Modul lizenziert),
    Stundenplan je STUDENT/CLASS, Mitteilungs-Endpunkte (nur GET,
    405 wird als Positiv-Befund gewertet)
  - freie Zusatzpfade + manuelle Schüler-ID („Erweitert")
  - Bericht im UI mit Ampel-Markierung und Markdown-Export
- Schutz: Brute-Force-Bremse über login_log (mit Ersatz-Wartezeit ohne
  DB), Abschaltbarkeit per `sondierung_freigeschaltet` in config.php,
  Proben-Timeout 8 s (Uberspace-Proxy-Limit), ignore_user_abort
- Offline-Testsuite `tests/run.php` (Strukturanalyse, Kürzung,
  Kandidaten-Platzhalter)
