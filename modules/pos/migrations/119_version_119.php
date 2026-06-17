<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_119 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();

        // Drop old fixed account columns — replaced by per-payment-method mapping table
        foreach (['sales_account_id', 'cash_account_id', 'digital_account_id', 'tax_account_id'] as $col) {
            if ($CI->db->field_exists($col, db_prefix() . 'pos_accounting_settings')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_accounting_settings` DROP COLUMN `' . $col . '`');
            }
        }

        // Per-payment-method debit/credit account mapping
        if (!$CI->db->table_exists(db_prefix() . 'pos_payment_method_accounts')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_payment_method_accounts` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `payment_type_id` INT(11) NOT NULL,
              `debit_account_id` INT(11) NULL DEFAULT NULL,
              `credit_account_id` INT(11) NULL DEFAULT NULL,
              `created_at` DATETIME NULL,
              `updated_at` DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_payment_type_id` (`payment_type_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set);
        }
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_payment_method_accounts`');
        // Restore dropped columns
        $cols = [
            'sales_account_id'   => 'INT(11) NULL DEFAULT NULL',
            'cash_account_id'    => 'INT(11) NULL DEFAULT NULL',
            'digital_account_id' => 'INT(11) NULL DEFAULT NULL',
            'tax_account_id'     => 'INT(11) NULL DEFAULT NULL',
        ];
        foreach ($cols as $col => $def) {
            if (!$CI->db->field_exists($col, db_prefix() . 'pos_accounting_settings')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_accounting_settings` ADD COLUMN `' . $col . '` ' . $def);
            }
        }
    }
}
