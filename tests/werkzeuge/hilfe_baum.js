// ============================================================
// tests/werkzeuge/hilfe_baum.js – Werkzeug, KEINE Prüfung (läuft nicht im
// Zähllauf). Beleg zu v0.9.89: Damit ist verglichen, dass die Neugliederung
// der Hilfe nur umsortiert hat.
//
// Führt ansichtHilfe einer Fassung von app.js aus und gibt den FERTIGEN
// Baum aus – Tiefensuche über die angehängten Kinder, also in
// Anzeigereihenfolge, nicht in Entstehungsreihenfolge. Je Zeile: Tiefe,
// Tag, Kennung, Text. Ein Werkzeug, das Texte beim Entstehen eines Knotens
// mitschreibt, sieht eine geänderte Anhängereihenfolge nicht.
//
// Aufruf: node tests/werkzeuge/hilfe_baum.js <app.js> [rolle]
//   rolle: eltern | schueler | lehrkraft | admin, ohne = nicht angemeldet
// Vergleich zweier Fassungen:
//   git show v0.9.88:frontend/app.js > /tmp/alt.js
//   diff <(node tests/werkzeuge/hilfe_baum.js /tmp/alt.js) \
//        <(node tests/werkzeuge/hilfe_baum.js frontend/app.js)
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');
const { rumpf } = require(path.join(__dirname, '..', 'rumpf.js'));
const js = fs.readFileSync(process.argv[2], 'utf8');
const rolle = process.argv[3] || null;
function knoten(tag, klasse, text) {
  return { tag, text: text == null ? '' : String(text), kennung: '', kinder: [],
    set textContent(t) { this.text = String(t); }, set innerHTML(h) {},
    appendChild(k) { this.kinder.push(k); return k; }, addEventListener() {}, setAttribute() {},
    set className(c) {}, set id(i) { this.kennung = i; }, get id() { return this.kennung; }, set href(h) {} };
}
const umgebung = {
  S: { user: rolle ? { rolle } : null, hilfeZusatz: '', offeneBloecke: {} },
  el: knoten, document: { createElement: (t) => knoten(t) },
  sektion: (t) => { const s = knoten('section'); s.appendChild(knoten('h3', null, t)); return s; },
  block: (k, t) => { const d = knoten('details'); d.appendChild(knoten('summary', null, t)); return d; },
  knopf: (t) => knoten('button', null, t), api: () => Promise.resolve({}),
  wechsleAnsicht() {}, zeichne() {}, kontaktSatz: (v) => v + ' KONTAKT.',
};
const name = (k) => rumpf(js, k);
const hilfeListe = new Function('document', 'punkte', name('function hilfeListe('));
const hilfeSchluessel = new Function('text', name('function hilfeSchluessel('));
const ds = new Function(name('function datenschutzAbsaetze()'));
const teileQuelle = (js.match(/const HILFE_TEILE = (\[[\s\S]*?\n\]);/) || [])[1];
const HILFE_TEILE = teileQuelle ? new Function('return ' + teileQuelle)() : null;
const spr = js.includes('function hilfeSprungmarken(')
  ? new Function('HILFE_TEILE', 'rolle', name('function hilfeSprungmarken(')) : null;
const ziel = knoten('main');
const namen = Object.keys(umgebung).concat(['hilfeListe', 'hilfeSchluessel', 'datenschutzAbsaetze',
  'HILFE_TEILE', 'hilfeSprungmarken', 'ziel']);
const werte = Object.values(umgebung).concat([(p) => hilfeListe(umgebung.document, p), hilfeSchluessel, ds,
  HILFE_TEILE, (r) => spr(HILFE_TEILE, r), ziel]);
new Function(...namen, name('function ansichtHilfe('))(...werte);
const aus = [];
(function lauf(k, t) {
  aus.push(t + ' ' + k.tag + (k.kennung ? '#' + k.kennung : '') + (k.text ? ' | ' + k.text : ''));
  for (const c of k.kinder) lauf(c, t + 1);
})(ziel, 0);
console.log(aus.join('\n'));
