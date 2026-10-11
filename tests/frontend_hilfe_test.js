// ============================================================
// tests/frontend_hilfe_test.js
// Prüft v0.9.17: Hilfe-Seite ohne Anmeldung, Links im Login-Screen und in
// der Sidebar – seit v0.9.89 gegliedert nach Rollen (Betreiber).
//
// v0.9.89: ansichtHilfe wird AUSGEFÜHRT (Ersatzknoten) und am Gezeigten
// geprüft: welche Abschnitte in welcher Reihenfolge, welche Sprungmarken,
// welche zuerst. Die übrigen Stellen werden im Rumpf ihrer Funktion
// gesucht, nicht in der Datei. Bis v0.9.88 suchte die Suite in ganz app.js
// und hielt die alte Gliederung (Schnellanleitung, Handbuch, FAQ) fest;
// die Rollen-Überschriften fand sie überall
// (docs/BEFUND-2026-10-11-pruefungen-in-der-datei.md).
//
// v0.9.90 (Schritt 2, Inhalt): Was die Hilfe über das Verhalten sagt, wird
// wo möglich mit dem Code verbunden, der es tut – die PHP-Funktion wird
// ausgeführt (bu_sperre_text, bu_kind_termin_text, slot_storno_erlaubt).
// Ändert jemand den Code, wird die Hilfe-Prüfung rot, statt dass die Hilfe
// still falsch wird. Jedes Zitat „…“ der Hilfe muss als Text im Code
// stehen, außerhalb der Hilfe.
//
// Aufruf: node tests/frontend_hilfe_test.js
// ============================================================
'use strict';
const fs = require('fs');
const path = require('path');

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

const { execFileSync } = require('child_process');
const js  = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'app.js'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'style.css'), 'utf8');
const rumpf = (kopf) => require('./rumpf.js').rumpf(js, kopf);

// ---- ansichtHilfe ausführen ----
function knoten(tag, klasse, text) {
  return { tag, klasse: klasse || '', text: text == null ? '' : String(text), kinder: [], id: '', href: '',
    appendChild(k) { this.kinder.push(k); return k; }, addEventListener() {}, setAttribute() {},
    set innerHTML(h) { this.html = h; }, set textContent(t) { this.text = String(t); },
    set className(c) { this.klasse = c; }, get className() { return this.klasse; } };
}
const alle = (k) => [k].concat(...(k.kinder || []).map(alle));
const hilfeRumpf = rumpf('function ansichtHilfe(');
const teileQuelle = (js.match(/^const HILFE_TEILE = (\[[\s\S]*?\n\]);/m) || [])[1];
let TEILE = null;
try { TEILE = teileQuelle ? new Function('return ' + teileQuelle)() : null; } catch (e) { TEILE = null; }
const sprungRumpf = rumpf('function hilfeSprungmarken(');
const sprungmarken = (rolle) => new Function('HILFE_TEILE', 'rolle', sprungRumpf)(TEILE, rolle);
const listeRumpf = rumpf('function hilfeListe(');
const dokument = { createElement: (t) => knoten(t) };
const hilfeListe = (punkte) => new Function('document', 'punkte', listeRumpf)(dokument, punkte);
function hilfe(user) {
  const ziel = knoten('main');
  const S = { user, hilfeZusatz: '', offeneBloecke: {} };
  const sektion = (t) => { const s = knoten('section', 'sektion'); s.kinder.push(knoten('h3', null, t)); return s; };
  const namen = ['S', 'el', 'document', 'sektion', 'block', 'knopf', 'api', 'wechsleAnsicht', 'zeichne',
    'kontaktSatz', 'hilfeListe', 'hilfeSchluessel', 'datenschutzAbsaetze', 'HILFE_TEILE', 'hilfeSprungmarken'];
  const werte = [S, knoten, dokument, sektion,
    (k, t) => knoten('details', null, t), (t) => knoten('button', null, t), () => Promise.resolve({}),
    () => {}, () => {}, (v) => v, hilfeListe, (t) => t, () => ['A', 'B'],
    TEILE, sprungmarken];
  try { new Function(...namen, 'ziel', hilfeRumpf)(...werte, ziel); }
  catch (e) { console.log('    (ansichtHilfe: ' + e.message + ')'); return null; }
  return ziel;
}
const ohne = hilfe(null);
const abschnitte = ohne ? ohne.kinder.filter((k) => k.tag === 'section') : [];
const titel = abschnitte.map((s) => s.kinder[0].text);

