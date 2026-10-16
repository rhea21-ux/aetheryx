-- Bring the older local aetheryx_db users table in line with registration.
-- This is a one-time migration for the legacy table that has `password`
-- and role values admin/moderator/user. It preserves existing rows/passwords.

USE `aetheryx_db`;

ALTER TABLE `users`
  ADD COLUMN `handle` VARCHAR(30) NULL AFTER `full_name`,
  ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `password`,
  MODIFY COLUMN `password` VARCHAR(255) NULL,
  MODIFY COLUMN `role` ENUM('admin','moderator','user','hero','enemy') NOT NULL DEFAULT 'hero',
  MODIFY COLUMN `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active';

UPDATE `users`
SET `handle` = CONCAT('legacy_', `id`)
WHERE `handle` IS NULL OR `handle` = '';

ALTER TABLE `users`
  MODIFY COLUMN `handle` VARCHAR(30) NOT NULL,
  ADD UNIQUE KEY `uq_users_handle` (`handle`);
