<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_145 extends App_module_migration
{
    // Capital Reserve: opt-in per-item tracking of how much of an item's
    // stock has depleted since it was last topped up to its par level.
    // capital_reserve_par_level is a manually-set "what a full restock looks
    // like" quantity (not derived from the latest PO, since order sizes
    // vary) — see get_capital_reserve_items() in Pos_model.
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'items')) {
            $cols = $CI->db->list_fields(db_prefix() . 'items');

            if (!in_array('capital_reserve_enabled', $cols)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "items`
                    ADD COLUMN `capital_reserve_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Item accrues a capital reserve target based on stock depletion vs capital_reserve_par_level'");
            }

            if (!in_array('capital_reserve_par_level', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . "items`
                    ADD COLUMN `capital_reserve_par_level` DECIMAL(15,4) NULL DEFAULT NULL COMMENT 'Manually-set full-stock quantity used to compute % remaining for the capital reserve'");
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'items')) {
            $cols = $CI->db->list_fields(db_prefix() . 'items');

            if (in_array('capital_reserve_par_level', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'items` DROP COLUMN `capital_reserve_par_level`');
            }

            if (in_array('capital_reserve_enabled', $cols)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'items` DROP COLUMN `capital_reserve_enabled`');
            }
        }
    }
}
