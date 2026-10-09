// ============================================================
// tests/frontend_lehrer_zeilen_test.js
// Prüft v0.9.62: In „Lehrkräfte, Anwesenheit und Räume“ behält eine Zeile
// ihre Ausrichtung, auch wenn das Zeitfenster offen ist.
//
// Ursache (aus dem Code, gemessen mit tests/mobil-messung): Die Zeitfelder
// tragen .zeit-feld (5.5rem), aber die allgemeine Regel
// „input[type=text] { display: block; width: 100% }“ ist spezifischer und
// gewann – jedes Feld wurde ein Block über die ganze Zelle, beide standen
// untereinander. Die Zeile wurde 150 statt 45 px hoch, die Häkchen
// rutschten 59 px unter den Namen (WebKit, 1280 px).
//
// Was hier steht, misst Regeln und ihre Reihenfolge (die Kaskade entscheidet
// bei gleicher Spezifität nach der Reihenfolge). Die Wirkung misst
// tests/mobil-messung (Zeilenhöhe, Häkchen-Versatz) – im CHANGELOG.
//
// Aufruf: node tests/frontend_lehrer_zeilen_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const css = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8')
  .replace(/\/\*[\s\S]*?\*\//g, '');

// Regeln der obersten Ebene in Dateireihenfolge (Medienabfragen übersprungen).
function regeln(text) {
  const aus = [];
  let i = 0;
  while (i < text.length) {
    const auf = text.indexOf('{', i);
    if (auf < 0) break;
    const kopf = text.slice(i, auf).trim().replace(/\s+/g, ' ');
    let tiefe = 1, j = auf + 1;
    while (j < text.length && tiefe > 0) {
      if (text[j] === '{') tiefe++;
      else if (text[j] === '}') tiefe--;
      j++;
    }
    if (!kopf.startsWith('@')) aus.push({ kopf, inhalt: text.slice(auf + 1, j - 1) });
    i = j;
  }
  return aus;
}
function wert(inhalt, eig) {
  const re = new RegExp('(?:^|;)\\s*' + eig + '\\s*:\\s*([^;]+)', 'g');
  let m, letzt = null;
  while ((m = re.exec(inhalt)) !== null) letzt = m[1].trim();
  return letzt;
}
const R = regeln(css);
const idx = (pred) => R.findIndex(pred);

const iAllg = idx((r) => r.kopf.split(',').map((x) => x.trim()).includes('input[type=text]'));
const iFeld = idx((r) => r.kopf === 'input.zeit-feld');
pruefe('Voraussetzung: allgemeine Regel für input[type=text] gefunden', iAllg >= 0);
pruefe('Zeitfeld-Regel ist so spezifisch wie die allgemeine (input.zeit-feld) und steht DANACH',
  iFeld > iAllg && iAllg >= 0);
pruefe('… Zeitfeld bleibt in der Zeile und knapp (display inline-block, width 4.5rem)',
  iFeld >= 0 && wert(R[iFeld].inhalt, 'display') === 'inline-block'
  && wert(R[iFeld].inhalt, 'width') === '4.5rem');
// Die Fassung, die verlor: .zeit-feld allein mit einer Breite.
pruefe('keine Breite mehr an „.zeit-feld“ allein (verlor gegen input[type=text])',
  !R.some((r) => r.kopf === '.zeit-feld' && wert(r.inhalt, 'width') !== null));
const iFelder = idx((r) => r.kopf === '.zeitfenster-felder');
pruefe('Zeitfelder stehen neben dem Uhr-Knopf (.zeitfenster-felder: inline-flex)',
  iFelder >= 0 && wert(R[iFelder].inhalt, 'display') === 'inline-flex');
pruefe('… ausgeblendet bleibt ausgeblendet (.zeitfenster-felder.versteckt: none, spezifischer)',
  R.some((r) => r.kopf === '.zeitfenster-felder.versteckt' && wert(r.inhalt, 'display') === 'none'));

// Die Zelle bricht nicht um: eigene Klasse am <td> der Anwesenheit, und die
// Regel dazu. Gesucht im Rumpf von anwesenheitZelle(), nicht in der Datei.
const js = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const zRumpf = require('./rumpf.js').rumpf(js, 'function anwesenheitZelle(');
pruefe('Anwesenheitszelle trägt ihre Klasse (anwesenheitZelle(): el(\'td\', \'anwesenheit-zelle\'))',
  /const td = el\('td', 'anwesenheit-zelle'\);/.test(zRumpf));
pruefe('… und bricht nicht um (.anwesenheit-zelle: white-space nowrap)',
  R.some((r) => r.kopf === '.anwesenheit-zelle' && wert(r.inhalt, 'white-space') === 'nowrap'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
