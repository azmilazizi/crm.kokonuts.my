DELIMITER $$
DROP PROCEDURE IF EXISTS add_col_if_not_exists$$
CREATE PROCEDURE add_col_if_not_exists(
    IN p_table_name VARCHAR(100),
    IN p_col_name   VARCHAR(100),
    IN p_alter_stmt TEXT
)
BEGIN
    DECLARE col_exists INT;
    SELECT COUNT(*) INTO col_exists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table_name
      AND COLUMN_NAME  = p_col_name;
    IF col_exists = 0 THEN
        SET @ddl = p_alter_stmt;
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DROP PROCEDURE IF EXISTS create_table_if_not_exists$$
CREATE PROCEDURE create_table_if_not_exists(
    IN p_table_name VARCHAR(100),
    IN p_create_stmt TEXT
)
BEGIN
    DECLARE tbl_exists INT;
    SELECT COUNT(*) INTO tbl_exists
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table_name;
    IF tbl_exists = 0 THEN
        SET @ddl = p_create_stmt;
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL add_col_if_not_exists('tblitems', 'item_type',
    'ALTER TABLE `tblitems` ADD COLUMN `item_type` ENUM(''raw_ingredient'',''packaging'',''mixed_ingredient'',''finished_product'',''combo'') NULL DEFAULT ''finished_product''');
CALL add_col_if_not_exists('tblitems', 'batch_size',
    'ALTER TABLE `tblitems` ADD COLUMN `batch_size` DECIMAL(15,4) NULL DEFAULT 1.0000');
CALL add_col_if_not_exists('tblitems', 'units_per_batch',
    'ALTER TABLE `tblitems` ADD COLUMN `units_per_batch` DECIMAL(15,4) NULL DEFAULT 1.0000');
CALL add_col_if_not_exists('tblitems', 'batch_uom',
    'ALTER TABLE `tblitems` ADD COLUMN `batch_uom` VARCHAR(50) NULL');
CALL add_col_if_not_exists('tblitems', 'unit_uom',
    'ALTER TABLE `tblitems` ADD COLUMN `unit_uom` VARCHAR(50) NULL');
CALL add_col_if_not_exists('tblitems', 'last_cost_update',
    'ALTER TABLE `tblitems` ADD COLUMN `last_cost_update` DATETIME NULL');
CALL add_col_if_not_exists('tblitems', 'cached_cost_per_unit',
    'ALTER TABLE `tblitems` ADD COLUMN `cached_cost_per_unit` DECIMAL(15,4) NULL DEFAULT 0.0000');
CALL add_col_if_not_exists('tblitems', 'cached_cost_valid_until',
    'ALTER TABLE `tblitems` ADD COLUMN `cached_cost_valid_until` DATETIME NULL');

