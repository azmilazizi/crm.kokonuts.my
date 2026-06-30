<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_124 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_crm_promos')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_crm_promos` (
                `id`             INT(11)          NOT NULL AUTO_INCREMENT,
                `name`           VARCHAR(255)     NOT NULL,
                `type`           ENUM("promo","bundle") NOT NULL DEFAULT "promo",
                `pos_item_id`    INT(11)          NULL COMMENT "tblitems.id of the product flagged as this promo/bundle",
                `description`    TEXT             NULL,
                `discount_type`  ENUM("percentage","fixed") NULL,
                `discount_value` DECIMAL(15,2)    NULL DEFAULT 0.00,
                `active`         TINYINT(1)       NOT NULL DEFAULT 1,
                `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `pos_item_id` (`pos_item_id`),
                KEY `type` (`type`),
                KEY `active` (`active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_crm_promo_components')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_crm_promo_components` (
                `id`             INT(11)          NOT NULL AUTO_INCREMENT,
                `promo_id`       INT(11)          NOT NULL,
                `component_type` ENUM("product","modifier") NOT NULL DEFAULT "product",
                `component_id`   INT(11)          NULL COMMENT "tblitems.id or tblmodifiers.id",
                `component_name` VARCHAR(255)     NOT NULL,
                `quantity`       DECIMAL(15,2)    NOT NULL DEFAULT 1.00,
                `notes`          VARCHAR(500)     NULL,
                `sort_order`     INT(11)          NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `promo_id` (`promo_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_crm_promo_components`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_crm_promos`');
    }
}
