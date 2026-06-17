-- ============================================================
-- POS Accounting Mapping Migration
-- Run this if you are not using the module upgrade panel.
-- Replace `tbl_` with your actual db_prefix if different.
-- ============================================================

-- Migration 118: base tables
-- ============================================================

CREATE TABLE IF NOT EXISTS `tbl_pos_accounting_settings` (
  `id`         INT(11)    NOT NULL AUTO_INCREMENT,
  `enabled`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME   NULL,
  `updated_at` DATETIME   NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbl_pos_shift_accounting_entries` (
  `id`               INT(11)  NOT NULL AUTO_INCREMENT,
  `shift_id`         INT(11)  NOT NULL,
  `journal_entry_id` INT(11)  NULL DEFAULT NULL,
  `synced_at`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shift_id` (`shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration 119: per-payment-method account mapping
-- Drop old fixed account columns if they were already added
-- ============================================================

ALTER TABLE `tbl_pos_accounting_settings`
  DROP COLUMN IF EXISTS `sales_account_id`,
  DROP COLUMN IF EXISTS `cash_account_id`,
  DROP COLUMN IF EXISTS `digital_account_id`,
  DROP COLUMN IF EXISTS `tax_account_id`;

CREATE TABLE IF NOT EXISTS `tbl_pos_payment_method_accounts` (
  `id`                INT(11)  NOT NULL AUTO_INCREMENT,
  `payment_type_id`   INT(11)  NOT NULL,
  `debit_account_id`  INT(11)  NULL DEFAULT NULL,
  `credit_account_id` INT(11)  NULL DEFAULT NULL,
  `created_at`        DATETIME NULL,
  `updated_at`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_type_id` (`payment_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Verify result
-- ============================================================

SELECT 'pos_accounting_settings'      AS `table`, COUNT(*) AS rows FROM `tbl_pos_accounting_settings`
UNION ALL
SELECT 'pos_shift_accounting_entries' AS `table`, COUNT(*) AS rows FROM `tbl_pos_shift_accounting_entries`
UNION ALL
SELECT 'pos_payment_method_accounts'  AS `table`, COUNT(*) AS rows FROM `tbl_pos_payment_method_accounts`;
