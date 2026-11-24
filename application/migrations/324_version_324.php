<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_324 extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ai_email_mailboxes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(191) NOT NULL,
            `display_name` VARCHAR(191) NOT NULL,
            `provider` VARCHAR(32) NOT NULL DEFAULT 'imap',
            `imap_host` VARCHAR(191) NOT NULL,
            `imap_port` SMALLINT UNSIGNED NOT NULL DEFAULT 993,
            `imap_encryption` VARCHAR(16) NOT NULL DEFAULT 'ssl',
            `imap_username` VARCHAR(191) NOT NULL,
            `imap_password` TEXT NOT NULL,
            `owner_staff_id` INT UNSIGNED NULL,
            `access_level` VARCHAR(16) NOT NULL DEFAULT 'off',
            `enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `scope_rules` TEXT NULL,
            `last_used_at` DATETIME NULL,
            `last_connection_status` VARCHAR(32) NULL,
            `last_connection_message` VARCHAR(255) NULL,
            `datecreated` DATETIME NOT NULL,
            `dateupdated` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `ai_email_mailboxes_access` (`enabled`, `access_level`),
            KEY `ai_email_mailboxes_owner` (`owner_staff_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ai_email_audit_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `mailbox_id` INT UNSIGNED NULL,
            `tool` VARCHAR(64) NOT NULL,
            `message_identifier` VARCHAR(255) NULL,
            `action` VARCHAR(64) NULL,
            `result` VARCHAR(32) NOT NULL,
            `error_code` VARCHAR(64) NULL,
            `datecreated` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ai_email_audit_mailbox_date` (`mailbox_id`, `datecreated`),
            KEY `ai_email_audit_tool_date` (`tool`, `datecreated`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        if (!option_exists('ai_email_audit_retention_days')) {
            add_option('ai_email_audit_retention_days', 180);
        }
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'ai_email_audit_logs`');
        $this->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'ai_email_mailboxes`');
    }
}
