<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Warehouse availability for products — no rows = available everywhere
        if (!$CI->db->table_exists(db_prefix() . 'pos_item_warehouses')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_item_warehouses` (
                `item_id`      INT(11) NOT NULL,
                `warehouse_id` INT(11) NOT NULL,
                PRIMARY KEY (`item_id`, `warehouse_id`),
                KEY `warehouse_id` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        // Warehouse availability for modifier groups — no rows = available everywhere
        if (!$CI->db->table_exists(db_prefix() . 'pos_modifier_group_warehouses')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_modifier_group_warehouses` (
                `modifier_group_id` INT UNSIGNED NOT NULL,
                `warehouse_id`      INT(11)      NOT NULL,
                PRIMARY KEY (`modifier_group_id`, `warehouse_id`),
                KEY `warehouse_id` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_modifier_group_warehouses`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_item_warehouses`');
    }
}
