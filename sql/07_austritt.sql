-- ============================================================
-- 07_austritt.sql – Austrittsdatum für die Schülerliste
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/07_austritt.sql
--
-- Grund: Der Schild-Export enthält immer ALLE Schüler, auch ehemalige
-- (rund 3500 statt der aktuell etwa 1000). Ohne Austrittsdatum wäre die
-- Auswahlliste unbrauchbar. WebUntis pflegt dieselbe Information
-- ("aktiv" und "Austrittsdatum" in der Schülerverwaltung), gibt sie über
-- getStudents() aber nicht heraus.
-- ============================================================

SET @spalte_da := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schueler'
      AND COLUMN_NAME = 'austritt'
);

SET @sql := IF(@spalte_da = 0,
    'ALTER TABLE schueler ADD COLUMN austritt DATE NULL
     COMMENT "Austrittsdatum; leer oder in der Zukunft = noch an der Schule"',
    'SELECT "Spalte austritt existiert bereits" AS hinweis');

PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;

-- Index für die gefilterte Auswahlliste
SET @idx_da := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schueler'
      AND INDEX_NAME = 'idx_aktiv_klasse'
);

SET @sql2 := IF(@idx_da = 0,
    'ALTER TABLE schueler ADD INDEX idx_aktiv_klasse (aktiv, klasse, nachname)',
    'SELECT "Index idx_aktiv_klasse existiert bereits" AS hinweis');

PREPARE s2 FROM @sql2;
EXECUTE s2;
DEALLOCATE PREPARE s2;
