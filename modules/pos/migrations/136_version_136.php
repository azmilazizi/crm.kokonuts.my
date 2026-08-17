<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_136 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_item_yields')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblpos_item_yields');

            if (!in_array('reference_price', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_item_yields`
                    ADD COLUMN `reference_price` DECIMAL(15,4) NULL DEFAULT 0 COMMENT 'Optional per-unit market/supplier reference price, used to split the source cost across outputs by relative value instead of each output absorbing the full source cost' AFTER `quantity`");
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_item_yields')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblpos_item_yields');
            if (in_array('reference_price', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_item_yields` DROP COLUMN `reference_price`");
            }
        }
    }
}
