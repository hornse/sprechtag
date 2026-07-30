-- ============================================================
-- 17_hilfe_zusatz.sql
-- Optionaler, von der Schule pflegbarer Hilfe-Zusatztext (Markdown).
-- Erscheint ZUSÄTZLICH zur eingebauten Hilfe; leer = nichts wird angezeigt.
--
--   hilfe_zusatz  – Markdown-Quelltext (max. ~8000 Zeichen), unterstützt
--                   Platzhalter wie {{kontakt}}, {{schulname}}, {{titel}}.
--
-- Neutraler Standard (leer), idempotent.
-- ============================================================

INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES
    ('hilfe_zusatz', '');
