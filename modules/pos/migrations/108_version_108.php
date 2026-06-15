<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_108 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_payment_mode_settings')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_payment_mode_settings` (
                `payment_mode_id` INT(11) NOT NULL,
                `pos_enabled`     TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`payment_mode_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_payment_mode_settings`');
    }
}
