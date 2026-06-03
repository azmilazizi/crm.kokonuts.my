<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pos_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
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
        $token      = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expire_days} days"));

        $this->db->insert(db_prefix() . 'pos_sessions', [
            'staff_id'   => $staff_id,
            'token'      => $token,
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
        if (!$employee) return false;
        if ($warehouse_id) {
            $warehouse_ids = json_decode($employee['warehouse_ids'] ?? '[]', true);
            if (!in_array((int)$warehouse_id, $warehouse_ids)) return false;
        }
        unset($employee['pin']);
        return $employee;
    }

    public function get_modifiers()
    {
        $groups = $this->db->order_by('name', 'ASC')->get(db_prefix() . 'modifier_groups')->result_array();
        foreach ($groups as &$group) {
            $group['modifiers'] = $this->db
                ->where('modifier_group_id', $group['id'])
                ->where('active', 1)
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
        }
        return $groups;
    }

    public function get_modifier_groups()
    {
        $groups = $this->db->order_by('name', 'ASC')->get(db_prefix() . 'modifier_groups')->result_array();
        foreach ($groups as &$group) {
            $group['modifiers'] = $this->db
                ->where('modifier_group_id', $group['id'])
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
        }
        return $groups;
    }

    public function get_modifier_group($id)
    {
        $group = $this->db->get_where(db_prefix() . 'modifier_groups', ['id' => $id])->row_array();
        if (!$group) return null;
        $group['modifiers'] = $this->db
            ->where('modifier_group_id', $id)
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'modifiers')->result_array();
        return $group;
    }

    public function save_modifier_group($data, $id = null)
    {
        $payload = [
            'name'            => $data['name'],
            'selection_type'  => $data['selection_type'] ?? 'single',
            'min_selections'  => (int)($data['min_selections'] ?? 0),
            'max_selections'  => (int)($data['max_selections'] ?? 1),
            'active'          => isset($data['active']) ? (int)$data['active'] : 1,
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

    public function save_modifier($data, $id = null)
    {
        $payload = [
            'modifier_group_id' => (int)$data['modifier_group_id'],
            'name'              => $data['name'],
            'price_adjustment'  => (float)($data['price_adjustment'] ?? 0),
            'sort_order'        => (int)($data['sort_order'] ?? 0),
            'active'            => isset($data['active']) ? (int)$data['active'] : 1,
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

    public function get_item_modifier_groups($item_id)
    {
        return $this->db
            ->select('img.*, mg.name, mg.selection_type, mg.min_selections, mg.max_selections, mg.active')
            ->from(db_prefix() . 'item_modifier_groups img')
            ->join(db_prefix() . 'modifier_groups mg', 'mg.id = img.modifier_group_id')
            ->where('img.pos_item_id', (string)$item_id)
            ->order_by('img.sort_order', 'ASC')
            ->get()->result_array();
    }

    public function assign_modifier_group($item_id, $modifier_group_id, $sort_order = 0)
    {
        $exists = $this->db->get_where(db_prefix() . 'item_modifier_groups', [
            'pos_item_id'       => (string)$item_id,
            'modifier_group_id' => (int)$modifier_group_id,
        ])->row();

        if ($exists) {
            $this->db->where('pos_item_id', (string)$item_id)
                ->where('modifier_group_id', (int)$modifier_group_id)
                ->update(db_prefix() . 'item_modifier_groups', ['sort_order' => (int)$sort_order]);
        } else {
            $this->db->insert(db_prefix() . 'item_modifier_groups', [
                'pos_item_id'       => (string)$item_id,
                'modifier_group_id' => (int)$modifier_group_id,
                'sort_order'        => (int)$sort_order,
            ]);
        }
        return true;
    }

    public function unassign_modifier_group($item_id, $modifier_group_id)
    {
        $this->db->where('pos_item_id', (string)$item_id)
            ->where('modifier_group_id', (int)$modifier_group_id)
            ->delete(db_prefix() . 'item_modifier_groups');
        return $this->db->affected_rows() > 0;
    }

    public function get_payment_types($warehouse_id = null)
    {
        $types = $this->db->where('deleted_at IS NULL')->get(db_prefix() . 'pos_payment_types')->result_array();
        if ($warehouse_id) {
            $types = array_filter($types, function ($t) use ($warehouse_id) {
                $ids = json_decode($t['warehouse_ids'] ?? '[]', true);
                return empty($ids) || in_array((int)$warehouse_id, $ids);
            });
        }
        return array_values($types);
    }

    // -------------------------------------------------------------------------
    // Items
    // -------------------------------------------------------------------------

    public function get_items($filters = [])
    {
        $q                  = $filters['q'] ?? null;
        $group_id           = $filters['group_id'] ?? null;
        $warehouse_id       = $filters['warehouse_id'] ?? null;
        $can_be_sold        = $filters['can_be_sold'] ?? null;
        $can_be_manufacturing = $filters['can_be_manufacturing'] ?? null;
        $page               = max(1, (int)($filters['page'] ?? 1));
        $limit              = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset             = ($page - 1) * $limit;

        $this->db->select('i.*, COALESCE(inv.inventory_number, 0) as stock_quantity')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'inventory_manage inv', 'inv.commodity_id = i.id' . ($warehouse_id ? ' AND inv.warehouse_id = ' . (int)$warehouse_id : ''), 'left')
            ->where('i.active', 1)
            ->where('i.parent_id IS NULL');

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

        $items = $this->db->limit($limit, $offset)->get()->result_array();

        foreach ($items as &$item) {
            $item['variants'] = $this->_get_item_variants($item['id'], $warehouse_id);
            $item['tax_info'] = $this->_get_item_tax_info($item);
        }
        return $items;
    }

    public function get_item($id)
    {
        $item = $this->db->select('i.*, COALESCE(inv.inventory_number, 0) as stock_quantity')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'inventory_manage inv', 'inv.commodity_id = i.id', 'left')
            ->where('i.id', $id)
            ->where('i.active', 1)
            ->get()->row_array();

        if (!$item) return null;
        $item['variants'] = $this->_get_item_variants($id, null);
        $item['tax_info'] = $this->_get_item_tax_info($item);
        return $item;
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

        if (!$item) return null;
        $item['variants'] = $this->_get_item_variants($item['id'], null);
        $item['tax_info'] = $this->_get_item_tax_info($item);
        return $item;
    }

    private function _get_item_variants($parent_id, $warehouse_id)
    {
        $this->db->select('i.*, COALESCE(inv.inventory_number, 0) as stock_quantity')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'inventory_manage inv', 'inv.commodity_id = i.id' . ($warehouse_id ? ' AND inv.warehouse_id = ' . (int)$warehouse_id : ''), 'left')
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
                if ($tax) $taxes[] = $tax;
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

    // -------------------------------------------------------------------------
    // Taxes / Payment modes
    // -------------------------------------------------------------------------

    public function get_taxes()
    {
        return $this->db->get(db_prefix() . 'taxes')->result_array();
    }

    public function get_payment_modes()
    {
        return $this->db->where('active', 1)->get(db_prefix() . 'payment_modes')->result_array();
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
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'] ?? 0,
            'image'       => $data['image'] ?? null,
            'active'      => isset($data['active']) ? (int)$data['active'] : 1,
            'warehouse_ids'   => isset($data['warehouse_ids']) ? json_encode($data['warehouse_ids']) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $bundle_id = $this->db->insert_id();
        if (!$bundle_id) return false;
        $this->_save_bundle_items($bundle_id, $data['items'] ?? []);
        return $bundle_id;
    }

    public function update_bundle($id, $data)
    {
        $update = [];
        foreach (['name', 'description', 'price', 'image', 'active'] as $f) {
            if (isset($data[$f])) $update[$f] = $data[$f];
        }
        if (isset($data['warehouse_ids'])) $update['warehouse_ids'] = json_encode($data['warehouse_ids']);
        if (!empty($update)) $this->db->where('id', $id)->update(db_prefix() . 'pos_bundles', $update);
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
                'bundle_id'    => $bundle_id,
                'item_id'      => $item['item_id'],
                'quantity'     => $item['quantity'] ?? 1,
                'modifier_ids' => isset($item['modifier_ids']) ? json_encode($item['modifier_ids']) : null,
            ]);
        }
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
                return empty($ids) || in_array((int)$warehouse_id, $ids);
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
            if ($subtotal < (float)$promo['min_order_value']) continue;

            $promo_item_ids     = json_decode($promo['item_ids'] ?? '[]', true);
            $promo_category_ids = json_decode($promo['category_ids'] ?? '[]', true);

            if ($promo['type'] === 'percentage' || $promo['type'] === 'fixed') {
                $discount = 0;
                foreach ($items as $line) {
                    $eligible = empty($promo_item_ids) && empty($promo_category_ids);
                    if (!$eligible && in_array((int)$line['item_id'], $promo_item_ids)) $eligible = true;
                    if (!$eligible && !empty($promo_category_ids)) {
                        $item_row = $this->db->get_where(db_prefix() . 'items', ['id' => $line['item_id']])->row_array();
                        if ($item_row && in_array((int)$item_row['group_id'], $promo_category_ids)) $eligible = true;
                    }
                    if (!$eligible) continue;
                    $line_total = (float)$line['price'] * (float)$line['quantity'];
                    $line_disc  = $promo['type'] === 'percentage'
                        ? round($line_total * $promo['value'] / 100, 2)
                        : min((float)$promo['value'], $line_total);
                    $discount += $line_disc;
                    $line_discounts[] = ['item_id' => $line['item_id'], 'promotion_id' => $promo['id'], 'discount' => $line_disc];
                }
                if ($discount > 0) {
                    $total_discount += $discount;
                    $applied[] = ['promotion_id' => $promo['id'], 'name' => $promo['name'], 'type' => $promo['type'], 'discount' => $discount];
                }
            } elseif ($promo['type'] === 'bogo') {
                foreach ($items as $line) {
                    if (!empty($promo_item_ids) && !in_array((int)$line['item_id'], $promo_item_ids)) continue;
                    $qty = (int)$line['quantity'];
                    $free = floor($qty / 2);
                    if ($free > 0) {
                        $disc = round($free * (float)$line['price'], 2);
                        $total_discount += $disc;
                        $line_discounts[] = ['item_id' => $line['item_id'], 'promotion_id' => $promo['id'], 'discount' => $disc];
                        $applied[] = ['promotion_id' => $promo['id'], 'name' => $promo['name'], 'type' => 'bogo', 'discount' => $disc];
                    }
                }
            }
        }

        return [
            'applied_promotions' => $applied,
            'line_discounts'     => $line_discounts,
            'total_discount'     => round($total_discount, 2),
            'final_total'        => round($subtotal - $total_discount, 2),
        ];
    }

    // -------------------------------------------------------------------------
    // Shifts
    // -------------------------------------------------------------------------

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
            'warehouse_id'  => $data['warehouse_id'],
            'employee_id'   => $data['employee_id'] ?? null,
            'shift_code'    => $shift_code,
            'opening_float' => $data['opening_float'] ?? 0,
            'status'        => 'open',
            'opened_at'     => date('Y-m-d H:i:s'),
        ]);
        $id = $this->db->insert_id();
        return $id ? $this->get_shift($id) : false;
    }

    public function get_shift($id)
    {
        $shift = $this->db->get_where(db_prefix() . 'pos_shifts', ['id' => $id])->row_array();
        if (!$shift) return null;
        $shift['cash_movements'] = $this->db->where('shift_id', $id)->order_by('created_at', 'ASC')->get(db_prefix() . 'pos_shift_cash_movements')->result_array();
        return $shift;
    }

    public function add_cash_movement($shift_id, $data)
    {
        $this->db->insert(db_prefix() . 'pos_shift_cash_movements', [
            'shift_id'    => $shift_id,
            'type'        => $data['type'],
            'amount'      => $data['amount'],
            'reason'      => $data['reason'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    public function close_shift($shift_id, $data)
    {
        $shift = $this->get_shift($shift_id);
        if (!$shift || $shift['status'] !== 'open') return false;

        // Sum pay-ins and pay-outs from cash movements
        $pay_ins = 0;
        $pay_outs = 0;
        foreach ($shift['cash_movements'] as $m) {
            if ($m['type'] === 'pay_in')  $pay_ins  += (float)$m['amount'];
            if ($m['type'] === 'pay_out') $pay_outs += (float)$m['amount'];
        }

        // Cash sales and cash refunds for this shift
        $cash_sales = (float)$this->db->select_sum('rp.money_amount')
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'SALE')
            ->get()->row()->money_amount;

        $cash_refunds = (float)$this->db->select_sum('rp.money_amount')
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'REFUND')
            ->get()->row()->money_amount;

        $expected_cash = (float)$shift['opening_float'] + $pay_ins - $pay_outs + $cash_sales - $cash_refunds;
        $actual_cash   = (float)($data['actual_cash'] ?? 0);
        $difference    = $actual_cash - $expected_cash;

        // Aggregate totals from receipts in this shift
        $summary = $this->db->select('SUM(total_money) as total_sales, SUM(total_discount) as total_discounts, SUM(total_tax) as total_tax, COUNT(*) as transaction_count')
            ->where('shift_id', $shift_id)
            ->where('receipt_type', 'SALE')
            ->where('cancelled_at IS NULL')
            ->get(db_prefix() . 'pos_receipts')->row_array();

        $refund_total = (float)$this->db->select_sum('amount')->where('receipt_id IN (SELECT id FROM `' . db_prefix() . 'pos_receipts` WHERE shift_id = ' . (int)$shift_id . ')')->get(db_prefix() . 'pos_refunds')->row()->amount;

        $cash_rounded = (float)$this->db->select_sum('surcharge')
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
            'closing_float'         => $actual_cash,
            'expected_cash'         => round($expected_cash, 2),
            'actual_cash'           => $actual_cash,
            'difference'            => round($difference, 2),
            'total_sales'           => round((float)$summary['total_sales'], 2),
            'total_refunds'         => round($refund_total, 2),
            'total_discounts'       => round((float)$summary['total_discounts'], 2),
            'total_tax'             => round((float)$summary['total_tax'], 2),
            'cash_rounded'          => round($cash_rounded, 2),
            'transaction_count'     => (int)$summary['transaction_count'],
            'cancelled_count'       => (int)$cancelled['cnt'],
            'cancelled_amount'      => round((float)$cancelled['amount'], 2),
            'status'                => 'closed',
            'closed_at'             => date('Y-m-d H:i:s'),
            'notes'                 => $data['notes'] ?? null,
        ]);

        return $this->get_shift($shift_id);
    }

    public function get_shift_report($shift_id)
    {
        $shift = $this->get_shift($shift_id);
        if (!$shift) return null;

        // Totals by payment type, split by SALE vs REFUND
        $by_payment_raw = $this->db->select('rp.payment_type_id, rp.payment_name, rp.type as payment_type, r.receipt_type, SUM(rp.money_amount) as total, COUNT(*) as transactions')
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->group_by('rp.payment_type_id, r.receipt_type')
            ->get()->result_array();

        $by_payment = [];
        foreach ($by_payment_raw as $row) {
            $id = $row['payment_type_id'];
            if (!isset($by_payment[$id])) {
                $by_payment[$id] = [
                    'payment_type_id'   => $id,
                    'payment_name'      => $row['payment_name'],
                    'payment_type'      => $row['payment_type'],
                    'sales_total'       => 0,
                    'sales_count'       => 0,
                    'refunds_total'     => 0,
                    'refunds_count'     => 0,
                ];
            }
            if ($row['receipt_type'] === 'SALE') {
                $by_payment[$id]['sales_total'] = (float)$row['total'];
                $by_payment[$id]['sales_count'] = (int)$row['transactions'];
            } else {
                $by_payment[$id]['refunds_total'] = (float)$row['total'];
                $by_payment[$id]['refunds_count'] = (int)$row['transactions'];
            }
        }
        $by_payment = array_values($by_payment);

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

        return [
            'shift'              => $shift,
            'by_payment_type'    => $by_payment,
            'top_items'          => $top_items,
            'hourly_breakdown'   => $hourly,
            'total_sales'        => $shift['total_sales'],
            'total_refunds'      => $shift['total_refunds'],
            'total_discounts'    => $shift['total_discounts'],
            'total_tax'          => $shift['total_tax'],
            'cash_rounded'       => $shift['cash_rounded'] ?? 0,
            'transaction_count'  => $shift['transaction_count'],
            'cancelled_count'    => $shift['cancelled_count'] ?? 0,
            'cancelled_amount'   => $shift['cancelled_amount'] ?? 0,
            'net_sales'          => round((float)$shift['total_sales'] - (float)$shift['total_refunds'], 2),
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
            $row['loyalty_tier'] = $this->_get_loyalty_tier((float)($row['total_points'] ?? 0));
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
        if (!$customer) return null;

        $customer['loyalty_tier'] = $this->_get_loyalty_tier((float)($customer['total_points'] ?? 0));
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
            'company'        => $data['name'],
            'phonenumber'    => $data['phone'] ?? null,
            'active'         => 1,
            'datecreated'    => date('Y-m-d H:i:s'),
        ]);
        $client_id = $this->db->insert_id();

        $this->db->insert(db_prefix() . 'contacts', [
            'userid'      => $client_id,
            'firstname'   => $data['name'],
            'lastname'    => '',
            'email'       => $data['email'] ?? null,
            'phonenumber' => $data['phone'] ?? null,
            'is_primary'  => 1,
        ]);

        $qr_token = $this->_generate_qr_token();
        $this->db->insert(db_prefix() . 'pos_loyalty_customers', [
            'client_id'     => $client_id,
            'phone'         => $data['phone'] ?? null,
            'email'         => $data['email'] ?? null,
            'name'          => $data['name'],
            'qr_token'      => $qr_token,
            'registered_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) return false;
        return $this->get_customer($client_id);
    }

    // -------------------------------------------------------------------------
    // Loyalty
    // -------------------------------------------------------------------------

    public function get_loyalty_balance($customer_id)
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();
        if (!$lc) return null;
        $lc['loyalty_tier'] = $this->_get_loyalty_tier((float)$lc['total_points']);
        return $lc;
    }

    public function earn_points($customer_id, $receipt_id, $amount_spent)
    {
        $points = round((float)$amount_spent * 0.10, 2);
        $this->db->trans_start();
        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id' => $customer_id,
            'receipt_id'  => $receipt_id,
            'type'        => 'earn',
            'points'      => $points,
            'description' => 'Earned from purchase',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->db->set('total_points', 'total_points + ' . (float)$points, false)
            ->set('total_spent', 'total_spent + ' . (float)$amount_spent, false)
            ->set('last_visit', date('Y-m-d H:i:s'))
            ->where('id', $customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) return false;
        return $points;
    }

    public function redeem_points($customer_id, $receipt_id, $points)
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();
        if (!$lc || (float)$lc['total_points'] < (float)$points) return false;

        $this->db->trans_start();
        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id' => $customer_id,
            'receipt_id'  => $receipt_id,
            'type'        => 'redeem',
            'points'      => $points,
            'description' => 'Redeemed at POS',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->db->set('total_points', 'total_points - ' . (float)$points, false)
            ->where('id', $customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) return false;

        // 1 point = 1 currency unit (points represent 10% cashback in points)
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
            if (!$result) return false;
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
            if ($points >= (float)$tier['minimum_number_of_points']) return $tier;
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
        if ($warehouse_id)  $this->db->where('warehouse_id', $warehouse_id);
        if ($date_from) $this->db->where('receipt_date >=', $date_from);
        if ($date_to)   $this->db->where('receipt_date <=', $date_to);
        $receipts = $this->db->order_by('receipt_date', 'DESC')->get(db_prefix() . 'pos_receipts')->result_array();
        foreach ($receipts as &$receipt) {
            $receipt = $this->_attach_receipt_details($receipt);
        }
        return $receipts;
    }

    public function get_receipt($receipt_number)
    {
        $receipt = $this->db->where('receipt_number', $receipt_number)->get(db_prefix() . 'pos_receipts')->row_array();
        return $receipt ? $this->_attach_receipt_details($receipt) : null;
    }

    private function _attach_receipt_details($receipt)
    {
        $receipt['line_items'] = $this->db->where('receipt_id', $receipt['id'])->get(db_prefix() . 'pos_receipt_line_items')->result_array();
        $receipt['payments']   = $this->db->where('receipt_id', $receipt['id'])->get(db_prefix() . 'pos_receipt_payments')->result_array();
        return $receipt;
    }

    public function create_receipt($data)
    {
        $this->db->trans_start();

        $receipt_number   = 'RCP-' . strtoupper(uniqid());
        $cashback_qr_token = bin2hex(random_bytes(32));

        $this->db->insert(db_prefix() . 'pos_receipts', [
            'receipt_number'      => $receipt_number,
            'receipt_type'        => $data['receipt_type'] ?? 'SALE',
            'refund_for'          => $data['refund_for'] ?? null,
            'warehouse_id'            => $data['warehouse_id'],
            'employee_id'         => $data['employee_id'] ?? null,
            'shift_id'            => $data['shift_id'] ?? null,
            'customer_id'         => $data['customer_id'] ?? null,
            'loyalty_customer_id' => $data['loyalty_customer_id'] ?? null,
            'cashback_qr_token'   => $cashback_qr_token,
            'note'                => $data['note'] ?? null,
            'dining_option'       => $data['dining_option'] ?? null,
            'source'              => $data['source'] ?? 'POS',
            'subtotal'            => $data['subtotal'] ?? 0,
            'total_discount'      => $data['total_discount'] ?? 0,
            'total_tax'           => $data['total_tax'] ?? 0,
            'tip'                 => $data['tip'] ?? 0,
            'surcharge'           => $data['surcharge'] ?? 0,
            'total_money'         => $data['total_money'] ?? 0,
            'points_earned'       => $data['points_earned'] ?? 0,
            'points_deducted'     => $data['points_deducted'] ?? 0,
            'receipt_date'        => !empty($data['receipt_date']) ? $data['receipt_date'] : date('Y-m-d H:i:s'),
            'uploaded_at'         => date('Y-m-d H:i:s'),
        ]);
        $receipt_id = $this->db->insert_id();

        if ($receipt_id) {
            foreach ($data['line_items'] ?? [] as $item) {
                $this->db->insert(db_prefix() . 'pos_receipt_line_items', [
                    'receipt_id'      => $receipt_id,
                    'item_id'         => $item['item_id'],
                    'item_name'       => $item['item_name'],
                    'variant_id'      => $item['variant_id'] ?? null,
                    'variant_name'    => $item['variant_name'] ?? null,
                    'quantity'        => $item['quantity'] ?? 1,
                    'unit_price'      => $item['unit_price'] ?? 0,
                    'cost'            => $item['cost'] ?? 0,
                    'gross_total'     => $item['gross_total'] ?? 0,
                    'total_discount'  => $item['total_discount'] ?? 0,
                    'total_tax'       => $item['total_tax'] ?? 0,
                    'total_money'     => $item['total_money'] ?? 0,
                    'modifier_ids'    => json_encode($item['modifier_ids'] ?? []),
                    'modifier_names'  => json_encode($item['modifier_names'] ?? []),
                    'modifiers_price' => $item['modifiers_price'] ?? 0,
                    'tax_ids'         => json_encode($item['tax_ids'] ?? []),
                    'line_note'       => $item['line_note'] ?? null,
                ]);
            }

            foreach ($data['payments'] ?? [] as $payment) {
                $this->db->insert(db_prefix() . 'pos_receipt_payments', [
                    'receipt_id'      => $receipt_id,
                    'payment_type_id' => $payment['payment_type_id'],
                    'payment_name'    => $payment['payment_name'],
                    'type'            => $payment['type'] ?? 'CASH',
                    'money_amount'    => $payment['money_amount'] ?? 0,
                    'cash_back'       => $payment['cash_back'] ?? 0,
                    'payment_date'    => date('Y-m-d H:i:s'),
                ]);
            }

            // Auto-earn loyalty points if loyalty_customer_id provided
            if (!empty($data['loyalty_customer_id']) && !empty($data['total_money'])) {
                $this->earn_points($data['loyalty_customer_id'], $receipt_id, $data['total_money']);
            }
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false || !$receipt_id) return false;

        return [
            'receipt_number'    => $receipt_number,
            'cashback_qr_url'   => 'https://loyalty.kokonuts.my/cashback?token=' . $cashback_qr_token,
            'cashback_qr_token' => $cashback_qr_token,
        ];
    }

    public function create_refund($data)
    {
        $refund_receipt_number = 'RFD-' . strtoupper(uniqid());
        $this->db->insert(db_prefix() . 'pos_refunds', [
            'receipt_id'            => $data['receipt_id'],
            'refund_receipt_number' => $refund_receipt_number,
            'employee_id'           => $data['employee_id'] ?? null,
            'payment_type_id'       => $data['payment_type_id'] ?? null,
            'amount'                => $data['amount'] ?? 0,
            'note'                  => $data['note'] ?? null,
            'refunded_at'           => date('Y-m-d H:i:s'),
        ]);
        $refund_id = $this->db->insert_id();
        if (!$refund_id) return false;
        $this->db->where('id', $data['receipt_id'])->update(db_prefix() . 'pos_receipts', ['receipt_type' => 'REFUNDED']);
        return $refund_id;
    }
}
