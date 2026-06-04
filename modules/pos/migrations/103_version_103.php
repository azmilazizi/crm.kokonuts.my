<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_103 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $CI->db->query('
            CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'pos_receipt_settings` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `warehouse_id`   INT(11)      NOT NULL,
                `logo`           VARCHAR(500) NULL,
                `company_name`   VARCHAR(255) NULL,
                `company_reg_id` VARCHAR(100) NULL,
                `address`        TEXT         NULL,
                `phone`          VARCHAR(50)  NULL,
                `header`         TEXT         NULL,
                `footer`         TEXT         NULL,
                `updated_at`     DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `warehouse_id` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_receipt_settings`;');
    }
}
