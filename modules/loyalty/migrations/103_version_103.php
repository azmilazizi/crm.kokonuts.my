<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_103 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // ── pos_loyalty_customers: member account columns ─────────────────────
        if ($CI->db->table_exists(db_prefix() . 'pos_loyalty_customers')) {
            $existing = array_column(
                $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_loyalty_customers`')->result_array(),
                'Field'
            );

            $add = [];
            if (!in_array('password_hash', $existing)) {
                $add[] = 'ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `pdpa_consented_at`';
            }
            if (!in_array('account_status', $existing)) {
                $add[] = "ADD COLUMN `account_status` ENUM('active','inactive','banned') NOT NULL DEFAULT 'active' AFTER `password_hash`";
            }
            if (!empty($add)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_loyalty_customers` ' . implode(', ', $add));
            }
        }

        // ── pos_loyalty_member_sessions ───────────────────────────────────────
        if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_member_sessions')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_loyalty_member_sessions` (
                `id`          INT(11)      NOT NULL AUTO_INCREMENT,
                `customer_id` INT(11)      NOT NULL,
                `token`       VARCHAR(64)  NOT NULL,
                `expires_at`  DATETIME     NOT NULL,
                `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token` (`token`),
                KEY `customer_id` (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }

        // ── pos_loyalty_promotions ────────────────────────────────────────────
        if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_promotions')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_loyalty_promotions` (
                `id`          INT(11)                                NOT NULL AUTO_INCREMENT,
                `title`       VARCHAR(255)                           NOT NULL,
                `description` TEXT                                   NULL,
                `image_url`   VARCHAR(500)                           NULL,
                `type`        ENUM("discount","event","announcement") NOT NULL DEFAULT "announcement",
                `start_date`  DATE                                   NULL,
                `end_date`    DATE                                   NULL,
                `target_tier` VARCHAR(100)                           NULL COMMENT "NULL = all tiers",
                `is_active`   TINYINT(1)                             NOT NULL DEFAULT 1,
                `created_at`  DATETIME                               NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }

        // ── pos_loyalty_notifications ─────────────────────────────────────────
        if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_notifications')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_loyalty_notifications` (
                `id`           INT(11)                          NOT NULL AUTO_INCREMENT,
                `customer_id`  INT(11)                          NULL COMMENT "NULL = broadcast to all members",
                `promotion_id` INT(11)                          NULL,
                `title`        VARCHAR(255)                     NOT NULL,
                `message`      TEXT                             NOT NULL,
                `type`         ENUM("promo","event","info","system") NOT NULL DEFAULT "info",
                `is_read`      TINYINT(1)                       NOT NULL DEFAULT 0,
                `created_at`   DATETIME                         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `customer_id` (`customer_id`),
                KEY `promotion_id` (`promotion_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }
    }

    public function down()
    {
        $CI = &get_instance();

        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_loyalty_notifications`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_loyalty_promotions`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_loyalty_member_sessions`');

        if ($CI->db->table_exists(db_prefix() . 'pos_loyalty_customers')) {
            foreach (['password_hash', 'account_status'] as $col) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_loyalty_customers` DROP COLUMN IF EXISTS `' . $col . '`');
            }
        }
    }
}
