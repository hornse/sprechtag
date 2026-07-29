// ============================================================
// tests/frontend_lehrer_export_test.js
// Prüft v0.9.26: Lehrkraft-Export (Tischvorlage HTML, iCal-Datei, Abo-Link).
//
// Aufruf: node tests/frontend_lehrer_export_test.js
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
const kal = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'kalender.php'), 'utf8');

// ---- Backend-Routen ----
pruefe('Abo-Link-Route für Lehrkräfte',
  idx.includes("=== 'lehrer-kalender'") && idx.includes('auth_require'));
pruefe('iCal-Datei der Tagesliste',
  idx.includes("=== 'lehrer-termine'") && idx.includes('kal_lehrer_buchungen'));
pruefe('druckbare Tischvorlage (HTML)',
  idx.includes("=== 'lehrer-tischvorlage'") && idx.includes('kal_tischvorlage_html'));
pruefe('nur Lehrkräfte (Guard auf lehrer_id)',
  idx.includes("Nur für Lehrkräfte"));

// ---- Kalender-Modul ----
pruefe('Lehrkraft-Events kindzentriert',
  kal.includes('function kal_vevents_lehrer') && kal.includes("'Sprechtag: ' . ($kind"));
pruefe('Tischvorlage enthält freie Slots + Pause',
  kal.includes('function kal_tischvorlage_html'));
pruefe('Token-Namespace trennt Lehrkraft von Eltern',
  kal.includes('1000000000') && kal.includes('function kal_lehrer_aus_token'));
pruefe('öffentlicher Feed erkennt Lehrkraft-Token',
  idx.includes('kal_lehrer_aus_token($pdo, $token)'));

// ---- Frontend ----
pruefe('Export-Bereich in der Lehrkraft-Ansicht',
  js.includes('lehrer-export') && js.includes('Druckbare Tischvorlage'));
pruefe('Export nur im eigenen Raster',
  js.includes('if (eigenesRaster) {') && js.includes('/api/lehrer-tischvorlage/'));
pruefe('iCal-Datei + Abo-Link verlinkt',
  js.includes("'/api/lehrer-termine/' + sid + '.ics'")
  && js.includes("'/api/lehrer-kalender'"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
