-- Starter fantasy equipment. Run after marketplace_resale_migration.sql.
-- Inserts only missing names and uses local placeholder artwork-free records.
-- Select aetheryx_db (Laragon) or aetheryx (Aiven) before importing.

INSERT INTO categories (name) VALUES ('Weapons'),('Arcana'),('Protection'),('Accessories')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO equipment (category_id, name, description, brand, access_role, is_black_market,
                       quantity, available_quantity, price, status, item_type, lore, rarity, fictional_effect)
SELECT c.id, s.name, s.description, 'AETHERYX Foundry', s.access_role, 0,
       10, 10, s.price, 'available', s.item_type, s.lore, s.rarity, s.fictional_effect
FROM (
 SELECT 'Weapons' category,'Dawnforged Blade' name,'A balanced blade designed for close-range combat and reliable offensive performance.' description,'hero' access_role,1250.00 price,'fighter' character_role,'Sword' item_type,'Forged at first light by the eastern smiths, its plain balance has outlasted flashier weapons.' lore,'Rare' rarity,'Fictional attack attribute: increased striking power.' fictional_effect
 UNION ALL SELECT 'Arcana','Aetherweave Staff','A focus for channeling controlled magical energy and improving spell-oriented performance.','hero',1890.00,'mage','Magic staff','Its silver threads were woven around a quiet aether spring to steady the wielder’s focus.','Epic','Fictional magic attribute: improved spell focus.'
 UNION ALL SELECT 'Protection','Guardian''s Bastion','Protective equipment intended to improve defensive capability.','hero',1425.00,'tank','Shield','A ward captain carried this broad shield through the seven-night evacuation of Veyr.','Rare','Fictional defense attribute: increased protection.'
 UNION ALL SELECT 'Weapons','Skytrace Longbow','Precision equipment intended for ranged attacks and accuracy-oriented gameplay.','hero',1750.00,'marksman','Longbow','Carved from wind-bent ash, its sight marks were set by scouts mapping the high passes.','Epic','Fictional accuracy attribute: steadier aim.'
 UNION ALL SELECT 'Accessories','Lifebloom Charm','A restorative accessory intended to improve a character''s support capability.','hero',1100.00,'support','Charm','A keepsake from the first garden tended after the Emberfall, carried by field healers.','Rare','Fictional support attribute: stronger restorative aid.'
 UNION ALL SELECT 'Weapons','Voidreaver Scythe','A formidable close-combat weapon designed for aggressive fictional combat.','enemy',1990.00,'fighter','Scythe','A crescent blade from the black foundries, balanced for deliberate close combat.','Epic','Fictional attack attribute: increased striking power.'
 UNION ALL SELECT 'Arcana','Umbral Grimoire','An arcane book designed for dark-magic-themed character builds.','enemy',1890.00,'mage','Spellbook','Its blank pages reveal old ink only under moonless skies, recording a vanished cabal’s lessons.','Epic','Fictional magic attribute: improved spell focus.'
 UNION ALL SELECT 'Protection','Dreadplate Armor','Heavy protective gear designed to increase defensive capability.','enemy',1425.00,'tank','Heavy armor','Layered plates reclaimed from a fortress gate and fitted with a muted shadow enamel.','Rare','Fictional defense attribute: increased protection.'
 UNION ALL SELECT 'Weapons','Nightpiercer Crossbow','A precision ranged weapon intended for accuracy-oriented gameplay.','enemy',1750.00,'marksman','Crossbow','A compact mechanism favored by night couriers for its stable, repeatable draw.','Epic','Fictional accuracy attribute: steadier aim.'
 UNION ALL SELECT 'Accessories','Hexbound Relic','A fictional relic designed to strengthen support-focused character builds.','enemy',1100.00,'support','Relic','A sealed fragment recovered from the old observatory; its markings are still being studied.','Rare','Fictional support attribute: stronger restorative aid.'
 UNION ALL SELECT 'Accessories','Concordance Sigil','A shared focus that supports both magical and restorative character builds.','common',980.00,'mage','Focus','Its two interlocking marks were designed by allied archivists and field medics.','Uncommon','Fictional focus attribute: steadier spell and support actions.'
) s INNER JOIN categories c ON c.name=s.category
WHERE NOT EXISTS (SELECT 1 FROM equipment e WHERE e.name=s.name);

INSERT IGNORE INTO equipment_character_roles (equipment_id, character_role)
SELECT e.id, s.character_role FROM (
 SELECT 'Dawnforged Blade' name,'fighter' character_role UNION ALL SELECT 'Aetherweave Staff','mage'
 UNION ALL SELECT 'Guardian''s Bastion','tank' UNION ALL SELECT 'Skytrace Longbow','marksman'
 UNION ALL SELECT 'Lifebloom Charm','support' UNION ALL SELECT 'Voidreaver Scythe','fighter'
 UNION ALL SELECT 'Umbral Grimoire','mage' UNION ALL SELECT 'Dreadplate Armor','tank'
 UNION ALL SELECT 'Nightpiercer Crossbow','marksman' UNION ALL SELECT 'Hexbound Relic','support'
 UNION ALL SELECT 'Concordance Sigil','support'
) s INNER JOIN equipment e ON e.name=s.name;
INSERT IGNORE INTO equipment_character_roles (equipment_id, character_role)
SELECT id,'mage' FROM equipment WHERE name='Concordance Sigil';
