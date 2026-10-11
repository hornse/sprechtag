// ============================================================
// tests/frontend_halbtags_test.js
// Statische Prüfung von Halbtagskräften + Krankheitsausfall (v0.9.9):
//   - Admin-Block markiert Halbtagskräfte (PATCH stammdaten/lehrer)
//   - Lehrer-Zuweisung bietet Hälfte-Dropdown + Ausfall-Knopf
//   - Lehrkraft wählt im eigenen Raster ihre Hälfte selbst
//   - Backend: slot_haelfte_fenster, haelfte-PATCH, ausfall-Route
//
// v0.9.85: Jede Prüfung sucht im Rumpf der Funktion oder Route, um die es
// geht, nicht in der ganzen Datei. Bis v0.9.84 suchten alle zwölf in der
// Datei; bei sechs stand die Zeichenkette an einer zweiten Stelle, und
// sieben Eingriffe an der gemeinten Stelle blieben grün (CHANGELOG v0.9.83,
// „Gefunden, nicht behoben“; Mutationen HB1–HB7). Die übrigen sechs fanden
// ihre Zeichenkette nur zufällig einmal – auch sie suchen jetzt im Rumpf.
//
//   - JavaScript: rumpf() aus rumpf.js, ohne Kommentare.
//   - PHP: phpBlock() vom Bedingungskopf bis zur schließenden Klammer auf
//     seiner Einrückung, ohne Kommentarzeilen.
// Kommt ein Kopf nicht genau einmal vor, ist der Ausschnitt leer, und die
// Prüfung wird rot.
//
// Was hier steht, ist Quelltext. Ausgeführt wird slot_haelfte_fenster in
// run_slots.php, die Ausfall-Route in run_abgesagte_termine.php.
//
// Aufruf: node tests/frontend_halbtags_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const lies  = (d) => fs.readFileSync(path.join(__dirname, '..', d), 'utf8');
const js    = lies('frontend/app.js');
const idx   = lies('backend/api/index.php');
const slots = lies('backend/api/slots.php');
const buch  = lies('backend/api/buchungen.php');
const sql   = lies('sql/11_halbtags.sql');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

/**
 * PHP-Ausschnitt: von der Zeile mit `kopf` bis zur ersten Zeile, die nur
 * aus der Einrückung dieser Zeile und „}“ besteht. Ganze Kommentarzeilen
 * fallen weg, damit eine Beschreibung nicht als Code zählt. Kommt `kopf`
 * nicht genau einmal vor, oder fehlt das Ende: ''.
 */
function phpBlock(quelle, kopf) {
  const anzahl = quelle.split(kopf).length - 1;
  if (anzahl !== 1) {
    console.log('    (Voraussetzung: „' + kopf + '" kommt ' + anzahl + '-mal vor, erwartet 1)');
    return '';
  }
  const a = quelle.lastIndexOf('\n', quelle.indexOf(kopf)) + 1;
  const einzug = (quelle.slice(a).match(/^[ \t]*/) || [''])[0];
  const e = quelle.indexOf('\n' + einzug + '}\n', a);
  if (e < 0) return '';
  return quelle.slice(a, e + einzug.length + 2).replace(/^[ \t]*(\/\/|\*|\/\*).*$/gm, '');
}
const ohneSqlKommentar = (s) => s.replace(/^\s*--.*$/gm, '');

