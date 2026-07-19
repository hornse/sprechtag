-- ============================================================
-- 01_schema.sql – Grundschema "sprechtag" (Paket 1)
-- Idempotent: kann mehrfach eingespielt werden.
-- Einspielen:  mysql hornse_sprechtag < sql/01_schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS login_log (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    webuntis_benutzer  VARCHAR(190) NOT NULL COMMENT 'WebUntis-Benutzername',
    erfolgreich        TINYINT(1)   NOT NULL DEFAULT 0,
    grund              VARCHAR(190) NULL COMMENT 'z. B. sondierung, falsches_passwort',
    ip                 VARCHAR(45)  NOT NULL DEFAULT '',
    zeitpunkt          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_benutzer_zeit (webuntis_benutzer, zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
