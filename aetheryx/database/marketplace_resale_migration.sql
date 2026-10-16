-- AETHERYX equipment roles and one-of-a-kind resale listings.
-- Run once against the existing database after backing it up. Safe to re-run.
-- Supports the local schema name and production schema name used in this repo.

CREATE TABLE IF NOT EXISTS equipment_character_roles (
  equipment_id BIGINT UNSIGNED NOT NULL,
  character_role ENUM('mage','fighter','marksman','tank','support') NOT NULL,
  PRIMARY KEY (equipment_id, character_role),
  KEY idx_equipment_character_role (character_role, equipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add the catalogue detail fields only when absent (repeatable migration).
SET @db_name = DATABASE();
SET @column_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='equipment' AND column_name='item_type');
SET @ddl = IF(@column_exists=0, 'ALTER TABLE equipment ADD COLUMN item_type VARCHAR(80) NULL', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @column_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='equipment' AND column_name='lore');
SET @ddl = IF(@column_exists=0, 'ALTER TABLE equipment ADD COLUMN lore TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @column_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='equipment' AND column_name='rarity');
SET @ddl = IF(@column_exists=0, 'ALTER TABLE equipment ADD COLUMN rarity VARCHAR(24) NULL', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @column_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db_name AND table_name='equipment' AND column_name='fictional_effect');
SET @ddl = IF(@column_exists=0, 'ALTER TABLE equipment ADD COLUMN fictional_effect VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS resale_listings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  inventory_id BIGINT UNSIGNED NOT NULL,
  seller_id BIGINT UNSIGNED NOT NULL,
  asking_price DECIMAL(10,2) NOT NULL,
  condition_grade ENUM('like_new','good','fair','poor') NOT NULL,
  condition_description VARCHAR(1000) NOT NULL,
  defects VARCHAR(1000) NULL,
  reported_ownership_months SMALLINT UNSIGNED NULL,
  status ENUM('active','reserved','sold','withdrawn') NOT NULL DEFAULT 'active',
  reservation_order_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  sold_at TIMESTAMP NULL DEFAULT NULL,
  active_inventory_id INT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN status IN ('active','reserved') THEN inventory_id ELSE NULL END) STORED,
  UNIQUE KEY uq_resale_active_inventory (active_inventory_id),
  KEY idx_resale_status_created (status, created_at),
  KEY idx_resale_seller_status (seller_id, status),
  CONSTRAINT chk_resale_asking_price CHECK (asking_price > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade tables created by an earlier pass of this same additive migration.
SET @column_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='resale_listings' AND column_name='reservation_order_id');
SET @ddl = IF(@column_exists=0, 'ALTER TABLE resale_listings ADD COLUMN reservation_order_id INT UNSIGNED NULL AFTER status', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE resale_listings MODIFY COLUMN status ENUM('active','reserved','sold','withdrawn') NOT NULL DEFAULT 'active';
ALTER TABLE resale_listings DROP INDEX uq_resale_active_inventory, DROP COLUMN active_inventory_id,
  ADD COLUMN active_inventory_id BIGINT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN status IN ('active','reserved') THEN inventory_id ELSE NULL END) STORED,
  ADD UNIQUE KEY uq_resale_active_inventory (active_inventory_id);

-- Order-line traceability for completed resale sales.
SET @db_name = DATABASE();
SET @column_exists = (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db_name AND table_name='order_items' AND column_name='resale_listing_id');
SET @ddl = IF(@column_exists=0,
  'ALTER TABLE order_items ADD COLUMN resale_listing_id INT UNSIGNED NULL, ADD COLUMN seller_id INT UNSIGNED NULL, ADD COLUMN inventory_id INT UNSIGNED NULL, ADD KEY idx_order_items_resale (resale_listing_id)',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- This application has deployed schemas with both signed and unsigned INT
-- identifiers. These reference IDs deliberately avoid incompatible foreign
-- key declarations; every API rechecks the owner and inventory row in a
-- transaction. The active inventory unique key still prevents double listings.
