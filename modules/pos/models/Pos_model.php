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

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function get_dashboard_summary($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND warehouse_id = ' . (int)$warehouse_id : '';

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

        $row['net_sales']    = round((float)$row['total_sales'] - (float)$row['total_refunds'], 2);
        $row['avg_transaction'] = $row['transaction_count'] > 0
            ? round((float)$row['total_sales'] / (int)$row['transaction_count'], 2)
            : 0;
        $total_txn = (int)$row['transaction_count'] + (int)$row['refund_count'];
        $row['refund_rate'] = $total_txn > 0
            ? round((float)$row['refund_count'] / $total_txn * 100, 1)
            : 0;
        return $row;
    }

    public function get_dashboard_daily_trend($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND warehouse_id = ' . (int)$warehouse_id : '';

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
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND warehouse_id = ' . (int)$warehouse_id : '';

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
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND r.warehouse_id = ' . (int)$warehouse_id : '';

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
            LIMIT " . (int)$limit
        , [$from, $to])->result_array();
    }

    public function get_dashboard_payments($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND r.warehouse_id = ' . (int)$warehouse_id : '';

        return $this->db->query("
            SELECT rp.payment_name,
                   COALESCE(SUM(rp.money_amount), 0) AS total
            FROM `" . db_prefix() . "pos_receipt_payments` rp
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = rp.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? $wh
            GROUP BY rp.payment_type_id, rp.payment_name
            ORDER BY total DESC
        ", [$from, $to])->result_array();
    }

    public function get_shifts($filters = [])
    {
        $warehouse_id = $filters['warehouse_id'] ?? null;
        $status       = $filters['status']       ?? '';
        $date_from    = $filters['date_from']    ?? null;
        $date_to      = $filters['date_to']      ?? null;
        $page         = max(1, (int)($filters['page']  ?? 1));
        $limit        = max(1, min(200, (int)($filters['limit'] ?? 20)));
        $offset       = ($page - 1) * $limit;

        $allowed_sort = [
            'opened_at'         => 's.opened_at',
            'closed_at'         => 's.closed_at',
            'total_sales'       => 's.total_sales',
            'opening_float'     => 's.opening_float',
            'expected_cash'     => 's.expected_cash',
            'actual_cash'       => 's.actual_cash',
            'difference'        => 's.difference',
            'transaction_count' => 's.transaction_count',
        ];
        $sort_col = $allowed_sort[$filters['sort'] ?? ''] ?? 's.opened_at';
        $sort_dir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $this->db->select('s.*, w.warehouse_name')
            ->from(db_prefix() . 'pos_shifts s')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = s.warehouse_id', 'left')
            ->order_by($sort_col, $sort_dir);

        if ($warehouse_id) $this->db->where('s.warehouse_id', (int)$warehouse_id);
        if ($status)       $this->db->where('s.status', $status);
        if ($date_from)    $this->db->where('DATE(s.opened_at) >=', $date_from);
        if ($date_to)      $this->db->where('DATE(s.opened_at) <=', $date_to);

        $total = $this->db->count_all_results('', false);

        $rows = $this->db->limit($limit, $offset)->get()->result_array();

        return [
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'page_count' => max(1, (int)ceil($total / $limit)),
        ];
    }

    public function get_dashboard_recent_shifts($warehouse_id = null, $limit = 8)
    {
        $this->db->select('s.*, w.warehouse_name')
            ->from(db_prefix() . 'pos_shifts s')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = s.warehouse_id', 'left')
            ->order_by('s.opened_at', 'DESC')
            ->limit($limit);
        if ($warehouse_id) {
            $this->db->where('s.warehouse_id', (int)$warehouse_id);
        }
        return $this->db->get()->result_array();
    }

    public function get_modifiers($warehouse_id = null)
    {
        $this->db->order_by('name', 'ASC');
        if ($warehouse_id) {
            $this->db->where('(NOT EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_modifier_group_warehouses` mgw WHERE mgw.modifier_group_id = `' . db_prefix() . 'modifier_groups`.id)
                OR EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_modifier_group_warehouses` mgw WHERE mgw.modifier_group_id = `' . db_prefix() . 'modifier_groups`.id AND mgw.warehouse_id = ' . (int)$warehouse_id . '))', null, false);
        }
        $groups = $this->db->get(db_prefix() . 'modifier_groups')->result_array();
        foreach ($groups as &$group) {
            $group['modifiers'] = $this->db
                ->where('modifier_group_id', $group['id'])
                ->where('active', 1)
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
            $group['warehouse_ids'] = $this->get_modifier_group_warehouses($group['id']);
        }
        return $groups;
    }

    public function get_modifier_groups($warehouse_id = null)
    {
        $this->db->order_by('name', 'ASC');
        if ($warehouse_id) {
            $this->db->where('(NOT EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_modifier_group_warehouses` mgw WHERE mgw.modifier_group_id = `' . db_prefix() . 'modifier_groups`.id)
                OR EXISTS (SELECT 1 FROM `' . db_prefix() . 'pos_modifier_group_warehouses` mgw WHERE mgw.modifier_group_id = `' . db_prefix() . 'modifier_groups`.id AND mgw.warehouse_id = ' . (int)$warehouse_id . '))', null, false);
        }
        $groups = $this->db->get(db_prefix() . 'modifier_groups')->result_array();
        foreach ($groups as &$group) {
            $group['modifiers'] = $this->db
                ->where('modifier_group_id', $group['id'])
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'modifiers')->result_array();
            $group['warehouse_ids'] = $this->get_modifier_group_warehouses($group['id']);
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

    public function delete_modifier_groups_bulk(array $ids)
    {
        if (empty($ids)) return false;
        $this->db->where_in('id', array_map('intval', $ids))->delete(db_prefix() . 'modifier_groups');
        return $this->db->affected_rows() > 0;
    }

    public function save_modifier_with_options($data, $id = null)
    {
        $this->db->trans_start();

        $group_id = $this->save_modifier_group($data, $id);

        // Replace all options
        if ($group_id) {
            $this->db->where('modifier_group_id', $group_id)->delete(db_prefix() . 'modifiers');
            foreach ($data['options'] ?? [] as $i => $opt) {
                $name = trim($opt['name'] ?? '');
                if ($name === '') continue;
                $this->db->insert(db_prefix() . 'modifiers', [
                    'modifier_group_id' => $group_id,
                    'name'              => $name,
                    'price_adjustment'  => (float)($opt['price_adjustment'] ?? 0),
                    'sort_order'        => $i,
                    'active'            => 1,
                ]);
            }
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) return false;
        return $group_id;
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

    public function get_modifier_group_items($modifier_group_id)
    {
        return $this->db
            ->select('i.id, i.sku_name, i.sku_code, img.sort_order')
            ->from(db_prefix() . 'item_modifier_groups img')
            ->join(db_prefix() . 'items i', 'i.id = img.pos_item_id')
            ->where('img.modifier_group_id', (int)$modifier_group_id)
            ->order_by('i.sku_name', 'ASC')
            ->get()->result_array();
    }

    public function assign_items_to_modifier_group($modifier_group_id, array $item_ids)
    {
        foreach ($item_ids as $item_id) {
            $exists = $this->db->get_where(db_prefix() . 'item_modifier_groups', [
                'pos_item_id'       => (string)$item_id,
                'modifier_group_id' => (int)$modifier_group_id,
            ])->row();
            if (!$exists) {
                $this->db->insert(db_prefix() . 'item_modifier_groups', [
                    'pos_item_id'       => (string)$item_id,
                    'modifier_group_id' => (int)$modifier_group_id,
                    'sort_order'        => 0,
                ]);
            }
        }
        return true;
    }

    public function unassign_item_from_modifier_group($modifier_group_id, $item_id)
    {
        $this->db
            ->where('modifier_group_id', (int)$modifier_group_id)
            ->where('pos_item_id', (string)$item_id)
            ->delete(db_prefix() . 'item_modifier_groups');
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

    // =========================================================================
    // Individual item modifiers
    // =========================================================================

    public function get_item_modifiers($item_id)
    {
        $groups = $this->db
            ->where('pos_item_id', (string)$item_id)
            ->where('active', 1)
            ->order_by('sort_order', 'ASC')
            ->get(db_prefix() . 'item_modifiers')->result_array();

        foreach ($groups as &$group) {
            $group['options'] = $this->db
                ->where('item_modifier_id', $group['id'])
                ->order_by('sort_order', 'ASC')
                ->get(db_prefix() . 'item_modifier_options')->result_array();
        }

        return $groups;
    }

    public function save_item_modifier($item_id, $data, $id = null)
    {
        $row = [
            'pos_item_id'    => (string)$item_id,
            'name'           => trim($data['name']),
            'selection_type' => in_array($data['selection_type'] ?? '', ['single', 'multiple']) ? $data['selection_type'] : 'single',
            'sort_order'     => (int)($data['sort_order'] ?? 0),
            'active'         => 1,
        ];

        if ($id) {
            $this->db->where('id', (int)$id)->where('pos_item_id', (string)$item_id)->update(db_prefix() . 'item_modifiers', $row);
            $modifier_id = (int)$id;
        } else {
            $this->db->insert(db_prefix() . 'item_modifiers', $row);
            $modifier_id = $this->db->insert_id();
        }

        // Replace options
        $this->db->where('item_modifier_id', $modifier_id)->delete(db_prefix() . 'item_modifier_options');
        if (!empty($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $i => $opt) {
                $opt_name = trim($opt['name'] ?? '');
                if ($opt_name === '') { continue; }
                $this->db->insert(db_prefix() . 'item_modifier_options', [
                    'item_modifier_id' => $modifier_id,
                    'name'             => $opt_name,
                    'price_adjustment' => (float)($opt['price_adjustment'] ?? 0),
                    'sort_order'       => (int)($opt['sort_order'] ?? $i),
                ]);
            }
        }

        return $modifier_id;
    }

    public function delete_item_modifier($id, $item_id)
    {
        $this->db->where('id', (int)$id)->where('pos_item_id', (string)$item_id)->delete(db_prefix() . 'item_modifiers');
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

        $wid = $warehouse_id ? (int)$warehouse_id : 0;
        $price_select = $wid
            ? 'COALESCE((SELECT price FROM `' . db_prefix() . 'pos_item_warehouse_prices` WHERE item_id = i.id AND warehouse_id = ' . $wid . ' LIMIT 1), i.rate) AS effective_price'
            : 'i.rate AS effective_price';

        // inventory_manage can have multiple rows per (commodity_id, warehouse_id) — sum via
        // subquery instead of joining directly, which would fan out one item into N duplicate rows.
        $stock_select = $wid
            ? 'COALESCE((SELECT SUM(inventory_number) FROM `' . db_prefix() . 'inventory_manage` WHERE commodity_id = i.id AND warehouse_id = ' . $wid . '), 0)'
            : 'COALESCE((SELECT SUM(inventory_number) FROM `' . db_prefix() . 'inventory_manage` WHERE commodity_id = i.id), 0)';

        $this->db->select('i.*, ' . $stock_select . ' as stock_quantity, ' . $price_select, FALSE)
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

        foreach ($items as &$item) {
            $item['variants']           = $this->_get_item_variants($item['id'], $warehouse_id);
            $item['tax_info']           = $this->_get_item_tax_info($item);
            $item['modifier_group_ids'] = array_column($this->get_item_modifier_groups($item['id']), 'modifier_group_id');
            $item['item_modifiers']     = $this->get_item_modifiers($item['id']);
        }
        return $items;
    }

    public function get_item($id, $warehouse_id = null)
    {
        $wid = $warehouse_id ? (int)$warehouse_id : 0;
        $price_select = $wid
            ? 'COALESCE((SELECT price FROM `' . db_prefix() . 'pos_item_warehouse_prices` WHERE item_id = i.id AND warehouse_id = ' . $wid . ' LIMIT 1), i.rate) AS effective_price'
            : 'i.rate AS effective_price';

        $item = $this->db->select('i.*, COALESCE(inv.inventory_number, 0) as stock_quantity, ' . $price_select, FALSE)
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'inventory_manage inv', 'inv.commodity_id = i.id', 'left')
            ->where('i.id', $id)
            ->where('i.active', 1)
            ->get()->row_array();

        if (!$item) return null;
        $item['variants']            = $this->_get_item_variants($id, $wid ?: null);
        $item['tax_info']            = $this->_get_item_tax_info($item);
        $item['modifier_group_ids']  = array_column($this->get_item_modifier_groups($id), 'modifier_group_id');
        $item['item_modifiers']      = $this->get_item_modifiers($id);
        $item['warehouse_prices']    = $this->get_item_warehouse_prices($id);
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
        $item['variants']           = $this->_get_item_variants($item['id'], null);
        $item['tax_info']           = $this->_get_item_tax_info($item);
        $item['modifier_group_ids'] = array_column($this->get_item_modifier_groups($item['id']), 'modifier_group_id');
        $item['item_modifiers']     = $this->get_item_modifiers($item['id']);
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
        $p   = db_prefix();
        $enabled = $enabled ? 1 : 0;
        $exists = $this->db->where('payment_mode_id', $payment_mode_id)->get("{$p}pos_payment_mode_settings")->row();
        if ($exists) {
            return $this->db->where('payment_mode_id', $payment_mode_id)
                ->update("{$p}pos_payment_mode_settings", ['pos_enabled' => $enabled]);
        }
        return $this->db->insert("{$p}pos_payment_mode_settings", [
            'payment_mode_id' => $payment_mode_id,
            'pos_enabled'     => $enabled,
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
        $cash_sales = (float)$this->db->select('SUM(rp.money_amount) as money_amount', FALSE)
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'SALE')
            ->get()->row()->money_amount;

        $cash_refunds = (float)$this->db->select('SUM(rp.money_amount) as money_amount', FALSE)
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'REFUND')
            ->get()->row()->money_amount;

        $expected_cash = (float)$shift['opening_float'] + $pay_ins - $pay_outs + $cash_sales - $cash_refunds;
        $actual_cash   = (float)($data['actual_cash'] ?? 0);
        $difference    = $actual_cash - $expected_cash;

        // Aggregate totals from receipts in this shift
        $summary = $this->db->select('SUM(total_money) as total_sales, SUM(total_discount) as total_discounts, SUM(total_tax) as total_tax, SUM(tip) as total_tips, COUNT(*) as transaction_count')
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
            'total_tips'            => round((float)$summary['total_tips'], 2),
            'cash_rounded'          => round($cash_rounded, 2),
            'transaction_count'     => (int)$summary['transaction_count'],
            'cancelled_count'       => (int)$cancelled['cnt'],
            'cancelled_amount'      => round((float)$cancelled['amount'], 2),
            'status'                => 'closed',
            'closed_at'             => date('Y-m-d H:i:s'),
            'notes'                 => $data['notes'] ?? null,
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
        if (!$shift) return null;

        // Totals by payment type, split by SALE vs REFUND
        $by_payment_raw = $this->db->select('rp.payment_type_id, MAX(rp.payment_name) as payment_name, MAX(rp.type) as payment_type, r.receipt_type, SUM(rp.money_amount) as total, COUNT(*) as transactions')
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

        // Cash-only totals for reconciliation display
        $cash_sales_total = (float)$this->db->select('SUM(rp.money_amount) as money_amount', FALSE)
            ->from(db_prefix() . 'pos_receipt_payments rp')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = rp.receipt_id')
            ->where('r.shift_id', $shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('rp.type', 'CASH')
            ->where('r.receipt_type', 'SALE')
            ->get()->row()->money_amount;

        $cash_refunds_total = (float)$this->db->select('SUM(rp.money_amount) as money_amount', FALSE)
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
            'cash_sales'         => $cash_sales_total,
            'cash_refunds'       => $cash_refunds_total,
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
            $row['loyalty_tier'] = $this->_get_loyalty_tier((float)($row['total_points'] ?? 0));
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
        if (!$lc) return null;
        $lc['loyalty_tier'] = $this->_get_loyalty_tier((float)$lc['total_points']);
        return $lc;
    }

    public function earn_points($customer_id, $receipt_id, $amount_spent, $warehouse_id = null)
    {
        $points = round((float)$amount_spent * 0.10, 2);
        $lc = $this->db->select('total_points')->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();
        $balance_after = round((float)($lc['total_points'] ?? 0) + $points, 2);
        $tier = $this->_get_loyalty_tier($balance_after);

        $this->db->trans_start();
        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id'  => $customer_id,
            'receipt_id'   => $receipt_id,
            'warehouse_id' => $warehouse_id ? (int)$warehouse_id : null,
            'type'         => 'earn',
            'points'       => $points,
            'balance_after' => $balance_after,
            'tier_name'    => $tier ? $tier['name'] : null,
            'description'  => 'Earned from purchase',
            'created_at'   => date('Y-m-d H:i:s'),
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

    public function redeem_points($customer_id, $receipt_id, $points, $warehouse_id = null)
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();
        if (!$lc || (float)$lc['total_points'] < (float)$points) return false;

        $balance_after = round((float)$lc['total_points'] - (float)$points, 2);
        $tier = $this->_get_loyalty_tier($balance_after);

        $this->db->trans_start();
        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id'  => $customer_id,
            'receipt_id'   => $receipt_id,
            'warehouse_id' => $warehouse_id ? (int)$warehouse_id : null,
            'type'         => 'redeem',
            'points'       => $points,
            'balance_after' => $balance_after,
            'tier_name'    => $tier ? $tier['name'] : null,
            'description'  => 'Redeemed at POS',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        $this->db->set('total_points', 'total_points - ' . (float)$points, false)
            ->where('id', $customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) return false;

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
        $pfx = db_prefix();
        $receipt = $this->db
            ->select('r.*, w.warehouse_name, e.name as employee_name')
            ->from($pfx . 'pos_receipts r')
            ->join($pfx . 'warehouse w',     'w.warehouse_id = r.warehouse_id', 'left')
            ->join($pfx . 'pos_employees e', 'e.id = r.employee_id',            'left')
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
            ->join($pfx . 'warehouse w',     'w.warehouse_id = r.warehouse_id', 'left')
            ->join($pfx . 'pos_employees e', 'e.id = r.employee_id',            'left')
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
        $this->db->where('id', $id)->update(db_prefix() . 'pos_receipts', [
            'cancelled_at'             => date('Y-m-d H:i:s'),
            'cancellation_reason'      => $reason ?: null,
            'cancelled_by_employee_id' => $employee_id ? (int)$employee_id : null,
        ]);
        return $this->db->affected_rows() > 0;
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
                'status'     => 'printed',
                'printed_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        }

        $attempts = (int) $job['attempts'] + 1;
        $this->db->where('id', $id)->update(db_prefix() . 'pos_print_jobs', [
            'attempts'   => $attempts,
            'status'     => $attempts >= 3 ? 'failed' : 'pending',
            'last_error' => $error ?: 'Print failed',
        ]);
        return true;
    }

    private function _attach_receipt_details($receipt)
    {
        $line_items = $this->db->where('receipt_id', $receipt['id'])->get(db_prefix() . 'pos_receipt_line_items')->result_array();
        foreach ($line_items as &$item) {
            $item['modifier_ids']   = json_decode($item['modifier_ids']   ?? '[]', true) ?: [];
            $item['modifier_names'] = json_decode($item['modifier_names'] ?? '[]', true) ?: [];
            $item['tax_ids']        = json_decode($item['tax_ids']        ?? '[]', true) ?: [];
        }
        $receipt['line_items']     = $line_items;
        $receipt['payments']       = $this->db->where('receipt_id', $receipt['id'])->get(db_prefix() . 'pos_receipt_payments')->result_array();
        $receipt['status']         = $this->_receipt_status($receipt);
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

        if (!$gf || empty($gf['raw_payload'])) return null;

        $order = json_decode($gf['raw_payload'], true);
        $price = $order['price'] ?? null;
        if (!$price) return null;

        $exponent = (int) ($order['currency']['exponent'] ?? 2);
        $shift    = function ($value) use ($exponent) {
            return round(((float) $value) / (10 ** max(0, $exponent)), 2);
        };

        return [
            'delivery_fee'            => $shift($price['deliveryFee']            ?? 0),
            'service_charge_fee'      => $shift($price['serviceChargeFee']       ?? 0),
            'small_order_fee'         => $shift($price['smallOrderFee']          ?? 0),
            'merchant_charge_fee_min' => $shift($price['merchantChargeFeeInMin'] ?? 0),
        ];
    }

    private function _receipt_status($receipt)
    {
        if (!empty($receipt['cancelled_at']))           return 'cancelled';
        if (!empty($receipt['refund_for']))             return 'return';
        if ($receipt['receipt_type'] === 'REFUNDED')   return 'refunded';
        return 'completed';
    }

    public function create_receipt($data)
    {
        $this->db->trans_start();

        $receipt_number   = 'RCP-' . strtoupper(uniqid());
        $cashback_qr_token = bin2hex(random_bytes(32));

        $this->db->insert(db_prefix() . 'pos_receipts', [
            'receipt_number'      => $receipt_number,
            'queue_number'        => isset($data['queue_number']) ? (string) $data['queue_number'] : null,
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
                    'category_id'     => isset($item['category_id']) ? (int)$item['category_id'] : null,
                    'category_name'   => $item['category_name'] ?? null,
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
                    'promotion_id'    => isset($item['promotion_id']) ? (int)$item['promotion_id'] : null,
                    'discount_type'   => $item['discount_type'] ?? null,
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
                $this->earn_points($data['loyalty_customer_id'], $receipt_id, $data['total_money'], $data['warehouse_id'] ?? null);
            }
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false || !$receipt_id) return false;

        return [
            'receipt_number'    => $receipt_number,
            'cashback_qr_url'   => 'https://loyalty.kokonuts.my/claim/' . $cashback_qr_token,
            'cashback_qr_token' => $cashback_qr_token,
        ];
    }

    // =========================================================================
    // Transactions (back-office list)
    // =========================================================================

    public function get_transactions($filters = [])
    {
        $warehouse_id = $filters['warehouse_id'] ?? null;
        $date_from    = $filters['date_from']    ?? null;
        $date_to      = $filters['date_to']      ?? null;
        $search       = trim($filters['search']  ?? '');
        $shift_id     = $filters['shift_id']     ?? null;
        $page         = max(1, (int)($filters['page']  ?? 1));
        $limit        = min(100, max(10, (int)($filters['limit'] ?? 20)));
        $offset       = ($page - 1) * $limit;

        $allowed_sort = [
            'receipt_date'   => 'r.receipt_date',
            'warehouse_name' => 'w.warehouse_name',
            'subtotal'       => 'items_subtotal',
            'total_discount' => 'r.total_discount',
            'delivery_fee'   => 'delivery_fee',
            'total_money'    => 'r.total_money',
        ];
        $sort_col = $allowed_sort[$filters['sort'] ?? ''] ?? 'r.receipt_date';
        $sort_dir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $this->_build_transactions_query($warehouse_id, $date_from, $date_to, $search, $shift_id);
        $total = $this->db->count_all_results('', false);

        $pfx  = db_prefix();
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
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'page_count' => (int) ceil($total / max(1, $limit)),
        ];
    }

    private function _build_transactions_query($warehouse_id, $date_from, $date_to, $search, $shift_id = null)
    {
        $this->db
            ->from(db_prefix() . 'pos_receipts r')
            ->join(db_prefix() . 'warehouse w',       'w.warehouse_id = r.warehouse_id', 'left')
            ->join(db_prefix() . 'pos_employees e',   'e.id = r.employee_id',            'left');

        if ($warehouse_id) $this->db->where('r.warehouse_id', (int)$warehouse_id);
        if ($date_from)    $this->db->where('r.receipt_date >=', $date_from . ' 00:00:00');
        if ($date_to)      $this->db->where('r.receipt_date <=', $date_to   . ' 23:59:59');
        if ($search)       $this->db->like('r.receipt_number', $search, 'both');
        if ($shift_id)     $this->db->where('r.shift_id', (int)$shift_id);
    }

    // =========================================================================
    // Receipt Settings
    // =========================================================================

    public function get_receipt_settings($warehouse_id)
    {
        return $this->db
            ->where('warehouse_id', (int)$warehouse_id)
            ->get(db_prefix() . 'pos_receipt_settings')
            ->row_array();
    }

    public function save_receipt_settings($warehouse_id, $data)
    {
        $warehouse_id = (int)$warehouse_id;
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
            ->where('warehouse_id', (int)$warehouse_id)
            ->get(db_prefix() . 'pos_cfd_settings')
            ->row_array();
    }

    public function save_cfd_settings($warehouse_id, $data)
    {
        $warehouse_id = (int)$warehouse_id;
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
            ->where('warehouse_id', (int)$warehouse_id)
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'pos_cfd_media_items')
            ->result_array();
    }

    public function add_cfd_media_item($warehouse_id, $data)
    {
        $next_order = (int)$this->db
            ->select_max('sort_order')
            ->where('warehouse_id', (int)$warehouse_id)
            ->get(db_prefix() . 'pos_cfd_media_items')
            ->row()->sort_order + 1;

        $data['warehouse_id'] = (int)$warehouse_id;
        $data['sort_order']   = $next_order;
        $data['created_at']   = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'pos_cfd_media_items', $data);
        return $this->db->insert_id();
    }

    public function delete_cfd_media_item($id, $warehouse_id)
    {
        return $this->db
            ->where('id', (int)$id)
            ->where('warehouse_id', (int)$warehouse_id)
            ->delete(db_prefix() . 'pos_cfd_media_items');
    }

    public function reorder_cfd_media_items($warehouse_id, array $ordered_ids)
    {
        foreach ($ordered_ids as $i => $id) {
            $this->db
                ->where('id', (int)$id)
                ->where('warehouse_id', (int)$warehouse_id)
                ->update(db_prefix() . 'pos_cfd_media_items', ['sort_order' => $i]);
        }
        return true;
    }

    public function delete_transaction($id)
    {
        $id = (int)$id;
        if (!$id) return false;

        $receipt = $this->db->get_where(db_prefix() . 'pos_receipts', ['id' => $id])->row_array();
        if (!$receipt) return false;

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

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $this->db->insert(db_prefix() . 'pos_refund_items', [
                    'refund_id'    => $refund_id,
                    'line_item_id' => $item['line_item_id'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'total_money'  => $item['total_money'],
                ]);
            }
        }

        $this->db->where('id', $data['receipt_id'])->update(db_prefix() . 'pos_receipts', ['receipt_type' => 'REFUNDED']);
        return $refund_id;
    }

    // -------------------------------------------------------------------------
    // POS Product CRUD
    // -------------------------------------------------------------------------

    public function get_pos_product($id)
    {
        $item = $this->db
            ->select('i.id, i.sku_name, i.sku_code, i.description, i.image, i.rate, i.group_id, i.sub_group, i.active, i.fd_available, i.fd_price, i.fd_available_published, i.fd_price_published')
            ->from(db_prefix() . 'items i')
            ->where('i.id', (int)$id)
            ->where('i.can_be_sold', 'can_be_sold')
            ->where('i.can_be_manufacturing', 'can_be_manufacturing')
            ->where('i.parent_id IS NULL', null, false)
            ->get()->row_array();
        if ($item) {
            $item['warehouse_ids']    = $this->get_item_warehouses($id);
            $item['warehouse_prices'] = $this->get_item_warehouse_prices($id);
        }
        return $item;
    }

    public function save_pos_product($data, $id = null)
    {
        $row = [
            'sku_name'     => $data['sku_name'],
            'sku_code'     => strtoupper(str_replace(' ', '', $data['sku_code'] ?: '')),
            'description'  => $data['description'] ?? '',
            'rate'         => (float)$data['rate'],
            'group_id'     => ($data['group_id'] !== '' && $data['group_id'] !== null) ? (int)$data['group_id'] : null,
            'sub_group'    => ($data['sub_group'] !== '' && $data['sub_group'] !== null) ? (int)$data['sub_group'] : null,
            'active'       => (int)$data['active'],
            'fd_available' => !empty($data['fd_available']) ? 1 : 0,
            'fd_price'     => ($data['fd_price'] !== '' && $data['fd_price'] !== null) ? (float)$data['fd_price'] : null,
        ];

        if ($id) {
            $this->db->where('id', (int)$id)
                ->where('can_be_sold', 'can_be_sold')
                ->where('can_be_manufacturing', 'can_be_manufacturing')
                ->update(db_prefix() . 'items', $row);
            return (int)$id;
        }

        if (empty($row['sku_code'])) {
            $row['sku_code'] = 'POS' . strtoupper(substr(md5(uniqid()), 0, 8));
        }

        $row['can_be_sold']        = 'can_be_sold';
        $row['can_be_manufacturing'] = 'can_be_manufacturing';
        $row['commodity_type']     = 5;
        $row['parent_id']          = null;

        $this->db->insert(db_prefix() . 'items', $row);
        return $this->db->insert_id() ?: false;
    }

    // =========================================================================
    // Warehouse availability — products & modifier groups
    // No rows in the junction table = available at ALL warehouses (global)
    // =========================================================================

    public function get_item_warehouse_prices($item_id)
    {
        return $this->db->select('warehouse_id, price')
            ->where('item_id', (int)$item_id)
            ->get(db_prefix() . 'pos_item_warehouse_prices')
            ->result_array();
    }

    public function set_item_warehouse_prices($item_id, array $prices)
    {
        $this->db->where('item_id', (int)$item_id)->delete(db_prefix() . 'pos_item_warehouse_prices');
        foreach ($prices as $wid => $price) {
            $wid   = (int)$wid;
            $price = (float)$price;
            if (!$wid || $price < 0) continue;
            $this->db->insert(db_prefix() . 'pos_item_warehouse_prices', [
                'item_id'      => (int)$item_id,
                'warehouse_id' => $wid,
                'price'        => $price,
            ]);
        }
    }

    public function get_item_warehouses($item_id)
    {
        return array_column(
            $this->db->select('warehouse_id')->where('item_id', (int)$item_id)
                ->get(db_prefix() . 'pos_item_warehouses')->result_array(),
            'warehouse_id'
        );
    }

    public function set_item_warehouses($item_id, array $warehouse_ids)
    {
        $this->db->where('item_id', (int)$item_id)->delete(db_prefix() . 'pos_item_warehouses');
        foreach (array_unique(array_map('intval', array_filter($warehouse_ids))) as $wid) {
            $this->db->insert(db_prefix() . 'pos_item_warehouses', [
                'item_id'      => (int)$item_id,
                'warehouse_id' => $wid,
            ]);
        }
    }

    public function get_modifier_group_warehouses($group_id)
    {
        return array_column(
            $this->db->select('warehouse_id')->where('modifier_group_id', (int)$group_id)
                ->get(db_prefix() . 'pos_modifier_group_warehouses')->result_array(),
            'warehouse_id'
        );
    }

    public function set_modifier_group_warehouses($group_id, array $warehouse_ids)
    {
        $this->db->where('modifier_group_id', (int)$group_id)->delete(db_prefix() . 'pos_modifier_group_warehouses');
        foreach (array_unique(array_map('intval', array_filter($warehouse_ids))) as $wid) {
            $this->db->insert(db_prefix() . 'pos_modifier_group_warehouses', [
                'modifier_group_id' => (int)$group_id,
                'warehouse_id'      => $wid,
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
        $sub_group_id = (int)$sub_group_id;
        if ($this->db->where('sub_group_id', $sub_group_id)->count_all_results(db_prefix() . 'pos_category_settings')) {
            return true; // already added — nothing to do
        }

        $section_id = $this->get_default_section_id();
        $next_sort  = (int)$this->db->select_max('sort_order')->get(db_prefix() . 'pos_category_settings')->row()->sort_order + 1;

        return $this->db->insert(db_prefix() . 'pos_category_settings', [
            'sub_group_id' => $sub_group_id,
            'section_id'   => $section_id,
            'sort_order'   => $next_sort,
            'published'    => 0,
        ]);
    }

    // The category's own "delete" button: removes it from the FD Menu Layout entirely.
    // Items' fd_available state is left untouched; re-add via "Add Category".
    public function disable_category_for_fd($sub_group_id)
    {
        return $this->db->where('sub_group_id', (int)$sub_group_id)
            ->delete(db_prefix() . 'pos_category_settings');
    }

    public function reorder_category($sub_group_id, $direction)
    {
        $sub_group_id = (int)$sub_group_id;
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
        $id  = (int)$id;
        $pos = array_search($id, $ids, true);
        if ($pos === false) return false;

        $swap_pos = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($swap_pos < 0 || $swap_pos >= count($ids)) return false;

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
        return $this->db->where('id', (int)$item_id)->update(db_prefix() . 'items', ['image' => $filename]);
    }

    public function remove_item_image($item_id)
    {
        return $this->db->where('id', (int)$item_id)->update(db_prefix() . 'items', ['image' => null]);
    }

    // =========================================================================
    // Analytics queries (use new columns added in migration 109)
    // =========================================================================

    public function get_dashboard_category_breakdown($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND r.warehouse_id = ' . (int)$warehouse_id : '';

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
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND r.warehouse_id = ' . (int)$warehouse_id : '';

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
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND r.warehouse_id = ' . (int)$warehouse_id : '';

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
        $map  = [];
        foreach ($rows as $row) {
            $map[(int)$row['payment_type_id']] = $row;
        }
        return $map;
    }

    public function save_accounting_settings($data)
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->limit(1)->get(db_prefix() . 'pos_accounting_settings')->row_array();
        $payload  = ['enabled' => isset($data['enabled']) ? (int)(bool)$data['enabled'] : 0, 'updated_at' => $now];
        if ($existing) {
            $this->db->where('id', $existing['id'])->update(db_prefix() . 'pos_accounting_settings', $payload);
        } else {
            $payload['created_at'] = $now;
            $this->db->insert(db_prefix() . 'pos_accounting_settings', $payload);
        }

        // Upsert per-payment-method mappings
        $mappings = $data['payment_method_accounts'] ?? [];
        foreach ($mappings as $type_id => $map) {
            $type_id = (int)$type_id;
            if (!$type_id) continue;
            $row = [
                'debit_account_id'  => !empty($map['debit'])  ? (int)$map['debit']  : null,
                'credit_account_id' => !empty($map['credit']) ? (int)$map['credit'] : null,
                'updated_at'        => $now,
            ];
            $existing_map = $this->db->where('payment_type_id', $type_id)
                ->limit(1)->get(db_prefix() . 'pos_payment_method_accounts')->row_array();
            if ($existing_map) {
                $this->db->where('id', $existing_map['id'])->update(db_prefix() . 'pos_payment_method_accounts', $row);
            } else {
                $row['payment_type_id'] = $type_id;
                $row['created_at']      = $now;
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
        $already = $this->db->where('shift_id', (int)$shift_id)
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
            ->where('r.shift_id', (int)$shift_id)
            ->where('r.cancelled_at IS NULL')
            ->where('r.receipt_type', 'SALE')
            ->group_by('rp.payment_type_id')
            ->get()->result_array();

        if (empty($payment_totals)) {
            return false;
        }

        $mappings     = $this->get_payment_method_accounts();
        $journal_date = date('Y-m-d', strtotime($shift['closed_at']));
        $description  = 'POS Shift ' . $shift['shift_code'] . ' — ' . ($shift['warehouse_name'] ?? '');
        $now          = date('Y-m-d H:i:s');

        $lines         = [];
        $journal_total = 0;

        foreach ($payment_totals as $pt) {
            $type_id = (int)$pt['payment_type_id'];
            $amount  = round((float)$pt['total'], 2);
            if ($amount <= 0) continue;

            $map = $mappings[$type_id] ?? null;
            if (!$map || empty($map['debit_account_id']) || empty($map['credit_account_id'])) {
                continue; // unmapped payment method — skip
            }

            $label = htmlspecialchars_decode($pt['payment_name']);

            $lines[] = [
                'account'     => (int)$map['debit_account_id'],
                'date'        => $journal_date,
                'debit'       => $amount,
                'credit'      => 0,
                'description' => 'POS ' . $label . ' receipts — ' . $description,
                'rel_id'      => 0,
                'rel_type'    => 'journal_entry',
                'datecreated' => $now,
                'addedfrom'   => 0,
            ];
            $lines[] = [
                'account'     => (int)$map['credit_account_id'],
                'date'        => $journal_date,
                'debit'       => 0,
                'credit'      => $amount,
                'description' => 'POS ' . $label . ' sales — ' . $description,
                'rel_id'      => 0,
                'rel_type'    => 'journal_entry',
                'datecreated' => $now,
                'addedfrom'   => 0,
            ];
            $journal_total += $amount;
        }

        if (empty($lines)) {
            return false;
        }

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'acc_journal_entries', [
            'number'       => 'POS-' . $shift['shift_code'],
            'description'  => $description,
            'journal_date' => $journal_date,
            'amount'       => round($journal_total, 2),
            'datecreated'  => $now,
            'addedfrom'    => 0,
            'recurring'    => 0,
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
                'shift_id'         => (int)$shift_id,
                'journal_entry_id' => $journal_id,
                'synced_at'        => $now,
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

    public function delete_pos_product($id)
    {
        $id = (int)$id;

        $used = $this->db->where('item_id', $id)
            ->count_all_results(db_prefix() . 'pos_receipt_line_items');

        if ($used > 0) {
            return [
                'success' => false,
                'message' => 'Product is used in ' . $used . ' transaction(s) and cannot be deleted.',
            ];
        }

        // Remove modifier assignments before deleting
        $this->db->where('pos_item_id', (string)$id)->delete(db_prefix() . 'item_modifier_groups');
        $modifiers = $this->db->select('id')->where('pos_item_id', (string)$id)
            ->get(db_prefix() . 'item_modifiers')->result_array();
        foreach ($modifiers as $m) {
            $this->db->where('item_modifier_id', $m['id'])->delete(db_prefix() . 'item_modifier_options');
        }
        $this->db->where('pos_item_id', (string)$id)->delete(db_prefix() . 'item_modifiers');

        $this->db->where('id', $id)
            ->where('can_be_sold', 'can_be_sold')
            ->where('can_be_manufacturing', 'can_be_manufacturing')
            ->delete(db_prefix() . 'items');

        if ($this->db->affected_rows() > 0) {
            return ['success' => true, 'message' => 'Product deleted.'];
        }

        return ['success' => false, 'message' => 'Product not found.'];
    }
}
