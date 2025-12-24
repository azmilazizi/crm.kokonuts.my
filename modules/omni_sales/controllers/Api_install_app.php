<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_install_app extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('authentication_model');
        $this->load->library('authorization_token');
    }

    public function verify_post()
    {
        $payload = $this->get_request_payload('post');

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouse_code = isset($payload['warehouse_code']) ? trim((string) $payload['warehouse_code']) : '';
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ($warehouse_code === '' || $email === '' || $password === '') {
            $this->response([
                'status'  => false,
                'message' => 'Warehouse code, email, and password are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouse = $this->db
            ->where('warehouse_code', $warehouse_code)
            ->get(db_prefix() . 'warehouse')
            ->row();

        if (!$warehouse) {
            $this->response([
                'status'  => false,
                'message' => 'Warehouse code not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $login = $this->authentication_model->login($email, $password, false, true);

        if ($login === true) {
            $staff_id = (int) get_staff_user_id();
            $staff = $this->db->where('staffid', $staff_id)->get(db_prefix() . 'staff')->row();

            if (!$staff || (int) $staff->role !== 2) {
                $this->response([
                    'status'  => false,
                    'message' => 'POS staff role required for activation.',
                ], self::HTTP_FORBIDDEN);

                return;
            }

            $token_payload = [
                'staffid'   => $staff_id,
                'email'     => $email,
                'timestamp' => time(),
            ];
            $token = $this->authorization_token->generateToken($token_payload);

            $this->load->config('jwt');
            $tokenHeaderName = $this->config->item('token_header');

            if (!is_string($tokenHeaderName) || $tokenHeaderName === '') {
                $tokenHeaderName = 'authtoken';
            }

            $this->db->where('staffid', $staff_id);
            $this->db->update(db_prefix() . 'staff', ['token' => $token]);

            $this->response([
                'status' => true,
                'result' => [
                    'warehouse_id'   => (int) $warehouse->warehouse_id,
                    'warehouse_code' => $warehouse->warehouse_code,
                    'warehouse_name' => $warehouse->warehouse_name,
                    'staff_id'       => $staff_id,
                    'authentication' => [
                        'token'        => $token,
                        'token_type'   => 'Bearer',
                        'generated_at' => $token_payload['timestamp'],
                        'headers'      => [
                            $tokenHeaderName => $token,
                            'Authorization'  => 'Bearer ' . $token,
                        ],
                    ],
                ],
            ], self::HTTP_OK);

            return;
        }

        if (is_array($login) && isset($login['memberinactive'])) {
            $this->response([
                'status'  => false,
                'message' => 'Staff account is inactive.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        if (is_array($login) && isset($login['two_factor_auth'])) {
            $this->response([
                'status'  => false,
                'message' => 'Two-factor authentication required.',
            ], self::HTTP_CONFLICT);

            return;
        }

        $this->response([
            'status'  => false,
            'message' => 'Invalid email or password.',
        ], self::HTTP_UNAUTHORIZED);
    }

    public function cross_check_post()
    {
        $payload = $this->get_request_payload('post');

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';

        if ($email === '' || $token === '') {
            $this->response([
                'status'  => false,
                'message' => 'Email and token are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $staff = $this->db
            ->where('email', $email)
            ->get(db_prefix() . 'staff')
            ->row();

        if (!$staff) {
            $this->response([
                'status'  => false,
                'message' => 'Staff email not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!isset($staff->token) || !is_string($staff->token) || $staff->token === '') {
            $this->response([
                'status'  => false,
                'message' => 'No token registered for this staff account.',
            ], self::HTTP_UNAUTHORIZED);

            return;
        }

        if (!hash_equals($staff->token, $token)) {
            $this->response([
                'status'  => false,
                'message' => 'Token does not match.',
            ], self::HTTP_UNAUTHORIZED);

            return;
        }

        $this->response([
            'status' => true,
            'result' => [
                'email'   => $email,
                'staff_id' => (int) $staff->staffid,
                'match'   => true,
            ],
        ], self::HTTP_OK);
    }

    private function get_request_payload($method)
    {
        $method = strtolower($method);

        if (!in_array($method, ['post', 'put'], true)) {
            $method = 'post';
        }

        $data = $this->{$method}();

        if (!is_array($data)) {
            $data = [];
        }

        if ($data === []) {
            $raw_input = $this->input->raw_input_stream;

            if ($raw_input !== '') {
                $decoded = json_decode($raw_input, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        return $data;
    }
}
