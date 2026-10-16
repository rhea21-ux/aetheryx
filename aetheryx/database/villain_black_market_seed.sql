-- Add a small Villain-only catalogue, including two priced Black Market
-- listings. Re-running this file does not overwrite live stock or prices.

INSERT INTO `categories` (`name`)
VALUES
  ('Protection'),
  ('Tools'),
  ('Communication')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `equipment`
  (`category_id`, `access_role`, `is_black_market`, `name`, `description`, `brand`,
   `quantity`, `available_quantity`, `price`, `status`)
SELECT c.`id`, seed.`access_role`, seed.`is_black_market`, seed.`name`,
       seed.`description`, seed.`brand`, seed.`quantity`, seed.`available_quantity`,
       seed.`price`, seed.`status`
FROM (
  SELECT 'Protection' AS `category`, 'enemy' AS `access_role`, 1 AS `is_black_market`,
         'Obsidian Phase Cloak' AS `name`,
         'A stealth mantle tuned to dampen tracking signals during covert operations.' AS `description`,
         'Umbra' AS `brand`, 4 AS `quantity`, 4 AS `available_quantity`,
         2450.00 AS `price`, 'available' AS `status`
  UNION ALL
  SELECT 'Tools', 'enemy', 1, 'Riftlock Breacher',
         'A compact breaching tool engineered for rapid access to secured field equipment.',
         'Blackforge', 5, 5, 1799.00, 'available'
  UNION ALL
  SELECT 'Communication', 'enemy', 0, 'Nightfall Signal Scrambler',
         'A portable encrypted relay for private Villain team communications.',
         'Nocturne', 6, 6, 1299.00, 'available'
) AS seed
INNER JOIN `categories` c ON c.`name` = seed.`category`
WHERE NOT EXISTS (
  SELECT 1 FROM `equipment` existing WHERE existing.`name` = seed.`name`
);

UPDATE `equipment`
SET `access_role` = 'enemy', `is_black_market` = 1
WHERE `name` IN ('Obsidian Phase Cloak', 'Riftlock Breacher');

UPDATE `equipment`
SET `access_role` = 'enemy', `is_black_market` = 0
WHERE `name` = 'Nightfall Signal Scrambler';
