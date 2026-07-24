// ============================================================
// tests/frontend_vollstaendig_test.js
// Statische Prüfung von frontend/app.js:
//   1. Jede in `ansichten` registrierte Funktion ist auch definiert.
//   2. Jede aufgerufene Hilfsfunktion existiert.
//   3. Die Datei ist syntaktisch gültig und in sich geschlossen.
//
// Hintergrund: Beim Umbau der Sondierungsansicht wurde versehentlich
// ansichtMitteilungen mitgelöscht. Da die Funktion in `ansichten` noch
// referenziert war, brach das Skript beim Start mit ReferenceError ab –
// die Seite blieb komplett leer.
//
// Aufruf: node tests/frontend_vollstaendig_test.js
// ============================================================
'use strict';

const fs = require('fs');
const pfad = __dirname + '/../frontend/app.js';
const quelle = fs.readFileSync(pfad, 'utf8');

let fehler = 0;
function pruefe(name, ok, zusatz) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name + (ok || !zusatz ? '' : ' -> ' + zusatz));
  if (!ok) fehler++;
}

// --- 1. Registrierte Ansichten -------------------------------
console.log('Ansichten-Registrierung');
const block = quelle.match(/const ansichten = \{([\s\S]*?)\};/);
pruefe('Registrierung gefunden', block !== null);

if (block) {
  const registriert = [...block[1].matchAll(/(\w+)\s*:\s*(\w+)/g)].map((m) => m[2]);
  pruefe('mindestens 8 Ansichten', registriert.length >= 8,
    'gefunden: ' + registriert.length);

  for (const fn of registriert) {
    const definiert = new RegExp('^(async )?function ' + fn + '\\s*\\(', 'm').test(quelle);
    pruefe('definiert: ' + fn, definiert, 'in app.js nicht gefunden');
  }
}

// --- 2. Aufgerufene Hilfsfunktionen --------------------------
console.log('Hilfsfunktionen');
const pflicht = ['el', 'knopf', 'feld', 'auswahl', 'wert', 'block',
                 'meldung', 'zeichne', 'api', 'sprechtagWaehler', 'phaseText'];
for (const fn of pflicht) {
  const definiert = new RegExp('^(async )?function ' + fn + '\\s*\\(', 'm').test(quelle);
  pruefe('definiert: ' + fn, definiert);
}

// --- 3. Ladefunktionen, die aus Ansichten gerufen werden -----
console.log('Ladefunktionen');
const geladen = [...quelle.matchAll(/\b(lade\w+)\s*\(/g)].map((m) => m[1]);
for (const fn of [...new Set(geladen)]) {
  const definiert = new RegExp('^(async )?function ' + fn + '\\s*\\(', 'm').test(quelle);
  pruefe('definiert: ' + fn, definiert);
}

// --- 4. Struktur ---------------------------------------------
console.log('Struktur');
pruefe('IIFE geschlossen', quelle.trimEnd().endsWith('})();'));
pruefe('start() wird aufgerufen', /^start\(\);/m.test(quelle));
const klammern = (quelle.match(/\{/g) || []).length - (quelle.match(/\}/g) || []).length;
pruefe('geschweifte Klammern ausgeglichen', klammern === 0, 'Differenz: ' + klammern);

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : `\n${fehler} TEST(S) ROT`);
process.exit(fehler === 0 ? 0 : 1);
