// ============================================================
// tests/frontend_kontakt_test.js
// Prüft v0.9.32: schulspezifische Inhalte ausgelagert (Kontakt, Schulname).
//
// Aufruf: node tests/frontend_kontakt_test.js
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
const mit = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'mitteilungen.php'), 'utf8');
const brd = fs.readFileSync(path.join(__dirname, '..', 'sql', '10_branding.sql'), 'utf8');

// ---- Keine hartcodierten schulspezifischen Reste mehr ----
pruefe('Frontend: kein „Sekretariat" mehr', !js.includes('Sekretariat'));
pruefe('Frontend: keine feste Schul-Adresse', !js.includes('rueckert-gymnasium.de'));
pruefe('Backend-Signatur: kein fester Schulname', !mit.includes('Friedrich-Rückert'));
pruefe('Standardwerte neutral', !est.includes('Friedrich-Rückert') && est.includes("'Ihre Schule'"));
pruefe('Branding-Seed neutral', !brd.includes('Friedrich-Rückert'));

// ---- Kontakt konfigurierbar ----
pruefe('kontaktSatz-Helfer nutzt marke_kontakt',
  js.includes('function kontaktSatz') && js.includes('marke_kontakt'));
pruefe('neutraler Fallback ohne Kontakt', js.includes("' die Schule.'"));
pruefe('Admin-Formular hat Kontakt-Feld',
  js.includes("'f-marke-kontakt'") && js.includes('marke_kontakt:'));
pruefe('Speicher-Regel kennt marke_kontakt', est.includes("'marke_kontakt'"));
pruefe('marke_kontakt im Branding-Seed', brd.includes("'marke_kontakt'"));

// ---- Schulname als Parameter der Signatur ----
pruefe('Signatur nimmt Schulname-Parameter',
  mit.includes('string $schule') && mit.includes('. $schule'));
pruefe('Aufrufer übergibt marke_schulname',
  js === js && est.includes('function marke_schulname'));

// ---- Regression: Formularfelder dürfen nicht mit Kopf-IDs kollidieren ----
const html = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'index.html'), 'utf8');
const kopfIds = ['marke-titel', 'marke-untertitel', 'marke-fusszeile', 'marke-logo'];
const formFelder = ['f-marke-schulname', 'f-marke-titel', 'f-marke-untertitel',
  'f-marke-fusszeile', 'f-marke-kontakt', 'f-marke-farbe', 'f-marke-farbe2'];
pruefe('Kopf-Elemente behalten ihre IDs', kopfIds.every((id) => html.includes('id="' + id + '"')));
pruefe('Formularfelder nutzen eigenes f-Präfix (keine ID-Kollision)',
  formFelder.every((id) => js.includes("'" + id + "'")));
pruefe('wert() ist robust gegen Elemente ohne .value',
  js.includes("typeof e.value === 'string'"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
