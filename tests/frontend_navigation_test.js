// ============================================================
// tests/frontend_navigation_test.js
// Prüft v0.9.29: Logo-Caching, Auto-Load „Meine Termine", Ansicht im URL-Hash.
//
// Aufruf: node tests/frontend_navigation_test.js
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
const est = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'einstellungen.php'), 'utf8');

// ---- Logo-Caching ----
pruefe('Logo: langer Cache + immutable',
  est.includes('max-age=86400') && est.includes('immutable'));
pruefe('Logo: ETag/Last-Modified für 304',
  est.includes('ETag:') && est.includes('HTTP_IF_NONE_MATCH') && est.includes('304'));
pruefe('Logo-URL stabil über Version (kein Date.now im Header-Logo)',
  js.includes("'/api/einstellungen/logo?v=' + (m.logo_version")
  && est.includes("logo_version"));

// ---- Auto-Load Meine Termine ----
pruefe('Meine Termine lädt automatisch',
  js.includes('meineLaedt') && js.includes('Termine werden geladen'));
pruefe('kein Lade-Button mehr',
  !js.includes("knopf('Termine laden'"));

// ---- Hash-Routing ----
pruefe('wechsleAnsicht setzt Hash', js.includes('setzeHash(ziel)'));
pruefe('Ansicht wird aus Hash wiederhergestellt',
  js.includes("(location.hash || '').replace(/^#/, '')") && js.includes('ANSICHT_KEYS.includes'));
pruefe('hashchange-Listener (Vor/Zurück)', js.includes("addEventListener('hashchange'"));
pruefe('Rollenprüfung für Ansichten', js.includes('function ansichtErlaubt'));
pruefe('Abmelden leert den Hash', js.includes("location.hash = ''"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
