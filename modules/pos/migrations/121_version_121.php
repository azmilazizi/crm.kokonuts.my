<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_121 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_manager_sessions')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_manager_sessions` (
                `id`         INT(11)      NOT NULL AUTO_INCREMENT,
                `staff_id`   INT(11)      NOT NULL,
                `token`      VARCHAR(64)  NOT NULL,
                `created_at` DATETIME     NOT NULL,
                `expires_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token` (`token`),
                KEY `staff_id` (`staff_id`),
                KEY `expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        if ($CI->db->table_exists(db_prefix() . 'pos_manager_sessions')) {
            $CI->db->query('DROP TABLE `' . db_prefix() . 'pos_manager_sessions`');
        }
    }
}
