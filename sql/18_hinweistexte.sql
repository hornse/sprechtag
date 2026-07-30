-- ============================================================
-- 18_hinweistexte.sql
-- Optionale, von der Schule pflegbare Hinweistexte (Markdown), analog zum
-- Hilfe-Zusatztext aus 17_hilfe_zusatz.sql.
--
--   buchung_hinweis  – erscheint oben auf der Buchungsseite (Eltern/Schüler)
--   login_hinweis    – erscheint auf der Anmeldeseite
--
-- Beide leer als Standard (nichts wird angezeigt). Unterstützen dieselben
-- Platzhalter ({{kontakt}}, {{schulname}}, {{titel}}) und werden serverseitig
-- sicher gerendert. Idempotent.
-- ============================================================

INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES
    ('buchung_hinweis', ''),
    ('login_hinweis',   '');
