<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_109 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();

        // --- pos_receipt_line_items ---
        if ($CI->db->table_exists(db_prefix() . 'pos_receipt_line_items')) {
            $cols = array_column(
                $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_receipt_line_items`')->result_array(),
                'Field'
            );

            $alter = [];

            if (!in_array('category_id', $cols)) {
                $alter[] = 'ADD COLUMN `category_id` INT(11) NULL DEFAULT NULL AFTER `item_name`';
            }
            if (!in_array('category_name', $cols)) {
                $alter[] = 'ADD COLUMN `category_name` VARCHAR(255) NULL DEFAULT NULL AFTER `category_id`';
            }
            if (!in_array('promotion_id', $cols)) {
                $alter[] = 'ADD COLUMN `promotion_id` INT(11) NULL DEFAULT NULL AFTER `total_money`';
            }
            if (!in_array('discount_type', $cols)) {
                $alter[] = 'ADD COLUMN `discount_type` ENUM("promotion","manual","loyalty") NULL DEFAULT NULL AFTER `promotion_id`';
            }

            if (!empty($alter)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipt_line_items` ' . implode(', ', $alter));
            }
        }

        // --- pos_receipts ---
        if ($CI->db->table_exists(db_prefix() . 'pos_receipts')) {
            $cols = array_column(
                $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_receipts`')->result_array(),
                'Field'
            );

            $alter = [];

            if (!in_array('cancellation_reason', $cols)) {
                $alter[] = 'ADD COLUMN `cancellation_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `cancelled_at`';
            }
            if (!in_array('cancelled_by_employee_id', $cols)) {
                $alter[] = 'ADD COLUMN `cancelled_by_employee_id` INT(11) NULL DEFAULT NULL AFTER `cancellation_reason`';
            }

            if (!empty($alter)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` ' . implode(', ', $alter));
            }
        }

        // --- pos_shifts ---
        if ($CI->db->table_exists(db_prefix() . 'pos_shifts')) {
            $cols = array_column(
                $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_shifts`')->result_array(),
                'Field'
            );

            if (!in_array('total_tips', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_shifts`
                    ADD COLUMN `total_tips` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `total_tax`');
            }
        }

        // --- pos_loyalty_transactions ---
        if ($CI->db->table_exists(db_prefix() . 'pos_loyalty_transactions')) {
            $cols = array_column(
                $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_loyalty_transactions`')->result_array(),
                'Field'
            );

            $alter = [];

            if (!in_array('warehouse_id', $cols)) {
                $alter[] = 'ADD COLUMN `warehouse_id` INT(11) NULL DEFAULT NULL AFTER `receipt_id`';
            }
            if (!in_array('balance_after', $cols)) {
                $alter[] = 'ADD COLUMN `balance_after` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `points`';
            }
            if (!in_array('tier_name', $cols)) {
                $alter[] = 'ADD COLUMN `tier_name` VARCHAR(100) NULL DEFAULT NULL AFTER `balance_after`';
            }

            if (!empty($alter)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_loyalty_transactions` ' . implode(', ', $alter));
            }
        }
    }

    public function down()
    {
        $CI =& get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pos_receipt_line_items')) {
            $cols  = array_column($CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_receipt_line_items`')->result_array(), 'Field');
            $drops = array_intersect(['category_id', 'category_name', 'promotion_id', 'discount_type'], $cols);
            if (!empty($drops)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipt_line_items` DROP COLUMN `' . implode('`, DROP COLUMN `', $drops) . '`');
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'pos_receipts')) {
            $cols  = array_column($CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_receipts`')->result_array(), 'Field');
            $drops = array_intersect(['cancellation_reason', 'cancelled_by_employee_id'], $cols);
            if (!empty($drops)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` DROP COLUMN `' . implode('`, DROP COLUMN `', $drops) . '`');
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'pos_shifts')) {
            $cols = array_column($CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_shifts`')->result_array(), 'Field');
            if (in_array('total_tips', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_shifts` DROP COLUMN `total_tips`');
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'pos_loyalty_transactions')) {
            $cols  = array_column($CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_loyalty_transactions`')->result_array(), 'Field');
            $drops = array_intersect(['warehouse_id', 'balance_after', 'tier_name'], $cols);
            if (!empty($drops)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_loyalty_transactions` DROP COLUMN `' . implode('`, DROP COLUMN `', $drops) . '`');
            }
        }
    }
}
