<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_106 extends CI_Migration
{
    public function up()
    {
        $CI =& get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pos_receipts')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts`
                ADD COLUMN IF NOT EXISTS `cashback_claimed_at` DATETIME NULL DEFAULT NULL');
        }
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts`
            DROP COLUMN IF EXISTS `cashback_claimed_at`');
    }
}
