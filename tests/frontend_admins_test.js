// ============================================================
// tests/frontend_admins_test.js – Seite „Administratoren“ (v0.9.99, E29)
//
// Teil 2 des Zugs zu Aufräumliste 10: die Oberfläche zu /api/admins.
// Geprüft wird der sichtbare Text, nicht der Wert im Code:
//   1. adminZeileText() – die drei Herkünfte, je mit eigenem Text; ein
//      Kürzel in beiden Listen bekommt KEINEN eigenen Text (Betreiber,
//      12.10.2026: im Betrieb 0 solche Fälle; nur die Vorsichtsmaßnahme).
//   2. zeichneAdmins() – jede Person genau einmal; „Entfernen“ genau dort,
//      wo der Server entfernbar sagt; die Auswahl zum Eintragen ohne die,
//      die schon Administrator sind; Laden, Fehler, Rückmeldung am Ort.
//   3. adminEntfernen() / adminEintragen() – Aufruf, Rückfrage, Rückmeldung.
//   4. Navigation (Quelltext): Nav-Punkt, Ansicht, Hash, Zurücksetzen.
//
// Ausgeführt (Rümpfe aus app.js mit knappem Ersatz für el() usw.).
// Wie die Seite aussieht, misst diese Suite nicht – das ist ein
// Abnahmepunkt im Browser.
// Kürzel und Namen ERFUNDEN.
//
// Aufruf: node tests/frontend_admins_test.js
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
  return { tag, klasse: klasse || '', text: text === undefined || text === null ? '' : String(text), kinder: [],
    appendChild(k) { this.kinder.push(k); return k; }, addEventListener() {} };
}
const alle = (k) => [k].concat(...k.kinder.map(alle));
const texte = (k) => alle(k).map((x) => x.text).join(' | ');
const sektion = (titel, beschr) => { const s = el('section', 'sektion'); s.appendChild(el('h3', null, titel));
  if (beschr) s.appendChild(el('p', 'hinweis', beschr)); return s; };
const knoepfe = [];
const knopf = (text, klasse, aktion) => { const b = el('button', klasse, text); b.aktion = aktion; knoepfe.push(b); return b; };
const auswahl = (label, id, optionen) => { const l = el('label', null, label); const s = el('select'); s.id = id;
  for (const o of optionen) { const op = el('option', null, o.text); op.wert = o.wert; s.appendChild(op); }
  l.appendChild(s); return l; };
const zeitstempel = (iso) => { const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
  return m ? m[3] + '.' + m[2] + '. ' + m[4] + ':' + m[5] : String(iso); };

// Antwort von GET /api/admins wie run_admins.php sie belegt.
const LISTE = [
  { herkunft: 'webuntis', kuerzel: null, name: '', id: null, selbst: false, entfernbar: false,
    auch_oberflaeche: false, ohne_stammsatz: false },
  { herkunft: 'notzugang', kuerzel: 'Aq', name: 'Anna Erfunden', id: 3, selbst: false, entfernbar: true,
    auch_oberflaeche: true, ohne_stammsatz: false },
  { herkunft: 'notzugang', kuerzel: 'Zq', name: '', id: null, selbst: false, entfernbar: false,
    auch_oberflaeche: false, ohne_stammsatz: true },
  { herkunft: 'oberflaeche', kuerzel: 'Bq', name: 'Berta Erfunden', id: 1, selbst: true, entfernbar: false,
    auch_oberflaeche: false, ohne_stammsatz: false, angelegt_von: 'Aq', angelegt_am: '2026-10-12 09:30:00' },
  { herkunft: 'oberflaeche', kuerzel: 'Cq', name: 'Carl Erfunden', id: 2, selbst: false, entfernbar: true,
    auch_oberflaeche: false, ohne_stammsatz: false, angelegt_von: 'Aq', angelegt_am: '2026-10-11 14:05:00' },
];
const LEHRER = [{ kuerzel: 'Aq', name: 'Anna Erfunden' }, { kuerzel: 'BQ', name: 'Berta Erfunden' },
  { kuerzel: 'Cq', name: 'Carl Erfunden' }, { kuerzel: 'Dq', name: 'Dora Erfunden' }];

