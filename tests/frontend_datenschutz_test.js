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
// v0.9.89: ansichtHilfe wird ausgeführt und am Gezeigten geprüft. Bis
// v0.9.88 verlangte die Prüfung die Schreibweise `el('h4', null,
// 'Datenschutz')` vor der Schleife und wurde bei der Neugliederung nach
// Rollen rot, obwohl die Absätze weiter dort standen
// (docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md, Grenze der Abhilfe).
const hilfe = rumpf('function ansichtHilfe(');
function hilfeAusfuehren() {
  const knoten = (tag, klasse, text) => ({ tag, text: text == null ? '' : String(text), kinder: [],
    appendChild(k) { this.kinder.push(k); return k; }, addEventListener() {}, setAttribute() {},
    set innerHTML(h) { this.html = h; } });
  const sektion = (t) => { const s = knoten('section'); s.kinder.push(knoten('h3', null, t)); return s; };
  const teileQuelle = (js.match(/^const HILFE_TEILE = (\[[\s\S]*?\n\]);/m) || [])[1];
  const TEILE = new Function('return ' + teileQuelle)();
  const sprung = new Function('HILFE_TEILE', 'rolle', rumpf('function hilfeSprungmarken('));
  const namen = ['S', 'el', 'document', 'sektion', 'block', 'knopf', 'api', 'wechsleAnsicht', 'zeichne',
    'kontaktSatz', 'hilfeListe', 'hilfeSchluessel', 'datenschutzAbsaetze', 'HILFE_TEILE', 'hilfeSprungmarken',
    'hilfeKnopfZuDenMarken'];
  const werte = [{ user: null, hilfeZusatz: '', offeneBloecke: {} }, knoten,
    { createElement: (t) => knoten(t) }, sektion, (k, t) => knoten('details', null, t),
    (t) => knoten('button', null, t), () => Promise.resolve({}), () => {}, () => {}, (v) => v,
    () => knoten('ol'), (t) => t, () => absaetze.slice(), TEILE, (rolle) => sprung(TEILE, rolle), () => {}];
  const ziel = knoten('main');
  new Function(...namen, 'ziel', hilfe)(...werte, ziel);
  return ziel;
}
let gezeigt = null;
try { gezeigt = hilfeAusfuehren(); } catch (e) { console.log('    (ansichtHilfe: ' + e.message + ')'); }
const alleKnoten = (k) => [k].concat(...k.kinder.map(alleKnoten));
const dsAbschnitte = gezeigt
  ? gezeigt.kinder.filter((k) => k.tag === 'section' && k.kinder[0].text === 'Datenschutz') : [];
const dsTexte = dsAbschnitte.length === 1
  ? dsAbschnitte[0].kinder.slice(1).filter((k) => k.tag === 'p').map((k) => k.text) : [];
const vorkommen = gezeigt
  ? absaetze.map((a) => alleKnoten(gezeigt).filter((k) => k.text === a).length) : [];
pruefe('ansichtHilfe zeigt die Absätze im Abschnitt „Datenschutz“, je genau einmal (ausgeführt)',
  absaetze.length >= 4 && dsAbschnitte.length === 1
  && JSON.stringify(dsTexte) === JSON.stringify(absaetze)
  && vorkommen.every((n) => n === 1));
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

// v0.9.83 (E19, G7): Jede Tabelle, die das Archivieren leert, ist im Absatz
// genannt. Die Liste kommt aus run_archivieren.php, das sie gegen den
// ausgeführten Archivieren-Block hält – eine neue Tabelle ohne Merkmal hier
// wird rot, statt still ungenannt zu bleiben.
const geleert = ((arch.match(/foreach \(\[([^\]]*)\] as \$t\) \{/) || [])[1] || '')
  .split(',').map((s) => s.trim().replace(/^'|'$/g, '')).filter(Boolean);
const genannt = {
  buchungen: /Termine mit Name und Klasse des Kindes/,
  einladungen: /Einladungen/,
  kind_lehrer_cache: /für das Kind ermittelten Lehrkräfte/,
  mitteilungen: /Benachrichtigungen/,
  abgesagte_termine: /auch abgesagte Termine/,
};
pruefe('Voraussetzung: geleerte Tabellen aus run_archivieren.php gelesen (mind. 5, darunter buchungen)',
  geleert.length >= 5 && geleert.includes('buchungen'));
// Eine neue Tabelle ohne Merkmal hier: eigene Prüfung mit festem Namen
// (v0.9.105) – vorher trug nur die Zeile der neuen Tabelle deren Namen, und
// die gab es in Grün nicht (Vorprüfung in tests/mutationen.sh, AT50).
const ohneMerkmal = geleert.filter((t) => genannt[t] === undefined);
pruefe('Jede Tabelle, die das Archivieren leert, hat hier ein Merkmal für die Hilfe – ohne: '
  + (ohneMerkmal.join(', ') || 'keine'), ohneMerkmal.length === 0);
for (const t of geleert.filter((x) => genannt[x] !== undefined)) {
  pruefe('Hilfe nennt, was beim Archivieren gelöscht wird: ' + t, genannt[t].test(text));
}

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
