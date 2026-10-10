// ============================================================
// tests/frontend_datenschutz_test.js
// Prüft v0.9.74 (H9 aus docs/HILFE-ABGLEICH-2026-10-09.md): den festen
// Datenschutz-Absatz der Hilfeseite.
//
// Bis v0.9.73 stand dort „Beim Archivieren … werden alle persönlichen
// Daten … gelöscht“. Das stimmte nicht: Login-Protokoll, Schülerliste und
// Kalender-Abo bleiben. Geprüft wird der SICHTBARE Text – datenschutz-
// Absaetze() wird ausgeführt –, und wo der Text eine Zahl nennt, deren
// Verbindung zur Quelle im Backend.
//
// Was das Archivieren wirklich löscht, prüft run_archivieren.php am
// ausgeführten Code; von dort kommt auch die Liste der personenbezogenen
// Tabellen, die NICHT ans Archivieren gebunden sind.
//
// Aufruf: node tests/frontend_datenschutz_test.js
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
const js = p('frontend/app.js');
const idx = p('backend/api/index.php');
const arch = p('tests/run_archivieren.php');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

// ---- ausführen ----
const r = rumpf('function datenschutzAbsaetze()');
let absaetze = [];
try { absaetze = new Function(r)() || []; } catch (e) { console.log('    (' + e.message + ')'); }
pruefe('datenschutzAbsaetze() liefert Absätze (Voraussetzung)',
  r !== '' && Array.isArray(absaetze) && absaetze.length >= 4
  && absaetze.every((a) => typeof a === 'string' && a.length > 20));
const text = absaetze.join('\n');

// ---- Aufrufstelle, genau eine Darstellung ----
const hilfe = rumpf('function ansichtHilfe(');
pruefe('ansichtHilfe zeigt den Absatz unter „Datenschutz“',
  /el\('h4', null, 'Datenschutz'\)\);\s*for \(const a of datenschutzAbsaetze\(\)\)/.test(hilfe));
pruefe('kein zweiter Archivier-Satz in ansichtHilfe',
  hilfe !== '' && !/rchivier/.test(hilfe));
pruefe('datenschutzAbsaetze() einmal aufgerufen', (js.match(/datenschutzAbsaetze\(\)/g) || []).length === 2);

// ---- die falsche Zusage von früher ----
pruefe('NICHT mehr: „alle persönlichen Daten“ werden gelöscht',
  !/alle persönlichen Daten/.test(js));

// ---- was beim Archivieren gelöscht wird ----
pruefe('Sprechtag-Daten: bleiben bis zum Archivieren, dann gelöscht',
  /bis die Schule den Sprechtag archiviert/.test(text) && /gelöscht/.test(text));
pruefe('… genannt: Termine mit Hinweisen an die Lehrkraft, Einladungen, Benachrichtigungen',
  /Termine/.test(text) && /Hinweise[n]? an die Lehrkraft/.test(text)
  && /Einladungen/.test(text) && /Benachrichtigungen/.test(text));
pruefe('… genannt: Name und Klasse des Kindes am Termin (v0.9.76, Zug 4)',
  /Termine mit Name und Klasse des Kindes/.test(text));
pruefe('… genannt: die für das Kind ermittelten Lehrkräfte (kind_lehrer_cache)',
  /für das Kind ermittelten Lehrkräfte/.test(text));
pruefe('… ohne automatische Frist', /keine automatische Frist/.test(text));

// ---- was NICHT gelöscht wird: jede personenbezogene Tabelle genannt ----
const persBlock = (arch.match(/const OHNE_SPRECHTAG = \[([\s\S]*?)\];/) || [])[1] || '';
const pers = [...persBlock.matchAll(/'(\w+)'\s*=>\s*'personenbezogen'/g)].map((m) => m[1]);
const merkmal = {
  login_log: /Anmeldeversuche/,
  // Der Eintrag selbst, nicht nur das Wort: Seit v0.9.77 nennt ein zweiter
  // Absatz den Kalender-Link (was das Abo liefert) – DS5 blieb sonst grün.
  kalender_abo: /Für den persönlichen Kalender-Link eine Kennnummer/,
};
// v0.9.82: mind. 2 statt 3 – schueler ist mit sql/23 fort. Die Voraussetzung
// schützt davor, eine leere Liste zu lesen; das kann sie weiterhin.
pruefe('Voraussetzung: personenbezogene Tabellen aus run_archivieren.php gelesen (mind. 2)',
  pers.length >= 2);
