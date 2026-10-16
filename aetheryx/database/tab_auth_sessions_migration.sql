-- Add independent tab authentication contexts without changing user data.
-- Run this against the same database configured in backend/config/database.php.

SELECT COLUMN_TYPE INTO @tab_auth_user_id_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'id';

SET @create_tab_auth_sessions = CONCAT(
  'CREATE TABLE IF NOT EXISTS tab_auth_sessions (',
  'token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  'user_id ', @tab_auth_user_id_type, ' NOT NULL,',
  'last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,',
  'expires_at TIMESTAMP NOT NULL,',
  'revoked_at TIMESTAMP NULL DEFAULT NULL,',
  'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,',
  'PRIMARY KEY (token_hash),',
  'KEY idx_tab_auth_user (user_id, revoked_at),',
  'KEY idx_tab_auth_expiry (expires_at),',
  'CONSTRAINT fk_tab_auth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE aetheryx_stmt FROM @create_tab_auth_sessions;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;
