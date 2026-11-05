// ============================================================
// tests/frontend_browser_test.js – Anmeldung und Hilfe im echten Browser
//
// S75 (koordination ENTWURF-AUFTRAG-doku-hilfe-S75.md; V37: Ist ein Browser
// da, wird das Verhalten geprüft, nicht der Quelltext). Bauart nach der
// Browserprüfung aus S74 (schulprozesse tests/browser_test.mjs), hier als
// .js, weil deploy.sh nur tests/*_test.js aufruft.
//
// Startet einen Chromium ohne Fenster, liefert frontend/ über einen kleinen
// Ersatz-Server aus (die API antwortet fest, keine Datenbank) und steuert die
// Seite über das DevTools-Protokoll mit ECHTEN Tasten- und Mausereignissen.
// Gemessen wird, was danach passiert: welche Anfragen der Server bekommt,
// was sichtbar ist.
//
// Die Sätze, die der Ersatz-Server schickt, kommen aus dem PHP-Code
// (ANMELDUNG_ABGELEHNT, bremse_wartesatz, wu_sitzung_meldung), nicht aus
// dieser Datei. Fehlt einer, ist er null, und die Prüfungen werden rot.
//
// Aufruf: node tests/frontend_browser_test.js [frontend-Verzeichnis]
// Chromium: $CHROME, sonst Google Chrome, sonst der headless shell aus dem
// Playwright-Cache. Fehlt jeder, endet die Datei mit „ÜBERSPRUNGEN“ und
// Exit 3 – sichtbar, nicht grün; deploy.sh hält dann an (Betreiber, S75).
//
// SPDX-License-Identifier: GPL-3.0-or-later
// ============================================================
'use strict';
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn, execFileSync } = require('child_process');

const WURZEL = path.resolve(__dirname, '..');
const FE = path.resolve(process.argv[2] || path.join(WURZEL, 'frontend'));

