-- Migration 119: per-payment-method debit/credit account mapping
-- Replaces the old fixed cash/digital/sales/tax account columns.

ALTER TABLE `tbl_pos_accounting_settings`
  DROP COLUMN `sales_account_id`,
  DROP COLUMN `cash_account_id`,
  DROP COLUMN `digital_account_id`,
  DROP COLUMN `tax_account_id`;

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
