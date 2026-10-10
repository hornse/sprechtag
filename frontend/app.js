// ============================================================
// app.js – gesamtes Frontend-JavaScript
// Kein JS im HTML (Uberspace-Proxy: 63-KB-HTML-Limit).
//
// Ansichten je Rolle:
//   eltern/schueler – Kind wählen, Lehrkraft wählen, Slot buchen,
//                     eigene Termine als Übersicht
//   lehrkraft       – eigene Termine, Einladungen (Phase 1),
//                     stellvertretend buchen, stornieren
//   admin           – Sprechtage, Parameter, Lehrkräfte/Räume,
//                     Sonderlehrkräfte, Stammdaten-Sync, Sondierung
// ============================================================
'use strict';

(function () {

const $ = (s) => document.querySelector(s);

const S = {
  user: null,
  ansicht: 'login',
  sprechtage: [],
  aktiverSprechtag: null,
  stammdaten: { lehrer: [], raeume: [], sonderrollen: [] },
  kind: null,
  lehrerListe: null,
  gewaehlteLehrkraft: null,
  raster: [],
  // null = nicht geladen. [] hieße „keine Termine“ – mit [] als Startwert
  // stand nach Anmeldung oder Neuladen „noch keine gebucht“, obwohl es
  // Termine gab, denn geladen wird nur bei null (v0.9.60).
  meineBuchungen: null,
  einladungen: null,
  einlLaedt: false,                  // Auto-Load-Guard Einladungen
  mitteilungen: null,
  mittLaedt: false,                  // Auto-Load-Guard Mitteilungen
  sitzungsKasten: null, // abgelaufene WebUntis-Anmeldung am Ort der Handlung (E17)
  offenHinweis: null,   // {anzahl, ids}: eigene, noch nicht verschickte Mitteilungen
  benutzername: '',     // nur zum Vorausfüllen des Kastens, nie gespeichert
  marke: null,         // Branding: Schulname, Titel, Logo (keine Farben, E7)
  adminOffen: false,   // Admin-Gruppe in der Seitenleiste aufgeklappt?
  lehrerSort: null,    // Sortierung der Lehrer-Tabelle {feld, richtung}
  anzeigeEinst: null,  // Signage-Einstellungen (Sortierung)
  weitereSuche: '',    // Suchtext für weitere Lehrkräfte (Gruppe 3, E10)
  lehrerLaedt: false,  // Auto-Load-Guard für die Lehrkraft-Liste
  sgDaten: null,       // Verwaltung: zugelassene Gruppen volljähriger Schüler (E15)
  sgLaedt: false,      // Guard
  sgFehler: null,      // Ladefehler – dann KEIN erneuter Abruf von selbst
  kalenderLink: null,  // persönlicher iCal-Abo-Link (Eltern)
  lehrerKalenderLink: null,  // persönlicher iCal-Abo-Link (Lehrkraft)
  loginLogConf: undefined,   // Einstellungen des Login-Protokolls (undefined = ungeladen)
  loginLogLaedt: false,      // Guard: Einstellungen werden geladen
  loginLogListe: null,       // geladene Protokoll-Einträge
  loginLogListeLaedt: false, // Guard: Liste wird geladen
  loginLogFilter: '',        // Benutzername-Filter
  hilfeZusatz: undefined,     // gerendertes Hilfe-Zusatz-HTML (undefined = ungeladen)
  hilfeZusatzLaedt: false,    // Guard
  buchungHinweis: undefined,  // gerendertes Buchungs-Hinweis-HTML
  buchungHinweisLaedt: false,
  loginHinweis: undefined,    // gerendertes Login-Hinweis-HTML
  loginHinweisLaedt: false,
  texte: null,                // Editor-Cache je Textschlüssel {roh, html}
  texteLaedt: {},             // Guards je Textschlüssel
  erinnerungConf: undefined,  // Erinnerungs-Einstellungen (undefined = ungeladen)
  erinnerungLaedt: false,     // Guard
  erinnerungVorschau: null,   // Ergebnis der Empfänger-Prüfung
  gewaehlteLehrkraftAnsicht: null,   // Admin: wessen Termine werden gezeigt
  einlTreffer: null,                 // Kind-Suche der Einladung: null | {laeuft} | {fehler} | {kinder, anzahl, grenze}
  einlSuche: '',                     // Suchbegriff der Einladungsauswahl
  svRaster: null,                    // Zeitraster der Lehrkraft (frei + belegt)
  svLehrer: null,                    // halbtags + Fenster der gezeigten Lehrkraft
  svSprechtag: null,                 // beginn/ende für die Hälften-Berechnung
  svLaedt: false,                    // Auto-Load läuft gerade (verhindert Doppelladen)
  svFehler: null,                    // Fehlermeldung, falls Auto-Load scheiterte
  svKind: null,                      // gewähltes Kind für stellvertretende Buchung
  svKindName: '',                    // Anzeigename des gewählten Kindes
  svKindSuche: '',                   // Suchbegriff im Kind-Suchfeld
  svTreffer: null,                   // Kind-Suche stellvertretend: wie einlTreffer
  svLaeuft: false,                   // Buchung im Gange (Doppelklick-Schutz)
  versandProtokoll: null,
  meldung: null,
  offeneBloecke: {},   // merkt aufgeklappte <details> über Neuzeichnen hinweg
  sondierung: {        // Eingaben und Ergebnis überleben das Neuzeichnen
    benutzer: '', von: '', bis: '', schueler: '',
    gruppen: ['basis', 'stammdaten'], bericht: null,
  },
};

// ---------- API-Helfer ----------------------------------------------------
async function api(pfad, optionen = {}) {
  const antwort = await fetch(pfad, {
    method: optionen.method || 'GET',
    headers: optionen.body ? { 'Content-Type': 'application/json' } : {},
    body: optionen.body ? JSON.stringify(optionen.body) : undefined,
  });
  const daten = await antwort.json().catch(() => ({}));
  if (!antwort.ok) {
    const f = new Error(daten.fehler || ('Fehler ' + antwort.status));
    // Ursache, wenn die WebUntis-Sitzung fehlt (E17): abgelaufen /
    // nicht_erreichbar / kaputt – die Aufrufstelle zeigt danach den Kasten.
    f.sitzung = daten.sitzung || null;
    throw f;
  }
  return daten;
}

function meldung(text, art = 'info') {
  S.meldung = text ? { text, art } : null;
  zeichne();
}

// Kurzmeldung OHNE Neuzeichnen: aktualisiert nur das Toast-Element. Wichtig
// für Aktionen in Tabellen (z. B. das „½"-Häkchen), bei denen ein volles
// zeichne() den gerade geöffneten Detailbereich zuklappen würde.
let toastTimer = null;
function toast(text, art = 'info') {
  const t = $('#toast');
  if (!t) return;
  // Fehler sofort ansagen (assertive), sonst höflich einreihen (polite).
  t.setAttribute('aria-live', art === 'fehler' ? 'assertive' : 'polite');
  t.textContent = text;
  t.className = 'toast ' + art;
  if (toastTimer) clearTimeout(toastTimer);
  // Fehler etwas länger stehen lassen – sie müssen gelesen werden.
  const dauer = art === 'fehler' ? 6000 : 3500;
  toastTimer = setTimeout(() => { t.className = 'toast versteckt'; }, dauer);
}

// ---------- kleine DOM-Helfer --------------------------------------------
function el(tag, klasse, text) {
  const e = document.createElement(tag);
  if (klasse) e.className = klasse;
  if (text != null) e.textContent = text;
  return e;
}
/**
 * Erzeugt ein Symbol aus dem Sprite von ci-css.
 *
 * Ersetzt die früheren Emoji: Die sahen je nach Betriebssystem
 * unterschiedlich aus, ließen sich nicht einfärben und waren in der
 * eingeklappten Leiste unterschiedlich groß.
 *
 * aria-hidden, weil der Knopf daneben bereits title und aria-label
 * trägt – sonst würde jeder Punkt doppelt vorgelesen.
 */
function symbol(name) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'nv-icon');
  svg.setAttribute('aria-hidden', 'true');
  const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
  use.setAttribute('href', '#ci-i-' + name);
  svg.appendChild(use);
  return svg;
}

/**
 * Holt den Symbolsatz einmalig und hängt ihn an den Anfang der Seite.
 *
 * Warum nicht direkt <use href="datei.svg#id">? Das funktioniert über
 * HTTPS zwar, erzeugt aber je Symbol einen eigenen Abruf. Einmal
 * einbetten und lokal referenzieren ist sparsamer.
 *
 * Schlägt der Abruf fehl, läuft alles Übrige weiter – eine Navigation
 * ohne Symbole ist immer noch bedienbar, denn jeder Punkt trägt seinen
 * Text.
 */
function symboleEinbetten() {
  const pfad = $('.shell')?.dataset.ciIcons;
  if (!pfad || document.getElementById('ci-icons-sprite')) return;

  fetch(pfad, { cache: 'force-cache' })
    .then((a) => { if (!a.ok) throw new Error('HTTP ' + a.status); return a.text(); })
    .then((text) => {
      const behaelter = document.createElement('div');
      behaelter.id = 'ci-icons-sprite';
      behaelter.setAttribute('aria-hidden', 'true');
      behaelter.style.display = 'none';
      behaelter.innerHTML = text;
      document.body.insertBefore(behaelter, document.body.firstChild);
    })
    .catch((f) => console.warn('[sprechtag] Symbole nicht ladbar:', f.message));
}

/**
 * Erzeugt einen <details>-Block, dessen Auf-/Zugeklappt-Zustand ein
 * Neuzeichnen der Ansicht übersteht (meldung() ruft zeichne() auf).
 */
function block(kennung, titel) {
  const d = el('details', 'block');
  d.appendChild(el('summary', null, titel));
  if (S.offeneBloecke[kennung]) d.open = true;
  d.addEventListener('toggle', () => { S.offeneBloecke[kennung] = d.open; });
  return d;
}

/**
 * Flache Sektionskarte (kein Auf-/Zuklappen). Titel als <h3>, optional
 * ein grauer Beschreibungstext darunter. Ersetzt block() überall dort,
 * wo alles sofort sichtbar sein soll (Admin-Unterseiten).
 */
/**
 * Rahmen um eine Tabelle, der waagrecht rollt (Zug 3b). Eine <table> selbst
 * kann nicht rollen; ohne Rahmen schob die breiteste Tabelle auf dem
 * Telefon die ganze Seite über den Rand. Jede Tabelle geht hier durch –
 * die Prüfung zählt Tabellen gegen Rahmen.
 */
function tabelleRahmen(tab) {
  const r = el('div', 'tabelle-rahmen');
  r.appendChild(tab);
  return r;
}

/**
 * Tabelle, die auf schmalem Bildschirm zu Karten wird (v0.9.60, Entscheidung
 * Betreiber): je Zeile ein Block, die Überschrift jeder Spalte als
 * Beschriftung der Zelle (data-label; das CSS setzt sie davor). Die Spalte
 * ohne Überschrift trägt die Knöpfe und wird zur Fußzeile der Karte.
 * Umgeschaltet wird allein im CSS, an der Grenze der Telefonansicht –
 * dieselbe Tabelle, eine Darstellung je Breite. Die Rollen erhalten die
 * Tabellenbedeutung für Bildschirmleser, wenn display sie aufhebt (Safari).
 * Der rollende Rahmen bleibt darum herum.
 */
function kartenTabelle(tab) {
  tab.classList.add('karten');
  tab.setAttribute('role', 'table');
  const zeilen = [...tab.querySelectorAll('tr')];
  const kopf = zeilen[0];
  const titel = [...kopf.children].map((th) => th.textContent);
  kopf.classList.add('kopfzeile');
  for (const tr of zeilen) {
    tr.setAttribute('role', 'row');
    if (tr === kopf) {
      [...tr.children].forEach((th) => th.setAttribute('role', 'columnheader'));
      continue;
    }
    [...tr.children].forEach((td, i) => {
      td.setAttribute('role', 'cell');
      if (titel[i]) td.setAttribute('data-label', titel[i]);
      else td.classList.add('karte-aktion');
    });
  }
  return tabelleRahmen(tab);
}

function sektion(titel, beschreibung) {
  const s = el('section', 'sektion');
  s.appendChild(el('h3', 'sektion-titel', titel));
  if (beschreibung) s.appendChild(el('p', 'hinweis', beschreibung));
  return s;
}

function knopf(text, klasse, aktion) {
  const b = el('button', klasse, text);
  b.type = 'button';
  b.addEventListener('click', aktion);
  return b;
}

// Kompakter Icon-Button für Tabellen-Aktionen. `variante` steuert die
// Farbe (z. B. 'speichern', 'ausfall'); `titel` wird als Tooltip gesetzt.
function iconKnopf(symbol, variante, titel, aktion) {
  const b = el('button', 'icon-knopf ' + (variante || ''), symbol);
  b.type = 'button';
  b.title = titel || '';
  b.setAttribute('aria-label', titel || symbol);
  b.addEventListener('click', aktion);
  return b;
}

function feld(label, id, typ = 'text', wert = '') {
  const l = el('label', null, label);
  const i = document.createElement('input');
  i.type = typ; i.id = id; i.value = wert ?? '';
  l.appendChild(i);
  return l;
}
function auswahl(label, id, optionen, wert) {
  const l = el('label', null, label);
  const s = document.createElement('select');
  s.id = id;
  for (const o of optionen) {
    const opt = document.createElement('option');
    opt.value = o.wert; opt.textContent = o.text;
    if (String(o.wert) === String(wert)) opt.selected = true;
    s.appendChild(opt);
  }
  l.appendChild(s);
  return l;
}
function wert(id) {
  const e = $('#' + id);
  // Nur echte Formularelemente haben .value. Sollte eine ID versehentlich auf
  // ein anderes Element zeigen, liefern wir '' statt einen Fehler zu werfen
  // (der sonst die ganze Speichern-Funktion stillschweigend abbräche).
  return (e && typeof e.value === 'string') ? e.value.trim() : '';
}

// Baut einen Kontakt-Hinweis aus dem Branding-Wert marke_kontakt. Ist kein
// Kontakt hinterlegt, wird ein neutraler Satz ohne schulspezifische Angabe
// verwendet – so bleibt das Tool an jeder Schule sinnvoll.
function kontaktSatz(einleitung) {
  const k = (S.marke && S.marke.marke_kontakt) ? String(S.marke.marke_kontakt).trim() : '';
  if (k) return einleitung + ' ' + k + '.';
  return einleitung + ' die Schule.';
}

// Formatiert einen DB-Zeitstempel ("YYYY-MM-DD HH:MM:SS") als
// "TT.MM. HH:MM". Leere/ungültige Werte ergeben einen Gedankenstrich.
function zeitstempel(iso) {
  if (!iso) return '–';
  const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
  if (!m) return String(iso);
  return m[3] + '.' + m[2] + '. ' + m[4] + ':' + m[5];
}

// ---------- Start ---------------------------------------------------------
async function start() {
  // Symbole holen, bevor gezeichnet wird. Ohne await: Ein langsamer
  // Abruf soll die Anmeldung nicht aufhalten – die Symbole erscheinen
  // dann eben eine Zehntelsekunde später.
  symboleEinbetten();
  tastaturBedienung();
  versionAnzeigen();

  // Marke zuerst laden und anwenden (öffentlich, auch vor dem Login) –
  // so erscheint die Seite gleich im richtigen Gewand.
  try {
    S.marke = await api('/api/einstellungen');
    wendeMarkeAn(S.marke);
  } catch { }

  // Anzeige-Modus (Signage): feste öffentliche URL /anzeige. Kein Login,
  // keine Seitenleiste – reine Vollbild-Anzeige mit Auto-Aktualisierung.
  if (window.location.pathname.replace(/\/+$/, '') === '/anzeige') {
    starteAnzeige();
    return;
  }

  try {
    const me = await api('/api/auth/me');
    S.user = me.angemeldet ? me : null;
  } catch { S.user = null; }

  if (S.user) {
    await ladeSprechtage();
    ladeOffenHinweis();
    const standard = S.user.rolle === 'admin' ? 'admin-aktiv'
      : S.user.rolle === 'lehrkraft' ? 'lehrkraft' : 'buchen';
    // Beim Neuladen die Ansicht aus dem URL-Hash wiederherstellen, sofern sie
    // gültig und für die Rolle sinnvoll ist – sonst die Standardansicht.
    // View-Hashes tragen ein „/"-Präfix (#/meine); Sprungmarken (#hilfe-faq)
    // werden hier ignoriert.
    const ausHash = (location.hash || '').replace(/^#\/?/, '');
    const istView = (location.hash || '').startsWith('#/');
    S.ansicht = (istView && ANSICHT_KEYS.includes(ausHash)
      && ansichtErlaubt(ausHash, S.user.rolle)) ? ausHash : standard;
    setzeHash(S.ansicht);
    // Auf Vor/Zurück und manuelle Hash-Änderungen reagieren.
    window.addEventListener('hashchange', () => {
      if (hashIntern) return;
      if (!(location.hash || '').startsWith('#/')) return;   // Sprungmarke: ignorieren
      const z = (location.hash || '').replace(/^#\//, '');
      if (z && ANSICHT_KEYS.includes(z) && ansichtErlaubt(z, S.user.rolle)
          && z !== S.ansicht) {
        ansichtZuruecksetzen();
        S.ansicht = z; S.meldung = null; zeichne();
      }
    });
  } else {
    S.ansicht = 'login';
  }
  zeichne();
}

// Grobe Rollenprüfung, welche Ansichten sinnvoll sind (Feinschutz macht die API).
function ansichtErlaubt(ansicht, rolle) {
  if (ansicht === 'hilfe') return true;
  if (rolle === 'admin') return true;   // Admin darf alles sehen
  if (rolle === 'lehrkraft') {
    return ['lehrkraft', 'buchen', 'meine', 'einladungen', 'mitteilungen'].includes(ansicht);
  }
  // Eltern / volljährige Schüler
  return ['buchen', 'meine'].includes(ansicht);
}

// ==========================================================================
// GENERISCHE SIGNAGE-ENGINE (projektunabhängig, wiederverwendbar)
// --------------------------------------------------------------------------
// Diese Funktion weiß NICHTS über Sprechtage. Sie liefert die Mechanik eines
// Info-Monitors: Vollbild-Kopf mit Logo/Titel, automatisch gemessene
// Kachelmenge, seitenweises Umblättern, Auto-Refresh, flackerfreies Neurendern.
// Für ein anderes Projekt kopiert man diesen Block unverändert und schreibt
// nur einen neuen Adapter (siehe starteAnzeige unten), der `cfg` befüllt.
//
// cfg = {
//   titel:        String  – große Überschrift
//   holen:        async () => object   – lädt die Anzeigedaten
//   istAktiv:     (daten) => bool       – gibt es etwas anzuzeigen?
//   untertitel:   (daten) => String     – Zeile unter dem Titel
//   posten:       (daten) => Array      – die Kachel-Datensätze
//   kachel:       (post)  => HTMLElement – baut EINE Kachel
//   proSeite:     (daten) => 'auto'|Number – Kacheln je Seite
//   intervall:    (daten) => Number      – Sekunden je Seite
//   leerText:     (daten) => String      – Text, wenn nichts aktiv/leer
// }
// ==========================================================================
function signageStart(cfg) {
  document.body.classList.add('anzeige-modus');
  document.body.textContent = '';
  const flaeche = el('div', 'anzeige');
  document.body.appendChild(flaeche);

  let daten = null, seite = 0, proSeite = 24, blaetterTimer = null;

  // Feste Bausteine – EINMAL erzeugt (kein Flackern beim Umblättern).
  const kopf = el('div', 'anzeige-kopf');
  const logoEck = el('div', 'anzeige-logo-eck');
  if (S.marke && S.marke.hat_logo) {
    const logo = document.createElement('img');
    logo.className = 'anzeige-logo';
    logo.src = '/api/einstellungen/logo?v=' + (S.marke.logo_version || '0');
    logo.alt = '';
    logoEck.appendChild(logo);
  }
  kopf.appendChild(logoEck);
  const kopfText = el('div', 'anzeige-kopf-text');
  kopfText.appendChild(el('div', 'anzeige-titel', cfg.titel || 'Info'));
  const untertitel = el('div', 'anzeige-untertitel');
  kopfText.appendChild(untertitel);
  kopf.appendChild(kopfText);
  flaeche.appendChild(kopf);

  const gitterBox = el('div', 'anzeige-gitter-box');
  flaeche.appendChild(gitterBox);

  const fuss = el('div', 'anzeige-fuss');
  const fussSeite = el('span', 'anzeige-seite');
  const fussStand = el('span', null, '');
  fuss.appendChild(fussSeite);
  fuss.appendChild(fussStand);
  flaeche.appendChild(fuss);

  // Misst, wie viele Kacheln in die verfügbare Höhe passen.
  const messeProSeite = (posten) => {
    const probe = el('div', 'anzeige-gitter');
    const muster = cfg.kachel(posten[0]);
    probe.appendChild(muster);
    gitterBox.textContent = '';
    gitterBox.appendChild(probe);
    const stil = getComputedStyle(probe);
    const spalten = stil.gridTemplateColumns.split(' ').filter(Boolean).length || 1;
    const kachelH = muster.offsetHeight || 1;
    const luecke = parseFloat(stil.rowGap) || 0;
    const reihen = Math.max(1, Math.floor((gitterBox.clientHeight + luecke) / (kachelH + luecke)));
    gitterBox.textContent = '';
    return Math.max(1, spalten * reihen);
  };

  const zustandLeer = (text) => {
    gitterBox.textContent = '';
    gitterBox.appendChild(el('div', 'anzeige-leer', text));
    fussSeite.textContent = '';
  };

  const posten = () => (daten ? (cfg.posten(daten) || []) : []);

  const render = (messen) => {
    untertitel.textContent = daten ? (cfg.untertitel(daten) || '') : '';

    if (!daten || !cfg.istAktiv(daten)) { zustandLeer(cfg.leerText(daten)); return; }
    const alle = posten();
    if (alle.length === 0) { zustandLeer(cfg.leerText(daten)); return; }

    const wahl = cfg.proSeite(daten);
    if (messen && (!wahl || wahl === 'auto')) proSeite = messeProSeite(alle);
    else if (wahl && wahl !== 'auto') proSeite = Math.max(1, parseInt(wahl, 10) || 24);

    const seiten = Math.max(1, Math.ceil(alle.length / proSeite));
    if (seite >= seiten) seite = 0;
    const teil = alle.slice(seite * proSeite, seite * proSeite + proSeite);

    const gitter = el('div', 'anzeige-gitter');
    for (const p of teil) gitter.appendChild(cfg.kachel(p));
    gitterBox.textContent = '';
    gitterBox.appendChild(gitter);

    fussSeite.textContent = seiten > 1 ? ('Seite ' + (seite + 1) + ' / ' + seiten) : '';
    fussStand.textContent = 'Stand ' + new Date().toLocaleTimeString('de-DE',
      { hour: '2-digit', minute: '2-digit' }) + ' Uhr';
  };

  const planeBlaettern = () => {
    if (blaetterTimer) clearInterval(blaetterTimer);
    const sek = (daten ? cfg.intervall(daten) : 10) || 10;
    blaetterTimer = setInterval(() => {
      const alle = posten();
      const seiten = Math.max(1, Math.ceil(alle.length / proSeite));
      if (seiten <= 1) return;
      seite = (seite + 1) % seiten;
      render(false);
    }, sek * 1000);
  };

  const datenHolen = async () => {
    try { daten = await cfg.holen(); } catch { return; }
    seite = 0;
    render(true);
    planeBlaettern();
  };

  let resizeTimer = null;
  window.addEventListener('resize', () => {
    if (resizeTimer) clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => { seite = 0; render(true); }, 400);
  });

  datenHolen();
  setInterval(datenHolen, 60000);
}

// ==========================================================================
// SPRECHTAG-ADAPTER für die generische Signage-Engine
// --------------------------------------------------------------------------
// Nur DIESER Teil ist sprechtag-spezifisch: Datenquelle /api/anzeige und wie
// eine Kachel (Raum, Kürzel, Name, Anwesenheitszeit) gefüllt wird.
// ==========================================================================
function starteAnzeige() {
  signageStart({
    titel: (S.marke && S.marke.marke_titel) || 'Sprechtag',
    holen: () => api('/api/anzeige'),
    istAktiv: (d) => !!d.aktiv,
    untertitel: (d) => (d.aktiv && d.sprechtag)
      ? (d.sprechtag.name + ' · ' + d.sprechtag.datum + ' · '
         + d.sprechtag.beginn + '–' + d.sprechtag.ende + ' Uhr')
      : '',
    posten: (d) => d.lehrer || [],
    proSeite: (d) => d.kacheln,
    intervall: (d) => d.intervall,
    leerText: (d) => (!d || !d.aktiv)
      ? 'Zurzeit findet kein Sprechtag statt.'
      : 'Die Raumaufteilung wird noch vorbereitet.',
    kachel: (l) => {
      const kachel = el('div', 'anzeige-kachel');
      kachel.appendChild(el('div', 'anzeige-raum', l.raum_kuerzel || 'Raum offen'));
      kachel.appendChild(el('div', 'anzeige-kuerzel', l.kuerzel));
      if (l.name) kachel.appendChild(el('div', 'anzeige-name', l.name));
      kachel.appendChild(el('div', 'anzeige-zeit', anzeigeZeit(l)));
      return kachel;
    },
  });
}

// Formuliert die Anwesenheitszeit einer Kachel in Klartext.
function anzeigeZeit(l) {
  const von = String(l.anwesend_von || '').slice(0, 5);
  const bis = String(l.anwesend_bis || '').slice(0, 5);
  if (!von && !bis) return 'ganztägig';
  if (von && bis) return von + '–' + bis + ' Uhr';
  if (von) return 'ab ' + von + ' Uhr';
  return 'bis ' + bis + ' Uhr';
}

// Wendet die Marke auf Kopf, Titel und Fußzeile an.
//
// Die Akzentfarbe kommt seit v0.9.47 NICHT mehr von hier, sondern aus
// ci-css über <html data-projekt="sprechtag">. Grund: Die Farbe
// kennzeichnet die ANWENDUNG, damit man bei mehreren offenen Tabs
// sieht, wo man ist. Trüge man über das Branding in allen Projekten
// dieselbe Hausfarbe ein, sähen sie wieder gleich aus. Das Branding
// steuert weiterhin Logo, Schulname und Titel.
function wendeMarkeAn(m) {
  if (!m) return;

  const titel = $('#marke-titel');
  if (titel && m.marke_titel) titel.textContent = m.marke_titel;
  const unter = $('#marke-untertitel');
  if (unter && m.marke_untertitel) unter.textContent = m.marke_untertitel;
  const fuss = $('#marke-fusszeile');
  if (fuss && m.marke_fusszeile) fuss.textContent = m.marke_fusszeile;
  if (m.marke_titel) document.title = m.marke_titel;

  const logo = $('#marke-logo');
  if (logo) {
    // Kein Alt-Text: Das Logo bleibt dekorativ (alt="" im HTML), denn der
    // Schulname steht direkt daneben als Text – sonst läse ein Screenreader
    // ihn zweimal (docs/ENTSCHEIDUNGEN.md, E6).
    if (m.hat_logo) {
      // Stabile URL mit Versionskennung: nur bei echtem Logo-Wechsel neu laden.
      logo.src = '/api/einstellungen/logo?v=' + (m.logo_version || '0');
      logo.classList.remove('versteckt');
    } else {
      logo.classList.add('versteckt');
    }
  }
}

async function ladeSprechtage() {
  try {
    const d = await api('/api/sprechtage');
    S.sprechtage = d.sprechtage || [];
    if (!S.aktiverSprechtag && S.sprechtage.length) {
      const offen = S.sprechtage.find((s) => s.phase === 'phase1' || s.phase === 'phase2');
      S.aktiverSprechtag = offen || S.sprechtage[0];
    }
  } catch { S.sprechtage = []; }
}

async function ladeStammdaten() {
  try { S.stammdaten = await api('/api/stammdaten'); } catch { }
}

async function abmelden() {
  await api('/api/auth/logout', { method: 'POST' });
  // Neu laden statt neu zeichnen: Sonst lebte der Zustand des Kontos
  // weiter (Termine, Kind, persönlicher Kalender-Link), und wer sich danach
  // im selben Browser anmeldete, sah ihn (v0.9.60). Ohne Hash, damit die
  // Seite auf der Anmeldung beginnt.
  location.replace(location.pathname);
}

// ============================================================
// ABGELAUFENE WEBUNTIS-ANMELDUNG (v0.9.72, E17)
// ============================================================
// Es gibt kein Dienstkonto mehr, das eine abgelaufene Sitzung still
// überbrückt. Was die Person auslöst, läuft unter ihrem Namen – und ist die
// Anmeldung abgelaufen, sagt die Oberfläche es am Ort der Handlung: Die
// Mitteilung ist gespeichert, aber noch nicht verschickt. Neu anmelden,
// dann geht es mit demselben Klick weiter.

// Zeigt den Kasten in der aktuellen Ansicht. auftrag: {text, knopf, aktion}
// – aktion läuft nach der Neuanmeldung (z. B. die gespeicherten senden).
function zeigeSitzungsKasten(auftrag) {
  S.sitzungsKasten = Object.assign({ ansicht: S.ansicht }, auftrag);
  S.meldung = null;
  zeichne();
  const k = document.querySelector('.sitzung-kasten');
  if (k && k.scrollIntoView) k.scrollIntoView({ block: 'center' });
  const pw = document.getElementById('sk-passwort');
  if (pw) pw.focus();
}

// Wertet die Ursache aus, wenn die Sitzung fehlte. Gibt true zurück, wenn
// etwas gezeigt wurde. Nur „abgelaufen“ bekommt den Kasten – bei „nicht
// erreichbar“ oder „kaputt“ hilft eine Neuanmeldung nicht, das sagt die
// Meldung selbst.
function sitzungAuswerten(sitzung, meldetext, auftrag) {
  if (!sitzung) return false;
  if (sitzung === 'abgelaufen') {
    zeigeSitzungsKasten(Object.assign({ text: meldetext }, auftrag));
  } else {
    meldung(meldetext, 'fehler');
  }
  return true;
}

function sitzungsKastenElement(k) {
  const box = el('div', 'meldung fehler sitzung-kasten');
  box.setAttribute('role', 'alert');
  box.appendChild(el('p', null, k.text));
  const form = document.createElement('form');
  form.appendChild(feld('WebUntis-Benutzername', 'sk-benutzer', 'text', S.benutzername || ''));
  form.appendChild(feld('Passwort', 'sk-passwort', 'password'));
  const aktionen = el('div', 'aktionen');
  const los = el('button', null, k.knopf || 'Anmelden');
  los.type = 'submit';
  aktionen.appendChild(los);
  aktionen.appendChild(knopf('Später', 'klein', () => {
    S.sitzungsKasten = null;
    // Was „Später“ bedeutet, sagt der Auftrag (v0.9.76): Eine Mitteilung
    // bleibt gespeichert, eine Buchung oder Einladung ist dagegen gar nicht
    // erst entstanden.
    meldung(k.spaeter || ('Nicht verschickt. Die Mitteilung bleibt gespeichert und '
      + 'erscheint nach der nächsten Anmeldung als Hinweis.'), 'info');
  }));
  form.appendChild(aktionen);
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const benutzername = wert('sk-benutzer');
    const passwort = wert('sk-passwort');
    if (benutzername === '' || passwort === '') {
      meldung('Bitte Benutzername und Passwort eingeben.', 'fehler');
      return;
    }
    los.disabled = true;
    try {
      S.user = await api('/api/auth/login', { method: 'POST',
        body: { benutzername, passwort } });
      S.benutzername = benutzername;
    } catch (f) {
      los.disabled = false;
      meldung(String(f.message), 'fehler');
      return;
    }
    const aktion = k.aktion;
    S.sitzungsKasten = null;
    if (aktion) await aktion();
    else meldung('Neu angemeldet.', 'ok');
  });
  box.appendChild(form);
  return box;
}

