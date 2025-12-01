<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_151 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pur_order_drafts')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "pur_order_drafts` (
              `id` varchar(100) NOT NULL,
              `vendor_id` int(11) NULL,
              `vendor_name` varchar(255) NULL,
              `vendor_code` varchar(255) NULL,
              `order_name` varchar(255) NOT NULL,
              `order_number` varchar(100) NULL,
              `order_date` date NULL,
              `is_paid` tinyint(1) NOT NULL DEFAULT 0,
              `discount_type` varchar(20) NULL,
              `discount_value` decimal(15,2) NULL DEFAULT 0,
              `shipping_fee` decimal(15,2) NULL DEFAULT 0,
              `items_subtotal` decimal(15,2) NULL DEFAULT 0,
              `total_discount` decimal(15,2) NULL DEFAULT 0,
              `grand_total` decimal(15,2) NULL DEFAULT 0,
              `pending_deletion_attachments` longtext NULL,
              `created_at` datetime NULL,
              `updated_at` datetime NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ";");
        }

        if (!$CI->db->table_exists(db_prefix() . 'pur_order_draft_items')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "pur_order_draft_items` (
              `id` varchar(100) NOT NULL,
              `draft_id` varchar(100) NOT NULL,
              `line_item_id` varchar(100) NULL,
              `inventory_item_id` varchar(100) NULL,
              `inventory_item_name` varchar(255) NULL,
              `description` text NULL,
              `quantity` decimal(15,4) NULL DEFAULT 0,
              `subtotal` decimal(15,2) NULL DEFAULT 0,
              `discount` decimal(15,2) NULL DEFAULT 0,
              `total` decimal(15,2) NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              KEY `draft_id` (`draft_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ";");
        }

        if (!$CI->db->table_exists(db_prefix() . 'pur_order_draft_payments')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "pur_order_draft_payments` (
              `id` varchar(100) NOT NULL,
              `draft_id` varchar(100) NOT NULL,
              `payment_id` varchar(100) NULL,
              `amount` decimal(15,2) NULL DEFAULT 0,
              `payment_date` date NULL,
              `payment_mode_id` varchar(100) NULL,
              `initial_payment_mode_label` varchar(191) NULL,
              PRIMARY KEY (`id`),
              KEY `draft_id` (`draft_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ";");
        }

        if (!$CI->db->table_exists(db_prefix() . 'pur_order_draft_attachments')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "pur_order_draft_attachments` (
              `id` varchar(100) NOT NULL,
              `draft_id` varchar(100) NOT NULL,
              `file_name` varchar(255) NULL,
              `size_bytes` bigint(20) NULL,
              `uploaded_by` varchar(191) NULL,
              `is_existing` tinyint(1) NOT NULL DEFAULT 0,
              `marked_for_deletion` tinyint(1) NOT NULL DEFAULT 0,
              `local_blob` longblob NULL,
              PRIMARY KEY (`id`),
              KEY `draft_id` (`draft_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ";");
        }
    }
}
