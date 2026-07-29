# Signage-Engine in anderen Projekten wiederverwenden

Die Info-Monitor-Anzeige ist in zwei Teile getrennt:

- **`signageStart(cfg)`** – die generische Engine (in `frontend/app.js`).
  Weiß nichts über Sprechtage. Liefert: Vollbild-Kopf mit Logo/Titel,
  automatisch gemessene Kachelmenge, seitenweises Umblättern, Auto-Refresh,
  flackerfreies Neurendern. Dazu gehören die CSS-Klassen `anzeige-*` in
  `frontend/style.css`.

- **`starteAnzeige()`** – der sprechtag-spezifische Adapter. Nur er kennt die
  Datenquelle (`/api/anzeige`) und wie eine Kachel gefüllt wird.

## Für ein neues Projekt

1. `signageStart` und die `anzeige-*`-CSS-Regeln kopieren (unverändert).
2. Einen eigenen Adapter schreiben, der `cfg` befüllt:

```js
function starteAnzeige() {
  signageStart({
    titel: 'Projektstunden',
    holen: () => api('/api/meine-anzeige'),
    istAktiv: (d) => !!d.aktiv,
    untertitel: (d) => d.titelzeile || '',
    posten: (d) => d.eintraege || [],
    proSeite: (d) => d.kacheln,      // 'auto' oder Zahl
    intervall: (d) => d.intervall,   // Sekunden
    leerText: (d) => 'Zurzeit nichts anzuzeigen.',
    kachel: (e) => {
      const k = el('div', 'anzeige-kachel');
      k.appendChild(el('div', 'anzeige-raum', e.oben));
      k.appendChild(el('div', 'anzeige-kuerzel', e.mitte));
      k.appendChild(el('div', 'anzeige-zeit', e.unten));
      return k;
    },
  });
}
```

3. Den Aufruf in `start()` bei `window.location.pathname === '/anzeige'`
   übernehmen und einen öffentlichen, datensparsamen Endpunkt bereitstellen.

## Wichtig
- Der Anzeige-Endpunkt sollte OHNE Login funktionieren und nur unkritische,
  ohnehin öffentliche Daten liefern (keine personenbezogenen/Buchungsdaten).
- Logo und Akzentfarbe kommen automatisch aus dem Branding (`/api/einstellungen`).
