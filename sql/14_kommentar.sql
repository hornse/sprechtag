-- ============================================================
-- 14_kommentar.sql
-- Optionaler Terminwunsch-/Themen-Kommentar der Eltern zur Buchung.
-- Freitext, den die Lehrkraft vorab sieht. Wird beim Archivieren mit den
-- übrigen personenbezogenen Buchungsdaten gelöscht.
-- Idempotent.
-- ============================================================

SET @vorhanden := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'buchungen'
      AND COLUMN_NAME = 'kommentar'
);

SET @sql := IF(@vorhanden = 0,
    'ALTER TABLE buchungen
       ADD COLUMN kommentar VARCHAR(280) NOT NULL DEFAULT ''''
       COMMENT ''optionaler Hinweis der Eltern (Thema)'' AFTER schueler_id',
    'SELECT ''kommentar existiert bereits'' AS hinweis');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
