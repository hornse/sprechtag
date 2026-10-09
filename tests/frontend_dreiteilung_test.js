// ============================================================
// tests/frontend_dreiteilung_test.js
// Prüft v0.9.57 (Zug 3, E10): Klassenleitung erkennbar, weitere
// teilnehmende Lehrkräfte hinter einer eingeklappten Suche, Treffer erst
// bei Eingabe, dort als dieselben Kacheln (Klick → Raster → buchbar).
// Seit v0.9.58 (Zug 3b): kein oberes Suchfeld mehr, kein Rand an der
// Klassenleitung – das Abzeichen ist die einzige Kennzeichnung.
// Seit v0.9.60 (E10-Nachtrag, Vierteilung): Die Sonderrollen stehen in
// einem eigenen Abschnitt nach den Unterrichtenden, abgesetzt.
//
// Ausgeführt, nicht gesucht: zeichneBuchenKacheln(), zeichneWeitereKacheln()
// und zeichneWeitereLehrkraefte() laufen mit einem knappen Ersatz für el(),
// block() und feld(). Der Ersatz zeichnet nur auf – er legt nichts selbst an.
//
// Aufruf: node tests/frontend_dreiteilung_test.js
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
const css = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

const kachelRumpf  = rumpf('function zeichneBuchenKacheln(');
const weitereRumpf = rumpf('function zeichneWeitereKacheln(');
const blockRumpf   = rumpf('function zeichneWeitereLehrkraefte(');
const ansichtRumpf = rumpf('function ansichtBuchen(');
const alleRumpf    = rumpf('function buchenLehrerAlle(');
const abschnRumpf  = rumpf('function buchenLehrerAbschnitte(');

// ---- Ersatz ----------------------------------------------------
function el(tag, klasse, text) {
  return {
    tag, klasse: klasse || '', text: text || '', kinder: [], hoerer: {},
    appendChild(k) { this.kinder.push(k); return k; },
    addEventListener(art, f) { this.hoerer[art] = f; },
    querySelector() { return this.kinder.find((k) => k.tag === 'input') || null; },
    set textContent(v) { this.kinder = []; },
  };
}
function block(kennung, titel) { const d = el('details', 'block'); d.kennung = kennung; d.titel = titel; return d; }
function feld(label, id, typ, wert) {
  const l = el('label', null, label);
  const i = el('input'); i.id = id; i.value = wert || '';
  l.appendChild(i);
  return l;
}
const texte = (k) => [k.text].concat(...k.kinder.map(texte));
const alleKnoten = (k) => [k].concat(...k.kinder.map(alleKnoten));
const kacheln = (k) => alleKnoten(k).filter((x) => x.klasse.split(' ').includes('buchen-kachel'));

// buchenSuche gibt es seit v0.9.58 nicht mehr; die Prüfungen setzen es
// trotzdem, um zu belegen, dass ein Rest davon nichts mehr filtert.
const S = { weitereSuche: '', gewaehlteLehrkraft: null };
const geladen = [];
const ladeRaster = (id) => geladen.push(id);
const fehlt = () => { throw new Error('Rumpf fehlt'); };
let zeichneBuchenKacheln = fehlt, zeichneWeitereKacheln = fehlt, zeichneWeitereLehrkraefte = fehlt;
if (kachelRumpf !== '') {
  zeichneBuchenKacheln = (gitter, alle, suche) => new Function(
    'S', 'el', 'anzeigeZeit', 'ladeRaster', 'gitter', 'alle', 'suche', kachelRumpf)(
    S, el, () => '', ladeRaster, gitter, alle, suche);
}
if (weitereRumpf !== '') {
  zeichneWeitereKacheln = (gitter, weitere) => new Function(
    'S', 'el', 'zeichneBuchenKacheln', 'gitter', 'weitere', weitereRumpf)(
    S, el, zeichneBuchenKacheln, gitter, weitere);
}
if (blockRumpf !== '') {
  zeichneWeitereLehrkraefte = (ziel, weitere) => new Function(
    'S', 'el', 'block', 'feld', 'zeichneWeitereKacheln', 'ziel', 'weitere', blockRumpf)(
    S, el, block, feld, zeichneWeitereKacheln, ziel, weitere);
}
const sicher = (f) => { try { return f(); } catch (e) { console.log('    (' + e.message + ')'); return false; } };
const versuch = (f) => { try { f(); } catch (e) { console.log('    (' + e.message + ')'); } };

// Form wie GET /api/buchbare-lehrer (Zahlen als Text, wie PDO/MariaDB)
const zeile = (id, kz, name, extra) => Object.assign({ lehrer_id: String(id), kuerzel: kz, name,
  faecher: '', stunden: '0', klausuren: '0', raum_kuerzel: null, rolle: null, klassenleitung: 0 }, extra || {});
