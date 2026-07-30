// ============================================================
// tests/frontend_marke_test.js
// Statische Prüfung der Individualisierung (v0.9.8):
//   - Frontend lädt und wendet die Marke an (Farben, Titel, Logo)
//   - Admin-Formular speichert, lädt Logo hoch, setzt zurück
//   - Backend-API bietet die erwarteten Routen und Validierungen
//
// Aufruf: node tests/frontend_marke_test.js
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
const php = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'einstellungen.php'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8');
const html = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'index.html'), 'utf8');

// ---- Frontend ----
pruefe('Marke wird beim Start geladen',
  js.includes("api('/api/einstellungen')") && js.includes('wendeMarkeAn'));
pruefe('Akzentfarben werden auf documentElement gesetzt',
  js.includes("setProperty('--akzent'") && js.includes("setProperty('--akzent2'"));
pruefe('Logo wird per Cache-Busting geladen',
  js.includes("'/api/einstellungen/logo?'"));
pruefe('Speichern schickt alle Marke-Felder',
  js.includes('marke_schulname') && js.includes('marke_farbe') && js.includes('marke_fusszeile'));
pruefe('Logo-Upload liest Datei als Base64',
  js.includes('dateiAlsBase64') && js.includes('readAsDataURL'));
pruefe('Zurücksetzen vorhanden',
  js.includes('/api/einstellungen/zuruecksetzen'));

// ---- CSS ----
pruefe('Akzentvariablen definiert und in der Oberfläche genutzt',
  css.includes('--akzent:') && css.includes('var(--akzent)'));

// ---- HTML ----
pruefe('Kopf hat Logo-Slot und benannte Marke-Elemente',
  html.includes('id="marke-logo"') && html.includes('id="marke-titel"')
  && html.includes('id="marke-fusszeile"'));

// ---- Backend ----
pruefe('Öffentliches GET liefert Marke ohne Logo-Pfad',
  php.includes("schluessel NOT IN ('marke_logo_pfad')"));
pruefe('Farb-Validierung #RRGGBB',
  php.includes('marke_ist_farbe') && php.includes('#[0-9A-Fa-f]{6}'));
pruefe('SVG-Sicherheitsprüfung',
  php.includes('marke_svg_sicher') && php.includes('<script'));
pruefe('MIME per finfo statt Client-Angabe',
  php.includes('finfo(FILEINFO_MIME_TYPE)'));
pruefe('Logo-Größenlimit 500 KB',
  php.includes('500 * 1024'));
pruefe('Admin-Guard bei Schreib-Routen',
  php.includes('auth_require_admin'));
pruefe('MariaDB-Upsert statt SQLite-Syntax',
  php.includes('ON DUPLICATE KEY UPDATE') && !php.includes('INSERT OR REPLACE'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
