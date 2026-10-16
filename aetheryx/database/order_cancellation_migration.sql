-- Add buyer cancellation reasons and timestamps to existing orders.
-- Select the same database configured in backend/config/database.php before import.

ALTER TABLE orders
  MODIFY COLUMN order_status ENUM(
    'pending',
    'confirmed',
    'processing',
    'ready_for_delivery',
    'shipped',
    'out_for_delivery',
    'delivered',
    'completed',
    'cancelled',
    'failed',
    'refunded'
  ) NOT NULL DEFAULT 'pending';

SELECT COUNT(*) INTO @has_cancellation_reason
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'orders'
  AND COLUMN_NAME = 'cancellation_reason';
SET @add_cancellation_reason = IF(
  @has_cancellation_reason = 0,
  'ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(500) NULL AFTER payment_status',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @add_cancellation_reason;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;

SELECT COUNT(*) INTO @has_cancelled_at
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'orders'
  AND COLUMN_NAME = 'cancelled_at';
SET @add_cancelled_at = IF(
  @has_cancelled_at = 0,
  'ALTER TABLE orders ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER cancellation_reason',
  'SELECT 1'
);
PREPARE aetheryx_stmt FROM @add_cancelled_at;
EXECUTE aetheryx_stmt;
DEALLOCATE PREPARE aetheryx_stmt;
