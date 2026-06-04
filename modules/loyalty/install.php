<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// Shared with POS module — created here only if POS was not installed first
if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_customers')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_loyalty_customers` (
        `id`           INT(11)       NOT NULL AUTO_INCREMENT,
        `client_id`    INT(11)       NULL,
        `phone`        VARCHAR(30)   NULL,
        `email`        VARCHAR(100)  NULL,
        `name`         VARCHAR(255)  NULL,
        `total_points` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_spent`  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `qr_token`     VARCHAR(64)   NULL,
        `registered_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_visit`   DATETIME      NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `client_id` (`client_id`),
        UNIQUE KEY `qr_token`  (`qr_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_transactions')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_loyalty_transactions` (
        `id`          INT(11)                        NOT NULL AUTO_INCREMENT,
        `customer_id` INT(11)                        NOT NULL,
        `receipt_id`  INT(11)                        NULL,
        `type`        ENUM("earn","redeem","adjust")  NOT NULL DEFAULT "earn",
        `points`      DECIMAL(15,2)                  NOT NULL DEFAULT 0.00,
        `description` VARCHAR(255)                   NULL,
        `created_at`  DATETIME                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `customer_id` (`customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}