function findeChrome() {
  const kandidaten = [process.env.CHROME,
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'];
  const cache = path.join(os.homedir(), 'Library/Caches/ms-playwright');
  try {
    for (const d of fs.readdirSync(cache).filter((n) => n.startsWith('chromium_headless_shell-')).sort().reverse())
      for (const u of fs.readdirSync(path.join(cache, d)))
        kandidaten.push(path.join(cache, d, u, 'chrome-headless-shell'));
  } catch { /* kein Cache */ }
  return kandidaten.find((k) => k && fs.existsSync(k));
}

let gruen = 0, rot = 0;
const pruefe = (ok, text, warum = '') => {
  if (ok) { gruen++; console.log('  ✓ ' + text); }
  else { rot++; console.log('  ✗ ' + text + (warum ? ' – ' + warum : '')); }
};

// ---- Die Sätze aus dem Code ------------------------------------------
function saetze() {
  const api = path.join(WURZEL, 'backend', 'api');
  try {
    return JSON.parse(execFileSync('php', ['-r', `
      require $argv[1]; require $argv[2];
      echo json_encode([
        'abgelehnt' => defined('ANMELDUNG_ABGELEHNT') ? ANMELDUNG_ABGELEHNT : null,
        'warten' => function_exists('bremse_wartesatz') ? bremse_wartesatz(60) : null,
        'ausfall' => function_exists('wu_sitzung_meldung') ? wu_sitzung_meldung('nicht_erreichbar') : null,
      ]);`, path.join(api, 'webuntis_adapter.php'), path.join(api, 'anmeldebremse.php')], { encoding: 'utf8' }));
  } catch (e) {
    console.log('    (php: ' + String(e.message).split('\n')[0] + ')');
    return { abgelehnt: null, warten: null, ausfall: null };
  }
}

async function main() {
  const CHROME = findeChrome();
  if (!CHROME) { console.log('  ÜBERSPRUNGEN: kein Chromium gefunden ($CHROME setzen)'); process.exit(3); }
  const SATZ = saetze();

  // ---- Ersatz-Server -------------------------------------------------
  // LOGIN: 'ok' | 'falsch' | 'warten' | 'ausfall'
  let LOGIN = 'ok', ANGEMELDET = false;
  const anfragen = [];
  const MIME = { html: 'text/html; charset=utf-8', js: 'application/javascript', css: 'text/css', svg: 'image/svg+xml' };
  const srv = http.createServer((q, r) => {
    const u = new URL(q.url, 'http://x').pathname;
    const json = (s, b) => { r.writeHead(s, { 'Content-Type': 'application/json' }); r.end(JSON.stringify(b)); };
    if (u.startsWith('/api/')) {
      anfragen.push(q.method + ' ' + u);
      if (u === '/api/auth/me') return json(200, ANGEMELDET ? { angemeldet: true, rolle: 'eltern', name: 'Probe' } : { angemeldet: false });
      if (u === '/api/auth/logout') { ANGEMELDET = false; return json(200, { ok: true }); }
      if (u === '/api/auth/login') {
        if (LOGIN === 'ok') { ANGEMELDET = true; return json(200, { angemeldet: true, rolle: 'eltern', name: 'Probe' }); }
        if (LOGIN === 'warten') return json(429, { fehler: SATZ.warten, warten_sekunden: 60 });
        if (LOGIN === 'ausfall') return json(503, { fehler: SATZ.ausfall });
        return json(401, { fehler: SATZ.abgelehnt });
      }
      if (u === '/api/sprechtage') return json(200, []);
      return json(200, {});
    }
    const f = path.join(FE, u === '/' ? 'index.html' : u);
    if (!f.startsWith(FE + path.sep) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) { r.writeHead(404); return r.end(); }
    r.writeHead(200, { 'Content-Type': MIME[path.extname(f).slice(1)] || 'application/octet-stream' });
    r.end(fs.readFileSync(f));
  });
  await new Promise((ok) => srv.listen(0, '127.0.0.1', ok));
  const BASIS = 'http://127.0.0.1:' + srv.address().port + '/';

  // ---- Browser -------------------------------------------------------
  const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'sprechtag-browser-'));
  const chrome = spawn(CHROME, ['--headless=new', '--remote-debugging-port=0', '--no-first-run',
    '--user-data-dir=' + profil, '--window-size=1280,900', 'about:blank'], { stdio: ['ignore', 'ignore', 'pipe'] });
  const ende = (code) => {
    chrome.kill(); srv.close();
    setTimeout(() => { try { fs.rmSync(profil, { recursive: true, force: true }); } catch { /* bleibt im tmp */ } process.exit(code); }, 300);
  };
  const wsUrl = await new Promise((ok, nein) => {
    let b = ''; const t = setTimeout(() => nein(new Error('Chromium startet nicht')), 15000);
    chrome.stderr.on('data', (d) => { b += d; const m = b.match(/ws:\/\/\S+/); if (m) { clearTimeout(t); ok(m[0]); } });
  });
  const ws = new WebSocket(wsUrl);
  await new Promise((ok) => { ws.onopen = ok; });
  let nr = 0, S = null, geladen = 0; const offen = {};
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data);
    if (m.id && offen[m.id]) { offen[m.id](m); delete offen[m.id]; }
    else if (m.method === 'Page.loadEventFired') geladen++;
  };
  const cdp = (method, params = {}) => new Promise((ok) => {
    const i = ++nr; offen[i] = ok; ws.send(JSON.stringify({ id: i, method, params, sessionId: S || undefined }));
  });
  const werte = async (a) => (await cdp('Runtime.evaluate', { expression: a, awaitPromise: true, returnByValue: true })).result?.result?.value;
  const pause = (ms) => new Promise((ok) => setTimeout(ok, ms));
  const bis = async (a, ms = 3000) => { const t = Date.now(); while (Date.now() - t < ms) { if (await werte(a)) return true; await pause(50); } return false; };

  const { result: { targetId } } = await cdp('Target.createTarget', { url: 'about:blank' });
  S = (await cdp('Target.attachToTarget', { targetId, flatten: true })).result.sessionId;
  await cdp('Runtime.enable'); await cdp('Page.enable');

  async function taste(key) {
    const [code, vk, text] = key === 'Enter' ? ['Enter', 13, '\r']
      : ['Key' + key.toUpperCase(), key.toUpperCase().charCodeAt(0), key];
    await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode: vk, text });
    await cdp('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: vk });
  }
  async function tippe(s) { for (const c of s) await taste(c); }
  // Ein sichtbares, nicht verdecktes Element mit diesem Text.
  const sichtbar = (sel, muster) => `(() => {
    const k = [...document.querySelectorAll(${JSON.stringify(sel)})].filter((e) => {
      if (!${muster}.test((e.getAttribute('aria-label') || e.textContent || '').trim())) return false;
      const r = e.getBoundingClientRect();
      if (!r.width || !r.height || getComputedStyle(e).visibility === 'hidden') return false;
      if (r.bottom < 0 || r.top > innerHeight) e.scrollIntoView({ block: 'center' });
      const r2 = e.getBoundingClientRect();
      const oben = document.elementFromPoint(r2.x + r2.width / 2, r2.y + r2.height / 2);
      return oben && (oben === e || e.contains(oben));
    });
    return k[0] || null; })()`;
  async function klicke(ausdruck) {
    const p = await werte(`(() => { const e = ${ausdruck}; if (!e) return null;
      e.scrollIntoView({ block: 'center' }); const r = e.getBoundingClientRect();
      return r.width && r.height ? { x: r.x + r.width / 2, y: r.y + r.height / 2 } : null; })()`);
    if (!p) return false;
    for (const type of ['mousePressed', 'mouseReleased'])
      await cdp('Input.dispatchMouseEvent', { type, x: p.x, y: p.y, button: 'left', clickCount: 1 });
    return true;
  }
  const maske = `(() => { const e = document.getElementById('login-passwort'); return !!e && e.getBoundingClientRect().height > 0; })()`;
  const mainText = `(document.querySelector('main')?.innerText || '')`;
  async function frisch(breite = 1280, hoehe = 900) {
    ANGEMELDET = false;
    await cdp('Emulation.setDeviceMetricsOverride', { width: breite, height: hoehe, deviceScaleFactor: 1, mobile: breite < 760 });
    await cdp('Page.navigate', { url: BASIS });
    await bis(`document.readyState === 'complete' && ${maske}`, 5000);
    await pause(200);
    anfragen.length = 0;
  }
  const logins = () => anfragen.filter((a) => a === 'POST /api/auth/login').length;
  async function anmeldeVersuch(benutzer, passwort) {
    await klicke(`document.getElementById('login-benutzer')`);
    await werte(`document.getElementById('login-benutzer').select()`);
    await tippe(benutzer);
    await klicke(`document.getElementById('login-passwort')`);
    await tippe(passwort);
    await taste('Enter');
  }

  // ---- Voraussetzung -------------------------------------------------
  await frisch();
  pruefe(await werte(maske) && SATZ.abgelehnt && SATZ.warten && SATZ.ausfall,
    'Voraussetzung: Chromium läuft, die Anmeldemaske steht da, die drei Sätze kommen aus dem PHP-Code',
    JSON.stringify(SATZ));

  // ---- Hilfe ohne Anmeldung (Befund §9) ------------------------------
  const linkHilfe = sichtbar('a, button', '/^Hilfe & Anleitung ansehen$/');
  const faqDa = `(() => { const f = document.getElementById('hilfe-faq'); return !!f && f.getBoundingClientRect().height > 0
    && f.innerText.includes('Ich kann mich nicht anmelden.'); })()`;
  for (const [name, b, h] of [['Desktop (1280 px)', 1280, 900], ['Telefon (390 px)', 390, 844]]) {
    await frisch(b, h);
    const da = await werte(`!!${linkHilfe}`);
    await klicke(linkHilfe);
    const offen = await bis(faqDa, 3000);
    pruefe(da && offen && logins() === 0,
      'Hilfe ohne Anmeldung, ' + name + ': „Hilfe & Anleitung ansehen“ sichtbar, ein Klick zeigt die Häufigen Fragen',
      'Link ' + (da ? 'da' : 'fehlt') + ', Häufige Fragen ' + (offen ? 'da' : 'nicht da') + ', ' + logins() + ' Anmeldungen');
  }

  // ---- Anmelden ------------------------------------------------------
  LOGIN = 'ok';
  await frisch();
  await anmeldeVersuch('probe', 'geheim');
  await bis(`!${maske}`, 3000);
  pruefe(logins() === 1 && !(await werte(maske)),
    'Enter im Passwortfeld schickt genau eine Anmeldung ab und meldet an',
    logins() + ' × POST /api/auth/login, Maske ' + ((await werte(maske)) ? 'noch da' : 'weg'));

  // ---- Abmelden ------------------------------------------------------
  const vorher = geladen;
  await klicke(sichtbar('button', '/^Abmelden$/'));
  await bis(maske, 4000);
  await pause(200);
  pruefe(geladen > vorher && (await werte(`document.getElementById('login-passwort')?.value`)) === '',
    'Abmelden lädt die Seite neu, das Passwortfeld ist danach leer',
    'neu geladen: ' + (geladen - vorher) + ', Feld: ' + JSON.stringify(await werte(`document.getElementById('login-passwort')?.value ?? null`)));

  // ---- Abgelehnt, Wartezeit, Ausfall -----------------------------------
  const gezeigt = {};
  for (const [fall, satz, text] of [
    ['falsch', SATZ.abgelehnt, 'falsches Passwort (401)'],
    ['warten', SATZ.warten, 'Wartezeit (429)'],
    ['ausfall', SATZ.ausfall, 'WebUntis nicht erreichbar (503)']]) {
    LOGIN = fall;
    await frisch();
    await anmeldeVersuch('probe', 'falsch');
    const da = satz ? await bis(`${mainText}.includes(${JSON.stringify(satz)}) && ${maske}`, 3000) : false;
    gezeigt[fall] = da ? satz : null;
    pruefe(logins() === 1 && da,
      text + ': der Satz des Servers steht sichtbar über der Anmeldemaske',
      logins() + ' × POST, sichtbar: „' + String(await werte(mainText)).split('\n').slice(0, 4).join(' / ') + '“');
    if (fall === 'falsch') {
      const wert = await werte(`document.getElementById('login-benutzer')?.value ?? null`);
      pruefe(wert === 'probe' && (await werte(`document.getElementById('login-passwort')?.value`)) === '',
        'falsches Passwort (401): der Benutzername bleibt stehen, das Passwortfeld ist leer',
        'Benutzername: ' + JSON.stringify(wert));
    }
  }

  // ---- Die Hilfe sagt, was die Anwendung zeigt --------------------------
  // Am Dargestellten: die Frage aufklappen, ihren sichtbaren Text lesen. Die
  // Wartezeit steht in der Hilfe als „…“ statt der Sekundenzahl.
  await frisch();
  await klicke(linkHilfe);
  await bis(faqDa, 3000);
  await klicke(sichtbar('#hilfe-faq summary', '/^Ich kann mich nicht anmelden\\.$/'));
  await pause(200);
  const antwort = await werte(`(() => { const d = [...document.querySelectorAll('#hilfe-faq details')]
    .find((x) => x.querySelector('summary')?.textContent === 'Ich kann mich nicht anmelden.');
    return d && d.open ? d.innerText : ''; })()`);
  const wartenHilfe = gezeigt.warten ? gezeigt.warten.replace(/\d+ Sekunden/, '… Sekunden') : null;
  pruefe(!!antwort && !!gezeigt.ausfall && !!wartenHilfe
    && antwort.includes('„' + gezeigt.ausfall + '“') && antwort.includes('„' + wartenHilfe + '“'),
    'Hilfe „Ich kann mich nicht anmelden.“ (aufgeklappt) zitiert den angezeigten Ausfallsatz und den Satz der Wartezeit',
    'Antwort: „' + String(antwort).slice(0, 160) + '…“');

  console.log('\n' + (rot === 0 ? 'ALLE TESTS GRÜN' : rot + ' FEHLER') + ' (' + gruen + ' grün)');
  ende(rot === 0 ? 0 : 1);
}

main().catch((e) => { console.log('  ✗ Browserprüfung abgebrochen – ' + e.message); process.exit(1); });
