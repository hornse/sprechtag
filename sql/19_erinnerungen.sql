-- ============================================================
-- 19_erinnerungen.sql
-- Einstellungen für „Erinnerungen vor dem Sprechtag".
--
-- Der Versand läuft über das bestehende Dienstkonto und wird vom Admin bewusst
-- ausgelöst (Weg B). Die allgemeine Erinnerung geht an eine benannte
-- WebUntis-Empfängerliste (z. B. „alle Eltern"), die über typ + referenceId
-- angesprochen wird.
--
--   erinnerung_liste_typ    'DYNAMIC' | 'QUICK' – Art der WebUntis-Liste
--   erinnerung_liste_id     referenceId der Liste (Zahl, z. B. 11)
--   erinnerung_liste_name   Anzeigename (nur informativ, z. B. „alle Eltern")
--   erinnerung_betreff      Betreff der Erinnerungsnachricht
--   erinnerung_text         Markdown-Text der Erinnerung (leer = Standard)
--
-- Betreff/Text leer = im Code hinterlegter Standard wird verwendet.
-- Idempotent (INSERT IGNORE).
-- ============================================================

INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES
    ('erinnerung_liste_typ',  'DYNAMIC'),
    ('erinnerung_liste_id',   ''),
    ('erinnerung_liste_name', ''),
    ('erinnerung_betreff',    ''),
    ('erinnerung_text',       '');
