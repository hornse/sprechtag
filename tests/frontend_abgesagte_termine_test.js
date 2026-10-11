// ============================================================
// tests/frontend_abgesagte_termine_test.js
// v0.9.83 (E18, E19): Status der Einladungen und „Abgesagte Termine“ in der
// Ansicht der Lehrkraft.
//
// Geprüft wird der angezeigte Text, gebildet von den Funktionen aus app.js
// (ausgeführt, Umgebung als Ersatz) – nicht, welche Zeichenkette im Code
// steht. Die Eingaben tragen die Feldnamen, die das Backend liefert:
//   - den Status-Alias liest die Suite aus der Lehrkraft-Abfrage in
//     backend/api/buchungen.php,
//   - die Felder der abgesagten Termine aus AB_FELDER in
//     tests/run_abgesagte_termine.php, das sie gegen die ausgeführte
//     Abfrage hält.
// Anlass: Bis zu diesem Zug las app.js e.erledigt weiter, als die API es
// nicht mehr lieferte – keine Prüfung wurde rot.
//
// Aufruf: node tests/frontend_abgesagte_termine_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const p = (d) => fs.readFileSync(path.join(__dirname, '..', d), 'utf8');
const js = p('frontend/app.js');
const bu = p('backend/api/buchungen.php');
const abTest = p('tests/run_abgesagte_termine.php');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

/** Ersatzknoten: merkt Kinder, Text und Ereignisse. */
function knoten(tag, klasse, text) {
  return {
    tag, klasse: klasse || '', text: text == null ? '' : String(text), kinder: [], ereignisse: 0,
    appendChild(k) { this.kinder.push(k); return k; },
    addEventListener() { this.ereignisse++; },
    setAttribute() {}, classList: { add() {}, remove() {} },
  };
}
/** Alle Knoten eines Baums, in Reihenfolge. */
function alle(k) { return [k].concat(...(k.kinder || []).map(alle)); }

