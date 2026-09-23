<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_146 extends App_module_migration
{
    // Capital Reserve's "par level" duplicated the Warehouse module's
    // existing tblinventory_commodity_min.inventory_number_max (shown as
    // "Maximum stock" on Warehouse > Items) — same concept, two places to
    // maintain it. This backfills any par level already saved via the
    // Capital Reserve tab into inventory_number_max (without overwriting a
    // max stock value that was already set there some other way, e.g. the
    // bulk import path), then drops the now-redundant items column.
    // get_capital_reserve_items()/save_capital_reserve_setting() in
    // Pos_model read/write inventory_number_max directly from here on.
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();

        if ($CI->db->table_exists($p . 'items') && $CI->db->field_exists('capital_reserve_par_level', $p . 'items')
            && $CI->db->table_exists($p . 'inventory_commodity_min')) {
            $items = $CI->db->select('id, sku_code, sku_name, capital_reserve_par_level')
                ->where('capital_reserve_par_level IS NOT NULL', null, false)
                ->get($p . 'items')->result_array();

            foreach ($items as $item) {
                $existing = $CI->db->where('commodity_id', (int) $item['id'])
                    ->order_by('id', 'DESC')->limit(1)
                    ->get($p . 'inventory_commodity_min')->row_array();

                if ($existing) {
                    if ($existing['inventory_number_max'] === null || $existing['inventory_number_max'] === '' || (float) $existing['inventory_number_max'] <= 0) {
                        $CI->db->where('id', (int) $existing['id'])->update($p . 'inventory_commodity_min', [
                            'inventory_number_max' => $item['capital_reserve_par_level'],
                        ]);
                    }
                } else {
                    $CI->db->insert($p . 'inventory_commodity_min', [
                        'commodity_id'         => (int) $item['id'],
                        'commodity_code'       => $item['sku_code'],
                        'commodity_name'       => $item['sku_name'],
                        'inventory_number_min' => 0,
                        'inventory_number_max' => $item['capital_reserve_par_level'],
                    ]);
                }
            }
        }

        if ($CI->db->table_exists($p . 'items') && $CI->db->field_exists('capital_reserve_par_level', $p . 'items')) {
            $CI->db->query('ALTER TABLE `' . $p . 'items` DROP COLUMN `capital_reserve_par_level`');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $p = db_prefix();

        if ($CI->db->table_exists($p . 'items') && !$CI->db->field_exists('capital_reserve_par_level', $p . 'items')) {
            $CI->db->query("ALTER TABLE `" . $p . "items`
                ADD COLUMN `capital_reserve_par_level` DECIMAL(15,4) NULL DEFAULT NULL COMMENT 'Manually-set full-stock quantity used to compute % remaining for the capital reserve'");
        }
        // Deliberately not reversing the inventory_commodity_min backfill —
        // those values are now the live "Maximum stock" figures and pulling
        // them back out would be destructive to data entered since.
    }
}
