<!-- VENDORED aus hornse/koordination v1.4.0 – dort ändern, hierher kopieren! -->
# Fallstricke: PHP, Router, WebUntis

Ergänzung zu `REIHENREGELN.md`. **Quelle ist `hornse/koordination`**; die
Kopien in den Projekten sind vendored und werden vom Bestandslauf
gemessen. Änderungen gehören in die Quelle — fällt hier ein Fehler auf,
wird die Kopie nicht geändert, sondern der Befund nach `koordination`
gemeldet.

**Diese Datei führt, wer einen eigenen Router, einen supervisord-Dienst
oder einen WebUntis-Zugang hat.** Ein rein statisches Projekt braucht sie
nicht.

**Kein Projektname und kein projekteigener Wert steht hier.** Sitzungsnamen,
Ports, Datenbanknamen und die WebUntis-Konfiguration stehen in der
`CLAUDE.md` des jeweiligen Projekts. Hier steht nur, was schon einmal
Schaden angerichtet hat — und zwar in mehr als einem Projekt oder auf eine
Weise, die sich wiederholen kann.

---

## 1 — Router und Sitzung

**`session_name()` und `$_SERVER['HTTPS'] = 'on';` stehen vor dem ersten
Sitzungszugriff**, in der Regel ganz oben im Router und vor jedem
`require`. Wer die Sitzung über eine eigene Klasse aufsetzt, hält die
Regel dort ein — maßgeblich ist der Zeitpunkt, nicht die Datei.

PHP liest den Cookie-Namen beim ersten Sitzungszugriff. Kommt
`session_name()` zu spät, legt PHP bei jedem Request eine neue leere
Sitzung an — der Login gibt 200 zurück, der Cookie steht im Browser, die
Sitzungsdatei liegt korrekt auf der Platte, und jeder Folgeaufruf
antwortet trotzdem mit 401. Ohne `HTTPS = 'on'` fehlt zusätzlich das
`Secure`-Flag, weil SSL vor dem PHP-Prozess terminiert wird; solche
Cookies werden auf HTTPS-Seiten stillschweigend verworfen. Die Suche hat
einmal einen halben Tag gekostet.

**In der Router-Datei kann deshalb kein `declare(strict_types=1)`
stehen.** Alle anderen Dateien setzen es.

**`session.save_path` gehört in die globale PHP-Konfiguration**, nicht in
ein `ini_set()` im Projekt. Bei PHP-FPM greift es dort zu spät. Nach der
Änderung den PHP-Dienst neu starten.

**Pfad-Traversal:** Der PHP built-in Server normalisiert `..` nicht.
**Immer** `realpath()` plus Präfixprüfung mit Verzeichnistrenner.

---

## 2 — Dienst

**`stopasgroup` und `killasgroup` in der supervisord-Datei sind Pflicht**,
sobald `PHP_CLI_SERVER_WORKERS` gesetzt ist. Sonst überleben Kindprozesse
den Neustart, halten den Port, und der Dienst steht auf `FATAL`, während
die Seite weiterläuft.

---

## 3 — PHP-Fallen

**Für eine ID, die `0` sein kann, niemals `empty()`.** `empty(0)` ist in
PHP `true`. Ein Benutzer mit der ID 0 galt dadurch als ausgeloggt.
Richtig ist `!isset($x) || $x === null`. Dasselbe im Frontend:
`if (me && me.id)` wirft ihn zurück auf den Login, es muss
`if (me && (me.id || me.id === 0))` heißen.

**`?:` nicht für Voreinstellungen mit Zahlen.** `getenv('X') ?: 0.25`
lässt sich nicht auf null setzen — die Zeichenkette `"0"` ist in PHP
falsch, also greift der Standardwert. Eine Einstellung, die sich nicht
abschalten lässt, ist keine.

**`mb_*`-Funktionen nur mit `function_exists()` aufrufen.** Zweimal ist
eine ganze Testsuite daran abgestürzt. Kürzel und Spaltenüberschriften
sind meist ASCII genug für `strtolower()`.

**JavaScript-Variablen überleben keinen Deploy.** Kritische IDs gehören
in ein verstecktes `<input>` im DOM; die Variable bleibt Rückfall.

**Ein PHP-Fehler kommt als HTML zurück.** Im Browser erscheint dann
`Unexpected token '<'` — eine Meldung, die nichts über die Ursache sagt.
Wer sie sieht, sucht die Ursache im Server-Log, nicht im JavaScript.