// ---- Frontend ----
const verwaltung = rumpf('async function oeffneLehrerVerwaltung(s)');
pruefe('Halbtags-Markierung in der Lehrer-Tabelle',
  verwaltung.includes("cbH.title = 'Halbtagskraft / Referendar:in';")
  && /api\('\/api\/stammdaten\/lehrer\/' \+ l\.lehrer_id,\s*\{ method: 'PATCH', body: \{ halbtags: /.test(verwaltung));
const zelle = rumpf('function anwesenheitZelle(s, l)');
pruefe('Lehrer-Zuweisung: Hälfte-Dropdown für Halbtagskräfte',
  zelle.includes("sel.id = 'haelfte-' + s.id + '-' + l.lehrer_id;") && zelle.includes("['erste', 'erste Hälfte']"));
// Der Aufruf im Knopf, nicht die Definition: Eine Funktion, die niemand
// aufruft, wäre genau der Fehler, den diese Prüfung fangen soll.
const ausfallFn = rumpf('async function lehrerAusfall(s, l)');
pruefe('Lehrer-Zuweisung: Ausfall-Knopf',
  /iconKnopf\('⊘', 'ausfall', '[^']*',\s*\(\) => lehrerAusfall\(s, l\)\)/.test(verwaltung)
  && /\+ '\/ausfall', \{ method: 'POST'/.test(ausfallFn));
const raster = rumpf('function zeichneLehrkraftRaster(ziel, lehrerId)');
const wahl = rumpf('function zeichneHaelfteWahl(ziel, lehrerId)');
pruefe('Selbstbedienung: eigene Hälfte wählbar',
  /zeichneHaelfteWahl\(ziel, lehrerId\);/.test(raster)
  && wahl.includes("auswahl('Meine Hälfte', 'haelfte-eigen',") && wahl.includes("wert('haelfte-eigen')"));
// Bedingung und Aufruf zusammenhängend: Ein Aufruf irgendwo im Rumpf
// bestünde auch ohne die Bedingung.
pruefe('Selbstbedienung nur bei halbtags und eigenem Raster',
  /if \(eigenesRaster && S\.svLehrer && parseInt\(S\.svLehrer\.halbtags, 10\) === 1\) \{\s*zeichneHaelfteWahl\(ziel, lehrerId\);\s*\}/.test(raster));

// ---- Backend ----
pruefe('slot_haelfte_fenster vorhanden',
  /^function slot_haelfte_fenster\(array \$sprechtag, string \$haelfte\): array$/m.test(slots));
const patch = phpBlock(idx, "if ($methode === 'PATCH' && isset($seg[3]) && ctype_digit($seg[3])) {");
pruefe('PATCH akzeptiert haelfte erste/zweite/ganz',
  /if \(!in_array\(\$h, \['erste', 'zweite', 'ganz'\], true\)\) \{/.test(patch)
  && /\$s = \$sprechtagHolen\(\$pdo, \$sid\);\s*\$f = slot_haelfte_fenster\(\$s, \$h\);\s*\$von = \$f\['von'\];\s*\$bis = \$f\['bis'\];/.test(patch));
pruefe('Lehrkraft darf eigenen Eintrag ändern',
  /\$eigene = \(\$u\['rolle'\] === 'lehrkraft'\s*&& \(int\)\(\$u\['lehrer_id'\] \?\? 0\) === \$lid\);/.test(patch)
  && /if \(\$u\['rolle'\] !== 'admin' && !\$eigene\) \{/.test(patch));
const toggle = phpBlock(idx, "if ($methode === 'PATCH' && ($seg[1] ?? '') === 'lehrer'");
pruefe('Halbtags-Toggle-Route',
  /^\s*&& isset\(\$seg\[2\]\) && ctype_digit\(\$seg\[2\]\)\) \{/m.test(toggle)
  && /\$pdo->prepare\('UPDATE lehrer SET halbtags = \? WHERE id = \?'\)/.test(toggle));
// Im Rumpf der Route gesucht, nicht in der Datei (v0.9.83): Bis v0.9.82
// suchte die Prüfung „DELETE FROM buchungen“ in ganz index.php und blieb nach
// dem Umbau auf bu_buchungen_entfernen grün – durch den Archivieren-Block.
// Ausschnitt: vom Bedingungskopf bis zum nächsten „// ----“; nicht gefunden
// heißt leer, und leer ist rot.
const ausfallAb = idx.indexOf("=== 'ausfall')");
const ausfallBis = ausfallAb < 0 ? -1 : idx.indexOf('// ----', ausfallAb);
const ausfallRumpf = ausfallAb < 0 || ausfallBis < 0 ? '' : idx.slice(ausfallAb, ausfallBis);
pruefe('Ausfall-Route gibt Termine frei und benachrichtigt',
  /bu_buchungen_entfernen\(\$pdo,[^;]*'ausfall'\);/.test(ausfallRumpf)
  && ausfallRumpf.includes('mit_text_absage('));
const rasterRoute = phpBlock(buch, "if ($methode === 'GET' && ($seg[0] ?? '') === 'raster') {");
pruefe('Raster liefert halbtags-Flag',
  /'lehrer' => \['id' => \$lid, 'halbtags' => \$halbtags,/.test(rasterRoute));

// ---- SQL ----
pruefe('Schema idempotent (ADD COLUMN IF NOT EXISTS)',
  /ALTER TABLE lehrer\s+ADD COLUMN IF NOT EXISTS halbtags\b/.test(ohneSqlKommentar(sql)));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
