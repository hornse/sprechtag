// ============================================================
// tests/frontend_kommentar_test.js
// Prüft v0.9.28: optionaler Terminwunsch-Kommentar der Eltern.
//
// Aufruf: node tests/frontend_kommentar_test.js
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
const bu  = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'buchungen.php'), 'utf8');
const kal = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'kalender.php'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8');

// ---- Frontend: Eingabe + Versand ----
pruefe('Kommentarfeld beim Buchen',
  js.includes("'buchung-kommentar'") && js.includes('kommentar-feld'));
pruefe('buchen() sendet Kommentar',
  js.includes('buchen(lehrerId, z.beginn, wert') && js.includes('kommentar:'));
pruefe('Kommentar im Lehrkraft-Raster sichtbar',
  js.includes('slot-kommentar') && js.includes('z.kommentar'));

// ---- Backend: lesen, speichern, ausgeben ----
pruefe('Kommentar gelesen (mit kuerze-Helfer, kein rohes mb_substr)',
  bu.includes("kuerze(trim((string)($body['kommentar']") && !bu.includes('mb_substr(trim'));
pruefe('Kommentar in beiden INSERTs',
  (bu.match(/kommentar, phase, gebucht_von/g) || []).length === 2);
pruefe('Kommentar nur an Lehrkraft ausgegeben',
  bu.includes("$eintrag['kommentar']") && bu.includes('if ($istLehrkraft)'));
pruefe('Raster-Query lädt kommentar', bu.includes('b.gebucht_von, b.kommentar'));

// ---- Tischvorlage ----
pruefe('Tischvorlage zeigt Kommentar',
  kal.includes("class=\"komm\"") && kal.includes("$z['kommentar']"));

// ---- CSS ----
pruefe('CSS für Kommentar vorhanden',
  css.includes('.slot-kommentar') && css.includes('.kommentar-feld'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
