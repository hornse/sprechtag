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
const kopf = koerper('zeichneStellvertreterKopf');
// v0.9.81 (E20 A, R3): Quelle /api/kinder, gesucht über Knopf und Eingabetaste.
pruefe('Kind-Auswahl per Suchfeld statt Dropdown',
  kopf.includes('sv-suche') && kopf.includes('svKindSuchen('));
const suche = koerper('svKindSuchen');
pruefe('Suche fragt das Backend ab: svKindSuchen über kinderSuchen → /api/kinder',
  suche.includes('await kinderSuchen(q)') && koerper('kinderSuchen').includes("'/api/kinder?suche='"));
pruefe('kein Abruf je Tastendruck (R3; die alte Fassung war entprellt)',
  kopf !== '' && !src.includes('svSucheTimer') && !src.includes('svSucheAnstossen')
  && !/addEventListener\('input'[\s\S]*?svKindSuchen/.test(kopf.split("addEventListener('submit'")[0]));
pruefe('Stellvertretend buchen nur im eigenen Raster',
  raster.includes('eigenesRaster') && raster.includes('S.user.lehrer_id'));
pruefe('Trefferliste ist begrenzt: Grenze auf dem Server (60), die Anzeige nennt den Rest',
  treffer.includes('kinderTrefferHinweis(S.svTreffer)'));
pruefe('Buchung nutzt den richtigen Endpunkt',
  buchen.includes('/api/buchungen/stellvertretend'));
pruefe('vor der Buchung wird ein Kind verlangt',
  buchen.includes('S.svKind'));
pruefe('Doppelklick-Schutz vorhanden',
  buchen.includes('S.svLaeuft'));

// Auto-Load: kein manueller „Termine laden"-Knopf mehr, dafür ein Guard
// gegen Mehrfachladen und ein Fehlerzustand gegen Endlosschleifen.
const ansicht = koerper('ansichtLehrkraft');
pruefe('kein manueller „Termine laden"-Knopf mehr',
  !ansicht.includes("knopf('Termine laden'"));
pruefe('Auto-Load mit Guard (svLaedt)',
  ansicht.includes('S.svLaedt') && ansicht.includes('ladeSvRaster'));
pruefe('Fehlerzustand bricht Auto-Load-Schleife',
  ansicht.includes('S.svFehler'));
const lade = koerper('ladeSvRaster');
pruefe('Guard wird nach dem Laden immer zurückgesetzt',
  lade.includes('finally') && lade.includes('S.svLaedt = false'));

// Die alte Darstellung darf nicht mehr existieren (sonst zwei Wege).
pruefe('alte Funktion zeichneStellvertreter entfernt',
  !src.includes('function zeichneStellvertreter('));
pruefe('alte Funktion ladeLehrkraftBuchungen entfernt',
  !src.includes('function ladeLehrkraftBuchungen'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
