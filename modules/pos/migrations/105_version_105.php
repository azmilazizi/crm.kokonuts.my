<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_105 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_refund_items')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_refund_items` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `refund_id` INT(11) NOT NULL,
                `line_item_id` INT(11) NOT NULL,
                `quantity` DECIMAL(15,3) NOT NULL,
                `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `total_money` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                PRIMARY KEY (`id`),
                KEY `refund_id` (`refund_id`),
                KEY `line_item_id` (`line_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_refund_items`');
    }
}
