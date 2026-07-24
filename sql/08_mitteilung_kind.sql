-- ============================================================
-- 08_mitteilung_kind.sql – Bezug zum betroffenen Kind
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/08_mitteilung_kind.sql
--
-- Grund: In der Mitteilungsliste stand bisher nur die WebUntis-User-ID
-- der Eltern (z. B. "5984") – für Lehrkräfte wertlos. Der Name der
-- Eltern wird bewusst NICHT gespeichert (Datensparsamkeit); stattdessen
-- wird das betroffene Kind vermerkt, dessen Name ohnehin in der
-- Schülerliste steht. Das ist auch die fachlich relevantere Angabe.
-- ============================================================

SET @spalte_da := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mitteilungen'
      AND COLUMN_NAME = 'schueler_id'
);

SET @sql := IF(@spalte_da = 0,
    'ALTER TABLE mitteilungen ADD COLUMN schueler_id INT UNSIGNED NULL
     COMMENT "WebUntis-Schüler-ID des betroffenen Kindes (für die Anzeige)"',
    'SELECT "Spalte schueler_id existiert bereits" AS hinweis');

PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;

-- Neuer Anlass "einladung": Benachrichtigung, dass eine Lehrkraft die
-- Eltern zum Gespräch bittet (Phase 1).
SET @werte := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mitteilungen'
      AND COLUMN_NAME = 'anlass'
);

SET @sql2 := IF(@werte NOT LIKE '%einladung%',
    'ALTER TABLE mitteilungen MODIFY anlass
     ENUM("bestaetigung","absage","einladung","hinweis")
     NOT NULL DEFAULT "hinweis"',
    'SELECT "Anlass einladung existiert bereits" AS hinweis');

PREPARE s2 FROM @sql2;
EXECUTE s2;
DEALLOCATE PREPARE s2;