const kl   = zeile(4, 'Kl', 'Vier', { klassenleitung: '1', raum_kuerzel: 'B202' });
const un   = zeile(2, 'Un', 'Zwei', { faecher: 'M', stunden: '4' });
const klEi = zeile(1, 'Ei', 'Eins', { klassenleitung: '1', eingeladen: '1' });
const we   = zeile(6, 'We', 'Sechs');
const wn   = zeile(7, 'Wn', 'Sieben', { raum_kuerzel: 'C303' });

// ------------------------------------------------------------
console.log('zeichneBuchenKacheln – Klassenleitung erkennbar');
const g = el('div', 'buchen-gitter');
versuch(() => zeichneBuchenKacheln(g, [klEi, kl, un]));
const k = kacheln(g);
const zaehle = (karte, t) => texte(karte).filter((x) => x === t).length;
pruefe('drei Kacheln', k.length === 3);
pruefe('Klassenleitung trägt „Klassenleitung“ genau einmal', k.length === 3 && zaehle(k[1], 'Klassenleitung') === 1);
pruefe('… und keine Randklasse (das Abzeichen genügt)', k.length === 3 && k[1].klasse === 'buchen-kachel');
pruefe('Unterrichtende ohne Klassenleitung: weder Text noch Klasse',
  k.length === 3 && zaehle(k[2], 'Klassenleitung') === 0 && !k[2].klasse.split(' ').includes('klassenleitung'));
pruefe('eingeladene Klassenleitung trägt beides',
  k.length === 3 && zaehle(k[0], 'Klassenleitung') === 1 && zaehle(k[0], 'hat Sie eingeladen') === 1);
pruefe('klassenleitung 0 als Zahl, als Text oder fehlend: keine Kennzeichnung', sicher(() => {
  const g2 = el('div'); zeichneBuchenKacheln(g2, [zeile(9, 'Nu', 'Null'),
    zeile(10, 'Nt', 'NullText', { klassenleitung: '0' }), { lehrer_id: '8', kuerzel: 'Oh', name: 'Ohne' }]);
  return kacheln(g2).length === 3 && kacheln(g2).every((x) => zaehle(x, 'Klassenleitung') === 0
    && !x.klasse.split(' ').includes('klassenleitung'));
}));
pruefe('Suchtext filtert', sicher(() => {
  S.buchenSuche = 'zwei'; const g3 = el('div');
  zeichneBuchenKacheln(g3, [kl, un, we], 'sechs'); delete S.buchenSuche;
  return kacheln(g3).length === 1 && texte(kacheln(g3)[0]).includes('Sechs');
}));
pruefe('ohne Suchtext: alle Kacheln – ein Rest von S.buchenSuche filtert nicht', sicher(() => {
  S.buchenSuche = 'zwei'; const g4 = el('div');
  zeichneBuchenKacheln(g4, [kl, un, we]); delete S.buchenSuche;
  return kacheln(g4).length === 3;
}));

// ------------------------------------------------------------
console.log('zeichneWeitereKacheln – Treffer erst bei Eingabe');
const gw = el('div', 'buchen-gitter');
S.weitereSuche = '';
versuch(() => zeichneWeitereKacheln(gw, [we, wn]));
pruefe('ohne Eingabe: keine Kachel', kacheln(gw).length === 0);
pruefe('ohne Eingabe: ein Hinweis, was einzugeben ist', gw.kinder.length === 1 && /eingeben/.test(gw.kinder[0].text));
S.weitereSuche = '   ';
versuch(() => zeichneWeitereKacheln(gw, [we, wn]));
pruefe('nur Leerzeichen gilt als keine Eingabe', kacheln(gw).length === 0);
S.weitereSuche = 'c303';
versuch(() => zeichneWeitereKacheln(gw, [we, wn]));
pruefe('Suche nach Raum findet genau die Lehrkraft', kacheln(gw).length === 1 && texte(kacheln(gw)[0]).includes('Sieben'));
S.weitereSuche = 'W';
versuch(() => zeichneWeitereKacheln(gw, [we, wn]));
pruefe('Suche nach Kürzelteil findet beide', kacheln(gw).length === 2);
pruefe('nur der Suchtext der Weiteren wirkt auf die Weiteren', sicher(() => {
  S.buchenSuche = 'xyz'; S.weitereSuche = 'sechs'; const g5 = el('div');
  zeichneWeitereKacheln(g5, [we, wn]); delete S.buchenSuche;
  return kacheln(g5).length === 1;
}));
pruefe('Klick auf eine Treffer-Kachel lädt deren Raster (buchbar wie jede Kachel)', sicher(() => {
  S.weitereSuche = 'sechs'; const g6 = el('div'); zeichneWeitereKacheln(g6, [we, wn]);
  const t = kacheln(g6)[0]; if (!t || !t.hoerer.click) return false;
  geladen.length = 0; t.hoerer.click(); return geladen.length === 1 && geladen[0] === '6';
}));

