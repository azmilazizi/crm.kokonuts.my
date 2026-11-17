<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_draft_controls extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        $drafts = $p . 'pur_order_drafts';
        $expenses = $p . 'expenses';

        if (!$CI->db->field_exists('status', $drafts)) $CI->db->query("ALTER TABLE `{$drafts}` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER `is_paid`");
        if (!$CI->db->field_exists('cancelled_at', $drafts)) $CI->db->query("ALTER TABLE `{$drafts}` ADD COLUMN `cancelled_at` DATETIME NULL AFTER `updated_at`");
        if (!$CI->db->field_exists('cancellation_note', $drafts)) $CI->db->query("ALTER TABLE `{$drafts}` ADD COLUMN `cancellation_note` TEXT NULL AFTER `cancelled_at`");
        if (!$CI->db->field_exists('purchase_agent_cancelled', $expenses)) $CI->db->query("ALTER TABLE `{$expenses}` ADD COLUMN `purchase_agent_cancelled` TINYINT(1) NOT NULL DEFAULT 0");
        if (!$CI->db->field_exists('purchase_agent_cancelled_at', $expenses)) $CI->db->query("ALTER TABLE `{$expenses}` ADD COLUMN `purchase_agent_cancelled_at` DATETIME NULL");
        if (!$CI->db->field_exists('purchase_agent_cancellation_note', $expenses)) $CI->db->query("ALTER TABLE `{$expenses}` ADD COLUMN `purchase_agent_cancellation_note` TEXT NULL");
    }

    public function down() {}
}
