// ============================================================
// tests/frontend_zustand_test.js
// Prüft das Muster, das zweimal zu Fehlern geführt hat:
// meldung() ruft zeichne() auf und ersetzt das gesamte DOM.
// Alles, was eine Aktion überleben muss, gehört in den Zustand –
// nicht in eine DOM-Referenz und nicht in ein Eingabefeld.
//
// Aufruf: node tests/frontend_zustand_test.js
// ============================================================
'use strict';

let fehler = 0;
function pruefe(name, ok) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + name);
  if (!ok) fehler++;
}

// --- Minimaler DOM-Ersatz ------------------------------------
// Neuzeichnen macht alle bisherigen Element-Referenzen wertlos
// und leert alle Eingabefelder (genau wie zeichne() im Original).
function neueWelt() {
  return {
    felder: {},          // id -> Wert (wird beim Zeichnen geleert)
    verwaist: new Set(), // Referenzen, die nicht mehr im Dokument hängen
    zustand: { bericht: null, eingaben: {} },
  };
}
function zeichne(welt, elementRefs) {
  for (const r of elementRefs) welt.verwaist.add(r);
  welt.felder = {};     // Eingabefelder sind nach dem Neuaufbau leer
}

console.log('Muster 1: Eingaben vor der Meldung sichern');
{
  // FALSCH: erst melden, dann lesen
  const w = neueWelt();
  w.felder['so-benutzer'] = 'adminho';
  zeichne(w, []);                       // meldung('läuft')
  const falsch = w.felder['so-benutzer'] || '';
  pruefe('alte Reihenfolge verliert die Eingabe', falsch === '');

  // RICHTIG: erst lesen (in den Zustand), dann melden
  const w2 = neueWelt();
  w2.felder['so-benutzer'] = 'adminho';
  w2.zustand.eingaben.benutzer = w2.felder['so-benutzer'];
  zeichne(w2, []);
  pruefe('neue Reihenfolge behält die Eingabe',
    w2.zustand.eingaben.benutzer === 'adminho');
  pruefe('Wert kann nach dem Zeichnen wieder angezeigt werden',
    w2.zustand.eingaben.benutzer !== '');
}

console.log('Muster 2: Ergebnis in den Zustand, nicht ins DOM');
{
  // FALSCH: Ergebnis in eine vor dem Zeichnen geholte Element-Referenz
  const w = neueWelt();
  const ausgabe = { id: 'pre1', inhalt: '' };
  zeichne(w, [ausgabe]);                 // meldung('läuft')
  ausgabe.inhalt = 'BERICHT';            // schreibt ins Nirgendwo
  pruefe('alte Variante: Element ist verwaist', w.verwaist.has(ausgabe));
  pruefe('alte Variante: Bericht nicht im Zustand', w.zustand.bericht === null);

  // RICHTIG: Ergebnis in den Zustand, Anzeige beim nächsten Zeichnen
  const w2 = neueWelt();
  const ausgabe2 = { id: 'pre1', inhalt: '' };
  zeichne(w2, [ausgabe2]);
  w2.zustand.bericht = 'BERICHT';        // in den Zustand
  zeichne(w2, []);                       // meldung('fertig') -> Neuaufbau
  pruefe('neue Variante: Bericht überlebt', w2.zustand.bericht === 'BERICHT');
  pruefe('neue Variante: Anzeige nach Neuaufbau möglich',
    w2.zustand.bericht !== null);
}

console.log('Muster 3: Passwörter gehören NICHT in den Zustand');
{
  // Sicherheitsregel: Benutzername darf gemerkt werden, das Passwort nicht.
  const zustand = { benutzer: 'adminho', von: '2026-06-15' };
  pruefe('kein Passwortfeld im Zustand',
    !Object.keys(zustand).some((k) => /pass|pw|kennwort/i.test(k)));
  pruefe('kein Passwortwert im Zustand',
    !JSON.stringify(zustand).includes('geheim'));
}

console.log(fehler === 0 ? '\nALLE TESTS GRÜN' : `\n${fehler} TEST(S) ROT`);
process.exit(fehler === 0 ? 0 : 1);