// ------------------------------------------------------------
console.log('1. Text je Herkunft');
const ztRumpf = rumpf('function adminZeileText(');
let zt = () => ({ wer: '', woher: '', hinweis: '' });
try { if (ztRumpf) zt = new Function('zeitstempel', 'return function (e) {' + ztRumpf + '};')(zeitstempel); }
catch (e) { console.log('    (Ausnahme: ' + e.message + ')'); }
const [tW, tA, tZ, tB, tC] = LISTE.map((e) => zt(e));
pruefe('WebUntis-Konto: „WebUntis-Administrationskonto“, Herkunft WebUntis, vergeben in WebUntis',
  ztRumpf !== '' && tW.wer === 'WebUntis-Administrationskonto' && tW.woher === 'WebUntis'
  && /in WebUntis, nicht hier/.test(tW.hinweis));
pruefe('Notzugang: Kürzel mit Name, Herkunft „Notzugang (config.php)“, nur auf dem Server zu ändern',
  tA.wer === 'Aq – Anna Erfunden' && tA.woher === 'Notzugang (config.php)'
  && /config\.php/.test(tA.hinweis) && /Server/.test(tA.hinweis) && /aussperren/.test(tA.hinweis));
pruefe('Notzugang ohne Stammsatz: Kürzel allein, mit „Keine Lehrkraft … in den Stammdaten“',
  tZ.wer === 'Zq' && /Keine Lehrkraft mit diesem Kürzel in den Stammdaten/.test(tZ.hinweis));
pruefe('Kürzel in beiden Listen (Aq): kein eigener Text – derselbe Hinweis wie ein reiner Notzugang',
  ztRumpf !== '' && tA.hinweis === zt(Object.assign({}, LISTE[1], { id: null, entfernbar: false, auch_oberflaeche: false })).hinweis);
pruefe('Oberfläche: Herkunft „diese Seite“, „Eingetragen von Aq am 11.10. 14:05.“',
  tC.woher === 'diese Seite' && tC.hinweis === 'Eingetragen von Aq am 11.10. 14:05.');
pruefe('eigener Eintrag: sagt, dass nur jemand anderes ihn entfernen kann',
  /eigener Eintrag/.test(tB.hinweis) && /jemand anderes/.test(tB.hinweis) && !/eigener Eintrag/.test(tC.hinweis));

// ------------------------------------------------------------
console.log('2. Seite');
const zaRumpf = rumpf('function zeichneAdmins(');
const zeichneMit = (S, api) => {
  const ziel = el('div');
  knoepfe.length = 0;
  const spur = { api: [], stamm: 0, entfernt: [], eingetragen: [] };
  try {
    new Function('S', 'el', 'sektion', 'knopf', 'auswahl', 'kartenTabelle', 'api', 'zeichne', 'ladeStammdaten',
      'adminZeileText', 'adminEntfernen', 'adminEintragen', 'wert', 'ziel', zaRumpf)(
      S, el, sektion, knopf, auswahl, (t) => t,
      (p) => { spur.api.push(p); return api ? api(p) : new Promise(() => {}); }, () => {},
      () => { spur.stamm++; return new Promise(() => {}); }, zt,
      (e) => spur.entfernt.push(e.kuerzel), (k) => spur.eingetragen.push(k), () => 'Dq', ziel);
  } catch (e) { ziel.appendChild(el('p', 'AUSNAHME', e.message)); }
  return { ziel, spur };
};
const voll = () => ({ admDaten: LISTE, admLaedt: false, admFehler: null, admMeldungen: [], admStamm: 'da',
  stammdaten: { lehrer: LEHRER } });

