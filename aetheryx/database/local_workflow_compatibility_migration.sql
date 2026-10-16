-- Add local assignment, maintenance, and activity-history support without dropping old data.
-- Legacy equipment-request approvals are intentionally no longer created.

USE `aetheryx_db`;

ALTER TABLE `inventory`
  MODIFY COLUMN `name` VARCHAR(150) NULL,
  MODIFY COLUMN `status` ENUM('available','unavailable','maintenance','assigned','returned','sold','lost','damaged') NOT NULL DEFAULT 'assigned',
  ADD COLUMN `user_id` INT NULL AFTER `id`,
  ADD COLUMN `equipment_id` INT NULL AFTER `user_id`,
  ADD COLUMN `assigned_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `returned_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `notes` TEXT NULL,
  ADD KEY `idx_local_inventory_user_status` (`user_id`,`status`),
  ADD KEY `idx_local_inventory_equipment` (`equipment_id`),
  ADD CONSTRAINT `fk_local_inventory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_local_inventory_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE;

ALTER TABLE `activity_logs`
  MODIFY COLUMN `description` TEXT NOT NULL,
  ADD COLUMN `entity_type` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN `entity_id` INT DEFAULT NULL,
  ADD COLUMN `user_agent` TEXT DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `equipment_id` INT NOT NULL,
  `requester_id` INT NOT NULL,
  `assigned_to` INT DEFAULT NULL,
  `description` TEXT NOT NULL,
  `reported_severity` ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  `status` ENUM('open','assigned','in_progress','resolved','cancelled') NOT NULL DEFAULT 'open',
  `resolution_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_local_maint_equipment` (`equipment_id`),
  KEY `idx_local_maint_requester` (`requester_id`),
  KEY `idx_local_maint_assigned` (`assigned_to`),
  KEY `idx_local_maint_status` (`status`),
  CONSTRAINT `fk_local_maint_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_local_maint_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_local_maint_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `maintenance_updates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `request_id` INT NOT NULL,
  `actor_id` INT NOT NULL,
  `old_status` ENUM('open','assigned','in_progress','resolved','cancelled') DEFAULT NULL,
  `new_status` ENUM('open','assigned','in_progress','resolved','cancelled') NOT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_local_mupdate_request` (`request_id`),
  KEY `idx_local_mupdate_actor` (`actor_id`),
  CONSTRAINT `fk_local_mupdate_request` FOREIGN KEY (`request_id`) REFERENCES `maintenance_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_local_mupdate_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
