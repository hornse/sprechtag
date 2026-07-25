// ============================================================
// tests/frontend_halbtags_test.js
// Statische Prüfung von Halbtagskräften + Krankheitsausfall (v0.9.9):
//   - Admin-Block markiert Halbtagskräfte (PATCH stammdaten/lehrer)
//   - Lehrer-Zuweisung bietet Hälfte-Dropdown + Ausfall-Knopf
//   - Lehrkraft wählt im eigenen Raster ihre Hälfte selbst
//   - Backend: slot_haelfte_fenster, haelfte-PATCH, ausfall-Route
//
// Aufruf: node tests/frontend_halbtags_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const js    = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const idx   = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'index.php'), 'utf8');
const slots = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'slots.php'), 'utf8');
const buch  = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'buchungen.php'), 'utf8');
const sql   = fs.readFileSync(path.join(__dirname, '..', 'sql', '11_halbtags.sql'), 'utf8');

// ---- Frontend ----
pruefe('Halbtags-Markierung in der Lehrer-Tabelle',
  js.includes('Halbtagskraft / Referendar') && js.includes('/api/stammdaten/lehrer/'));
pruefe('Lehrer-Zuweisung: Hälfte-Dropdown für Halbtagskräfte',
  js.includes("'haelfte-' + s.id") && js.includes('erste Hälfte'));
pruefe('Lehrer-Zuweisung: Ausfall-Knopf',
  js.includes('lehrerAusfall') && js.includes("'/ausfall'"));
pruefe('Selbstbedienung: eigene Hälfte wählbar',
  js.includes('zeichneHaelfteWahl') && js.includes("'haelfte-eigen'"));
pruefe('Selbstbedienung nur bei halbtags und eigenem Raster',
  js.includes('S.svLehrer') && js.includes('halbtags'));

// ---- Backend ----
pruefe('slot_haelfte_fenster vorhanden',
  slots.includes('function slot_haelfte_fenster'));
pruefe('PATCH akzeptiert haelfte erste/zweite/ganz',
  idx.includes("['erste', 'zweite', 'ganz']") && idx.includes('slot_haelfte_fenster'));
pruefe('Lehrkraft darf eigenen Eintrag ändern',
  idx.includes('$eigene') && idx.includes("=== 'lehrkraft'"));
pruefe('Halbtags-Toggle-Route',
  idx.includes("=== 'lehrer'") && idx.includes('UPDATE lehrer SET halbtags'));
pruefe('Ausfall-Route gibt Termine frei und benachrichtigt',
  idx.includes("=== 'ausfall'") && idx.includes('DELETE FROM buchungen')
  && idx.includes('mit_text_absage'));
pruefe('Raster liefert halbtags-Flag',
  buch.includes("'halbtags' => $halbtags"));

// ---- SQL ----
pruefe('Schema idempotent (ADD COLUMN IF NOT EXISTS)',
  sql.includes('ADD COLUMN IF NOT EXISTS halbtags'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
