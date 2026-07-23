-- ============================================================
-- 02_sprechtag.sql – Datenmodell Paket 2
-- Idempotent: kann mehrfach eingespielt werden.
-- Einspielen:  mysql hornse_sprechtag < sql/02_sprechtag.sql
--
-- DATENSPARSAMKEIT (Vorgabe): Von Erziehungsberechtigten wird nur
-- die WebUntis-user.id gespeichert, KEIN Name, KEINE E-Mail.
-- Anzeigenamen kommen zur Laufzeit aus der Session bzw. dem
-- kurzlebigen Cache und werden beim Archivieren gelöscht.
-- ============================================================

-- ---- App-Administratoren (zusätzlich zu admin_kuerzel/personType 16) ----
CREATE TABLE IF NOT EXISTS app_admins (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lehrer_kuerzel  VARCHAR(20)  NOT NULL COMMENT 'WebUntis-Kürzel der Lehrkraft',
    anzeigename     VARCHAR(120) NOT NULL DEFAULT '',
    angelegt_von    VARCHAR(20)  NOT NULL DEFAULT '',
    angelegt_am     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_kuerzel (lehrer_kuerzel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Lehrkräfte (Stammdaten-Spiegel aus getTeachers) ----
CREATE TABLE IF NOT EXISTS lehrer (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    webuntis_id   INT          NOT NULL COMMENT 'id aus getTeachers (kann 0 sein!)',
    kuerzel       VARCHAR(20)  NOT NULL,
    name          VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Vor- und Nachname',
    aktiv         TINYINT(1)   NOT NULL DEFAULT 1,
    zuletzt_sync  DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_webuntis (webuntis_id),
    KEY idx_kuerzel (kuerzel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Räume (Stammdaten-Spiegel aus getRooms) ----
CREATE TABLE IF NOT EXISTS raeume (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    webuntis_id  INT          NOT NULL,
    kuerzel      VARCHAR(30)  NOT NULL,
    name         VARCHAR(120) NOT NULL DEFAULT '',
    aktiv        TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_webuntis_raum (webuntis_id),
    KEY idx_raum_kuerzel (kuerzel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Sonderrollen (SV-Lehrkraft, Beratung, Schulleitung …) ----
-- Auswahlliste für die Adminseite; erweiterbar.
CREATE TABLE IF NOT EXISTS sonderrollen (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bezeichnung  VARCHAR(80)  NOT NULL,
    reihenfolge  INT          NOT NULL DEFAULT 100,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_bezeichnung (bezeichnung)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Sprechtag (mehrere möglich; einer aktiv, ältere archiviert) ----
CREATE TABLE IF NOT EXISTS sprechtage (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                   VARCHAR(120) NOT NULL COMMENT 'z. B. Elternsprechtag 1. Halbjahr 2026/27',
    datum                  DATE         NOT NULL,
    beginn                 TIME         NOT NULL DEFAULT '15:00:00',
    ende                   TIME         NOT NULL DEFAULT '19:00:00',
    slot_minuten           INT UNSIGNED NOT NULL DEFAULT 10  COMMENT 'Länge eines buchbaren Zeitslots',
    max_termine_pro_eltern INT UNSIGNED NOT NULL DEFAULT 6   COMMENT 'gezählt je Elternteil, nicht je Kind',
    pause_nach_terminen    INT UNSIGNED NOT NULL DEFAULT 0   COMMENT '0 = keine automatische Pause',
    pause_minuten          INT UNSIGNED NOT NULL DEFAULT 10,
    phase                  ENUM('vorbereitung','phase1','phase2','geschlossen','archiviert')
                           NOT NULL DEFAULT 'vorbereitung',
    referenz_von           DATE         NULL COMMENT 'Referenzzeitraum für Lehrkraft-Ermittlung',
    referenz_bis           DATE         NULL,
    archiviert_am          DATETIME     NULL,
    angelegt_am            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_phase (phase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Teilnehmende Lehrkräfte je Sprechtag (Anwesenheit, Raum) ----
CREATE TABLE IF NOT EXISTS sprechtag_lehrer (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sprechtag_id   INT UNSIGNED NOT NULL,
    lehrer_id      INT UNSIGNED NOT NULL,
    anwesend_von   TIME         NULL COMMENT 'NULL = ganzer Zeitraum (Teilzeit: eigenes Fenster)',
    anwesend_bis   TIME         NULL,
    raum_id        INT UNSIGNED NULL,
    teilnahme      TINYINT(1)   NOT NULL DEFAULT 1,
    bemerkung      VARCHAR(190) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uniq_sprechtag_lehrer (sprechtag_id, lehrer_id),
    KEY idx_raum (sprechtag_id, raum_id),
    CONSTRAINT fk_stl_sprechtag FOREIGN KEY (sprechtag_id)
        REFERENCES sprechtage (id) ON DELETE CASCADE,
    CONSTRAINT fk_stl_lehrer FOREIGN KEY (lehrer_id)
        REFERENCES lehrer (id) ON DELETE CASCADE,
    CONSTRAINT fk_stl_raum FOREIGN KEY (raum_id)
        REFERENCES raeume (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Sonderlehrkräfte: für alle (oder bestimmte Jahrgänge) buchbar ----
CREATE TABLE IF NOT EXISTS sprechtag_sonderlehrer (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sprechtag_id  INT UNSIGNED NOT NULL,
    lehrer_id     INT UNSIGNED NOT NULL,
    rolle_id      INT UNSIGNED NOT NULL,
    jahrgaenge    VARCHAR(120) NOT NULL DEFAULT ''
                  COMMENT 'leer = alle; sonst Komma-Liste, z. B. "EF,Q1,Q2"',
    PRIMARY KEY (id),
    UNIQUE KEY uniq_sonder (sprechtag_id, lehrer_id, rolle_id),
    CONSTRAINT fk_sl_sprechtag FOREIGN KEY (sprechtag_id)
        REFERENCES sprechtage (id) ON DELETE CASCADE,
    CONSTRAINT fk_sl_lehrer FOREIGN KEY (lehrer_id)
        REFERENCES lehrer (id) ON DELETE CASCADE,
    CONSTRAINT fk_sl_rolle FOREIGN KEY (rolle_id)
        REFERENCES sonderrollen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Cache: welche Lehrkraft unterrichtet welches Kind ----
-- Aus timetable/entries (nur REGULAR/CANCELLED, ohne Vertretungen).
-- Enthält KEINE Namen von Kindern – nur die WebUntis-Schüler-ID.
CREATE TABLE IF NOT EXISTS kind_lehrer_cache (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sprechtag_id  INT UNSIGNED NOT NULL,
    schueler_id   INT UNSIGNED NOT NULL COMMENT 'WebUntis-Schüler-ID (user.students[].id)',
    lehrer_id     INT UNSIGNED NOT NULL,
    faecher       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'Kürzel, kommagetrennt',
    stunden       INT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'Sortiersignal für die Buchungsliste',
    ermittelt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_kind_lehrer (sprechtag_id, schueler_id, lehrer_id),
    KEY idx_kind (sprechtag_id, schueler_id),
    CONSTRAINT fk_klc_sprechtag FOREIGN KEY (sprechtag_id)
        REFERENCES sprechtage (id) ON DELETE CASCADE,
    CONSTRAINT fk_klc_lehrer FOREIGN KEY (lehrer_id)
        REFERENCES lehrer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Einladungen für Phase 1 ----
-- Lehrkraft lädt gezielt die Eltern eines Kindes ein.
CREATE TABLE IF NOT EXISTS einladungen (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sprechtag_id  INT UNSIGNED NOT NULL,
    lehrer_id     INT UNSIGNED NOT NULL,
    schueler_id   INT UNSIGNED NOT NULL COMMENT 'WebUntis-Schüler-ID',
    hinweis       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'optionaler Text an die Eltern',
    erledigt      TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '1 = Termin gebucht',
    angelegt_am   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_einladung (sprechtag_id, lehrer_id, schueler_id),
    KEY idx_einl_kind (sprechtag_id, schueler_id),
    CONSTRAINT fk_ein_sprechtag FOREIGN KEY (sprechtag_id)
        REFERENCES sprechtage (id) ON DELETE CASCADE,
    CONSTRAINT fk_ein_lehrer FOREIGN KEY (lehrer_id)
        REFERENCES lehrer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Buchungen ----
-- Eine Buchung hängt IMMER an einem Kind (die Lehrkraft muss wissen,
-- über wen gesprochen wird). Gezählt wird je Elternteil (eltern_user_id).
-- Doppelbuchung eines Slots verhindert der UNIQUE KEY.
CREATE TABLE IF NOT EXISTS buchungen (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sprechtag_id    INT UNSIGNED NOT NULL,
    lehrer_id       INT UNSIGNED NOT NULL,
    slot_beginn     TIME         NOT NULL,
    eltern_user_id  INT UNSIGNED NOT NULL COMMENT 'WebUntis user.id – KEIN Name gespeichert!',
    schueler_id     INT UNSIGNED NOT NULL COMMENT 'WebUntis-Schüler-ID des betroffenen Kindes',
    phase           ENUM('phase1','phase2') NOT NULL DEFAULT 'phase2'
                    COMMENT 'phase1-Buchungen dürfen Eltern nicht selbst löschen',
    gebucht_von     ENUM('eltern','lehrkraft','schueler','admin') NOT NULL DEFAULT 'eltern',
    gebucht_am      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_slot (sprechtag_id, lehrer_id, slot_beginn),
    KEY idx_eltern (sprechtag_id, eltern_user_id),
    KEY idx_kind_buchung (sprechtag_id, schueler_id),
    CONSTRAINT fk_bu_sprechtag FOREIGN KEY (sprechtag_id)
        REFERENCES sprechtage (id) ON DELETE CASCADE,
    CONSTRAINT fk_bu_lehrer FOREIGN KEY (lehrer_id)
        REFERENCES lehrer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Standard-Sonderrollen (idempotent) ----
INSERT IGNORE INTO sonderrollen (bezeichnung, reihenfolge) VALUES
    ('Schulleitung',              10),
    ('Stufenleitung',             20),
    ('Beratungslehrkraft',        30),
    ('SV-Verbindungslehrkraft',   40),
    ('Sonderpädagogik/Inklusion', 50),
    ('Sozialpädagogik',           60),
    ('Sonstige',                  99);
