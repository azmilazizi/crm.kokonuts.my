<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_149 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'pos_receipt_settings';
        if (!$CI->db->field_exists('google_review_url', $table)) {
            $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `google_review_url` VARCHAR(2048) NULL');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'pos_receipt_settings';
        if ($CI->db->field_exists('google_review_url', $table)) {
            $CI->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `google_review_url`');
        }
    }
}
