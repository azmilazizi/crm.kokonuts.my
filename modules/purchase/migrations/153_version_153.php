<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_153 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'wa_expense_drafts')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "wa_expense_drafts` (
              `id` varchar(100) NOT NULL,
              `vendor_name` varchar(255) NULL,
              `expense_name` varchar(255) NULL,
              `date` date NULL,
              `amount` decimal(15,2) NULL DEFAULT 0,
              `category_id` int(11) NULL,
              `note` text NULL,
              `created_at` datetime NULL,
              `updated_at` datetime NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
        }

        if (!$CI->db->table_exists(db_prefix() . 'wa_expense_draft_attachments')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "wa_expense_draft_attachments` (
              `id` varchar(100) NOT NULL,
              `draft_id` varchar(100) NOT NULL,
              `file_name` varchar(255) NULL,
              `size_bytes` bigint(20) NULL,
              `uploaded_by` varchar(191) NULL,
              `local_blob` longblob NULL,
              PRIMARY KEY (`id`),
              KEY `draft_id` (`draft_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
        }

        if (!$CI->db->table_exists(db_prefix() . 'wa_bill_drafts')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "wa_bill_drafts` (
              `id` varchar(100) NOT NULL,
              `vendor_name` varchar(255) NULL,
              `date` date NULL,
              `due_date` date NULL,
              `amount` decimal(15,2) NULL DEFAULT 0,
              `reference_no` varchar(255) NULL,
              `bill_category_id` int(11) NULL,
              `note` text NULL,
              `created_at` datetime NULL,
              `updated_at` datetime NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
        }

        if (!$CI->db->table_exists(db_prefix() . 'wa_bill_draft_attachments')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "wa_bill_draft_attachments` (
              `id` varchar(100) NOT NULL,
              `draft_id` varchar(100) NOT NULL,
              `file_name` varchar(255) NULL,
              `size_bytes` bigint(20) NULL,
              `uploaded_by` varchar(191) NULL,
              `local_blob` longblob NULL,
              PRIMARY KEY (`id`),
              KEY `draft_id` (`draft_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
        }

        if (!$CI->db->table_exists(db_prefix() . 'wa_pending_sessions')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "wa_pending_sessions` (
              `phone` varchar(30) NOT NULL,
              `image_data` longtext NULL,
              `image_mime` varchar(100) NULL DEFAULT 'image/jpeg',
              `created_at` datetime NULL,
              PRIMARY KEY (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
        }
    }
}
