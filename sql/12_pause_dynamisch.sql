-- ============================================================
-- 12_pause_dynamisch.sql
-- Schalter für dynamische Pausen (Variante 1):
--   0 = feste Pause (bisheriges Verhalten: Pause immer an fester Position)
--   1 = dynamisch   (Pause nur wirksam, wenn die x zusammenhängend davor
--                    liegenden Slots belegt sind und die Pausenzeit frei ist)
-- Idempotent: Spalte nur anlegen, wenn sie noch fehlt.
-- ============================================================

SET @vorhanden := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sprechtage'
      AND COLUMN_NAME = 'pause_dynamisch'
);

SET @sql := IF(@vorhanden = 0,
    'ALTER TABLE sprechtage
       ADD COLUMN pause_dynamisch TINYINT(1) NOT NULL DEFAULT 0
       COMMENT ''1 = Pause nur bei durchgehender Belegung (dynamisch)''
       AFTER pause_minuten',
    'SELECT ''pause_dynamisch existiert bereits'' AS hinweis');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
