<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Manager App REST API
 *
 * Base URL: /pos/manager/{method}
 *
 * Auth: POST /pos/manager/login  → returns Bearer token
 *       All other routes require: Authorization: Bearer {token}
 *
 * Roles:
 *   administrator — admin=1 in tblstaff — sees all warehouses, warehouse_id optional
 *   manager       — admin=0             — restricted to warehouses in pos_api_tokens,
 *                                         warehouse_id required on every data endpoint
 */
class Manager extends App_Controller
{
    private static $public_methods = ['login'];

    protected $_auth_staff  = null; // row from pos_manager_sessions + staff join
    protected $_auth_role   = null; // 'administrator' | 'manager'
    protected $_auth_wh_ids = null; // array<int> for manager, null for administrator (= all)

    public function __construct()
    {
        parent::__construct();
        $this->load->model('pos/pos_model');

        $method = $this->router->fetch_method();
        if (!in_array($method, self::$public_methods)) {
            $this->_verify_token();
        }
    }

    // =========================================================================
    // Auth
    // =========================================================================

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = trim($body['email']    ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            $this->_error('email and password are required');
        }

        $staff = $this->db
            ->select('staffid, firstname, lastname, email, password, admin, active')
            ->where('email', $email)
            ->get(db_prefix() . 'staff')
            ->row();

        if (!$staff || !app_hasher()->CheckPassword($password, $staff->password)) {
            $this->_error('Invalid email or password', 401);
        }

        if ((int) $staff->active === 0) {
            $this->_error('Account is inactive', 403);
        }

        $is_admin   = (int) $staff->admin === 1;
        $role       = $is_admin ? 'administrator' : 'manager';
        $warehouses = $is_admin
            ? $this->_all_warehouses()
            : $this->_staff_warehouses($staff->staffid);

        if (!$is_admin && empty($warehouses)) {
            $this->_error('No warehouse access configured for this account. Ask an admin to assign a store token.', 403);
        }

