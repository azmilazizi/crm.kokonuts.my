<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_engage_schema extends App_module_migration
{
    public function up()
    {
        $CI    = &get_instance();
        $pfx   = db_prefix();
        $table = $pfx . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        $cols = array_column($CI->db->query("SHOW COLUMNS FROM `{$table}`")->result_array(), 'Field');

        if (!in_array('signup_recurrence', $cols)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `signup_recurrence` VARCHAR(20) NOT NULL DEFAULT 'annual'");
        }
        if (!in_array('stale_days', $cols)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `stale_days` INT NOT NULL DEFAULT 90");
        }
        if (!in_array('birthday_start_date', $cols)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `birthday_start_date` DATE NULL DEFAULT NULL");
        }

        // Rename anniversary → signup_freebies
        $CI->db->query("UPDATE `{$table}` SET `trigger_type` = 'signup_freebies' WHERE `trigger_type` = 'anniversary'");

        // Claims table for per-member claim tracking (birthday, signup freebies)
        $claims = $pfx . 'pos_loyalty_promo_claims';
        if (!$CI->db->table_exists($claims)) {
            $CI->db->query("
                CREATE TABLE `{$claims}` (
                  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `promo_id`    INT NOT NULL,
                  `customer_id` INT NOT NULL,
                  `claim_year`  SMALLINT NOT NULL DEFAULT 0,
                  `claimed_at`  DATETIME NOT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_promo_member_year` (`promo_id`, `customer_id`, `claim_year`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }

    public function down()
    {
        $CI  = &get_instance();
        $pfx = db_prefix();

        $table = $pfx . 'pos_loyalty_promotions';
        if ($CI->db->table_exists($table)) {
            $CI->db->query("UPDATE `{$table}` SET `trigger_type` = 'anniversary' WHERE `trigger_type` = 'signup_freebies'");
            $cols = array_column($CI->db->query("SHOW COLUMNS FROM `{$table}`")->result_array(), 'Field');
            foreach (['signup_recurrence', 'stale_days', 'birthday_start_date'] as $col) {
                if (in_array($col, $cols)) {
                    $CI->db->query("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
                }
            }
        }

        $claims = $pfx . 'pos_loyalty_promo_claims';
        if ($CI->db->table_exists($claims)) {
            $CI->db->query("DROP TABLE `{$claims}`");
        }
    }
}
