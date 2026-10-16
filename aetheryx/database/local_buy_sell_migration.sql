-- One-time migration for purchase prices and user sell-back records.
-- Run this against the local database before using the updated PHP APIs.
USE `aetheryx_db`;

ALTER TABLE `equipment`
  ADD COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE `inventory`
  MODIFY COLUMN `status` ENUM('available','unavailable','maintenance','assigned','returned','sold','lost','damaged') NOT NULL DEFAULT 'assigned',
  ADD COLUMN `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `sale_price` DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN `sold_at` TIMESTAMP NULL DEFAULT NULL;
