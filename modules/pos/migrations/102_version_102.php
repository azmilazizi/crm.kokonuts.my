<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Add selection_type to item_modifiers if not present
        $cols      = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'item_modifiers`')->result_array();
        $col_names = array_column($cols, 'Field');

        if (!in_array('selection_type', $col_names)) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'item_modifiers`
                ADD COLUMN `selection_type` ENUM("single","multiple") NOT NULL DEFAULT "single" AFTER `name`
            ');
        }

        // Create item_modifier_options table
        $CI->db->query('
            CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'item_modifier_options` (
                `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `item_modifier_id` INT UNSIGNED    NOT NULL,
                `name`             VARCHAR(191)    NOT NULL,
                `price_adjustment` DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
                `sort_order`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `item_modifier_id` (`item_modifier_id`),
                CONSTRAINT `fk_item_modifier_options_modifier`
                    FOREIGN KEY (`item_modifier_id`)
                    REFERENCES `' . db_prefix() . 'item_modifiers` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'item_modifier_options`;');
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'item_modifiers` DROP COLUMN IF EXISTS `selection_type`');
    }
}
