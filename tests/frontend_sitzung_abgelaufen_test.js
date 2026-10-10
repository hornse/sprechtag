// ============================================================
// tests/frontend_sitzung_abgelaufen_test.js
// Prüft v0.9.72 (E17) in der Oberfläche: die abgelaufene WebUntis-Anmeldung.
//   1. api() reicht die Ursache weiter (abgelaufen / nicht_erreichbar / kaputt).
//   2. Nur „abgelaufen“ bekommt den Kasten; sonst eine Meldung, die nicht
//      „neu anmelden“ verspricht.
//   3. Absage: „gespeichert, aber NICHT verschickt“ – mit Neuanmeldung, danach
//      geht DIESELBE Mitteilung mit einem Klick hinaus.
//   4. Stellvertretend: ohne Sitzung nicht gebucht; nach der Anmeldung
//      derselbe Klick.
//   5. Der Kasten: Anmeldung, dann der Auftrag – bei falschem Passwort nicht.
//   6. Hinweis nach der Anmeldung: Anzahl, „Jetzt senden“ mit den Kennungen.
//   7. Kein Dienstkonto mehr in der Oberfläche; der Schüler-Sync nimmt die
//      eingetippten Zugangsdaten.
//
// Ausgeführt (Rümpfe aus app.js mit knappem Ersatz für el() usw.).
// Aufruf: node tests/frontend_sitzung_abgelaufen_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const js = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

function el(tag, klasse, text) {
  return { tag, klasse: klasse || '', text: text === undefined ? '' : String(text), kinder: [], value: '', id: '',
    type: '', disabled: false, hoerer: {}, attr: {},
    appendChild(k) { this.kinder.push(k); return k; },
    addEventListener(e, f) { this.hoerer[e] = f; },
    setAttribute(n, v) { this.attr[n] = v; } };
}
const alle = (k) => [k].concat(...k.kinder.map(alle));
const texte = (k) => alle(k).map((x) => x.text).join(' | ');

const R = {
  // Kopf samt Vorgabe „= {}“: sonst hielte rumpf() die Vorgabe für den Rumpf.
  api: rumpf('async function api(pfad, optionen = {})'),
  auswerten: rumpf('function sitzungAuswerten('),
  storno: rumpf('async function lehrkraftStorno('),
  sv: rumpf('async function stellvertretendBuchen('),
  kasten: rumpf('function sitzungsKastenElement('),
  senden: rumpf('async function sendeVorgemerkte('),
  hinweis: rumpf('function offenHinweisElement('),
  einladen: rumpf('async function einladenAusfuehren('),
  buchen: rumpf('async function buchen('),
};
for (const [n, r] of Object.entries(R)) pruefe('Voraussetzung: Rumpf ' + n + ' gefunden', r !== '');

