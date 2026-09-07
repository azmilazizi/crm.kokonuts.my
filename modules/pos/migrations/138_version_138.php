<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_138 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Replaces the ratio-based serving_size (quantity ÷ serving_size) with a
        // per-recipe-line serving_quantity typed directly on the BOM row — simpler
        // to reason about and doesn't produce odd numbers like "33⅓ scoop".
        if ($CI->db->table_exists(db_prefix() . 'tblitems')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblitems');
            if (in_array('serving_size', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `serving_size`");
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'tblpos_product_bom')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblpos_product_bom');
            if (!in_array('serving_quantity', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom`
                    ADD COLUMN `serving_quantity` DECIMAL(15,4) NULL COMMENT 'Optional kitchen-facing quantity for this recipe line, e.g. 1 (with the component items serving_label, e.g. scoop). NULL = show the metric quantity_per_serving/uom instead' AFTER `quantity_per_serving`");
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblpos_product_bom')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblpos_product_bom');
            if (in_array('serving_quantity', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblpos_product_bom` DROP COLUMN `serving_quantity`");
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'tblitems')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblitems');
            if (!in_array('serving_size', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems`
                    ADD COLUMN `serving_size` DECIMAL(15,4) NULL COMMENT 'How many unit_uom make up one serving_label, e.g. 50 (ml) = 1 scoop'");
            }
        }
    }
}
