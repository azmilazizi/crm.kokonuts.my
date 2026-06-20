<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_137 extends App_module_migration
{
    public function up()
    {
        if (!$this->db->table_exists(db_prefix() . 'acc_bill_categories')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . "acc_bill_categories` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(255) NOT NULL,
              `debit_account` INT(11) NULL,
              `credit_account` INT(11) NULL,
              `description` TEXT NULL,
              `active` INT(11) NOT NULL DEFAULT 1,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $this->db->char_set . ';');
        }

        if (!$this->db->field_exists('bill_category_id', db_prefix() . 'expenses')) {
            $this->db->query('ALTER TABLE `' . db_prefix() . 'expenses`
                ADD COLUMN `bill_category_id` INT(11) NULL DEFAULT NULL;');
        }
    }
}
