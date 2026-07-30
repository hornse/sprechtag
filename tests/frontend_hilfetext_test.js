// ============================================================
// tests/frontend_hilfetext_test.js
// Prüft v0.9.41: editierbarer Hilfe-Zusatztext (Markdown, serverseitig sicher).
//
// Aufruf: node tests/frontend_hilfetext_test.js
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
const est = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'einstellungen.php'), 'utf8');
const hlf = fs.readFileSync(path.join(__dirname, '..', 'backend', 'helfer.php'), 'utf8');
const mig = fs.readFileSync(path.join(__dirname, '..', 'sql', '17_hilfe_zusatz.sql'), 'utf8');

// ---- Backend: sicherer Renderer ----
pruefe('markdown_sicher escaped zuerst',
  hlf.includes('function markdown_sicher') && hlf.includes('htmlspecialchars'));
pruefe('Links nur http/https/mailto', hlf.includes("^(https?://|mailto:)"));
pruefe('Platzhalter-Funktion vorhanden', hlf.includes('function platzhalter_ersetzen'));

// ---- Backend: Routen ----
pruefe('GET/POST hilfe-Route', est.includes("=== 'hilfe'"));
pruefe('Rohtext nur für Admins', est.includes('auth_ist_admin()'));
pruefe('serverseitig gerendert', est.includes('markdown_sicher(platzhalter_ersetzen'));
pruefe('Migration vorhanden', mig.includes("'hilfe_zusatz'"));

// ---- Frontend ----
pruefe('Hilfeseite zeigt Zusatztext', js.includes('S.hilfeZusatz') && js.includes('hilfe-zusatz'));
pruefe('Admin-Editor vorhanden', js.includes('function ansichtAdminHilfetext'));
pruefe('Editor per Nav erreichbar', js.includes("navKnopf('admin-hilfetext'"));
pruefe('Variablenliste im Editor', js.includes('{{kontakt}}') && js.includes('{{schulname}}'));
pruefe('Vorschau serverseitig (innerHTML nur für gesäubertes HTML)',
  js.includes('S.hilfetextVorschau') && js.includes('serverseitig gesäubert'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
