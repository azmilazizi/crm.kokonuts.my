<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Admin_agent_account_policies extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}admin_agent_account_proposals` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `parent_account_id` INT(11) NOT NULL,
            `proposed_name` VARCHAR(255) NOT NULL,
            `account_type_id` INT(11) NOT NULL,
            `account_detail_type_id` INT(11) NOT NULL,
            `source_pattern` VARCHAR(255) NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'proposed',
            `request_json` LONGTEXT NULL,
            `created_account_id` INT(11) NULL,
            `created_by` INT(11) NULL,
            `created_at` DATETIME NOT NULL,
            `decided_at` DATETIME NULL,
            PRIMARY KEY (`id`), KEY `parent_account_id` (`parent_account_id`), KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}admin_agent_category_proposals` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_name` VARCHAR(255) NOT NULL,
            `category_type` VARCHAR(20) NOT NULL,
            `debit_account` INT(11) NULL,
            `credit_account` INT(11) NULL,
            `description` TEXT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'proposed',
            `created_by` INT(11) NULL,
            `created_at` DATETIME NOT NULL,
            `decided_at` DATETIME NULL,
            PRIMARY KEY (`id`), KEY `category_type` (`category_type`), KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down() {}
}
