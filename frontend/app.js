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
  meineBuchungen: [],
  einladungen: [],
  mitteilungen: null,
  dienstkonto: null,   // Status des hinterlegten Dienstkontos
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
  if (!antwort.ok) throw new Error(daten.fehler || ('Fehler ' + antwort.status));
  return daten;
}

function meldung(text, art = 'info') {
  S.meldung = text ? { text, art } : null;
  zeichne();
}

// ---------- kleine DOM-Helfer --------------------------------------------
function el(tag, klasse, text) {
  const e = document.createElement(tag);
  if (klasse) e.className = klasse;
  if (text != null) e.textContent = text;
  return e;
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

function knopf(text, klasse, aktion) {
  const b = el('button', klasse, text);
  b.type = 'button';
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
function wert(id) { const e = $('#' + id); return e ? e.value.trim() : ''; }

// ---------- Start ---------------------------------------------------------
async function start() {
  try {
    const me = await api('/api/auth/me');
    S.user = me.angemeldet ? me : null;
  } catch { S.user = null; }

  if (S.user) {
    await ladeSprechtage();
    S.ansicht = S.user.rolle === 'admin' ? 'admin'
      : S.user.rolle === 'lehrkraft' ? 'lehrkraft' : 'buchen';
  } else {
    S.ansicht = 'login';
  }
  zeichne();
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

$('#abmelden').addEventListener('click', async () => {
  await api('/api/auth/logout', { method: 'POST' });
  S.user = null; S.ansicht = 'login'; S.aktiverSprechtag = null;
  zeichne();
});

// ============================================================
// Zeichnen
// ============================================================
function zeichne() {
  // Kopfzeile
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

  const ziel = $('#ansicht');
  ziel.textContent = '';

  if (S.meldung) {
    const m = el('div', 'meldung ' + S.meldung.art, S.meldung.text);
    ziel.appendChild(m);
  }

  const ansichten = {
    login: ansichtLogin,
    buchen: ansichtBuchen,
    meine: ansichtMeineTermine,
    lehrkraft: ansichtLehrkraft,
    einladungen: ansichtEinladungen,
    admin: ansichtAdmin,
    mitteilungen: ansichtMitteilungen,
    sondierung: ansichtSondierung,
  };
  (ansichten[S.ansicht] || ansichtLogin)(ziel);
}

function zeichneNavigation() {
  const nav = $('#navigation');
  nav.textContent = '';
  if (!S.user) { nav.classList.add('versteckt'); return; }
  nav.classList.remove('versteckt');

  const punkte = [];
  if (S.user.rolle === 'eltern' || S.user.rolle === 'schueler') {
    punkte.push(['buchen', 'Termin buchen'], ['meine', 'Meine Termine']);
  }
  if (S.user.rolle === 'lehrkraft' || S.user.rolle === 'admin') {
    punkte.push(['lehrkraft', 'Meine Termine'], ['einladungen', 'Einladungen'],
                ['mitteilungen', 'Mitteilungen']);
  }
  if (S.user.rolle === 'admin') {
    punkte.push(['admin', 'Administration'], ['sondierung', 'Sondierung']);
  }
  for (const [ziel, text] of punkte) {
    const b = knopf(text, 'nav-knopf' + (S.ansicht === ziel ? ' aktiv' : ''),
      () => { S.ansicht = ziel; S.meldung = null; zeichne(); });
    nav.appendChild(b);
  }
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
  ziel.appendChild(el('p', 'hinweis',
    'Bitte mit den WebUntis-Zugangsdaten anmelden. Erziehungsberechtigte '
    + 'nutzen ihren eigenen Zugang – nicht den ihres Kindes.'));

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
      S.user = await api('/api/auth/login', { method: 'POST', body: {
        benutzername: wert('login-benutzer'), passwort: wert('login-passwort') } });
      await ladeSprechtage();
      S.ansicht = S.user.rolle === 'admin' ? 'admin'
        : S.user.rolle === 'lehrkraft' ? 'lehrkraft' : 'buchen';
      meldung(null);
    } catch (f) {
      senden.disabled = false;
      meldung(String(f.message), 'fehler');
    }
  });
  ziel.appendChild(form);
}

