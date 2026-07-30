-- ============================================================
-- 16_login_log.sql
-- Einstellungen für das OPTIONALE Login-Protokoll im Admin.
--
-- Die Tabelle login_log existiert bereits (siehe 01_schema.sql) und wird für
-- die Brute-Force-Bremse benötigt – fehlgeschlagene Anmeldungen werden daher
-- immer kurzzeitig festgehalten. Diese Einstellungen steuern das ZUSÄTZLICHE,
-- für den Admin sichtbare Protokoll:
--
--   login_log_aktiv    '0'/'1'  – Admin-Ansicht + Protokollierung aktiv?
--                                 STANDARD 0 (aus): Datenschutz by default.
--   login_log_erfolge  '0'/'1'  – auch erfolgreiche Anmeldungen protokollieren?
--                                 STANDARD 0 (nur Fehlschläge).
--   login_log_tage     Zahl     – Aufbewahrung in Tagen; ältere Einträge werden
--                                 bei jeder Anmeldung entfernt. STANDARD 30.
--
-- Nur neutrale Standardwerte, idempotent (INSERT IGNORE lässt Gesetztes stehen).
-- ============================================================

INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES
    ('login_log_aktiv',   '0'),
    ('login_log_erfolge', '0'),
    ('login_log_tage',    '30');
