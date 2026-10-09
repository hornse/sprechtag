-- ============================================================
-- 21_dienstkonto_entfernen.sql – v0.9.72 (E17)
--
-- Das Dienstkonto ist abgeschafft: Jede WebUntis-Aktion läuft über die
-- Sitzung der handelnden Person. Diese Migration
--   1. löscht die hinterlegten Zugangsdaten (Benutzername und das
--      verschlüsselte Passwort) aus `einstellungen`;
--   2. gibt der Warteschlange zwei Spalten:
--        lehrer_id      – die Lehrkraft, um deren Termin es geht. Daran
--                         erkennt der Versand „zu meinen Terminen“, auch
--                         nach einer Absage, wenn die Buchung gelöscht ist.
--        empfaenger_art – 'konto' (an ein WebUntis-Konto, wie bisher) oder
--                         'eltern' (an die Erziehungsberechtigten des Kindes
--                         über recipientOption PARENTS, empfaenger_user_id 0).
--
-- VOR dem Ausrollen von v0.9.72 einspielen: Die neuen Spalten sind für
-- v0.9.71 unschädlich (Vorgaben NULL bzw. 'konto'), v0.9.72 braucht sie.
-- Zweimal einspielbar (IF NOT EXISTS, DELETE ohne Wirkung beim zweiten Mal).
--
-- Den Schlüssel `dienstkonto_schluessel` in backend/config.php auf dem
-- Server danach von Hand entfernen – die Datei ist nicht versioniert.
-- ============================================================

DELETE FROM einstellungen
 WHERE schluessel IN ('dienstkonto_benutzer', 'dienstkonto_passwort');

ALTER TABLE mitteilungen
    ADD COLUMN IF NOT EXISTS lehrer_id INT UNSIGNED NULL
        COMMENT 'Lehrkraft, um deren Termin es geht (E17)',
    ADD COLUMN IF NOT EXISTS empfaenger_art VARCHAR(10) NOT NULL DEFAULT 'konto'
        COMMENT 'konto = empfaenger_user_id; eltern = PARENTS über schueler_id (E17)';
