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
const { rumpf } = require('./rumpf.js');

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
// Umgekehrt seit dem CI-Umbau (August 2026): Die Farben kommen aus
// ci-tokens.css, das Branding setzt keine mehr (siehe tests-sprechtag.sh).
// Bleibt als Prüfung stehen, damit ein versehentliches Zurückholen auffällt.
// Gesucht wird jede Schreibweise, auch mit doppelten Anführungszeichen.
pruefe('Branding setzt keine Akzentfarbe',
  !/setProperty\(\s*['"`]--akzent/.test(js));
pruefe('Logo wird per Cache-Busting geladen',
  js.includes("'/api/einstellungen/logo?'"));
pruefe('Speichern schickt alle Marke-Felder',
  js.includes('marke_schulname') && js.includes('marke_fusszeile') && js.includes('marke_kontakt'));
// Entschieden in v0.9.52 (docs/ENTSCHEIDUNGEN.md, E7): Die Farbfelder sind
// entfernt. Sie bewirkten seit dem CI-Umbau (August 2026) nichts – ein Feld,
// das nichts tut, verspricht etwas. Gesucht wird die falsche Fassung.
// Aneinandergehängte Zeichenkettenstücke ('a ' + 'b') werden verbunden,
// damit der Satz so gesucht wird, wie er angezeigt wird.
const markeBlock = rumpf(js, 'function zeichneMarkeBlock(')
  .replace(/'\s*\+\s*'/g, '');
// Auch kein angezeigter Text, der Farben im Erscheinungsbild verspricht –
// weder im Formular noch in der Hilfe (dort stand „Logo, Farben, Texte").
const jsVerbunden = js.replace(/'\s*\+\s*'/g, '');
pruefe('Admin-Formular bietet keine Farbfelder',
  !/f-marke-farbe|marke_farbe/.test(js)
  && markeBlock !== '' && !markeBlock.includes('Farben')
  && !/Erscheinungsbild[^'\n]*Farben/.test(jsVerbunden));
pruefe('Formular sagt, warum es keine Farbfelder gibt',
  markeBlock.includes('kennzeichnet die Anwendung'));
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
pruefe('Backend kennt keine Farbfelder mehr',
  !php.includes('marke_farbe') && !php.includes('marke_ist_farbe'));

// ---- Datenbank (v0.9.52, E7) ----
// Die Migration allein genügt nicht: 10_branding.sql legt die Werte per
// INSERT IGNORE an und brächte sie bei jedem erneuten Einspielen oder
// einer Neueinrichtung zurück. Gesucht wird die falsche Fassung – eine
// Wertzeile mit dem Schlüssel –, nicht die Erwähnung im Kopfkommentar.
const sqlOhneKommentar = (t) => t.replace(/--[^\n]*/g, '');
const seed = fs.readFileSync(path.join(__dirname, '..', 'sql', '10_branding.sql'), 'utf8');
pruefe('Branding-Seed legt keine Farbfelder an',
  seed.includes('INSERT IGNORE INTO einstellungen')
  && !/\(\s*'marke_farbe2?'/.test(sqlOhneKommentar(seed)));
const migPfad = path.join(__dirname, '..', 'sql', '20_farbfelder_entfernen.sql');
const mig = fs.existsSync(migPfad) ? sqlOhneKommentar(fs.readFileSync(migPfad, 'utf8')) : '';
pruefe('Migration entfernt beide Farbfelder',
  /DELETE\s+FROM\s+einstellungen\s+WHERE\s+schluessel\s+IN\s*\(\s*'marke_farbe'\s*,\s*'marke_farbe2'\s*\)/.test(mig));
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
