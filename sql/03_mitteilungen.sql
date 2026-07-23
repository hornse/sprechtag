-- ============================================================
-- 03_mitteilungen.sql – Mitteilungs-Warteschlange (Paket 3)
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/03_mitteilungen.sql
--
-- Speichert NUR die WebUntis-user.id des Empfängers (kein Name,
-- keine E-Mail). Der Text wird gespeichert, damit die Lehrkraft ihn
-- bei fehlgeschlagenem Versand manuell übernehmen kann; beim
-- Archivieren des Sprechtags wird alles gelöscht.
-- ============================================================

CREATE TABLE IF NOT EXISTS mitteilungen (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sprechtag_id       INT UNSIGNED NOT NULL,
    empfaenger_user_id INT UNSIGNED NOT NULL COMMENT 'WebUntis user.id (nicht personId!)',
    anlass             ENUM('bestaetigung','absage','hinweis') NOT NULL DEFAULT 'hinweis',
    betreff            VARCHAR(190) NOT NULL,
    text               TEXT         NOT NULL,
    status             ENUM('offen','gesendet','verworfen') NOT NULL DEFAULT 'offen',
    grund              VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Fehlermeldung des letzten Versuchs',
    versuche           INT UNSIGNED NOT NULL DEFAULT 0,
    angelegt_am        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    gesendet_am        DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_status (sprechtag_id, status),
    KEY idx_empfaenger (empfaenger_user_id),
    CONSTRAINT fk_mit_sprechtag FOREIGN KEY (sprechtag_id)
        REFERENCES sprechtage (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schlüssel-Wert-Einstellungen (u. a. die erprobte Versand-Variante)
CREATE TABLE IF NOT EXISTS einstellungen (
    schluessel   VARCHAR(60)  NOT NULL,
    wert         VARCHAR(500) NOT NULL DEFAULT '',
    geaendert_am DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