// ============================================================
// ANSICHT: Termin buchen (Eltern/Schüler)
// ============================================================
function ansichtBuchen(ziel) {
  ziel.appendChild(el('h2', null, 'Termin buchen'));
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
      'Diesem Konto sind keine Kinder zugeordnet. Bitte im Sekretariat melden.'));
    return;
  }
  if (!S.kind) S.kind = kinder[0].id;

  const kw = auswahl('Kind', 'kind-wahl',
    kinder.map((k) => ({ wert: k.id, text: k.name || ('Schüler-ID ' + k.id) })), S.kind);
  kw.querySelector('select').addEventListener('change', (e) => {
    S.kind = parseInt(e.target.value, 10);
    S.lehrerListe = null; S.gewaehlteLehrkraft = null; S.raster = [];
    zeichne();
  });
  ziel.appendChild(kw);

  // Lehrkräfte laden
  if (S.lehrerListe === null) {
    ziel.appendChild(knopf('Lehrkräfte anzeigen', null, () => ladeLehrerListe()));
    return;
  }

  const alle = (S.lehrerListe.unterrichtend || [])
    .concat(S.lehrerListe.sonderlehrer || []);
  if (alle.length === 0) {
    ziel.appendChild(el('p', 'hinweis',
      'Für dieses Kind konnten keine Lehrkräfte ermittelt werden. '
      + 'Bitte im Sekretariat melden.'));
    return;
  }

  ziel.appendChild(el('h3', null, 'Lehrkraft wählen'));
  const liste = el('div', 'lehrer-liste');
  for (const l of alle) {
    const karte = el('div', 'lehrer-karte'
      + (S.gewaehlteLehrkraft === l.lehrer_id ? ' gewaehlt' : ''));
    karte.appendChild(el('strong', null, (l.name || l.kuerzel)));
    if (l.faecher) karte.appendChild(el('span', 'faecher', l.faecher));
    if (l.rolle) karte.appendChild(el('span', 'rolle-badge', l.rolle));
    if (l.raum_kuerzel) karte.appendChild(el('span', 'raum', 'Raum ' + l.raum_kuerzel));
    if (l.anwesend_von) {
      karte.appendChild(el('span', 'zeitfenster',
        'anwesend ' + l.anwesend_von.slice(0, 5) + '–' + (l.anwesend_bis || '').slice(0, 5)));
    }
    karte.addEventListener('click', () => ladeRaster(l.lehrer_id));
    liste.appendChild(karte);
  }
  ziel.appendChild(liste);

  if (S.gewaehlteLehrkraft && S.raster.length) {
    zeichneRaster(ziel, S.gewaehlteLehrkraft);
  }
}

async function ladeLehrerListe() {
  try {
    S.lehrerListe = await api('/api/buchbare-lehrer?sprechtag='
      + S.aktiverSprechtag.id + '&kind=' + S.kind);
    if ((S.lehrerListe.unterrichtend || []).length === 0) {
      meldung('Für dieses Kind sind noch keine Lehrkräfte hinterlegt. '
        + 'Die Zuordnung wird von der Schule vorbereitet.', 'info');
    } else { meldung(null); }
  } catch (f) { meldung(String(f.message), 'fehler'); }
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
  const raster = el('div', 'raster');
  for (const z of S.raster) {
    if (z.typ === 'pause') {
      raster.appendChild(el('div', 'slot pause', 'Pause'));
      continue;
    }
    const klasse = 'slot ' + (z.frei ? 'frei' : (z.eigene ? 'eigene' : 'belegt'));
    const s = el('div', klasse, z.beginn);
    if (z.frei) {
      s.addEventListener('click', () => buchen(lehrerId, z.beginn));
    } else if (z.eigene) {
      s.title = 'Von Ihnen gebucht';
    }
    raster.appendChild(s);
  }
  ziel.appendChild(raster);
}

