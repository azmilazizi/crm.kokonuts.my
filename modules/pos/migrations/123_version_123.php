<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_123 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_manager_sale_notif_prefs')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_manager_sale_notif_prefs` (
                `id`           INT(11)     NOT NULL AUTO_INCREMENT,
                `staff_id`     INT(11)     NOT NULL,
                `warehouse_id` INT(11)     NOT NULL,
                `enabled`      TINYINT(1)  NOT NULL DEFAULT 1,
                `updated_at`   DATETIME    NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `staff_warehouse` (`staff_id`, `warehouse_id`),
                KEY `warehouse_id` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_manager_sale_notif_prefs`');
    }
}
