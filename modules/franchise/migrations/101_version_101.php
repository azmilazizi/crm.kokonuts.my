<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Distinguishes HQ/production warehouses from ordinary retail outlets.
        // Deliberately NOT inferred from franchisee_id (NULL there just means
        // "HQ-owned outlet", not "this is the HQ production site") — every
        // warehouse defaults to 'outlet' here; whichever warehouse is actually
        // the HQ back-kitchen must be flipped to 'hq' afterwards via the
        // Franchise admin screen (Franchise_model::set_warehouse_type()).
        if (!$CI->db->field_exists('warehouse_type', db_prefix() . 'warehouse')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . "warehouse`
                ADD COLUMN `warehouse_type` ENUM('outlet','hq') NOT NULL DEFAULT 'outlet' AFTER `franchisee_id`;");
        }

        // Links a franchisee to their core CRM client record so they can be
        // billed with real Estimates/Invoices instead of the franchise module
        // tracking a parallel, non-billable identity.
        if (!$CI->db->field_exists('client_id', db_prefix() . 'franchise_franchisees')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . "franchise_franchisees`
                ADD COLUMN `client_id` int(11) NULL AFTER `id`,
                ADD INDEX `client_id` (`client_id`);");
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->field_exists('client_id', db_prefix() . 'franchise_franchisees')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'franchise_franchisees` DROP COLUMN `client_id`');
        }

        if ($CI->db->field_exists('warehouse_type', db_prefix() . 'warehouse')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'warehouse` DROP COLUMN `warehouse_type`');
        }
    }
}
