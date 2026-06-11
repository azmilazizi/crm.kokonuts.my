<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Loyalty REST API — consumed by loyalty.kokonuts.my and the future customer app.
 * All endpoints except `register` require:  Authorization: Bearer <pos_api_token>
 */
class Api extends App_Controller
{
    private static $public_methods = ['register', 'claim'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('loyalty/loyalty_model');

        $method = $this->router->fetch_method();
        if (!in_array($method, self::$public_methods)) {
            $this->_verify_token();
        }
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
    // Helpers
    // =========================================================================

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
