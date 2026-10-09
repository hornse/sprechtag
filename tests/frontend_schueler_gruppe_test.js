// ============================================================
// tests/frontend_schueler_gruppe_test.js
// Prüft v0.9.65 (E15) in der Oberfläche:
//   1. Buchungsseite: Ist das Buchen für eine Schülerin/einen Schüler
//      gesperrt, steht die Erklärung des Servers da – keine Kacheln, kein
//      Suchfeld, und nicht zusätzlich „keine Lehrkräfte hinterlegt“.
//   2. Verwaltung: zugelassene Gruppen eintragen. Die Seite nennt die
//      Gruppe des eigenen Kontos, sagt, dass WebUntis auf 20 Zeichen kürzt,
//      zeigt, wie verglichen wird, und warnt bei leerer Liste und bei
//      gekürzten Einträgen.
//
// Ausgeführt (Rümpfe aus app.js mit knappem Ersatz für el() usw.).
//
// Aufruf: node tests/frontend_schueler_gruppe_test.js
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

function el(tag, klasse, text) {
  return { tag, klasse: klasse || '', text: text === undefined ? '' : String(text), kinder: [], value: '', id: '',
    appendChild(k) { this.kinder.push(k); return k; }, addEventListener() {},
    querySelector() { return this.kinder.find((k) => k.tag === 'input' || k.tag === 'textarea') || null; },
    set textContent(v) { this.kinder = []; this.text = String(v); } };
}
const alle = (k) => [k].concat(...k.kinder.map(alle));
const texte = (k) => alle(k).map((x) => x.text).join(' | ');

