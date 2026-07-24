-- ============================================================
-- 09_grund_null.sql – Korrektur zu 06_diagnose.sql
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/09_grund_null.sql
--
-- Grund: 06_diagnose.sql setzte die Spalte auf TEXT NOT NULL. TEXT-Spalten
-- können in MySQL keinen Standardwert haben – jeder INSERT ohne
-- ausdrückliches "grund" scheiterte deshalb mit Fehler 1364. Das traf
-- alle neu angelegten Mitteilungen.
-- ============================================================

SET @nullbar := (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mitteilungen'
      AND COLUMN_NAME = 'grund'
);

SET @sql := IF(@nullbar = 'NO',
    'ALTER TABLE mitteilungen MODIFY grund TEXT NULL
     COMMENT "Fehlermeldungen aller Versandversuche"',
    'SELECT "Spalte grund ist bereits NULL-fähig" AS hinweis');

PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
