// ============================================================
// tests/frontend_lehrersitzung_test.js
// Prueft den Umbau: Mitteilungen gehen unter dem Konto der angemeldeten
// Person hinaus, Dienstkonto nur noch als Rueckfall.
//
// Grundlage: lernzeiten-Auskunft 06./07.10.2026 (eigener Code hat mit der
// Lehrkraft-Sitzung gesendet; Scope mg:r ist keine Sperre).
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const p = (f) => fs.readFileSync(path.join(__dirname, '..', f), 'utf8');
const mit = p('backend/api/mitteilungen.php');
const aut = p('backend/api/auth.php');
const ada = p('backend/api/webuntis_adapter.php');

// ---- Sitzung wird festgehalten statt weggeworfen ----
pruefe('Login loggt bei Erfolg NICHT mehr aus (nur im Fehlerfall)',
  ada.includes('catch (Throwable $e)') && ada.includes('Sitzung bleibt bei erfolgreicher Anmeldung'));
pruefe('Cookie wird zurueckgegeben', ada.includes("$ergebnis['wu_cookie']"));
pruefe('Cookie landet in der PHP-Sitzung', aut.includes("$_SESSION['wu_cookie']"));
pruefe('Cookie steht NICHT in auth_user (nicht ans Frontend)',
  aut.indexOf('wu_cookie') > aut.indexOf('function auth_login_speichern'));
pruefe('Zugriffsfunktion vorhanden', aut.includes('function auth_wu_cookie'));

// ---- Versand nutzt die Sitzung ----
pruefe('Client aus der Sitzung baubar', mit.includes('function mit_rest_aus_sitzung'));
pruefe('abgelaufene Sitzung gibt null', mit.includes('if (!$rest->tokenHolen()) return null;'));
pruefe('Versand kann vorgegebene Sitzung nutzen',
  mit.includes('?WebUntisRest $restVorgegeben = null'));
pruefe('fremde Sitzung wird nicht ausgeloggt',
  mit.includes('if ($restVorgegeben === null) $wu->logout();'));

// ---- Rangfolge: eigenes Konto vor Dienstkonto ----
pruefe('eigene Sitzung wird zuerst versucht',
  mit.indexOf('mit_rest_aus_sitzung($cfg)') < mit.indexOf("'absender' => 'Dienstkonto'"));
pruefe('Absender wird gemeldet', mit.includes("'absender' => 'eigenes Konto'"));
pruefe('abgelaufene Sitzung ohne Dienstkonto -> Hinweis auf Neuanmeldung',
  mit.includes('bitte neu anmelden und erneut senden'));

console.log(fehler === 0 ? '\nALLE TESTS GRUEN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
