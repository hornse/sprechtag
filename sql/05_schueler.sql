-- ============================================================
-- 05_schueler.sql – Schülerliste für die Einladungsauswahl
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/05_schueler.sql
--
-- ZWECK: Lehrkräfte sollen Eltern über eine Klassenliste einladen
-- können statt über die Eingabe einer Schüler-ID.
--
-- HERKUNFT DER DATEN (zwei Wege, beide optional):
--   1. WebUntis: getStudents() liefert id, key (= Schild-ID), Namen –
--      ABER keine Klassenzuordnung (Befund 07/2026). Die Klasse kommt
--      deshalb aus den Stundenplänen oder aus Weg 2.
--   2. CSV-Import aus Schild-NRW: liefert Klasse zuverlässig.
--      Verknüpfung mit WebUntis über die Schild-ID (= WebUntis 'key').
--
-- DATENSCHUTZ: Diese Tabelle enthält Namen und ist damit die einzige
-- Stelle im System mit personenbezogenen Schülerdaten. Sie dient nur
-- der Auswahl durch Lehrkräfte und lässt sich jederzeit leeren
-- (Adminseite → Schülerliste → Alle Einträge löschen).
-- ============================================================

CREATE TABLE IF NOT EXISTS schueler (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    webuntis_id  INT UNSIGNED NULL COMMENT 'id aus getStudents – Bezug zu Buchungen',
    schild_id    VARCHAR(30)  NOT NULL DEFAULT '' COMMENT 'key aus WebUntis = Schild-ID',
    vorname      VARCHAR(80)  NOT NULL DEFAULT '',
    nachname     VARCHAR(80)  NOT NULL DEFAULT '',
    klasse       VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'z. B. 6b, EF, Q1',
    quelle       ENUM('webuntis','csv','manuell') NOT NULL DEFAULT 'webuntis',
    aktiv        TINYINT(1)   NOT NULL DEFAULT 1,
    aktualisiert DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_webuntis_schueler (webuntis_id),
    KEY idx_klasse (klasse, nachname),
    KEY idx_schild (schild_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