---

## 4 — Datenbank

**Ein `UPDATE` darf keine Fremdschlüsselspalte auf `0` setzen.** Bei
fehlendem Wert die Spalte aus dem `UPDATE` weglassen.

**SQLite ist nicht MariaDB.** Welche Datenbank ein Projekt führt, steht
in seiner `CLAUDE.md`; der Unterschied fällt erst beim Einspielen auf.
Die vier Ersetzungen:

| MariaDB | SQLite |
|---|---|
| `AUTO_INCREMENT` | `INTEGER PRIMARY KEY AUTOINCREMENT` |
| `ENGINE=InnoDB` | entfällt |
| `DEFAULT CHARSET=utf8mb4` | entfällt — SQLite speichert immer UTF-8 |
| `NOW()` | `datetime('now')` |
| `DATE_SUB(NOW(), INTERVAL 7 DAY)` | `datetime('now','-7 days')` |

**SQL zweimal einspielen**, um Idempotenz zu belegen. Der statisch
prüfbare Teil davon: `CREATE TABLE` und `ADD COLUMN` immer mit
`IF NOT EXISTS`. **Für `INSERT` gilt das nicht** — dort sind mehrere
Muster legitim (`INSERT IGNORE`, `ON DUPLICATE KEY UPDATE`, ein
`DELETE`-Vorspann in einer Transaktion), und eine Prüfung darüber wäre
löchrig oder falsch-rot.

---

## 5 — WebUntis: die drei Zugänge

| Zugang | Weg | Eigenheit |
|---|---|---|
| **JSON-RPC** | `/WebUntis/jsonrpc.do` | feste Methoden, braucht oft eine Schuljahres-ID |
| **Interne REST** | `/WebUntis/api/rest/view/v1/…` | undokumentiert, JWT plus `X-Webuntis-Api-School-Year-Id` |
| **Struts-Formulare** | `/WebUntis/*.do` | klassische Sitzung plus CSRF-Token |

**Struts ist der einzige Weg zum Schreiben von Kurszuordnungen.**

**Die offizielle Plattform-API (`developer.untis.com`) kann die Zuordnung
nicht schreiben.** Lesend ist sie besser als alles andere.

**Lesende Zugriffe deshalb hinter eine schmale eigene Schicht legen** —
auf beiden Seiten austauschbar bleiben.

**Der JSESSIONID-Cookie aus `authenticate` muss an alle Folgeaufrufe.**
Ohne ihn kam einmal eine leere Antwort zurück und der Login gelang mit
leerem Vor- und Nachnamen — ohne jede Fehlermeldung.

**Die `.do`-Methoden sind nicht einheitlich.** Manche Seiten wollen POST,
manche GET, manche beides für verschiedene Zwecke. **Nachbauen, nicht
übertragen.** Wo eine Seite als Formular gebaut ist, steht die Methode im
HTML — ein Blick auf das `<form>`-Element spart Tage.

**Ein 403 auf einer `.do`-Seite heißt fast nie „keine Rechte".** Die
häufigste Ursache ist ein POST ohne CSRF-Token oder ein POST, wo die
Oberfläche GET schickt. Ein 500 heißt „Pfad da, Anfrage unvollständig",
ein 404 „gibt es nicht". Wenn sich die Rechte nicht geändert haben
können, liegt es nicht an den Rechten.

**Feldnamen aus fremden Formularen gelten nur für den Aufruf, an dem sie
beobachtet wurden.** Derselbe Name kann in zwei Formularen zwei
verschiedene Dinge bezeichnen.

---

## 6 — WebUntis: Eigenheiten

**Das Schuljahr ist nie selbstverständlich.** Zwischen zwei Schuljahren
ist keines aktiv; `currentSchoolYear` ist dann `null`, und jede Methode,
die eines voraussetzt, bricht mit `-8998` ab. Die ID immer ausdrücklich
ermitteln und mitgeben — als Parameter oder als Kopfzeile
`X-Webuntis-Api-School-Year-Id`. **Der Zustand tritt planbar jeden Sommer
auf**, also genau dann, wenn für das kommende Schuljahr eingerichtet
wird.

