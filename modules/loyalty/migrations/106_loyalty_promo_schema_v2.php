<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_promo_schema_v2 extends App_module_migration
{
    public function up()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        // notify_days_before: INT → VARCHAR(200) to hold comma-separated values e.g. "0,1,7,month_start"
        $CI->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `notify_days_before` VARCHAR(200) NOT NULL DEFAULT '0'");

        // target_customer_id: INT → TEXT to hold comma-separated IDs for multi-member targeting
        $CI->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `target_customer_id` TEXT NULL DEFAULT NULL");
    }

    public function down()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        $CI->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `notify_days_before` INT NOT NULL DEFAULT 0");
        $CI->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `target_customer_id` INT NULL DEFAULT NULL");
    }
}
