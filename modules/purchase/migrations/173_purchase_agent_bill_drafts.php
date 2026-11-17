<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_bill_drafts extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $captures = db_prefix() . 'purchase_agent_captures';
        if (!$CI->db->field_exists('bill_draft_ids', $captures)) {
            $CI->db->query("ALTER TABLE `{$captures}` ADD COLUMN `bill_draft_ids` TEXT NULL AFTER `expense_draft_ids`");
        }
    }

    public function down() {}
}
