<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_auth extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('authorization_token');
        $this->load->model('staff_model');
    }

    public function login_post()
    {
        $raw_payload = json_decode($this->input->raw_input_stream, true);

        if (is_array($raw_payload)) {
            $_POST = $raw_payload;
        }

        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
        $this->form_validation->set_rules('passcode', 'Passcode', 'trim|required|max_length[4]');

        if ($this->form_validation->run() == false) {
            $message = [
                'status'  => false,
                'message' => trim(validation_errors()),
                'errors'  => $this->form_validation->error_array(),
            ];

            $this->response($message, API_Controller::HTTP_BAD_REQUEST);
            return;
        }

        $email    = $this->input->post('email', true);
        $passcode = $this->input->post('passcode', true);

        $this->db->where('email', $email);
        $staff = $this->db->get(db_prefix() . 'staff')->row();

        if (!$staff) {
            $this->response([
                'status'  => false,
                'message' => _l('staff_login_invalid_email_or_password'),
            ], API_Controller::HTTP_UNAUTHORIZED);
            return;
        }

        if ($staff->active == 0) {
            $this->response([
                'status'  => false,
                'message' => _l('admin_auth_inactive_account'),
            ], API_Controller::HTTP_FORBIDDEN);
            return;
        }

        if (empty($staff->passcode) || !app_hasher()->CheckPassword($passcode, $staff->passcode)) {
            $this->response([
                'status'  => false,
                'message' => _l('staff_login_invalid_email_or_password'),
            ], API_Controller::HTTP_UNAUTHORIZED);
            return;
        }

        $token_payload = [
            'staffid' => $staff->staffid,
            'email' => $staff->email,
            'timestamp' => time(),
        ];

        $token = $this->authorization_token->generateToken($token_payload);

        $this->load->config('jwt');
        $tokenHeaderName = $this->config->item('token_header');
        if (!is_string($tokenHeaderName) || $tokenHeaderName === '') {
            $tokenHeaderName = 'authtoken';
        }

        $this->db->where('staffid', $staff->staffid);
        $this->db->update(db_prefix() . 'staff', ['token' => $token]);

        $staffData = $this->staff_model->get($staff->staffid);

        $this->response([
            'status' => true,
            'result' => [
                'staffid'        => $staffData->staffid,
                'fullName'       => $staffData->full_name,
                'email'          => $staffData->email,
                'two_factor'     => (bool) $staffData->two_factor_auth_enabled,
                'permissions'    => $staffData->permissions,
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
        ], API_Controller::HTTP_OK);
    }
}
