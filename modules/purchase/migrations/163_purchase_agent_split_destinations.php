<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_split_destinations extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        foreach ([
            'payment_total' => 'DECIMAL(15,4) NULL',
            'payment_method' => 'VARCHAR(100) NULL',
            'payment_date' => 'DATE NULL',
            'rounding_amount' => 'DECIMAL(15,4) NULL',
            'expense_draft_ids' => 'TEXT NULL',
        ] as $field => $definition) {
            if (!$CI->db->field_exists($field, $p . 'purchase_agent_captures')) {
                $CI->db->query("ALTER TABLE `{$p}purchase_agent_captures` ADD COLUMN `{$field}` {$definition}");
            }
        }
        foreach ([
            'destination' => "VARCHAR(20) NOT NULL DEFAULT 'po'",
            'expense_category_id' => 'INT(11) NULL',
        ] as $field => $definition) {
            if (!$CI->db->field_exists($field, $p . 'purchase_agent_lines')) {
                $CI->db->query("ALTER TABLE `{$p}purchase_agent_lines` ADD COLUMN `{$field}` {$definition}");
            }
        }
    }

    public function down() {}
}
