<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_category_separation extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_lines';
        if (!$CI->db->field_exists('bill_category_id', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `bill_category_id` INT(11) NULL AFTER `expense_category_id`");
        }
    }

    public function down() {}
}
