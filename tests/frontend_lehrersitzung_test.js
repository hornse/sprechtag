// ============================================================
// tests/frontend_lehrersitzung_test.js
// Prueft den Umbau: Mitteilungen gehen unter dem Konto der angemeldeten
// Person hinaus. Seit v0.9.72 (E17) NUR noch so – das Dienstkonto ist fort;
// das Verhalten bei abgelaufener Sitzung prüft run_sitzung_versand.php.
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

// ---- Versand nur über die Sitzung (v0.9.72, E17) ----
pruefe('Sitzungszugang im Adapter (wu_sitzung)', ada.includes('function wu_sitzung(array $cfg'));
pruefe('Versand verlangt eine Sitzung – keine eigene Anmeldung mit Zugangsdaten mehr',
  mit.includes('function mit_versand_ausfuehren(PDO $pdo, array $ids, WebUntisRest $rest)')
  && !mit.includes('->authenticate('));
pruefe('kein Dienstkonto als Rückfall',
  !mit.includes("'absender' => 'Dienstkonto'") && !mit.includes('dk_lesen'));

console.log(fehler === 0 ? '\nALLE TESTS GRUEN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
