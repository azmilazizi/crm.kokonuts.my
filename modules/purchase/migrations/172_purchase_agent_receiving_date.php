<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_receiving_date extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $drafts = db_prefix() . 'pur_order_drafts';
        if (!$CI->db->field_exists('items_received_date', $drafts)) {
            $CI->db->query("ALTER TABLE `{$drafts}` ADD COLUMN `items_received_date` DATE NULL AFTER `items_received`");
        }
    }

    public function down() {}
}