// ------------------------------------------------------------
console.log('1. Buchungsseite bei Sperre');
const ladeRumpf = rumpf('async function ladeLehrerListe(');
(async () => {
  const S = { aktiverSprechtag: { id: 7 }, kind: 4242, lehrerListe: null, lehrerLaedt: true };
  const meldungen = [];
  const antwort = { eingeladen: [], unterrichtend: [], sonderlehrer: [], weitere: [], nur_eingeladene: false,
    buchen_gesperrt: 'gruppe_nicht_zugelassen', hinweis: 'Termine buchen die Erziehungsberechtigten. Erfunden.',
    automatisch_ermittelt: null, ohne_stammsatz: [] };
  try {
    await new Function('S', 'api', 'meldung', 'zeichne', 'return (async () => {' + ladeRumpf + '})();')(
      S, async () => antwort, (t, a) => meldungen.push([t, a]), () => {});
  } catch (e) { meldungen.push(['AUSNAHME ' + e.message]); }
  pruefe('Laden bei Sperre: keine Meldung „keine Lehrkräfte hinterlegt“ (die Erklärung steht in der Seite)',
    ladeRumpf !== '' && meldungen.length > 0 && meldungen.every(([t]) => t === null));

  const ansichtRumpf = rumpf('function ansichtBuchen(');
  const ziel = el('div');
  const S2 = { aktiverSprechtag: { id: 7, phase: 'phase2' }, user: { kinder: [{ id: 4242, name: '' }] }, kind: 4242,
    lehrerListe: antwort, raster: [], weitereSuche: '' };
  let kacheln = 0;
  try {
    const auswahl = () => { const d = el('label'); d.querySelector = () => ({ addEventListener() {} }); return d; };
    new Function('S', 'el', 'feld', 'auswahl', 'zeigeHinweisText', 'sprechtagWaehler', 'kontaktSatz',
      'zeichneTermineKompakt', 'ladeLehrerListe', 'buchenLehrerAlle', 'buchenLehrerAbschnitte',
      'zeichneBuchenKacheln', 'zeichneWeitereLehrkraefte', 'zeichneRaster', 'zeichne', 'ziel', ansichtRumpf)(
      S2, el, (l, id) => { const x = el('label', null, l); const i = el('input'); i.id = id; x.appendChild(i); return x; },
      auswahl, () => {}, () => true, () => '', () => {}, () => {}, () => [], () => [],
      () => { kacheln++; }, () => { kacheln++; }, () => {}, () => {}, ziel);
  } catch (e) { ziel.appendChild(el('p', 'AUSNAHME', e.message)); }
  const wichtig = alle(ziel).filter((x) => x.klasse === 'hinweis-wichtig');
  pruefe('Ansicht bei Sperre: die Erklärung des Servers, genau einmal',
    ansichtRumpf !== '' && wichtig.filter((x) => x.text === antwort.hinweis).length === 1);
  pruefe('… und keine Kacheln, keine Suche der Weiteren, kein Suchfeld',
    kacheln === 0 && !alle(ziel).some((x) => x.tag === 'input' && x.id === 'buchen-weitere-suche')
    && !alle(ziel).some((x) => x.klasse.includes('buchen-gitter')));

  // ----------------------------------------------------------
  console.log('2. Verwaltung: zugelassene Gruppen');
  const sgRumpf = rumpf('function zeichneSchuelerGruppen(');
  const zeichneSg = (daten) => {
    const z = el('div');
    const S3 = { sgDaten: daten, sgLaedt: false };
    const sektion = (t, b) => { const s = el('section', 'sektion'); s.appendChild(el('h3', 'sektion-titel', t));
      if (b) s.appendChild(el('p', 'hinweis', b)); return s; };
    try {
      new Function('S', 'el', 'sektion', 'knopf', 'api', 'toast', 'zeichne', 'ziel', sgRumpf)(
        S3, el, sektion, (t) => el('button', null, t), async () => ({}), () => {}, () => {}, z);
    } catch (e) { z.appendChild(el('p', 'AUSNAHME', e.message)); }
    return z;
  };
  const z1 = zeichneSg({ gruppen: ['SuS über 18', 'SuS über 18 mit Atte'], eigene_gruppe: 'Lehrkräfte', laenge: 20 });
  const t1 = texte(z1);
  pruefe('nennt die Gruppe des eigenen Kontos (zum Abschreiben)', sgRumpf !== '' && t1.includes('„Lehrkräfte“'));
  pruefe('sagt, dass WebUntis auf 20 Zeichen kürzt', /20 Zeichen/.test(t1));
  const ta = alle(z1).find((x) => x.tag === 'textarea');
  pruefe('Eingabefeld: eine Gruppe je Zeile, mit den gespeicherten Gruppen',
    !!ta && ta.value === 'SuS über 18\nSuS über 18 mit Atte');
  pruefe('zeigt, wie verglichen wird (jede gespeicherte Gruppe genannt)',
    alle(z1).filter((x) => x.tag === 'code').map((x) => x.text).join('|') === 'SuS über 18|SuS über 18 mit Atte');
  const z2 = zeichneSg({ gruppen: [], eigene_gruppe: null, laenge: 20 });
  pruefe('leere Liste: Warnung, dass derzeit niemand als Schüler:in selbst buchen kann',
    alle(z2).some((x) => x.klasse === 'hinweis-wichtig' && /derzeit/.test(x.text)));
  pruefe('keine eigene Gruppe ermittelt: sagt es, statt „null“ zu zeigen',
    !texte(z2).includes('null') && /keine Gruppe/.test(texte(z2)));
  const gkRumpf = rumpf('function gruppenGekuerztText(');
  let gk = '';
  try { gk = String(new Function('liste', gkRumpf)([{ eingegeben: 'SuS über 18 mit Attest', verglichen: 'SuS über 18 mit Atte' }]) || ''); }
  catch (e) { gk = ''; }
  pruefe('gekürzte Einträge werden mit beiden Fassungen gemeldet',
    gk.includes('„SuS über 18 mit Attest“') && gk.includes('„SuS über 18 mit Atte“'));
  const datenRumpf = rumpf('function ansichtAdminDaten(');
  pruefe('Aufrufstelle: „Dienstkonto & Schülerliste“ zeichnet den Abschnitt',
    /zeichneSchuelerGruppen\(ziel\);/.test(datenRumpf));

  console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
  process.exit(fehler === 0 ? 0 : 1);
})();
