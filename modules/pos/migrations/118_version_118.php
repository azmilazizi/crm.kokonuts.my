<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_118 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();

        // Global accounting account mapping for POS shift sales
        if (!$CI->db->table_exists(db_prefix() . 'pos_accounting_settings')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_accounting_settings` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `enabled` TINYINT(1) NOT NULL DEFAULT 0,
              `sales_account_id` INT(11) NULL DEFAULT NULL,
              `cash_account_id` INT(11) NULL DEFAULT NULL,
              `digital_account_id` INT(11) NULL DEFAULT NULL,
              `tax_account_id` INT(11) NULL DEFAULT NULL,
              `created_at` DATETIME NULL,
              `updated_at` DATETIME NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set);
        }

        // Tracks which closed shifts have been converted to journal entries
        if (!$CI->db->table_exists(db_prefix() . 'pos_shift_accounting_entries')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_shift_accounting_entries` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `shift_id` INT(11) NOT NULL,
              `journal_entry_id` INT(11) NULL DEFAULT NULL,
              `synced_at` DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_shift_id` (`shift_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set);
        }
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_accounting_settings`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_shift_accounting_entries`');
    }
}
