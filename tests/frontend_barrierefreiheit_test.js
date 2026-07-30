// ============================================================
// tests/frontend_barrierefreiheit_test.js
// Prüft v0.9.40: Fokus-Rahmen, Icon-aria-hidden, Skip-Link, aria-live.
//
// Aufruf: node tests/frontend_barrierefreiheit_test.js
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

// ---- Fokus-Rahmen ----
pruefe('Sichtbarer Fokus-Rahmen (:focus-visible)', css.includes(':focus-visible'));
pruefe('Fokus nutzt Akzentfarbe', /:focus[^{]*\{[^}]*outline[^}]*var\(--akzent\)/s.test(css));

// ---- Skip-Link ----
pruefe('Skip-Link im HTML', html.includes('class="skip-link"') && html.includes('#hauptinhalt'));
pruefe('Skip-Ziel auf main', html.includes('<main id="hauptinhalt"'));
pruefe('Skip-Link-CSS (bei Fokus sichtbar)', css.includes('.skip-link:focus'));

// ---- Icons für Screenreader ausgeblendet ----
pruefe('Nav-Icon aria-hidden', js.includes("ic.setAttribute('aria-hidden', 'true')"));
pruefe('Nav-Knopf hat echten Namen (aria-label)',
  js.includes("b.setAttribute('aria-label', text)"));
pruefe('aktive Ansicht als aria-current', js.includes("aria-current"));

// ---- Live-Regionen ----
pruefe('Toast ist Live-Region', html.includes('aria-live') && html.includes('role="status"'));
pruefe('Fehler-Toast assertive', js.includes("art === 'fehler' ? 'assertive' : 'polite'"));
pruefe('meldung() als role=alert', js.includes("m.setAttribute('role', 'alert')"));

// ---- Kleinere Verbesserungen ----
pruefe('Logo mit beschreibendem Alt-Text', js.includes("logo.alt = 'Logo '"));
pruefe('Hamburger aria-expanded gepflegt',
  js.includes("'#mobil-menue')?.setAttribute('aria-expanded'"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
