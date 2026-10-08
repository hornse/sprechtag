// ============================================================
// tests/frontend_einladung_kachel_test.js
// Prüft v0.9.53: Eingeladene Lehrkräfte erscheinen als Kachel und sind
// als solche erkennbar (Befund 08.10.2026, Einladungs-Kachel; E10).
//
// Ausgeführt, nicht gesucht: buchenLehrerAlle() und zeichneBuchenKacheln()
// laufen mit einem knappen Ersatz für el() und den Zustand. Der Ersatz
// zeichnet nur auf, was angelegt wird – er legt nichts selbst an.
//
// Aufruf: node tests/frontend_einladung_kachel_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const js = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

const alleRumpf    = rumpf('function buchenLehrerAlle(');
const kachelRumpf  = rumpf('function zeichneBuchenKacheln(');
const ansichtRumpf = rumpf('function ansichtBuchen(');
const ladeRumpf    = rumpf('async function ladeLehrerListe(');

// ---- Ersatz: el() legt ein Aufzeichnungsobjekt an -------------
function el(tag, klasse, text) {
  return {
    tag, klasse: klasse || '', text: text || '', kinder: [],
    appendChild(k) { this.kinder.push(k); return k; },
    addEventListener() {},
    set textContent(v) { this.kinder = []; },
  };
}
const texte = (knoten) => [knoten.text].concat(...knoten.kinder.map(texte));

let buchenLehrerAlle = () => { throw new Error('Rumpf fehlt'); };
let zeichneBuchenKacheln = buchenLehrerAlle;
if (alleRumpf !== '') buchenLehrerAlle = new Function('liste', alleRumpf);
if (kachelRumpf !== '') {
  // Seit v0.9.57 mit drittem Parameter `suche` (Gruppe 3); hier ohne ihn.
  zeichneBuchenKacheln = (S, gitter, alle) => new Function(
    'S', 'el', 'anzeigeZeit', 'ladeRaster', 'gitter', 'alle', 'suche', kachelRumpf)(
    S, el, () => '', () => {}, gitter, alle, undefined);
}

// ---- Daten: Form wie GET /api/buchbare-lehrer (Zahlen als Text, wie PDO/MariaDB)
const einl = { lehrer_id: '1', kuerzel: 'Ei', name: 'Eins', faecher: '', stunden: '0',
  klausuren: '0', raum_kuerzel: 'A101', rolle: null, eingeladen: '1' };
const unt  = { lehrer_id: '2', kuerzel: 'Un', name: 'Zwei', faecher: 'M', stunden: '4',
  klausuren: '0', raum_kuerzel: null, rolle: null };
const son  = { lehrer_id: '3', kuerzel: 'So', name: 'Drei', faecher: '', stunden: '0',
  klausuren: '0', raum_kuerzel: null, rolle: 'Beratung' };

console.log('buchenLehrerAlle – Eingeladene zuerst');
let alle = [];
try { alle = buchenLehrerAlle({ eingeladen: [einl], unterrichtend: [unt], sonderlehrer: [son] }); }
catch (f) { console.log('    (' + f.message + ')'); }
pruefe('Reihenfolge: eingeladen, unterrichtend, Sonderrolle',
  alle.map((l) => l.kuerzel).join(',') === 'Ei,Un,So');
let ohne = null;
try { ohne = buchenLehrerAlle({ unterrichtend: [unt], sonderlehrer: [son] }); }
catch (f) { console.log('    (' + f.message + ')'); }
pruefe('fehlende Liste „eingeladen“ gilt als leer',
  Array.isArray(ohne) && ohne.map((l) => l.kuerzel).join(',') === 'Un,So');
let nurEin = null;
try { nurEin = buchenLehrerAlle({ eingeladen: [einl], unterrichtend: [], sonderlehrer: [],
  nur_eingeladene: true }); }
catch (f) { console.log('    (' + f.message + ')'); }
pruefe('Phase 1: genau die Eingeladene erscheint',
  Array.isArray(nurEin) && nurEin.length === 1 && nurEin[0].kuerzel === 'Ei');

console.log('zeichneBuchenKacheln – Einladung ist erkennbar');
const gitter = el('div', 'buchen-gitter');
try { zeichneBuchenKacheln({ buchenSuche: '', gewaehlteLehrkraft: null }, gitter, alle); }
catch (f) { console.log('    (' + f.message + ')'); }
const karten = gitter.kinder.filter((k) => k.klasse.split(' ').includes('buchen-kachel'));
pruefe('drei Kacheln gezeichnet', karten.length === 3);
const zaehle = (karte) => texte(karte).filter((t) => t === 'hat Sie eingeladen').length;
pruefe('Kachel der Eingeladenen trägt „hat Sie eingeladen“ genau einmal',
  karten.length === 3 && zaehle(karten[0]) === 1);
pruefe('Kacheln der anderen tragen es nicht',
  karten.length === 3 && zaehle(karten[1]) === 0 && zaehle(karten[2]) === 0);
pruefe('Kachel der Eingeladenen zeigt ihren Namen',
  karten.length === 3 && texte(karten[0]).includes('Eins'));

console.log('Aufrufstellen');
pruefe('ansichtBuchen() bildet die Liste über buchenLehrerAlle()',
  ansichtRumpf !== '' && /const alle = buchenLehrerAlle\(S\.lehrerListe\)/.test(ansichtRumpf));
pruefe('ansichtBuchen() setzt die Liste nicht mehr selbst zusammen',
  ansichtRumpf !== '' && !/S\.lehrerListe\.unterrichtend/.test(ansichtRumpf));
pruefe('leere Liste in Phase 1 erklärt die Phase',
  ansichtRumpf !== '' && /S\.lehrerListe\.nur_eingeladene/.test(ansichtRumpf)
  && ansichtRumpf.includes('Für dieses Kind liegt keine Einladung vor.'));
pruefe('„keine Lehrkräfte hinterlegt“ nicht in Phase 1',
  ladeRumpf !== '' && /!S\.lehrerListe\.nur_eingeladene\s*&&/.test(ladeRumpf));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
