// ============================================================
// tests/frontend_kalender_test.js
// Prüft v0.9.25: iCal-Abo-Link + Einzeldatei-Download.
//
// Aufruf: node tests/frontend_kalender_test.js
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
const kal = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'kalender.php'), 'utf8');

// ---- Backend: öffentlicher Abo-Feed ----
pruefe('öffentlicher .ics-Feed vor Auth',
  idx.includes("=== 'kalender'") && idx.indexOf("=== 'kalender'") < idx.indexOf('auth_require'));
pruefe('Token statt user.id in der URL (48 Hex)',
  kal.includes('random_bytes(24)') && idx.includes('[a-f0-9]{48}'));
pruefe('Feed liefert nur eigene Buchungen (Filter eltern_user_id)',
  kal.includes('WHERE b.eltern_user_id = ?'));
pruefe('iCal-Rahmen VCALENDAR/VEVENT',
  kal.includes('BEGIN:VCALENDAR') && kal.includes('BEGIN:VEVENT'));
pruefe('iCal escaped Sonderzeichen', kal.includes('function kal_escape'));

// ---- Backend: authed Link + Einzeldatei ----
pruefe('Abo-Link-Route (authed)',
  idx.includes("=== 'kalender-link'") && idx.includes('auth_require'));
pruefe('Neuer Link revoziert alten', idx.includes("=== 'neu'") && kal.includes('kal_token_neu'));
pruefe('Einzelbuchung als .ics (nur eigene)',
  idx.includes("=== 'buchung'") && idx.includes('kal_buchungen_laden($pdo, (int)$u[\'user_id\'], $bid)'));

// ---- Datensparsamkeit: keine Elternnamen im Event ----
pruefe('Event nennt nur Lehrkraft/Raum/Kind (kein Elternname)',
  kal.includes("'Sprechtag: ' . $lehrer") && !kal.includes('eltern_name'));

// ---- Frontend ----
pruefe('Meine Termine: Abo-Link-Bereich',
  js.includes('kalender-abo') && js.includes('/api/kalender-link'));
pruefe('Einzeltermin-Download je Zeile',
  js.includes("'/api/buchung/' + b.id + '.ics'"));
pruefe('Neuer-Link-Knopf', js.includes("'/api/kalender-link/neu'"));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
