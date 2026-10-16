-- ÆTHERYX SENTINEL — Migration 001
-- Run against the `aetheryx` database in Aiven's SQL editor.
-- Non-destructive: one additive column, two new tables. No existing data touched.

USE `aetheryx`;

-- --------------------------------------------------------
-- 1. Equipment condition tracking (nullable — existing rows become
--    NULL, meaning "not yet assessed", not a guessed value)
-- --------------------------------------------------------

SET @condition_column_sql = IF(
  EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'equipment'
      AND column_name = 'condition_status'
  ),
  'SELECT ''condition_status already exists; skipping''',
  'ALTER TABLE `equipment` ADD COLUMN `condition_status` enum(''excellent'',''good'',''fair'',''damaged'') COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `status`'
);
PREPARE condition_column_stmt FROM @condition_column_sql;
EXECUTE condition_column_stmt;
DEALLOCATE PREPARE condition_column_stmt;

-- --------------------------------------------------------
-- 2. Maintenance / service requests
--    Legacy `equipment_requests` rows, if present, are historical only and do not
--    participate in marketplace checkout.
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` int UNSIGNED NOT NULL,
  `requester_id` int UNSIGNED NOT NULL,
  `assigned_to` int UNSIGNED DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reported_severity` enum('low','normal','high','critical')
      COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `status` enum('open','assigned','in_progress','resolved','cancelled')
      COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_maint_equipment` (`equipment_id`),
  KEY `idx_maint_requester` (`requester_id`),
  KEY `idx_maint_assigned` (`assigned_to`),
  KEY `idx_maint_status` (`status`),
  CONSTRAINT `fk_maint_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_maint_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_maint_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Per-request status timeline (feeds maintenance-details.html;
--    a summary line is separately written to activity_logs for the
--    system-wide feed — this table is not a duplicate of that log)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `maintenance_updates` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` int UNSIGNED NOT NULL,
  `actor_id` int UNSIGNED NOT NULL,
  `old_status` enum('open','assigned','in_progress','resolved','cancelled')
      COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` enum('open','assigned','in_progress','resolved','cancelled')
      COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mupdate_request` (`request_id`),
  KEY `idx_mupdate_actor` (`actor_id`),
  CONSTRAINT `fk_mupdate_request` FOREIGN KEY (`request_id`) REFERENCES `maintenance_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mupdate_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
