-- Extend the existing equipment/resource relationship table with equipment
-- pairs and role assignments while preserving all existing resource links.
-- Select the target ÆTHERYX database before running this migration.

SELECT COLUMN_TYPE INTO @equipment_id_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment'
  AND COLUMN_NAME = 'id';

SELECT COLUMN_TYPE INTO @resource_id_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'resources'
  AND COLUMN_NAME = 'id';

SELECT COUNT(*) INTO @has_compatible_equipment
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND COLUMN_NAME = 'compatible_equipment_id';
SET @sql = IF(
  @has_compatible_equipment = 0,
  CONCAT('ALTER TABLE equipment_resource_compatibility ADD COLUMN compatible_equipment_id ', @equipment_id_type, ' NULL AFTER resource_id'),
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SET @sql = CONCAT('ALTER TABLE equipment_resource_compatibility MODIFY COLUMN resource_id ', @resource_id_type, ' NULL');
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

ALTER TABLE equipment_resource_compatibility
  MODIFY COLUMN compatibility_type ENUM('compatible','incompatible','situational') NOT NULL DEFAULT 'compatible',
  MODIFY COLUMN notes TEXT NULL;

SELECT COUNT(*) INTO @has_situations
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND COLUMN_NAME = 'situations';
SET @sql = IF(
  @has_situations = 0,
  'ALTER TABLE equipment_resource_compatibility ADD COLUMN situations TEXT NULL AFTER notes',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SELECT COUNT(*) INTO @has_pair_key
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND INDEX_NAME = 'uq_equipment_compatibility_pair';
SET @sql = IF(
  @has_pair_key = 0,
  'ALTER TABLE equipment_resource_compatibility ADD UNIQUE KEY uq_equipment_compatibility_pair (equipment_id, compatible_equipment_id)',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SELECT COUNT(*) INTO @has_compatible_equipment_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND CONSTRAINT_NAME = 'fk_erc_compatible_equipment';
SET @sql = IF(
  @has_compatible_equipment_fk = 0,
  'ALTER TABLE equipment_resource_compatibility ADD CONSTRAINT fk_erc_compatible_equipment FOREIGN KEY (compatible_equipment_id) REFERENCES equipment(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SELECT COUNT(*) INTO @has_pair_order_check
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'equipment_resource_compatibility'
  AND CONSTRAINT_NAME = 'chk_equipment_pair_order'
  AND CONSTRAINT_TYPE = 'CHECK';
SET @sql = IF(
  @has_pair_order_check = 0,
  'ALTER TABLE equipment_resource_compatibility ADD CONSTRAINT chk_equipment_pair_order CHECK (compatible_equipment_id IS NULL OR equipment_id < compatible_equipment_id)',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @sql;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

CREATE TABLE IF NOT EXISTS equipment_compatibility_roles (
  compatibility_id INT UNSIGNED NOT NULL,
  compatible_role ENUM('mage','fighter','marksman','tank','support') NOT NULL,
  PRIMARY KEY (compatibility_id, compatible_role),
  KEY idx_compatibility_roles_role (compatible_role),
  CONSTRAINT fk_compatibility_roles_record
    FOREIGN KEY (compatibility_id) REFERENCES equipment_resource_compatibility(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
