<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api extends App_Controller
{
    // Methods that skip token auth
    private static $public_methods = ['auth_login'];

    /** @var object|null Populated by _verify_token() */
    protected $_auth = null;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('manager/manager_model');
        $this->load->model('pos/pos_model');

        if (!in_array($this->router->fetch_method(), self::$public_methods)) {
            $this->_verify_token();
        }
    }

    // =========================================================================
    // Auth
    // =========================================================================

    public function auth_login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->_error('email and password are required', 422);
        }

        $staff = $this->db
            ->select('staffid, firstname, lastname, email, password, active, admin, warehouse_ids')
            ->where('email', $email)
            ->get(db_prefix() . 'staff')
            ->row();

        if (!$staff || !app_hasher()->CheckPassword($password, $staff->password)) {
            $this->_error('Invalid email or password', 401);
        }

        if ((int) $staff->active === 0) {
            $this->_error('Account is inactive', 403);
        }

        $role       = (int) $staff->admin === 1 ? 'administrator' : 'manager';
        $warehouses = $this->_get_accessible_warehouses($staff);

        if ($role === 'manager' && empty($warehouses)) {
            $this->_error('No warehouses assigned to this account. Contact your administrator.', 403);
        }

        $token = $this->manager_model->create_session((int) $staff->staffid);

        $this->_json([
            'token' => $token,
            'staff' => [
                'id'        => (int) $staff->staffid,
                'full_name' => trim($staff->firstname . ' ' . $staff->lastname),
                'email'     => $staff->email,
            ],
            'role'       => $role,
            'warehouses' => $warehouses,
        ]);
    }

    public function auth_logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }
        $this->manager_model->delete_session($this->_auth->token);
        $this->_json(['success' => true]);
    }

    public function auth_me()
    {
        $staff = $this->db
            ->select('staffid, firstname, lastname, email, active, admin, warehouse_ids')
            ->where('staffid', $this->_auth->staff_id)
            ->get(db_prefix() . 'staff')
            ->row();

        if (!$staff || (int) $staff->active === 0) {
            $this->_error('Account inactive', 403);
        }

        $role       = (int) $staff->admin === 1 ? 'administrator' : 'manager';
        $warehouses = $this->_get_accessible_warehouses($staff);

        $this->_json([
            'staff' => [
                'id'        => (int) $staff->staffid,
                'full_name' => trim($staff->firstname . ' ' . $staff->lastname),
                'email'     => $staff->email,
            ],
            'role'       => $role,
            'warehouses' => $warehouses,
        ]);
    }

    // =========================================================================
    // Warehouses
    // =========================================================================

    public function warehouses()
    {
        $data = $this->manager_model->get_accessible_warehouses(
            $this->_auth->staff_id,
            $this->_auth->is_admin
        );
        $this->_success_list($data);
    }

    // =========================================================================
    // Dashboard
    // =========================================================================

    public function dashboard_summary()
    {
        [$wid, $wname] = $this->_resolve_warehouse();
        $from          = $this->_require_date('date_from');
        $to            = $this->_require_date('date_to');
        $compare       = filter_var($this->input->get('compare'), FILTER_VALIDATE_BOOLEAN);

        $row     = $this->pos_model->get_dashboard_summary($from, $to, $wid);
        $current = $this->_format_summary_row($row);

        $response = [
            'success'        => true,
            'warehouse_id'   => $wid,
            'warehouse_name' => $wname,
            'date_from'      => $from,
            'date_to'        => $to,
        ] + $current;

        if ($compare) {
            [$prev_from, $prev_to] = $this->_previous_period($from, $to);
            $prev_row              = $this->pos_model->get_dashboard_summary($prev_from, $prev_to, $wid);
            $previous              = $this->_format_summary_row($prev_row);

            $response['previous_period'] = ['date_from' => $prev_from, 'date_to' => $prev_to] + $previous;
            $response['changes']         = $this->_compute_changes($current, $previous);
        }

        $this->_json($response);
    }

    private function _format_summary_row(array $row): array
    {
        $sales = (float) $row['total_sales'];
        $count = (int) $row['transaction_count'];

        return [
            'total_sales'               => round($sales, 2),
            'total_refunds'             => round((float) $row['total_refunds'],   2),
            'net_sales'                 => round($sales - (float) $row['total_refunds'], 2),
            'total_discounts'           => round((float) $row['total_discounts'],  2),
            'total_tax'                 => round((float) $row['total_tax'],        2),
            'transaction_count'         => $count,
            'refund_count'              => (int) $row['refund_count'],
            'average_transaction_value' => round($count > 0 ? $sales / $count : 0, 2),
        ];
    }

    private function _previous_period(string $from, string $to): array
    {
        $start    = new DateTime($from);
        $end      = new DateTime($to);
        $days     = (int) $start->diff($end)->days + 1;

        $prev_to  = (clone $start)->modify('-1 day');
        $prev_from = (clone $prev_to)->modify('-' . ($days - 1) . ' days');

        return [$prev_from->format('Y-m-d'), $prev_to->format('Y-m-d')];
    }

    private function _compute_changes(array $current, array $previous): array
    {
        $keys = [
            'total_sales', 'total_refunds', 'net_sales', 'total_discounts',
            'total_tax', 'transaction_count', 'refund_count', 'average_transaction_value',
        ];

        $changes = [];
        foreach ($keys as $key) {
            $cur  = (float) ($current[$key]  ?? 0);
            $prev = (float) ($previous[$key] ?? 0);
            $abs  = round($cur - $prev, 2);
            $pct  = $prev != 0 ? round(($abs / abs($prev)) * 100, 2) : null;

            $changes[$key] = ['absolute' => $abs, 'percent' => $pct];
        }

        return $changes;
    }

    public function dashboard_daily_trend()
    {
        [$wid] = $this->_resolve_warehouse();
        $from  = $this->_require_date('date_from');
        $to    = $this->_require_date('date_to');

        $rows = $this->pos_model->get_dashboard_daily_trend($from, $to, $wid);

        $this->_json([
            'success' => true,
            'data'    => array_map(fn($r) => [
                'date'              => $r['date'],
                'revenue'           => round((float) $r['revenue'], 2),
                'transaction_count' => (int) $r['transaction_count'],
            ], $rows),
        ]);
    }

    public function dashboard_hourly_trend()
    {
        [$wid] = $this->_resolve_warehouse();
        $date  = $this->_require_date('date');

        $rows = $this->pos_model->get_dashboard_hourly($date, $date, $wid);

        // Fill all 24 hours
        $by_hour = [];
        foreach ($rows as $r) {
            $by_hour[(int) $r['hour']] = $r;
        }

        $data = [];
        for ($h = 0; $h < 24; $h++) {
            $data[] = [
                'hour'              => $h,
                'revenue'           => round((float) ($by_hour[$h]['revenue'] ?? 0), 2),
                'transaction_count' => (int) ($by_hour[$h]['transaction_count'] ?? 0),
            ];
        }

        $this->_json(['success' => true, 'data' => $data]);
    }

    public function dashboard_payment_breakdown()
    {
        [$wid] = $this->_resolve_warehouse();
        $from  = $this->_require_date('date_from');
        $to    = $this->_require_date('date_to');

        $rows  = $this->pos_model->get_dashboard_payments($from, $to, $wid);
        $total = array_sum(array_column($rows, 'amount'));

        $data = array_map(fn($r) => [
            'payment_method'    => $r['payment_method'],
            'amount'            => round((float) $r['amount'], 2),
            'transaction_count' => (int) $r['transaction_count'],
            'percentage'        => $total > 0 ? round((float) $r['amount'] / $total * 100, 2) : 0,
        ], $rows);

        $this->_json(['success' => true, 'data' => $data]);
    }

    public function dashboard_recent_shifts()
    {
        [$wid] = $this->_resolve_warehouse();
        $limit = min((int) ($this->input->get('limit') ?: 5), 20);
        $from  = $this->input->get('date_from') ?: null;
        $to    = $this->input->get('date_to') ?: null;

        $rows = $this->pos_model->get_dashboard_recent_shifts($wid, $limit, $from, $to);

        $this->_json([
            'success' => true,
            'data'    => array_map([$this, '_format_shift_summary'], $rows),
        ]);
    }

    // =========================================================================
    // Sales
    // =========================================================================

    public function sales()
    {
        [$wid]        = $this->_resolve_warehouse();
        $from         = $this->_require_date('date_from');
        $to           = $this->_require_date('date_to');
        [$page, $per] = $this->_pagination();
        $type         = $this->input->get('receipt_type') ?: null;
        $status       = $this->input->get('status') ?: 'active';

        $result = $this->manager_model->get_receipts([
            'warehouse_id'   => $wid,
            'date_from'      => $from,
            'date_to'        => $to,
            'receipt_type'   => $type,
            'status'         => $status,
            'page'           => $page,
            'per_page'       => $per,
        ]);

        $this->_paginated($result['data'], $result['total'], $page, $per);
    }

    public function sale($receipt_number)
    {
        $row = $this->manager_model->get_receipt_detail($receipt_number);

        if (!$row) {
            $this->_error('Receipt not found', 404);
        }

        // Enforce warehouse access for managers
        if (!$this->_auth->is_admin) {
            $allowed = $this->manager_model->get_allowed_warehouse_ids($this->_auth->staff_id);
            if (!in_array((int) $row['warehouse_id'], $allowed)) {
                $this->_error('Forbidden', 403);
            }
        }

        $this->_success_item($row);
    }

    // =========================================================================
    // Shifts
    // =========================================================================

    public function shifts()
    {
        [$wid]        = $this->_resolve_warehouse();
        [$page, $per] = $this->_pagination();
        $from         = $this->input->get('date_from') ?: null;
        $to           = $this->input->get('date_to') ?: null;
        $status       = $this->input->get('status') ?: null;

        $result = $this->manager_model->get_shifts([
            'warehouse_id' => $wid,
            'date_from'    => $from,
            'date_to'      => $to,
            'status'       => $status,
            'page'         => $page,
            'per_page'     => $per,
        ]);

        $this->_paginated($result['data'], $result['total'], $page, $per);
    }

    public function shift($id)
    {
        $row = $this->manager_model->get_shift_detail((int) $id);

        if (!$row) {
            $this->_error('Shift not found', 404);
        }

        if (!$this->_auth->is_admin) {
            $allowed = $this->manager_model->get_allowed_warehouse_ids($this->_auth->staff_id);
            if (!in_array((int) $row['warehouse_id'], $allowed)) {
                $this->_error('Forbidden', 403);
            }
        }

        $this->_success_item($row);
    }

    // =========================================================================
    // Push notifications — FCM token registration
    // =========================================================================

    public function fcm_token()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $token  = trim($body['token'] ?? '');

        if (empty($token)) {
            $this->_error('token is required');
            return;
        }

        if ($method === 'POST') {
            $exists = (int) $this->db
                ->where('fcm_token', $token)
                ->count_all_results(db_prefix() . 'pos_manager_fcm_tokens');

            if (!$exists) {
                $this->db->insert(db_prefix() . 'pos_manager_fcm_tokens', [
                    'staff_id'   => $this->_auth->staff_id,
                    'fcm_token'  => $token,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $this->_json(['success' => true, 'data' => ['registered' => true]]);

        } elseif ($method === 'DELETE') {
            $this->db->where('fcm_token', $token)
                     ->delete(db_prefix() . 'pos_manager_fcm_tokens');
            $this->_json(['success' => true, 'data' => ['removed' => true]]);

        } else {
            $this->_error('Method not allowed', 405);
        }
    }

    public function notification_preferences()
    {
        $method   = $_SERVER['REQUEST_METHOD'];
        $staff_id = $this->_auth->staff_id;

        if ($method === 'GET') {
            // Return all warehouses accessible to this user with their current pref
            $warehouses = $this->_get_accessible_warehouses($this->db
                ->select('staffid, admin, warehouse_ids')
                ->where('staffid', $staff_id)
                ->get(db_prefix() . 'staff')->row());

            $prefs = $this->db
                ->select('warehouse_id, enabled')
                ->where('staff_id', $staff_id)
                ->get(db_prefix() . 'pos_manager_sale_notif_prefs')
                ->result_array();
            $pref_map = [];
            foreach ($prefs as $p) {
                $pref_map[(int) $p['warehouse_id']] = (bool) $p['enabled'];
            }

            $out = [];
            foreach ($warehouses as $wh) {
                $wid       = (int) $wh['id'];
                $out[] = [
                    'warehouse_id'   => $wid,
                    'warehouse_name' => $wh['name'],
                    // default true when no pref row exists
                    'sales_notify'   => array_key_exists($wid, $pref_map) ? $pref_map[$wid] : true,
                ];
            }

            $this->_json(['success' => true, 'data' => $out]);

        } elseif ($method === 'PUT') {
            $body  = json_decode(file_get_contents('php://input'), true) ?? [];
            $items = $body['preferences'] ?? [];

            if (!is_array($items) || empty($items)) {
                $this->_error('preferences array is required');
                return;
            }

            // Validate warehouse access
            $accessible = array_column($this->_get_accessible_warehouses($this->db
                ->select('staffid, admin, warehouse_ids')
                ->where('staffid', $staff_id)
                ->get(db_prefix() . 'staff')->row()), 'id');
            $accessible = array_map('intval', $accessible);

            $now = date('Y-m-d H:i:s');
            foreach ($items as $item) {
                $wid     = (int) ($item['warehouse_id'] ?? 0);
                $enabled = isset($item['sales_notify']) ? (int) (bool) $item['sales_notify'] : 1;

                if (!$wid || !in_array($wid, $accessible, true)) continue;

                $exists = (int) $this->db
                    ->where('staff_id', $staff_id)
                    ->where('warehouse_id', $wid)
                    ->count_all_results(db_prefix() . 'pos_manager_sale_notif_prefs');

                if ($exists) {
                    $this->db
                        ->where('staff_id', $staff_id)
                        ->where('warehouse_id', $wid)
                        ->update(db_prefix() . 'pos_manager_sale_notif_prefs', [
                            'enabled'    => $enabled,
                            'updated_at' => $now,
                        ]);
                } else {
                    $this->db->insert(db_prefix() . 'pos_manager_sale_notif_prefs', [
                        'staff_id'     => $staff_id,
                        'warehouse_id' => $wid,
                        'enabled'      => $enabled,
                        'updated_at'   => $now,
                    ]);
                }
            }

            $this->_json(['success' => true, 'data' => ['updated' => true]]);

        } else {
            $this->_error('Method not allowed', 405);
        }
    }

    // =========================================================================
    // Reports
    // =========================================================================

    public function reports_top_products()
    {
        [$wid]   = $this->_resolve_warehouse();
        $from    = $this->_require_date('date_from');
        $to      = $this->_require_date('date_to');
        $sort_by = in_array($this->input->get('sort_by'), ['revenue', 'quantity'])
            ? $this->input->get('sort_by')
            : 'revenue';
        $limit   = min((int) ($this->input->get('limit') ?: 10), 50);

        $rows = $this->manager_model->get_top_products($from, $to, $wid, $sort_by, $limit);

        $this->_json([
            'success'      => true,
            'date_from'    => $from,
            'date_to'      => $to,
            'warehouse_id' => $wid,
            'data'         => $rows,
        ]);
    }

    public function reports_product_sales()
    {
        [$wid]        = $this->_resolve_warehouse();
        $from         = $this->_require_date('date_from');
        $to           = $this->_require_date('date_to');
        [$page, $per] = $this->_pagination();
        $category_id  = $this->input->get('category_id') ? (int) $this->input->get('category_id') : null;

        $result = $this->manager_model->get_product_sales([
            'warehouse_id' => $wid,
            'date_from'    => $from,
            'date_to'      => $to,
            'category_id'  => $category_id,
            'page'         => $page,
            'per_page'     => $per,
        ]);

        $this->_paginated($result['data'], $result['total'], $page, $per);
    }

    public function reports_staff_performance()
    {
        [$wid, $wname] = $this->_resolve_warehouse();
        $from          = $this->_require_date('date_from');
        $to            = $this->_require_date('date_to');

        $data = $this->manager_model->get_staff_performance($from, $to, $wid);

        $this->_json([
            'success'      => true,
            'date_from'    => $from,
            'date_to'      => $to,
            'warehouse_id' => $wid,
            'data'         => $data,
        ]);
    }

    // =========================================================================
    // Inventory
    // =========================================================================

    public function inventory()
    {
        [$wid]        = $this->_resolve_warehouse();
        [$page, $per] = $this->_pagination();
        $search       = $this->input->get('search') ?: null;
        $category_id  = $this->input->get('category_id') ? (int) $this->input->get('category_id') : null;
        $low_stock    = (bool) $this->input->get('low_stock_only');

        $result = $this->manager_model->get_inventory([
            'warehouse_id'  => $wid,
            'search'        => $search,
            'category_id'   => $category_id,
            'low_stock_only' => $low_stock,
            'page'          => $page,
            'per_page'      => $per,
        ]);

        $this->_paginated($result['data'], $result['total'], $page, $per);
    }

    public function inventory_transactions()
    {
        [$wid]        = $this->_resolve_warehouse();
        [$page, $per] = $this->_pagination();
        $product_id   = $this->input->get('product_id') ? (int) $this->input->get('product_id') : null;
        $from         = $this->input->get('date_from') ?: null;
        $to           = $this->input->get('date_to') ?: null;

        $result = $this->manager_model->get_inventory_transactions([
            'warehouse_id' => $wid,
            'product_id'   => $product_id,
            'date_from'    => $from,
            'date_to'      => $to,
            'page'         => $page,
            'per_page'     => $per,
        ]);

        $this->_paginated($result['data'], $result['total'], $page, $per);
    }

    // =========================================================================
    // Helpers — Auth
    // =========================================================================

    private function _verify_token()
    {
        $headers = $this->input->request_headers();
        $auth    = $headers['Authorization'] ?? '';
        $token   = trim(str_replace('Bearer', '', $auth));

        if (empty($token)) {
            $this->_error('Unauthorized', 401);
        }

        $session = $this->manager_model->verify_session($token);

        if (!$session) {
            $this->_error('Unauthorized', 401);
        }

        $staff = $this->db
            ->select('staffid, admin, active, warehouse_ids')
            ->where('staffid', $session->staff_id)
            ->get(db_prefix() . 'staff')
            ->row();

        if (!$staff || (int) $staff->active === 0) {
            $this->_error('Account inactive', 403);
        }

        $this->_auth = (object) [
            'staff_id'      => (int) $staff->staffid,
            'is_admin'      => (int) $staff->admin === 1,
            'warehouse_ids' => json_decode($staff->warehouse_ids ?? '[]', true) ?: [],
            'token'         => $token,
        ];
    }

    // =========================================================================
    // Helpers — Warehouse resolution
    // =========================================================================

    /**
     * Returns [warehouse_id_or_null, warehouse_name].
     * Enforces access control: managers must supply a warehouse_id they own.
     */
    private function _resolve_warehouse(): array
    {
        $wid = $this->input->get('warehouse_id')
            ? (int) $this->input->get('warehouse_id')
            : null;

        if ($this->_auth->is_admin) {
            // Admin may omit warehouse_id for all-outlet aggregation
            if ($wid === null) {
                return [null, 'All Outlets'];
            }
        } else {
            // Manager must supply warehouse_id
            if ($wid === null) {
                $this->_error('warehouse_id is required for managers', 403);
            }
            if (!in_array($wid, $this->_auth->warehouse_ids)) {
                $this->_error('Access denied for this warehouse', 403);
            }
        }

        $wh = $this->db
            ->select('warehouse_id, warehouse_name')
            ->where('warehouse_id', $wid)
            ->get(db_prefix() . 'warehouse')
            ->row();

        if (!$wh) {
            $this->_error('Warehouse not found', 404);
        }

        return [$wid, $wh->warehouse_name];
    }

    private function _get_accessible_warehouses(object $staff): array
    {
        $q = $this->db
            ->select('warehouse_id as id, warehouse_name as name, warehouse_code as code, warehouse_address as address')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC');

        if ((int) $staff->admin !== 1) {
            $ids = json_decode($staff->warehouse_ids ?? '[]', true) ?: [];
            if (empty($ids)) return [];
            $q->where_in('warehouse_id', $ids);
        }

        return $q->get(db_prefix() . 'warehouse')->result_array();
    }

    // =========================================================================
    // Helpers — Request
    // =========================================================================

    private function _require_date(string $param): string
    {
        $val = $this->input->get($param);
        if (empty($val) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $this->_error("$param is required (YYYY-MM-DD)", 400);
        }
        return $val;
    }

    private function _pagination(): array
    {
        $page = max(1, (int) ($this->input->get('page') ?: 1));
        $per  = min(max(1, (int) ($this->input->get('per_page') ?: 20)), 100);
        return [$page, $per];
    }

    // =========================================================================
    // Helpers — Response
    // =========================================================================

    private function _json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function _success_list(array $data): void
    {
        $this->_json(['success' => true, 'data' => $data]);
    }

    private function _success_item(array $data): void
    {
        $this->_json(['success' => true, 'data' => $data]);
    }

    private function _paginated(array $data, int $total, int $page, int $per): void
    {
        $this->_json([
            'success' => true,
            'meta'    => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $per,
                'total_pages' => (int) ceil($total / $per),
            ],
            'data'    => $data,
        ]);
    }

    private function _error(string $message, int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $message,
            'code'    => $status,
        ]);
        exit;
    }

    // =========================================================================
    // Formatters
    // =========================================================================

    private function _format_shift_summary(array $s): array
    {
        $total_sales   = (float) ($s['total_sales'] ?? 0);
        $total_refunds = (float) ($s['total_refunds'] ?? 0);
        return [
            'id'                => (int) $s['id'],
            'warehouse_id'      => (int) $s['warehouse_id'],
            'warehouse_name'    => $s['warehouse_name'] ?? '',
            'status'            => $s['status'],
            'opened_by'         => $s['opened_by_name'] ?? '',
            'closed_by'         => $s['closed_by_name'] ?? null,
            'opening_cash'      => round((float) ($s['opening_float'] ?? 0), 2),
            'closing_cash'      => isset($s['closing_float']) ? round((float) $s['closing_float'], 2) : null,
            'total_sales'       => round($total_sales, 2),
            'total_refunds'     => round($total_refunds, 2),
            'net_sales'         => round($total_sales - $total_refunds, 2),
            'transaction_count' => (int) ($s['transaction_count'] ?? 0),
            'opened_at'         => $s['opened_at'] ?? null,
            'closed_at'         => $s['closed_at'] ?? null,
        ];
    }
}
