<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_promo_notifications extends App_module_migration
{
    public function up()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        $existing = array_column(
            $CI->db->query('SHOW COLUMNS FROM `' . $table . '`')->result_array(),
            'Field'
        );

        $add = [];

        // How this promo is triggered
        if (!in_array('trigger_type', $existing)) {
            $add[] = "ADD COLUMN `trigger_type` VARCHAR(20) NOT NULL DEFAULT 'standard' COMMENT 'standard|birthday|anniversary'";
        }
        // Audience targeting (target_tier already exists)
        if (!in_array('target', $existing)) {
            $add[] = "ADD COLUMN `target` VARCHAR(20) NOT NULL DEFAULT 'all' COMMENT 'all|tier|individual'";
        }
        if (!in_array('target_customer_id', $existing)) {
            $add[] = 'ADD COLUMN `target_customer_id` INT NULL DEFAULT NULL';
        }
        // Notification channels
        if (!in_array('notify_push', $existing)) {
            $add[] = 'ADD COLUMN `notify_push` TINYINT(1) NOT NULL DEFAULT 0';
        }
        if (!in_array('notify_sms', $existing)) {
            $add[] = 'ADD COLUMN `notify_sms` TINYINT(1) NOT NULL DEFAULT 0';
        }
        // How many days before start_date / birthday / anniversary to send. 0 = immediately on save.
        if (!in_array('notify_days_before', $existing)) {
            $add[] = 'ADD COLUMN `notify_days_before` INT NOT NULL DEFAULT 0';
        }
        // For standard promos: pending|sent. For birthday/anniversary: recurring (never moves to sent).
        if (!in_array('notify_status', $existing)) {
            $add[] = "ADD COLUMN `notify_status` VARCHAR(20) NOT NULL DEFAULT 'pending'";
        }
        if (!in_array('notified_at', $existing)) {
            $add[] = 'ADD COLUMN `notified_at` DATETIME NULL DEFAULT NULL';
        }

        if (!empty($add)) {
            $CI->db->query('ALTER TABLE `' . $table . '` ' . implode(', ', $add));
        }
    }

    public function down()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        foreach (['trigger_type', 'target', 'target_customer_id', 'notify_push', 'notify_sms',
                  'notify_days_before', 'notify_status', 'notified_at'] as $col) {
            $CI->db->query('ALTER TABLE `' . $table . '` DROP COLUMN IF EXISTS `' . $col . '`');
        }
    }
}
