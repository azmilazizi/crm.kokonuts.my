<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_vendor_barcodes extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_vendor_aliases';
        if (!$CI->db->field_exists('source_barcode', $table)) {
            $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `source_barcode` VARCHAR(100) NULL AFTER `vendor_description`');
            $CI->db->query('ALTER TABLE `' . $table . '` ADD KEY `vendor_barcode` (`vendor_id`, `source_barcode`)');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_vendor_aliases';
        if ($CI->db->field_exists('source_barcode', $table)) {
            $CI->db->query('ALTER TABLE `' . $table . '` DROP KEY `vendor_barcode`');
            $CI->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `source_barcode`');
        }
    }
}
