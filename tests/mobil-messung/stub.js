// ============================================================
// tests/mobil-messung/stub.js – ersetzt fetch für /api/* durch mock.js
// und misst nach dem Laden (Aufruf über messen.sh → messen.js).
//   ?rolle=eltern|lehrkraft|admin|gast  #/ansicht
//   &aktion=kachel,suche,meine,offen,oben,unten,toast (nacheinander)
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

  // Knappe Stellen (v0.9.60): Was rechnerisch knapp ist, wird gemessen,
  // auch wo es die Seite nicht verbreitert – in einem Block mit
  // overflow: hidden wird Überstand abgeschnitten statt geschoben, und eine
  // feste Meldung ragt links aus dem Bild, ohne dass sich etwas rollen lässt.
  // Je Selektor: Anzahl sichtbarer Elemente und der größte Überstand in px
  // (Inhalt über den eigenen Kasten, Kasten über den Inhaltsbereich des
  // Elternelements bzw. bei position: fixed über den Viewport). > 0 = über.
  const KNAPP = ['.schueler-liste', '.zeile > label', '#toast', '.aktionen',
    'input[type=file]', '.raum-zelle'];
  function knapp() {
    const vw = document.documentElement.clientWidth;
    const aus = {};
    for (const sel of KNAPP) {
      let n = 0, ueber = -Infinity;
      for (const e of document.querySelectorAll(sel)) {
        const cs = getComputedStyle(e);
        const r = e.getBoundingClientRect();
        if (cs.display === 'none' || (r.width === 0 && r.height === 0)) continue;
        n++;
        let links = 0, rechts = vw;
        if (cs.position !== 'fixed' && e.parentElement) {
          const p = e.parentElement, pr = p.getBoundingClientRect(), pc = getComputedStyle(p);
          links = pr.left + parseFloat(pc.borderLeftWidth) + parseFloat(pc.paddingLeft);
          rechts = pr.right - parseFloat(pc.borderRightWidth) - parseFloat(pc.paddingRight);
        }
        ueber = Math.max(ueber, e.scrollWidth - e.clientWidth, r.right - rechts, links - r.left);
      }
      if (n) aus[sel] = { n, ueber: Math.round(ueber) };
    }
    return aus;
  }
  // Auskunft der Terminübersicht (v0.9.60): Titel gegen die Zahl der
  // Termine, die die Ersatz-API für den gewählten Sprechtag liefert.
  function uebersicht() {
    const s = [...document.querySelectorAll('#ansicht details.block > summary')]
      .find((x) => /^Meine Termine/.test(x.textContent.trim()));
    if (!s) return null;
    const w = document.querySelector('#sprechtag-wahl');
    const r = w ? window.mockAntwort(rolle, '/api/buchungen?sprechtag=' + w.value, 'GET') : null;
    return { titel: s.textContent.trim(), mock: r && r.json.buchungen ? r.json.buchungen.length : null };
  }

  // Abschnitte der Buchungskacheln (v0.9.61, Vierteilung): Gitter außerhalb
  // eines Blocks, je Gitter Kachelzahl und senkrechter Abstand zum vorigen –
  // dazu der Abstand zweier Reihen IM Gitter zum Vergleich.
  function abschnitte() {
    const g = [...document.querySelectorAll('#ansicht .buchen-gitter')].filter((x) => !x.closest('details'));
    if (g.length === 0) return null;
    const reihe = parseFloat(getComputedStyle(g[0]).rowGap) || 0;
    return { reihe: Math.round(reihe), gitter: g.map((x, i) => ({
      art: x.classList.contains('buchen-sonderrollen') ? 'sonderrollen' : 'unterricht',
      kacheln: x.querySelectorAll('.buchen-kachel').length,
      abstand: i === 0 ? null : Math.round(x.getBoundingClientRect().top - g[i - 1].getBoundingClientRect().bottom) })) };
  }

  // Fugen zwischen Abschnitten (v0.9.62): In jedem Abschnittsbehälter
  // (#ansicht, .sektion, offener .block) der senkrechte Abstand zwischen
  // je zwei sichtbaren Nachbarn – gemessen an der SICHTBAREN Kante (Text,
  // Rahmen, Hintergrund, Bedienelement), nicht am Kasten: Ein Rand innerhalb
  // eines Rollrahmens zählt sonst als Abstand, den niemand sieht.
  function tinte(e) {
    let oben = Infinity, unten = -Infinity;
    const sicht = (x) => {
      const cs = getComputedStyle(x), r = x.getBoundingClientRect();
      if (cs.display === 'none' || cs.visibility === 'hidden' || r.width === 0 || r.height === 0) return;
      const rand = parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth) > 0;
      const grund = cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== 'transparent';
      const text = [...x.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
      const element = /^(INPUT|SELECT|TEXTAREA|BUTTON|IMG|SVG)$/i.test(x.tagName);
      if (rand || grund || text || element) { oben = Math.min(oben, r.top); unten = Math.max(unten, r.bottom); }
    };
    // In einem geschlossenen <details> zählt nur die Zusammenfassung – der
    // Inhalt ist nicht zu sehen, liefert in WebKit aber trotzdem Kästen.
    const verborgen = (x) => { const d = x.closest('details'); return !!d && d !== x && !d.open && !x.closest('summary'); };
    sicht(e);
    e.querySelectorAll('*').forEach((x) => { if (!verborgen(x)) sicht(x); });
    return oben === Infinity ? null : { oben, unten };
  }
  function fugen() {
    const behaelter = [document.querySelector('#ansicht'),
      ...document.querySelectorAll('#ansicht .sektion, #ansicht details.block[open]')].filter(Boolean);
    const paare = [];
    for (const b of behaelter) {
      const kinder = [...b.children].filter((k) => k.tagName !== 'SUMMARY')
        .map((k) => ({ k, t: tinte(k) })).filter((x) => x.t);
      for (let i = 1; i < kinder.length; i++) {
        paare.push({ in: name(b).slice(0, 30), oben: name(kinder[i - 1].k).slice(0, 40), unten: name(kinder[i].k).slice(0, 40),
          fuge: Math.round(kinder[i].t.oben - kinder[i - 1].t.unten) });
      }
    }
    return paare;
  }

  // Zeilen der Lehrkraft-Tabelle (v0.9.62): Höhe der Zeilen mit offenem
  // Zeitfenster gegen den Median aller Zeilen, und wie weit die Häkchen
  // („dabei“, „½“) senkrecht von der Mitte des Namens abweichen.
  function zeilen() {
    const t = document.querySelector('table.tabelle-breit');
    if (!t) return null;
    const rows = [...t.querySelectorAll('tr')].filter((r) => r.querySelector('td'));
    const h = rows.map((r) => r.getBoundingClientRect().height).sort((a, b) => a - b);
    const median = h[Math.floor(h.length / 2)];
    const offen = rows.filter((r) => [...r.querySelectorAll('.zeitfenster-felder')].some((f) => getComputedStyle(f).display !== 'none'));
    let versatz = 0;
    for (const r of offen) {
      const name = r.querySelector('td'); const nr = name.getBoundingClientRect();
      const nMitte = nr.top + parseFloat(getComputedStyle(name).paddingTop) + parseFloat(getComputedStyle(name).lineHeight || 0) / 2;
      for (const cb of r.querySelectorAll('input[type=checkbox]')) {
        const c = cb.getBoundingClientRect();
        versatz = Math.max(versatz, Math.abs((c.top + c.height / 2) - nMitte));
      }
    }
    return { zeilen: rows.length, median: Math.round(median), offen: offen.length,
      hoechsteOffen: Math.round(Math.max(0, ...offen.map((r) => r.getBoundingClientRect().height))),
      haekchenVersatz: Math.round(versatz) };
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
      knapp: knapp(), uebersicht: uebersicht(), abschnitte: abschnitte(), fugen: fugen(), zeilen: zeilen(),
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
      // Kurzmeldung: dasselbe, was toast() in app.js tut (Text und Klasse
      // setzen) – toast() selbst liegt in der IIFE und ist nicht erreichbar.
      // Text erfunden, so lang wie eine Fehlermeldung des Servers sein kann.
      if (a === 'toast') { const t = document.querySelector('#toast');
        if (t) { t.textContent = 'Dieser Termin ist leider gerade vergeben worden. Bitte wählen Sie einen anderen.';
          t.className = 'toast fehler'; } await warte(100); }
      if (a === 'offen') { document.querySelectorAll('details').forEach((d) => { d.open = true; }); await warte(300); }
      erg.push(messen('nach ' + a));
    }
    window.__messung = erg;
  });
})();
