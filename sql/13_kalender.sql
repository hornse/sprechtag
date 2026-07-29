-- ============================================================
-- 13_kalender.sql
-- Persönlicher, abonnierbarer iCal-Link je Elternkonto.
-- Der Token steht in der URL (sprechtag..../kalender/<token>.ics) und ersetzt
-- die WebUntis user.id, damit diese NICHT in der URL auftaucht. Der Token ist
-- geheim, lang und zufällig; er lässt sich neu erzeugen (Revocation).
-- Idempotent.
-- ============================================================

CREATE TABLE IF NOT EXISTS kalender_abo (
    eltern_user_id  INT UNSIGNED NOT NULL COMMENT 'WebUntis user.id – KEIN Name',
    token           CHAR(48)     NOT NULL,
    erstellt_am     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (eltern_user_id),
    UNIQUE KEY uniq_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
