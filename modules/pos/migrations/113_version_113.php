<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_113 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_grabfood_settings')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_grabfood_settings` (
                `id`               INT(11)        NOT NULL AUTO_INCREMENT,
                `warehouse_id`     INT(11)        NOT NULL,
                `client_id`        VARCHAR(255)   NULL DEFAULT NULL,
                `client_secret`    TEXT           NULL DEFAULT NULL,
                `partner_id`       VARCHAR(255)   NULL DEFAULT NULL COMMENT "Grab merchant/partner ID",
                `grabfood_store_id` VARCHAR(255)  NULL DEFAULT NULL COMMENT "GrabFood outlet store ID",
                `environment`      ENUM("sandbox","production") NOT NULL DEFAULT "sandbox",
                `active`           TINYINT(1)     NOT NULL DEFAULT 1,
                `access_token`     TEXT           NULL DEFAULT NULL,
                `token_expires_at` DATETIME       NULL DEFAULT NULL,
                `last_sync_at`     DATETIME       NULL DEFAULT NULL,
                `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`       DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `warehouse_id` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_grabfood_orders')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_grabfood_orders` (
                `id`                    INT(11)        NOT NULL AUTO_INCREMENT,
                `receipt_id`            INT(11)        NULL DEFAULT NULL COMMENT "FK to pos_receipts",
                `warehouse_id`          INT(11)        NOT NULL,
                `grabfood_order_id`     VARCHAR(255)   NOT NULL,
                `order_short_ref`       VARCHAR(100)   NULL DEFAULT NULL COMMENT "Collection number shown to customer",
                `order_status`          VARCHAR(50)    NOT NULL DEFAULT "PLACED",
                `order_type`            VARCHAR(50)    NULL DEFAULT NULL COMMENT "DELIVERY, SELF_PICKUP, DINE_IN",
                `subtotal`              DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
                `discount`              DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
                `delivery_fee`          DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
                `total`                 DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
                `customer_name`         VARCHAR(255)   NULL DEFAULT NULL,
                `customer_phone`        VARCHAR(100)   NULL DEFAULT NULL,
                `delivery_address`      TEXT           NULL DEFAULT NULL,
                `scheduled_at`          DATETIME       NULL DEFAULT NULL,
                `estimated_pickup_time` DATETIME       NULL DEFAULT NULL,
                `raw_payload`           MEDIUMTEXT     NULL DEFAULT NULL,
                `synced_at`             DATETIME       NULL DEFAULT NULL,
                `created_at`            DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`            DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `grabfood_order_id` (`grabfood_order_id`),
                KEY `warehouse_id` (`warehouse_id`),
                KEY `order_status` (`order_status`),
                KEY `receipt_id` (`receipt_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_grabfood_order_items')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_grabfood_order_items` (
                `id`                INT(11)        NOT NULL AUTO_INCREMENT,
                `grabfood_order_id` VARCHAR(255)   NOT NULL,
                `item_id`           VARCHAR(255)   NULL DEFAULT NULL,
                `item_name`         VARCHAR(500)   NOT NULL,
                `quantity`          INT(11)        NOT NULL DEFAULT 1,
                `unit_price`        DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
                `total_price`       DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
                `modifiers_json`    TEXT           NULL DEFAULT NULL,
                `notes`             TEXT           NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `grabfood_order_id` (`grabfood_order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_grabfood_order_items`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_grabfood_orders`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_grabfood_settings`');
    }
}
