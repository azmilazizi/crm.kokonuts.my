<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_customers')) {
            return;
        }

        $existing = array_column(
            $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'pos_loyalty_customers`')->result_array(),
            'Field'
        );

        $add = [];

        if (!in_array('pdpa_consent', $existing)) {
            $add[] = 'ADD COLUMN `pdpa_consent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_visit`';
        }
        if (!in_array('pdpa_consented_at', $existing)) {
            $add[] = 'ADD COLUMN `pdpa_consented_at` DATETIME NULL AFTER `pdpa_consent`';
        }

        if (!empty($add)) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_loyalty_customers` ' . implode(', ', $add));
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_loyalty_customers')) {
            return;
        }

        foreach (['pdpa_consent', 'pdpa_consented_at'] as $col) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_loyalty_customers` DROP COLUMN IF EXISTS `' . $col . '`');
        }
    }
}
