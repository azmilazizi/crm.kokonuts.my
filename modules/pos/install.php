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

if (!$CI->db->table_exists(db_prefix() . 'pos_shifts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_shifts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `store_id` INT(11) NOT NULL,
        `employee_id` INT(11) NOT NULL,
        `shift_code` VARCHAR(50) NOT NULL,
        `opening_float` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `closing_float` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `expected_cash` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `actual_cash` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `difference` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_sales` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_refunds` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_discounts` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_tax` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `transaction_count` INT(11) NOT NULL DEFAULT 0,
        `status` ENUM("open","closed") NOT NULL DEFAULT "open",
        `opened_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `closed_at` DATETIME NULL,
        `notes` TEXT NULL,
        PRIMARY KEY (`id`),
        KEY `store_id` (`store_id`),
        KEY `employee_id` (`employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_shift_cash_movements')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_shift_cash_movements` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `shift_id` INT(11) NOT NULL,
        `type` ENUM("pay_in","pay_out") NOT NULL,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `reason` VARCHAR(255) NULL,
        `employee_id` INT(11) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `shift_id` (`shift_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_bundles')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_bundles` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `image` VARCHAR(500) NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `store_ids` TEXT NULL,
        `deleted_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_bundle_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_bundle_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `bundle_id` INT(11) NOT NULL,
        `item_id` INT(11) NOT NULL,
        `quantity` DECIMAL(15,3) NOT NULL DEFAULT 1.000,
        `modifier_ids` TEXT NULL,
        PRIMARY KEY (`id`),
        KEY `bundle_id` (`bundle_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_promotions')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_promotions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `type` ENUM("percentage","fixed","bogo","bundle") NOT NULL DEFAULT "percentage",
        `value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `min_order_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `item_ids` TEXT NULL,
        `category_ids` TEXT NULL,
        `store_ids` TEXT NULL,
        `start_at` DATETIME NULL,
        `end_at` DATETIME NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_customers')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_loyalty_customers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `client_id` INT(11) NULL,
        `phone` VARCHAR(30) NULL,
        `email` VARCHAR(100) NULL,
        `name` VARCHAR(255) NULL,
        `total_points` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_spent` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `qr_token` VARCHAR(64) NULL,
        `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_visit` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `client_id` (`client_id`),
        UNIQUE KEY `qr_token` (`qr_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_transactions')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_loyalty_transactions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `customer_id` INT(11) NOT NULL,
        `receipt_id` INT(11) NULL,
        `type` ENUM("earn","redeem") NOT NULL,
        `points` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `description` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `customer_id` (`customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_sessions')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_sessions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `token` VARCHAR(64) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expires_at` DATETIME NULL,
        `last_used_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token` (`token`),
        KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

// Add selection_type to item_modifiers if not present
if ($CI->db->table_exists(db_prefix() . 'item_modifiers')) {
    $im_cols      = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'item_modifiers`')->result_array();
    $im_col_names = array_column($im_cols, 'Field');
    if (!in_array('selection_type', $im_col_names)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'item_modifiers`
            ADD COLUMN `selection_type` ENUM("single","multiple") NOT NULL DEFAULT "single" AFTER `name`
        ');
    }
}

// Create item_modifier_options if missing
if (!$CI->db->table_exists(db_prefix() . 'item_modifier_options')) {
    $CI->db->query('
        CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'item_modifier_options` (
            `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            `item_modifier_id` INT UNSIGNED    NOT NULL,
            `name`             VARCHAR(191)    NOT NULL,
            `price_adjustment` DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
            `sort_order`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `item_modifier_id` (`item_modifier_id`),
            CONSTRAINT `fk_item_modifier_options_modifier`
                FOREIGN KEY (`item_modifier_id`)
                REFERENCES `' . db_prefix() . 'item_modifiers` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ');
}

// Add/rename columns in pos_api_tokens
$token_cols     = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_api_tokens`')->result_array();
$token_col_names = array_column($token_cols, 'Field');

if (!in_array('staff_id', $token_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_api_tokens` ADD COLUMN `staff_id` INT(11) NULL AFTER `name`');
}
// Rename store_id -> warehouse_id (warehouse module is the source of truth for locations)
if (in_array('store_id', $token_col_names) && !in_array('warehouse_id', $token_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_api_tokens` CHANGE `store_id` `warehouse_id` INT(11) NULL');
} elseif (!in_array('warehouse_id', $token_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_api_tokens` ADD COLUMN `warehouse_id` INT(11) NULL AFTER `staff_id`');
}

// Rename store_id -> warehouse_id in pos_receipts and pos_shifts
foreach (['pos_receipts', 'pos_shifts'] as $tbl) {
    $cols      = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . $tbl . '`')->result_array();
    $col_names = array_column($cols, 'Field');
    if (in_array('store_id', $col_names) && !in_array('warehouse_id', $col_names)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . $tbl . '` CHANGE `store_id` `warehouse_id` INT(11) NOT NULL DEFAULT 0');
    }
}

// Rename store_ids -> warehouse_ids (JSON columns) in relevant tables
foreach (['pos_employees', 'pos_payment_types', 'pos_bundles', 'pos_promotions'] as $tbl) {
    if (!$CI->db->table_exists(db_prefix() . $tbl)) { continue; }
    $cols      = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . $tbl . '`')->result_array();
    $col_names = array_column($cols, 'Field');
    if (in_array('store_ids', $col_names) && !in_array('warehouse_ids', $col_names)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . $tbl . '` CHANGE `store_ids` `warehouse_ids` TEXT NULL');
    }
}

// Add columns to pos_receipts if they don't exist
$receipt_cols     = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_receipts`')->result_array();
$receipt_col_names = array_column($receipt_cols, 'Field');

if (!in_array('shift_id', $receipt_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` ADD COLUMN `shift_id` INT(11) NULL AFTER `employee_id`');
}
if (!in_array('loyalty_customer_id', $receipt_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` ADD COLUMN `loyalty_customer_id` INT(11) NULL AFTER `customer_id`');
}
if (!in_array('cashback_qr_token', $receipt_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` ADD COLUMN `cashback_qr_token` VARCHAR(64) NULL AFTER `loyalty_customer_id`');
}
if (!in_array('uploaded_at', $receipt_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` ADD COLUMN `uploaded_at` DATETIME NULL AFTER `receipt_date`');
}

// Add columns to pos_shifts if they don't exist
$shift_cols      = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_shifts`')->result_array();
$shift_col_names = array_column($shift_cols, 'Field');

if (!in_array('closed_by_employee_id', $shift_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_shifts` ADD COLUMN `closed_by_employee_id` INT(11) NULL AFTER `employee_id`');
}
if (!in_array('cash_rounded', $shift_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_shifts` ADD COLUMN `cash_rounded` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `total_tax`');
}
if (!in_array('cancelled_count', $shift_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_shifts` ADD COLUMN `cancelled_count` INT(11) NOT NULL DEFAULT 0 AFTER `transaction_count`');
}
if (!in_array('cancelled_amount', $shift_col_names)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_shifts` ADD COLUMN `cancelled_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `cancelled_count`');
}

// Make employee_id nullable
$shift_employee_col = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_shifts` WHERE Field = "employee_id"')->row_array();
if ($shift_employee_col && strpos($shift_employee_col['Null'], 'NO') !== false) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_shifts` MODIFY COLUMN `employee_id` INT(11) NULL');
}
