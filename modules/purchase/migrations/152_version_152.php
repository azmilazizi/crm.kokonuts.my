<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_152 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->field_exists('items_received', db_prefix() . 'pur_order_draft_items')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . "pur_order_draft_items`
                ADD COLUMN `items_received` decimal(15,4) NULL DEFAULT 0
            ;");
        }
    }
}
