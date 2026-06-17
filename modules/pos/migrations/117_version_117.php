<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_117 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();
        // Widen queue_number to VARCHAR(10) so GrabFood short order numbers (e.g. "GF-9303")
        // can be stored alongside numeric walk-in queue numbers (e.g. "101").
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` MODIFY COLUMN `queue_number` VARCHAR(10) NULL DEFAULT NULL');
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_receipts` MODIFY COLUMN `queue_number` INT(11) NULL DEFAULT NULL');
    }
}
