<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        $CI->db->query('
            CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'item_modifiers` (
                `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `pos_item_id`      VARCHAR(191)    NOT NULL,
                `name`             VARCHAR(191)    NOT NULL,
                `price_adjustment` DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
                `sort_order`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `active`           TINYINT(1)      NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `pos_item_id` (`pos_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'item_modifiers`;');
    }
}
