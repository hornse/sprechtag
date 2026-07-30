-- ============================================================
-- 11_halbtags.sql – Halbtagskräfte und Referendariat
--
-- Fügt ein dauerhaftes Kennzeichen an der Lehrkraft hinzu: Wer als
-- Halbtagskraft (oder Referendar:in) markiert ist, muss nur einen
-- halben Sprechtag leisten. Die konkrete Hälfte wird pro Sprechtag
-- über das bestehende Fenster anwesend_von/anwesend_bis abgebildet –
-- „erste Hälfte" = beginn..Mitte, „zweite Hälfte" = Mitte..ende.
-- Es braucht also KEINE zusätzliche Spalte für die Hälfte.
--
-- Idempotent: die Spalte wird nur angelegt, wenn sie fehlt. MariaDB
-- kennt "ADD COLUMN IF NOT EXISTS".
-- ============================================================

ALTER TABLE lehrer
    ADD COLUMN IF NOT EXISTS halbtags TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Halbtagskraft/Referendar:in – nur ein halber Sprechtag';
