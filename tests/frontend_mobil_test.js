// ============================================================
// tests/frontend_mobil_test.js
// Prüft v0.9.58 (Zug 3b): mobile Ansicht, schmaler Bildschirm.
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
// Gezählt wird ohne die Definition – sie enthält selbst „appendChild(tab)“.
const jsOhne = js.replace('function tabelleRahmen(tab) {' + rahmenRumpf + '}', '');
const nTabellen = (jsOhne.match(/el\('table'/g) || []).length;
const nRahmen   = (jsOhne.match(/tabelleRahmen\(\w*tab\)/g) || []).length;
pruefe('jede Tabelle bekommt ihren Rahmen (' + nTabellen + ' Tabellen, ' + nRahmen + ' Rahmen)',
  nTabellen >= 6 && nRahmen === nTabellen);
pruefe('keine Tabelle wird ohne Rahmen eingehängt (kein appendChild(tab) / return tab)',
  rahmenRumpf !== '' && jsOhne !== js && !/appendChild\(\w*tab\)|return \w*tab;/.test(jsOhne));
pruefe('Rahmen rollt waagrecht', wert(regel('.tabelle-rahmen'), 'overflow-x') === 'auto');
pruefe('Inhalt wächst nicht mit der breitesten Tabelle mit (main: min-width 0)',
  wert(regel('main'), 'min-width') === '0');

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
