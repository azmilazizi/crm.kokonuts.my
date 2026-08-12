<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_134 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pos_product_bom')) {
            $cols = $CI->db->list_fields(db_prefix() . 'pos_product_bom');

            if (!in_array('requires_conditions', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "pos_product_bom`
                    ADD COLUMN `requires_conditions` TEXT NULL COMMENT 'Comma-separated type:id pairs (e.g. modifier:5,item_modifier_option:12) — row only counts toward cost when the customer picks ANY of these modifier options' AFTER `requires_modifier_id`");
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pos_product_bom')) {
            $cols = $CI->db->list_fields(db_prefix() . 'pos_product_bom');

            if (in_array('requires_conditions', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "pos_product_bom` DROP COLUMN `requires_conditions`");
            }
        }
    }
}
