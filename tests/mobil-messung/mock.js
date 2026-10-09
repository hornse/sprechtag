// tests/mobil-messung/mock.js – erfundene API-Antworten für die Breitenmessung
// von frontend/app.js (messen.sh). SPDX-License-Identifier: GPL-3.0-or-later
//
// ALLE Daten sind erfunden. Formen (Schlüssel, Typen) sind aus
// backend/api/*.php abgelesen; Werte nicht.
//
// Typregel: Was aus einer PDO-Zeile kommt, ist hier eine Zeichenkette
// (ids, Flags, Zahlen, TIME, DATE). Was PHP selbst baut oder castet
// ((int)-Casts, Raster-Einträge, auth/me, login-log/einstellungen,
// klassenleitung, anzeige-einstellungen.intervall), ist eine Zahl bzw.
// ein Boolean – so wie im Backend.
//
// Aufruf: window.mockAntwort(rolle, pfad, methode) -> { status, json }
// rolle: 'eltern' | 'lehrkraft' | 'admin' | 'gast'
'use strict';

(function () {
  const S = (v) => (v === null || v === undefined ? null : String(v));
  const zwei = (n) => String(n).padStart(2, '0');

  // ---------- Sprechtage ----------------------------------------------------
  // Aktiver Sprechtag: phase2, Datum nahe Zukunft. 15:00–18:20, 10-Min-Slots,
  // Pause nach 8 Terminen (10 Min) -> 18 Slots + 2 Pausen = 20 Einträge.
  const SPRECHTAG_AKTIV = {
    id: '7', name: 'Elternsprechtag 1. Halbjahr 2026/27 (erfunden)',
    datum: '2026-10-22', beginn: '15:00:00', ende: '18:20:00',
    slot_minuten: '10', max_termine_pro_eltern: '6',
    pause_nach_terminen: '8', pause_minuten: '10', phase: 'phase2',
    referenz_von: '2026-09-07', referenz_bis: '2026-10-02',
    archiviert_am: null, angelegt_am: '2026-09-01 08:12:44',
    klausuren_werten: '1', pause_dynamisch: '0',
  };
  const SPRECHTAG_VORB = {
    id: '8', name: 'Elternsprechtag 2. Halbjahr 2026/27 mit Laufbahnberatung Oberstufe',
    datum: '2027-02-25', beginn: '14:30:00', ende: '19:00:00',
    slot_minuten: '15', max_termine_pro_eltern: '4',
    pause_nach_terminen: '0', pause_minuten: '10', phase: 'vorbereitung',
    referenz_von: null, referenz_bis: null,
    archiviert_am: null, angelegt_am: '2026-10-01 10:00:00',
    klausuren_werten: '0', pause_dynamisch: '1',
  };
  const SPRECHTAG_ALT = {
    id: '5', name: 'Elternsprechtag 2. Halbjahr 2025/26',
    datum: '2026-03-12', beginn: '15:00:00', ende: '19:00:00',
    slot_minuten: '10', max_termine_pro_eltern: '6',
    pause_nach_terminen: '6', pause_minuten: '10', phase: 'archiviert',
    referenz_von: '2026-02-02', referenz_bis: '2026-02-27',
    archiviert_am: '2026-03-20 07:00:00', angelegt_am: '2026-01-15 09:30:00',
    klausuren_werten: '1', pause_dynamisch: '0',
  };
  // ORDER BY datum DESC wie im Backend.
  const SPRECHTAGE_ADMIN = [SPRECHTAG_VORB, SPRECHTAG_AKTIV, SPRECHTAG_ALT];
  // Nicht-Admin: nur phase1/phase2/geschlossen.
  const SPRECHTAGE_ANDERE = [SPRECHTAG_AKTIV];

  // ---------- Lehrkräfte (erfunden) ----------------------------------------
  const ZAHLWORT = ['Null', 'Eins', 'Zwei', 'Drei', 'Vier', 'Fünf', 'Sechs',
    'Sieben', 'Acht', 'Neun', 'Zehn', 'Elf', 'Zwölf', 'Dreizehn', 'Vierzehn',
    'Fünfzehn'];
  function lehrerName(n) {
    if (n === 3) return 'Erfunden-Ausgedachtmann von Beispielhausen-Musterfeld';
    if (n === 9) return 'Erfunden Neun-Langnamige-Doppelname';
    if (n < ZAHLWORT.length) return 'Erfunden ' + ZAHLWORT[n];
    return 'Erfunden Nummer ' + n;
  }
  const lid = (n) => String(100 + n);
  const kuerzel = (n) => 'EF' + n;

  const RAEUME = [
    { id: '201', webuntis_id: '9201', kuerzel: 'B202', name: 'Klassenraum B202' },
    { id: '202', webuntis_id: '9202', kuerzel: 'A014', name: 'Klassenraum A014' },
    { id: '203', webuntis_id: '9203', kuerzel: 'C3.07', name: 'Fachraum Chemie 3.07' },
    { id: '204', webuntis_id: '9204', kuerzel: 'NW-LAB2', name: 'Naturwissenschaftliches Labor 2 (Altbau, Obergeschoss)' },
    { id: '205', webuntis_id: '9205', kuerzel: 'B105', name: null },
    { id: '206', webuntis_id: '9206', kuerzel: 'A1.12', name: 'Kursraum A1.12' },
    { id: '207', webuntis_id: '9207', kuerzel: 'MUS-PAV', name: 'Musikpavillon' },
    { id: '208', webuntis_id: '9208', kuerzel: 'BIB', name: 'Bibliothek' },
  ];
  const raumFuer = (n) => RAEUME[n % RAEUME.length];

  // ---------- Kinder / Schülerliste (erfunden) ------------------------------
  const KLASSEN = ['05A', '07B', '09C', 'EF', 'Q1'];
  function schuelerZeile(i) {
    const klasse = KLASSEN[i % KLASSEN.length];
    let nachname = 'Erfunden ' + zwei(i);
    let vorname = 'Kind';
    if (i === 13) { nachname = 'Erfunden-Ausgedachtmann-Beispielhausen ' + zwei(i); vorname = 'Kind Zweitname Drittname'; }
    if (i === 21) vorname = '';
    return {
      id: String(500 + i),
      webuntis_id: i === 17 ? null : String(9100 + i),   // einer ohne WebUntis-Zuordnung
      schild_id: String(1100000 + i),
      vorname, nachname, klasse,
      austritt: '2031-07-31',
    };
  }
  const SCHUELER = [];
  for (let i = 1; i <= 30; i++) SCHUELER.push(schuelerZeile(i));
  const kindAnzeige = (s) => s.nachname + (s.vorname ? ', ' + s.vorname : '');

  function schuelerKlassen(suche) {
    const q = (suche || '').trim().toLowerCase();
    const liste = SCHUELER.filter((s) => !q
      || s.nachname.toLowerCase().includes(q)
      || s.vorname.toLowerCase().includes(q)
      || s.klasse.toLowerCase().includes(q));
    liste.sort((a, b) => (a.klasse + a.nachname).localeCompare(b.klasse + b.nachname));
    const klassen = {};
    for (const s of liste) (klassen[s.klasse] = klassen[s.klasse] || []).push(s);
    return { klassen, anzahl: liste.length };
  }

  // ---------- Benutzer (auth/me) -------------------------------------------
  const ME = {
    gast: { angemeldet: false },
    eltern: {
      angemeldet: true, rolle: 'eltern', name: 'Elternteil Erfunden',
      kuerzel: null, lehrer_id: null, user_id: 5001, person_id: 7001,
      kinder: [
        { id: 9101, name: 'Kind Erfunden 01' },
        { id: 9113, name: 'Kind Zweitname Drittname Erfunden-Ausgedachtmann-Beispielhausen 13' },
      ],
    },
    lehrkraft: {
      angemeldet: true, rolle: 'lehrkraft', name: lehrerName(1),
      kuerzel: kuerzel(1), lehrer_id: 101, user_id: null, person_id: 301,
      kinder: [],
    },
    admin: {
      angemeldet: true, rolle: 'admin', name: lehrerName(2),
      kuerzel: kuerzel(2), lehrer_id: 102, user_id: null, person_id: 302,
      kinder: [],
    },
  };

  // ---------- Branding ------------------------------------------------------
  const MARKE = {
    marke_schulname: 'Erfundenes Gymnasium am Beispielweg',
    marke_titel: 'Sprechtag',
    marke_untertitel: 'Elternsprechtag',
    marke_fusszeile: 'sprechtag · GPL-3.0-or-later',
    marke_kontakt: 'sekretariat@schule.example',
    hat_logo: false,
    logo_version: '0',
  };

  const TEXTE = {
    hilfe_zusatz: '<h2>Willkommen</h2><p>Bei Fragen wenden Sie sich an sekretariat@schule.example. Dieser Text ist erfunden und dient nur der Layout-Messung.</p>',
    buchung_hinweis: '<p>Bitte buchen Sie Ihre Termine bis <strong>Freitag, 16. Oktober</strong>. Pro Erziehungsberechtigtem sind höchstens sechs Termine möglich.</p>',
    login_hinweis: '<p>Melden Sie sich mit Ihrem persönlichen Erfundenes-Gymnasium-WebUntis-Zugang an.</p>',
  };
  const TEXTE_ROH = {
    hilfe_zusatz: '## Willkommen\n\nBei Fragen wenden Sie sich an {{kontakt}}. Dieser Text ist erfunden und dient nur der Layout-Messung.',
    buchung_hinweis: 'Bitte buchen Sie Ihre Termine bis **Freitag, 16. Oktober**. Pro Erziehungsberechtigtem sind höchstens sechs Termine möglich.',
    login_hinweis: 'Melden Sie sich mit Ihrem persönlichen {{schulname}}-WebUntis-Zugang an.',
  };

  // ---------- buchbare-lehrer ----------------------------------------------
  // Spalten wie bu_buchbare_lehrer(): DB-Zeilen als Strings, klassenleitung
  // als PHP-Int (in_array ? 1 : 0).
  function buchbarZeile(n, extra) {
    const r = raumFuer(n);
    return Object.assign({
      lehrer_id: lid(n), kuerzel: kuerzel(n), name: lehrerName(n),
      faecher: '', stunden: '0', klausuren: '0',
      anwesend_von: null, anwesend_bis: null,
      raum_kuerzel: r.kuerzel, rolle: null, klassenleitung: 0,
    }, extra || {});
  }
  function buchbareLehrer(kind) {
    const k = String(kind || 9101);
    const eingeladen = [
      buchbarZeile(1, { id: '61', schueler_id: k, hinweis: 'Bitte zum Gespräch über die Leistungsentwicklung in Mathematik kommen.', erledigt: '0',
        faecher: 'M, PH', stunden: '4', klausuren: '1', teilnahme: '1', eingeladen: '1' }),
      buchbarZeile(2, { id: '62', schueler_id: k, hinweis: null, erledigt: '1',
        faecher: '', stunden: '0', klausuren: '0', teilnahme: null, eingeladen: '1',
        anwesend_von: '16:30:00', anwesend_bis: '18:20:00' }),
    ];
    const unterrichtend = [
      // Klassenleitung vorn (wie im Backend).
      buchbarZeile(3, { faecher: 'Deutsch, Geschichte, Sozialwissenschaften', stunden: '6', klausuren: '2', klassenleitung: 1 }),
      buchbarZeile(4, { faecher: 'E', stunden: '4', klausuren: '1' }),
      buchbarZeile(5, { faecher: 'BI, CH', stunden: '3', klausuren: '0', anwesend_von: '15:00:00', anwesend_bis: '16:40:00' }),
      buchbarZeile(6, { faecher: 'SP', stunden: '3', klausuren: '0', raum_kuerzel: null }),
      buchbarZeile(7, { faecher: 'KR', stunden: '2', klausuren: '0', anwesend_von: '17:00:00' }),
      buchbarZeile(8, { faecher: 'F', stunden: '2', klausuren: '0' }),
      // nur Klausurtermin: stunden 0, klausuren > 0
      buchbarZeile(9, { faecher: 'IF', stunden: '0', klausuren: '1' }),
    ];
    const sonderlehrer = [
      buchbarZeile(10, { rolle: 'Beratungslehrkraft' }),
      buchbarZeile(11, { rolle: 'Schulsozialarbeit und Laufbahnberatung Sekundarstufe II', anwesend_bis: '17:30:00' }),
      buchbarZeile(12, { rolle: 'Inklusion' }),
    ];
    const weitere = [];
    for (let n = 13; n < 13 + 40; n++) weitere.push(buchbarZeile(n));
    return { eingeladen, unterrichtend, sonderlehrer, weitere,
      nur_eingeladene: false, automatisch_ermittelt: false, ohne_stammsatz: [] };
  }

  // ---------- Raster --------------------------------------------------------
  // Raster-Einträge baut PHP selbst: Zeiten 'HH:MM', frei/eigene Booleans,
  // buchung_id/schueler_id als (int).
  function rasterGitter() {
    const aus = [];
    let t = 15 * 60;
    const hm = (m) => zwei(Math.floor(m / 60)) + ':' + zwei(m % 60);
    let zaehler = 0;
    while (t + 10 <= 18 * 60 + 20) {
      aus.push({ beginn: hm(t), ende: hm(t + 10), typ: 'slot' });
      t += 10; zaehler++;
      if (zaehler % 8 === 0 && t + 10 + 10 <= 18 * 60 + 20) {
        aus.push({ beginn: hm(t), ende: hm(t + 10), typ: 'pause' });
        t += 10;
      }
    }
    return aus;   // 18 Slots + 2 Pausen
  }
  // Belegte Slots: Index im Gitter -> Buchungsdaten.
  const BELEGT = {
    '15:10': { schueler: 2, eigene: false, kommentar: '' },
    '15:20': { schueler: 13, eigene: true, kommentar: 'Thema: Versetzungsgefährdung und Förderplan im zweiten Halbjahr' },
    '15:40': { schueler: 5, eigene: false, kommentar: '' },
    '15:50': { schueler: 8, eigene: false, kommentar: 'Mathe-Note' },
    '16:40': { schueler: 1, eigene: true, kommentar: '' },
    '16:50': { schueler: 21, eigene: false, kommentar: '' },
    '17:20': { schueler: 11, eigene: false, kommentar: '' },
    '18:00': { schueler: 27, eigene: false, kommentar: '' },
  };
  function raster(rolle, lehrer) {
    const lehrerZahl = parseInt(lehrer, 10) || 101;
    const me = ME[rolle] || {};
    const siehtNamen = rolle === 'admin'
      || (rolle === 'lehrkraft' && me.lehrer_id === lehrerZahl);
    const ausgabe = rasterGitter().map((z) => {
      if (z.typ === 'pause') return z;
      const b = BELEGT[z.beginn];
      const e = Object.assign({}, z, { frei: !b });
      if (b) {
        const s = SCHUELER[b.schueler - 1];
        e.eigene = rolle === 'eltern' && b.eigene;
        e.phase_gebucht = 'phase2';
        if (siehtNamen) {
          e.buchung_id = 800 + b.schueler;
          e.schueler_id = 9100 + b.schueler;
          e.kind_name = kindAnzeige(s);
          e.klasse = s.klasse;
          e.gebucht_von = b.schueler === 8 ? 'lehrkraft' : 'eltern';
          e.kommentar = b.kommentar;
        }
      }
      return e;
    });
    // Eigene Lehrkraft (101) ist Halbtagskraft ohne gewählte Hälfte
    // (Fenster leer = ganzer Tag) – so erscheint die Hälften-Wahl, und das
    // Raster bleibt vollständig.
    return {
      raster: ausgabe,
      lehrer: { id: lehrerZahl, halbtags: lehrerZahl === 101 ? 1 : 0,
        anwesend_von: null, anwesend_bis: null },
      sprechtag: { id: 7, phase: SPRECHTAG_AKTIV.phase, datum: SPRECHTAG_AKTIV.datum,
        beginn: SPRECHTAG_AKTIV.beginn, ende: SPRECHTAG_AKTIV.ende },
    };
  }

  // ---------- Buchungen der Eltern -----------------------------------------
  function elternBuchungen() {
    const z = (id, slot, kind, n, phase, raum) => ({
      id: String(id), slot_beginn: slot, schueler_id: String(kind), phase,
      lehrer_id: lid(n), kuerzel: kuerzel(n), name: lehrerName(n),
      raum_kuerzel: raum === undefined ? raumFuer(n).kuerzel : raum,
    });
    return [
      z(901, '15:20:00', 9113, 3, 'phase2'),
      z(902, '15:40:00', 9101, 1, 'phase1'),
      z(903, '16:40:00', 9101, 4, 'phase2'),
      z(904, '17:30:00', 9113, 11, 'phase2', null),
      z(905, '18:10:00', 9113, 9, 'phase2'),
    ];
  }

  // ---------- Einladungen (Lehrkraft) --------------------------------------
  function einladungen() {
    const z = (id, i, hinweis, erledigt) => {
      const s = SCHUELER[i - 1];
      return { id: String(id), schueler_id: String(9100 + i), hinweis, erledigt: String(erledigt),
        angelegt_am: '2026-10-0' + (1 + (id % 8)) + ' 1' + (id % 10) + ':05:00',
        kind_name: kindAnzeige(s), klasse: s.klasse };
    };
    return [
      z(71, 1, 'Bitte zum Gespräch über die Leistungsentwicklung in Mathematik kommen.', 0),
      z(72, 13, null, 1),
      z(73, 6, 'Kurzes Gespräch zur Kurswahl', 0),
      z(74, 11, null, 0),
      z(75, 16, 'Rücksprache wegen häufiger Fehlzeiten im September und Oktober erbeten', 1),
      z(76, 26, null, 0),
    ];
  }

  // ---------- Mitteilungen -------------------------------------------------
  function mitteilungen() {
    const z = (id, i, anlass, betreff, status, grund, angelegt, gesendet) => {
      const s = i ? SCHUELER[i - 1] : null;
      return {
        id: String(id), empfaenger_user_id: String(5000 + id),
        schueler_id: i ? String(9100 + i) : null, anlass, betreff, status,
        grund, versuche: status === 'offen' ? '2' : '1',
        angelegt_am: angelegt, gesendet_am: gesendet,
        kind_name: s ? kindAnzeige(s) : '', klasse: s ? s.klasse : null,
      };
    };
    return [
      z(301, 13, 'bestaetigung', 'Terminbestätigung Elternsprechtag am 22.10. um 15:20 Uhr (B202)', 'offen',
        'WebUntis lehnte die Mitteilung ab: Empfängerkonto nicht gefunden (HTTP 404, ohne validationErrors)',
        '2026-10-08 19:42:10', null),
      z(302, 1, 'bestaetigung', 'Terminbestätigung Elternsprechtag am 22.10. um 16:40 Uhr (A014)', 'gesendet',
        null, '2026-10-08 18:01:00', '2026-10-08 18:01:03'),
      z(303, 2, 'absage', 'Absage Ihres Termins am Elternsprechtag – Lehrkraft erkrankt, bitte neu buchen', 'gesendet',
        null, '2026-10-07 07:15:22', '2026-10-07 07:15:25'),
      z(304, 5, 'einladung', 'Einladung zum Elternsprechtag durch die Klassenleitung der Klasse 05A', 'verworfen',
        'Manuell verworfen', '2026-10-05 12:00:00', null),
      z(305, 21, 'hinweis', 'Hinweis: Raumänderung für Ihren Termin – neuer Raum NW-LAB2 im Altbau', 'offen',
        null, '2026-10-09 08:30:00', null),
      z(306, 8, 'bestaetigung', 'Terminbestätigung Elternsprechtag am 22.10. um 15:50 Uhr (C3.07)', 'gesendet',
        null, '2026-10-06 21:10:00', '2026-10-06 21:10:02'),
      z(307, null, 'absage', 'Absage Ihres Termins am Elternsprechtag am 22.10. um 18:00 Uhr', 'offen',
        'Kein Dienstkonto hinterlegt', '2026-10-09 09:01:00', null),
      z(308, 27, 'einladung', 'Einladung zum Elternsprechtag', 'verworfen',
        null, '2026-10-02 10:10:10', null),
    ];
  }

  // ---------- Admin: Lehrkräfte je Sprechtag --------------------------------
  // Spalten exakt wie GET /api/sprechtage/{id}/lehrer. halbtags seit
  // v0.9.60 (vorher lieferte das Backend es dort nicht); Werte wie in den
  // Stammdaten unten (n = 1 und 5).
  function sprechtagLehrer() {
    const aus = [];
    for (let n = 1; n <= 15; n++) {
      const ohneZuweisung = n === 14;
      // Räume: n=1..8 je eigener Raum; n=9 teilt B202 mit n=1, n=10 teilt
      // C3.07 mit n=3 -> genau zwei Konflikte. n>=11 ohne Raum.
      const r = n <= 8 ? RAEUME[n - 1] : (n === 9 ? RAEUME[0] : (n === 10 ? RAEUME[2] : null));
      aus.push({
        lehrer_id: lid(n), kuerzel: kuerzel(n), name: lehrerName(n),
        halbtags: n === 1 || n === 5 ? '1' : '0',
        zuweisung_id: ohneZuweisung ? null : String(4000 + n),
        anwesend_von: n === 5 ? '15:00:00' : (n === 7 ? '17:00:00' : null),
        anwesend_bis: n === 5 ? '16:40:00' : null,
        raum_id: ohneZuweisung || n === 6 || !r ? null : r.id,
        teilnahme: ohneZuweisung ? null : (n === 12 ? '0' : '1'),
        bemerkung: null,
        raum_kuerzel: ohneZuweisung || n === 6 || !r ? null : r.kuerzel,
      });
    }
    return aus;
  }
  function raumkonflikte() {
    const zaehler = {};
    for (const l of sprechtagLehrer()) {
      if (l.raum_id === null) continue;
      zaehler[l.raum_id] = (zaehler[l.raum_id] || 0) + 1;
    }
    const k = {};
    for (const [rid, n] of Object.entries(zaehler)) if (n > 1) k[rid] = n;
    return k;
  }
  function stammdaten() {
    const lehrer = [];
    for (let n = 1; n <= 15; n++) {
      lehrer.push({ id: lid(n), webuntis_id: String(600 + n), kuerzel: kuerzel(n),
        name: lehrerName(n), aktiv: '1', halbtags: n === 1 || n === 5 ? '1' : '0' });
    }
    return {
      lehrer,
      raeume: RAEUME.slice().sort((a, b) => a.kuerzel.localeCompare(b.kuerzel)),
      sonderrollen: [
        { id: '1', bezeichnung: 'Beratungslehrkraft' },
        { id: '2', bezeichnung: 'Schulsozialarbeit und Laufbahnberatung Sekundarstufe II' },
        { id: '3', bezeichnung: 'Inklusion' },
      ],
    };
  }
  function sonderlehrer() {
    return [
      { id: '31', lehrer_id: lid(10), rolle_id: '1', jahrgaenge: '', kuerzel: kuerzel(10), name: lehrerName(10), rolle: 'Beratungslehrkraft' },
      { id: '32', lehrer_id: lid(11), rolle_id: '2', jahrgaenge: 'EF, Q1, Q2', kuerzel: kuerzel(11), name: lehrerName(11), rolle: 'Schulsozialarbeit und Laufbahnberatung Sekundarstufe II' },
      { id: '33', lehrer_id: lid(12), rolle_id: '3', jahrgaenge: '05, 06', kuerzel: kuerzel(12), name: lehrerName(12), rolle: 'Inklusion' },
      { id: '34', lehrer_id: lid(3), rolle_id: '1', jahrgaenge: null, kuerzel: kuerzel(3), name: lehrerName(3), rolle: 'Beratungslehrkraft' },
    ];
  }

  // ---------- Login-Protokoll ----------------------------------------------
  function loginLog() {
    const z = (b, ok, grund, zeit) => ({ webuntis_benutzer: b, erfolgreich: ok ? '1' : '0', grund, zeitpunkt: zeit });
    return [
      z('erfunden.eltern01', true, 'eltern', '2026-10-09 09:12:03'),
      z('EF1', true, 'lehrkraft', '2026-10-09 08:55:41'),
      z('erfunden.eltern.mit.sehr.langem.benutzernamen13', false, 'WebUntis: bad credentials (-8504)', '2026-10-09 08:40:12'),
      z('erfunden.eltern13', true, 'eltern', '2026-10-09 08:41:00'),
      z('EF2', true, 'admin', '2026-10-08 20:01:19'),
      z('erfunden.schueler21', false, 'Rolle nicht zugelassen: Schüler:in unter 18 Jahren ohne Freigabe für die Buchung', '2026-10-08 18:30:00'),
      z('erfunden.eltern05', true, 'eltern', '2026-10-08 17:22:51'),
      z('EF7', false, 'Zu viele Fehlversuche – bitte später erneut versuchen', '2026-10-08 16:04:33'),
      z('erfunden.eltern08', true, 'eltern', '2026-10-08 12:00:00'),
      z('erfunden.eltern11', false, 'WebUntis nicht erreichbar: Verbindung von der Gegenseite abgebrochen', '2026-10-07 21:45:09'),
    ];
  }

  // ---------- Anzeige (Signage, öffentlich) ---------------------------------
  function anzeige() {
    const lehrer = sprechtagLehrer().filter((l) => l.teilnahme === '1').map((l) => ({
      kuerzel: l.kuerzel, name: l.name,   // halbtags seit v0.9.61 nicht mehr (öffentlich)
      anwesend_von: l.anwesend_von, anwesend_bis: l.anwesend_bis,
      raum_kuerzel: l.raum_kuerzel,
      raum_name: (RAEUME.find((r) => r.id === l.raum_id) || {}).name || null,
    }));
    return { aktiv: true,
      sprechtag: { name: SPRECHTAG_AKTIV.name, datum: SPRECHTAG_AKTIV.datum, beginn: '15:00', ende: '18:20' },
      sortierung: 'raum', kacheln: 'auto', intervall: 10, lehrer };
  }

  const ursprung = () => (typeof location !== 'undefined' ? location.origin : 'https://sprechtag.example');
  const kalUrl = (token) => ursprung() + '/api/kalender/' + token + '.ics';
  const TOKEN_ELTERN = 'a0'.repeat(24);   // 48 Hex, erfunden
  const TOKEN_LEHRER = 'b1'.repeat(24);

  // ---------- Verteiler -----------------------------------------------------
  const ok = (json) => ({ status: 200, json });
  const fehlt = (status, text) => ({ status, json: { fehler: text } });

  window.mockAntwort = function (rolle, pfad, methode) {
    methode = (methode || 'GET').toUpperCase();
    const [weg, query] = String(pfad).split('?');
    const p = new URLSearchParams(query || '');
    const seg = weg.replace(/^\/+|\/+$/g, '').split('/').slice(1);   // ohne 'api'
    const s0 = seg[0] || '';
    const me = ME[rolle] || ME.gast;
    const angemeldet = !!me.angemeldet;
    const istAdmin = rolle === 'admin';
    const istLk = rolle === 'lehrkraft' || istAdmin;

    // Öffentlich
    if (s0 === 'health') return ok({ app: 'sprechtag', version: '0.9.57', db: 'ok' });
    if (s0 === 'einstellungen') {
      if (seg[1] === 'text' && seg[2]) {
        const k = seg[2];
        if (methode === 'POST') return ok({ ok: true, html: TEXTE[k] || '' });
        const a = { html: TEXTE[k] || '' };
        if (istAdmin) a.roh = TEXTE_ROH[k] || '';
        return ok(a);
      }
      if (seg[1] === 'zuruecksetzen') return ok({ ok: true, marke: MARKE });
      if (methode === 'GET' && !seg[1]) return ok(MARKE);
      return ok({ ok: true });
    }
    if (s0 === 'anzeige' && methode === 'GET') return ok(anzeige());
    if (s0 === 'auth') {
      if (seg[1] === 'me') return ok(me);
      if (seg[1] === 'logout') return ok({ ok: true });
      if (seg[1] === 'login') return ok(ME.eltern);
    }

    if (!angemeldet) return fehlt(401, 'Nicht angemeldet');

    switch (s0) {
      case 'sprechtage': {
        if (!seg[1] && methode === 'GET') {
          return ok({ sprechtage: istAdmin ? SPRECHTAGE_ADMIN : SPRECHTAGE_ANDERE });
        }
        if (seg[2] === 'lehrer' && !seg[3] && methode === 'GET') return ok({ lehrer: sprechtagLehrer() });
        if (seg[2] === 'lehrer' && !seg[3] && methode === 'PUT') return ok({ ok: true, gespeichert: 15 });
        if (seg[2] === 'raumkonflikte') return ok({ konflikte: raumkonflikte() });
        if (seg[2] === 'lehrer' && seg[4] === 'ausfall') return ok({ ok: true, hinweis: 'Ausfall eingetragen (erfunden).' });
        if (seg[2] === 'lehrer' && seg[3]) return ok({ ok: true, anwesend_von: null, anwesend_bis: null });
        return ok({ ok: true });
      }
      case 'stammdaten':
        if (!seg[1] && methode === 'GET') return ok(stammdaten());
        return ok({ ok: true });
      case 'sonderlehrer':
        if (methode === 'GET') return ok({ sonderlehrer: sonderlehrer() });
        return ok({ ok: true });
      case 'buchbare-lehrer':
        return ok(buchbareLehrer(p.get('kind')));
      case 'raster':
        return ok(raster(rolle, p.get('lehrer')));
      case 'buchungen':
        if (methode === 'GET' && !seg[1]) {
          if (istLk && p.get('sicht') === 'lehrkraft') return ok({ buchungen: [], sicht: 'lehrkraft' });
          return ok({ buchungen: rolle === 'eltern' ? elternBuchungen() : [], sicht: 'eigene' });
        }
        if (seg[1] === 'stellvertretend') return ok({ ok: true, id: 999, hinweis: 'Termin eingetragen (erfunden).' });
        if (methode === 'DELETE') return ok({ ok: true, mitteilung: { status: 'offen' } });
        return ok({ ok: true, id: 999 });
      case 'kalender-link':
        return ok({ url: kalUrl(TOKEN_ELTERN) });
      case 'lehrer-kalender':
        return ok({ url: kalUrl(TOKEN_LEHRER) });
      case 'einladungen':
        if (methode === 'GET') return ok({ einladungen: istLk ? einladungen() : [] });
        if (methode === 'POST') return ok({ ok: true, eltern_bekannt: true, eltern_anzahl: 2 });
        return ok({ ok: true });
      case 'schueler':
        if (!istLk) return fehlt(403, 'Nur für Lehrkräfte');
        if (methode === 'GET' && !seg[1]) return ok(schuelerKlassen(p.get('suche')));
        if (seg[1] === 'sync') return ok({ ok: true, gelesen: 30, neu: 0, aktualisiert: 30 });
        if (seg[1] === 'csv') return ok({ ok: true, neu: 0, aktualisiert: 30, inaktiv: 0, uebersprungen: [] });
        return ok({ ok: true });
      case 'mitteilungen':
        if (!istLk) return fehlt(403, 'Nur für Lehrkräfte');
        if (methode === 'GET' && !seg[1]) return ok({ mitteilungen: mitteilungen() });
        if (seg[1] === 'senden') return ok({ ok: true, gesendet: 0, grund: 'Erfundener Versandlauf.', variante: null, protokoll: [] });
        return ok({ ok: true });
      case 'dienstkonto':
        if (!istLk) return fehlt(403, 'Nur für Lehrkräfte');
        if (methode === 'GET') {
          if (istAdmin) {
            return ok({ hinterlegt: true, benutzer: 'dienstkonto.erfunden', schluessel_ok: true,
              verfahren: 'sodium (XSalsa20-Poly1305)', entschluesselbar: true });
          }
          // Lehrkraft sieht nur, OB eines nutzbar ist; hier: keins -> Felder sichtbar.
          return ok({ hinterlegt: false, entschluesselbar: false });
        }
        return ok({ ok: true });
      case 'anzeige-einstellungen':
        if (!istAdmin) return fehlt(403, 'Nur für die Administration');
        if (methode === 'GET') return ok({ sortierung: 'raum', kacheln: 'auto', intervall: 10 });
        return ok({ ok: true, sortierung: 'raum', kacheln: 'auto', intervall: 10 });
      case 'login-log':
        if (!istAdmin) return fehlt(403, 'Nur für die Administration');
        if (seg[1] === 'einstellungen') {
          if (methode === 'GET') return ok({ aktiv: 1, erfolge: 1, tage: 30 });
          return ok({ ok: true });
        }
        if (methode === 'GET') {
          const f = (p.get('benutzer') || '').toLowerCase();
          return ok({ aktiv: true, eintraege: loginLog().filter((e) => !f || e.webuntis_benutzer.toLowerCase().includes(f)) });
        }
        return ok({ ok: true });
      case 'erinnerungen':
        if (!istAdmin) return fehlt(403, 'Nur für die Administration');
        if (seg[1] === 'einstellungen' && methode === 'GET') {
          return ok({
            liste_typ: 'DYNAMIC', liste_id: '4711',
            liste_name: 'Alle Erziehungsberechtigten (erfunden)',
            betreff: '',
            text: '',
            standard_betreff: 'Erinnerung: Elternsprechtag am Erfundenen Gymnasium am Beispielweg',
            standard_text: 'Liebe Erziehungsberechtigte,\n\nam kommenden Elternsprechtag können Sie noch Termine buchen. '
              + 'Bitte melden Sie sich dazu mit Ihrem WebUntis-Zugang an.\n\nMit freundlichen Grüßen\nIhre Schule (erfunden)',
          });
        }
        if (seg[1] === 'vorschau') return ok({ ok: true, anzahl: 742, vollstaendig: true });
        if (seg[1] === 'senden') return ok({ gesendet: 0, empfaenger: 742, vollstaendig: false, unklar: false, grund: 'Erfundener Versandlauf.' });
        return ok({ ok: true });
      case 'sondierung':
        return ok({ bericht: null });
      default:
        return ok({});
    }
  };
})();
