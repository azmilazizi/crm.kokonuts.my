<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_130 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblitems')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblitems');

            if (!in_array('item_type', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `item_type` ENUM('raw_ingredient','packaging','mixed_ingredient','finished_product','combo') NULL DEFAULT 'finished_product'");
            }
            if (!in_array('batch_size', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `batch_size` DECIMAL(15,4) NULL DEFAULT 1.0000");
            }
            if (!in_array('units_per_batch', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `units_per_batch` DECIMAL(15,4) NULL DEFAULT 1.0000");
            }
            if (!in_array('batch_uom', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `batch_uom` VARCHAR(50) NULL");
            }
            if (!in_array('unit_uom', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `unit_uom` VARCHAR(50) NULL");
            }
            if (!in_array('last_cost_update', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `last_cost_update` DATETIME NULL");
            }
            if (!in_array('cached_cost_per_unit', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `cached_cost_per_unit` DECIMAL(15,4) NULL DEFAULT 0.0000 COMMENT 'Denormalized per-unit cost recalculated on demand; used for speed'");
            }
            if (!in_array('cached_cost_valid_until', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `cached_cost_valid_until` DATETIME NULL COMMENT 'Optional TTL; if exceeded trigger recalc'");
            }
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_uoms')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_uoms` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `category` ENUM('weight','volume','count','packaging') DEFAULT 'count',
                `base_unit_id` INT UNSIGNED NULL,
                `conversion_factor` DECIMAL(18,9) NULL DEFAULT 1.0 COMMENT 'How many base units = 1 of this',
                `active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`),
                KEY `base_unit_id` (`base_unit_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_mixed_ingredients')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_mixed_ingredients` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `item_id` INT UNSIGNED NOT NULL COMMENT 'FK to tblitems.id - the mixed ingredient item itself',
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_mixed_ingredient_components')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_mixed_ingredient_components` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `mixed_ingredient_id` INT UNSIGNED NOT NULL,
                `component_type` ENUM('raw_ingredient','packaging','mixed_ingredient') NOT NULL,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_product_bom')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_product_bom` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `product_item_id` INT UNSIGNED NOT NULL,
                `variant_id` INT UNSIGNED NULL COMMENT 'NULL = applies to all variants (base product)',
                `section` ENUM('mixed_ingredient','raw_ingredient','packaging') NOT NULL,
                `component_type` ENUM('raw_ingredient','packaging','mixed_ingredient') NOT NULL,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_product_variant_groups')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_product_variant_groups` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `base_product_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `base_product_id` (`base_product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_product_variants')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_product_variants` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_combo_components')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_combo_components` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_cost_snapshots')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_cost_snapshots` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `snapshot_date` DATE NOT NULL,
                `name` VARCHAR(255) NULL,
                `created_by_staff_id` INT NULL,
                `notes` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `snapshot_date` (`snapshot_date`, `name`),
                KEY `created_by_staff_id` (`created_by_staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_cost_snapshot_values')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_cost_snapshot_values` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `snapshot_id` INT UNSIGNED NOT NULL,
                `item_id` INT UNSIGNED NOT NULL,
                `variant_id` INT UNSIGNED NULL,
                `cost_type` ENUM('raw_ingredient','packaging','mixed_ingredient','finished_product','combo') NOT NULL,
                `cost_per_unit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                `selling_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                `profit_per_unit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                `margin_pct` DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
                PRIMARY KEY (`id`),
                KEY `snapshot_id` (`snapshot_id`),
                KEY `item_id` (`item_id`),
                KEY `variant_id` (`variant_id`),
                KEY `cost_type` (`cost_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if ($CI->db->table_exists(db_prefix() . 'tblpos_uoms')) {
            $CI->db->query("INSERT IGNORE INTO `" . db_prefix() . "tblpos_uoms` (`name`, `category`, `base_unit_id`, `conversion_factor`, `active`) VALUES
                ('piece', 'count', NULL, 1.0, 1),
                ('gram', 'weight', NULL, 1.0, 1),
                ('kilogram', 'weight', NULL, 1000.0, 1),
                ('milliliter', 'volume', NULL, 1.0, 1),
                ('liter', 'volume', NULL, 1000.0, 1),
                ('carton', 'count', NULL, 1.0, 1),
                ('batch', 'count', NULL, 1.0, 1),
                ('cup_16oz', 'packaging', NULL, 1.0, 1),
                ('cup_22oz', 'packaging', NULL, 1.0, 1),
                ('lid_16oz', 'packaging', NULL, 1.0, 1),
                ('straw', 'packaging', NULL, 1.0, 1),
                ('plastic_bag', 'packaging', NULL, 1.0, 1)");
        }
    }

    public function down()
    {
        $CI = &get_instance();

        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_cost_snapshot_values`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_cost_snapshots`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_combo_components`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_product_variants`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_product_variant_groups`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_product_bom`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_mixed_ingredient_components`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_mixed_ingredients`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'tblpos_uoms`');

        if ($CI->db->table_exists(db_prefix() . 'tblitems')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblitems');

            if (in_array('cached_cost_valid_until', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `cached_cost_valid_until`");
            }
            if (in_array('cached_cost_per_unit', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `cached_cost_per_unit`");
            }
            if (in_array('last_cost_update', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `last_cost_update`");
            }
            if (in_array('unit_uom', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `unit_uom`");
            }
            if (in_array('batch_uom', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `batch_uom`");
            }
            if (in_array('units_per_batch', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `units_per_batch`");
            }
            if (in_array('batch_size', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `batch_size`");
            }
            if (in_array('item_type', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `item_type`");
            }
        }
    }
}
