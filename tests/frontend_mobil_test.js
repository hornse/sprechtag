// ============================================================
// tests/frontend_mobil_test.js
// Prüft v0.9.58 (Zug 3b) und v0.9.59: mobile Ansicht, schmaler Bildschirm.
//
//   1. Seitenmenü überlagert und schließt beim Tippen daneben
//   2. Tabellen laufen nicht über den Rand, sie rollen in einem Rahmen
//   3. Safaris Leiste unten und die Ränder im Querformat
//
// Was hier steht, misst den QUELLTEXT und Regeln, nicht das Ergebnis auf
// dem Gerät. Ob es auf dem iPhone passt, zeigt nur das Gerät – das steht
// als Abnahmepunkt im CHANGELOG, nicht als grüne Prüfung hier.
//
// CSS wird regelweise gelesen (Selektor → Deklarationen), Kommentare
// vorher entfernt: Eine Prüfung darf nicht auf ihre Beschreibung
// anschlagen und nicht auf dieselbe Eigenschaft in einer anderen Regel.
//
// Aufruf: node tests/frontend_mobil_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const js   = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const css  = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8')
  .replace(/\/\*[\s\S]*?\*\//g, '');
const html = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'index.html'), 'utf8');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

// ---- CSS regelweise -----------------------------------------------
// Liefert die Deklarationen aller Regeln mit genau diesem Selektor –
// außerhalb jeder Medienabfrage (medien = null) oder in der genannten.
function bloecke(text) {
  const aus = [];
  let i = 0;
  while (i < text.length) {
    const auf = text.indexOf('{', i);
    if (auf < 0) break;
    const kopf = text.slice(i, auf).trim();
    let tiefe = 1, j = auf + 1;
    while (j < text.length && tiefe > 0) {
      if (text[j] === '{') tiefe++;
      else if (text[j] === '}') tiefe--;
      j++;
    }
    aus.push({ kopf, inhalt: text.slice(auf + 1, j - 1) });
    i = j;
  }
  return aus;
}
const norm = (s) => s.replace(/\s+/g, ' ').trim();
function regel(selektor, medien) {
  let ebene = bloecke(css);
  if (medien) {
    ebene = ebene.filter((b) => norm(b.kopf) === medien).flatMap((b) => bloecke(b.inhalt));
  } else {
    ebene = ebene.filter((b) => !b.kopf.startsWith('@'));
  }
  return ebene.filter((b) => norm(b.kopf) === selektor).map((b) => b.inhalt).join(';');
}
// Wert der LETZTEN Deklaration einer Eigenschaft in einer Regel (die gilt).
function wert(deklarationen, eigenschaft) {
  const re = new RegExp('(?:^|;)\\s*' + eigenschaft + '\\s*:\\s*([^;]+)', 'g');
  let m, letzt = null;
  while ((m = re.exec(deklarationen)) !== null) letzt = m[1].trim();
  return letzt;
}
const zahl = (s) => (s === null ? NaN : parseInt(s.replace('!important', ''), 10));
const MOBIL = '@media (max-width: 760px)';

// Voraussetzung: Die Medienabfrage ist da, sonst prüft alles Folgende nichts.
pruefe('Voraussetzung: Medienabfrage für schmale Bildschirme gefunden',
  bloecke(css).some((b) => norm(b.kopf) === MOBIL));

// ------------------------------------------------------------
console.log('1. Seitenmenü: überlagern, Tippen daneben schließt');
const zKopf    = zahl(wert(regel('.mobil-leiste'), 'z-index'));
const zSchleier = zahl(wert(regel('.menue-overlay'), 'z-index'));
const zLeiste  = zahl(wert(regel('.seitenleiste', MOBIL), 'z-index'));
pruefe('Schleier liegt über der Kopfleiste – auch dort schließt ein Tippen',
  zSchleier > zKopf);
pruefe('Menü liegt über dem Schleier', zLeiste > zSchleier);
const breite   = wert(regel('.seitenleiste', MOBIL), 'width') || '';
const breiteZu = wert(regel('.shell.leiste-zu .seitenleiste', MOBIL), 'width') || '';
pruefe('Menübreite begrenzt, damit daneben Platz zum Tippen bleibt (min(…, 85vw))',
  /^min\(.*85vw\)$/.test(breite));
// Die Grundregel .shell.leiste-zu .seitenleiste (58px) ist spezifischer als
// .seitenleiste in der Medienabfrage – ohne eigene Regel dort gälte am
// Telefon die schmale Symbolleiste, sobald am Rechner eingeklappt war.
pruefe('… auch nach Einklappen am Rechner (gemerkter Zustand gilt am Telefon mit)',
  wert(regel('.shell.leiste-zu .seitenleiste'), 'width') !== null && breiteZu === breite);
