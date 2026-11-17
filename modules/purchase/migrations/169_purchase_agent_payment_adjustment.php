<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_payment_adjustment extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_captures';
        if (!$CI->db->field_exists('payment_adjustment_amount', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `payment_adjustment_amount` DECIMAL(15,4) NULL AFTER `rounding_amount`");
        }
    }

    public function down() {}
}
