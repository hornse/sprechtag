-- ============================================================
-- 10_branding.sql – Individualisierung (Logo, Texte)
--
-- Nutzt die bestehende Key-Value-Tabelle `einstellungen`. Kein neues
-- Schema nötig. Nur Standardwerte werden idempotent eingespielt; ein
-- bereits gesetzter Wert bleibt unangetastet (INSERT IGNORE).
--
-- Definierte Schlüssel:
--   marke_schulname   – Schulname im Kopf (max. 80)
--   marke_titel       – App-Titel / Seitentitel (max. 40)
--   marke_untertitel  – Untertitel im Kopf (max. 120)
--   marke_fusszeile   – Text der Fußzeile (max. 200)
--   marke_kontakt     – Kontakt für Rückfragen, z. B. E-Mail (max. 160)
--   marke_logo_pfad   – interner Dateipfad zum Logo (nie ans Frontend)
--   marke_logo_mime   – MIME-Type des Logos
--
-- Keine Farben (v0.9.52): Die beiden Farbschlüssel sind entfernt und
-- werden hier nicht mehr angelegt, sonst brächte ein erneutes
-- Einspielen sie zurück (20_farbfelder_entfernen.sql, E7).
--
-- Das Logo selbst liegt als Datei unter backend/data/logos/ und wird
-- ausschließlich über GET /api/einstellungen/logo ausgeliefert.
-- ============================================================

INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES
    ('marke_schulname',  'Ihre Schule'),
    ('marke_titel',      'Sprechtag'),
    ('marke_untertitel', 'Elternsprechtag'),
    ('marke_fusszeile',  'sprechtag · GPL-3.0-or-later'),
    ('marke_kontakt',    ''),
    ('marke_logo_pfad',  ''),
    ('marke_logo_mime',  '');
