<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_125 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Flag individual modifier options as a CRM promo (links to tblpos_crm_promos)
        if ($CI->db->table_exists(db_prefix() . 'modifiers')) {
            $cols = $CI->db->list_fields(db_prefix() . 'modifiers');
            if (!in_array('crm_promo_id', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifiers`
                    ADD COLUMN `crm_promo_id` INT(11) NULL DEFAULT NULL
                    COMMENT "tblpos_crm_promos.id this modifier option represents as an a-la-carte item"');
            }
        }

        // Extend component_type to support modifier_group as a bundle component
        if ($CI->db->table_exists(db_prefix() . 'pos_crm_promo_components')) {
            $CI->db->query("ALTER TABLE `" . db_prefix() . "pos_crm_promo_components`
                MODIFY `component_type` ENUM('product','modifier','modifier_group')
                NOT NULL DEFAULT 'product'");
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'modifiers')) {
            $cols = $CI->db->list_fields(db_prefix() . 'modifiers');
            if (in_array('crm_promo_id', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifiers` DROP COLUMN `crm_promo_id`');
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'pos_crm_promo_components')) {
            $CI->db->query("ALTER TABLE `" . db_prefix() . "pos_crm_promo_components`
                MODIFY `component_type` ENUM('product','modifier') NOT NULL DEFAULT 'product'");
        }
    }
}
