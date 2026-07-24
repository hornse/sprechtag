-- ============================================================
-- 10_branding.sql – Individualisierung (Logo, Farben, Texte)
--
-- Nutzt die bestehende Key-Value-Tabelle `einstellungen`. Kein neues
-- Schema nötig. Nur Standardwerte werden idempotent eingespielt; ein
-- bereits gesetzter Wert bleibt unangetastet (INSERT IGNORE).
--
-- Definierte Schlüssel:
--   marke_schulname   – Schulname im Kopf (max. 80)
--   marke_titel       – App-Titel / Seitentitel (max. 40)
--   marke_untertitel  – Untertitel im Kopf (max. 120)
--   marke_farbe       – Akzentfarbe #RRGGBB (Standard: sprechtag-Blau)
--   marke_farbe2      – Sekundärfarbe #RRGGBB (Standard: sprechtag-Grün)
--   marke_fusszeile   – Text der Fußzeile (max. 200)
--   marke_logo_pfad   – interner Dateipfad zum Logo (nie ans Frontend)
--   marke_logo_mime   – MIME-Type des Logos
--
-- Das Logo selbst liegt als Datei unter backend/data/logos/ und wird
-- ausschließlich über GET /api/einstellungen/logo ausgeliefert.
-- ============================================================

INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES
    ('marke_schulname',  'Friedrich-Rückert-Gymnasium Düsseldorf'),
    ('marke_titel',      'Sprechtag'),
    ('marke_untertitel', 'Elternsprechtag · Friedrich-Rückert-Gymnasium Düsseldorf'),
    ('marke_farbe',      '#1d4e89'),
    ('marke_farbe2',     '#1e7d3e'),
    ('marke_fusszeile',  'sprechtag · GPL-3.0-or-later · Sebastian Horn'),
    ('marke_logo_pfad',  ''),
    ('marke_logo_mime',  '');
