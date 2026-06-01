<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'pos_stores')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_stores` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `address` VARCHAR(500) NULL,
        `city` VARCHAR(100) NULL,
        `state` VARCHAR(100) NULL,
        `postal_code` VARCHAR(20) NULL,
        `country` VARCHAR(100) NULL,
        `phone_number` VARCHAR(50) NULL,
        `description` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_categories')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_categories` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `color` VARCHAR(20) NULL DEFAULT "#4CAF50",
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_employees')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_employees` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NULL,
        `phone_number` VARCHAR(50) NULL,
        `pin` VARCHAR(10) NULL,
        `store_ids` TEXT NULL,
        `is_owner` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_modifier_sets')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_modifier_sets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `position` INT(11) NOT NULL DEFAULT 0,
        `store_ids` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_modifier_options')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_modifier_options` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `modifier_id` INT(11) NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `position` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `modifier_id` (`modifier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_payment_types')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_payment_types` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `type` VARCHAR(50) NOT NULL DEFAULT "CASH",
        `store_ids` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` DATETIME NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_api_tokens')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_api_tokens` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `token` VARCHAR(64) NOT NULL,
        `name` VARCHAR(255) NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_receipts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_receipts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `receipt_number` VARCHAR(100) NOT NULL,
        `receipt_type` VARCHAR(10) NOT NULL DEFAULT "SALE",
        `refund_for` VARCHAR(100) NULL,
        `store_id` INT(11) NOT NULL,
        `employee_id` INT(11) NULL,
        `customer_id` INT(11) NULL,
        `note` TEXT NULL,
        `dining_option` VARCHAR(50) NULL,
        `source` VARCHAR(100) NULL DEFAULT "POS",
        `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_tax` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `tip` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `surcharge` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_money` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `points_earned` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `points_deducted` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `receipt_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        `cancelled_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `receipt_number` (`receipt_number`),
        KEY `store_id` (`store_id`),
        KEY `employee_id` (`employee_id`),
        KEY `customer_id` (`customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_receipt_line_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_receipt_line_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `receipt_id` INT(11) NOT NULL,
        `item_id` INT(11) NOT NULL,
        `item_name` VARCHAR(500) NOT NULL,
        `variant_id` INT(11) NULL,
        `variant_name` VARCHAR(255) NULL,
        `quantity` DECIMAL(15,3) NOT NULL DEFAULT 1.000,
        `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `gross_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_tax` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_money` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `modifier_ids` TEXT NULL,
        `modifier_names` TEXT NULL,
        `modifiers_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `tax_ids` TEXT NULL,
        `line_note` TEXT NULL,
        PRIMARY KEY (`id`),
        KEY `receipt_id` (`receipt_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_receipt_payments')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_receipt_payments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `receipt_id` INT(11) NOT NULL,
        `payment_type_id` INT(11) NOT NULL,
        `payment_name` VARCHAR(255) NOT NULL,
        `type` VARCHAR(50) NOT NULL DEFAULT "CASH",
        `money_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `cash_back` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `receipt_id` (`receipt_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_refunds')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_refunds` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `receipt_id` INT(11) NOT NULL,
        `refund_receipt_number` VARCHAR(100) NOT NULL,
        `employee_id` INT(11) NULL,
        `payment_type_id` INT(11) NULL,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `note` TEXT NULL,
        `refunded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `receipt_id` (`receipt_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}
