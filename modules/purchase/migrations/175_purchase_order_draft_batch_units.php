<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_order_draft_batch_units extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'pur_order_draft_items';

        if (!$CI->db->table_exists($table)) {
            return;
        }

        if (!$CI->db->field_exists('units_per_batch', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `units_per_batch` DECIMAL(15,4) NULL");
        }
        if (!$CI->db->field_exists('batch_quantity', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `batch_quantity` DECIMAL(15,4) NULL");
        }
        if (!$CI->db->field_exists('base_quantity', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `base_quantity` DECIMAL(15,4) NULL");
        }

        $CI->db->query("UPDATE `{$table}`
            SET `batch_quantity` = `quantity`,
                `base_quantity` = CASE
                    WHEN `units_per_batch` IS NOT NULL THEN `quantity` * `units_per_batch`
                    ELSE NULL
                END
            WHERE `batch_quantity` IS NULL OR `base_quantity` IS NULL");
    }
}
