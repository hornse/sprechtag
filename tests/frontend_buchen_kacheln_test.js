// ============================================================
// tests/frontend_buchen_kacheln_test.js
// Prüft v0.9.20: Buchungs-Einstieg als durchsuchbares Kachelraster.
//
// Aufruf: node tests/frontend_buchen_kacheln_test.js
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

pruefe('Kachel-Renderer vorhanden', js.includes('function zeichneBuchenKacheln'));
pruefe('Kacheln zeigen Raum + Name + Zeit',
  js.includes('bk-raum') && js.includes('bk-name') && js.includes('bk-zeit'));
pruefe('Suchfeld filtert clientseitig',
  js.includes('buchen-suche') && js.includes('S.buchenSuche'));
pruefe('Filter über Name, Kürzel, Fach, Raum',
  js.includes('l.faecher') && js.includes('l.raum_kuerzel') && js.includes('heu.includes'));
pruefe('Klick auf Kachel startet Buchung (ladeRaster)',
  js.includes('ladeRaster(l.lehrer_id)'));
pruefe('Lehrkräfte werden automatisch geladen',
  js.includes('S.lehrerLaedt') && js.includes('ladeLehrerListe()'));
pruefe('Auto-Load hat Fehler-Fallback (keine Endlosschleife)',
  js.includes('S.lehrerListe = { unterrichtend: [], sonderlehrer: [] }'));
pruefe('Kachel-CSS vorhanden', css.includes('.buchen-gitter') && css.includes('.buchen-kachel'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
