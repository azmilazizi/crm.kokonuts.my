<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_139 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'tblpos_modifier_bom')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "tblpos_modifier_bom` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `modifier_id` INT UNSIGNED NOT NULL COMMENT 'FK to tblmodifiers.id',
                `section` ENUM('mixed_ingredient','raw_ingredient','packaging') NOT NULL,
                `component_item_id` INT UNSIGNED NOT NULL,
                `quantity` DECIMAL(15,4) NOT NULL COMMENT 'Metric quantity, reference only — never feeds cost math',
                `serving_quantity` DECIMAL(15,4) NULL COMMENT 'Optional kitchen-facing quantity (e.g. 1, with the components serving_label, e.g. scoop)',
                `note` VARCHAR(255) NULL,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `modifier_id` (`modifier_id`),
                KEY `component_item_id` (`component_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_modifier_bom')) {
            $CI->db->query("DROP TABLE `" . db_prefix() . "tblpos_modifier_bom`");
        }
    }
}