// Sendet gespeicherte Mitteilungen über die eigene Sitzung. Ist sie
// abgelaufen, kommt der Kasten – und nach der Anmeldung derselbe Aufruf.
async function sendeVorgemerkte(ids, text) {
  try {
    const d = await api('/api/mitteilungen/senden', { method: 'POST',
      body: { sprechtag_id: S.aktiverSprechtag ? S.aktiverSprechtag.id : 0, ids } });
    if (sitzungAuswerten(d.sitzung, (text || 'Noch NICHT verschickt: ') + d.grund,
      { knopf: 'Anmelden und senden', aktion: () => sendeVorgemerkte(ids, text) })) return;
    S.mitteilungen = null; S.mittLaedt = false;
    meldung(d.grund, d.gesendet > 0 && d.fehler === 0 ? 'ok' : 'fehler');
  } catch (f) {
    meldung(String(f.message), 'fehler');
  }
  ladeOffenHinweis();
}

// Nach jeder Anmeldung: Gibt es eigene, noch nicht verschickte Mitteilungen?
async function ladeOffenHinweis() {
  if (!S.user || !['lehrkraft', 'admin'].includes(S.user.rolle)) {
    S.offenHinweis = null;
    return;
  }
  try {
    const d = await api('/api/mitteilungen/offen-eigene');
    const vorher = S.offenHinweis ? S.offenHinweis.anzahl : 0;
    S.offenHinweis = d.anzahl > 0 ? d : null;
    if (vorher !== (d.anzahl || 0)) zeichne();
  } catch { S.offenHinweis = null; }
}

// Warnung für das echte WebUntis-Admin-Konto (v0.9.80, E23): Es behält die
// Verwaltung als Notzugang, darf aber nicht an Eltern senden (PARENTS, 403).
// Die Entscheidung bleibt bei der Person vor dem Bildschirm.
function adminKontoWarnungElement() {
  const box = el('div', 'meldung fehler admin-konto-warnung');
  box.setAttribute('role', 'alert');
  box.appendChild(el('p', null,
    'Sie sind mit dem WebUntis-Administrationskonto angemeldet. Damit gehen '
    + 'Nachrichten an Eltern nicht hinaus: Absagen, Ausfälle, Einladungen und '
    + 'Bestätigungen. WebUntis erlaubt diesem Konto die Empfängerart „Eltern“ '
    + 'nicht. Die Aktionen selbst werden ausgeführt, die Nachrichten bleiben '
    + 'unter „Mitteilungen“ offen stehen.'));
  box.appendChild(el('p', null,
    'Für die Verwaltung bitte mit einem Lehrerkonto anmelden, dessen Kürzel '
    + 'in admin_kuerzel steht.'));
  return box;
}

function offenHinweisElement() {
  const h = S.offenHinweis;
  const box = el('div', 'meldung info offen-hinweis');
  box.setAttribute('role', 'status');
  box.appendChild(el('p', null, h.anzahl === 1
    ? '1 Mitteilung ist gespeichert, aber noch nicht verschickt.'
    : h.anzahl + ' Mitteilungen sind gespeichert, aber noch nicht verschickt.'));
  const aktionen = el('div', 'aktionen');
  aktionen.appendChild(knopf('Jetzt senden', null, () => sendeVorgemerkte(h.ids)));
  aktionen.appendChild(knopf('Ansehen', 'klein', () => wechsleAnsicht('mitteilungen')));
  box.appendChild(aktionen);
  return box;
}

// Mobiles Menü: Hamburger öffnet, Overlay schließt.
$('#mobil-menue')?.addEventListener('click', () => {
  const offen = $('#seitenleiste')?.classList.contains('offen');
  if (offen) menueSchliessen(); else menueOeffnen();
});
$('#menue-overlay')?.addEventListener('click', () => menueSchliessen());

// Seitenleiste ein-/ausklappen (Desktop/Tablet, spart horizontalen Platz).
// Zustand wird gemerkt, damit die Wahl ein Neuladen überlebt.
function leisteEinklappen(zu) {
  const shell = document.querySelector('.shell');
  if (!shell) return;
  shell.classList.toggle('leiste-zu', zu);
  const knopf = document.querySelector('#leiste-einklappen');
  if (knopf) {
    knopf.textContent = zu ? '»' : '«';
    knopf.title = zu ? 'Menü ausklappen' : 'Menü einklappen';
  }
  try { localStorage.setItem('leiste_zu', zu ? '1' : '0'); } catch (e) { /* egal */ }
}
$('#leiste-einklappen')?.addEventListener('click', () => {
  const shell = document.querySelector('.shell');
  leisteEinklappen(!(shell && shell.classList.contains('leiste-zu')));
});
$('#leiste-ausklappen')?.addEventListener('click', () => leisteEinklappen(false));
// Gemerkten Zustand beim Start anwenden.
try { if (localStorage.getItem('leiste_zu') === '1') leisteEinklappen(true); }
catch (e) { /* egal */ }

// ============================================================
// Zeichnen
// ============================================================
function zeichne() {
  // Benutzer-Box in der Seitenleiste
  const box = $('#benutzer-box');
  if (S.user) {
    box.classList.remove('versteckt');
    $('#benutzer-name').textContent = S.user.name || '';
    $('#benutzer-rolle').textContent = {
      admin: 'Administration', lehrkraft: 'Lehrkraft',
      eltern: 'Erziehungsberechtigt', schueler: 'Schüler:in',
    }[S.user.rolle] || S.user.rolle;
  } else {
    box.classList.add('versteckt');
  }

  zeichneNavigation();
  menueSchliessen();   // mobiles Menü nach jedem Wechsel zu

  const ziel = $('#ansicht');
  ziel.textContent = '';

  if (S.meldung) {
    const m = el('div', 'meldung ' + S.meldung.art, S.meldung.text);
    m.setAttribute('role', 'alert');
    ziel.appendChild(m);
  }
  // Echtes Admin-Konto (E23): in jeder Ansicht, solange angemeldet.
  if (S.user && S.user.admin_konto && S.ansicht !== 'login') {
    ziel.appendChild(adminKontoWarnungElement());
  }
  // Abgelaufene Anmeldung (E17): bleibt stehen, bis sie erledigt ist.
  if (S.sitzungsKasten && S.sitzungsKasten.ansicht === S.ansicht) {
    ziel.appendChild(sitzungsKastenElement(S.sitzungsKasten));
  } else if (S.offenHinweis && S.user && S.ansicht !== 'login'
             && S.ansicht !== 'mitteilungen') {
    // Nicht in „Mitteilungen“: Dort steht dasselbe als eigener Abschnitt –
    // eine Darstellung, nicht zwei.
    ziel.appendChild(offenHinweisElement());
  }

  const ansichten = {
    login: ansichtLogin,
    buchen: ansichtBuchen,
    meine: ansichtMeineTermine,
    lehrkraft: ansichtLehrkraft,
    einladungen: ansichtEinladungen,
    admin: ansichtAdminMarke,          // Erscheinungsbild
    'admin-marke': ansichtAdminMarke,
    'admin-aktiv': ansichtAdminAktiv,
    'admin-anzeige': ansichtAdminAnzeige,
    'admin-sprechtage': ansichtAdminSprechtage,
    'admin-daten': ansichtAdminDaten,
    'admin-loginlog': ansichtAdminLoginLog,
    'admin-texte': ansichtAdminTexte,
    'admin-erinnerungen': ansichtAdminErinnerungen,
    mitteilungen: ansichtMitteilungen,
    sondierung: ansichtSondierung,
    hilfe: ansichtHilfe,
  };
  (ansichten[S.ansicht] || ansichtLogin)(ziel);
}

// Verwirft geladene Listen beim Ansichtswechsel, damit keine veralteten
// Daten stehen bleiben (z. B. gelöschte Einladungen).
function ansichtZuruecksetzen() {
  S.einladungen = null; S.einlLaedt = false;
  S.mitteilungen = null; S.mittLaedt = false;
  S.meineBuchungen = null; S.meineLaedt = false; S.lehrerListe = null;
  S.raster = []; S.svRaster = null; S.svLaedt = false;
  S.svFehler = null; S.gewaehlteLehrkraft = null;
  S.loginLogConf = undefined; S.loginLogListe = null;
  S.loginLogLaedt = false; S.loginLogListeLaedt = false;
  S.texte = null; S.texteLaedt = {};
  S.erinnerungConf = undefined; S.erinnerungLaedt = false; S.erinnerungVorschau = null;
  S.sgDaten = null; S.sgLaedt = false; S.sgFehler = null;
}

// Gültige Ansichts-Schlüssel (für die URL-Hash-Wiederherstellung).
const ANSICHT_KEYS = ['buchen', 'meine', 'lehrkraft', 'einladungen', 'admin',
  'admin-marke', 'admin-aktiv', 'admin-anzeige', 'admin-sprechtage',
  'admin-daten', 'admin-loginlog', 'admin-texte', 'admin-erinnerungen', 'mitteilungen', 'sondierung', 'hilfe'];

function wechsleAnsicht(ziel) {
  if (S.ansicht !== ziel) ansichtZuruecksetzen();
  S.ansicht = ziel;
  S.meldung = null;
  // Aktuelle Ansicht im URL-Hash festhalten, damit ein Neuladen (F5) auf
  // derselben Seite bleibt. setzeHash unterdrückt den hashchange-Handler.
  setzeHash(ziel);
  zeichne();
}

// Setzt den Hash, ohne den hashchange-Handler erneut auszulösen.
// View-Hashes bekommen ein „/"-Präfix (z. B. #/meine), damit sie sich nie mit
// seiteninternen Sprungmarken (z. B. #hilfe-faq) überschneiden.
let hashIntern = false;
function setzeHash(ziel) {
  const neu = '#/' + ziel;
  if (location.hash === neu) return;
  hashIntern = true;
  location.hash = neu;
  // Flag im nächsten Tick zurücksetzen (hashchange feuert asynchron).
  setTimeout(() => { hashIntern = false; }, 0);
}

function zeichneNavigation() {
  const nav = $('#navigation');
  nav.textContent = '';
  if (!S.user) { nav.classList.add('versteckt'); return; }
  nav.classList.remove('versteckt');

  // Einen Navigationsknopf erzeugen. Das Symbol wird in der eingeklappten
  // schmalen Leiste angezeigt, der Text per Tooltip erreichbar.
  const navKnopf = (ziel, text, kindEbene, icon) => {
    const b = el('button', 'nv' + (kindEbene ? ' nv-child' : '')
      + (S.ansicht === ziel ? ' on' : ''));
    b.type = 'button';
    b.title = text;
    b.setAttribute('aria-label', text);
    if (S.ansicht === ziel) b.setAttribute('aria-current', 'page');
    b.appendChild(symbol(icon || 'datei'));
    b.appendChild(el('span', 'nv-text', text));
    b.addEventListener('click', () => wechsleAnsicht(ziel));
    return b;
  };

  // Rollenabhängige Hauptpunkte.
  if (S.user.rolle === 'eltern' || S.user.rolle === 'schueler') {
    nav.appendChild(navKnopf('buchen', 'Termin buchen', false, 'kalender'));
    nav.appendChild(navKnopf('meine', 'Meine Termine', false, 'uebersicht'));
  }
  if (S.user.rolle === 'lehrkraft' || S.user.rolle === 'admin') {
    nav.appendChild(navKnopf('lehrkraft', 'Meine Termine', false, 'uebersicht'));
    nav.appendChild(navKnopf('einladungen', 'Einladungen', false, 'umschlag'));
    nav.appendChild(navKnopf('mitteilungen', 'Mitteilungen', false, 'nachricht'));
  }

  // Administration als aufklappbare Gruppe.
  if (S.user.rolle === 'admin') {
    const adminSeiten = ['admin', 'admin-marke', 'admin-aktiv', 'admin-anzeige',
                         'admin-sprechtage', 'admin-daten', 'admin-loginlog',
                         'admin-texte', 'admin-erinnerungen'];
    const adminAktiv = adminSeiten.includes(S.ansicht);
    if (adminAktiv) S.adminOffen = true;   // aktive Unterseite -> Gruppe offen

    const gruppe = el('div', 'nv-group');
    const toggle = el('button', 'nv nv-group-toggle'
      + (S.adminOffen ? ' open' : ''));
    toggle.type = 'button';
    toggle.title = 'Administration';
    toggle.setAttribute('aria-label', 'Administration');
    toggle.setAttribute('aria-expanded', S.adminOffen ? 'true' : 'false');
    toggle.appendChild(symbol('einstellungen'));
    toggle.appendChild(el('span', 'nv-text', 'Administration'));
    const chev = symbol('runter');
    chev.classList.add('nv-chev');
    toggle.appendChild(chev);
    toggle.addEventListener('click', () => {
      S.adminOffen = !S.adminOffen;
      zeichneNavigation();
    });
    gruppe.appendChild(toggle);

    const sub = el('div', 'nv-sub');
    if (!S.adminOffen) sub.classList.add('versteckt');
    sub.appendChild(navKnopf('admin-aktiv', 'Aktiver Sprechtag', true, 'stern'));
    sub.appendChild(navKnopf('admin-marke', 'Erscheinungsbild', true, 'inhalte'));
    sub.appendChild(navKnopf('admin-anzeige', 'Anzeige', true, 'bildschirm'));
    sub.appendChild(navKnopf('admin-sprechtage', 'Sprechtage', true, 'kalender'));
    sub.appendChild(navKnopf('admin-daten', 'Volljährige Schüler', true, 'personen'));
    sub.appendChild(navKnopf('admin-loginlog', 'Login-Protokoll', true, 'datei'));
    sub.appendChild(navKnopf('admin-texte', 'Texte', true, 'stift'));
    sub.appendChild(navKnopf('admin-erinnerungen', 'Erinnerungen', true, 'glocke'));
    gruppe.appendChild(sub);
    nav.appendChild(gruppe);

    nav.appendChild(navKnopf('sondierung', 'Sondierung', false, 'suche'));
  }

  // Hilfe für alle Rollen.
  nav.appendChild(navKnopf('hilfe', 'Hilfe', false, 'hilfe'));

  // Abmelden unten.
  const ab = el('button', 'nv nv-abmelden');
  ab.type = 'button';
  ab.title = 'Abmelden';
  ab.setAttribute('aria-label', 'Abmelden');
  ab.appendChild(symbol('abmelden'));
  ab.appendChild(el('span', 'nv-text', 'Abmelden'));
  ab.addEventListener('click', () => abmelden());
  nav.appendChild(ab);

  // Mobiler Titel spiegelt die aktive Ansicht.
  const mt = $('#mobil-titel');
  if (mt) mt.textContent = (S.marke && S.marke.marke_titel) || 'Sprechtag';
}

// ---- Mobiles Menü (Hamburger) -------------------------------------------
function menueOeffnen() {
  $('#seitenleiste')?.classList.add('offen');
  $('#menue-overlay')?.classList.add('sichtbar');
  $('#mobil-menue')?.setAttribute('aria-expanded', 'true');
}
function menueSchliessen() {
  const warOffen = $('#seitenleiste')?.classList.contains('offen');
  $('#seitenleiste')?.classList.remove('offen');
  $('#menue-overlay')?.classList.remove('sichtbar');
  $('#mobil-menue')?.setAttribute('aria-expanded', 'false');
  // Fokus zurück auf den Knopf, der das Menü geöffnet hat. Ohne das
  // landet der Tastaturfokus nach dem Schließen im Nichts, und der
  // nächste Tabulatorsprung beginnt wieder ganz oben.
  if (warOffen) $('#mobil-menue')?.focus();
}

/**
 * Tastaturbedienung, die vorher fehlte.
 *
 * Escape schließt das mobile Menü. Ohne diese Behandlung war das Menü
 * nur mit der Maus zu schließen – auf einem Tablet mit Tastatur eine
 * Sackgasse.
 */
function tastaturBedienung() {
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if ($('#seitenleiste')?.classList.contains('offen')) {
      menueSchliessen();
    }
  });
}

/**
 * Trägt die Version aus /api/health nach.
 *
 * Das Ziel ist ein EIGENES Element neben der Fußzeile. Beides in
 * denselben Text zu schreiben hieße, dass Branding und Versionsanzeige
 * um dieselbe Stelle konkurrieren – und wer gewinnt, hinge daran, was
 * zuerst geladen wird.
 *
 * Fest im HTML musste die Version bei jedem Release von Hand gepflegt
 * werden; das wird irgendwann vergessen und zeigt dann dauerhaft eine
 * falsche Zahl.
 */
function versionAnzeigen() {
  const ziel = $('#marke-version');
  if (!ziel) return;
  fetch('/api/health', { cache: 'no-store' })
    .then((a) => a.json())
    .then((d) => { if (d && d.version) ziel.textContent = ' · v' + d.version; })
    .catch(() => { /* Die Version ist kein Grund für eine Fehlermeldung. */ });
}

// ---------- Sprechtag-Auswahl (in mehreren Ansichten genutzt) -------------
function sprechtagWaehler(ziel, beiWechsel) {
  if (S.sprechtage.length === 0) {
    ziel.appendChild(el('p', 'hinweis',
      'Zurzeit ist kein Sprechtag freigeschaltet.'));
    return false;
  }
  const zeile = el('div', 'zeile');
  const w = auswahl('Sprechtag', 'sprechtag-wahl',
    S.sprechtage.map((s) => ({ wert: s.id,
      text: s.name + ' (' + s.datum + ', ' + phaseText(s.phase) + ')' })),
    S.aktiverSprechtag ? S.aktiverSprechtag.id : '');
  w.querySelector('select').addEventListener('change', (e) => {
    S.aktiverSprechtag = S.sprechtage.find((s) => String(s.id) === e.target.value);
    S.lehrerListe = null; S.gewaehlteLehrkraft = null; S.raster = [];
    // Die eigenen Termine gehören zum Sprechtag – sonst zeigte die
    // Übersicht auf der Buchungsseite die des vorigen (v0.9.60).
    S.meineBuchungen = null; S.meineLaedt = false;
    if (beiWechsel) beiWechsel();
    zeichne();
  });
  zeile.appendChild(w);
  ziel.appendChild(zeile);
  return true;
}

function phaseText(p) {
  return { vorbereitung: 'in Vorbereitung', phase1: 'Phase 1 – nur auf Einladung',
    phase2: 'Phase 2 – offen für alle', geschlossen: 'geschlossen',
    archiviert: 'archiviert' }[p] || p;
}

// ============================================================
// ANSICHT: Login
// ============================================================
function ansichtLogin(ziel) {
  ziel.appendChild(el('h2', null, 'Anmeldung mit WebUntis'));
  const eigener = zeigeHinweisText(ziel, 'login_hinweis', 'loginHinweis');
  if (!eigener) {
    ziel.appendChild(el('p', 'hinweis',
      'Bitte mit den WebUntis-Zugangsdaten anmelden. Erziehungsberechtigte '
      + 'nutzen ihren eigenen Zugang – nicht den ihres Kindes.'));
  }

  const form = document.createElement('form');
  form.appendChild(feld('WebUntis-Benutzername', 'login-benutzer'));
  form.appendChild(feld('Passwort', 'login-passwort', 'password'));
  const aktionen = el('div', 'aktionen');
  const senden = el('button', null, 'Anmelden');
  senden.type = 'submit';
  aktionen.appendChild(senden);
  form.appendChild(aktionen);

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    senden.disabled = true;
    try {
      const benutzername = wert('login-benutzer');
      S.user = await api('/api/auth/login', { method: 'POST', body: {
        benutzername, passwort: wert('login-passwort') } });
      S.benutzername = benutzername;
      await ladeSprechtage();
      ladeOffenHinweis();
      S.ansicht = S.user.rolle === 'admin' ? 'admin-aktiv'
        : S.user.rolle === 'lehrkraft' ? 'lehrkraft' : 'buchen';
      setzeHash(S.ansicht);
      meldung(null);
    } catch (f) {
      senden.disabled = false;
      meldung(String(f.message), 'fehler');
    }
  });
  ziel.appendChild(form);

  // Hilfe ohne Anmeldung erreichbar.
  const hilfe = el('p', 'login-hilfe');
  const link = el('a', null, 'Hilfe & Anleitung ansehen');
  link.href = '#/hilfe';
  link.addEventListener('click', (e) => { e.preventDefault(); wechsleAnsicht('hilfe'); });
  hilfe.appendChild(link);
  ziel.appendChild(hilfe);
}

