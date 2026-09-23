<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_144 extends App_module_migration
{
    // Franchisee Cost Profit: an explicit "what we actually sell this to a
    // franchisee for" override, settable on any raw ingredient / packaging /
    // mixed ingredient (Individual Ingredients, Packaging, Mixed Ingredients
    // tabs) and on modifiers (Modifiers Cost Profit tab). When set, the new
    // Franchisee Cost Profit tab uses it instead of the item's own raw/BOM
    // cost when summing a product's total cost from a franchisee's
    // perspective — see get_franchisee_cost_profit_summary() in Pos_model.
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'items')) {
            $cols = $CI->db->list_fields(db_prefix() . 'items');
            if (!in_array('franchisee_price', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'items`
                    ADD COLUMN `franchisee_price` DECIMAL(15,4) NULL DEFAULT NULL AFTER `cached_cost_per_unit`');
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'modifiers')) {
            $cols = $CI->db->list_fields(db_prefix() . 'modifiers');
            if (!in_array('franchisee_price', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifiers`
                    ADD COLUMN `franchisee_price` DECIMAL(15,4) NULL DEFAULT NULL');
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'items')) {
            $cols = $CI->db->list_fields(db_prefix() . 'items');
            if (in_array('franchisee_price', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'items` DROP COLUMN `franchisee_price`');
            }
        }

        if ($CI->db->table_exists(db_prefix() . 'modifiers')) {
            $cols = $CI->db->list_fields(db_prefix() . 'modifiers');
            if (in_array('franchisee_price', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifiers` DROP COLUMN `franchisee_price`');
            }
        }
    }
}
