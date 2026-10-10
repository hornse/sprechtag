// ============================================================
// tests/frontend_kind_suche_test.js
// Prüft v0.9.81 (Zug 4, Schritt 3; E20 A/D/E, R1–R4): Einladungsauswahl
// und Kind-Suche beim stellvertretenden Buchen lesen /api/kinder (pageconfig
// über die Sitzung). Ohne Suchbegriff wird nichts geladen (R1), gesucht
// wird über Knopf und Eingabetaste (R3), eine nicht erreichbare Liste
// erscheint nie still leer, die freie Eingabe einer Schüler-ID entfällt
// (E20 D). Abgelaufene Sitzung: Kasten „Anmelden und suchen“.
// Ausgeführt, wo es geht; Quelltext, wo es um Aufrufstellen geht.
// Aufruf: node tests/frontend_kind_suche_test.js
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
const AsyncFunction = Object.getPrototypeOf(async function () {}).constructor;

function el(tag, klasse, text) {
  return { tag, klasse: klasse || '', text: text === undefined ? '' : String(text), kinder: [], attr: {},
    appendChild(k) { this.kinder.push(k); return k; },
    setAttribute(n, v) { this.attr[n] = v; } };
}
const alle = (k) => [k].concat(...k.kinder.map(alle));
const text = (k) => (k ? alle(k).map((x) => x.text).join(' ') : '');