// ------------------------------------------------------------
console.log('zeichneWeitereLehrkraefte – eingeklappter Block mit eigener Suche');
const ziel = el('div');
S.weitereSuche = '';
versuch(() => zeichneWeitereLehrkraefte(ziel, [we, wn]));
const bl = ziel.kinder.find((x) => x.tag === 'details');
pruefe('ein Block (details), über block() – also eingeklappt, Zustand gemerkt', !!bl && bl.kennung === 'buchen-weitere');
pruefe('Titel nennt die Anzahl', !!bl && /\(2\)/.test(bl.titel));
const eingabe = bl ? alleKnoten(bl).find((x) => x.tag === 'input') : null;
pruefe('eigenes Suchfeld im Block', !!eingabe && eingabe.id === 'buchen-weitere-suche');
pruefe('anfangs keine Kachel im Block', !!bl && kacheln(bl).length === 0);
pruefe('Eingabe zeichnet die Treffer im Block', sicher(() => {
  if (!eingabe || !eingabe.hoerer.input) return false;
  eingabe.hoerer.input({ target: { value: 'sieben' } });
  return S.weitereSuche === 'sieben' && kacheln(bl).length === 1 && texte(kacheln(bl)[0]).includes('Sieben');
}));
S.weitereSuche = '';

// ------------------------------------------------------------
console.log('ansichtBuchen – Aufrufstellen (Stufe „richtige Stelle“, nicht Wirkung)');
const iW = ansichtRumpf.indexOf('zeichneWeitereLehrkraefte(ziel, weitere)');
const iR = ansichtRumpf.indexOf('zeichneRaster(ziel');
pruefe('ansichtBuchen() zeichnet die Weiteren', iW >= 0);
pruefe('… nur, wenn es welche gibt', /if \(weitere\.length > 0\) zeichneWeitereLehrkraefte\(ziel, weitere\)/.test(ansichtRumpf));
pruefe('… vor dem Raster (Raster erscheint unter der Wahl)', iW >= 0 && iR > iW);
pruefe('leere Gruppen 1–2 brechen nicht ab, wenn es Weitere gibt',
  /if \(alle\.length === 0 && weitere\.length === 0\)/.test(ansichtRumpf));
pruefe('Kindwechsel leert die Suche der Weiteren', /S\.weitereSuche = ''/.test(ansichtRumpf));

// ------------------------------------------------------------
// ansichtBuchen ausgeführt: Wie viele Suchfelder stehen da? Gezählt, nicht
// gesucht – ein zweites Feld neben dem richtigen fiele sonst nicht auf.
console.log('ansichtBuchen – ausgeführt: genau ein Suchfeld (Entscheidung Betreiber, Zug 3b)');
let ansichtBuchen = fehlt;
let buchenLehrerAbschnitte = fehlt;
if (abschnRumpf !== '') buchenLehrerAbschnitte = new Function('liste', abschnRumpf);
if (ansichtRumpf !== '' && alleRumpf !== '') {
  const buchenLehrerAlle = (liste) => new Function('buchenLehrerAbschnitte', 'liste', alleRumpf)(
    buchenLehrerAbschnitte, liste);
  const auswahl = () => { const d = el('label'); d.querySelector = () => ({ addEventListener() {} }); return d; };
  const leer = () => {};
  ansichtBuchen = (ziel) => new Function('S', 'el', 'feld', 'auswahl', 'zeigeHinweisText',
    'sprechtagWaehler', 'kontaktSatz', 'zeichneTermineKompakt', 'ladeLehrerListe',
    'buchenLehrerAlle', 'buchenLehrerAbschnitte', 'zeichneBuchenKacheln', 'zeichneWeitereLehrkraefte',
    'zeichneRaster', 'zeichne', 'ziel', ansichtRumpf)(
    S, el, feld, auswahl, leer, () => true, () => '', leer, leer,
    buchenLehrerAlle, buchenLehrerAbschnitte, zeichneBuchenKacheln, zeichneWeitereLehrkraefte, leer, leer, ziel);
}
const eingaben = (k) => alleKnoten(k).filter((x) => x.tag === 'input');
const ausserhalbBlock = (k) => [k].concat(...k.kinder.filter((x) => x.tag !== 'details').map(ausserhalbBlock));
Object.assign(S, { aktiverSprechtag: { id: 1, phase: 'phase2' }, user: { kinder: [{ id: 1, name: 'K' }] },
  kind: 1, raster: [], buchenSuche: 'zzz' });