// Vermutung, auf dem Gerät nicht belegt: Safari auf iOS schickt ein click
// an ein Element ohne Zeiger-Cursor unter Umständen nicht ab.
pruefe('Schleier trägt cursor: pointer (Safari-Antippbarkeit, Vermutung)',
  wert(regel('.menue-overlay'), 'cursor') === 'pointer');
const hoehen = [...regel('.seitenleiste', MOBIL).matchAll(/(?:^|;)\s*height\s*:\s*([^;]+)/g)]
  .map((m) => m[1].trim());
pruefe('Menühöhe folgt der sichtbaren Höhe: 100vh, danach 100dvh',
  hoehen.length === 2 && hoehen[0] === '100vh' && hoehen[1] === '100dvh');
pruefe('Tippen auf den Schleier ruft menueSchliessen() (Quelltext)',
  /\$\('#menue-overlay'\)\?\.addEventListener\('click', \(\) => menueSchliessen\(\)\);/.test(js));

// ------------------------------------------------------------
console.log('2. Tabellen rollen in einem Rahmen');
const rahmenRumpf = rumpf('function tabelleRahmen(');
let tabelleRahmen = () => null;
if (rahmenRumpf !== '') {
  const el = (tag, klasse) => ({ tag, klasse: klasse || '', kinder: [],
    appendChild(k) { this.kinder.push(k); return k; } });
  tabelleRahmen = (tab) => new Function('el', 'tab', rahmenRumpf)(el, tab);
}
const t = { tag: 'table', klasse: 'tabelle', kinder: [] };
const r = (() => { try { return tabelleRahmen(t); } catch (e) { return null; } })();
pruefe('tabelleRahmen() legt die Tabelle als einziges Kind in div.tabelle-rahmen',
  !!r && r.tag === 'div' && r.klasse === 'tabelle-rahmen' && r.kinder.length === 1 && r.kinder[0] === t);
