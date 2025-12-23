<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_323 extends CI_Migration
{
    public function up()
    {
        $tableName = db_prefix() . 'staff';

        if (!$this->db->table_exists($tableName)) {
            return;
        }

        if ($this->db->field_exists('passcode', $tableName)) {
            return;
        }

        $this->db->query("ALTER TABLE `{$tableName}` ADD `passcode` VARCHAR(250) NULL AFTER `password`;");
    }
}
