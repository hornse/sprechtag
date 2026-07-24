-- ============================================================
-- 06_diagnose.sql – mehr Platz für Fehlermeldungen
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/06_diagnose.sql
--
-- Grund: Der Versandweg der WebUntis-API ist undokumentiert; das System
-- probiert vier Feldstrukturen durch. Für die Diagnose werden ALLE vier
-- Antworten gebraucht – VARCHAR(500) reicht dafür nicht.
-- ============================================================

SET @typ := (
    SELECT DATA_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mitteilungen'
      AND COLUMN_NAME = 'grund'
);

SET @sql := IF(@typ = 'varchar',
    'ALTER TABLE mitteilungen MODIFY grund TEXT NOT NULL
     COMMENT "Fehlermeldungen aller Versandversuche"',
    'SELECT "Spalte grund ist bereits TEXT" AS hinweis');

PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
