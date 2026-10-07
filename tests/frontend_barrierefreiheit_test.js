// ============================================================
// tests/frontend_barrierefreiheit_test.js
// Prüft v0.9.40: Fokus-Rahmen, Icon-aria-hidden, Skip-Link, aria-live.
//
// Umgestellt mit v0.9.51: Gesucht wird nicht mehr in der ganzen Datei,
// sondern im Rumpf der Funktion bzw. am Element, wo die Sache wirken
// soll – Kommentare vorher entfernt. Anlass: Nach dem CI-Umbau
// (August 2026) hing die Icon-Prüfung am alten Wortlaut und wurde rot,
// obwohl symbol() das Attribut setzt; umgekehrt wäre die Toast-Prüfung
// grün geblieben, wenn nur der Kommentar über #toast „aria-live" nennt.
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
const css  = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8')
  .replace(/\/\*[\s\S]*?\*\//g, '');
const html = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'index.html'), 'utf8')
  .replace(/<!--[\s\S]*?-->/g, '');

// Rumpf einer Funktion ohne Kommentare – gemeinsame Hilfe, siehe rumpf.js.
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

// Inhalt einer CSS-Regel mit genau diesem Selektor (Kommentare entfernt).
function regel(selektor) {
  const esc = selektor.replace(/[.*+?^${}()|[\]\\:]/g, '\\$&');
  const m = css.match(new RegExp('(?:^|[\\n}])\\s*' + esc + '\\s*\\{([^}]*)\\}'));
  return m ? m[1] : '';
}

// Öffnendes Tag des Elements mit diesem Merkmal (Kommentare entfernt).
function tag(merkmal) {
  const m = html.match(new RegExp('<[a-z]+\\b[^>]*' + merkmal + '[^>]*>'));
  return m ? m[0] : '';
}

// ---- Fokus-Rahmen ----
const fokus = regel(':focus-visible');
pruefe('Sichtbarer Fokus-Rahmen (:focus-visible)', fokus !== '');
pruefe('Fokus nutzt Akzentfarbe', /outline\s*:[^;]*var\(--akzent\)/.test(fokus));

// ---- Skip-Link ----
pruefe('Skip-Link im HTML', tag('class="skip-link"').includes('href="#hauptinhalt"'));
pruefe('Skip-Ziel auf main', tag('id="hauptinhalt"').startsWith('<main'));
pruefe('Skip-Link-CSS (bei Fokus sichtbar)', /top\s*:\s*0/.test(regel('.skip-link:focus')));

// ---- Icons für Screenreader ausgeblendet ----
const symbolRumpf = rumpf('function symbol(');
const navRumpf    = rumpf('const navKnopf = (');
pruefe('Nav-Icon aria-hidden (in symbol())',
  symbolRumpf.includes("setAttribute('aria-hidden', 'true')"));
pruefe('navKnopf ruft symbol() auf', /appendChild\(\s*symbol\(/.test(navRumpf));
pruefe('Nav-Knopf hat echten Namen (aria-label)',
  navRumpf.includes("setAttribute('aria-label', text)"));
pruefe('aktive Ansicht als aria-current',
  navRumpf.includes("setAttribute('aria-current', 'page')"));

// ---- Live-Regionen ----
const toastTag = tag('id="toast"');
pruefe('Toast ist Live-Region', toastTag.includes('aria-live=') && toastTag.includes('role="status"'));
pruefe('Fehler-Toast assertive',
  rumpf('function toast(').includes("art === 'fehler' ? 'assertive' : 'polite'"));
pruefe('meldung() als role=alert',
  rumpf('function zeichne(').includes("setAttribute('role', 'alert')"));

// ---- Kleinere Verbesserungen ----
// Entschieden in v0.9.52 (docs/ENTSCHEIDUNGEN.md, E6): Das Logo im Kopf
// ist dekorativ – der Schulname steht direkt daneben als Text, sonst läse
// ein Screenreader ihn zweimal. Geprüft wird beides: das alt="" im HTML
// und dass wendeMarkeAn() es nicht zur Laufzeit überschreibt (so war es
// von v0.9.40 bis v0.9.51). Ein leerer Rumpf zählt nicht als bestanden.
pruefe('Logo im Kopf ist im HTML dekorativ (alt="")',
  tag('id="marke-logo"').includes('alt=""'));
const markeRumpf = rumpf('function wendeMarkeAn(');
pruefe('wendeMarkeAn() vergibt dem Logo keinen Alt-Text',
  markeRumpf !== ''
  && !/\.alt\s*=\s*(?!''|"")/.test(markeRumpf)
  && !/setAttribute\(\s*['"`]alt['"`]/.test(markeRumpf));
pruefe('Hamburger aria-expanded gepflegt',
  rumpf('function menueOeffnen(').includes("setAttribute('aria-expanded', 'true')")
  && rumpf('function menueSchliessen(').includes("setAttribute('aria-expanded', 'false')"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
