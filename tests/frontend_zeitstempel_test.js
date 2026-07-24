// ============================================================
// tests/frontend_zeitstempel_test.js
// Prüft den Helfer zeitstempel() aus app.js: DB-Zeitstempel
// ("YYYY-MM-DD HH:MM:SS") werden als "TT.MM. HH:MM" angezeigt,
// leere/ungültige Werte ergeben einen Gedankenstrich.
//
// Grund: In der Mitteilungsliste war unklar, von wann ein Eintrag
// ist. Die Spalte zeigt jetzt gesendet_am bzw. angelegt_am – dieser
// Test sichert die Formatierung ab.
//
// Aufruf: node tests/frontend_zeitstempel_test.js
// ============================================================
'use strict';

const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

// zeitstempel() isoliert aus app.js holen, ohne den Browser-Rahmen
// auszuführen (app.js erwartet document etc.).
const src = fs.readFileSync(
  path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const m = src.match(/function zeitstempel\(iso\) \{[\s\S]*?\n\}/);
if (!m) {
  console.log('  ✗ zeitstempel() nicht in app.js gefunden');
  process.exit(1);
}
// eslint-disable-next-line no-eval
const zeitstempel = eval('(' + m[0].replace('function zeitstempel', 'function') + ')');

pruefe('Datum + Zeit', zeitstempel('2026-07-24 14:05:33') === '24.07. 14:05');
pruefe('ISO mit T', zeitstempel('2026-01-09T08:00:00') === '09.01. 08:00');
pruefe('leer -> Gedankenstrich', zeitstempel('') === '–');
pruefe('null -> Gedankenstrich', zeitstempel(null) === '–');
pruefe('undefined -> Gedankenstrich', zeitstempel(undefined) === '–');
pruefe('unbekanntes Format bleibt unverändert',
  zeitstempel('sofort') === 'sofort');

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
