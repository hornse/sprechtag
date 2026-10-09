// ============================================================
// tests/frontend_abstaende_test.js
// Prüft v0.9.63: Abstände zwischen Abschnitten – zwei Werte an einer
// Stelle (Entscheidung Betreiber), keine verteilten Einzelwerte.
//
//   --abstand-abschnitt (2rem, am Gerät bewährt bei den Sonderrollen):
//     zwischen Abschnitten – auch vor einem Knopf unter einer Liste und
//     zwischen den Sektionen der Verwaltung.
//   --abstand-innen (.8rem): innerhalb eines Abschnitts – nach einer
//     Überschrift, und zwischen gleichartigen Blöcken (FAQ, Klassenlisten).
//
// Bis v0.9.62 brachte jeder Baustein seinen eigenen Abstand mit, ein Block
// oben gar keinen, ein frei stehender Knopf keinen: gemessen 0 px zwischen
// Kachelgruppe und „Weitere Lehrkräfte“ und zwischen „Aktualisieren“ und
// dem folgenden Block, 13 px zwischen Tabelle und „Aktualisieren“.
//
// Was hier steht, misst Regeln, ihre Reihenfolge und dass es keine
// Einzelwerte mehr gibt. Die Wirkung (Fugen an der sichtbaren Kante) misst
// tests/mobil-messung – im CHANGELOG, nicht als grüne Prüfung.
//
// Aufruf: node tests/frontend_abstaende_test.js
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

// Alle Regeln in Dateireihenfolge, auch aus Medienabfragen (mit Kennung).
function regeln(text, medien) {
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
    const inhalt = text.slice(auf + 1, j - 1);
    if (kopf.startsWith('@media')) aus.push(...regeln(inhalt, kopf));
    else if (!kopf.startsWith('@')) aus.push({ kopf, inhalt, medien: medien || null });
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
const wurzel = R.filter((r) => r.kopf === ':root' && !r.medien).map((r) => r.inhalt).join(';');

pruefe('zwei Werte an einer Stelle: --abstand-abschnitt 2rem, --abstand-innen .8rem (:root)',
  wert(wurzel, '--abstand-abschnitt') === '2rem' && wert(wurzel, '--abstand-innen') === '.8rem');

const nutztAbschnitt = R.filter((r) => /var\(--abstand-abschnitt\)/.test(r.inhalt));
pruefe('genau eine Regel setzt den Abstand zwischen Abschnitten (' + nutztAbschnitt.length + ')',
  nutztAbschnitt.length === 1);
const A = nutztAbschnitt[0] || { kopf: '', inhalt: '' };
const iA = R.indexOf(A);
const BEHAELTER = ['#ansicht', '.sektion', 'details.block'];
const ABSCHNITTE = ['.block', '.sektion', '.buchen-gitter', '.tabelle-rahmen', '.raster', '.aktionen', 'h3', 'button'];
const teile = (k) => (k.match(/:where\(([^)]*)\)/g) || []).map((w) => w.slice(7, -1).split(',').map((x) => x.trim()));
const [aBeh, aAbs] = teile(A.kopf);
pruefe('… sie gilt in Ansicht, Sektion und Block für Blöcke, Sektionen, Gitter, Tabellen, Raster, Knopfzeilen, Überschriften und frei stehende Knöpfe',
  !!aBeh && !!aAbs && BEHAELTER.every((b) => aBeh.includes(b)) && ABSCHNITTE.every((s) => aAbs.includes(s))
  && wert(A.inhalt, 'margin-top') === 'var(--abstand-abschnitt)' && /:not\(:first-child\)/.test(A.kopf));
// :where() hat keine Spezifität – die Ausnahmen gewinnen durch Reihenfolge
// bzw. eigene Klassen, nicht durch eine #id im Selektor.
pruefe('… ohne Spezifität aus Behälter oder Abschnitt (alles in :where())',
  A.kopf !== '' && A.kopf.replace(/:where\([^)]*\)/g, '').replace(/:not\(:first-child\)/, '').replace(/[\s>]/g, '') === '');

const nachUeber = R.find((r, i) => i > iA && /var\(--abstand-innen\)/.test(r.inhalt) && /h3/.test(r.kopf) && /\+/.test(r.kopf));
pruefe('nach einer Überschrift der kleinere Wert – Regel steht NACH der Abschnittsregel (gleiche Spezifität)',
  !!nachUeber && wert(nachUeber.inhalt, 'margin-top') === 'var(--abstand-innen)'
  && /:not\(:first-child\)/.test(nachUeber.kopf));
// Im Block polstert der Block selbst (padding-top 1rem nach der
// Zusammenfassung); dort kein zusätzlicher Außenabstand.
const nachSummary = R.find((r) => r.kopf === '.block > summary + *');
pruefe('nach der Titelzeile eines Blocks trägt die Polsterung, kein Außenabstand (margin-top 0)',
  !!nachSummary && wert(nachSummary.inhalt, 'margin-top') === '0' && wert(nachSummary.inhalt, 'padding-top') === '1rem');
const liste = R.find((r) => r.kopf === '.block + .block');
pruefe('gleichartige Blöcke bleiben eine Liste (.block + .block: --abstand-innen)',
  !!liste && wert(liste.inhalt, 'margin-top') === 'var(--abstand-innen)');

// Keine verteilten Einzelwerte: Die Grundregeln der Bausteine tragen
// keinen eigenen Außenabstand mehr (außer 0), die Sonderregel der
// Sonderrollen ist entfallen.
const BAUSTEINE = ['.block', '.sektion', '.buchen-gitter', '.tabelle-rahmen', '.tabelle', '.aktionen', '.raster'];
const mitRand = BAUSTEINE.filter((b) => R.some((r) => r.kopf === b
  && ['margin', 'margin-top', 'margin-bottom'].some((e) => { const v = wert(r.inhalt, e); return v !== null && !/^0(\s+0)*$/.test(v); })));
pruefe('keine Bausteine mit eigenem Außenabstand (' + (mitRand.join(', ') || 'keine') + ')', mitRand.length === 0);
pruefe('keine Sonderregel mehr für die Sonderrollen (.buchen-sonderrollen)',
  !R.some((r) => r.kopf === '.buchen-sonderrollen'));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
