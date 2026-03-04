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
        $mappings = $this->get_payment_mode_mappings($modes);

        $this->response([
            'status' => true,
            'result' => $this->format_payment_modes($modes, $mappings),
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
        $mappings = $this->get_payment_mode_mappings([$mode]);

        if (!$mode) {
            $this->response([
                'status'  => false,
                'message' => 'Payment mode not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_payment_mode($mode, $mappings),
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

    private function format_payment_modes($modes, $mappings = [])
    {
        if (!is_array($modes)) {
            return [];
        }

        return array_map(function ($mode) use ($mappings) {
            return $this->format_payment_mode($mode, $mappings);
        }, $modes);
    }

    private function format_payment_mode($mode, $mappings = [])
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

        $mapping = null;
        if (isset($mode['id']) && isset($mappings[(int) $mode['id']])) {
            $mapping = $mappings[(int) $mode['id']];
        }

        $mode['payment_mode_mapping'] = [
            'payment_account' => $mapping ? $mapping['payment_account'] : null,
            'deposit_to'      => $mapping ? $mapping['deposit_to'] : null,
        ];

        return $mode;
    }

    private function get_payment_mode_mappings($modes)
    {
        if (!$this->db->table_exists(db_prefix() . 'acc_payment_mode_mappings')) {
            return [];
        }

        $modeIds = [];
        foreach ($modes as $mode) {
            if (is_object($mode) && isset($mode->id)) {
                $modeIds[] = (int) $mode->id;
            }

            if (is_array($mode) && isset($mode['id'])) {
                $modeIds[] = (int) $mode['id'];
            }
        }

        $modeIds = array_values(array_unique(array_filter($modeIds)));

        if ($modeIds === []) {
            return [];
        }

        $rows = $this->db
            ->select('payment_mode_id, payment_account, deposit_to')
            ->from(db_prefix() . 'acc_payment_mode_mappings')
            ->where_in('payment_mode_id', $modeIds)
            ->get()
            ->result_array();

        $mappings = [];
        foreach ($rows as $row) {
            $mappings[(int) $row['payment_mode_id']] = [
                'payment_account' => isset($row['payment_account']) && $row['payment_account'] !== '' ? $row['payment_account'] : null,
                'deposit_to'      => isset($row['deposit_to']) && $row['deposit_to'] !== '' ? $row['deposit_to'] : null,
            ];
        }

        return $mappings;
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