// Gezählt wird ohne die Definitionen – tabelleRahmen() enthält selbst
// „appendChild(tab)“, kartenTabelle() selbst „tabelleRahmen(tab)“.
// Entfernt wird roh bis zur passenden Klammer (Zeichenketten und
// Kommentare übersprungen), nicht über den kommentarfreien Rumpf.
function ohneDefinition(text, kopf) {
  const a = text.indexOf(kopf);
  if (a < 0 || text.indexOf(kopf, a + 1) >= 0) return text;
  let k = text.indexOf('{', a + kopf.length), tiefe = 0;
  while (k < text.length) {
    const c = text[k], n = text[k + 1];
    if (c === '/' && n === '/') { k = text.indexOf('\n', k); continue; }
    if (c === '/' && n === '*') { k = text.indexOf('*/', k + 2) + 2; continue; }
    if (c === "'" || c === '"' || c === '`') {
      let e = k + 1;
      while (e < text.length && text[e] !== c) { if (text[e] === '\\') e++; e++; }
      k = e + 1; continue;
    }
    if (c === '{') tiefe++;
    if (c === '}' && --tiefe === 0) return text.slice(0, a) + text.slice(k + 1);
    k++;
  }
  return text;
}
const jsOhne = ohneDefinition(ohneDefinition(js, 'function tabelleRahmen('), 'function kartenTabelle(');
const nTabellen = (jsOhne.match(/el\('table'/g) || []).length;
const nRahmen   = (jsOhne.match(/(?:tabelleRahmen|kartenTabelle)\(\w*tab\)/g) || []).length;
pruefe('jede Tabelle bekommt ihren Rahmen (' + nTabellen + ' Tabellen, ' + nRahmen + ' Rahmen)',
  nTabellen >= 6 && nRahmen === nTabellen);
pruefe('keine Tabelle wird ohne Rahmen eingehängt (kein appendChild(tab) / return tab)',
  rahmenRumpf !== '' && jsOhne !== js && !/appendChild\(\w*tab\)|return \w*tab;/.test(jsOhne));
pruefe('Rahmen rollt waagrecht', wert(regel('.tabelle-rahmen'), 'overflow-x') === 'auto');
pruefe('Inhalt wächst nicht mit der breitesten Tabelle mit (main: min-width 0)',
  wert(regel('main'), 'min-width') === '0');

// ------------------------------------------------------------
// v0.9.59: In WebKit zählt der Text der längsten Option eines <select> zur
// Breite seines Inhalts, auch wenn das Feld selbst schmal ist; über die
// Mindestbreite seines Flex-Elterns (label in .zeile) machte er die ganze
// Seite 590 px breit (gemessen, WebKit iPhone 13, tests/mobil-messung).
// Die Regel steht am select selbst – eine Stelle für jedes Auswahlfeld,
// gleich in welchem Behälter.
console.log('2b. Grundbreite: kein Auswahlfeld macht die Seite breiter');
pruefe('select schneidet seinen Inhalt ab (overflow: hidden in der Grundregel)',
  wert(regel('select'), 'overflow') === 'hidden');

// ------------------------------------------------------------
// v0.9.60: gemessen übergelaufen (WebKit, tests/mobil-messung). Die
// Knopfzeile .aktionen ragte bei 390 px in „Aktiver Sprechtag“ und
// „Sprechtage“ 262 px über – abgeschnitten vom Block (overflow: hidden),
// deshalb nicht als Seitenbreite sichtbar –, im Login-Protokoll bei 320 px
// über die Seite. Das Dateifeld bei 320 px, die Schülerliste bei 320 px
// um 67 px (abgeschnitten).
console.log('2c. Gemessene Überläufe');
pruefe('Knopfzeile bricht um (.aktionen: flex-wrap: wrap)',
  wert(regel('.aktionen'), 'flex-wrap') === 'wrap');
pruefe('Dateifeld nie breiter als sein Platz (input[type=file]: max-width: 100%)',
  wert(regel('input[type=file]'), 'max-width') === '100%');
pruefe('Schülerliste: Spaltenmindestbreite nie über dem Platz (minmax(min(15rem, 100%), 1fr))',
  (wert(regel('.schueler-liste'), 'grid-template-columns') || '').replace(/\s+/g, ' ')
    === 'repeat(auto-fill, minmax(min(15rem, 100%), 1fr))');

// ------------------------------------------------------------
// v0.9.60 (Entscheidung Betreiber): Die vier zu breiten Tabellen werden auf
// schmalem Bildschirm zu Karten – je Zeile ein Block, die Überschriften
// als Beschriftung darin. Seitliches Rollen bleibt Rückfall für die übrigen.
// Umgeschaltet wird an DERSELBEN Grenze wie die Telefonansicht (760 px),
// allein im CSS: dieselbe Tabelle, eine Darstellung je Breite.
console.log('2d. Karten statt Rollen');
const kartenRumpf = rumpf('function kartenTabelle(');
// Ersatz-DOM, nur so großzügig wie nötig: Attribute und Klassen werden
// gespeichert, nichts wird selbst angelegt.
function knoten(tag, kinder) {
  const k = { tag, kinder: kinder || [], attr: {}, klassen: [],
    setAttribute(n, v) { this.attr[n] = String(v); },
    classList: { add: (...c) => { k.klassen.push(...c); } },
    appendChild(x) { this.kinder.push(x); return x; },
    get children() { return this.kinder; },
    querySelectorAll(sel) { return sel === 'tr' ? this.kinder.filter((x) => x.tag === 'tr') : []; } };
  return k;
}
const kTab = knoten('table', [
  knoten('tr', [knoten('th'), knoten('th'), knoten('th')]),
  knoten('tr', [knoten('td'), knoten('td'), knoten('td')]),
  knoten('tr', [knoten('td'), knoten('td'), knoten('td')]),
]);
['Zeit', 'Lehrkraft', ''].forEach((t, i) => { kTab.kinder[0].kinder[i].textContent = t; });
let kErg = null;
try {
  const el = (tag, klasse) => { const k = knoten(tag); k.klasse = klasse || ''; return k; };
  const rahmen = (tab) => new Function('el', 'tab', rahmenRumpf)(el, tab);
  kErg = new Function('el', 'tabelleRahmen', 'tab', kartenRumpf)(el, rahmen, kTab);
} catch (e) { kErg = null; }
const zeilen = kTab.kinder.slice(1);
pruefe('kartenTabelle(): jede Zelle trägt die Überschrift ihrer Spalte als data-label',
  kartenRumpf !== '' && zeilen.every((z) => z.kinder[0].attr['data-label'] === 'Zeit'
    && z.kinder[1].attr['data-label'] === 'Lehrkraft'));
pruefe('… die Spalte ohne Überschrift (Knöpfe) wird Fußzeile der Karte (karte-aktion)',
  kartenRumpf !== '' && zeilen.every((z) => z.kinder[2].klassen.includes('karte-aktion')
    && !z.kinder[0].klassen.includes('karte-aktion')));
pruefe('… Kopfzeile gekennzeichnet, Tabelle als „karten“, im rollenden Rahmen (Rückfall)',
  !!kErg && kErg.klasse === 'tabelle-rahmen' && kErg.kinder[0] === kTab
  && kTab.klassen.includes('karten') && kTab.kinder[0].klassen.includes('kopfzeile'));
const KARTEN_ANSICHTEN = ['function ansichtMeineTermine(', 'function ansichtEinladungen(',
  'function ansichtAdminLoginLog(', 'function ansichtMitteilungen('];
const jeAnsicht = KARTEN_ANSICHTEN.map((k) => (rumpf(k).match(/kartenTabelle\(\w*tab\)/g) || []).length);
const kartenGesamt = (jsOhne.match(/kartenTabelle\(/g) || []).length;
pruefe('Karten genau in Meine Termine, Einladungen, Login-Protokoll, Mitteilungen ('
  + jeAnsicht.join('/') + ', gesamt ' + kartenGesamt + ')',
  jeAnsicht.every((n) => n === 1) && kartenGesamt === 4);
pruefe('schmal: jede Zeile ein Block (.tabelle.karten tr: display block)',
  wert(regel('.tabelle.karten tr', MOBIL), 'display') === 'block');
pruefe('schmal: Kopfzeile ausgeblendet (.tabelle.karten tr.kopfzeile: display none)',
  wert(regel('.tabelle.karten tr.kopfzeile', MOBIL), 'display') === 'none');
pruefe('schmal: Beschriftung aus data-label vor dem Wert',
  wert(regel('.tabelle.karten td::before', MOBIL), 'content') === 'attr(data-label)'
  && wert(regel('.tabelle.karten td.karte-aktion::before', MOBIL), 'content') === 'none');
// Eine Schwelle: Außerhalb der Medienabfrage der Telefonansicht steht keine
// Regel für .karten – sonst entschieden zwei Stellen dieselbe Frage.
const kartenAussen = bloecke(css).filter((b) => norm(b.kopf) !== MOBIL)
  .flatMap((b) => (b.kopf.startsWith('@') ? bloecke(b.inhalt).map((x) => b.kopf + ' ' + x.kopf) : [b.kopf]))
  .filter((k) => /\.karten\b/.test(k));
pruefe('Kartenregeln nur in der Medienabfrage der Telefonansicht (' + kartenAussen.length + ' außerhalb)',
  kartenAussen.length === 0 && /\.karten\b/.test(css));

// ------------------------------------------------------------
console.log('3. Safari unten und Ränder im Querformat');
pruefe('viewport-fit=cover – ohne das ist env(safe-area-inset-*) immer 0',
  /<meta name="viewport" content="[^"]*viewport-fit=cover[^"]*">/.test(html));
const mainMobil = regel('main', MOBIL);
pruefe('Inhalt hat unten Abstand für Safaris Leiste (env(safe-area-inset-bottom))',
  /env\(safe-area-inset-bottom/.test(wert(mainMobil, 'padding-bottom') || ''));
pruefe('… und links/rechts für die Kamera-Aussparung im Querformat',
  /env\(safe-area-inset-left/.test(wert(mainMobil, 'padding-left') || '')
  && /env\(safe-area-inset-right/.test(wert(mainMobil, 'padding-right') || ''));
pruefe('Menü unten (Abmelden) nicht unter der Leiste',
  /env\(safe-area-inset-bottom/.test(wert(regel('.seitenleiste', MOBIL), 'padding-bottom') || ''));
pruefe('Kopfleiste links im Querformat nicht unter der Aussparung',
  /env\(safe-area-inset-left/.test(wert(regel('.mobil-leiste', MOBIL), 'padding-left') || ''));
pruefe('Kurzmeldung unten über der Leiste',
  /env\(safe-area-inset-bottom/.test(wert(regel('.toast'), 'bottom') || ''));
// Ältere Browser kennen env() nicht und verwerfen die GANZE Deklaration.
// Deshalb steht vor jeder env()-Deklaration dieselbe Eigenschaft ohne env().
const envOhneRueckfall = bloecke(css).flatMap((b) => (b.kopf.startsWith('@') ? bloecke(b.inhalt) : [b]))
  .flatMap((b) => {
    const dekl = b.inhalt.split(';').map((d) => d.trim()).filter(Boolean);
    return dekl.map((d, i) => ({ d, i, dekl, kopf: b.kopf })).filter((x) => /env\(/.test(x.d))
      .filter((x) => {
        const eig = x.d.split(':')[0].trim();
        return !x.dekl.slice(0, x.i).some((v) => v.split(':')[0].trim() === eig && !/env\(/.test(v));
      });
  });
pruefe('jede env()-Deklaration hat davor einen Rückfall ohne env() (' + envOhneRueckfall.length + ' ohne)',
  envOhneRueckfall.length === 0 && /env\(/.test(css));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