CALL create_table_if_not_exists('tblpos_uoms',
'CREATE TABLE `tblpos_uoms` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `category` ENUM(''weight'',''volume'',''count'',''packaging'') DEFAULT ''count'',
    `base_unit_id` INT UNSIGNED NULL,
    `conversion_factor` DECIMAL(18,9) NULL DEFAULT 1.0,
    `active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`),
    KEY `base_unit_id` (`base_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

CALL create_table_if_not_exists('tblpos_mixed_ingredients',
'CREATE TABLE `tblpos_mixed_ingredients` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` INT UNSIGNED NOT NULL,
    `total_batches_yield` DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    `yield_uom` VARCHAR(50) NULL,
    `prep_minutes` INT NULL,
    `instructions` MEDIUMTEXT NULL,
    `active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `item_id` (`item_id`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

CALL create_table_if_not_exists('tblpos_mixed_ingredient_components',
'CREATE TABLE `tblpos_mixed_ingredient_components` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mixed_ingredient_id` INT UNSIGNED NOT NULL,
    `component_type` ENUM(''raw_ingredient'',''packaging'',''mixed_ingredient'') NOT NULL,
    `component_item_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(15,4) NOT NULL,
    `uom` VARCHAR(50) NULL,
    `sort_order` INT DEFAULT 0,
    `note` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `mixed_ingredient_id` (`mixed_ingredient_id`),
    KEY `component_item_id` (`component_item_id`),
    KEY `component_type` (`component_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

CALL create_table_if_not_exists('tblpos_product_bom',
'CREATE TABLE `tblpos_product_bom` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_item_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL,
    `section` ENUM(''mixed_ingredient'',''raw_ingredient'',''packaging'') NOT NULL,
    `component_type` ENUM(''raw_ingredient'',''packaging'',''mixed_ingredient'') NOT NULL,
    `component_item_id` INT UNSIGNED NOT NULL,
    `quantity_per_serving` DECIMAL(15,4) NOT NULL,
    `uom` VARCHAR(50) NULL,
    `sort_order` INT DEFAULT 0,
    `note` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_item_id` (`product_item_id`),
    KEY `component_item_id` (`component_item_id`),
    KEY `variant_id` (`variant_id`),
    KEY `section` (`section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

CALL create_table_if_not_exists('tblpos_product_variant_groups',
'CREATE TABLE `tblpos_product_variant_groups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `base_product_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `base_product_id` (`base_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

CALL create_table_if_not_exists('tblpos_product_variants',
'CREATE TABLE `tblpos_product_variants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `variant_group_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `sku_suffix` VARCHAR(32) NULL,
    `price_adjustment` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `explicit_price` DECIMAL(15,2) NULL,
    `is_base_variant` TINYINT(1) DEFAULT 0,
    `active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `variant_group_id` (`variant_group_id`),
    KEY `is_base_variant` (`is_base_variant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

CALL create_table_if_not_exists('tblpos_combo_components',
'CREATE TABLE `tblpos_combo_components` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `combo_item_id` INT UNSIGNED NOT NULL,
    `component_product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `price_override_component` DECIMAL(15,2) NULL,
    `sort_order` INT DEFAULT 0,
    `note` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `combo_item_id` (`combo_item_id`),
    KEY `component_product_id` (`component_product_id`),
    KEY `variant_id` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

CALL create_table_if_not_exists('tblpos_cost_snapshots',
'CREATE TABLE `tblpos_cost_snapshots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `snapshot_date` DATE NOT NULL,
    `name` VARCHAR(255) NULL,
    `created_by_staff_id` INT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `snapshot_date` (`snapshot_date`, `name`),
    KEY `created_by_staff_id` (`created_by_staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

CALL create_table_if_not_exists('tblpos_cost_snapshot_values',
'CREATE TABLE `tblpos_cost_snapshot_values` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `snapshot_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL,
    `cost_type` ENUM(''raw_ingredient'',''packaging'',''mixed_ingredient'',''finished_product'',''combo'') NOT NULL,
    `cost_per_unit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `selling_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `profit_per_unit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `margin_pct` DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    PRIMARY KEY (`id`),
    KEY `snapshot_id` (`snapshot_id`),
    KEY `item_id` (`item_id`),
    KEY `variant_id` (`variant_id`),
    KEY `cost_type` (`cost_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

INSERT IGNORE INTO `tblpos_uoms` (`name`, `category`, `base_unit_id`, `conversion_factor`, `active`) VALUES
    ('piece',       'count',     NULL, 1.0,    1),
    ('gram',        'weight',    NULL, 1.0,    1),
    ('kilogram',    'weight',    NULL, 1000.0, 1),
    ('milliliter',  'volume',    NULL, 1.0,    1),
    ('liter',       'volume',    NULL, 1000.0, 1),
    ('carton',      'count',     NULL, 1.0,    1),
    ('batch',       'count',     NULL, 1.0,    1),
    ('cup_16oz',    'packaging', NULL, 1.0,    1),
    ('cup_22oz',    'packaging', NULL, 1.0,    1),
    ('lid_16oz',    'packaging', NULL, 1.0,    1),
    ('straw',       'packaging', NULL, 1.0,    1),
    ('plastic_bag', 'packaging', NULL, 1.0,    1);

UPDATE `tblitems` SET `item_type` = 'finished_product' WHERE `item_type` IS NULL OR `item_type` = '';

DROP PROCEDURE IF EXISTS add_col_if_not_exists;
DROP PROCEDURE IF EXISTS create_table_if_not_exists;
