<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_140 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_checklist_templates')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "pos_checklist_templates` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `warehouse_id` INT UNSIGNED NULL COMMENT 'NULL = global fallback template, applies to any outlet without one of its own',
                `type` ENUM('sop_open','sop_close','equipment') NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `warehouse_id` (`warehouse_id`),
                KEY `type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_checklist_groups')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "pos_checklist_groups` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `template_id` INT UNSIGNED NOT NULL COMMENT 'FK to pos_checklist_templates.id',
                `name` VARCHAR(150) NOT NULL COMMENT 'e.g. Mesh bag, Blue ice container',
                `transport_role` VARCHAR(150) NULL COMMENT 'Descriptive label for what this container holds in transit',
                `onsite_role` VARCHAR(150) NULL COMMENT 'Descriptive label for what this container is used for on-site',
                `sort_order` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `template_id` (`template_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_checklist_items')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "pos_checklist_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `template_id` INT UNSIGNED NOT NULL COMMENT 'FK to pos_checklist_templates.id, denormalized for simple all-items-in-template queries',
                `group_id` INT UNSIGNED NULL COMMENT 'FK to pos_checklist_groups.id, NULL = standalone item directly under the template',
                `label` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `sort_order` INT DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `template_id` (`template_id`),
                KEY `group_id` (`group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_checklist_completions')) {
            $CI->db->query("CREATE TABLE `" . db_prefix() . "pos_checklist_completions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `shift_id` INT UNSIGNED NOT NULL COMMENT 'FK to pos_shifts.id',
                `template_id` INT UNSIGNED NULL COMMENT 'FK to pos_checklist_templates.id, nullable since the template may later be deleted or none was configured',
                `warehouse_id` INT UNSIGNED NOT NULL,
                `type` ENUM('sop_open','sop_close') NOT NULL,
                `employee_id` INT UNSIGNED NULL COMMENT 'Who ran the checklist',
                `is_complete` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether every non-excluded item was checked',
                `items_snapshot` LONGTEXT NULL COMMENT 'JSON array of {item_id,label,status} at time of submission, for dispute resolution — not a trend-analysis table',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `shift_id` (`shift_id`),
                KEY `warehouse_id` (`warehouse_id`),
                KEY `type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function down()
    {
        $CI = &get_instance();

        foreach ([
            'pos_checklist_completions',
            'pos_checklist_items',
            'pos_checklist_groups',
            'pos_checklist_templates',
        ] as $table) {
            if ($CI->db->table_exists(db_prefix() . $table)) {
                $CI->db->query("DROP TABLE `" . db_prefix() . $table . "`");
            }
        }
    }
}
