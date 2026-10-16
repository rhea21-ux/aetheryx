-- Creates the equipment-to-resource relationship registry for an existing
-- Aiven database. Safe to run more than once; legacy rows are kept intact.
USE `aetheryx`;

CREATE TABLE IF NOT EXISTS `equipment_resource_compatibility` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` INT UNSIGNED NOT NULL,
  `resource_id` INT UNSIGNED NOT NULL,
  `compatibility_type` ENUM('compatible','incompatible') NOT NULL DEFAULT 'compatible',
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_erc_equipment_resource` (`equipment_id`,`resource_id`),
  KEY `idx_erc_resource` (`resource_id`),
  CONSTRAINT `fk_erc_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_erc_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
