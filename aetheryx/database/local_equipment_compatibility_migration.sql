-- Add the equipment fields expected by the Marketplace API to the older
-- local aetheryx_db schema. Existing equipment remains common and visible.

USE `aetheryx_db`;

ALTER TABLE `equipment`
  ADD COLUMN `access_role` ENUM('common','hero','enemy') NOT NULL DEFAULT 'common' AFTER `category_id`,
  ADD COLUMN `brand` VARCHAR(100) DEFAULT NULL AFTER `description`,
  ADD COLUMN `image_url` VARCHAR(500) DEFAULT NULL AFTER `brand`;
