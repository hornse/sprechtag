// ============================================================
// tests/frontend_anzeige_test.js
// Prüft v0.9.18: öffentlicher Anzeige-Modus (Signage).
//   - /anzeige rendert ohne Login/Sidebar, mit Auto-Refresh
//   - Backend-Endpunkt liefert nur neutrale Raumzuordnung (datensparsam)
//
// Aufruf: node tests/frontend_anzeige_test.js
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
const idx = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'index.php'), 'utf8');

// ---- Frontend ----
pruefe('Pfad /anzeige startet den Anzeige-Modus',
  js.includes("=== '/anzeige'") && js.includes('starteAnzeige()'));
pruefe('Anzeige-Funktion + Auto-Refresh',
  js.includes('function starteAnzeige') && js.includes('setInterval'));
pruefe('seitenweises Umblättern (Intervall konfigurierbar)',
  js.includes('weiterblaettern') && js.includes('planeBlaettern')
  && js.includes('sek * 1000'));
pruefe('Kachelmenge automatisch messbar',
  js.includes('messeProSeite') && js.includes('gridTemplateColumns'));
pruefe('Logo im Anzeige-Kopf', js.includes('anzeige-logo') && js.includes('hat_logo'));
pruefe('kein Flackern: Kopf bleibt, nur Gitter tauscht',
  js.includes('anzeige-gitter-box') && js.includes('gitterBox.textContent')
  && js.includes("logo.src = '/api/einstellungen/logo'"));
pruefe('Seitenanzeige (Seite X / Y)', js.includes('anzeige-seite'));
pruefe('Admin-Unterseite Anzeige + Sortier-Wahl',
  js.includes('function ansichtAdminAnzeige') && js.includes('anzeige-sortierung'));
pruefe('Backend: Anzeige-Sortierung konfigurierbar',
  idx.includes('anzeige_sortierung') && idx.includes("=== 'anzeige-einstellungen'"));
pruefe('Kacheln zeigen Raum, Kürzel, Name, Zeit',
  js.includes('anzeige-raum') && js.includes('anzeige-kuerzel')
  && js.includes('anzeige-name') && js.includes('anzeige-zeit'));
pruefe('Anwesenheitszeit in Klartext (ganztägig/ab X)',
  js.includes('function anzeigeZeit') && js.includes('ganztägig'));
pruefe('Anzeige-CSS (Vollbild-Gitter)',
  css.includes('.anzeige-gitter') && css.includes('anzeige-modus'));

// ---- Backend: öffentlich + datensparsam ----
pruefe('öffentlicher Endpunkt /api/anzeige (vor Auth)',
  idx.includes("=== 'anzeige'") && idx.indexOf("=== 'anzeige'") < idx.indexOf('auth_require'));
pruefe('nur teilnehmende Lehrkräfte',
  idx.includes('sl.teilnahme = 1'));
pruefe('KEINE persönlichen/Buchungsdaten (keine eltern/buchungen im Endpunkt)',
  !/anzeige[\s\S]{0,900}eltern_user_id/.test(idx)
  && !/anzeige[\s\S]{0,900}FROM buchungen/.test(idx));
pruefe('nur aktiver Sprechtag (Phase 1/2)',
  /anzeige[\s\S]{0,400}phase1.*phase2/.test(idx));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
