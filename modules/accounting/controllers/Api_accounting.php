<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_accounting extends API_Controller
{
    public function __construct()
    {
        $this->module_language_file      = 'accounting';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->model('accounting_model');
    }

    public function accounts_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $accounts = $this->accounting_model->get_accounts('', [], false);

        $this->response([
            'status' => true,
            'result' => $accounts,
        ], self::HTTP_OK);
    }

    public function accounts_post()
    {
        if (!$this->authenticate_token()) {
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

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            $this->response([
                'status'  => false,
                'message' => 'Account name is required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $account_data = $this->prepare_account_payload($payload, false);
        $account_id   = $this->accounting_model->add_account($account_data);

        if (!$account_id) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create account with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => [
                'id'   => $account_id,
                'name' => $name,
            ],
        ], self::HTTP_CREATED);
    }

    public function account_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $account = $this->accounting_model->get_accounts((int) $id);

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

    public function account_put($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $account = $this->accounting_model->get_accounts((int) $id);

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

        $account_data = $this->prepare_account_payload($payload, true);

        if ($account_data === []) {
            $this->response([
                'status'  => false,
                'message' => 'No updatable fields were provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->accounting_model->update_account($account_data, (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Account update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Account updated successfully.',
        ], self::HTTP_OK);
    }

    public function account_delete($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $account = $this->accounting_model->get_accounts((int) $id);

        if (!$account) {
            $this->response([
                'status'  => false,
                'message' => 'Account not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $result = $this->accounting_model->delete_account((int) $id);

        if ($result === 'have_transaction') {
            $this->response([
                'status'  => false,
                'message' => 'Cannot delete an account that has related transactions.',
            ], self::HTTP_CONFLICT);

            return;
        }

        if ($result !== true) {
            $this->response([
                'status'  => false,
                'message' => 'Account deletion failed. The account may be protected or already removed.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Account deleted successfully.',
        ], self::HTTP_OK);
    }

    public function account_transactions_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $limit  = $this->get('limit');
        $offset = $this->get('offset');

        $limit  = is_numeric($limit) ? (int) $limit : 50;
        $offset = is_numeric($offset) ? (int) $offset : 0;

        if ($limit <= 0) {
            $limit = 50;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $this->db->where('account', (int) $id);
        $this->db->order_by('date', 'DESC');
        $query = $this->db->get(db_prefix() . 'acc_account_history', $limit, $offset);

        $this->response([
            'status' => true,
            'result' => $query->result_array(),
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

    private function prepare_account_payload(array $input, bool $is_update)
    {
        $allowed_fields = [
            'name',
            'number',
            'account_type_id',
            'account_detail_type_id',
            'description',
            'balance',
            'balance_as_of',
            'parent_account',
            'bank_name',
            'bank_account',
            'bank_routing',
            'address_line_1',
            'currency',
            'update_balance',
        ];

        $payload = [];

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = $input[$field];
            }
        }

        if (!$is_update) {
            if (!isset($payload['balance']) || $payload['balance'] === '') {
                $payload['balance'] = 0;
            }

            if (!isset($payload['balance_as_of'])) {
                $payload['balance_as_of'] = '';
            }
        }

        if (isset($payload['account_type_id']) && $payload['account_type_id'] !== '') {
            $payload['account_type_id'] = (int) $payload['account_type_id'];
        } else {
            unset($payload['account_type_id']);
        }

        if (isset($payload['account_detail_type_id']) && $payload['account_detail_type_id'] !== '') {
            $payload['account_detail_type_id'] = (int) $payload['account_detail_type_id'];
        } else {
            unset($payload['account_detail_type_id']);
        }

        if (isset($payload['parent_account']) && $payload['parent_account'] !== '') {
            if (is_numeric($payload['parent_account'])) {
                $payload['parent_account'] = (int) $payload['parent_account'];
            } else {
                unset($payload['parent_account']);
            }
        }

        if (isset($payload['update_balance'])) {
            $payload['update_balance'] = $this->boolean_to_int($payload['update_balance']);
        }

        return $payload;
    }

    private function boolean_to_int($value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 1 : 0;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            return in_array($value, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
        }

        return 0;
    }
}
