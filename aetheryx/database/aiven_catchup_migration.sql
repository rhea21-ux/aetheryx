-- ============================================================================
-- ÆTHERYX – Aiven Production Catch-Up Migration
-- ============================================================================
-- Brings the Aiven `aetheryx` database fully up to date with the latest
-- application code. Every statement is idempotent (CREATE IF NOT EXISTS,
-- column-existence checks) so it is safe to run more than once.
--
-- Run this against your Aiven MySQL database.
-- ============================================================================

-- ────────────────────────────────────────────────────────────────────────────
-- 1. Core session storage
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `aetheryx_sessions` (
  `session_id` VARCHAR(128) COLLATE ascii_bin NOT NULL,
  `session_data` MEDIUMBLOB NOT NULL,
  `last_activity` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `idx_aetheryx_sessions_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 2. Users
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `handle` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','hero','enemy') NOT NULL DEFAULT 'hero',
  `status` ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_handle` (`handle`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 3. Tab-based authentication sessions  *** THIS IS THE TABLE CAUSING YOUR ERROR ***
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `tab_auth_sessions` (
  `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `last_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_hash`),
  KEY `idx_tab_auth_user` (`user_id`,`revoked_at`),
  KEY `idx_tab_auth_expiry` (`expires_at`),
  CONSTRAINT `fk_tab_auth_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 4. Categories
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 5. Equipment
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `equipment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `access_role` ENUM('common','hero','enemy') NOT NULL DEFAULT 'common',
  `is_black_market` TINYINT(1) NOT NULL DEFAULT 0,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `brand` VARCHAR(100) DEFAULT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
  `available_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('available','unavailable','maintenance') NOT NULL DEFAULT 'available',
  `condition_status` ENUM('excellent','good','fair','damaged') DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipment_category` (`category_id`),
  KEY `idx_equipment_status` (`status`),
  CONSTRAINT `fk_aiven_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add is_black_market if missing (older schema)
SET @has_bm = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment' AND COLUMN_NAME = 'is_black_market');
SET @ddl = IF(@has_bm = 0, 'ALTER TABLE equipment ADD COLUMN is_black_market TINYINT(1) NOT NULL DEFAULT 0 AFTER access_role', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add catalogue detail columns if missing
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipment' AND COLUMN_NAME='item_type');
SET @ddl = IF(@col=0, 'ALTER TABLE equipment ADD COLUMN item_type VARCHAR(80) NULL', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipment' AND COLUMN_NAME='lore');
SET @ddl = IF(@col=0, 'ALTER TABLE equipment ADD COLUMN lore TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipment' AND COLUMN_NAME='rarity');
SET @ddl = IF(@col=0, 'ALTER TABLE equipment ADD COLUMN rarity VARCHAR(24) NULL', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipment' AND COLUMN_NAME='fictional_effect');
SET @ddl = IF(@col=0, 'ALTER TABLE equipment ADD COLUMN fictional_effect VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ────────────────────────────────────────────────────────────────────────────
-- 6. Cart
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `equipment_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_user_equipment` (`user_id`,`equipment_id`),
  CONSTRAINT `fk_aiven_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aiven_cart_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 7. Inventory
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `equipment_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('assigned','returned','sold','lost','damaged') NOT NULL DEFAULT 'assigned',
  `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(10,2) DEFAULT NULL,
  `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `returned_at` TIMESTAMP NULL DEFAULT NULL,
  `sold_at` TIMESTAMP NULL DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aiven_inventory_user` (`user_id`,`status`),
  CONSTRAINT `fk_aiven_inventory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aiven_inventory_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 8. Orders + order items + payments + delivery
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `order_number` VARCHAR(32) NOT NULL,
  `checkout_token` CHAR(36) NOT NULL,
  `order_status` ENUM('pending','confirmed','processing','ready_for_delivery','shipped','out_for_delivery','delivered','completed','cancelled','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_method` ENUM('cash_on_delivery','demo_payment') NOT NULL,
  `payment_status` ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `cancellation_reason` VARCHAR(500) DEFAULT NULL,
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_order_number` (`order_number`),
  UNIQUE KEY `uq_orders_user_checkout_token` (`user_id`,`checkout_token`),
  KEY `idx_orders_user_created` (`user_id`,`created_at`),
  KEY `idx_orders_status_payment` (`order_status`,`payment_status`),
  CONSTRAINT `fk_aiven_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Widen order_status enum if table existed with older values
ALTER TABLE `orders`
  MODIFY COLUMN `order_status` ENUM('pending','confirmed','processing','ready_for_delivery','shipped','out_for_delivery','delivered','completed','cancelled','failed','refunded') NOT NULL DEFAULT 'pending';

-- Add cancellation columns if missing
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='cancellation_reason');
SET @ddl = IF(@col=0, 'ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(500) NULL AFTER payment_status', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='cancelled_at');
SET @ddl = IF(@col=0, 'ALTER TABLE orders ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER cancellation_reason', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `equipment_id` INT UNSIGNED DEFAULT NULL,
  `equipment_name` VARCHAR(150) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_aiven_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aiven_order_items_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add resale columns to order_items if missing
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='resale_listing_id');
SET @ddl = IF(@col=0, 'ALTER TABLE order_items ADD COLUMN resale_listing_id INT UNSIGNED NULL, ADD COLUMN seller_id INT UNSIGNED NULL, ADD COLUMN inventory_id INT UNSIGNED NULL, ADD KEY idx_order_items_resale (resale_listing_id)', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `transaction_reference` VARCHAR(48) NOT NULL,
  `payment_method` ENUM('cash_on_delivery','demo_payment') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('pending','paid','failed','refunded','cancelled') NOT NULL,
  `is_simulated` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_transaction_reference` (`transaction_reference`),
  UNIQUE KEY `uq_aiven_payment_transactions_order` (`order_id`),
  CONSTRAINT `fk_aiven_payment_transactions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_delivery_details` (
  `order_id` INT UNSIGNED NOT NULL,
  `recipient_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `address_line` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `region` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(20) NOT NULL,
  `delivery_notes` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  CONSTRAINT `fk_aiven_order_delivery_details_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 9. Notifications
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aiven_notifications_user` (`user_id`,`is_read`),
  CONSTRAINT `fk_aiven_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 10. Compatibility tables
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `compatibility` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` INT UNSIGNED NOT NULL,
  `compatible_equipment_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'compatible',
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aiven_compat_pair` (`equipment_id`,`compatible_equipment_id`),
  CONSTRAINT `fk_aiven_compat_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aiven_compat_target` FOREIGN KEY (`compatible_equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `resource_type` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipment_resource_compatibility` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assigned_user_id` INT UNSIGNED DEFAULT NULL,
  `equipment_id` INT UNSIGNED NOT NULL,
  `resource_id` INT UNSIGNED DEFAULT NULL,
  `compatible_equipment_id` INT UNSIGNED DEFAULT NULL,
  `compatibility_type` ENUM('compatible','incompatible','situational') NOT NULL DEFAULT 'compatible',
  `notes` TEXT DEFAULT NULL,
  `situations` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_erc_equipment_resource` (`equipment_id`,`resource_id`),
  UNIQUE KEY `uq_user_equipment_compatibility_pair` (`assigned_user_id`,`equipment_id`,`compatible_equipment_id`),
  KEY `idx_erc_resource` (`resource_id`),
  CONSTRAINT `fk_erc_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_erc_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_erc_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_erc_compatible_equipment` FOREIGN KEY (`compatible_equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipment_compatibility_roles` (
  `compatibility_id` INT UNSIGNED NOT NULL,
  `compatible_role` ENUM('mage','fighter','marksman','tank','support') NOT NULL,
  PRIMARY KEY (`compatibility_id`,`compatible_role`),
  KEY `idx_compatibility_roles_role` (`compatible_role`),
  CONSTRAINT `fk_compatibility_roles_record`
    FOREIGN KEY (`compatibility_id`) REFERENCES `equipment_resource_compatibility` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 11. Equipment character roles
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `equipment_character_roles` (
  `equipment_id` INT UNSIGNED NOT NULL,
  `character_role` ENUM('mage','fighter','marksman','tank','support') NOT NULL,
  PRIMARY KEY (`equipment_id`, `character_role`),
  KEY `idx_equipment_character_role` (`character_role`, `equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 12. Resale listings
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `resale_listings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `inventory_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NOT NULL,
  `asking_price` DECIMAL(10,2) NOT NULL,
  `condition_grade` ENUM('like_new','good','fair','poor') NOT NULL,
  `condition_description` VARCHAR(1000) NOT NULL,
  `defects` VARCHAR(1000) NULL,
  `reported_ownership_months` SMALLINT UNSIGNED NULL,
  `status` ENUM('active','reserved','sold','withdrawn') NOT NULL DEFAULT 'active',
  `reservation_order_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sold_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_resale_status_created` (`status`, `created_at`),
  KEY `idx_resale_seller_status` (`seller_id`, `status`),
  CONSTRAINT `chk_resale_asking_price` CHECK (`asking_price` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 13. Rules
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `rule_type` VARCHAR(100) DEFAULT NULL,
  `condition_data` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 14. Activity logs
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `entity_type` VARCHAR(100) DEFAULT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aiven_activity_user` (`user_id`),
  KEY `idx_aiven_activity_created` (`created_at`),
  CONSTRAINT `fk_aiven_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────────────
-- 15. Maintenance (Sentinel)
-- ────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` INT UNSIGNED NOT NULL,
  `requester_id` INT UNSIGNED NOT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `description` TEXT NOT NULL,
  `reported_severity` ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  `status` ENUM('open','assigned','in_progress','resolved','cancelled') NOT NULL DEFAULT 'open',
  `resolution_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_maint_equipment` (`equipment_id`),
  KEY `idx_maint_requester` (`requester_id`),
  KEY `idx_maint_assigned` (`assigned_to`),
  KEY `idx_maint_status` (`status`),
  CONSTRAINT `fk_maint_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_maint_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_maint_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `maintenance_updates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT UNSIGNED NOT NULL,
  `actor_id` INT UNSIGNED NOT NULL,
  `old_status` ENUM('open','assigned','in_progress','resolved','cancelled') DEFAULT NULL,
  `new_status` ENUM('open','assigned','in_progress','resolved','cancelled') NOT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mupdate_request` (`request_id`),
  KEY `idx_mupdate_actor` (`actor_id`),
  CONSTRAINT `fk_mupdate_request` FOREIGN KEY (`request_id`) REFERENCES `maintenance_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mupdate_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DONE – All tables and columns are now in sync with the application code.
-- ============================================================================