S.lehrerListe = { eingeladen: [klEi], unterrichtend: [kl, un], sonderlehrer: [], weitere: [we, wn] };
const zb = el('div');
versuch(() => ansichtBuchen(zb));
pruefe('Phase 2: genau ein Suchfeld, und es ist das der Weiteren',
  eingaben(zb).length === 1 && eingaben(zb)[0].id === 'buchen-weitere-suche');
pruefe('Phase 2: alle Kacheln der Gruppen 1–2 stehen ungefiltert da',
  ausserhalbBlock(zb).filter((x) => x.klasse.split(' ').includes('buchen-kachel')).length === 3);
S.lehrerListe = { eingeladen: [klEi], unterrichtend: [], sonderlehrer: [], weitere: [], nur_eingeladene: true };
const zp = el('div');
versuch(() => ansichtBuchen(zp));
pruefe('ohne Weitere (Phase 1): kein Suchfeld, die Kachel steht da',
  eingaben(zp).length === 0 && kacheln(zp).length === 1);
delete S.buchenSuche;

// ------------------------------------------------------------
// v0.9.60, Vierteilung (E10-Nachtrag, Entscheidung Betreiber): Eingeladene /
// Klassenleitung + Unterrichtende / Sonderrollen / Weitere hinter der Suche.
// Die Sonderrollen beantworten eine andere Frage („An wen wende ich mich
// sonst?“) und stehen abgesetzt – an derselben Stelle wie bisher.
console.log('Vierteilung – Sonderrollen abgesetzt');
const so = zeile(9, 'So', 'Neun', { rolle: 'Beratungslehrkraft' });
const ab = sicher(() => buchenLehrerAbschnitte({ eingeladen: [klEi], unterrichtend: [kl, un], sonderlehrer: [so] }));
pruefe('buchenLehrerAbschnitte(): zwei Abschnitte – Eingeladene + Unterrichtende, dann Sonderrollen',
  Array.isArray(ab) && ab.length === 2
  && ab[0].lehrer.map((l) => l.kuerzel).join(',') === 'Ei,Kl,Un'
  && ab[1].art === 'sonderrollen' && ab[1].lehrer.map((l) => l.kuerzel).join(',') === 'So');
const abOhne = sicher(() => buchenLehrerAbschnitte({ eingeladen: [], unterrichtend: [un], sonderlehrer: [] }));
pruefe('… leere Abschnitte entfallen (kein leeres Gitter)',
  Array.isArray(abOhne) && abOhne.length === 1 && abOhne[0].art === 'unterricht');
const gitterVon = (z) => ausserhalbBlock(z).filter((x) => x.klasse.split(' ').includes('buchen-gitter'));
S.lehrerListe = { eingeladen: [klEi], unterrichtend: [kl, un], sonderlehrer: [so], weitere: [we] };
const zv = el('div');
versuch(() => ansichtBuchen(zv));
const gv = gitterVon(zv);
pruefe('ansichtBuchen(): Sonderrollen in eigenem Gitter NACH den Unterrichtenden, als solches gekennzeichnet',
  gv.length === 2 && kacheln(gv[0]).length === 3 && kacheln(gv[1]).length === 1
  && gv[1].klasse.split(' ').includes('buchen-sonderrollen')
  && !gv[0].klasse.split(' ').includes('buchen-sonderrollen')
  && texte(kacheln(gv[1])[0]).includes('Beratungslehrkraft'));
// Genau eine Darstellung: jede Lehrkraft einmal, nicht zusätzlich im
// gemeinsamen Gitter.
const alleKacheln = ausserhalbBlock(zv).filter((x) => x.klasse.split(' ').includes('buchen-kachel'));
pruefe('… jede Lehrkraft genau einmal (4 Kacheln außerhalb der Suche)',
  alleKacheln.length === 4 && new Set(alleKacheln.map((k) => texte(k).join('|'))).size === 4);
S.lehrerListe = { eingeladen: [], unterrichtend: [kl, un], sonderlehrer: [], weitere: [] };
const zo = el('div');
versuch(() => ansichtBuchen(zo));
pruefe('ohne Sonderrollen: ein Gitter, keins für Sonderrollen',
  gitterVon(zo).length === 1 && !gitterVon(zo)[0].klasse.includes('buchen-sonderrollen'));
S.lehrerListe = null;

console.log('Stil');
// Kommentare zuerst entfernen: Die Prüfung darf nicht auf die Beschreibung anschlagen.
const cssCode = css.replace(/\/\*[\s\S]*?\*\//g, '');
pruefe('kein Rand an der Klassenleitung – keine Regel für .klassenleitung', !/\.klassenleitung\b/.test(cssCode));

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
