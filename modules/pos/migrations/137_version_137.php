<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_137 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblitems')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblitems');

            if (!in_array('serving_label', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems`
                    ADD COLUMN `serving_label` VARCHAR(30) NULL COMMENT 'Kitchen-facing serving unit for recipe display, e.g. \"scoop\" (display only — never used in cost math)'");
            }
            if (!in_array('serving_size', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems`
                    ADD COLUMN `serving_size` DECIMAL(15,4) NULL COMMENT 'How many unit_uom make up one serving_label, e.g. 50 (ml) = 1 scoop'");
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'tblitems')) {
            $cols = $CI->db->list_fields(db_prefix() . 'tblitems');
            if (in_array('serving_size', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `serving_size`");
            }
            if (in_array('serving_label', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "tblitems` DROP COLUMN `serving_label`");
            }
        }
    }
}
