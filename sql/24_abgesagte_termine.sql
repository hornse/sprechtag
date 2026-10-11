-- ============================================================
-- 24_abgesagte_termine.sql – Ablage für Termine, die die Schule absagt (E19)
-- Idempotent. Einspielen: mysql hornse_sprechtag < sql/24_abgesagte_termine.sql
--
-- REIHENFOLGE (v0.9.83): Diese Datei VOR dem Deploy einspielen.
--   Der Code ab v0.9.83 legt jede Absage durch Lehrkraft oder Verwaltung
--   und jeden Krankheitsausfall hier ab, bevor er die Buchung löscht – in
--   einer Transaktion. Fehlt die Tabelle, scheitert jede Absage (500); die
--   Buchung bliebe dann zwar stehen, aber niemand könnte absagen. Der Code
--   bis v0.9.82 kennt die Tabelle nicht und stört sich nicht an ihr.
--   sql/25_einladung_erledigt_entfernen.sql kommt erst NACH dem Deploy.
--
-- Abgelegt wird nur, was die Schule absagt (anlass absage oder ausfall);
-- storniert eine Familie selbst, wird wie bisher nur gelöscht (E19, G2).
-- Keine Kennung der Eltern und kein Absagetext: Der Text steht in
-- mitteilungen, die Kennung braucht hier niemand (G3).
--
-- Name, Klasse und Hinweis sind so breit wie in buchungen (sql/22, sql/14),
-- damit die Übernahme nichts abschneidet. Das Archivieren des Sprechtags
-- löscht die Ablage mit (tests/run_archivieren.php); eine eigene Frist
-- gibt es nicht (E19-Nachtrag).
-- ============================================================

CREATE TABLE IF NOT EXISTS abgesagte_termine (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sprechtag_id    INT UNSIGNED NOT NULL,
    lehrer_id       INT UNSIGNED NOT NULL,
    slot_beginn     TIME         NOT NULL,
    schueler_id     INT UNSIGNED NOT NULL COMMENT 'WebUntis-Schüler-ID des Kindes',
    kind_name       VARCHAR(170) NOT NULL DEFAULT '' COMMENT 'aus der Buchung übernommen',
    kind_klasse     VARCHAR(30)  NOT NULL DEFAULT '' COMMENT 'aus der Buchung übernommen',
    kommentar       VARCHAR(280) NOT NULL DEFAULT '' COMMENT 'Hinweis der Eltern an die Lehrkraft (Thema)',
    anlass          ENUM('absage','ausfall') NOT NULL COMMENT 'absage = einzeln, ausfall = Krankheitsausfall',
    abgesagt_am     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ab_lehrer (sprechtag_id, lehrer_id),
    KEY idx_ab_kind (sprechtag_id, schueler_id),
    CONSTRAINT fk_ab_sprechtag FOREIGN KEY (sprechtag_id)
        REFERENCES sprechtage (id) ON DELETE CASCADE,
    CONSTRAINT fk_ab_lehrer FOREIGN KEY (lehrer_id)
        REFERENCES lehrer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
