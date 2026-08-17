<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pos_model extends App_Model
{
    protected $last_inventory_error = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_last_inventory_error()
    {
        return $this->last_inventory_error;
    }

    private function _set_inventory_error($message)
    {
        $this->last_inventory_error = $message;

        return false;
    }

    public function get_inventory_tracking_items()
    {
        return $this->db
            ->select('i.id, i.sku_name, i.sku_code')
            ->from(db_prefix() . 'items i')
            ->where('i.parent_id IS NULL', null, false)
            ->where('i.active', 1)
            ->where('i.can_be_purchased', 'can_be_purchased')
            ->where('i.can_be_inventory', 'can_be_inventory')
            ->order_by('i.sku_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function get_inventory_rules($owner_type, $owner_id)
    {
        if (!$owner_id) {
            return [];
        }

        return $this->db
            ->where('owner_type', $owner_type)
            ->where('owner_id', (int) $owner_id)
            ->where('active', 1)
            ->order_by('priority', 'DESC')
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'pos_inventory_rules')
            ->result_array();
    }

    private function _get_inventory_rules_map($owner_type, array $owner_ids)
    {
        $owner_ids = array_values(array_unique(array_filter(array_map('intval', $owner_ids))));
        if (empty($owner_ids)) {
            return [];
        }

        $rows = $this->db
            ->where('owner_type', $owner_type)
            ->where_in('owner_id', $owner_ids)
            ->where('active', 1)
            ->order_by('priority', 'DESC')
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'pos_inventory_rules')
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['owner_id']][] = $row;
        }

        return $map;
    }

    private function _normalize_inventory_rules($rules)
    {
        $normalized = [];
        if (!is_array($rules)) {
            return $normalized;
        }

        foreach ($rules as $i => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $action_type = in_array($rule['action_type'] ?? '', ['deduct', 'replace', 'remove'], true)
                ? $rule['action_type']
                : 'deduct';
            $inventory_item_id = !empty($rule['inventory_item_id']) ? (int) $rule['inventory_item_id'] : null;
            $quantity = isset($rule['quantity']) ? (float) $rule['quantity'] : 1;
            $role_key = trim((string) ($rule['role_key'] ?? ''));

            if ($action_type !== 'remove' && (!$inventory_item_id || $quantity <= 0)) {
                continue;
            }

            $normalized[] = [
                'role_key' => $role_key !== '' ? $role_key : null,
                'action_type' => $action_type,
                'inventory_item_id' => $action_type === 'remove' ? null : $inventory_item_id,
                'quantity' => $action_type === 'remove' ? 0 : round($quantity, 3),
                'priority' => isset($rule['priority']) ? (int) $rule['priority'] : 0,
                'sort_order' => isset($rule['sort_order']) ? (int) $rule['sort_order'] : $i,
                'active' => 1,
            ];
        }

        return $normalized;
    }

    public function save_inventory_rules($owner_type, $owner_id, $rules)
    {
        $owner_id = (int) $owner_id;
        if (!$owner_id) {
            return false;
        }

        $allowed_owner_types = ['product', 'modifier', 'item_modifier_option'];
        if (!in_array($owner_type, $allowed_owner_types, true)) {
            return false;
        }

        $rules = $this->_normalize_inventory_rules($rules);

        $this->db->where('owner_type', $owner_type)
            ->where('owner_id', $owner_id)
            ->delete(db_prefix() . 'pos_inventory_rules');

        foreach ($rules as $rule) {
            $rule['owner_type'] = $owner_type;
            $rule['owner_id'] = $owner_id;
            $rule['created_at'] = date('Y-m-d H:i:s');
            $rule['updated_at'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'pos_inventory_rules', $rule);
        }

        return true;
    }

    private function _resolve_line_modifier_refs(array $line_item)
    {
        $product_id = (int) ($line_item['item_id'] ?? 0);
        $refs = [];
        $fallback_modifier_ids = [];

        foreach (($line_item['selected_modifiers'] ?? []) as $modifier) {
            if (!is_array($modifier)) {
                continue;
            }

            $source_type = $modifier['source_type'] ?? $modifier['modifier_source_type'] ?? null;
            $owner_type = null;
            if ($source_type === 'item_modifier_option') {
                $owner_type = 'item_modifier_option';
            } elseif ($source_type === 'modifier') {
                $owner_type = 'modifier';
            }

            $owner_id = (int) ($modifier['id'] ?? $modifier['modifier_id'] ?? $modifier['option_id'] ?? 0);
            if ($owner_type && $owner_id) {
                $refs[$owner_type . ':' . $owner_id] = [
                    'owner_type' => $owner_type,
                    'owner_id' => $owner_id,
                ];
                continue;
            }

            if ($owner_id) {
                $fallback_modifier_ids[] = $owner_id;
            }
        }

        foreach (array_merge($line_item['modifier_ids'] ?? [], $fallback_modifier_ids) as $modifier_id) {
            $modifier_id = (int) $modifier_id;
            if (!$modifier_id || isset($refs['modifier:' . $modifier_id]) || isset($refs['item_modifier_option:' . $modifier_id])) {
                continue;
            }

            $shared_exists = $this->db->where('id', $modifier_id)->count_all_results(db_prefix() . 'modifiers') > 0;
            if ($shared_exists) {
                $refs['modifier:' . $modifier_id] = [
                    'owner_type' => 'modifier',
                    'owner_id' => $modifier_id,
                ];
                continue;
            }

            if ($product_id) {
                $option_exists = $this->db
                    ->from(db_prefix() . 'item_modifier_options imo')
                    ->join(db_prefix() . 'item_modifiers im', 'im.id = imo.item_modifier_id', 'inner')
                    ->where('imo.id', $modifier_id)
                    ->where('im.pos_item_id', (string) $product_id)
                    ->count_all_results() > 0;

                if ($option_exists) {
                    $refs['item_modifier_option:' . $modifier_id] = [
                        'owner_type' => 'item_modifier_option',
                        'owner_id' => $modifier_id,
                    ];
                }
            }
        }

        return array_values($refs);
    }

    private function _prepare_receipt_line_inventory_deductions($warehouse_id, array $line_item)
    {
        $warehouse_id = (int) $warehouse_id;
        $product_id = (int) ($line_item['item_id'] ?? 0);
        $line_qty = isset($line_item['quantity']) ? (float) $line_item['quantity'] : 1;

        if (!$warehouse_id || !$product_id || $line_qty <= 0) {
            return [];
        }

        $entries = [];
        foreach ($this->get_inventory_rules('product', $product_id) as $rule) {
            $entries[] = [
                'source_type' => 'product',
                'source_owner_id' => $product_id,
                'source_rule_id' => (int) $rule['id'],
                'role_key' => $rule['role_key'] ?: null,
                'action_type' => $rule['action_type'],
                'inventory_item_id' => $rule['inventory_item_id'] ? (int) $rule['inventory_item_id'] : null,
                'quantity' => round((float) $rule['quantity'] * $line_qty, 3),
                'priority' => (int) $rule['priority'],
                'note' => 'Product rule',
            ];
        }

        foreach ($this->_resolve_line_modifier_refs($line_item) as $ref) {
            foreach ($this->get_inventory_rules($ref['owner_type'], $ref['owner_id']) as $rule) {
                $entries[] = [
                    'source_type' => $ref['owner_type'],
                    'source_owner_id' => (int) $ref['owner_id'],
                    'source_rule_id' => (int) $rule['id'],
                    'role_key' => $rule['role_key'] ?: null,
                    'action_type' => $rule['action_type'],
                    'inventory_item_id' => $rule['inventory_item_id'] ? (int) $rule['inventory_item_id'] : null,
                    'quantity' => round((float) $rule['quantity'] * $line_qty, 3),
                    'priority' => (int) $rule['priority'],
                    'note' => $ref['owner_type'] === 'modifier' ? 'Modifier rule' : 'Item modifier rule',
                ];
            }
        }

        usort($entries, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return ($a['source_rule_id'] ?? 0) <=> ($b['source_rule_id'] ?? 0);
            }

            return $a['priority'] <=> $b['priority'];
        });

        $resolved = [];
        $roles = [];

        foreach ($entries as $entry) {
            if (!empty($entry['role_key'])) {
                if ($entry['action_type'] === 'remove') {
                    $roles[$entry['role_key']] = null;
                    continue;
                }

                $roles[$entry['role_key']] = $entry;
                continue;
            }

            if ($entry['action_type'] === 'deduct' && !empty($entry['inventory_item_id']) && $entry['quantity'] > 0) {
                $resolved[] = $entry;
            }
        }

        foreach ($roles as $entry) {
            if ($entry && !empty($entry['inventory_item_id']) && $entry['quantity'] > 0) {
                $resolved[] = $entry;
            }
        }

        return $resolved;
    }

    private function _get_inventory_stock_total($warehouse_id, $inventory_item_id)
    {
        $row = $this->db
            ->select('COALESCE(SUM(CAST(inventory_number AS DECIMAL(15,3))), 0) AS qty', false)
            ->where('warehouse_id', (int) $warehouse_id)
            ->where('commodity_id', (int) $inventory_item_id)
            ->get(db_prefix() . 'inventory_manage')
            ->row_array();

        return round((float) ($row['qty'] ?? 0), 3);
    }

    private function _deduct_inventory_stock($warehouse_id, $inventory_item_id, $quantity)
    {
        $quantity = round((float) $quantity, 3);
        if ($quantity <= 0) {
            return [];
        }

        $available = $this->_get_inventory_stock_total($warehouse_id, $inventory_item_id);
        if ($available < $quantity) {
            $item = $this->db->select('sku_name, sku_code')->where('id', (int) $inventory_item_id)->get(db_prefix() . 'items')->row_array();
            $label = trim(($item['sku_name'] ?? 'Inventory item') . (!empty($item['sku_code']) ? ' (' . $item['sku_code'] . ')' : ''));

            return $this->_set_inventory_error('Insufficient stock for ' . $label . ' at the selected warehouse.');
        }

        $rows = $this->db
            ->where('warehouse_id', (int) $warehouse_id)
            ->where('commodity_id', (int) $inventory_item_id)
            ->where('CAST(inventory_number AS DECIMAL(15,3)) >', 0, false)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'inventory_manage')
            ->result_array();

        $remaining = $quantity;
        $allocations = [];

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }

            $current = round((float) ($row['inventory_number'] ?? 0), 3);
            if ($current <= 0) {
                continue;
            }

            $take = min($current, $remaining);
            $this->db->where('id', (int) $row['id'])->update(db_prefix() . 'inventory_manage', [
                'inventory_number' => round($current - $take, 3),
            ]);

            $allocations[] = [
                'inventory_manage_id' => (int) $row['id'],
                'quantity' => round($take, 3),
            ];
            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0) {
            return $this->_set_inventory_error('Inventory deduction failed due to inconsistent stock records.');
        }

        return $allocations;
    }

    private function _restore_inventory_stock($warehouse_id, $inventory_item_id, $quantity, $inventory_manage_id = null)
    {
        $quantity = round((float) $quantity, 3);
        if ($quantity <= 0) {
            return true;
        }

        if ($inventory_manage_id) {
            $row = $this->db->where('id', (int) $inventory_manage_id)->get(db_prefix() . 'inventory_manage')->row_array();
            if ($row) {
                $this->db->where('id', (int) $inventory_manage_id)->update(db_prefix() . 'inventory_manage', [
                    'inventory_number' => round((float) ($row['inventory_number'] ?? 0) + $quantity, 3),
                ]);

                return true;
            }
        }

        $row = $this->db
            ->where('warehouse_id', (int) $warehouse_id)
            ->where('commodity_id', (int) $inventory_item_id)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'inventory_manage')
            ->row_array();

        if ($row) {
            $this->db->where('id', (int) $row['id'])->update(db_prefix() . 'inventory_manage', [
                'inventory_number' => round((float) ($row['inventory_number'] ?? 0) + $quantity, 3),
            ]);

            return true;
        }

        $this->db->insert(db_prefix() . 'inventory_manage', [
            'warehouse_id' => (int) $warehouse_id,
            'commodity_id' => (int) $inventory_item_id,
            'inventory_number' => $quantity,
        ]);

        return true;
    }

    private function _apply_receipt_line_inventory_deductions($receipt_id, $receipt_line_item_id, $warehouse_id, array $deductions)
    {
        foreach ($deductions as $deduction) {
            $allocations = $this->_deduct_inventory_stock($warehouse_id, (int) $deduction['inventory_item_id'], (float) $deduction['quantity']);
            if ($allocations === false) {
                return false;
            }

            foreach ($allocations as $allocation) {
                $this->db->insert(db_prefix() . 'pos_receipt_inventory_deductions', [
                    'receipt_id' => (int) $receipt_id,
                    'receipt_line_item_id' => (int) $receipt_line_item_id,
                    'warehouse_id' => (int) $warehouse_id,
                    'inventory_item_id' => (int) $deduction['inventory_item_id'],
                    'inventory_manage_id' => (int) ($allocation['inventory_manage_id'] ?? 0) ?: null,
                    'role_key' => $deduction['role_key'] ?? null,
                    'quantity' => (float) $allocation['quantity'],
                    'restored_quantity' => 0,
                    'source_type' => $deduction['source_type'],
                    'source_owner_id' => (int) $deduction['source_owner_id'],
                    'source_rule_id' => (int) ($deduction['source_rule_id'] ?? 0) ?: null,
                    'note' => $deduction['note'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return true;
    }

    public function restore_receipt_inventory_deductions($receipt_id, array $refund_items = [])
    {
        $receipt_id = (int) $receipt_id;
        if (!$receipt_id) {
            return false;
        }

        $ratio_map = [];
        if (!empty($refund_items)) {
            $line_item_ids = array_values(array_unique(array_map(function ($item) {
                return (int) ($item['line_item_id'] ?? 0);
            }, $refund_items)));
            $line_item_ids = array_values(array_filter($line_item_ids));
            if (empty($line_item_ids)) {
                return true;
            }

            $line_rows = $this->db->where_in('id', $line_item_ids)->get(db_prefix() . 'pos_receipt_line_items')->result_array();
            $line_map = array_column($line_rows, null, 'id');

            foreach ($refund_items as $item) {
                $line_item_id = (int) ($item['line_item_id'] ?? 0);
                if (!$line_item_id || empty($line_map[$line_item_id])) {
                    continue;
                }

                $original_qty = (float) ($line_map[$line_item_id]['quantity'] ?? 0);
                $refund_qty = (float) ($item['quantity'] ?? 0);
                if ($original_qty <= 0 || $refund_qty <= 0) {
                    continue;
                }

                $ratio_map[$line_item_id] = min(1, max(0, $refund_qty / $original_qty));
            }
        }

        $rows = $this->db
            ->where('receipt_id', $receipt_id)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_receipt_inventory_deductions')
            ->result_array();

        foreach ($rows as $row) {
            $ratio = empty($ratio_map) ? 1 : ($ratio_map[(int) $row['receipt_line_item_id']] ?? null);
            if ($ratio === null) {
                continue;
            }

            $target_restore = round((float) $row['quantity'] * $ratio, 3);
            $remaining = round((float) $row['quantity'] - (float) $row['restored_quantity'], 3);
            $restore_now = min($remaining, $target_restore);

            if ($restore_now <= 0) {
                continue;
            }

            $this->_restore_inventory_stock(
                (int) $row['warehouse_id'],
                (int) $row['inventory_item_id'],
                $restore_now,
                !empty($row['inventory_manage_id']) ? (int) $row['inventory_manage_id'] : null
            );

            $new_restored = round((float) $row['restored_quantity'] + $restore_now, 3);
            $this->db->where('id', (int) $row['id'])->update(db_prefix() . 'pos_receipt_inventory_deductions', [
                'restored_quantity' => $new_restored,
                'restored_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Cost / Profit Helpers
    // -------------------------------------------------------------------------

    public function get_latest_purchase_unit_price($item_id)
    {
        $item_id = (int) $item_id;
        if (!$item_id) {
            return 0.0;
        }

        $row = $this->db->select('unit_price')
            ->from(db_prefix() . 'pur_order_detail')
            ->where('item_code', $item_id)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        return $row ? (float) $row['unit_price'] : 0.0;
    }

    /**
     * Looks up whether $item_id is the derived output of a Yield Breakdown
     * (e.g. "Coconut Juice" derived from "Coconut Fruit"). An item can only be
     * the output of one source at a time (tblpos_item_yields.output_item_id is
     * UNIQUE), so this returns at most one row.
     */
    public function get_yield_source_for_item($item_id)
    {
        $item_id = (int)$item_id;
        if (!$item_id) {
            return null;
        }
        return $this->db->where('output_item_id', $item_id)
            ->get(db_prefix() . 'pos_item_yields')
            ->row_array() ?: null;
    }

    /**
     * Cost/unit for a Yield Breakdown output. When none of its sibling outputs (same
     * source_item_id) have a reference_price set, each output simply absorbs the
     * source's full cost (source_cost / quantity) — the original, simpler behavior.
     * Once at least one sibling has a reference_price, the source's cost is instead
     * split across all outputs by relative market value (quantity * reference_price),
     * so the allocated costs sum back to exactly the source's cost instead of each
     * output independently "costing" the whole source.
     */
    private function calc_yield_output_unit_cost($yield_source, $force_recalc = false, &$visited_stack = [])
    {
        $quantity = (float)($yield_source['quantity'] ?? 0);
        if ($quantity <= 0) {
            return 0.0;
        }

        $source_item_id = (int)$yield_source['source_item_id'];
        $source_cost = $this->get_item_unit_cost($source_item_id, $force_recalc, $visited_stack);

        $siblings = $this->db->select('output_item_id, quantity, reference_price')
            ->where('source_item_id', $source_item_id)
            ->get(db_prefix() . 'pos_item_yields')
            ->result_array();

        $total_market_value = 0.0;
        foreach ($siblings as $sibling) {
            $total_market_value += (float)($sibling['quantity'] ?? 0) * (float)($sibling['reference_price'] ?? 0);
        }

        if ($total_market_value > 0) {
            $this_market_value = $quantity * (float)($yield_source['reference_price'] ?? 0);
            return round(($source_cost * ($this_market_value / $total_market_value)) / $quantity, 4);
        }

        return round($source_cost / $quantity, 4);
    }

    public function get_item_unit_cost($item_id, $force_recalc = false, &$visited_stack = [])
    {
        $item_id = (int) $item_id;
        if (!$item_id) {
            return 0.0;
        }

        if (in_array($item_id, $visited_stack, true)) {
            return 0.0;
        }
        $visited_stack[] = $item_id;

        $item = $this->db->select('items.id, items.item_type, items.purchase_price, items.units_per_batch, items.cached_cost_per_unit, items.cached_cost_valid_until, items.unit_uom, items.can_be_manufacturing, items.can_be_sold, g.name AS category_name')
            ->from(db_prefix() . 'items items')
            ->join(db_prefix() . 'items_groups g', 'g.id = items.group_id', 'left')
            ->where('items.id', $item_id)
            ->get()
            ->row_array();

        if (!$item) {
            array_pop($visited_stack);
            return 0.0;
        }

        $item_type = $item['item_type'] ?? '';
        // Some items are categorized as Packaging (items_groups.name) without their
        // item_type column actually being set to 'packaging' — get_items_for_costing()
        // (the Packaging Cost tab) already treats either signal as "packaging"; mirror
        // that here so this function doesn't fall through to the finished_product/BOM
        // path (which returns 0 for an item that has no recipe of its own) for them.
        if ($item_type !== 'raw_ingredient' && ($item['category_name'] ?? '') === 'Packaging') {
            $item_type = 'packaging';
        } elseif ($item_type === 'finished_product') {
            // item_type has no UI/API to set it, so every item silently defaults to
            // 'finished_product' whether or not it actually has a recipe. Fall back to
            // the pre-existing Warehouse "can be manufactured / can be sold" checkboxes
            // (which the item create form does force the user to set) to tell a real
            // finished product apart from a purchased-only item that was never
            // reclassified, so this doesn't resolve to 0 via an empty BOM.
            $can_manufacture = ($item['can_be_manufacturing'] ?? '') === 'can_be_manufacturing';
            $can_sell = ($item['can_be_sold'] ?? '') === 'can_be_sold';
            if (!$can_manufacture) {
                $item_type = 'raw_ingredient';
            } elseif (!$can_sell) {
                $item_type = 'mixed_ingredient';
            }
        }
        $now = date('Y-m-d H:i:s');
        // raw_ingredient/packaging are always recomputed live from the latest purchase
        // order price (matching get_items_for_costing(), the Individual Ingredients /
        // Packaging Cost tabs) instead of trusting the cache, since purchase orders can
        // land outside this module without anything invalidating the cache.
        $cache_valid = !$force_recalc
            && isset($item['cached_cost_per_unit'])
            && $item['cached_cost_per_unit'] !== null
            && !in_array($item_type, ['raw_ingredient', 'packaging'], true)
            && (empty($item['cached_cost_valid_until']) || strtotime($item['cached_cost_valid_until']) > strtotime($now));

        if ($cache_valid && in_array($item_type, ['raw_ingredient', 'packaging', 'mixed_ingredient', 'finished_product', 'combo'], true)) {
            array_pop($visited_stack);
            return round((float) $item['cached_cost_per_unit'], 4);
        }

        $unit_cost = 0.0;

        switch ($item_type) {
            case 'raw_ingredient':
            case 'packaging':
                // A yield-breakdown output (e.g. "Coconut Juice" derived from "Coconut
                // Fruit") has no purchase price of its own — its cost is a fixed-ratio
                // share of its source item's cost, so resolve that instead of falling
                // through to the purchase-price math below.
                $yield_source = $this->get_yield_source_for_item($item_id);
                if ($yield_source && (float)($yield_source['quantity'] ?? 0) > 0) {
                    $unit_cost = $this->calc_yield_output_unit_cost($yield_source, $force_recalc, $visited_stack);
                } else {
                    // Matches the fallback rule used by get_items_for_costing() (the
                    // Individual Ingredients / Packaging Cost tabs): prefer the latest
                    // purchase order price over the manually-set purchase_price field.
                    $latest_purchase_price = $this->get_latest_purchase_unit_price($item_id);
                    $purchase_price = $latest_purchase_price > 0 ? $latest_purchase_price : (float) ($item['purchase_price'] ?? 0);
                    $units_per_batch = (float) ($item['units_per_batch'] ?? 0);
                    if ($units_per_batch > 0) {
                        $unit_cost = round($purchase_price / $units_per_batch, 4);
                    } else {
                        $unit_cost = round($purchase_price, 4);
                    }
                }
                $prev_cached = $item['cached_cost_per_unit'] !== null ? round((float) $item['cached_cost_per_unit'], 4) : null;
                if ($prev_cached === null || abs($prev_cached - $unit_cost) > 0.00005) {
                    $this->db->where('id', $item_id)->update(db_prefix() . 'items', [
                        'cached_cost_per_unit' => $unit_cost,
                    ]);
                }
                break;

            case 'mixed_ingredient':
                $unit_cost = $this->calc_mixed_ingredient_cost($item_id, $visited_stack);
                break;

            case 'finished_product':
                $unit_cost = $this->calc_product_cost($item_id, null, $visited_stack);
                break;

            case 'combo':
                $unit_cost = $this->calc_combo_cost($item_id, $visited_stack);
                break;

            default:
                $unit_cost = round((float) ($item['cached_cost_per_unit'] ?? $item['purchase_price'] ?? 0), 4);
                break;
        }

        array_pop($visited_stack);
        return round((float) $unit_cost, 4);
    }

    /**
     * Cascades a cost change outward from $item_id to every mixed ingredient,
     * product, and combo that consumes it (directly or via nested mixed
     * ingredients), recalculating and re-caching each one so the Mixed
     * Ingredients / Product Cost Profit tabs never show stale numbers after an
     * Individual Ingredient's cost changes.
     */
    public function propagate_cost_change($item_id, array $visited = [])
    {
        $item_id = (int) $item_id;
        if (!$item_id || in_array($item_id, $visited, true)) {
            return;
        }
        $visited[] = $item_id;

        // Yield-breakdown outputs (e.g. "Coconut Juice" derived from "Coconut Fruit")
        // recompute their own cost from the source's, so a source price change has to
        // cascade into each output before continuing on to whatever consumes them.
        $yieldRows = $this->db->where('source_item_id', $item_id)
            ->get(db_prefix() . 'pos_item_yields')
            ->result_array();
        foreach ($yieldRows as $row) {
            $outputId = (int) $row['output_item_id'];
            $calcVisited = [];
            $this->get_item_unit_cost($outputId, true, $calcVisited);
            $this->propagate_cost_change($outputId, $visited);
        }

        $mixedRows = $this->db->select('DISTINCT mixed_ingredient_id', false)
            ->where('component_item_id', $item_id)
            ->get(db_prefix() . 'pos_mixed_ingredient_components')
            ->result_array();
        foreach ($mixedRows as $row) {
            $mixedId = (int) $row['mixed_ingredient_id'];
            $calcVisited = [];
            $this->calc_mixed_ingredient_cost($mixedId, $calcVisited);
            $mixedItem = $this->db->select('item_id')->where('id', $mixedId)->get(db_prefix() . 'pos_mixed_ingredients')->row_array();
            if ($mixedItem && !empty($mixedItem['item_id'])) {
                $this->propagate_cost_change((int) $mixedItem['item_id'], $visited);
            }
        }

        $productRows = $this->db->select('DISTINCT product_item_id, variant_id', false)
            ->where('component_item_id', $item_id)
            ->get(db_prefix() . 'pos_product_bom')
            ->result_array();
        foreach ($productRows as $row) {
            $productId = (int) $row['product_item_id'];
            $variantId = $row['variant_id'] !== null ? (int) $row['variant_id'] : null;
            $calcVisited = [];
            $this->calc_product_cost($productId, $variantId, $calcVisited);
            $this->propagate_cost_change($productId, $visited);
        }

        if ($this->db->table_exists(db_prefix() . 'pos_combo_components')) {
            $comboRows = $this->db->select('DISTINCT combo_item_id', false)
                ->where('component_product_id', $item_id)
                ->get(db_prefix() . 'pos_combo_components')
                ->result_array();
            foreach ($comboRows as $row) {
                $comboId = (int) $row['combo_item_id'];
                $calcVisited = [];
                $this->calc_combo_cost($comboId, $calcVisited);
                $this->propagate_cost_change($comboId, $visited);
            }
        }
    }

    public function calc_mixed_ingredient_cost($mixed_ingredient_id, &$visited = [])
    {
        $mixed_ingredient_id = (int) $mixed_ingredient_id;
        if (!$mixed_ingredient_id) {
            return 0.0;
        }

        $mixed = $this->db->where('id', $mixed_ingredient_id)
            ->get(db_prefix() . 'pos_mixed_ingredients')
            ->row_array();

        if (!$mixed) {
            return 0.0;
        }

        $total_batch_yield = (float) ($mixed['total_batches_yield'] ?? 0);
        if ($total_batch_yield <= 0) {
            $total_batch_yield = 1;
        }

        $components = $this->db->where('mixed_ingredient_id', $mixed_ingredient_id)
            ->get(db_prefix() . 'pos_mixed_ingredient_components')
            ->result_array();

        $total_batch_cost = 0.0;
        $mixed_item = $this->db->select('unit_uom')->where('id', (int) ($mixed['item_id'] ?? 0))->get(db_prefix() . 'items')->row_array();
        $mixed_uom = $mixed_item['unit_uom'] ?? null;

        foreach ($components as $comp) {
            $comp_item_id = (int) ($comp['component_item_id'] ?? 0);
            $qty = (float) ($comp['quantity'] ?? 0);
            $comp_uom = $comp['uom'] ?? null;

            if (!$comp_item_id || $qty <= 0) {
                continue;
            }

            $comp_cost = $this->get_item_unit_cost($comp_item_id, false, $visited);

            if ($comp_uom && $mixed_uom && $comp_uom !== $mixed_uom) {
            }

            $total_batch_cost += round($comp_cost * $qty, 4);
        }

        $per_unit_cost = round($total_batch_cost / $total_batch_yield, 4);

        $item_id = (int) ($mixed['item_id'] ?? 0);
        if ($item_id) {
            $this->db->where('id', $item_id)->update(db_prefix() . 'items', [
                'cached_cost_per_unit' => $per_unit_cost,
            ]);
        }

        return $per_unit_cost;
    }

    /**
     * Resolves a BOM row set to a min/max cost range.
     *
     * Rows with no `group_key` always contribute their line cost. Rows
     * sharing a `group_key` are mutually exclusive alternatives: if any row
     * in the group has `requires_modifier_id` set, which option applies
     * depends on what the customer picks at POS and can't be known here, so
     * the group contributes its cheapest row's cost to `min` and its
     * priciest row's cost to `max` (this is what makes `is_range` true). If
     * no row in the group is modifier-gated, the group is deterministic and
     * just uses its first row (both `min` and `max`) as the fixed pick.
     */
    public function resolve_bom_cost_range(array $rows)
    {
        $lineCost = [];
        foreach ($rows as $key => $row) {
            $cid = (int) ($row['component_item_id'] ?? 0);
            $qty = (float) ($row['quantity_per_serving'] ?? $row['quantity'] ?? 0);
            $lineCost[$key] = ($cid > 0 && $qty > 0)
                ? round($this->get_item_unit_cost($cid, false) * $qty, 4)
                : 0.0;
        }

        $groups = [];
        $min = 0.0;
        $max = 0.0;

        foreach ($rows as $key => $row) {
            $groupKey = trim((string) ($row['group_key'] ?? ''));
            if ($groupKey === '') {
                $min += $lineCost[$key];
                $max += $lineCost[$key];
                continue;
            }
            $groups[$groupKey][] = $key;
        }

        $isRange = false;
        foreach ($groups as $groupKeys) {
            $conditional = [];
            $defaults = [];
            foreach ($groupKeys as $key) {
                $hasConditions = trim((string) ($rows[$key]['requires_conditions'] ?? '')) !== ''
                    || (int) ($rows[$key]['requires_modifier_id'] ?? 0) > 0;
                if ($hasConditions) {
                    $conditional[] = $key;
                } else {
                    $defaults[] = $key;
                }
            }

            if (!empty($conditional)) {
                $isRange = true;
                $groupCosts = array_map(function ($key) use ($lineCost) {
                    return $lineCost[$key];
                }, $groupKeys);
                $min += min($groupCosts);
                $max += max($groupCosts);
            } else {
                $pick = $defaults[0] ?? $groupKeys[0];
                $min += $lineCost[$pick];
                $max += $lineCost[$pick];
            }
        }

        return [
            'min'      => round($min, 4),
            'max'      => round($max, 4),
            'is_range' => $isRange,
        ];
    }

    /**
     * Flat list of the modifier options assignable to this product — shared
     * modifier groups assigned via item_modifier_groups, plus the product's
     * own individual (item_modifiers) options. Used to populate the "Requires"
     * condition dropdown in the Product Cost Profit dialog.
     */
    public function get_product_condition_options($item_id)
    {
        $item_id = (string) (int) $item_id;
        $options = [];

        $assignedGroups = $this->db
            ->select('img.modifier_group_id, mg.name')
            ->from(db_prefix() . 'item_modifier_groups img')
            ->join(db_prefix() . 'modifier_groups mg', 'mg.id = img.modifier_group_id')
            ->where('img.pos_item_id', $item_id)
            ->where('mg.active', 1)
            ->order_by('img.sort_order', 'ASC')
            ->get()->result_array();

        if (!empty($assignedGroups)) {
            $groupIds = array_column($assignedGroups, 'modifier_group_id');
            $groupNames = array_column($assignedGroups, 'name', 'modifier_group_id');
            $mods = $this->db
                ->where_in('modifier_group_id', $groupIds)
                ->where('active', 1)
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
            foreach ($mods as $m) {
                $groupName = $groupNames[$m['modifier_group_id']] ?? '';
                $options[] = [
                    'type'        => 'modifier',
                    'id'          => (int) $m['id'],
                    'group_name'  => $groupName,
                    'option_name' => $m['name'],
                    'label'       => $groupName . ': ' . $m['name'],
                ];
            }
        }

        $itemModifiers = $this->db
            ->where('pos_item_id', $item_id)
            ->where('active', 1)
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'item_modifiers')->result_array();

        if (!empty($itemModifiers)) {
            $imIds = array_column($itemModifiers, 'id');
            $imNames = array_column($itemModifiers, 'name', 'id');
            $imOpts = $this->db
                ->where_in('item_modifier_id', $imIds)
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'item_modifier_options')->result_array();
            foreach ($imOpts as $o) {
                $groupName = $imNames[$o['item_modifier_id']] ?? '';
                $options[] = [
                    'type'        => 'item_modifier_option',
                    'id'          => (int) $o['id'],
                    'group_name'  => $groupName,
                    'option_name' => $o['name'],
                    'label'       => $groupName . ': ' . $o['name'],
                ];
            }
        }

        return $options;
    }

    public function calc_product_cost($product_item_id, $variant_id = null, &$visited = [])
    {
        $product_item_id = (int) $product_item_id;
        if (!$product_item_id) {
            return 0.0;
        }

        $this->db->where('product_item_id', $product_item_id);
        if ($variant_id !== null) {
            $variant_id = (int) $variant_id;
            $this->db->group_start()
                ->where('variant_id IS NULL', null, false)
                ->or_where('variant_id', $variant_id)
                ->group_end();
        } else {
            $this->db->where('variant_id IS NULL', null, false);
        }

        $bom_rows = $this->db->get(db_prefix() . 'pos_product_bom')->result_array();
        // Uses the top of the range (worst case) as the single cached cost value
        // consumed elsewhere (combos, other products nesting this one, etc.).
        $total_unit_cost = $this->resolve_bom_cost_range($bom_rows)['max'];

        $this->db->where('id', $product_item_id)->update(db_prefix() . 'items', [
            'cached_cost_per_unit' => $total_unit_cost,
        ]);

        return $total_unit_cost;
    }

    public function calc_combo_cost($combo_item_id, &$visited = [])
    {
        $combo_item_id = (int) $combo_item_id;
        if (!$combo_item_id) {
            return 0.0;
        }

        $components = $this->db->where('combo_item_id', $combo_item_id)
            ->get(db_prefix() . 'pos_combo_components')
            ->result_array();

        $total = 0.0;
        foreach ($components as $comp) {
            $comp_product_id = (int) ($comp['component_product_id'] ?? 0);
            $qty = (float) ($comp['quantity'] ?? 0);
            if (!$comp_product_id || $qty <= 0) {
                continue;
            }
            $comp_cost = $this->get_item_unit_cost($comp_product_id, false, $visited);
            $total += round($comp_cost * $qty, 4);
        }

        $total = round($total, 4);

        $this->db->where('id', $combo_item_id)->update(db_prefix() . 'items', [
            'cached_cost_per_unit' => $total,
        ]);

        return $total;
    }

    public function recalculate_all_costs(array $options = [])
    {
        $raw_count = 0;
        $mixed_count = 0;
        $product_count = 0;
        $combo_count = 0;
        $created_snapshot_id = null;

        $this->db->update(db_prefix() . 'items', [
            'cached_cost_valid_until' => null,
        ]);

        $mixed_items = $this->db->select('i.id')
            ->from(db_prefix() . 'items i')
            ->where('i.item_type', 'mixed_ingredient')
            ->order_by('i.id', 'ASC')
            ->get()
            ->result_array();

        $processed_item_costs = [];
        $visited = [];

        foreach ($mixed_items as $mi) {
            $item_id = (int) $mi['id'];
            $has_row = $this->db->where('item_id', $item_id)
                ->count_all_results(db_prefix() . 'pos_mixed_ingredients') > 0;
            if ($has_row) {
                $cost = $this->calc_mixed_ingredient_cost($item_id, $visited);
                $processed_item_costs[] = ['item_id' => $item_id, 'variant_id' => null, 'cost' => $cost, 'type' => 'mixed_ingredient'];
                $mixed_count++;
            }
        }

        $product_items = $this->db->select('i.id, i.rate')
            ->from(db_prefix() . 'items i')
            ->where('i.item_type', 'finished_product')
            ->order_by('i.id', 'ASC')
            ->get()
            ->result_array();

        $variants_table_exists = $this->db->table_exists(db_prefix() . 'pos_item_variants');

        foreach ($product_items as $pi) {
            $item_id = (int) $pi['id'];
            $cost = $this->calc_product_cost($item_id, null, $visited);
            $processed_item_costs[] = ['item_id' => $item_id, 'variant_id' => null, 'cost' => $cost, 'rate' => (float) ($pi['rate'] ?? 0), 'type' => 'finished_product'];
            $product_count++;

            if ($variants_table_exists) {
                $variants = $this->db->select('id, rate')->where('parent_id', $item_id)->get(db_prefix() . 'items')->result_array();
                foreach ($variants as $v) {
                    $v_id = (int) $v['id'];
                    $v_cost = $this->calc_product_cost($item_id, $v_id, $visited);
                    $processed_item_costs[] = ['item_id' => $item_id, 'variant_id' => $v_id, 'cost' => $v_cost, 'rate' => (float) ($v['rate'] ?? 0), 'type' => 'finished_product'];
                }
            }
        }

        $combo_items = $this->db->select('i.id, i.rate')
            ->from(db_prefix() . 'items i')
            ->where('i.item_type', 'combo')
            ->order_by('i.id', 'ASC')
            ->get()
            ->result_array();

        foreach ($combo_items as $ci) {
            $item_id = (int) $ci['id'];
            $cost = $this->calc_combo_cost($item_id, $visited);
            $processed_item_costs[] = ['item_id' => $item_id, 'variant_id' => null, 'cost' => $cost, 'rate' => (float) ($ci['rate'] ?? 0), 'type' => 'combo'];
            $combo_count++;
        }

        $raw_items = $this->db->select('i.id, i.rate, i.item_type, i.purchase_price, i.units_per_batch, i.cached_cost_per_unit')
            ->from(db_prefix() . 'items i')
            ->where_in('i.item_type', ['raw_ingredient', 'packaging'])
            ->order_by('i.id', 'ASC')
            ->get()
            ->result_array();

        foreach ($raw_items as $ri) {
            $item_id = (int) $ri['id'];
            $purchase_price = (float) ($ri['purchase_price'] ?? 0);
            $units_per_batch = (float) ($ri['units_per_batch'] ?? 0);
            if ($units_per_batch > 0) {
                $unit_cost = round($purchase_price / $units_per_batch, 4);
            } else {
                $unit_cost = round($purchase_price, 4);
            }
            $this->db->where('id', $item_id)->update(db_prefix() . 'items', [
                'cached_cost_per_unit' => $unit_cost,
            ]);
            $processed_item_costs[] = ['item_id' => $item_id, 'variant_id' => null, 'cost' => $unit_cost, 'rate' => (float) ($ri['rate'] ?? 0), 'type' => (string) ($ri['item_type'] ?? 'raw_ingredient')];
            $raw_count++;
        }

        if (!empty($options['create_snapshot'])) {
            $this->db->trans_start();

            $snapshot_name = trim((string) ($options['snapshot_name'] ?? '')) ?: ('Recalc ' . date('Y-m-d H:i:s'));

            $this->db->insert(db_prefix() . 'pos_cost_snapshots', [
                'snapshot_date' => date('Y-m-d'),
                'name' => $snapshot_name,
                'created_by_staff_id' => function_exists('get_staff_user_id') ? (get_staff_user_id() ?: null) : null,
                'notes' => $options['notes'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $created_snapshot_id = $this->db->insert_id();

            if ($created_snapshot_id) {
                foreach ($processed_item_costs as $entry) {
                    $rate = (float) ($entry['rate'] ?? 0);
                    $cost = (float) ($entry['cost'] ?? 0);

                    $item_row = $this->db->select('rate')->where('id', (int) $entry['item_id'])->get(db_prefix() . 'items')->row_array();
                    $selling_rate = $rate > 0 ? $rate : (float) ($item_row['rate'] ?? 0);
                    $profit = $selling_rate > 0 ? round($selling_rate - $cost, 4) : 0;
                    $margin_pct = $selling_rate > 0 ? round(($profit / $selling_rate) * 100, 2) : 0;

                    $this->db->insert(db_prefix() . 'pos_cost_snapshot_values', [
                        'snapshot_id' => $created_snapshot_id,
                        'item_id' => (int) $entry['item_id'],
                        'variant_id' => $entry['variant_id'] ? (int) $entry['variant_id'] : null,
                        'cost_type' => (string) ($entry['type'] ?? 'raw_ingredient'),
                        'cost_per_unit' => $cost,
                        'selling_price' => $selling_rate,
                        'profit_per_unit' => $profit,
                        'margin_pct' => $margin_pct,
                    ]);
                }
            }

            $this->db->trans_complete();
            if ($this->db->trans_status() === false) {
                $created_snapshot_id = null;
            }
        }

        return [
            'raw_count' => $raw_count,
            'mixed_count' => $mixed_count,
            'product_count' => $product_count,
            'combo_count' => $combo_count,
            'snapshot_id' => $created_snapshot_id ? (int) $created_snapshot_id : null,
        ];
    }

    public function get_profit_report_summary($date_from, $date_to, $warehouse_id = null, $category_id = null, $product_search = null, $group_by = 'daily')
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $cat = $category_id ? 'AND li.category_id = ' . (int) $category_id : '';
        $search = '';
        if ($product_search) {
            $search = 'AND (li.item_name LIKE ' . $this->db->escape('%' . $product_search . '%')
                . ' OR li.item_id IN (SELECT id FROM ' . db_prefix() . 'items WHERE sku_name LIKE ' . $this->db->escape('%' . $product_search . '%') . '))';
        }

        $by_product = $this->db->query("
            SELECT
                li.item_id,
                li.item_name,
                li.category_id,
                li.category_name,
                COALESCE(SUM(li.quantity), 0)                                           AS qty_sold,
                COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)      AS total_revenue,
                COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0)                   AS total_cost,
                (COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                    - COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0))             AS gross_profit,
                CASE
                    WHEN COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0) > 0
                    THEN ROUND(
                        ((COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                          - COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0))
                         / COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                        ) * 100, 2)
                    ELSE 0
                END                                                                     AS margin_pct,
                CASE
                    WHEN COALESCE(SUM(li.quantity), 0) > 0
                    THEN ROUND(COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0) / SUM(li.quantity), 4)
                    ELSE 0
                END                                                                     AS avg_unit_price,
                CASE
                    WHEN COALESCE(SUM(li.quantity), 0) > 0
                    THEN ROUND(COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0) / SUM(li.quantity), 4)
                    ELSE 0
                END                                                                     AS avg_cost_unit
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE'
              AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ?
              $wh $cat $search
            GROUP BY li.item_id, li.item_name, li.category_id, li.category_name
            ORDER BY total_revenue DESC
        ", [$from, $to])->result_array();

        $by_category = $this->db->query("
            SELECT
                li.category_id,
                li.category_name,
                COALESCE(SUM(li.quantity), 0)                                           AS qty_sold,
                COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)      AS total_revenue,
                COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0)                   AS total_cost,
                (COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                    - COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0))             AS gross_profit,
                CASE
                    WHEN COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0) > 0
                    THEN ROUND(
                        ((COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                          - COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0))
                         / COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                        ) * 100, 2)
                    ELSE 0
                END                                                                     AS margin_pct
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE'
              AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ?
              $wh $cat $search
            GROUP BY li.category_id, li.category_name
            ORDER BY total_revenue DESC
        ", [$from, $to])->result_array();

        $receipt_count = (int) $this->db->query("
            SELECT COUNT(DISTINCT r.id) AS cnt
            FROM `" . db_prefix() . "pos_receipts` r
            WHERE r.receipt_type = 'SALE'
              AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ?
              $wh
        ", [$from, $to])->row()->cnt;

        $grand_row = $this->db->query("
            SELECT
                COALESCE(SUM(li.quantity), 0)                                           AS qty_sold,
                COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)      AS total_revenue,
                COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0)                   AS total_cost,
                (COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                    - COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0))             AS gross_profit,
                CASE
                    WHEN COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0) > 0
                    THEN ROUND(
                        ((COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                          - COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0))
                         / COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                        ) * 100, 2)
                    ELSE 0
                END                                                                     AS margin_pct,
                CASE
                    WHEN COALESCE(SUM(li.quantity), 0) > 0
                    THEN ROUND(COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0) / SUM(li.quantity), 4)
                    ELSE 0
                END                                                                     AS avg_unit_price,
                CASE
                    WHEN COALESCE(SUM(li.quantity), 0) > 0
                    THEN ROUND(COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0) / SUM(li.quantity), 4)
                    ELSE 0
                END                                                                     AS avg_cost_unit
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE'
              AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ?
              $wh $cat $search
        ", [$from, $to])->row_array();

        $grand_totals = $grand_row ?: [
            'qty_sold' => 0,
            'total_revenue' => 0,
            'total_cost' => 0,
            'gross_profit' => 0,
            'margin_pct' => 0,
            'avg_unit_price' => 0,
            'avg_cost_unit' => 0,
        ];

        $label_expr = [
            'daily' => "DATE(r.receipt_date)",
            'weekly' => "DATE(DATE_SUB(r.receipt_date, INTERVAL WEEKDAY(r.receipt_date) DAY))",
            'monthly' => "DATE_FORMAT(r.receipt_date, '%Y-%m')",
            'hourly' => "DATE_FORMAT(r.receipt_date, '%H:00')",
            'dow' => "DAYNAME(r.receipt_date)",
        ];
        $lbl = $label_expr[$group_by] ?? $label_expr['daily'];

        $product_trend_all = $this->db->query("
            SELECT
                $lbl                                                                    AS label,
                li.item_id,
                li.item_name,
                COALESCE(SUM(li.quantity), 0)                                           AS qty_sold,
                COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)      AS total_revenue,
                COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0)                   AS total_cost,
                (COALESCE(SUM(li.gross_total - COALESCE(li.total_discount, 0)), 0)
                    - COALESCE(SUM(COALESCE(li.cost, 0) * li.quantity), 0))             AS gross_profit
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE'
              AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ?
              $wh $cat $search
            GROUP BY $lbl, li.item_id, li.item_name
            ORDER BY $lbl ASC, total_revenue DESC
        ", [$from, $to])->result_array();

        return [
            'by_product' => $by_product,
            'by_category' => $by_category,
            'product_trend_all' => $product_trend_all,
            'grand_totals' => $grand_totals,
            'receipt_count' => $receipt_count,
        ];
    }

    // -------------------------------------------------------------------------
    // Auth / Sessions
    // -------------------------------------------------------------------------

    public function verify_api_token($token)
    {
        return $this->db
            ->select('t.*, w.warehouse_name, w.warehouse_address, w.warehouse_code')
            ->from(db_prefix() . 'pos_api_tokens t')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = t.warehouse_id', 'left')
            ->where('t.token', $token)
            ->where('t.active', 1)
            ->get()->row();
    }

    public function get_tokens_for_staff($staff_id)
    {
        return $this->db
            ->select('t.token, t.name, w.warehouse_id, w.warehouse_name, w.warehouse_address, w.warehouse_code')
            ->from(db_prefix() . 'pos_api_tokens t')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = t.warehouse_id', 'left')
            ->where('t.staff_id', $staff_id)
            ->where('t.active', 1)
            ->where('w.display', 1)
            ->get()->result_array();
    }

    public function create_session($staff_id, $expire_days = 30)
    {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expire_days} days"));

        $this->db->insert(db_prefix() . 'pos_sessions', [
            'staff_id' => $staff_id,
            'token' => $token,
            'expires_at' => $expires_at,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id() ? $token : false;
    }

    public function verify_session($token)
    {
        $row = $this->db
            ->where('token', $token)
            ->where('(expires_at IS NULL OR expires_at > NOW())', null, false)
            ->get(db_prefix() . 'pos_sessions')
            ->row();

        if (!$row) {
            return false;
        }

        $this->db->where('token', $token)->update(db_prefix() . 'pos_sessions', [
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);

        return $row;
    }

    public function delete_session($token)
    {
        $this->db->where('token', $token)->delete(db_prefix() . 'pos_sessions');
        return $this->db->affected_rows() > 0;
    }

    // -------------------------------------------------------------------------
    // Warehouses / Categories / Employees / Modifiers / Payment types
    // -------------------------------------------------------------------------

    public function get_stores()
    {
        return $this->db
            ->select('warehouse_id as id, warehouse_name as name, warehouse_address as address, warehouse_code as code, note as description')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')
            ->result_array();
    }

    public function get_store($id)
    {
        return $this->db
            ->select('warehouse_id as id, warehouse_name as name, warehouse_address as address, warehouse_code as code, note as description')
            ->where('warehouse_id', $id)
            ->where('display', 1)
            ->get(db_prefix() . 'warehouse')
            ->row_array();
    }

    public function get_categories()
    {
        return $this->db->where('deleted_at IS NULL')->order_by('name', 'ASC')->get(db_prefix() . 'pos_categories')->result_array();
    }

    public function get_employees()
    {
        return $this->db->where('deleted_at IS NULL')->get(db_prefix() . 'pos_employees')->result_array();
    }

    public function get_employee_by_pin($pin, $warehouse_id = null)
    {
        $this->db->where('pin', $pin)->where('deleted_at IS NULL');
        $employee = $this->db->get(db_prefix() . 'pos_employees')->row_array();
        if (!$employee)
            return false;
        if ($warehouse_id) {
            $warehouse_ids = json_decode($employee['warehouse_ids'] ?? '[]', true);
            if (!in_array((int) $warehouse_id, $warehouse_ids))
                return false;
        }
        unset($employee['pin']);
        return $employee;
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function get_dashboard_summary($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND warehouse_id = ' . (int) $warehouse_id : '';

        $row = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_money    ELSE 0 END), 0) AS total_sales,
                COALESCE(SUM(CASE WHEN receipt_type='REFUND' AND cancelled_at IS NULL THEN total_money    ELSE 0 END), 0) AS total_refunds,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_discount ELSE 0 END), 0) AS total_discounts,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_tax      ELSE 0 END), 0) AS total_tax,
                COALESCE(COUNT(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN 1 END), 0)  AS transaction_count,
                COALESCE(COUNT(CASE WHEN receipt_type='REFUND' AND cancelled_at IS NULL THEN 1 END), 0)  AS refund_count,
                COALESCE(COUNT(CASE WHEN cancelled_at IS NOT NULL THEN 1 END), 0)                        AS cancelled_count
            FROM `" . db_prefix() . "pos_receipts`
            WHERE receipt_date BETWEEN ? AND ? $wh
        ", [$from, $to])->row_array();

        $row['net_sales'] = round((float) $row['total_sales'] - (float) $row['total_refunds'], 2);
        $row['avg_transaction'] = $row['transaction_count'] > 0
            ? round((float) $row['total_sales'] / (int) $row['transaction_count'], 2)
            : 0;
        $total_txn = (int) $row['transaction_count'] + (int) $row['refund_count'];
        $row['refund_rate'] = $total_txn > 0
            ? round((float) $row['refund_count'] / $total_txn * 100, 1)
            : 0;
        return $row;
    }

    public function get_dashboard_daily_trend($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT DATE(receipt_date) AS date,
                   COALESCE(SUM(total_money), 0) AS revenue,
                   COUNT(*) AS transactions
            FROM `" . db_prefix() . "pos_receipts`
            WHERE receipt_type = 'SALE' AND cancelled_at IS NULL
              AND receipt_date BETWEEN ? AND ? $wh
            GROUP BY DATE(receipt_date)
            ORDER BY date ASC
        ", [$from, $to])->result_array();
    }

    public function get_dashboard_hourly($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT HOUR(receipt_date) AS hour,
                   COALESCE(SUM(total_money), 0) AS revenue,
                   COUNT(*) AS transactions
            FROM `" . db_prefix() . "pos_receipts`
            WHERE receipt_type = 'SALE' AND cancelled_at IS NULL
              AND receipt_date BETWEEN ? AND ? $wh
            GROUP BY HOUR(receipt_date)
            ORDER BY hour ASC
        ", [$from, $to])->result_array();
    }

    public function get_dashboard_top_products($date_from, $date_to, $warehouse_id = null, $limit = 10)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT li.item_name,
                   COALESCE(SUM(li.quantity), 0)    AS qty_sold,
                   COALESCE(SUM(li.total_money), 0) AS revenue
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY li.item_id, li.item_name
            ORDER BY revenue DESC
            LIMIT " . (int) $limit
            ,
            [$from, $to]
        )->result_array();
    }

    public function get_dashboard_payments($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT rp.payment_name                        AS payment_method,
                   COALESCE(SUM(rp.money_amount), 0)     AS amount,
                   COUNT(DISTINCT r.id)                  AS transaction_count
            FROM `" . db_prefix() . "pos_receipt_payments` rp
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = rp.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY rp.payment_type_id, rp.payment_name
            ORDER BY amount DESC
        ", [$from, $to])->result_array();
    }

    public function get_shifts($filters = [])
    {
        $warehouse_id = $filters['warehouse_id'] ?? null;
        $status = $filters['status'] ?? '';
        $date_from = $filters['date_from'] ?? null;
        $date_to = $filters['date_to'] ?? null;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $allowed_sort = [
            'opened_at' => 's.opened_at',
            'closed_at' => 's.closed_at',
            'total_sales' => 's.total_sales',
            'opening_float' => 's.opening_float',
            'expected_cash' => 's.expected_cash',
            'actual_cash' => 's.actual_cash',
            'difference' => 's.difference',
            'transaction_count' => 's.transaction_count',
        ];
        $sort_col = $allowed_sort[$filters['sort'] ?? ''] ?? 's.opened_at';
        $sort_dir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $this->db->select('s.*, w.warehouse_name')
            ->from(db_prefix() . 'pos_shifts s')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = s.warehouse_id', 'left')
            ->order_by($sort_col, $sort_dir);

        if ($warehouse_id)
            $this->db->where('s.warehouse_id', (int) $warehouse_id);
        if ($status)
            $this->db->where('s.status', $status);
        if ($date_from)
            $this->db->where('s.opened_at >=', $date_from . ' 00:00:00');
        if ($date_to)
            $this->db->where('s.opened_at <=', $date_to . ' 23:59:59');

        $total = $this->db->count_all_results('', false);

        $rows = $this->db->limit($limit, $offset)->get()->result_array();

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'page_count' => max(1, (int) ceil($total / $limit)),
        ];
    }

    public function get_dashboard_recent_shifts($warehouse_id = null, $limit = 8, $date_from = null, $date_to = null)
    {
        $this->db->select('s.*, w.warehouse_name, e1.name AS opened_by_name, e2.name AS closed_by_name')
            ->from(db_prefix() . 'pos_shifts s')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = s.warehouse_id', 'left')
            ->join(db_prefix() . 'pos_employees e1', 'e1.id = s.employee_id', 'left')
            ->join(db_prefix() . 'pos_employees e2', 'e2.id = s.closed_by_employee_id', 'left')
            ->order_by('s.opened_at', 'DESC')
            ->limit($limit);
        if ($warehouse_id) {
            $this->db->where('s.warehouse_id', (int) $warehouse_id);
        }
        if ($date_from) {
            $this->db->where('s.opened_at >=', $date_from . ' 00:00:00');
        }
        if ($date_to) {
            $this->db->where('s.opened_at <=', $date_to . ' 23:59:59');
        }
        return $this->db->get()->result_array();
    }

    public function get_modifiers($warehouse_id = null)
    {
        $this->db->order_by('name', 'ASC');
        if ($warehouse_id) {
            $this->db->where('(NOT EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_modifier_group_warehouses` mgw WHERE mgw.modifier_group_id = `' . db_prefix() . 'modifier_groups`.id)
                OR EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_modifier_group_warehouses` mgw WHERE mgw.modifier_group_id = `' . db_prefix() . 'modifier_groups`.id AND mgw.warehouse_id = ' . (int) $warehouse_id . '))', null, false);
        }
        $groups = $this->db->get(db_prefix() . 'modifier_groups')->result_array();
        foreach ($groups as &$group) {
            $group['modifiers'] = $this->db
                ->where('modifier_group_id', $group['id'])
                ->where('active', 1)
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
            foreach ($group['modifiers'] as &$modifier) {
                $modifier['source_type'] = 'modifier';
            }
            unset($modifier);
            $group['warehouse_ids'] = $this->get_modifier_group_warehouses($group['id']);
        }
        return $groups;
    }

    public function get_modifier_groups($warehouse_id = null)
    {
        $this->db->order_by('name', 'ASC');
        if ($warehouse_id) {
            $this->db->where('(NOT EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_modifier_group_warehouses` mgw WHERE mgw.modifier_group_id = `' . db_prefix() . 'modifier_groups`.id)
                OR EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_modifier_group_warehouses` mgw WHERE mgw.modifier_group_id = `' . db_prefix() . 'modifier_groups`.id AND mgw.warehouse_id = ' . (int) $warehouse_id . '))', null, false);
        }
        $groups = $this->db->get(db_prefix() . 'modifier_groups')->result_array();
        foreach ($groups as &$group) {
            $group['modifiers'] = $this->db
                ->where('modifier_group_id', $group['id'])
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
            foreach ($group['modifiers'] as &$modifier) {
                $modifier['source_type'] = 'modifier';
            }
            unset($modifier);
            $group['warehouse_ids'] = $this->get_modifier_group_warehouses($group['id']);
        }
        return $groups;
    }

    public function get_modifier_group($id)
    {
        $group = $this->db->get_where(db_prefix() . 'modifier_groups', ['id' => $id])->row_array();
        if (!$group)
            return null;
        $group['modifiers'] = $this->db
            ->where('modifier_group_id', $id)
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'modifiers')->result_array();
        foreach ($group['modifiers'] as &$modifier) {
            $modifier['source_type'] = 'modifier';
            $modifier['inventory_rules'] = $this->get_inventory_rules('modifier', $modifier['id']);
        }
        unset($modifier);
        return $group;
    }

    public function save_modifier_group($data, $id = null)
    {
        $payload = [
            'name' => $data['name'],
            'selection_type' => $data['selection_type'] ?? 'single',
            'min_selections' => (int) ($data['min_selections'] ?? 0),
            'max_selections' => (int) ($data['max_selections'] ?? 1),
            'active' => isset($data['active']) ? (int) $data['active'] : 1,
            'is_promo_modifier' => isset($data['is_promo_modifier']) ? (int) (bool) $data['is_promo_modifier'] : 0,
        ];

        if ($id) {
            $this->db->where('id', $id)->update(db_prefix() . 'modifier_groups', $payload);
            return $id;
        }

        $payload['datecreated'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'modifier_groups', $payload);
        return $this->db->insert_id();
    }

    public function delete_modifier_group($id)
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'modifier_groups');
        return $this->db->affected_rows() > 0;
    }

    public function delete_modifier_groups_bulk(array $ids)
    {
        if (empty($ids))
            return false;
        $this->db->where_in('id', array_map('intval', $ids))->delete(db_prefix() . 'modifier_groups');
        return $this->db->affected_rows() > 0;
    }

    public function save_modifier_with_options($data, $id = null)
    {
        $this->db->trans_start();

        $group_id = $this->save_modifier_group($data, $id);

        // Replace all options
        if ($group_id) {
            $existing = [];
            if ($id) {
                $rows = $this->db->where('modifier_group_id', $group_id)->get(db_prefix() . 'modifiers')->result_array();
                $existing = array_column($rows, null, 'id');
            }

            $keep_ids = [];
            foreach ($data['options'] ?? [] as $i => $opt) {
                $name = trim($opt['name'] ?? '');
                if ($name === '')
                    continue;

                $payload = [
                    'modifier_group_id' => $group_id,
                    'name' => $name,
                    'price_adjustment' => (float) ($opt['price_adjustment'] ?? 0),
                    'crm_promo_id' => !empty($opt['crm_promo_id']) ? (int) $opt['crm_promo_id'] : null,
                    'source_modifier_id' => !empty($opt['source_modifier_id']) ? (int) $opt['source_modifier_id'] : null,
                    'sort_order' => $i,
                    'active' => 1,
                ];

                $modifier_id = (int) ($opt['id'] ?? 0);
                if ($modifier_id && isset($existing[$modifier_id])) {
                    $this->db->where('id', $modifier_id)
                        ->where('modifier_group_id', $group_id)
                        ->update(db_prefix() . 'modifiers', $payload);
                } else {
                    $this->db->insert(db_prefix() . 'modifiers', $payload);
                    $modifier_id = (int) $this->db->insert_id();
                }

                $keep_ids[] = $modifier_id;
                $this->save_inventory_rules('modifier', $modifier_id, $opt['inventory_rules'] ?? []);
            }

            if (!empty($existing)) {
                $delete_ids = array_diff(array_keys($existing), $keep_ids);
                if (!empty($delete_ids)) {
                    $this->db->where_in('id', $delete_ids)->delete(db_prefix() . 'modifiers');
                    $this->db->where('owner_type', 'modifier')->where_in('owner_id', $delete_ids)->delete(db_prefix() . 'pos_inventory_rules');
                }
            }
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false)
            return false;
        return $group_id;
    }

    public function save_modifier($data, $id = null)
    {
        $payload = [
            'modifier_group_id' => (int) $data['modifier_group_id'],
            'name' => $data['name'],
            'price_adjustment' => (float) ($data['price_adjustment'] ?? 0),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => isset($data['active']) ? (int) $data['active'] : 1,
        ];

        if ($id) {
            $this->db->where('id', $id)->update(db_prefix() . 'modifiers', $payload);
            return $id;
        }

        $this->db->insert(db_prefix() . 'modifiers', $payload);
        return $this->db->insert_id();
    }

    public function delete_modifier($id)
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'modifiers');
        return $this->db->affected_rows() > 0;
    }

    public function get_modifier_group_items($modifier_group_id)
    {
        return $this->db
            ->select('i.id, i.sku_name, i.sku_code, img.sort_order')
            ->from(db_prefix() . 'item_modifier_groups img')
            ->join(db_prefix() . 'items i', 'i.id = img.pos_item_id')
            ->where('img.modifier_group_id', (int) $modifier_group_id)
            ->order_by('i.sku_name', 'ASC')
            ->get()->result_array();
    }

    public function assign_items_to_modifier_group($modifier_group_id, array $item_ids)
    {
        foreach ($item_ids as $item_id) {
            $exists = $this->db->get_where(db_prefix() . 'item_modifier_groups', [
                'pos_item_id' => (string) $item_id,
                'modifier_group_id' => (int) $modifier_group_id,
            ])->row();
            if (!$exists) {
                $this->db->insert(db_prefix() . 'item_modifier_groups', [
                    'pos_item_id' => (string) $item_id,
                    'modifier_group_id' => (int) $modifier_group_id,
                    'sort_order' => 0,
                ]);
            }
        }
        return true;
    }

    public function unassign_item_from_modifier_group($modifier_group_id, $item_id)
    {
        $this->db
            ->where('modifier_group_id', (int) $modifier_group_id)
            ->where('pos_item_id', (string) $item_id)
            ->delete(db_prefix() . 'item_modifier_groups');
        return $this->db->affected_rows() > 0;
    }

    public function get_item_modifier_groups($item_id)
    {
        return $this->db
            ->select('img.*, mg.name, mg.selection_type, mg.min_selections, mg.max_selections, mg.active')
            ->from(db_prefix() . 'item_modifier_groups img')
            ->join(db_prefix() . 'modifier_groups mg', 'mg.id = img.modifier_group_id')
            ->where('img.pos_item_id', (string) $item_id)
            ->order_by('img.sort_order', 'ASC')
            ->get()->result_array();
    }

    public function assign_modifier_group($item_id, $modifier_group_id, $sort_order = 0)
    {
        $exists = $this->db->get_where(db_prefix() . 'item_modifier_groups', [
            'pos_item_id' => (string) $item_id,
            'modifier_group_id' => (int) $modifier_group_id,
        ])->row();

        if ($exists) {
            $this->db->where('pos_item_id', (string) $item_id)
                ->where('modifier_group_id', (int) $modifier_group_id)
                ->update(db_prefix() . 'item_modifier_groups', ['sort_order' => (int) $sort_order]);
        } else {
            $this->db->insert(db_prefix() . 'item_modifier_groups', [
                'pos_item_id' => (string) $item_id,
                'modifier_group_id' => (int) $modifier_group_id,
                'sort_order' => (int) $sort_order,
            ]);
        }
        return true;
    }

    public function unassign_modifier_group($item_id, $modifier_group_id)
    {
        $this->db->where('pos_item_id', (string) $item_id)
            ->where('modifier_group_id', (int) $modifier_group_id)
            ->delete(db_prefix() . 'item_modifier_groups');
        return $this->db->affected_rows() > 0;
    }

    // =========================================================================
    // Individual item modifiers
    // =========================================================================

    public function get_item_modifiers($item_id)
    {
        $groups = $this->db
            ->where('pos_item_id', (string) $item_id)
            ->where('active', 1)
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'item_modifiers')->result_array();

        foreach ($groups as &$group) {
            $group['options'] = $this->db
                ->where('item_modifier_id', $group['id'])
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'item_modifier_options')->result_array();
            foreach ($group['options'] as &$option) {
                $option['source_type'] = 'item_modifier_option';
                $option['inventory_rules'] = $this->get_inventory_rules('item_modifier_option', $option['id']);
            }
            unset($option);
        }

        return $groups;
    }

    public function save_item_modifier($item_id, $data, $id = null)
    {
        $row = [
            'pos_item_id' => (string) $item_id,
            'name' => trim($data['name']),
            'selection_type' => in_array($data['selection_type'] ?? '', ['single', 'multiple']) ? $data['selection_type'] : 'single',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => 1,
        ];

        if ($id) {
            $this->db->where('id', (int) $id)->where('pos_item_id', (string) $item_id)->update(db_prefix() . 'item_modifiers', $row);
            $modifier_id = (int) $id;
        } else {
            $this->db->insert(db_prefix() . 'item_modifiers', $row);
            $modifier_id = $this->db->insert_id();
        }

        $existing_options = [];
        if ($id) {
            $rows = $this->db->where('item_modifier_id', $modifier_id)->get(db_prefix() . 'item_modifier_options')->result_array();
            $existing_options = array_column($rows, null, 'id');
        }

        $keep_ids = [];
        if (!empty($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $i => $opt) {
                $opt_name = trim($opt['name'] ?? '');
                if ($opt_name === '') {
                    continue;
                }

                $payload = [
                    'item_modifier_id' => $modifier_id,
                    'name' => $opt_name,
                    'price_adjustment' => (float) ($opt['price_adjustment'] ?? 0),
                    'sort_order' => (int) ($opt['sort_order'] ?? $i),
                ];

                $option_id = (int) ($opt['id'] ?? 0);
                if ($option_id && isset($existing_options[$option_id])) {
                    $this->db->where('id', $option_id)
                        ->where('item_modifier_id', $modifier_id)
                        ->update(db_prefix() . 'item_modifier_options', $payload);
                } else {
                    $this->db->insert(db_prefix() . 'item_modifier_options', $payload);
                    $option_id = (int) $this->db->insert_id();
                }

                $keep_ids[] = $option_id;
                $this->save_inventory_rules('item_modifier_option', $option_id, $opt['inventory_rules'] ?? []);
            }
        }

        if (!empty($existing_options)) {
            $delete_ids = array_diff(array_keys($existing_options), $keep_ids);
            if (!empty($delete_ids)) {
                $this->db->where_in('id', $delete_ids)->delete(db_prefix() . 'item_modifier_options');
                $this->db->where('owner_type', 'item_modifier_option')->where_in('owner_id', $delete_ids)->delete(db_prefix() . 'pos_inventory_rules');
            }
        }

        return $modifier_id;
    }

    public function delete_item_modifier($id, $item_id)
    {
        $this->db->where('id', (int) $id)->where('pos_item_id', (string) $item_id)->delete(db_prefix() . 'item_modifiers');
        return $this->db->affected_rows() > 0;
    }

    public function get_payment_types($warehouse_id = null)
    {
        $types = $this->db->where('deleted_at IS NULL')->get(db_prefix() . 'pos_payment_types')->result_array();
        if ($warehouse_id) {
            $types = array_filter($types, function ($t) use ($warehouse_id) {
                $ids = json_decode($t['warehouse_ids'] ?? '[]', true);
                return empty($ids) || in_array((int) $warehouse_id, $ids);
            });
        }
        return array_values($types);
    }

    // -------------------------------------------------------------------------
    // Items
    // -------------------------------------------------------------------------

    public function get_items($filters = [])
    {
        $q = $filters['q'] ?? null;
        $group_id = $filters['group_id'] ?? null;
        $warehouse_id = $filters['warehouse_id'] ?? null;
        $can_be_sold = $filters['can_be_sold'] ?? null;
        $can_be_manufacturing = $filters['can_be_manufacturing'] ?? null;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(200, max(1, (int) ($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $wid = $warehouse_id ? (int) $warehouse_id : 0;
        $price_select = $wid
            ? 'COALESCE((SELECT price FROM `' . db_prefix() . 'pos_item_warehouse_prices` WHERE item_id = i.id AND warehouse_id = ' . $wid . ' LIMIT 1), i.rate) AS effective_price'
            : 'i.rate AS effective_price';

        // inventory_manage can have multiple rows per (commodity_id, warehouse_id) — sum via
        // subquery instead of joining directly, which would fan out one item into N duplicate rows.
        $stock_select = $wid
            ? 'COALESCE((SELECT SUM(inventory_number) FROM `' . db_prefix() . 'inventory_manage` WHERE commodity_id = i.id AND warehouse_id = ' . $wid . '), 0)'
            : 'COALESCE((SELECT SUM(inventory_number) FROM `' . db_prefix() . 'inventory_manage` WHERE commodity_id = i.id), 0)';

        $this->db->select('i.*, ' . $stock_select . ' as stock_quantity, ' . $price_select . ', 
            COALESCE(
              NULLIF(i.cached_cost_per_unit, 0),
              CASE WHEN COALESCE(i.units_per_batch,0) > 0 
                   THEN COALESCE(i.purchase_price,0) / NULLIF(i.units_per_batch,0) 
                   ELSE COALESCE(i.purchase_price,0) END,
              0) AS cost', FALSE)
            ->from(db_prefix() . 'items i')
            ->where('i.active', 1)
            ->where('i.parent_id IS NULL');

        // Exclude items restricted to other warehouses (global items have no rows in pos_item_warehouses)
        if ($wid) {
            $this->db->where('(NOT EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_item_warehouses` piw WHERE piw.item_id = i.id)
                OR EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_item_warehouses` piw WHERE piw.item_id = i.id AND piw.warehouse_id = ' . $wid . '))', null, false);
        }

        if ($q) {
            $this->db->group_start()
                ->like('i.commodity_name', $q)
                ->or_like('i.commodity_barcode', $q)
                ->or_like('i.sku_code', $q)
                ->group_end();
        }
        if ($group_id) {
            $this->db->where('i.group_id', $group_id);
        }
        if ($can_be_sold !== null) {
            $this->db->where('i.can_be_sold', $can_be_sold);
        }
        if ($can_be_manufacturing !== null) {
            $this->db->where('i.can_be_manufacturing', $can_be_manufacturing);
        }

        $items = $this->db->order_by('i.menu_sort_order', 'ASC')->order_by('i.sku_name', 'ASC')
            ->limit($limit, $offset)->get()->result_array();

        $item_ids = array_column($items, 'id');
        $bundle_groups_by_item = $this->_get_bundle_modifier_groups_bulk($item_ids);

        foreach ($items as &$item) {
            $item['variants'] = $this->_get_item_variants($item['id'], $warehouse_id);
            $item['tax_info'] = $this->_get_item_tax_info($item);
            $item['modifier_group_ids'] = array_column($this->get_item_modifier_groups($item['id']), 'modifier_group_id');
            $item['item_modifiers'] = $this->get_item_modifiers($item['id']);
            $item['bundle_modifier_groups'] = $bundle_groups_by_item[$item['id']] ?? [];
        }
        return $items;
    }

    public function get_item($id, $warehouse_id = null)
    {
        $wid = $warehouse_id ? (int) $warehouse_id : 0;
        $price_select = $wid
            ? 'COALESCE((SELECT price FROM `' . db_prefix() . 'pos_item_warehouse_prices` WHERE item_id = i.id AND warehouse_id = ' . $wid . ' LIMIT 1), i.rate) AS effective_price'
            : 'i.rate AS effective_price';

        $item = $this->db->select('i.*, COALESCE(inv.inventory_number, 0) as stock_quantity, ' . $price_select . ', 
            COALESCE(
              NULLIF(i.cached_cost_per_unit, 0),
              CASE WHEN COALESCE(i.units_per_batch,0) > 0 
                   THEN COALESCE(i.purchase_price,0) / NULLIF(i.units_per_batch,0) 
                   ELSE COALESCE(i.purchase_price,0) END,
              0) AS cost', FALSE)
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'inventory_manage inv', 'inv.commodity_id = i.id', 'left')
            ->where('i.id', $id)
            ->where('i.active', 1)
            ->get()->row_array();

        if (!$item)
            return null;
        $item['variants'] = $this->_get_item_variants($id, $wid ?: null);
        $item['tax_info'] = $this->_get_item_tax_info($item);
        $item['modifier_group_ids'] = array_column($this->get_item_modifier_groups($id), 'modifier_group_id');
        $item['item_modifiers'] = $this->get_item_modifiers($id);
        $item['warehouse_prices'] = $this->get_item_warehouse_prices($id);
        $item['bundle_modifier_groups'] = $this->_get_bundle_modifier_groups_for_item($id);
        return $item;
    }

    // Returns bundle groups for a single item, shaped identically to modifier group objects
    // so the Flutter app can render them with the same modifier-selection widget.
    // All price_adjustments are "0.00". IDs are prefixed (bg_*, bg_item_*, bg_mod_*)
    // to avoid collision with real modifier group / modifier IDs.
    private function _get_bundle_modifier_groups_for_item($item_id)
    {
        static $table_ok = null;
        if ($table_ok === null) {
            $table_ok = $this->db->table_exists(db_prefix() . 'pos_crm_bundle_groups');
        }
        if (!$table_ok)
            return [];

        $promo = $this->db
            ->select('id')
            ->where('pos_item_id', (string) $item_id)
            ->where('type', 'bundle')
            ->where('active', 1)
            ->get(db_prefix() . 'pos_crm_promos')->row_array();

        if (!$promo)
            return [];

        $groups = $this->db
            ->where('promo_id', (int) $promo['id'])
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'pos_crm_bundle_groups')->result_array();

        $out = [];
        foreach ($groups as $g) {
            $modifiers = [];

            if ($g['source_type'] === 'modifier_group_ref' && !empty($g['modifier_group_id'])) {
                // Options come from an existing Promo Modifier Group
                $mods = $this->db
                    ->select('id, name, sort_order')
                    ->where('modifier_group_id', (int) $g['modifier_group_id'])
                    ->where('active', 1)
                    ->order_by('sort_order', 'ASC')
                    ->get(db_prefix() . 'modifiers')->result_array();
                foreach ($mods as $i => $m) {
                    $modifiers[] = [
                        'id' => 'bg_mod_' . $m['id'],
                        'name' => $m['name'],
                        'price_adjustment' => '0.00',
                        'sort_order' => (string) $i,
                        'option_type' => 'modifier',
                        'source_id' => (int) $m['id'],
                    ];
                }
            } else {
                // Options are defined inline in bundle_group_options
                $rows = $this->db
                    ->where('bundle_group_id', (int) $g['id'])
                    ->order_by('sort_order', 'ASC')
                    ->get(db_prefix() . 'pos_crm_bundle_group_options')->result_array();

                foreach ($rows as $i => $row) {
                    if ($row['option_type'] === 'item') {
                        $itm = $this->db->select('id, sku_name, commodity_name')
                            ->get_where(db_prefix() . 'items', ['id' => (int) $row['option_id'], 'active' => 1])
                            ->row_array();
                        if (!$itm)
                            continue;
                        $modifiers[] = [
                            'id' => 'bg_item_' . $itm['id'],
                            'name' => $itm['sku_name'] ?: $itm['commodity_name'],
                            'price_adjustment' => '0.00',
                            'sort_order' => (string) $i,
                            'option_type' => 'item',
                            'source_id' => (int) $itm['id'],
                        ];
                    } else {
                        $mod = $this->db->select('id, name')
                            ->get_where(db_prefix() . 'modifiers', ['id' => (int) $row['option_id'], 'active' => 1])
                            ->row_array();
                        if (!$mod)
                            continue;
                        $modifiers[] = [
                            'id' => 'bg_mod_' . $mod['id'],
                            'name' => $mod['name'],
                            'price_adjustment' => '0.00',
                            'sort_order' => (string) $i,
                            'option_type' => 'modifier',
                            'source_id' => (int) $mod['id'],
                        ];
                    }
                }
            }

            $out[] = [
                'id' => 'bg_' . $g['id'],
                'name' => $g['name'],
                'selection_type' => 'single',
                'min_selections' => '1',
                'max_selections' => '1',
                'active' => '1',
                'group_type' => $g['group_type'],   // 'product_choice' or 'modifier_choice'
                'source_type' => $g['source_type'],  // 'custom' or 'modifier_group_ref'
                'modifiers' => $modifiers,
            ];
        }
        return $out;
    }

    // Bulk version: fetches bundle_modifier_groups for a list of item IDs in a fixed number
    // of queries regardless of list size. Returns [item_id => [groups]].
    private function _get_bundle_modifier_groups_bulk(array $item_ids)
    {
        if (empty($item_ids))
            return [];

        static $table_ok = null;
        if ($table_ok === null) {
            $table_ok = $this->db->table_exists(db_prefix() . 'pos_crm_bundle_groups');
        }
        if (!$table_ok)
            return [];

        // 1. Find bundle promos for these items
        $promos = $this->db
            ->select('id, pos_item_id')
            ->where_in('pos_item_id', array_map('strval', $item_ids))
            ->where('type', 'bundle')
            ->where('active', 1)
            ->get(db_prefix() . 'pos_crm_promos')->result_array();

        if (empty($promos))
            return [];

        $promo_ids = array_column($promos, 'id');
        $item_by_promo = array_column($promos, 'pos_item_id', 'id');

        // 2. Fetch all bundle groups for those promos
        $groups = $this->db
            ->where_in('promo_id', $promo_ids)
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'pos_crm_bundle_groups')->result_array();

        if (empty($groups))
            return [];

        $groups_by_promo = [];
        $custom_group_ids = [];
        $ref_mg_ids = [];
        foreach ($groups as $g) {
            $groups_by_promo[$g['promo_id']][] = $g;
            if ($g['source_type'] === 'modifier_group_ref' && !empty($g['modifier_group_id'])) {
                $ref_mg_ids[] = (int) $g['modifier_group_id'];
            } else {
                $custom_group_ids[] = (int) $g['id'];
            }
        }

        // 3. Fetch custom option rows in one query
        $opt_rows_by_group = [];
        if (!empty($custom_group_ids)) {
            $rows = $this->db
                ->where_in('bundle_group_id', $custom_group_ids)
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'pos_crm_bundle_group_options')->result_array();
            foreach ($rows as $r) {
                $opt_rows_by_group[$r['bundle_group_id']][] = $r;
            }
        }

        // 4. Collect all referenced item/modifier IDs for bulk lookup
        $item_opt_ids = [];
        $mod_opt_ids = [];
        foreach ($opt_rows_by_group as $rows) {
            foreach ($rows as $r) {
                if ($r['option_type'] === 'item')
                    $item_opt_ids[] = (int) $r['option_id'];
                else
                    $mod_opt_ids[] = (int) $r['option_id'];
            }
        }

        $item_map = [];
        if (!empty($item_opt_ids)) {
            $rows = $this->db->select('id, sku_name, commodity_name')
                ->where_in('id', array_unique($item_opt_ids))->where('active', 1)
                ->get(db_prefix() . 'items')->result_array();
            $item_map = array_column($rows, null, 'id');
        }

        $mod_map = [];
        if (!empty($mod_opt_ids)) {
            $rows = $this->db->select('id, name')
                ->where_in('id', array_unique($mod_opt_ids))->where('active', 1)
                ->get(db_prefix() . 'modifiers')->result_array();
            $mod_map = array_column($rows, null, 'id');
        }

        // 5. Fetch modifiers for modifier_group_ref groups
        $ref_mods_by_mg = [];
        if (!empty($ref_mg_ids)) {
            $rows = $this->db->select('id, modifier_group_id, name, sort_order')
                ->where_in('modifier_group_id', array_unique($ref_mg_ids))
                ->where('active', 1)->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
            foreach ($rows as $r) {
                $ref_mods_by_mg[$r['modifier_group_id']][] = $r;
            }
        }

        // 6. Build output map: item_id => [bundle modifier groups]
        $result = [];
        foreach ($promos as $promo) {
            $item_id = $promo['pos_item_id'];
            $bg_list = $groups_by_promo[$promo['id']] ?? [];
            $out = [];

            foreach ($bg_list as $g) {
                $modifiers = [];

                if ($g['source_type'] === 'modifier_group_ref' && !empty($g['modifier_group_id'])) {
                    foreach ($ref_mods_by_mg[(int) $g['modifier_group_id']] ?? [] as $i => $m) {
                        $modifiers[] = [
                            'id' => 'bg_mod_' . $m['id'],
                            'name' => $m['name'],
                            'price_adjustment' => '0.00',
                            'sort_order' => (string) $i,
                            'option_type' => 'modifier',
                            'source_id' => (int) $m['id'],
                        ];
                    }
                } else {
                    foreach ($opt_rows_by_group[(int) $g['id']] ?? [] as $i => $row) {
                        if ($row['option_type'] === 'item') {
                            $itm = $item_map[$row['option_id']] ?? null;
                            if (!$itm)
                                continue;
                            $modifiers[] = [
                                'id' => 'bg_item_' . $itm['id'],
                                'name' => $itm['sku_name'] ?: $itm['commodity_name'],
                                'price_adjustment' => '0.00',
                                'sort_order' => (string) $i,
                                'option_type' => 'item',
                                'source_id' => (int) $itm['id'],
                            ];
                        } else {
                            $mod = $mod_map[$row['option_id']] ?? null;
                            if (!$mod)
                                continue;
                            $modifiers[] = [
                                'id' => 'bg_mod_' . $mod['id'],
                                'name' => $mod['name'],
                                'price_adjustment' => '0.00',
                                'sort_order' => (string) $i,
                                'option_type' => 'modifier',
                                'source_id' => (int) $mod['id'],
                            ];
                        }
                    }
                }

                $out[] = [
                    'id' => 'bg_' . $g['id'],
                    'name' => $g['name'],
                    'selection_type' => 'single',
                    'min_selections' => '1',
                    'max_selections' => '1',
                    'active' => '1',
                    'group_type' => $g['group_type'],
                    'source_type' => $g['source_type'],
                    'modifiers' => $modifiers,
                ];
            }

            $result[$item_id] = $out;
        }
        return $result;
    }

    public function get_item_by_barcode($code)
    {
        $item = $this->db->select('i.*, COALESCE(inv.inventory_number, 0) as stock_quantity')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'inventory_manage inv', 'inv.commodity_id = i.id', 'left')
            ->where('i.active', 1)
            ->group_start()
            ->where('i.commodity_barcode', $code)
            ->or_where('i.sku_code', $code)
            ->group_end()
            ->get()->row_array();

        if (!$item)
            return null;
        $item['variants'] = $this->_get_item_variants($item['id'], null);
        $item['tax_info'] = $this->_get_item_tax_info($item);
        $item['modifier_group_ids'] = array_column($this->get_item_modifier_groups($item['id']), 'modifier_group_id');
        $item['item_modifiers'] = $this->get_item_modifiers($item['id']);
        $item['bundle_modifier_groups'] = $this->_get_bundle_modifier_groups_for_item($item['id']);
        return $item;
    }

    private function _get_item_variants($parent_id, $warehouse_id)
    {
        $this->db->select('i.*, COALESCE(inv.inventory_number, 0) as stock_quantity')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'inventory_manage inv', 'inv.commodity_id = i.id' . ($warehouse_id ? ' AND inv.warehouse_id = ' . (int) $warehouse_id : ''), 'left')
            ->where('i.parent_id', $parent_id)
            ->where('i.active', 1);
        return $this->db->get()->result_array();
    }

    private function _get_item_tax_info($item)
    {
        $taxes = [];
        foreach (['tax', 'tax2'] as $field) {
            if (!empty($item[$field])) {
                $tax = $this->db->get_where(db_prefix() . 'taxes', ['id' => $item[$field]])->row_array();
                if ($tax)
                    $taxes[] = $tax;
            }
        }
        return $taxes;
    }

    public function get_item_groups()
    {
        return $this->db->get(db_prefix() . 'items_groups')->result_array();
    }

    public function get_sub_groups($group_id = null)
    {
        if ($group_id !== null) {
            $this->db->where('group_id', $group_id);
        }
        return $this->db->get(db_prefix() . 'wh_sub_group')->result_array();
    }

    public function get_uoms()
    {
        return $this->db->query('SELECT unit_type_id AS id, unit_name AS name FROM ' . db_prefix() . 'ware_unit_type WHERE display = 1 ORDER BY ' . db_prefix() . 'ware_unit_type.order ASC')->result_array();
    }

    // -------------------------------------------------------------------------
    // Taxes / Payment modes
    // -------------------------------------------------------------------------

    public function get_taxes()
    {
        return $this->db->get(db_prefix() . 'taxes')->result_array();
    }

    public function get_payment_modes()
    {
        $p = db_prefix();
        return $this->db
            ->select("pm.*")
            ->from("{$p}payment_modes pm")
            ->join("{$p}pos_payment_mode_settings pms", "pms.payment_mode_id = pm.id", 'left')
            ->where('pm.active', 1)
            ->where("(pms.pos_enabled IS NULL OR pms.pos_enabled = 1)")
            ->get()->result_array();
    }

    public function get_payment_modes_with_pos_status()
    {
        $p = db_prefix();
        return $this->db
            ->select("pm.id, pm.name, pm.description, pm.active, COALESCE(pms.pos_enabled, 1) as pos_enabled")
            ->from("{$p}payment_modes pm")
            ->join("{$p}pos_payment_mode_settings pms", "pms.payment_mode_id = pm.id", 'left')
            ->where('pm.active', 1)
            ->order_by('pm.name', 'ASC')
            ->get()->result_array();
    }

    public function toggle_payment_mode_for_pos($payment_mode_id, $enabled)
    {
        $p = db_prefix();
        $enabled = $enabled ? 1 : 0;
        $exists = $this->db->where('payment_mode_id', $payment_mode_id)->get("{$p}pos_payment_mode_settings")->row();
        if ($exists) {
            return $this->db->where('payment_mode_id', $payment_mode_id)
                ->update("{$p}pos_payment_mode_settings", ['pos_enabled' => $enabled]);
        }
        return $this->db->insert("{$p}pos_payment_mode_settings", [
            'payment_mode_id' => $payment_mode_id,
            'pos_enabled' => $enabled,
        ]);
    }

    // -------------------------------------------------------------------------
    // Bundles
    // -------------------------------------------------------------------------

    public function get_bundles()
    {
        $bundles = $this->db->where('active', 1)->where('deleted_at IS NULL')->get(db_prefix() . 'pos_bundles')->result_array();
        foreach ($bundles as &$bundle) {
            $bundle['items'] = $this->db->where('bundle_id', $bundle['id'])->get(db_prefix() . 'pos_bundle_items')->result_array();
        }
        return $bundles;
    }

    public function create_bundle($data)
    {
        $this->db->insert(db_prefix() . 'pos_bundles', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'] ?? 0,
            'image' => $data['image'] ?? null,
            'active' => isset($data['active']) ? (int) $data['active'] : 1,
            'warehouse_ids' => isset($data['warehouse_ids']) ? json_encode($data['warehouse_ids']) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $bundle_id = $this->db->insert_id();
        if (!$bundle_id)
            return false;
        $this->_save_bundle_items($bundle_id, $data['items'] ?? []);
        return $bundle_id;
    }

    public function update_bundle($id, $data)
    {
        $update = [];
        foreach (['name', 'description', 'price', 'image', 'active'] as $f) {
            if (isset($data[$f]))
                $update[$f] = $data[$f];
        }
        if (isset($data['warehouse_ids']))
            $update['warehouse_ids'] = json_encode($data['warehouse_ids']);
        if (!empty($update))
            $this->db->where('id', $id)->update(db_prefix() . 'pos_bundles', $update);
        if (isset($data['items'])) {
            $this->db->where('bundle_id', $id)->delete(db_prefix() . 'pos_bundle_items');
            $this->_save_bundle_items($id, $data['items']);
        }
        return true;
    }

    public function delete_bundle($id)
    {
        $this->db->where('id', $id)->update(db_prefix() . 'pos_bundles', ['deleted_at' => date('Y-m-d H:i:s')]);
        return $this->db->affected_rows() > 0;
    }

    private function _save_bundle_items($bundle_id, $items)
    {
        foreach ($items as $item) {
            $this->db->insert(db_prefix() . 'pos_bundle_items', [
                'bundle_id' => $bundle_id,
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'] ?? 1,
                'modifier_ids' => isset($item['modifier_ids']) ? json_encode($item['modifier_ids']) : null,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // CRM Promos & Bundles  (CRM-only, not synced to Flutter POS)
    // -------------------------------------------------------------------------

    public function get_crm_promos($type = null, $include_inactive = false)
    {
        $this->db->select('p.*, i.sku_name as item_name, i.sku_code as item_code')
            ->from(db_prefix() . 'pos_crm_promos p')
            ->join(db_prefix() . 'items i', 'i.id = p.pos_item_id', 'left')
            ->order_by('p.type', 'ASC')
            ->order_by('p.name', 'ASC');
        if ($type)
            $this->db->where('p.type', $type);
        if (!$include_inactive)
            $this->db->where('p.active', 1);
        return $this->db->get()->result_array();
    }

    public function get_crm_promo($id)
    {
        $promo = $this->db->select('p.*, i.sku_name as item_name, i.sku_code as item_code')
            ->from(db_prefix() . 'pos_crm_promos p')
            ->join(db_prefix() . 'items i', 'i.id = p.pos_item_id', 'left')
            ->where('p.id', (int) $id)
            ->get()->row_array();
        if ($promo) {
            $promo['components'] = $this->get_crm_promo_components($promo['id']);
            $promo['bundle_groups'] = $this->get_bundle_groups($promo['id']);
        }
        return $promo;
    }

    public function get_crm_promo_by_item_id($item_id)
    {
        $promo = $this->db->where('pos_item_id', (int) $item_id)
            ->get(db_prefix() . 'pos_crm_promos')->row_array();
        if ($promo) {
            $promo['components'] = $this->get_crm_promo_components($promo['id']);
            $promo['bundle_groups'] = $this->get_bundle_groups($promo['id']);
        }
        return $promo ?: null;
    }

    public function get_bundle_groups($promo_id)
    {
        $groups = $this->db->where('promo_id', (int) $promo_id)
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'pos_crm_bundle_groups')->result_array();
        foreach ($groups as &$g) {
            $g['options'] = $g['source_type'] === 'custom'
                ? $this->db->where('bundle_group_id', (int) $g['id'])
                    ->order_by('sort_order', 'ASC')
                    ->get(db_prefix() . 'pos_crm_bundle_group_options')->result_array()
                : [];
        }
        return $groups;
    }

    public function get_promo_modifier_groups()
    {
        $groups = $this->db->where('is_promo_modifier', 1)
            ->where('active', 1)
            ->order_by('name', 'ASC')
            ->get(db_prefix() . 'modifier_groups')->result_array();
        foreach ($groups as &$g) {
            $g['modifiers'] = $this->db->where('modifier_group_id', (int) $g['id'])
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
        }
        return $groups;
    }

    public function get_crm_promo_flags_by_item_ids(array $item_ids)
    {
        if (empty($item_ids))
            return [];
        $rows = $this->db->select('id, pos_item_id, name, type, active')
            ->where_in('pos_item_id', array_map('intval', $item_ids))
            ->get(db_prefix() . 'pos_crm_promos')->result_array();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['pos_item_id']] = $r;
        }
        return $map;
    }

    public function get_crm_promo_components($promo_id)
    {
        return $this->db->where('promo_id', (int) $promo_id)
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_crm_promo_components')->result_array();
    }

    public function save_crm_promo($data, $id = null)
    {
        $row = [
            'name' => trim($data['name']),
            'type' => in_array($data['type'] ?? '', ['promo', 'bundle', 'set']) ? $data['type'] : 'promo',
            'pos_item_id' => !empty($data['pos_item_id']) ? (int) $data['pos_item_id'] : null,
            'description' => $data['description'] ?? null,
            'discount_type' => in_array($data['discount_type'] ?? '', ['percentage', 'fixed']) ? $data['discount_type'] : null,
            'discount_value' => isset($data['discount_value']) ? (float) $data['discount_value'] : 0,
            'active' => isset($data['active']) ? (int) (bool) $data['active'] : 1,
        ];

        if ($id) {
            $row['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', (int) $id)->update(db_prefix() . 'pos_crm_promos', $row);
        } else {
            $row['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'pos_crm_promos', $row);
            $id = $this->db->insert_id();
        }

        if (!$id)
            return false;

        if (isset($data['components'])) {
            $this->db->where('promo_id', (int) $id)->delete(db_prefix() . 'pos_crm_promo_components');
            $sort = 0;
            foreach ($data['components'] as $c) {
                if (empty($c['component_id']))
                    continue;
                $this->db->insert(db_prefix() . 'pos_crm_promo_components', [
                    'promo_id' => $id,
                    'component_type' => in_array($c['component_type'] ?? '', ['product', 'modifier', 'modifier_group']) ? $c['component_type'] : 'product',
                    'component_id' => (int) $c['component_id'],
                    'component_name' => trim($c['component_name'] ?? ''),
                    'quantity' => isset($c['quantity']) ? (float) $c['quantity'] : 1,
                    'notes' => $c['notes'] ?? null,
                    'sort_order' => $sort++,
                ]);
            }
        }

        if (isset($data['bundle_groups'])) {
            $this->_delete_bundle_groups((int) $id);
            foreach ($data['bundle_groups'] as $i => $g) {
                $gtype = ($g['group_type'] ?? '') === 'modifier_choice' ? 'modifier_choice' : 'product_choice';
                $stype = ($g['source_type'] ?? '') === 'modifier_group_ref' ? 'modifier_group_ref' : 'custom';
                $this->db->insert(db_prefix() . 'pos_crm_bundle_groups', [
                    'promo_id' => $id,
                    'name' => trim($g['name'] ?? ''),
                    'group_type' => $gtype,
                    'source_type' => $stype,
                    'modifier_group_id' => !empty($g['modifier_group_id']) ? (int) $g['modifier_group_id'] : null,
                    'sort_order' => $i,
                ]);
                $bg_id = $this->db->insert_id();
                if ($stype === 'custom') {
                    $otype = $gtype === 'modifier_choice' ? 'modifier' : 'item';
                    foreach ($g['options'] ?? [] as $j => $opt) {
                        if (empty($opt['option_id']))
                            continue;
                        $this->db->insert(db_prefix() . 'pos_crm_bundle_group_options', [
                            'bundle_group_id' => $bg_id,
                            'option_type' => $otype,
                            'option_id' => (int) $opt['option_id'],
                            'sort_order' => $j,
                        ]);
                    }
                }
            }
        }

        return $id;
    }

    private function _delete_bundle_groups($promo_id)
    {
        $gids = $this->db->select('id')->where('promo_id', $promo_id)
            ->get(db_prefix() . 'pos_crm_bundle_groups')->result_array();
        if ($gids) {
            $this->db->where_in('bundle_group_id', array_column($gids, 'id'))
                ->delete(db_prefix() . 'pos_crm_bundle_group_options');
        }
        $this->db->where('promo_id', $promo_id)->delete(db_prefix() . 'pos_crm_bundle_groups');
    }

    public function delete_crm_promo($id)
    {
        $this->_delete_bundle_groups((int) $id);
        $this->db->where('promo_id', (int) $id)->delete(db_prefix() . 'pos_crm_promo_components');
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'pos_crm_promos');
        return $this->db->affected_rows() > 0;
    }

    public function delete_crm_promos_bulk(array $ids)
    {
        $ids = array_map('intval', $ids);
        foreach ($ids as $pid) {
            $this->_delete_bundle_groups($pid);
        }
        $this->db->where_in('promo_id', $ids)->delete(db_prefix() . 'pos_crm_promo_components');
        $this->db->where_in('id', $ids)->delete(db_prefix() . 'pos_crm_promos');
        return true;
    }

    public function get_report_crm_promos_summary($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        $row = $this->db->query("
            SELECT
                COUNT(DISTINCT p.id)                                AS total_promos_defined,
                COUNT(DISTINCT CASE WHEN li.id IS NOT NULL THEN p.id END) AS promos_with_sales,
                COALESCE(SUM(li.quantity), 0)                       AS total_units_sold,
                COALESCE(SUM(li.total_money), 0)                    AS total_revenue,
                COALESCE(SUM(li.total_discount), 0)                 AS total_discount_given,
                COUNT(DISTINCT li.receipt_id)                       AS receipts_count
            FROM `" . db_prefix() . "pos_crm_promos` p
            LEFT JOIN `" . db_prefix() . "pos_receipt_line_items` li ON li.item_id = p.pos_item_id
            LEFT JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
                AND r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
                AND r.receipt_date BETWEEN ? AND ? $wh
            WHERE p.active = 1
        ", [$from, $to])->row_array();

        return $row ?: [];
    }

    public function get_report_crm_promos_detail($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT
                p.id                                                AS promo_id,
                p.name                                              AS promo_name,
                p.type                                              AS promo_type,
                p.discount_type,
                p.discount_value,
                COALESCE(i.sku_name, '— (no item linked)')         AS item_name,
                COALESCE(i.sku_code, '')                           AS item_code,
                COALESCE(SUM(li.quantity), 0)                       AS units_sold,
                COALESCE(SUM(li.total_money), 0)                    AS gross_revenue,
                COALESCE(SUM(li.total_discount), 0)                 AS total_discount,
                COALESCE(SUM(li.total_money - COALESCE(li.total_discount,0)), 0) AS net_revenue,
                COUNT(DISTINCT li.receipt_id)                       AS order_count,
                COUNT(DISTINCT r.warehouse_id)                      AS outlet_count
            FROM `" . db_prefix() . "pos_crm_promos` p
            LEFT JOIN `" . db_prefix() . "items` i ON i.id = p.pos_item_id
            LEFT JOIN `" . db_prefix() . "pos_receipt_line_items` li ON li.item_id = p.pos_item_id
            LEFT JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
                AND r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
                AND r.receipt_date BETWEEN ? AND ? $wh
            WHERE p.active = 1
            GROUP BY p.id, p.name, p.type, p.discount_type, p.discount_value, i.sku_name, i.sku_code
            ORDER BY gross_revenue DESC
        ", [$from, $to])->result_array();
    }

    public function get_report_crm_promo_trend($date_from, $date_to, $warehouse_id = null, $group_by = 'daily')
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        $label_expr = [
            'daily' => "DATE(r.receipt_date)",
            'weekly' => "DATE(DATE_SUB(r.receipt_date, INTERVAL WEEKDAY(r.receipt_date) DAY))",
            'monthly' => "DATE_FORMAT(r.receipt_date, '%Y-%m')",
            'hourly' => "DATE_FORMAT(r.receipt_date, '%H:00')",
            'dow' => "DAYNAME(r.receipt_date)",
        ];
        $lbl = $label_expr[$group_by] ?? $label_expr['daily'];

        return $this->db->query("
            SELECT
                $lbl                                    AS label,
                p.name                                  AS promo_name,
                COALESCE(SUM(li.total_money), 0)        AS revenue,
                COALESCE(SUM(li.quantity), 0)           AS units_sold,
                COUNT(DISTINCT li.receipt_id)           AS order_count
            FROM `" . db_prefix() . "pos_crm_promos` p
            JOIN `" . db_prefix() . "pos_receipt_line_items` li ON li.item_id = p.pos_item_id
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
              AND p.active = 1
            GROUP BY $lbl, p.id, p.name
            ORDER BY $lbl ASC, revenue DESC
        ", [$from, $to])->result_array();
    }

    public function get_report_crm_promo_components_usage($promo_id, $date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        $promo = $this->db->select('p.*, COALESCE(i.rate, 0) AS selling_price, i.sku_name AS item_name')
            ->from(db_prefix() . 'pos_crm_promos p')
            ->join(db_prefix() . 'items i', 'i.id = p.pos_item_id', 'left')
            ->where('p.id', (int) $promo_id)
            ->get()->row_array();
        if (!$promo || !$promo['pos_item_id'])
            return ['promo' => $promo, 'transactions' => [], 'components' => [], 'bundle_groups' => []];

        $txns = $this->db->query("
            SELECT r.receipt_number, r.receipt_date, li.quantity, li.unit_price,
                   li.total_money, li.total_discount, w.warehouse_name
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            LEFT JOIN `" . db_prefix() . "warehouse` w ON w.warehouse_id = r.warehouse_id
            WHERE li.item_id = ? AND r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            ORDER BY r.receipt_date DESC LIMIT 100
        ", [(int) $promo['pos_item_id'], $from, $to])->result_array();

        // Components with ala-carte pricing (handles product, modifier, modifier_group types)
        $components = $this->db->query("
            SELECT pc.*,
                   COALESCE(
                       CASE WHEN pc.component_type = 'product'  THEN i.rate
                            WHEN pc.component_type = 'modifier' THEN m.price_adjustment
                            ELSE NULL END, 0
                   ) AS unit_rate,
                   COALESCE(i.sku_name, m.name, mg.name, pc.component_name) AS display_name
            FROM `" . db_prefix() . "pos_crm_promo_components` pc
            LEFT JOIN `" . db_prefix() . "items`          i  ON i.id  = pc.component_id AND pc.component_type = 'product'
            LEFT JOIN `" . db_prefix() . "modifiers`      m  ON m.id  = pc.component_id AND pc.component_type = 'modifier'
            LEFT JOIN `" . db_prefix() . "modifier_groups` mg ON mg.id = pc.component_id AND pc.component_type = 'modifier_group'
            WHERE pc.promo_id = ?
            ORDER BY pc.sort_order ASC, pc.id ASC
        ", [(int) $promo_id])->result_array();

        // Bundle groups with option pricing (for bundle type)
        $bundle_groups = [];
        if ($promo['type'] === 'bundle') {
            $p = db_prefix();
            $groups = $this->db->query("
                SELECT bg.id, bg.name, bg.group_type, bg.source_type, bg.modifier_group_id
                FROM `{$p}pos_crm_bundle_groups` bg
                WHERE bg.promo_id = ? ORDER BY bg.sort_order ASC
            ", [(int) $promo_id])->result_array();

            foreach ($groups as &$g) {
                if ($g['source_type'] === 'custom') {
                    $g['options'] = $this->db->query("
                        SELECT bgo.option_type, bgo.option_id,
                               COALESCE(i.sku_name, m.name)  AS option_name,
                               COALESCE(i.rate, 0)            AS item_rate,
                               COALESCE(m.price_adjustment,0) AS mod_rate
                        FROM `{$p}pos_crm_bundle_group_options` bgo
                        LEFT JOIN `{$p}items` i    ON i.id = bgo.option_id AND bgo.option_type = 'item'
                        LEFT JOIN `{$p}modifiers` m ON m.id = bgo.option_id AND bgo.option_type = 'modifier'
                        WHERE bgo.bundle_group_id = ?
                        ORDER BY bgo.sort_order ASC
                    ", [$g['id']])->result_array();
                } else {
                    $g['options'] = $this->db->query("
                        SELECT 'modifier' AS option_type, m.id AS option_id,
                               m.name AS option_name, 0 AS item_rate,
                               COALESCE(m.price_adjustment,0) AS mod_rate
                        FROM `{$p}modifiers` m
                        WHERE m.modifier_group_id = ? AND m.active = 1
                        ORDER BY m.sort_order ASC
                    ", [$g['modifier_group_id']])->result_array();
                }
                $prices = array_map(function ($o) {
                    return $o['option_type'] === 'item' ? (float) $o['item_rate'] : (float) $o['mod_rate'];
                }, $g['options']);
                $g['avg_price'] = count($prices) ? round(array_sum($prices) / count($prices), 2) : 0;
                $g['min_price'] = count($prices) ? round(min($prices), 2) : 0;
            }
            $bundle_groups = $groups;
        }

        return ['promo' => $promo, 'transactions' => $txns, 'components' => $components, 'bundle_groups' => $bundle_groups];
    }

    // -------------------------------------------------------------------------
    // Promotions
    // -------------------------------------------------------------------------

    public function get_promotions($warehouse_id = null)
    {
        $now = date('Y-m-d H:i:s');
        $this->db->where('active', 1)
            ->group_start()
            ->where('start_at IS NULL')
            ->or_where('start_at <=', $now)
            ->group_end()
            ->group_start()
            ->where('end_at IS NULL')
            ->or_where('end_at >=', $now)
            ->group_end();
        $promos = $this->db->get(db_prefix() . 'pos_promotions')->result_array();
        if ($warehouse_id) {
            $promos = array_filter($promos, function ($p) use ($warehouse_id) {
                $ids = json_decode($p['warehouse_ids'] ?? '[]', true);
                return empty($ids) || in_array((int) $warehouse_id, $ids);
            });
        }
        return array_values($promos);
    }

    public function validate_promotions($warehouse_id, $items, $subtotal, $voucher_code = null)
    {
        $promos = $this->get_promotions($warehouse_id);
        $applied = [];
        $line_discounts = [];
        $total_discount = 0;

        foreach ($promos as $promo) {
            if ($subtotal < (float) $promo['min_order_value'])
                continue;

            $promo_item_ids = json_decode($promo['item_ids'] ?? '[]', true);
            $promo_category_ids = json_decode($promo['category_ids'] ?? '[]', true);

            if ($promo['type'] === 'percentage' || $promo['type'] === 'fixed') {
                $discount = 0;
                foreach ($items as $line) {
                    $eligible = empty($promo_item_ids) && empty($promo_category_ids);
                    if (!$eligible && in_array((int) $line['item_id'], $promo_item_ids))
                        $eligible = true;
                    if (!$eligible && !empty($promo_category_ids)) {
                        $item_row = $this->db->get_where(db_prefix() . 'items', ['id' => $line['item_id']])->row_array();
                        if ($item_row && in_array((int) $item_row['group_id'], $promo_category_ids))
                            $eligible = true;
                    }
                    if (!$eligible)
                        continue;
                    $line_total = (float) $line['price'] * (float) $line['quantity'];
                    $line_disc = $promo['type'] === 'percentage'
                        ? round($line_total * $promo['value'] / 100, 2)
                        : min((float) $promo['value'], $line_total);
                    $discount += $line_disc;
                    $line_discounts[] = ['item_id' => $line['item_id'], 'promotion_id' => $promo['id'], 'discount' => $line_disc];
                }
                if ($discount > 0) {
                    $total_discount += $discount;
                    $applied[] = ['promotion_id' => $promo['id'], 'name' => $promo['name'], 'type' => $promo['type'], 'discount' => $discount];
                }
            } elseif ($promo['type'] === 'bogo') {
                foreach ($items as $line) {
                    if (!empty($promo_item_ids) && !in_array((int) $line['item_id'], $promo_item_ids))
                        continue;
                    $qty = (int) $line['quantity'];
                    $free = floor($qty / 2);
                    if ($free > 0) {
                        $disc = round($free * (float) $line['price'], 2);
                        $total_discount += $disc;
                        $line_discounts[] = ['item_id' => $line['item_id'], 'promotion_id' => $promo['id'], 'discount' => $disc];
                        $applied[] = ['promotion_id' => $promo['id'], 'name' => $promo['name'], 'type' => 'bogo', 'discount' => $disc];
                    }
                }
            }
        }

        return [
            'applied_promotions' => $applied,
            'line_discounts' => $line_discounts,
            'total_discount' => round($total_discount, 2),
            'final_total' => round($subtotal - $total_discount, 2),
        ];
    }

    // -------------------------------------------------------------------------
    // Shifts
    // -------------------------------------------------------------------------

    public function delete_shift($id)
    {
        $this->db->where('shift_id', $id)->delete(db_prefix() . 'pos_shift_cash_movements');
        $this->db->where('id', $id)->delete(db_prefix() . 'pos_shifts');
        return $this->db->affected_rows() > 0;
    }

    public function get_open_shift_for_employee($employee_id)
    {
        return $this->db->where('employee_id', $employee_id)->where('status', 'open')->get(db_prefix() . 'pos_shifts')->row_array();
    }

    public function get_open_shift_for_warehouse($warehouse_id)
    {
        return $this->db->where('warehouse_id', $warehouse_id)->where('status', 'open')->get(db_prefix() . 'pos_shifts')->row_array();
    }

    public function open_shift($data)
    {
        $shift_code = 'SHF-' . strtoupper(uniqid());
        $this->db->insert(db_prefix() . 'pos_shifts', [
            'warehouse_id' => $data['warehouse_id'],
            'employee_id' => $data['employee_id'] ?? null,
            'shift_code' => $shift_code,
            'opening_float' => $data['opening_float'] ?? 0,
            'status' => 'open',
            'opened_at' => date('Y-m-d H:i:s'),
        ]);
        $id = $this->db->insert_id();
        return $id ? $this->get_shift($id) : false;
    }

    public function get_shift($id)
    {
        $shift = $this->db->get_where(db_prefix() . 'pos_shifts', ['id' => $id])->row_array();
        if (!$shift)
            return null;
        $shift['cash_movements'] = $this->db->where('shift_id', $id)->order_by('created_at', 'ASC')->get(db_prefix() . 'pos_shift_cash_movements')->result_array();
        return $shift;
    }

    public function add_cash_movement($shift_id, $data)
    {
        $this->db->insert(db_prefix() . 'pos_shift_cash_movements', [
            'shift_id' => $shift_id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    public function close_shift($shift_id, $data)
    {
        $shift = $this->get_shift($shift_id);
        if (!$shift || $shift['status'] !== 'open')
            return false;

        // Sum pay-ins and pay-outs from cash movements
        $pay_ins = 0;
        $pay_outs = 0;
        foreach ($shift['cash_movements'] as $m) {
            if ($m['type'] === 'pay_in')
                $pay_ins += (float) $m['amount'];
            if ($m['type'] === 'pay_out')
                $pay_outs += (float) $m['amount'];
        }

        // Cash sales and cash refunds for this shift
        $cash_sales = (float) $this->db->select('SUM(rp.money_amount - rp.cash_back) as money_amount', FALSE)
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'SALE')
            ->get()->row()->money_amount;

        $cash_refunds = (float) $this->db->select('SUM(rp.money_amount) as money_amount', FALSE)
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'REFUND')
            ->get()->row()->money_amount;

        $expected_cash = (float) $shift['opening_float'] + $pay_ins - $pay_outs + $cash_sales - $cash_refunds;
        $actual_cash = (float) ($data['actual_cash'] ?? 0);
        $difference = $actual_cash - $expected_cash;

        // Aggregate totals from receipts in this shift
        $summary = $this->db->select('SUM(total_money) as total_sales, SUM(total_discount) as total_discounts, SUM(total_tax) as total_tax, SUM(tip) as total_tips, COUNT(*) as transaction_count')
            ->where('shift_id', $shift_id)
            ->where('receipt_type', 'SALE')
            ->where('cancelled_at IS NULL')
            ->get(db_prefix() . 'pos_receipts')->row_array();

        $refund_total = (float) $this->db->select_sum('amount')->where('receipt_id IN (SELECT id FROM `' . db_prefix() . 'pos_receipts` WHERE shift_id = ' . (int) $shift_id . ')')->get(db_prefix() . 'pos_refunds')->row()->amount;

        $cash_rounded = (float) $this->db->select_sum('surcharge')
            ->where('shift_id', $shift_id)
            ->where('receipt_type', 'SALE')
            ->where('cancelled_at IS NULL')
            ->get(db_prefix() . 'pos_receipts')->row()->surcharge;

        $cancelled = $this->db->select('COUNT(*) as cnt, SUM(total_money) as amount')
            ->where('shift_id', $shift_id)
            ->where('cancelled_at IS NOT NULL')
            ->get(db_prefix() . 'pos_receipts')->row_array();

        $this->db->where('id', $shift_id)->update(db_prefix() . 'pos_shifts', [
            'closed_by_employee_id' => $data['employee_id'] ?? null,
            'closing_float' => $actual_cash,
            'expected_cash' => round($expected_cash, 2),
            'actual_cash' => $actual_cash,
            'difference' => round($difference, 2),
            'total_sales' => round((float) $summary['total_sales'], 2),
            'total_refunds' => round($refund_total, 2),
            'total_discounts' => round((float) $summary['total_discounts'], 2),
            'total_tax' => round((float) $summary['total_tax'], 2),
            'total_tips' => round((float) $summary['total_tips'], 2),
            'cash_rounded' => round($cash_rounded, 2),
            'transaction_count' => (int) $summary['transaction_count'],
            'cancelled_count' => (int) $cancelled['cnt'],
            'cancelled_amount' => round((float) $cancelled['amount'], 2),
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
            'notes' => $data['notes'] ?? null,
        ]);

        $closed = $this->get_shift($shift_id);

        // Auto-create accounting journal entry if configured
        if ($closed) {
            $this->create_shift_accounting_entry($shift_id);
        }

        return $closed;
    }

    public function get_shift_report($shift_id)
    {
        $shift = $this->get_shift($shift_id);
        if (!$shift)
            return null;

        // Totals by payment type, split by SALE vs REFUND
        // Group by type+name because payment_type_id is not reliably unique across methods
        $by_payment_raw = $this->db->select('rp.type as payment_type, rp.payment_name, r.receipt_type, SUM(rp.money_amount - rp.cash_back) as total, COUNT(*) as transactions')
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->group_by('rp.type, rp.payment_name, r.receipt_type')
            ->get()->result_array();

        $by_payment = [];
        foreach ($by_payment_raw as $row) {
            $key = $row['payment_type'] . '|' . $row['payment_name'];
            if (!isset($by_payment[$key])) {
                $by_payment[$key] = [
                    'payment_name' => $row['payment_name'],
                    'payment_type' => $row['payment_type'],
                    'sales_total' => 0,
                    'sales_count' => 0,
                    'refunds_total' => 0,
                    'refunds_count' => 0,
                ];
            }
            if ($row['receipt_type'] === 'SALE') {
                $by_payment[$key]['sales_total'] = (float) $row['total'];
                $by_payment[$key]['sales_count'] = (int) $row['transactions'];
            } else {
                $by_payment[$key]['refunds_total'] = (float) $row['total'];
                $by_payment[$key]['refunds_count'] = (int) $row['transactions'];
            }
        }
        $by_payment = array_values($by_payment);

        // Cash-only totals for reconciliation display
        $cash_sales_total = (float) $this->db->select('SUM(rp.money_amount - rp.cash_back) as money_amount', FALSE)
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'SALE')
            ->get()->row()->money_amount;

        $cash_refunds_total = (float) $this->db->select('SUM(rp.money_amount) as money_amount', FALSE)
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'REFUND')
            ->get()->row()->money_amount;

        // Top items
        $top_items = $this->db->select('li.item_name, SUM(li.quantity) as qty_sold, SUM(li.total_money) as revenue')
            ->from(db_prefix() . 'pos_receipt_line_items li')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = li.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.receipt_type', 'SALE')
            ->where('r.cancelled_at IS NULL')
            ->group_by('li.item_id')
            ->order_by('qty_sold', 'DESC')
            ->limit(10)
            ->get()->result_array();

        // Hourly breakdown
        $hourly = $this->db->select('HOUR(receipt_date) as hour, COUNT(*) as transactions, SUM(total_money) as revenue')
            ->where('shift_id', $shift_id)
            ->where('receipt_type', 'SALE')
            ->where('cancelled_at IS NULL')
            ->group_by('HOUR(receipt_date)')
            ->order_by('hour', 'ASC')
            ->get(db_prefix() . 'pos_receipts')->result_array();

        $pay_ins = 0;
        $pay_outs = 0;
        foreach ($shift['cash_movements'] as $m) {
            if ($m['type'] === 'pay_in')
                $pay_ins += (float) $m['amount'];
            if ($m['type'] === 'pay_out')
                $pay_outs += (float) $m['amount'];
        }
        $computed_expected_cash = round((float) $shift['opening_float'] + $pay_ins - $pay_outs + $cash_sales_total - $cash_refunds_total, 2);

        return [
            'shift' => $shift,
            'by_payment_type' => $by_payment,
            'top_items' => $top_items,
            'hourly_breakdown' => $hourly,
            'total_sales' => $shift['total_sales'],
            'total_refunds' => $shift['total_refunds'],
            'total_discounts' => $shift['total_discounts'],
            'total_tax' => $shift['total_tax'],
            'cash_rounded' => $shift['cash_rounded'] ?? 0,
            'transaction_count' => $shift['transaction_count'],
            'cancelled_count' => $shift['cancelled_count'] ?? 0,
            'cancelled_amount' => $shift['cancelled_amount'] ?? 0,
            'net_sales' => round((float) $shift['total_sales'] - (float) $shift['total_refunds'], 2),
            'cash_sales' => $cash_sales_total,
            'cash_refunds' => $cash_refunds_total,
            'expected_cash' => $computed_expected_cash,
            'difference' => round((float) $shift['actual_cash'] - $computed_expected_cash, 2),
        ];
    }

    // -------------------------------------------------------------------------
    // Customers / Loyalty
    // -------------------------------------------------------------------------

    public function search_customers($q)
    {
        $this->db->select('c.userid as id, c.company as name, ct.phonenumber as phone, ct.email, lc.total_points, lc.total_spent, lc.qr_token')
            ->from(db_prefix() . 'clients c')
            ->join(db_prefix() . 'contacts ct', 'ct.userid = c.userid', 'left')
            ->join(db_prefix() . 'pos_loyalty_customers lc', 'lc.client_id = c.userid', 'left')
            ->group_start()
            ->like('c.company', $q)
            ->or_like('ct.phonenumber', $q)
            ->or_like('ct.email', $q)
            ->or_like('ct.firstname', $q)
            ->or_like('ct.lastname', $q)
            ->group_end()
            ->group_by('c.userid');
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$row) {
            $row['loyalty_tier'] = $this->_get_loyalty_tier((float) ($row['total_points'] ?? 0));
        }
        return $rows;
    }

    public function get_customer($client_id)
    {
        $this->db->select('c.userid as id, c.company as name, ct.phonenumber as phone, ct.email, lc.*')
            ->from(db_prefix() . 'clients c')
            ->join(db_prefix() . 'contacts ct', 'ct.userid = c.userid', 'left')
            ->join(db_prefix() . 'pos_loyalty_customers lc', 'lc.client_id = c.userid', 'left')
            ->where('c.userid', $client_id)
            ->group_by('c.userid');
        $customer = $this->db->get()->row_array();
        if (!$customer)
            return null;

        $customer['loyalty_tier'] = $this->_get_loyalty_tier((float) ($customer['total_points'] ?? 0));
        $customer['recent_visits'] = $this->db->select('r.*')
            ->where('r.customer_id', $client_id)
            ->where('r.receipt_type', 'SALE')
            ->order_by('r.receipt_date', 'DESC')
            ->limit(10)
            ->get(db_prefix() . 'pos_receipts r')->result_array();
        return $customer;
    }

    public function create_customer($data)
    {
        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'clients', [
            'company' => $data['name'],
            'phonenumber' => $data['phone'] ?? null,
            'active' => 1,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        $client_id = $this->db->insert_id();

        $this->db->insert(db_prefix() . 'contacts', [
            'userid' => $client_id,
            'firstname' => $data['name'],
            'lastname' => '',
            'email' => $data['email'] ?? null,
            'phonenumber' => $data['phone'] ?? null,
            'is_primary' => 1,
        ]);

        $qr_token = $this->_generate_qr_token();
        $this->db->insert(db_prefix() . 'pos_loyalty_customers', [
            'client_id' => $client_id,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'name' => $data['name'],
            'qr_token' => $qr_token,
            'registered_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->trans_complete();
        if ($this->db->trans_status() === false)
            return false;
        return $this->get_customer($client_id);
    }

    // -------------------------------------------------------------------------
    // Loyalty
    // -------------------------------------------------------------------------

    public function get_loyalty_members($search = '', $limit = 50, $offset = 0)
    {
        $this->db->select('lc.*, ct.email as client_email, ct.phonenumber as client_phone')
            ->from(db_prefix() . 'pos_loyalty_customers lc')
            ->join(db_prefix() . 'contacts ct', 'ct.userid = lc.client_id', 'left')
            ->order_by('lc.registered_at', 'DESC')
            ->limit((int) $limit, (int) $offset);

        if (!empty($search)) {
            $this->db->group_start()
                ->like('lc.name', $search)
                ->or_like('lc.phone', $search)
                ->or_like('lc.email', $search)
                ->group_end();
        }

        $rows = $this->db->get()->result_array();
        foreach ($rows as &$row) {
            $row['loyalty_tier'] = $this->_get_loyalty_tier((float) ($row['total_points'] ?? 0));
        }

        $this->db->from(db_prefix() . 'pos_loyalty_customers lc');
        if (!empty($search)) {
            $this->db->group_start()
                ->like('lc.name', $search)
                ->or_like('lc.phone', $search)
                ->or_like('lc.email', $search)
                ->group_end();
        }
        $total = $this->db->count_all_results();

        return ['data' => $rows, 'total' => $total];
    }

    public function get_loyalty_balance($customer_id)
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();
        if (!$lc)
            return null;
        $lc['loyalty_tier'] = $this->_get_loyalty_tier((float) $lc['total_points']);
        return $lc;
    }

    public function earn_points($customer_id, $receipt_id, $amount_spent, $warehouse_id = null)
    {
        $points = round((float) $amount_spent * 0.10, 2);
        $lc = $this->db->select('total_points')->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();
        $balance_after = round((float) ($lc['total_points'] ?? 0) + $points, 2);
        $tier = $this->_get_loyalty_tier($balance_after);

        $this->db->trans_start();
        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id' => $customer_id,
            'receipt_id' => $receipt_id,
            'warehouse_id' => $warehouse_id ? (int) $warehouse_id : null,
            'type' => 'earn',
            'points' => $points,
            'balance_after' => $balance_after,
            'tier_name' => $tier ? $tier['name'] : null,
            'description' => 'Earned from purchase',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->set('total_points', 'total_points + ' . (float) $points, false)
            ->set('total_spent', 'total_spent + ' . (float) $amount_spent, false)
            ->set('last_visit', date('Y-m-d H:i:s'))
            ->where('id', $customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');
        $this->db->trans_complete();
        if ($this->db->trans_status() === false)
            return false;
        return $points;
    }

    public function redeem_points($customer_id, $receipt_id, $points, $warehouse_id = null)
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();
        if (!$lc || (float) $lc['total_points'] < (float) $points)
            return false;

        $balance_after = round((float) $lc['total_points'] - (float) $points, 2);
        $tier = $this->_get_loyalty_tier($balance_after);

        $this->db->trans_start();
        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id' => $customer_id,
            'receipt_id' => $receipt_id,
            'warehouse_id' => $warehouse_id ? (int) $warehouse_id : null,
            'type' => 'redeem',
            'points' => $points,
            'balance_after' => $balance_after,
            'tier_name' => $tier ? $tier['name'] : null,
            'description' => 'Redeemed at POS',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->set('total_points', 'total_points - ' . (float) $points, false)
            ->where('id', $customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');
        $this->db->trans_complete();
        if ($this->db->trans_status() === false)
            return false;

        return ['points_redeemed' => $points, 'points_value_in_currency' => $points];
    }

    public function get_loyalty_customer_by_qr($token)
    {
        return $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['qr_token' => $token])->row_array();
    }

    public function get_receipt_by_cashback_token($token)
    {
        return $this->db->get_where(db_prefix() . 'pos_receipts', ['cashback_qr_token' => $token])->row_array();
    }

    public function loyalty_register_from_qr($token, $name, $phone, $email)
    {
        // Check if token is a receipt cashback token
        $receipt = $this->get_receipt_by_cashback_token($token);
        $points_earned = 0;

        // Find existing customer by phone/email
        $existing_lc = null;
        if ($phone) {
            $existing_lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['phone' => $phone])->row_array();
        }
        if (!$existing_lc && $email) {
            $existing_lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['email' => $email])->row_array();
        }

        if ($existing_lc) {
            $customer_id = $existing_lc['id'];
        } else {
            $result = $this->create_customer(['name' => $name, 'phone' => $phone, 'email' => $email]);
            if (!$result)
                return false;
            $new_lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['client_id' => $result['id']])->row_array();
            $customer_id = $new_lc['id'];
        }

        if ($receipt && empty($receipt['loyalty_customer_id'])) {
            // Associate receipt with this loyalty customer and award points
            $this->db->where('id', $receipt['id'])->update(db_prefix() . 'pos_receipts', ['loyalty_customer_id' => $customer_id]);
            $points_earned = $this->earn_points($customer_id, $receipt['id'], $receipt['total_money']);
        }

        $customer = $this->get_loyalty_balance($customer_id);
        return ['customer' => $customer, 'points_earned' => $points_earned ?: 0, 'message' => 'Registration successful'];
    }

    private function _get_loyalty_tier($points)
    {
        $tiers = $this->db->order_by('minimum_number_of_points', 'DESC')->get(db_prefix() . 'ma_point_triggers')->result_array();
        foreach ($tiers as $tier) {
            if ($points >= (float) $tier['minimum_number_of_points'])
                return $tier;
        }
        return null;
    }

    private function _generate_qr_token()
    {
        do {
            $token = bin2hex(random_bytes(32));
            $exists = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['qr_token' => $token])->row();
        } while ($exists);
        return $token;
    }

    // -------------------------------------------------------------------------
    // Receipts
    // -------------------------------------------------------------------------

    public function get_receipts($warehouse_id = null, $date_from = null, $date_to = null)
    {
        if ($warehouse_id)
            $this->db->where('warehouse_id', $warehouse_id);
        if ($date_from)
            $this->db->where('receipt_date >=', $date_from);
        if ($date_to)
            $this->db->where('receipt_date <=', $date_to);
        $receipts = $this->db->order_by('receipt_date', 'DESC')->get(db_prefix() . 'pos_receipts')->result_array();
        foreach ($receipts as &$receipt) {
            $receipt = $this->_attach_receipt_details($receipt);
        }
        return $receipts;
    }

    public function get_receipt($receipt_number)
    {
        $pfx = db_prefix();
        $receipt = $this->db
            ->select('r.*, w.warehouse_name, e.name as employee_name')
            ->from($pfx . 'pos_receipts r')
            ->join($pfx . 'warehouse w', 'w.warehouse_id = r.warehouse_id', 'left')
            ->join($pfx . 'pos_employees e', 'e.id = r.employee_id', 'left')
            ->where('r.receipt_number', $receipt_number)
            ->get()->row_array();
        return $receipt ? $this->_attach_receipt_details($receipt) : null;
    }

    public function get_receipt_by_id($id)
    {
        $pfx = db_prefix();
        $receipt = $this->db
            ->select('r.*, w.warehouse_name, e.name as employee_name')
            ->from($pfx . 'pos_receipts r')
            ->join($pfx . 'warehouse w', 'w.warehouse_id = r.warehouse_id', 'left')
            ->join($pfx . 'pos_employees e', 'e.id = r.employee_id', 'left')
            ->where('r.id', $id)
            ->get()->row_array();
        return $receipt ? $this->_attach_receipt_details($receipt) : null;
    }

    public function get_receipt_line_items($receipt_id)
    {
        return $this->db
            ->where('receipt_id', $receipt_id)
            ->get(db_prefix() . 'pos_receipt_line_items')
            ->result_array();
    }

    public function cancel_receipt($id, $reason = null, $employee_id = null)
    {
        $receipt = $this->get_receipt_by_id($id);
        if (!$receipt || !empty($receipt['cancelled_at'])) {
            return false;
        }

        $this->db->trans_start();
        $this->db->where('id', $id)->update(db_prefix() . 'pos_receipts', [
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancellation_reason' => $reason ?: null,
            'cancelled_by_employee_id' => $employee_id ? (int) $employee_id : null,
        ]);
        $this->restore_receipt_inventory_deductions((int) $id);
        $this->db->trans_complete();

        return $this->db->trans_status() !== false;
    }

    // -------------------------------------------------------------------------
    // Print Jobs — queued when a delivery-platform order is accepted (see
    // Pos_grabfood_model::handle_order_state_update / accept_order). The Flutter POS
    // polls get_pending_print_jobs() and acks each one after printing.
    // -------------------------------------------------------------------------

    public function get_pending_print_jobs($warehouse_id)
    {
        return $this->db
            ->where('warehouse_id', (int) $warehouse_id)
            ->where('status', 'pending')
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_print_jobs')
            ->result_array();
    }

    public function ack_print_job($id, $status, $error = null)
    {
        $job = $this->db->where('id', (int) $id)->get(db_prefix() . 'pos_print_jobs')->row_array();
        if (!$job) {
            return false;
        }

        if ($status === 'printed') {
            $this->db->where('id', $id)->update(db_prefix() . 'pos_print_jobs', [
                'status' => 'printed',
                'printed_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        }

        $attempts = (int) $job['attempts'] + 1;
        $this->db->where('id', $id)->update(db_prefix() . 'pos_print_jobs', [
            'attempts' => $attempts,
            'status' => $attempts >= 3 ? 'failed' : 'pending',
            'last_error' => $error ?: 'Print failed',
        ]);
        return true;
    }

    private function _attach_receipt_details($receipt)
    {
        $line_items = $this->db->where('receipt_id', $receipt['id'])->get(db_prefix() . 'pos_receipt_line_items')->result_array();
        foreach ($line_items as &$item) {
            $item['modifier_ids'] = json_decode($item['modifier_ids'] ?? '[]', true) ?: [];
            $item['modifier_names'] = json_decode($item['modifier_names'] ?? '[]', true) ?: [];
            $item['tax_ids'] = json_decode($item['tax_ids'] ?? '[]', true) ?: [];
        }
        $receipt['line_items'] = $line_items;
        $receipt['payments'] = $this->db->where('receipt_id', $receipt['id'])->get(db_prefix() . 'pos_receipt_payments')->result_array();
        $receipt['status'] = $this->_receipt_status($receipt);
        $receipt['grabfood_price'] = ($receipt['source'] ?? '') === 'GRABFOOD'
            ? $this->_get_grabfood_price_breakdown($receipt['id'])
            : null;

        // What the print template should show in the "collection number" slot — Grab (and
        // future delivery platforms) print their own short order number there instead of the
        // dine-in queue number. Same field name either way, so printing logic doesn't branch.
        $receipt['print_collection_number'] = $receipt['queue_number'] ?? $receipt['receipt_number'];

        return $receipt;
    }

    // Pulls the original GrabFood "price" breakdown (delivery fee, service charge, etc.) out of
    // the raw webhook payload so it can be shown alongside the receipt totals — those fields
    // aren't part of pos_receipts since walk-in sales don't have them.
    private function _get_grabfood_price_breakdown($receipt_id)
    {
        $gf = $this->db
            ->select('raw_payload')
            ->where('receipt_id', $receipt_id)
            ->get(db_prefix() . 'pos_grabfood_orders')
            ->row_array();

        if (!$gf || empty($gf['raw_payload']))
            return null;

        $order = json_decode($gf['raw_payload'], true);
        $price = $order['price'] ?? null;
        if (!$price)
            return null;

        $exponent = (int) ($order['currency']['exponent'] ?? 2);
        $shift = function ($value) use ($exponent) {
            return round(((float) $value) / (10 ** max(0, $exponent)), 2);
        };

        return [
            'delivery_fee' => $shift($price['deliveryFee'] ?? 0),
            'service_charge_fee' => $shift($price['serviceChargeFee'] ?? 0),
            'small_order_fee' => $shift($price['smallOrderFee'] ?? 0),
            'merchant_charge_fee_min' => $shift($price['merchantChargeFeeInMin'] ?? 0),
        ];
    }

    private function _receipt_status($receipt)
    {
        if (!empty($receipt['cancelled_at']))
            return 'cancelled';
        if (!empty($receipt['refund_for']))
            return 'return';
        if ($receipt['receipt_type'] === 'REFUNDED')
            return 'refunded';
        return 'completed';
    }

    public function create_receipt($data)
    {
        $this->last_inventory_error = null;
        $this->db->trans_start();
        $inventory_ok = true;

        $receipt_number = 'RCP-' . strtoupper(uniqid());
        $cashback_qr_token = bin2hex(random_bytes(32));

        $this->db->insert(db_prefix() . 'pos_receipts', [
            'receipt_number' => $receipt_number,
            'queue_number' => isset($data['queue_number']) ? (string) $data['queue_number'] : null,
            'receipt_type' => $data['receipt_type'] ?? 'SALE',
            'refund_for' => $data['refund_for'] ?? null,
            'warehouse_id' => $data['warehouse_id'],
            'employee_id' => $data['employee_id'] ?? null,
            'shift_id' => $data['shift_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'loyalty_customer_id' => $data['loyalty_customer_id'] ?? null,
            'cashback_qr_token' => $cashback_qr_token,
            'note' => $data['note'] ?? null,
            'dining_option' => $data['dining_option'] ?? null,
            'source' => $data['source'] ?? 'POS',
            'subtotal' => $data['subtotal'] ?? 0,
            'total_discount' => $data['total_discount'] ?? 0,
            'total_tax' => $data['total_tax'] ?? 0,
            'tip' => $data['tip'] ?? 0,
            'surcharge' => $data['surcharge'] ?? 0,
            'total_money' => $data['total_money'] ?? 0,
            'points_earned' => $data['points_earned'] ?? 0,
            'points_deducted' => $data['points_deducted'] ?? 0,
            'receipt_date' => !empty($data['receipt_date']) ? $data['receipt_date'] : date('Y-m-d H:i:s'),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);
        $receipt_id = $this->db->insert_id();

        if ($receipt_id) {
            // Resolve category_id / category_name from items → wh_sub_group for all line items at once
            $item_ids = array_filter(array_map(function ($li) {
                return (int) ($li['item_id'] ?? 0);
            }, $data['line_items'] ?? []));
            $category_map = [];
            if ($item_ids) {
                $cat_rows = $this->db
                    ->select('i.id AS item_id, i.sub_group AS category_id, sg.sub_group_name AS category_name')
                    ->from(db_prefix() . 'items i')
                    ->join(db_prefix() . 'wh_sub_group sg', 'sg.id = i.sub_group', 'left')
                    ->where_in('i.id', array_values($item_ids))
                    ->get()->result_array();
                foreach ($cat_rows as $cr) {
                    $category_map[(int) $cr['item_id']] = [
                        'category_id' => $cr['category_id'] ? (int) $cr['category_id'] : null,
                        'category_name' => $cr['category_name'] ?: null,
                    ];
                }
            }

            foreach ($data['line_items'] ?? [] as $item) {
                $cat = $category_map[(int) ($item['item_id'] ?? 0)] ?? ['category_id' => null, 'category_name' => null];
                $this->db->insert(db_prefix() . 'pos_receipt_line_items', [
                    'receipt_id' => $receipt_id,
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'category_id' => $cat['category_id'],
                    'category_name' => $cat['category_name'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'variant_name' => $item['variant_name'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'cost' => $item['cost'] ?? 0,
                    'gross_total' => $item['gross_total'] ?? 0,
                    'total_discount' => $item['total_discount'] ?? 0,
                    'total_tax' => $item['total_tax'] ?? 0,
                    'total_money' => $item['total_money'] ?? 0,
                    'modifier_ids' => json_encode($item['modifier_ids'] ?? []),
                    'modifier_names' => json_encode($item['modifier_names'] ?? []),
                    'modifiers_price' => $item['modifiers_price'] ?? 0,
                    'tax_ids' => json_encode($item['tax_ids'] ?? []),
                    'line_note' => $item['line_note'] ?? null,
                    'promotion_id' => isset($item['promotion_id']) ? (int) $item['promotion_id'] : null,
                    'discount_type' => $item['discount_type'] ?? null,
                ]);

                $receipt_line_item_id = (int) $this->db->insert_id();
                if ($receipt_line_item_id) {
                    $deductions = $this->_prepare_receipt_line_inventory_deductions((int) $data['warehouse_id'], $item);
                    if ($deductions) {
                        $applied = $this->_apply_receipt_line_inventory_deductions($receipt_id, $receipt_line_item_id, (int) $data['warehouse_id'], $deductions);
                        if ($applied === false) {
                            $inventory_ok = false;
                            break;
                        }
                    }
                }
            }

            foreach ($data['payments'] ?? [] as $payment) {
                $this->db->insert(db_prefix() . 'pos_receipt_payments', [
                    'receipt_id' => $receipt_id,
                    'payment_type_id' => $payment['payment_type_id'],
                    'payment_name' => $payment['payment_name'],
                    'type' => $payment['type'] ?? 'CASH',
                    'money_amount' => $payment['money_amount'] ?? 0,
                    'cash_back' => $payment['cash_back'] ?? 0,
                    'payment_date' => date('Y-m-d H:i:s'),
                ]);
            }

            // Auto-earn loyalty points if loyalty_customer_id provided
            if (!empty($data['loyalty_customer_id']) && !empty($data['total_money'])) {
                $this->earn_points($data['loyalty_customer_id'], $receipt_id, $data['total_money'], $data['warehouse_id'] ?? null);
            }
        }

        $this->db->trans_complete();
        if (!$inventory_ok || $this->db->trans_status() === false || !$receipt_id)
            return false;

        return [
            'receipt_number' => $receipt_number,
            'cashback_qr_url' => 'https://loyalty.kokonuts.my/claim/' . $cashback_qr_token,
            'cashback_qr_token' => $cashback_qr_token,
        ];
    }

    // =========================================================================
    // Transactions (back-office list)
    // =========================================================================

    public function get_transactions($filters = [])
    {
        $warehouse_id = $filters['warehouse_id'] ?? null;
        $date_from = $filters['date_from'] ?? null;
        $date_to = $filters['date_to'] ?? null;
        $search = trim($filters['search'] ?? '');
        $shift_id = $filters['shift_id'] ?? null;
        $payment_mode = trim($filters['payment_mode'] ?? '');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(10, (int) ($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $allowed_sort = [
            'receipt_date' => 'r.receipt_date',
            'warehouse_name' => 'w.warehouse_name',
            'subtotal' => 'items_subtotal',
            'total_discount' => 'r.total_discount',
            'delivery_fee' => 'delivery_fee',
            'total_money' => 'r.total_money',
            'payment_method' => 'payment_method',
        ];
        $sort_col = $allowed_sort[$filters['sort'] ?? ''] ?? 'r.receipt_date';
        $sort_dir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $this->_build_transactions_query($warehouse_id, $date_from, $date_to, $search, $shift_id, $payment_mode);
        $total = $this->db->count_all_results('', false);

        $pfx = db_prefix();
        $rows = $this->db
            ->select("r.id, r.receipt_number, r.queue_number, r.receipt_type, r.refund_for, r.cancelled_at, r.shift_id, r.warehouse_id, r.employee_id, r.dining_option, r.source, r.subtotal, r.total_discount, r.total_tax, r.tip, r.surcharge, r.total_money, r.receipt_date, w.warehouse_name, e.name as employee_name,
                (SELECT p.payment_name FROM {$pfx}pos_receipt_payments p WHERE p.receipt_id = r.id ORDER BY p.id ASC LIMIT 1) AS payment_method,
                (SELECT p.type        FROM {$pfx}pos_receipt_payments p WHERE p.receipt_id = r.id ORDER BY p.id ASC LIMIT 1) AS payment_type,
                (SELECT COALESCE(SUM(li.gross_total + li.modifiers_price), 0) FROM {$pfx}pos_receipt_line_items li WHERE li.receipt_id = r.id) AS items_subtotal,
                (SELECT g.delivery_fee   FROM {$pfx}pos_grabfood_orders g WHERE g.receipt_id = r.id LIMIT 1) AS delivery_fee,
                (SELECT g.order_status   FROM {$pfx}pos_grabfood_orders g WHERE g.receipt_id = r.id LIMIT 1) AS gf_order_status", false)
            ->order_by($sort_col, $sort_dir)
            ->limit($limit, $offset)
            ->get()->result_array();

        foreach ($rows as &$row) {
            $row['status'] = $this->_receipt_status($row);
        }

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'page_count' => (int) ceil($total / max(1, $limit)),
        ];
    }

    private function _build_transactions_query($warehouse_id, $date_from, $date_to, $search, $shift_id = null, $payment_mode = null)
    {
        $pfx = db_prefix();
        $this->db
            ->from($pfx . 'pos_receipts r')
            ->join($pfx . 'warehouse w', 'w.warehouse_id = r.warehouse_id', 'left')
            ->join($pfx . 'pos_employees e', 'e.id = r.employee_id', 'left');

        if ($warehouse_id)
            $this->db->where('r.warehouse_id', (int) $warehouse_id);
        if ($date_from)
            $this->db->where('r.receipt_date >=', $date_from . ' 00:00:00');
        if ($date_to)
            $this->db->where('r.receipt_date <=', $date_to . ' 23:59:59');
        if ($search)
            $this->db->like('r.receipt_number', $search, 'both');
        if ($shift_id)
            $this->db->where('r.shift_id', (int) $shift_id);
        if ($payment_mode)
            $this->db->where("EXISTS (SELECT 1 FROM {$pfx}pos_receipt_payments p_f WHERE p_f.receipt_id = r.id AND p_f.type = " . $this->db->escape($payment_mode) . ")", null, false);
    }

    // =========================================================================
    // Receipt Settings
    // =========================================================================

    public function get_receipt_settings($warehouse_id)
    {
        return $this->db
            ->where('warehouse_id', (int) $warehouse_id)
            ->get(db_prefix() . 'pos_receipt_settings')
            ->row_array();
    }

    public function save_receipt_settings($warehouse_id, $data)
    {
        $warehouse_id = (int) $warehouse_id;
        $exists = $this->db
            ->where('warehouse_id', $warehouse_id)
            ->count_all_results(db_prefix() . 'pos_receipt_settings');

        if ($exists) {
            $this->db->where('warehouse_id', $warehouse_id)
                ->update(db_prefix() . 'pos_receipt_settings', $data);
        } else {
            $data['warehouse_id'] = $warehouse_id;
            $this->db->insert(db_prefix() . 'pos_receipt_settings', $data);
        }
        return true;
    }

    // =========================================================================
    // Customer Facing Display (CFD) Settings
    // =========================================================================

    public function get_cfd_settings($warehouse_id)
    {
        return $this->db
            ->where('warehouse_id', (int) $warehouse_id)
            ->get(db_prefix() . 'pos_cfd_settings')
            ->row_array();
    }

    public function save_cfd_settings($warehouse_id, $data)
    {
        $warehouse_id = (int) $warehouse_id;
        $exists = $this->db
            ->where('warehouse_id', $warehouse_id)
            ->count_all_results(db_prefix() . 'pos_cfd_settings');

        if ($exists) {
            $this->db->where('warehouse_id', $warehouse_id)
                ->update(db_prefix() . 'pos_cfd_settings', $data);
        } else {
            $data['warehouse_id'] = $warehouse_id;
            $this->db->insert(db_prefix() . 'pos_cfd_settings', $data);
        }
        return true;
    }

    public function get_cfd_media_items($warehouse_id)
    {
        return $this->db
            ->where('warehouse_id', (int) $warehouse_id)
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_cfd_media_items')
            ->result_array();
    }

    public function add_cfd_media_item($warehouse_id, $data)
    {
        $next_order = (int) $this->db
            ->select_max('sort_order')
            ->where('warehouse_id', (int) $warehouse_id)
            ->get(db_prefix() . 'pos_cfd_media_items')
            ->row()->sort_order + 1;

        $data['warehouse_id'] = (int) $warehouse_id;
        $data['sort_order'] = $next_order;
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'pos_cfd_media_items', $data);
        return $this->db->insert_id();
    }

    public function delete_cfd_media_item($id, $warehouse_id)
    {
        return $this->db
            ->where('id', (int) $id)
            ->where('warehouse_id', (int) $warehouse_id)
            ->delete(db_prefix() . 'pos_cfd_media_items');
    }

    public function reorder_cfd_media_items($warehouse_id, array $ordered_ids)
    {
        foreach ($ordered_ids as $i => $id) {
            $this->db
                ->where('id', (int) $id)
                ->where('warehouse_id', (int) $warehouse_id)
                ->update(db_prefix() . 'pos_cfd_media_items', ['sort_order' => $i]);
        }
        return true;
    }

    public function delete_transaction($id)
    {
        $id = (int) $id;
        if (!$id)
            return false;

        $receipt = $this->db->get_where(db_prefix() . 'pos_receipts', ['id' => $id])->row_array();
        if (!$receipt)
            return false;

        $this->db->trans_start();
        $this->db->where('receipt_id', $id)->delete(db_prefix() . 'pos_receipt_line_items');
        $this->db->where('receipt_id', $id)->delete(db_prefix() . 'pos_receipt_payments');
        $this->db->where('receipt_id', $id)->delete(db_prefix() . 'pos_refunds');
        $this->db->where('receipt_id', $id)->delete(db_prefix() . 'pos_loyalty_transactions');
        $this->db->where('receipt_id', $id)->delete(db_prefix() . 'pos_print_jobs');

        // GrabFood-specific cleanup: remove order items and the grabfood order row itself
        if ($receipt['source'] === 'GRABFOOD') {
            $gf_orders = $this->db->select('grabfood_order_id')
                ->where('receipt_id', $id)
                ->get(db_prefix() . 'pos_grabfood_orders')
                ->result_array();
            foreach ($gf_orders as $gf) {
                $this->db->where('grabfood_order_id', $gf['grabfood_order_id'])
                    ->delete(db_prefix() . 'pos_grabfood_order_items');
            }
            $this->db->where('receipt_id', $id)->delete(db_prefix() . 'pos_grabfood_orders');
        }

        $this->db->where('id', $id)->delete(db_prefix() . 'pos_receipts');
        $this->db->trans_complete();

        return $this->db->trans_status() !== false;
    }

    public function create_refund($data)
    {
        $this->db->trans_start();

        $refund_receipt_number = 'RFD-' . strtoupper(uniqid());
        $this->db->insert(db_prefix() . 'pos_refunds', [
            'receipt_id' => $data['receipt_id'],
            'refund_receipt_number' => $refund_receipt_number,
            'employee_id' => $data['employee_id'] ?? null,
            'payment_type_id' => $data['payment_type_id'] ?? null,
            'amount' => $data['amount'] ?? 0,
            'note' => $data['note'] ?? null,
            'refunded_at' => date('Y-m-d H:i:s'),
        ]);
        $refund_id = $this->db->insert_id();
        if (!$refund_id)
            return false;

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $this->db->insert(db_prefix() . 'pos_refund_items', [
                    'refund_id' => $refund_id,
                    'line_item_id' => $item['line_item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_money' => $item['total_money'],
                ]);
            }
        }

        $this->db->where('id', $data['receipt_id'])->update(db_prefix() . 'pos_receipts', ['receipt_type' => 'REFUNDED']);
        $this->restore_receipt_inventory_deductions((int) $data['receipt_id'], $data['items'] ?? []);

        $this->db->trans_complete();

        return $this->db->trans_status() === false ? false : $refund_id;
    }

    // -------------------------------------------------------------------------
    // POS Product CRUD
    // -------------------------------------------------------------------------

    public function get_pos_product($id)
    {
        $item = $this->db
            ->select('i.id, i.sku_name, i.sku_code, i.description, i.image, i.rate, i.group_id, i.sub_group, i.active, i.fd_available, i.fd_price, i.fd_available_published, i.fd_price_published')
            ->from(db_prefix() . 'items i')
            ->where('i.id', (int) $id)
            ->where('i.can_be_sold', 'can_be_sold')
            ->where('i.can_be_manufacturing', 'can_be_manufacturing')
            ->where('i.parent_id IS NULL', null, false)
            ->get()->row_array();
        if ($item) {
            $item['warehouse_ids'] = $this->get_item_warehouses($id);
            $item['warehouse_prices'] = $this->get_item_warehouse_prices($id);
            $item['inventory_rules'] = $this->get_inventory_rules('product', $id);
        }
        return $item;
    }

    public function save_pos_product($data, $id = null)
    {
        $row = [
            'sku_name' => $data['sku_name'],
            'sku_code' => strtoupper(str_replace(' ', '', $data['sku_code'] ?: '')),
            'description' => $data['description'] ?? '',
            'rate' => (float) $data['rate'],
            'group_id' => ($data['group_id'] !== '' && $data['group_id'] !== null) ? (int) $data['group_id'] : null,
            'sub_group' => ($data['sub_group'] !== '' && $data['sub_group'] !== null) ? (int) $data['sub_group'] : null,
            'active' => (int) $data['active'],
            'fd_available' => !empty($data['fd_available']) ? 1 : 0,
            'fd_price' => ($data['fd_price'] !== '' && $data['fd_price'] !== null) ? (float) $data['fd_price'] : null,
        ];

        if ($id) {
            $this->db->where('id', (int) $id)
                ->where('can_be_sold', 'can_be_sold')
                ->where('can_be_manufacturing', 'can_be_manufacturing')
                ->update(db_prefix() . 'items', $row);
            $this->save_inventory_rules('product', (int) $id, $data['inventory_rules'] ?? []);
            return (int) $id;
        }

        if (empty($row['sku_code'])) {
            $row['sku_code'] = 'POS' . strtoupper(substr(md5(uniqid()), 0, 8));
        }

        $row['can_be_sold'] = 'can_be_sold';
        $row['can_be_manufacturing'] = 'can_be_manufacturing';
        $row['can_be_purchased'] = null;
        $row['can_be_inventory'] = 'can_be_inventory';
        $row['commodity_type'] = 5;
        $row['parent_id'] = null;

        $this->db->insert(db_prefix() . 'items', $row);
        $new_id = $this->db->insert_id() ?: false;
        if ($new_id) {
            $this->save_inventory_rules('product', (int) $new_id, $data['inventory_rules'] ?? []);
        }
        return $new_id;
    }

    // =========================================================================
    // Warehouse availability — products & modifier groups
    // No rows in the junction table = available at ALL warehouses (global)
    // =========================================================================

    public function get_item_warehouse_prices($item_id)
    {
        return $this->db->select('warehouse_id, price')
            ->where('item_id', (int) $item_id)
            ->get(db_prefix() . 'pos_item_warehouse_prices')
            ->result_array();
    }

    public function set_item_warehouse_prices($item_id, array $prices)
    {
        $this->db->where('item_id', (int) $item_id)->delete(db_prefix() . 'pos_item_warehouse_prices');
        foreach ($prices as $wid => $price) {
            $wid = (int) $wid;
            $price = (float) $price;
            if (!$wid || $price < 0)
                continue;
            $this->db->insert(db_prefix() . 'pos_item_warehouse_prices', [
                'item_id' => (int) $item_id,
                'warehouse_id' => $wid,
                'price' => $price,
            ]);
        }
    }

    public function get_item_warehouses($item_id)
    {
        return array_column(
            $this->db->select('warehouse_id')->where('item_id', (int) $item_id)
                ->get(db_prefix() . 'pos_item_warehouses')->result_array(),
            'warehouse_id'
        );
    }

    public function set_item_warehouses($item_id, array $warehouse_ids)
    {
        $this->db->where('item_id', (int) $item_id)->delete(db_prefix() . 'pos_item_warehouses');
        foreach (array_unique(array_map('intval', array_filter($warehouse_ids))) as $wid) {
            $this->db->insert(db_prefix() . 'pos_item_warehouses', [
                'item_id' => (int) $item_id,
                'warehouse_id' => $wid,
            ]);
        }
    }

    public function get_modifier_group_warehouses($group_id)
    {
        return array_column(
            $this->db->select('warehouse_id')->where('modifier_group_id', (int) $group_id)
                ->get(db_prefix() . 'pos_modifier_group_warehouses')->result_array(),
            'warehouse_id'
        );
    }

    public function set_modifier_group_warehouses($group_id, array $warehouse_ids)
    {
        $this->db->where('modifier_group_id', (int) $group_id)->delete(db_prefix() . 'pos_modifier_group_warehouses');
        foreach (array_unique(array_map('intval', array_filter($warehouse_ids))) as $wid) {
            $this->db->insert(db_prefix() . 'pos_modifier_group_warehouses', [
                'modifier_group_id' => (int) $group_id,
                'warehouse_id' => $wid,
            ]);
        }
    }

    // =========================================================================
    // Food Delivery Menu — fixed single section, opt-in categories (wh_sub_group)
    // -> items. Everything here is a DRAFT the admin edits; nothing reaches the
    // grabfood_menu() feed (or future FoodPanda/ShopeeFood) until publish_fd_menu().
    // =========================================================================

    public function get_menu_sections()
    {
        return $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_menu_sections')->result_array();
    }

    // The single fixed section every category belongs to (seeded by migration 114).
    public function get_default_section_id()
    {
        $row = $this->db->order_by('sort_order', 'ASC')->limit(1)
            ->get(db_prefix() . 'pos_menu_sections')->row_array();
        return $row['id'] ?? null;
    }

    // Categories already added to the FD menu (i.e. have a pos_category_settings row).
    public function get_categories_with_settings()
    {
        return $this->db
            ->select('sg.id, sg.sub_group_name, sg.sub_group_code, cs.section_id, cs.sort_order, cs.published, cs.sort_order_published')
            ->from(db_prefix() . 'wh_sub_group sg')
            ->join(db_prefix() . 'pos_category_settings cs', 'cs.sub_group_id = sg.id', 'inner')
            ->order_by('cs.sort_order', 'ASC')
            ->order_by('sg.sub_group_name', 'ASC')
            ->get()->result_array();
    }

    // Sub Groups that have POS products but aren't on the FD menu yet — source list
    // for the "Add Category" picker.
    public function get_addable_categories()
    {
        return $this->db
            ->select('sg.id, sg.sub_group_name')
            ->from(db_prefix() . 'wh_sub_group sg')
            ->where('EXISTS (SELECT 1 FROM `' . db_prefix() . 'items` i WHERE i.sub_group = sg.id AND i.can_be_sold = "can_be_sold")', null, false)
            ->where('NOT EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_category_settings` cs WHERE cs.sub_group_id = sg.id)', null, false)
            ->order_by('sg.sub_group_name', 'ASC')
            ->get()->result_array();
    }

    public function add_category($sub_group_id)
    {
        $sub_group_id = (int) $sub_group_id;
        if ($this->db->where('sub_group_id', $sub_group_id)->count_all_results(db_prefix() . 'pos_category_settings')) {
            return true; // already added — nothing to do
        }

        $section_id = $this->get_default_section_id();
        $next_sort = (int) $this->db->select_max('sort_order')->get(db_prefix() . 'pos_category_settings')->row()->sort_order + 1;

        return $this->db->insert(db_prefix() . 'pos_category_settings', [
            'sub_group_id' => $sub_group_id,
            'section_id' => $section_id,
            'sort_order' => $next_sort,
            'published' => 0,
        ]);
    }

    // The category's own "delete" button: removes it from the FD Menu Layout entirely.
    // Items' fd_available state is left untouched; re-add via "Add Category".
    public function disable_category_for_fd($sub_group_id)
    {
        return $this->db->where('sub_group_id', (int) $sub_group_id)
            ->delete(db_prefix() . 'pos_category_settings');
    }

    public function reorder_category($sub_group_id, $direction)
    {
        $sub_group_id = (int) $sub_group_id;
        $siblings = $this->db
            ->select('cs.sub_group_id, cs.sort_order')
            ->from(db_prefix() . 'pos_category_settings cs')
            ->order_by('cs.sort_order', 'ASC')
            ->get()->result_array();

        return $this->_swap_sort_order($siblings, $sub_group_id, $direction, db_prefix() . 'pos_category_settings', 'sub_group_id', 'sort_order');
    }

    // Shared helper: swap the sort column of $id with its previous/next sibling in an
    // already-ordered list, normalizing all siblings to sequential 0..n values as it goes.
    private function _swap_sort_order(array $ordered, $id, $direction, $table, $pk, $sort_col)
    {
        $ids = array_map('intval', array_column($ordered, $pk));
        $id = (int) $id;
        $pos = array_search($id, $ids, true);
        if ($pos === false)
            return false;

        $swap_pos = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($swap_pos < 0 || $swap_pos >= count($ids))
            return false;

        [$ordered[$pos], $ordered[$swap_pos]] = [$ordered[$swap_pos], $ordered[$pos]];

        foreach ($ordered as $i => $row) {
            $this->db->where($pk, $row[$pk])->update($table, [$sort_col => $i]);
        }
        return true;
    }

    // Copies every draft FD field (item availability/price, category membership/order)
    // into its *_published twin. Called by the "Sync" button — this is the only thing
    // that makes pending Menu Layout / Products edits visible to grabfood_menu().
    public function publish_fd_menu()
    {
        $this->db->query(
            'UPDATE `' . db_prefix() . 'items` SET fd_available_published = fd_available, fd_price_published = fd_price
             WHERE can_be_sold = "can_be_sold" AND can_be_manufacturing = "can_be_manufacturing" AND parent_id IS NULL'
        );
        $this->db->query(
            'UPDATE `' . db_prefix() . 'pos_category_settings` SET published = 1, sort_order_published = sort_order'
        );
        return true;
    }

    public function save_item_image($item_id, $filename)
    {
        return $this->db->where('id', (int) $item_id)->update(db_prefix() . 'items', ['image' => $filename]);
    }

    public function remove_item_image($item_id)
    {
        return $this->db->where('id', (int) $item_id)->update(db_prefix() . 'items', ['image' => null]);
    }

    // =========================================================================
    // Analytics queries (use new columns added in migration 109)
    // =========================================================================

    public function get_dashboard_category_breakdown($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT COALESCE(li.category_name, 'Uncategorised') AS category_name,
                   COALESCE(SUM(li.quantity), 0)    AS qty_sold,
                   COALESCE(SUM(li.total_money), 0) AS revenue
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY li.category_name
            ORDER BY revenue DESC
            LIMIT 10
        ", [$from, $to])->result_array();
    }

    public function get_dashboard_discount_breakdown($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT COALESCE(li.discount_type, 'manual') AS discount_type,
                   COALESCE(SUM(li.total_discount), 0)  AS total_discount,
                   COUNT(DISTINCT r.id)                  AS receipt_count
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND li.total_discount > 0
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY li.discount_type
            ORDER BY total_discount DESC
        ", [$from, $to])->result_array();
    }

    public function get_dashboard_promotion_performance($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT p.name AS promotion_name,
                   p.type AS promotion_type,
                   COUNT(DISTINCT li.receipt_id)         AS receipts_used,
                   COALESCE(SUM(li.total_discount), 0)   AS total_discount_given
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r    ON r.id = li.receipt_id
            JOIN `" . db_prefix() . "pos_promotions` p  ON p.id = li.promotion_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND li.promotion_id IS NOT NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY li.promotion_id, p.name, p.type
            ORDER BY total_discount_given DESC
            LIMIT 10
        ", [$from, $to])->result_array();
    }

    // -------------------------------------------------------------------------
    // Chip-in / DuitNow QR settings
    // -------------------------------------------------------------------------

    public function get_chip_settings()
    {
        return $this->db
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get(db_prefix() . 'pos_chip_settings')
            ->row_array();
    }

    public function save_chip_settings($data)
    {
        $existing = $this->get_chip_settings();
        if ($existing) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->db->where('id', $existing['id'])
                ->update(db_prefix() . 'pos_chip_settings', $data);
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->insert(db_prefix() . 'pos_chip_settings', $data);
    }

    // -------------------------------------------------------------------------
    // DuitNow Transactions
    // -------------------------------------------------------------------------

    public function create_duitnow_transaction($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'pos_duitnow_transactions', $data);
        return $this->db->insert_id();
    }

    public function get_duitnow_transaction_by_purchase_id($purchase_id)
    {
        return $this->db
            ->where('purchase_id', $purchase_id)
            ->get(db_prefix() . 'pos_duitnow_transactions')
            ->row_array();
    }

    public function update_duitnow_transaction($purchase_id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('purchase_id', $purchase_id)
            ->update(db_prefix() . 'pos_duitnow_transactions', $data);
    }

    // =========================================================================
    // Accounting Settings & Shift Journal Entries
    // =========================================================================

    public function get_accounting_settings()
    {
        $row = $this->db->order_by('id', 'ASC')->limit(1)
            ->get(db_prefix() . 'pos_accounting_settings')->row_array();
        return $row ?: ['enabled' => 0];
    }

    public function get_payment_method_accounts()
    {
        $rows = $this->db->get(db_prefix() . 'pos_payment_method_accounts')->result_array();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['payment_type_id']] = $row;
        }
        return $map;
    }

    public function save_accounting_settings($data)
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->limit(1)->get(db_prefix() . 'pos_accounting_settings')->row_array();
        $payload = ['enabled' => isset($data['enabled']) ? (int) (bool) $data['enabled'] : 0, 'updated_at' => $now];
        if ($existing) {
            $this->db->where('id', $existing['id'])->update(db_prefix() . 'pos_accounting_settings', $payload);
        } else {
            $payload['created_at'] = $now;
            $this->db->insert(db_prefix() . 'pos_accounting_settings', $payload);
        }

        // Upsert per-payment-method mappings
        $mappings = $data['payment_method_accounts'] ?? [];
        foreach ($mappings as $type_id => $map) {
            $type_id = (int) $type_id;
            if (!$type_id)
                continue;
            $row = [
                'debit_account_id' => !empty($map['debit']) ? (int) $map['debit'] : null,
                'credit_account_id' => !empty($map['credit']) ? (int) $map['credit'] : null,
                'updated_at' => $now,
            ];
            $existing_map = $this->db->where('payment_type_id', $type_id)
                ->limit(1)->get(db_prefix() . 'pos_payment_method_accounts')->row_array();
            if ($existing_map) {
                $this->db->where('id', $existing_map['id'])->update(db_prefix() . 'pos_payment_method_accounts', $row);
            } else {
                $row['payment_type_id'] = $type_id;
                $row['created_at'] = $now;
                $this->db->insert(db_prefix() . 'pos_payment_method_accounts', $row);
            }
        }

        return true;
    }

    /**
     * Create a journal entry in the accounting module for a closed shift.
     *
     * Each payment method maps to its own DR (debit) and CR (credit) account.
     * Payment methods with no mapping are skipped.
     */
    public function create_shift_accounting_entry($shift_id)
    {
        $settings = $this->get_accounting_settings();
        if (empty($settings['enabled'])) {
            return false;
        }

        // Idempotent: skip if already synced
        $already = $this->db->where('shift_id', (int) $shift_id)
            ->count_all_results(db_prefix() . 'pos_shift_accounting_entries');
        if ($already) {
            return false;
        }

        $shift = $this->get_shift($shift_id);
        if (!$shift || $shift['status'] !== 'closed') {
            return false;
        }

        // Payment totals by method for this shift
        $payment_totals = $this->db
            ->select('rp.payment_type_id, rp.payment_name, SUM(rp.money_amount) as total', false)
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', (int) $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('r.receipt_type', 'SALE')
            ->group_by('rp.payment_type_id')
            ->get()->result_array();

        if (empty($payment_totals)) {
            return false;
        }

        $mappings = $this->get_payment_method_accounts();
        $journal_date = date('Y-m-d', strtotime($shift['closed_at']));
        $description = 'POS Shift ' . $shift['shift_code'] . ' — ' . ($shift['warehouse_name'] ?? '');
        $now = date('Y-m-d H:i:s');

        $lines = [];
        $journal_total = 0;

        foreach ($payment_totals as $pt) {
            $type_id = (int) $pt['payment_type_id'];
            $amount = round((float) $pt['total'], 2);
            if ($amount <= 0)
                continue;

            $map = $mappings[$type_id] ?? null;
            if (!$map || empty($map['debit_account_id']) || empty($map['credit_account_id'])) {
                continue; // unmapped payment method — skip
            }

            $label = htmlspecialchars_decode($pt['payment_name']);

            $lines[] = [
                'account' => (int) $map['debit_account_id'],
                'date' => $journal_date,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'POS ' . $label . ' receipts — ' . $description,
                'rel_id' => 0,
                'rel_type' => 'journal_entry',
                'datecreated' => $now,
                'addedfrom' => 0,
            ];
            $lines[] = [
                'account' => (int) $map['credit_account_id'],
                'date' => $journal_date,
                'debit' => 0,
                'credit' => $amount,
                'description' => 'POS ' . $label . ' sales — ' . $description,
                'rel_id' => 0,
                'rel_type' => 'journal_entry',
                'datecreated' => $now,
                'addedfrom' => 0,
            ];
            $journal_total += $amount;
        }

        if (empty($lines)) {
            return false;
        }

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'acc_journal_entries', [
            'number' => 'POS-' . $shift['shift_code'],
            'description' => $description,
            'journal_date' => $journal_date,
            'amount' => round($journal_total, 2),
            'datecreated' => $now,
            'addedfrom' => 0,
            'recurring' => 0,
        ]);
        $journal_id = $this->db->insert_id();

        if ($journal_id) {
            // Back-fill the journal entry id into the line descriptions' rel_id
            foreach ($lines as &$line) {
                $line['rel_id'] = $journal_id;
            }
            unset($line);

            $this->db->insert_batch(db_prefix() . 'acc_account_history', $lines);

            $this->db->insert(db_prefix() . 'pos_shift_accounting_entries', [
                'shift_id' => (int) $shift_id,
                'journal_entry_id' => $journal_id,
                'synced_at' => $now,
            ]);
        }

        $this->db->trans_complete();
        return $this->db->trans_status() !== false ? $journal_id : false;
    }

    /**
     * Return shifts that have no accounting entry yet (status = closed).
     * Used by the manual bulk-sync endpoint.
     */
    public function get_unsynced_shifts()
    {
        return $this->db
            ->select('s.id, s.shift_code, s.warehouse_id, s.total_sales, s.total_tax, s.closed_at, w.warehouse_name')
            ->from(db_prefix() . 'pos_shifts s')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = s.warehouse_id', 'left')
            ->where('s.status', 'closed')
            ->where('NOT EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_shift_accounting_entries` sae WHERE sae.shift_id = s.id)', null, false)
            ->order_by('s.closed_at', 'ASC')
            ->get()->result_array();
    }

    // =========================================================================
    // Reports — detailed analytics (Sales, Products, Payments, Shifts, Customers, Promotions)
    // =========================================================================

    /**
     * Returns SQL fragments for group-by trend queries.
     * $field must be a fully-qualified column, e.g. 'receipt_date' or 'r.receipt_date'.
     */
    private function _trend_expr($group_by, $field = 'receipt_date')
    {
        switch ($group_by) {
            case 'hourly':
                return [
                    'select' => "CONCAT(LPAD(HOUR($field), 2, '0'), ':00') AS label",
                    'group' => "HOUR($field)",
                    'order' => "HOUR($field) ASC",
                ];
            case 'hourly_by_day':
                return [
                    'select' => "DATE_FORMAT($field, '%d %b %H:00') AS label",
                    'group' => "DATE($field), HOUR($field)",
                    'order' => "DATE($field) ASC, HOUR($field) ASC",
                ];
            case 'dow':
                return [
                    'select' => "DAYNAME($field) AS label",
                    'group' => "DAYOFWEEK($field)",
                    'order' => "DAYOFWEEK($field) ASC",
                ];
            case 'weekly':
                return [
                    'select' => "MIN(DATE_FORMAT($field, '%d %b %Y')) AS label",
                    'group' => "YEARWEEK($field, 1)",
                    'order' => "YEARWEEK($field, 1) ASC",
                ];
            case 'monthly':
                return [
                    'select' => "DATE_FORMAT($field, '%b %Y') AS label",
                    'group' => "DATE_FORMAT($field, '%Y-%m')",
                    'order' => "DATE_FORMAT($field, '%Y-%m') ASC",
                ];
            default: // daily
                return [
                    'select' => "DATE($field) AS label",
                    'group' => "DATE($field)",
                    'order' => "DATE($field) ASC",
                ];
        }
    }

    // --- Sales ---

    public function get_report_sales_summary($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND warehouse_id = ' . (int) $warehouse_id : '';

        $row = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN subtotal        ELSE 0 END), 0) AS gross_sales,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_discount  ELSE 0 END), 0) AS total_discounts,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_tax       ELSE 0 END), 0) AS total_tax,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN tip             ELSE 0 END), 0) AS total_tips,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN surcharge       ELSE 0 END), 0) AS total_surcharge,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN points_deducted ELSE 0 END), 0) AS loyalty_redeemed,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_money     ELSE 0 END), 0) AS net_sales,
                COALESCE(COUNT(CASE WHEN receipt_type='SALE' AND cancelled_at IS NULL THEN 1 END), 0)                     AS transaction_count,
                COALESCE(SUM(CASE WHEN receipt_type='REFUND' AND cancelled_at IS NULL THEN total_money     ELSE 0 END), 0) AS total_refunds,
                COALESCE(COUNT(CASE WHEN receipt_type='REFUND' AND cancelled_at IS NULL THEN 1 END), 0)                   AS refund_count,
                COALESCE(COUNT(CASE WHEN cancelled_at IS NOT NULL THEN 1 END), 0)                                         AS cancelled_count,
                COALESCE(SUM(CASE WHEN cancelled_at IS NOT NULL THEN total_money ELSE 0 END), 0)                          AS cancelled_amount
            FROM `" . db_prefix() . "pos_receipts`
            WHERE receipt_date BETWEEN ? AND ? $wh
        ", [$from, $to])->row_array();

        $row['avg_transaction'] = $row['transaction_count'] > 0
            ? round((float) $row['net_sales'] / (int) $row['transaction_count'], 2) : 0;

        $items = $this->db->query("
            SELECT COALESCE(SUM(li.quantity), 0) AS items_sold
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
        ", [$from, $to])->row_array();
        $row['items_sold'] = (int) ($items['items_sold'] ?? 0);

        return $row;
    }

    public function get_report_sales_daily($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT DATE(receipt_date)                AS date,
                   COALESCE(SUM(subtotal), 0)        AS gross_sales,
                   COALESCE(SUM(total_money), 0)     AS net_sales,
                   COALESCE(SUM(total_discount), 0)  AS total_discounts,
                   COALESCE(SUM(total_tax), 0)       AS total_tax,
                   COUNT(*)                          AS transaction_count
            FROM `" . db_prefix() . "pos_receipts`
            WHERE receipt_type = 'SALE' AND cancelled_at IS NULL
              AND receipt_date BETWEEN ? AND ? $wh
            GROUP BY DATE(receipt_date)
            ORDER BY date ASC
        ", [$from, $to])->result_array();
    }

    public function get_report_sales_hourly($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT HOUR(receipt_date)                 AS hour,
                   COALESCE(SUM(total_money), 0)      AS net_sales,
                   COUNT(*)                           AS transaction_count,
                   ROUND(AVG(total_money), 2)          AS avg_transaction
            FROM `" . db_prefix() . "pos_receipts`
            WHERE receipt_type = 'SALE' AND cancelled_at IS NULL
              AND receipt_date BETWEEN ? AND ? $wh
            GROUP BY HOUR(receipt_date)
            ORDER BY hour ASC
        ", [$from, $to])->result_array();
    }

    public function get_report_sales_dow($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT DAYOFWEEK(receipt_date)            AS day_of_week,
                   DAYNAME(receipt_date)              AS day_name,
                   COALESCE(SUM(total_money), 0)      AS net_sales,
                   COUNT(*)                           AS transaction_count,
                   ROUND(AVG(total_money), 2)          AS avg_transaction
            FROM `" . db_prefix() . "pos_receipts`
            WHERE receipt_type = 'SALE' AND cancelled_at IS NULL
              AND receipt_date BETWEEN ? AND ? $wh
            GROUP BY DAYOFWEEK(receipt_date), DAYNAME(receipt_date)
            ORDER BY day_of_week ASC
        ", [$from, $to])->result_array();
    }

    // --- Products ---

    // Build optional WHERE clauses for category and product name filters.
    // $i_alias = items table alias, $li_alias = line_items table alias
    private function _product_filter_sql($filters = [], $i_alias = 'i', $li_alias = 'li')
    {
        $sql = '';
        if (isset($filters['category_id']) && $filters['category_id'] !== '' && $filters['category_id'] !== null) {
            $cid = (int) $filters['category_id'];
            $sql .= $cid === 0
                ? " AND {$i_alias}.sub_group IS NULL"
                : " AND {$i_alias}.sub_group = {$cid}";
        }
        if (!empty($filters['product_search'])) {
            $s = $this->db->escape_like_str(trim($filters['product_search']));
            $sql .= " AND {$li_alias}.item_name LIKE '%{$s}%'";
        }
        return $sql;
    }

    public function get_report_products_top($date_from, $date_to, $warehouse_id = null, $limit = 25, $filters = [])
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $filter = $this->_product_filter_sql($filters, 'i', 'li');

        return $this->db->query("
            SELECT li.item_id,
                   li.item_name,
                   COALESCE(sg.sub_group_name, 'Uncategorised') AS category_name,
                   COALESCE(SUM(li.quantity), 0)                AS qty_sold,
                   COALESCE(SUM(li.gross_total + li.modifiers_price), 0) AS gross_revenue,
                   COALESCE(SUM(li.total_discount), 0)          AS total_discounts,
                   COALESCE(SUM(li.total_money), 0)             AS net_revenue,
                   ROUND(COALESCE(AVG(li.unit_price), 0), 2)    AS avg_unit_price
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r  ON r.id  = li.receipt_id
            JOIN `" . db_prefix() . "items` i          ON i.id  = li.item_id
            LEFT JOIN `" . db_prefix() . "wh_sub_group` sg ON sg.id = i.sub_group
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh $filter
            GROUP BY li.item_id, li.item_name, sg.id, sg.sub_group_name
            ORDER BY net_revenue DESC
            LIMIT " . (int) $limit
            ,
            [$from, $to]
        )->result_array();
    }

    public function get_report_products_bottom($date_from, $date_to, $warehouse_id = null, $limit = 10)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT li.item_id,
                   li.item_name,
                   COALESCE(sg.sub_group_name, 'Uncategorised') AS category_name,
                   COALESCE(SUM(li.quantity), 0)    AS qty_sold,
                   COALESCE(SUM(li.total_money), 0) AS net_revenue
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r  ON r.id  = li.receipt_id
            JOIN `" . db_prefix() . "items` i          ON i.id  = li.item_id
            LEFT JOIN `" . db_prefix() . "wh_sub_group` sg ON sg.id = i.sub_group
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY li.item_id, li.item_name, sg.id, sg.sub_group_name
            HAVING qty_sold > 0
            ORDER BY net_revenue ASC
            LIMIT " . (int) $limit
            ,
            [$from, $to]
        )->result_array();
    }

    public function get_report_products_by_category($date_from, $date_to, $warehouse_id = null, $filters = [])
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $filter = $this->_product_filter_sql($filters, 'i', 'li');

        return $this->db->query("
            SELECT COALESCE(sg.sub_group_name, 'Uncategorised') AS category_name,
                   COUNT(DISTINCT li.item_id)           AS item_count,
                   COALESCE(SUM(li.quantity), 0)        AS qty_sold,
                   COALESCE(SUM(li.total_money), 0)     AS net_revenue,
                   COALESCE(SUM(li.total_discount), 0)  AS total_discounts,
                   COUNT(DISTINCT r.id)                 AS receipt_count
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r  ON r.id  = li.receipt_id
            JOIN `" . db_prefix() . "items` i          ON i.id  = li.item_id
            LEFT JOIN `" . db_prefix() . "wh_sub_group` sg ON sg.id = i.sub_group
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh $filter
            GROUP BY sg.id, sg.sub_group_name
            ORDER BY net_revenue DESC
        ", [$from, $to])->result_array();
    }

    // --- Payments ---

    public function get_report_payments_breakdown($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        $rows = $this->db->query("
            SELECT rp.payment_name,
                   rp.type                                    AS payment_type,
                   COALESCE(SUM(rp.money_amount), 0)          AS total_amount,
                   COUNT(DISTINCT r.id)                       AS transaction_count,
                   COALESCE(SUM(rp.cash_back), 0)             AS total_cashback
            FROM `" . db_prefix() . "pos_receipt_payments` rp
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = rp.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY rp.payment_type_id, rp.payment_name, rp.type
            ORDER BY total_amount DESC
        ", [$from, $to])->result_array();

        $total = array_sum(array_column($rows, 'total_amount'));
        foreach ($rows as &$r) {
            $r['percentage'] = $total > 0 ? round((float) $r['total_amount'] / $total * 100, 1) : 0;
        }
        return $rows;
    }

    public function get_report_payments_daily($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT DATE(r.receipt_date)                    AS date,
                   rp.payment_name,
                   COALESCE(SUM(rp.money_amount), 0)       AS total_amount,
                   COUNT(DISTINCT r.id)                    AS transaction_count
            FROM `" . db_prefix() . "pos_receipt_payments` rp
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = rp.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY DATE(r.receipt_date), rp.payment_type_id, rp.payment_name
            ORDER BY date ASC, total_amount DESC
        ", [$from, $to])->result_array();
    }

    public function get_report_refunds_by_payment($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT COALESCE(pt.name, 'Unknown')       AS payment_name,
                   COUNT(rf.id)                       AS refund_count,
                   COALESCE(SUM(rf.amount), 0)        AS total_refunded
            FROM `" . db_prefix() . "pos_refunds` rf
            JOIN `" . db_prefix() . "pos_receipts` r        ON r.id = rf.receipt_id
            LEFT JOIN `" . db_prefix() . "pos_payment_types` pt ON pt.id = rf.payment_type_id
            WHERE r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY rf.payment_type_id, pt.name
            ORDER BY total_refunded DESC
        ", [$from, $to])->result_array();
    }

    // --- Transaction Types ---

    public function get_report_txn_types($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $pfx = db_prefix();
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT
                CASE
                    WHEN r.source = 'GRABFOOD'   THEN 'GrabFood'
                    WHEN r.source = 'FOODPANDA'  THEN 'FoodPanda'
                    WHEN r.source = 'SHOPEEFOOD' THEN 'ShopeeFood'
                    WHEN r.dining_option IN ('DINE_IN','Dine-in','dine_in')     THEN 'Dine-in'
                    WHEN r.dining_option IN ('TAKEAWAY','TakeAway','takeaway','SELF_PICKUP','Self-Pickup') THEN 'Takeaway'
                    WHEN r.dining_option IN ('DELIVERY','Delivery','delivery')  THEN 'Delivery'
                    ELSE 'Walk-in / POS'
                END                                   AS txn_type,
                COUNT(r.id)                           AS total_receipts,
                COALESCE(SUM(r.total_money), 0)       AS total_revenue,
                COALESCE(AVG(r.total_money), 0)       AS avg_order_value,
                COALESCE(SUM(r.total_discount), 0)    AS total_discount
            FROM `{$pfx}pos_receipts` r
            WHERE r.receipt_date BETWEEN ? AND ?
              AND r.cancelled_at IS NULL
              AND (r.refund_for IS NULL OR r.refund_for = 0)
              $wh
            GROUP BY txn_type
            ORDER BY total_revenue DESC
        ", [$from, $to])->result_array();
    }

    // --- Trend methods (group_by aware) ---

    public function get_report_sales_trend($date_from, $date_to, $warehouse_id = null, $group_by = 'daily')
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND warehouse_id = ' . (int) $warehouse_id : '';
        $e = $this->_trend_expr($group_by);

        return $this->db->query("
            SELECT {$e['select']},
                   COALESCE(SUM(total_money), 0)       AS net_sales,
                   COALESCE(SUM(subtotal), 0)           AS gross_sales,
                   COUNT(*)                             AS transaction_count,
                   COALESCE(SUM(total_discount), 0)     AS total_discounts,
                   COALESCE(SUM(total_tax), 0)          AS total_tax,
                   COALESCE(SUM(points_deducted), 0)    AS loyalty_redeemed
            FROM `" . db_prefix() . "pos_receipts`
            WHERE receipt_type = 'SALE' AND cancelled_at IS NULL
              AND receipt_date BETWEEN ? AND ? $wh
            GROUP BY {$e['group']}
            ORDER BY {$e['order']}
        ", [$from, $to])->result_array();
    }

    public function get_report_products_category_trend($date_from, $date_to, $warehouse_id = null, $group_by = 'daily', $filters = [])
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $e = $this->_trend_expr($group_by, 'r.receipt_date');
        $filter = $this->_product_filter_sql($filters, 'i', 'li');

        return $this->db->query("
            SELECT {$e['select']},
                   COALESCE(sg.sub_group_name, 'Uncategorised') AS category_name,
                   COALESCE(SUM(li.total_money), 0)             AS net_revenue,
                   COALESCE(SUM(li.quantity), 0)                AS qty_sold
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r  ON r.id  = li.receipt_id
            JOIN `" . db_prefix() . "items` i          ON i.id  = li.item_id
            LEFT JOIN `" . db_prefix() . "wh_sub_group` sg ON sg.id = i.sub_group
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh $filter
            GROUP BY {$e['group']}, sg.id, sg.sub_group_name
            ORDER BY {$e['order']}, net_revenue DESC
        ", [$from, $to])->result_array();
    }

    // Total distinct receipts that contain products matching the given filters
    public function get_report_products_receipt_count($date_from, $date_to, $warehouse_id = null, $filters = [])
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $filter = $this->_product_filter_sql($filters, 'i', 'li');

        $row = $this->db->query("
            SELECT COUNT(DISTINCT r.id) AS receipt_count
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            JOIN `" . db_prefix() . "items` i          ON i.id = li.item_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh $filter
        ", [$from, $to])->row_array();

        return (int) ($row['receipt_count'] ?? 0);
    }

    // All products × period (no top-N limit) — used for the data table
    public function get_report_products_all_trend($date_from, $date_to, $warehouse_id = null, $group_by = 'daily', $filters = [])
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $e = $this->_trend_expr($group_by, 'r.receipt_date');
        $filter = $this->_product_filter_sql($filters, 'i', 'li');

        return $this->db->query("
            SELECT {$e['select']},
                   li.item_id,
                   li.item_name,
                   COALESCE(sg.sub_group_name, 'Uncategorised') AS category_name,
                   COALESCE(SUM(li.total_money), 0) AS net_revenue,
                   COALESCE(SUM(li.quantity), 0)    AS qty_sold
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r  ON r.id  = li.receipt_id
            JOIN `" . db_prefix() . "items` i          ON i.id  = li.item_id
            LEFT JOIN `" . db_prefix() . "wh_sub_group` sg ON sg.id = i.sub_group
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh $filter
            GROUP BY {$e['group']}, li.item_id, li.item_name, sg.id, sg.sub_group_name
            ORDER BY {$e['order']}, net_revenue DESC
        ", [$from, $to])->result_array();
    }

    public function get_report_products_top_trend($date_from, $date_to, $warehouse_id = null, $group_by = 'daily', $limit = 10, $filters = [])
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $e = $this->_trend_expr($group_by, 'r.receipt_date');
        $filter = $this->_product_filter_sql($filters, 'i', 'li');
        $filter_inner = $this->_product_filter_sql($filters, 'i2', 'li2');

        return $this->db->query("
            SELECT {$e['select']},
                   li.item_name,
                   COALESCE(SUM(li.total_money), 0) AS net_revenue,
                   COALESCE(SUM(li.quantity), 0)    AS qty_sold
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r  ON r.id  = li.receipt_id
            JOIN `" . db_prefix() . "items` i          ON i.id  = li.item_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh $filter
              AND li.item_id IN (
                  SELECT item_id FROM (
                      SELECT li2.item_id
                      FROM `" . db_prefix() . "pos_receipt_line_items` li2
                      JOIN `" . db_prefix() . "pos_receipts` r2 ON r2.id = li2.receipt_id
                      JOIN `" . db_prefix() . "items` i2         ON i2.id = li2.item_id
                      WHERE r2.receipt_type = 'SALE' AND r2.cancelled_at IS NULL
                        AND r2.receipt_date BETWEEN ? AND ? $wh $filter_inner
                      GROUP BY li2.item_id
                      ORDER BY SUM(li2.total_money) DESC
                      LIMIT " . (int) $limit . "
                  ) AS _top_items
              )
            GROUP BY {$e['group']}, li.item_id, li.item_name
            ORDER BY {$e['order']}, net_revenue DESC
        ", [$from, $to, $from, $to])->result_array();
    }

    public function get_report_products_trend($date_from, $date_to, $warehouse_id = null, $group_by = 'daily')
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $e = $this->_trend_expr($group_by, 'r.receipt_date');

        return $this->db->query("
            SELECT {$e['select']},
                   COALESCE(SUM(li.total_money), 0)  AS net_revenue,
                   COALESCE(SUM(li.quantity), 0)     AS qty_sold,
                   COUNT(DISTINCT r.id)              AS receipt_count
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY {$e['group']}
            ORDER BY {$e['order']}
        ", [$from, $to])->result_array();
    }

    public function get_report_payments_trend($date_from, $date_to, $warehouse_id = null, $group_by = 'daily')
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $e = $this->_trend_expr($group_by, 'r.receipt_date');

        return $this->db->query("
            SELECT {$e['select']},
                   rp.payment_name,
                   COALESCE(SUM(rp.money_amount), 0) AS total_amount,
                   COUNT(DISTINCT r.id)              AS transaction_count
            FROM `" . db_prefix() . "pos_receipt_payments` rp
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = rp.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY {$e['group']}, rp.payment_type_id, rp.payment_name
            ORDER BY {$e['order']}, total_amount DESC
        ", [$from, $to])->result_array();
    }

    public function get_report_txn_types_trend($date_from, $date_to, $warehouse_id = null, $group_by = 'daily')
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $pfx = db_prefix();
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $e = $this->_trend_expr($group_by, 'r.receipt_date');

        return $this->db->query("
            SELECT {$e['select']},
                   CASE
                       WHEN r.source = 'GRABFOOD'   THEN 'GrabFood'
                       WHEN r.source = 'FOODPANDA'  THEN 'FoodPanda'
                       WHEN r.source = 'SHOPEEFOOD' THEN 'ShopeeFood'
                       WHEN r.dining_option IN ('DINE_IN','Dine-in','dine_in')                        THEN 'Dine-in'
                       WHEN r.dining_option IN ('TAKEAWAY','TakeAway','takeaway','SELF_PICKUP','Self-Pickup') THEN 'Takeaway'
                       WHEN r.dining_option IN ('DELIVERY','Delivery','delivery')                     THEN 'Delivery'
                       ELSE 'Walk-in / POS'
                   END                               AS txn_type,
                   COUNT(r.id)                       AS total_receipts,
                   COALESCE(SUM(r.total_money), 0)   AS total_revenue
            FROM `{$pfx}pos_receipts` r
            WHERE r.cancelled_at IS NULL
              AND (r.refund_for IS NULL OR r.refund_for = 0)
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY {$e['group']}, txn_type
            ORDER BY {$e['order']}, total_revenue DESC
        ", [$from, $to])->result_array();
    }

    // --- Shifts ---

    public function get_report_shifts_list($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';

        $this->db->select('s.*, w.warehouse_name, e1.name AS employee_name, e2.name AS closed_by_name')
            ->from(db_prefix() . 'pos_shifts s')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = s.warehouse_id', 'left')
            ->join(db_prefix() . 'pos_employees e1', 'e1.id = s.employee_id', 'left')
            ->join(db_prefix() . 'pos_employees e2', 'e2.id = s.closed_by_employee_id', 'left')
            ->where('s.opened_at >=', $from)
            ->where('s.opened_at <=', $to)
            ->order_by('s.opened_at', 'DESC')
            ->limit(500);

        if ($warehouse_id)
            $this->db->where('s.warehouse_id', (int) $warehouse_id);

        return $this->db->get()->result_array();
    }

    public function get_report_staff_performance($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND s.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT e.name                                    AS employee_name,
                   COUNT(s.id)                              AS shift_count,
                   COALESCE(SUM(s.total_sales), 0)          AS total_sales,
                   COALESCE(SUM(s.transaction_count), 0)    AS total_transactions,
                   COALESCE(SUM(s.total_refunds), 0)        AS total_refunds,
                   COALESCE(SUM(s.total_discounts), 0)      AS total_discounts,
                   CASE WHEN COUNT(s.id) > 0
                        THEN ROUND(SUM(s.total_sales)/COUNT(s.id), 2)
                        ELSE 0 END                          AS avg_sales_per_shift
            FROM `" . db_prefix() . "pos_shifts` s
            JOIN `" . db_prefix() . "pos_employees` e ON e.id = s.employee_id
            WHERE s.opened_at BETWEEN ? AND ? $wh
            GROUP BY s.employee_id, e.name
            ORDER BY total_sales DESC
        ", [$from, $to])->result_array();
    }

    public function get_report_cash_movements_summary($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND s.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT cm.type,
                   COUNT(cm.id)                AS movement_count,
                   COALESCE(SUM(cm.amount), 0) AS total_amount
            FROM `" . db_prefix() . "pos_shift_cash_movements` cm
            JOIN `" . db_prefix() . "pos_shifts` s ON s.id = cm.shift_id
            WHERE s.opened_at BETWEEN ? AND ? $wh
            GROUP BY cm.type
            ORDER BY cm.type ASC
        ", [$from, $to])->result_array();
    }

    // --- Customers ---

    public function get_report_customers_summary($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        $new = $this->db->query("
            SELECT COUNT(*) AS new_members
            FROM `" . db_prefix() . "pos_loyalty_customers`
            WHERE registered_at BETWEEN ? AND ?
        ", [$from, $to])->row_array();

        $loyalty = $this->db->query("
            SELECT COALESCE(SUM(CASE WHEN lt.type='earn'   THEN lt.points ELSE 0 END), 0) AS total_earned,
                   COALESCE(SUM(CASE WHEN lt.type='redeem' THEN lt.points ELSE 0 END), 0) AS total_redeemed,
                   COUNT(DISTINCT CASE WHEN lt.type='earn'   THEN lt.customer_id END)     AS earning_customers,
                   COUNT(DISTINCT CASE WHEN lt.type='redeem' THEN lt.customer_id END)     AS redeeming_customers
            FROM `" . db_prefix() . "pos_loyalty_transactions` lt
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = lt.receipt_id
            WHERE lt.created_at BETWEEN ? AND ? $wh
        ", [$from, $to])->row_array();

        $repeat = $this->db->query("
            SELECT COUNT(DISTINCT r.loyalty_customer_id) AS loyalty_customers_with_sales
            FROM `" . db_prefix() . "pos_receipts` r
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.loyalty_customer_id IS NOT NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
        ", [$from, $to])->row_array();

        return array_merge($new, $loyalty, $repeat);
    }

    public function get_report_customers_top($date_from, $date_to, $warehouse_id = null, $limit = 20)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT lc.name                              AS customer_name,
                   lc.phone,
                   lc.email,
                   COUNT(DISTINCT r.id)                AS visit_count,
                   COALESCE(SUM(r.total_money), 0)     AS total_spent,
                   COALESCE(SUM(r.points_earned), 0)   AS points_earned,
                   COALESCE(SUM(r.points_deducted), 0) AS points_redeemed
            FROM `" . db_prefix() . "pos_receipts` r
            JOIN `" . db_prefix() . "pos_loyalty_customers` lc ON lc.id = r.loyalty_customer_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY r.loyalty_customer_id, lc.name, lc.phone, lc.email
            ORDER BY total_spent DESC
            LIMIT " . (int) $limit
            ,
            [$from, $to]
        )->result_array();
    }

    public function get_report_customers_new_daily($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';

        return $this->db->query("
            SELECT DATE(registered_at) AS date, COUNT(*) AS new_members
            FROM `" . db_prefix() . "pos_loyalty_customers`
            WHERE registered_at BETWEEN ? AND ?
            GROUP BY DATE(registered_at)
            ORDER BY date ASC
        ", [$from, $to])->result_array();
    }

    public function get_report_loyalty_activity($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT DATE(lt.created_at) AS date,
                   COALESCE(SUM(CASE WHEN lt.type='earn'   THEN lt.points ELSE 0 END), 0) AS earn_points,
                   COALESCE(SUM(CASE WHEN lt.type='redeem' THEN lt.points ELSE 0 END), 0) AS redeem_points,
                   COUNT(CASE WHEN lt.type='earn'   THEN 1 END) AS earn_count,
                   COUNT(CASE WHEN lt.type='redeem' THEN 1 END) AS redeem_count
            FROM `" . db_prefix() . "pos_loyalty_transactions` lt
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = lt.receipt_id
            WHERE lt.created_at BETWEEN ? AND ? $wh
            GROUP BY DATE(lt.created_at)
            ORDER BY date ASC
        ", [$from, $to])->result_array();
    }

    public function get_report_customer_retention($date_from, $date_to, $warehouse_id = null)
    {
        $pfx = db_prefix();
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $days = max(1, (int) ((strtotime($date_to) - strtotime($date_from)) / 86400) + 1);
        $prev_to = date('Y-m-d', strtotime($date_from) - 86400) . ' 23:59:59';
        $prev_from = date('Y-m-d', strtotime($date_from) - ($days * 86400)) . ' 00:00:00';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        $row = $this->db->query("
            SELECT
                SUM(in_current = 1 AND in_prev = 1) AS retained,
                SUM(in_current = 1 AND in_prev = 0) AS new_or_returning,
                SUM(in_current = 0 AND in_prev = 1) AS lapsed
            FROM (
                SELECT
                    lc.id,
                    MAX(CASE WHEN r.receipt_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS in_current,
                    MAX(CASE WHEN r.receipt_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS in_prev
                FROM `{$pfx}pos_loyalty_customers` lc
                JOIN `{$pfx}pos_receipts` r ON r.loyalty_customer_id = lc.id
                    AND r.receipt_type = 'SALE' AND r.cancelled_at IS NULL $wh
                WHERE r.receipt_date BETWEEN ? AND ?
                GROUP BY lc.id
            ) cohort
        ", [$from, $to, $prev_from, $prev_to, $prev_from, $to])->row_array();

        $retained = (int) ($row['retained'] ?? 0);
        $lapsed = (int) ($row['lapsed'] ?? 0);
        $new_ret = (int) ($row['new_or_returning'] ?? 0);
        $base = $retained + $lapsed;

        return [
            'retained' => $retained,
            'new_or_returning' => $new_ret,
            'lapsed' => $lapsed,
            'retention_rate' => $base > 0 ? round($retained / $base * 100, 1) : null,
            'churn_rate' => $base > 0 ? round($lapsed / $base * 100, 1) : null,
            'period_days' => $days,
            'prev_from' => substr($prev_from, 0, 10),
            'prev_to' => substr($prev_to, 0, 10),
        ];
    }

    // --- Promotions & Discounts ---

    public function get_report_promotions($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT p.name                                    AS promotion_name,
                   p.type                                    AS promotion_type,
                   COUNT(DISTINCT li.receipt_id)             AS receipts_used,
                   COALESCE(SUM(li.total_discount), 0)       AS total_discount_given,
                   COALESCE(SUM(li.quantity), 0)             AS items_sold_in_promo
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r    ON r.id = li.receipt_id
            JOIN `" . db_prefix() . "pos_promotions` p  ON p.id = li.promotion_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND li.promotion_id IS NOT NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY li.promotion_id, p.name, p.type
            ORDER BY total_discount_given DESC
        ", [$from, $to])->result_array();
    }

    public function get_report_most_discounted_items($date_from, $date_to, $warehouse_id = null, $limit = 15)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';

        return $this->db->query("
            SELECT li.item_name,
                   COALESCE(sg.sub_group_name, 'Uncategorised') AS category_name,
                   COUNT(li.id)                         AS times_discounted,
                   COALESCE(SUM(li.total_discount), 0)  AS total_discount,
                   ROUND(COALESCE(AVG(li.total_discount), 0), 2) AS avg_discount_per_line
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r  ON r.id  = li.receipt_id
            JOIN `" . db_prefix() . "items` i          ON i.id  = li.item_id
            LEFT JOIN `" . db_prefix() . "wh_sub_group` sg ON sg.id = i.sub_group
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND li.total_discount > 0
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY li.item_id, li.item_name, sg.id, sg.sub_group_name
            ORDER BY total_discount DESC
            LIMIT " . (int) $limit
            ,
            [$from, $to]
        )->result_array();
    }

    // -------------------------------------------------------------------------
    // Promo & Bundle Feasibility Analytics
    // -------------------------------------------------------------------------

    public function get_report_crm_promo_feasibility($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $p = db_prefix();
        $params = [$from, $to];
        $wh_sql = '';
        if ($warehouse_id) {
            $wh_sql = 'AND r.warehouse_id = ?';
            $params[] = (int) $warehouse_id;
        }

        $rows = $this->db->query("
            SELECT cp.id,
                   cp.name           AS promo_name,
                   cp.type           AS promo_type,
                   cp.discount_type,
                   cp.discount_value,
                   COALESCE(i.rate, 0)      AS selling_price,
                   COALESCE(i.sku_name, cp.name) AS item_name,
                   cp.pos_item_id,
                   COUNT(DISTINCT li.receipt_id)   AS receipts_sold,
                   COALESCE(SUM(li.quantity),   0) AS units_sold,
                   COALESCE(SUM(li.gross_total),0) AS total_revenue,
                   COALESCE(SUM(li.cost),       0) AS total_cost
            FROM `{$p}pos_crm_promos` cp
            LEFT JOIN `{$p}items` i ON i.id = cp.pos_item_id
            LEFT JOIN (
                SELECT li2.receipt_id, li2.item_id, li2.quantity, li2.gross_total, li2.cost
                FROM `{$p}pos_receipt_line_items` li2
                JOIN `{$p}pos_receipts` r ON r.id = li2.receipt_id
                WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
                  AND r.receipt_date BETWEEN ? AND ?
                  {$wh_sql}
            ) li ON li.item_id = cp.pos_item_id
            WHERE cp.active = 1
            GROUP BY cp.id, cp.name, cp.type, cp.discount_type, cp.discount_value,
                     i.rate, i.sku_name, cp.pos_item_id
            ORDER BY units_sold DESC, cp.name ASC
        ", $params)->result_array();

        foreach ($rows as &$row) {
            $ac_min = 0.0;
            $ac_max = 0.0;

            if ($row['promo_type'] === 'promo') {
                $comps = $this->db->query("
                    SELECT pc.quantity, COALESCE(ci.rate, 0) AS rate
                    FROM `{$p}pos_crm_promo_components` pc
                    LEFT JOIN `{$p}items` ci ON ci.id = pc.component_id
                    WHERE pc.promo_id = ? AND pc.component_type = 'product'
                ", [$row['id']])->result_array();
                foreach ($comps as $c) {
                    $val = (float) $c['quantity'] * (float) $c['rate'];
                    $ac_min += $val;
                    $ac_max += $val;
                }
            } elseif ($row['promo_type'] === 'set') {
                $products = $this->db->query("
                    SELECT pc.quantity, COALESCE(ci.rate, 0) AS rate
                    FROM `{$p}pos_crm_promo_components` pc
                    LEFT JOIN `{$p}items` ci ON ci.id = pc.component_id
                    WHERE pc.promo_id = ? AND pc.component_type = 'product'
                ", [$row['id']])->result_array();
                foreach ($products as $c) {
                    $val = (float) $c['quantity'] * (float) $c['rate'];
                    $ac_min += $val;
                    $ac_max += $val;
                }
                $set_mods = $this->db->query("
                    SELECT pc.quantity, COALESCE(m.price_adjustment, 0) AS rate
                    FROM `{$p}pos_crm_promo_components` pc
                    LEFT JOIN `{$p}modifiers` m ON m.id = pc.component_id
                    WHERE pc.promo_id = ? AND pc.component_type = 'modifier'
                ", [$row['id']])->result_array();
                foreach ($set_mods as $c) {
                    $val = (float) $c['quantity'] * (float) $c['rate'];
                    $ac_min += $val;
                    $ac_max += $val;
                }
            } elseif ($row['promo_type'] === 'bundle') {
                $groups = $this->db->query("
                    SELECT id, source_type, modifier_group_id
                    FROM `{$p}pos_crm_bundle_groups`
                    WHERE promo_id = ? ORDER BY sort_order ASC
                ", [$row['id']])->result_array();

                foreach ($groups as $g) {
                    if ($g['source_type'] === 'custom') {
                        $opts = $this->db->query("
                            SELECT bgo.option_type,
                                   COALESCE(i.rate, 0)             AS item_rate,
                                   COALESCE(m.price_adjustment, 0) AS mod_rate
                            FROM `{$p}pos_crm_bundle_group_options` bgo
                            LEFT JOIN `{$p}items` i    ON i.id = bgo.option_id AND bgo.option_type = 'item'
                            LEFT JOIN `{$p}modifiers` m ON m.id = bgo.option_id AND bgo.option_type = 'modifier'
                            WHERE bgo.bundle_group_id = ?
                        ", [$g['id']])->result_array();
                        if ($opts) {
                            $prices = array_map(function ($o) {
                                return $o['option_type'] === 'item' ? (float) $o['item_rate'] : (float) $o['mod_rate'];
                            }, $opts);
                            $nonzero = array_values(array_filter($prices, function ($v) {
                                return $v > 0;
                            }));
                            $ac_min += $nonzero ? min($nonzero) : 0;
                            $ac_max += max($prices);
                        }
                    } elseif ($g['source_type'] === 'modifier_group_ref' && $g['modifier_group_id']) {
                        $mods = $this->db->query("
                            SELECT COALESCE(price_adjustment, 0) AS rate
                            FROM `{$p}modifiers`
                            WHERE modifier_group_id = ? AND active = 1
                        ", [$g['modifier_group_id']])->result_array();
                        if ($mods) {
                            $rates = array_map('floatval', array_column($mods, 'rate'));
                            $nonzero = array_values(array_filter($rates, function ($v) {
                                return $v > 0;
                            }));
                            $ac_min += $nonzero ? min($nonzero) : 0;
                            $ac_max += max($rates);
                        }
                    }
                }

                // Fixed products always included — no range, exact value
                $fixed = $this->db->query("
                    SELECT pc.quantity, COALESCE(ci.rate, 0) AS rate
                    FROM `{$p}pos_crm_promo_components` pc
                    LEFT JOIN `{$p}items` ci ON ci.id = pc.component_id
                    WHERE pc.promo_id = ? AND pc.component_type = 'product'
                ", [$row['id']])->result_array();
                foreach ($fixed as $f) {
                    $val = (float) $f['quantity'] * (float) $f['rate'];
                    $ac_min += $val;
                    $ac_max += $val;
                }
            }

            $row['alacarte_min'] = round($ac_min, 2);
            $row['alacarte_max'] = round($ac_max, 2);
            $row['alacarte_value'] = round($ac_max, 2); // worst-case for status/savings
            $sp = (float) $row['selling_price'];
            $av = (float) $row['alacarte_value'];
            $row['savings_per_use'] = round(max(0, $av - $sp), 2);
            $row['savings_pct'] = $av > 0 ? round($row['savings_per_use'] / $av * 100, 1) : 0;
            $row['total_savings'] = round($row['savings_per_use'] * (float) $row['units_sold'], 2);
            $units = (float) $row['units_sold'];
            $row['avg_revenue'] = $units > 0 ? round($row['total_revenue'] / $units, 2) : 0;
            $row['gross_margin_pct'] = $row['total_revenue'] > 0
                ? round((1 - $row['total_cost'] / $row['total_revenue']) * 100, 1)
                : 0;
        }

        return $rows;
    }

    public function get_report_pos_bundle_feasibility()
    {
        $p = db_prefix();

        $bundles = $this->db->query("
            SELECT b.id,
                   b.name        AS bundle_name,
                   b.price       AS bundle_price,
                   b.description,
                   COUNT(bi.id)  AS component_count,
                   COALESCE(SUM(bi.quantity * COALESCE(ci.rate, 0)), 0) AS alacarte_value
            FROM `{$p}pos_bundles` b
            LEFT JOIN `{$p}pos_bundle_items` bi ON bi.bundle_id = b.id
            LEFT JOIN `{$p}items` ci            ON ci.id = bi.item_id
            WHERE b.active = 1 AND b.deleted_at IS NULL
            GROUP BY b.id, b.name, b.price, b.description
            ORDER BY b.name ASC
        ")->result_array();

        foreach ($bundles as &$b) {
            $price = (float) $b['bundle_price'];
            $av = (float) $b['alacarte_value'];
            $b['savings'] = round(max(0, $av - $price), 2);
            $b['savings_pct'] = $av > 0 ? round($b['savings'] / $av * 100, 1) : 0;
            $b['markup_pct'] = $price > 0 && $av > 0 ? round(($av / $price - 1) * 100, 1) : 0;
        }

        return $bundles;
    }

    public function delete_pos_product($id)
    {
        $id = (int) $id;

        $used = $this->db->where('item_id', $id)
            ->count_all_results(db_prefix() . 'pos_receipt_line_items');

        if ($used > 0) {
            return [
                'success' => false,
                'message' => 'Product is used in ' . $used . ' transaction(s) and cannot be deleted.',
            ];
        }

        // Remove modifier assignments before deleting
        $this->db->where('pos_item_id', (string) $id)->delete(db_prefix() . 'item_modifier_groups');
        $modifiers = $this->db->select('id')->where('pos_item_id', (string) $id)
            ->get(db_prefix() . 'item_modifiers')->result_array();
        foreach ($modifiers as $m) {
            $this->db->where('item_modifier_id', $m['id'])->delete(db_prefix() . 'item_modifier_options');
        }
        $this->db->where('pos_item_id', (string) $id)->delete(db_prefix() . 'item_modifiers');

        $this->db->where('id', $id)
            ->where('can_be_sold', 'can_be_sold')
            ->where('can_be_manufacturing', 'can_be_manufacturing')
            ->delete(db_prefix() . 'items');

        if ($this->db->affected_rows() > 0) {
            return ['success' => true, 'message' => 'Product deleted.'];
        }

        return ['success' => false, 'message' => 'Product not found.'];
    }

    // --- Modifier Analytics ---

    public function get_report_modifiers_summary($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $p = db_prefix();

        $row = $this->db->query("
            SELECT
                COALESCE(SUM(li.modifiers_price), 0) AS total_modifier_revenue,
                COUNT(CASE WHEN li.modifier_ids IS NOT NULL
                            AND li.modifier_ids NOT IN ('[]','null','') THEN 1 END) AS line_items_with_modifiers,
                COUNT(DISTINCT CASE WHEN li.modifier_ids IS NOT NULL
                            AND li.modifier_ids NOT IN ('[]','null','') THEN r.id END) AS receipts_with_modifiers,
                COUNT(DISTINCT r.id) AS total_receipts
            FROM `{$p}pos_receipt_line_items` li
            JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
        ", [$from, $to])->row_array();

        return [
            'total_modifier_revenue' => round((float) ($row['total_modifier_revenue'] ?? 0), 2),
            'line_items_with_modifiers' => (int) ($row['line_items_with_modifiers'] ?? 0),
            'receipts_with_modifiers' => (int) ($row['receipts_with_modifiers'] ?? 0),
            'total_receipts' => (int) ($row['total_receipts'] ?? 0),
        ];
    }

    public function get_report_modifiers_top($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $p = db_prefix();

        return $this->db->query("
            SELECT
                m.id                AS modifier_id,
                mg.name             AS group_name,
                m.name              AS modifier_name,
                m.price_adjustment,
                COUNT(li.id)             AS attach_count,
                COUNT(DISTINCT r.id)     AS order_count,
                COALESCE(SUM(li.quantity), 0) AS total_quantity
            FROM `{$p}modifiers` m
            JOIN `{$p}modifier_groups` mg ON mg.id = m.modifier_group_id
            JOIN `{$p}pos_receipt_line_items` li
                ON li.modifier_ids IS NOT NULL
               AND li.modifier_ids NOT IN ('[]','null','')
               AND JSON_CONTAINS(li.modifier_ids, CAST(m.id AS CHAR), '$')
            JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY m.id, mg.name, m.name, m.price_adjustment
            ORDER BY attach_count DESC
        ", [$from, $to])->result_array();
    }

    public function get_item_transaction_detail($item_id, $date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $p = db_prefix();

        $rows = $this->db->query("
            SELECT r.receipt_number,
                   DATE_FORMAT(r.receipt_date, '%Y-%m-%d %H:%i') AS receipt_date,
                   w.warehouse_name,
                   e.name       AS cashier_name,
                   li.quantity,
                   li.unit_price,
                   li.modifiers_price,
                   li.total_money,
                   li.modifier_names,
                   li.line_note,
                   r.total_money AS receipt_total
            FROM `{$p}pos_receipt_line_items` li
            JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
            LEFT JOIN `{$p}warehouse` w ON w.warehouse_id = r.warehouse_id
            LEFT JOIN `{$p}pos_employees` e ON e.id = r.employee_id
            WHERE li.item_id = ?
              AND r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            ORDER BY r.receipt_date DESC
            LIMIT 300
        ", [(int) $item_id, $from, $to])->result_array();

        foreach ($rows as &$r) {
            $r['modifier_names'] = json_decode($r['modifier_names'] ?? '[]', true) ?: [];
            $r['quantity'] = (float) $r['quantity'];
            $r['unit_price'] = round((float) $r['unit_price'], 2);
            $r['modifiers_price'] = round((float) $r['modifiers_price'], 2);
            $r['total_money'] = round((float) $r['total_money'], 2);
            $r['receipt_total'] = round((float) $r['receipt_total'], 2);
        }
        unset($r);
        return $rows;
    }

    public function get_item_modifier_usage($item_id, $date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $p = db_prefix();

        return $this->db->query("
            SELECT mg.name AS group_name,
                   m.name  AS modifier_name,
                   COUNT(li.id)         AS times_applied,
                   COUNT(DISTINCT r.id) AS order_count
            FROM `{$p}pos_receipt_line_items` li
            JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
            JOIN `{$p}modifiers` m
                ON li.modifier_ids IS NOT NULL
               AND li.modifier_ids NOT IN ('[]','null','')
               AND JSON_CONTAINS(li.modifier_ids, CAST(m.id AS CHAR), '$')
            JOIN `{$p}modifier_groups` mg ON mg.id = m.modifier_group_id
            WHERE li.item_id = ?
              AND r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY m.id, mg.name, m.name
            ORDER BY order_count DESC
        ", [(int) $item_id, $from, $to])->result_array();
    }

    public function get_modifier_transaction_detail($modifier_id, $date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $p = db_prefix();

        $rows = $this->db->query("
            SELECT r.receipt_number,
                   DATE_FORMAT(r.receipt_date, '%Y-%m-%d %H:%i') AS receipt_date,
                   w.warehouse_name,
                   e.name       AS cashier_name,
                   li.item_name,
                   li.quantity,
                   li.unit_price,
                   li.total_money,
                   r.total_money AS receipt_total
            FROM `{$p}modifiers` m
            JOIN `{$p}pos_receipt_line_items` li
                ON li.modifier_ids IS NOT NULL
               AND li.modifier_ids NOT IN ('[]','null','')
               AND JSON_CONTAINS(li.modifier_ids, CAST(m.id AS CHAR), '$')
            JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
            LEFT JOIN `{$p}warehouse` w ON w.warehouse_id = r.warehouse_id
            LEFT JOIN `{$p}pos_employees` e ON e.id = r.employee_id
            WHERE m.id = ?
              AND r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            ORDER BY r.receipt_date DESC
            LIMIT 300
        ", [(int) $modifier_id, $from, $to])->result_array();

        foreach ($rows as &$r) {
            $r['quantity'] = (float) $r['quantity'];
            $r['unit_price'] = round((float) $r['unit_price'], 2);
            $r['total_money'] = round((float) $r['total_money'], 2);
            $r['receipt_total'] = round((float) $r['receipt_total'], 2);
        }
        unset($r);
        return $rows;
    }

    public function get_modifier_co_items($modifier_id, $date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $p = db_prefix();

        return $this->db->query("
            SELECT li2.item_name,
                   COUNT(DISTINCT r.id)          AS order_count,
                   COALESCE(SUM(li2.quantity), 0)    AS total_qty,
                   COALESCE(SUM(li2.total_money), 0) AS total_revenue
            FROM `{$p}modifiers` m
            JOIN `{$p}pos_receipt_line_items` li
                ON li.modifier_ids IS NOT NULL
               AND li.modifier_ids NOT IN ('[]','null','')
               AND JSON_CONTAINS(li.modifier_ids, CAST(m.id AS CHAR), '$')
            JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
            JOIN `{$p}pos_receipt_line_items` li2 ON li2.receipt_id = r.id
            WHERE m.id = ?
              AND r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY li2.item_name
            ORDER BY order_count DESC
            LIMIT 20
        ", [(int) $modifier_id, $from, $to])->result_array();
    }

    public function get_report_modifier_groups($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to = $date_to . ' 23:59:59';
        $wh = $warehouse_id ? 'AND r.warehouse_id = ' . (int) $warehouse_id : '';
        $p = db_prefix();

        return $this->db->query("
            SELECT
                mg.id,
                mg.name             AS group_name,
                mg.selection_type,
                COUNT(DISTINCT m.id) AS modifier_count,
                COUNT(li.id)         AS attach_count,
                COALESCE(SUM(li.quantity), 0) AS total_quantity
            FROM `{$p}modifier_groups` mg
            JOIN `{$p}modifiers` m ON m.modifier_group_id = mg.id
            JOIN `{$p}pos_receipt_line_items` li
                ON li.modifier_ids IS NOT NULL
               AND li.modifier_ids NOT IN ('[]','null','')
               AND JSON_CONTAINS(li.modifier_ids, CAST(m.id AS CHAR), '$')
            JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY mg.id, mg.name, mg.selection_type
            ORDER BY attach_count DESC
        ", [$from, $to])->result_array();
    }

    // =========================================================================
    // CSV Import
    // =========================================================================

    /**
     * Import walk-in receipts from a parsed CSV.
     *
     * @param  array $rows         Parsed CSV rows (assoc arrays keyed by header name).
     * @param  int   $warehouse_id Target warehouse.
     * @return array ['imported'=>int, 'skipped'=>int, 'errors'=>array]
     */
    public function import_walk_in_csv(array $rows, int $warehouse_id, ?string $filename = null): array
    {
        $p = db_prefix();
        $batch_id = $this->_create_import_batch('IMPORT', $warehouse_id, $filename, count($rows));

        // Load payment modes indexed by lowercase name for fuzzy matching.
        $raw_modes = $this->db->select('id, name')->get("{$p}payment_modes")->result_array();
        $mode_by_name = [];
        foreach ($raw_modes as $m) {
            $mode_by_name[strtolower(trim($m['name']))] = (int) $m['id'];
        }

        // Static CSV column name → payment type string mapping.
        $col_type_map = [
            'cash' => 'CASH',
            'credit card' => 'CREDIT_CARD',
            'debit card' => 'DEBIT_CARD',
            'store credit' => 'STORE_CREDIT',
            'duitnow qr' => 'DIGITAL',
            'duitnow qr - manual' => 'DIGITAL',
            'paywave' => 'DIGITAL',
            'grabfood' => 'DIGITAL',
            'grabfood (deleted)' => 'DIGITAL',
            'foodpanda' => 'DIGITAL',
            'foodpanda (deleted)' => 'DIGITAL',
            'shopeefood - manual' => 'DIGITAL',
            'cash (deleted)' => 'CASH',
            'inventory (deleted)' => 'OTHER',
        ];

        // Detect payment columns from the first row's keys.
        $non_payment_cols = [
            'time',
            'receipt number',
            'original sale receipt number',
            'store',
            'register id',
            'employee',
            'transaction type',
            'customer',
            'is_cancelled',
            'subtotal',
            'discount',
            'service charge',
            'tax',
            'rounding',
            'total',
            'notes',
        ];
        $all_keys = array_keys($rows[0] ?? []);
        $payment_cols = [];
        foreach ($all_keys as $k) {
            if (!in_array(strtolower(trim($k)), $non_payment_cols)) {
                $payment_cols[] = $k;
            }
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // 1-based, accounting for header row

            $receipt_number = trim($row['Receipt Number'] ?? '');
            if ($receipt_number === '') {
                $errors[] = "Row {$line}: missing Receipt Number — skipped";
                $skipped++;
                continue;
            }

            // Skip if already imported.
            $exists = $this->db->where('receipt_number', $receipt_number)
                ->get("{$p}pos_receipts")->row();
            if ($exists) {
                $skipped++;
                continue;
            }

            // Parse receipt date.
            $time_str = trim($row['Time'] ?? '');
            $receipt_date = $time_str ? date('Y-m-d H:i:s', strtotime($time_str)) : date('Y-m-d H:i:s');
            if ($receipt_date === '1970-01-01 00:00:00') {
                $receipt_date = date('Y-m-d H:i:s');
            }

            $transaction_type = strtolower(trim($row['Transaction Type'] ?? 'sale'));
            $receipt_type = $transaction_type === 'return' ? 'REFUND' : 'SALE';

            $is_cancelled = strtolower(trim($row['Is_Cancelled'] ?? '')) === 'true';
            $cancelled_at = $is_cancelled ? $receipt_date : null;

            $subtotal = (float) str_replace(',', '', $row['SubTotal'] ?? 0);
            $total_discount = (float) str_replace(',', '', $row['Discount'] ?? 0);
            $surcharge = (float) str_replace(',', '', $row['Service Charge'] ?? 0);
            $total_tax = (float) str_replace(',', '', $row['Tax'] ?? 0);
            $total_money = (float) str_replace(',', '', $row['Total'] ?? 0);
            $note = trim($row['Notes'] ?? '');
            $refund_for = trim($row['Original Sale Receipt Number'] ?? '') ?: null;

            $cashback_qr_token = bin2hex(random_bytes(32));

            $this->db->trans_start();

            $this->db->insert("{$p}pos_receipts", [
                'receipt_number' => $receipt_number,
                'receipt_type' => $receipt_type,
                'refund_for' => $refund_for,
                'warehouse_id' => $warehouse_id,
                'employee_id' => null,
                'shift_id' => null,
                'customer_id' => null,
                'loyalty_customer_id' => null,
                'cashback_qr_token' => $cashback_qr_token,
                'note' => $note ?: null,
                'dining_option' => null,
                'source' => 'IMPORT',
                'subtotal' => $subtotal,
                'total_discount' => $total_discount,
                'total_tax' => $total_tax,
                'tip' => 0,
                'surcharge' => $surcharge,
                'total_money' => $total_money,
                'points_earned' => 0,
                'points_deducted' => 0,
                'cancelled_at' => $cancelled_at,
                'receipt_date' => $receipt_date,
                'uploaded_at' => date('Y-m-d H:i:s'),
            ]);
            $receipt_id = $this->db->insert_id();

            // Dummy line item so the transactions UI subtotal subquery returns the right value.
            $this->db->insert("{$p}pos_receipt_line_items", [
                'receipt_id' => $receipt_id,
                'item_id' => 0,
                'item_name' => 'Walk-in Sales',
                'variant_id' => null,
                'variant_name' => null,
                'quantity' => 1,
                'unit_price' => $subtotal,
                'cost' => 0,
                'gross_total' => $subtotal,
                'total_discount' => $total_discount,
                'total_tax' => $total_tax,
                'total_money' => $total_money,
                'modifier_ids' => '[]',
                'modifier_names' => '[]',
                'modifiers_price' => 0,
                'tax_ids' => '[]',
                'line_note' => null,
            ]);

            // Insert a payment row for each non-zero payment column.
            foreach ($payment_cols as $col) {
                $amount = (float) str_replace(',', '', $row[$col] ?? 0);
                if ($amount == 0) {
                    continue;
                }
                $col_lower = strtolower(trim($col));
                $payment_type = $col_type_map[$col_lower] ?? 'OTHER';
                $payment_type_id = $mode_by_name[$col_lower] ?? 0;

                $this->db->insert("{$p}pos_receipt_payments", [
                    'receipt_id' => $receipt_id,
                    'payment_type_id' => $payment_type_id,
                    'payment_name' => $col,
                    'type' => $payment_type,
                    'money_amount' => $amount,
                    'cash_back' => 0,
                    'payment_date' => $receipt_date,
                ]);
            }

            $this->db->trans_complete();

            if ($this->db->trans_status() === false) {
                $errors[] = "Row {$line}: DB error inserting {$receipt_number}";
                $skipped++;
            } else {
                $imported++;
            }
        }

        $this->_finalize_import_batch($batch_id, $imported, $skipped, $errors);
        return ['batch_id' => $batch_id, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    // -------------------------------------------------------------------------
    // Import platform (GrabFood / FoodPanda / ShopeeFood) CSV sales
    // -------------------------------------------------------------------------

    public function import_platform_csv(array $rows, int $warehouse_id, string $source, ?string $filename = null): array
    {
        $p = db_prefix();
        $source = strtoupper($source);
        $batch_id = $this->_create_import_batch($source, $warehouse_id, $filename, count($rows));

        $source_map = [
            'GRABFOOD' => 'GrabFood',
            'FOODPANDA' => 'FoodPanda',
            'SHOPEEFOOD' => 'ShopeeFood',
        ];
        $source_label = $source_map[$source] ?? $source;

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;

            $receipt_number = ''
                ?? trim($row['Order ID'] ?? '')
                ?? trim($row['OrderID'] ?? '')
                ?? trim($row['Receipt Number'] ?? '');
            if ($receipt_number === '') {
                $errors[] = "Row {$line}: missing Order ID / Receipt Number — skipped";
                $skipped++;
                continue;
            }

            $exists = $this->db
                ->where('(receipt_number = ? OR receipt_number = ?)', [
                    $receipt_number,
                    $source . '-' . $receipt_number,
                ])
                ->get("{$p}pos_receipts")->row();
            if ($exists) {
                $skipped++;
                continue;
            }

            $date_str = trim(
                $row['Order Date'] ?? $row['OrderTime'] ?? $row['Date'] ?? $row['Time'] ?? ''
            );
            $receipt_date = $date_str
                ? (@date('Y-m-d H:i:s', strtotime($date_str)) ?: null)
                : null;
            if (!$receipt_date || $receipt_date === '1970-01-01 00:00:00') {
                $receipt_date = date('Y-m-d H:i:s');
            }

            $status_raw = strtolower(trim(
                $row['Order Status'] ?? $row['Status'] ?? $row['OrderStatus'] ?? 'completed'
            ));
            $cancelled_statuses = ['cancelled', 'canceled', 'rejected', 'refunded', 'failed', 'void', 'declined', 'not found', 'driver_not_found'];
            $refund_statuses = ['refunded', 'returned'];
            $is_cancelled = in_array($status_raw, $cancelled_statuses, true);
            $is_refund = in_array($status_raw, $refund_statuses, true);
            $receipt_type = $is_refund ? 'REFUND' : 'SALE';
            $sign = $is_refund ? -1 : 1;

            $find_amount = function (...$keys) use ($row) {
                foreach ($keys as $k) {
                    if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
                        return (float) str_replace(',', '', $row[$k]);
                    }
                }
                return 0.0;
            };

            $subtotal = $find_amount('Subtotal', 'Sub Total', 'SubTotal', 'Gross Sales', 'Gross Amount') * $sign;
            $discount = $find_amount('Promo / Discount', 'Discount', 'Voucher / Discount', 'Voucher', 'Promo') * $sign;
            $tax = $find_amount('Tax', 'VAT / Tax', 'VAT', 'Service Tax') * $sign;
            $delivery_fee = $find_amount('Delivery Fee', 'Shipping Fee', 'DeliveryCharge');
            $eater_total = $find_amount('Eater Payment', 'Eater Paid', 'Eater Total', 'Total Amount', 'Total Order', 'Customer Paid') * $sign;
            $merchant_get = $find_amount('Merchant Payout', 'Merchant Remittance', 'Payout', 'Remittance', 'Net Payout', 'Net Amount', 'Settlement Amount') * $sign;

            if ($merchant_get == 0) {
                $merchant_get = $eater_total;
            }
            if ($eater_total == 0) {
                $eater_total = $subtotal - $discount + $tax + $delivery_fee;
            }

            $cust_name = trim($row['Customer Name'] ?? $row['Customer'] ?? $row['Buyer Name'] ?? '');
            $cust_phone = trim($row['Customer Phone'] ?? $row['Customer Contact'] ?? $row['Phone'] ?? $row['Buyer Phone'] ?? '');
            $short_ref = trim($row['Short Ref'] ?? $row['Short Order Number'] ?? $row['Queue No'] ?? $row['Receipt No'] ?? '');
            $notes = trim($row['Promo Notes'] ?? $row['Remarks'] ?? $row['Notes'] ?? $row['Special Instructions'] ?? '');

            $note_parts = [];
            if ($cust_name)
                $note_parts[] = 'Customer: ' . $cust_name;
            if ($cust_phone)
                $note_parts[] = $cust_phone;
            if ($notes)
                $note_parts[] = $notes;
            $note = implode(' | ', $note_parts) ?: null;

            $receipt_number_stored = $source . '-' . $receipt_number;
            $cancelled_at = $is_cancelled ? $receipt_date : null;

            $cashback_qr_token = bin2hex(random_bytes(32));

            $this->db->trans_start();

            $this->db->insert("{$p}pos_receipts", [
                'receipt_number' => $receipt_number_stored,
                'receipt_type' => $receipt_type,
                'warehouse_id' => $warehouse_id,
                'employee_id' => null,
                'shift_id' => null,
                'customer_id' => null,
                'loyalty_customer_id' => null,
                'cashback_qr_token' => $cashback_qr_token,
                'note' => $note,
                'source' => $source,
                'dining_option' => 'DELIVERY',
                'queue_number' => $short_ref ?: null,
                'subtotal' => round($subtotal, 2),
                'total_discount' => round($discount, 2),
                'total_tax' => round($tax, 2),
                'tip' => 0,
                'surcharge' => 0,
                'total_money' => round($merchant_get, 2),
                'points_earned' => 0,
                'points_deducted' => 0,
                'cancelled_at' => $cancelled_at,
                'receipt_date' => $receipt_date,
                'uploaded_at' => date('Y-m-d H:i:s'),
            ]);
            $receipt_id = $this->db->insert_id();

            $line_title = $source_label . ' Sale — Order ' . $receipt_number;
            $this->db->insert("{$p}pos_receipt_line_items", [
                'receipt_id' => $receipt_id,
                'item_id' => 0,
                'item_name' => $line_title,
                'variant_id' => null,
                'variant_name' => null,
                'quantity' => 1,
                'unit_price' => round($subtotal, 2),
                'cost' => 0,
                'gross_total' => round($subtotal, 2),
                'total_discount' => round($discount, 2),
                'total_tax' => round($tax, 2),
                'total_money' => round($subtotal - $discount + $tax, 2),
                'modifier_ids' => '[]',
                'modifier_names' => '[]',
                'modifiers_price' => 0,
                'tax_ids' => '[]',
                'line_note' => $delivery_fee > 0 ? "Delivery fee: {$delivery_fee}" : null,
            ]);

            $this->db->insert("{$p}pos_receipt_payments", [
                'receipt_id' => $receipt_id,
                'payment_type_id' => 0,
                'payment_name' => $source_label,
                'type' => $source,
                'money_amount' => round($merchant_get, 2),
                'cash_back' => 0,
                'payment_date' => $receipt_date,
            ]);

            $this->db->trans_complete();

            if ($this->db->trans_status() === false) {
                $errors[] = "Row {$line}: DB error inserting {$receipt_number}";
                $skipped++;
            } else {
                $imported++;
            }
        }

        $this->_finalize_import_batch($batch_id, $imported, $skipped, $errors);
        return ['batch_id' => $batch_id, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    // -------------------------------------------------------------------------
    // Import batch audit helpers
    // -------------------------------------------------------------------------

    private function _create_import_batch(string $source, int $warehouse_id, ?string $filename, int $total_rows): ?int
    {
        $p = db_prefix();
        // Graceful fallback if the batches table doesn't exist yet (pre-migration).
        $table_exists = $this->db->query("SHOW TABLES LIKE '{$p}pos_import_batches'")->num_rows() > 0;
        if (!$table_exists) {
            return null;
        }
        $this->db->insert("{$p}pos_import_batches", [
            'source' => $source,
            'warehouse_id' => $warehouse_id,
            'filename' => $filename ? substr($filename, 0, 255) : null,
            'total_rows' => $total_rows,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id() ?: null;
    }

    private function _finalize_import_batch(?int $batch_id, int $imported, int $skipped, array $errors): void
    {
        if (!$batch_id)
            return;
        $p = db_prefix();
        $this->db->where('id', $batch_id)->update("{$p}pos_import_batches", [
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'error_count' => count($errors),
            'error_log' => empty($errors) ? null : substr(implode("\n", $errors), 0, 65000),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function import_cost_ingredients_sheet(array $rows)
    {
        $p = db_prefix();
        $created = 0;
        $updated = 0;
        $errors = [];

        $default_group_id = $this->db
            ->select('id')
            ->where('LOWER(name)', 'raw materials')
            ->or_where('LOWER(name)', 'raw material')
            ->or_where('LOWER(name)', 'rawmaterials')
            ->limit(1)
            ->get("{$p}items_groups")
            ->row();
        $group_id = $default_group_id ? (int)$default_group_id->id : null;
        if (!$group_id) {
            $any_group = $this->db->limit(1)->get("{$p}items_groups")->row();
            $group_id = $any_group ? (int)$any_group->id : null;
        }

        foreach ($rows as $idx => $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $sku_code = trim((string)($row['sku_code'] ?? $row['sku'] ?? ''));
            $sku_name = trim((string)($row['sku_name'] ?? $row['item name'] ?? $row['name'] ?? ''));
            if ($sku_code === '' && $sku_name === '') {
                $errors[] = 'Row ' . ($idx + 1) . ': SKU or Item Name is required.';
                continue;
            }

            $batch_size = isset($row['batch_size']) ? (float)$row['batch_size'] : 1;
            $units_per_batch = isset($row['units_per_batch']) ? (float)$row['units_per_batch'] : 1;
            $batch_uom = isset($row['batch_uom']) ? trim((string)$row['batch_uom']) : null;
            $unit_uom = isset($row['unit_uom']) ? trim((string)$row['unit_uom']) : null;
            $purchase_price = isset($row['purchase_price']) ? (float)$row['purchase_price'] : (isset($row['cost per batch']) ? (float)$row['cost per batch'] : 0);
            $description = isset($row['description']) ? trim((string)$row['description']) : '';

            $existing = null;
            if ($sku_code !== '') {
                $existing = $this->db
                    ->where('sku_code', $sku_code)
                    ->limit(1)
                    ->get("{$p}items")
                    ->row_array();
            }
            if (!$existing && $sku_name !== '') {
                $existing = $this->db
                    ->where('sku_name', $sku_name)
                    ->limit(1)
                    ->get("{$p}items")
                    ->row_array();
            }

            $data = [
                'item_type' => 'raw_ingredient',
                'batch_size' => $batch_size,
                'units_per_batch' => $units_per_batch,
                'batch_uom' => $batch_uom,
                'unit_uom' => $unit_uom,
                'purchase_price' => $purchase_price,
            ];

            if ($existing) {
                if ($sku_code !== '') {
                    $data['sku_code'] = $sku_code;
                }
                if ($sku_name !== '') {
                    $data['sku_name'] = $sku_name;
                }
                if ($description !== '') {
                    $data['description'] = $description;
                }
                $this->db
                    ->where('id', (int)$existing['id'])
                    ->update("{$p}items", $data);
                $updated++;
            } else {
                if ($sku_code === '') {
                    $sku_code = 'RAW' . strtoupper(substr(md5(uniqid()), 0, 8));
                }
                if ($sku_name === '') {
                    $sku_name = $sku_code;
                }
                $data['sku_code'] = $sku_code;
                $data['sku_name'] = $sku_name;
                $data['description'] = $description;
                $data['can_be_purchased'] = 1;
                $data['can_be_sold'] = 0;
                $data['can_be_inventory'] = 1;
                $data['can_be_manufacturing'] = 'can_be_manufacturing';
                $data['group_id'] = $group_id;
                $data['active'] = 1;
                $data['commodity_type'] = 5;
                $data['parent_id'] = null;
                $this->db->insert("{$p}items", $data);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function import_cost_packaging_sheet(array $rows)
    {
        $p = db_prefix();
        $created = 0;
        $updated = 0;
        $errors = [];

        $default_group_id = $this->db
            ->select('id')
            ->where('LOWER(name)', 'packaging')
            ->limit(1)
            ->get("{$p}items_groups")
            ->row();
        $group_id = $default_group_id ? (int)$default_group_id->id : null;
        if (!$group_id) {
            $any_group = $this->db->limit(1)->get("{$p}items_groups")->row();
            $group_id = $any_group ? (int)$any_group->id : null;
        }

        foreach ($rows as $idx => $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $sku_code = trim((string)($row['sku_code'] ?? $row['sku'] ?? ''));
            $sku_name = trim((string)($row['sku_name'] ?? $row['item name'] ?? $row['name'] ?? ''));
            if ($sku_code === '' && $sku_name === '') {
                $errors[] = 'Row ' . ($idx + 1) . ': SKU or Item Name is required.';
                continue;
            }

            $batch_size = isset($row['batch_size']) ? (float)$row['batch_size'] : 1;
            $units_per_batch = isset($row['units_per_batch']) ? (float)$row['units_per_batch'] : 1;
            $batch_uom = isset($row['batch_uom']) ? trim((string)$row['batch_uom']) : null;
            $unit_uom = isset($row['unit_uom']) ? trim((string)$row['unit_uom']) : null;
            $purchase_price = isset($row['purchase_price']) ? (float)$row['purchase_price'] : (isset($row['cost per batch']) ? (float)$row['cost per batch'] : 0);
            $description = isset($row['description']) ? trim((string)$row['description']) : '';

            $existing = null;
            if ($sku_code !== '') {
                $existing = $this->db
                    ->where('sku_code', $sku_code)
                    ->limit(1)
                    ->get("{$p}items")
                    ->row_array();
            }
            if (!$existing && $sku_name !== '') {
                $existing = $this->db
                    ->where('sku_name', $sku_name)
                    ->limit(1)
                    ->get("{$p}items")
                    ->row_array();
            }

            $data = [
                'item_type' => 'packaging',
                'batch_size' => $batch_size,
                'units_per_batch' => $units_per_batch,
                'batch_uom' => $batch_uom,
                'unit_uom' => $unit_uom,
                'purchase_price' => $purchase_price,
            ];

            if ($existing) {
                if ($sku_code !== '') {
                    $data['sku_code'] = $sku_code;
                }
                if ($sku_name !== '') {
                    $data['sku_name'] = $sku_name;
                }
                if ($description !== '') {
                    $data['description'] = $description;
                }
                $this->db
                    ->where('id', (int)$existing['id'])
                    ->update("{$p}items", $data);
                $updated++;
            } else {
                if ($sku_code === '') {
                    $sku_code = 'PKG' . strtoupper(substr(md5(uniqid()), 0, 8));
                }
                if ($sku_name === '') {
                    $sku_name = $sku_code;
                }
                $data['sku_code'] = $sku_code;
                $data['sku_name'] = $sku_name;
                $data['description'] = $description;
                $data['can_be_purchased'] = 1;
                $data['can_be_sold'] = 0;
                $data['can_be_inventory'] = 1;
                $data['can_be_manufacturing'] = 'can_be_manufacturing';
                $data['group_id'] = $group_id;
                $data['active'] = 1;
                $data['commodity_type'] = 5;
                $data['parent_id'] = null;
                $this->db->insert("{$p}items", $data);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function import_cost_mixed_sheet(string $sheet_name, array $header_summary, array $ingredient_rows, array $mixed_rows)
    {
        $p = db_prefix();
        $sheet_name = trim($sheet_name);
        $errors = [];
        $created = 0;
        $updated = 0;

        if ($sheet_name === '') {
            return ['created' => 0, 'updated' => 0, 'errors' => ['Sheet name is empty.']];
        }

        $item = $this->db
            ->group_start()
            ->where('sku_code', $sheet_name)
            ->or_where('sku_name', $sheet_name)
            ->group_end()
            ->limit(1)
            ->get("{$p}items")
            ->row_array();

        $item_data = [
            'item_type' => 'mixed_ingredient',
        ];

        if ($item) {
            $item_id = (int)$item['id'];
            $this->db
                ->where('id', $item_id)
                ->update("{$p}items", $item_data);
            $updated++;
        } else {
            $item_data['sku_code'] = 'MIX' . strtoupper(substr(md5(uniqid()), 0, 8));
            $item_data['sku_name'] = $sheet_name;
            $item_data['can_be_purchased'] = 0;
            $item_data['can_be_sold'] = 0;
            $item_data['can_be_inventory'] = 1;
            $item_data['can_be_manufacturing'] = 'can_be_manufacturing';
            $item_data['active'] = 1;
            $item_data['commodity_type'] = 5;
            $item_data['parent_id'] = null;
            $item_data['batch_size'] = 1;
            $item_data['units_per_batch'] = 1;
            $this->db->insert("{$p}items", $item_data);
            $item_id = (int)$this->db->insert_id();
            $created++;
        }

        $total_yield = isset($header_summary['total_units']) ? (float)$header_summary['total_units'] : (isset($header_summary['batch yield']) ? (float)$header_summary['batch yield'] : 1);
        $yield_uom = isset($header_summary['yield_uom']) ? trim((string)$header_summary['yield_uom']) : null;
        $prep_minutes = isset($header_summary['prep_minutes']) ? (int)$header_summary['prep_minutes'] : null;
        $active = isset($header_summary['active']) ? (int)$header_summary['active'] : 1;

        $mixed_row = $this->db
            ->where('item_id', $item_id)
            ->limit(1)
            ->get("{$p}pos_mixed_ingredients")
            ->row_array();

        $mixed_payload = [
            'item_id' => $item_id,
            'total_batches_yield' => $total_yield > 0 ? $total_yield : 1,
            'yield_uom' => $yield_uom,
            'prep_minutes' => $prep_minutes,
            'active' => $active,
        ];

        if ($mixed_row) {
            $mixed_id = (int)$mixed_row['id'];
            $this->db
                ->where('id', $mixed_id)
                ->update("{$p}pos_mixed_ingredients", $mixed_payload);
        } else {
            $this->db->insert("{$p}pos_mixed_ingredients", $mixed_payload);
            $mixed_id = (int)$this->db->insert_id();
        }

        $this->db
            ->where('mixed_ingredient_id', $mixed_id)
            ->delete("{$p}pos_mixed_ingredient_components");

        $sort_order = 0;
        $component_inserted = 0;

        foreach ($ingredient_rows as $idx => $row) {
            $row_lc = array_change_key_case($row, CASE_LOWER);
            $comp_sku = trim((string)($row_lc['component sku'] ?? $row_lc['sku_code'] ?? $row_lc['sku'] ?? ''));
            $comp_name = trim((string)($row_lc['component name'] ?? $row_lc['name'] ?? ''));
            $qty = isset($row_lc['quantity']) ? (float)$row_lc['quantity'] : 0;
            $uom = isset($row_lc['uom']) ? trim((string)$row_lc['uom']) : null;
            $note = isset($row_lc['note']) ? trim((string)$row_lc['note']) : null;

            if ($qty <= 0 || ($comp_sku === '' && $comp_name === '')) {
                continue;
            }

            $comp_item = null;
            if ($comp_sku !== '') {
                $comp_item = $this->db
                    ->where('sku_code', $comp_sku)
                    ->limit(1)
                    ->get("{$p}items")
                    ->row_array();
            }
            if (!$comp_item && $comp_name !== '') {
                $comp_item = $this->db
                    ->where('sku_name', $comp_name)
                    ->limit(1)
                    ->get("{$p}items")
                    ->row_array();
            }

            if (!$comp_item) {
                $errors[] = 'Ingredient row ' . ($idx + 1) . ': component "' . ($comp_sku ?: $comp_name) . '" not found.';
                continue;
            }

            $comp_type = $comp_item['item_type'] ?? '';
            if (!in_array($comp_type, ['raw_ingredient', 'packaging'])) {
                $comp_type = 'raw_ingredient';
            }

            $this->db->insert("{$p}pos_mixed_ingredient_components", [
                'mixed_ingredient_id' => $mixed_id,
                'component_type' => $comp_type,
                'component_item_id' => (int)$comp_item['id'],
                'quantity' => $qty,
                'uom' => $uom,
                'sort_order' => $sort_order++,
                'note' => $note,
            ]);
            $component_inserted++;
        }

        foreach ($mixed_rows as $idx => $row) {
            $row_lc = array_change_key_case($row, CASE_LOWER);
            $comp_sku = trim((string)($row_lc['component sku'] ?? $row_lc['sku_code'] ?? $row_lc['sku'] ?? ''));
            $comp_name = trim((string)($row_lc['component name'] ?? $row_lc['name'] ?? ''));
            $qty = isset($row_lc['quantity']) ? (float)$row_lc['quantity'] : 0;
            $uom = isset($row_lc['uom']) ? trim((string)$row_lc['uom']) : null;
            $note = isset($row_lc['note']) ? trim((string)$row_lc['note']) : null;

            if ($qty <= 0 || ($comp_sku === '' && $comp_name === '')) {
                continue;
            }

            $comp_item = null;
            if ($comp_sku !== '') {
                $comp_item = $this->db
                    ->where('sku_code', $comp_sku)
                    ->limit(1)
                    ->get("{$p}items")
                    ->row_array();
            }
            if (!$comp_item && $comp_name !== '') {
                $comp_item = $this->db
                    ->where('sku_name', $comp_name)
                    ->limit(1)
                    ->get("{$p}items")
                    ->row_array();
            }

            if (!$comp_item) {
                $errors[] = 'Mixed row ' . ($idx + 1) . ': component "' . ($comp_sku ?: $comp_name) . '" not found.';
                continue;
            }

            $this->db->insert("{$p}pos_mixed_ingredient_components", [
                'mixed_ingredient_id' => $mixed_id,
                'component_type' => 'mixed_ingredient',
                'component_item_id' => (int)$comp_item['id'],
                'quantity' => $qty,
                'uom' => $uom,
                'sort_order' => $sort_order++,
                'note' => $note,
            ]);
            $component_inserted++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'mixed_id' => $mixed_id,
            'item_id' => $item_id,
            'components_inserted' => $component_inserted,
            'errors' => $errors,
        ];
    }

    public function get_items_for_costing($filters = [])
    {
        $prefix = db_prefix();
        $podTable = $prefix . 'pur_order_detail';
        $poTable = $prefix . 'pur_orders';

        $latestPodJoin = 'pod.id = (SELECT MAX(pod2.id) FROM `' . $podTable . '` pod2 WHERE pod2.item_code = items.id)';

        $this->db->select('items.id, items.sku_code, items.sku_name, items.item_type, items.group_id, items.sub_group, items.rate AS selling_price, items.purchase_price, items.batch_size, items.units_per_batch, items.batch_uom, items.unit_uom, items.cached_cost_per_unit, items.last_cost_update, items.active, items.fd_price, items.parent_id, items.unit_id, items.can_be_purchased, items.can_be_inventory, g.name AS category_name, sg.sub_group_name AS sub_category_name, wu.unit_name AS item_unit_name, pod.id AS last_purchase_detail_id, pod.unit_price AS last_purchase_price, pod.pur_order AS purchase_order_id, po.pur_order_number, po.pur_order_name');
        $this->db->from($prefix . 'items items');
        $this->db->join($prefix . 'items_groups g', 'g.id = items.group_id', 'left');
        $this->db->join($prefix . 'wh_sub_group sg', 'sg.id = items.sub_group', 'left');
        $this->db->join($prefix . 'ware_unit_type wu', 'wu.unit_type_id = items.unit_id', 'left');
        $this->db->join($podTable . ' pod', $latestPodJoin, 'left', false);
        $this->db->join($poTable . ' po', 'po.id = pod.pur_order', 'left');
        $this->db->where('(items.parent_id IS NULL OR items.parent_id = 0)', null, false);
        $this->db->where('items.active', 1);
        // Every caller of this function is an ingredient/packaging costing tab or
        // picker (Individual Ingredients, Packaging, Mixed Ingredient dropdown, Yield
        // Breakdown dropdown) — POS-sellable products belong in the separate Product
        // Cost Profit tab (get_product_cost_profit_summary()), not here.
        $this->db->where('(items.can_be_sold IS NULL OR items.can_be_sold != "can_be_sold")', null, false);

        if (!empty($filters['purchase_inventory_only'])) {
            $this->db->where('items.can_be_purchased', 'can_be_purchased');
            $this->db->where('items.can_be_inventory', 'can_be_inventory');
        }

        if (!empty($filters['exclude_packaging'])) {
            $this->db->group_start();
            $this->db->where('items.item_type !=', 'packaging');
            $this->db->or_where('items.item_type IS NULL', null, false);
            $this->db->group_end();
            $this->db->group_start();
            $this->db->where('g.name !=', 'Packaging');
            $this->db->or_where('g.name IS NULL', null, false);
            $this->db->group_end();
        }

        if (!empty($filters['packaging_only'])) {
            $this->db->group_start();
            $this->db->where('items.item_type', 'packaging');
            $this->db->or_where('g.name', 'Packaging');
            $this->db->group_end();
        }

        if (!empty($filters['category_id'])) {
            $cat_id = (int)$filters['category_id'];
            $this->db->group_start();
            $this->db->where('items.group_id', $cat_id);
            $this->db->or_where('items.sub_group', $cat_id);
            $this->db->group_end();
        }

        if (!empty($filters['search'])) {
            $this->db->group_start();
            $this->db->like('items.sku_name', $filters['search']);
            $this->db->or_like('items.sku_code', $filters['search']);
            $this->db->group_end();
        }

        if (!empty($filters['item_type'])) {
            if (is_array($filters['item_type'])) {
                $this->db->where_in('items.item_type', $filters['item_type']);
            } else {
                $this->db->where('items.item_type', $filters['item_type']);
            }
        }

        $this->db->order_by('category_name', 'ASC');
        $this->db->order_by('items.sku_name', 'ASC');

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $cached = $row['cached_cost_per_unit'] !== null ? round((float)$row['cached_cost_per_unit'], 4) : null;
            $units_per_batch = (float)($row['units_per_batch'] ?? 0);
            $last_purchase_price = (float)($row['last_purchase_price'] ?? 0);
            $fallback_purchase = $last_purchase_price > 0 ? $last_purchase_price : (float)($row['purchase_price'] ?? 0);

            // A yield-breakdown output (e.g. "Coconut Juice" derived from "Coconut
            // Fruit") is never purchased on its own, so its cost comes from its
            // source's cost instead of the purchase-price math below.
            $yield_source = $this->get_yield_source_for_item((int)$row['id']);
            if ($yield_source && (float)($yield_source['quantity'] ?? 0) > 0) {
                $live_cost = $this->calc_yield_output_unit_cost($yield_source);
            } else {
                // Always derive from the latest purchase order first (this is what the
                // tab claims to show); only fall back to a stale cache when there is no
                // purchase price at all to compute from (e.g. never purchased yet).
                $live_cost = $units_per_batch > 0 ? round($fallback_purchase / $units_per_batch, 4) : round($fallback_purchase, 4);
                if ($live_cost <= 0 && $cached !== null && $cached > 0) {
                    $live_cost = $cached;
                }
            }
            $row['cost_per_unit_fallback'] = $live_cost;

            $item_type = (string)($row['item_type'] ?? '');
            if (in_array($item_type, ['raw_ingredient', 'packaging'], true)
                && ($cached === null || abs($cached - $live_cost) > 0.00005)) {
                $this->db->where('id', (int)$row['id'])->update($prefix . 'items', ['cached_cost_per_unit' => $live_cost]);
                $this->propagate_cost_change((int)$row['id']);
            }

            $row['purchase_price_display'] = $fallback_purchase;
            $row['profit_per_unit'] = (float)($row['selling_price'] ?? 0) - (float)$row['cost_per_unit_fallback'];
            $row['margin_pct'] = (float)($row['selling_price'] ?? 0) > 0
                ? ($row['profit_per_unit'] / (float)$row['selling_price']) * 100
                : 0.0;
            $row['purchase_order_label'] = trim((string)($row['pur_order_number'] ?? ''));
            if ($row['purchase_order_label'] === '' && !empty($row['purchase_order_name'])) {
                $row['purchase_order_label'] = (string)$row['purchase_order_name'];
            } elseif ($row['purchase_order_label'] !== '' && !empty($row['purchase_order_name'])) {
                $row['purchase_order_label'] .= ' - ' . $row['purchase_order_name'];
            }
            $row['purchase_order_url'] = !empty($row['purchase_order_id'])
                ? admin_url('purchase/purchase_order/' . (int)$row['purchase_order_id'])
                : '';
        }
        unset($row);

        return $rows;
    }

    public function get_product_cost_profit_summary($filters = [])
    {
        $this->db->select('i.id, i.sku_code, i.sku_name, i.rate AS selling_price, i.item_type, i.cached_cost_per_unit, i.purchase_price, i.units_per_batch, i.parent_id, i.active, g.name AS category_name, sg.sub_group_name AS sub_category_name');
        $this->db->from(db_prefix() . 'items i');
        $this->db->join(db_prefix() . 'items_groups g', 'g.id = i.group_id', 'left');
        $this->db->join(db_prefix() . 'wh_sub_group sg', 'sg.id = i.sub_group', 'left');
        $this->db->where('(i.parent_id IS NULL OR i.parent_id = 0)', null, false);
        $this->db->where('i.active', 1);
        $this->db->where('i.can_be_sold', 'can_be_sold');

        if (!empty($filters['category_id'])) {
            $cat_id = (int)$filters['category_id'];
            $this->db->group_start();
            $this->db->where('i.group_id', $cat_id);
            $this->db->or_where('i.sub_group', $cat_id);
            $this->db->group_end();
        }

        if (!empty($filters['search'])) {
            $this->db->group_start();
            $this->db->like('i.sku_name', $filters['search']);
            $this->db->or_like('i.sku_code', $filters['search']);
            $this->db->group_end();
        }

        $this->db->order_by('i.sku_name', 'ASC');
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $bomRows = $this->db
                ->where('product_item_id', (int)$row['id'])
                ->where('variant_id IS NULL', null, false)
                ->get(db_prefix() . 'pos_product_bom')
                ->result_array();

            if (!empty($bomRows)) {
                $range = $this->resolve_bom_cost_range($bomRows);
                $costMin = $range['min'];
                $costMax = $range['max'];
                $isRange = $range['is_range'];
            } else {
                $cost = (float)($row['cached_cost_per_unit'] ?? 0);
                if ($cost <= 0) {
                    $calc = $this->get_item_unit_cost((int)$row['id'], false);
                    $cost = is_array($calc) ? (float)($calc['cost_per_unit'] ?? 0) : (float)$calc;
                }
                if ($cost <= 0) {
                    $units = (float)($row['units_per_batch'] ?? 0);
                    $purchase = (float)($row['purchase_price'] ?? 0);
                    $cost = $units > 0 ? ($purchase / $units) : $purchase;
                }
                $costMin = $cost;
                $costMax = $cost;
                $isRange = false;
            }

            $sell = (float)($row['selling_price'] ?? 0);
            $row['total_cost']      = round($costMax, 4);
            $row['total_cost_min']  = round($costMin, 4);
            $row['total_cost_max']  = round($costMax, 4);
            $row['is_range']        = $isRange;
            $row['profit_per_unit'] = round($sell - $costMax, 4);
            $row['profit_min']      = round($sell - $costMax, 4);
            $row['profit_max']      = round($sell - $costMin, 4);
            $row['margin_pct']      = $sell > 0 ? round((($sell - $costMax) / $sell) * 100, 2) : 0.0;
            $row['margin_min']      = $sell > 0 ? round((($sell - $costMax) / $sell) * 100, 2) : 0.0;
            $row['margin_max']      = $sell > 0 ? round((($sell - $costMin) / $sell) * 100, 2) : 0.0;
        }
        unset($row);

        return $rows;
    }

    public function get_product_cost_profit_detail($item_id)
    {
        $item_id = (int)$item_id;
        if ($item_id <= 0) {
            return [];
        }

        $item = $this->db
            ->select('i.id, i.sku_code, i.sku_name, i.rate AS selling_price, i.cached_cost_per_unit, i.purchase_price, i.units_per_batch, i.instructions')
            ->from(db_prefix() . 'items i')
            ->where('i.id', $item_id)
            ->get()
            ->row_array();

        if (!$item) {
            return [];
        }

        $rows = $this->db
            ->select('b.*, c.sku_code AS component_sku_code, c.sku_name AS component_name, c.item_type AS component_item_type')
            ->from(db_prefix() . 'pos_product_bom b')
            ->join(db_prefix() . 'items c', 'c.id = b.component_item_id', 'left')
            ->where('b.product_item_id', $item_id)
            ->where('b.variant_id IS NULL', null, false)
            ->order_by('b.section', 'ASC')
            ->order_by('b.sort_order', 'ASC')
            ->order_by('b.id', 'ASC')
            ->get()
            ->result_array();

        $conditionOptions = $this->get_product_condition_options($item_id);
        $conditionLabelByKey = [];
        foreach ($conditionOptions as $opt) {
            $conditionLabelByKey[$opt['type'] . ':' . $opt['id']] = $opt['label'];
        }

        $sections = [
            'mixed_ingredients' => [],
            'ingredients'       => [],
            'packaging'         => [],
        ];

        foreach ($rows as $row) {
            $sectionKey = 'ingredients';
            if (($row['section'] ?? '') === 'mixed_ingredient') {
                $sectionKey = 'mixed_ingredients';
            } elseif (($row['section'] ?? '') === 'packaging') {
                $sectionKey = 'packaging';
            }
            $componentCost = (float)$this->get_item_unit_cost((int)$row['component_item_id'], false);
            $qty = (float)($row['quantity_per_serving'] ?? 0);

            $requiresConditions = [];
            $requiresRaw = trim((string)($row['requires_conditions'] ?? ''));
            if ($requiresRaw !== '') {
                foreach (explode(',', $requiresRaw) as $pair) {
                    $pair = trim($pair);
                    if ($pair === '' || strpos($pair, ':') === false) {
                        continue;
                    }
                    list($type, $id) = explode(':', $pair, 2);
                    $type = trim($type);
                    $id = (int)trim($id);
                    if ($id > 0 && in_array($type, ['modifier', 'item_modifier_option'], true)) {
                        $requiresConditions[] = ['type' => $type, 'id' => $id];
                    }
                }
            } elseif ((int)($row['requires_modifier_id'] ?? 0) > 0) {
                // Legacy single-condition rows saved before multi-select support.
                $requiresConditions[] = [
                    'type' => (string)($row['requires_modifier_type'] ?? ''),
                    'id'   => (int)$row['requires_modifier_id'],
                ];
            }

            $requiresLabels = [];
            foreach ($requiresConditions as $rc) {
                $label = $conditionLabelByKey[$rc['type'] . ':' . $rc['id']] ?? '';
                if ($label !== '') {
                    $requiresLabels[] = $label;
                }
            }

            $sections[$sectionKey][] = [
                'id'                  => (int)$row['id'],
                'component_item_id'   => (int)$row['component_item_id'],
                'name'                => (string)($row['component_name'] ?? ''),
                'sku_code'            => (string)($row['component_sku_code'] ?? ''),
                'quantity'            => $qty,
                'cost_per_unit'       => round($componentCost, 6),
                'total_cost'          => round($componentCost * $qty, 6),
                'note'                => (string)($row['note'] ?? ''),
                'group_key'           => (string)($row['group_key'] ?? ''),
                'requires_conditions' => $requiresConditions,
                'requires_label'      => implode(', ', $requiresLabels),
            ];
        }

        $range = $this->resolve_bom_cost_range($rows);
        $currentMin = $range['min'];
        $currentMax = $range['max'];

        if (empty($rows)) {
            $fallback = (float)($item['cached_cost_per_unit'] ?? 0);
            if ($fallback <= 0) {
                $fallback = (float)$this->get_item_unit_cost($item_id, false);
            }
            $currentMin = $fallback;
            $currentMax = $fallback;
        }

        $sell = (float)($item['selling_price'] ?? 0);
        $profitMin = $sell - $currentMax;
        $profitMax = $sell - $currentMin;

        return [
            'item' => [
                'id'             => (int)$item['id'],
                'sku_code'       => (string)($item['sku_code'] ?? ''),
                'sku_name'       => (string)($item['sku_name'] ?? ''),
                'instructions'   => (string)($item['instructions'] ?? ''),
                'selling_price'  => $sell,
                'total_cost'     => round($currentMax, 6),
                'total_cost_min' => round($currentMin, 6),
                'total_cost_max' => round($currentMax, 6),
                'is_range'       => $range['is_range'],
                'profit'         => round($profitMax, 6),
                'profit_min'     => round($profitMin, 6),
                'profit_max'     => round($profitMax, 6),
                'margin_pct'     => $sell > 0 ? round(($profitMax / $sell) * 100, 2) : 0.0,
                'margin_min'     => $sell > 0 ? round(($profitMin / $sell) * 100, 2) : 0.0,
                'margin_max'     => $sell > 0 ? round(($profitMax / $sell) * 100, 2) : 0.0,
            ],
            'sections'          => $sections,
            'condition_options' => $conditionOptions,
        ];
    }

    public function save_product_cost_profit_detail($item_id, $sections = [], $instructions = null)
    {
        $item_id = (int)$item_id;
        if ($item_id <= 0) {
            throw new Exception('Invalid product item.');
        }

        if ($instructions !== null) {
            $this->db->where('id', $item_id)->update(db_prefix() . 'items', [
                'instructions' => trim((string)$instructions),
            ]);
        }

        $map = [
            'mixed_ingredients' => ['section' => 'mixed_ingredient', 'component_type' => 'mixed_ingredient'],
            'ingredients'       => ['section' => 'raw_ingredient', 'component_type' => 'raw_ingredient'],
            'packaging'         => ['section' => 'packaging', 'component_type' => 'packaging'],
        ];

        $this->db->where('product_item_id', $item_id)
            ->where('variant_id IS NULL', null, false)
            ->delete(db_prefix() . 'pos_product_bom');

        foreach ($map as $payloadKey => $meta) {
            $rows = isset($sections[$payloadKey]) && is_array($sections[$payloadKey]) ? $sections[$payloadKey] : [];
            $sort = 0;
            foreach ($rows as $row) {
                $componentItemId = (int)($row['component_item_id'] ?? 0);
                $quantity = (float)($row['quantity'] ?? 0);
                if ($componentItemId <= 0 || $quantity <= 0) {
                    continue;
                }
                $groupKey = trim((string)($row['group_key'] ?? ''));

                $requiresConditions = [];
                if (!empty($row['requires_conditions']) && is_array($row['requires_conditions'])) {
                    foreach ($row['requires_conditions'] as $cond) {
                        if (is_array($cond)) {
                            $type = trim((string)($cond['type'] ?? ''));
                            $id = (int)($cond['id'] ?? 0);
                        } else {
                            $parts = explode(':', (string)$cond, 2);
                            $type = trim($parts[0] ?? '');
                            $id = (int)trim($parts[1] ?? '0');
                        }
                        if ($id > 0 && in_array($type, ['modifier', 'item_modifier_option'], true)) {
                            $requiresConditions[] = $type . ':' . $id;
                        }
                    }
                    $requiresConditions = array_values(array_unique($requiresConditions));
                }
                $requiresConditionsStr = !empty($requiresConditions) ? implode(',', $requiresConditions) : null;

                $this->db->insert(db_prefix() . 'pos_product_bom', [
                    'product_item_id'       => $item_id,
                    'variant_id'            => null,
                    'section'               => $meta['section'],
                    'component_type'        => $meta['component_type'],
                    'component_item_id'     => $componentItemId,
                    'quantity_per_serving'  => $quantity,
                    'uom'                   => null,
                    'sort_order'            => $sort++,
                    'note'                  => trim((string)($row['note'] ?? '')),
                    'group_key'             => $groupKey !== '' ? $groupKey : null,
                    'requires_modifier_type'=> null,
                    'requires_modifier_id'  => null,
                    'requires_conditions'   => $requiresConditionsStr,
                ]);
            }
        }

        $visited = [];
        $this->calc_product_cost($item_id, null, $visited);
        $this->propagate_cost_change($item_id);

        return $this->get_product_cost_profit_detail($item_id);
    }

    public function get_mixed_cost_summary($filters = [])
    {
        $this->db->select('mi.id, mi.item_id, mi.total_batches_yield, mi.yield_uom, mi.prep_minutes, mi.instructions, i.sku_code, i.sku_name, i.cached_cost_per_unit');
        $this->db->from(db_prefix() . 'pos_mixed_ingredients mi');
        $this->db->join(db_prefix() . 'items i', 'i.id = mi.item_id', 'left');

        if (!empty($filters['search'])) {
            $this->db->group_start();
            $this->db->like('i.sku_name', $filters['search']);
            $this->db->or_like('i.sku_code', $filters['search']);
            $this->db->group_end();
        }

        $this->db->order_by('i.sku_name', 'ASC');
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['total_cost'] = round(((float)($row['cached_cost_per_unit'] ?? 0)) * (float)($row['total_batches_yield'] ?? 0), 6);
            $row['cost_per_unit'] = round((float)($row['cached_cost_per_unit'] ?? 0), 6);
            $row['components_count'] = (int)$this->db
                ->where('mixed_ingredient_id', (int)$row['id'])
                ->count_all_results(db_prefix() . 'pos_mixed_ingredient_components');
        }
        unset($row);

        return $rows;
    }

    public function get_mixed_cost_detail($mixed_id)
    {
        $mixed_id = (int)$mixed_id;
        if ($mixed_id <= 0) {
            return [];
        }

        $mixed = $this->db
            ->select('mi.id, mi.item_id, mi.total_batches_yield, mi.yield_uom, mi.prep_minutes, mi.instructions, i.sku_name, i.sku_code, i.cached_cost_per_unit')
            ->from(db_prefix() . 'pos_mixed_ingredients mi')
            ->join(db_prefix() . 'items i', 'i.id = mi.item_id', 'left')
            ->where('mi.id', $mixed_id)
            ->get()
            ->row_array();

        if (!$mixed) {
            return [];
        }

        $components = $this->db
            ->select('c.*, i.sku_code, i.sku_name')
            ->from(db_prefix() . 'pos_mixed_ingredient_components c')
            ->join(db_prefix() . 'items i', 'i.id = c.component_item_id', 'left')
            ->where('c.mixed_ingredient_id', $mixed_id)
            ->order_by('c.sort_order', 'ASC')
            ->order_by('c.id', 'ASC')
            ->get()
            ->result_array();

        $rows = [];
        foreach ($components as $component) {
            $componentCost = (float)$this->get_item_unit_cost((int)$component['component_item_id'], false);
            $qty = (float)($component['quantity'] ?? 0);
            $rows[] = [
                'id'               => (int)$component['id'],
                'component_item_id'=> (int)$component['component_item_id'],
                'name'             => (string)($component['sku_name'] ?? ''),
                'sku_code'         => (string)($component['sku_code'] ?? ''),
                'component_type'   => (string)($component['component_type'] ?? 'raw_ingredient'),
                'quantity'         => $qty,
                'cost_per_unit'    => round($componentCost, 6),
                'total_cost'       => round($componentCost * $qty, 6),
                'note'             => (string)($component['note'] ?? ''),
            ];
        }

        return [
            'mixed' => [
                'id'             => (int)$mixed['id'],
                'item_id'        => (int)$mixed['item_id'],
                'sku_code'       => (string)($mixed['sku_code'] ?? ''),
                'sku_name'       => (string)($mixed['sku_name'] ?? ''),
                'total_units'    => (float)($mixed['total_batches_yield'] ?? 1),
                'yield_uom'      => (string)($mixed['yield_uom'] ?? ''),
                'prep_minutes'   => (int)($mixed['prep_minutes'] ?? 0),
                'instructions'   => (string)($mixed['instructions'] ?? ''),
                'cost_per_unit'  => round((float)($mixed['cached_cost_per_unit'] ?? 0), 6),
                'total_cost'     => round((float)($mixed['cached_cost_per_unit'] ?? 0) * (float)($mixed['total_batches_yield'] ?? 0), 6),
            ],
            'components' => $rows,
        ];
    }

    /**
     * Fixed-ratio Yield Breakdown for a source item (e.g. 1 "Coconut Fruit" -> 110ml
     * "Coconut Juice" + 50g "Coconut Meat"). Each output stays a normal, independently
     * selectable item; only its cost/unit is derived from the source instead of its
     * own purchase price.
     */
    public function get_item_yield_summary($filters = [])
    {
        $p = db_prefix();

        $this->db->select('y.source_item_id, i.sku_code, i.sku_name, i.unit_uom, COUNT(y.id) AS outputs_count');
        $this->db->from("{$p}pos_item_yields y");
        $this->db->join("{$p}items i", 'i.id = y.source_item_id', 'left');

        if (!empty($filters['search'])) {
            $this->db->group_start();
            $this->db->like('i.sku_name', $filters['search']);
            $this->db->or_like('i.sku_code', $filters['search']);
            $this->db->group_end();
        }

        $this->db->group_by('y.source_item_id');
        $this->db->order_by('i.sku_name', 'ASC');
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['source_cost_per_unit'] = $this->get_item_unit_cost((int)$row['source_item_id']);
        }
        unset($row);

        return $rows;
    }

    public function get_item_yields($source_item_id)
    {
        $p = db_prefix();
        $source_item_id = (int)$source_item_id;

        $source = $this->db->select('id, sku_code, sku_name, unit_uom, has_yield_breakdown')
            ->where('id', $source_item_id)
            ->get("{$p}items")
            ->row_array();

        if (!$source) {
            return ['enabled' => false, 'source_cost_per_unit' => 0.0, 'source_unit_uom' => '', 'rows' => []];
        }

        $rows = $this->db->select('y.id, y.output_item_id, y.quantity, y.reference_price, i.sku_code, i.sku_name, i.unit_uom')
            ->from("{$p}pos_item_yields y")
            ->join("{$p}items i", 'i.id = y.output_item_id', 'left')
            ->where('y.source_item_id', $source_item_id)
            ->order_by('y.sort_order', 'ASC')
            ->get()
            ->result_array();

        $source_cost = $this->get_item_unit_cost($source_item_id);

        // Split by relative market value (quantity * reference_price) once any row
        // has a reference price set; otherwise each row absorbs the full source cost
        // (see calc_yield_output_unit_cost() — same rule, inlined here since $rows
        // already holds every sibling, avoiding a re-query per row).
        $total_market_value = 0.0;
        foreach ($rows as $row) {
            $total_market_value += (float)$row['quantity'] * (float)($row['reference_price'] ?? 0);
        }

        foreach ($rows as &$row) {
            $qty = (float)$row['quantity'];
            if ($qty <= 0) {
                $row['derived_cost_per_unit'] = 0.0;
            } elseif ($total_market_value > 0) {
                $market_value = $qty * (float)($row['reference_price'] ?? 0);
                $row['derived_cost_per_unit'] = round(($source_cost * ($market_value / $total_market_value)) / $qty, 4);
            } else {
                $row['derived_cost_per_unit'] = round($source_cost / $qty, 4);
            }
        }
        unset($row);

        return [
            'enabled'              => !empty($source['has_yield_breakdown']),
            'source_cost_per_unit' => $source_cost,
            'source_unit_uom'      => (string)($source['unit_uom'] ?? ''),
            'rows'                 => $rows,
        ];
    }

    public function save_item_yields($source_item_id, $enabled, array $rows)
    {
        $p = db_prefix();
        $source_item_id = (int)$source_item_id;
        if (!$source_item_id) {
            throw new Exception('Item is required.');
        }

        $old_output_ids = array_column(
            $this->db->select('output_item_id')->where('source_item_id', $source_item_id)->get("{$p}pos_item_yields")->result_array(),
            'output_item_id'
        );

        $this->db->where('id', $source_item_id)->update("{$p}items", [
            'has_yield_breakdown' => $enabled ? 1 : 0,
        ]);

        $this->db->where('source_item_id', $source_item_id)->delete("{$p}pos_item_yields");

        $new_output_ids = [];
        if ($enabled) {
            $sort = 0;
            foreach ($rows as $row) {
                $outputId = (int)($row['output_item_id'] ?? 0);
                $quantity = (float)($row['quantity'] ?? 0);
                $referencePrice = (float)($row['reference_price'] ?? 0);
                if ($outputId <= 0 || $outputId === $source_item_id || $quantity <= 0 || in_array($outputId, $new_output_ids, true)) {
                    continue;
                }
                // output_item_id is UNIQUE (an item can only be derived from one
                // source at a time) — clear any prior link before re-assigning it.
                $this->db->where('output_item_id', $outputId)->delete("{$p}pos_item_yields");
                $this->db->insert("{$p}pos_item_yields", [
                    'source_item_id'  => $source_item_id,
                    'output_item_id'  => $outputId,
                    'quantity'        => $quantity,
                    'reference_price' => $referencePrice,
                    'sort_order'      => $sort++,
                ]);
                $new_output_ids[] = $outputId;
            }
        }

        foreach (array_unique(array_merge($old_output_ids, $new_output_ids)) as $outputId) {
            $calcVisited = [];
            $this->get_item_unit_cost((int)$outputId, true, $calcVisited);
            $this->propagate_cost_change((int)$outputId);
        }

        return $this->get_item_yields($source_item_id);
    }

    public function resolve_mixed_ingredient_item($item_id, $item_name)
    {
        $p = db_prefix();
        $item_id = (int)$item_id;
        $item_name = trim((string)$item_name);

        if ($item_name === '') {
            throw new Exception('Item name is required.');
        }

        if ($item_id > 0) {
            $this->db->where('id', $item_id)->update("{$p}items", [
                'sku_name'  => $item_name,
                'item_type' => 'mixed_ingredient',
            ]);
            return $item_id;
        }

        $existing = $this->db
            ->where('sku_name', $item_name)
            ->where('item_type', 'mixed_ingredient')
            ->limit(1)
            ->get("{$p}items")
            ->row_array();

        if ($existing) {
            return (int)$existing['id'];
        }

        $this->db->insert("{$p}items", [
            'sku_code'             => 'MIX' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'sku_name'             => $item_name,
            'item_type'            => 'mixed_ingredient',
            'can_be_purchased'     => 0,
            'can_be_sold'          => 0,
            'can_be_inventory'     => 1,
            'can_be_manufacturing' => 'can_be_manufacturing',
            'active'               => 1,
            'commodity_type'       => 5,
            'parent_id'            => null,
            'batch_size'           => 1,
            'units_per_batch'      => 1,
        ]);

        return (int)$this->db->insert_id();
    }

    public function save_mixed_cost_detail($mixed_id, $payload = [])
    {
        $mixed_id = (int)$mixed_id;
        $item_id = $this->resolve_mixed_ingredient_item(
            (int)($payload['item_id'] ?? 0),
            (string)($payload['item_name'] ?? '')
        );

        $header = [
            'item_id'             => $item_id,
            'total_batches_yield' => max(1, (float)($payload['total_units'] ?? 1)),
            'yield_uom'           => trim((string)($payload['yield_uom'] ?? '')),
            'prep_minutes'        => (int)($payload['prep_minutes'] ?? 0),
            'instructions'        => trim((string)($payload['instructions'] ?? '')),
        ];

        if ($mixed_id > 0) {
            $this->db->where('id', $mixed_id)->update(db_prefix() . 'pos_mixed_ingredients', $header);
        } else {
            $this->db->insert(db_prefix() . 'pos_mixed_ingredients', $header);
            $mixed_id = (int)$this->db->insert_id();
        }

        $this->db->where('mixed_ingredient_id', $mixed_id)->delete(db_prefix() . 'pos_mixed_ingredient_components');

        $components = isset($payload['components']) && is_array($payload['components']) ? $payload['components'] : [];
        $sort = 0;
        foreach ($components as $component) {
            $componentItemId = (int)($component['component_item_id'] ?? 0);
            $quantity = (float)($component['quantity'] ?? 0);
            if ($componentItemId <= 0 || $quantity <= 0) {
                continue;
            }
            $componentItem = $this->db->select('item_type')->from(db_prefix() . 'items')->where('id', $componentItemId)->get()->row_array();
            $componentType = (string)($componentItem['item_type'] ?? 'raw_ingredient');
            if (!in_array($componentType, ['raw_ingredient', 'packaging', 'mixed_ingredient'], true)) {
                $componentType = 'raw_ingredient';
            }
            $this->db->insert(db_prefix() . 'pos_mixed_ingredient_components', [
                'mixed_ingredient_id' => $mixed_id,
                'component_type'      => $componentType,
                'component_item_id'   => $componentItemId,
                'quantity'            => $quantity,
                'uom'                 => null,
                'sort_order'          => $sort++,
                'note'                => trim((string)($component['note'] ?? '')),
            ]);
        }

        $visited = [];
        $this->calc_mixed_ingredient_cost($mixed_id, $visited);
        $this->propagate_cost_change($item_id);

        return $this->get_mixed_cost_detail($mixed_id);
    }

    public function get_snapshot_values($snapshot_id)
    {
        $snapshot_id = (int)$snapshot_id;
        if (!$snapshot_id) {
            return [];
        }

        $this->db->select('v.*, i.sku_name, i.sku_code, i.item_type, vg.name as variant_group_name, v2.name as variant_name');
        $this->db->from(db_prefix() . 'pos_cost_snapshot_values v');
        $this->db->join(db_prefix() . 'items i', 'i.id = v.item_id', 'left');
        $this->db->join(db_prefix() . 'pos_product_variants v2', 'v2.id = v.variant_id', 'left');
        $this->db->join(db_prefix() . 'pos_product_variant_groups vg', 'vg.id = v2.variant_group_id', 'left');
        $this->db->where('v.snapshot_id', $snapshot_id);
        $this->db->order_by('i.sku_name', 'ASC');
        $this->db->order_by('v.variant_id', 'ASC');

        $result = $this->db->get()->result_array();

        return is_array($result) ? $result : [];
    }

    public function get_snapshot_comparison($snapshot_a_id, $snapshot_b_id)
    {
        $a_rows = $this->get_snapshot_values($snapshot_a_id);
        $b_rows = $this->get_snapshot_values($snapshot_b_id);

        $a_map = [];
        foreach ($a_rows as $r) {
            $key = (int)($r['item_id'] ?? 0) . ':' . (int)($r['variant_id'] ?? 0);
            $a_map[$key] = $r;
        }

        $b_map = [];
        foreach ($b_rows as $r) {
            $key = (int)($r['item_id'] ?? 0) . ':' . (int)($r['variant_id'] ?? 0);
            $b_map[$key] = $r;
        }

        $all_keys = array_unique(array_merge(array_keys($a_map), array_keys($b_map)));
        sort($all_keys);

        $deltas = [];
        $changed_count = 0;

        foreach ($all_keys as $key) {
            $a = $a_map[$key] ?? null;
            $b = $b_map[$key] ?? null;

            $item_id = (int)($a['item_id'] ?? $b['item_id'] ?? 0);
            $variant_id = (int)($a['variant_id'] ?? $b['variant_id'] ?? 0);

            $item_name = $a['sku_name'] ?? $b['sku_name'] ?? null;
            $sku_code = $a['sku_code'] ?? $b['sku_code'] ?? null;

            $cost_type_a = $a ? ($a['cost_type'] ?? 'finished_product') : ($b ? ($b['cost_type'] ?? 'finished_product') : 'finished_product');

            $cost_a = (float)($a['cost_per_unit'] ?? 0);
            $cost_b = (float)($b['cost_per_unit'] ?? 0);
            $cost_delta = $cost_b - $cost_a;

            $price_a = (float)($a['selling_price'] ?? 0);
            $price_b = (float)($b['selling_price'] ?? 0);
            $price_delta = $price_b - $price_a;

            $profit_a = (float)($a['profit_per_unit'] ?? 0);
            $profit_b = (float)($b['profit_per_unit'] ?? 0);
            $profit_delta = $profit_b - $profit_a;

            $margin_pct_a = (float)($a['margin_pct'] ?? 0);
            $margin_pct_b = (float)($b['margin_pct'] ?? 0);
            $margin_pp_delta = $margin_pct_b - $margin_pct_a;

            if ($cost_delta != 0 || $price_delta != 0 || $profit_delta != 0 || $margin_pp_delta != 0) {
                $changed_count++;
            }

            $deltas[] = [
                'key' => $key,
                'item_id' => $item_id,
                'variant_id' => $variant_id,
                'item_name' => $item_name,
                'sku_code' => $sku_code,
                'cost_type_a' => $cost_type_a,
                'cost_a' => $cost_a,
                'cost_b' => $cost_b,
                'cost_delta' => $cost_delta,
                'price_a' => $price_a,
                'price_b' => $price_b,
                'price_delta' => $price_delta,
                'profit_a' => $profit_a,
                'profit_b' => $profit_b,
                'profit_delta' => $profit_delta,
                'margin_pct_a' => $margin_pct_a,
                'margin_pct_b' => $margin_pct_b,
                'margin_pp_delta' => $margin_pp_delta,
            ];
        }

        return [
            'items' => $deltas,
            'meta' => [
                'a_id' => (int)$snapshot_a_id,
                'b_id' => (int)$snapshot_b_id,
                'count_a' => count($a_map),
                'count_b' => count($b_map),
                'changed_count' => $changed_count,
            ],
        ];
    }
}
