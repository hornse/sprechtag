// ============================================================
// tests/frontend_bestaetigung_test.js
// Prüft v0.9.79 (E17-Nachtrag): Nach einer eigenen Buchung kommt keine
// Bestätigung mehr – Elternkonten dürfen in WebUntis nicht senden (403,
// gemessen 10.10.2026). Die FAQ sagt das, statt ein Dienstkonto zu
// versprechen (H13 aus docs/HILFE-ABGLEICH-2026-10-09.md).
// Gesucht wird im Rumpf von ansichtHilfe(), nicht in der Datei.
// Aufruf: node tests/frontend_bestaetigung_test.js
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
const hilfe = require('./rumpf.js').rumpf(js, 'function ansichtHilfe(');
pruefe('Voraussetzung: Rumpf ansichtHilfe gefunden', hilfe !== '');
// Die FAQ-Antwort zur Frage „… keine Bestätigung“: von der Frage bis zum
// Ende ihres Eintrags.
const a = hilfe.indexOf('aber es kam keine Bestätigung');
// Zusammengesetzte Zeichenketten ('…' + '…') zum sichtbaren Text verbinden.
const antwort = a < 0 ? '' : hilfe.slice(a, hilfe.indexOf("'],", a)).replace(/'\s*\+\s*'/g, '');
pruefe('Voraussetzung: FAQ-Eintrag zur Bestätigung gefunden', antwort !== '');
pruefe('FAQ: nach der eigenen Buchung kommt keine Nachricht',
  /Nach einer eigenen Buchung kommt keine Nachricht/.test(antwort));
pruefe('FAQ: die Buchung steht unter „Meine Termine“ und im Kalender-Abo',
  /Meine Termine/.test(antwort) && /Kalender-Abo/.test(antwort));
pruefe('FAQ: Bestätigung nur, wenn eine Lehrkraft gebucht hat (stellvertretend)',
  /nur, wenn eine Lehrkraft für Sie gebucht hat/.test(antwort));
pruefe('FAQ verspricht kein Dienstkonto mehr (H13)', !/Dienstkonto/.test(hilfe));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' ROT');
process.exit(fehler === 0 ? 0 : 1);
