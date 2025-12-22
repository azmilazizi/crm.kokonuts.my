<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_install_app extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('authentication_model');
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
            $this->response([
                'status' => true,
                'result' => [
                    'warehouse_id'   => (int) $warehouse->warehouse_id,
                    'warehouse_code' => $warehouse->warehouse_code,
                    'warehouse_name' => $warehouse->warehouse_name,
                    'staff_id'       => (int) get_staff_user_id(),
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
}
