// ============================================================
// tests/frontend_sidebar_test.js
// Statische Prüfung des Sidebar-Umbaus (v0.9.10):
//   - HTML hat Shell, Seitenleiste, Hamburger, Overlay
//   - Navigation baut Haupt- und Admin-Untereinträge
//   - Admin ist in vier Unterseiten gesplittet und registriert
//   - Mobiles Menü öffnet/schließt
//
// Aufruf: node tests/frontend_sidebar_test.js
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
const css  = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8');

// ---- HTML-Gerüst ----
pruefe('Shell mit Seitenleiste', html.includes('class="shell"')
  && html.includes('id="seitenleiste"'));
pruefe('Hamburger + Overlay vorhanden', html.includes('id="mobil-menue"')
  && html.includes('id="menue-overlay"'));
pruefe('Marke in der Seitenleiste', html.includes('id="marke-logo"')
  && html.includes('id="marke-titel"'));
pruefe('kein alter Header mehr', !html.includes('class="kopf"'));

// ---- Navigation ----
pruefe('Navigation baut nv-Knöpfe', js.includes("el('button', 'nv'"));
pruefe('Admin als aufklappbare Gruppe', js.includes('nv-group-toggle')
  && js.includes('S.adminOffen'));
pruefe('vier Admin-Unterpunkte inkl. Aktiver Sprechtag', js.includes("'admin-aktiv'")
  && js.includes("'admin-marke'") && js.includes("'admin-sprechtage'")
  && js.includes("'admin-daten'") && !js.includes("navKnopf('admin-lehrer'"));
pruefe('Abmelden im Menü', js.includes('nv-abmelden') && js.includes('abmelden()'));
pruefe('aktive Admin-Unterseite klappt Gruppe auf',
  js.includes('adminAktiv') && js.includes('S.adminOffen = true'));

// ---- Admin-Unterseiten als Funktionen + in der Map ----
for (const fn of ['ansichtAdminMarke', 'ansichtAdminAktiv', 'ansichtAdminSprechtage',
                  'ansichtAdminDaten']) {
  pruefe('Funktion ' + fn + ' definiert', js.includes('function ' + fn + '('));
}
pruefe('aktiver Sprechtag = Phase 1/2, nächstes Datum',
  js.includes('function aktiverSprechtagFinden')
  && js.includes("s.phase === 'phase1'"));
pruefe('Unterseiten in der Ansichten-Map', js.includes("'admin-sprechtage': ansichtAdminSprechtage")
  && js.includes("'admin-daten': ansichtAdminDaten"));

// ---- Mobiles Menü ----
pruefe('menueOeffnen/menueSchliessen vorhanden',
  js.includes('function menueOeffnen') && js.includes('function menueSchliessen'));
pruefe('Menü schließt nach Ansichtswechsel', js.includes('menueSchliessen();'));

// ---- CSS ----
pruefe('Sidebar-CSS vorhanden', css.includes('.seitenleiste')
  && css.includes('.nv') && css.includes('.nv-sub'));
pruefe('Mobile Media-Query für Overlay-Sidebar',
  css.includes('@media (max-width: 760px)') && css.includes('.seitenleiste.offen'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
