<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Install_app extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('authentication_model');
    }

    public function verify()
    {
        if (strtolower($this->input->method()) !== 'post') {
            return $this->respond([
                'status'  => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $warehouse_code = trim((string) $this->input->post('warehouse_code'));
        $email = trim((string) $this->input->post('email'));
        $password = (string) $this->input->post('password', false);

        if ($warehouse_code === '' || $email === '' || $password === '') {
            return $this->respond([
                'status'  => false,
                'message' => 'Warehouse code, email, and password are required.',
            ], 400);
        }

        $warehouse = $this->db
            ->where('warehouse_code', $warehouse_code)
            ->get(db_prefix() . 'warehouse')
            ->row();

        if (!$warehouse) {
            return $this->respond([
                'status'  => false,
                'message' => 'Warehouse code not found.',
            ], 404);
        }

        $login = $this->authentication_model->login($email, $password, false, true);

        if ($login === true) {
            return $this->respond([
                'status' => true,
                'result' => [
                    'warehouse_id'   => (int) $warehouse->warehouse_id,
                    'warehouse_code' => $warehouse->warehouse_code,
                    'warehouse_name' => $warehouse->warehouse_name,
                    'staff_id'       => (int) get_staff_user_id(),
                ],
            ], 200);
        }

        if (is_array($login) && isset($login['memberinactive'])) {
            return $this->respond([
                'status'  => false,
                'message' => 'Staff account is inactive.',
            ], 403);
        }

        if (is_array($login) && isset($login['two_factor_auth'])) {
            return $this->respond([
                'status'  => false,
                'message' => 'Two-factor authentication required.',
            ], 409);
        }

        return $this->respond([
            'status'  => false,
            'message' => 'Invalid email or password.',
        ], 401);
    }

    private function respond(array $payload, int $status)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }
}
