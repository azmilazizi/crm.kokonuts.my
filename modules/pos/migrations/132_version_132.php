<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_132 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_product_bom')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblpos_product_bom');

            if (!in_array('requires_modifier_type', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom`
                    ADD COLUMN `requires_modifier_type` ENUM('modifier','item_modifier_option') NULL COMMENT 'Which modifier table requires_modifier_id points to' AFTER `requires_component_id`");
            }
            if (!in_array('requires_modifier_id', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom`
                    ADD COLUMN `requires_modifier_id` INT UNSIGNED NULL COMMENT 'FK into tblmodifiers.id or tblitem_modifier_options.id depending on requires_modifier_type; row only counts toward cost when this modifier option is chosen' AFTER `requires_modifier_type`");
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom`
                    ADD KEY `requires_modifier_id` (`requires_modifier_id`)");
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_product_bom')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblpos_product_bom');

            if (in_array('requires_modifier_id', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom` DROP COLUMN `requires_modifier_id`");
            }
            if (in_array('requires_modifier_type', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom` DROP COLUMN `requires_modifier_type`");
            }
        }
    }
}
