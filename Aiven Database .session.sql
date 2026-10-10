USE aetheryx;

CREATE TABLE IF NOT EXISTS aetheryx_sessions (
    session_id VARCHAR(128) COLLATE ascii_bin NOT NULL,
    session_data MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    PRIMARY KEY (session_id),
    KEY idx_aetheryx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
