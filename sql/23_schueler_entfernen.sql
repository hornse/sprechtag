-- ============================================================
-- 23_schueler_entfernen.sql – die alte Schülerliste fällt (Zug 4, Schritt 4)
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/23_schueler_entfernen.sql
--
-- REIHENFOLGE (v0.9.82, E20-Nachtrag Schritt 4):
--   1. Deploy von v0.9.82 – der Code liest und schreibt die Tabelle nicht
--      mehr. Der alte Code (bis v0.9.81) braucht sie; vorher entfernt,
--      liefen Admin-Seite, Abgleich, CSV und beide Messrouten in einen 500.
--   2. sql/23_pruefung.sql ausführen und die Zahlen lesen, vor allem
--      NICHT_FUELLBAR und die letzte Zeile („OK …“ oder „ANSEHEN …“).
--   3. Diese Datei einspielen.
--
-- Zuerst füllt sie noch einmal nach wie sql/22: Name aus der alten Tabelle,
-- nur wo kind_name leer ist. Abweichend von 22 überschreibt sie eine schon
-- gesetzte Klasse nicht. Dann entfernt sie die Tabelle.
--
-- Zweimal einspielbar: Das Nachfüllen läuft nur, solange es die Tabelle
-- gibt (sonst eine Hinweiszeile), DROP TABLE IF EXISTS ist es von selbst.
-- Wie sql/07 setzt die Datei voraus, dass doppelte Anführungszeichen
-- Zeichenketten begrenzen (kein ANSI_QUOTES).
--
-- Am Text geprüft (tests/run_schuelerliste_abbau.php), nicht gegen
-- MariaDB – hier läuft keines. Den Namensausdruck teilt sie mit der
-- Prüfdatei; was die als „noch_fuellbar" zählt, schreibt diese hier.
-- ============================================================

SET @schueler_da := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schueler'
);

SET @sql_buchungen := IF(@schueler_da = 1,
    "UPDATE buchungen x JOIN schueler s ON s.webuntis_id = x.schueler_id
        SET x.kind_name = TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)),
            x.kind_klasse = CASE WHEN x.kind_klasse = '' THEN s.klasse ELSE x.kind_klasse END
      WHERE x.kind_name = ''",
    "SELECT 'Tabelle schueler gibt es nicht mehr – buchungen: nichts nachzufüllen' AS hinweis");
PREPARE n1 FROM @sql_buchungen;
EXECUTE n1;
DEALLOCATE PREPARE n1;

SET @sql_einladungen := IF(@schueler_da = 1,
    "UPDATE einladungen x JOIN schueler s ON s.webuntis_id = x.schueler_id
        SET x.kind_name = TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)),
            x.kind_klasse = CASE WHEN x.kind_klasse = '' THEN s.klasse ELSE x.kind_klasse END
      WHERE x.kind_name = ''",
    "SELECT 'Tabelle schueler gibt es nicht mehr – einladungen: nichts nachzufüllen' AS hinweis");
PREPARE n2 FROM @sql_einladungen;
EXECUTE n2;
DEALLOCATE PREPARE n2;

SET @sql_mitteilungen := IF(@schueler_da = 1,
    "UPDATE mitteilungen x JOIN schueler s ON s.webuntis_id = x.schueler_id
        SET x.kind_name = TRIM(CONCAT(s.nachname, CASE WHEN s.vorname = '' THEN '' ELSE CONCAT(', ', s.vorname) END)),
            x.kind_klasse = CASE WHEN x.kind_klasse = '' THEN s.klasse ELSE x.kind_klasse END
      WHERE x.kind_name = ''",
    "SELECT 'Tabelle schueler gibt es nicht mehr – mitteilungen: nichts nachzufüllen' AS hinweis");
PREPARE n3 FROM @sql_mitteilungen;
EXECUTE n3;
DEALLOCATE PREPARE n3;

DROP TABLE IF EXISTS schueler;
