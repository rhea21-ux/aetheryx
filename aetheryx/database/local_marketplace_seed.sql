-- Sample catalogue for the local aetheryx_db database.
-- Safe to re-run: categories use their unique names and equipment names are
-- checked before insertion. Existing catalogue data is left untouched.

USE `aetheryx_db`;

INSERT INTO `categories` (`name`, `description`)
VALUES
  ('Protection', 'Protective equipment for field operations.'),
  ('Tools', 'Tools and equipment for field repairs.'),
  ('Communication', 'Secure communications equipment.'),
  ('Mobility', 'Equipment for movement and traversal.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `equipment`
  (`category_id`, `name`, `description`, `brand`, `access_role`, `quantity`, `available_quantity`, `price`, `status`, `is_black_market`)
SELECT c.`id`, seed.`name`, seed.`description`, seed.`brand`, seed.`access_role`, seed.`quantity`, seed.`available_quantity`, seed.`price`, seed.`status`, seed.`is_black_market`
FROM (
  SELECT 'Protection' AS `category`, 'Reinforced Field Vest' AS `name`, 'Lightweight protective vest for daily field work.' AS `description`, 'Aegis' AS `brand`, 'common' AS `access_role`, 10 AS `quantity`, 10 AS `available_quantity`, 0.00 AS `price`, 'available' AS `status`, 0 AS `is_black_market`
  UNION ALL SELECT 'Protection', 'Hero Shield Module', 'Portable energy shield for hero operations.', 'Aegis', 'hero', 5, 5, 0.00, 'available', 0
  UNION ALL SELECT 'Tools', 'Multi-Tool Kit', 'Compact tool kit for repairs on the go.', 'Forge', 'common', 15, 15, 0.00, 'available', 0
  UNION ALL SELECT 'Tools', 'Villain Toolkit', 'Specialised tools reserved for villain operations.', 'Umbra', 'enemy', 4, 4, 400.00, 'available', 0
  UNION ALL SELECT 'Communication', 'Secure Radio', 'Encrypted handheld radio.', 'Relay', 'common', 12, 12, 0.00, 'available', 0
  UNION ALL SELECT 'Mobility', 'Grapple Launcher', 'Compact grapple launcher for rooftops.', 'Vertex', 'hero', 6, 6, 0.00, 'available', 0
  UNION ALL SELECT 'Mobility', 'Jet Boots', 'Short-range boost boots.', 'Vertex', 'common', 3, 3, 2500.00, 'available', 0
  UNION ALL SELECT 'Protection', 'Obsidian Phase Cloak', 'A stealth mantle tuned to dampen tracking signals during covert operations.', 'Umbra', 'enemy', 4, 4, 2450.00, 'available', 1
  UNION ALL SELECT 'Tools', 'Riftlock Breacher', 'A compact breaching tool engineered for rapid access to secured field equipment.', 'Blackforge', 'enemy', 5, 5, 1799.00, 'available', 1
  UNION ALL SELECT 'Communication', 'Nightfall Signal Scrambler', 'A portable encrypted relay for private Villain team communications.', 'Nocturne', 'enemy', 6, 6, 1299.00, 'available', 0
) AS seed
JOIN `categories` c ON c.`name` = seed.`category`
WHERE NOT EXISTS (
  SELECT 1 FROM `equipment` existing WHERE existing.`name` = seed.`name`
);
