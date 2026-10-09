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
      new Function('S', 'el', 'sektion', 'block', 'knopf', 'api', 'toast', 'zeichne', 'ziel', sgRumpf)(
        S3, el, sektion, () => el('details', 'block'), (t) => el('button', null, t), async () => ({}), () => {}, () => {}, z);
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
  // ----------------------------------------------------------
  // v0.9.66: Der GELADENE Zustand, nicht nur der Aufruf. Im Betrieb
  // antwortete die Route mit 500; die Ansicht blieb bei „Wird geladen …“
  // und rief erneut ab. Ausgeführt über zwei Zeichnungen wie im Betrieb:
  // erste Zeichnung startet den Abruf, nach seinem Ende wird neu gezeichnet.
  console.log('3. Geladener Zustand der Ansicht');
  async function ladeLauf(antwort) {
    const S4 = { sgDaten: null, sgLaedt: false, sgFehler: null };
    let abrufe = 0, z = el('div');
    const sektion = (t) => { const x = el('section', 'sektion'); x.appendChild(el('h3', 'sektion-titel', t)); return x; };
    const zeichnen = () => {
      z = el('div');
      try {
        new Function('S', 'el', 'sektion', 'block', 'knopf', 'api', 'toast', 'zeichne', 'ziel', sgRumpf)(
          S4, el, sektion, () => el('details', 'block'), (t) => el('button', null, t),
          () => { abrufe++; return antwort(); }, () => {}, () => {}, z);
      } catch (e) { z.appendChild(el('p', 'AUSNAHME', e.message)); }
    };
    zeichnen();                                      // startet den Abruf
    await new Promise((f) => setTimeout(f, 0));      // Abruf endet
    zeichnen(); zeichnen();                          // spätere Zeichnungen
    return { abrufe, text: texte(z), knoepfe: alle(z).filter((x) => x.tag === 'button').map((x) => x.text) };
  }
  const fehl = await ladeLauf(() => Promise.reject(new Error('Fehler 500')));
  pruefe('Abruf scheitert: Fehler steht da, nicht „Wird geladen …“',
    sgRumpf !== '' && /nicht geladen/.test(fehl.text) && fehl.text.includes('Fehler 500') && !/Wird geladen/.test(fehl.text));
  pruefe('… kein erneuter Abruf von selbst (über drei Zeichnungen genau einer), Knopf „Erneut laden“',
    fehl.abrufe === 1 && fehl.knoepfe.includes('Erneut laden'));
  const gut = await ladeLauf(() => Promise.resolve({ gruppen: ['SuS über 18'], eigene_gruppe: null, laenge: 20 }));
  pruefe('Abruf gelingt: geladener Zustand mit Eingabefeld, ein Abruf',
    gut.abrufe === 1 && !/Wird geladen/.test(gut.text) && gut.text.includes('SuS über 18'));

  // ----------------------------------------------------------
  // v0.9.68: Auswahlliste aus userrole/config statt Eintippen. Belegt
  // (Messung 09.10.2026): vier Gruppen mit Schülern (Student 1755, SuS über
  // 18 185, SuS über 18 mit Atte 13, I-Helfer*in 1); bei systemeigenen
  // Gruppen stimmte der Name nicht mit profile/general überein („Admin“
  // gegen „Administration“). Übrige Werte erfunden.
  console.log('4. Auswahlliste');
  function sgMit(daten) {
    const z = el('div');
    const S5 = { sgDaten: daten, sgLaedt: false, sgFehler: null };
    const knoepfe = {}; const posts = [];
    const sektion = (t, b) => { const x = el('section', 'sektion'); x.appendChild(el('h3', 'sektion-titel', t));
      if (b) x.appendChild(el('p', 'hinweis', b)); return x; };
    const block = (k, t) => { const d = el('details', 'block'); d.appendChild(el('summary', null, t)); return d; };
    try {
      new Function('S', 'el', 'sektion', 'block', 'knopf', 'api', 'toast', 'zeichne', 'ziel', sgRumpf)(
        S5, el, sektion, block, (t, k, f) => { knoepfe[t] = f; return el('button', null, t); },
        async (pfad, o) => { posts.push(o && o.body); return { gruppen: [], eigene_gruppe: null, laenge: 20 }; },
        () => {}, () => {}, z);
    } catch (e) { z.appendChild(el('p', 'AUSNAHME', e.message)); }
    return { z, knoepfe, posts };
  }
  const AUSWAHL = [
    { id: 3, label: 'Student', userRole: 5, userCount: 1755, schueler: 1755 },
    { id: 25, label: 'SuS über 18', userRole: -1, userCount: 185, schueler: 185 },
    { id: 45, label: 'SuS über 18 mit Atte', userRole: -1, userCount: 13, schueler: 13 },
    { id: 70, label: 'I-Helfer*in', userRole: -1, userCount: 5, schueler: 1 },
    { id: 1, label: 'Admin', userRole: 16, userCount: 2, schueler: 0 },
    { id: 80, label: 'Beratung', userRole: -1, userCount: 0, schueler: 0 },
  ];
  const m = sgMit({ gruppen: ['SuS über 18', 'Altname'], eigene_gruppe: 'Administration', laenge: 20,
    auswahl: AUSWAHL, auswahl_fehler: null });
  const kaesten = alle(m.z).filter((x) => x.tag === 'input' && x.type === 'checkbox');
  const beschr = (cb) => { const zeile = alle(m.z).find((x) => x.kinder.includes(cb)); return zeile ? texte(zeile) : ''; };
  pruefe('Auswahl statt Textfeld: je Gruppe ein Kästchen, keine ausgeblendet (6 + 1 nicht in der Liste)',
    sgRumpf !== '' && kaesten.length === 7 && !alle(m.z).some((x) => x.tag === 'textarea'));
  pruefe('Gruppen mit Schülern zuerst, mit Anzahl („SuS über 18 — 185 Schüler“)',
    /Student — 1755 Schüler/.test(beschr(kaesten[0])) && /SuS über 18 — 185 Schüler/.test(beschr(kaesten[1]))
    && /I-Helfer\*in — 1 Schüler/.test(beschr(kaesten[3])));
  pruefe('angehakt sind genau die gespeicherten Gruppen',
    kaesten.filter((k) => k.checked).length === 2 && kaesten[1].checked && !kaesten[0].checked);
  pruefe('eine gespeicherte Gruppe, die WebUntis nicht kennt, steht da – mit Warnung',
    /Altname/.test(texte(m.z)) && alle(m.z).some((x) => x.klasse === 'hinweis-wichtig' && /Altname/.test(x.text) && /nicht in der Liste/.test(x.text)));
  pruefe('Hinweis: WebUntis zeigt im persönlichen Bereich den vollständigen Namen – dieselbe Gruppe',
    /vollständigen Namen/.test(texte(m.z)) && /20 Zeichen/.test(texte(m.z)));
  const sys = sgMit({ gruppen: ['Student'], eigene_gruppe: null, laenge: 20, auswahl: AUSWAHL, auswahl_fehler: null });
  pruefe('systemeigene Gruppe gewählt: Warnung, dass der Name bei der Anmeldung anders lauten kann',
    alle(sys.z).some((x) => x.klasse === 'hinweis-wichtig' && /systemeigen/.test(x.text) && /Student/.test(x.text)));
  if (kaesten[2]) kaesten[2].checked = true;
  try { await m.knoepfe['Speichern'](); } catch (e) { m.posts.push('AUSNAHME ' + e.message); }
  pruefe('Speichern schickt die angehakten Namen – so, wie WebUntis sie liefert',
    m.posts.length === 1 && m.posts[0] && m.posts[0].gruppen === 'SuS über 18\nSuS über 18 mit Atte\nAltname');
  const rf = sgMit({ gruppen: ['SuS über 18'], eigene_gruppe: null, laenge: 20, auswahl: null,
    auswahl_fehler: 'WebUntis-Sitzung abgelaufen – bitte abmelden und neu anmelden' });
  pruefe('Abruf gescheitert: Grund steht da, Rückfall Eintippen mit Kürzungshinweis, kein „Wird geladen“',
    /abgelaufen/.test(texte(rf.z)) && alle(rf.z).some((x) => x.tag === 'textarea') && /20 Zeichen/.test(texte(rf.z))
    && !/Wird geladen/.test(texte(rf.z)));

  const datenRumpf = rumpf('function ansichtAdminDaten(');
  pruefe('Aufrufstelle: „Dienstkonto & Schülerliste“ zeichnet den Abschnitt',
    /zeichneSchuelerGruppen\(ziel\);/.test(datenRumpf));

  console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
  process.exit(fehler === 0 ? 0 : 1);
})();
