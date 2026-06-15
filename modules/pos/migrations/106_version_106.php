<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_106 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pos_receipts')) {
            $cols = array_column(
                $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_receipts`')->result_array(),
                'Field'
            );
            if (!in_array('cashback_claimed_at', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts`
                    ADD COLUMN `cashback_claimed_at` DATETIME NULL DEFAULT NULL');
            }
        }
    }

    public function down()
    {
        $CI =& get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pos_receipts')) {
            $cols = array_column(
                $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_receipts`')->result_array(),
                'Field'
            );
            if (in_array('cashback_claimed_at', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts`
                    DROP COLUMN `cashback_claimed_at`');
            }
        }
    }
}