// ------------------------------------------------------------
console.log('kinderSuchen: eine Quelle, ohne Suchbegriff kein Abruf (R1)');
const rS = rumpf('async function kinderSuchen(q)');
pruefe('Voraussetzung: Rumpf kinderSuchen gefunden', rS !== '');
const lauf = async (q) => {
  const urls = [];
  const api = async (u) => { urls.push(u); return { kinder: [{ id: 1, name: 'A, B', klasse: '5a' }], anzahl: 1, grenze: 60 }; };
  let d = null;
  try { d = await new AsyncFunction('q', 'api', rS)(q, api); } catch (e) { console.log('    (' + e.message + ')'); }
  return { urls, d };
};
(async () => {
  const a = await lauf('5a ä');
  pruefe('fragt /api/kinder?suche=… (kodiert)', a.urls.length === 1 && a.urls[0] === '/api/kinder?suche=' + encodeURIComponent('5a ä'));
  const b = await lauf('   ');
  pruefe('leerer Suchbegriff: kein Abruf, leere Treffer', b.urls.length === 0 && b.d && Array.isArray(b.d.kinder) && b.d.kinder.length === 0);

  // ------------------------------------------------------------
  console.log('Anzeige: Gruppen, Hinweise, nie still leer');
  const rG = rumpf('function kinderNachKlasse(kinder)');
  pruefe('Voraussetzung: Rumpf kinderNachKlasse gefunden', rG !== '');
  let g = [];
  try {
    g = new Function('kinder', rG)([{ id: 1, name: 'A', klasse: '' }, { id: 2, name: 'B', klasse: '5a' },
      { id: 3, name: 'C', klasse: '5a' }, { id: 4, name: 'D', klasse: '10b' }]) || [];
  } catch (e) { console.log('    (' + e.message + ')'); }
  pruefe('gruppiert nach Klasse in der gelieferten Reihenfolge (keine Objektschlüssel, die „10“ vorziehen)',
    JSON.stringify(g.map((x) => [x[0], x[1].map((k) => k.id)]))
    === JSON.stringify([['(Klassenname fehlt)', [1]], ['5a', [2, 3]], ['10b', [4]]]));

  const rH = rumpf('function kinderTrefferHinweis(d)');
  pruefe('Voraussetzung: Rumpf kinderTrefferHinweis gefunden', rH !== '');
  const h = (d) => { try { return new Function('d', rH)(d); } catch (e) { return 'FEHLER ' + e.message; } };
  pruefe('mehr Treffer als geliefert: nennt Anzahl und Grenze, bittet um Verfeinern',
    h({ kinder: new Array(60).fill({}), anzahl: 61, grenze: 60 }) === '61 Treffer – die ersten 60 werden gezeigt. Suche verfeinern.');
  pruefe('genau auf der Grenze (60 von 60): kein Hinweis', h({ kinder: new Array(60).fill({}), anzahl: 60, grenze: 60 }) === '');

  const rZ = rumpf('function kinderStatusElement(t)');
  pruefe('Voraussetzung: Rumpf kinderStatusElement gefunden', rZ !== '');
  const z = (t) => {
    if (rZ === '') return el('p', 'x', 'FEHLT');
    try { return new Function('el', 't', rZ)(el, t); } catch (e) { return el('p', 'x', 'FEHLER ' + e.message); }
  };
  pruefe('noch nicht gesucht: Aufforderung, Name oder Klasse einzugeben (R1)',
    /Name oder Klasse eingeben/.test(text(z(null))));
  pruefe('während der Suche: „Sucht …“', /Sucht/.test(text(z({ laeuft: true }))));
  const zf = z({ fehler: 'Die Klassenliste aus WebUntis ließ sich gerade nicht lesen. Bitte erneut suchen.' });
  pruefe('Liste nicht erreichbar: der Grund steht als Fehlermeldung da, nicht still leer',
    /ließ sich gerade nicht lesen/.test(text(zf)) && /\bmeldung\b/.test(zf.klasse) && /\bfehler\b/.test(zf.klasse));
  pruefe('keine Treffer: sagt, dass Kinder ohne Klasse nicht erscheinen (E20 E)',
    /Keine Treffer/.test(text(z({ kinder: [], anzahl: 0, grenze: 60 }))) && /ohne Klasse/.test(text(z({ kinder: [], anzahl: 0, grenze: 60 }))));
  pruefe('Treffer vorhanden: kein Statuselement', z({ kinder: [{ id: 1 }], anzahl: 1, grenze: 60 }) === null);

  // ------------------------------------------------------------
  console.log('Suchen und abgelaufene Sitzung, beide Ansichten');
  const fall = async (kopf, feldName, f) => {
    const r = rumpf(kopf);
    const S = {};
    const kaesten = [];
    const kinderSuchen = async () => { if (f) throw f; return { kinder: [{ id: 9 }], anzahl: 1, grenze: 60 }; };
    const sitzungAuswerten = (s, t, a) => { kaesten.push({ s, t, a }); return !!s; };
    const noop = () => {};
    try {
      await new AsyncFunction('S', 'kinderSuchen', 'sitzungAuswerten', 'zeichne', 'zeichneSvTreffer', 'q', r)(
        S, kinderSuchen, sitzungAuswerten, noop, noop, '5a');
    } catch (e) { console.log('    (' + e.message + ')'); }
    return { r, S, kaesten, t: S[feldName] };
  };
  for (const [name, kopf, feldName, erneut] of [
    ['Einladung', 'async function einlSuchen(q)', 'einlTreffer', 'einlSuchen(q)'],
    ['stellvertretend', 'async function svKindSuchen(q)', 'svTreffer', 'svKindSuchen(q)']]) {
    const ok = await fall(kopf, feldName, null);
    pruefe(name + ': Voraussetzung Rumpf gefunden; Treffer landen im Zustand',
      ok.r !== '' && ok.t && Array.isArray(ok.t.kinder) && ok.t.kinder[0].id === 9);
    const ab = await fall(kopf, feldName, Object.assign(new Error('Ihre WebUntis-Sitzung ist abgelaufen.'), { sitzung: 'abgelaufen' }));
    const k = ab.kaesten[0] || { a: {} };
    pruefe(name + ': abgelaufen → Kasten „Anmelden und suchen“, danach dieselbe Suche',
      ab.kaesten.length === 1 && k.s === 'abgelaufen' && k.a.knopf === 'Anmelden und suchen'
      && typeof k.a.aktion === 'function' && String(k.a.aktion).includes(erneut));
    pruefe(name + ': abgelaufen → der Zustand trägt den Fehler, keine leere Trefferliste',
      ab.t && typeof ab.t.fehler === 'string' && ab.t.fehler !== '' && !Array.isArray(ab.t.kinder));
    const nicht = await fall(kopf, feldName, Object.assign(new Error('Die Klassenliste aus WebUntis ließ sich gerade nicht lesen.'), { sitzung: null }));
    pruefe(name + ': nicht erreichbar (502) → Fehler mit Grund im Zustand, kein Kasten',
      nicht.kaesten.length === 0 && nicht.t && /nicht lesen/.test(nicht.t.fehler || ''));
  }

  // ------------------------------------------------------------
  console.log('Aufrufstellen und Wegfall (Quelltext)');
  const rE = rumpf('function ansichtEinladungen(ziel)');
  const rT = rumpf('function einlTrefferZeichnen(ziel)');
  const rV = rumpf('function zeichneSvTreffer()');
  const rK = rumpf('function zeichneStellvertreterKopf(ziel, lehrerId)');
  pruefe('Voraussetzung: Rümpfe der beiden Ansichten gefunden', rE !== '' && rT !== '' && rV !== '' && rK !== '');
  pruefe('beide Ansichten zeigen den Zustand über kinderStatusElement',
    rT.includes('kinderStatusElement(S.einlTreffer)') && rV.includes('kinderStatusElement(S.svTreffer)'));
  pruefe('Einladung: Treffer nach Klasse gruppiert, Ankreuzfeld trägt die Kennung',
    rT.includes('kinderNachKlasse(') && /cb\.value = String\(k\.id\)/.test(rT));
  pruefe('Einladung: Suche ist ein Formular (Eingabetaste) mit Knopf vom Typ submit (R3)',
    /createElement\('form'\)/.test(rE) && /addEventListener\('submit'/.test(rE) && /\.type = 'submit'/.test(rE)
    && rE.includes('einlSuchen('));
  pruefe('stellvertretend: Suche ist ein Formular mit Knopf vom Typ submit (R3)',
    /createElement\('form'\)/.test(rK) && /addEventListener\('submit'/.test(rK) && /\.type = 'submit'/.test(rK)
    && rK.includes('svKindSuchen('));
  pruefe('stellvertretend: kein Abruf je Tastendruck mehr (die alte Fassung: entprellte Suche)',
    rK !== '' && !/svSucheAnstossen|setTimeout\(\(\) => svKindSuchen/.test(js) && !/addEventListener\('input'[^;]*svKindSuchen/.test(rK));
  pruefe('freie Eingabe einer Schüler-ID entfällt (E20 D)',
    rE !== '' && !/einl-schueler|Ersatzweise|Schüler-ID eingeben/.test(rE));
  const altRoute = (t) => (t.match(/\/api\/schueler[?']/g) || []).length;
  const rA = rumpf('function ansichtAdminDaten(ziel)');
  pruefe('/api/schueler steht nur noch in der Admin-Seite (bis Schritt 4): alle Vorkommen dort',
    rA !== '' && altRoute(rA) >= 1 && altRoute(js) === altRoute(rA));
  pruefe('Admin-Seite verspricht die Auswahl nicht mehr über die alte Liste oder Schüler-IDs',
    rA !== '' && !/Damit Lehrkräfte Eltern über eine Klassenliste einladen/.test(rA)
    && !/wieder über Schüler-IDs/.test(rA) && /benutzen diese Liste nicht mehr/.test(rA));
  const rD = rumpf('function datenschutzAbsaetze()');
  pruefe('Datenschutz: die Schülerliste ist nicht mehr die Quelle der Einladungsauswahl',
    rD !== '' && !/aus der Lehrkräfte für \'\s*\+\s*\'Einladungen auswählen/.test(rD)
    && !/aus der Lehrkräfte für Einladungen auswählen/.test(rD) && /benutzt sie nicht mehr/.test(rD));
  pruefe('die alte Ladefunktion ist fort', !/function ladeSchueler\(/.test(js) && !/S\.schuelerListe/.test(js));

  console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' ROT');
  process.exit(fehler === 0 ? 0 : 1);
})();
