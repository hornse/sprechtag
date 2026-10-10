-- ============================================================
-- 22_kindname.sql – Name und Klasse des Kindes am Vorgang (Zug 4, E20)
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/22_kindname.sql
--
-- Ab v0.9.76 werden Name und Klasse beim Buchen, Einladen und Einreihen
-- festgehalten, statt zur Laufzeit aus der Tabelle schueler nachgeschlagen
-- zu werden. Grund: Die Kalender-Abos haben keine Sitzung (E8-Nachtrag), und
-- die Tabelle schueler fällt mit Schritt 4 weg.
--
-- Vorhandene Zeilen bekommen den Namen aus der alten Tabelle, und zwar nur,
-- wo noch keiner steht. Deshalb lässt sich die Datei zweimal einspielen. Die
-- Klasse ist dort leer, weil der Schild-Import nie lief; sie bleibt bei
-- Altzeilen leer.
--
-- Die Tabelle schueler bleibt hier stehen. Sie fällt mit Schritt 4.
-- Das Archivieren löscht die neuen Spalten mit ihren Zeilen
-- (tests/run_archivieren.php).
-- ============================================================

ALTER TABLE buchungen
    ADD COLUMN IF NOT EXISTS kind_name VARCHAR(170) NOT NULL DEFAULT ''
        COMMENT 'Nachname, Vorname – beim Buchen festgehalten (E20)',
    ADD COLUMN IF NOT EXISTS kind_klasse VARCHAR(30) NOT NULL DEFAULT ''
        COMMENT 'Klasse (timetable/filter displayName) – beim Buchen festgehalten (E20)';

ALTER TABLE einladungen
    ADD COLUMN IF NOT EXISTS kind_name VARCHAR(170) NOT NULL DEFAULT ''
        COMMENT 'Nachname, Vorname – beim Einladen festgehalten (E20)',
    ADD COLUMN IF NOT EXISTS kind_klasse VARCHAR(30) NOT NULL DEFAULT ''
        COMMENT 'Klasse – beim Einladen festgehalten (E20)';

ALTER TABLE mitteilungen
    ADD COLUMN IF NOT EXISTS kind_name VARCHAR(170) NOT NULL DEFAULT ''
        COMMENT 'Nachname, Vorname – beim Einreihen festgehalten (E20)',
    ADD COLUMN IF NOT EXISTS kind_klasse VARCHAR(30) NOT NULL DEFAULT ''
        COMMENT 'Klasse – beim Einreihen festgehalten (E20)';

-- Übernahme für vorhandene Zeilen (nur, wo noch kein Name steht)
UPDATE buchungen x JOIN schueler s ON s.webuntis_id = x.schueler_id
   SET x.kind_name = TRIM(CONCAT(s.nachname, IF(s.vorname = '', '', CONCAT(', ', s.vorname)))),
       x.kind_klasse = s.klasse
 WHERE x.kind_name = '';

UPDATE einladungen x JOIN schueler s ON s.webuntis_id = x.schueler_id
   SET x.kind_name = TRIM(CONCAT(s.nachname, IF(s.vorname = '', '', CONCAT(', ', s.vorname)))),
       x.kind_klasse = s.klasse
 WHERE x.kind_name = '';

UPDATE mitteilungen x JOIN schueler s ON s.webuntis_id = x.schueler_id
   SET x.kind_name = TRIM(CONCAT(s.nachname, IF(s.vorname = '', '', CONCAT(', ', s.vorname)))),
       x.kind_klasse = s.klasse
 WHERE x.kind_name = '';
