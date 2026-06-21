-- Manager module installation SQL
-- Run once after deploying the manager module.
-- Safe to run multiple times (uses IF NOT EXISTS / IF NOT EXISTS checks).

-- Session table for manager app tokens
CREATE TABLE IF NOT EXISTS `tblpos_manager_sessions` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `staff_id`   INT UNSIGNED    NOT NULL,
    `token`      VARCHAR(64)     NOT NULL,
    `created_at` DATETIME        NOT NULL,
    `expires_at` DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    KEY `idx_staff_id` (`staff_id`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add warehouse_ids to staff table for manager warehouse assignment
-- Stores a JSON array of warehouse_id integers the staff member may access.
-- NULL or empty array = no warehouse access (unless admin = 1).
ALTER TABLE `tblstaff`
    ADD COLUMN IF NOT EXISTS `warehouse_ids` TEXT NULL DEFAULT NULL COMMENT 'JSON array of accessible warehouse IDs for manager role';