pruefe('Hilfe-Ansicht definiert und ausführbar', hilfeRumpf !== '' && !!ohne);
pruefe('in der Ansichten-Map', /\bhilfe: ansichtHilfe,/.test(rumpf('function zeichne()')));
pruefe('Abschnitte nach Rollen, in dieser Reihenfolge, je einmal',
  JSON.stringify(titel) === JSON.stringify(['Für Erziehungsberechtigte',
    'Für volljährige Schülerinnen und Schüler', 'Für Lehrkräfte',
    'Für die Administration', 'Häufige Fragen', 'Datenschutz']));
const abschnitt = (id) => abschnitte.find((s) => s.id === id) || null;
pruefe('jede Rolle beginnt mit ihrer Schritt-Liste (Eltern, Lehrkräfte, Administration)',
  ['hilfe-eltern', 'hilfe-lehrkraefte', 'hilfe-verwaltung'].every((id) => {
    const s = abschnitt(id); return s && s.kinder[1] && s.kinder[1].klasse === 'hilfe-liste'; }));
const marken = ohne ? alle(ohne).filter((k) => k.klasse === 'hilfe-sprung') : [];
pruefe('eine Sprungmarke je Abschnitt, jede auf einen vorhandenen Abschnitt',
  marken.length === abschnitte.length && abschnitte.length === 6
  && marken.every((m) => abschnitte.some((s) => '#' + s.id === m.href)));
// Allen alles, die eigene Rolle vorn (Betreiber, v0.9.89).
const erste = (rolle) => { const z = hilfe(rolle === null ? null : { rolle });
  const m = z ? alle(z).filter((k) => k.klasse === 'hilfe-sprung') : [];
  return m.length === 6 ? m[0].href : 'FEHLT'; };
pruefe('Sprungmarke der eigenen Rolle zuerst, keine fehlt',
  erste('eltern') === '#hilfe-eltern' && erste('schueler') === '#hilfe-schueler'
  && erste('lehrkraft') === '#hilfe-lehrkraefte' && erste('admin') === '#hilfe-verwaltung'
  && erste(null) === '#hilfe-eltern');
const login = rumpf('function ansichtLogin(ziel)');
pruefe('Login-Screen verlinkt Hilfe',
  login.includes("el('p', 'login-hilfe')") && login.includes("wechsleAnsicht('hilfe')"));
pruefe('Sidebar hat Hilfe-Eintrag', rumpf('function zeichneNavigation()').includes("navKnopf('hilfe', 'Hilfe'"));
const knoepfe = (z) => (z ? alle(z).filter((k) => k.tag === 'button' && k.text === 'Zur Anmeldung').length : -1);
pruefe('ohne Anmeldung erreichbar: „Zur Anmeldung“ nur ohne Anmeldung',
  knoepfe(ohne) === 1 && knoepfe(hilfe({ rolle: 'eltern' })) === 0);
