<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_122 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_manager_fcm_tokens')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_manager_fcm_tokens` (
                `id`         INT(11)      NOT NULL AUTO_INCREMENT,
                `staff_id`   INT(11)      NOT NULL,
                `fcm_token`  VARCHAR(512) NOT NULL,
                `created_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `fcm_token` (`fcm_token`(255)),
                KEY `staff_id` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_manager_fcm_tokens`');
    }
}
