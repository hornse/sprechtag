-- ============================================================
-- 26_pruefung.sql – NUR LESEN. Vor 26_ein_termin_je_kind.sql ausführen.
-- Einspielen: mysql hornse_sprechtag < sql/26_pruefung.sql
--
-- REIHENFOLGE (v0.9.84): Deploy, dann diese Prüfdatei, dann sql/26_ein_termin_je_kind.sql.
--   Ab v0.9.84 lehnen beide Buchungswege einen zweiten Termin desselben
--   Kindes bei derselben Lehrkraft ab (E24). Vorher gebuchte Doppelungen
--   bleiben aber stehen, und solange es eine gibt, lässt sich der Schlüssel
--   aus sql/26 nicht anlegen – MariaDB bricht das ALTER dann ab.
--
-- Ausgabe, nur Zahlen, keine Namen und keine Kennungen:
--   DOPPELT_FAELLE        – Kind und Lehrkraft am selben Sprechtag mit mehr
--                           als einem Termin
--   UEBERZAHLIGE_TERMINE  – so viele Termine müssten weichen, damit je Fall
--                           einer bleibt
-- Die letzte Zeile sagt es als Satz: „OK …“ oder „ANSEHEN …“.
--
-- Bei „ANSEHEN“: Welche Buchung weicht, entscheidet der Betreiber, nicht
-- eine Migration. Ansehen lassen sich die Fälle mit dieser Abfrage – nur
-- Kennungen der Buchung, Uhrzeit und Herkunft, keine Namen:
--   SELECT b.id, b.sprechtag_id, b.lehrer_id, b.slot_beginn, b.gebucht_von, b.gebucht_am
--     FROM buchungen b
--     JOIN (SELECT sprechtag_id, lehrer_id, schueler_id FROM buchungen
--            GROUP BY sprechtag_id, lehrer_id, schueler_id HAVING COUNT(*) > 1) d
--       ON d.sprechtag_id = b.sprechtag_id AND d.lehrer_id = b.lehrer_id
--      AND d.schueler_id = b.schueler_id
--    ORDER BY b.sprechtag_id, b.lehrer_id, b.schueler_id, b.slot_beginn;
-- Absagen über die Oberfläche (Lehrkraft oder Verwaltung) benachrichtigt
-- die Eltern und legt den Termin ab (E19); ein DELETE von Hand tut beides
-- nicht.
--
-- Geschrieben im gemeinsamen Teil von MariaDB und SQLite, damit
-- tests/run_ein_termin_je_kind.php die Datei ausführen kann.
-- ============================================================

SELECT COUNT(*) AS DOPPELT_FAELLE,
       COALESCE(SUM(n - 1), 0) AS UEBERZAHLIGE_TERMINE
  FROM (SELECT COUNT(*) AS n FROM buchungen
         GROUP BY sprechtag_id, lehrer_id, schueler_id HAVING COUNT(*) > 1) d;

SELECT CASE WHEN COUNT(*) = 0
            THEN 'OK – keine doppelten Termine; sql/26_ein_termin_je_kind.sql kann eingespielt werden'
            ELSE 'ANSEHEN – doppelte Termine; vor sql/26_ein_termin_je_kind.sql entscheiden, welche weichen'
       END AS ergebnis
  FROM (SELECT 1 AS f FROM buchungen
         GROUP BY sprechtag_id, lehrer_id, schueler_id HAVING COUNT(*) > 1) d;