let { ziel, spur } = zeichneMit(voll());
const zeilen = alle(ziel).filter((x) => x.tag === 'tr').slice(1);
const werTexte = zeilen.map((z) => (z.kinder[0] || {}).text);
pruefe('jede Person genau einmal, in der Reihenfolge des Servers – ' + werTexte.join(' / '),
  zaRumpf !== '' && JSON.stringify(werTexte) === JSON.stringify(LISTE.map((e) => zt(e).wer)));
const entf = knoepfe.filter((b) => b.text === 'Entfernen');
pruefe('„Entfernen“ genau bei den entfernbaren (Aq, Cq) – nicht beim WebUntis-Konto, Zq, dem eigenen Bq',
  entf.length === 2 && zeilen.filter((z) => alle(z).some((x) => x.text === 'Entfernen'))
    .map((z) => z.kinder[0].text).join(',') === 'Aq – Anna Erfunden,Cq – Carl Erfunden');
entf[1] && entf[1].aktion();
pruefe('der Knopf ruft adminEntfernen mit seinem Eintrag (Cq)', spur.entfernt.join(',') === 'Cq');
pruefe('Einleitung nennt die beiden Wege, die sich hier nicht ändern lassen',
  /Notzugang in config\.php/.test(texte(ziel)) && /Administrationskonto von WebUntis/.test(texte(ziel)));
const optionen = alle(ziel).filter((x) => x.tag === 'option').map((o) => o.wert);
pruefe('Auswahl zum Eintragen: nur Lehrkräfte, die noch nicht Administrator sind (Dq; BQ = Bq fällt weg) – '
  + optionen.join(','), JSON.stringify(optionen) === JSON.stringify(['', 'Dq']));
const ein = knoepfe.find((b) => b.text === 'Als Administrator eintragen');
ein && ein.aktion();
pruefe('„Als Administrator eintragen“ ruft adminEintragen mit der Auswahl', spur.eingetragen.join(',') === 'Dq');
pruefe('sagt, wann es wirkt: Eintragen ab der nächsten Anmeldung, Entfernen sofort',
  /ab der nächsten Anmeldung/.test(texte(ziel)) && /Entfernen wirkt sofort/.test(texte(ziel)));
pruefe('mit geladener Liste und Stammdaten: kein Abruf', zaRumpf !== '' && spur.api.length === 0 && spur.stamm === 0);

({ ziel, spur } = zeichneMit(Object.assign(voll(), { stammdaten: { lehrer: LEHRER.slice(0, 3) } })));
pruefe('alle Lehrkräfte schon Administrator: Satz statt leerer Auswahl, kein Knopf',
  /bereits Administrator/.test(texte(ziel)) && !alle(ziel).some((x) => x.tag === 'select')
  && !knoepfe.some((b) => b.text === 'Als Administrator eintragen'));
({ ziel, spur } = zeichneMit(Object.assign(voll(), { stammdaten: { lehrer: [] } })));
pruefe('keine Stammdaten: verweist auf die Übernahme aus WebUntis',
  /Stammdaten aus WebUntis/.test(texte(ziel)) && !alle(ziel).some((x) => x.tag === 'select'));
({ ziel, spur } = zeichneMit(Object.assign(voll(), { admStamm: undefined, stammdaten: { lehrer: [] } })));
pruefe('Stammdaten noch nicht geladen: einmal laden, „Wird geladen …“ statt „keine Lehrkräfte“',
  spur.stamm === 1 && /Wird geladen/.test(texte(ziel)) && !/Stammdaten aus WebUntis/.test(texte(ziel)));

const S1 = { admDaten: null, admLaedt: false, admFehler: null, admMeldungen: [], stammdaten: { lehrer: [] } };
({ ziel, spur } = zeichneMit(S1));
const spur2 = zeichneMit(S1).spur;
pruefe('Liste nicht geladen: „Wird geladen …“, GET /api/admins genau einmal, auch beim zweiten Zeichnen',
  /Wird geladen/.test(texte(ziel)) && spur.api.join(',') === '/api/admins' && spur2.api.length === 0
  && !alle(ziel).some((x) => x.tag === 'table'));
