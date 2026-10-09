// ============================================================
// tests/mobil-messung/stub.js – ersetzt fetch für /api/* durch mock.js
// und misst nach dem Laden (Aufruf über messen.sh).
//   ?rolle=eltern|lehrkraft|admin|gast  #/ansicht
//   &aktion=kachel,suche,meine,offen,oben,unten,menue (nacheinander)
// Übergänge werden abgeschaltet, damit der Endzustand gemessen wird.
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================
(function () {
  const q = new URLSearchParams(location.search);
  const rolle = q.get('rolle') || 'gast';
  const aktionen = (q.get('aktion') || '').split(',').filter(Boolean);
  const echt = window.fetch.bind(window);
  const skriptfehler = [];
  window.addEventListener('error', (e) => skriptfehler.push(String(e.message)));
  window.fetch = async (pfad, opt) => {
    pfad = String(pfad);
    if (!pfad.startsWith('/api/')) return echt(pfad, opt);
    const r = window.mockAntwort(rolle, pfad, (opt && opt.method) || 'GET');
    return new Response(JSON.stringify(r.json), { status: r.status, headers: { 'Content-Type': 'application/json' } });
  };
  const warte = (ms) => new Promise((f) => setTimeout(f, ms));
  function messen(stufe) {
    const vw = document.documentElement.clientWidth;
    // Voraussetzung: App und Stilvorlage sind geladen. Ohne sie misst das
    // Werkzeug eine leere Seite – und die ragt nirgends über.
    const kopf = document.querySelector('.mobil-leiste');
    const css = !!kopf && getComputedStyle(kopf).position === 'sticky';
    const ueber = [];
    for (const e of document.querySelectorAll('body *')) {
      if (e.closest('#seitenleiste') && !document.querySelector('#seitenleiste.offen')) continue;
      const cs = getComputedStyle(e);
      if (cs.display === 'none' || cs.visibility === 'hidden') continue;
      const r = e.getBoundingClientRect();
      if (r.width === 0 && r.height === 0) continue;
      // in einem eigenen Rollbereich? Dann zählt dessen Rand, nicht der Inhalt.
      let p = e.parentElement, imRoller = false;
      while (p && p !== document.body) {
        const ox = getComputedStyle(p).overflowX;
        if (ox === 'auto' || ox === 'scroll' || ox === 'hidden') { imRoller = true; break; }
        p = p.parentElement;
      }
      if (imRoller) continue;
      if (r.right > vw + 0.5 || r.left < -0.5) {
        ueber.push({ el: e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).join('.') : ''),
          links: Math.round(r.left), rechts: Math.round(r.right), text: (e.textContent || '').trim().slice(0, 30) });
      }
    }
    // Nur die äußersten melden: Kinder eines gemeldeten Elements fallen weg.
    // app.js liegt in einer IIFE und legt keine globalen Namen an; ob es
    // lief, zeigt das DOM: Der Platzhalter aus index.html ist ersetzt.
    const ans = document.querySelector('#ansicht');
    const app = !!ans && !(ans.children.length === 1 && /^Wird geladen/.test(ans.textContent.trim()));
    return { stufe, app, css, skriptfehler: skriptfehler.slice(0, 3), viewport: vw, seitenbreite: document.documentElement.scrollWidth,
      seitenhoehe: document.documentElement.scrollHeight,
      tabellen: [...document.querySelectorAll('table')].map((t) => t.className + ':' + Math.round(t.getBoundingClientRect().width)
        + '/' + Math.round(t.parentElement.getBoundingClientRect().width)),
      ueberstehend: ueber.length, beispiele: ueber.slice(0, 12) };
  }
  // Übergänge aus: Die Messung soll den Endzustand sehen, nicht die Animation.
  const st = document.createElement('style'); st.textContent = '*{transition:none!important}';
  document.head.appendChild(st);
  window.addEventListener('load', async () => {
    await warte(1500);
    const erg = [messen('geladen')];
    for (const a of aktionen) {
      if (a === 'kachel') { const k = document.querySelector('.buchen-kachel'); if (k) k.click(); await warte(800); }
      if (a === 'suche') { document.querySelectorAll('details').forEach((d) => { d.open = true; });
        const i = document.querySelector('#buchen-weitere-suche');
        if (i) { i.value = 'e'; i.dispatchEvent(new Event('input')); } await warte(300); }
      if (a === 'meine') { const b = [...document.querySelectorAll('#navigation .nv')].find((x) => /Meine Termine/.test(x.textContent));
        if (b) b.click(); await warte(800); }
      if (a === 'oben') { window.scrollTo(0, 0); await warte(100); }
      if (a === 'unten') { window.scrollTo(0, document.documentElement.scrollHeight); await warte(300); }
      if (a === 'offen') { document.querySelectorAll('details').forEach((d) => { d.open = true; }); await warte(300); }
      if (a === 'menue') { document.querySelector('#mobil-menue').click(); await warte(500);
        const sl = document.querySelector('#seitenleiste').getBoundingClientRect();
        const ov = document.querySelector('#menue-overlay');
        const mitte = document.elementFromPoint(Math.min(sl.right + 40, innerWidth - 5), innerHeight / 2);
        const oben = document.elementFromPoint(Math.min(sl.right + 40, innerWidth - 5), 20);
        erg.push({ stufe: 'menue-offen', leiste: { links: Math.round(sl.left), rechts: Math.round(sl.right), hoehe: Math.round(sl.height) },
          overlaySichtbar: getComputedStyle(ov).display, trefferNebenLeisteMitte: mitte && (mitte.id || mitte.className),
          trefferNebenLeisteOben: oben && (oben.id || oben.className) });
        if (mitte) mitte.click(); await warte(300);
        erg.push({ stufe: 'nach-tippen-daneben', offen: document.querySelector('#seitenleiste').classList.contains('offen') });
        continue; }
      erg.push(messen('nach ' + a));
    }
    await echt('/__ergebnis?id=' + (q.get('id') || 'x'), { method: 'POST', body: JSON.stringify(erg) });
  });
})();
