<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_156 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->field_exists('units_per_batch', db_prefix() . 'pur_order_draft_items')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pur_order_draft_items`
                ADD COLUMN `units_per_batch` DECIMAL(15,4) NULL');
        }
    }
}
