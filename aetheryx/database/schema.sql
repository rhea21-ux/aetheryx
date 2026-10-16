-- ÆTHERYX database schema (matches the queries used by the backend)
-- Fresh-install only: this script drops/recreates the application tables.
-- Do not import it over a populated database; apply additive migrations instead.
-- Import in phpMyAdmin or:  mysql -u root < database/schema.sql
-- Then create demo accounts:  php database/seed-users.php

CREATE DATABASE IF NOT EXISTS aetheryx_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aetheryx_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS aetheryx_sessions, tab_auth_sessions, activity_logs, notifications, payment_transactions, order_items, orders, inventory, cart_items,
                     equipment_resource_compatibility, compatibility, resources, equipment, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE aetheryx_sessions (
  session_id    VARCHAR(128) COLLATE ascii_bin NOT NULL PRIMARY KEY,
  session_data  MEDIUMBLOB NOT NULL,
  last_activity INT UNSIGNED NOT NULL,
  KEY idx_aetheryx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE tab_auth_sessions (
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  revoked_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tab_auth_user (user_id, revoked_at),
  KEY idx_tab_auth_expiry (expires_at),
  CONSTRAINT fk_tab_auth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
  is_black_market    TINYINT(1) NOT NULL DEFAULT 0,
  quantity           INT NOT NULL DEFAULT 0,
  available_quantity INT NOT NULL DEFAULT 0,
  price             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
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

CREATE TABLE inventory (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  status       ENUM('assigned','returned','sold','lost','damaged') NOT NULL DEFAULT 'assigned',
  purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  sale_price   DECIMAL(10,2) NULL DEFAULT NULL,
  assigned_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  returned_at  TIMESTAMP NULL DEFAULT NULL,
  sold_at      TIMESTAMP NULL DEFAULT NULL,
  notes        TEXT NULL,
  KEY idx_inventory_user (user_id, status),
  CONSTRAINT fk_inv_user      FOREIGN KEY (user_id)      REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_inv_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  order_number VARCHAR(32) NOT NULL UNIQUE,
  checkout_token CHAR(36) NOT NULL,
  order_status ENUM('pending','confirmed','processing','ready_for_delivery','shipped','out_for_delivery','delivered','completed','cancelled','failed','refunded') NOT NULL DEFAULT 'pending',
  payment_method ENUM('cash_on_delivery','demo_payment') NOT NULL,
  payment_status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  cancellation_reason VARCHAR(500) NULL,
  cancelled_at TIMESTAMP NULL DEFAULT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_user_checkout_token (user_id, checkout_token),
  KEY idx_orders_user_created (user_id, created_at),
  KEY idx_orders_status_payment (order_status, payment_status),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NULL,
  equipment_name VARCHAR(150) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payment_transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  transaction_reference VARCHAR(48) NOT NULL UNIQUE,
  payment_method ENUM('cash_on_delivery','demo_payment') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL,
  is_simulated TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment_transactions_order (order_id),
  CONSTRAINT fk_payment_transactions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_delivery_details (
  order_id INT UNSIGNED NOT NULL PRIMARY KEY,
  recipient_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL,
  region VARCHAR(100) NOT NULL,
  postal_code VARCHAR(20) NOT NULL,
  delivery_notes VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_delivery_details_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
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

CREATE TABLE equipment_resource_compatibility (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  assigned_user_id   INT UNSIGNED NULL,
  equipment_id       INT UNSIGNED NOT NULL,
  resource_id        INT UNSIGNED NULL,
  compatible_equipment_id INT UNSIGNED NULL,
  compatibility_type ENUM('compatible','incompatible','situational') NOT NULL DEFAULT 'compatible',
  notes              TEXT NULL,
  situations         TEXT NULL,
  created_at         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_equipment_resource_pair (equipment_id, resource_id),
  UNIQUE KEY uq_user_equipment_compatibility_pair (assigned_user_id, equipment_id, compatible_equipment_id),
  KEY idx_erc_resource (resource_id),
  CONSTRAINT fk_erc_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_erc_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
  CONSTRAINT fk_erc_resource FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
  CONSTRAINT fk_erc_compatible_equipment FOREIGN KEY (compatible_equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
  CONSTRAINT chk_equipment_pair_order CHECK (compatible_equipment_id IS NULL OR equipment_id < compatible_equipment_id)
) ENGINE=InnoDB;

CREATE TABLE equipment_compatibility_roles (
  compatibility_id INT UNSIGNED NOT NULL,
  compatible_role ENUM('mage','fighter','marksman','tank','support') NOT NULL,
  PRIMARY KEY (compatibility_id, compatible_role),
  KEY idx_compatibility_roles_role (compatible_role),
  CONSTRAINT fk_compatibility_roles_record
    FOREIGN KEY (compatibility_id) REFERENCES equipment_resource_compatibility(id) ON DELETE CASCADE
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
(4,'Jet Boots','Short-range boost boots.','Vertex','common',3,3,2500.00,'available');

INSERT INTO equipment
  (category_id, access_role, is_black_market, name, description, brand, quantity, available_quantity, price, status)
SELECT c.id, seed.access_role, seed.is_black_market, seed.name, seed.description, seed.brand,
       seed.quantity, seed.available_quantity, seed.price, 'available'
FROM (
  SELECT 'Protection' AS category, 'enemy' AS access_role, 1 AS is_black_market,
         'Obsidian Phase Cloak' AS name,
         'A stealth mantle tuned to dampen tracking signals during covert operations.' AS description,
         'Umbra' AS brand, 4 AS quantity, 4 AS available_quantity, 2450.00 AS price
  UNION ALL
  SELECT 'Tools', 'enemy', 1, 'Riftlock Breacher',
         'A compact breaching tool engineered for rapid access to secured field equipment.',
         'Blackforge', 5, 5, 1799.00
  UNION ALL
  SELECT 'Communication', 'enemy', 0, 'Nightfall Signal Scrambler',
         'A portable encrypted relay for private Villain team communications.',
         'Nocturne', 6, 6, 1299.00
) AS seed
INNER JOIN categories c ON c.name = seed.category
WHERE NOT EXISTS (
  SELECT 1 FROM equipment existing WHERE existing.name = seed.name
);

INSERT INTO compatibility (equipment_id, compatible_equipment_id, status, notes) VALUES
(1,2,'compatible','Vest supports the shield module.'),
(5,3,'compatible','Radio fits the tool kit pouch.'),
(2,6,'incompatible','Shield and launcher interfere.');

INSERT INTO resources (name, description, category) VALUES
('Field Safety Guide','Basic safety rules for all equipment.','Guide'),
('Return Procedure','How to return assigned equipment.','Guide');
