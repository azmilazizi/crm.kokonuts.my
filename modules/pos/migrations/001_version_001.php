<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_001 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        $CI->db->query('
            CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'modifier_groups` (
                `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `name`            VARCHAR(191)    NOT NULL,
                `selection_type`  ENUM("single","multiple") NOT NULL DEFAULT "single",
                `min_selections`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `max_selections`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `active`          TINYINT(1)      NOT NULL DEFAULT 1,
                `datecreated`     DATETIME        NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');

        $CI->db->query('
            CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'modifiers` (
                `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `modifier_group_id` INT UNSIGNED    NOT NULL,
                `name`              VARCHAR(191)    NOT NULL,
                `price_adjustment`  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
                `sort_order`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `active`            TINYINT(1)      NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `modifier_group_id` (`modifier_group_id`),
                CONSTRAINT `fk_modifiers_group`
                    FOREIGN KEY (`modifier_group_id`)
                    REFERENCES `' . db_prefix() . 'modifier_groups` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');

        $CI->db->query('
            CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'item_modifier_groups` (
                `pos_item_id`       VARCHAR(191)    NOT NULL,
                `modifier_group_id` INT UNSIGNED    NOT NULL,
                `sort_order`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`pos_item_id`, `modifier_group_id`),
                KEY `modifier_group_id` (`modifier_group_id`),
                CONSTRAINT `fk_item_modifier_groups_group`
                    FOREIGN KEY (`modifier_group_id`)
                    REFERENCES `' . db_prefix() . 'modifier_groups` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');

        $CI->db->query('
            CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'order_line_modifiers` (
                `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `order_line_id`    INT UNSIGNED    NOT NULL,
                `modifier_id`      INT UNSIGNED    NOT NULL,
                `name`             VARCHAR(191)    NOT NULL,
                `price_adjustment` DECIMAL(15,2)   NOT NULL,
                PRIMARY KEY (`id`),
                KEY `order_line_id` (`order_line_id`),
                KEY `modifier_id` (`modifier_id`),
                CONSTRAINT `fk_order_line_modifiers_modifier`
                    FOREIGN KEY (`modifier_id`)
                    REFERENCES `' . db_prefix() . 'modifiers` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    public function down()
    {
        $CI = &get_instance();

        $CI->db->query('SET FOREIGN_KEY_CHECKS = 0;');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'order_line_modifiers`;');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'item_modifier_groups`;');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'modifiers`;');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'modifier_groups`;');
        $CI->db->query('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
