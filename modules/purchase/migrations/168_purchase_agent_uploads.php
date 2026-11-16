<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_uploads extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_uploads` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `token_hash` CHAR(64) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'requested',
            `file_name` VARCHAR(255) NOT NULL,
            `mime_type` VARCHAR(100) NOT NULL,
            `size_bytes` BIGINT UNSIGNED NULL,
            `max_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 31457280,
            `sha256` CHAR(64) NULL,
            `file_blob` LONGBLOB NULL,
            `capture_id` BIGINT UNSIGNED NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL,
            `uploaded_at` DATETIME NULL,
            `finalized_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token_hash` (`token_hash`),
            KEY `status` (`status`),
            KEY `capture_id` (`capture_id`),
            KEY `expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down() {}
}
