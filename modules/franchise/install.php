<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'franchise_franchisees')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "franchise_franchisees` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(191) NOT NULL,
        `contact_person` varchar(191) NULL,
        `phone` varchar(50) NULL,
        `email` varchar(191) NULL,
        `bank_name` varchar(191) NULL,
        `bank_account_name` varchar(191) NULL,
        `bank_account_no` varchar(100) NULL,
        `notes` text NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}

// Outlets live in the POS/warehouse module (`warehouse` table) — tag ownership here.
if (!$CI->db->field_exists('franchisee_id', db_prefix() . 'warehouse')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "warehouse`
        ADD COLUMN `franchisee_id` int(11) NULL AFTER `warehouse_id`,
        ADD INDEX `franchisee_id` (`franchisee_id`);");
}

// Cashback redemptions live in the loyalty module (`pos_loyalty_transactions` table) —
// tag which ones have been settled to the franchisee that fronted the redemption.
if (!$CI->db->field_exists('franchise_transfer_id', db_prefix() . 'pos_loyalty_transactions')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . "pos_loyalty_transactions`
        ADD COLUMN `franchise_transfer_id` int(11) NULL AFTER `warehouse_id`,
        ADD INDEX `franchise_transfer_id` (`franchise_transfer_id`);");

    // Backfill: past redeem transactions were never stamped with warehouse_id
    // (the POS API never passed it through) — recover it from the linked receipt so
    // historical redemptions can still be attributed to the right outlet.
    $CI->db->query('UPDATE `' . db_prefix() . 'pos_loyalty_transactions` t
        INNER JOIN `' . db_prefix() . "pos_receipts` r ON r.id = t.receipt_id
        SET t.warehouse_id = r.warehouse_id
        WHERE t.type = 'redeem' AND t.warehouse_id IS NULL AND r.warehouse_id IS NOT NULL;");
}

if (!$CI->db->table_exists(db_prefix() . 'franchise_transfers')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "franchise_transfers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `franchisee_id` int(11) NOT NULL,
        `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
        `reference_no` varchar(191) NULL,
        `method` varchar(50) NULL,
        `note` text NULL,
        `transferred_at` datetime NOT NULL,
        `staff_id` int(11) NULL,
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        INDEX `franchisee_id` (`franchisee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}
