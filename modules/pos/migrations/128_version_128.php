<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_128 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_inventory_rules')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_inventory_rules` (
                `id`                INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `owner_type`        ENUM("product","modifier","item_modifier_option") NOT NULL,
                `owner_id`          INT(11) NOT NULL,
                `role_key`          VARCHAR(100) NULL DEFAULT NULL,
                `action_type`       ENUM("deduct","replace","remove") NOT NULL DEFAULT "deduct",
                `inventory_item_id` INT(11) NULL DEFAULT NULL,
                `quantity`          DECIMAL(15,3) NOT NULL DEFAULT 1.000,
                `priority`          INT(11) NOT NULL DEFAULT 0,
                `sort_order`        INT(11) NOT NULL DEFAULT 0,
                `active`            TINYINT(1) NOT NULL DEFAULT 1,
                `created_at`        DATETIME NOT NULL,
                `updated_at`        DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `owner_lookup` (`owner_type`, `owner_id`),
                KEY `inventory_item_id` (`inventory_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_receipt_inventory_deductions')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_receipt_inventory_deductions` (
                `id`                   INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `receipt_id`           INT(11) NOT NULL,
                `receipt_line_item_id` INT(11) NOT NULL,
                `warehouse_id`         INT(11) NOT NULL,
                `inventory_item_id`    INT(11) NOT NULL,
                `inventory_manage_id`  INT(11) NULL DEFAULT NULL,
                `role_key`             VARCHAR(100) NULL DEFAULT NULL,
                `quantity`             DECIMAL(15,3) NOT NULL DEFAULT 0.000,
                `restored_quantity`    DECIMAL(15,3) NOT NULL DEFAULT 0.000,
                `source_type`          ENUM("product","modifier") NOT NULL,
                `source_owner_id`      INT(11) NOT NULL,
                `source_rule_id`       INT(11) NULL DEFAULT NULL,
                `note`                 VARCHAR(255) NULL DEFAULT NULL,
                `created_at`           DATETIME NOT NULL,
                `restored_at`          DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `receipt_id` (`receipt_id`),
                KEY `receipt_line_item_id` (`receipt_line_item_id`),
                KEY `warehouse_id` (`warehouse_id`),
                KEY `inventory_item_id` (`inventory_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();

        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_receipt_inventory_deductions`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_inventory_rules`');
    }
}
