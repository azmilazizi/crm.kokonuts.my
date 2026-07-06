<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Loyalty REST API — consumed by loyalty.kokonuts.my and the future customer app.
 * All endpoints except `register` require:  Authorization: Bearer <pos_api_token>
 */
class Api extends App_Controller
{
    private static $public_methods = ['register', 'claim', 'member_login', 'member_set_password', 'member_register', 'confirm_review'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('loyalty/loyalty_model');
        // Auth check is done in _remap after resolving the true method name
    }

    public function _remap($method, $params = [])
    {
        // Translate member/X sub-routes (e.g. member/set_password → member_set_password)
        if ($method === 'member' && !empty($params)) {
            $sub    = array_shift($params);
            $method = 'member_' . $sub;
        }

        // Translate pos/voucher/X sub-routes (e.g. pos/voucher/validate → validate_voucher)
        if ($method === 'pos' && ($params[0] ?? '') === 'voucher' && !empty($params[1])) {
            $method = $params[1] . '_voucher';
            $params = array_slice($params, 2);
        }

        $is_member_endpoint = strncmp($method, 'member_', 7) === 0;
        $self_auth          = in_array($method, ['transactions']);
        if (!$is_member_endpoint && !$self_auth && !in_array($method, self::$public_methods)) {
            $this->_verify_token();
        }

        if (!method_exists($this, $method)) {
            $this->_error('Not found', 404);
        }

        call_user_func_array([$this, $method], $params);
    }

    // =========================================================================
    // GET loyalty/api/balance/{id}
    // =========================================================================

    public function balance($customer_id = 0)
    {
        $lc = $this->loyalty_model->get_balance((int)$customer_id);
        $lc ? $this->_json($lc) : $this->_error('Customer not found', 404);
    }

    // =========================================================================
    // GET loyalty/api/profile/{id}
    // =========================================================================

    public function profile($customer_id = 0)
    {
        $lc = $this->loyalty_model->get_customer((int)$customer_id);
        if (!$lc) {
            $this->_error('Customer not found', 404);
        }

        $recent = $this->loyalty_model->get_customer_transactions((int)$customer_id, 1, 5);

        $this->_json([
            'customer'            => $lc,
            'recent_transactions' => $recent,
        ]);
    }

    // =========================================================================
    // POST loyalty/api/earn
    // =========================================================================

