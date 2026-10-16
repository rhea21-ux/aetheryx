-- Make equipment compatibility records private to a selected Hero or Villain.
-- Run this after the equipment compatibility migration, with the target
-- ÆTHERYX database selected. Existing rows remain unassigned and are hidden
-- from regular users until an administrator edits
-- and assigns them.

SELECT COUNT(*) INTO @has_assigned_user_id
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND COLUMN_NAME = 'assigned_user_id';
SET @sql = IF(
  @has_assigned_user_id = 0,
  'ALTER TABLE equipment_resource_compatibility ADD COLUMN assigned_user_id INT UNSIGNED NULL AFTER id',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SELECT COLUMN_TYPE INTO @user_id_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'id';
SET @sql = CONCAT(
  'ALTER TABLE equipment_resource_compatibility MODIFY COLUMN assigned_user_id ',
  @user_id_type,
  ' NULL'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SELECT COUNT(*) INTO @has_assignment_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND CONSTRAINT_NAME = 'fk_erc_assigned_user';
SET @sql = IF(
  @has_assignment_fk = 0,
  'ALTER TABLE equipment_resource_compatibility ADD CONSTRAINT fk_erc_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SELECT COUNT(*) INTO @has_old_pair_key
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND INDEX_NAME = 'uq_equipment_compatibility_pair';
SET @sql = IF(
  @has_old_pair_key > 0,
  'ALTER TABLE equipment_resource_compatibility DROP INDEX uq_equipment_compatibility_pair',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SELECT COUNT(*) INTO @has_user_pair_key
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND INDEX_NAME = 'uq_user_equipment_compatibility_pair';
SET @sql = IF(
  @has_user_pair_key = 0,
  'ALTER TABLE equipment_resource_compatibility ADD UNIQUE KEY uq_user_equipment_compatibility_pair (assigned_user_id, equipment_id, compatible_equipment_id)',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;
