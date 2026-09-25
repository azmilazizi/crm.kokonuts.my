<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_148 extends App_module_migration
{
    // Migrations 130/135/136/139 concatenated db_prefix() (which is already
    // "tbl") with a table name that ALSO started with "tbl" ('tblpos_x'
    // instead of 'pos_x'), creating stray tables literally named
    // tbltblpos_x alongside the correctly-named tblpos_x ones that the app
    // actually reads/writes (grep across modules/ and application/ found
    // zero code references to the tbltblpos_x names, only this comment).
    // Dropping the orphans; no FOREIGN KEY constraints tie them to anything
    // (plain KEY indexes only), so order doesn't matter, but kept the same
    // order as 130's own down() for a diff-friendly comparison.
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();

        $stray_tables = [
            'tblpos_cost_snapshot_values',
            'tblpos_cost_snapshots',
            'tblpos_combo_components',
            'tblpos_product_variants',
            'tblpos_product_variant_groups',
            'tblpos_product_bom',
            'tblpos_mixed_ingredient_components',
            'tblpos_mixed_ingredients',
            'tblpos_uoms',
            'tblpos_modifier_bom',
            'tblpos_item_yields',
        ];

        foreach ($stray_tables as $table) {
            // $p is already "tbl", so this targets tbl + tblpos_x = tbltblpos_x —
            // the buggy name — deliberately, not a typo.
            $CI->db->query('DROP TABLE IF EXISTS `' . $p . $table . '`');
        }
    }

    public function down()
    {
        // Deliberately not reversible — these were dead, buggy-named tables
        // with zero code references; recreating them would just bring the
        // clutter back, not restore anything that was actually in use.
    }
}