for (const t of pers) {
  pruefe('Hilfe nennt, was beim Archivieren bleibt: ' + t,
    merkmal[t] !== undefined && merkmal[t].test(text));
}

// v0.9.82 (Zug 4, Schritt 4): Die Tabelle schueler ist fort (sql/23). Bis
// v0.9.81 verlangte diese Stelle den Satz „bleibt, bis die Schule sie
// löscht“. Die Engstelle oben sagt, was das Archivieren NICHT löscht – ob
// es eine Tabelle gibt, sagt sie nicht. Erst diese Umkehrung hält den Satz
// draußen.
pruefe('Hilfe nennt keine Schülerliste mehr als gespeichert (die Tabelle ist fort)',
  absaetze.length >= 5 && !/Schülerliste/.test(text) && !/früheren Abgleich/.test(text));
pruefe('Voraussetzung: schueler steht nicht mehr unter den personenbezogenen Tabellen (run_archivieren.php)',
  pers.length >= 2 && !pers.includes('schueler'));

const tage = (p('sql/16_login_log.sql').match(/\('login_log_tage', +'(\d+)'\)/) || [])[1];
const hoechst = (idx.match(/min\((\d+), \(int\)marke_wert\(\$pdo, 'login_log_tage'/) || [])[1];
pruefe('Voraussetzung: Voreinstellung (sql/16) und Obergrenze (index.php) gelesen', !!tage && !!hoechst);
pruefe('Login: Benutzername und IP-Adresse fehlgeschlagener Anmeldeversuche',
  /[Ff]ehlgeschlagene Anmeldeversuche[^\n]*Benutzername[^\n]*IP-Adresse/.test(text));
pruefe('Login: Frist mit der Voreinstellung aus der Migration (' + tage + ' Tage)',
  new RegExp('voreingestellt ' + tage + ' Tage').test(text));
pruefe('Login: Obergrenze aus dem Backend (' + hoechst + ')',
  new RegExp('höchstens ' + hoechst + '\\b').test(text));
pruefe('Login: Erfolge nur, wenn die Schule es eingestellt hat',
  /Erfolgreiche Anmeldungen[^\n]*nur[^\n]*eingestellt/.test(text));

pruefe('Kalender: Kennnummer des Kontos, kein Name, keine Frist',
  /Kalender-Link[^\n]*Kennnummer[^\n]*kein Name/.test(text) && /derzeit keine Frist/.test(text));
pruefe('Kalender: entsteht beim Öffnen von „Meine Termine“',
  /sobald „Meine Termine“ geöffnet wird/.test(text));

// ---- das Abo liefert nur kommende Sprechtage (v0.9.77, E21) ----
// Was das Abo liefert, prüft run_kalender_abo.php am ausgeführten Code;
// hier der Text, der es den Eltern sagt, und seine Grenze.
pruefe('Kalender-Abo: nur Termine kommender Sprechtage, am Tag danach heraus',
  /Kalender-Link liefert nur die Termine kommender Sprechtage/.test(text)
  && /am Tag nach dem Sprechtag/.test(text));
pruefe('Kalender-Abo: verspricht nicht, dass die App sie löscht',
  /liegt an der App/.test(text) && !/werden (auch )?aus (Ihrem|dem) Kalender gelöscht/.test(text));
pruefe('Grenze genannt: ein einzeln übernommener Termin ist eine Kopie und bleibt',
  /einzeln[^\n]*Kopie[^\n]*bleibt dort/.test(text));
const meine = rumpf('function ansichtMeineTermine(');
const hilfeR = rumpf('function ansichtHilfe(');
pruefe('Eltern: „Meine Termine“ und FAQ sagen „kommende“, nicht mehr „alle“',
  /Ihre kommenden Sprechtag-Termine/.test(meine) && /die Termine kommender Sprechtage automatisch/.test(hilfeR)
  && !/alle Ihre Sprechtag-Termine/.test(js) && !/dem alle Termine automatisch/.test(js));
pruefe('Lehrkraft: der Hinweis zum Abo-Link sagt „kommenden Termine“',
  /Abo-Link, mit dem Ihre kommenden Termine/.test(js));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
