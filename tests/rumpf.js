// ============================================================
// tests/rumpf.js – gemeinsame Hilfe für die Frontend-Suiten
//
// rumpf(quelle, kopf) liefert den Rumpf der Funktion, deren Kopf `kopf`
// ist, ohne Kommentare. `kopf` muss genau einmal vorkommen; sonst kommt
// '' zurück und eine Meldung „(Voraussetzung: …)“ wird ausgegeben.
// Zeichenketten werden übersprungen, damit Klammern oder „//“ darin
// nicht mitzählen.
//
// WICHTIG für Abwesenheitsprüfungen („X kommt im Rumpf NICHT vor“):
// Ein leerer Rumpf besteht jede solche Prüfung. Dort immer zusätzlich
// `r !== ''` verlangen.
//
// Kein Suite-Muster (*_test.js), wird also nicht selbst ausgeführt.
// ============================================================
'use strict';

function rumpf(quelle, kopf) {
  const anzahl = quelle.split(kopf).length - 1;
  if (anzahl !== 1) {
    console.log('    (Voraussetzung: „' + kopf + '" kommt ' + anzahl + '-mal vor, erwartet 1)');
    return '';
  }
  const start = quelle.indexOf('{', quelle.indexOf(kopf) + kopf.length);
  let tiefe = 0, aus = '', k = start;
  while (k < quelle.length) {
    const c = quelle[k], n = quelle[k + 1];
    if (c === '/' && n === '/') { k = quelle.indexOf('\n', k); if (k < 0) break; continue; }
    if (c === '/' && n === '*') { const e = quelle.indexOf('*/', k + 2); if (e < 0) break; k = e + 2; continue; }
    if (c === "'" || c === '"' || c === '`') {
      let e = k + 1;
      while (e < quelle.length && quelle[e] !== c) { if (quelle[e] === '\\') e++; e++; }
      aus += quelle.slice(k, e + 1); k = e + 1; continue;
    }
    if (c === '{') tiefe++;
    if (c === '}') { tiefe--; if (tiefe === 0) return aus.slice(1); }
    aus += c; k++;
  }
  return '';
}

module.exports = { rumpf };
