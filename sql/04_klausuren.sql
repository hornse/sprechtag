-- ============================================================
-- 04_klausuren.sql – Schalter für die Klausur-Auswertung
-- Idempotent (prüft, ob die Spalte schon existiert).
-- Einspielen: mysql hornse_sprechtag < sql/04_klausuren.sql
--
-- Hintergrund: Bei der Lehrkraft-Ermittlung werden Einträge vom Typ
-- EXAM mit Status REGULAR mitgewertet. In Unter- und Mittelstufe
-- beaufsichtigen die Fachlehrkräfte ihre eigenen Klassenarbeiten;
-- fällt der reguläre Unterricht im Referenzzeitraum aus oder wird
-- vertreten, ist der Klausurtermin der einzige Beleg. Wo Aufsichten
-- fachfremd verteilt werden, lässt sich das je Sprechtag abschalten.
-- ============================================================

SET @spalte_da := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sprechtage'
      AND COLUMN_NAME = 'klausuren_werten'
);

SET @sql := IF(@spalte_da = 0,
    'ALTER TABLE sprechtage ADD COLUMN klausuren_werten TINYINT(1) NOT NULL DEFAULT 1
     COMMENT "1 = EXAM/REGULAR bei der Lehrkraftermittlung mitwerten"',
    'SELECT "Spalte klausuren_werten existiert bereits" AS hinweis');

PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;

-- Klausur-Anzahl im Cache: Damit die Oberfläche kennzeichnen kann,
-- dass eine Lehrkraft nur über einen Klausurtermin gefunden wurde.
SET @spalte2_da := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'kind_lehrer_cache'
      AND COLUMN_NAME = 'klausuren'
);

SET @sql2 := IF(@spalte2_da = 0,
    'ALTER TABLE kind_lehrer_cache ADD COLUMN klausuren INT UNSIGNED NOT NULL DEFAULT 0
     COMMENT "Zahl der EXAM/REGULAR-Einträge (zählen nicht als Unterrichtsstunden)"',
    'SELECT "Spalte klausuren existiert bereits" AS hinweis');

PREPARE s2 FROM @sql2;
EXECUTE s2;
DEALLOCATE PREPARE s2;
