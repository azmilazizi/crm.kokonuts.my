<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_branch_reviews extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $CI->db->query('CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'loyalty_branch_reviews` (
            `customer_id` INT(11) NOT NULL,
            `warehouse_id` INT(11) NOT NULL,
            `prompted_at` DATETIME NOT NULL,
            `opened_at` DATETIME NULL,
            PRIMARY KEY (`customer_id`, `warehouse_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'loyalty_branch_reviews`');
    }
}
