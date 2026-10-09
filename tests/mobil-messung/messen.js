// ============================================================
// tests/mobil-messung/messen.js – fährt die Ansichten in WebKit mit
// iPhone-Nachbildung ab (Aufruf über messen.sh, nicht direkt).
//   PLAYWRIGHT_CORE  Pfad zum Paket playwright-core (messen.sh sucht es)
//   PORT, AUS        Server und Ablage für Bilder/Ergebnisse
//   GERAET           Gerät aus der Playwright-Liste (Vorgabe „iPhone 13“)
//   BREITE, HOEHE    überschreiben den Viewport des Geräts. Nötig fürs
//                    Querformat: „iPhone 13 landscape“ hat in Playwright
//                    750 px, Safari auf dem Gerät 844 px (über der Schwelle
//                    von 760 px – dort gilt die Rechneransicht).
//
// Warum WebKit und nicht mehr Chrome: Chrome (v0.9.58) meldete für alle
// Ansichten „Seite 390 px“, während die Seite auf dem iPhone überlief.
// WebKit mit isMobile/viewport-Meta zeigt denselben Fehler (590 px).
// Es ist trotzdem nicht Safari auf dem Gerät – E11.
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================
'use strict';
const fs = require('fs');
const { webkit, devices } = require(process.env.PLAYWRIGHT_CORE);

const GERAET = process.env.GERAET || 'iPhone 13';
const L = [
  'login gast login',
  'buchen eltern buchen kachel,suche,toast',
  'meine eltern buchen meine',
  'hilfe eltern hilfe',
  'lehrkraft lehrkraft lehrkraft unten',
  'einladungen lehrkraft einladungen offen',
  'mitt-l lehrkraft mitteilungen offen',
  'mitt-a admin mitteilungen offen',
  'aktiv admin admin-aktiv offen',
  'sprechtage admin admin-sprechtage offen',
  'daten admin admin-daten offen',
  'loginlog admin admin-loginlog offen',
  'texte admin admin-texte offen',
  'erinnerungen admin admin-erinnerungen offen',
  'anzeige admin admin-anzeige offen',
  'marke admin admin-marke offen',
];

(async () => {
  if (!devices[GERAET]) { console.log('ANGEHALTEN: Gerät „' + GERAET + '“ unbekannt.'); process.exit(1); }
  const b = await webkit.launch();
  const geraet = Object.assign({}, devices[GERAET]);
  if (process.env.BREITE) {
    geraet.viewport = { width: +process.env.BREITE, height: +(process.env.HOEHE || geraet.viewport.height) };
    geraet.screen = { width: geraet.viewport.width, height: geraet.viewport.height };
  }
  const ctx = await b.newContext(geraet);
  const basis = 'http://127.0.0.1:' + process.env.PORT;
  let fehlt = 0;
  console.log('Gerät: ' + GERAET + ', Viewport ' + geraet.viewport.width + '×' + geraet.viewport.height
    + ' (WebKit ' + b.version() + ')');
  const zeile = (id, text) => console.log(id.padEnd(14) + text);

  for (const z of L) {
    const [id, rolle, ansicht, aktion] = z.split(' ');
    const p = await ctx.newPage();
    try {
      await p.goto(basis + '/?rolle=' + rolle + '&aktion=' + (aktion || '') + '#/' + ansicht);
      await p.waitForFunction(() => window.__messung, null, { timeout: 20000 });
      const e = await p.evaluate(() => window.__messung);
      await p.screenshot({ path: process.env.AUS + '/' + id + '.png' });
      fs.writeFileSync(process.env.AUS + '/' + id + '.json', JSON.stringify(e, null, 1));
      // Ohne App oder Stilvorlage ist die Messung keine – Fehler, nicht „passt“.
      if (!e[0].app || !e[0].css) {
        zeile(id, 'VORAUSSETZUNG FEHLT (app ' + e[0].app + ', css ' + e[0].css + ')'); fehlt++; continue;
      }
      zeile(id, e.map((x) => x.stufe + ': Seite ' + x.seitenbreite + (x.seitenbreite > x.viewport ? ' > ' + x.viewport : '')
        + (x.verursacher.length ? ' VERURSACHER ' + x.verursacher.map((v) => v.el).join(', ') : '')
        + (x.tabellen.length ? ', Tabellen ' + x.tabellen.join(' ') : '')
        + (Object.keys(x.knapp).length ? ', knapp ' + Object.entries(x.knapp)
          .map(([k, v]) => k + ' ' + v.n + '× ' + (v.ueber > 0 ? 'ÜBER ' + v.ueber : v.ueber)).join('; ') : '')
        + (x.uebersicht ? ', Übersicht „' + x.uebersicht.titel + '“ (Ersatz-API: ' + x.uebersicht.mock + ')' : '')
        + (x.abschnitte ? ', Abschnitte ' + x.abschnitte.gitter.map((a) => a.art + ' ' + a.kacheln
          + (a.abstand === null ? '' : ' (Abstand ' + a.abstand + ' px)')).join(' / ')
          + ', Reihenabstand ' + x.abschnitte.reihe + ' px' : '')).join(' | '));
    } catch (err) {
      zeile(id, 'KEIN ERGEBNIS (' + String(err.message).split('\n')[0] + ')'); fehlt++;
    } finally { await p.close(); }
  }

  // Menü: echtes Tippen (Touch) neben das offene Menü – einmal oben auf
  // Höhe der Kopfleiste, einmal in der Mitte. Je ein frischer Aufruf, weil
  // das erste Tippen das Menü schon schließen soll.
  for (const hoehe of ['oben', 'mitte']) {
    const p = await ctx.newPage();
    try {
      await p.goto(basis + '/?rolle=eltern#/buchen');
      await p.waitForFunction(() => window.__messung, null, { timeout: 20000 });
      // Oberhalb der Schwelle (760 px) gibt es keine Kopfleiste und kein
      // überlagerndes Menü – dann entfällt die Prüfung, ausdrücklich.
      if (!(await p.isVisible('#mobil-menue'))) { zeile('menue-' + hoehe, 'entfällt (keine Kopfleiste in dieser Breite)'); continue; }
      await p.tap('#mobil-menue');
      await p.waitForTimeout(400);
      const vor = await p.evaluate(() => {
        const sl = document.querySelector('#seitenleiste');
        const r = sl.getBoundingClientRect();
        return { offen: sl.classList.contains('offen'), rechts: r.right, vw: document.documentElement.clientWidth,
          vh: innerHeight, seite: document.documentElement.scrollWidth };
      });
      const x = Math.min(vor.rechts + 40, vor.vw - 5);
      const y = hoehe === 'oben' ? 20 : Math.round(vor.vh / 2);
      await p.touchscreen.tap(x, y);
      await p.waitForTimeout(400);
      const nach = await p.evaluate(() => document.querySelector('#seitenleiste').classList.contains('offen'));
      zeile('menue-' + hoehe, 'offen ' + vor.offen + ', Leiste bis ' + Math.round(vor.rechts) + ' px, Tippen bei ('
        + x + ', ' + y + ') → ' + (nach ? 'BLEIBT OFFEN' : 'geschlossen') + ', Seite ' + vor.seite);
      if (!vor.offen) fehlt++;
    } catch (err) {
      zeile('menue-' + hoehe, 'KEIN ERGEBNIS (' + String(err.message).split('\n')[0] + ')'); fehlt++;
    } finally { await p.close(); }
  }
  await b.close();
  process.exit(fehlt ? 3 : 0);
})();
