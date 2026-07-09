<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_154 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->field_exists('is_draft', db_prefix() . 'expenses')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'expenses`
                ADD COLUMN `is_draft` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_bill`');
        }

        if (!$CI->db->table_exists(db_prefix() . 'wa_bill_attachments')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "wa_bill_attachments` (
              `id` varchar(100) NOT NULL,
              `bill_id` int(11) NOT NULL,
              `file_name` varchar(255) NULL,
              `size_bytes` bigint(20) NULL,
              `local_blob` longblob NULL,
              PRIMARY KEY (`id`),
              KEY `bill_id` (`bill_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
        }

        if (!$CI->db->table_exists(db_prefix() . 'wa_expense_attachments')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "wa_expense_attachments` (
              `id` varchar(100) NOT NULL,
              `expense_id` int(11) NOT NULL,
              `file_name` varchar(255) NULL,
              `size_bytes` bigint(20) NULL,
              `local_blob` longblob NULL,
              PRIMARY KEY (`id`),
              KEY `expense_id` (`expense_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
        }
    }
}
