// ============================================================
// tests/frontend_adminfeinschliff_test.js
// Prüft v0.9.30: Sonderlehrer ohne Seiten-Neuaufbau, Datums-Kalenderfelder,
// ausgerichtete CSV-Buttons.
//
// Aufruf: node tests/frontend_adminfeinschliff_test.js
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

// ---- Sonderlehrer: toast statt meldung (kein Seiten-Neuaufbau) ----
const slBlock = js.slice(js.indexOf('async function oeffneSonderlehrer'),
                         js.indexOf('function ansichtSondierung'));
pruefe('Sonderlehrer Hinzufügen nutzt toast',
  slBlock.includes("toast('Hinzugefügt.', 'ok')"));
pruefe('Sonderlehrer Entfernen nutzt toast',
  slBlock.includes("toast('Entfernt.', 'ok')"));
pruefe('Sonderlehrer ruft kein meldung() mehr',
  !slBlock.includes("meldung('Hinzugefügt") && !slBlock.includes("meldung(String(f.message"));
pruefe('flackerfrei: Liste vor Aufbau laden, in einem Zug tauschen',
  slBlock.includes('replaceChildren') && !slBlock.includes("ziel.textContent = ''"));

// ---- Datums-Kalenderfelder ----
pruefe('Anlege-Datum ist Kalenderfeld',
  js.includes("feld('Datum', 'neu-datum', 'date')"));
pruefe('Bearbeiten-Datum ist Kalenderfeld',
  js.includes("feld('Datum', 'e-datum-' + s.id, 'date'"));
pruefe('Referenz-Felder als Kalender',
  js.includes("'e-refvon-' + s.id, 'date'") && js.includes("'e-refbis-' + s.id, 'date'"));
pruefe('Sondierung-Zeitraum als Kalender',
  js.includes("'so-von', 'date'") && js.includes("'so-bis', 'date'"));
pruefe('CSS stylt Datumsfelder', css.includes('input[type=date]'));

// ---- CSV-Buttons ausgerichtet ----
pruefe('CSV-Buttons in .aktionen-Zeile',
  js.includes('slAktionen') && js.includes("el('div', 'aktionen')"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
