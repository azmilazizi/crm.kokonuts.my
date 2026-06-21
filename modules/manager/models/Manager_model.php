<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Manager_model extends App_Model
{
    // =========================================================================
    // Sessions
    // =========================================================================

    public function create_session(int $staff_id, int $expire_days = 30): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db->insert(db_prefix() . 'pos_manager_sessions', [
            'staff_id'   => $staff_id,
            'token'      => $token,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$expire_days} days")),
        ]);
        return $token;
    }

    public function verify_session(string $token): ?object
    {
        $row = $this->db
            ->where('token', $token)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get(db_prefix() . 'pos_manager_sessions')
            ->row();
        return $row ?: null;
    }

    public function delete_session(string $token): void
    {
        $this->db->where('token', $token)->delete(db_prefix() . 'pos_manager_sessions');
    }

    // =========================================================================
    // Warehouses
    // =========================================================================

    public function get_accessible_warehouses(int $staff_id, bool $is_admin): array
    {
        $q = $this->db
            ->select('warehouse_id as id, warehouse_name as name, warehouse_code as code, warehouse_address as address, note as description')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC');

        if (!$is_admin) {
            $ids = $this->get_allowed_warehouse_ids($staff_id);
            if (empty($ids)) return [];
            $q->where_in('warehouse_id', $ids);
        }

        return $q->get(db_prefix() . 'warehouse')->result_array();
    }

    public function get_allowed_warehouse_ids(int $staff_id): array
    {
        $row = $this->db
            ->select('warehouse_ids')
            ->where('staffid', $staff_id)
            ->get(db_prefix() . 'staff')
            ->row();
        return json_decode($row->warehouse_ids ?? '[]', true) ?: [];
    }

    // =========================================================================
    // Sales / Receipts
    // =========================================================================

    public function get_receipts(array $f): array
    {
        $p    = db_prefix();
        $from = $f['date_from'] . ' 00:00:00';
        $to   = $f['date_to']   . ' 23:59:59';
        $wh   = $f['warehouse_id'] ? 'AND r.warehouse_id = ' . (int) $f['warehouse_id'] : '';
        $type = !empty($f['receipt_type']) ? "AND r.receipt_type = '" . $this->db->escape_str($f['receipt_type']) . "'" : '';
        $stat = '';
        if ($f['status'] === 'active')    $stat = 'AND r.cancelled_at IS NULL';
        if ($f['status'] === 'cancelled') $stat = 'AND r.cancelled_at IS NOT NULL';

        $offset = ($f['page'] - 1) * $f['per_page'];

        $count_sql = "SELECT COUNT(*) AS total
            FROM `{$p}pos_receipts` r
            WHERE r.receipt_date BETWEEN ? AND ? $wh $type $stat";

        $total = (int) $this->db->query($count_sql, [$from, $to])->row()->total;

        $sql = "SELECT r.id, r.receipt_number, r.receipt_type, r.warehouse_id,
                    w.warehouse_name,
                    r.total_money, r.total_discount, r.total_tax,
                    (r.total_money - r.total_discount) AS subtotal,
                    (SELECT p.payment_name FROM `{$p}pos_receipt_payments` p WHERE p.receipt_id = r.id ORDER BY p.id ASC LIMIT 1) AS payment_method,
                    r.receipt_date AS created_at,
                    r.cancelled_at, r.shift_id,
                    e.name AS cashier_name
                FROM `{$p}pos_receipts` r
                LEFT JOIN `{$p}warehouse` w ON w.warehouse_id = r.warehouse_id
                LEFT JOIN `{$p}pos_employees` e ON e.id = r.employee_id
                WHERE r.receipt_date BETWEEN ? AND ? $wh $type $stat
                ORDER BY r.receipt_date DESC
                LIMIT ? OFFSET ?";

        $rows = $this->db->query($sql, [$from, $to, $f['per_page'], $offset])->result_array();

        foreach ($rows as &$r) {
            $r['total_money']    = round((float) $r['total_money'],    2);
            $r['total_discount'] = round((float) $r['total_discount'], 2);
            $r['total_tax']      = round((float) $r['total_tax'],      2);
            $r['subtotal']       = round((float) $r['subtotal'],       2);
            $r['warehouse_id']   = (int) $r['warehouse_id'];
        }
        unset($r);

        return ['data' => $rows, 'total' => $total];
    }

    public function get_receipt_detail(string $receipt_number): ?array
    {
        $p = db_prefix();

        $r = $this->db->query(
            "SELECT r.*, w.warehouse_name,
                    e.name AS cashier_name
             FROM `{$p}pos_receipts` r
             LEFT JOIN `{$p}warehouse` w ON w.warehouse_id = r.warehouse_id
             LEFT JOIN `{$p}pos_employees` e ON e.id = r.employee_id
             WHERE r.receipt_number = ?",
            [$receipt_number]
        )->row_array();

        if (!$r) return null;

        $items = $this->db->query(
            "SELECT li.*, i.description AS product_name, i.sku_code AS sku
             FROM `{$p}pos_receipt_line_items` li
             LEFT JOIN `{$p}items` i ON i.id = li.item_id
             WHERE li.receipt_id = ?
             ORDER BY li.id ASC",
            [(int) $r['id']]
        )->result_array();

        $formatted_items = array_map(fn($li) => [
            'product_name' => $li['product_name'] ?? $li['item_name'] ?? '',
            'sku'          => $li['sku'] ?? null,
            'quantity'     => (float) ($li['quantity'] ?? 0),
            'unit_price'   => round((float) ($li['unit_price'] ?? 0), 2),
            'discount'     => round((float) ($li['total_discount'] ?? 0), 2),
            'tax'          => round((float) ($li['total_tax'] ?? 0), 2),
            'total_money'  => round((float) ($li['total_money'] ?? 0), 2),
            'modifiers'    => $li['modifier_names'] ? array_filter(explode(',', $li['modifier_names'])) : [],
        ], $items);

        return [
            'id'                      => (int) $r['id'],
            'receipt_number'          => $r['receipt_number'],
            'receipt_type'            => $r['receipt_type'],
            'warehouse_id'            => (int) $r['warehouse_id'],
            'warehouse_name'          => $r['warehouse_name'] ?? '',
            'subtotal'                => round((float) ($r['subtotal'] ?? $r['total_money'] - $r['total_discount']), 2),
            'total_discount'          => round((float) ($r['total_discount'] ?? 0), 2),
            'total_tax'               => round((float) ($r['total_tax'] ?? 0), 2),
            'total_money'             => round((float) ($r['total_money'] ?? 0), 2),
            'surcharge'               => round((float) ($r['surcharge'] ?? 0), 2),
            'payment_method'          => $r['payment_method'] ?? '',
            'note'                    => $r['note'] ?? null,
            'created_at'              => $r['receipt_date'] ?? null,
            'cancelled_at'            => $r['cancelled_at'] ?? null,
            'cashier_name'            => $r['cashier_name'] ?? null,
            'shift_id'                => isset($r['shift_id']) ? (int) $r['shift_id'] : null,
            'items'                   => $formatted_items,
            'original_receipt_number' => $r['original_receipt_number'] ?? null,
        ];
    }

    // =========================================================================
    // Shifts
    // =========================================================================

    public function get_shifts(array $f): array
    {
        $p    = db_prefix();
        $wh   = $f['warehouse_id'] ? 'AND s.warehouse_id = ' . (int) $f['warehouse_id'] : '';
        $st   = $f['status'] ? "AND s.status = '" . $this->db->escape_str($f['status']) . "'" : '';
        $from = '';
        $to   = '';
        if ($f['date_from']) $from = "AND s.opened_at >= '" . $this->db->escape_str($f['date_from']) . " 00:00:00'";
        if ($f['date_to'])   $to   = "AND s.opened_at <= '" . $this->db->escape_str($f['date_to'])   . " 23:59:59'";

        $offset = ($f['page'] - 1) * $f['per_page'];

        $total = (int) $this->db->query(
            "SELECT COUNT(*) AS total FROM `{$p}pos_shifts` s WHERE 1 $wh $st $from $to"
        )->row()->total;

        $rows = $this->db->query(
            "SELECT s.*, w.warehouse_name,
                    ob.name AS opened_by,
                    cb.name AS closed_by_name,
                    (SELECT COALESCE(SUM(r.total_money),0)
                     FROM `{$p}pos_receipts` r
                     WHERE r.shift_id = s.id AND r.receipt_type='SALE' AND r.cancelled_at IS NULL) AS total_sales,
                    (SELECT COALESCE(SUM(r.total_money),0)
                     FROM `{$p}pos_receipts` r
                     WHERE r.shift_id = s.id AND r.receipt_type='REFUND' AND r.cancelled_at IS NULL) AS total_refunds,
                    (SELECT COUNT(*)
                     FROM `{$p}pos_receipts` r
                     WHERE r.shift_id = s.id AND r.receipt_type='SALE' AND r.cancelled_at IS NULL) AS transaction_count
             FROM `{$p}pos_shifts` s
             LEFT JOIN `{$p}warehouse` w ON w.warehouse_id = s.warehouse_id
             LEFT JOIN `{$p}pos_employees` ob ON ob.id = s.employee_id
             LEFT JOIN `{$p}pos_employees` cb ON cb.id = s.closed_by_employee_id
             WHERE 1 $wh $st $from $to
             ORDER BY s.opened_at DESC
             LIMIT ? OFFSET ?",
            [$f['per_page'], $offset]
        )->result_array();

        $data = array_map(fn($s) => $this->_format_shift($s), $rows);
        return ['data' => $data, 'total' => $total];
    }

    public function get_shift_detail(int $id): ?array
    {
        $p = db_prefix();

        $s = $this->db->query(
            "SELECT s.*, w.warehouse_name,
                    ob.name AS opened_by,
                    cb.name AS closed_by_name
             FROM `{$p}pos_shifts` s
             LEFT JOIN `{$p}warehouse` w ON w.warehouse_id = s.warehouse_id
             LEFT JOIN `{$p}pos_employees` ob ON ob.id = s.employee_id
             LEFT JOIN `{$p}pos_employees` cb ON cb.id = s.closed_by_employee_id
             WHERE s.id = ?",
            [$id]
        )->row_array();

        if (!$s) return null;

        // Payment breakdown
        $breakdown = $this->db->query(
            "SELECT rp.payment_name AS payment_method, SUM(rp.money_amount) AS amount, COUNT(*) AS count
             FROM `{$p}pos_receipt_payments` rp
             JOIN `{$p}pos_receipts` r ON r.id = rp.receipt_id
             WHERE r.shift_id = ? AND r.cancelled_at IS NULL
             GROUP BY rp.payment_name",
            [$id]
        )->result_array();

        // Cash movements
        $movements = $this->db->query(
            "SELECT cm.type, cm.amount, cm.reason AS note, cm.created_at
             FROM `{$p}pos_shift_cash_movements` cm
             WHERE cm.shift_id = ?
             ORDER BY cm.created_at ASC",
            [$id]
        )->result_array();

        // Financials
        $fin = $this->db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_money ELSE 0 END),0) AS total_sales,
                COALESCE(SUM(CASE WHEN receipt_type='REFUND' AND cancelled_at IS NULL THEN total_money ELSE 0 END),0) AS total_refunds,
                COALESCE(COUNT(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN 1 END),0)  AS transaction_count,
                COALESCE(COUNT(CASE WHEN receipt_type='REFUND' AND cancelled_at IS NULL THEN 1 END),0)  AS refund_count,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_discount ELSE 0 END),0) AS total_discount,
                COALESCE(SUM(CASE WHEN receipt_type='SALE'   AND cancelled_at IS NULL THEN total_tax ELSE 0 END),0) AS total_tax
             FROM `{$p}pos_receipts`
             WHERE shift_id = ?",
            [$id]
        )->row_array();

        $net = (float) $fin['total_sales'] - (float) $fin['total_refunds'];

        return [
            'id'                => (int) $s['id'],
            'warehouse_id'      => (int) $s['warehouse_id'],
            'warehouse_name'    => $s['warehouse_name'] ?? '',
            'status'            => $s['status'],
            'opened_by'         => $s['opened_by'] ?? '',
            'closed_by'         => $s['closed_by_name'] ?? null,
            'opening_cash'      => round((float) ($s['opening_float'] ?? 0), 2),
            'closing_cash'      => isset($s['closing_float']) ? round((float) $s['closing_float'], 2) : null,
            'expected_cash'     => isset($s['expected_cash']) ? round((float) $s['expected_cash'], 2) : null,
            'cash_difference'   => isset($s['difference']) ? round((float) $s['difference'], 2) : null,
            'total_sales'       => round((float) $fin['total_sales'],    2),
            'total_refunds'     => round((float) $fin['total_refunds'],  2),
            'net_sales'         => round($net,                            2),
            'transaction_count' => (int) $fin['transaction_count'],
            'refund_count'      => (int) $fin['refund_count'],
            'total_discount'    => round((float) $fin['total_discount'],  2),
            'total_tax'         => round((float) $fin['total_tax'],       2),
            'opened_at'         => $s['opened_at'],
            'closed_at'         => $s['closed_at'] ?? null,
            'payment_breakdown' => array_map(fn($b) => [
                'payment_method' => $b['payment_method'],
                'amount'         => round((float) $b['amount'], 2),
                'count'          => (int) $b['count'],
            ], $breakdown),
            'cash_movements'    => array_map(fn($m) => [
                'type'       => $m['type'],
                'amount'     => round((float) $m['amount'], 2),
                'note'       => $m['note'] ?? null,
                'created_at' => $m['created_at'],
            ], $movements),
        ];
    }

    // =========================================================================
    // Reports
    // =========================================================================

    public function get_top_products(
        string $from,
        string $to,
        ?int $wid,
        string $sort_by = 'revenue',
        int $limit = 10
    ): array {
        $p  = db_prefix();
        $wh = $wid ? 'AND r.warehouse_id = ' . $wid : '';
        $order        = $sort_by === 'quantity' ? 'quantity_sold DESC' : 'revenue DESC';
        $window_order = $sort_by === 'quantity' ? 'SUM(li.quantity) DESC' : 'SUM(li.total_money) DESC';

        $rows = $this->db->query(
            "SELECT
                ROW_NUMBER() OVER (ORDER BY $window_order) AS `rank`,
                COALESCE(i.description, li.item_name) AS product_name,
                i.sku_code AS sku,
                c.name AS category,
                SUM(li.quantity)       AS quantity_sold,
                SUM(li.total_money)    AS revenue,
                SUM(li.total_discount)       AS total_discount
             FROM `{$p}pos_receipt_line_items` li
             JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
             LEFT JOIN `{$p}items` i ON i.id = li.item_id
             LEFT JOIN `{$p}pos_categories` c ON c.id = li.category_id
             WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
               AND r.receipt_date BETWEEN ? AND ? $wh
             GROUP BY li.item_id, i.description, li.item_name, i.sku_code, c.name
             ORDER BY $order
             LIMIT ?",
            [$from . ' 00:00:00', $to . ' 23:59:59', $limit]
        )->result_array();

        return array_map(fn($r) => [
            'rank'           => (int) $r['rank'],
            'product_name'   => $r['product_name'] ?? '',
            'sku'            => $r['sku'] ?? null,
            'category'       => $r['category'] ?? null,
            'quantity_sold'  => round((float) $r['quantity_sold'], 3),
            'revenue'        => round((float) $r['revenue'], 2),
            'total_discount' => round((float) $r['total_discount'], 2),
        ], $rows);
    }

    public function get_product_sales(array $f): array
    {
        $p    = db_prefix();
        $wh   = $f['warehouse_id'] ? 'AND r.warehouse_id = ' . (int) $f['warehouse_id'] : '';
        $cat  = $f['category_id']  ? 'AND li.category_id = ' . (int) $f['category_id']  : '';
        $from = $f['date_from'] . ' 00:00:00';
        $to   = $f['date_to']   . ' 23:59:59';
        $off  = ($f['page'] - 1) * $f['per_page'];

        $total = (int) $this->db->query(
            "SELECT COUNT(DISTINCT COALESCE(li.item_id, li.item_name)) AS total
             FROM `{$p}pos_receipt_line_items` li
             JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
             LEFT JOIN `{$p}items` i ON i.id = li.item_id
             WHERE r.receipt_type='SALE' AND r.cancelled_at IS NULL
               AND r.receipt_date BETWEEN ? AND ? $wh $cat",
            [$from, $to]
        )->row()->total;

        $rows = $this->db->query(
            "SELECT
                COALESCE(i.description, li.item_name) AS product_name,
                i.sku_code AS sku,
                c.name AS category,
                SUM(li.quantity)    AS quantity_sold,
                SUM(li.total_money) AS revenue,
                SUM(li.total_discount)    AS total_discount,
                SUM(li.total_money) - SUM(li.total_discount) AS net_revenue
             FROM `{$p}pos_receipt_line_items` li
             JOIN `{$p}pos_receipts` r ON r.id = li.receipt_id
             LEFT JOIN `{$p}items` i ON i.id = li.item_id
             LEFT JOIN `{$p}pos_categories` c ON c.id = li.category_id
             WHERE r.receipt_type='SALE' AND r.cancelled_at IS NULL
               AND r.receipt_date BETWEEN ? AND ? $wh $cat
             GROUP BY li.item_id, i.description, li.item_name, i.sku_code, c.name
             ORDER BY revenue DESC
             LIMIT ? OFFSET ?",
            [$from, $to, $f['per_page'], $off]
        )->result_array();

        $data = array_map(fn($r) => [
            'product_name'   => $r['product_name'] ?? '',
            'sku'            => $r['sku'] ?? null,
            'category'       => $r['category'] ?? null,
            'quantity_sold'  => round((float) $r['quantity_sold'], 3),
            'revenue'        => round((float) $r['revenue'], 2),
            'total_discount' => round((float) $r['total_discount'], 2),
            'net_revenue'    => round((float) $r['net_revenue'], 2),
        ], $rows);

        return ['data' => $data, 'total' => $total];
    }

    public function get_staff_performance(string $from, string $to, ?int $wid): array
    {
        $p  = db_prefix();
        $wh = $wid ? 'AND r.warehouse_id = ' . $wid : '';

        return $this->db->query(
            "SELECT
                r.staff_id AS cashier_id,
                CONCAT(s.firstname,' ',s.lastname) AS cashier_name,
                SUM(CASE WHEN r.receipt_type='SALE'   AND r.cancelled_at IS NULL THEN r.total_money ELSE 0 END) AS total_sales,
                COUNT(CASE WHEN r.receipt_type='SALE'   AND r.cancelled_at IS NULL THEN 1 END)  AS transaction_count,
                SUM(CASE WHEN r.receipt_type='REFUND' AND r.cancelled_at IS NULL THEN r.total_money ELSE 0 END) AS total_refunds,
                COUNT(CASE WHEN r.receipt_type='REFUND' AND r.cancelled_at IS NULL THEN 1 END)  AS refund_count,
                COUNT(DISTINCT r.shift_id) AS shifts_count
             FROM `{$p}pos_receipts` r
             LEFT JOIN `{$p}staff` s ON s.staffid = r.staff_id
             WHERE r.receipt_date BETWEEN ? AND ? $wh
             GROUP BY r.staff_id, s.firstname, s.lastname
             ORDER BY total_sales DESC",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        )->result_array() ?: [];
    }

    // =========================================================================
    // Inventory
    // =========================================================================

    public function get_inventory(array $f): array
    {
        $p    = db_prefix();
        $wh   = $f['warehouse_id'] ? 'AND s.warehouse_id = ' . (int) $f['warehouse_id'] : '';
        $cat  = $f['category_id']  ? 'AND i.category_id  = ' . (int) $f['category_id']  : '';
        $srch = '';
        if (!empty($f['search'])) {
            $q    = $this->db->escape_str($f['search']);
            $srch = "AND (i.description LIKE '%{$q}%' OR i.sku LIKE '%{$q}%')";
        }
        $low  = $f['low_stock_only'] ? 'AND s.quantity <= COALESCE(i.reorder_level, 0)' : '';
        $off  = ($f['page'] - 1) * $f['per_page'];

        $total = (int) $this->db->query(
            "SELECT COUNT(*) AS total
             FROM `{$p}warehouse_stock` s
             JOIN `{$p}items` i ON i.id = s.item_id
             WHERE 1 $wh $cat $srch $low"
        )->row()->total;

        $rows = $this->db->query(
            "SELECT i.id AS product_id, i.description AS product_name, i.sku_code AS sku,
                    c.name AS category, i.unit,
                    s.warehouse_id, w.warehouse_name,
                    s.quantity AS quantity_on_hand,
                    i.reorder_level,
                    (s.quantity <= COALESCE(i.reorder_level, -1)) AS is_low_stock
             FROM `{$p}warehouse_stock` s
             JOIN `{$p}items` i ON i.id = s.item_id
             LEFT JOIN `{$p}pos_categories` c ON c.id = i.category_id
             LEFT JOIN `{$p}warehouse` w ON w.warehouse_id = s.warehouse_id
             WHERE 1 $wh $cat $srch $low
             ORDER BY i.description ASC
             LIMIT ? OFFSET ?",
            [$f['per_page'], $off]
        )->result_array();

        $data = array_map(fn($r) => [
            'product_id'       => (int) $r['product_id'],
            'product_name'     => $r['product_name'] ?? '',
            'sku'              => $r['sku'] ?? null,
            'category'         => $r['category'] ?? null,
            'unit'             => $r['unit'] ?? null,
            'warehouse_id'     => (int) $r['warehouse_id'],
            'warehouse_name'   => $r['warehouse_name'] ?? '',
            'quantity_on_hand' => round((float) $r['quantity_on_hand'], 3),
            'reorder_level'    => isset($r['reorder_level']) ? round((float) $r['reorder_level'], 3) : null,
            'is_low_stock'     => (bool) $r['is_low_stock'],
        ], $rows);

        return ['data' => $data, 'total' => $total];
    }

    public function get_inventory_transactions(array $f): array
    {
        $p    = db_prefix();
        $wh   = $f['warehouse_id'] ? 'AND t.warehouse_id = ' . (int) $f['warehouse_id'] : '';
        $pid  = $f['product_id']   ? 'AND t.item_id = '      . (int) $f['product_id']   : '';
        $from = $f['date_from'] ? "AND t.created_at >= '" . $this->db->escape_str($f['date_from']) . " 00:00:00'" : '';
        $to   = $f['date_to']   ? "AND t.created_at <= '" . $this->db->escape_str($f['date_to'])   . " 23:59:59'" : '';
        $off  = ($f['page'] - 1) * $f['per_page'];

        $total = (int) $this->db->query(
            "SELECT COUNT(*) AS total FROM `{$p}warehouse_stock_transactions` t WHERE 1 $wh $pid $from $to"
        )->row()->total;

        $rows = $this->db->query(
            "SELECT t.id, i.description AS product_name, i.sku_code AS sku,
                    w.warehouse_name, t.transaction_type, t.quantity,
                    t.note, t.created_at,
                    CONCAT(s.firstname,' ',s.lastname) AS created_by
             FROM `{$p}warehouse_stock_transactions` t
             LEFT JOIN `{$p}items` i ON i.id = t.item_id
             LEFT JOIN `{$p}warehouse` w ON w.warehouse_id = t.warehouse_id
             LEFT JOIN `{$p}staff` s ON s.staffid = t.created_by_staff_id
             WHERE 1 $wh $pid $from $to
             ORDER BY t.created_at DESC
             LIMIT ? OFFSET ?",
            [$f['per_page'], $off]
        )->result_array();

        $data = array_map(fn($r) => [
            'id'               => (int) $r['id'],
            'product_name'     => $r['product_name'] ?? '',
            'sku'              => $r['sku'] ?? null,
            'warehouse_name'   => $r['warehouse_name'] ?? '',
            'transaction_type' => $r['transaction_type'] ?? '',
            'quantity'         => round((float) $r['quantity'], 3),
            'note'             => $r['note'] ?? null,
            'created_at'       => $r['created_at'] ?? null,
            'created_by'       => trim($r['created_by'] ?? '') ?: null,
        ], $rows);

        return ['data' => $data, 'total' => $total];
    }

    // =========================================================================
    // Internal
    // =========================================================================

    private function _format_shift(array $s): array
    {
        $net = (float) ($s['total_sales'] ?? 0) - (float) ($s['total_refunds'] ?? 0);
        return [
            'id'                => (int) $s['id'],
            'warehouse_id'      => (int) $s['warehouse_id'],
            'warehouse_name'    => $s['warehouse_name'] ?? '',
            'status'            => $s['status'],
            'opened_by'         => $s['opened_by'] ?? '',
            'closed_by'         => $s['closed_by_name'] ?? null,
            'opening_cash'      => round((float) ($s['opening_float'] ?? 0), 2),
            'closing_cash'      => isset($s['closing_float']) ? round((float) $s['closing_float'], 2) : null,
            'expected_cash'     => isset($s['expected_cash']) ? round((float) $s['expected_cash'], 2) : null,
            'cash_difference'   => isset($s['difference']) ? round((float) $s['difference'], 2) : null,
            'total_sales'       => round((float) ($s['total_sales'] ?? 0), 2),
            'total_refunds'     => round((float) ($s['total_refunds'] ?? 0), 2),
            'net_sales'         => round($net, 2),
            'transaction_count' => (int) ($s['transaction_count'] ?? 0),
            'opened_at'         => $s['opened_at'] ?? null,
            'closed_at'         => $s['closed_at'] ?? null,
        ];
    }
}
