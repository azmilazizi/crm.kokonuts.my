<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_promo_filters extends App_module_migration
{
    public function up()
    {
        $CI    = &get_instance();
        $pfx   = db_prefix();
        $table = $pfx . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        $cols = array_column($CI->db->query("SHOW COLUMNS FROM `{$table}`")->result_array(), 'Field');

        $add = [];

        // Birthday: only target members inactive for N+ days
        if (!in_array('birthday_inactive_filter', $cols)) {
            $add[] = "ADD COLUMN `birthday_inactive_filter` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = only members with no transaction in birthday_inactive_days days'";
        }
        if (!in_array('birthday_inactive_days', $cols)) {
            $add[] = "ADD COLUMN `birthday_inactive_days` INT NOT NULL DEFAULT 90";
        }

        // Sign-up freebies: only send within first N days of signup (NULL = no limit / anniversary)
        if (!in_array('signup_days_window', $cols)) {
            $add[] = "ADD COLUMN `signup_days_window` INT NULL DEFAULT NULL COMMENT 'NULL = no window / pure anniversary; N = send only within first N days of signup'";
        }

        if (!empty($add)) {
            $CI->db->query("ALTER TABLE `{$table}` " . implode(', ', $add));
        }
    }

    public function down()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        foreach (['birthday_inactive_filter', 'birthday_inactive_days', 'signup_days_window'] as $col) {
            $CI->db->query("ALTER TABLE `{$table}` DROP COLUMN IF EXISTS `{$col}`");
        }
    }
}
