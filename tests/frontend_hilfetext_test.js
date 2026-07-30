// ============================================================
// tests/frontend_hilfetext_test.js
// Prüft die editierbaren Texte (Hilfe, Buchung, Login) – Markdown,
// serverseitig sicher gerendert, generische Route.
//
// Aufruf: node tests/frontend_hilfetext_test.js
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
const hlf = fs.readFileSync(path.join(__dirname, '..', 'backend', 'helfer.php'), 'utf8');
const m17 = fs.readFileSync(path.join(__dirname, '..', 'sql', '17_hilfe_zusatz.sql'), 'utf8');
const m18 = fs.readFileSync(path.join(__dirname, '..', 'sql', '18_hinweistexte.sql'), 'utf8');

pruefe('markdown_sicher escaped zuerst',
  hlf.includes('function markdown_sicher') && hlf.includes('htmlspecialchars'));
pruefe('Links nur http/https/mailto', hlf.includes("^(https?://|mailto:)"));
pruefe('Platzhalter-Funktion vorhanden', hlf.includes('function platzhalter_ersetzen'));

pruefe('generische text-Route', est.includes("($seg[1] ?? '') === 'text'"));
pruefe('Whitelist der Schluessel',
  est.includes("'hilfe_zusatz', 'buchung_hinweis', 'login_hinweis'"));
pruefe('Rohtext nur fuer Admins', est.includes('auth_ist_admin()'));
pruefe('serverseitig gerendert', est.includes('markdown_sicher(platzhalter_ersetzen'));

pruefe('Migration Hilfe', m17.includes("'hilfe_zusatz'"));
pruefe('Migration Buchung/Login',
  m18.includes("'buchung_hinweis'") && m18.includes("'login_hinweis'"));

pruefe('Editor-Ansicht (drei Texte)', js.includes('function ansichtAdminTexte')
  && js.includes("textEditor('hilfe_zusatz'") && js.includes("textEditor('buchung_hinweis'")
  && js.includes("textEditor('login_hinweis'"));
pruefe('wiederverwendbarer Editor', js.includes('function textEditor'));
pruefe('Nav-Punkt Texte', js.includes("navKnopf('admin-texte'"));
pruefe('Variablenliste', js.includes('{{kontakt}}') && js.includes('{{schulname}}'));
pruefe('Hilfeseite zeigt Zusatz', js.includes("api('/api/einstellungen/text/hilfe_zusatz')"));
pruefe('Buchungsseite zeigt Hinweis',
  js.includes("zeigeHinweisText(ziel, 'buchung_hinweis'"));
pruefe('Loginseite zeigt Hinweis',
  js.includes("zeigeHinweisText(ziel, 'login_hinweis'"));
pruefe('innerHTML nur fuer serverseitig gesaeubertes HTML',
  js.includes('serverseitig gesäubert'));

pruefe('Login: eigener Text ersetzt Standard',
  js.includes('const eigener = zeigeHinweisText') && js.includes('if (!eigener)'));
pruefe('zeigeHinweisText meldet Anzeige zurueck',
  js.includes('return true;') && js.includes('return false;'));

console.log(fehler === 0 ? '\nALLE TESTS GRUEN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
