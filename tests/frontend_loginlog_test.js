// ============================================================
// tests/frontend_loginlog_test.js
// Prüft v0.9.37: optionales Login-Protokoll (Admin), aus per Default.
//
// Aufruf: node tests/frontend_loginlog_test.js
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
const idx = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'index.php'), 'utf8');
const bremse = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'anmeldebremse.php'), 'utf8');
const rumpf = require('./rumpf.js').rumpf;
const mig = fs.readFileSync(path.join(__dirname, '..', 'sql', '16_login_log.sql'), 'utf8');

// ---- Standard: aus ----
pruefe('Default aktiv=0 (aus per Werk)', mig.includes("('login_log_aktiv',   '0')"));
pruefe('Default erfolge=0 (nur Fehlschläge)', mig.includes("('login_log_erfolge', '0')"));
pruefe('Default 30 Tage', mig.includes("('login_log_tage',    '30')"));

// ---- Backend ----
// v0.9.93: Gesucht wird dort, wo die Sache steht. Bis v0.9.92 suchten
// beide Prüfungen in ganz index.php. Die erste wurde rot, als das Schreiben
// der Erfolge nach anmeldebremse.php zog. Die zweite blieb grün, obwohl sie
// nichts mehr bewachte: Ihr Text stand auch in der Einstellungsroute (Art 1,
// docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md).
const erfolg = rumpf(bremse, 'function anmeldung_erfolg_merken(');
// E37: Der Versuch wird zur gelungenen Anmeldung – mit Rolle nur bei
// aktiv+erfolge, sonst als Vermerk ohne Rolle (ausgeführt in run_bremse.php).
pruefe('Erfolge nur bei aktiv+erfolge geloggt',
  /\(\$logAktiv && \$logErfolge\) \? \$grund : BREMSE_ZURUECK/.test(erfolg));
pruefe('Bereinigung bei Login (Aufbewahrung)',
  idx.includes('DELETE FROM login_log WHERE zeitpunkt < NOW() - INTERVAL ? DAY'));
pruefe('Admin-Route login-log (nur Admin)',
  idx.includes("=== 'login-log'") && idx.includes('auth_require_admin'));
// Reihenfolge im Quelltext (Rückfall, die Route wird hier nicht ausgeführt):
// In der Admin-Route steht die Abfrage „aktiv?“ VOR dem Lesen des Protokolls.
const llRoute = rumpf(idx, "if (($seg[0] ?? '') === 'login-log')");
const lesen = llRoute.indexOf("if ($methode === 'GET' && !isset($seg[1])) {");
const waechter = /if \(\(int\)marke_wert\(\$pdo, 'login_log_aktiv', '0'\) !== 1\) \{\s*json_ok\(\['aktiv' => false, 'eintraege' => \[\]\]\);\s*\}/;
const ab = lesen < 0 ? '' : llRoute.slice(lesen);
const w = ab.search(waechter);
pruefe('Protokoll nur bei aktiv sichtbar',
  lesen >= 0 && w >= 0 && w < ab.indexOf('FROM login_log'));
pruefe('IP wird dem Admin nicht ausgeliefert',
  !idx.includes('SELECT webuntis_benutzer, erfolgreich, grund, zeitpunkt, ip'));

// ---- Frontend ----
pruefe('Admin-Ansicht Login-Protokoll',
  js.includes('function ansichtAdminLoginLog') && js.includes("'admin-loginlog'"));
pruefe('Datenschutz-Warnung sichtbar',
  js.includes('daten-warnung') && js.includes('Datenschutz-Hinweis'));
pruefe('Schalter aktiv/erfolge/tage',
  js.includes("'ll-aktiv'") && js.includes("'ll-erfolge'") && js.includes("'ll-tage'"));
pruefe('Nav-Punkt vorhanden', js.includes("navKnopf('admin-loginlog'"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
