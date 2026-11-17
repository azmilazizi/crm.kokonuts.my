<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Restore debit/credit rows for categorized bills created before the API
 * consistently passed the simple bill category through to Accounting.
 */
class Migration_Bill_category_mapping_backfill extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();

        $sql = "INSERT INTO `{$p}acc_bill_mappings`
            (`bill_id`, `type`, `account`, `amount`, `item_id`, `qty`, `cost`, `description`)
            SELECT e.id, 'debit', c.debit_account, e.amount, 0, 0, 0, ''
            FROM `{$p}expenses` e
            INNER JOIN `{$p}acc_bill_categories` c ON c.id = e.bill_category_id
            WHERE e.is_bill = 1
              AND e.bill_category_id IS NOT NULL
              AND c.debit_account IS NOT NULL AND c.debit_account <> ''
              AND NOT EXISTS (
                  SELECT 1 FROM `{$p}acc_bill_mappings` m
                  WHERE m.bill_id = e.id AND m.type IN ('debit', 'credit')
              )
            UNION ALL
            SELECT e.id, 'credit', c.credit_account, e.amount, 0, 0, 0, ''
            FROM `{$p}expenses` e
            INNER JOIN `{$p}acc_bill_categories` c ON c.id = e.bill_category_id
            WHERE e.is_bill = 1
              AND e.bill_category_id IS NOT NULL
              AND c.credit_account IS NOT NULL AND c.credit_account <> ''
              AND NOT EXISTS (
                  SELECT 1 FROM `{$p}acc_bill_mappings` m
                  WHERE m.bill_id = e.id AND m.type IN ('debit', 'credit')
              )";

        $CI->db->query($sql);
    }

    public function down() {}
}
