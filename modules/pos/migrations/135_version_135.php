<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_135 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblitems')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblitems');

            if (!in_array('has_yield_breakdown', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems
                    ADD COLUMN `has_yield_breakdown` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Item is split into one or more derived items (e.g. a whole coconut yielding juice + meat)'");
            }
        }

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_item_yields')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_item_yields` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `source_item_id` INT UNSIGNED NOT NULL COMMENT 'FK to tblitems.id - the item being broken down',
                `output_item_id` INT UNSIGNED NOT NULL COMMENT 'FK to tblitems.id - the derived item; its cost is computed from the source, not its own purchase price',
                `quantity` DECIMAL(15,4) NOT NULL DEFAULT 0 COMMENT 'How much of output_item one unit of source_item yields',
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `output_item_id` (`output_item_id`),
                KEY `source_item_id` (`source_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_item_yields')) {
            $CI->db->query("DROP TABLE `" . db_prefix() . "tblpos_item_yields`");
        }

        if ($CI->db->table_exists(db_prefix() . 'tblitems')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblitems');
            if (in_array('has_yield_breakdown', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `has_yield_breakdown`");
            }
        }
    }
}