**Zwei Nummernkreise, die nie verwechselt werden dürfen.** Der
Fremdschlüssel aus dem führenden System und die WebUntis-interne Nummer
sind verschiedene Dinge. Wer sie verwechselt, schreibt ohne
Fehlermeldung den falschen Datensatz. **Über den Namen wird nie
zugeordnet** — im Bestand stehen gleiche und fast gleiche Namen
nebeneinander.

**Listenseiten zeigen eine Seite, nicht den Bestand.** „161 Elemente
gefunden" bei rund 1200 Schülern ist keine Auskunft über den Bestand.
Jede gelesene Liste braucht eine Vollständigkeitsprüfung, bevor gezählt
wird.

**Räume: Kapazität und `canBeBooked` sind zwei Felder.** Es gibt Räume
mit Kapazität, die absichtlich nicht buchbar sind.

**Eine Viertelsekunde Pause zwischen zwei Aufrufen**, einstellbar. Ein
Dutzend Anfragen in wenigen Sekunden hat einmal zu
`Connection reset by peer` geführt, während die Oberfläche im Browser
einwandfrei lief.

**Netzfehler werden gedeutet, nicht durchgereicht.** „Nicht erreichbar"
führt in die falsche Richtung, wenn die Gegenseite die Verbindung
abgebrochen hat — dann sucht man beim Netz, wo nichts zu finden ist.

**Fremde Fehlermeldungen übersetzen, eigene Fehler vorher fangen.**
`-8500 invalid schoolname` kam einmal von einem mitkopierten `#`. Was
sich selbst prüfen lässt, wird vorher geprüft; was nicht, bekommt eine
Übersetzung, die die Ursache nennt.

**Vor eigener Datenhaltung prüfen, ob WebUntis das Feld schon führt.**
Räume, Zeiten, Kapazitäten und Buchungsfristen stehen dort bereits. Jedes
nachgebaute Feld ist eine zweite Wahrheit — und doppelte Pflege war der
Anlass der Reihe, nicht ihr Ergebnis.

---

## 7 — WebUntis: Antworten auswerten

**Am Wesensmerkmal erkennen, nicht am Begleitmerkmal.** Eine Stunde ist
eine Stunde, weil sie ein Fach hat, nicht weil sie eine Zeitangabe trägt.
Feldnamen einer undokumentierten API sind geraten und ändern sich; die
Sache selbst nicht.

**Ein Wesensmerkmal kann außerhalb des Knotens liegen.** Bei einer
Abfrage nach Fach steht das Fach am Tag, nicht an der Stunde. Wo der
Aufbau einer Antwort belegt ist, wird er genutzt; die allgemeine Suche
bleibt Rückfall für unbekannte Formen.

**Personenbezogene Endpunkte gehören nicht hinein.** Die
Lehrkraftabfrage trägt `birthDate`, `gender`, `personnelNumber` und
`varQuotas` (Deputate); die Schülerabfrage Namen und Geschlecht. Für eine
Teilnehmerliste genügt das Kürzel aus dem Stundenplan. Wo ein Schlüssel
gebraucht wird, werden nur `id` und `key` übernommen und alles andere
sofort verworfen.

**Es gibt keinen REST-Pfad für die Schülerliste.** Die Oberfläche ruft
ein Struts-Formular auf. Zwei Vermutungen — der Anmeldeweg und die
Schuljahres-Kopfzeile — haben zwei Tage gekostet, bevor der Mitschnitt es
in Minuten geklärt hat.

---

## 8 — Anmeldung und Rollen

**Die `personType`-Zuordnung ist überall dieselbe:** 2 = Lehrkraft,
5 = Schüler, 16 = WebUntis-Admin. **Wer zugelassen ist, ist es nicht** —
das entscheidet jedes Projekt für sich und hält es in seiner `CLAUDE.md`
fest.

**Ein WebUntis-Admin (`personType 16`) hat `personId = -1`** und taucht
in `getTeachers()` nicht auf, weil Admins keine Stundenplan-Personen
sind. Bei `personId <= 0` wird die Abfrage übersprungen und der Name über
das Kürzel aus der lokalen Datenbank geholt. Die Bedingung als **Bereich**
prüfen, nicht als Gleichheit — dann ist der Fall der ID 0 aus Abschnitt 3
mit abgedeckt.

**Die Ablehnung ist einheitlich, solange ein Passwort im Spiel ist.** Es
darf nicht erkennbar sein, ob ein Kürzel existiert — und ebenso wenig, ob
das Passwort stimmte.

