<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Proposal storage for item creation, item accounting-mapping fixes and
 * category edits, plus reject/decision tracking on the existing account and
 * category proposal tables.
 */
class Migration_Admin_agent_config_proposals extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}admin_agent_config_proposals` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `proposal_type` VARCHAR(40) NOT NULL,
            `payload_json` LONGTEXT NOT NULL,
            `before_json` LONGTEXT NULL,
            `result_json` LONGTEXT NULL,
            `reason` TEXT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'proposed',
            `decision_note` TEXT NULL,
            `created_by` INT(11) NULL,
            `created_at` DATETIME NOT NULL,
            `decided_by` INT(11) NULL,
            `decided_at` DATETIME NULL,
            PRIMARY KEY (`id`), KEY `proposal_type_status` (`proposal_type`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        foreach (['admin_agent_account_proposals', 'admin_agent_category_proposals'] as $table) {
            if (!$CI->db->field_exists('decision_note', $p . $table)) {
                $CI->db->query("ALTER TABLE `{$p}{$table}` ADD COLUMN `decision_note` TEXT NULL");
            }
            if (!$CI->db->field_exists('decided_by', $p . $table)) {
                $CI->db->query("ALTER TABLE `{$p}{$table}` ADD COLUMN `decided_by` INT(11) NULL");
            }
        }
        if (!$CI->db->field_exists('created_category_id', $p . 'admin_agent_category_proposals')) {
            $CI->db->query("ALTER TABLE `{$p}admin_agent_category_proposals` ADD COLUMN `created_category_id` INT(11) NULL");
        }
    }

    public function down() {}
}
