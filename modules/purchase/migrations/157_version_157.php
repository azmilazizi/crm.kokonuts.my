<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_157 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // unit_price was computed as into_money / quantity, ignoring
        // units_per_batch — a purely derived/reporting field, so it's safe
        // to recompute in place. into_money/total/total_money (the
        // authoritative $ figures) are never touched.
        $CI->db->query('UPDATE `' . db_prefix() . 'pur_order_detail`
            SET `unit_price` = ROUND(`into_money` / (`quantity` * `units_per_batch`), 4)
            WHERE `units_per_batch` IS NOT NULL AND `units_per_batch` > 0 AND `quantity` > 0');
    }
}
