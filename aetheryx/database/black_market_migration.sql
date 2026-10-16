-- Add villain-purchasable Black Market listings without changing existing
-- equipment access or stock. Safe to run more than once.

SELECT COUNT(*) INTO @has_black_market
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment'
  AND COLUMN_NAME = 'is_black_market';
SET @sql = IF(
  @has_black_market = 0,
  'ALTER TABLE equipment ADD COLUMN is_black_market TINYINT(1) NOT NULL DEFAULT 0 AFTER access_role',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;
