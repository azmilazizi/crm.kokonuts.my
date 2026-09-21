<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_143 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // HQ back-kitchen Production: turns the existing cost-only
        // pos_item_yields ratios into a real, transactional inventory_manage
        // movement (source item deducted, one or more output items credited),
        // mirroring the same delta debit/credit pattern the POS sale flow
        // already uses (_deduct_inventory_stock/_restore_inventory_stock),
        // with its own audit trail generalizing pos_receipt_inventory_deductions
        // to both directions.
        if (!$CI->db->table_exists(db_prefix() . 'pos_production_runs')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_production_runs` (
                `id`               INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `warehouse_id`     INT(11) NOT NULL,
                `source_item_id`   INT(11) NOT NULL,
                `source_quantity`  DECIMAL(15,3) NOT NULL,
                `staff_id`         INT(11) NOT NULL,
                `status`           ENUM("completed","voided") NOT NULL DEFAULT "completed",
                `note`             TEXT NULL DEFAULT NULL,
                `created_at`       DATETIME NOT NULL,
                `voided_at`        DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `warehouse_id` (`warehouse_id`),
                KEY `source_item_id` (`source_item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_production_run_outputs')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_production_run_outputs` (
                `id`                  INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `production_run_id`   INT(11) NOT NULL,
                `output_item_id`      INT(11) NOT NULL,
                `quantity_produced`   DECIMAL(15,3) NOT NULL,
                `unit_cost_snapshot`  DECIMAL(15,4) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `production_run_id` (`production_run_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_production_run_deductions')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_production_run_deductions` (
                `id`                  INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `production_run_id`   INT(11) NOT NULL,
                `direction`           ENUM("deduct","credit") NOT NULL,
                `inventory_item_id`   INT(11) NOT NULL,
                `inventory_manage_id` INT(11) NULL DEFAULT NULL,
                `quantity`            DECIMAL(15,3) NOT NULL,
                `created_at`          DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `production_run_id` (`production_run_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();

        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_production_run_deductions`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_production_run_outputs`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_production_runs`');
    }
}
