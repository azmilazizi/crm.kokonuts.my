<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_blast_log extends App_module_migration
{
    public function up()
    {
        $CI  = &get_instance();
        $pfx = db_prefix();

        $log = $pfx . 'pos_loyalty_blast_log';
        if (!$CI->db->table_exists($log)) {
            $CI->db->query("
                CREATE TABLE `{$log}` (
                  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `promo_id`        INT NULL,
                  `promo_title`     VARCHAR(300) NOT NULL DEFAULT '',
                  `blast_type`      VARCHAR(20)  NOT NULL DEFAULT 'promotion',
                  `trigger_type`    VARCHAR(30)  NOT NULL DEFAULT 'standard',
                  `channels`        VARCHAR(30)  NOT NULL DEFAULT '',
                  `recipient_count` INT          NOT NULL DEFAULT 0,
                  `push_sent`       INT          NOT NULL DEFAULT 0,
                  `sms_sent`        INT          NOT NULL DEFAULT 0,
                  `sms_failed`      INT          NOT NULL DEFAULT 0,
                  `blasted_by`      INT          NULL,
                  `blasted_at`      DATETIME     NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_promo_id`   (`promo_id`),
                  KEY `idx_blasted_at` (`blasted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        $members = $pfx . 'pos_loyalty_blast_log_members';
        if (!$CI->db->table_exists($members)) {
            $CI->db->query("
                CREATE TABLE `{$members}` (
                  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `blast_log_id`   INT UNSIGNED NOT NULL,
                  `customer_id`    INT          NOT NULL,
                  `customer_name`  VARCHAR(200) NOT NULL DEFAULT '',
                  `customer_phone` VARCHAR(50)  NOT NULL DEFAULT '',
                  `push_sent`      TINYINT(1)   NOT NULL DEFAULT 0,
                  `sms_sent`       TINYINT(1)   NOT NULL DEFAULT 0,
                  `sms_failed`     TINYINT(1)   NOT NULL DEFAULT 0,
                  PRIMARY KEY (`id`),
                  KEY `idx_blast_log_id` (`blast_log_id`),
                  KEY `idx_customer_id`  (`customer_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }

    public function down()
    {
        $CI  = &get_instance();
        $pfx = db_prefix();

        foreach (['pos_loyalty_blast_log_members', 'pos_loyalty_blast_log'] as $tbl) {
            if ($CI->db->table_exists($pfx . $tbl)) {
                $CI->db->query("DROP TABLE `{$pfx}{$tbl}`");
            }
        }
    }
}
