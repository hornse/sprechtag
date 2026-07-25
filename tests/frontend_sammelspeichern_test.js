// ============================================================
// tests/frontend_sammelspeichern_test.js
// Prüft v0.9.13: Toast statt Neuzeichnen (½-Häkchen bleibt stehen),
// „Alle teilnehmen" + „Alle speichern", Bulk-Endpunkt.
//
// Aufruf: node tests/frontend_sammelspeichern_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const js   = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const html = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'index.html'), 'utf8');
const idx  = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'index.php'), 'utf8');

// ---- Toast statt Neuzeichnen ----
pruefe('Toast-Element im HTML', html.includes('id="toast"'));
pruefe('toast()-Funktion vorhanden', js.includes('function toast('));
pruefe('½-Häkchen nutzt toast, nicht meldung',
  js.includes("toast((cbH.checked ? 'Als Halbtagskraft"));
pruefe('Speichern-Icon nutzt toast',
  js.includes("toast('Gespeichert: ' + l.kuerzel"));

// ---- Sammelaktionen ----
pruefe('„Alle teilnehmen"-Knopf', js.includes("knopf('Alle teilnehmen'"));
pruefe('„Alle speichern"-Knopf', js.includes("knopf('Alle speichern'"));
pruefe('alleLehrerSpeichern sammelt Zeilen',
  js.includes('function alleLehrerSpeichern') && js.includes("method: 'PUT'"));
pruefe('respektiert Halbtags (haelfte vs von/bis)',
  js.includes('z.haelfte =') && js.includes('z.anwesend_von ='));

// ---- Backend Bulk-Route ----
pruefe('PUT-Route für alle Zeilen',
  idx.includes("$methode === 'PUT'") && idx.includes("body['zeilen']"));
pruefe('Bulk in einer Transaktion',
  idx.includes('beginTransaction') && idx.includes('rollBack'));
pruefe('Admin-Guard bei Bulk', idx.includes('auth_require_admin'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
