// ============================================================
// app.js – gesamtes Frontend-JavaScript (Paket 1: Sondierung)
// Kein JS im HTML (Uberspace-Proxy: 63-KB-HTML-Limit).
// ============================================================
'use strict';

(function () {

  const $ = (sel) => document.querySelector(sel);
  let letzterBericht = null;

  // ---------- Sondierung ausführen ----------------------------------------
  $('#sondierung-form').addEventListener('submit', async (ev) => {
    ev.preventDefault();

    const gruppen = Array.from(
      document.querySelectorAll('.gruppen input:checked')
    ).map((el) => el.value);

    const body = {
      benutzername: $('#benutzername').value.trim(),
      passwort:     $('#passwort').value,
      gruppen,
      schueler_id:  $('#schueler-id').value.trim(),
      extra_pfade:  $('#extra-pfade').value,
    };

    const knopf  = $('#starten');
    const status = $('#status');
    knopf.disabled = true;
    status.className = '';
    status.textContent = 'Sondierung läuft – je nach Probengruppen bis ~1 Minute …';

    try {
      const antwort = await fetch('/api/sondierung', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const daten = await antwort.json().catch(() => null);
      if (!antwort.ok || !daten || !daten.bericht) {
        throw new Error((daten && daten.fehler) || ('HTTP ' + antwort.status));
      }
      letzterBericht = daten.bericht;
      status.className = 'ok';
      status.textContent = 'Fertig.';
      berichtAnzeigen(daten.bericht);
    } catch (fehler) {
      status.className = 'fehler';
      status.textContent = String(fehler.message || fehler);
    } finally {
      knopf.disabled = false;
      $('#passwort').value = '';   // Passwort nie stehen lassen
    }
  });

  // ---------- Bericht rendern ----------------------------------------------
  function berichtAnzeigen(b) {
    const ziel = $('#bericht');
    ziel.textContent = '';
    $('#bericht-karte').classList.remove('versteckt');

    // authenticate-Befund prominent
    if (b.authenticate) {
      const a = b.authenticate;
      const box = el('div', 'probe ' + (a.personType != null ? 'ok' : 'fehlt'));
      box.appendChild(el('h4', null, 'authenticate (JSON-RPC)'));
      box.appendChild(absatz(
        'personType: ' + a.personType + ' → ' + a.rolle_bekannt +
        ' · personId: ' + a.personId +
        (a.klasseId != null ? ' · klasseId: ' + a.klasseId : '')
      ));
      box.appendChild(vorformatiert('Felder: ' + (a.alle_schluessel || []).join(', ')));
      ziel.appendChild(box);
    }

    if (b.rest_zugang) {
      const box = el('div', 'probe ' + (b.rest_zugang.jwt ? 'ok' : 'fehlt'));
      box.appendChild(el('h4', null, 'REST-Zugang (/api/token/new)'));
      box.appendChild(absatz(b.rest_zugang.jwt
        ? 'JWT erhalten – interne REST-API erreichbar.'
        : 'KEIN JWT – ' + (b.rest_zugang.hinweis || '')));
      ziel.appendChild(box);
    }

    if (b.app_data) probeAnzeigen(ziel, 'app/data (Basis)', b.app_data);

    for (const gruppe of ['sprechtag', 'stundenplan', 'mitteilungen', 'eigene_pfade']) {
      if (!Array.isArray(b[gruppe])) continue;
      ziel.appendChild(el('h3', null, ueberschrift(gruppe)));
      for (const probe of b[gruppe]) probeAnzeigen(ziel, probe.pfad, probe);
    }
  }

  function probeAnzeigen(ziel, titel, p) {
    const status = p.uebersprungen ? -1 : (p.status || 0);
    // 405 bei Mitteilungen = Endpunkt existiert, will POST -> positiv werten
    const klasse = p.uebersprungen ? 'warn'
      : (status >= 200 && status < 300) || status === 405 ? 'ok'
      : status === 403 || status === 401 ? 'warn' : 'fehlt';

    const box = el('div', 'probe ' + klasse);
    box.appendChild(el('h4', null, titel));

    if (p.uebersprungen) {
      box.appendChild(absatz('Übersprungen: ' + p.uebersprungen));
      ziel.appendChild(box);
      return;
    }

    const st = el('span', 'st', 'HTTP ' + status);
    const zeile = el('p');
    zeile.appendChild(st);
    if (p.query) zeile.appendChild(document.createTextNode(
      '  ·  Query: ' + JSON.stringify(p.query)));
    box.appendChild(zeile);

    if (p.json_schluessel && p.json_schluessel.length) {
      box.appendChild(absatz('JSON-Schlüssel: ' + p.json_schluessel.join(', ')));
    }
    if (p.fehler_details) {
      box.appendChild(vorformatiert(JSON.stringify(p.fehler_details, null, 2)));
    }
    if (p.kind_strukturen && p.kind_strukturen.length) {
      for (const f of p.kind_strukturen) {
        const treffer = el('div', 'treffer');
        treffer.appendChild(absatz(
          '👪 Kind-/Personen-Struktur: ' + f.pfad + ' (Anzahl: ' + f.anzahl +
          (f.ids && f.ids.length ? ', IDs: ' + f.ids.join(', ') : '') + ')'
        ));
        if (f.schluessel_erstes && f.schluessel_erstes.length) {
          treffer.appendChild(absatz('Felder: ' + f.schluessel_erstes.join(', ')));
        }
        box.appendChild(treffer);
      }
    }
    if (p.roh_auszug) {
      const details = document.createElement('details');
      details.appendChild(el('summary', null, 'Roh-Auszug'));
      details.appendChild(vorformatiert(p.roh_auszug));
      box.appendChild(details);
    }
    ziel.appendChild(box);
  }

  // ---------- Markdown-Export ----------------------------------------------
  $('#kopieren').addEventListener('click', async () => {
    if (!letzterBericht) return;
    const md = alsMarkdown(letzterBericht);
    try {
      await navigator.clipboard.writeText(md);
      $('#kopier-status').textContent = 'Kopiert – bitte in den Chat einfügen.';
    } catch (fehler) {
      $('#kopier-status').textContent = 'Kopieren fehlgeschlagen: ' + fehler;
    }
  });

  function alsMarkdown(b) {
    const zeilen = [
      '# Sondierungsbericht sprechtag',
      '',
      '- Instanz: ' + b.instanz + ' (' + b.schule + ')',
      '- Zeit: ' + b.zeit,
      '- Gruppen: ' + (b.gruppen || []).join(', '),
      '- ' + (b.hinweis || ''),
      '',
    ];
    if (b.authenticate) {
      zeilen.push('## authenticate', '```json',
        JSON.stringify(b.authenticate, null, 2), '```', '');
    }
    if (b.rest_zugang) {
      zeilen.push('## REST-Zugang: JWT ' +
        (b.rest_zugang.jwt ? 'erhalten' : 'FEHLT'), '');
    }
    if (b.app_data) {
      zeilen.push('## app/data', '```json',
        JSON.stringify(b.app_data, null, 2), '```', '');
    }
    for (const gruppe of ['sprechtag', 'stundenplan', 'mitteilungen', 'eigene_pfade']) {
      if (!Array.isArray(b[gruppe])) continue;
      zeilen.push('## ' + ueberschrift(gruppe), '');
      for (const p of b[gruppe]) {
        zeilen.push('### `' + (p.pfad || '?') + '`', '```json',
          JSON.stringify(p, null, 2), '```', '');
      }
    }
    return zeilen.join('\n');
  }

  // ---------- kleine Helfer -------------------------------------------------
  function ueberschrift(gruppe) {
    return {
      sprechtag:    'Sprechtag-Endpunkte',
      stundenplan:  'Stundenplan-Proben',
      mitteilungen: 'Mitteilungs-Endpunkte (nur GET)',
      eigene_pfade: 'Eigene Zusatzpfade',
    }[gruppe] || gruppe;
  }
  function el(tag, klasse, text) {
    const e = document.createElement(tag);
    if (klasse) e.className = klasse;
    if (text != null) e.textContent = text;
    return e;
  }
  function absatz(text) { return el('p', null, text); }
  function vorformatiert(text) { return el('pre', null, text); }

})();
