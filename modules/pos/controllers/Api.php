<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api extends App_Controller
{
    // Routes that do not require a Bearer token
    private static $public_methods = ['loyalty_register', 'login'];

    // Populated by _verify_token() — holds the authenticated staff session row
    protected $_auth_staff = null;

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
            return;
        }

        $data     = json_decode(file_get_contents('php://input'), true);
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->_error('email and password are required');
            return;
        }

        $staff = $this->db
            ->select('staffid, firstname, lastname, email, password, passcode, active')
            ->where('email', $email)
            ->get(db_prefix() . 'staff')
            ->row();

        if (!$staff || !app_hasher()->CheckPassword($password, $staff->password)) {
            $this->_error('Invalid email or password', 401);
            return;
        }

        if ((int) $staff->active === 0) {
            $this->_error('Account is inactive', 403);
            return;
        }

        $tokens = $this->pos_model->get_tokens_for_staff($staff->staffid);

        if (empty($tokens)) {
            $this->_error('No active POS access configured for this account. Ask an admin to assign a store token.', 403);
            return;
        }

        $this->_json([
            'staff' => [
                'id'        => (int) $staff->staffid,
                'full_name' => trim($staff->firstname . ' ' . $staff->lastname),
                'email'     => $staff->email,
            ],
            'access' => array_map(function ($t) {
                return [
                    'token'     => $t['token'],
                    'label'     => $t['warehouse_name'],
                    'warehouse' => [
                        'id'      => (int) $t['warehouse_id'],
                        'name'    => $t['warehouse_name'],
                        'code'    => $t['warehouse_code'],
                        'address' => $t['warehouse_address'],
                    ],
                ];
            }, $tokens),
        ]);
    }

    // =========================================================================
    // Me
    // =========================================================================

    public function me()
    {
        $staff = $this->db
            ->select('staffid, firstname, lastname, email, passcode')
            ->where('staffid', $this->_auth_staff->staff_id)
            ->get(db_prefix() . 'staff')
            ->row();

        $this->_json([
            'staff' => [
                'id'        => (int) $staff->staffid,
                'full_name' => trim($staff->firstname . ' ' . $staff->lastname),
                'email'     => $staff->email,
                'passcode'  => $staff->passcode,
            ],
            'warehouse' => [
                'id'      => (int) $this->_auth_staff->warehouse_id,
                'name'    => $this->_auth_staff->warehouse_name,
                'code'    => $this->_auth_staff->warehouse_code,
                'address' => $this->_auth_staff->warehouse_address,
            ],
        ]);
    }

    // =========================================================================
    // Re-authentication
    // =========================================================================

    public function verify_passcode()
    {
        $data     = json_decode(file_get_contents('php://input'), true);
        $passcode = $data['passcode'] ?? '';

        if (empty($passcode)) {
            $this->_error('passcode is required');
            return;
        }

        $staff = $this->db
            ->select('passcode')
            ->where('staffid', $this->_auth_staff->staff_id)
            ->get(db_prefix() . 'staff')
            ->row();

        if (!$staff || !app_hasher()->CheckPassword($passcode, $staff->passcode)) {
            $this->_error('Invalid passcode', 401);
            return;
        }

        $this->_json(['verified' => true]);
    }

    // =========================================================================
    // Stores
    // =========================================================================

    public function stores()
    {
        $this->_json($this->pos_model->get_stores());
    }

    public function store($id)
    {
        $store = $this->pos_model->get_store($id);
        $store ? $this->_json($store) : $this->_not_found('Store');
    }

    // =========================================================================
    // Categories / Item groups
    // =========================================================================

    public function categories()
    {
        $this->_json($this->pos_model->get_categories());
    }

    public function item_groups()
    {
        $this->_json($this->pos_model->get_item_groups());
    }

    public function sub_groups()
    {
        $group_id = $this->input->get('group_id');
        $group_id = is_numeric($group_id) ? (int) $group_id : null;
        $this->_json($this->pos_model->get_sub_groups($group_id));
    }

    // =========================================================================
    // Items
    // =========================================================================

    public function items()
    {
        $can_be_sold        = $this->input->get('can_be_sold');
        $can_be_manufacturing = $this->input->get('can_be_manufacturing');

        $filters = [
            'q'                   => $this->input->get('q'),
            'group_id'            => $this->input->get('group_id'),
            'warehouse_id'        => $this->input->get('warehouse_id'),
            'page'                => $this->input->get('page'),
            'limit'               => $this->input->get('limit'),
            'can_be_sold'         => $can_be_sold !== null ? $can_be_sold : 'can_be_sold',
            'can_be_manufacturing' => $can_be_manufacturing !== null ? $can_be_manufacturing : 'can_be_manufacturing',
        ];
        $this->_json($this->pos_model->get_items($filters));
    }

    public function item($id)
    {
        $item = $this->pos_model->get_item($id);
        $item ? $this->_json($item) : $this->_not_found('Item');
    }

    public function item_by_barcode($code)
    {
        $item = $this->pos_model->get_item_by_barcode($code);
        $item ? $this->_json($item) : $this->_not_found('Item');
    }

    // =========================================================================
    // Employees
    // =========================================================================

    public function employees()
    {
        $this->_json($this->pos_model->get_employees());
    }

    public function employee_login()
    {
        $pin      = $this->input->post('pin');
        $store_id = $this->input->post('warehouse_id');
        $employee = $this->pos_model->get_employee_by_pin($pin, $store_id);
        $employee ? $this->_json($employee) : $this->_error('Invalid PIN', 401);
    }

    // =========================================================================
    // Modifiers / Payment types / Payment modes / Taxes
    // =========================================================================

    public function modifiers()
    {
        $this->_json($this->pos_model->get_modifiers());
    }

    public function payment_types()
    {
        $this->_json($this->pos_model->get_payment_types($this->input->get('warehouse_id')));
    }

    public function payment_modes()
    {
        $this->_json($this->pos_model->get_payment_modes());
    }

    public function taxes()
    {
        $this->_json($this->pos_model->get_taxes());
    }

    // =========================================================================
    // Bundles
    // =========================================================================

    public function bundles()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'GET') {
            $this->_json($this->pos_model->get_bundles());
        } elseif ($method === 'POST') {
            $data   = json_decode(file_get_contents('php://input'), true);
            $result = $this->pos_model->create_bundle($data);
            $result ? $this->_json(['id' => $result], 201) : $this->_error('Failed to create bundle');
        } else {
            $this->_error('Method not allowed', 405);
        }
    }

    public function bundle($id)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'PUT') {
            $data   = json_decode(file_get_contents('php://input'), true);
            $result = $this->pos_model->update_bundle($id, $data);
            $result ? $this->_json(['success' => true]) : $this->_error('Failed to update bundle');
        } elseif ($method === 'DELETE') {
            $result = $this->pos_model->delete_bundle($id);
            $result ? $this->_json(['success' => true]) : $this->_not_found('Bundle');
        } else {
            $this->_error('Method not allowed', 405);
        }
    }

    // =========================================================================
    // Promotions
    // =========================================================================

    public function promotions()
    {
        $this->_json($this->pos_model->get_promotions($this->input->get('warehouse_id')));
    }

    public function promotions_validate()
    {
        $data     = json_decode(file_get_contents('php://input'), true);
        $store_id = $data['warehouse_id'] ?? null;
        $items    = $data['items'] ?? [];
        $subtotal = (float)($data['subtotal'] ?? 0);
        $result   = $this->pos_model->validate_promotions($store_id, $items, $subtotal, $data['voucher_code'] ?? null);
        $this->_json($result);
    }

    // =========================================================================
    // Shifts
    // =========================================================================

    public function shifts_open()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $data['warehouse_id'] = $this->_auth_staff->warehouse_id;

        $existing = $this->pos_model->get_open_shift_for_warehouse($data['warehouse_id']);
        if ($existing) {
            $this->_error('A shift is already open for this warehouse', 409);
            return;
        }
        $shift = $this->pos_model->open_shift($data);
        $shift ? $this->_json($shift, 201) : $this->_error('Failed to open shift');
    }

    public function shift($id)
    {
        $shift = $this->pos_model->get_shift($id);
        $shift ? $this->_json($shift) : $this->_not_found('Shift');
    }

    public function shift_current()
    {
        $employee_id = $this->input->get('employee_id');
        if (!$employee_id) {
            $this->_error('employee_id is required');
            return;
        }
        $shift = $this->pos_model->get_open_shift_for_employee($employee_id);
        $shift ? $this->_json($shift) : $this->_not_found('Open shift');
    }

    public function shift_cash_movement($id)
    {
        $data   = json_decode(file_get_contents('php://input'), true);
        $shift  = $this->pos_model->get_shift($id);
        if (!$shift) { $this->_not_found('Shift'); return; }
        if ($shift['status'] !== 'open') { $this->_error('Shift is not open', 409); return; }
        if (!in_array($data['type'] ?? '', ['pay_in', 'pay_out'])) {
            $this->_error('type must be pay_in or pay_out');
            return;
        }
        $result = $this->pos_model->add_cash_movement($id, $data);
        $result ? $this->_json(['id' => $result], 201) : $this->_error('Failed to record cash movement');
    }

    public function shift_close($id)
    {
        $data   = json_decode(file_get_contents('php://input'), true);
        $result = $this->pos_model->close_shift($id, $data);
        if ($result === false) {
            $this->_error('Shift not found or already closed', 409);
            return;
        }
        $this->_json($result);
    }

    public function shift_report($id)
    {
        $report = $this->pos_model->get_shift_report($id);
        $report ? $this->_json($report) : $this->_not_found('Shift');
    }

    // =========================================================================
    // Customers
    // =========================================================================

    public function customers_search()
    {
        $q = $this->input->get('q');
        if (empty($q)) { $this->_error('q is required'); return; }
        $this->_json($this->pos_model->search_customers($q));
    }

    public function customer($id)
    {
        $customer = $this->pos_model->get_customer($id);
        $customer ? $this->_json($customer) : $this->_not_found('Customer');
    }

    public function customers_create()
    {
        $data   = json_decode(file_get_contents('php://input'), true);
        $result = $this->pos_model->create_customer($data);
        $result ? $this->_json($result, 201) : $this->_error('Failed to create customer');
    }

    // =========================================================================
    // Loyalty
    // =========================================================================

    public function loyalty_balance($customer_id)
    {
        $balance = $this->pos_model->get_loyalty_balance($customer_id);
        $balance ? $this->_json($balance) : $this->_not_found('Loyalty customer');
    }

    public function loyalty_earn()
    {
        $data        = json_decode(file_get_contents('php://input'), true);
        $customer_id = $data['customer_id'] ?? null;
        $receipt_id  = $data['receipt_id'] ?? null;
        $amount      = $data['amount_spent'] ?? null;
        if (!$customer_id || !$receipt_id || $amount === null) {
            $this->_error('customer_id, receipt_id and amount_spent are required');
            return;
        }
        $points = $this->pos_model->earn_points($customer_id, $receipt_id, $amount);
        $points !== false ? $this->_json(['points_earned' => $points]) : $this->_error('Failed to earn points');
    }

    public function loyalty_redeem()
    {
        $data        = json_decode(file_get_contents('php://input'), true);
        $customer_id = $data['customer_id'] ?? null;
        $receipt_id  = $data['receipt_id'] ?? null;
        $points      = $data['points'] ?? null;
        if (!$customer_id || !$receipt_id || $points === null) {
            $this->_error('customer_id, receipt_id and points are required');
            return;
        }
        $result = $this->pos_model->redeem_points($customer_id, $receipt_id, $points);
        $result !== false ? $this->_json($result) : $this->_error('Insufficient points or customer not found', 409);
    }

    // Public — no token required. GET: resolve a QR token. POST: register/earn.
    public function loyalty_register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $token = $this->input->get('token');
            if (empty($token)) { $this->_error('token is required'); return; }

            $lc = $this->pos_model->get_loyalty_customer_by_qr($token);
            if ($lc) { $this->_json(['type' => 'loyalty_customer', 'data' => $lc]); return; }

            $receipt = $this->pos_model->get_receipt_by_cashback_token($token);
            if ($receipt) { $this->_json(['type' => 'cashback_receipt', 'data' => $receipt]); return; }

            $this->_not_found('Token');
        } else {
            $data  = json_decode(file_get_contents('php://input'), true);
            $token = $data['qr_token'] ?? null;
            $name  = $data['name'] ?? null;
            $phone = $data['phone'] ?? null;
            $email = $data['email'] ?? null;

            if (empty($token) || empty($name)) {
                $this->_error('qr_token and name are required');
                return;
            }

            $result = $this->pos_model->loyalty_register_from_qr($token, $name, $phone, $email);
            $result ? $this->_json($result, 201) : $this->_error('Registration failed');
        }
    }

    // =========================================================================
    // Receipts
    // =========================================================================

    public function receipts()
    {
        $receipts = $this->pos_model->get_receipts(
            $this->input->get('warehouse_id'),
            $this->input->get('date_from'),
            $this->input->get('date_to')
        );
        $this->_json($receipts);
    }

    public function receipt($receipt_number)
    {
        $receipt = $this->pos_model->get_receipt($receipt_number);
        $receipt ? $this->_json($receipt) : $this->_not_found('Receipt');
    }

    public function create_receipt()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['shift_id'])) {
            $this->_error('shift_id is required');
            return;
        }
        $shift = $this->pos_model->get_shift((int) $data['shift_id']);
        if (!$shift || $shift['status'] !== 'open') {
            $this->_error('No open shift found for the given shift_id', 409);
            return;
        }
        $result = $this->pos_model->create_receipt($data);
        $result ? $this->_json($result, 201) : $this->_error('Failed to create receipt');
    }

    public function create_refund()
    {
        $data   = json_decode(file_get_contents('php://input'), true);
        $result = $this->pos_model->create_refund($data);
        $result ? $this->_json(['id' => $result], 201) : $this->_error('Failed to create refund');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function _verify_token()
    {
        $headers = $this->input->request_headers();
        $auth    = $headers['Authorization'] ?? '';
        $token   = trim(str_replace('Bearer', '', $auth));

        if (empty($token)) {
            $this->_error('Unauthorized', 401);
            exit;
        }

        $row = $this->pos_model->verify_api_token($token);

        if (!$row) {
            $this->_error('Unauthorized', 401);
            exit;
        }

        $this->_auth_staff = $row; // exposes ->staff_id, ->store_id, ->store_name
    }

    private function _json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    private function _error($message, $status = 400)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }

    private function _not_found($entity)
    {
        $this->_error($entity . ' not found', 404);
    }
}