// ============================================================
// ANSICHT: Hilfe (ohne Anmeldung erreichbar)
// ============================================================
// Der Text ist ein Entwurf und darf redigiert werden. Er ist bewusst
// rollenübergreifend, weil die Seite auch vor dem Login erreichbar ist.
function ansichtHilfe(ziel) {
  ziel.appendChild(el('h2', null, 'Hilfe & Anleitung'));

  // Sprungmarken
  const nav = el('div', 'hilfe-nav');
  for (const [ziel2, text] of [['hilfe-schnell', 'Schnellanleitung'],
      ['hilfe-handbuch', 'Handbuch'], ['hilfe-faq', 'Häufige Fragen']]) {
    const a = el('a', 'hilfe-sprung', text);
    a.href = '#' + ziel2;
    nav.appendChild(a);
  }
  ziel.appendChild(nav);

  // Optionaler, von der Schule gepflegter Hilfetext (sicher serverseitig
  // gerendert). Wird einmal geladen und oben angezeigt, wenn vorhanden.
  if (S.hilfeZusatz === undefined) {
    S.hilfeZusatz = null;
    if (!S.hilfeZusatzLaedt) {
      S.hilfeZusatzLaedt = true;
      api('/api/einstellungen/text/hilfe_zusatz').then((d) => {
        S.hilfeZusatz = d.html || ''; S.hilfeZusatzLaedt = false; zeichne();
      }).catch(() => { S.hilfeZusatzLaedt = false; });
    }
  } else if (S.hilfeZusatz) {
    const box = el('div', 'hilfe-zusatz karte-innen');
    box.innerHTML = S.hilfeZusatz;   // serverseitig gesäubertes HTML
    ziel.appendChild(box);
  }

  // Wenn nicht angemeldet: Weg zurück zur Anmeldung anbieten.
  if (!S.user) {
    ziel.appendChild(knopf('Zur Anmeldung', 'klein', () => wechsleAnsicht('login')));
  }

  // ---- Schnellanleitung ----
  const schnell = sektion('Schnellanleitung');
  schnell.id = 'hilfe-schnell';
  schnell.appendChild(el('h4', null, 'Für Erziehungsberechtigte'));
  schnell.appendChild(hilfeListe([
    'Mit den eigenen WebUntis-Zugangsdaten anmelden – nicht mit dem Konto '
      + 'des Kindes.',
    'Oben den Sprechtag wählen (falls mehrere zur Auswahl stehen).',
    'Das Kind auswählen, um dessen Termine es geht.',
    'Bei der gewünschten Lehrkraft auf eine freie Uhrzeit tippen – der Termin '
      + 'ist damit gebucht.',
    'Unter „Meine Termine" sieht man alle Buchungen und kann sie wieder absagen.',
  ]));
  schnell.appendChild(el('h4', null, 'Für Lehrkräfte'));
  schnell.appendChild(hilfeListe([
    'Mit dem WebUntis-Zugang anmelden.',
    'Unter „Meine Termine" sieht man das eigene Zeitraster mit gebuchten und '
      + 'freien Slots.',
    'In Phase 1 können Eltern per „Einladungen" gezielt eingeladen werden.',
    'Ist ein Elternteil verhindert, kann die Lehrkraft stellvertretend für '
      + 'einen freien Slot buchen.',
  ]));
  schnell.appendChild(el('h4', null, 'Für die Administration'));
  schnell.appendChild(hilfeListe([
    'Unter „Aktiver Sprechtag" wird der laufende Sprechtag direkt verwaltet.',
    'Lehrkräfte, Anwesenheit und Räume werden in der Tabelle des Sprechtags '
      + 'gepflegt; „Alle speichern" schreibt alle Zeilen auf einmal.',
    'Das Erscheinungsbild (Logo, Texte) lässt sich unter '
      + '„Erscheinungsbild" anpassen.',
  ]));
  ziel.appendChild(schnell);

  // ---- Handbuch ----
  const hb = sektion('Handbuch');
  hb.id = 'hilfe-handbuch';

  hb.appendChild(el('h4', null, 'Anmeldung'));
  hb.appendChild(el('p', null,
    'Die Anmeldung erfolgt mit den WebUntis-Zugangsdaten der Schule. '
    + 'Erziehungsberechtigte verwenden ihren eigenen Elternzugang. Die App '
    + 'speichert keine Passwörter; die Anmeldung wird bei WebUntis geprüft.'));

  hb.appendChild(el('h4', null, 'Die zwei Phasen eines Sprechtags'));
  hb.appendChild(el('p', null,
    'Ein Sprechtag durchläuft in der Regel zwei Phasen. In Phase 1 können nur '
    + 'Erziehungsberechtigte buchen, die von einer Lehrkraft eingeladen wurden '
    + '– so kommen wichtige Gespräche zuerst zustande. In Phase 2 ist die '
    + 'Buchung für alle geöffnet. Ist ein Sprechtag geschlossen, sind keine '
    + 'Buchungen mehr möglich.'));

  hb.appendChild(el('h4', null, 'Termine buchen und absagen'));
  hb.appendChild(el('p', null,
    'Buchungen sind einem bestimmten Kind zugeordnet, damit die Lehrkraft '
    + 'weiß, um wen es geht. Ein Elternteil kann zur selben Uhrzeit nur einen '
    + 'Termin haben – Doppelbuchungen bei zwei Lehrkräften gleichzeitig werden '
    + 'verhindert. Abgesagte Termine geben den Slot sofort wieder frei.'));

  hb.appendChild(el('h4', null, 'Anwesenheit der Lehrkräfte'));
  hb.appendChild(el('p', null,
    'Lehrkräfte sind normalerweise den ganzen Sprechtag anwesend. Über das '
    + 'Uhr-Symbol (⏱) lässt sich ausnahmsweise ein Zeitfenster setzen. '
    + 'Halbtagskräfte und Referendar:innen werden mit „½" markiert und wählen '
    + 'dann die erste oder zweite Hälfte – entweder selbst oder über die '
    + 'Administration.'));

  hb.appendChild(el('h4', null, 'Räume und Doppelbelegung'));
  hb.appendChild(el('p', null,
    'Jeder Lehrkraft kann ein Raum zugewiesen werden. Wird ein Raum von '
    + 'mehreren Personen genutzt, ist das erlaubt, wird aber farblich markiert '
    + '– jede Farbe steht für einen mehrfach belegten Raum, sodass '
    + 'zusammengehörige Zeilen leicht zu erkennen sind.'));

  hb.appendChild(el('h4', null, 'Krankheitsausfall'));
  hb.appendChild(el('p', null,
    'Fällt eine Lehrkraft aus, gibt die Administration die Termine über das '
    + '⊘-Symbol frei. Die betroffenen Erziehungsberechtigten werden '
    + 'automatisch benachrichtigt, und die Lehrkraft ist danach nicht mehr '
    + 'buchbar.'));

  hb.appendChild(el('h4', null, 'Datenschutz'));
  for (const a of datenschutzAbsaetze()) hb.appendChild(el('p', null, a));
  ziel.appendChild(hb);

  // ---- FAQ ----
  const faq = sektion('Häufige Fragen');
  faq.id = 'hilfe-faq';
  const fragen = [
    ['Ich kann mich nicht anmelden.',
     'Bitte prüfen Sie, ob Sie den eigenen WebUntis-Zugang verwenden (nicht '
     + 'den des Kindes) und ob Benutzername und Passwort stimmen. Bei '
     + 'anhaltenden Problemen ' + kontaktSatz('wenden Sie sich an')],
    ['Warum sehe ich keine freien Termine?',
     'Möglicherweise läuft gerade Phase 1, in der nur eingeladene '
     + 'Erziehungsberechtigte buchen können, oder die Lehrkraft ist bereits '
     + 'ausgebucht. In Phase 2 stehen wieder alle freien Slots offen.'],
    ['Kann ich mehrere Kinder in einem Konto buchen?',
     'Ja. Wählen Sie oben das jeweilige Kind aus; die Buchungen werden dem '
     + 'richtigen Kind zugeordnet.'],
    ['Ich habe einen Termin gebucht, aber es kam keine Bestätigung.',
     'Nach einer eigenen Buchung kommt keine Nachricht. Die Buchung gilt '
     + 'sofort und steht unter „Meine Termine" und im Kalender-Abo. Eine '
     + 'Bestätigung per WebUntis-Nachricht kommt nur, wenn eine Lehrkraft für '
     + 'Sie gebucht hat.'],
    ['Kann ich der Lehrkraft vorab ein Thema mitteilen?',
     'Ja. Beim Buchen gibt es ein optionales Feld „Hinweis an die Lehrkraft". '
     + 'Was Sie dort eintragen (z. B. „Thema: Mathe-Note"), sieht nur die '
     + 'jeweilige Lehrkraft – keine anderen Eltern. Das Feld ist freiwillig.'],
    ['Wie sage ich einen Termin ab?',
     'Unter „Meine Termine" lässt sich jeder Termin absagen; der Platz wird '
     + 'sofort wieder frei.'],
    ['Kann ich die Termine in meinen Kalender übernehmen?',
     'Ja. Unter „Meine Termine" gibt es bei jedem Termin „📅 hinzufügen" für '
     + 'den einzelnen Eintrag und darunter einen persönlichen Abo-Link, mit '
     + 'dem die Termine kommender Sprechtage automatisch in Google-, Apple-, Outlook- oder '
     + 'WebUntis-Kalender erscheinen und sich bei Änderungen selbst '
     + 'aktualisieren. Der Link ist privat und sollte nicht weitergegeben '
     + 'werden.'],
    ['Als Lehrkraft: Kann ich für Eltern buchen, die selbst nicht können?',
     'Ja, in Ihrer eigenen Ansicht können Sie stellvertretend für ein freies '
     + 'Zeitfenster buchen. Das Elternkonto wird dabei automatisch ermittelt.'],
  ];
  for (const [frage, antwort] of fragen) {
    const f = block('faq-' + hilfeSchluessel(frage), frage);
    f.appendChild(el('p', null, antwort));
    faq.appendChild(f);
  }
  ziel.appendChild(faq);

  ziel.appendChild(el('p', 'hinweis-klein',
    'Diese Anleitung wird von der Schule gepflegt und kann sich ändern.'));
}

// Fester Datenschutz-Absatz der Hilfeseite (v0.9.74, H9). Bis v0.9.73
// stand dort, beim Archivieren würden „alle“ persönlichen Daten gelöscht –
// Login-Protokoll, Schülerliste und Kalender-Abo bleiben aber. Was das
// Archivieren löscht, hält tests/run_archivieren.php am Code fest; eine
// neue Tabelle (etwa die Ablage abgesagter Termine, E19) macht sie rot.
// Die Schülerliste ist seit v0.9.82 fort (sql/23); ihr Satz entfällt.
function datenschutzAbsaetze() {
  return [
    'Es werden so wenige personenbezogene Daten wie möglich gespeichert. '
      + 'Namen von Erziehungsberechtigten werden nur zur Laufzeit aus der '
      + 'aktuellen Sitzung verwendet.',
    'Was zu einem Sprechtag gehört – die Termine mit Name und Klasse des '
      + 'Kindes und den Hinweisen an die Lehrkraft, die Einladungen, die '
      + 'Benachrichtigungen und die für das '
      + 'Kind ermittelten Lehrkräfte – bleibt gespeichert, bis die Schule den '
      + 'Sprechtag archiviert, und wird dann gelöscht. Dafür gibt es keine '
      + 'automatische Frist. Erhalten bleibt nur die Struktur für den '
      + 'nächsten Sprechtag: Lehrkräfte, Räume und Zeiten.',
    'Der persönliche Kalender-Link liefert nur die Termine kommender Sprechtage; '
      + 'am Tag nach dem Sprechtag fallen sie aus dem Abo heraus. Ob die '
      + 'Kalender-App sie dann auch bei sich entfernt, liegt an der App. Ein '
      + 'einzeln über „📅 hinzufügen“ übernommener Termin ist eine Kopie in der '
      + 'Kalender-App; sie bleibt dort, bis man sie selbst löscht.',
    'Unabhängig vom Archivieren bleibt Folgendes gespeichert:',
    'Fehlgeschlagene Anmeldeversuche mit dem eingegebenen '
      + 'WebUntis-Benutzernamen und der IP-Adresse, zum Schutz vor dem '
      + 'Durchprobieren von Passwörtern. '
      + 'Erfolgreiche Anmeldungen werden nur festgehalten, wenn die Schule es '
      + 'eingestellt hat. Die Einträge werden nach einer Frist gelöscht, die '
      + 'die Schule festlegt (voreingestellt 30 Tage, höchstens 365), und zwar '
      + 'bei der ersten Anmeldung an der App nach Ablauf der Frist.',
    'Für den persönlichen Kalender-Link eine Kennnummer des Kontos '
      + '(kein Name) zusammen mit dem geheimen Link. Der Eintrag entsteht, sobald '
      + '„Meine Termine“ geöffnet wird, und bleibt bestehen; dafür gibt es '
      + 'derzeit keine Frist. „Neuen Link erzeugen“ macht den alten Link '
      + 'ungültig.',
  ];
}

// Baut eine nummerierte Liste aus Strings.
function hilfeListe(punkte) {
  const ol = document.createElement('ol');
  ol.className = 'hilfe-liste';
  for (const p of punkte) {
    const li = document.createElement('li');
    li.textContent = p;
    ol.appendChild(li);
  }
  return ol;
}

// Erzeugt einen einfachen Schlüssel aus einem Text (für block-Kennungen).
function hilfeSchluessel(text) {
  return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 30);
}

// ============================================================
// ANSICHT: Termin buchen (Eltern/Schüler)
// ============================================================
function ansichtBuchen(ziel) {
  ziel.appendChild(el('h2', null, 'Termin buchen'));
  zeigeHinweisText(ziel, 'buchung_hinweis', 'buchungHinweis');
  if (!sprechtagWaehler(ziel)) return;

  const s = S.aktiverSprechtag;
  if (s.phase === 'phase1') {
    ziel.appendChild(el('p', 'hinweis-wichtig',
      'Zurzeit läuft Phase 1: Termine können nur von Erziehungsberechtigten '
      + 'gebucht werden, die von einer Lehrkraft ausdrücklich eingeladen wurden.'));
  }

  // Kind wählen
  const kinder = S.user.kinder || [];
  if (kinder.length === 0) {
    ziel.appendChild(el('p', 'hinweis',
      'Diesem Konto sind keine Kinder zugeordnet. ' + kontaktSatz('Bitte wenden Sie sich an')));
    return;
  }
  if (!S.kind) S.kind = kinder[0].id;

  const kw = auswahl('Kind', 'kind-wahl',
    kinder.map((k) => ({ wert: k.id, text: k.name || ('Schüler-ID ' + k.id) })), S.kind);
  kw.querySelector('select').addEventListener('change', (e) => {
    S.kind = parseInt(e.target.value, 10);
    S.lehrerListe = null; S.lehrerLaedt = false;
    S.weitereSuche = '';
    S.gewaehlteLehrkraft = null; S.raster = [];
    zeichne();
  });
  ziel.appendChild(kw);

  // Kompakte, einklappbare Übersicht der eigenen Termine – damit Eltern beim
  // Buchen den Überblick behalten, ohne auf „Meine Termine" wechseln zu müssen.
  zeichneTermineKompakt(ziel);

  // Lehrkräfte automatisch laden.
  if (S.lehrerListe === null) {
    ziel.appendChild(el('p', 'hinweis', 'Lehrkräfte werden geladen …'));
    if (!S.lehrerLaedt) { S.lehrerLaedt = true; ladeLehrerListe(); }
    return;
  }

  // Volljährige Schüler außerhalb der zugelassenen Gruppen (E15): keine
  // Kacheln, sondern die Erklärung des Servers. Angemeldet sind sie trotzdem.
  if (S.lehrerListe.buchen_gesperrt) {
    ziel.appendChild(el('p', 'hinweis-wichtig',
      S.lehrerListe.hinweis || 'Termine buchen die Erziehungsberechtigten.'));
    return;
  }

  const alle = buchenLehrerAlle(S.lehrerListe);
  // Gruppe 3 (E10): weitere teilnehmende Lehrkräfte, ab Phase 2.
  const weitere = S.lehrerListe.weitere || [];
  if (alle.length === 0 && weitere.length === 0) {
    ziel.appendChild(el('p', 'hinweis', S.lehrerListe.nur_eingeladene
      ? 'In dieser Phase können Sie nur bei Lehrkräften buchen, die Sie '
        + 'eingeladen haben. Für dieses Kind liegt keine Einladung vor.'
      : 'Für dieses Kind konnten keine Lehrkräfte ermittelt werden. '
        + kontaktSatz('Bitte wenden Sie sich an')));
    return;
  }

  if (alle.length > 0) {
    ziel.appendChild(el('h3', null, 'Lehrkraft wählen'));

    // Kein Suchfeld über diesen Kacheln (Zug 3b, Entscheidung Betreiber):
    // Es filterte nur die ohnehin sichtbaren und stand direkt über der
    // Suche, die alle Teilnehmenden findet. Wer oben nichts fand, hielt
    // das für vollständig.
    // Je Abschnitt ein Gitter; die Sonderrollen beginnen eine neue Zeile
    // mit Abstand davor (Überschrift ja/nein: am Screenshot, v0.9.60).
    for (const a of buchenLehrerAbschnitte(S.lehrerListe)) {
      const gitter = el('div', 'buchen-gitter'
        + (a.art === 'sonderrollen' ? ' buchen-sonderrollen' : ''));
      zeichneBuchenKacheln(gitter, a.lehrer);
      ziel.appendChild(gitter);
    }
  } else {
    ziel.appendChild(el('p', 'hinweis',
      'Für dieses Kind wurden keine unterrichtenden Lehrkräfte ermittelt. '
      + 'Über die Suche unten finden Sie alle teilnehmenden Lehrkräfte.'));
  }

  if (weitere.length > 0) zeichneWeitereLehrkraefte(ziel, weitere);

  if (S.gewaehlteLehrkraft && S.raster.length) {
    zeichneRaster(ziel, S.gewaehlteLehrkraft);
  }
}

// Zeigt einen schulspezifischen Hinweistext (serverseitig gerendert) an, wenn
// hinterlegt. cacheKey ist das Zustandsfeld (z. B. 'buchungHinweis').
// Rückgabe: true, wenn ein eigener Text angezeigt wurde (dann kann der Aufrufer
// den fest eingebauten Standardtext weglassen); false sonst. Solange der Text
// noch lädt, wird ebenfalls false zurückgegeben (Standard erscheint kurz).
function zeigeHinweisText(ziel, schluessel, cacheKey) {
  if (S[cacheKey] === undefined) {
    if (!S[cacheKey + 'Laedt']) {
      S[cacheKey + 'Laedt'] = true;
      api('/api/einstellungen/text/' + schluessel).then((d) => {
        S[cacheKey] = d.html || ''; S[cacheKey + 'Laedt'] = false; zeichne();
      }).catch(() => { S[cacheKey + 'Laedt'] = false; S[cacheKey] = ''; });
    }
    return false;
  }
  if (S[cacheKey]) {
    const box = el('div', 'hilfe-zusatz');
    box.innerHTML = S[cacheKey];   // serverseitig gesäubert
    ziel.appendChild(box);
    return true;
  }
  return false;
}

// Kompakte, einklappbare Übersicht der eigenen Termine für die Buchungsseite.
// Zugeklappt zeigt sie die Kurzfassung; aufgeklappt die chronologische Liste
// mit Absage-Möglichkeit. Startet eingeklappt (block() merkt sich den Zustand).
function zeichneTermineKompakt(ziel) {
  // Termine laden, falls noch nicht vorhanden (Guard gegen Mehrfachladen).
  if (S.meineBuchungen === null) {
    if (!S.meineLaedt) { S.meineLaedt = true; ladeMeineBuchungen(); }
    return;
  }
  const termine = S.meineBuchungen.slice().sort(
    (a, c) => String(a.slot_beginn).localeCompare(String(c.slot_beginn)));
  const anzahl = termine.length;

  const titel = anzahl === 0
    ? 'Meine Termine: noch keine gebucht'
    : ('Meine Termine: ' + anzahl + (anzahl === 1 ? ' Termin' : ' Termine'));
  const b = block('buchen-uebersicht', titel);

  if (anzahl === 0) {
    b.appendChild(el('p', 'hinweis',
      'Sobald Sie unten einen Termin buchen, erscheint er hier.'));
    ziel.appendChild(b);
    return;
  }

  const kindName = (id) => {
    const k = (S.user.kinder || []).find((x) => x.id === id);
    return k ? (k.name || ('Schüler-ID ' + id)) : ('Schüler-ID ' + id);
  };

  const liste = el('div', 'termine-kompakt');
  for (const t of termine) {
    const zeile = el('div', 'termin-zeile');
    zeile.appendChild(el('span', 'termin-zeit', String(t.slot_beginn).slice(0, 5)));
    const info = el('div', 'termin-info');
    info.appendChild(el('span', 'termin-lehrer', t.name || t.kuerzel));
    const detail = [t.raum_kuerzel ? ('Raum ' + t.raum_kuerzel) : null,
      kindName(parseInt(t.schueler_id, 10))].filter(Boolean).join(' · ');
    info.appendChild(el('span', 'termin-detail', detail));
    zeile.appendChild(info);
    if (t.phase !== 'phase1') {
      zeile.appendChild(knopf('Absagen', 'klein gefahr', () => stornieren(t.id)));
    } else {
      zeile.appendChild(el('span', 'hinweis-klein', 'auf Einladung'));
    }
    liste.appendChild(zeile);
  }
  b.appendChild(liste);
  const zurLink = knopf('Zur vollen Übersicht (Drucken, Kalender)', 'klein',
    () => wechsleAnsicht('meine'));
  b.appendChild(zurLink);
  ziel.appendChild(b);
}

// Abschnitte der buchbaren Lehrkräfte in Anzeigereihenfolge (E10,
// Vierteilung seit v0.9.60): 1. Eingeladene, Klassenleitung und
// Unterrichtende – „Wer unterrichtet mein Kind?“; 2. Sonderrollen – „An wen
// wende ich mich bei einem Anliegen, das kein Fach betrifft?“, abgesetzt,
// an derselben Stelle wie zuvor. Die Weiteren (4.) stehen hinter der Suche.
// In Phase 1 liefert der Server nur Eingeladene (nur_eingeladene, E10).
// Fehlende Listen gelten als leer, leere Abschnitte entfallen.
function buchenLehrerAbschnitte(liste) {
  return [
    { art: 'unterricht', lehrer: (liste.eingeladen || []).concat(liste.unterrichtend || []) },
    { art: 'sonderrollen', lehrer: liste.sonderlehrer || [] },
  ].filter((a) => a.lehrer.length > 0);
}

// Alle buchbaren Lehrkräfte in Anzeigereihenfolge – aus den Abschnitten,
// damit die Reihenfolge an einer Stelle steht.
function buchenLehrerAlle(liste) {
  return buchenLehrerAbschnitte(liste).flatMap((a) => a.lehrer);
}

// Rendert die Lehrkraft-Kacheln in den Container.
// suche: Suchtext (nur Gruppe 3); ohne ihn erscheinen alle.
function zeichneBuchenKacheln(gitter, alle, suche) {
  gitter.textContent = '';
  const q = (suche || '').trim().toLowerCase();
  const treffer = !q ? alle : alle.filter((l) => {
    const heu = [l.name, l.kuerzel, l.faecher, l.raum_kuerzel]
      .filter(Boolean).join(' ').toLowerCase();
    return heu.includes(q);
  });

  if (treffer.length === 0) {
    gitter.appendChild(el('p', 'hinweis', 'Keine Lehrkraft passt zur Suche.'));
    return;
  }

  for (const l of treffer) {
    const istKl = Number(l.klassenleitung) === 1;
    // Klassenleitung nur über das Abzeichen, ohne Rand: Ein Rand sah aus
    // wie „gewählt“ (Zug 3b).
    const karte = el('div', 'buchen-kachel'
      + (S.gewaehlteLehrkraft === l.lehrer_id ? ' gewaehlt' : ''));
    if (l.raum_kuerzel) karte.appendChild(el('div', 'bk-raum', l.raum_kuerzel));
    karte.appendChild(el('div', 'bk-name', l.name || l.kuerzel));
    if (l.faecher) karte.appendChild(el('div', 'bk-faecher', l.faecher));
    if (istKl) karte.appendChild(el('span', 'rolle-badge', 'Klassenleitung'));
    if (l.rolle) karte.appendChild(el('span', 'rolle-badge', l.rolle));
    // Warum steht sie hier? Gerade wenn sie das Kind nicht unterrichtet.
    if (Number(l.eingeladen) === 1) {
      karte.appendChild(el('div', 'hinweis-klein', 'hat Sie eingeladen'));
    }
    if (parseInt(l.stunden, 10) === 0 && parseInt(l.klausuren, 10) > 0) {
      karte.appendChild(el('div', 'hinweis-klein', 'nur Klausurtermin'));
    }
    if (l.anwesend_von) {
      karte.appendChild(el('div', 'bk-zeit',
        anzeigeZeit(l, S.aktiverSprechtag)));
    } else {
      karte.appendChild(el('div', 'bk-zeit', 'ganztägig'));
    }
    karte.addEventListener('click', () => ladeRaster(l.lehrer_id));
    gitter.appendChild(karte);
  }
}

// Gruppe 3 (E10): Treffer erst bei Eingabe – sonst stünde eine Wand aus
// allen Lehrkräften da. Dieselben Kacheln, also derselbe Weg zum Buchen.
function zeichneWeitereKacheln(gitter, weitere) {
  const q = (S.weitereSuche || '').trim();
  if (!q) {
    gitter.textContent = '';
    gitter.appendChild(el('p', 'hinweis', 'Bitte Name, Kürzel oder Raum eingeben.'));
    return;
  }
  zeichneBuchenKacheln(gitter, weitere, q);
}

