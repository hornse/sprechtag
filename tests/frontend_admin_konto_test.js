// ============================================================
// tests/frontend_admin_konto_test.js
// Prüft v0.9.80 (E23-Nachtrag): Wer mit dem echten WebUntis-Admin-Konto
// angemeldet ist, sieht eine Warnung. Sie nennt, WAS nicht hinausgeht,
// dass die Nachrichten offen stehen bleiben, und den Weg: ein Lehrerkonto
// in admin_kuerzel. Der sichtbare Text wird ausgeführt.
// Aufruf: node tests/frontend_admin_konto_test.js
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
  return { tag, klasse: klasse || '', text: text === undefined ? '' : String(text), kinder: [], attr: {},
    appendChild(k) { this.kinder.push(k); return k; },
    setAttribute(n, v) { this.attr[n] = v; } };
}
const alle = (k) => [k].concat(...k.kinder.map(alle));

const r = rumpf('function adminKontoWarnungElement()');
pruefe('Voraussetzung: Rumpf adminKontoWarnungElement gefunden', r !== '');
let box = null;
try { box = new Function('el', r)(el); } catch (e) { console.log('    (' + e.message + ')'); }
const text = box ? alle(box).map((x) => x.text).join(' ') : '';

pruefe('nennt, was nicht hinausgeht: Absagen, Ausfälle, Einladungen, Bestätigungen an Eltern',
  /Absagen/.test(text) && /Ausfälle/.test(text) && /Einladungen/.test(text)
  && /Bestätigungen/.test(text) && /an Eltern/.test(text));
pruefe('sagt, dass die Nachrichten offen stehen bleiben', /bleiben[^.]*offen stehen/.test(text));
pruefe('nennt den Weg: Lehrerkonto, dessen Kürzel in admin_kuerzel steht',
  /Lehrerkonto/.test(text) && /admin_kuerzel/.test(text));
pruefe('ist eine Warnung (Klasse meldung fehler, role alert)',
  !!box && /\bmeldung\b/.test(box.klasse) && /\bfehler\b/.test(box.klasse) && box.attr.role === 'alert');

const z = rumpf('function zeichne(');
pruefe('zeichne() zeigt sie nur mit admin_konto und nicht auf der Anmeldeseite (Quelltext)',
  /if \(S\.user && S\.user\.admin_konto && S\.ansicht !== 'login'\) \{?\s*ziel\.appendChild\(adminKontoWarnungElement\(\)\)/.test(z));
pruefe('genau eine Darstellung: einmal aufgerufen, nur in zeichne()',
  (js.match(/adminKontoWarnungElement\(\)/g) || []).length === 2 && z.includes('adminKontoWarnungElement()'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' ROT');
process.exit(fehler === 0 ? 0 : 1);
