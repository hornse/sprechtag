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
const mig = fs.readFileSync(path.join(__dirname, '..', 'sql', '16_login_log.sql'), 'utf8');

// ---- Standard: aus ----
pruefe('Default aktiv=0 (aus per Werk)', mig.includes("('login_log_aktiv',   '0')"));
pruefe('Default erfolge=0 (nur Fehlschläge)', mig.includes("('login_log_erfolge', '0')"));
pruefe('Default 30 Tage', mig.includes("('login_log_tage',    '30')"));

// ---- Backend ----
pruefe('Erfolge nur bei aktiv+erfolge geloggt',
  idx.includes('$logAktiv && $logErfolge'));
pruefe('Bereinigung bei Login (Aufbewahrung)',
  idx.includes('DELETE FROM login_log WHERE zeitpunkt < NOW() - INTERVAL ? DAY'));
pruefe('Admin-Route login-log (nur Admin)',
  idx.includes("=== 'login-log'") && idx.includes('auth_require_admin'));
pruefe('Protokoll nur bei aktiv sichtbar',
  idx.includes("marke_wert($pdo, 'login_log_aktiv'"));
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
