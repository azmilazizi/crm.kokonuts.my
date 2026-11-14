<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_barcodes extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_lines';
        if (!$CI->db->field_exists('source_barcode', $table)) {
            $CI->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `source_barcode` VARCHAR(100) NULL AFTER `source_description`');
            $CI->db->query('ALTER TABLE `' . $table . '` ADD KEY `source_barcode` (`source_barcode`)');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_lines';
        if ($CI->db->field_exists('source_barcode', $table)) {
            $CI->db->query('ALTER TABLE `' . $table . '` DROP KEY `source_barcode`');
            $CI->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `source_barcode`');
        }
    }
}
