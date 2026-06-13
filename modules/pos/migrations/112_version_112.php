<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_112 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Warehouse-specific prices for products.
        // No row for a warehouse = fall back to items.rate (default price).
        if (!$CI->db->table_exists(db_prefix() . 'pos_item_warehouse_prices')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_item_warehouse_prices` (
                `item_id`      INT(11)        NOT NULL,
                `warehouse_id` INT(11)        NOT NULL,
                `price`        DECIMAL(15,2)  NOT NULL,
                PRIMARY KEY (`item_id`, `warehouse_id`),
                KEY `warehouse_id` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_item_warehouse_prices`');
    }
}
