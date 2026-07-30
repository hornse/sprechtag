// ============================================================
// tests/frontend_buchen_uebersicht_test.js
// Prüft v0.9.38: kompakte Terminübersicht auf der Buchungsseite +
// einklappbare Seitenleiste.
//
// Aufruf: node tests/frontend_buchen_uebersicht_test.js
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
const css  = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8');
const html = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'index.html'), 'utf8');

// ---- Kompaktübersicht auf der Buchungsseite ----
pruefe('Kompaktübersicht wird auf Buchungsseite gezeichnet',
  js.includes('zeichneTermineKompakt(ziel)') && js.includes('function zeichneTermineKompakt'));
pruefe('einklappbar über block() (startet eingeklappt)',
  js.includes("block('buchen-uebersicht'"));
pruefe('chronologisch sortiert',
  js.includes('localeCompare') && js.includes('termine-kompakt'));
pruefe('aktualisiert nach Buchung',
  js.includes('S.meineBuchungen = null; S.meineLaedt = false;') );
pruefe('Absage frischt auch das Raster auf',
  js.includes("S.ansicht === 'buchen' && S.gewaehlteLehrkraft"));

// ---- Regression: Eltern-Buchung berechnet slot_frei (sonst „bereits vergeben") ----
const bu = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'buchungen.php'), 'utf8');
pruefe('Eltern-Pfad berechnet $frei vor der Prüfung',
  bu.includes('$frei = (int)$stFrei->fetchColumn() === 0') && bu.includes("'slot_frei'      => $frei"));
pruefe('Link zur vollen Übersicht bleibt',
  js.includes("wechsleAnsicht('meine')"));
pruefe('CSS für Kompaktliste', css.includes('.termin-zeile') && css.includes('.termin-zeit'));

// ---- Einklappbare Seitenleiste ----
pruefe('Einklapp-/Ausklapp-Knöpfe im HTML',
  html.includes('id="leiste-einklappen"') && html.includes('id="leiste-ausklappen"'));
pruefe('Toggle-Logik im JS', js.includes('function leisteEinklappen'));
pruefe('Zustand gemerkt (localStorage)', js.includes("localStorage.setItem('leiste_zu'"));
pruefe('CSS zugeklappte Leiste', css.includes('.shell.leiste-zu .seitenleiste'));
pruefe('Auf Mobil deaktiviert', css.includes('.leiste-einklappen, .leiste-ausklappen { display: none'));

// ---- v0.9.39: Buchungs-Toast, Icon-Leiste, breiterer Inhalt ----
pruefe('Buchungsfehler als Toast', js.includes("toast(String(f.message), 'fehler')")
  && js.includes("toast('Termin um '"));
pruefe('Fehler-Toast länger sichtbar', js.includes("art === 'fehler' ? 6000"));
pruefe('Nav-Knöpfe mit Icon + Text', js.includes("'nv-icon'") && js.includes("'nv-text'"));
pruefe('Eingeklappt = schmale Icon-Leiste (nicht versteckt)',
  css.includes('.shell.leiste-zu .seitenleiste { width:') );
pruefe('Inhalt breiter für große Schirme', css.includes('max-width: 100rem'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
