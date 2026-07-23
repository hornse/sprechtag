// Simuliert den Fehler: Werden Feldwerte vor dem Neuzeichnen gelesen?
// Minimaler DOM-Ersatz, nur was app.js für diesen Pfad braucht.
const felder = { 'sync-benutzer': 'adminho', 'sync-passwort': 'geheim' };
let gezeichnet = 0;

function wert(id) {
  // Nach dem Neuzeichnen sind die Felder weg -> leer
  return gezeichnet > 0 ? '' : (felder[id] || '');
}
function meldung() { gezeichnet++; }

// ---- ALTE Reihenfolge (fehlerhaft) ----
gezeichnet = 0;
meldung('läuft');
const alt = { benutzername: wert('sync-benutzer'), passwort: wert('sync-passwort') };

// ---- NEUE Reihenfolge (korrigiert) ----
gezeichnet = 0;
const neu = { benutzername: wert('sync-benutzer'), passwort: wert('sync-passwort') };
meldung('läuft');

console.log('alte Reihenfolge :', JSON.stringify(alt));
console.log('neue Reihenfolge :', JSON.stringify(neu));
const ok = neu.benutzername === 'adminho' && alt.benutzername === '';
console.log(ok ? '\n✓ Fehler reproduziert und behoben' : '\n✗ Test greift nicht');
process.exit(ok ? 0 : 1);
