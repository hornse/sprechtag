// ============================================================
// tests/frontend_pausen_test.js
// Prüft v0.9.23: dynamische Pausen (UI-Teile). Die Kernlogik wird in
// tests/run_slots.php (PHP) geprüft.
//
// Aufruf: node tests/frontend_pausen_test.js
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
const idx = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'index.php'), 'utf8');
const slots = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'slots.php'), 'utf8');

// ---- Admin-Formulare: Schalter ----
pruefe('Anlege-Formular hat dynamisch-Checkbox',
  js.includes("'neu-pause-dyn'") && js.includes('pause_dynamisch:'));
pruefe('Bearbeiten-Formular hat dynamisch-Checkbox',
  js.includes("'e-pausedyn-' + s.id") && js.includes('pause_dynamisch:'));
pruefe('Hinweis erklärt fest vs. dynamisch',
  js.includes('durchgehender Belegung'));

// ---- Pausen im Raster mit Uhrzeit ----
pruefe('Pausen-Block zeigt Uhrzeit',
  js.includes("'Pause ' + z.beginn"));

// ---- Backend verdrahtet ----
pruefe('pause_dynamisch wird gespeichert (INSERT + PATCH)',
  idx.includes('pause_dynamisch') && idx.includes("'pause_dynamisch'"));
pruefe('slot_pausen_anwenden existiert',
  slots.includes('function slot_pausen_anwenden'));
pruefe('dynamisches Raster ohne feste Pausen',
  slots.includes("(int)($sprechtag['pause_dynamisch'] ?? 0) === 1"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
