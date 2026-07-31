// ============================================================
// tests/frontend_erinnerungen_test.js
// Prüft „Erinnerungen vor dem Sprechtag" (Weg B: Admin löst aus).
//
// Aufruf: node tests/frontend_erinnerungen_test.js
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
const eri = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'erinnerungen.php'), 'utf8');
const rst = fs.readFileSync(path.join(__dirname, '..', 'backend', 'auth', 'WebUntisRest.php'), 'utf8');
const mig = fs.readFileSync(path.join(__dirname, '..', 'sql', '19_erinnerungen.sql'), 'utf8');

// ---- REST-Client: Liste auflösen ----
pruefe('listeAufloesen vorhanden', rst.includes('function listeAufloesen'));
pruefe('nutzt typ + referenceId (DYNAMIC/QUICK)',
  rst.includes("'type'  => $typ") && rst.includes("'referenceId' => $referenceId"));
pruefe('paginiert defensiv', rst.includes('maxSeiten') && rst.includes('$gesehen'));

// ---- Backend-Logik ----
pruefe('Empfaenger-Aufloesung', eri.includes('function erinnerung_empfaenger_ermitteln'));
pruefe('Versand blockweise', eri.includes('function erinnerung_versenden')
  && eri.includes('array_chunk'));
pruefe('nutzt Dienstkonto', eri.includes('dk_lesen'));
pruefe('Standardtext/Betreff', eri.includes('function erinnerung_standard_text')
  && eri.includes('function erinnerung_standard_betreff'));
pruefe('Platzhalter im Text', eri.includes('platzhalter_ersetzen'));
pruefe('recipientUserIds-Array', eri.includes("'recipientUserIds'    => array_values($block)"));

// ---- Routen ----
pruefe('erinnerungen-Route (admin)', idx.includes("=== 'erinnerungen'")
  && idx.includes('auth_require_admin'));
pruefe('Vorschau + Senden getrennt',
  idx.includes("=== 'vorschau'") && idx.includes("=== 'senden'"));
pruefe('früh geladen', idx.includes("require_once __DIR__ . '/erinnerungen.php'"));

// ---- Migration ----
pruefe('Migration vorhanden',
  mig.includes("'erinnerung_liste_typ'") && mig.includes("'erinnerung_liste_id'"));

// ---- Frontend ----
pruefe('Admin-Ansicht', js.includes('function ansichtAdminErinnerungen'));
pruefe('Nav-Punkt', js.includes("navKnopf('admin-erinnerungen'"));
pruefe('Empfaenger pruefen vor Versand',
  js.includes("api('/api/erinnerungen/vorschau')") && js.includes('Empfänger prüfen'));
pruefe('Versand mit Bestaetigung', js.includes("api('/api/erinnerungen/senden'")
  && js.includes('kann nicht rückgängig'));
pruefe('Datenschutz-/Warnhinweis vor Versand', js.includes('Vor dem Senden'));

console.log(fehler === 0 ? '\nALLE TESTS GRUEN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