// Eingeklappter Block mit eigener Suche; block() merkt sich, ob er offen ist.
function zeichneWeitereLehrkraefte(ziel, weitere) {
  const b = block('buchen-weitere', 'Weitere Lehrkräfte suchen (' + weitere.length + ')');
  b.appendChild(el('p', 'hinweis', 'Auch bei Lehrkräften, die Ihr Kind nicht '
    + 'unterrichten, können Sie einen Termin buchen.'));
  // suchtreffer: Treffer gehören zum Suchfeld – kleinerer Abstand (E14).
  const gitter = el('div', 'buchen-gitter suchtreffer');
  const suche = feld('Name, Kürzel oder Raum', 'buchen-weitere-suche', 'text',
    S.weitereSuche || '');
  suche.querySelector('input').addEventListener('input', (e) => {
    S.weitereSuche = e.target.value;
    zeichneWeitereKacheln(gitter, weitere);
  });
  b.appendChild(suche);
  zeichneWeitereKacheln(gitter, weitere);
  b.appendChild(gitter);
  ziel.appendChild(b);
}

async function ladeLehrerListe() {
  try {
    S.lehrerListe = await api('/api/buchbare-lehrer?sprechtag='
      + S.aktiverSprechtag.id + '&kind=' + S.kind);

    // Lehrkräfte aus dem Stundenplan ohne Stammsatz: Das ist fast immer
    // ein veralteter Stammdaten-Sync und würde sonst unbemerkt bleiben.
    const fehlend = S.lehrerListe.ohne_stammsatz || [];
    if (fehlend.length > 0) {
      meldung('Hinweis: Für diese Lehrkräfte aus dem Stundenplan fehlt ein '
        + 'Stammsatz und sie sind deshalb nicht buchbar: ' + fehlend.join(', ')
        + '. Bitte in der Administration die Stammdaten synchronisieren.', 'fehler');
    } else if (S.lehrerListe.buchen_gesperrt) {
      meldung(null);   // die Erklärung steht in der Seite (E15)
    } else if (S.lehrerListe.sitzung
               && (S.lehrerListe.unterrichtend || []).length === 0) {
      // Ohne Dienstkonto ermittelt die eigene Sitzung (E17); ist sie
      // abgelaufen, wird das gesagt – nicht „noch keine Lehrkräfte“.
      sitzungAuswerten(S.lehrerListe.sitzung,
        'Die Lehrkräfte Ihres Kindes konnten nicht aus dem Stundenplan ermittelt '
        + 'werden: ' + S.lehrerListe.sitzung_meldung,
        { knopf: 'Anmelden', aktion: async () => {
          S.lehrerListe = null; S.lehrerLaedt = false; meldung(null); } });
    } else if (!S.lehrerListe.nur_eingeladene
               && (S.lehrerListe.unterrichtend || []).length === 0) {
      meldung('Für dieses Kind sind noch keine Lehrkräfte hinterlegt. '
        + 'Die Zuordnung wird von der Schule vorbereitet.', 'info');
    } else { meldung(null); }
  } catch (f) {
    S.lehrerListe = { unterrichtend: [], sonderlehrer: [] };
    meldung(String(f.message), 'fehler');
  } finally {
    S.lehrerLaedt = false;
    zeichne();
  }
}