(async () => {
  // ----------------------------------------------------------
  console.log('1. api() reicht die Ursache weiter');
  const apiFn = new Function('fetch', 'return async function (pfad, optionen = {}) {' + R.api + '};');
  const fetch409 = async () => ({ ok: false, status: 409,
    json: async () => ({ fehler: 'Ihre WebUntis-Anmeldung ist abgelaufen.', sitzung: 'abgelaufen' }) });
  let fa = null;
  try { await apiFn(fetch409)('/x'); } catch (f) { fa = f; }
  pruefe('409 mit sitzung: Fehler trägt Meldung und Ursache',
    fa !== null && fa.message === 'Ihre WebUntis-Anmeldung ist abgelaufen.' && fa.sitzung === 'abgelaufen');
  let fb = null;
  try { await apiFn(async () => ({ ok: false, status: 500, json: async () => ({ fehler: 'x' }) }))('/x'); } catch (f) { fb = f; }
  pruefe('ohne sitzung: Ursache null', fb !== null && fb.sitzung === null);

  // ----------------------------------------------------------
  console.log('2. Nur „abgelaufen“ bekommt den Kasten');
  const lauf = { kasten: [], meldungen: [] };
  const zeigeSitzungsKasten = (a) => lauf.kasten.push(a);
  const meldung = (t, a) => lauf.meldungen.push([t, a]);
  const auswerten = new Function('zeigeSitzungsKasten', 'meldung',
    'return function (sitzung, meldetext, auftrag) {' + R.auswerten + '};')(zeigeSitzungsKasten, meldung);
  const zuruecksetzen = () => { lauf.kasten = []; lauf.meldungen = []; };
  zuruecksetzen();
  const r1 = auswerten('abgelaufen', 'Text A', { knopf: 'K' });
  pruefe('abgelaufen: Kasten mit Text und Knopf, keine Meldung',
    r1 === true && lauf.kasten.length === 1 && lauf.kasten[0].text === 'Text A' && lauf.kasten[0].knopf === 'K'
    && lauf.meldungen.length === 0);
  zuruecksetzen();
  const r2 = auswerten('nicht_erreichbar', 'Text B', { knopf: 'K' });
  const r3 = auswerten('kaputt', 'Text C', { knopf: 'K' });
  pruefe('nicht erreichbar / kaputt: KEIN Kasten (Neuanmeldung hilft nicht), Fehlermeldung',
    r2 === true && r3 === true && lauf.kasten.length === 0
    && lauf.meldungen.length === 2 && lauf.meldungen.every(([, a]) => a === 'fehler'));
  zuruecksetzen();
  pruefe('Sitzung in Ordnung (null): nichts gezeigt', auswerten(null, 'x', {}) === false && lauf.kasten.length === 0);

  // ----------------------------------------------------------
  console.log('3. Absage der Lehrkraft');
  const storno = (antwort) => {
    zuruecksetzen();
    const gesendet = [];
    const S = { gewaehlteLehrkraftAnsicht: null, user: { lehrer_id: 7 }, svRaster: [] };
    return new Function('S', 'api', 'meldung', 'ladeSvRaster', 'prompt', 'sitzungAuswerten', 'sendeVorgemerkte',
      'return (async (b) => {' + R.storno + '});')(
      S, async () => antwort, meldung, async () => {}, () => 'Termin entfällt.', auswerten,
      async (ids) => gesendet.push(ids))({ id: 5, slot_beginn: '15:00:00' }).then(() => gesendet);
  };
  let gs = await storno({ mitteilung: { status: 'offen', sitzung: 'abgelaufen', ids: [11],
    grund: 'Ihre WebUntis-Anmeldung ist abgelaufen. Bitte melden Sie sich neu an.' } });
  const k = lauf.kasten[0] || {};
  pruefe('abgelaufen: Kasten „gespeichert, aber noch NICHT verschickt“, Knopf „Anmelden und senden“',
    lauf.kasten.length === 1 && /gespeichert, aber noch NICHT verschickt/.test(k.text || '')
    && /abgelaufen/.test(k.text || '') && k.knopf === 'Anmelden und senden');
  if (k.aktion) await k.aktion();
  pruefe('… nach der Anmeldung geht DIESELBE Mitteilung hinaus (Kennung 11)',
    gs.length === 1 && JSON.stringify(gs[0]) === '[11]');
  await storno({ mitteilung: { status: 'gesendet', sitzung: null, ids: [12], grund: '' } });
  pruefe('gesendet: Erfolgsmeldung, kein Kasten',
    lauf.kasten.length === 0 && lauf.meldungen.length === 1 && lauf.meldungen[0][1] === 'ok');
  await storno({ mitteilung: { status: 'offen', sitzung: 'nicht_erreichbar', ids: [13],
    grund: 'WebUntis ist gerade nicht erreichbar.' } });
  pruefe('nicht erreichbar: kein Kasten, Meldung nennt den Grund und „NICHT verschickt“',
    lauf.kasten.length === 0 && /NICHT verschickt/.test(lauf.meldungen[0][0]) && /nicht erreichbar/.test(lauf.meldungen[0][0]));
  await storno({ mitteilung: { status: 'fehler', sitzung: null, ids: [14], grund: 'Keine Berechtigung (HTTP 403).' } });
  pruefe('von WebUntis abgelehnt: keine Erfolgsmeldung, der Grund steht da',
    lauf.kasten.length === 0 && lauf.meldungen[0][1] === 'fehler' && /403/.test(lauf.meldungen[0][0]));

  // ----------------------------------------------------------
  console.log('4. Stellvertretend: ohne Sitzung nicht gebucht');
  zuruecksetzen();
  const S4 = { svLaeuft: false, svKind: 90042, aktiverSprechtag: { id: 1 } };
  const aufrufe = [];
  const sv = new Function('S', 'api', 'meldung', 'ladeSvRaster', 'sitzungAuswerten', 'sendeVorgemerkte',
    'return async function stellvertretendBuchen(lehrerId, slot) {' + R.sv + '};')(
    S4, async (p, o) => { aufrufe.push(o.body); const f = new Error('Ihre WebUntis-Anmeldung ist abgelaufen.'); f.sitzung = 'abgelaufen'; throw f; },
    meldung, async () => {}, auswerten, async () => {});
  await sv(7, '15:00');
  const k4 = lauf.kasten[0] || {};
  pruefe('Kasten „noch NICHT eingetragen“, Knopf „Anmelden und buchen“; das Kind bleibt gewählt',
    /NICHT eingetragen/.test(k4.text || '') && k4.knopf === 'Anmelden und buchen' && S4.svKind === 90042 && S4.svLaeuft === false);
  if (k4.aktion) await k4.aktion();
  pruefe('… nach der Anmeldung derselbe Auftrag (Lehrkraft, Zeit, Kind)',
    aufrufe.length === 2 && JSON.stringify(aufrufe[1]) === JSON.stringify(aufrufe[0])
    && aufrufe[1].slot_beginn === '15:00' && aufrufe[1].schueler_id === 90042);

  // ----------------------------------------------------------
  console.log('5. Der Kasten: erst anmelden, dann der Auftrag');
  const baueKasten = (S, apiFn2, kAuftrag) => {
    const werte = { 'sk-benutzer': 'lehrkraft.erfunden', 'sk-passwort': 'geheim' };
    const document = { createElement: (t) => el(t) };
    const feld = (l, id, typ, w) => { const x = el('label', null, l); const i = el('input'); i.id = id; i.type = typ || 'text'; i.value = w || ''; x.appendChild(i); return x; };
    const knopf = (t, kl, f) => { const b = el('button', kl, t); b.hoerer.click = f; return b; };
    const box = new Function('S', 'el', 'feld', 'knopf', 'wert', 'api', 'meldung', 'document',
      'return function (k) {' + R.kasten + '};')(
      S, el, feld, knopf, (id) => werte[id] || '', apiFn2, meldung, document)(kAuftrag);
    return { box, werte };
  };
  zuruecksetzen();
  const S5 = { sitzungsKasten: { ansicht: 'lehrkraft' }, benutzername: 'lehrkraft.erfunden', user: null };
  const logins = []; let ausgefuehrt = 0;
  const { box } = baueKasten(S5, async (p, o) => { logins.push([p, o.body]); return { rolle: 'lehrkraft' }; },
    { text: 'T', knopf: 'Anmelden und senden', aktion: async () => { ausgefuehrt++; } });
  const form = alle(box).find((x) => x.tag === 'form');
  const pw = alle(box).find((x) => x.tag === 'input' && x.id === 'sk-passwort');
  const bn = alle(box).find((x) => x.tag === 'input' && x.id === 'sk-benutzer');
  const sub = alle(box).find((x) => x.tag === 'button' && x.type === 'submit');
  pruefe('Kasten: role=alert, Passwortfeld, Benutzername vorausgefüllt, Knopf trägt den Auftrag',
    box.attr.role === 'alert' && pw && pw.type === 'password' && bn && bn.value === 'lehrkraft.erfunden'
    && sub && sub.text === 'Anmelden und senden');
  pruefe('ein <form> mit submit – die Eingabetaste meldet an', !!form && typeof form.hoerer.submit === 'function');
  if (form && form.hoerer.submit) await form.hoerer.submit({ preventDefault() {} });
  pruefe('Anmeldung über /api/auth/login, dann genau einmal der Auftrag; Kasten weg',
    logins.length === 1 && logins[0][0] === '/api/auth/login' && logins[0][1].passwort === 'geheim'
    && ausgefuehrt === 1 && S5.sitzungsKasten === null && S5.user && S5.user.rolle === 'lehrkraft');
  zuruecksetzen();
  const S5b = { sitzungsKasten: { ansicht: 'lehrkraft' }, benutzername: '', user: { rolle: 'lehrkraft' } };
  let ausgefuehrtB = 0;
  const kb = baueKasten(S5b, async () => { throw new Error('Anmeldung fehlgeschlagen'); },
    { text: 'T', knopf: 'Anmelden und senden', aktion: async () => { ausgefuehrtB++; } }).box;
  const formB = alle(kb).find((x) => x.tag === 'form');
  if (formB && formB.hoerer.submit) await formB.hoerer.submit({ preventDefault() {} });
  pruefe('falsches Passwort: KEIN Auftrag, der Kasten bleibt, Fehlermeldung',
    ausgefuehrtB === 0 && S5b.sitzungsKasten !== null && lauf.meldungen.some(([, a]) => a === 'fehler'));

  // ----------------------------------------------------------
  console.log('6. Hinweis nach der Anmeldung');
  const S6 = { offenHinweis: { anzahl: 2, ids: [305, 307] } };
  const gesendet6 = [];
  const knopf6 = (t, kl, f) => { const b = el('button', kl, t); b.hoerer.click = f; return b; };
  const h = new Function('S', 'el', 'knopf', 'sendeVorgemerkte', 'wechsleAnsicht', 'return function () {' + R.hinweis + '};')(
    S6, el, knopf6, (ids) => gesendet6.push(ids), () => {})();
  const jetzt = alle(h).find((x) => x.tag === 'button' && x.text === 'Jetzt senden');
  pruefe('nennt die Anzahl: „2 Mitteilungen … noch nicht verschickt“', /^2 Mitteilungen .*noch nicht verschickt/.test(texte(h).replace(/^ \| /, '').split(' | ').find((t) => /Mitteilung/.test(t)) || ''));
  if (jetzt) jetzt.hoerer.click();
  pruefe('„Jetzt senden“ schickt genau diese Kennungen', gesendet6.length === 1 && JSON.stringify(gesendet6[0]) === '[305,307]');
  // sendeVorgemerkte: abgelaufen → Kasten, danach derselbe Aufruf
  zuruecksetzen();
  const body7 = [];
  let hinweisGeladen = 0;
  const senden = new Function('S', 'api', 'meldung', 'sitzungAuswerten', 'ladeOffenHinweis',
    'return async function sendeVorgemerkte(ids, text) {' + R.senden + '};')(
    { aktiverSprechtag: null }, async (p, o) => { body7.push([p, o.body]); return { gesendet: 0, fehler: 0, ids: o.body.ids,
      sitzung: 'abgelaufen', grund: 'Ihre WebUntis-Anmeldung ist abgelaufen.' }; },
    meldung, auswerten, () => { hinweisGeladen++; });
  await senden([305, 307]);
  const k7 = lauf.kasten[0] || {};
  pruefe('sendeVorgemerkte bei abgelaufener Sitzung: POST /api/mitteilungen/senden mit den Kennungen, dann Kasten',
    body7.length === 1 && body7[0][0] === '/api/mitteilungen/senden' && JSON.stringify(body7[0][1].ids) === '[305,307]'
    && k7.knopf === 'Anmelden und senden');
  if (k7.aktion) await k7.aktion();
  pruefe('… nach der Anmeldung derselbe Aufruf mit denselben Kennungen',
    body7.length === 2 && JSON.stringify(body7[1][1].ids) === '[305,307]');

  // ----------------------------------------------------------
  console.log('7. Kein Dienstkonto mehr in der Oberfläche');
  const code = js.replace(/\/\/[^\n]*/g, '');
  pruefe('kein Abruf von /api/dienstkonto, kein S.dienstkonto', !code.includes('/api/dienstkonto') && !code.includes('S.dienstkonto'));
  const mitt = rumpf('function ansichtMitteilungen(');
  pruefe('Mitteilungen versenden ohne Zugangsdaten-Felder', mitt !== '' && !mitt.includes('mv-passwort')
    && mitt.includes("'/api/mitteilungen/senden'") && mitt.includes('sitzungAuswerten(d.sitzung'));
  // v0.9.82 (Zug 4, Schritt 4): Der Abgleich mit eingetippten Zugangsdaten
  // ist fort – bis v0.9.81 prüfte diese Stelle, dass er sie übergibt.
  // (Das Feld sync-passwort gibt es weiter: im Stammdaten-Abgleich der
  // Sprechtage-Seite, ansichtAdminSprechtage.)
  const daten = rumpf('function ansichtAdminDaten(');
  pruefe('kein Abgleich der Schülerliste mit eingetippten Zugangsdaten mehr (Admin-Seite ohne Passwortfeld, kein /api/schueler/sync)',
    daten !== '' && !daten.includes('passwort') && !code.includes('/api/schueler/sync'));
  const zr = rumpf('function zeichne(');
  pruefe('Hinweis nicht in „Mitteilungen“ – dort steht derselbe Stand als Abschnitt (Quelltext)',
    zr !== '' && /S\.offenHinweis && S\.user && S\.ansicht !== 'login'\s*&& S\.ansicht !== 'mitteilungen'\)/.test(zr));
  pruefe('der Kasten steht nur in der Ansicht, in der gehandelt wurde (Quelltext)',
    zr.includes('S.sitzungsKasten && S.sitzungsKasten.ansicht === S.ansicht'));
  const login = rumpf('function ansichtLogin(');
  const pL = login.indexOf("api('/api/auth/login'"), pH = login.indexOf('ladeOffenHinweis()');
  pruefe('nach der Anmeldung wird der Hinweis geladen (Reihenfolge im Quelltext)', pL > 0 && pH > pL);

  // ----------------------------------------------------------
  console.log('8. Einladen ohne Sitzung (Zug 4, E20 C): nicht eingeladen, danach die übrigen');
  zuruecksetzen();
  const posts = [];
  let abgelaufenAb = 2;       // das zweite Kind trifft auf die abgelaufene Sitzung
  const apiE = async (p, o) => {
    posts.push(o.body.schueler_id);
    if (posts.length >= abgelaufenAb && abgelaufenAb > 0) {
      abgelaufenAb = 0;       // nur einmal – nach der Anmeldung gelingt es
      const f = new Error('Ihre WebUntis-Anmeldung ist abgelaufen. Bitte melden Sie sich neu an.');
      f.sitzung = 'abgelaufen';
      throw f;
    }
    return { mitteilung: { status: 'gesendet', ids: [] } };
  };
  const einladen = new Function('S', 'api', 'meldung', 'ladeEinladungen', 'sitzungAuswerten', 'sendeVorgemerkte',
    'return async function einladenAusfuehren(ids, hinweis) {' + R.einladen + '};')(
    { aktiverSprechtag: { id: 1 } }, apiE, meldung, async () => {}, auswerten, async () => {});
  await einladen([601, 602, 603], 'Bitte kommen');
  const k8 = lauf.kasten[0] || {};
  pruefe('hält beim ersten Kind ohne Sitzung an: 603 wird nicht mehr versucht',
    JSON.stringify(posts) === '[601,602]');
  pruefe('Kasten: „1 Einladung(en) angelegt. 2 noch NICHT eingeladen“, Knopf „Anmelden und einladen“',
    /1 Einladung\(en\) angelegt\. 2 noch NICHT eingeladen/.test(k8.text || '') && k8.knopf === 'Anmelden und einladen');
  pruefe('„Später“ sagt: nicht eingeladen (nicht „Mitteilung gespeichert“)',
    /^Nicht eingeladen\./.test(k8.spaeter || ''));
  if (k8.aktion) await k8.aktion();
  pruefe('… nach der Anmeldung genau die übrigen (602, 603), 601 nicht doppelt',
    JSON.stringify(posts) === '[601,602,602,603]');
  zuruecksetzen();
  const postsN = [];
  const einladenN = new Function('S', 'api', 'meldung', 'ladeEinladungen', 'sitzungAuswerten', 'sendeVorgemerkte',
    'return async function einladenAusfuehren(ids, hinweis) {' + R.einladen + '};')(
    { aktiverSprechtag: { id: 1 } }, async (p, o) => { postsN.push(o.body.schueler_id);
      const f = new Error('WebUntis ist gerade nicht erreichbar.'); f.sitzung = 'nicht_erreichbar'; throw f; },
    meldung, async () => {}, auswerten, async () => {});
  await einladenN([601, 602], '');
  pruefe('nicht erreichbar: kein Kasten, Fehlermeldung, kein weiterer Versuch',
    lauf.kasten.length === 0 && JSON.stringify(postsN) === '[601]'
    && lauf.meldungen.some(([t, a]) => a === 'fehler' && /NICHT eingeladen/.test(t)));
  zuruecksetzen();
  const einladenF = new Function('S', 'api', 'meldung', 'ladeEinladungen', 'sitzungAuswerten', 'sendeVorgemerkte',
    'return async function einladenAusfuehren(ids, hinweis) {' + R.einladen + '};')(
    { aktiverSprechtag: { id: 1 } }, async () => { throw new Error('Dieses Kind steht nicht in der Klassenliste aus WebUntis.'); },
    meldung, async () => {}, auswerten, async () => {});
  await einladenF([601], '');
  pruefe('anderer Fehler (404): kein Kasten, der Grund steht in der Meldung',
    lauf.kasten.length === 0 && lauf.meldungen.some(([t, a]) => a === 'fehler' && /Klassenliste/.test(t)));
  const ui = rumpf('function einlTrefferZeichnen(');   // seit v0.9.81 dort
  pruefe('Knopf „Ausgewählte einladen“ ruft einladenAusfuehren (Aufrufstelle)',
    ui !== '' && /await einladenAusfuehren\(ids, hinweis\);/.test(ui));

  console.log('9. „Später“ sagt, was geschehen ist');
  zuruecksetzen();
  const S9 = { sitzungsKasten: { ansicht: 'x' }, benutzername: '', user: null };
  const box9 = baueKasten(S9, async () => ({}), { text: 'T', knopf: 'K', spaeter: 'Nicht gebucht. Der Termin ist nicht eingetragen.' }).box;
  const sp9 = alle(box9).find((x) => x.tag === 'button' && x.text === 'Später');
  if (sp9) sp9.hoerer.click();
  pruefe('Kasten mit eigenem „Später“-Text: genau dieser', lauf.meldungen.length === 1
    && lauf.meldungen[0][0] === 'Nicht gebucht. Der Termin ist nicht eingetragen.');
  zuruecksetzen();
  const box9b = baueKasten({ sitzungsKasten: {}, benutzername: '' }, async () => ({}), { text: 'T', knopf: 'K' }).box;
  const sp9b = alle(box9b).find((x) => x.tag === 'button' && x.text === 'Später');
  if (sp9b) sp9b.hoerer.click();
  pruefe('ohne eigenen Text: wie bisher „Die Mitteilung bleibt gespeichert“ (Absage, Abnahmetest)',
    lauf.meldungen.length === 1 && /Die Mitteilung bleibt gespeichert/.test(lauf.meldungen[0][0]));
  pruefe('stellvertretend gibt „Nicht gebucht“ als „Später“-Text mit (Quelltext)',
    /spaeter: 'Nicht gebucht\. Der Termin ist nicht eingetragen\.'/.test(R.sv));

  console.log('10. Elternbuchung ohne Kinddaten und ohne Sitzung (v0.9.76): nicht gebucht, Kasten, derselbe Klick');
  zuruecksetzen();
  const posts10 = []; const toasts10 = [];
  let ab10 = true;
  const api10 = async (p, o) => {
    posts10.push([p, o.body.lehrer_id, o.body.slot_beginn, o.body.kommentar]);
    if (ab10) {
      ab10 = false;
      const f = new Error('Ihre WebUntis-Anmeldung ist abgelaufen. Bitte melden Sie sich neu an.');
      f.sitzung = 'abgelaufen';
      throw f;
    }
    return { id: 1 };
  };
  const baueBuchen = (apiX) => new Function('S', 'api', 'ladeRaster', 'toast', 'sitzungAuswerten',
    'return async function buchen(lehrerId, slot, kommentar) {' + R.buchen + '};')(
    { aktiverSprechtag: { id: 1 }, kind: 601 }, apiX, async () => {}, (t, a) => toasts10.push([t, a]), auswerten);
  await baueBuchen(api10)(7, '16:10', ' Frage ');
  const k10 = lauf.kasten[0] || {};
  pruefe('abgelaufen: kein „gebucht“, Kasten „Anmelden und buchen“, Text sagt NICHT eingetragen',
    !toasts10.some(([, a]) => a === 'ok') && k10.knopf === 'Anmelden und buchen'
    && /NICHT eingetragen/.test(k10.text || ''));
  pruefe('„Später“ sagt: nicht gebucht', /^Nicht gebucht\./.test(k10.spaeter || ''));
  if (k10.aktion) await k10.aktion();
  pruefe('… nach der Anmeldung derselbe Termin (Lehrkraft, Uhrzeit, Hinweis), dann „gebucht“',
    posts10.length === 2 && JSON.stringify(posts10[1]) === JSON.stringify(['/api/buchungen', 7, '16:10', 'Frage'])
    && toasts10.some(([t, a]) => a === 'ok' && /16:10/.test(t)));
  zuruecksetzen(); toasts10.length = 0;
  await baueBuchen(async () => { const f = new Error('WebUntis ist gerade nicht erreichbar.');
    f.sitzung = 'nicht_erreichbar'; throw f; })(7, '16:10', '');
  pruefe('nicht erreichbar: kein Kasten, Fehlermeldung, kein „gebucht“',
    lauf.kasten.length === 0 && !toasts10.some(([, a]) => a === 'ok')
    && (lauf.meldungen.some(([, a]) => a === 'fehler') || toasts10.some(([, a]) => a === 'fehler')));

  console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' ROT');
  process.exit(fehler === 0 ? 0 : 1);
})();
