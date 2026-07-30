-- ============================================================
-- 15_kontakt.sql
-- Fügt den Branding-Schlüssel marke_kontakt hinzu (Kontakt für Rückfragen,
-- z. B. E-Mail). Für bestehende Installationen, bei denen 10_branding bereits
-- lief und der Schlüssel deshalb noch fehlt.
--
-- Der Wert bleibt hier LEER (neutral). Jede Schule trägt ihre Adresse im
-- Admin unter „Erscheinungsbild" ein. INSERT IGNORE lässt einen bereits
-- gesetzten Wert unangetastet. Idempotent.
-- ============================================================

INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES
    ('marke_kontakt', '');
