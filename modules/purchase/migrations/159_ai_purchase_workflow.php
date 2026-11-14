<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Purchase Agent workflow storage.
 *
 * These tables are deliberately separate from the purchase and warehouse
 * ledgers. An AI or human may prepare and correct a capture here, but the
 * official inventory/accounting records are only touched by a later, explicit
 * posting step.
 */
class Migration_Ai_purchase_workflow extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p  = db_prefix();

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_captures` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `source` VARCHAR(30) NOT NULL DEFAULT 'claude',
            `source_reference` VARCHAR(191) NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'captured',
            `vendor_id` INT(11) NULL,
            `vendor_name` VARCHAR(255) NULL,
            `warehouse_id` INT(11) NULL,
            `invoice_number` VARCHAR(100) NULL,
            `purchase_date` DATE NULL,
            `currency` VARCHAR(3) NOT NULL DEFAULT 'MYR',
            `subtotal` DECIMAL(15,4) NULL,
            `tax` DECIMAL(15,4) NULL,
            `discount` DECIMAL(15,4) NULL,
            `shipping` DECIMAL(15,4) NULL,
            `grand_total` DECIMAL(15,4) NULL,
            `receipt_hash` CHAR(64) NULL,
            `raw_extraction` LONGTEXT NULL,
            `notes` TEXT NULL,
            `created_by` INT(11) NULL,
            `approved_by` INT(11) NULL,
            `approved_at` DATETIME NULL,
            `posted_entity_type` VARCHAR(50) NULL,
            `posted_entity_id` VARCHAR(100) NULL,
            `posted_at` DATETIME NULL,
            `reconciled_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `vendor_id` (`vendor_id`),
            KEY `warehouse_id` (`warehouse_id`),
            KEY `purchase_date` (`purchase_date`),
            KEY `invoice_number` (`invoice_number`),
            KEY `receipt_hash` (`receipt_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_lines` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `capture_id` BIGINT UNSIGNED NOT NULL,
            `source_line_number` INT(11) NULL,
            `source_description` VARCHAR(500) NOT NULL,
            `inventory_item_id` INT(11) NULL,
            `quantity` DECIMAL(15,4) NULL,
            `purchase_unit` VARCHAR(50) NULL,
            `base_quantity` DECIMAL(15,4) NULL,
            `base_unit` VARCHAR(50) NULL,
            `conversion_factor` DECIMAL(15,6) NULL,
            `unit_price` DECIMAL(15,4) NULL,
            `tax` DECIMAL(15,4) NULL,
            `discount` DECIMAL(15,4) NULL,
            `line_total` DECIMAL(15,4) NULL,
            `match_method` VARCHAR(40) NULL,
            `match_confidence` DECIMAL(5,2) NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'needs_review',
            `matched_by` INT(11) NULL,
            `matched_at` DATETIME NULL,
            `review_reason` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `capture_id` (`capture_id`),
            KEY `inventory_item_id` (`inventory_item_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_vendor_aliases` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vendor_id` INT(11) NULL,
            `vendor_description` VARCHAR(500) NOT NULL,
            `normalized_description` VARCHAR(500) NOT NULL,
            `inventory_item_id` INT(11) NOT NULL,
            `purchase_unit` VARCHAR(50) NULL,
            `base_unit` VARCHAR(50) NULL,
            `units_per_pack` DECIMAL(15,6) NULL,
            `confidence` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
            `confirmed_by` INT(11) NULL,
            `confirmed_at` DATETIME NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `vendor_description_item` (`vendor_id`, `normalized_description`(191), `inventory_item_id`),
            KEY `vendor_id` (`vendor_id`),
            KEY `inventory_item_id` (`inventory_item_id`),
            KEY `normalized_description` (`normalized_description`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_corrections` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `capture_id` BIGINT UNSIGNED NOT NULL,
            `line_id` BIGINT UNSIGNED NULL,
            `field_name` VARCHAR(80) NOT NULL,
            `old_value` TEXT NULL,
            `new_value` TEXT NULL,
            `reason` VARCHAR(500) NULL,
            `corrected_by` INT(11) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `capture_id` (`capture_id`),
            KEY `line_id` (`line_id`),
            KEY `field_name` (`field_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_workflow_runs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `capture_id` BIGINT UNSIGNED NOT NULL,
            `run_key` VARCHAR(191) NOT NULL,
            `workflow_name` VARCHAR(80) NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'running',
            `current_step` VARCHAR(80) NULL,
            `attempts` INT(11) NOT NULL DEFAULT 0,
            `input_json` LONGTEXT NULL,
            `output_json` LONGTEXT NULL,
            `error_message` TEXT NULL,
            `started_at` DATETIME NOT NULL,
            `finished_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `run_key` (`run_key`),
            KEY `capture_id` (`capture_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_approvals` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `capture_id` BIGINT UNSIGNED NOT NULL,
            `requested_action` VARCHAR(80) NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
            `requested_by` VARCHAR(50) NOT NULL DEFAULT 'claude',
            `approved_by` INT(11) NULL,
            `decision_note` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `decided_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `capture_id` (`capture_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}purchase_agent_actions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `capture_id` BIGINT UNSIGNED NULL,
            `workflow_run_id` BIGINT UNSIGNED NULL,
            `tool_name` VARCHAR(100) NOT NULL,
            `idempotency_key` VARCHAR(191) NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'requested',
            `actor_type` VARCHAR(30) NOT NULL DEFAULT 'claude',
            `actor_id` VARCHAR(100) NULL,
            `request_json` LONGTEXT NULL,
            `response_json` LONGTEXT NULL,
            `error_message` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `completed_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idempotency_key` (`idempotency_key`),
            KEY `capture_id` (`capture_id`),
            KEY `workflow_run_id` (`workflow_run_id`),
            KEY `tool_name` (`tool_name`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down()
    {
        $CI = &get_instance();
        $p  = db_prefix();

        foreach ([
            'purchase_agent_actions',
            'purchase_agent_approvals',
            'purchase_agent_workflow_runs',
            'purchase_agent_corrections',
            'purchase_agent_vendor_aliases',
            'purchase_agent_lines',
            'purchase_agent_captures',
        ] as $table) {
            $CI->db->query('DROP TABLE IF EXISTS `' . $p . $table . '`');
        }
    }
}
