# Befund: Welche Adresse sieht die Anwendung hinter dem Proxy von Uberspace? (14.10.2026)

Für Befund 5 der Review aus `koordination` (Anmeldebremse) und deren offene
Frage O2. Gemessen mit der befristeten Route `POST /api/messung/herkunft`
(v0.9.106), die nur Vergleiche und gesalzene Prüfsummen ausgibt.
**Keine Adresse steht in dieser Datei.**

## Vorher (Betreiber, Datenbank)

Die 91 Einträge in `login_log` tragen eine einzige IPv4-Adresse – nicht
intern, nicht die des Servers, nicht die heutige des Rechners, von dem fast
alle Anmeldungen kamen. Proxy oder gewechselte Adresse: nicht zu trennen.

## Messung 1 – 14.10.2026, 21:47, `sprechtag` v0.9.106

Eigene Adresse vorher festgehalten aus zwei unabhängigen Quellen
(`api.ipify.org`, `icanhazip.com`, beide über IPv4; einig). Dann über IPv4
eine absichtlich falsche Anmeldung mit erfundenem Benutzernamen
(`messung-herkunft-…`, Antwort 401), dann der Vergleich:

| | Ergebnis |
|---|---|
| `REMOTE_ADDR` gleich eigener Adresse | **nein** |
| `REMOTE_ADDR` intern | nein |
| `X-Forwarded-For` vorhanden | ja, **1 Eintrag** |
| … dieser Eintrag gleich eigener Adresse | **ja** |
| `X-Real-IP` | nicht vorhanden |
| `login_log.ip` gleich eigener Adresse | **nein** |
| `login_log.ip` gleich `REMOTE_ADDR` | **ja** |
| weitere Kopfzeilen (nur Namen) | `X-Forwarded-For-Anon`, `X-Forwarded-Port`, `X-Forwarded-Proto` |

## Messung 2 – dieselbe Minute, mit gefälschten Kopfzeilen

Mitgeschickt: `X-Forwarded-For: 192.0.2.1` und `X-Real-IP: 192.0.2.1`
(Dokumentationsbereich, RFC 5737).

| | Ergebnis |
|---|---|
| `X-Forwarded-For` | **2 Einträge** |
| … erster gleich eigener Adresse | nein (der mitgeschickte) |
| … **letzter** gleich eigener Adresse | **ja** |
| `X-Real-IP` | vorhanden, **nicht** gleich eigener Adresse |
| `login_log.ip` gleich `REMOTE_ADDR` | ja |

## Was daraus folgt (am Messergebnis, für diese Instanz)

- **`REMOTE_ADDR` ist der Proxy.** Alle Einträge in `login_log.ip` tragen
  dieselbe Adresse – die Spalte ist heute ohne Aussage über das Gerät, und
  eine Bremse „je Herkunft“ auf `REMOTE_ADDR` sperrte alle zugleich.
- **Der Proxy HÄNGT AN.** Vertrauenswürdig ist nur der **letzte** Eintrag
  von `X-Forwarded-For`; alle davor kann der Aufrufer frei setzen. Das
  bestätigt die Begründung in `signage/backend/netz.php` – dort war es
  begründet, hier ist es gemessen.
- **`X-Real-IP` ist nicht zu gebrauchen:** Der Proxy setzt es nicht und
  reicht ein mitgeschicktes unverändert durch.
- **Nicht gemessen:** was `X-Forwarded-For-Anon` enthält; ob es über IPv6
  ebenso ist; ob es nach einem Umbau bei Uberspace so bleibt. Eine
  Anwendung, die sich auf den letzten Eintrag stützt, sollte prüfen, dass
  `REMOTE_ADDR` der erwartete Proxy ist (oder zumindest nicht das Gerät).

## Für die Reihe

Jedes Projekt auf Uberspace, das `REMOTE_ADDR` speichert oder danach
bremst, sieht den Proxy. Kandidat für `FALLSTRICKE.md` (Abschnitt Dienst
oder Anmeldung).
