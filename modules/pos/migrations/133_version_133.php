<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_133 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'items')) {
            $cols = $CI->db->list_fields(db_prefix() . 'items');

            if (!in_array('instructions', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "items`
                    ADD COLUMN `instructions` MEDIUMTEXT NULL COMMENT 'Prep/build instructions shown in Product Cost Profit'");
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'items')) {
            $cols = $CI->db->list_fields(db_prefix() . 'items');

            if (in_array('instructions', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "items` DROP COLUMN `instructions`");
            }
        }
    }
}