    public function earn()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }

        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $customer_id = (int)($data['customer_id'] ?? 0);
        $receipt_id  = (int)($data['receipt_id'] ?? 0) ?: null;
        $amount      = (float)($data['amount_spent'] ?? 0);

        if (!$customer_id || $amount <= 0) {
            $this->_error('customer_id and amount_spent are required');
        }

        $points = $this->loyalty_model->earn_points($customer_id, $receipt_id, $amount);
        $points !== false
            ? $this->_json(['points_earned' => $points])
            : $this->_error('Failed to earn points');
    }

    // =========================================================================
    // POST loyalty/api/redeem
    // =========================================================================

    public function redeem()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }

        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $customer_id = (int)($data['customer_id'] ?? 0);
        $receipt_id  = (int)($data['receipt_id'] ?? 0) ?: null;
        $points      = (float)($data['points'] ?? 0);

        if (!$customer_id || $points <= 0) {
            $this->_error('customer_id and points are required');
        }

        $result = $this->loyalty_model->redeem_points($customer_id, $receipt_id, $points);
        $result !== false
            ? $this->_json($result)
            : $this->_error('Insufficient points or customer not found', 409);
    }

    // =========================================================================
    // GET  loyalty/api/claim/{receipt_no}  — check receipt status
    // POST loyalty/api/claim/{receipt_no}  — submit name + phone to claim cashback
    // Both public (no token required)
    // =========================================================================

    public function claim($receipt_no = '')
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if (empty($receipt_no)) {
            $this->_error('receipt_no is required', 400);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $info = $this->loyalty_model->get_receipt_for_claim($receipt_no);
            $info ? $this->_json($info) : $this->_error('Receipt not found', 404);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data         = json_decode(file_get_contents('php://input'), true) ?? [];
            $phone        = $this->_clean_phone(trim($data['phone'] ?? ''));
            $name         = $this->_pascal_name(trim($data['name'] ?? ''));
            $birthday     = trim($data['birthday'] ?? '');
            $pdpa_consent = filter_var($data['pdpa_consent'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (empty($phone)) {
                $this->_error('phone is required');
            }

            $result = $this->loyalty_model->process_claim($receipt_no, $phone, $name, $birthday, $pdpa_consent);

            if (isset($result['error'])) {
                $code = $result['code'] ?? 400;
                if (isset($result['status'])) {
                    http_response_code($code);
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                }
                $this->_error($result['error'], $code);
            }

            $this->_json($result);
            return;
        }

        $this->_error('Method not allowed', 405);
    }

    // =========================================================================
    // POST loyalty/api/register  — public (no token required)
    // Called by loyalty.kokonuts.my when a customer scans their cashback QR
    // =========================================================================

    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }

        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = trim($data['token'] ?? '');
        $name  = trim($data['name'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $email = trim($data['email'] ?? '');

        if (empty($token) || empty($name)) {
            $this->_error('token and name are required');
        }

        // If the token matches an existing loyalty member, return their profile
        $lc = $this->loyalty_model->get_customer_by_qr($token);
        if ($lc) {
            $this->_json(['type' => 'loyalty_customer', 'data' => $lc]);
            return;
        }

        // Otherwise treat it as a cashback receipt QR — delegate to POS model
        $this->load->model('pos/pos_model');
        $result = $this->pos_model->loyalty_register_from_qr($token, $name, $phone, $email);
        $result ? $this->_json($result, 201) : $this->_error('Registration failed');
    }

    // =========================================================================
    // POST loyalty/api/member/login — public
    // Body: { phone, password }
    // Returns: { token, expires_in_days: 30, customer: {...} }
    // =========================================================================
    // POST loyalty/api/member/register — public
    // Create a new member account. If phone already exists with no password,
    // the existing cashback record is claimed instead of creating a duplicate.
    // Body: { name, phone, password, password_confirm, email?, birthday?, pdpa_consent? }
    // =========================================================================

    public function member_register()
    {
        $this->_cors();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_error('Method not allowed', 405);

        $data             = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone            = $this->_clean_phone(trim($data['phone'] ?? ''));
        $name             = $this->_pascal_name(trim($data['name'] ?? ''));
        $password         = $data['password'] ?? '';
        $password_confirm = $data['password_confirm'] ?? '';

        if (empty($name))     $this->_error('name is required');
        if (empty($phone))    $this->_error('phone is required');
        if (empty($password)) $this->_error('password is required');
        if (strlen($password) < 8) $this->_error('Password must be at least 8 characters');
        if ($password !== $password_confirm) $this->_error('Passwords do not match');

        $result = $this->loyalty_model->create_member([
            'name'         => $name,
            'phone'        => $phone,
            'email'        => trim($data['email'] ?? ''),
            'birthday'     => $data['birthday'] ?? null,
            'address1'     => trim($data['address1'] ?? ''),
            'address2'     => trim($data['address2'] ?? ''),
            'city'         => trim($data['city'] ?? ''),
            'state'        => trim($data['state'] ?? ''),
            'postcode'     => trim($data['postcode'] ?? ''),
            'pdpa_consent' => filter_var($data['pdpa_consent'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);

        if (isset($result['error'])) {
            $this->_error($result['error'], $result['code'] ?? 400);
        }

        $this->loyalty_model->set_member_password($result['id'], $password);
        $token    = $this->loyalty_model->create_member_session($result['id']);
        $customer = $this->loyalty_model->get_customer($result['id']);
        unset($customer['password_hash']);
        $customer['tier'] = $this->loyalty_model->get_tier((float)$customer['total_points']);

        $this->_json([
            'claimed'        => $result['claimed'],
            'token'          => $token,
            'expires_in_days' => 30,
            'customer'       => $customer,
        ], 201);
    }

    // =========================================================================
    // DELETE loyalty/api/member/account — member token required
    // Soft-deletes the account: sets account_status to 'inactive', revokes all sessions.
    // =========================================================================

    public function member_account()
    {
        $this->_cors();
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }

        $customer_id = $this->_require_member_token();

        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $password = $data['password'] ?? '';

        if (empty($password)) $this->_error('password is required to confirm account deletion');

        $customer = $this->loyalty_model->get_customer($customer_id);
        if (!$customer) $this->_error('Member not found', 404);

        if (!password_verify($password, $customer['password_hash'])) {
            $this->_error('Incorrect password', 401);
        }

        $this->loyalty_model->update_customer($customer_id, ['account_status' => 'inactive']);
        $this->loyalty_model->revoke_all_member_sessions($customer_id);

        $this->_json(['message' => 'Account deactivated successfully.']);
    }

    // =========================================================================

    public function member_login()
    {
        $this->_cors();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_error('Method not allowed', 405);

        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone    = $this->_clean_phone(trim($data['phone'] ?? ''));
        $password = $data['password'] ?? '';

        if (empty($phone) || empty($password)) {
            $this->_error('phone and password are required');
        }

        $customer = $this->loyalty_model->authenticate_member($phone, $password);
        if (!$customer) {
            $this->_error('Invalid phone number or password', 401);
        }

        $token = $this->loyalty_model->create_member_session($customer['id']);
        if (!$token) $this->_error('Failed to create session', 500);

        unset($customer['password_hash']);
        $customer['tier'] = $this->loyalty_model->get_tier((float)$customer['total_points']);

        $this->_json(['token' => $token, 'expires_in_days' => 30, 'customer' => $customer]);
    }

    // =========================================================================
    // POST loyalty/api/member/set_password — public
    // Used when a member who has cashback points wants to create/claim an account.
    // Body: { phone, password, password_confirm }
    // If no password_hash exists yet → account claim (no current_password needed).
    // If password_hash exists → requires current_password for a password change.
    // =========================================================================

    public function member_set_password()
    {
        $this->_cors();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_error('Method not allowed', 405);

        $data             = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone            = $this->_clean_phone(trim($data['phone'] ?? ''));
        $password         = $data['password'] ?? '';
        $password_confirm = $data['password_confirm'] ?? '';
        $current_password = $data['current_password'] ?? '';

        if (empty($phone))    $this->_error('phone is required');
        if (empty($password)) $this->_error('password is required');
        if (strlen($password) < 8) $this->_error('Password must be at least 8 characters');
        if ($password !== $password_confirm) $this->_error('Passwords do not match');

        $row = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['phone' => $phone])->row_array();
        if (!$row) $this->_error('No account found for that phone number', 404);
        if ($row['account_status'] === 'banned') $this->_error('Account is suspended', 403);

        // Existing account — require current password to change
        if (!empty($row['password_hash'])) {
            if (empty($current_password)) $this->_error('current_password is required to change password');
            if (!password_verify($current_password, $row['password_hash'])) {
                $this->_error('Current password is incorrect', 401);
            }
        }

        $ok = $this->loyalty_model->set_member_password($row['id'], $password);
        if (!$ok) $this->_error('Failed to set password', 500);

        // Revoke existing sessions so they must log in fresh
        $this->loyalty_model->revoke_all_member_sessions($row['id']);

        $this->_json(['message' => 'Password set successfully. Please log in.']);
    }

    // =========================================================================
    // POST loyalty/api/member/logout — member token required
    // =========================================================================

    public function member_logout()
    {
        $this->_cors();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_error('Method not allowed', 405);

        $token = $this->_extract_member_token();
        if ($token) $this->loyalty_model->revoke_member_session($token);

        $this->_json(['message' => 'Logged out']);
    }

    // =========================================================================
    // GET loyalty/api/member/me — member token required
    // Returns full profile + tier + recent transactions
    // =========================================================================

    public function member_me()
    {
        $this->_cors();
        $customer_id = $this->_require_member_token();

        $customer = $this->loyalty_model->get_customer($customer_id);
        if (!$customer) $this->_error('Member not found', 404);

        unset($customer['password_hash']);
        $recent = $this->loyalty_model->get_customer_transactions($customer_id, 1, 5);
        $unread = $this->loyalty_model->get_unread_count($customer_id);

        $this->_json([
            'customer'            => $customer,
            'recent_transactions' => $recent,
            'unread_notifications' => $unread,
        ]);
    }

    // =========================================================================
    // PUT loyalty/api/member/profile — member token required
    // Body: { name, email, birthday, address1, address2, city, state, postcode }
    // =========================================================================

    public function member_profile()
    {
        $this->_cors();
        $customer_id = $this->_require_member_token();

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->_error('Method not allowed', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $allowed = ['name', 'email', 'birthday', 'address1', 'address2', 'city', 'state', 'postcode'];
        $update  = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) $update[$f] = $data[$f];
        }

        if (empty($update)) $this->_error('No fields to update');

        $this->loyalty_model->update_customer($customer_id, $update);
        $customer = $this->loyalty_model->get_customer($customer_id);
        unset($customer['password_hash']);

        $this->_json(['customer' => $customer]);
    }

    // =========================================================================
    // GET loyalty/api/member/transactions?page=1&per_page=20 — member token
    // =========================================================================

    public function member_transactions()
    {
        $this->_cors();
        $customer_id = $this->_require_member_token();

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = min(50, max(1, (int)($this->input->get('per_page') ?: 20)));
        $total    = $this->loyalty_model->count_customer_transactions($customer_id);
        $txns     = $this->loyalty_model->get_customer_transactions($customer_id, $page, $per_page);

        $this->_json([
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'items'    => $txns,
        ]);
    }

    // =========================================================================
    // GET loyalty/api/transactions/{customer_id}?page=1&per_page=20 — POS token
    // Admin/system view of a member's transaction history by customer ID
    // =========================================================================

    public function transactions($customer_id = 0)
    {
        $this->_cors();
        $customer_id = (int)$customer_id;
        if (!$customer_id) $this->_error('customer_id is required', 400);

        $member_token = $this->_extract_member_token();
        if ($member_token) {
            $session_id = $this->loyalty_model->verify_member_session($member_token);
            if (!$session_id) $this->_error('Invalid or expired session. Please log in again.', 401);
            if ($session_id !== $customer_id) $this->_error('Unauthorized', 403);
        } else {
            $this->_verify_token();
        }

        $customer = $this->loyalty_model->get_customer($customer_id);
        if (!$customer) $this->_error('Customer not found', 404);

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = min(100, max(1, (int)($this->input->get('per_page') ?: 20)));
        $total    = $this->loyalty_model->count_customer_transactions($customer_id);
        $txns     = $this->loyalty_model->get_customer_transactions($customer_id, $page, $per_page);

        unset($customer['password_hash']);

        $this->_json([
            'customer' => $customer,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'items'    => $txns,
        ]);
    }

    // =========================================================================
    // GET loyalty/api/member/promotions?page=1 — member token
    // =========================================================================

    public function member_promotions()
    {
        $this->_cors();
        $this->_require_member_token();

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = min(50, max(1, (int)($this->input->get('per_page') ?: 20)));
        $items    = $this->loyalty_model->get_promotions(true, $page, $per_page);
        $total    = $this->loyalty_model->count_promotions(true);

        $this->_json(compact('total', 'page', 'per_page', 'items'));
    }

    // =========================================================================
    // GET loyalty/api/member/notifications?page=1 — member token
    // POST loyalty/api/member/notifications/{id}/read — mark as read
    // =========================================================================

    public function member_notifications($notification_id = null)
    {
        $this->_cors();
        $customer_id = $this->_require_member_token();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $notification_id) {
            $this->loyalty_model->mark_notification_read((int)$notification_id, $customer_id);
            $this->_json(['message' => 'Marked as read']);
            return;
        }

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = min(50, max(1, (int)($this->input->get('per_page') ?: 20)));
        $items    = $this->loyalty_model->get_member_notifications($customer_id, $page, $per_page);
        $unread   = $this->loyalty_model->get_unread_count($customer_id);

        $this->_json(compact('unread', 'page', 'per_page', 'items'));
    }

    // =========================================================================
    // POST loyalty/api/validate_voucher — POS token required
    // Body: { "code": "MDAY2025", "customer_id": 123 }
    // =========================================================================

    public function validate_voucher()
    {
        $this->_cors();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_error('Method not allowed', 405);

        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $code        = strtoupper(trim($data['code'] ?? ''));
        $customer_id = (int)($data['customer_id'] ?? 0);

        if (!$code)        $this->_error('code is required');
        if (!$customer_id) $this->_error('customer_id is required');

        $result = $this->loyalty_model->validate_voucher($code, $customer_id);

        if (!$result['valid']) {
            $this->_error($result['error'], 422);
        }

        $voucher = $result['voucher'];
        $this->_json([
            'voucher_id'          => (int)$voucher['id'],
            'title'               => $voucher['title'],
            'reward_type'         => $voucher['reward_type'],
            'reward_value'        => $voucher['reward_value'] !== null ? (float)$voucher['reward_value'] : null,
            'reward_item'         => $voucher['reward_item'],
            'max_uses_per_member' => (int)$voucher['max_uses_per_member'],
        ]);
    }

    // =========================================================================
    // POST loyalty/api/redeem_voucher — POS token required
    // Body: { "code": "MDAY2025", "customer_id": 123, "order_id": 456 }
    // =========================================================================

    public function redeem_voucher()
    {
        $this->_cors();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_error('Method not allowed', 405);

        $data        = json_decode(file_get_contents('php://input'), true) ?? [];
        $code        = strtoupper(trim($data['code'] ?? ''));
        $customer_id = (int)($data['customer_id'] ?? 0);
        $order_id    = !empty($data['order_id']) ? (int)$data['order_id'] : null;

        if (!$code)        $this->_error('code is required');
        if (!$customer_id) $this->_error('customer_id is required');

        // Re-validate before redeeming
        $result = $this->loyalty_model->validate_voucher($code, $customer_id);
        if (!$result['valid']) {
            $this->_error($result['error'], 422);
        }

        $voucher     = $result['voucher'];
        $instance    = $result['instance'];
        $instance_id = $instance ? (int)$instance['id'] : null;

        $ok = $this->loyalty_model->redeem_voucher((int)$voucher['id'], $customer_id, $instance_id, $order_id);
        if (!$ok) $this->_error('Failed to record redemption', 500);

        $this->_json([
            'redeemed'     => true,
            'voucher_id'   => (int)$voucher['id'],
            'reward_type'  => $voucher['reward_type'],
            'reward_value' => $voucher['reward_value'] !== null ? (float)$voucher['reward_value'] : null,
            'reward_item'  => $voucher['reward_item'],
        ]);
    }

    // =========================================================================
    // POST loyalty/api/confirm_review — public (no token required)
    // Body: { phone }
    // Called when the user confirms they have left a Google review.
    // Sets google_review_done = 1 for that phone number.
    // =========================================================================

    public function confirm_review()
    {
        $this->_cors();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_error('Method not allowed', 405);

        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone = $this->_clean_phone(trim($data['phone'] ?? ''));

        if (empty($phone)) $this->_error('phone is required');

        $ok = $this->loyalty_model->confirm_google_review($phone);
        $ok
            ? $this->_json(['message' => 'Thank you for your review!'])
            : $this->_error('Phone number not found', 404);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function _cors()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    private function _extract_member_token()
    {
        $headers = $this->input->request_headers();
        $auth    = $headers['Authorization'] ?? '';
        if (preg_match('/^Member\s+(.+)$/i', trim($auth), $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function _require_member_token()
    {
        $token = $this->_extract_member_token();
        if (!$token) $this->_error('Member token required', 401);

        $customer_id = $this->loyalty_model->verify_member_session($token);
        if (!$customer_id) $this->_error('Invalid or expired session. Please log in again.', 401);

        return $customer_id;
    }

    private function _clean_phone($phone)
    {
        $phone = preg_replace('/[\s\-\(\)\.]/', '', $phone);
        if (strpos($phone, '+') === 0) {
            $phone = substr($phone, 1);
        }
        // Normalise Malaysian local 0XX → 60XX
        if (strpos($phone, '0') === 0) {
            $phone = '60' . substr($phone, 1);
        }
        return $phone;
    }

    private function _pascal_name($name)
    {
        if ($name === '') return '';
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    private function _verify_token()
    {
        $headers = $this->input->request_headers();
        $auth    = $headers['Authorization'] ?? '';
        $token   = trim(str_replace('Bearer', '', $auth));

        if (empty($token) || !$this->loyalty_model->verify_api_token($token)) {
            $this->_error('Unauthorized', 401);
        }
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
}
