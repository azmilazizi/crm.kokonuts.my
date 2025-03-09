<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_109 extends App_module_migration

{
  public function up()
  {      
    $CI = &get_instance();
    
    
    if (!$CI->db->table_exists(db_prefix() . 'currency_rates')) {
      $CI->db->query('CREATE TABLE `' . db_prefix() . "currency_rates` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `from_currency_id` int(11) NULL,
        `from_currency_name` VARCHAR(100) NULL,
        `from_currency_rate` decimal(15,6) NOT NULL DEFAULT '0.000000',
        `to_currency_id` int(11) NULL,
        `to_currency_name` VARCHAR(100) NULL,
        `to_currency_rate` decimal(15,6) NOT NULL DEFAULT '0.000000',
        `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
    }

    if (!$CI->db->table_exists(db_prefix() . 'currency_rate_logs')) {
      $CI->db->query('CREATE TABLE `' . db_prefix() . "currency_rate_logs` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `from_currency_id` int(11) NULL,
        `from_currency_name` VARCHAR(100) NULL,
        `from_currency_rate` decimal(15,6) NOT NULL DEFAULT '0.000000',
        `to_currency_id` int(11) NULL,
        `to_currency_name` VARCHAR(100) NULL,
        `to_currency_rate` decimal(15,6) NOT NULL DEFAULT '0.000000',
        `date` DATE NULL,

        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
    }

    add_option('cr_date_cronjob_currency_rates', '');
    add_option('cr_automatically_get_currency_rate', 1);
    add_option('cr_global_amount_expiration', 0);

    if (!$CI->db->field_exists('from_currency_id' ,db_prefix() . 'hrp_payslips')) { 
      $CI->db->query('ALTER TABLE `' . db_prefix() . "hrp_payslips`
        ADD COLUMN `from_currency_id` int(11) NULL DEFAULT 0,
        ADD COLUMN `from_currency_name` VARCHAR(100) NULL,
        ADD COLUMN `from_currency_rate` decimal(15,6) NOT NULL DEFAULT '1',
        ADD COLUMN `to_currency_id` int(11) NULL DEFAULT 0,
        ADD COLUMN `to_currency_name` VARCHAR(100) NULL,
        ADD COLUMN `to_currency_rate` decimal(15,6) NOT NULL DEFAULT '1'

        ;");
    }
  }

}

