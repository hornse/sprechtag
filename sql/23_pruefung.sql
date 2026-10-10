-- ============================================================
-- 23_pruefung.sql – NUR LESEN. Vor 23_schueler_entfernen.sql ausführen.
-- Einspielen: mysql hornse_sprechtag < sql/23_pruefung.sql
--
-- Zug 4, Schritt 4 (v0.9.82, E20): Die Tabelle schueler fällt. Vorher
-- zählt diese Datei, ob dabei ein Kindname verloren geht. Sie gibt nur
-- Zahlen aus, keine Namen und keine Kennungen.
--
-- Je Tabelle (buchungen, einladungen, mitteilungen):
--   leer_mit_kind   – Vorgänge mit Kind, deren kind_name leer ist
--   noch_fuellbar   – davon füllt 23_schueler_entfernen.sql aus der alten
--                     Tabelle nach
--   NICHT_FUELLBAR  – davon kennt auch die alte Tabelle keinen Namen.
--   ohne_kind       – Vorgänge ohne Kind (nur Mitteilungen können das sein);
--                     dort ist kein Name zu erwarten, sie zählen nicht mit.
--
-- NICHT_FUELLBAR ist die Zahl, auf die es ankommt. Diese Namen sind
-- schon heute verloren – das Entfernen der Tabelle ändert daran nichts,
-- aber danach gibt es keine Quelle mehr, aus der sie zu holen wären. Die
-- letzte Zeile der Ausgabe sagt es als Satz: „OK …“ oder „ANSEHEN …“.
-- Bei „ANSEHEN“: erst einspielen, wenn das so hingenommen wird.
--
-- Geschrieben im gemeinsamen Teil von MariaDB und SQLite (CASE statt IF),
-- damit tests/run_schuelerliste_abbau.php die Datei ausführen kann.
-- Nach Migration 23 meldet MariaDB hier, dass die Tabelle schueler fehlt.
-- ============================================================

SELECT 'buchungen' AS tabelle,
       COUNT(*) AS leer_mit_kind,
       COALESCE(SUM(CASE WHEN s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> '' THEN 1 ELSE 0 END), 0) AS noch_fuellbar,
       COALESCE(SUM(CASE WHEN s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> '' THEN 0 ELSE 1 END), 0) AS NICHT_FUELLBAR,
       (SELECT COUNT(*) FROM buchungen WHERE kind_name = '' AND COALESCE(schueler_id, 0) = 0) AS ohne_kind
  FROM buchungen x LEFT JOIN schueler s ON s.webuntis_id = x.schueler_id
 WHERE x.kind_name = '' AND x.schueler_id > 0
UNION ALL
SELECT 'einladungen',
       COUNT(*),
       COALESCE(SUM(CASE WHEN s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> '' THEN 1 ELSE 0 END), 0),
       COALESCE(SUM(CASE WHEN s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> '' THEN 0 ELSE 1 END), 0),
       (SELECT COUNT(*) FROM einladungen WHERE kind_name = '' AND COALESCE(schueler_id, 0) = 0)
  FROM einladungen x LEFT JOIN schueler s ON s.webuntis_id = x.schueler_id
 WHERE x.kind_name = '' AND x.schueler_id > 0
UNION ALL
SELECT 'mitteilungen',
       COUNT(*),
       COALESCE(SUM(CASE WHEN s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> '' THEN 1 ELSE 0 END), 0),
       COALESCE(SUM(CASE WHEN s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> '' THEN 0 ELSE 1 END), 0),
       (SELECT COUNT(*) FROM mitteilungen WHERE kind_name = '' AND COALESCE(schueler_id, 0) = 0)
  FROM mitteilungen x LEFT JOIN schueler s ON s.webuntis_id = x.schueler_id
 WHERE x.kind_name = '' AND x.schueler_id > 0;

SELECT CASE WHEN t.n = 0
         THEN 'OK – kein Vorgang mit Kind verliert seinen Namen. 23_schueler_entfernen.sql kann eingespielt werden.'
         ELSE CONCAT('ANSEHEN – ', t.n, ' Vorgänge mit Kind haben keinen Namen, und auch die alte Tabelle kennt ihn nicht (NICHT_FUELLBAR oben). Diese Namen sind schon heute verloren; das Entfernen ändert daran nichts, aber danach gibt es keine Quelle mehr. Erst einspielen, wenn das so hingenommen wird.')
       END AS ergebnis
  FROM (SELECT
          (SELECT COUNT(*) FROM buchungen x LEFT JOIN schueler s ON s.webuntis_id = x.schueler_id
            WHERE x.kind_name = '' AND x.schueler_id > 0
              AND NOT (s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> ''))
        + (SELECT COUNT(*) FROM einladungen x LEFT JOIN schueler s ON s.webuntis_id = x.schueler_id
            WHERE x.kind_name = '' AND x.schueler_id > 0
              AND NOT (s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> ''))
        + (SELECT COUNT(*) FROM mitteilungen x LEFT JOIN schueler s ON s.webuntis_id = x.schueler_id
            WHERE x.kind_name = '' AND x.schueler_id > 0
              AND NOT (s.id IS NOT NULL AND TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)) <> ''))
        AS n) t;
