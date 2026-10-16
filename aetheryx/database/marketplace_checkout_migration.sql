-- Additive marketplace checkout and payment tables.
-- Safe to run on an existing installation; existing request and inventory data is preserved.
-- Select the database used by backend/config/database.php before importing this file.

-- Match the existing parent-key signedness. Earlier ÆTHERYX installations use
-- signed INT identifiers, while fresh schemas use INT UNSIGNED.
SELECT COLUMN_TYPE INTO @aetheryx_user_id_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id';
SELECT COLUMN_TYPE INTO @aetheryx_equipment_id_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment' AND COLUMN_NAME = 'id';

SET @aetheryx_create_orders = CONCAT(
  'CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id ', @aetheryx_user_id_type, ' NOT NULL,
    order_number VARCHAR(32) NOT NULL UNIQUE,
    checkout_token CHAR(36) NOT NULL,
    order_status ENUM(''pending'',''processing'',''completed'',''cancelled'',''failed'',''refunded'') NOT NULL DEFAULT ''pending'',
    payment_method ENUM(''cash_on_delivery'',''demo_payment'') NOT NULL,
    payment_status ENUM(''pending'',''paid'',''failed'',''refunded'',''cancelled'') NOT NULL DEFAULT ''pending'',
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_user_checkout_token (user_id, checkout_token),
    KEY idx_orders_user_created (user_id, created_at),
    KEY idx_orders_status_payment (order_status, payment_status),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE aetheryx_stmt FROM @aetheryx_create_orders;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SET @aetheryx_create_order_items = CONCAT(
  'CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    equipment_id ', @aetheryx_equipment_id_type, ' NULL,
    equipment_name VARCHAR(150) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE aetheryx_stmt FROM @aetheryx_create_order_items;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

CREATE TABLE IF NOT EXISTS payment_transactions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_delivery_details (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