pruefe('Hilfe-CSS vorhanden (Regeln .hilfe-nav und .hilfe-liste)',
  /^\.hilfe-nav \{[^}]*display: flex/m.test(css) && /^\.hilfe-liste \{/m.test(css));

// ============================================================
// Schritt 2: Inhalt (v0.9.90, H1–H12 aus docs/HILFE-ABGLEICH-2026-10-09.md)
// ============================================================
const alleKn = (k) => (k ? alle(k) : []);
const textVon = (k) => alleKn(k).map((x) => x.text).filter(Boolean).join('\n');
const eltern = textVon(abschnitt('hilfe-eltern'));
const schueler = textVon(abschnitt('hilfe-schueler'));
const lehrk = textVon(abschnitt('hilfe-lehrkraefte'));
const verw = textVon(abschnitt('hilfe-verwaltung'));
const faqK = abschnitt('hilfe-faq');
const faq = textVon(faqK);
const fragen = alleKn(faqK).filter((k) => k.tag === 'details').map((k) => k.text);
const ganz = textVon(ohne);

// PHP-Funktion aus backend/api/slots.php ausführen – derselbe Code wie im
// Betrieb, ohne Datenbank. Liefert die JSON-Ausgabe oder null.
const slots = fs.readFileSync(path.join(__dirname, '..', 'backend', 'api', 'slots.php'), 'utf8');
const prumpf = (kopf) => require('./rumpf.js').rumpf(slots, kopf);
function php(kopf, aufruf) {
  const r = prumpf(kopf);
  if (r === '') return null;
  try {
    return JSON.parse(execFileSync('php', ['-r', kopf + ' {' + r + '}\necho json_encode(' + aufruf + ');'],
      { encoding: 'utf8' }));
  } catch (e) { console.log('    (php: ' + String(e.message).split('\n')[0] + ')'); return null; }
}

pruefe('Eltern: erst die Kachel, dann eine freie Uhrzeit – gebucht ohne Rückfrage; Feld „Optionaler Hinweis an die Lehrkraft“ (H1)',
  /Kachel der Lehrkraft antippen/.test(eltern) && /„Optionaler Hinweis an die Lehrkraft“/.test(eltern)
  && /freie Uhrzeit antippen – der Termin ist damit gebucht, ohne weitere Rückfrage/.test(eltern));
pruefe('Eltern: „Meine Termine“ oben auf der Buchungsseite, dort absagen (H3)',
  /Oben auf der Seite zeigt „Meine Termine“ die gebuchten Termine/.test(eltern));

// Reihenfolge der Kacheln: aus buchenLehrerAbschnitte() ausgeführt, nicht
// abgeschrieben – zuerst Eingeladene, dann Unterrichtende, dann Sonderrollen.
let kachelFolge = null;
try {
  kachelFolge = new Function('liste', rumpf('function buchenLehrerAbschnitte('))(
    { eingeladen: ['E'], unterrichtend: ['U'], sonderlehrer: ['S'] }).flatMap((a) => a.lehrer).join('');
} catch (e) { kachelFolge = null; }
const pos = (t) => eltern.indexOf(t);
pruefe('Eltern: Gliederung der Kacheln in der Reihenfolge der Seite (H2, E10; Reihenfolge aus buchenLehrerAbschnitte ausgeführt)',
  kachelFolge === 'EUS' && pos('„hat Sie eingeladen“') >= 0
  && pos('„hat Sie eingeladen“') < pos('„Klassenleitung“')
  && pos('„Klassenleitung“') < pos('besonderer Aufgabe')
  && pos('besonderer Aufgabe') < pos('„Weitere Lehrkräfte suchen“')
  && /Ab Phase 2 finden Sie alle weiteren teilnehmenden Lehrkräfte unter „Weitere Lehrkräfte suchen“/.test(eltern));
pruefe('Phase 1: nur bei der Lehrkraft, die eingeladen hat (H7)',
  /In Phase 1 können Sie nur bei einer Lehrkraft buchen, die Sie eingeladen hat/.test(eltern)
  && !/nur Erziehungsberechtigte buchen, die von einer Lehrkraft eingeladen/.test(ganz));

const storno = php('function slot_storno_erlaubt(array $buchung, string $rolle, int $eigeneUserId): array',
  "[slot_storno_erlaubt(['eltern_user_id' => 7, 'phase' => 'phase1'], 'eltern', 7)['ok'],"
  + " slot_storno_erlaubt(['eltern_user_id' => 7, 'phase' => 'phase2'], 'eltern', 7)['ok'],"
  + " slot_storno_erlaubt(['eltern_user_id' => 7, 'phase' => 'phase1'], 'lehrkraft', 9)['ok']]");
pruefe('Absagen: Termin aus Phase 1 auf Einladung sagt nur die Lehrkraft ab – wie slot_storno_erlaubt (H4, H10; ausgeführt)',
  JSON.stringify(storno) === '[false,true,true]'
  && /Einen Termin, der in Phase 1 auf Einladung entstanden ist, kann nur die Lehrkraft absagen/.test(eltern)
  && fragen.includes('Warum kann ich einen Termin nicht absagen?')
  && /in Phase 1 auf Einladung entstanden; absagen kann ihn nur die Lehrkraft/.test(faq));
pruefe('keine Zusage mehr, jeder Termin lasse sich absagen (H4, H10)',
  ganz !== '' && !/jeder Termin absagen/.test(ganz) && !/alle Buchungen und kann sie wieder absagen/.test(ganz));

const kt = php('function bu_kind_termin_text(array $termin, string $wer, int $konto): string',
  "[bu_kind_termin_text(['slot_beginn' => '16:40:00', 'eltern_user_id' => 7], 'eltern', 7),"
  + " bu_kind_termin_text(['slot_beginn' => '16:40:00', 'eltern_user_id' => 7], 'eltern', 8)]");
pruefe('E24: ein Termin je Kind und Lehrkraft; Uhrzeit nur beim eigenen Konto – wie bu_kind_termin_text (ausgeführt)',
  Array.isArray(kt) && kt[0].includes('16:40') && !kt[1].includes('16:40') && /anderes Konto/.test(kt[1])
  && /Je Kind gibt es bei jeder Lehrkraft nur einen Termin/.test(eltern)
  && /die Uhrzeit aber nur bei einem Termin des eigenen Kontos/.test(eltern));

pruefe('Volljährige Schülerinnen und Schüler: Freischaltung je Gruppe, angemeldet in beiden Fällen (E15)',
  /wenn die Schule es für Ihre WebUntis-Benutzergruppe freigeschaltet hat/.test(schueler)
  && /Angemeldet sind Sie in beiden Fällen/.test(schueler));
const sperre = php('function bu_sperre_text(string $grund): string',
  "[bu_sperre_text('gruppe_unbekannt'), bu_sperre_text('gruppe_nicht_zugelassen')]");
const zitatSperre = (schueler.match(/steht auf der Buchungsseite „([^“]+)“/) || [])[1] || '';
pruefe('E15: der zitierte Satz ist der Anfang beider Sperr-Meldungen aus bu_sperre_text (ausgeführt)',
  zitatSperre.length > 10 && Array.isArray(sperre) && sperre.length === 2
  && sperre.every((t) => t.startsWith(zitatSperre)));

pruefe('Lehrkräfte: Einladungen in jeder Phase, über die Suche (H5)',
  /Unter „Einladungen“ Kinder suchen \(Name oder Klasse, „Suchen“\)/.test(lehrk)
  && /in der Vorbereitung vorbereitet und beim Anlegen versendet/.test(lehrk)
  && /in Phase 2 als gezielte Erinnerung/.test(lehrk)
  && !/In Phase 1 können Eltern per/.test(ganz));
pruefe('Lehrkräfte: stellvertretend – Kind suchen, Zeit antippen, alle Erziehungsberechtigten benachrichtigt (H6)',
  /das Kind im eigenen Zeitraster suchen und einen freien Zeitpunkt antippen; alle Erziehungsberechtigten werden benachrichtigt/.test(lehrk));
pruefe('Krankheitsausfall: freigegebener Wortlaut, kein „automatisch“ (H8)',
  /Die betroffenen Erziehungsberechtigten werden benachrichtigt; ist die Anmeldung der Verwaltung abgelaufen, bleibt die Absage gespeichert und wird nach erneuter Anmeldung verschickt\./.test(verw)
  && !/automatisch benachrichtigt/.test(ganz));
pruefe('Verwaltung: mit dem Lehrerkonto; Administrationskonto ohne Elternnachrichten, nur einmal für die Gruppen (E23)',
  /Die Verwaltung mit dem eigenen Lehrerkonto benutzen/.test(verw)
  && /Mit dem WebUntis-Administrationskonto gehen keine Nachrichten an Eltern hinaus/.test(verw)
  && /Es wird nur einmal gebraucht, um unter „Volljährige Schüler“ die Gruppen auszuwählen/.test(verw));
pruefe('Verwaltung: unter „Volljährige Schüler“ die Gruppen wählen (E15)',
  /Unter „Volljährige Schüler“ werden die WebUntis-Gruppen gewählt, deren Mitglieder selbst buchen dürfen/.test(verw));
pruefe('Verwaltung: gespeicherte, nicht verschickte Mitteilungen – „Jetzt senden“',
  /Mitteilungen gespeichert, aber noch nicht verschickt sind; „Jetzt senden“ holt das nach/.test(verw));
pruefe('FAQ: „Ich finde eine Lehrkraft nicht.“ (H11)',
  fragen.includes('Ich finde eine Lehrkraft nicht.')
  && /In Phase 1 erscheinen nur Lehrkräfte, die Sie eingeladen haben/.test(faq));
pruefe('FAQ: keine freien Termine – in Phase 1 ohne Einladung keine Kachel (H12)',
  /Läuft Phase 1 und liegt für Ihr Kind keine Einladung vor, erscheint keine Kachel/.test(faq));
pruefe('FAQ: Karten auf dem Telefon – einmal, nicht in jedem Teil',
  fragen.includes('Auf dem Telefon sieht die Seite anders aus.')
  && (ganz.match(/werden Tabellen zu Karten/g) || []).length === 1);
pruefe('FAQ ohne Doppelung zur Schritt-Liste: kein Ablauf des Absagens, keine Lehrkraft-Frage zum stellvertretenden Buchen',
  fragen.length >= 8 && !fragen.includes('Wie sage ich einen Termin ab?')
  && !fragen.some((f) => /^Als Lehrkraft:/.test(f)));

// Jedes Zitat „…“ der Hilfe (ohne den Datenschutz-Teil) muss als Text im
// Code stehen – in app.js außerhalb von ansichtHilfe und
// datenschutzAbsaetze, oder im Backend. Kommentare zählen nicht (rumpf
// entfernt sie), zusammengesetzte Zeichenketten werden verbunden.
// Das Zitat muss an Grenzen stehen: davor Anfang der Zeichenkette oder „,
// danach ihr Ende, “, „(“, „.“, „:“ oder „,“. Ein Teilstück zählt nicht
// als Ganzes – „Termin absagen“ steht nicht schon in „Diesen Termin
// absagen“ (HZ7, v0.9.90; Art 4 im Befund vom 11.10.).
const ohneKommentar = (q) => require('./rumpf.js').rumpf('function __alles__() {\n' + q + '\n}', 'function __alles__(');
const verbinden = (q) => q.replace(/'\s*[+.]\s*'/g, '').replace(/"\s*[+.]\s*"/g, '');
let code = ohneKommentar(js);
for (const k of ['function ansichtHilfe(', 'function datenschutzAbsaetze()']) code = code.split(rumpf(k)).join('');
code = verbinden(code);
const phpDir = path.join(__dirname, '..', 'backend', 'api');
for (const f of fs.readdirSync(phpDir).filter((f) => f.endsWith('.php'))) {
  code += '\n' + verbinden(ohneKommentar(fs.readFileSync(path.join(phpDir, f), 'utf8')));
}
const hilfeOhneDs = alleKn(ohne).filter((k) => !alleKn(abschnitt('hilfe-datenschutz')).includes(k))
  .map((k) => k.text).join('\n');
const zitate = [...new Set([...hilfeOhneDs.matchAll(/„([^“"]+)[“"]/g)].map((m) => m[1]))];
const imCode = (z) => new RegExp('[\'"„]' + z.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  + '(?:[\'"“(.:,]| \\()').test(code);
const fehlen = zitate.filter((z) => !imCode(z));
if (fehlen.length) console.log('    (nicht im Code: ' + fehlen.map((z) => '„' + z + '“').join(', ') + ')');
pruefe('jedes Zitat der Hilfe steht als Text im Code, außerhalb der Hilfe, an Grenzen (mind. 15 Zitate)',
  code.length > 10000 && zitate.length >= 15 && fehlen.length === 0);

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : '\n' + fehler + ' FEHLER');
process.exit(fehler === 0 ? 0 : 1);
