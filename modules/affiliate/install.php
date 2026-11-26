<?php
defined('BASEPATH') or exit('No direct script access allowed');

function affiliate_create_tables()
{
    $CI = &get_instance();
    $prefix = db_prefix();
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}affiliate_categories` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`), UNIQUE KEY `affiliate_category_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}affiliate_products` (
        `item_no` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `image_url` TEXT NULL,
        `image_path` VARCHAR(255) NULL,
        `price` DECIMAL(12,2) NULL,
        `discount_percent` DECIMAL(5,2) NULL,
        `sold_count` INT UNSIGNED NULL,
        `video_url` TEXT NULL,
        `metadata_fetched_at` DATETIME NULL,
        `category_id` INT UNSIGNED NULL,
        `notes` TEXT NULL,
        `shopee_url` TEXT NULL,
        `tiktok_url` TEXT NULL,
        `tiktok_expires_at` DATE NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`item_no`), KEY `affiliate_category` (`category_id`), KEY `affiliate_active` (`is_active`), KEY `affiliate_tiktok_expiry` (`tiktok_expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}affiliate_clicks` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `item_no` INT UNSIGNED NOT NULL,
        `platform` VARCHAR(16) NOT NULL,
        `clicked_at` DATETIME NOT NULL,
        `referrer` VARCHAR(500) NULL,
        `campaign` VARCHAR(191) NULL,
        `user_agent` VARCHAR(500) NULL,
        PRIMARY KEY (`id`), KEY `affiliate_click_item_date` (`item_no`, `clicked_at`), KEY `affiliate_click_platform_date` (`platform`, `clicked_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!is_dir(FCPATH . 'uploads/affiliate')) @mkdir(FCPATH . 'uploads/affiliate', 0755, true);
}
