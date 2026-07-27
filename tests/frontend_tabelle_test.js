// ============================================================
// tests/frontend_tabelle_test.js
// Prüft v0.9.15: optionales Zeitfenster, Sortierung, Konfliktfarben,
// „Alle speichern" oben und unten, Ausrichtung.
//
// Aufruf: node tests/frontend_tabelle_test.js
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

// ---- Optionales Zeitfenster ----
pruefe('Standard ganzer Tag, „nur zeitweise" blendet Felder ein',
  js.includes('zeitfenster-opt') && js.includes("'zf-' + s.id"));
pruefe('lehrerZeileDaten respektiert Zeitfenster-Häkchen',
  js.includes('function lehrerZeileDaten') && js.includes("$('#zf-' + s.id"));
pruefe('leeres Fenster = ganzer Tag',
  js.includes("z.anwesend_von = '';") && js.includes("z.anwesend_bis = '';"));

// ---- Sortierung ----
pruefe('Sortierstate + sortierbare Spalten',
  js.includes('S.lehrerSort') && js.includes("['kuerzel', 'Kürzel']"));
pruefe('Klick auf Kopf dreht/ändert Sortierung',
  js.includes('S.lehrerSort.richtung *= -1'));
pruefe('sortierbare Kopfzeile im CSS', css.includes('th.sortierbar'));

// ---- Konfliktfarben ----
pruefe('Palette pro Konfliktraum', js.includes('raumFarbe') && js.includes("'kf-a'"));
pruefe('Farbklassen im CSS', css.includes('.kf-a') && css.includes('.kf-h'));
pruefe('Warnung + Select bekommen dieselbe Farbklasse',
  js.includes("sel.classList.add('konflikt', kf)") && js.includes("'raum-warnung ' + kf"));

// ---- Alle speichern oben + unten ----
pruefe('zwei „Alle speichern"-Knöpfe (oben + unten)',
  (js.match(/knopf\('Alle speichern'/g) || []).length >= 2);

// ---- Ausrichtung ----
pruefe('Zellen oben ausgerichtet (½-Zeile springt nicht)',
  css.includes('vertical-align: top'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
