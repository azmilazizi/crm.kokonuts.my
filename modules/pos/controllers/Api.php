<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api extends App_Controller
{
    // Routes that do not require a Bearer token
    private static $public_methods = ['loyalty_register', 'login', 'chip_webhook'];

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
        $shift = $this->pos_model->get_open_shift_for_warehouse($this->_auth_staff->warehouse_id);
        $shift ? $this->_json($shift) : $this->_json(null);
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

    public function loyalty_members()
    {
        $search = $this->input->get('q') ?? $this->input->get('search') ?? '';
        $limit  = min((int)($this->input->get('limit') ?? 50), 200);
        $page   = max(1, (int)($this->input->get('page') ?? 1));
        $offset = (int)($this->input->get('offset') ?? (($page - 1) * $limit));
        $this->_json($this->pos_model->get_loyalty_members($search, $limit, $offset));
    }

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
    // Receipt settings
    // =========================================================================

    public function receipt_settings()
    {
        $warehouse_id = (int) $this->_auth_staff->warehouse_id;
        $row          = $this->pos_model->get_receipt_settings($warehouse_id);

        $this->_json([
            'warehouse_id'   => $warehouse_id,
            'logo_url'       => !empty($row['logo']) ? base_url($row['logo']) : null,
            'company_name'   => $row['company_name']   ?? null,
            'company_reg_id' => $row['company_reg_id'] ?? null,
            'address'        => $row['address']        ?? null,
            'phone'          => $row['phone']          ?? null,
            'header'         => $row['header']         ?? null,
            'footer'         => $row['footer']         ?? null,
        ]);
    }

    // =========================================================================
    // Customer Facing Display settings
    // =========================================================================

    public function cfd_settings()
    {
        $warehouse_id = (int) $this->_auth_staff->warehouse_id;
        $settings     = $this->pos_model->get_cfd_settings($warehouse_id) ?: [];
        $items        = $this->pos_model->get_cfd_media_items($warehouse_id);

        $media = [];
        foreach ($items as $item) {
            $media[] = [
                'id'       => (int) $item['id'],
                'type'     => $item['media_type'],
                'url'      => base_url($item['file_path']),
                'duration' => $item['duration'] !== null ? (int) $item['duration'] : null,
            ];
        }

        $this->_json([
            'warehouse_id'   => $warehouse_id,
            'display_type'   => $settings['display_type']   ?? 'static_image',
            'slide_duration' => (int) ($settings['slide_duration'] ?? 5),
            'media_items'    => $media,
        ]);
    }

    // =========================================================================
    // Receipts
    // =========================================================================

    public function receipts()
    {
        $result = $this->pos_model->get_transactions([
            'warehouse_id' => (int) $this->_auth_staff->warehouse_id,
            'date_from'    => $this->input->get('date_from'),
            'date_to'      => $this->input->get('date_to'),
            'search'       => $this->input->get('q'),
            'shift_id'     => $this->input->get('shift_id'),
            'page'         => $this->input->get('page')  ?: 1,
            'limit'        => $this->input->get('limit') ?: 20,
        ]);
        $this->_json($result);
    }

    public function orders()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
            return;
        }

        $raw = json_decode(file_get_contents('php://input'), true);

        if (empty($raw['shift_id'])) {
            $this->_error('shift_id is required');
            return;
        }

        $shift = $this->pos_model->get_shift((int) $raw['shift_id']);
        if (!$shift || $shift['status'] !== 'open') {
            $this->_error('No open shift found for the given shift_id', 409);
            return;
        }

        $warehouse_id = (int) $this->_auth_staff->warehouse_id;

        // Map line items from Flutter shape → create_receipt shape
        $line_items = [];
        foreach ($raw['items'] ?? [] as $item) {
            $qty            = (float) ($item['qty']          ?? $item['quantity']   ?? 1);
            $unit_price     = (float) ($item['unit_price']   ?? 0);
            $line_discount  = (float) ($item['line_discount'] ?? $item['total_discount'] ?? 0);
            $modifiers      = $item['modifiers'] ?? [];

            $modifiers_price = 0;
            $modifier_ids    = [];
            $modifier_names  = [];
            foreach ($modifiers as $m) {
                $modifiers_price += (float) ($m['price'] ?? $m['price_adjustment'] ?? $m['amount'] ?? 0);
                $modifier_ids[]   = $m['id']   ?? $m['modifier_id'] ?? null;
                $modifier_names[] = $m['name'] ?? null;
            }
            $modifier_ids   = array_values(array_filter($modifier_ids));
            $modifier_names = array_values(array_filter($modifier_names));

            $gross_total = $qty * $unit_price;
            $total_money = $gross_total + $modifiers_price - $line_discount + (float) ($item['total_tax'] ?? 0);

            $line_items[] = [
                'item_id'         => $item['item_id'] ?? $item['id'] ?? 0,
                'item_name'       => $item['name']    ?? $item['item_name'] ?? '',
                'category_id'     => isset($item['category_id'])   ? (int)$item['category_id']   : null,
                'category_name'   => $item['category_name'] ?? null,
                'variant_id'      => $item['variant_id']   ?? null,
                'variant_name'    => $item['variant_name'] ?? null,
                'quantity'        => $qty,
                'unit_price'      => $unit_price,
                'cost'            => (float) ($item['cost'] ?? 0),
                'gross_total'     => $gross_total,
                'total_discount'  => $line_discount,
                'total_tax'       => (float) ($item['total_tax'] ?? 0),
                'total_money'     => $total_money,
                'modifier_ids'    => $modifier_ids,
                'modifier_names'  => $modifier_names,
                'modifiers_price' => $modifiers_price,
                'tax_ids'         => $item['tax_ids'] ?? [],
                'line_note'       => $item['line_note'] ?? $item['note'] ?? null,
                'promotion_id'    => isset($item['promotion_id'])   ? (int)$item['promotion_id']   : null,
                'discount_type'   => $item['discount_type'] ?? null,
            ];
        }

        // Map payment
        $payment_method  = strtolower(trim($raw['payment_method'] ?? 'cash'));
        $type_map        = ['cash' => 'CASH', 'card' => 'CARD', 'duitnow_qr' => 'EWALLET', 'ewallet' => 'EWALLET', 'online' => 'ONLINE'];
        $payment_type    = $type_map[$payment_method] ?? strtoupper($payment_method);
        $payment_type_id = (int) ($raw['payment_type_id'] ?? 0);
        $payment_name    = $raw['payment_name'] ?? ucfirst(str_replace('_', ' ', $payment_method));

        $payments = [[
            'payment_type_id' => $payment_type_id,
            'payment_name'    => $payment_name,
            'type'            => $payment_type,
            'money_amount'    => (float) ($raw['cash_received'] ?? $raw['total'] ?? 0),
            'cash_back'       => (float) ($raw['change'] ?? 0),
        ]];

        $result = $this->pos_model->create_receipt([
            'warehouse_id'        => $warehouse_id,
            'shift_id'            => (int) $raw['shift_id'],
            'employee_id'         => (int) ($raw['employee_id'] ?? 0) ?: null,
            'customer_id'         => (int) ($raw['customer_id'] ?? 0) ?: null,
            'loyalty_customer_id' => (int) ($raw['loyalty_customer_id'] ?? 0) ?: null,
            'queue_number'        => isset($raw['queue_number']) ? (int) $raw['queue_number'] : null,
            'dining_option'       => $raw['dining_option'] ?? null,
            'note'                => $raw['note']          ?? null,
            'source'              => 'POS',
            'subtotal'            => (float) ($raw['subtotal']     ?? 0),
            'total_discount'      => (float) ($raw['bill_discount'] ?? $raw['total_discount'] ?? 0),
            'total_tax'           => (float) ($raw['total_tax']    ?? 0),
            'tip'                 => (float) ($raw['tip']          ?? 0),
            'surcharge'           => (float) ($raw['surcharge']    ?? 0),
            'total_money'         => (float) ($raw['total']        ?? $raw['total_money'] ?? 0),
            'points_earned'       => (float) ($raw['points_earned']    ?? 0),
            'points_deducted'     => (float) ($raw['cashback_redeemed'] ?? $raw['points_deducted'] ?? 0),
            'line_items'          => $line_items,
            'payments'            => $payments,
        ]);

        if (!$result) {
            $this->_error('Failed to create order');
            return;
        }

        $row = $this->db->where('receipt_number', $result['receipt_number'])
            ->get(db_prefix() . 'pos_receipts')->row_array();

        $this->_json([
            'receipt_id'        => (int) $row['id'],
            'receipt_number'    => $result['receipt_number'],
            'cashback_qr_url'   => $result['cashback_qr_url'],
            'cashback_qr_token' => $result['cashback_qr_token'],
        ], 201);
    }

    public function customer_cashback_redeem($customer_id)
    {
        $data       = json_decode(file_get_contents('php://input'), true);
        $receipt_id = (int) ($data['receipt_id'] ?? 0);
        $amount     = (float) ($data['amount'] ?? $data['points'] ?? 0);

        if (!$receipt_id || $amount <= 0) {
            $this->_error('receipt_id and amount are required');
            return;
        }

        $result = $this->pos_model->redeem_points((int) $customer_id, $receipt_id, $amount);
        $result !== false
            ? $this->_json($result)
            : $this->_error('Insufficient balance or customer not found', 409);
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

    public function receipt_refund($receipt_id)
    {
        if ($this->input->method() !== 'post') { $this->_error('Method not allowed', 405); return; }
        $data               = json_decode(file_get_contents('php://input'), true) ?? [];
        $data['receipt_id'] = (int) $receipt_id;

        if (empty($data['amount']) || $data['amount'] <= 0) {
            $this->_error('amount is required and must be greater than 0');
            return;
        }

        $receipt = $this->pos_model->get_receipt_by_id((int) $receipt_id);
        if (!$receipt) { $this->_not_found('Receipt'); return; }
        if (!empty($receipt['cancelled_at'])) { $this->_error('Cannot refund a cancelled receipt', 409); return; }
        if ($receipt['receipt_type'] === 'REFUNDED') { $this->_error('Receipt has already been refunded', 409); return; }

        if (!empty($data['items'])) {
            if (!is_array($data['items'])) {
                $this->_error('items must be an array');
                return;
            }

            $line_items = $this->pos_model->get_receipt_line_items((int) $receipt_id);
            $valid_ids  = array_column($line_items, null, 'id');

            foreach ($data['items'] as $i => $item) {
                if (empty($item['line_item_id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
                    $this->_error('Each item requires line_item_id and quantity > 0');
                    return;
                }

                $lid = (int) $item['line_item_id'];
                if (!isset($valid_ids[$lid])) {
                    $this->_error('line_item_id ' . $lid . ' does not belong to this receipt');
                    return;
                }

                $original = $valid_ids[$lid];
                if ($item['quantity'] > $original['quantity']) {
                    $this->_error('Refund quantity for line_item_id ' . $lid . ' exceeds original quantity');
                    return;
                }

                $ratio = $item['quantity'] / $original['quantity'];
                $data['items'][$i] = [
                    'line_item_id' => $lid,
                    'quantity'     => (float) $item['quantity'],
                    'unit_price'   => (float) $original['unit_price'],
                    'total_money'  => round((float) $original['total_money'] * $ratio, 2),
                ];
            }
        }

        $result = $this->pos_model->create_refund($data);
        $result ? $this->_json(['id' => $result], 201) : $this->_error('Failed to create refund');
    }

    public function receipt_cancel($receipt_id)
    {
        if ($this->input->method() !== 'patch') { $this->_error('Method not allowed', 405); return; }
        $receipt = $this->pos_model->get_receipt_by_id((int) $receipt_id);
        if (!$receipt) { $this->_not_found('Receipt'); return; }
        if (!empty($receipt['cancelled_at'])) { $this->_error('Receipt is already cancelled', 409); return; }
        if ($receipt['receipt_type'] === 'REFUNDED') { $this->_error('Cannot cancel a refunded receipt', 409); return; }

        $ok = $this->pos_model->cancel_receipt((int) $receipt_id);
        $ok ? $this->_json(['message' => 'Receipt cancelled successfully']) : $this->_error('Failed to cancel receipt');
    }

    // =========================================================================
    // DuitNow QR (Chip-in)
    // =========================================================================

    public function duitnow_settings()
    {
        $settings = $this->pos_model->get_chip_settings();
        $this->_json([
            'enabled' => !empty($settings) && (int)$settings['active'] === 1,
        ]);
    }

    public function duitnow_create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
            return;
        }

        $settings = $this->pos_model->get_chip_settings();
        if (empty($settings) || !(int)$settings['active']) {
            $this->_error('DuitNow QR is not configured or disabled', 503);
            return;
        }

        $data      = json_decode(file_get_contents('php://input'), true) ?? [];
        $amount    = (float)($data['amount'] ?? 0);
        $reference = $data['reference'] ?? ('POS-' . time());

        if ($amount <= 0) {
            $this->_error('amount must be greater than 0');
            return;
        }

        // CHIP amounts are in cents
        $amount_cents = (int)round($amount * 100);

        $body = json_encode([
            'client'   => ['email' => 'customer@pos.local', 'full_name' => 'POS Customer'],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [[
                    'name'     => 'POS Order ' . $reference,
                    'price'    => $amount_cents,
                    'quantity' => 1,
                ]],
            ],
            'brand_id'                 => $settings['brand_id'],
            'payment_method_whitelist' => ['duitnow_qr'],
            'success_callback'         => site_url('pos/webhook/chip'),
            'reference'                => (string)$reference,
            'due_strict'               => false,
        ]);

        $ch = curl_init('https://gate.chip-in.asia/api/v1/purchases/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $settings['secret_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 201) {
            $err  = json_decode($resp, true);
            $msg  = 'HTTP ' . $code;
            if (is_array($err)) {
                $parts = [];
                foreach ($err as $v) {
                    $parts[] = is_array($v) ? implode(', ', $v) : (string)$v;
                }
                $msg = implode('; ', $parts);
            }
            $this->_error('CHIP error: ' . $msg, 502);
            return;
        }

        $purchase    = json_decode($resp, true);
        $purchase_id = $purchase['id'];
        $qr_url      = $purchase['checkout_url'];
        $expires_at  = !empty($purchase['due'])
            ? date('Y-m-d H:i:s', (int)$purchase['due'])
            : null;

        $this->pos_model->create_duitnow_transaction([
            'purchase_id'      => $purchase_id,
            'client_reference' => $reference,
            'checkout_url'     => $purchase['checkout_url'],
            'qr_code_url'      => $qr_url,
            'amount'           => $amount,
            'currency'         => 'MYR',
            'status'           => 'pending',
            'expires_at'       => $expires_at,
        ]);

        $this->_json([
            'purchase_id'  => $purchase_id,
            'checkout_url' => $purchase['checkout_url'],
            'qr_url'       => $qr_url,
            'amount'       => $amount,
            'status'       => 'pending',
        ], 201);
    }

    public function duitnow_status($purchase_id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->_error('Method not allowed', 405);
            return;
        }

        $tx = $this->pos_model->get_duitnow_transaction_by_purchase_id($purchase_id);
        if (!$tx) {
            $this->_not_found('DuitNow transaction');
            return;
        }

        // Return immediately if already in a terminal state
        if (in_array($tx['status'], ['paid', 'failed', 'cancelled', 'expired'])) {
            $this->_json([
                'purchase_id' => $purchase_id,
                'status'      => $tx['status'],
                'paid_at'     => $tx['paid_at'],
            ]);
            return;
        }

        $settings = $this->pos_model->get_chip_settings();
        if (empty($settings)) {
            $this->_error('DuitNow QR is not configured', 503);
            return;
        }

        $ch = curl_init('https://gate.chip-in.asia/api/v1/purchases/' . $purchase_id . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $settings['secret_key']],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $this->_error('Could not fetch purchase status from CHIP', 502);
            return;
        }

        $purchase    = json_decode($resp, true);
        $chip_status = $purchase['status'] ?? '';

        $local_status = $tx['status'];
        $paid_at      = $tx['paid_at'];

        if ($chip_status === 'paid') {
            $local_status = 'paid';
            $paid_at      = date('Y-m-d H:i:s');
        } elseif (in_array($chip_status, ['expired', 'overdue'])) {
            $local_status = 'expired';
        } elseif (in_array($chip_status, ['cancelled'])) {
            $local_status = 'cancelled';
        } elseif (in_array($chip_status, ['error', 'blocked'])) {
            $local_status = 'failed';
        }

        if ($local_status !== $tx['status']) {
            $this->pos_model->update_duitnow_transaction($purchase_id, [
                'status'  => $local_status,
                'paid_at' => $paid_at,
            ]);
        }

        $this->_json([
            'purchase_id' => $purchase_id,
            'status'      => $local_status,
            'paid_at'     => $paid_at,
        ]);
    }

    public function duitnow_qr_image($purchase_id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->_error('Method not allowed', 405);
            return;
        }

        $tx = $this->pos_model->get_duitnow_transaction_by_purchase_id($purchase_id);
        if (!$tx) {
            $this->_not_found('DuitNow transaction');
            return;
        }

        $checkout_url = rtrim($tx['checkout_url'], '/') . '/';

        $ch = curl_init($checkout_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; POS/1.0)',
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$html) {
            $this->_error('Could not fetch QR page from CHIP (HTTP ' . $code . ')', 502);
            return;
        }

        // Extract the inline base64 PNG — CHIP embeds the DuitNow QR as a data URI
        if (!preg_match('/src="(data:image\/png;base64,[A-Za-z0-9+\/=]+)"/', $html, $m)) {
            $this->_error('QR image not found in CHIP checkout page', 502);
            return;
        }

        $this->_json(['qr_image' => $m[1]]);
    }

    public function duitnow_cancel($purchase_id)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
            $this->_error('Method not allowed', 405);
            return;
        }

        $tx = $this->pos_model->get_duitnow_transaction_by_purchase_id($purchase_id);
        if (!$tx) {
            $this->_not_found('DuitNow transaction');
            return;
        }

        if (in_array($tx['status'], ['paid', 'failed', 'cancelled', 'expired'])) {
            $this->_error('Transaction is already in a terminal state: ' . $tx['status'], 409);
            return;
        }

        $settings = $this->pos_model->get_chip_settings();
        if (empty($settings)) {
            $this->_error('DuitNow QR is not configured', 503);
            return;
        }

        $ch = curl_init('https://gate.chip-in.asia/api/v1/purchases/' . $purchase_id . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $settings['secret_key']],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 200/204 = cancelled, 404 = already gone on CHIP's side (treat as success)
        if (!in_array($code, [200, 204, 404])) {
            $this->_error('CHIP cancel failed (HTTP ' . $code . '): ' . $resp, 502);
            return;
        }

        $this->pos_model->update_duitnow_transaction($purchase_id, ['status' => 'cancelled']);

        $this->_json(['purchase_id' => $purchase_id, 'status' => 'cancelled']);
    }

    // Called by CHIP — no Bearer token required (listed in $public_methods)
    public function chip_webhook()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $raw_body = file_get_contents('php://input');
        $payload  = json_decode($raw_body, true);

        if (empty($payload['id'])) {
            http_response_code(400);
            exit;
        }

        $purchase_id = $payload['id'];

        $tx = $this->pos_model->get_duitnow_transaction_by_purchase_id($purchase_id);
        if (!$tx) {
            // Unknown purchase — acknowledge to stop retries
            http_response_code(200);
            echo 'OK';
            exit;
        }

        // Verify CHIP signature when public key is configured
        $settings = $this->pos_model->get_chip_settings();
        if (!empty($settings['public_key'])) {
            $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
            if ($signature && !$this->_verify_chip_signature($raw_body, $signature, $settings['public_key'])) {
                http_response_code(400);
                exit;
            }
        }

        $chip_status  = $payload['status'] ?? '';
        $local_status = $tx['status'];
        $paid_at      = $tx['paid_at'];

        if ($chip_status === 'paid') {
            $local_status = 'paid';
            $paid_at      = date('Y-m-d H:i:s');
        } elseif (in_array($chip_status, ['expired', 'overdue'])) {
            $local_status = 'expired';
        } elseif (in_array($chip_status, ['cancelled'])) {
            $local_status = 'cancelled';
        } elseif (in_array($chip_status, ['error', 'blocked'])) {
            $local_status = 'failed';
        }

        $this->pos_model->update_duitnow_transaction($purchase_id, [
            'status'          => $local_status,
            'paid_at'         => $paid_at,
            'webhook_payload' => $raw_body,
        ]);

        http_response_code(200);
        echo 'OK';
        exit;
    }

    private function _verify_chip_signature($payload, $signature, $public_key)
    {
        $key = openssl_pkey_get_public($public_key);
        if (!$key) {
            return false;
        }
        return openssl_verify($payload, base64_decode($signature), $key, OPENSSL_ALGO_SHA256) === 1;
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
