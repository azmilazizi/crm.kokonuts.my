<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_google_review extends App_module_migration
{
    public function up()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_customers';

        if (!$CI->db->table_exists($table)) {
            return;
        }

        $existing = array_column(
            $CI->db->query('SHOW COLUMNS FROM `' . $table . '`')->result_array(),
            'Field'
        );

        $add = [];

        if (!in_array('google_review_prompted_at', $existing)) {
            $add[] = 'ADD COLUMN `google_review_prompted_at` DATETIME NULL DEFAULT NULL';
        }
        if (!in_array('google_review_done', $existing)) {
            $add[] = 'ADD COLUMN `google_review_done` TINYINT(1) NOT NULL DEFAULT 0';
        }

        if (!empty($add)) {
            $CI->db->query('ALTER TABLE `' . $table . '` ' . implode(', ', $add));
        }
    }

    public function down()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_customers';

        if (!$CI->db->table_exists($table)) {
            return;
        }

        foreach (['google_review_prompted_at', 'google_review_done'] as $col) {
            $CI->db->query('ALTER TABLE `' . $table . '` DROP COLUMN IF EXISTS `' . $col . '`');
        }
    }
}