async function buchen(lehrerId, slot) {
  try {
    await api('/api/buchungen', { method: 'POST', body: {
      sprechtag_id: S.aktiverSprechtag.id, lehrer_id: lehrerId,
      schueler_id: S.kind, slot_beginn: slot } });
    await ladeRaster(lehrerId);
    meldung('Termin um ' + slot + ' Uhr gebucht.', 'ok');
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

// ============================================================
// ANSICHT: Meine Termine (Eltern/Schüler)
// ============================================================
function ansichtMeineTermine(ziel) {
  ziel.appendChild(el('h2', null, 'Meine Termine'));
  if (!sprechtagWaehler(ziel, () => ladeMeineBuchungen())) return;

  if (S.meineBuchungen === null) {
    ziel.appendChild(knopf('Termine laden', null, () => ladeMeineBuchungen()));
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
  for (const t of ['Zeit', 'Lehrkraft', 'Raum', 'Kind', '']) {
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
  ziel.appendChild(tab);
  ziel.appendChild(knopf('Aktualisieren', 'klein', () => ladeMeineBuchungen()));
}

async function ladeMeineBuchungen() {
  try {
    const d = await api('/api/buchungen?sprechtag=' + S.aktiverSprechtag.id);
    S.meineBuchungen = d.buchungen || [];
    meldung(null);
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

async function stornieren(id) {
  if (!confirm('Diesen Termin wirklich absagen?')) return;
  try {
    await api('/api/buchungen/' + id, { method: 'DELETE' });
    await ladeMeineBuchungen();
    meldung('Termin abgesagt.', 'ok');
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

// ============================================================
// ANSICHT: Lehrkraft – eigene Termine
// ============================================================
function ansichtLehrkraft(ziel) {
  ziel.appendChild(el('h2', null, 'Meine Sprechtags-Termine'));
  if (!sprechtagWaehler(ziel, () => ladeLehrkraftBuchungen())) return;

  if (S.user.lehrer_id === null && S.user.rolle === 'lehrkraft') {
    ziel.appendChild(el('p', 'hinweis-wichtig',
      'Diesem Konto ist kein Lehrkraft-Stammsatz zugeordnet. '
      + 'Bitte die Administration bitten, die Stammdaten zu synchronisieren.'));
    return;
  }

  if (S.meineBuchungen === null) {
    ziel.appendChild(knopf('Termine laden', null, () => ladeLehrkraftBuchungen()));
    return;
  }

  if (S.meineBuchungen.length === 0) {
    ziel.appendChild(el('p', 'hinweis', 'Noch keine Termine gebucht.'));
  } else {
    const tab = el('table', 'tabelle');
    const kopf = el('tr');
    for (const t of ['Zeit', 'Kind (Schüler-ID)', 'Phase', 'gebucht von', '']) {
      kopf.appendChild(el('th', null, t));
    }
    tab.appendChild(kopf);
    for (const b of S.meineBuchungen) {
      const tr = el('tr');
      tr.appendChild(el('td', 'zeit', String(b.slot_beginn).slice(0, 5)));
      tr.appendChild(el('td', null, String(b.schueler_id)));
      tr.appendChild(el('td', null, b.phase === 'phase1' ? 'Einladung' : 'offen'));
      tr.appendChild(el('td', null, b.gebucht_von));
      const td = el('td');
      td.appendChild(knopf('Absagen', 'klein gefahr', () => lehrkraftStorno(b)));
      tr.appendChild(td);
      tab.appendChild(tr);
    }
    ziel.appendChild(tab);
  }
  ziel.appendChild(knopf('Aktualisieren', 'klein', () => ladeLehrkraftBuchungen()));
}

async function ladeLehrkraftBuchungen() {
  try {
    const d = await api('/api/buchungen?sicht=lehrkraft&sprechtag='
      + S.aktiverSprechtag.id);
    S.meineBuchungen = d.buchungen || [];
    meldung(null);
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

async function lehrkraftStorno(b) {
  const text = prompt('Absage – Nachricht an die Erziehungsberechtigten '
    + '(wird in Paket 3 als WebUntis-Mitteilung versendet):',
    'Der Termin um ' + String(b.slot_beginn).slice(0, 5) + ' Uhr muss leider entfallen.');
  if (text === null) return;
  try {
    const d = await api('/api/buchungen/' + b.id
      + '?nachricht=' + encodeURIComponent(text), { method: 'DELETE' });
    await ladeLehrkraftBuchungen();
    const m = d.mitteilung;
    meldung('Termin abgesagt. ' + (m && m.status === 'gesendet'
      ? 'Die Erziehungsberechtigten wurden benachrichtigt.'
      : 'Die Benachrichtigung ist unter „Mitteilungen" zum Versand vorgemerkt.'), 'ok');
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

// ============================================================
// ANSICHT: Einladungen (Phase 1)
// ============================================================
function ansichtEinladungen(ziel) {
  ziel.appendChild(el('h2', null, 'Einladungen für Phase 1'));
  ziel.appendChild(el('p', 'hinweis',
    'In Phase 1 können nur eingeladene Erziehungsberechtigte buchen. '
    + 'Die Schüler-ID des Kindes steht in WebUntis; alternativ kann hier '
    + 'auch direkt stellvertretend gebucht werden.'));
  if (!sprechtagWaehler(ziel, () => ladeEinladungen())) return;

  const form = el('div', 'zeile');
  form.appendChild(feld('Schüler-ID', 'einl-schueler'));
  form.appendChild(feld('Hinweis (optional)', 'einl-hinweis'));
  ziel.appendChild(form);
  ziel.appendChild(knopf('Einladung anlegen', null, async () => {
    try {
      await api('/api/einladungen', { method: 'POST', body: {
        sprechtag_id: S.aktiverSprechtag.id,
        schueler_id: parseInt(wert('einl-schueler'), 10),
        hinweis: wert('einl-hinweis') } });
      await ladeEinladungen();
      meldung('Einladung angelegt.', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));

  if (S.einladungen === null) {
    ziel.appendChild(knopf('Einladungen laden', 'klein', () => ladeEinladungen()));
    return;
  }
  if (S.einladungen.length === 0) {
    ziel.appendChild(el('p', 'hinweis', 'Noch keine Einladungen angelegt.'));
    return;
  }

  const tab = el('table', 'tabelle');
  const kopf = el('tr');
  for (const t of ['Schüler-ID', 'Hinweis', 'Status', '']) kopf.appendChild(el('th', null, t));
  tab.appendChild(kopf);
  for (const e of S.einladungen) {
    const tr = el('tr');
    tr.appendChild(el('td', null, String(e.schueler_id)));
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
  ziel.appendChild(tab);
}

async function ladeEinladungen() {
  try {
    const d = await api('/api/einladungen?sprechtag=' + S.aktiverSprechtag.id);
    S.einladungen = d.einladungen || [];
    meldung(null);
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

// ============================================================
// ANSICHT: Administration
// ============================================================
function ansichtAdmin(ziel) {
  ziel.appendChild(el('h2', null, 'Administration'));

  // ---- Dienstkonto ------------------------------------------------------
  const dk = block('dienstkonto', 'Dienstkonto für die Lehrkraft-Ermittlung');
  dk.appendChild(el('p', 'hinweis',
    'Damit Eltern beim Buchen sofort ihre Lehrkräfte sehen, ermittelt das '
    + 'System sie im Hintergrund aus dem Stundenplan. Dafür wird ein '
    + 'WebUntis-Konto mit Leserecht auf Schülerstundenpläne benötigt. '
    + 'Die Zugangsdaten werden verschlüsselt gespeichert.'));
  dk.appendChild(el('p', 'hinweis-wichtig',
    'Bitte möglichst ein eigenes Dienstkonto mit minimalen Rechten verwenden, '
    + 'nicht das persönliche Konto. Wer Zugriff auf Server und Datenbank hat, '
    + 'kann die Zugangsdaten entschlüsseln – das lässt sich nicht vermeiden, '
    + 'weil der Server sie im Klartext zum Anmelden braucht.'));
  const dkStatus = el('div', 'dk-status');
  dk.appendChild(dkStatus);
  const dkZeile = el('div', 'zeile');
  dkZeile.appendChild(feld('WebUntis-Benutzername', 'dk-benutzer'));
  dkZeile.appendChild(feld('Passwort', 'dk-passwort', 'password'));
  dk.appendChild(dkZeile);
  const dkAktionen = el('div', 'aktionen');
  dkAktionen.appendChild(knopf('Speichern und prüfen', null, async () => {
    const daten = { benutzername: wert('dk-benutzer'), passwort: wert('dk-passwort') };
    if (daten.benutzername === '' || daten.passwort === '') {
      meldung('Bitte Benutzername und Passwort eingeben.', 'fehler');
      return;
    }
    meldung('Zugangsdaten werden gespeichert …', 'info');
    try {
      const d = await api('/api/dienstkonto', { method: 'POST', body: daten });
      S.dienstkonto = d;
      meldung(d.grund + (d.entschluesselbar
        ? ' Verschlüsselung funktioniert.'
        : ' ACHTUNG: Entschlüsselung schlug fehl – Schlüssel prüfen.'),
        d.entschluesselbar ? 'ok' : 'fehler');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));
  dkAktionen.appendChild(knopf('Entfernen', 'klein gefahr', async () => {
    if (!confirm('Zugangsdaten des Dienstkontos löschen? Die Lehrkraft-'
      + 'Ermittlung läuft danach nicht mehr automatisch.')) return;
    try {
      await api('/api/dienstkonto', { method: 'DELETE' });
      S.dienstkonto = null;
      meldung('Dienstkonto entfernt.', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));
  dk.appendChild(dkAktionen);
  ziel.appendChild(dk);

  // Status anzeigen (aus dem Zustand, sonst nachladen)
  if (S.dienstkonto === null) {
    dkStatus.appendChild(el('p', 'hinweis', 'Status wird geladen …'));
    api('/api/dienstkonto').then((d) => { S.dienstkonto = d; zeichne(); })
      .catch(() => { S.dienstkonto = { hinterlegt: false, schluessel_ok: false }; });
  } else {
    const d = S.dienstkonto;
    if (!d.schluessel_ok) {
      dkStatus.appendChild(el('p', 'meldung fehler',
        'Kein Verschlüsselungsschlüssel in config.php hinterlegt '
        + '(dienstkonto_schluessel, mindestens 32 Zeichen). Solange er fehlt, '
        + 'können keine Zugangsdaten gespeichert werden.'));
    } else if (d.hinterlegt) {
      dkStatus.appendChild(el('p', 'meldung ok',
        'Dienstkonto hinterlegt: ' + d.benutzer
        + ' · Verfahren: ' + d.verfahren
        + (d.entschluesselbar ? ' · entschlüsselbar'
                              : ' · NICHT entschlüsselbar (Schlüssel geändert?)')));
    } else {
      dkStatus.appendChild(el('p', 'hinweis',
        'Noch kein Dienstkonto hinterlegt. Ohne eines müssen Eltern beim '
        + 'ersten Besuch selbst ermitteln lassen.'));
    }
  }

  // ---- Stammdaten-Sync -------------------------------------------------
  const sync = block('sync', 'Stammdaten aus WebUntis übernehmen');
  sync.appendChild(el('p', 'hinweis',
    'Holt Lehrkräfte und Räume aus WebUntis. Zugangsdaten werden nur für '
    + 'diesen Abruf verwendet und nicht gespeichert. Beim ersten Lauf bitte '
    + 'prüfen, ob die Zahlen zum Kollegium passen.'));
  const sf = el('div', 'zeile');
  sf.appendChild(feld('WebUntis-Benutzername', 'sync-benutzer'));
  sf.appendChild(feld('Passwort', 'sync-passwort', 'password'));
  sync.appendChild(sf);
  sync.appendChild(knopf('Synchronisieren', null, async () => {
    // WICHTIG: Werte VOR meldung() lesen – meldung() ruft zeichne() auf
    // und baut die Ansicht neu auf, wodurch die Eingabefelder verschwinden.
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
  const neu = block('neu', 'Neuen Sprechtag anlegen');
  const nf = el('div');
  nf.appendChild(feld('Bezeichnung', 'neu-name', 'text',
    'Elternsprechtag ' + new Date().getFullYear()));
  const z1 = el('div', 'zeile');
  z1.appendChild(feld('Datum (JJJJ-MM-TT)', 'neu-datum'));
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
  neu.appendChild(nf);
  neu.appendChild(knopf('Anlegen', null, async () => {
    try {
      await api('/api/sprechtage', { method: 'POST', body: {
        name: wert('neu-name'), datum: wert('neu-datum'),
        beginn: wert('neu-beginn'), ende: wert('neu-ende'),
        slot_minuten: parseInt(wert('neu-slot'), 10) || 10,
        max_termine_pro_eltern: parseInt(wert('neu-max'), 10) || 6,
        pause_nach_terminen: parseInt(wert('neu-pausen'), 10) || 0,
        pause_minuten: parseInt(wert('neu-pausenlang'), 10) || 10 } });
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
  z1.appendChild(feld('Datum', 'e-datum-' + s.id, 'text', s.datum));
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
  const z4 = el('div', 'zeile');
  z4.appendChild(feld('Referenz von', 'e-refvon-' + s.id, 'text', s.referenz_von || ''));
  z4.appendChild(feld('Referenz bis', 'e-refbis-' + s.id, 'text', s.referenz_bis || ''));
  z4.appendChild(auswahl('Phase', 'e-phase-' + s.id, [
    { wert: 'vorbereitung', text: 'in Vorbereitung' },
    { wert: 'phase1', text: 'Phase 1 – nur auf Einladung' },
    { wert: 'phase2', text: 'Phase 2 – offen für alle' },
    { wert: 'geschlossen', text: 'geschlossen' },
    { wert: 'archiviert', text: 'archiviert (löscht Buchungen!)' },
  ], s.phase));
  p.appendChild(z4);
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
        referenz_von: wert('e-refvon-' + s.id) || null,
        referenz_bis: wert('e-refbis-' + s.id) || null,
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
    'Anwesenheitszeiten leer lassen = ganzer Zeitraum. Doppelt belegte Räume '
    + 'sind zulässig, werden aber farblich markiert.'));

  const tab = el('table', 'tabelle');
  const kopf = el('tr');
  for (const t of ['Kürzel', 'Name', 'dabei', 'von', 'bis', 'Raum', '']) {
    kopf.appendChild(el('th', null, t));
  }
  tab.appendChild(kopf);

  for (const l of daten.lehrer) {
    const tr = el('tr');
    tr.appendChild(el('td', null, l.kuerzel));
    tr.appendChild(el('td', null, l.name || ''));

    const tdD = el('td');
    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.id = 'tn-' + s.id + '-' + l.lehrer_id;
    cb.checked = l.zuweisung_id === null ? false : parseInt(l.teilnahme, 10) === 1;
    tdD.appendChild(cb);
    tr.appendChild(tdD);

    const mkZeit = (id, w) => {
      const td = el('td');
      const i = document.createElement('input');
      i.type = 'text'; i.id = id; i.className = 'zeit-feld';
      i.value = w ? String(w).slice(0, 5) : '';
      i.placeholder = '--:--';
      td.appendChild(i);
      return td;
    };
    tr.appendChild(mkZeit('von-' + s.id + '-' + l.lehrer_id, l.anwesend_von));
    tr.appendChild(mkZeit('bis-' + s.id + '-' + l.lehrer_id, l.anwesend_bis));

    const tdR = el('td');
    const sel = document.createElement('select');
    sel.id = 'raum-' + s.id + '-' + l.lehrer_id;
    const leer = document.createElement('option');
    leer.value = ''; leer.textContent = '– kein Raum –';
    sel.appendChild(leer);
    for (const r of S.stammdaten.raeume) {
      const o = document.createElement('option');
      o.value = r.id;
      o.textContent = r.kuerzel + (konflikte[r.id] ? ' (' + konflikte[r.id] + '×)' : '');
      if (String(r.id) === String(l.raum_id)) o.selected = true;
      sel.appendChild(o);
    }
    if (l.raum_id && konflikte[l.raum_id]) sel.classList.add('konflikt');
    tdR.appendChild(sel);
    tr.appendChild(tdR);

    const tdA = el('td');
    tdA.appendChild(knopf('Speichern', 'klein', async () => {
      try {
        await api('/api/sprechtage/' + s.id + '/lehrer/' + l.lehrer_id,
          { method: 'PATCH', body: {
            teilnahme: cb.checked ? 1 : 0,
            anwesend_von: wert('von-' + s.id + '-' + l.lehrer_id),
            anwesend_bis: wert('bis-' + s.id + '-' + l.lehrer_id),
            raum_id: wert('raum-' + s.id + '-' + l.lehrer_id) } });
        meldung('Gespeichert: ' + l.kuerzel, 'ok');
        oeffneLehrerVerwaltung(s);
      } catch (f) { meldung(String(f.message), 'fehler'); }
    }));
    tr.appendChild(tdA);
    tab.appendChild(tr);
  }
  ziel.appendChild(tab);
}

// ---- Sonderlehrkräfte ----------------------------------------------------
async function oeffneSonderlehrer(s) {
  const ziel = $('#detail-' + s.id);
  if (!ziel) return;
  ziel.textContent = '';
  if (S.stammdaten.lehrer.length === 0) await ladeStammdaten();

  ziel.appendChild(el('h4', null, 'Zusätzlich buchbare Lehrkräfte'));
  ziel.appendChild(el('p', 'hinweis',
    'Diese Lehrkräfte können unabhängig davon gebucht werden, ob sie das Kind '
    + 'unterrichten. Jahrgänge leer lassen = für alle buchbar; sonst z. B. "EF, Q1, Q2".'));

  const z = el('div', 'zeile');
  z.appendChild(auswahl('Lehrkraft', 'sl-lehrer',
    S.stammdaten.lehrer.map((l) => ({ wert: l.id,
      text: l.kuerzel + (l.name ? ' – ' + l.name : '') })), ''));
  z.appendChild(auswahl('Rolle', 'sl-rolle',
    S.stammdaten.sonderrollen.map((r) => ({ wert: r.id, text: r.bezeichnung })), ''));
  z.appendChild(feld('Jahrgänge (optional)', 'sl-jahrgaenge'));
  ziel.appendChild(z);
  ziel.appendChild(knopf('Hinzufügen', null, async () => {
    try {
      await api('/api/sonderlehrer', { method: 'POST', body: {
        sprechtag_id: s.id,
        lehrer_id: parseInt(wert('sl-lehrer'), 10),
        rolle_id: parseInt(wert('sl-rolle'), 10),
        jahrgaenge: wert('sl-jahrgaenge') } });
      oeffneSonderlehrer(s);
      meldung('Hinzugefügt.', 'ok');
    } catch (f) { meldung(String(f.message), 'fehler'); }
  }));

  let liste = [];
  try {
    liste = (await api('/api/sonderlehrer?sprechtag=' + s.id)).sonderlehrer || [];
  } catch (f) { meldung(String(f.message), 'fehler'); }

  if (liste.length === 0) {
    ziel.appendChild(el('p', 'hinweis', 'Noch keine zusätzlichen Lehrkräfte.'));
    return;
  }
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
      } catch (f) { meldung(String(f.message), 'fehler'); }
    }));
    tr.appendChild(td);
    tab.appendChild(tr);
  }
  ziel.appendChild(tab);
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
  z2.appendChild(feld('Zeitraum von (JJJJ-MM-TT)', 'so-von', 'text', S0.von));
  z2.appendChild(feld('Zeitraum bis', 'so-bis', 'text', S0.bis));
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
    'Terminbestätigungen und Absagen werden hier gesammelt. Der Versand über '
    + 'WebUntis wird von der Administration angestoßen und benötigt einmalig '
    + 'deren Zugangsdaten (sie werden nicht gespeichert).'));
  if (!sprechtagWaehler(ziel, () => ladeMitteilungen())) return;

  if (S.mitteilungen === null) {
    ziel.appendChild(knopf('Mitteilungen laden', null, () => ladeMitteilungen()));
    return;
  }

  const offen = S.mitteilungen.filter((m) => m.status === 'offen');
  ziel.appendChild(el('p', 'hinweis',
    S.mitteilungen.length + ' Mitteilung(en), davon ' + offen.length + ' offen.'));

  // Versand nur für die Administration
  if (S.user.rolle === 'admin' && offen.length > 0) {
    const kasten = block('versand', 'Offene Mitteilungen versenden');
    kasten.appendChild(el('p', 'hinweis',
      'Der Versandweg der WebUntis-Schnittstelle ist nicht dokumentiert. '
      + 'Beim ersten Versand werden mehrere Feldstrukturen ausprobiert; '
      + 'die funktionierende wird gemerkt. Schlägt alles fehl, bleiben die '
      + 'Mitteilungen hier stehen und können manuell in WebUntis versendet werden.'));
    const z = el('div', 'zeile');
    z.appendChild(feld('WebUntis-Benutzername', 'mv-benutzer'));
    z.appendChild(feld('Passwort', 'mv-passwort', 'password'));
    kasten.appendChild(z);
    kasten.appendChild(knopf('Alle offenen versenden', null, async () => {
      // Werte VOR meldung() lesen (meldung() zeichnet die Ansicht neu)
      const auftrag = { sprechtag_id: S.aktiverSprechtag.id,
                        benutzername: wert('mv-benutzer'),
                        passwort: wert('mv-passwort') };
      if (auftrag.benutzername === '' || auftrag.passwort === '') {
        meldung('Bitte Benutzername und Passwort eingeben.', 'fehler');
        return;
      }
      meldung('Versand läuft …', 'info');
      try {
        const d = await api('/api/mitteilungen/senden',
          { method: 'POST', body: auftrag });
        await ladeMitteilungen();
        meldung(d.grund + (d.variante ? ' (Variante: ' + d.variante + ')' : ''),
          d.gesendet > 0 ? 'ok' : 'fehler');
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
  for (const t of ['Anlass', 'Betreff', 'Empfänger (User-ID)', 'Status', '']) {
    kopf.appendChild(el('th', null, t));
  }
  tab.appendChild(kopf);

  for (const m of S.mitteilungen) {
    const tr = el('tr');
    tr.appendChild(el('td', null, {
      bestaetigung: 'Bestätigung', absage: 'Absage', hinweis: 'Hinweis',
    }[m.anlass] || m.anlass));
    tr.appendChild(el('td', null, m.betreff));
    tr.appendChild(el('td', null, String(m.empfaenger_user_id)));

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
  ziel.appendChild(tab);
  ziel.appendChild(knopf('Aktualisieren', 'klein', () => ladeMitteilungen()));
}

async function ladeMitteilungen() {
  try {
    const d = await api('/api/mitteilungen?sprechtag=' + S.aktiverSprechtag.id);
    S.mitteilungen = d.mitteilungen || [];
    meldung(null);
  } catch (f) { meldung(String(f.message), 'fehler'); }
}

start();

})();