Wo nach erfolgreicher Anmeldung ein **zweiter Schritt** über Freigabe
oder Zuordnung entscheidet, darf sein Fehlschlag nicht anders aussehen
als ein falsches Passwort: gleicher Wortlaut, gleicher Statuscode. Sonst
unterscheidet ein Angreifer „Passwort falsch" von „Passwort richtig, kein
Datensatz", und die Anwendung wird zum Prüfstand für fremde
Zugangsdaten. **Der Grund gehört ins Protokoll, nicht in die Antwort** —
wer nicht hineinkommt, meldet sich ohnehin bei jemandem, und der liest
das Log.

**Eine Rechteprüfung an einer bestehenden Sitzung darf unterscheiden.**
Dort war kein Passwort im Spiel; wer die Sitzung hat, kennt es bereits.
„Nicht angemeldet" gegen „diese Aktion ist der Verwaltung vorbehalten"
ist richtig so.

Die Trennlinie ist also nicht der Statuscode und nicht die Datei, sondern
die Frage: **Entschied in diesem Vorgang ein Passwort über den Zugang?**

**Der Passwortwechsel ist deshalb kein Fall dieser Regel.** Dort wird ein
Passwort geprüft, aber der Aufrufer ist bereits angemeldet und ändert
sein eigenes — die Antwort „altes Passwort ist falsch" verrät nichts über
ein fremdes Konto. Formal betroffen, dem Zweck nach nicht.

**`session_regenerate_id(true)` beim Login.**

**Eine Brute-Force-Bremse gehört dazu, und sie scheitert geschlossen** —
im Fehlerfall wird abgewiesen, nicht durchgelassen.

**Ein Notfall-Zugang darf nur das, wofür er da ist.** Kein Import, kein
Lesepfad, kein Schreibweg.

---

## 9 — Fachdaten

**Von einer KI erzeugte Fachdaten gelten als unbelegt.** Einmal stammten
die Kompetenzrahmen aller Fächer aus einer früheren KI-Sitzung: Die
Struktur sah plausibel aus und war vollständig falsch — der falsche
Lehrplan-Jahrgang, bei einem Fach 227 „Bereiche" mit je genau einer
Kompetenz. Aufgefallen ist es erst beim Abgleich mit dem PDF des
Kernlehrplans, Monate später.

**Lieber die Quelle anfordern als rekonstruieren.**

**Geprüft wird über Zählwerte je Gliederungseinheit**, nicht über
Stichproben allein: Wie viele Einträge hat jede Einheit, und stimmt das
mit der Quelle überein? Eine Zusammenfassung liest sich Wort für Wort
plausibel und verrät sich an der **Verteilung** — zu wenige Einträge je
Einheit. Die 227 Bereiche mit je einer Kompetenz waren an der Zahl `1`
sofort erkennbar und an keiner Stichprobe.

**Der Wortlautvergleich kommt danach**, stichprobenweise. Er entscheidet,
ob die Einträge echte Formulierungen der Quelle sind oder
Nacherzählungen. Wer mit Stichproben anfängt, findet plausible Sätze und
hört auf.

Das ist dieselbe Art Prüfung wie die Prüfungszahl in `REIHENREGELN.md`
Abschnitt 2: eine Zahl, die einen Fehler zeigt, den kein Blick auf den
Inhalt zeigt.

**Zählwerte unterscheiden aber keine Fassungen derselben Quelle.** Ein
Entwurf und die verabschiedete Fassung eines Lehrplans hatten identisch
30 Bereiche, 197 Erwartungen und dieselbe Verteilung je Phase — der
Unterschied lag allein im Wortlaut. Die Zählung trennt
**Zusammenfassung von Vollständigkeit**, nicht **Fassung von Fassung**.
Das sind zwei verschiedene Fehler und brauchen zwei verschiedene
Prüfungen.

**Welche Fassung verarbeitet wurde, entscheidet die Prüfsumme der
Quelldatei**, nicht ihr Dateiname und nicht ihr Inhaltsverzeichnis. Sie
gehört zum Datensatz, nicht in eine Notiz.

**`pdftotext` ohne `-layout` verliert Text.** Bei einer geprüften Datei
16 Prozent, ohne Fehlermeldung; Sätze brechen mitten ab. Das betrifft
jeden Fachimport aus einem PDF. Wer den Umfang der Extraktion nicht
gegen das Original hält, verarbeitet stillschweigend eine gekürzte
Quelle.
