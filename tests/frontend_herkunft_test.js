// ============================================================
// tests/frontend_herkunft_test.js – die Oberfläche schickt die Kopfzeile
// X-Angefordert-Von: sprechtag bei jedem Aufruf (v0.9.105, E33)
//
// Der Erfolgsfall der Herkunftsprüfung (tests/run_herkunft.php): Der Server
// weist jeden Aufruf außer GET ohne die Kopfzeile ab. Schickt die Oberfläche
// sie nicht, ist die Anwendung für alle unbenutzbar – das ist in der Reihe
// einmal geschehen (koordination M22). Ausgeführt wird api() aus app.js mit
// einem fetch-Ersatz, der mitschreibt.
//
// Statisch: api() ist die einzige fetch()-Stelle mit einer Methode; die
// beiden anderen (Symbolsatz, Versionsanzeige) sind GET ohne Körper.
//
// Aufruf: node tests/frontend_herkunft_test.js
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
const apiRumpf = rumpf('async function api(pfad, optionen = {})');

(async () => {
  const aufrufe = [];
  const fetchErsatz = async (pfad, o) => {
    aufrufe.push({ pfad, o });
    return { ok: true, status: 200, json: async () => ({ ok: true }) };
  };
  let api = null;
  try {
    api = new Function('fetch', 'return async function (pfad, optionen = {}) {' + apiRumpf + '};')(fetchErsatz);
  } catch (e) { console.log('    (Ausnahme: ' + e.message + ')'); }
  pruefe('Voraussetzung: api() gefunden und ausführbar', apiRumpf !== '' && api !== null);
  const k = (i) => (aufrufe[i] && aufrufe[i].o && aufrufe[i].o.headers) || {};
  try {
    await api('/api/sprechtage', { method: 'POST', body: { name: 'Erfunden' } });
    await api('/api/mitteilungen/3', { method: 'DELETE' });
    await api('/api/sprechtage');
  } catch (e) { console.log('    (Ausnahme: ' + e.message + ')'); }
  pruefe('POST mit Körper: X-Angefordert-Von: sprechtag und Content-Type application/json',
    k(0)['X-Angefordert-Von'] === 'sprechtag' && k(0)['Content-Type'] === 'application/json');
  pruefe('DELETE ohne Körper: X-Angefordert-Von: sprechtag, kein Content-Type',
    k(1)['X-Angefordert-Von'] === 'sprechtag' && !('Content-Type' in k(1)) && (aufrufe[1] || {}).o.method === 'DELETE');
  pruefe('GET: die Kopfzeile schadet nicht und ist dabei (ein Ort, keine Fallunterscheidung)',
    k(2)['X-Angefordert-Von'] === 'sprechtag' && (aufrufe[2] || {}).o.method === 'GET');

  // Statisch, ohne Kommentare und Zeichenketten gezählt über rumpf.js-Logik:
  // jede fetch(-Stelle außerhalb von api() trägt keine Methode.
  const stellen = [];
  const re = /fetch\(/g;
  let m;
  while ((m = re.exec(js)) !== null) {
    const zeile = js.slice(js.lastIndexOf('\n', m.index) + 1, js.indexOf('\n', m.index));
    if (/^\s*(\/\/|\*)/.test(zeile)) continue;
    stellen.push(js.slice(m.index, js.indexOf(')', js.indexOf('}', m.index) + 1) + 1));
  }
  const ohneApi = stellen.filter((s) => !s.startsWith('fetch(pfad, {\n    method: optionen.method'));
  pruefe('genau drei fetch()-Stellen, und nur api() setzt eine Methode – ' + stellen.length + ' gefunden',
    stellen.length === 3 && ohneApi.length === 2 && ohneApi.every((s) => !/method\s*:/.test(s)));

  console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : `\n${fehler} TEST(S) ROT`);
  process.exit(fehler === 0 ? 0 : 1);
})();
