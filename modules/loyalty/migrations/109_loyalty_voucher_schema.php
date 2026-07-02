<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_voucher_schema extends App_module_migration
{
    public function up()
    {
        $CI  = &get_instance();
        $pfx = db_prefix();

        // ── pos_loyalty_vouchers ──────────────────────────────────────────────
        if (!$CI->db->table_exists($pfx . 'pos_loyalty_vouchers')) {
            $CI->db->query("
                CREATE TABLE `{$pfx}pos_loyalty_vouchers` (
                    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
                    `title`               VARCHAR(255)     NOT NULL,
                    `code_mode`           ENUM('shared','unique_per_member') NOT NULL DEFAULT 'shared',
                    `base_code`           VARCHAR(50)      NOT NULL,
                    `reward_type`         ENUM('discount_pct','discount_fixed','points_bonus','free_item') NOT NULL DEFAULT 'discount_pct',
                    `reward_value`        DECIMAL(10,2)    NULL DEFAULT NULL,
                    `reward_item`         VARCHAR(255)     NULL DEFAULT NULL,
                    `max_uses`            INT              NULL DEFAULT NULL COMMENT 'NULL = unlimited globally',
                    `used_count`          INT              NOT NULL DEFAULT 0,
                    `max_uses_per_member` INT              NOT NULL DEFAULT 1,
                    `valid_from`          DATE             NULL DEFAULT NULL,
                    `valid_until`         DATE             NULL DEFAULT NULL,
                    `is_active`           TINYINT(1)       NOT NULL DEFAULT 1,
                    `created_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `base_code` (`base_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        // ── pos_loyalty_voucher_instances (unique_per_member codes) ───────────
        if (!$CI->db->table_exists($pfx . 'pos_loyalty_voucher_instances')) {
            $CI->db->query("
                CREATE TABLE `{$pfx}pos_loyalty_voucher_instances` (
                    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `voucher_id`  INT UNSIGNED NOT NULL,
                    `customer_id` INT          NOT NULL,
                    `code`        VARCHAR(60)  NOT NULL,
                    `issued_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_code` (`code`),
                    UNIQUE KEY `uq_voucher_customer` (`voucher_id`, `customer_id`),
                    KEY `customer_id` (`customer_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        // ── pos_loyalty_voucher_redemptions ───────────────────────────────────
        if (!$CI->db->table_exists($pfx . 'pos_loyalty_voucher_redemptions')) {
            $CI->db->query("
                CREATE TABLE `{$pfx}pos_loyalty_voucher_redemptions` (
                    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `voucher_id`  INT UNSIGNED NOT NULL,
                    `customer_id` INT          NOT NULL,
                    `instance_id` INT UNSIGNED NULL DEFAULT NULL,
                    `order_id`    INT          NULL DEFAULT NULL,
                    `redeemed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `voucher_id` (`voucher_id`),
                    KEY `customer_id` (`customer_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        // ── voucher_id FK on pos_loyalty_promotions ───────────────────────────
        $promo_table = $pfx . 'pos_loyalty_promotions';
        if ($CI->db->table_exists($promo_table)) {
            $cols = array_column(
                $CI->db->query("SHOW COLUMNS FROM `{$promo_table}`")->result_array(),
                'Field'
            );
            if (!in_array('voucher_id', $cols)) {
                $CI->db->query("ALTER TABLE `{$promo_table}` ADD COLUMN `voucher_id` INT UNSIGNED NULL DEFAULT NULL");
            }
        }
    }

    public function down()
    {
        $CI  = &get_instance();
        $pfx = db_prefix();

        $promo_table = $pfx . 'pos_loyalty_promotions';
        if ($CI->db->table_exists($promo_table)) {
            $cols = array_column(
                $CI->db->query("SHOW COLUMNS FROM `{$promo_table}`")->result_array(),
                'Field'
            );
            if (in_array('voucher_id', $cols)) {
                $CI->db->query("ALTER TABLE `{$promo_table}` DROP COLUMN `voucher_id`");
            }
        }

        $CI->db->query("DROP TABLE IF EXISTS `{$pfx}pos_loyalty_voucher_redemptions`");
        $CI->db->query("DROP TABLE IF EXISTS `{$pfx}pos_loyalty_voucher_instances`");
        $CI->db->query("DROP TABLE IF EXISTS `{$pfx}pos_loyalty_vouchers`");
    }
}
