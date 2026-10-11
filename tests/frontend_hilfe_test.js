// ============================================================
// tests/frontend_hilfe_test.js
// Prüft v0.9.17: Hilfe-Seite ohne Anmeldung, Links im Login-Screen und in
// der Sidebar – seit v0.9.89 gegliedert nach Rollen (Betreiber).
//
// v0.9.89: ansichtHilfe wird AUSGEFÜHRT (Ersatzknoten) und am Gezeigten
// geprüft: welche Abschnitte in welcher Reihenfolge, welche Sprungmarken,
// welche zuerst. Die übrigen Stellen werden im Rumpf ihrer Funktion
// gesucht, nicht in der Datei. Bis v0.9.88 suchte die Suite in ganz app.js
// und hielt die alte Gliederung (Schnellanleitung, Handbuch, FAQ) fest;
// die Rollen-Überschriften fand sie überall
// (docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md).
//
// Aufruf: node tests/frontend_hilfe_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const js  = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

// ---- ansichtHilfe ausführen ----
function knoten(tag, klasse, text) {
  return { tag, klasse: klasse || '', text: text == null ? '' : String(text), kinder: [], id: '', href: '',
    appendChild(k) { this.kinder.push(k); return k; }, addEventListener() {}, setAttribute() {},
    set innerHTML(h) { this.html = h; } };
}
const alle = (k) => [k].concat(...(k.kinder || []).map(alle));
const hilfeRumpf = rumpf('function ansichtHilfe(');
const teileQuelle = (js.match(/^const HILFE_TEILE = (\[[\s\S]*?\n\]);/m) || [])[1];
let TEILE = null;
try { TEILE = teileQuelle ? new Function('return ' + teileQuelle)() : null; } catch (e) { TEILE = null; }
const sprungRumpf = rumpf('function hilfeSprungmarken(');
const sprungmarken = (rolle) => new Function('HILFE_TEILE', 'rolle', sprungRumpf)(TEILE, rolle);
function hilfe(user) {
  const ziel = knoten('main');
  const S = { user, hilfeZusatz: '', offeneBloecke: {} };
  const sektion = (t) => { const s = knoten('section', 'sektion'); s.kinder.push(knoten('h3', null, t)); return s; };
  const namen = ['S', 'el', 'document', 'sektion', 'block', 'knopf', 'api', 'wechsleAnsicht', 'zeichne',
    'kontaktSatz', 'hilfeListe', 'hilfeSchluessel', 'datenschutzAbsaetze', 'HILFE_TEILE', 'hilfeSprungmarken'];
  const werte = [S, knoten, { createElement: (t) => knoten(t) }, sektion,
    (k, t) => knoten('details', null, t), (t) => knoten('button', null, t), () => Promise.resolve({}),
    () => {}, () => {}, (v) => v, () => knoten('ol', 'hilfe-liste'), (t) => t, () => ['A', 'B'],
    TEILE, sprungmarken];
  try { new Function(...namen, 'ziel', hilfeRumpf)(...werte, ziel); }
  catch (e) { console.log('    (ansichtHilfe: ' + e.message + ')'); return null; }
  return ziel;
}
const ohne = hilfe(null);
const abschnitte = ohne ? ohne.kinder.filter((k) => k.tag === 'section') : [];
const titel = abschnitte.map((s) => s.kinder[0].text);

pruefe('Hilfe-Ansicht definiert und ausführbar', hilfeRumpf !== '' && !!ohne);
pruefe('in der Ansichten-Map', /\bhilfe: ansichtHilfe,/.test(rumpf('function zeichne()')));
pruefe('Abschnitte nach Rollen, in dieser Reihenfolge, je einmal',
  JSON.stringify(titel) === JSON.stringify(['Für Erziehungsberechtigte', 'Für Lehrkräfte',
    'Für die Administration', 'Häufige Fragen', 'Datenschutz']));
pruefe('jede Rolle beginnt mit ihrer Schritt-Liste (Eltern, Lehrkräfte, Administration)',
  abschnitte.length >= 3 && abschnitte.slice(0, 3).every((s) => s.kinder[1] && s.kinder[1].klasse === 'hilfe-liste'));
const marken = ohne ? alle(ohne).filter((k) => k.klasse === 'hilfe-sprung') : [];
pruefe('eine Sprungmarke je Abschnitt, jede auf einen vorhandenen Abschnitt',
  marken.length === abschnitte.length && abschnitte.length === 5
  && marken.every((m) => abschnitte.some((s) => '#' + s.id === m.href)));
// Allen alles, die eigene Rolle vorn (Betreiber, v0.9.89).
const erste = (rolle) => { const z = hilfe(rolle === null ? null : { rolle });
  const m = z ? alle(z).filter((k) => k.klasse === 'hilfe-sprung') : [];
  return m.length === 5 ? m[0].href : 'FEHLT'; };
pruefe('Sprungmarke der eigenen Rolle zuerst, keine fehlt',
  erste('eltern') === '#hilfe-eltern' && erste('schueler') === '#hilfe-eltern'
  && erste('lehrkraft') === '#hilfe-lehrkraefte' && erste('admin') === '#hilfe-verwaltung'
  && erste(null) === '#hilfe-eltern');
const login = rumpf('function ansichtLogin(ziel)');
pruefe('Login-Screen verlinkt Hilfe',
  login.includes("el('p', 'login-hilfe')") && login.includes("wechsleAnsicht('hilfe')"));
pruefe('Sidebar hat Hilfe-Eintrag', rumpf('function zeichneNavigation()').includes("navKnopf('hilfe', 'Hilfe'"));
const knoepfe = (z) => (z ? alle(z).filter((k) => k.tag === 'button' && k.text === 'Zur Anmeldung').length : -1);
pruefe('ohne Anmeldung erreichbar: „Zur Anmeldung“ nur ohne Anmeldung',
  knoepfe(ohne) === 1 && knoepfe(hilfe({ rolle: 'eltern' })) === 0);
pruefe('Hilfe-CSS vorhanden (Regeln .hilfe-nav und .hilfe-liste)',
  /^\.hilfe-nav \{[^}]*display: flex/m.test(css) && /^\.hilfe-liste \{/m.test(css));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
