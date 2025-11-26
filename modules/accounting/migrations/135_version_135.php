<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_135 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Version 1.3.5
        if (!$CI->db->field_exists('parent_account', db_prefix() . 'acc_account_history')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . "acc_account_history` ADD COLUMN `parent_account` INT(11) NULL AFTER `account`;");
        }
    }
}
