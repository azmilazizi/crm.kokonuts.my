<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_104 extends CI_Migration
{
    public function up()
    {
        $CI =& get_instance();
        $table = db_prefix() . 'pos_receipts';

        $cols = $CI->db->query('SHOW COLUMNS FROM `' . $table . '`')->result_array();
        $col_names = array_column($cols, 'Field');

        if (!in_array('queue_number', $col_names)) {
            $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `queue_number` INT(11) NULL DEFAULT NULL AFTER `receipt_number`');
        }
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` DROP COLUMN IF EXISTS `queue_number`');
    }
}
