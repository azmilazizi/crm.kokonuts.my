<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
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

        if (!in_array('birthday', $existing)) {
            $add[] = 'ADD COLUMN `birthday` DATE NULL AFTER `email`';
        }
        if (!in_array('address1', $existing)) {
            $add[] = 'ADD COLUMN `address1` VARCHAR(255) NULL AFTER `birthday`';
        }
        if (!in_array('address2', $existing)) {
            $add[] = 'ADD COLUMN `address2` VARCHAR(255) NULL AFTER `address1`';
        }
        if (!in_array('city', $existing)) {
            $add[] = 'ADD COLUMN `city` VARCHAR(100) NULL AFTER `address2`';
        }
        if (!in_array('state', $existing)) {
            $add[] = 'ADD COLUMN `state` VARCHAR(100) NULL AFTER `city`';
        }
        if (!in_array('postcode', $existing)) {
            $add[] = 'ADD COLUMN `postcode` VARCHAR(20) NULL AFTER `state`';
        }
        if (!in_array('total_transactions', $existing)) {
            $add[] = 'ADD COLUMN `total_transactions` INT(11) NOT NULL DEFAULT 0 AFTER `total_spent`';
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

        foreach (['birthday', 'address1', 'address2', 'city', 'state', 'postcode', 'total_transactions'] as $col) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_loyalty_customers` DROP COLUMN IF EXISTS `' . $col . '`');
        }
    }
}
