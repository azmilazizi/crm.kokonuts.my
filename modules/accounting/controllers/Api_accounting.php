<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_accounting extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        $this->module_language_file      = 'accounting/accounting';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('accounting/accounting_model');
    }

    public function accounts_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $withBalances      = $this->boolean_from_query('with_balances', false);
        $showAccountNumber = $this->boolean_from_query('show_account_numbers', true);
        $activeFilter      = $this->get('active');

        $where = [];

        if ($activeFilter !== null) {
            $active = $this->interpret_boolean($activeFilter, null);

            if ($active !== null) {
                $where['active'] = $active ? 1 : 0;
            }
        }

        if ($withBalances) {
            $accounts = $this->accounting_model->get_accounts_with_balances($where, $showAccountNumber);
        } else {
            $accounts = $this->accounting_model->get_accounts('', $where, $showAccountNumber);
        }

        $this->response([
            'status' => true,
            'result' => $accounts,
        ], self::HTTP_OK);
    }

    public function account_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $accountId    = (int) $id;
        $withBalances = $this->boolean_from_query('with_balances', false);

        if ($withBalances) {
            $accounts = $this->accounting_model->get_accounts_with_balances(['id' => $accountId]);
            $account  = !empty($accounts) ? reset($accounts) : false;
        } else {
            $account = $this->accounting_model->get_accounts($accountId);
        }

        if (!$account) {
            $this->response([
                'status'  => false,
                'message' => 'Account not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $account,
        ], self::HTTP_OK);
    }

    public function accounts_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $payload = $this->get_request_payload('post');

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $normalized = $this->prepare_account_payload($payload, false);

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $accountId = $this->create_account($normalized['data']);

        if (!$accountId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create account with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $account = $this->accounting_model->get_accounts($accountId);

        $this->response([
            'status' => true,
            'result' => $account,
        ], self::HTTP_CREATED);
    }

    public function account_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $accountId = (int) $id;
        $account   = $this->accounting_model->get_accounts($accountId);

        if (!$account) {
            $this->response([
                'status'  => false,
                'message' => 'Account not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $payload = $this->get_request_payload('put');

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $normalized = $this->prepare_account_payload($payload, true);

        if ($normalized['data'] === []) {
            $this->response([
                'status'  => false,
                'message' => 'No updatable fields were provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->update_account($accountId, $normalized['data']);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Account update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $account = $this->accounting_model->get_accounts($accountId);

        $this->response([
            'status' => true,
            'result' => $account,
        ], self::HTTP_OK);
    }

    public function account_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $accountId = (int) $id;

        $result = $this->delete_account_record($accountId);

        if ($result === 'have_transaction') {
            $this->response([
                'status'  => false,
                'message' => 'Cannot delete account because it already has related transactions.',
            ], self::HTTP_CONFLICT);

            return;
        }

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Account not found or already deleted.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Account deleted successfully.',
        ], self::HTTP_OK);
    }

    private function ensureAuthenticated()
    {
        if ($this->tokenPayload !== null) {
            return true;
        }

        $token = $this->authenticate_token();

        if ($token === false) {
            return false;
        }

        $this->tokenPayload = $token;

        return true;
    }

    private function create_account(array $data)
    {
        $result = null;

        if (method_exists($this->accounting_model, 'add_account')) {
            $result = $this->accounting_model->add_account($data);
        } else {
            $this->db->insert(db_prefix() . 'acc_accounts', $data);
            $result = $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
        }

        if (is_numeric($result)) {
            return (int) $result;
        }

        if ($result) {
            $insertId = $this->db->insert_id();

            return $insertId ? (int) $insertId : false;
        }

        return false;
    }

    private function update_account(int $accountId, array $data)
    {
        if (method_exists($this->accounting_model, 'update_account')) {
            return (bool) $this->accounting_model->update_account($data, $accountId);
        }

        $this->db->where('id', $accountId);
        $this->db->update(db_prefix() . 'acc_accounts', $data);

        return $this->db->affected_rows() > 0;
    }

    private function delete_account_record(int $accountId)
    {
        if (method_exists($this->accounting_model, 'delete_account')) {
            return $this->accounting_model->delete_account($accountId);
        }

        $this->db->where('id', $accountId);
        $this->db->delete(db_prefix() . 'acc_accounts');

        return $this->db->affected_rows() > 0;
    }

    private function prepare_account_payload(array $input, bool $is_update)
    {
        $allowed_fields = [
            'name',
            'number',
            'parent_account',
            'account_type_id',
            'account_detail_type_id',
            'balance',
            'balance_as_of',
            'description',
            'default_account',
            'active',
            'bank_account',
            'bank_routing',
            'address_line_1',
            'address_line_2',
            'bank_name',
            'key_name',
            'access_token',
            'account_id',
            'plaid_status',
            'plaid_account_name',
            'update_balance',
        ];

        $data   = [];
        $errors = [];

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }

        if (!$is_update) {
            foreach (['name', 'account_type_id', 'account_detail_type_id'] as $required) {
                if (!isset($data[$required]) || trim((string) $data[$required]) === '') {
                    $errors[] = sprintf('Field "%s" is required.', $required);
                }
            }
        }

        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }

        if (isset($data['number'])) {
            $data['number'] = trim((string) $data['number']);
        }

        if (isset($data['parent_account']) && $data['parent_account'] !== '') {
            $data['parent_account'] = (int) $data['parent_account'];
        } else {
            unset($data['parent_account']);
        }

        if (isset($data['account_type_id']) && $data['account_type_id'] !== '') {
            $data['account_type_id'] = (int) $data['account_type_id'];
        }

        if (isset($data['account_detail_type_id']) && $data['account_detail_type_id'] !== '') {
            $data['account_detail_type_id'] = (int) $data['account_detail_type_id'];
        }

        if (isset($data['balance'])) {
            $data['balance'] = $this->normalize_decimal($data['balance']);
        }

        if (isset($data['balance_as_of'])) {
            $normalizedDate = $this->normalize_date($data['balance_as_of']);

            if ($normalizedDate === null) {
                $errors[] = 'Invalid balance_as_of date provided. Expected format: YYYY-MM-DD.';
                unset($data['balance_as_of']);
            } else {
                $data['balance_as_of'] = $normalizedDate;
            }
        }

        if (isset($data['default_account'])) {
            $data['default_account'] = $this->interpret_boolean($data['default_account']) ? 1 : 0;
        }

        if (isset($data['active'])) {
            $active = $this->interpret_boolean($data['active'], null);
            if ($active !== null) {
                $data['active'] = $active ? 1 : 0;
            } else {
                unset($data['active']);
            }
        }

        foreach (['bank_account', 'bank_routing', 'address_line_1', 'address_line_2', 'bank_name', 'key_name', 'access_token', 'account_id', 'plaid_account_name'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim((string) $data[$field]);
            }
        }

        if (isset($data['plaid_status'])) {
            $data['plaid_status'] = (int) $data['plaid_status'];
        }

        if (isset($data['update_balance'])) {
            $data['update_balance'] = $this->interpret_boolean($data['update_balance']) ? 1 : 0;
        }

        if (!$is_update) {
            $errors = array_values(array_unique($errors));
        }

        return [
            'data'   => $data,
            'errors' => $errors,
        ];
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

    private function boolean_from_query($key, $default = false)
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        $result = $this->interpret_boolean($value, null);

        return $result === null ? $default : $result;
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

    private function normalize_decimal($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace([',', ' '], '', $value);

            if (is_numeric($normalized)) {
                return (float) $normalized;
            }
        }

        return 0.0;
    }

    private function normalize_date($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return date('Y-m-d', (int) $value);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', DateTime::RFC3339, DateTime::ATOM];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);

            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);

        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }
}
