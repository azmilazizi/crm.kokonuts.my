<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_payment_allocations extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_payment_allocations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `capture_id` BIGINT UNSIGNED NOT NULL,
            `destination` VARCHAR(20) NOT NULL,
            `record_type` VARCHAR(40) NULL,
            `record_id` VARCHAR(100) NULL,
            `amount` DECIMAL(15,4) NOT NULL DEFAULT 0,
            `payment_method` VARCHAR(100) NULL,
            `payment_mode_id` INT(11) NULL,
            `payment_date` DATE NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
            `notes` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `capture_id` (`capture_id`),
            KEY `destination` (`destination`),
            KEY `record` (`record_type`, `record_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down() {}
}
