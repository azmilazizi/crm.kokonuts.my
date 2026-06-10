-- Chip-in DuitNow QR Settings
CREATE TABLE IF NOT EXISTS `tblpos_chip_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_id` varchar(100) NOT NULL COMMENT 'Chip Brand ID',
  `secret_key` text NOT NULL COMMENT 'Chip Secret Key (encrypted)',
  `public_key` text NOT NULL COMMENT 'Chip Public Key',
  `webhook_url` varchar(255) DEFAULT NULL,
  `test_mode` tinyint(1) DEFAULT 1 COMMENT '1=sandbox, 0=production',
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add pos_enabled column to payment_modes if not exists
ALTER TABLE `tblpayment_modes`
ADD COLUMN IF NOT EXISTS `pos_enabled` tinyint(1) DEFAULT 0 COMMENT 'Available in POS';

-- DuitNow QR Payment Transactions
CREATE TABLE IF NOT EXISTS `tblpos_duitnow_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_id` int(11) DEFAULT NULL COMMENT 'FK to tblpos_receipts',
  `purchase_id` varchar(100) NOT NULL COMMENT 'Chip Purchase ID',
  `client_reference` varchar(100) DEFAULT NULL,
  `qr_code_url` text DEFAULT NULL,
  `qr_content` text DEFAULT NULL COMMENT 'QR code data',
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'MYR',
  `status` enum('pending','paid','failed','cancelled','expired') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT 'duitnow',
  `paid_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `webhook_payload` text DEFAULT NULL COMMENT 'Last webhook payload received',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `receipt_id` (`receipt_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