async function ladeRaster(lehrerId) {
  try {
    const d = await api('/api/raster?sprechtag=' + S.aktiverSprechtag.id
      + '&lehrer=' + lehrerId);
    S.gewaehlteLehrkraft = lehrerId;
    S.raster = d.raster || [];
    meldung(null);
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

function zeichneRaster(ziel, lehrerId) {
  ziel.appendChild(el('h3', null, 'Freie Zeiten'));

  // Optionaler Hinweis an die Lehrkraft (Thema des Gesprächs). Gilt für den
  // als Nächstes angeklickten freien Termin.
  const komm = feld('Optionaler Hinweis an die Lehrkraft (z. B. „Thema: '
    + 'Mathe-Note")', 'buchung-kommentar', 'text', '');
  komm.classList.add('kommentar-feld');
  ziel.appendChild(komm);

  const raster = el('div', 'raster');
  for (const z of S.raster) {
    if (z.typ === 'pause') {
      raster.appendChild(el('div', 'slot pause',
        'Pause ' + z.beginn + (z.ende ? '–' + z.ende : '')));
      continue;
    }
    const klasse = 'slot ' + (z.frei ? 'frei' : (z.eigene ? 'eigene' : 'belegt'));
    const s = el('div', klasse, z.beginn);
    if (z.frei) {
      s.addEventListener('click',
        () => buchen(lehrerId, z.beginn, wert('buchung-kommentar')));
    } else if (z.eigene) {
      s.title = 'Von Ihnen gebucht';
    }
    raster.appendChild(s);
  }
  ziel.appendChild(raster);
}

async function buchen(lehrerId, slot, kommentar) {
  try {
    await api('/api/buchungen', { method: 'POST', body: {
      sprechtag_id: S.aktiverSprechtag.id, lehrer_id: lehrerId,
      schueler_id: S.kind, slot_beginn: slot,
      kommentar: (kommentar || '').trim() } });
    // Eigene Termine neu laden, damit die Kompaktübersicht oben stimmt.
    S.meineBuchungen = null; S.meineLaedt = false;
    await ladeRaster(lehrerId);
    toast('Termin um ' + slot + ' Uhr gebucht.', 'ok');
  } catch (f) {
    // Abgelaufen (v0.9.76): Fehlten die Kinddaten in der Sitzung und ließen
    // sie sich nicht nachholen, ist NICHT gebucht – nach der Anmeldung
    // derselbe Termin.
    if (sitzungAuswerten(f.sitzung, 'Der Termin ist NICHT eingetragen: ' + f.message,
      { knopf: 'Anmelden und buchen', aktion: () => buchen(lehrerId, slot, kommentar),
        spaeter: 'Nicht gebucht. Der Termin ist nicht eingetragen.' })) return;
    toast(String(f.message), 'fehler');
  }
}

// ============================================================
// ANSICHT: Meine Termine (Eltern/Schüler)
// ============================================================
function ansichtMeineTermine(ziel) {
  ziel.appendChild(el('h2', null, 'Meine Termine'));
  if (!sprechtagWaehler(ziel)) return;

  if (S.meineBuchungen === null) {
    // Automatisch laden, sobald die Ansicht sichtbar ist. Der Guard verhindert
    // Mehrfachladen (ladeMeineBuchungen ruft meldung(), das neu zeichnet).
    ziel.appendChild(el('p', 'hinweis', 'Termine werden geladen …'));
    if (!S.meineLaedt) {
      S.meineLaedt = true;
      ladeMeineBuchungen();
    }
    return;
  }
  if (S.meineBuchungen.length === 0) {
    ziel.appendChild(el('p', 'hinweis', 'Noch keine Termine gebucht.'));
    ziel.appendChild(knopf('Aktualisieren', 'klein', () => ladeMeineBuchungen()));
    return;
  }

  const kindName = (id) => {
    const k = (S.user.kinder || []).find((x) => x.id === id);
    return k ? (k.name || ('Schüler-ID ' + id)) : ('Schüler-ID ' + id);
  };

  const tab = el('table', 'tabelle');
  const kopf = el('tr');
  for (const t of ['Zeit', 'Lehrkraft', 'Raum', 'Kind', 'Kalender', '']) {
    kopf.appendChild(el('th', null, t));
  }
  tab.appendChild(kopf);

  for (const b of S.meineBuchungen.slice().sort(
      (a, c) => String(a.slot_beginn).localeCompare(String(c.slot_beginn)))) {
    const tr = el('tr');
    tr.appendChild(el('td', 'zeit', String(b.slot_beginn).slice(0, 5)));
    tr.appendChild(el('td', null, b.name || b.kuerzel));
    tr.appendChild(el('td', null, b.raum_kuerzel || '–'));
    tr.appendChild(el('td', null, kindName(parseInt(b.schueler_id, 10))));
    // Einzeltermin als .ics herunterladen (öffnet die Kalender-App).
    const tdIcs = el('td');
    const a = el('a', 'ics-link', '📅 hinzufügen');
    a.href = '/api/buchung/' + b.id + '.ics';
    a.setAttribute('download', 'termin.ics');
    a.target = '_blank';
    tdIcs.appendChild(a);
    tr.appendChild(tdIcs);
    const td = el('td');
    if (b.phase === 'phase1') {
      td.appendChild(el('span', 'hinweis-klein',
        'auf Einladung – Absage nur durch die Lehrkraft'));
    } else {
      td.appendChild(knopf('Absagen', 'klein gefahr', () => stornieren(b.id)));
    }
    tr.appendChild(td);
    tab.appendChild(tr);
  }
  ziel.appendChild(kartenTabelle(tab));
  ziel.appendChild(knopf('Aktualisieren', 'klein', () => ladeMeineBuchungen()));

  // ---- Kalender abonnieren -------------------------------------------
  const abo = block('kalender-abo', 'Termine im Kalender abonnieren');
  abo.appendChild(el('p', 'hinweis',
    'Mit diesem persönlichen Link erscheinen Ihre kommenden Sprechtag-Termine '
    + 'automatisch in Ihrem Kalender (Google, Apple, Outlook – oder in '
    + 'WebUntis als externer Kalender). Absagen und neue Buchungen '
    + 'aktualisieren sich von selbst. Der Link ist privat – bitte nicht '
    + 'weitergeben.'));
  const linkZeile = el('div', 'zeile');
  const feldLink = document.createElement('input');
  feldLink.type = 'text'; feldLink.id = 'kal-abo-url'; feldLink.readOnly = true;
  feldLink.value = S.kalenderLink || 'wird geladen …';
  linkZeile.appendChild(feldLink);
  abo.appendChild(linkZeile);
  const btnZeile = el('div', 'zeile');
  btnZeile.appendChild(knopf('Link kopieren', 'klein', async () => {
    try { await navigator.clipboard.writeText($('#kal-abo-url').value);
      toast('Link kopiert.', 'ok'); }
    catch { $('#kal-abo-url').select(); toast('Bitte manuell kopieren.', 'info'); }
  }));
  btnZeile.appendChild(knopf('Neuen Link erzeugen', 'klein', async () => {
    if (!confirm('Der alte Link wird ungültig. Fortfahren?')) return;
    try {
      const d = await api('/api/kalender-link/neu', { method: 'POST' });
      S.kalenderLink = d.url;
      const f = $('#kal-abo-url'); if (f) f.value = d.url;
      toast('Neuer Kalender-Link erzeugt.', 'ok');
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));
  abo.appendChild(btnZeile);
  ziel.appendChild(abo);

  if (!S.kalenderLink) {
    api('/api/kalender-link').then((d) => {
      S.kalenderLink = d.url;
      const f = $('#kal-abo-url'); if (f) f.value = d.url;
    }).catch(() => {});
  }
}

async function ladeMeineBuchungen() {
  const sid = S.aktiverSprechtag.id;
  try {
    const d = await api('/api/buchungen?sprechtag=' + sid);
    // Inzwischen anderer Sprechtag gewählt: Diese Antwort gehört nicht
    // mehr hierher; das Laden für den neuen läuft schon.
    if (!S.aktiverSprechtag || S.aktiverSprechtag.id !== sid) return;
    S.meineBuchungen = d.buchungen || [];
    S.meineLaedt = false;
    meldung(null);
  } catch (f) { S.meineLaedt = false; meldung(String(f.message), 'fehler'); }
}

async function stornieren(id) {
  if (!confirm('Diesen Termin wirklich absagen?')) return;
  try {
    await api('/api/buchungen/' + id, { method: 'DELETE' });
    await ladeMeineBuchungen();
    // Falls gerade ein Buchungsraster offen ist, ebenfalls neu laden, damit der
    // frei gewordene Slot dort sofort wieder auftaucht.
    if (S.ansicht === 'buchen' && S.gewaehlteLehrkraft) {
      await ladeRaster(S.gewaehlteLehrkraft);
    }
    toast('Termin abgesagt.', 'ok');
  } catch (f) { toast(String(f.message), 'fehler'); }
}

// ============================================================
// ANSICHT: Lehrkraft – eigene Termine
// ============================================================
function ansichtLehrkraft(ziel) {
  const istAdmin = S.user.rolle === 'admin';
  const eigeneId = S.user.lehrer_id;
  const gezeigteId = S.gewaehlteLehrkraftAnsicht !== null
    ? S.gewaehlteLehrkraftAnsicht : eigeneId;

  ziel.appendChild(el('h2', null, istAdmin && gezeigteId !== eigeneId
    ? 'Sprechtags-Termine einer Lehrkraft' : 'Meine Sprechtags-Termine'));
  if (!sprechtagWaehler(ziel, () => { S.svRaster = null; S.svLaedt = false; })) return;

  // Admins sehen sonst immer nur die Termine der Lehrkraft, die in
  // admin_kuerzel hinterlegt ist – das ist ohne Auswahl irreführend.
  if (istAdmin) {
    if (S.stammdaten.lehrer.length === 0) {
      ladeStammdaten().then(() => zeichne());
    } else {
      const w = auswahl('Termine anzeigen für', 'lk-wahl',
        [{ wert: '', text: eigeneId === null
            ? '– Lehrkraft wählen –' : 'Eigene Termine' }].concat(
          S.stammdaten.lehrer.map((l) => ({ wert: l.id,
            text: l.kuerzel + (l.name ? ' – ' + l.name : '') }))),
        S.gewaehlteLehrkraftAnsicht === null ? '' : S.gewaehlteLehrkraftAnsicht);
      w.querySelector('select').addEventListener('change', (e) => {
        S.gewaehlteLehrkraftAnsicht = e.target.value === ''
          ? null : parseInt(e.target.value, 10);
        S.svRaster = null;
        S.svLaedt = false;
        S.svFehler = null;
        zeichne();
      });
      ziel.appendChild(w);
    }
  }

  if (gezeigteId === null && S.user.rolle === 'lehrkraft') {
    ziel.appendChild(el('p', 'hinweis-wichtig',
      'Diesem Konto ist kein Lehrkraft-Stammsatz zugeordnet. '
      + 'Bitte die Administration bitten, die Stammdaten zu synchronisieren.'));
    return;
  }
  if (gezeigteId === null) {
    ziel.appendChild(el('p', 'hinweis', 'Bitte oben eine Lehrkraft auswählen.'));
    return;
  }

  if (S.svRaster === null) {
    // Bei einem vorherigen Fehler nicht endlos neu laden, sondern die
    // Fehlermeldung zeigen und einen Wiederholen-Knopf anbieten.
    if (S.svFehler !== null) {
      ziel.appendChild(el('p', 'meldung fehler', S.svFehler));
      ziel.appendChild(knopf('Erneut versuchen', null, () => {
        S.svFehler = null; S.svLaedt = true; ladeSvRaster(gezeigteId);
      }));
      return;
    }
    // Automatisch laden, sobald die Ansicht sichtbar ist – kein Knopfdruck
    // mehr nötig. Der Guard verhindert Mehrfachladen (ladeSvRaster ruft
    // zeichne(), das diese Ansicht erneut aufbaut).
    ziel.appendChild(el('p', 'hinweis', 'Termine werden geladen …'));
    if (!S.svLaedt) {
      S.svLaedt = true;
      ladeSvRaster(gezeigteId);
    }
    return;
  }

  zeichneLehrkraftRaster(ziel, gezeigteId);
}

// Einheitliche Slot-Ansicht: belegte Slots zeigen Kind + Zeit, freie Slots
// sind anklickbar und werden stellvertretend gebucht (für Eltern, die nicht
// selbst buchen können). Dieselbe Rasterquelle wie bei den Eltern – nur mit
// Namen, weil die Lehrkraft sehen darf, wer gebucht hat.
function zeichneLehrkraftRaster(ziel, lehrerId) {
  const slots = S.svRaster.filter((r) => r.typ === 'slot');
  const belegte = slots.filter((r) => !r.frei);
  const freie   = slots.filter((r) => r.frei);

  // Stellvertretend buchen darf man nur im EIGENEN Raster. Ein Admin, der
  // die Termine einer anderen Lehrkraft ansieht, bekommt nur die Übersicht
  // – nicht das Recht, in fremdem Namen Eltern einzutragen.
  const eigenesRaster = lehrerId === S.user.lehrer_id;

  // Halbtagskräfte wählen im eigenen Raster ihre Hälfte selbst.
  if (eigenesRaster && S.svLehrer && parseInt(S.svLehrer.halbtags, 10) === 1) {
    zeichneHaelfteWahl(ziel, lehrerId);
  }

  if (eigenesRaster) {
    zeichneStellvertreterKopf(ziel, lehrerId);
  } else {
    ziel.appendChild(el('p', 'hinweis',
      'Ansicht der Termine einer anderen Lehrkraft. Stellvertretend buchen '
      + 'kann nur die Lehrkraft selbst in ihrer eigenen Ansicht.'));
  }

  // ---- Das Raster ------------------------------------------------------
  ziel.appendChild(el('h3', null, 'Zeitraster'
    + ' – ' + belegte.length + ' belegt, ' + freie.length + ' frei'));
  const raster = el('div', 'raster raster-breit');
  for (const z of S.svRaster) {
    if (z.typ === 'pause') {
      raster.appendChild(el('div', 'slot pause',
        'Pause ' + z.beginn + (z.ende ? '–' + z.ende : '')));
      continue;
    }
    if (z.frei) {
      const s = el('div', 'slot frei', z.beginn);
      if (eigenesRaster) {
        s.title = 'Freien Termin stellvertretend buchen';
        s.addEventListener('click', () => stellvertretendBuchen(lehrerId, z.beginn));
      } else {
        // Fremde Ansicht: freie Slots nur zeigen, nicht buchbar machen.
        s.classList.remove('frei');
        s.classList.add('frei-passiv');
      }
      raster.appendChild(s);
    } else {
      // Belegter Slot: Zeit + Kind (+ Klasse), plus Absage-Möglichkeit.
      const s = el('div', 'slot belegt-info');
      s.appendChild(el('div', 'slot-zeit', z.beginn));
      s.appendChild(el('div', 'slot-kind',
        z.kind_name || ('ID ' + (z.schueler_id || '?'))));
      if (z.klasse) s.appendChild(el('div', 'slot-klasse', z.klasse));
      if (z.kommentar) s.appendChild(el('div', 'slot-kommentar', '„' + z.kommentar + '"'));
      // Absagen darf ebenfalls nur die Lehrkraft im eigenen Raster.
      if (eigenesRaster) {
        const ab = el('span', 'slot-absage', 'absagen');
        ab.title = 'Diesen Termin absagen';
        ab.addEventListener('click', () => lehrkraftStorno({
          id: z.buchung_id, slot_beginn: z.beginn,
          sprechtag_id: S.aktiverSprechtag.id }));
        s.appendChild(ab);
      }
      raster.appendChild(s);
    }
  }
  ziel.appendChild(raster);
  ziel.appendChild(knopf('Aktualisieren', 'klein',
    () => { S.svRaster = null; ladeSvRaster(lehrerId); }));

  // Export nur im eigenen Raster anbieten (nicht in fremder Admin-Ansicht).
  if (eigenesRaster) {
    const sid = S.aktiverSprechtag.id;
    const exp = block('lehrer-export', 'Terminliste exportieren');
    exp.appendChild(el('p', 'hinweis',
      'Für den Sprechtag: eine druckbare Tischvorlage, die Tagesliste als '
      + 'Kalenderdatei, oder ein persönlicher Abo-Link, mit dem Ihre kommenden Termine '
      + 'automatisch im eigenen Kalender (auch WebUntis) erscheinen.'));
    const z = el('div', 'zeile');
    const vorlage = el('a', 'knopf', 'Druckbare Tischvorlage');
    vorlage.href = '/api/lehrer-tischvorlage/' + sid;
    vorlage.target = '_blank';
    z.appendChild(vorlage);
    const icsDatei = el('a', 'knopf klein', 'Als Kalenderdatei (.ics)');
    icsDatei.href = '/api/lehrer-termine/' + sid + '.ics';
    icsDatei.setAttribute('download', 'meine-termine.ics');
    icsDatei.target = '_blank';
    z.appendChild(icsDatei);
    exp.appendChild(z);

    const aboZeile = el('div', 'zeile');
    const aboFeld = document.createElement('input');
    aboFeld.type = 'text'; aboFeld.id = 'lk-abo-url'; aboFeld.readOnly = true;
    aboFeld.value = S.lehrerKalenderLink || 'Abo-Link wird geladen …';
    aboZeile.appendChild(aboFeld);
    exp.appendChild(aboZeile);
    const aboBtn = el('div', 'zeile');
    aboBtn.appendChild(knopf('Abo-Link kopieren', 'klein', async () => {
      try { await navigator.clipboard.writeText($('#lk-abo-url').value);
        toast('Link kopiert.', 'ok'); }
      catch { $('#lk-abo-url').select(); toast('Bitte manuell kopieren.', 'info'); }
    }));
    aboBtn.appendChild(knopf('Neuen Abo-Link', 'klein', async () => {
      if (!confirm('Der alte Link wird ungültig. Fortfahren?')) return;
      try {
        const d = await api('/api/lehrer-kalender/neu', { method: 'POST' });
        S.lehrerKalenderLink = d.url;
        const f = $('#lk-abo-url'); if (f) f.value = d.url;
        toast('Neuer Abo-Link erzeugt.', 'ok');
      } catch (f) { toast(String(f.message), 'fehler'); }
    }));
    exp.appendChild(aboBtn);
    ziel.appendChild(exp);

    if (!S.lehrerKalenderLink) {
      api('/api/lehrer-kalender').then((d) => {
        S.lehrerKalenderLink = d.url;
        const f = $('#lk-abo-url'); if (f) f.value = d.url;
      }).catch(() => {});
    }
  }
}

// Kopfzeile der eigenen Ansicht: Kind wählen (Suchfeld) für die
// stellvertretende Buchung. Nur im eigenen Raster sichtbar.
function zeichneStellvertreterKopf(ziel, lehrerId) {
  const kopf = sektion('Stellvertretend für Eltern buchen');
  kopf.appendChild(el('p', 'hinweis',
    'Für Erziehungsberechtigte, die nicht selbst buchen können: erst das '
    + 'Kind wählen, dann unten auf einen freien Zeitpunkt tippen. Das '
    + 'Elternkonto wird automatisch ermittelt; der Termin ist danach für '
    + 'andere gesperrt und alle Erziehungsberechtigten werden benachrichtigt.'));

  if (S.svKind !== null) {
    // Ein Kind ist gewählt – kompakt anzeigen, mit Möglichkeit zu wechseln.
    const z = el('div', 'zeile sv-gewaehlt');
    z.appendChild(el('span', 'sv-gewaehlt-name',
      'Gewählt: ' + (S.svKindName || ('ID ' + S.svKind))));
    z.appendChild(knopf('Anderes Kind', 'klein', () => {
      S.svKind = null; S.svKindName = ''; S.svTreffer = null; zeichne();
    }));
    kopf.appendChild(z);
  } else {
    // Kind-Suche über /api/kinder (pageconfig über die Sitzung, v0.9.81,
    // E20). Gesucht wird über Knopf und Eingabetaste, nicht je Tastendruck:
    // Jede Suche kostet zwei WebUntis-Abrufe (R3).
    const form = document.createElement('form');
    form.className = 'zeile';
    const f = feld('Kind suchen (Name oder Klasse)', 'sv-suche', 'text',
      S.svKindSuche || '');
    f.querySelector('input').addEventListener('input', (e) => {
      S.svKindSuche = e.target.value;
    });
    form.appendChild(f);
    const los = el('button', 'klein', 'Suchen');
    los.type = 'submit';
    form.appendChild(los);
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      svKindSuchen(wert('sv-suche'));
    });
    kopf.appendChild(form);

    // suchtreffer: Treffer gehören zum Suchfeld – kleinerer Abstand (E14).
    const treffer = el('div', 'sv-treffer suchtreffer');
    treffer.id = 'sv-treffer';
    kopf.appendChild(treffer);
    setTimeout(zeichneSvTreffer, 0);   // Erstbefüllung nach dem Anhängen
  }
  ziel.appendChild(kopf);
}

// Selbstbedienung für Halbtagskräfte: erste/zweite Hälfte oder ganzer Tag.
// Nur im eigenen Raster sichtbar. Speichert über denselben PATCH-Endpunkt
// wie die Administration – der Server berechnet das Fenster aus der Hälfte.
function zeichneHaelfteWahl(ziel, lehrerId) {
  const k = sektion('Ihre Anwesenheit (Halbtagskraft)');
  k.appendChild(el('p', 'hinweis',
    'Als Halbtagskraft leisten Sie nur einen halben Sprechtag. Wählen Sie, '
    + 'welche Hälfte Sie übernehmen – Ihr Zeitraster passt sich sofort an. '
    + 'Bereits gebuchte Termine außerhalb der gewählten Hälfte bleiben '
    + 'bestehen, prüfen Sie diese daher vor einer Änderung.'));

  // Aktuelle Hälfte aus dem gelieferten Fenster erraten (nur Vorauswahl).
  const le = S.svLehrer || {};
  const sp = S.svSprechtag || {};
  const von = String(le.anwesend_von || '').slice(0, 5);
  const bis = String(le.anwesend_bis || '').slice(0, 5);
  const beginn = String(sp.beginn || '').slice(0, 5);
  const ende = String(sp.ende || '').slice(0, 5);
  let aktuell = 'ganz';
  if (von && bis) {
    if (von === beginn && bis !== ende) aktuell = 'erste';
    else if (von !== beginn && bis === ende) aktuell = 'zweite';
  }

  const z = el('div', 'zeile');
  const sel = auswahl('Meine Hälfte', 'haelfte-eigen',
    [{ wert: 'ganz', text: 'ganzer Tag' },
     { wert: 'erste', text: 'erste Hälfte' },
     { wert: 'zweite', text: 'zweite Hälfte' }], aktuell);
  z.appendChild(sel);
  k.appendChild(z);

  k.appendChild(knopf('Übernehmen', 'klein', async () => {
    const h = wert('haelfte-eigen');
    try {
      await api('/api/sprechtage/' + S.aktiverSprechtag.id + '/lehrer/' + lehrerId,
        { method: 'PATCH', body: { haelfte: h } });
      S.svRaster = null;   // Raster neu laden – Fenster hat sich geändert
      await ladeSvRaster(lehrerId);
      meldung('Anwesenheit gespeichert.', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));
  ziel.appendChild(k);
}

// Kind-Suche der stellvertretenden Buchung. Treffer, Fehler oder „sucht“
// stehen in S.svTreffer; abgelaufen: Kasten „Anmelden und suchen“.
async function svKindSuchen(q) {
  S.svKindSuche = q;
  S.svTreffer = { laeuft: true };
  zeichneSvTreffer();
  try {
    S.svTreffer = await kinderSuchen(q);
  } catch (f) {
    S.svTreffer = { fehler: String(f.message) };
    if (f.sitzung) {
      sitzungAuswerten(f.sitzung, String(f.message), { knopf: 'Anmelden und suchen',
        aktion: () => svKindSuchen(q),
        spaeter: 'Nicht gesucht. Die Kind-Suche braucht die WebUntis-Anmeldung.' });
    }
  }
  zeichneSvTreffer();
}

// Füllt die Trefferliste (#sv-treffer), ohne die ganze Ansicht neu zu
// zeichnen – so behält das Suchfeld den Fokus.
function zeichneSvTreffer() {
  const ziel = $('#sv-treffer');
  if (!ziel) return;
  ziel.textContent = '';
  const status = kinderStatusElement(S.svTreffer);
  if (status) { ziel.appendChild(status); return; }

  const liste = el('div', 'sv-treffer-liste');
  for (const k of S.svTreffer.kinder) {
    const b = el('button', 'sv-treffer-zeile', k.name + '  ·  ' + (k.klasse || '(Klassenname fehlt)'));
    b.type = 'button';
    b.addEventListener('click', () => {
      S.svKind = parseInt(k.id, 10);
      S.svKindName = k.name + (k.klasse ? ' (' + k.klasse + ')' : '');
      S.svKindSuche = '';
      S.svTreffer = null;
      zeichne();
    });
    liste.appendChild(b);
  }
  ziel.appendChild(liste);
  const hinweis = kinderTrefferHinweis(S.svTreffer);
  if (hinweis) ziel.appendChild(el('p', 'hinweis-klein', hinweis));
}

// ---- Kind-Suche: eine Quelle für Einladung und stellvertretend ----------
// /api/kinder liest pageconfig über die Sitzung der Lehrkraft (v0.9.81,
// E20 A). Ohne Suchbegriff wird nichts abgefragt (R1).
async function kinderSuchen(q) {
  const begriff = String(q || '').trim();
  if (begriff === '') return { kinder: [], anzahl: 0, grenze: 60, leer: true };
  return api('/api/kinder?suche=' + encodeURIComponent(begriff));
}

// Treffer nach Klasse, in der gelieferten Reihenfolge (der Server sortiert).
// Eine Liste statt eines Objekts: Objektschlüssel wie „10“ zöge JavaScript vor.
function kinderNachKlasse(kinder) {
  const gruppen = [];
  for (const k of kinder) {
    const name = k.klasse || '(Klassenname fehlt)';
    const letzte = gruppen[gruppen.length - 1];
    if (letzte && letzte[0] === name) letzte[1].push(k);
    else gruppen.push([name, [k]]);
  }
  return gruppen;
}

function kinderTrefferHinweis(d) {
  if (!d || !Array.isArray(d.kinder) || !(d.anzahl > d.kinder.length)) return '';
  return d.anzahl + ' Treffer – die ersten ' + d.grenze + ' werden gezeigt. Suche verfeinern.';
}

// Was statt der Treffer dasteht: Aufforderung, „sucht“, der Grund eines
// Fehlers oder „keine Treffer“. null, wenn es Treffer gibt. Eine nicht
// erreichbare Liste erscheint so nie still leer.
function kinderStatusElement(t) {
  if (!t || t.leer) return el('p', 'hinweis-klein', 'Name oder Klasse eingeben, dann „Suchen“.');
  if (t.laeuft) return el('p', 'hinweis-klein', 'Sucht …');
  if (t.fehler) return el('p', 'meldung fehler', t.fehler);
  if (!Array.isArray(t.kinder) || t.kinder.length === 0) {
    return el('p', 'hinweis-klein', 'Keine Treffer. Kinder ohne Klasse erscheinen nicht.');
  }
  return null;
}

async function stellvertretendBuchen(lehrerId, slot) {
  if (S.svLaeuft) return;
  if (!(S.svKind > 0)) {
    meldung('Bitte zuerst oben ein Kind auswählen.', 'fehler');
    return;
  }
  S.svLaeuft = true;
  meldung('Termin wird eingetragen …', 'info');
  try {
    const d = await api('/api/buchungen/stellvertretend',
      { method: 'POST', body: {
        sprechtag_id: S.aktiverSprechtag.id,
        lehrer_id: lehrerId, schueler_id: S.svKind, slot_beginn: slot } });
    S.svLaeuft = false;
    S.svKind = null;
    S.svKindName = '';
    S.svKindSuche = '';
    S.svRaster = null;
    await ladeSvRaster(lehrerId);
    const m = d.mitteilung;
    if (!(m && m.status !== 'gesendet' && sitzungAuswerten(m.sitzung,
        d.hinweis, { knopf: 'Anmelden und senden', aktion: () => sendeVorgemerkte(m.ids) }))) {
      meldung(d.hinweis || 'Termin eingetragen.', m && m.status !== 'gesendet' ? 'fehler' : 'ok');
    }
  } catch (f) {
    S.svLaeuft = false;
    // Abgelaufen: Es wurde NICHT gebucht – nach der Anmeldung derselbe Klick.
    if (sitzungAuswerten(f.sitzung, 'Der Termin ist noch NICHT eingetragen: ' + f.message,
      { knopf: 'Anmelden und buchen', aktion: () => stellvertretendBuchen(lehrerId, slot),
        spaeter: 'Nicht gebucht. Der Termin ist nicht eingetragen.' })) return;
    meldung(String(f.message), 'fehler');
  }
}

async function ladeSvRaster(lehrerId) {
  try {
    const d = await api('/api/raster?sprechtag=' + S.aktiverSprechtag.id
      + '&lehrer=' + lehrerId);
    S.svRaster = d.raster || [];
    S.svLehrer = d.lehrer || null;   // halbtags + Fenster für die Selbstbedienung
    S.svSprechtag = d.sprechtag || null;
    S.svFehler = null;
    meldung(null);
  } catch (f) {
    // Fehler merken, damit die Ansicht ihn zeigt statt in einer
    // Auto-Load-Schleife zu hängen.
    S.svFehler = String(f.message);
  } finally {
    S.svLaedt = false;
    zeichne();
  }
}

async function lehrkraftStorno(b) {
  const text = prompt('Absage – Nachricht an die Erziehungsberechtigten:',
    'Der Termin um ' + String(b.slot_beginn).slice(0, 5) + ' Uhr muss leider entfallen.');
  if (text === null) return;
  try {
    const d = await api('/api/buchungen/' + b.id
      + '?nachricht=' + encodeURIComponent(text), { method: 'DELETE' });
    // Raster der aktuell gezeigten Lehrkraft neu laden.
    const lid = S.gewaehlteLehrkraftAnsicht !== null
      ? S.gewaehlteLehrkraftAnsicht : S.user.lehrer_id;
    S.svRaster = null;
    await ladeSvRaster(lid);
    const m = d.mitteilung;
    if (m && m.status === 'gesendet') {
      meldung('Termin abgesagt. Die Erziehungsberechtigten wurden benachrichtigt.', 'ok');
    } else if (!(m && sitzungAuswerten(m.sitzung,
        'Termin abgesagt. Die Absage an die Erziehungsberechtigten ist gespeichert, aber '
        + 'noch NICHT verschickt: ' + m.grund,
        { knopf: 'Anmelden und senden', aktion: () => sendeVorgemerkte(m.ids) }))) {
      meldung('Termin abgesagt. Die Absage ist noch NICHT verschickt'
        + (m && m.grund ? ': ' + m.grund : '.') + ' Sie steht unter „Mitteilungen".', 'fehler');
    }
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

// ============================================================
// ANSICHT: Einladungen (Phase 1)
// ============================================================
function ansichtEinladungen(ziel) {
  ziel.appendChild(el('h2', null, 'Einladungen'));
  if (!sprechtagWaehler(ziel, () => { S.einladungen = null; S.einlLaedt = false; })) return;

  // Hinweis abhängig von der Phase des gewählten Sprechtags: In Phase 1
  // sind Einladungen der reguläre Weg; in Phase 2 kann ohnehin jeder
  // buchen, deshalb sind sie normalerweise nicht nötig – bleiben aber
  // möglich (z. B. um gezielt an ein Gespräch zu erinnern).
  const phase = S.aktiverSprechtag ? S.aktiverSprechtag.phase : null;
  if (phase === 'phase2') {
    ziel.appendChild(el('p', 'hinweis-wichtig',
      'Dieser Sprechtag ist in Phase 2 (offen für alle) – '
      + 'Erziehungsberechtigte können bereits selbst buchen. Einladungen '
      + 'sind hier nicht nötig, aber weiterhin möglich, um gezielt an ein '
      + 'Gespräch zu erinnern.'));
  } else if (phase === 'phase1') {
    ziel.appendChild(el('p', 'hinweis',
      'In Phase 1 können nur eingeladene Erziehungsberechtigte buchen. '
      + 'Wählen Sie die Kinder aus, deren Eltern Sie zum Gespräch bitten möchten.'));
  } else {
    ziel.appendChild(el('p', 'hinweis',
      'Dieser Sprechtag ist noch nicht für Buchungen freigeschaltet. '
      + 'Einladungen lassen sich bereits vorbereiten und werden mit dem '
      + 'Anlegen versendet.'));
  }

  // ---- Auswahl über die Kind-Suche (v0.9.81, E20) ------------------------
  // Quelle ist pageconfig über die Sitzung (/api/kinder). Ohne Suchbegriff
  // wird nichts geladen (R1); die Suche nach einer Klasse liefert die
  // ganze Klasse. Die freie Eingabe einer Schüler-ID entfällt (E20 D).
  const aus = sektion('Kinder auswählen');
  const form = document.createElement('form');
  form.className = 'zeile';
  form.appendChild(feld('Suche (Name oder Klasse)', 'einl-suche', 'text', S.einlSuche || ''));
  const los = el('button', 'klein', 'Suchen');
  los.type = 'submit';
  form.appendChild(los);
  form.addEventListener('submit', (ev) => {
    ev.preventDefault();
    einlSuchen(wert('einl-suche'));
  });
  aus.appendChild(form);
  einlTrefferZeichnen(aus);
  ziel.appendChild(aus);

  // ---- Bestehende Einladungen ------------------------------------------
  ziel.appendChild(el('h3', null, 'Angelegte Einladungen'));
  if (S.einladungen === null) {
    ziel.appendChild(el('p', 'hinweis', 'Einladungen werden geladen …'));
    if (!S.einlLaedt) { S.einlLaedt = true; ladeEinladungen(); }
    return;
  }
  if (S.einladungen.length === 0) {
    ziel.appendChild(el('p', 'hinweis', 'Noch keine Einladungen angelegt.'));
    return;
  }

  const tab = el('table', 'tabelle');
  const kopf = el('tr');
  for (const t of ['Kind', 'Hinweis', 'Status', '']) kopf.appendChild(el('th', null, t));
  tab.appendChild(kopf);
  for (const e of S.einladungen) {
    const tr = el('tr');
    tr.appendChild(el('td', null, e.kind_name
      || ('Schüler-ID ' + e.schueler_id)));
    tr.appendChild(el('td', null, e.hinweis || '–'));
    tr.appendChild(el('td', null,
      parseInt(e.erledigt, 10) === 1 ? 'Termin gebucht' : 'offen'));
    const td = el('td');
    td.appendChild(knopf('Löschen', 'klein gefahr', async () => {
      try {
        await api('/api/einladungen/' + e.id, { method: 'DELETE' });
        await ladeEinladungen();
      } catch (f) { meldung(String(f.message), 'fehler'); }
    }));
    tr.appendChild(td);
    tab.appendChild(tr);
  }
  ziel.appendChild(kartenTabelle(tab));
}

// Kind-Suche der Einladung. Treffer, Fehler oder „sucht“ stehen in
// S.einlTreffer; abgelaufen: Kasten „Anmelden und suchen“.
async function einlSuchen(q) {
  S.einlSuche = q;
  S.einlTreffer = { laeuft: true };
  zeichne();
  try {
    S.einlTreffer = await kinderSuchen(q);
  } catch (f) {
    S.einlTreffer = { fehler: String(f.message) };
    if (f.sitzung) {
      sitzungAuswerten(f.sitzung, String(f.message), { knopf: 'Anmelden und suchen',
        aktion: () => einlSuchen(q),
        spaeter: 'Nicht gesucht. Die Auswahl braucht die WebUntis-Anmeldung.' });
    }
  }
  zeichne();
}

// Treffer der Einladung: nach Klasse gruppiert, je Kind ein Ankreuzfeld.
function einlTrefferZeichnen(ziel) {
  const status = kinderStatusElement(S.einlTreffer);
  if (status) { ziel.appendChild(status); return; }
  const t = S.einlTreffer;
  for (const [klasse, kinder] of kinderNachKlasse(t.kinder)) {
    ziel.appendChild(el('h4', null, klasse + ' (' + kinder.length + ')'));
    const liste = el('div', 'schueler-liste');
    for (const k of kinder) {
      const zeile = el('label', 'schueler-zeile');
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = String(k.id);
      cb.className = 'einl-kind';
      zeile.appendChild(cb);
      zeile.appendChild(document.createTextNode(' ' + k.name));
      liste.appendChild(zeile);
    }
    ziel.appendChild(liste);
  }
  const mehr = kinderTrefferHinweis(t);
  if (mehr) ziel.appendChild(el('p', 'hinweis-klein', mehr));
  ziel.appendChild(feld('Hinweis an die Eltern (optional, gilt für alle)', 'einl-hinweis'));
  ziel.appendChild(knopf('Ausgewählte einladen', null, async () => {
    const ids = Array.from(document.querySelectorAll('.einl-kind:checked'))
      .map((e) => parseInt(e.value, 10)).filter((n) => n > 0);
    const hinweis = wert('einl-hinweis');
    if (ids.length === 0) {
      meldung('Bitte mindestens ein Kind auswählen.', 'fehler');
      return;
    }
    await einladenAusfuehren(ids, hinweis);
  }));
}

async function ladeEinladungen() {
  try {
    const d = await api('/api/einladungen?sprechtag=' + S.aktiverSprechtag.id);
    S.einladungen = d.einladungen || [];
    S.einlFehler = null;
    meldung(null);
  } catch (f) {
    // Leere Liste statt null, damit die Ansicht nicht endlos neu lädt.
    S.einladungen = [];
    S.einlFehler = String(f.message);
    meldung(String(f.message), 'fehler');
  } finally {
    S.einlLaedt = false;
    zeichne();
  }
}

// ============================================================
// ANSICHT: Administration
// ============================================================
// ---- Branding / Individualisierung --------------------------------------
function zeichneMarkeBlock(ziel) {
  const m = S.marke || {};
  const b = sektion('Erscheinungsbild (Logo, Texte)');
  b.appendChild(el('p', 'hinweis',
    'Passen Sie den Auftritt an Ihre Schule an. Änderungen gelten sofort '
    + 'für alle. Das Logo wird als Datei gespeichert (PNG, JPG oder SVG, '
    + 'max. 500 KB).'));

  b.appendChild(feld('Schulname', 'f-marke-schulname', 'text', m.marke_schulname || ''));
  b.appendChild(feld('Titel (Kopf und Browser-Tab)', 'f-marke-titel', 'text', m.marke_titel || ''));
  b.appendChild(feld('Untertitel', 'f-marke-untertitel', 'text', m.marke_untertitel || ''));
  b.appendChild(feld('Fußzeile', 'f-marke-fusszeile', 'text', m.marke_fusszeile || ''));
  const kf = feld('Kontakt für Rückfragen (z. B. E-Mail)', 'f-marke-kontakt', 'text',
    m.marke_kontakt || '');
  b.appendChild(kf);
  b.appendChild(el('p', 'hinweis-klein',
    'Erscheint in Hinweisen für Eltern (z. B. bei Anmeldeproblemen). Leer '
    + 'lassen zeigt einen neutralen Text ohne Adresse.'));

  // Keine Farbfelder mehr (docs/ENTSCHEIDUNGEN.md, E7): Sie bewirkten seit
  // dem CI-Umbau nichts. Der Satz erklärt der Verwaltung, warum es sie
  // nicht gibt.
  b.appendChild(el('p', 'hinweis-klein',
    'Eine Farbe lässt sich hier nicht einstellen: Die Akzentfarbe kennzeichnet '
    + 'die Anwendung, nicht die Schule – so sieht man bei mehreren offenen '
    + 'Tabs, in welcher man ist.'));

  // ---- Logo: Vorschau + Upload + Entfernen ----
  const logoZeile = el('div', 'marke-logo-zeile');
  if (m.hat_logo) {
    const img = document.createElement('img');
    img.src = '/api/einstellungen/logo?' + Date.now();
    img.alt = 'Aktuelles Logo';
    img.className = 'marke-logo-vorschau';
    logoZeile.appendChild(img);
  } else {
    logoZeile.appendChild(el('span', 'hinweis-klein', 'Kein Logo hinterlegt.'));
  }
  b.appendChild(logoZeile);

  const datei = feld('Logo hochladen (PNG, JPG, SVG)', 'marke-logo-datei', 'file');
  datei.querySelector('input').accept = 'image/png,image/jpeg,image/svg+xml';
  b.appendChild(datei);

  const knoepfe = el('div', 'zeile');
  knoepfe.appendChild(knopf('Speichern', null, () => markeSpeichern()));
  knoepfe.appendChild(knopf('Logo hochladen', 'klein', () => markeLogoHochladen()));
  if (m.hat_logo) {
    knoepfe.appendChild(knopf('Logo entfernen', 'klein gefahr', () => markeLogoEntfernen()));
  }
  knoepfe.appendChild(knopf('Auf Standard zurücksetzen', 'klein', () => markeZuruecksetzen()));
  b.appendChild(knoepfe);

  ziel.appendChild(b);
}

async function markeSpeichern() {
  toast('Speichern …', 'info');   // sofortiges Signal, dass der Klick ankommt
  const gesendet = {
    marke_schulname:  wert('f-marke-schulname'),
    marke_titel:      wert('f-marke-titel'),
    marke_untertitel: wert('f-marke-untertitel'),
    marke_fusszeile:  wert('f-marke-fusszeile'),
    marke_kontakt:    wert('f-marke-kontakt'),
  };
  try {
    await api('/api/einstellungen', { method: 'POST', body: gesendet });
    S.marke = await api('/api/einstellungen');
    wendeMarkeAn(S.marke);
    // Prüfen, ob der Kontakt wirklich ankam (deckt einen veralteten Server auf,
    // der marke_kontakt noch nicht kennt).
    const kontaktErwartet = gesendet.marke_kontakt;
    const kontaktGespeichert = (S.marke && S.marke.marke_kontakt) || '';
    zeichne();
    if (kontaktErwartet && kontaktGespeichert !== kontaktErwartet) {
      toast('Gespeichert – aber das Kontaktfeld kam nicht an. Läuft schon '
        + 'v0.9.34 auf dem Server? Bitte Deploy prüfen.', 'fehler');
    } else {
      toast('Erscheinungsbild gespeichert.', 'ok');
    }
  } catch (f) { toast(String(f.message), 'fehler'); }
}

async function markeLogoHochladen() {
  const inp = $('#marke-logo-datei');
  const datei = inp && inp.files && inp.files[0];
  if (!datei) { meldung('Bitte zuerst eine Bilddatei wählen.', 'fehler'); return; }
  if (datei.size > 500 * 1024) {
    meldung('Das Logo darf maximal 500 KB groß sein.', 'fehler'); return;
  }
  meldung('Logo wird hochgeladen …', 'info');
  try {
    const base64 = await dateiAlsBase64(datei);
    await api('/api/einstellungen/logo', { method: 'POST', body: {
      daten: base64, mime_type: datei.type, dateiname: datei.name } });
    S.marke = await api('/api/einstellungen');
    wendeMarkeAn(S.marke);
    meldung('Logo hochgeladen.', 'ok');
    zeichne();
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

async function markeLogoEntfernen() {
  if (!confirm('Logo wirklich entfernen?')) return;
  try {
    await api('/api/einstellungen/logo', { method: 'DELETE' });
    S.marke = await api('/api/einstellungen');
    wendeMarkeAn(S.marke);
    meldung('Logo entfernt.', 'ok');
    zeichne();
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

async function markeZuruecksetzen() {
  if (!confirm('Erscheinungsbild auf Standardwerte zurücksetzen?')) return;
  try {
    const d = await api('/api/einstellungen/zuruecksetzen', { method: 'POST' });
    S.marke = d.marke || await api('/api/einstellungen');
    wendeMarkeAn(S.marke);
    meldung('Auf Standard zurückgesetzt.', 'ok');
    zeichne();
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

// Liest eine Datei als reines Base64 (ohne data:-Präfix).
function dateiAlsBase64(datei) {
  return new Promise((ok, fehler) => {
    const r = new FileReader();
    r.onload = () => {
      const s = String(r.result);
      const komma = s.indexOf(',');
      ok(komma >= 0 ? s.slice(komma + 1) : s);
    };
    r.onerror = () => fehler(new Error('Datei konnte nicht gelesen werden.'));
    r.readAsDataURL(datei);
  });
}

// Lädt die gewählten Kinder nacheinander ein. Seit v0.9.76 (Zug 4, E20 C)
// braucht jede Einladung die WebUntis-Sitzung – Name und Klasse kommen von
// dort. Ist sie nicht nutzbar, wird NICHT eingeladen: Der Lauf hält beim
// ersten solchen Kind an; bei abgelaufener Sitzung bietet der Kasten
// „Anmelden und einladen“ an und lädt danach genau die übrigen ein.
async function einladenAusfuehren(ids, hinweis) {
  meldung(ids.length + ' Einladung(en) werden angelegt …', 'info');
  let ok = 0; let benachrichtigt = 0;
  const probleme = [];
  const liegen = [];      // gespeichert, aber nicht verschickt (Kennungen)
  let ursache = null;     // abgelaufen / nicht_erreichbar / kaputt
  let liegenGrund = '';
  for (let i = 0; i < ids.length; i++) {
    try {
      const d = await api('/api/einladungen', { method: 'POST', body: {
        sprechtag_id: S.aktiverSprechtag.id, schueler_id: ids[i],
        hinweis } });
      ok++;
      const m = d.mitteilung;
      if (m && m.status === 'gesendet') benachrichtigt++;
      else if (m) {
        liegen.push(...(m.ids || []));
        ursache = ursache || m.sitzung;
        liegenGrund = liegenGrund || m.grund;
      }
    } catch (f) {
      if (f.sitzung) {
        const uebrig = ids.slice(i);
        await ladeEinladungen();
        const text = (ok > 0 ? ok + ' Einladung(en) angelegt. ' : '')
          + uebrig.length + ' noch NICHT eingeladen: ' + f.message;
        if (sitzungAuswerten(f.sitzung, text, { knopf: 'Anmelden und einladen',
          aktion: () => einladenAusfuehren(uebrig, hinweis),
          spaeter: 'Nicht eingeladen. Bitte die Kinder nach der nächsten Anmeldung '
            + 'erneut auswählen.' })) return;
      }
      // Fehler NICHT verschlucken – sonst bleibt unklar, warum
      // nichts passiert ist.
      probleme.push(String(f.message));
    }
  }
  await ladeEinladungen();
  if (probleme.length === 0) {
    let text = ok + ' Einladung(en) angelegt';
    if (benachrichtigt > 0) {
      text += ', bei ' + benachrichtigt + ' die Erziehungsberechtigten benachrichtigt';
    }
    text += '.';
    if (liegen.length > 0) {
      text += ' ' + liegen.length + ' Mitteilung(en) sind gespeichert, aber noch '
        + 'NICHT verschickt: ' + liegenGrund;
      if (sitzungAuswerten(ursache, text, { knopf: 'Anmelden und senden',
        aktion: () => sendeVorgemerkte(liegen) })) return;
    }
    meldung(text, liegen.length > 0 ? 'fehler' : 'ok');
  } else {
    const einmalig = [...new Set(probleme)];
    meldung(ok + ' angelegt, ' + probleme.length + ' fehlgeschlagen: '
      + einmalig.slice(0, 2).join(' | '), 'fehler');
  }
}

// Admin-Unterseiten – die Sidebar ruft sie direkt über S.ansicht auf.
// (Der alte Sammel-Screen 'admin' zeigt weiterhin das Erscheinungsbild.)
function ansichtAdmin(ziel) { ansichtAdminMarke(ziel); }

function ansichtAdminMarke(ziel) {
  ziel.appendChild(el('h2', null, 'Erscheinungsbild'));
  zeichneMarkeBlock(ziel);
}

// Bis v0.9.81 stand hier zuerst die alte Schülerliste (Abgleich mit
// eingetippten Zugangsdaten, Schild-CSV, Löschen). Sie ist mit Zug 4,
// Schritt 4 fort (v0.9.82, E20); die Seite zeigt nur noch die Gruppen.
function ansichtAdminDaten(ziel) {
  ziel.appendChild(el('h2', null, 'Volljährige Schüler'));
  zeichneSchuelerGruppen(ziel);
}

// ---- Volljährige Schüler: zugelassene Benutzergruppen (E15) -------------
// Wer als Schülerin oder Schüler selbst buchen darf, entscheidet die
// WebUntis-Benutzergruppe. WebUntis kürzt Gruppennamen auf 20 Zeichen; die
// Seite sagt das, zeigt, wie verglichen wird, und nennt die Gruppe des
// eigenen Kontos – zum Abschreiben statt Raten.
function zeichneSchuelerGruppen(ziel) {
  const b = sektion('Volljährige Schülerinnen und Schüler',
    'Schülerinnen und Schüler können selbst buchen, wenn ihre WebUntis-'
    + 'Benutzergruppe hier steht – sonst buchen die Erziehungsberechtigten. '
    + 'Anmelden können sich alle, die WebUntis zulässt. Eltern und Lehrkräfte '
    + 'betrifft diese Liste nicht.');
  ziel.appendChild(b);
  // Scheitert das Laden, steht der Fehler da – kein erneuter Abruf bei jedem
  // Neuzeichnen (v0.9.66: im Betrieb blieb es bei „Wird geladen …“, während
  // die Route mit 500 antwortete und die Ansicht erneut abrief).
  if (S.sgFehler) {
    b.appendChild(el('p', 'hinweis-wichtig',
      'Die Liste konnte nicht geladen werden: ' + S.sgFehler));
    b.appendChild(knopf('Erneut laden', 'klein', () => { S.sgFehler = null; zeichne(); }));
    return;
  }
  if (S.sgDaten === null) {
    b.appendChild(el('p', 'hinweis', 'Wird geladen …'));
    if (!S.sgLaedt) {
      S.sgLaedt = true;
      api('/api/schueler-gruppen').then((d) => { S.sgDaten = d; S.sgLaedt = false; zeichne(); })
        .catch((f) => { S.sgLaedt = false; S.sgFehler = String(f.message) || 'unbekannter Fehler'; zeichne(); });
    }
    return;
  }
  const d = S.sgDaten;
  b.appendChild(el('p', 'hinweis', d.eigene_gruppe
    ? 'Ihr eigenes Konto trägt in WebUntis die Gruppe „' + d.eigene_gruppe + '“ – '
      + 'so, wie WebUntis sie liefert.'
    : 'Für Ihr eigenes Konto wurde keine Gruppe ermittelt (erneut anmelden).'));
  // Drei Darstellungen desselben Namens (Betreiber, 09.10.2026): Liste,
  // Anmeldung und Mitteilungs-Filter kürzen auf 20 Zeichen, nur der
  // persönliche Bereich in WebUntis zeigt den vollen Namen.
  b.appendChild(el('p', 'hinweis', 'WebUntis kürzt Gruppennamen auf ' + d.laenge
    + ' Zeichen – so stehen sie hier und so werden sie bei der Anmeldung '
    + 'verglichen. Im persönlichen Bereich zeigt WebUntis den vollständigen Namen '
    + '(etwa „… mit Attest“ statt „… mit Atte“); es ist dieselbe Gruppe.'));

  const gewaehlt = d.gruppen || [];
  const auswahl = Array.isArray(d.auswahl) ? d.auswahl : null;
  const kaesten = [];   // [Kästchen, Gruppenname] – in Anzeigereihenfolge
  let ta = null;
  if (auswahl) {
    // Auswahl aus userrole/config (v0.9.68): Gruppen mit Schülern zuerst,
    // mit Anzahl; die übrigen dahinter, nicht ausgeblendet.
    const kaestchen = (ziel2, name, text) => {
      const zeile = el('label', 'check-zeile');
      const cb = el('input');
      cb.type = 'checkbox';
      cb.checked = gewaehlt.includes(name);
      zeile.appendChild(cb);
      zeile.appendChild(el('span', null, text));
      ziel2.appendChild(zeile);
      kaesten.push([cb, name]);
    };
    const mit = auswahl.filter((g) => g.schueler > 0);
    const ohne = auswahl.filter((g) => !(g.schueler > 0));
    b.appendChild(el('h4', null, 'Gruppen mit Schülerinnen und Schülern'));
    for (const g of mit) {
      kaestchen(b, g.label, g.label + ' — ' + g.schueler + ' Schüler');
    }
    if (ohne.length > 0) {
      const w = block('sg-weitere', 'Weitere Gruppen, heute ohne Schüler (' + ohne.length + ')');
      for (const g of ohne) kaestchen(w, g.label, g.label + ' — keine Schüler');
      b.appendChild(w);
    }
    for (const name of gewaehlt.filter((n) => !auswahl.some((g) => g.label === n))) {
      kaestchen(b, name, name + ' — nicht in der Liste von WebUntis');
    }
  } else {
    if (d.auswahl_fehler) {
      b.appendChild(el('p', 'hinweis-wichtig', 'Die Gruppenliste aus WebUntis ließ sich '
        + 'nicht abrufen: ' + d.auswahl_fehler + '. Gruppen bitte eintippen, eine je Zeile.'));
    }
    ta = el('textarea');
    ta.id = 'sg-text';
    ta.rows = 4;
    ta.value = gewaehlt.join('\n');
    const lab = el('label', null, 'Zugelassene Gruppen (eine je Zeile)');
    lab.appendChild(ta);
    b.appendChild(lab);
  }

  // Was verglichen wird – damit ein Fehlgriff auffällt, statt still zu wirken.
  if (gewaehlt.length === 0) {
    b.appendChild(el('p', 'hinweis-wichtig',
      'Keine Gruppe eingetragen – derzeit kann keine Schülerin und kein Schüler selbst buchen.'));
  } else {
    const p = el('p', 'hinweis', 'So wird verglichen: ');
    gewaehlt.forEach((g, i) => {
      if (i > 0) p.appendChild(el('span', null, ', '));
      p.appendChild(el('code', null, g));
    });
    b.appendChild(p);
    for (const name of gewaehlt) {
      const g = auswahl ? auswahl.find((x) => x.label === name) : null;
      if (auswahl && !g) {
        b.appendChild(el('p', 'hinweis-wichtig', '„' + name + '“ steht nicht in der Liste von '
          + 'WebUntis – Name prüfen; so trifft der Vergleich womöglich niemanden.'));
      } else if (g && g.userRole !== -1) {
        // Gemessen 09.10.2026: Bei systemeigenen Gruppen lieferte die Liste
        // „Admin“, die Anmeldung „Administration“ – der Abgleich ist dort
        // nicht gesichert. Bei schuleigenen (userRole −1) nicht gemessen.
        b.appendChild(el('p', 'hinweis-wichtig', '„' + name + '“ ist eine systemeigene Gruppe '
          + 'von WebUntis. Bei solchen Gruppen kann der Name bei der Anmeldung anders lauten '
          + '(gemessen: „Admin“ hier, „Administration“ bei der Anmeldung) – dann trifft der '
          + 'Vergleich nicht.'));
      }
    }
  }

  b.appendChild(knopf('Speichern', null, async () => {
    const text = ta ? ta.value
      : kaesten.filter(([cb]) => cb.checked).map(([, name]) => name).join('\n');
    try {
      const r = await api('/api/schueler-gruppen', { method: 'POST', body: { gruppen: text } });
      // Die Auswahl bleibt; die Antwort trägt nur die gespeicherten Gruppen.
      S.sgDaten = Object.assign({}, S.sgDaten, r);
      toast((r.gekuerzt || []).length ? gruppenGekuerztText(r.gekuerzt) : 'Gespeichert.',
        (r.gekuerzt || []).length ? 'info' : 'ok');
      zeichne();
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));
}

// Meldet, welche Einträge auf 20 Zeichen gekürzt wurden – mit beiden Fassungen.
function gruppenGekuerztText(liste) {
  return 'Gespeichert. Gekürzt wie in WebUntis: '
    + liste.map((g) => '„' + g.eingegeben + '“ → „' + g.verglichen + '“').join(', ') + '.';
}

// Ermittelt den „aktiven" Sprechtag: in Phase 1/2, mit dem nächstliegenden
// Datum. Gibt null zurück, wenn keiner aktiv ist.
function aktiverSprechtagFinden() {
  const offen = (S.sprechtage || []).filter(
    (s) => s.phase === 'phase1' || s.phase === 'phase2');
  if (offen.length === 0) return null;
  offen.sort((a, b) => String(a.datum).localeCompare(String(b.datum)));
  return offen[0];
}

function ansichtAdminAnzeige(ziel) {
  ziel.appendChild(el('h2', null, 'Anzeige (Info-Monitor)'));

  const b = sektion('Öffentliche Raumübersicht');
  b.appendChild(el('p', 'hinweis',
    'Die öffentliche Anzeige unter /anzeige zeigt die Raumaufteilung des '
    + 'aktiven Sprechtags auf einem Info-Monitor – ohne Anmeldung, mit '
    + 'automatischem Weiterblättern (alle 10 Sekunden) und Aktualisierung.'));

  const sig = el('p', null);
  sig.appendChild(document.createTextNode('Monitor-Adresse: '));
  const a = el('a', null, location.origin + '/anzeige');
  a.href = '/anzeige'; a.target = '_blank';
  sig.appendChild(a);
  b.appendChild(sig);

  b.appendChild(el('h4', null, 'Sortierung der Kacheln'));
  b.appendChild(el('p', 'hinweis',
    'Nach Raum gruppiert räumlich zusammengehörige Lehrkräfte; nach Kürzel '
    + 'ist die Reihenfolge alphabetisch.'));
  const wahl = auswahl('Sortieren nach', 'anzeige-sortierung',
    [{ wert: 'raum', text: 'Raum' }, { wert: 'kuerzel', text: 'Kürzel' }],
    (S.anzeigeEinst && S.anzeigeEinst.sortierung) || 'raum');
  b.appendChild(wahl);

  b.appendChild(el('h4', null, 'Kacheln pro Seite'));
  b.appendChild(el('p', 'hinweis',
    '„Automatisch" füllt den Bildschirm optimal und passt sich an jede '
    + 'Monitorgröße an. Alternativ eine feste Zahl vorgeben.'));
  const ke = S.anzeigeEinst || {};
  const autoAn = !ke.kacheln || ke.kacheln === 'auto';
  const kachelWahl = auswahl('Kachelmenge', 'anzeige-kacheln-modus',
    [{ wert: 'auto', text: 'Automatisch (empfohlen)' },
     { wert: 'fest', text: 'Feste Anzahl' }], autoAn ? 'auto' : 'fest');
  b.appendChild(kachelWahl);
  const festZeile = el('div', 'zeile');
  festZeile.id = 'anzeige-fest-zeile';
  if (autoAn) festZeile.classList.add('versteckt');
  festZeile.appendChild(feld('Anzahl (1–60)', 'anzeige-kacheln-zahl', 'number',
    autoAn ? '24' : String(ke.kacheln || 24)));
  b.appendChild(festZeile);
  kachelWahl.querySelector('select').addEventListener('change', (e) => {
    festZeile.classList.toggle('versteckt', e.target.value !== 'fest');
  });

  b.appendChild(el('h4', null, 'Umblätter-Intervall'));
  const iv = auswahl('Sekunden pro Seite', 'anzeige-intervall',
    [{ wert: '5', text: '5 Sekunden' }, { wert: '10', text: '10 Sekunden' },
     { wert: '15', text: '15 Sekunden' }, { wert: '20', text: '20 Sekunden' }],
    String((S.anzeigeEinst && S.anzeigeEinst.intervall) || 10));
  b.appendChild(iv);

  b.appendChild(knopf('Speichern', null, async () => {
    const modus = wert('anzeige-kacheln-modus');
    const koerper = {
      sortierung: wert('anzeige-sortierung'),
      intervall: parseInt(wert('anzeige-intervall'), 10),
      kacheln: modus === 'auto' ? 'auto'
        : String(parseInt(wert('anzeige-kacheln-zahl'), 10) || 24),
    };
    try {
      const d = await api('/api/anzeige-einstellungen',
        { method: 'POST', body: koerper });
      S.anzeigeEinst = d;
      toast('Anzeige-Einstellungen gespeichert.', 'ok');
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));
  ziel.appendChild(b);

  // Aktuelle Einstellung nachladen, falls noch nicht bekannt.
  if (!S.anzeigeEinst) {
    api('/api/anzeige-einstellungen').then((d) => {
      S.anzeigeEinst = d;
      zeichne();
    }).catch(() => {});
  }
}

function ansichtAdminAktiv(ziel) {
  ziel.appendChild(el('h2', null, 'Aktiver Sprechtag'));
  const s = aktiverSprechtagFinden();
  if (!s) {
    ziel.appendChild(el('p', 'hinweis',
      'Zurzeit ist kein Sprechtag aktiv (keiner in Phase 1 oder 2). Unter '
      + '„Sprechtage" lässt sich einer anlegen oder eine Phase starten.'));
    return;
  }
  ziel.appendChild(el('p', 'hinweis',
    'Verwaltung des laufenden Sprechtags „' + s.name + '" (' + s.datum + ', '
    + phaseText(s.phase) + '). Die Lehrkraft-Tabelle ist direkt geöffnet.'));

  const karte = sprechtagKarte(s);
  // Karte aufgeklappt zeigen (block() merkt sich den Zustand pro Kennung).
  S.offeneBloecke['st' + s.id] = true;
  const det = karte.tagName === 'DETAILS' ? karte : karte.querySelector('details');
  if (det) det.open = true;
  ziel.appendChild(karte);

  // Hinweis auf den öffentlichen Anzeige-Modus (Signage).
  const sig = el('p', 'hinweis-klein');
  sig.appendChild(document.createTextNode('Für einen Info-Monitor im Foyer: '));
  const a = el('a', null, 'Anzeige-Modus öffnen');
  a.href = '/anzeige'; a.target = '_blank';
  sig.appendChild(a);
  sig.appendChild(document.createTextNode(
    ' – zeigt die Raumaufteilung ohne Anmeldung, aktualisiert sich selbst.'));
  ziel.appendChild(sig);
  // Lehrkraft-Verwaltung sofort aufklappen – das ist der häufigste Arbeitsweg.
  setTimeout(() => oeffneLehrerVerwaltung(s), 0);
}

function ansichtAdminSprechtage(ziel) {
  ziel.appendChild(el('h2', null, 'Sprechtage'));

  // ---- Stammdaten aus WebUntis (global, einmal je Schuljahr) -----------
  const sync = sektion('Stammdaten aus WebUntis übernehmen');
  sync.appendChild(el('p', 'hinweis',
    'Holt Lehrkräfte und Räume aus WebUntis. Zugangsdaten werden nur für '
    + 'diesen Abruf verwendet und nicht gespeichert. Beim ersten Lauf bitte '
    + 'prüfen, ob die Zahlen zum Kollegium passen. Ob jemand Halbtagskraft '
    + 'ist, wird direkt in der Lehrer-Tabelle des Sprechtags markiert.'));
  const sf = el('div', 'zeile');
  sf.appendChild(feld('WebUntis-Benutzername', 'sync-benutzer'));
  sf.appendChild(feld('Passwort', 'sync-passwort', 'password'));
  sync.appendChild(sf);
  sync.appendChild(knopf('Synchronisieren', null, async () => {
    const zugang = { benutzername: wert('sync-benutzer'),
                     passwort: wert('sync-passwort') };
    if (zugang.benutzername === '' || zugang.passwort === '') {
      meldung('Bitte Benutzername und Passwort eingeben.', 'fehler');
      return;
    }
    meldung('Synchronisierung läuft …', 'info');
    try {
      const d = await api('/api/stammdaten/sync', { method: 'POST', body: zugang });
      await ladeStammdaten();
      meldung('Übernommen: ' + d.lehrer + ' Lehrkräfte, ' + d.raeume + ' Räume.', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));
  ziel.appendChild(sync);

  // ---- Sprechtag anlegen ------------------------------------------------
  const neu = sektion('Neuen Sprechtag anlegen');
  const nf = el('div');
  nf.appendChild(feld('Bezeichnung', 'neu-name', 'text',
    'Elternsprechtag ' + new Date().getFullYear()));
  const z1 = el('div', 'zeile');
  z1.appendChild(feld('Datum', 'neu-datum', 'date'));
  z1.appendChild(feld('Beginn', 'neu-beginn', 'text', '15:00'));
  z1.appendChild(feld('Ende', 'neu-ende', 'text', '19:00'));
  nf.appendChild(z1);
  const z2 = el('div', 'zeile');
  z2.appendChild(feld('Slotlänge (Minuten)', 'neu-slot', 'text', '10'));
  z2.appendChild(feld('Max. Termine je Elternteil', 'neu-max', 'text', '6'));
  nf.appendChild(z2);
  const z3 = el('div', 'zeile');
  z3.appendChild(feld('Pause nach x Terminen (0 = keine)', 'neu-pausen', 'text', '0'));
  z3.appendChild(feld('Pausenlänge (Minuten)', 'neu-pausenlang', 'text', '10'));
  nf.appendChild(z3);
  const dynLabel = el('label', 'check-zeile');
  const dynCb = document.createElement('input');
  dynCb.type = 'checkbox'; dynCb.id = 'neu-pause-dyn';
  dynLabel.appendChild(dynCb);
  dynLabel.appendChild(document.createTextNode(
    ' Pause nur bei durchgehender Belegung (dynamisch)'));
  nf.appendChild(dynLabel);
  nf.appendChild(el('p', 'hinweis-klein',
    'Fest: Die Pause liegt immer zur selben Uhrzeit. Dynamisch: Eine Pause '
    + 'entsteht nur, wenn davor x Termine ohne Lücke gebucht wurden – sonst '
    + 'bleibt die Zeit buchbar. Bereits gebuchte Zeiten verschieben sich nie.'));
  neu.appendChild(nf);
  neu.appendChild(knopf('Anlegen', null, async () => {
    try {
      await api('/api/sprechtage', { method: 'POST', body: {
        name: wert('neu-name'), datum: wert('neu-datum'),
        beginn: wert('neu-beginn'), ende: wert('neu-ende'),
        slot_minuten: parseInt(wert('neu-slot'), 10) || 10,
        max_termine_pro_eltern: parseInt(wert('neu-max'), 10) || 6,
        pause_nach_terminen: parseInt(wert('neu-pausen'), 10) || 0,
        pause_minuten: parseInt(wert('neu-pausenlang'), 10) || 10,
        pause_dynamisch: $('#neu-pause-dyn').checked ? 1 : 0 } });
      await ladeSprechtage();
      meldung('Sprechtag angelegt.', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));
  ziel.appendChild(neu);

  // ---- Vorhandene Sprechtage --------------------------------------------
  ziel.appendChild(el('h3', null, 'Sprechtage'));
  if (S.sprechtage.length === 0) {
    ziel.appendChild(el('p', 'hinweis', 'Noch kein Sprechtag angelegt.'));
  }
  for (const s of S.sprechtage) {
    ziel.appendChild(sprechtagKarte(s));
  }
}

function sprechtagKarte(s) {
  const k = block('st' + s.id,
    s.name + ' – ' + s.datum + ' (' + phaseText(s.phase) + ')');

  // Parameter
  const p = el('div');
  const z1 = el('div', 'zeile');
  z1.appendChild(feld('Bezeichnung', 'e-name-' + s.id, 'text', s.name));
  z1.appendChild(feld('Datum', 'e-datum-' + s.id, 'date', s.datum));
  p.appendChild(z1);
  const z2 = el('div', 'zeile');
  z2.appendChild(feld('Beginn', 'e-beginn-' + s.id, 'text', String(s.beginn).slice(0, 5)));
  z2.appendChild(feld('Ende', 'e-ende-' + s.id, 'text', String(s.ende).slice(0, 5)));
  z2.appendChild(feld('Slot (Min.)', 'e-slot-' + s.id, 'text', s.slot_minuten));
  p.appendChild(z2);
  const z3 = el('div', 'zeile');
  z3.appendChild(feld('Max. Termine/Eltern', 'e-max-' + s.id, 'text',
    s.max_termine_pro_eltern));
  z3.appendChild(feld('Pause nach x', 'e-pausen-' + s.id, 'text', s.pause_nach_terminen));
  z3.appendChild(feld('Pause (Min.)', 'e-pausenlang-' + s.id, 'text', s.pause_minuten));
  p.appendChild(z3);
  const dynL = el('label', 'check-zeile');
  const dynC = document.createElement('input');
  dynC.type = 'checkbox'; dynC.id = 'e-pausedyn-' + s.id;
  dynC.checked = parseInt(s.pause_dynamisch, 10) === 1;
  dynL.appendChild(dynC);
  dynL.appendChild(document.createTextNode(
    ' Pause nur bei durchgehender Belegung (dynamisch)'));
  p.appendChild(dynL);
  const z4 = el('div', 'zeile');
  z4.appendChild(feld('Referenz von', 'e-refvon-' + s.id, 'date', s.referenz_von || ''));
  z4.appendChild(feld('Referenz bis', 'e-refbis-' + s.id, 'date', s.referenz_bis || ''));
  p.appendChild(z4);

  // Klausur-Schalter
  const z5 = el('div', 'zeile');
  const lKl = el('label', 'inline');
  const cbKl = document.createElement('input');
  cbKl.type = 'checkbox';
  cbKl.id = 'e-klausuren-' + s.id;
  cbKl.checked = parseInt(s.klausuren_werten, 10) !== 0;
  lKl.appendChild(cbKl);
  lKl.appendChild(document.createTextNode(
    ' Klausurtermine bei der Lehrkraftermittlung mitwerten'));
  z5.appendChild(lKl);
  p.appendChild(z5);
  p.appendChild(el('p', 'hinweis-klein',
    'Sinnvoll, wenn Fachlehrkräfte ihre eigenen Arbeiten beaufsichtigen. '
    + 'Abschalten, wenn Aufsichten bei Ihnen fachfremd verteilt werden.'));

  const z4b = el('div', 'zeile');
  z4b.appendChild(auswahl('Phase', 'e-phase-' + s.id, [
    { wert: 'vorbereitung', text: 'in Vorbereitung' },
    { wert: 'phase1', text: 'Phase 1 – nur auf Einladung' },
    { wert: 'phase2', text: 'Phase 2 – offen für alle' },
    { wert: 'geschlossen', text: 'geschlossen' },
    { wert: 'archiviert', text: 'archiviert (löscht Buchungen!)' },
  ], s.phase));
  p.appendChild(z4b);
  k.appendChild(p);

  const a = el('div', 'aktionen');
  a.appendChild(knopf('Speichern', null, async () => {
    const neuePhase = wert('e-phase-' + s.id);
    if (neuePhase === 'archiviert' && s.phase !== 'archiviert') {
      if (!confirm('Archivieren löscht alle Buchungen, Einladungen und die '
        + 'Lehrkraft-Zuordnung dieses Sprechtags (Datenschutz). '
        + 'Die Struktur (Lehrkräfte, Räume, Sonderrollen) bleibt erhalten. '
        + 'Fortfahren?')) return;
    }
    try {
      await api('/api/sprechtage/' + s.id, { method: 'PATCH', body: {
        name: wert('e-name-' + s.id), datum: wert('e-datum-' + s.id),
        beginn: wert('e-beginn-' + s.id), ende: wert('e-ende-' + s.id),
        slot_minuten: parseInt(wert('e-slot-' + s.id), 10),
        max_termine_pro_eltern: parseInt(wert('e-max-' + s.id), 10),
        pause_nach_terminen: parseInt(wert('e-pausen-' + s.id), 10),
        pause_minuten: parseInt(wert('e-pausenlang-' + s.id), 10),
        pause_dynamisch: $('#e-pausedyn-' + s.id).checked ? 1 : 0,
        referenz_von: wert('e-refvon-' + s.id) || null,
        referenz_bis: wert('e-refbis-' + s.id) || null,
        klausuren_werten: cbKl.checked ? 1 : 0,
        phase: neuePhase } });
      await ladeSprechtage();
      meldung('Gespeichert.', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));
  a.appendChild(knopf('Kopieren', 'klein', async () => {
    const datum = prompt('Datum des neuen Sprechtags (JJJJ-MM-TT):', s.datum);
    if (!datum) return;
    try {
      await api('/api/sprechtage/' + s.id + '/kopieren', { method: 'POST',
        body: { datum } });
      await ladeSprechtage();
      meldung('Kopie angelegt (ohne Buchungen).', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));
  a.appendChild(knopf('Lehrkräfte & Räume', 'klein', () => oeffneLehrerVerwaltung(s)));
  a.appendChild(knopf('Sonderlehrkräfte', 'klein', () => oeffneSonderlehrer(s)));
  a.appendChild(knopf('Löschen', 'klein gefahr', async () => {
    if (!confirm('Sprechtag "' + s.name + '" endgültig löschen?')) return;
    try {
      await api('/api/sprechtage/' + s.id, { method: 'DELETE' });
      await ladeSprechtage();
      meldung('Gelöscht.', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));
  k.appendChild(a);

  const detail = el('div', 'detail-bereich');
  detail.id = 'detail-' + s.id;
  k.appendChild(detail);
  return k;
}

// ---- Lehrkräfte: Teilnahme, Zeitfenster, Räume ---------------------------
// Baut die Anwesenheits-Tabellenzelle einer Lehrkraft.
//  - Halbtagskraft (½): Hälfte-Dropdown.
//  - sonst Standard „ganzer Tag"; ein Häkchen „nur zeitweise" blendet erst
//    dann die von/bis-Felder ein (verschlankt die Übersicht).
function anwesenheitZelle(s, l) {
  // Eigene Klasse: Die Zelle bricht nicht um – Uhr-Knopf und Zeitfelder
  // bleiben in einer Zeile, die Zeile behält ihre Höhe (v0.9.62).
  const td = el('td', 'anwesenheit-zelle');
  const halbtags = parseInt(l.halbtags, 10) === 1;

  if (halbtags) {
    const sel = document.createElement('select');
    sel.id = 'haelfte-' + s.id + '-' + l.lehrer_id;
    const von = String(l.anwesend_von || '').slice(0, 5);
    const bis = String(l.anwesend_bis || '').slice(0, 5);
    const beginn = String(s.beginn || '').slice(0, 5);
    const ende = String(s.ende || '').slice(0, 5);
    let aktuell = 'ganz';
    if (von && bis) {
      if (von === beginn && bis !== ende) aktuell = 'erste';
      else if (von !== beginn && bis === ende) aktuell = 'zweite';
    }
    for (const [w, t] of [['ganz', 'ganzer Tag'], ['erste', 'erste Hälfte'],
                          ['zweite', 'zweite Hälfte']]) {
      const o = document.createElement('option');
      o.value = w; o.textContent = t;
      if (w === aktuell) o.selected = true;
      sel.appendChild(o);
    }
    td.appendChild(sel);
    return td;
  }

  // Nicht-Halbtags: Standard ganzer Tag, optional Zeitfenster per Uhr-Symbol.
  const hatFenster = !!(l.anwesend_von || l.anwesend_bis);
  const opt = el('button', 'zeitfenster-schalter' + (hatFenster ? ' an' : ''), '⏱');
  opt.type = 'button';
  opt.id = 'zf-' + s.id + '-' + l.lehrer_id;
  opt.title = 'Zeitfenster festlegen (sonst ganzer Tag)';
  opt.setAttribute('aria-pressed', hatFenster ? 'true' : 'false');
  // Zustand am Element merken, damit lehrerZeileDaten es auslesen kann.
  opt.dataset.an = hatFenster ? '1' : '0';
  td.appendChild(opt);
  if (!hatFenster) td.appendChild(el('span', 'zeitfenster-label', ' ganzer Tag'));

  const felder = el('div', 'zeitfenster-felder');
  if (!hatFenster) felder.classList.add('versteckt');
  const mkZeit = (id, w) => {
    const i = document.createElement('input');
    i.type = 'text'; i.id = id; i.className = 'zeit-feld';
    i.value = w ? String(w).slice(0, 5) : '';
    i.placeholder = '--:--';
    return i;
  };
  felder.appendChild(mkZeit('von-' + s.id + '-' + l.lehrer_id, l.anwesend_von));
  felder.appendChild(document.createTextNode(' – '));
  felder.appendChild(mkZeit('bis-' + s.id + '-' + l.lehrer_id, l.anwesend_bis));
  td.appendChild(felder);

  opt.addEventListener('click', () => {
    const an = opt.dataset.an !== '1';
    opt.dataset.an = an ? '1' : '0';
    opt.classList.toggle('an', an);
    opt.setAttribute('aria-pressed', an ? 'true' : 'false');
    felder.classList.toggle('versteckt', !an);
    const label = td.querySelector('.zeitfenster-label');
    if (label) label.remove();
    if (an) {
      const v = felder.querySelector('#von-' + s.id + '-' + l.lehrer_id);
      const b = felder.querySelector('#bis-' + s.id + '-' + l.lehrer_id);
      if (v && !v.value) v.value = String(s.beginn || '').slice(0, 5);
      if (b && !b.value) b.value = String(s.ende || '').slice(0, 5);
    } else {
      td.insertBefore(el('span', 'zeitfenster-label', ' ganzer Tag'),
        felder);
    }
  });
  return td;
}

async function oeffneLehrerVerwaltung(s) {
  const ziel = $('#detail-' + s.id);
  if (!ziel) return;
  ziel.textContent = '';
  if (S.stammdaten.raeume.length === 0) await ladeStammdaten();

  let daten;
  try {
    daten = await api('/api/sprechtage/' + s.id + '/lehrer');
  } catch (f) { meldung(String(f.message), 'fehler'); return; }

  let konflikte = {};
  try {
    konflikte = (await api('/api/sprechtage/' + s.id + '/raumkonflikte')).konflikte || {};
  } catch { }

  ziel.appendChild(el('h4', null, 'Lehrkräfte, Anwesenheit und Räume'));
  ziel.appendChild(el('p', 'hinweis',
    'Lehrkräfte sind standardmäßig den ganzen Sprechtag anwesend. Nur wenn '
    + 'jemand ausnahmsweise bloß zeitweise da ist, das ⏱-Symbol anklicken '
    + 'und ein Zeitfenster setzen. Das Häkchen „½" markiert eine Halbtagskraft '
    + '(dauerhaft); sie wählt dann eine Hälfte. Alle Spalten außer „Aktion" '
    + 'sind über die Überschrift sortierbar. Doppelt belegte Räume werden '
    + 'farblich markiert.'));

  // Sammelaktionen: alle als teilnehmend markieren (dann Dummys abhaken),
  // und alle Zeilen auf einmal speichern.
  const werkzeuge = el('div', 'tabellen-werkzeuge');
  werkzeuge.appendChild(knopf('Alle teilnehmen', 'klein', () => {
    for (const l of daten.lehrer) {
      const cb = $('#tn-' + s.id + '-' + l.lehrer_id);
      if (cb) cb.checked = true;
    }
    toast('Alle angehakt – Dummys jetzt abhaken, dann „Alle speichern".', 'info');
  }));
  werkzeuge.appendChild(knopf('Keine teilnehmen', 'klein', () => {
    for (const l of daten.lehrer) {
      const cb = $('#tn-' + s.id + '-' + l.lehrer_id);
      if (cb) cb.checked = false;
    }
  }));
  werkzeuge.appendChild(knopf('Alle speichern', null,
    () => alleLehrerSpeichern(s, daten.lehrer)));
  ziel.appendChild(werkzeuge);

  // Konfliktfarben: jeder mehrfach belegte Raum bekommt eine eigene, stabile
  // Tönung aus einer Palette – so sieht man auf einen Blick, welche Zeilen
  // sich denselben Raum teilen.
  const palette = ['kf-a', 'kf-b', 'kf-c', 'kf-d', 'kf-e', 'kf-f', 'kf-g', 'kf-h'];
  const raumFarbe = {};
  let fi = 0;
  for (const rid of Object.keys(konflikte)) {
    raumFarbe[rid] = palette[fi % palette.length];
    fi++;
  }

  // Container, in dem die Tabelle lebt – so lässt sie sich beim Sortieren
  // einzeln austauschen, ohne den ganzen Detailbereich (und die aufgeklappte
  // Sprechtag-Karte) neu aufzubauen.
  const tabBox = el('div', 'lehrer-tabelle-box');
  ziel.appendChild(tabBox);

  // Vergleichswert einer Zeile je Sortierspalte.
  const sortWert = (l, feld) => {
    if (feld === 'dabei') return parseInt(l.teilnahme, 10) === 1 ? '1' : '0';
    if (feld === 'halbtags') return parseInt(l.halbtags, 10) === 1 ? '1' : '0';
    if (feld === 'anwesenheit') {
      if (parseInt(l.halbtags, 10) === 1) return '1_halbtags';
      return (l.anwesend_von || l.anwesend_bis) ? '2_fenster' : '0_ganz';
    }
    if (feld === 'raum') {
      const r = S.stammdaten.raeume.find((x) => String(x.id) === String(l.raum_id));
      return r ? (r.kuerzel || '').toLowerCase() : 'zzz';
    }
    return String(l[feld] || '').toLowerCase();
  };

  function baueTabelle() {
    const sort = S.lehrerSort || { feld: 'kuerzel', richtung: 1 };
    const reihen = daten.lehrer.slice().sort((a, b) => {
      const va = sortWert(a, sort.feld), vb = sortWert(b, sort.feld);
      if (va < vb) return -1 * sort.richtung;
      if (va > vb) return 1 * sort.richtung;
      return 0;
    });

    const tab = el('table', 'tabelle tabelle-breit');
    const kopf = el('tr');
    const spalten = [['kuerzel', 'Kürzel'], ['name', 'Name'], ['dabei', 'dabei'],
                     ['halbtags', '½'], ['anwesenheit', 'Anwesenheit'],
                     ['raum', 'Raum'], [null, 'Aktion']];
    for (const [feld, titel] of spalten) {
      const th = el('th', feld ? 'sortierbar' : null, titel);
      if (feld) {
        if (sort.feld === feld) {
          th.appendChild(document.createTextNode(sort.richtung === 1 ? ' ▲' : ' ▼'));
        }
        th.addEventListener('click', () => {
          if (S.lehrerSort && S.lehrerSort.feld === feld) {
            S.lehrerSort.richtung *= -1;
          } else {
            S.lehrerSort = { feld, richtung: 1 };
          }
          // NUR die Tabelle austauschen – kein Neuladen, kein Zuklappen.
          const neu = baueTabelle();
          tabBox.textContent = '';
          tabBox.appendChild(neu);
        });
      }
      kopf.appendChild(th);
    }
    tab.appendChild(kopf);

    for (const l of reihen) {
      tab.appendChild(baueZeile(l));
    }
    return tabelleRahmen(tab);
  }

  // Baut eine einzelne Tabellenzeile (schließt über s, konflikte, raumFarbe).
  function baueZeile(l) {
    const tr = el('tr');
    tr.appendChild(el('td', null, l.kuerzel));
    tr.appendChild(el('td', null, l.name || ''));

    const tdD = el('td', 'mitte');
    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.id = 'tn-' + s.id + '-' + l.lehrer_id;
    cb.checked = l.zuweisung_id === null ? false : parseInt(l.teilnahme, 10) === 1;
    tdD.appendChild(cb);
    tr.appendChild(tdD);

    // Halbtags-Häkchen – setzt die Stammdaten direkt (dauerhaft).
    const tdH = el('td', 'mitte');
    const cbH = document.createElement('input');
    cbH.type = 'checkbox';
    cbH.checked = parseInt(l.halbtags, 10) === 1;
    cbH.title = 'Halbtagskraft / Referendar:in';
    cbH.addEventListener('change', async () => {
      const vorher = l.halbtags;
      try {
        await api('/api/stammdaten/lehrer/' + l.lehrer_id,
          { method: 'PATCH', body: { halbtags: cbH.checked ? 1 : 0 } });
        l.halbtags = cbH.checked ? 1 : 0;
        toast((cbH.checked ? 'Als Halbtagskraft markiert: '
          : 'Markierung entfernt: ') + l.kuerzel, 'ok');
        // NUR die Anwesenheitszelle DIESER Zeile austauschen – kein Neuaufbau
        // der ganzen Tabelle (der sonst nach oben springt und stört).
        const neu = anwesenheitZelle(s, l);
        tdZeit.replaceWith(neu);
        tdZeit = neu;
      } catch (f) {
        cbH.checked = parseInt(vorher, 10) === 1;
        toast(String(f.message), 'fehler');
      }
    });
    tdH.appendChild(cbH);
    tr.appendChild(tdH);

    // Anwesenheit: Halbtagskräfte -> Hälfte-Dropdown; sonst von/bis-Felder.
    // Als eigene Funktion, damit sie beim ½-Wechsel einzeln neu gebaut wird.
    let tdZeit = anwesenheitZelle(s, l);
    tr.appendChild(tdZeit);

    // Raum: breitere Spalte, volle Kürzel; Dopplung als Hinweis NEBEN dem
    // Dropdown (nicht mehr in die Option gequetscht → nichts wird abgeschnitten).
    const tdR = el('td', 'raum-zelle');
    const sel = document.createElement('select');
    sel.id = 'raum-' + s.id + '-' + l.lehrer_id;
    const leer = document.createElement('option');
    leer.value = ''; leer.textContent = '– kein Raum –';
    sel.appendChild(leer);
    for (const r of S.stammdaten.raeume) {
      const o = document.createElement('option');
      o.value = r.id;
      o.textContent = r.kuerzel + (r.name ? ' – ' + r.name : '');
      if (String(r.id) === String(l.raum_id)) o.selected = true;
      sel.appendChild(o);
    }
    tdR.appendChild(sel);
    if (l.raum_id && konflikte[l.raum_id]) {
      const kf = raumFarbe[l.raum_id];
      sel.classList.add('konflikt', kf);
      tdR.appendChild(el('span', 'raum-warnung ' + kf,
        konflikte[l.raum_id] + '× belegt'));
    }
    tr.appendChild(tdR);

    // Aktionen als Icon-Buttons in eigener, rechtsbündiger Spalte.
    const tdA = el('td', 'aktion-zelle');
    const speichern = iconKnopf('✓', 'speichern', 'Speichern', async () => {
      const koerper = lehrerZeileDaten(s, l);
      try {
        await api('/api/sprechtage/' + s.id + '/lehrer/' + l.lehrer_id,
          { method: 'PATCH', body: koerper });
        toast('Gespeichert: ' + l.kuerzel, 'ok');
      } catch (f) { toast(String(f.message), 'fehler'); }
    });
    // Ausfall: dezentes Symbol (durchgestrichener Kreis), Farbe erst im Dialog.
    const ausfall = iconKnopf('⊘', 'ausfall', 'Ausfall (Termine freigeben)',
      () => lehrerAusfall(s, l));
    tdA.appendChild(speichern);
    tdA.appendChild(ausfall);
    tr.appendChild(tdA);
    return tr;
  }

  // Tabelle erstmalig aufbauen und einhängen.
  tabBox.appendChild(baueTabelle());

  // Bei langen Listen: „Alle speichern" auch am Seitenende, da man von oben
  // nach unten arbeitet.
  const werkzeugeUnten = el('div', 'tabellen-werkzeuge');
  werkzeugeUnten.appendChild(knopf('Alle speichern', null,
    () => alleLehrerSpeichern(s, daten.lehrer)));
  ziel.appendChild(werkzeugeUnten);
}

// Liest die Formularwerte einer Lehrer-Zeile in ein API-Objekt. Respektiert
// die drei Anwesenheits-Modi: Halbtags (Hälfte), Zeitfenster (von/bis) oder
// ganzer Tag (leer). Von beiden Speicherwegen (einzeln + Sammel) genutzt.
function lehrerZeileDaten(s, l) {
  const cb = $('#tn-' + s.id + '-' + l.lehrer_id);
  const z = {
    lehrer_id: l.lehrer_id,
    teilnahme: cb && cb.checked ? 1 : 0,
    raum_id: wert('raum-' + s.id + '-' + l.lehrer_id),
  };
  if (parseInt(l.halbtags, 10) === 1) {
    z.haelfte = wert('haelfte-' + s.id + '-' + l.lehrer_id);
  } else {
    const zf = $('#zf-' + s.id + '-' + l.lehrer_id);
    if (zf && zf.dataset.an === '1') {
      z.anwesend_von = wert('von-' + s.id + '-' + l.lehrer_id);
      z.anwesend_bis = wert('bis-' + s.id + '-' + l.lehrer_id);
    } else {
      z.anwesend_von = '';   // ganzer Tag
      z.anwesend_bis = '';
    }
  }
  return z;
}

// Speichert alle Zeilen der Lehrer-Tabelle auf einmal (Sammel-Endpunkt).
async function alleLehrerSpeichern(s, lehrer) {
  const zeilen = lehrer.map((l) => lehrerZeileDaten(s, l));
  try {
    const d = await api('/api/sprechtage/' + s.id + '/lehrer',
      { method: 'PUT', body: { zeilen } });
    toast('Gespeichert: ' + (d.gespeichert || zeilen.length) + ' Zeilen.', 'ok');
    oeffneLehrerVerwaltung(s);
  } catch (f) { toast(String(f.message), 'fehler'); }
}

// Krankheitsausfall: Termine freigeben + Eltern benachrichtigen.
async function lehrerAusfall(s, l) {
  const nachricht = prompt('Ausfall von ' + (l.name || l.kuerzel)
    + ' – Nachricht an die betroffenen Eltern:',
    'Der Termin muss leider entfallen, da die Lehrkraft erkrankt ist.');
  if (nachricht === null) return;
  if (!confirm('Alle Termine von ' + (l.name || l.kuerzel)
    + ' werden freigegeben und die Eltern benachrichtigt. Fortfahren?')) return;
  try {
    const d = await api('/api/sprechtage/' + s.id + '/lehrer/' + l.lehrer_id
      + '/ausfall', { method: 'POST', body: { nachricht } });
    const m = d.mitteilung;
    if (m && m.offen > 0 && sitzungAuswerten(m.sitzung,
        d.hinweis + ' ' + m.grund,
        { knopf: 'Anmelden und senden', aktion: () => sendeVorgemerkte(m.ids) })) return;
    toast(d.hinweis || 'Ausfall eingetragen.', m && m.offen > 0 ? 'fehler' : 'ok');
    oeffneLehrerVerwaltung(s);
  } catch (f) { toast(String(f.message), 'fehler'); }
}

// ---- Sonderlehrkräfte ----------------------------------------------------
async function oeffneSonderlehrer(s) {
  const ziel = $('#detail-' + s.id);
  if (!ziel) return;
  // Nur beim ERSTEN Öffnen (Bereich noch leer) eine kurze Ladeanzeige zeigen.
  // Beim Aktualisieren bleibt der alte Inhalt stehen, bis der neue fertig ist.
  if (ziel.childNodes.length === 0) {
    ziel.appendChild(el('p', 'hinweis', 'Wird geladen …'));
  }
  if (S.stammdaten.lehrer.length === 0) await ladeStammdaten();

  // Liste ZUERST laden, dann den kompletten Inhalt in einem losen Container
  // aufbauen und am Ende in EINEM Zug einsetzen. So bleibt der alte Inhalt
  // stehen, bis der neue fertig ist – kein Leeren, kein Flackern.
  let liste = [];
  try {
    liste = (await api('/api/sonderlehrer?sprechtag=' + s.id)).sonderlehrer || [];
  } catch (f) { toast(String(f.message), 'fehler'); return; }

  const neu = document.createElement('div');
  neu.appendChild(el('h4', null, 'Zusätzlich buchbare Lehrkräfte'));
  neu.appendChild(el('p', 'hinweis',
    'Diese Lehrkräfte können unabhängig davon gebucht werden, ob sie das Kind '
    + 'unterrichten. Jahrgänge leer lassen = für alle buchbar; sonst z. B. "EF, Q1, Q2".'));

  const z = el('div', 'zeile');
  z.appendChild(auswahl('Lehrkraft', 'sl-lehrer',
    S.stammdaten.lehrer.map((l) => ({ wert: l.id,
      text: l.kuerzel + (l.name ? ' – ' + l.name : '') })), ''));
  z.appendChild(auswahl('Rolle', 'sl-rolle',
    S.stammdaten.sonderrollen.map((r) => ({ wert: r.id, text: r.bezeichnung })), ''));
  z.appendChild(feld('Jahrgänge (optional)', 'sl-jahrgaenge'));
  neu.appendChild(z);
  neu.appendChild(knopf('Hinzufügen', null, async () => {
    try {
      await api('/api/sonderlehrer', { method: 'POST', body: {
        sprechtag_id: s.id,
        lehrer_id: parseInt(wert('sl-lehrer'), 10),
        rolle_id: parseInt(wert('sl-rolle'), 10),
        jahrgaenge: wert('sl-jahrgaenge') } });
      oeffneSonderlehrer(s);   // aktualisiert flackerfrei (siehe oben)
      toast('Hinzugefügt.', 'ok');
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));

  if (liste.length === 0) {
    neu.appendChild(el('p', 'hinweis', 'Noch keine zusätzlichen Lehrkräfte.'));
  } else {
    const tab = el('table', 'tabelle');
    const kopf = el('tr');
    for (const t of ['Kürzel', 'Name', 'Rolle', 'Jahrgänge', '']) kopf.appendChild(el('th', null, t));
    tab.appendChild(kopf);
    for (const e of liste) {
      const tr = el('tr');
      tr.appendChild(el('td', null, e.kuerzel));
      tr.appendChild(el('td', null, e.name || ''));
      tr.appendChild(el('td', null, e.rolle));
      tr.appendChild(el('td', null, e.jahrgaenge || 'alle'));
      const td = el('td');
      td.appendChild(knopf('Entfernen', 'klein gefahr', async () => {
        try {
          await api('/api/sonderlehrer/' + e.id, { method: 'DELETE' });
          oeffneSonderlehrer(s);
          toast('Entfernt.', 'ok');
        } catch (f) { toast(String(f.message), 'fehler'); }
      }));
      tr.appendChild(td);
      tab.appendChild(tr);
    }
    neu.appendChild(tabelleRahmen(tab));
  }

  // Jetzt erst austauschen: alten Inhalt durch den fertigen neuen ersetzen.
  ziel.replaceChildren(...neu.childNodes);
}

// ============================================================
// ANSICHT: Login-Protokoll (optionales Admin-Feature)
// ============================================================
function ansichtAdminLoginLog(ziel) {
  ziel.appendChild(el('h2', null, 'Login-Protokoll'));

  // Einstellungen einmalig holen. Guard-Flag verhindert Mehrfachladen und das
  // frühere Problem, dass die Übersicht erst nach manuellem Neuladen erschien.
  if (S.loginLogConf === undefined || S.loginLogConf === null) {
    ziel.appendChild(el('p', 'hinweis', 'Wird geladen …'));
    if (!S.loginLogLaedt) {
      S.loginLogLaedt = true;
      api('/api/login-log/einstellungen').then((c) => {
        S.loginLogConf = c; S.loginLogLaedt = false; zeichne();
      }).catch((f) => { S.loginLogLaedt = false; meldung(String(f.message), 'fehler'); });
    }
    return;
  }
  const c = S.loginLogConf;

  // ---- Einstellungen ----
  const konf = sektion('Einstellungen');
  konf.appendChild(el('p', 'hinweis',
    'Zeichnet Anmeldungen auf, um Rückfragen zu klären („konnte mich nicht '
    + 'anmelden") und Fehler nachzuvollziehen. Standardmäßig ausgeschaltet.'));
  // Datenschutz-Warnung – bewusst prominent.
  const warn = el('div', 'daten-warnung');
  warn.appendChild(el('strong', null, 'Datenschutz-Hinweis: '));
  warn.appendChild(document.createTextNode(
    'Ein aktiviertes Protokoll erzeugt eine personenbeziehbare Aufzeichnung, '
    + 'wer sich wann angemeldet hat – auch von Lehrkräften. Bitte Zweck und '
    + 'Aufbewahrung vor der Aktivierung mit der/dem Datenschutzbeauftragten '
    + '(und ggf. der Personalvertretung) abstimmen. Fehlgeschlagene Versuche '
    + 'werden aus Sicherheitsgründen ohnehin kurzzeitig gespeichert.'));
  konf.appendChild(warn);

  const l1 = el('label', 'check-zeile');
  const cbAktiv = document.createElement('input');
  cbAktiv.type = 'checkbox'; cbAktiv.id = 'll-aktiv'; cbAktiv.checked = c.aktiv === 1;
  l1.appendChild(cbAktiv);
  l1.appendChild(document.createTextNode(' Protokoll aktivieren (Ansicht unten wird befüllt)'));
  konf.appendChild(l1);

  const l2 = el('label', 'check-zeile');
  const cbErf = document.createElement('input');
  cbErf.type = 'checkbox'; cbErf.id = 'll-erfolge'; cbErf.checked = c.erfolge === 1;
  l2.appendChild(cbErf);
  l2.appendChild(document.createTextNode(
    ' Auch erfolgreiche Anmeldungen protokollieren (sonst nur Fehlschläge)'));
  konf.appendChild(l2);

  konf.appendChild(auswahl('Aufbewahrung', 'll-tage', [
    { wert: 14, text: '14 Tage' }, { wert: 30, text: '30 Tage' },
    { wert: 90, text: '90 Tage' },
  ], c.tage));

  const aktionen = el('div', 'aktionen');
  aktionen.appendChild(knopf('Einstellungen speichern', null, async () => {
    try {
      await api('/api/login-log/einstellungen', { method: 'POST', body: {
        aktiv: $('#ll-aktiv').checked ? 1 : 0,
        erfolge: $('#ll-erfolge').checked ? 1 : 0,
        tage: parseInt(wert('ll-tage'), 10) || 30 } });
      S.loginLogConf = null; S.loginLogListe = null;
      toast('Einstellungen gespeichert.', 'ok');
      zeichne();
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));
  konf.appendChild(aktionen);
  ziel.appendChild(konf);

  // ---- Protokoll-Tabelle ----
  if (c.aktiv !== 1) {
    ziel.appendChild(el('p', 'hinweis',
      'Das Protokoll ist ausgeschaltet. Aktivieren Sie es oben, um Anmeldungen '
      + 'hier zu sehen.'));
    return;
  }

  const box = sektion('Anmeldungen');
  const filterZeile = el('div', 'zeile');
  filterZeile.appendChild(feld('Nach Benutzername filtern', 'll-filter', 'text',
    S.loginLogFilter || ''));
  box.appendChild(filterZeile);
  const fAktionen = el('div', 'aktionen');
  fAktionen.appendChild(knopf('Suchen', 'klein', () => {
    S.loginLogFilter = wert('ll-filter'); S.loginLogListe = null; zeichne();
  }));
  fAktionen.appendChild(knopf('Aktualisieren', 'klein', () => {
    S.loginLogListe = null; zeichne();
  }));
  fAktionen.appendChild(knopf('Protokoll leeren', 'klein gefahr', async () => {
    if (!confirm('Alle Protokolleinträge löschen?')) return;
    try {
      await api('/api/login-log', { method: 'DELETE' });
      S.loginLogListe = null; toast('Protokoll geleert.', 'ok'); zeichne();
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));
  box.appendChild(fAktionen);
  ziel.appendChild(box);

  if (S.loginLogListe === null) {
    box.appendChild(el('p', 'hinweis', 'Wird geladen …'));
    if (!S.loginLogListeLaedt) {
      S.loginLogListeLaedt = true;
      const q = S.loginLogFilter ? ('?benutzer=' + encodeURIComponent(S.loginLogFilter)) : '';
      api('/api/login-log' + q).then((d) => {
        S.loginLogListe = d.eintraege || []; S.loginLogListeLaedt = false; zeichne();
      }).catch((f) => { S.loginLogListeLaedt = false; meldung(String(f.message), 'fehler'); });
    }
    return;
  }
  if (S.loginLogListe.length === 0) {
    box.appendChild(el('p', 'hinweis', 'Keine Einträge im gewählten Zeitraum.'));
    return;
  }

  const tab = el('table', 'tabelle');
  const kopf = el('tr');
  for (const t of ['Zeitpunkt', 'Benutzername', 'Ergebnis', 'Rolle / Grund']) {
    kopf.appendChild(el('th', null, t));
  }
  tab.appendChild(kopf);
  for (const e of S.loginLogListe) {
    const tr = el('tr');
    tr.appendChild(el('td', null, logZeit(e.zeitpunkt)));
    tr.appendChild(el('td', null, e.webuntis_benutzer));
    const ok = parseInt(e.erfolgreich, 10) === 1;
    const erg = el('td', null, ok ? 'erfolgreich' : 'fehlgeschlagen');
    erg.style.color = ok ? 'var(--gruen)' : 'var(--rot)';
    tr.appendChild(erg);
    tr.appendChild(el('td', null, e.grund || ''));
    tab.appendChild(tr);
  }
  box.appendChild(kartenTabelle(tab));
}

// ============================================================
// ANSICHT: Eigene Texte (Hilfe-, Buchungs-, Login-Hinweis)
// ============================================================
function ansichtAdminTexte(ziel) {
  ziel.appendChild(el('h2', null, 'Eigene Texte'));
  ziel.appendChild(el('p', 'hinweis',
    'Optionale, schulspezifische Texte (Markdown). Leer lassen = es wird nichts '
    + 'angezeigt. Alle unterstützen dieselben Platzhalter und werden sicher '
    + 'dargestellt.'));

  // Gemeinsame Platzhalter-/Formatierungshilfe (einmal oben).
  const vars = el('div', 'daten-warnung');
  vars.appendChild(el('strong', null, 'Verfügbare Platzhalter'));
  vars.appendChild(el('p', 'hinweis-klein',
    'Diese Kürzel werden beim Anzeigen automatisch ersetzt:'));
  const vtab = el('table', 'platzhalter-tabelle');
  for (const [ph, was] of [
    ['{{kontakt}}', 'die hinterlegte Kontakt-E-Mail'],
    ['{{schulname}}', 'der Schulname'],
    ['{{titel}}', 'der App-Titel'],
  ]) {
    const tr = el('tr');
    const tdC = el('td'); tdC.appendChild(el('code', null, ph)); tr.appendChild(tdC);
    tr.appendChild(el('td', null, was));
    vtab.appendChild(tr);
  }
  vars.appendChild(tabelleRahmen(vtab));
  vars.appendChild(el('p', 'hinweis-klein',
    'Formatierung: ## Überschrift, ### Unterüberschrift, **fett**, *kursiv*, '
    + '- Aufzählung, 1. nummeriert, [Link-Text](https://…).'));
  ziel.appendChild(vars);

  // Drei Editoren – jeweils Schlüssel, Titel, Beschreibung, Beispiel.
  ziel.appendChild(textEditor('hilfe_zusatz', 'Hilfetext',
    'Erscheint ganz oben auf der Hilfeseite – zusätzlich zur eingebauten Hilfe.',
    '## Willkommen\n\nBei Fragen wenden Sie sich an {{kontakt}}.'));
  ziel.appendChild(textEditor('buchung_hinweis', 'Buchungs-Hinweis',
    'Erscheint oben auf der Buchungsseite (für Eltern und Schüler:innen).',
    'Bitte buchen Sie Ihre Termine bis **Freitag**.'));
  ziel.appendChild(textEditor('login_hinweis', 'Login-Hinweis',
    'Erscheint auf der Anmeldeseite.',
    'Melden Sie sich mit Ihrem persönlichen {{schulname}}-WebUntis-Zugang an.'));
}

// Wiederverwendbarer Editor für einen einzelnen Text (Markdown).
// Lädt Rohtext + Vorschau bei Bedarf, speichert und zeigt Live-Vorschau.
function textEditor(schluessel, titel, beschreibung, beispiel) {
  const box = sektion(titel, beschreibung);
  S.texte = S.texte || {};
  const eintrag = S.texte[schluessel];

  if (eintrag === undefined) {
    box.appendChild(el('p', 'hinweis', 'Wird geladen …'));
    if (!S.texteLaedt[schluessel]) {
      S.texteLaedt[schluessel] = true;
      api('/api/einstellungen/text/' + schluessel).then((d) => {
        S.texte[schluessel] = { roh: d.roh || '', html: d.html || '' };
        S.texteLaedt[schluessel] = false; zeichne();
      }).catch((f) => { S.texteLaedt[schluessel] = false; meldung(String(f.message), 'fehler'); });
    }
    return box;
  }

  const taId = 'txt-' + schluessel;
  const ta = el('textarea');
  ta.id = taId; ta.rows = 8; ta.value = eintrag.roh || '';
  ta.style.width = '100%'; ta.style.fontFamily = 'ui-monospace, monospace';
  ta.placeholder = beispiel;
  box.appendChild(ta);

  const aktionen = el('div', 'aktionen');
  aktionen.appendChild(knopf('Speichern', null, async () => {
    toast('Speichern …', 'info');
    try {
      const d = await api('/api/einstellungen/text/' + schluessel, { method: 'POST',
        body: { text: wert(taId) } });
      S.texte[schluessel] = { roh: wert(taId), html: d.html || '' };
      // Anzeige-Caches der betroffenen Seiten verwerfen, damit sie neu laden.
      if (schluessel === 'hilfe_zusatz') S.hilfeZusatz = undefined;
      if (schluessel === 'buchung_hinweis') S.buchungHinweis = undefined;
      if (schluessel === 'login_hinweis') S.loginHinweis = undefined;
      toast(titel + ' gespeichert.', 'ok');
      zeichne();
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));
  box.appendChild(aktionen);

  // Vorschau
  if (eintrag.html) {
    box.appendChild(el('p', 'hinweis-klein', 'Vorschau:'));
    const vor = el('div', 'hilfe-zusatz karte-innen');
    vor.innerHTML = eintrag.html;   // serverseitig gesäubert
    box.appendChild(vor);
  }
  return box;
}

// ============================================================
// ANSICHT: Erinnerungen vor dem Sprechtag (Admin löst aus)
// ============================================================
function ansichtAdminErinnerungen(ziel) {
  ziel.appendChild(el('h2', null, 'Erinnerungen'));

  if (S.erinnerungConf === undefined) {
    ziel.appendChild(el('p', 'hinweis', 'Wird geladen …'));
    if (!S.erinnerungLaedt) {
      S.erinnerungLaedt = true;
      api('/api/erinnerungen/einstellungen').then((c) => {
        S.erinnerungConf = c; S.erinnerungLaedt = false; zeichne();
      }).catch((f) => { S.erinnerungLaedt = false; meldung(String(f.message), 'fehler'); });
    }
    return;
  }
  const c = S.erinnerungConf;

  ziel.appendChild(el('p', 'hinweis',
    'Sendet eine allgemeine Erinnerung an eine WebUntis-Empfängerliste (z. B. '
    + '„alle Eltern"). Der Versand läuft unter Ihrem WebUntis-Konto und '
    + 'wird von Ihnen bewusst ausgelöst – es gibt keinen automatischen Versand.'));

  // ---- Empfängerliste ----
  const liste = sektion('Empfängerliste');
  liste.appendChild(el('p', 'hinweis',
    'Die Liste wird über ihren Typ und ihre WebUntis-ID angesprochen. Die ID '
    + 'finden Sie in WebUntis (Nachricht verfassen → Liste auswählen); technisch '
    + 'versierte Nutzer sehen sie im Netzwerk-Aufruf als „referenceId".'));
  liste.appendChild(auswahl('Listen-Typ', 'er-typ', [
    { wert: 'DYNAMIC', text: 'DYNAMIC (systemgepflegt, z. B. „alle Eltern")' },
    { wert: 'QUICK', text: 'QUICK (manuell zusammengestellt)' },
  ], c.liste_typ || 'DYNAMIC'));
  liste.appendChild(feld('Listen-ID (referenceId)', 'er-id', 'text', c.liste_id || ''));
  liste.appendChild(feld('Name der Liste (nur zur Info)', 'er-name', 'text', c.liste_name || ''));
  ziel.appendChild(liste);

  // ---- Nachricht ----
  const nachricht = sektion('Nachricht');
  nachricht.appendChild(el('p', 'hinweis-klein',
    'Platzhalter {{schulname}}, {{titel}}, {{kontakt}} werden ersetzt. '
    + 'Leer lassen = der vorbereitete Standardtext wird verwendet.'));
  nachricht.appendChild(feld('Betreff', 'er-betreff', 'text', c.betreff || ''));
  nachricht.appendChild(el('p', 'hinweis-klein',
    'Standard-Betreff: „' + (c.standard_betreff || '') + '"'));
  const ta = el('textarea');
  ta.id = 'er-text'; ta.rows = 10; ta.value = c.text || '';
  ta.style.width = '100%'; ta.style.fontFamily = 'ui-monospace, monospace';
  ta.placeholder = c.standard_text || '';
  nachricht.appendChild(el('label', null, 'Text (leer = Standardtext)'));
  nachricht.appendChild(ta);
  ziel.appendChild(nachricht);

  // Gemeinsame Speicherfunktion für Liste UND Nachricht (beide Kästen).
  const speichern = async () => {
    await api('/api/erinnerungen/einstellungen', { method: 'POST', body: {
      liste_typ: wert('er-typ'), liste_id: parseInt(wert('er-id'), 10) || 0,
      liste_name: wert('er-name'), betreff: wert('er-betreff'),
      text: wert('er-text') } });
  };

  // ---- Speichern (eigener Abschnitt, gilt für Liste + Nachricht) ----
  const sp = sektion('Speichern');
  sp.appendChild(el('p', 'hinweis-klein',
    'Speichert Empfängerliste und Nachricht.'));
  const spBtn = el('div', 'aktionen');
  spBtn.appendChild(knopf('Alle Einstellungen speichern', null, async () => {
    toast('Speichern …', 'info');
    try {
      await speichern();
      S.erinnerungConf = undefined; S.erinnerungVorschau = null;
      toast('Gespeichert.', 'ok'); zeichne();
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));
  sp.appendChild(spBtn);
  ziel.appendChild(sp);

  // ---- Versand ----
  const versand = sektion('Versand');
  const warn = el('div', 'daten-warnung');
  warn.appendChild(el('strong', null, 'Vor dem Senden: '));
  warn.appendChild(document.createTextNode(
    'Der Versand geht an ALLE Personen der Liste und kann nicht zurückgenommen '
    + 'werden. „Empfänger prüfen" speichert Ihre Eingaben und zeigt die aktuelle '
    + 'Empfängerzahl – bitte immer zuerst prüfen.'));
  versand.appendChild(warn);

  const va = el('div', 'aktionen');
  va.appendChild(knopf('Empfänger prüfen', 'klein', async () => {
    toast('Speichere & ermittle Empfänger …', 'info');
    try {
      await speichern();               // erst speichern, damit die Prüfung
      const d = await api('/api/erinnerungen/vorschau');  // die aktuellen Werte nutzt
      // Conf neu laden lassen, aber Formulareingaben sind ja gespeichert.
      S.erinnerungConf = undefined; S.erinnerungVorschau = d;
      // Abgelaufen (E17): anmelden, dann selbst erneut prüfen – keine Automatik.
      if (sitzungAuswerten(d.sitzung, 'Empfänger nicht geprüft: ' + d.grund,
        { knopf: 'Anmelden', aktion: async () => meldung(
          'Neu angemeldet – bitte „Empfänger prüfen“ erneut auslösen.', 'ok') })) return;
      zeichne();
    } catch (f) { toast(String(f.message), 'fehler'); }
  }));
  versand.appendChild(va);

  if (S.erinnerungVorschau) {
    const v = S.erinnerungVorschau;
    if (v.ok) {
      const p = el('p', 'hinweis',
        'Die Liste umfasst aktuell ' + v.anzahl + ' Empfänger.'
        + (v.vollstaendig ? '' : ' (Achtung: möglicherweise nicht vollständig '
          + 'aufgelöst – bitte mit der erwarteten Zahl vergleichen.)'));
      versand.appendChild(p);
      versand.appendChild(knopf('Erinnerung jetzt an ' + v.anzahl
        + ' Empfänger senden', 'gefahr', async () => {
        if (!confirm('Erinnerung wirklich an ' + v.anzahl + ' Empfänger senden? '
          + 'Das kann nicht rückgängig gemacht werden.')) return;
        toast('Sende … (das kann einen Moment dauern)', 'info');
        try {
          const r = await api('/api/erinnerungen/senden', { method: 'POST' });
          S.erinnerungVorschau = null;
          // Abgelaufen (E17): NICHT verschickt; nach der Anmeldung bewusst
          // erneut prüfen und senden – an alle wird nie automatisch gesendet.
          if (sitzungAuswerten(r.sitzung, 'Die Erinnerung wurde NICHT verschickt: ' + r.grund,
            { knopf: 'Anmelden', aktion: async () => meldung('Neu angemeldet – bitte die '
              + 'Empfänger erneut prüfen und dann senden.', 'ok') })) return;
          // Drei Stände: bestätigt gesendet, unklar, Fehlschlag.
          // „Unklar" ist bewusst KEIN Erfolg: WebUntis hat die Mitteilung
          // womöglich angenommen, aber nicht bestätigt. Erneut zu senden
          // kann Doppelte erzeugen – deshalb der Hinweis aufs Nachsehen.
          if (r.unklar) {
            meldung('Unklarer Versandstand: ' + (r.gesendet > 0
              ? r.gesendet + ' Empfänger wurden bestätigt, danach brach es ab. '
              : '') + (r.grund || '')
              + ' Bitte in WebUntis unter „Gesendet" nachsehen, bevor Sie '
              + 'erneut senden – es gibt keinen Schutz gegen doppelte '
              + 'Mitteilungen.', 'fehler');
          } else if (r.gesendet > 0 && r.vollstaendig) {
            meldung('Erinnerung an ' + r.gesendet
              + ' Empfänger gesendet und von WebUntis bestätigt.', 'ok');
          } else if (r.gesendet > 0) {
            meldung('Teilweise gesendet: ' + r.gesendet + ' von '
              + r.empfaenger + ' Empfängern bestätigt. '
              + (r.grund || ''), 'fehler');
          } else {
            meldung('Versand nicht erfolgreich: ' + (r.grund || 'unbekannt'), 'fehler');
          }
          zeichne();
        } catch (f) { toast(String(f.message), 'fehler'); }
      }));
    } else {
      versand.appendChild(el('p', 'hinweis-wichtig', v.grund || 'Keine Empfänger.'));
    }
  }
  ziel.appendChild(versand);
}

function logZeit(s) {
  if (!s) return '';
  const d = new Date(String(s).replace(' ', 'T'));
  if (isNaN(d)) return String(s);
  return d.toLocaleString('de-DE', { day: '2-digit', month: '2-digit',
    year: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' Uhr';
}

// ============================================================
// ANSICHT: Sondierung (Werkzeug aus Paket 1)
// ============================================================
function ansichtSondierung(ziel) {
  ziel.appendChild(el('h2', null, 'WebUntis-Sondierung'));
  ziel.appendChild(el('p', 'hinweis',
    'Diagnosewerkzeug: klopft die WebUntis-Instanz mit einem beliebigen Konto '
    + 'ab (nur lesend). Nach Abschluss der Einrichtung in der config.php '
    + 'abschalten (sondierung_freigeschaltet = false).'));

  const S0 = S.sondierung;

  // Eingaben aus dem Zustand vorbelegen, damit sie ein Neuzeichnen überleben
  const f = el('div');
  const z1 = el('div', 'zeile');
  z1.appendChild(feld('Benutzername', 'so-benutzer', 'text', S0.benutzer));
  z1.appendChild(feld('Passwort', 'so-passwort', 'password'));
  f.appendChild(z1);
  const z2 = el('div', 'zeile');
  z2.appendChild(feld('Zeitraum von', 'so-von', 'date', S0.von));
  z2.appendChild(feld('Zeitraum bis', 'so-bis', 'date', S0.bis));
  z2.appendChild(feld('Schüler-ID (optional)', 'so-schueler', 'text', S0.schueler));
  f.appendChild(z2);
  ziel.appendChild(f);

  const gruppen = el('div', 'gruppen-zeile');
  for (const [w, t] of [['basis', 'Basis'], ['sprechtag', 'Sprechtag-Endpunkte'],
      ['stundenplan', 'Stundenplan'], ['mitteilungen', 'Mitteilungen'],
      ['stammdaten', 'Klassen & Schüler:innen']]) {
    const l = el('label', 'inline');
    const cb = document.createElement('input');
    cb.type = 'checkbox'; cb.value = w; cb.className = 'so-gruppe';
    cb.checked = S0.gruppen.includes(w);
    l.appendChild(cb);
    l.appendChild(document.createTextNode(' ' + t));
    gruppen.appendChild(l);
  }
  ziel.appendChild(gruppen);

  ziel.appendChild(knopf('Sondierung starten', null, async () => {
    // Alle Eingaben in den Zustand übernehmen, BEVOR gezeichnet wird
    S0.benutzer = wert('so-benutzer');
    S0.von      = wert('so-von');
    S0.bis      = wert('so-bis');
    S0.schueler = wert('so-schueler');
    S0.gruppen  = Array.from(document.querySelectorAll('.so-gruppe:checked'))
      .map((e) => e.value);
    const passwort = wert('so-passwort');

    if (S0.benutzer === '' || passwort === '') {
      meldung('Bitte Benutzername und Passwort eingeben.', 'fehler');
      return;
    }
    meldung('Sondierung läuft … (kann bis zu einer Minute dauern)', 'info');
    try {
      const d = await api('/api/sondierung', { method: 'POST', body: {
        benutzername: S0.benutzer, passwort,
        gruppen: S0.gruppen, von: S0.von, bis: S0.bis,
        schueler_id: S0.schueler } });
      S0.bericht = d.bericht;          // Bericht in den Zustand, nicht ins DOM
      meldung('Sondierung abgeschlossen.', 'ok');
    } catch (f2) {
      S0.bericht = null;
      meldung(String(f2.message), 'fehler');
    }
  }));

  // Bericht aus dem Zustand anzeigen – überlebt jedes Neuzeichnen
  if (S0.bericht !== null) {
    const text = JSON.stringify(S0.bericht, null, 2);
    const kopf = el('div', 'aktionen');
    kopf.appendChild(knopf('Als Markdown kopieren', 'klein', async () => {
      const md = '# Sondierungsbericht sprechtag\n\n```json\n' + text + '\n```\n';
      try {
        await navigator.clipboard.writeText(md);
        meldung('Bericht kopiert – bitte in den Chat einfügen.', 'ok');
      } catch (e) {
        meldung('Kopieren nicht möglich – bitte den Text unten markieren.', 'fehler');
      }
    }));
    kopf.appendChild(knopf('Bericht verwerfen', 'klein', () => {
      S0.bericht = null;
      zeichne();
    }));
    ziel.appendChild(kopf);
    ziel.appendChild(el('pre', 'sondierung-ausgabe', text));
  }
}

// ============================================================
// ANSICHT: Mitteilungen
// ============================================================
function ansichtMitteilungen(ziel) {
  ziel.appendChild(el('h2', null, 'Mitteilungen an Erziehungsberechtigte'));
  ziel.appendChild(el('p', 'hinweis',
    'Terminbestätigungen, Einladungen und Absagen werden hier gesammelt. Das '
    + 'System versendet sie sofort unter dem Namen der Person, die gebucht, '
    + 'eingeladen oder abgesagt hat. Was liegen geblieben ist – etwa weil die '
    + 'WebUntis-Anmeldung abgelaufen war –, lässt sich hier nachsenden.'));
  if (!sprechtagWaehler(ziel, () => { S.mitteilungen = null; S.mittLaedt = false; })) return;

  if (S.mitteilungen === null) {
    ziel.appendChild(el('p', 'hinweis', 'Mitteilungen werden geladen …'));
    if (!S.mittLaedt) { S.mittLaedt = true; ladeMitteilungen(); }
    return;
  }

  const offen = S.mitteilungen.filter((m) => m.status === 'offen');
  const gesendet = S.mitteilungen.filter((m) => m.status === 'gesendet');
  ziel.appendChild(el('p', 'hinweis',
    S.mitteilungen.length + ' Mitteilung(en): ' + offen.length + ' offen, '
    + gesendet.length + ' gesendet.'));
  if (offen.length === 0 && S.mitteilungen.length > 0) {
    ziel.appendChild(el('p', 'meldung ok',
      'Alle Mitteilungen sind versendet – nichts zu tun.'));
  }

  // Versand nur für die Administration
  if (offen.length > 0) {
    const kasten = sektion('Offene Mitteilungen versenden');
    kasten.appendChild(el('p', 'hinweis',
      'Der Versandweg der WebUntis-Schnittstelle ist nicht dokumentiert. '
      + 'Beim ersten Versand werden mehrere Feldstrukturen ausprobiert; '
      + 'die funktionierende wird gemerkt. Schlägt alles fehl, bleiben die '
      + 'Mitteilungen hier stehen und können manuell in WebUntis versendet werden.'));
    // Über die eigene Sitzung (E17) – keine Zugangsdaten. Ist sie
    // abgelaufen, kommt der Kasten; danach derselbe Versand.
    kasten.appendChild(knopf('Offene Mitteilungen versenden', null, async () => {
      meldung('Versand läuft …', 'info');
      try {
        const d = await api('/api/mitteilungen/senden',
          { method: 'POST', body: { sprechtag_id: S.aktiverSprechtag.id } });
        if (sitzungAuswerten(d.sitzung, 'Noch NICHT verschickt: ' + d.grund,
          { knopf: 'Anmelden und senden', aktion: () => sendeVorgemerkte(d.ids) })) return;
        S.versandProtokoll = d.protokoll || null;
        await ladeMitteilungen();
        meldung(d.grund + (d.variante ? ' (Variante: ' + d.variante + ')' : ''),
          d.gesendet > 0 ? 'ok' : 'fehler');
        ladeOffenHinweis();
      } catch (f) { meldung(String(f.message), 'fehler'); }
    }));
    ziel.appendChild(kasten);
  }

  if (S.mitteilungen.length === 0) {
    ziel.appendChild(el('p', 'hinweis', 'Noch keine Mitteilungen.'));
    return;
  }

  const tab = el('table', 'tabelle');
  const kopf = el('tr');
  for (const t of ['Anlass', 'Betreff', 'Kind', 'Zeitpunkt', 'Status', '']) {
    kopf.appendChild(el('th', null, t));
  }
  tab.appendChild(kopf);

  for (const m of S.mitteilungen) {
    const tr = el('tr');
    tr.appendChild(el('td', null, {
      bestaetigung: 'Bestätigung', absage: 'Absage',
      einladung: 'Einladung', hinweis: 'Hinweis',
    }[m.anlass] || m.anlass));
    tr.appendChild(el('td', null, m.betreff));
    // Kind statt Eltern-User-ID: fachlich relevanter und ohne
    // zusätzliche personenbezogene Speicherung möglich.
    tr.appendChild(el('td', null, m.kind_name
      ? m.kind_name + (m.klasse ? ' (' + m.klasse + ')' : '')
      : (m.schueler_id ? 'Schüler-ID ' + m.schueler_id
                       : 'Konto ' + m.empfaenger_user_id)));

    // Zeitpunkt: gesendet_am, sobald versendet – sonst angelegt_am.
    // Ein kleiner Zusatz sagt, worauf sich die Zeit bezieht, damit klar
    // ist, ob die Mitteilung schon raus ist.
    const tdZ = el('td', 'zeit');
    if (m.status === 'gesendet' && m.gesendet_am) {
      tdZ.appendChild(document.createTextNode(zeitstempel(m.gesendet_am)));
      tdZ.appendChild(el('div', 'hinweis-klein', 'gesendet'));
    } else {
      tdZ.appendChild(document.createTextNode(zeitstempel(m.angelegt_am)));
      tdZ.appendChild(el('div', 'hinweis-klein', 'angelegt'));
    }
    tr.appendChild(tdZ);

    const tdS = el('td');
    tdS.appendChild(el('span', 'status-' + m.status, {
      offen: 'offen', gesendet: 'gesendet', verworfen: 'verworfen',
    }[m.status] || m.status));
    if (m.grund && m.status === 'offen') {
      tdS.appendChild(el('div', 'hinweis-klein', m.grund));
    }
    tr.appendChild(tdS);

    const tdA = el('td');
    if (m.status === 'offen') {
      tdA.appendChild(knopf('Verwerfen', 'klein gefahr', async () => {
        try {
          await api('/api/mitteilungen/' + m.id, { method: 'DELETE' });
          await ladeMitteilungen();
        } catch (f) { meldung(String(f.message), 'fehler'); }
      }));
    }
    tr.appendChild(tdA);
    tab.appendChild(tr);
  }
  ziel.appendChild(kartenTabelle(tab));
  ziel.appendChild(knopf('Aktualisieren', 'klein', async () => {
    await ladeMitteilungen();
    const o = (S.mitteilungen || []).filter((m) => m.status === 'offen').length;
    meldung('Liste aktualisiert: ' + (S.mitteilungen || []).length
      + ' Mitteilung(en), ' + o + ' offen.', 'info');
  }));

  // Diagnose: Was hat WebUntis auf welche Feldstruktur geantwortet?
  if (S.versandProtokoll !== null && S.versandProtokoll.length > 0) {
    const dia = block('versand-protokoll', 'Diagnose des letzten Versands');
    dia.appendChild(el('p', 'hinweis',
      'Der Versandweg der WebUntis-Schnittstelle ist nicht dokumentiert. '
      + 'Hier steht, was die Instanz auf jede probierte Feldstruktur '
      + 'geantwortet hat – hilfreich für die Fehlersuche.'));
    for (const p of S.versandProtokoll) {
      const kasten = el('div', 'probe ' + (p.ok ? 'ok' : 'fehlt'));
      kasten.appendChild(el('h4', null, 'Mitteilung ' + p.id
        + (p.ok ? ' – versendet' : ' – fehlgeschlagen')));
      for (const v of (p.versuche || [])) {
        kasten.appendChild(el('div', 'hinweis-klein',
          v.variante + ' → ' + v.grund));
      }
      dia.appendChild(kasten);
    }
    dia.appendChild(knopf('Diagnose als Text kopieren', 'klein', async () => {
      const text = JSON.stringify(S.versandProtokoll, null, 2);
      try {
        await navigator.clipboard.writeText(text);
        meldung('Diagnose kopiert.', 'ok');
      } catch { meldung('Kopieren nicht möglich.', 'fehler'); }
    }));
    ziel.appendChild(dia);
  }
}

async function ladeMitteilungen() {
  try {
    const d = await api('/api/mitteilungen?sprechtag=' + S.aktiverSprechtag.id);
    S.mitteilungen = d.mitteilungen || [];
    meldung(null);
  } catch (f) {
    S.mitteilungen = [];   // leere Liste statt null: keine Auto-Load-Schleife
    meldung(String(f.message), 'fehler');
  } finally {
    S.mittLaedt = false;
    zeichne();
  }
}

start();

})();
