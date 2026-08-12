<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_131 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_product_bom')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblpos_product_bom');

            if (!in_array('group_key', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom`
                    ADD COLUMN `group_key` VARCHAR(50) NULL COMMENT 'Ties mutually-exclusive alternative components together, e.g. \"lid\"' AFTER `note`");
            }
            if (!in_array('requires_component_id', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom`
                    ADD COLUMN `requires_component_id` INT UNSIGNED NULL COMMENT 'Row only counts toward cost if this component also appears in the BOM; rows in the same group_key without this set are the default/fallback' AFTER `group_key`");
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom`
                    ADD KEY `requires_component_id` (`requires_component_id`)");
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_product_bom')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblpos_product_bom');

            if (in_array('requires_component_id', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom` DROP COLUMN `requires_component_id`");
            }
            if (in_array('group_key', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom` DROP COLUMN `group_key`");
            }
        }
    }
}
