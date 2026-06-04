<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_100 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_transactions')) {
            return;
        }

        $col = $CI->db->query(
            'SHOW COLUMNS FROM `' . db_prefix() . 'pos_loyalty_transactions` WHERE Field = "type"'
        )->row_array();

        if ($col && strpos($col['Type'], 'adjust') === false) {
            $CI->db->query(
                'ALTER TABLE `' . db_prefix() . 'pos_loyalty_transactions`
                 MODIFY COLUMN `type` ENUM("earn","redeem","adjust") NOT NULL'
            );
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_transactions')) {
            return;
        }

        $CI->db->query(
            'DELETE FROM `' . db_prefix() . 'pos_loyalty_transactions` WHERE `type` = "adjust"'
        );
        $CI->db->query(
            'ALTER TABLE `' . db_prefix() . 'pos_loyalty_transactions`
             MODIFY COLUMN `type` ENUM("earn","redeem") NOT NULL'
        );
    }
}
