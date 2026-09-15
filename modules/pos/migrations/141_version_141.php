<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_141 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Outlet scoping for checklist templates, moved from a single nullable
        // warehouse_id column to a many-to-many junction table — no rows =
        // global fallback template, mirroring pos_modifier_group_warehouses.
        if (!$CI->db->table_exists(db_prefix() . 'pos_checklist_template_warehouses')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_checklist_template_warehouses` (
                `template_id`  INT UNSIGNED NOT NULL,
                `warehouse_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`template_id`, `warehouse_id`),
                KEY `warehouse_id` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if ($CI->db->field_exists('warehouse_id', db_prefix() . 'pos_checklist_templates')) {
            $existing = $CI->db->select('id, warehouse_id')
                ->where('warehouse_id IS NOT NULL')
                ->get(db_prefix() . 'pos_checklist_templates')->result_array();
            foreach ($existing as $row) {
                $CI->db->insert(db_prefix() . 'pos_checklist_template_warehouses', [
                    'template_id' => $row['id'],
                    'warehouse_id' => $row['warehouse_id'],
                ]);
            }
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_checklist_templates` DROP COLUMN `warehouse_id`');
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if (!$CI->db->field_exists('warehouse_id', db_prefix() . 'pos_checklist_templates')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_checklist_templates` ADD COLUMN `warehouse_id` INT UNSIGNED NULL AFTER `id`');
        }

        if ($CI->db->table_exists(db_prefix() . 'pos_checklist_template_warehouses')) {
            $CI->db->query('DROP TABLE `' . db_prefix() . 'pos_checklist_template_warehouses`');
        }
    }
}