        $token = bin2hex(random_bytes(32));
        $this->db->insert(db_prefix() . 'pos_manager_sessions', [
            'staff_id'   => (int) $staff->staffid,
            'token'      => $token,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $this->_json([
            'token'      => $token,
            'role'       => $role,
            'staff'      => [
                'id'        => (int) $staff->staffid,
                'full_name' => trim($staff->firstname . ' ' . $staff->lastname),
                'email'     => $staff->email,
            ],
            'warehouses' => $warehouses,
        ]);
    }

    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }
        $this->db->where('token', $this->_get_bearer())->delete(db_prefix() . 'pos_manager_sessions');
        $this->_json(['logged_out' => true]);
    }

    public function me()
    {
        $staff = $this->db
            ->select('staffid, firstname, lastname, email, admin')
            ->where('staffid', $this->_auth_staff->staff_id)
            ->get(db_prefix() . 'staff')
            ->row();

        $warehouses = $this->_auth_role === 'administrator'
            ? $this->_all_warehouses()
            : $this->_staff_warehouses($this->_auth_staff->staff_id);

        $this->_json([
            'staff' => [
                'id'        => (int) $staff->staffid,
                'full_name' => trim($staff->firstname . ' ' . $staff->lastname),
                'email'     => $staff->email,
            ],
            'role'       => $this->_auth_role,
            'warehouses' => $warehouses,
        ]);
    }

    // =========================================================================
    // Warehouses
    // =========================================================================

    public function warehouses()
    {
        $list = $this->_auth_role === 'administrator'
            ? $this->_all_warehouses()
            : $this->_staff_warehouses($this->_auth_staff->staff_id);

        $this->_json($list);
    }

    // =========================================================================
    // Dashboard
    // =========================================================================

    public function dashboard_summary()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-d');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');

        $data = $this->pos_model->get_dashboard_summary($date_from, $date_to, $wh);

        if ($wh) {
            $w = $this->db->select('warehouse_name')->where('warehouse_id', $wh)
                ->get(db_prefix() . 'warehouse')->row_array();
            $data['warehouse_id']   = (int) $wh;
            $data['warehouse_name'] = $w['warehouse_name'] ?? '';
        } else {
            $data['warehouse_id']   = null;
            $data['warehouse_name'] = 'All Outlets';
        }

        $data['date_from'] = $date_from;
        $data['date_to']   = $date_to;

        $this->_json($data);
    }

    public function dashboard_daily_trend()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-01');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');

        $this->_json($this->pos_model->get_dashboard_daily_trend($date_from, $date_to, $wh));
    }

    public function dashboard_hourly()
    {
        $wh   = $this->_resolve_warehouse();
        $date = $this->input->get('date') ?: date('Y-m-d');

        $this->_json($this->pos_model->get_dashboard_hourly($date, $date, $wh));
    }

    public function dashboard_payment_breakdown()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-d');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');

        $rows  = $this->pos_model->get_dashboard_payments($date_from, $date_to, $wh);
        $total = array_sum(array_column($rows, 'total'));

        foreach ($rows as &$row) {
            $row['percentage'] = $total > 0
                ? round((float) $row['total'] / $total * 100, 1)
                : 0;
        }

        $this->_json($rows);
    }

    public function dashboard_category_breakdown()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-d');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');

        $this->_json($this->pos_model->get_dashboard_category_breakdown($date_from, $date_to, $wh));
    }

    public function dashboard_recent_shifts()
    {
        $wh    = $this->_resolve_warehouse();
        $limit = max(1, min(20, (int) ($this->input->get('limit') ?: 5)));

        $this->_json($this->pos_model->get_dashboard_recent_shifts($wh, $limit));
    }

    // =========================================================================
    // Sales / Receipts
    // =========================================================================

    public function sales()
    {
        $wh = $this->_resolve_warehouse();

        $result = $this->pos_model->get_transactions([
            'warehouse_id' => $wh,
            'date_from'    => $this->input->get('date_from'),
            'date_to'      => $this->input->get('date_to'),
            'search'       => $this->input->get('q'),
            'shift_id'     => $this->input->get('shift_id'),
            'page'         => $this->input->get('page')     ?: 1,
            'limit'        => $this->input->get('per_page') ?: 20,
        ]);

        $this->_json($result);
    }

    public function sale($receipt_number)
    {
        $receipt = $this->pos_model->get_receipt($receipt_number);

        if (!$receipt) {
            $this->_not_found('Receipt');
        }

        if (!$this->_can_access_warehouse((int) $receipt['warehouse_id'])) {
            $this->_error('Forbidden', 403);
        }

        $this->_json($receipt);
    }

    // =========================================================================
    // Shifts
    // =========================================================================

    public function shifts()
    {
        $wh = $this->_resolve_warehouse();

        $result = $this->pos_model->get_shifts([
            'warehouse_id' => $wh,
            'date_from'    => $this->input->get('date_from'),
            'date_to'      => $this->input->get('date_to'),
            'status'       => $this->input->get('status'),
            'page'         => $this->input->get('page')     ?: 1,
            'limit'        => $this->input->get('per_page') ?: 20,
            'sort'         => $this->input->get('sort'),
            'dir'          => $this->input->get('dir'),
        ]);

        $this->_json($result);
    }

    public function shift($id)
    {
        $report = $this->pos_model->get_shift_report((int) $id);

        if (!$report) {
            $this->_not_found('Shift');
        }

        if (!$this->_can_access_warehouse((int) $report['shift']['warehouse_id'])) {
            $this->_error('Forbidden', 403);
        }

        $this->_json($report);
    }

    // =========================================================================
    // Reports
    // =========================================================================

    public function reports_top_products()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-d');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');
        $limit     = max(1, min(50, (int) ($this->input->get('limit') ?: 10)));

        $this->_json($this->pos_model->get_dashboard_top_products($date_from, $date_to, $wh, $limit));
    }

    public function reports_product_sales()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-01');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');
        $page      = max(1, (int) ($this->input->get('page')     ?: 1));
        $per_page  = max(1, min(100, (int) ($this->input->get('per_page') ?: 20)));
        $offset    = ($page - 1) * $per_page;

        $from      = $date_from . ' 00:00:00';
        $to        = $date_to   . ' 23:59:59';
        $wh_clause = $wh ? 'AND r.warehouse_id = ' . (int) $wh : '';

        $total = (int) $this->db->query("
            SELECT COUNT(DISTINCT li.item_id) AS total
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? {$wh_clause}
        ", [$from, $to])->row()->total;

        $rows = $this->db->query("
            SELECT li.item_id,
                   li.item_name,
                   COALESCE(li.category_name, 'Uncategorised') AS category,
                   COALESCE(SUM(li.quantity),      0) AS qty_sold,
                   COALESCE(SUM(li.gross_total),   0) AS revenue,
                   COALESCE(SUM(li.total_discount),0) AS total_discount,
                   COALESCE(SUM(li.total_money),   0) AS net_revenue
            FROM `" . db_prefix() . "pos_receipt_line_items` li
            JOIN `" . db_prefix() . "pos_receipts` r ON r.id = li.receipt_id
            WHERE r.receipt_type = 'SALE' AND r.cancelled_at IS NULL
              AND r.receipt_date BETWEEN ? AND ? {$wh_clause}
            GROUP BY li.item_id, li.item_name, li.category_name
            ORDER BY net_revenue DESC
            LIMIT {$per_page} OFFSET {$offset}
        ", [$from, $to])->result_array();

        $this->_json([
            'meta' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => max(1, (int) ceil($total / $per_page)),
                'date_from'   => $date_from,
                'date_to'     => $date_to,
            ],
            'data' => $rows,
        ]);
    }

    public function reports_staff_performance()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-01');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');

        $from      = $date_from . ' 00:00:00';
        $to        = $date_to   . ' 23:59:59';
        $wh_clause = $wh ? 'AND r.warehouse_id = ' . (int) $wh : '';

        $rows = $this->db->query("
            SELECT
                e.id   AS cashier_id,
                e.name AS cashier_name,
                COALESCE(SUM(CASE WHEN r.receipt_type = 'SALE'   AND r.cancelled_at IS NULL THEN r.total_money ELSE 0 END), 0) AS total_sales,
                COALESCE(COUNT(CASE WHEN r.receipt_type = 'SALE'   AND r.cancelled_at IS NULL THEN 1 END), 0)                  AS transaction_count,
                COALESCE(SUM(CASE WHEN r.receipt_type = 'REFUND' AND r.cancelled_at IS NULL THEN r.total_money ELSE 0 END), 0) AS total_refunds,
                COALESCE(COUNT(CASE WHEN r.receipt_type = 'REFUND' AND r.cancelled_at IS NULL THEN 1 END), 0)                  AS refund_count
            FROM `" . db_prefix() . "pos_employees` e
            LEFT JOIN `" . db_prefix() . "pos_receipts` r
                ON r.employee_id = e.id
               AND r.receipt_date BETWEEN ? AND ? {$wh_clause}
            GROUP BY e.id, e.name
            ORDER BY total_sales DESC
        ", [$from, $to])->result_array();

        foreach ($rows as &$row) {
            $count = (int) $row['transaction_count'];
            $row['avg_transaction'] = $count > 0
                ? round((float) $row['total_sales'] / $count, 2)
                : 0;
        }

        $this->_json([
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'data'      => $rows,
        ]);
    }

    public function reports_discount_breakdown()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-d');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');

        $this->_json($this->pos_model->get_dashboard_discount_breakdown($date_from, $date_to, $wh));
    }

    public function reports_promotion_performance()
    {
        $wh        = $this->_resolve_warehouse();
        $date_from = $this->input->get('date_from') ?: date('Y-m-d');
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');

        $this->_json($this->pos_model->get_dashboard_promotion_performance($date_from, $date_to, $wh));
    }

    // =========================================================================
    // Inventory
    // =========================================================================

    public function inventory()
    {
        $wh       = $this->_resolve_warehouse();
        $search   = trim($this->input->get('search') ?? '');
        $low_only = (int) $this->input->get('low_stock_only');
        $page     = max(1, (int) ($this->input->get('page')     ?: 1));
        $per_page = max(1, min(100, (int) ($this->input->get('per_page') ?: 20)));
        $offset   = ($page - 1) * $per_page;

        $pfx        = db_prefix();
        $wh_clause  = $wh ? "AND im.warehouse_id = " . (int) $wh : '';
        $low_clause = $low_only
            ? "AND im.inventory_number <= COALESCE(icm.inventory_number_min, 0)"
            : '';

        $search_clause = '';
        if ($search !== '') {
            $s             = $this->db->escape_str($search);
            $search_clause = "AND (it.sku_name LIKE '%{$s}%' OR it.sku_code LIKE '%{$s}%')";
        }

        $total = (int) $this->db->query("
            SELECT COUNT(*) AS cnt
            FROM `{$pfx}inventory_manage` im
            JOIN `{$pfx}items` it ON it.id = im.commodity_id
            LEFT JOIN `{$pfx}inventory_commodity_min` icm ON icm.commodity_id = im.commodity_id
            WHERE 1=1 {$wh_clause} {$search_clause} {$low_clause}
        ")->row()->cnt;

        $rows = $this->db->query("
            SELECT
                im.id,
                it.id                         AS product_id,
                it.sku_name                   AS product_name,
                it.sku_code                   AS sku,
                ig.name                       AS category,
                u.unit_name                   AS unit,
                im.warehouse_id,
                w.warehouse_name,
                im.inventory_number           AS quantity_on_hand,
                icm.inventory_number_min      AS reorder_level,
                (im.inventory_number <= COALESCE(icm.inventory_number_min, -1)
                 AND icm.inventory_number_min IS NOT NULL) AS is_low_stock
            FROM `{$pfx}inventory_manage` im
            JOIN `{$pfx}items` it              ON it.id = im.commodity_id
            JOIN `{$pfx}warehouse` w           ON w.warehouse_id = im.warehouse_id
            LEFT JOIN `{$pfx}items_groups` ig  ON ig.id = it.group_id
            LEFT JOIN `{$pfx}ware_unit_type` u ON u.unit_type_id = it.unit_id
            LEFT JOIN `{$pfx}inventory_commodity_min` icm ON icm.commodity_id = im.commodity_id
            WHERE 1=1 {$wh_clause} {$search_clause} {$low_clause}
            ORDER BY it.sku_name ASC
            LIMIT {$per_page} OFFSET {$offset}
        ")->result_array();

        foreach ($rows as &$row) {
            $row['is_low_stock'] = (bool) $row['is_low_stock'];
        }

        $this->_json([
            'meta' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => max(1, (int) ceil($total / $per_page)),
            ],
            'data' => $rows,
        ]);
    }

    public function inventory_transactions()
    {
        $wh         = $this->_resolve_warehouse();
        $product_id = (int) ($this->input->get('product_id') ?: 0);
        $date_from  = $this->input->get('date_from');
        $date_to    = $this->input->get('date_to');
        $page       = max(1, (int) ($this->input->get('page')     ?: 1));
        $per_page   = max(1, min(100, (int) ($this->input->get('per_page') ?: 20)));

        $filters = [];
        if ($wh)         $filters['warehouse_ids']  = [(int) $wh];
        if ($product_id) $filters['commodity_ids']  = [$product_id];
        if ($date_from)  $filters['from']            = $date_from;
        if ($date_to)    $filters['to']              = $date_to;

        $this->load->model('warehouse/warehouse_model');

        $total = $this->warehouse_model->count_api_goods_transaction_history($filters);
        $rows  = $this->warehouse_model->get_api_goods_transaction_history(
            $filters,
            $per_page,
            ($page - 1) * $per_page
        );

        $this->_json([
            'meta' => [
                'total'       => (int) $total,
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => max(1, (int) ceil($total / $per_page)),
            ],
            'data' => $rows,
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function _verify_token()
    {
        $token = $this->_get_bearer();

        if (empty($token)) {
            $this->_error('Unauthorized', 401);
        }

        $row = $this->db
            ->select('s.staff_id, s.token, st.firstname, st.lastname, st.admin, st.active')
            ->from(db_prefix() . 'pos_manager_sessions s')
            ->join(db_prefix() . 'staff st', 'st.staffid = s.staff_id')
            ->where('s.token', $token)
            ->where('s.expires_at >', date('Y-m-d H:i:s'))
            ->get()
            ->row();

        if (!$row) {
            $this->_error('Unauthorized', 401);
        }

        if ((int) $row->active === 0) {
            $this->_error('Account is inactive', 403);
        }

        $this->_auth_staff  = $row;
        $this->_auth_role   = (int) $row->admin === 1 ? 'administrator' : 'manager';
        $this->_auth_wh_ids = $this->_auth_role === 'administrator'
            ? null
            : array_map('intval', array_column($this->_staff_warehouses($row->staff_id), 'id'));
    }

    private function _get_bearer()
    {
        $headers = $this->input->request_headers();
        $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        return trim(str_replace('Bearer', '', $auth));
    }

    /**
     * Resolve and gate the warehouse_id query param.
     * Returns int|null (null only valid for administrators).
     */
    private function _resolve_warehouse()
    {
        $wh = (int) ($this->input->get('warehouse_id') ?: 0) ?: null;

        if (!$wh && $this->_auth_role !== 'administrator') {
            $this->_error('warehouse_id is required', 400);
        }

        if ($wh && !$this->_can_access_warehouse($wh)) {
            $this->_error('Forbidden', 403);
        }

        return $wh;
    }

    private function _can_access_warehouse(int $wh_id): bool
    {
        if ($this->_auth_role === 'administrator') return true;
        return in_array($wh_id, $this->_auth_wh_ids ?? [], true);
    }

    private function _all_warehouses(): array
    {
        return $this->db
            ->select('warehouse_id as id, warehouse_name as name, warehouse_code as code, warehouse_address as address, note as description')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')
            ->result_array();
    }

    private function _staff_warehouses(int $staff_id): array
    {
        return $this->db
            ->select('w.warehouse_id as id, w.warehouse_name as name, w.warehouse_code as code, w.warehouse_address as address')
            ->from(db_prefix() . 'pos_api_tokens t')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = t.warehouse_id')
            ->where('t.staff_id', $staff_id)
            ->where('w.display', 1)
            ->group_by('w.warehouse_id')
            ->order_by('w.warehouse_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function _json($data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    private function _error(string $message, int $status = 400)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message, 'code' => $status]);
        exit;
    }

    private function _not_found(string $entity)
    {
        $this->_error($entity . ' not found', 404);
    }
}