({ ziel, spur } = zeichneMit({ admDaten: null, admLaedt: false, admFehler: 'Fehler 500', admMeldungen: [],
  stammdaten: { lehrer: [] } }));
pruefe('Laden gescheitert: der Fehler steht da, kein erneuter Abruf, „Erneut laden“',
  /konnte nicht geladen werden: Fehler 500/.test(texte(ziel)) && spur.api.length === 0
  && knoepfe.some((b) => b.text === 'Erneut laden'));
({ ziel, spur } = zeichneMit(Object.assign(voll(), { admMeldungen: [{ art: 'fehler', text: 'Erfundene Meldung A' },
  { art: 'info', text: 'Erfundene Meldung B' }] })));
const sek = alle(ziel).find((x) => x.tag === 'section');
pruefe('Rückmeldung am Ort: beide Meldungen in der Seite, je einmal, der Fehler hervorgehoben',
  sek && alle(sek).filter((x) => x.text === 'Erfundene Meldung A' && x.klasse === 'hinweis-wichtig').length === 1
  && alle(sek).filter((x) => x.text === 'Erfundene Meldung B').length === 1);

// ------------------------------------------------------------
console.log('3. Entfernen und Eintragen');
const entfRumpf = rumpf('async function adminEntfernen(');
const eintRumpf = rumpf('async function adminEintragen(');
const handle = async (r, arg, S, antwort, frage = true) => {
  const aufrufe = []; let gezeichnet = 0;
  const api = async (p, o) => { aufrufe.push([p, (o || {}).method || 'GET', JSON.stringify((o || {}).body || null)]);
    if (antwort instanceof Error) throw antwort; return antwort; };
  try {
    await new Function('S', 'api', 'zeichne', 'confirm', 'return (async (x) => {' + r + '});')(
      S, api, () => { gezeichnet++; }, () => frage)(arg);
  } catch (e) { aufrufe.push(['AUSNAHME ' + e.message]); }
  return { aufrufe, gezeichnet };
};
(async () => {
  // Der Parameter heißt in app.js e bzw. kuerzel – der Rumpf wird mit x gerufen.
  const entfernen = entfRumpf.replace(/^/, 'const e = x;');
  const eintragen = eintRumpf.replace(/^/, 'const kuerzel = x;');
  let S = { admDaten: LISTE, admMeldungen: [] };
  let r = await handle(entfernen, LISTE[4], S, { ok: true, hinweise: [] });
  pruefe('Entfernen (Cq): DELETE /api/admins/2, Liste neu laden, „nicht mehr Administrator“ in der Seite',
    entfRumpf !== '' && r.aufrufe.length === 1 && r.aufrufe[0][0] === '/api/admins/2' && r.aufrufe[0][1] === 'DELETE'
    && S.admDaten === null && r.gezeichnet === 1
    && S.admMeldungen.some((m) => /„Cq“ ist nicht mehr Administrator/.test(m.text)));
  S = { admDaten: LISTE, admMeldungen: [] };
  r = await handle(entfernen, LISTE[4], S, { ok: true }, false);
  pruefe('Rückfrage verneint: kein Aufruf, Liste bleibt', entfRumpf !== '' && r.aufrufe.length === 0 && S.admDaten === LISTE);
  S = { admDaten: LISTE, admMeldungen: [] };
  const hinweis = '„Aq“ bleibt Administrator: Das Kürzel steht als Notzugang in config.php (admin_kuerzel). Erfunden.';
  r = await handle(entfernen, LISTE[1], S, { ok: true, hinweise: [hinweis] });
  pruefe('Eintrag, der auch Notzugang ist (Aq): der Hinweis des Servers steht da, kein „nicht mehr Administrator“',
    S.admMeldungen.some((m) => m.text === hinweis)
    && !S.admMeldungen.some((m) => /nicht mehr Administrator/.test(m.text)));
  S = { admDaten: LISTE, admMeldungen: [] };
  r = await handle(entfernen, LISTE[4], S, Object.assign(new Error('Erfundener Serverfehler'), {}));
  pruefe('Server lehnt ab: seine Meldung als Fehler in der Seite',
    S.admMeldungen.length === 1 && S.admMeldungen[0].art === 'fehler' && S.admMeldungen[0].text === 'Erfundener Serverfehler');

  S = { admDaten: LISTE, admMeldungen: [] };
  r = await handle(eintragen, '', S, { ok: true });
  pruefe('Eintragen ohne Auswahl: kein Aufruf, Fehler „Lehrkraft wählen“ in der Seite',
    eintRumpf !== '' && r.aufrufe.length === 0 && S.admMeldungen.some((m) => m.art === 'fehler' && /Lehrkraft wählen/.test(m.text)));
  S = { admDaten: LISTE, admMeldungen: [] };
  r = await handle(eintragen, 'Dq', S, { ok: true });
  pruefe('Eintragen (Dq): POST /api/admins {kuerzel}, neu laden, „ab der nächsten Anmeldung“',
    r.aufrufe.length === 1 && r.aufrufe[0][0] === '/api/admins' && r.aufrufe[0][1] === 'POST'
    && r.aufrufe[0][2] === JSON.stringify({ kuerzel: 'Dq' }) && S.admDaten === null
    && S.admMeldungen.some((m) => /„Dq“/.test(m.text) && /ab der nächsten Anmeldung/.test(m.text)));
  S = { admDaten: LISTE, admMeldungen: [] };
  r = await handle(eintragen, 'Dq', S, { ok: true, hinweis: '„Dq“ ist bereits eingetragen.' });
  pruefe('Server sagt „bereits eingetragen“: dieser Satz statt der Erfolgsmeldung',
    S.admMeldungen.length === 1 && S.admMeldungen[0].text === '„Dq“ ist bereits eingetragen.');
  S = { admDaten: LISTE, admMeldungen: [] };
  r = await handle(eintragen, 'Aq', S, new Error('„Aq“ ist bereits Administrator über den Notzugang. Erfunden.'));
  pruefe('Server lehnt ab (Notzugang): seine Meldung als Fehler in der Seite',
    S.admMeldungen.length === 1 && S.admMeldungen[0].art === 'fehler' && /Notzugang/.test(S.admMeldungen[0].text));

  // ----------------------------------------------------------
  console.log('4. Navigation (Quelltext)');
  pruefe('Nav-Punkt „Administratoren“ in der Gruppe Administration',
    /sub\.appendChild\(navKnopf\('admin-admins', 'Administratoren', true, '[a-z]+'\)\);/.test(js));
  const sym = (js.match(/navKnopf\('admin-admins', 'Administratoren', true, '([a-z]+)'\)/) || [])[1];
  const sprite = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'vendor', 'ci-css', 'ci-icons.svg'), 'utf8');
  pruefe('… sein Symbol steht im Symbolsatz des Moduls (' + sym + ')', !!sym && sprite.includes('id="ci-i-' + sym + '"'));
  pruefe('Ansicht, Hash-Schlüssel und Admin-Seitenliste kennen „admin-admins“',
    js.includes("'admin-admins': ansichtAdminAdmins,")
    && /const ANSICHT_KEYS = \[[^\]]*'admin-admins'/.test(js)
    && /const adminSeiten = \[[^\]]*'admin-admins'/.test(js));
  const zr = rumpf('function ansichtZuruecksetzen(');
  pruefe('Ansichtswechsel verwirft Liste, Meldungen und den Stand der Stammdaten der Seite',
    /S\.admDaten = null;/.test(zr) && /S\.admMeldungen = \[\];/.test(zr) && /S\.admStamm = undefined;/.test(zr)
    && /S\.admFehler = null;/.test(zr) && /S\.admLaedt = false;/.test(zr));
  pruefe('Startzustand: admDaten null („nicht geladen“), nicht []',
    /const S = \{[\s\S]*?\n  admDaten: null,/.test(js));

  console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : `\n${fehler} TEST(S) ROT`);
  process.exit(fehler === 0 ? 0 : 1);
})();