// ------------------------------------------------------------
console.log('Status einer Einladung (E18, G1)');
const alias = (bu.match(/bu_einladung_status_sql\('e'\) \. ' AS (\w+),/) || [])[1];
const statusRumpf = rumpf('function einladungStatusText(');
let statusText = null;
try { statusText = new Function('e', statusRumpf); } catch (e) { statusText = null; }
pruefe('Voraussetzung: Status-Alias aus der Lehrkraft-Abfrage gelesen, einladungStatusText gefunden',
  !!alias && statusRumpf !== '' && !!statusText);
const st = (wert) => { try { return statusText({ [alias]: wert }); } catch (e) { return 'AUSNAHME ' + e.message; } };
pruefe('angezeigt: „Termin gebucht“, „Termin abgesagt“, „offen“ – aus dem Feld, das die API liefert',
  !!statusText && st('gebucht') === 'Termin gebucht' && st('abgesagt') === 'Termin abgesagt' && st('offen') === 'offen');
let ohne = '';
try { ohne = statusText({ erledigt: '1' }); } catch (e) { ohne = 'AUSNAHME'; }
pruefe('fehlt der Status (etwa nur das alte erledigt), steht „unbekannt“ da – nicht „offen“',
  !!statusText && ohne === 'unbekannt');
const einl = rumpf('function ansichtEinladungen(');
pruefe('Einladungsliste zeigt den Status über einladungStatusText und liest erledigt nicht (Quelltext)',
  einl !== '' && /el\('td', null, einladungStatusText\(e\)\)/.test(einl) && !/erledigt/.test(einl));
// Engstelle (G5): kein Skript unter frontend/ liest das Feld. Gesucht werden
// die Schreibweisen eines Feldes, nicht das Wort – „bis sie erledigt ist“ in
// einem Kommentar bleibt erlaubt.
const feld = /\.\s*erledigt\b|['"]erledigt['"]|\berledigt\s*:/;
pruefe('Suchausdruck trifft e.erledigt, e[\'erledigt\'] und { erledigt: 1 }, nicht das Wort im Satz',
  feld.test('if (e.erledigt) x();') && feld.test("e['erledigt']") && feld.test('{ erledigt: 1 }')
  && !feld.test('// bleibt stehen, bis sie erledigt ist.'));
const skripte = fs.readdirSync(path.join(__dirname, '..', 'frontend'), { recursive: true })
  .map(String).filter((d) => d.endsWith('.js'));
const mitFeld = skripte.filter((d) => feld.test(p('frontend/' + d)));
pruefe('kein Skript unter frontend/ liest erledigt (' + skripte.length + ' Skripte'
  + (mitFeld.length ? ', getroffen: ' + mitFeld.join(', ') : '') + ')',
  skripte.includes('app.js') && mitFeld.length === 0);
// Die Messumgebung für das Telefon liefert, was die API liefert: den Status
// unter dem Alias der Lehrkraft-Abfrage, kein erledigt.
const mock = p('tests/mobil-messung/mock.js');
pruefe('Mobil-Messung (mock.js): kein erledigt, Einladungen mit „' + alias + '“',
  !!alias && !feld.test(mock) && new RegExp('\\b' + alias + ':').test(mock));

// ------------------------------------------------------------
console.log('Abgesagte Termine: Zeilen (G4)');
const felder = ((abTest.match(/const AB_FELDER = \[([^\]]*)\];/) || [])[1] || '')
  .split(',').map((s) => s.trim().replace(/^'|'$/g, '')).filter(Boolean);
pruefe('Voraussetzung: Felder der Abfrage aus run_abgesagte_termine.php gelesen (7)',
  felder.length === 7 && felder.includes('neu_gebucht'));
// Eingaben nur mit diesen Feldern – liest die Anzeige ein anderes, fehlt es.
const zeile = (werte) => Object.fromEntries(felder.map((f, i) => [f, werte[i]]));
const zeilenRumpf = rumpf('function abgesagteZeilen(');
let zeilen = null;
try { zeilen = new Function('liste', 'zeitstempel', zeilenRumpf); } catch (e) { zeilen = null; }
// Die echte Zeitformatierung aus app.js, keine nachgebaute.
let zs = null;
try { zs = new Function('iso', rumpf('function zeitstempel(')); } catch (e) { zs = null; }
// ERFUNDEN, in der Reihenfolge von AB_FELDER:
// slot_beginn, kind_name, klasse, kommentar, anlass, abgesagt_am, neu_gebucht
const eingabe = felder.length === 7 ? [
  zeile(['15:00:00', 'ErfundenA, Ute', '7a', 'Mathe', 'ausfall', '2026-11-12 08:05:00', null]),
  zeile(['15:10:00', 'ErfundenB, Tom', '7b', '', 'absage', '2026-11-10 17:30:00', '16:00:00']),
] : [];
let aus = null;
try { aus = zeilen && zs ? zeilen(eingabe, zs) : null; } catch (e) { aus = 'AUSNAHME ' + e.message; }
pruefe('Krankheitsausfall: Uhrzeit, Kind, Klasse, Anlass, wann abgesagt, Hinweis, „nicht neu gebucht“',
  Array.isArray(aus) && JSON.stringify(aus[0]) === JSON.stringify(
    ['15:00', 'ErfundenA, Ute', '7a', 'Krankheitsausfall', '12.11. 08:05', 'Mathe', 'nicht neu gebucht']));
pruefe('einzelne Absage, neu gebucht: „neu gebucht um 16:00“, ohne Hinweis ein Strich',
  Array.isArray(aus) && JSON.stringify(aus[1]) === JSON.stringify(
    ['15:10', 'ErfundenB, Tom', '7b', 'Absage', '10.11. 17:30', '–', 'neu gebucht um 16:00']));

// ------------------------------------------------------------
console.log('Abgesagte Termine: Abschnitt');
const abschnittRumpf = rumpf('function zeichneAbgesagte(');
function abschnitt(liste) {
  const ziel = knoten('div');
  let knoepfe = 0;
  try {
    new Function('ziel', 'liste', 'el', 'knopf', 'kartenTabelle', 'abgesagteZeilen', 'zeitstempel', abschnittRumpf)(
      ziel, liste, knoten, () => { knoepfe++; return knoten('button'); }, (t) => t,
      (l) => l.map((a) => ['Z' + a.slot_beginn, 'K', 'C', 'A', 'D', 'H', 'S']), (s) => s);
  } catch (e) { ziel.appendChild(knoten('p', null, 'AUSNAHME ' + e.message)); }
  return { ziel, knoepfe };
}
const zwei = abschnitt(eingabe);
const k2 = alle(zwei.ziel);
pruefe('Überschrift „Abgesagte Termine“ genau einmal, Kopf und eine Zeile je Termin',
  abschnittRumpf !== '' && k2.filter((k) => k.tag === 'h3' && k.text === 'Abgesagte Termine').length === 1
  && k2.filter((k) => k.tag === 'tr').length === 3
  && k2.filter((k) => k.tag === 'td').map((k) => k.text).join('|').startsWith('Z15:00:00|K|C|A|D|H|S|Z15:10:00'));
pruefe('… ohne Knöpfe und ohne Klickziele (G4: nur zum Ansehen)',
  abschnittRumpf !== '' && zwei.knoepfe === 0 && k2.every((k) => k.ereignisse === 0));
pruefe('keine abgesagten Termine: kein Abschnitt',
  abschnittRumpf !== '' && abschnitt([]).ziel.kinder.length === 0);

// ------------------------------------------------------------
console.log('Abgesagte Termine: in der Ansicht der Lehrkraft');
const rasterRumpf = rumpf('function zeichneLehrkraftRaster(');
function raster(eigenId, gezeigteId) {
  const ziel = knoten('div');
  const aufrufe = [];
  const S = { svRaster: [{ typ: 'slot', beginn: '15:00', frei: true }], svAbgesagt: eingabe,
    svLehrer: null, user: { lehrer_id: eigenId }, aktiverSprechtag: { id: 1 }, lehrerKalenderLink: 'x' };
  const namen = ['S', 'el', 'knopf', 'block', 'zeichneHaelfteWahl', 'zeichneStellvertreterKopf',
    'stellvertretendBuchen', 'lehrkraftStorno', 'ladeSvRaster', 'zeichneAbgesagte', 'api', '$', 'toast',
    'document', 'navigator', 'confirm'];
  const werte = [S, knoten, (t) => knoten('button', null, t), (id, t) => knoten('section', null, t),
    () => {}, () => {}, () => {}, () => {}, () => {},
    (z, l) => { aufrufe.push(l); z.appendChild(knoten('MARKE')); },
    () => Promise.resolve({}), () => null, () => {},
    { createElement: (t) => knoten(t) }, {}, () => false];
  try { new Function(...namen, 'ziel', 'lehrerId', rasterRumpf)(...werte, ziel, gezeigteId); }
  catch (e) { return { fehler: e.message }; }
  const reihe = ziel.kinder.map((k) => k.tag + ':' + k.text);
  return { aufrufe, reihe };
}
const eigen = raster(7, 7), fremd = raster(null, 7);
const nachRaster = (r) => !!r.reihe && r.reihe.findIndex((x) => x.startsWith('MARKE')) > r.reihe.findIndex((x) => x.startsWith('h3:Zeitraster'))
  && r.reihe.findIndex((x) => x.startsWith('h3:Zeitraster')) >= 0;
pruefe('unter dem Raster, genau einmal, mit S.svAbgesagt – in der eigenen Ansicht und in der Ansicht der Verwaltung',
  rasterRumpf !== '' && !eigen.fehler && !fremd.fehler
  && eigen.aufrufe.length === 1 && eigen.aufrufe[0] === eingabe && nachRaster(eigen)
  && fremd.aufrufe.length === 1 && fremd.aufrufe[0] === eingabe && nachRaster(fremd));

const ladeRumpf = rumpf('async function ladeSvRaster(');
async function lade(antwort) {
  const S = { aktiverSprechtag: { id: 1 }, svAbgesagt: 'ALT' };
  try {
    await new Function('S', 'api', 'meldung', 'zeichne', 'lehrerId',
      'return (async () => {' + ladeRumpf + '})();')(S, async () => antwort, () => {}, () => {}, 7);
  } catch (e) { return 'AUSNAHME ' + e.message; }
  return S.svAbgesagt;
}

(async () => {
  const mit = await lade({ raster: [], abgesagt: eingabe });
  const ohneFeld = await lade({ raster: [] });
  pruefe('ladeSvRaster übernimmt die abgesagten Termine aus /api/raster; fehlt das Feld, eine leere Liste',
    ladeRumpf !== '' && mit === eingabe && Array.isArray(ohneFeld) && ohneFeld.length === 0);

  // ------------------------------------------------------------
  console.log('Hilfe');
  const hilfe = rumpf('function ansichtHilfe(');
  // Der Absatz direkt nach der Überschrift, gleich in welchem Abschnitt –
  // nicht am Variablennamen (v0.9.89: Die Hilfe ist nach Rollen gegliedert).
  const ausfall = (hilfe.match(/'Krankheitsausfall'\)\);\s*\w+\.appendChild\(el\('p', null,([\s\S]*?)\)\);/) || [])[1] || '';
  pruefe('Krankheitsausfall: die Lehrkraft sieht die Termine weiter unter „Abgesagte Termine“',
    /sieht[\s\S]*„Abgesagte Termine“/.test(ausfall.replace(/'\s*\+\s*'/g, '')));

  console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
  process.exit(fehler === 0 ? 0 : 1);
})().catch((e) => {
  // Ein Abbruch meldet sich selbst als rote Zeile (E5), nicht nur über Node.
  console.log('  ✗ Suite abgebrochen: ' + e.message);
  process.exit(1);
});
