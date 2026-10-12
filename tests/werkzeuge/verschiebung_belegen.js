#!/usr/bin/env node
// verschiebung_belegen.js – Beleg, dass ein Text wörtlich auf eine oder
// mehrere Dateien verteilt wurde: nichts verloren, nichts doppelt, nichts
// verändert. Kein Teil des Testlaufs; ein Werkzeug für einen Umbau
// (README → docs/BETRIEB.md, 12.10.2026), damit der Beleg wiederholbar ist.
//
//   node tests/werkzeuge/verschiebung_belegen.js ALT ZUSAETZE NEU1 [NEU2 …]
//
// ALT       der Text vorher (z. B. aus `git show REV:README.md`)
// ZUSAETZE  die absichtlich neu geschriebenen Zeilen, eine je Zeile – alles,
//           was in NEU* steht und nicht aus ALT stammt
// NEU*      die Dateien danach
//
// Drei Sichten, jede für sich:
//   1. Zeilen:  jede nichtleere Zeile aus ALT steht in NEU* so oft wie in
//               ALT; was darüber hinaus steht, ist genau ZUSAETZE.
//   2. Absätze: jeder Absatz aus ALT (zwischen Leerzeilen) steht in NEU*
//               zusammenhängend und an Zeilengrenzen, so oft wie in ALT.
//   3. Sätze:   jeder Satz aus ALT (Absatz zu einer Zeile verbunden, getrennt
//               an . ! ? : vor Leerraum) steht in NEU* so oft wie in ALT.
// Ausgabe: je Sicht die Abweichungen und eine Zeile „… gleich“. Exit 1 bei
// jeder Abweichung, auch wenn ALT leer ist (null Zeilen sind kein Beleg).
'use strict';
const fs = require('fs');

const [alt, zus, ...neu] = process.argv.slice(2);
if (!alt || !zus || neu.length === 0) {
  console.error('Aufruf: verschiebung_belegen.js ALT ZUSAETZE NEU1 [NEU2 …]');
  process.exit(2);
}
const lies = (p) => fs.readFileSync(p, 'utf8').replace(/\r\n/g, '\n');
const altText = lies(alt);
const neuTexte = neu.map(lies);
const zusaetze = lies(zus).split('\n').filter((z) => z.trim() !== '');

let fehler = 0;
const melde = (s) => { fehler++; console.log('  ✗ ' + s); };

function zaehle(liste) {
  const m = new Map();
  for (const x of liste) m.set(x, (m.get(x) || 0) + 1);
  return m;
}
function vergleiche(name, altM, neuM) {
  const vorher = fehler;
  for (const [x, n] of altM) {
    const k = neuM.get(x) || 0;
    if (k !== n) melde(`${name}: ${k}× statt ${n}×: ${JSON.stringify(x).slice(0, 110)}`);
  }
  for (const [x, k] of neuM) {
    if (!altM.has(x)) melde(`${name}: neu, nicht aus ALT (${k}×): ${JSON.stringify(x).slice(0, 110)}`);
  }
  if (fehler === vorher) console.log(`  ✓ ${name}: gleich (${[...altM.values()].reduce((a, b) => a + b, 0)})`);
}

// 1. Zeilen
const nichtleer = (t) => t.split('\n').filter((z) => z.trim() !== '');
const altZeilen = nichtleer(altText);
if (altZeilen.length === 0) { console.log('  ✗ ALT ist leer – kein Beleg'); process.exit(1); }
const neuZeilen = neuTexte.flatMap(nichtleer);
const neuOhneZus = zaehle(neuZeilen);
for (const z of zusaetze) {
  const k = neuOhneZus.get(z) || 0;
  if (k === 0) melde(`Zusatz steht nirgends: ${JSON.stringify(z)}`);
  else if (k === 1) neuOhneZus.delete(z); else neuOhneZus.set(z, k - 1);
}
vergleiche('Zeilen', zaehle(altZeilen), neuOhneZus);

// 2. Absätze – zusammenhängend und an Zeilengrenzen
const absaetze = (t) => t.split(/\n[ \t]*\n/).map((a) => a.replace(/^\n+|\n+$/g, '')).filter((a) => a.trim() !== '');
function vorkommen(text, stueck) {
  let n = 0, i = -1;
  while ((i = text.indexOf(stueck, i + 1)) !== -1) {
    const anfang = i === 0 || text[i - 1] === '\n';
    const ende = i + stueck.length === text.length || text[i + stueck.length] === '\n';
    if (anfang && ende) n++;
  }
  return n;
}
{
  const vorher = fehler;
  const altA = zaehle(absaetze(altText));
  let summe = 0;
  for (const [a, n] of altA) {
    summe += n;
    const k = neuTexte.reduce((s, t) => s + vorkommen(t, a), 0);
    if (k !== n) melde(`Absatz ${k}× statt ${n}×: ${JSON.stringify(a.split('\n')[0]).slice(0, 110)}`);
  }
  if (fehler === vorher) console.log(`  ✓ Absätze: gleich (${summe})`);
}

// 3. Sätze
const saetze = (t) => absaetze(t)
  .map((a) => a.split('\n').map((z) => z.trim()).join(' '))
  .flatMap((a) => a.split(/(?<=[.!?:])\s+/))
  .map((s) => s.trim()).filter((s) => s !== '');
{
  const altS = zaehle(saetze(altText));
  const neuS = zaehle(neuTexte.flatMap(saetze));
  const vorher = fehler;
  let summe = 0;
  for (const [s, n] of altS) {
    summe += n;
    const k = neuS.get(s) || 0;
    if (k !== n) melde(`Satz ${k}× statt ${n}×: ${JSON.stringify(s).slice(0, 110)}`);
  }
  if (fehler === vorher) console.log(`  ✓ Sätze: gleich (${summe})`);
}

console.log(fehler === 0 ? 'Beleg: vollständig, nichts doppelt, nichts verändert.' : `Beleg: ${fehler} Abweichung(en).`);
process.exit(fehler === 0 ? 0 : 1);

// Beleg README → docs/BETRIEB.md (12.10.2026), wiederholbar mit:
//   git show 9a0102b:README.md > /tmp/alt.md
//   node tests/werkzeuge/verschiebung_belegen.js /tmp/alt.md \
//        tests/werkzeuge/readme_betrieb_zusaetze.txt README.md docs/BETRIEB.md
// Ergebnis damals: Zeilen 124, Absätze 34, Sätze 59, alle gleich.
