<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_payment_modes extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('payment_modes_model');
    }

    public function payment_modes_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $includeInactive = $this->interpret_boolean($this->get('include_inactive'), false);

        $modes = $this->payment_modes_model->get('', [], $includeInactive);

        $this->response([
            'status' => true,
            'result' => $this->format_payment_modes($modes),
        ], self::HTTP_OK);
    }

    public function payment_mode_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if ($id === null || $id === '') {
            $this->response([
                'status'  => false,
                'message' => 'Payment mode identifier is required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $includeInactive = $this->interpret_boolean($this->get('include_inactive'), false);

        $mode = $this->payment_modes_model->get($id, [], $includeInactive, $includeInactive);

        if (!$mode) {
            $this->response([
                'status'  => false,
                'message' => 'Payment mode not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_payment_mode($mode),
        ], self::HTTP_OK);
    }

    private function ensureAuthenticated()
    {
        if ($this->tokenPayload !== null) {
            return true;
        }

        $tokenData = $this->authenticate_token();

        if ($tokenData === false) {
            return false;
        }

        $tokenString = $this->authorization_token->get_token();

        if (!empty($tokenString) && $tokenString !== 'Token is not defined.') {
            $staff = $this->db->where('token', $tokenString)->get(db_prefix() . 'staff')->row();

            if ($staff) {
                $this->session->set_userdata([
                    'staff_logged_in' => true,
                    'staff_user_id'   => $staff->staffid,
                ]);

                $GLOBALS['current_user'] = $staff;
            }
        }

        $this->tokenPayload = isset($tokenData['data']) ? $tokenData['data'] : $tokenData;

        return true;
    }

    private function format_payment_modes($modes)
    {
        if (!is_array($modes)) {
            return [];
        }

        return array_map(function ($mode) {
            return $this->format_payment_mode($mode);
        }, $modes);
    }

    private function format_payment_mode($mode)
    {
        if (is_object($mode)) {
            $mode = (array) $mode;
        }

        if (!is_array($mode)) {
            return $mode;
        }

        if (isset($mode['instance'])) {
            unset($mode['instance']);
        }

        if (isset($mode['description'])) {
            $mode['description'] = $this->convert_breaks_to_newlines($mode['description']);
        }

        return $mode;
    }

    private function interpret_boolean($value, $default = false)
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    private function convert_breaks_to_newlines($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        return preg_replace('/<br\s*\/?>(\r\n)?/i', PHP_EOL, $decoded);
    }
}
