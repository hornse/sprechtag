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

// Rumpf einer Funktion ohne Kommentare. `kopf` muss genau einmal
// vorkommen; sonst kommt '' zurück und die Prüfung wird rot, statt auf
// einem leeren oder falschen Rumpf zu bestehen. Zeichenketten werden
// übersprungen, damit Klammern oder „//" darin nicht mitzählen.
function rumpf(kopf) {
  const anzahl = js.split(kopf).length - 1;
  if (anzahl !== 1) {
    console.log('    (Voraussetzung: „' + kopf + '" kommt ' + anzahl + '-mal vor, erwartet 1)');
    return '';
  }
  const start = js.indexOf('{', js.indexOf(kopf) + kopf.length);
  let tiefe = 0, aus = '', k = start;
  while (k < js.length) {
    const c = js[k], n = js[k + 1];
    if (c === '/' && n === '/') { k = js.indexOf('\n', k); if (k < 0) break; continue; }
    if (c === '/' && n === '*') { const e = js.indexOf('*/', k + 2); if (e < 0) break; k = e + 2; continue; }
    if (c === "'" || c === '"' || c === '`') {
      let e = k + 1;
      while (e < js.length && js[e] !== c) { if (js[e] === '\\') e++; e++; }
      aus += js.slice(k, e + 1); k = e + 1; continue;
    }
    if (c === '{') tiefe++;
    if (c === '}') { tiefe--; if (tiefe === 0) return aus.slice(1); }
    aus += c; k++;
  }
  return '';
}

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
// OFFEN (v0.9.51): Diese Aussage widerspricht tests-sprechtag.sh („Logo ist
// als dekorativ ausgezeichnet", alt="" im HTML). Im Browser gilt das JS.
// Hier nur örtlich enger gefasst, nicht entschieden; die Entscheidung
// steht aus und bekommt einen eigenen Eintrag in docs/ENTSCHEIDUNGEN.md.
pruefe('Logo mit beschreibendem Alt-Text',
  rumpf('function wendeMarkeAn(').includes("logo.alt = 'Logo '"));
pruefe('Hamburger aria-expanded gepflegt',
  rumpf('function menueOeffnen(').includes("setAttribute('aria-expanded', 'true')")
  && rumpf('function menueSchliessen(').includes("setAttribute('aria-expanded', 'false')"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
