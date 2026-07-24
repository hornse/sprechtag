// ============================================================
// tests/frontend_lehrkraft_raster_test.js
// Statische Prüfung der neuen Lehrkraft-Rasteransicht (v0.9.3).
// Kein DOM nötig – prüft, dass die entscheidenden Verdrahtungen
// in zeichneLehrkraftRaster()/stellvertretendBuchen() vorhanden sind:
//   - belegte Slots zeigen den Kindnamen
//   - freie Slots lösen die stellvertretende Buchung aus
//   - vor der Buchung wird ein Kind verlangt
//   - die alte Tabellen-/Formularlogik ist entfernt
//
// Grund: Der Laufzeittest lädt app.js nur, fährt die neue Ansicht aber
// nicht an. Dieser Test sichert die Verkabelung gegen versehentliches
// Wegbrechen (wie damals bei ansichtMitteilungen).
//
// Aufruf: node tests/frontend_lehrkraft_raster_test.js
// ============================================================
'use strict';

const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(
  path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

// Funktionskörper grob ausschneiden (bis zur nächsten Top-Level-Funktion).
function koerper(name) {
  const start = src.indexOf('function ' + name);
  if (start < 0) return '';
  const rest = src.slice(start + 8);
  const m = rest.match(/\nasync function |\nfunction /);
  return m ? rest.slice(0, m.index) : rest;
}

const raster = koerper('zeichneLehrkraftRaster');
const buchen = koerper('stellvertretendBuchen');
const treffer = koerper('zeichneSvTreffer');

pruefe('zeichneLehrkraftRaster existiert', raster !== '');
pruefe('stellvertretendBuchen existiert', buchen !== '');
pruefe('zeichneSvTreffer existiert', treffer !== '');

pruefe('belegte Slots zeigen den Kindnamen',
  raster.includes('kind_name'));
pruefe('belegte Slots bieten Absage',
  raster.includes('lehrkraftStorno'));
pruefe('freie Slots lösen stellvertretende Buchung aus',
  raster.includes('stellvertretendBuchen'));
pruefe('Kind-Auswahl per Suchfeld statt Dropdown',
  raster.includes('sv-suche') && raster.includes('zeichneSvTreffer'));
pruefe('Suche filtert über Name und Klasse',
  treffer.includes('.name') && treffer.includes('.klasse'));
pruefe('Trefferliste ist begrenzt (keine endlose Liste)',
  treffer.includes('grenze') && treffer.includes('slice'));
pruefe('Buchung nutzt den richtigen Endpunkt',
  buchen.includes('/api/buchungen/stellvertretend'));
pruefe('vor der Buchung wird ein Kind verlangt',
  buchen.includes('S.svKind'));
pruefe('Doppelklick-Schutz vorhanden',
  buchen.includes('S.svLaeuft'));

// Die alte Darstellung darf nicht mehr existieren (sonst zwei Wege).
pruefe('alte Funktion zeichneStellvertreter entfernt',
  !src.includes('function zeichneStellvertreter'));
pruefe('alte Funktion ladeLehrkraftBuchungen entfernt',
  !src.includes('function ladeLehrkraftBuchungen'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
