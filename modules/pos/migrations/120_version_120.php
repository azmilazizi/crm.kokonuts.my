<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_120 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->field_exists('integration_status', db_prefix() . 'pos_grabfood_settings')) {
            $CI->db->query(
                'ALTER TABLE `' . db_prefix() . 'pos_grabfood_settings`
                 ADD COLUMN `integration_status` VARCHAR(20) NULL DEFAULT NULL
                 AFTER `active`'
            );
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->field_exists('integration_status', db_prefix() . 'pos_grabfood_settings')) {
            $CI->db->query(
                'ALTER TABLE `' . db_prefix() . 'pos_grabfood_settings`
                 DROP COLUMN `integration_status`'
            );
        }
    }
}
