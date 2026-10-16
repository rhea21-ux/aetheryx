-- One-time migration for purchase prices and user sell-back records.
-- Run this against the production `aetheryx` database before deploying APIs.
USE `aetheryx`;

ALTER TABLE `equipment`
  ADD COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE `inventory`
  MODIFY COLUMN `status` ENUM('assigned','returned','sold','lost','damaged') NOT NULL DEFAULT 'assigned',
  ADD COLUMN `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `sale_price` DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN `sold_at` TIMESTAMP NULL DEFAULT NULL;
