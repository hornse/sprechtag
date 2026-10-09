// ============================================================
// tests/mobil-messung/stub.js – ersetzt fetch für /api/* durch mock.js
// und misst nach dem Laden (Aufruf über messen.sh → messen.js).
//   ?rolle=eltern|lehrkraft|admin|gast  #/ansicht
//   &aktion=kachel,suche,meine,offen,oben,unten (nacheinander)
// Das Ergebnis steht danach in window.__messung (messen.js liest es).
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
  const name = (e) => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '')
    + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).join('.') : '');

  // Verursacher der Seitenbreite: Gemessen wird die Seite selbst
  // (scrollWidth gegen die Breite des Viewports), nicht die Kästen der
  // Elemente. Der erste Verursacher (v0.9.58, WebKit) lag mit seinem Kasten
  // ganz im Bild – nur sein Inhalt ragte über –, und eine Suche nach
  // Kästen jenseits des Rands fand nichts.
  // Abstieg: Von body aus wird je Ebene JEDES Kind gesucht, dessen
  // Ausblenden die Seitenbreite senkt, und in jedes hinabgestiegen; die
  // tiefsten sind die Verursacher. Mehrere je Ebene sind kein Sonderfall:
  // Im Querformat (Rechneransicht) senkt das Ausblenden der Seitenleiste die
  // Breite ebenso wie das des zu breiten Inhalts daneben – wer nur das erste
  // meldet, nennt die Leiste und verschweigt das Feld. Danach werden die
  // gefundenen ausgeblendet und weitergesucht, bis die Seite passt.
  function verursacher() {
    const sw = () => document.documentElement.scrollWidth;
    const vw = document.documentElement.clientWidth;
    const gefunden = [], aus = [];
    const senkt = (c, vorher) => {
      const alt = c.style.display;
      c.style.display = 'none';
      const w = sw();
      c.style.display = alt;
      return w < vorher;
    };
    function abstieg(k, vorher, tiefe, blaetter) {
      const kinder = tiefe < 40 ? [...k.children].filter((c) => senkt(c, vorher)) : [];
      if (kinder.length === 0) { if (k !== document.body) blaetter.push(k); return; }
      for (const c of kinder.slice(0, 4)) abstieg(c, vorher, tiefe + 1, blaetter);
    }
    for (let runde = 0; runde < 10 && sw() > vw; runde++) {
      const vorher = sw(), blaetter = [];
      abstieg(document.body, vorher, 0, blaetter);
      // Kein einzelnes Kind senkt die Breite: als Fehler melden, nicht schweigen.
      if (blaetter.length === 0) { gefunden.push({ el: 'UNAUFFINDBAR', seiteMit: vorher }); break; }
      for (const e of blaetter) {
        const r = e.getBoundingClientRect();
        gefunden.push({ el: name(e), links: Math.round(r.left), rechts: Math.round(r.right),
          seiteMit: vorher, inhalt: e.scrollWidth });
        aus.push([e, e.style.display]);
        e.style.display = 'none';
      }
    }
    aus.reverse().forEach(([e, d]) => { e.style.display = d; });
    return gefunden;
  }

  function messen(stufe) {
    const vw = document.documentElement.clientWidth;
    // Voraussetzung: App und Stilvorlage sind geladen. Ohne sie misst das
    // Werkzeug eine leere Seite – und die ist nie zu breit.
    const kopf = document.querySelector('.mobil-leiste');
    const css = !!kopf && getComputedStyle(kopf).position === 'sticky';
    // app.js liegt in einer IIFE und legt keine globalen Namen an; ob es
    // lief, zeigt das DOM: Der Platzhalter aus index.html ist ersetzt.
    const ans = document.querySelector('#ansicht');
    const app = !!ans && !(ans.children.length === 1 && /^Wird geladen/.test(ans.textContent.trim()));
    const seitenbreite = document.documentElement.scrollWidth;
    return { stufe, app, css, skriptfehler: skriptfehler.slice(0, 3), viewport: vw, innerWidth,
      seitenbreite, seitenhoehe: document.documentElement.scrollHeight,
      verursacher: seitenbreite > vw ? verursacher() : [],
      tabellen: [...document.querySelectorAll('table')].map((t) => t.className + ':' + Math.round(t.getBoundingClientRect().width)
        + '/' + Math.round(t.parentElement.getBoundingClientRect().width)) };
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
      erg.push(messen('nach ' + a));
    }
    window.__messung = erg;
  });
})();
