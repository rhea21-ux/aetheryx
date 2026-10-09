-- ÆTHERYX database schema (matches the queries used by the backend)
-- Import in phpMyAdmin or:  mysql -u root < database/schema.sql
-- Then create demo accounts:  php database/seed-users.php

CREATE DATABASE IF NOT EXISTS aetheryx_db_new CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aetheryx_db_new;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs, notifications, inventory, equipment_requests, cart_items,
                     compatibility, resources, equipment, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  handle        VARCHAR(30)  NOT NULL UNIQUE,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','hero','enemy') NOT NULL DEFAULT 'hero',
  status        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE equipment (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id        INT UNSIGNED NOT NULL,
  name               VARCHAR(150) NOT NULL,
  description        TEXT NULL,
  brand              VARCHAR(100) NULL,
  image_url          VARCHAR(255) NULL,
  access_role        ENUM('common','hero','enemy') NOT NULL DEFAULT 'common',
  quantity           INT NOT NULL DEFAULT 0,
  available_quantity INT NOT NULL DEFAULT 0,
  status             ENUM('available','unavailable','maintenance') NOT NULL DEFAULT 'available',
  created_at         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_equipment_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_user_equipment (user_id, equipment_id),
  CONSTRAINT fk_cart_user      FOREIGN KEY (user_id)      REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_cart_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE equipment_requests (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  status       ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  notes        TEXT NULL,
  requested_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at  TIMESTAMP NULL DEFAULT NULL,
  reviewed_by  INT UNSIGNED NULL,
  KEY idx_requests_user (user_id, status),
  CONSTRAINT fk_req_user      FOREIGN KEY (user_id)      REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_req_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
  CONSTRAINT fk_req_reviewer  FOREIGN KEY (reviewed_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE inventory (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  status       ENUM('assigned','returned','lost','damaged') NOT NULL DEFAULT 'assigned',
  assigned_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  returned_at  TIMESTAMP NULL DEFAULT NULL,
  notes        TEXT NULL,
  KEY idx_inventory_user (user_id, status),
  CONSTRAINT fk_inv_user      FOREIGN KEY (user_id)      REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_inv_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(150) NOT NULL,
  message    TEXT NOT NULL,
  type       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE compatibility (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  equipment_id            INT UNSIGNED NOT NULL,
  compatible_equipment_id INT UNSIGNED NOT NULL,
  status                  VARCHAR(30) NOT NULL DEFAULT 'compatible',
  notes                   VARCHAR(255) NULL,
  created_at              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_compat_pair (equipment_id, compatible_equipment_id),
  CONSTRAINT fk_compat_a FOREIGN KEY (equipment_id)            REFERENCES equipment(id) ON DELETE CASCADE,
  CONSTRAINT fk_compat_b FOREIGN KEY (compatible_equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE resources (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  description TEXT NULL,
  category    VARCHAR(100) NULL
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  entity_type VARCHAR(50)  NULL,
  entity_id   INT UNSIGNED NULL,
  ip_address  VARCHAR(45)  NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Sample catalogue -------------------------------------------------------
INSERT INTO categories (name) VALUES ('Protection'),('Tools'),('Communication'),('Mobility');

INSERT INTO equipment (category_id, name, description, brand, access_role, quantity, available_quantity, status) VALUES
(1,'Reinforced Field Vest','Lightweight protective vest for daily field work.','Aegis','common',10,10,'available'),
(1,'Hero Shield Module','Portable energy shield for hero operations.','Aegis','hero',5,5,'available'),
(2,'Multi-Tool Kit','Compact tool kit for repairs on the go.','Forge','common',15,15,'available'),
(2,'Villain Toolkit','Specialised tools reserved for villain operations.','Umbra','enemy',4,4,'available'),
(3,'Secure Radio','Encrypted handheld radio.','Relay','common',12,12,'available'),
(4,'Grapple Launcher','Compact grapple launcher for rooftops.','Vertex','hero',6,6,'available'),
(4,'Jet Boots','Short-range boost boots.','Vertex','common',3,3,'maintenance');

INSERT INTO compatibility (equipment_id, compatible_equipment_id, status, notes) VALUES
(1,2,'compatible','Vest supports the shield module.'),
(5,3,'compatible','Radio fits the tool kit pouch.'),
(2,6,'incompatible','Shield and launcher interfere.');

INSERT INTO resources (name, description, category) VALUES
('Field Safety Guide','Basic safety rules for all equipment.','Guide'),
('Return Procedure','How to return assigned equipment.','Guide');
