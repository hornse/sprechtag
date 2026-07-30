// Lädt app.js in einem minimalen DOM-Ersatz und prüft, ob start()
// ohne ReferenceError durchläuft (der Fehler, der die Seite leer ließ).
const fs = require('fs');
let quelle = fs.readFileSync(__dirname + '/../frontend/app.js', 'utf8');

const elemente = {};
function fakeEl(tag) {
  return { tagName: tag, className: '', textContent: '', value: '', type: '',
    children: [], style: {}, classList: { add(){}, remove(){}, contains(){return false;} },
    appendChild(k){ this.children.push(k); return k; },
    addEventListener(){}, querySelector(){ return null; },
    querySelectorAll(){ return []; }, remove(){}, open: false };
}
global.document = {
  querySelector: (sel) => elemente[sel] || (elemente[sel] = fakeEl('div')),
  querySelectorAll: () => [],
  createElement: (t) => fakeEl(t),
};
global.window = {};
global.fetch = async () => ({ ok: true, json: async () => ({ angemeldet: false }) });
global.navigator = { clipboard: { writeText: async () => {} } };
global.confirm = () => true;
global.prompt = () => null;

try {
  eval(quelle);
  // start() läuft asynchron; kurz warten
  setTimeout(() => {
    console.log('✓ app.js vollständig ausgeführt, kein ReferenceError');
    process.exit(0);
  }, 300);
} catch (e) {
  console.log('✗ Fehler beim Ausführen: ' + e.message);
  process.exit(1);
}
