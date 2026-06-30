<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_126 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'modifier_groups')) {
            $cols = $CI->db->list_fields(db_prefix() . 'modifier_groups');
            if (!in_array('is_promo_modifier', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifier_groups`
                    ADD COLUMN `is_promo_modifier` TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT "When 1, options must be picked from existing tblmodifiers items"');
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'modifiers')) {
            $cols = $CI->db->list_fields(db_prefix() . 'modifiers');
            if (!in_array('source_modifier_id', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifiers`
                    ADD COLUMN `source_modifier_id` INT(11) NULL DEFAULT NULL
                    COMMENT "tblmodifiers.id this option aliases (Promo Modifier Group only)"');
            }
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_crm_bundle_groups')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_crm_bundle_groups` (
                `id`                INT(11)      NOT NULL AUTO_INCREMENT,
                `promo_id`          INT(11)      NOT NULL,
                `name`              VARCHAR(255) NOT NULL DEFAULT "",
                `group_type`        ENUM("product_choice","modifier_choice") NOT NULL DEFAULT "product_choice",
                `source_type`       ENUM("custom","modifier_group_ref")      NOT NULL DEFAULT "custom",
                `modifier_group_id` INT(11)      NULL DEFAULT NULL
                    COMMENT "tblmodifier_groups.id when source_type = modifier_group_ref",
                `sort_order`        INT(11)      NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `promo_id` (`promo_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_crm_bundle_group_options')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_crm_bundle_group_options` (
                `id`              INT(11) NOT NULL AUTO_INCREMENT,
                `bundle_group_id` INT(11) NOT NULL,
                `option_type`     ENUM("item","modifier") NOT NULL DEFAULT "item",
                `option_id`       INT(11) NOT NULL,
                `sort_order`      INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `bundle_group_id` (`bundle_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();

        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_crm_bundle_group_options`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_crm_bundle_groups`');

        if ($CI->db->table_exists(db_prefix() . 'modifiers')) {
            $cols = $CI->db->list_fields(db_prefix() . 'modifiers');
            if (in_array('source_modifier_id', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifiers` DROP COLUMN `source_modifier_id`');
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'modifier_groups')) {
            $cols = $CI->db->list_fields(db_prefix() . 'modifier_groups');
            if (in_array('is_promo_modifier', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifier_groups` DROP COLUMN `is_promo_modifier`');
            }
        }
    }
}
