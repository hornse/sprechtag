-- ============================================================
-- 26_ein_termin_je_kind.sql – ein Termin je Kind und Lehrkraft (E24)
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/26_ein_termin_je_kind.sql
--
-- REIHENFOLGE (v0.9.84): Deploy, dann sql/26_pruefung.sql, dann diese Datei.
--   Erst wenn die Prüfdatei „OK“ meldet. Gibt es noch doppelte Termine,
--   bricht MariaDB das ALTER mit „Duplicate entry“ ab und ändert nichts.
--
-- Zweite Stufe zur Prüfung im Code (bu_kind_termin in slots.php): Der
-- Schlüssel fängt auch zwei gleichzeitige Buchungen beider Elternteile,
-- die beide die Prüfung im Code passieren. Ein abgesagter Termin liegt in
-- abgesagte_termine (E19), nicht in buchungen, und stört den Schlüssel nicht.
--
-- Zweimal einspielbar: ADD UNIQUE KEY IF NOT EXISTS (MariaDB). Am Text
-- geprüft (tests/run_ein_termin_je_kind.php); die Testdatenbank dort baut
-- den Schlüssel aus den Spalten dieser Datei.
-- ============================================================

ALTER TABLE buchungen ADD UNIQUE KEY IF NOT EXISTS uniq_kind_termin (sprechtag_id, lehrer_id, schueler_id);
