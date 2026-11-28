<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * POS -> ledger posting through drafts.
 *
 * acc_ledger_drafts / acc_ledger_draft_lines hold balanced journals that are
 * NOT yet in the ledger. A person approves a draft, and only then is it
 * written to acc_journal_entries / acc_account_history. The tables are
 * generic (source column) so stock-count COGS and reclass journals can use
 * the same approval path.
 *
 * acc_pos_ledger_links records which POS sale/refund went into which draft.
 * Its unique key is what makes re-running the build safe: a receipt can only
 * ever be in one live draft.
 */
class Migration_Version_150 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p  = db_prefix();

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}acc_ledger_drafts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `source` VARCHAR(40) NOT NULL,
            `source_key` VARCHAR(120) NOT NULL,
            `live_key` VARCHAR(160) NULL,
            `journal_date` DATE NOT NULL,
            `warehouse_id` INT(11) NULL,
            `description` TEXT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
            `total_debit` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `total_credit` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `details_json` LONGTEXT NULL,
            `journal_entry_id` INT(11) NULL,
            `created_by` INT(11) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `decided_by` INT(11) NULL,
            `decided_at` DATETIME NULL,
            `decision_note` TEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_live_key` (`live_key`),
            KEY `source_status` (`source`, `status`),
            KEY `journal_date` (`journal_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}acc_ledger_draft_lines` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `draft_id` INT(11) NOT NULL,
            `account_id` INT(11) NOT NULL,
            `debit` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `credit` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `description` VARCHAR(255) NULL,
            `meta_json` TEXT NULL,
            PRIMARY KEY (`id`), KEY `draft_id` (`draft_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}acc_pos_ledger_links` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `link_type` VARCHAR(20) NOT NULL,
            `ref_id` INT(11) NOT NULL,
            `receipt_id` INT(11) NOT NULL,
            `draft_id` INT(11) NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_link` (`link_type`, `ref_id`),
            KEY `draft_id` (`draft_id`), KEY `receipt_id` (`receipt_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}acc_pos_ledger_exclusions` (
            `receipt_id` INT(11) NOT NULL,
            `reason` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`receipt_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Grab developer-console test order: never a real sale.
        if ($CI->db->table_exists($p . 'pos_grabfood_orders')) {
            $CI->db->query("INSERT IGNORE INTO `{$p}acc_pos_ledger_exclusions` (receipt_id, reason, created_at)
                SELECT receipt_id, 'Grab developer-console test order', NOW() FROM `{$p}pos_grabfood_orders`
                WHERE customer_name = 'grab-dev-console-test' AND receipt_id IS NOT NULL");
        }

        add_option('acc_pos_ledger_start_date', '2026-06-01');
        add_option('acc_pos_ledger_auto_build', '1');
        // POS receipt source (channel) -> Sales Income child account.
        add_option('acc_pos_ledger_channel_accounts', json_encode([
            'POS' => 217, 'GRABFOOD' => 214, 'FOODPANDA' => 215, 'SHOPEEFOOD' => 216,
        ]));

        // "DuitNow QR (Manual)" money lands directly in Maybank. Same layout as
        // the Bank Transfer mapping. Only added when the mode has no mapping.
        $mode = $CI->db->where('name', 'DuitNow QR (Manual)')->get($p . 'payment_modes')->row();
        if ($mode && !$CI->db->where('payment_mode_id', $mode->id)->count_all_results($p . 'acc_payment_mode_mappings')) {
            $CI->db->insert($p . 'acc_payment_mode_mappings', [
                'payment_mode_id' => (int) $mode->id,
                'payment_account' => 217, 'deposit_to' => 139,
                'expense_payment_account' => 139, 'expense_deposit_to' => 137,
                'credit_note_refund_payment_account' => 137, 'credit_note_refund_deposit_to' => 139,
            ]);
        }
    }

    public function down()
    {
    }
}
