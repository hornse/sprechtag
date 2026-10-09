// ============================================================
// tests/frontend_meine_termine_test.js
// Prüft v0.9.60: „Meine Termine“ gibt keine falsche Auskunft.
//
//   1. Nach Anmeldung oder Neuladen stand „Meine Termine: noch keine
//      gebucht“, obwohl Termine bestanden: Die Liste startete als [] statt
//      als „nicht geladen“ (null), und geladen wird nur bei null.
//   2. Ein Wechsel des Sprechtags ließ die Termine des vorigen stehen.
//   3. Eine späte Antwort für einen inzwischen abgewählten Sprechtag
//      überschrieb die richtige.
//   4. Nach dem Abmelden lebte der Zustand des vorigen Kontos weiter –
//      wer sich danach im selben Browser anmeldete, sah dessen Termine
//      (und dessen persönlichen Kalender-Link). Abmelden lädt die Seite
//      deshalb neu.
//
// Die Funktionen werden ausgeführt (Rümpfe aus app.js, Umgebung als
// Ersatz), nicht nur gesucht.
//
// Aufruf: node tests/frontend_meine_termine_test.js
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

// Startzustand so, wie app.js ihn anlegt (das Objektliteral selbst).
const sRumpf = rumpf('const S =');
let S0 = null;
try { S0 = new Function('return {' + sRumpf + '};')(); } catch (e) { S0 = null; }

// ------------------------------------------------------------
console.log('1. Frisch angemeldet / neu geladen');
pruefe('Startwert der eigenen Termine ist „nicht geladen“ (null), nicht „keine“ ([])',
  !!S0 && S0.meineBuchungen === null);

// zeichneTermineKompakt mit dem Startzustand: Es muss laden, nicht
// „noch keine gebucht“ zeichnen.
const kompaktRumpf = rumpf('function zeichneTermineKompakt(');
function kompakt(S) {
  const titel = [];
  let geladen = 0;
  const el = () => ({ appendChild() {}, addEventListener() {}, setAttribute() {}, classList: { add() {} } });
  const block = (id, t) => { titel.push(t); return el(); };
  try {
    new Function('S', 'block', 'el', 'ladeMeineBuchungen', 'knopf', 'wechsleAnsicht', kompaktRumpf)(
      S, block, el, () => { geladen++; }, el, () => {});
  } catch (e) { titel.push('AUSNAHME ' + e.message); }
  return { titel, geladen };
}
const frisch = kompakt(Object.assign({}, S0 || {}, { meineLaedt: false }));
pruefe('Startzustand: Übersicht lädt die Termine und behauptet nicht „noch keine gebucht“',
  kompaktRumpf !== '' && frisch.geladen === 1 && !frisch.titel.some((t) => /noch keine/.test(String(t))));

// ------------------------------------------------------------
console.log('2. Sprechtag wechseln');
const waehlerRumpf = rumpf('function sprechtagWaehler(');
function wechsel(S, beiWechsel) {
  let handler = null;
  const sel = { addEventListener(ev, f) { if (ev === 'change') handler = f; } };
  const auswahl = () => ({ querySelector: () => sel });
  const el = () => ({ appendChild() {} });
  const ziel = { appendChild() {} };
  try {
    new Function('S', 'el', 'auswahl', 'zeichne', 'phaseText', 'ziel', 'beiWechsel', waehlerRumpf)(
      S, el, auswahl, () => {}, () => '', ziel, beiWechsel);
    if (handler) handler({ target: { value: '8' } });
  } catch (e) { return 'AUSNAHME ' + e.message; }
  return handler ? 'ok' : 'kein Handler';
}
const S2 = { sprechtage: [{ id: '7', name: 'A', datum: 'x', phase: 'phase2' }, { id: '8', name: 'B', datum: 'y', phase: 'phase2' }],
  meineBuchungen: [{ id: 1 }], meineLaedt: true, lehrerListe: [], gewaehlteLehrkraft: 1, raster: [] };
S2.aktiverSprechtag = S2.sprechtage[0];
const w = wechsel(S2);
pruefe('Wechsel des Sprechtags verwirft die Termine des vorigen (gilt für jede Ansicht)',
  waehlerRumpf !== '' && w === 'ok' && S2.aktiverSprechtag.id === '8'
  && S2.meineBuchungen === null && S2.meineLaedt === false);

// ------------------------------------------------------------
console.log('3. Späte Antwort');
const ladeRumpf = rumpf('async function ladeMeineBuchungen(');
async function lade(wechselnAuf) {
  const S = { aktiverSprechtag: { id: '7' }, meineBuchungen: null, meineLaedt: true };
  let freigeben;
  const api = () => new Promise((f) => { freigeben = f; });
  const lauf = new Function('S', 'api', 'meldung',
    'return (async () => {' + ladeRumpf + '})();')(S, api, () => {});
  if (wechselnAuf) S.aktiverSprechtag = { id: wechselnAuf };
  freigeben({ buchungen: [{ id: 99, sprechtag: '7' }] });
  try { await lauf; } catch (e) { return { S, fehler: e.message }; }
  return { S };
}

(async () => {
  const gleich = await lade(null);
  pruefe('Antwort für den gewählten Sprechtag wird übernommen',
    ladeRumpf !== '' && Array.isArray(gleich.S.meineBuchungen) && gleich.S.meineBuchungen.length === 1);
  const spaet = await lade('8');
  pruefe('Antwort für einen inzwischen abgewählten Sprechtag wird verworfen',
    ladeRumpf !== '' && !spaet.fehler && spaet.S.meineBuchungen === null);

  // ----------------------------------------------------------
  console.log('4. Abmelden');
  const abRumpf = rumpf('async function abmelden(');
  const ersetzt = [];
  let gezeichnet = 0;
  const loc = { pathname: '/', search: '', hash: '#/meine', replace(u) { ersetzt.push(u); } };
  try {
    await new Function('S', 'api', 'location', 'zeichne',
      'return (async () => {' + abRumpf + '})();')(
      { user: { id: 1 }, meineBuchungen: [{ id: 1 }], kalenderLink: 'x' },
      async () => ({}), loc, () => { gezeichnet++; });
  } catch (e) { ersetzt.push('AUSNAHME ' + e.message); }
  pruefe('Abmelden lädt die Seite ohne Hash neu – kein Zustand des Kontos lebt weiter',
    abRumpf !== '' && ersetzt.length === 1 && ersetzt[0] === '/' && gezeichnet === 0);

  console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
  process.exit(fehler === 0 ? 0 : 1);
})();
