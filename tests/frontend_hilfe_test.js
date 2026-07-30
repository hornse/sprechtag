// ============================================================
// tests/frontend_hilfe_test.js
// Prüft v0.9.17: Hilfe-Seite ohne Anmeldung, Schnellanleitung/Handbuch/FAQ,
// Links im Login-Screen und in der Sidebar.
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

pruefe('Hilfe-Ansicht definiert', js.includes('function ansichtHilfe('));
pruefe('in der Ansichten-Map', js.includes('hilfe: ansichtHilfe'));
pruefe('drei Abschnitte: Schnellanleitung, Handbuch, FAQ',
  js.includes("sektion('Schnellanleitung')") && js.includes("sektion('Handbuch')")
  && js.includes("sektion('Häufige Fragen')"));
pruefe('Rollen abgedeckt (Eltern, Lehrkräfte, Administration)',
  js.includes('Für Erziehungsberechtigte') && js.includes('Für Lehrkräfte')
  && js.includes('Für die Administration'));
pruefe('Login-Screen verlinkt Hilfe',
  js.includes("wechsleAnsicht('hilfe')") && js.includes('login-hilfe'));
pruefe('Sidebar hat Hilfe-Eintrag', js.includes("navKnopf('hilfe', 'Hilfe'"));
pruefe('ohne Anmeldung erreichbar (Zur-Anmeldung-Weg)',
  js.includes('!S.user') && js.includes("wechsleAnsicht('login')"));
pruefe('Hilfe-CSS vorhanden', css.includes('.hilfe-nav') && css.includes('.hilfe-liste'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
