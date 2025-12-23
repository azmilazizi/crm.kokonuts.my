<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api_auth extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Authentication_model');
    }

    public function login_post()
    {
        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'trim|required');
        $this->form_validation->set_rules('passcode', 'Passcode', 'trim|required');

        if ($this->form_validation->run() == false) {
            $this->response(['status' => false, 'message' => validation_errors()], 400);
            return;
        }

        $email = $this->input->post('email');
        $password = $this->input->post('password', false);
        $passcode = $this->input->post('passcode');

        $user = $this->Authentication_model->login($email, $password, false, true, $passcode);

        if ($user) {
            $token_payload = [
                'staff_id' => $user->staffid,
                'email' => $user->email,
            ];

            $this->load->library('Authorization_Token');
            $token = $this->authorization_token->generateToken($token_payload);

            $this->response(['status' => true, 'token' => $token], 200);
        } else {
            $this->response(['status' => false, 'message' => 'Invalid login credentials'], 401);
        }
    }

    public function update_passcode_post()
    {
        $this->load->library('Authorization_Token');
        $is_valid_token = $this->authorization_token->validateToken();
        if ($is_valid_token['status'] === false) {
            $this->response(['status' => false, 'message' => $is_valid_token['message']], 401);
            return;
        }

        $this->form_validation->set_rules('passcode', 'Passcode', 'trim|required|exact_length[4]|numeric');

        if ($this->form_validation->run() == false) {
            $this->response(['status' => false, 'message' => validation_errors()], 400);
            return;
        }

        $staff_id = $is_valid_token['data']->staff_id;
        $passcode = $this->input->post('passcode');

        $this->load->model('Staff_model');
        $this->Staff_model->update(['passcode' => $passcode], $staff_id);

        $this->response(['status' => true, 'message' => 'Passcode updated successfully.'], 200);
    }
}
