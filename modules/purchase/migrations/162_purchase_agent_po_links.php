<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_po_links extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_captures';
        foreach (['purchase_order_draft_id', 'purchase_order_id', 'goods_receipt_id'] as $field) {
            if (!$CI->db->field_exists($field, $table)) {
                $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $field . '` VARCHAR(100) NULL');
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_captures';
        foreach (['purchase_order_draft_id', 'purchase_order_id', 'goods_receipt_id'] as $field) {
            if ($CI->db->field_exists($field, $table)) {
                $CI->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `' . $field . '`');
            }
        }
    }
}
