<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_accounting extends API_Controller
{
    /**
     * Force every accounting API response to use JSON regardless of the
     * requesting client's Accept header. This keeps mobile integrations from
     * accidentally receiving PHP-serialized payloads when they send
     * "text/plain" or other generic values.
     *
     * @var array<string,string>
     */
    protected $_supported_formats = [
        'json' => 'application/json',
    ];

    /**
     * Default response format for this controller.
     *
     * @var string
     */
    protected $rest_format = 'json';

    public function __construct()
    {
        $this->module_language_file      = 'accounting';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->response->format = 'json';
        $this->output->set_content_type('application/json');

        $this->load->library('authorization_token');
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

    public function bills_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $bills = $this->accounting_model->get_bill('', ['is_bill' => 1]);

        $result = [];

        foreach ($bills as $bill) {
            $result[] = $this->format_bill_summary($bill);
        }

        $this->response([
            'status' => true,
            'result' => $result,
        ], self::HTTP_OK);
    }

    public function bill_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $bill = $this->accounting_model->get_bill((int) $id, ['is_bill' => 1]);

        if (!$bill) {
            $this->response([
                'status'  => false,
                'message' => 'Bill not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_bill_detail($bill),
        ], self::HTTP_OK);
    }

    public function bills_post()
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

        $prepared = $this->prepare_bill_payload($payload);

        if ($prepared['errors'] !== []) {
            $this->response([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $prepared['errors'],
            ], self::HTTP_UNPROCESSABLE_ENTITY);

            return;
        }

        if ($this->is_books_closed_for_date($prepared['payload']['date'])) {
            $this->response([
                'status'  => false,
                'message' => 'The accounting books are closed for the provided date.',
            ], self::HTTP_CONFLICT);

            return;
        }

        $bill_id = $this->accounting_model->add_bill($prepared['payload']);

        if (!$bill_id) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create bill with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $bill = $this->accounting_model->get_bill($bill_id, ['is_bill' => 1]);

        $this->response([
            'status' => true,
            'result' => $this->format_bill_detail($bill),
        ], self::HTTP_CREATED);
    }

    public function bill_put($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing_bill = $this->accounting_model->get_bill((int) $id, ['is_bill' => 1]);

        if (!$existing_bill) {
            $this->response([
                'status'  => false,
                'message' => 'Bill not found.',
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

        $prepared = $this->prepare_bill_payload($payload);

        if ($prepared['errors'] !== []) {
            $this->response([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $prepared['errors'],
            ], self::HTTP_UNPROCESSABLE_ENTITY);

            return;
        }

        if ($this->is_books_closed_for_date($prepared['payload']['date'])) {
            $this->response([
                'status'  => false,
                'message' => 'The accounting books are closed for the provided date.',
            ], self::HTTP_CONFLICT);

            return;
        }

        $updated = $this->accounting_model->update_bill($prepared['payload'], (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update bill with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $bill = $this->accounting_model->get_bill((int) $id, ['is_bill' => 1]);

        $this->response([
            'status' => true,
            'result' => $this->format_bill_detail($bill),
        ], self::HTTP_OK);
    }

    public function bill_delete($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing_bill = $this->accounting_model->get_bill((int) $id, ['is_bill' => 1]);

        if (!$existing_bill) {
            $this->response([
                'status'  => false,
                'message' => 'Bill not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $result = $this->accounting_model->delete_bill((int) $id);

        if ($result === 'paid') {
            $this->response([
                'status'  => false,
                'message' => 'Paid bills cannot be deleted.',
            ], self::HTTP_CONFLICT);

            return;
        }

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to delete bill.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Bill deleted successfully.',
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

    private function prepare_bill_payload(array $input)
    {
        $errors  = [];
        $payload = [];

        $vendor = $input['vendor_id'] ?? $input['vendor'] ?? null;

        if (!is_numeric($vendor) || (int) $vendor <= 0) {
            $errors[] = 'A valid vendor_id must be provided.';
        } else {
            $payload['vendor'] = (int) $vendor;
        }

        $date = $this->normalize_date_string($input['date'] ?? null);

        if ($date === null) {
            $errors[] = 'A valid bill date must be provided.';
        } else {
            $payload['date'] = $date;
        }

        $due_date_input = $input['due_date'] ?? ($input['date'] ?? null);
        $due_date       = $this->normalize_date_string($due_date_input);

        if ($due_date === null) {
            $errors[] = 'A valid due date must be provided.';
        } else {
            $payload['due_date'] = $due_date;
        }

        $string_fields = ['expense_name', 'reference_no', 'note', 'currency'];

        foreach ($string_fields as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = trim((string) $input[$field]);
            }
        }

        $integer_fields = ['paymentmode', 'tax', 'tax2', 'billable_type', 'category', 'clientid', 'project_id'];

        foreach ($integer_fields as $field) {
            if (array_key_exists($field, $input) && $input[$field] !== null && $input[$field] !== '') {
                if (!is_numeric($input[$field])) {
                    $errors[] = sprintf('Field %s must be numeric.', $field);
                } else {
                    $payload[$field] = (int) $input[$field];
                }
            }
        }

        if (array_key_exists('billable', $input)) {
            $payload['billable'] = $this->boolean_to_int($input['billable']);
        }

        if (array_key_exists('currency_rate', $input) && $input['currency_rate'] !== null && $input['currency_rate'] !== '') {
            if (!is_numeric($input['currency_rate'])) {
                $errors[] = 'Field currency_rate must be numeric.';
            } else {
                $payload['currency_rate'] = (float) $input['currency_rate'];
            }
        }

        $debit_source  = $input['debits'] ?? $this->legacy_ledger_lines($input, 'debit_account', 'debit_amount');
        $credit_source = $input['credits'] ?? $this->legacy_ledger_lines($input, 'credit_account', 'credit_amount');
        $item_source   = $input['items'] ?? $this->legacy_bill_items($input);

        $debit_accounts = $this->normalize_bill_ledger_lines($debit_source, 'debit');
        $credit_accounts = $this->normalize_bill_ledger_lines($credit_source, 'credit');
        $items           = $this->normalize_bill_items($item_source);

        if ($debit_accounts['accounts'] === [] && $items['item_id'] === []) {
            $errors[] = 'At least one debit entry or item with a positive amount is required.';
        }

        if ($debit_accounts['accounts'] !== []) {
            $payload['debit_account'] = $debit_accounts['accounts'];
            $payload['debit_amount']  = $debit_accounts['amounts'];
        }

        if ($credit_accounts['accounts'] !== []) {
            $payload['credit_account'] = $credit_accounts['accounts'];
            $payload['credit_amount']  = $credit_accounts['amounts'];
        }

        if ($items['item_id'] !== []) {
            $payload['item_id']          = $items['item_id'];
            $payload['item_description'] = $items['item_description'];
            $payload['item_qty']         = $items['item_qty'];
            $payload['item_cost']        = $items['item_cost'];
            $payload['item_amount']      = $items['item_amount'];
        }

        $total = $debit_accounts['total'] + $items['total'];

        if ($total <= 0) {
            $errors[] = 'The bill total must be greater than zero.';
        } else {
            $payload['amount'] = $this->format_money_value($total);
        }

        return [
            'errors'  => $errors,
            'payload' => $payload,
        ];
    }

    private function legacy_ledger_lines(array $input, $account_key, $amount_key)
    {
        if (!isset($input[$account_key], $input[$amount_key])) {
            return [];
        }

        if (!is_array($input[$account_key]) || !is_array($input[$amount_key])) {
            return [];
        }

        $lines = [];

        foreach ($input[$account_key] as $index => $account) {
            $amount = $input[$amount_key][$index] ?? null;

            $lines[] = [
                'account' => $account,
                'amount'  => $amount,
            ];
        }

        return $lines;
    }

    private function legacy_bill_items(array $input)
    {
        if (!isset($input['item_id']) || !is_array($input['item_id'])) {
            return [];
        }

        $items = [];

        foreach ($input['item_id'] as $index => $item_id) {
            $items[] = [
                'item_id'     => $item_id,
                'description' => $input['item_description'][$index] ?? '',
                'qty'         => $input['item_qty'][$index] ?? 0,
                'cost'        => $input['item_cost'][$index] ?? 0,
                'amount'      => $input['item_amount'][$index] ?? null,
            ];
        }

        return $items;
    }

    private function normalize_bill_ledger_lines($lines, $type)
    {
        $accounts = [];
        $amounts  = [];
        $total    = 0.0;

        if (!is_array($lines)) {
            $lines = [];
        }

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $account = $line['account_id'] ?? $line['account'] ?? null;
            $amount  = $line['amount'] ?? null;

            if (!is_numeric($account) || (int) $account <= 0) {
                continue;
            }

            $amount_value = $this->parse_numeric_value($amount);

            if ($amount_value === null) {
                continue;
            }

            if ($amount_value <= 0) {
                continue;
            }

            $accounts[] = (int) $account;
            $amounts[]  = $this->format_money_value($amount_value);
            $total     += $amount_value;
        }

        return [
            'accounts' => $accounts,
            'amounts'  => $amounts,
            'total'    => $total,
            'type'     => $type,
        ];
    }

    private function normalize_bill_items($items)
    {
        $result = [
            'item_id'          => [],
            'item_description' => [],
            'item_qty'         => [],
            'item_cost'        => [],
            'item_amount'      => [],
            'total'            => 0.0,
        ];

        if (!is_array($items)) {
            return $result;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_id = $item['item_id'] ?? $item['id'] ?? null;
            $qty     = $item['qty'] ?? $item['quantity'] ?? 0;
            $cost    = $item['cost'] ?? $item['rate'] ?? $item['price'] ?? 0;
            $amount  = $item['amount'] ?? null;

            if (!is_numeric($item_id) || (int) $item_id <= 0) {
                continue;
            }

            $qty_value  = $this->parse_numeric_value($qty);
            $cost_value = $this->parse_numeric_value($cost);

            if ($qty_value === null) {
                $qty_value = 0.0;
            }

            if ($cost_value === null) {
                $cost_value = 0.0;
            }

            if ($amount === null) {
                $amount = $qty_value * $cost_value;
            }

            $amount_value = $this->parse_numeric_value($amount);

            if ($amount_value === null) {
                continue;
            }

            if ($amount_value <= 0) {
                continue;
            }

            $result['item_id'][]          = (int) $item_id;
            $result['item_description'][] = trim((string) ($item['description'] ?? $item['name'] ?? ''));
            $result['item_qty'][]         = $qty_value;
            $result['item_cost'][]        = $this->format_money_value($cost_value);
            $result['item_amount'][]      = $this->format_money_value($amount_value);
            $result['total']             += $amount_value;
        }

        return $result;
    }

    private function format_bill_summary(array $bill)
    {
        $account_ids = [];

        if (isset($bill['account_ids']) && $bill['account_ids'] !== '') {
            $parts = explode(',', (string) $bill['account_ids']);

            foreach ($parts as $part) {
                if (is_numeric($part)) {
                    $account_ids[] = (int) $part;
                }
            }
        }

        return [
            'id'           => isset($bill['id']) ? (int) $bill['id'] : null,
            'vendor_id'    => isset($bill['vendor']) ? (int) $bill['vendor'] : null,
            'expense_name' => $bill['expense_name'] ?? '',
            'reference_no' => $bill['reference_no'] ?? '',
            'date'         => $bill['date'] ?? null,
            'due_date'     => $bill['due_date'] ?? null,
            'status'       => isset($bill['status']) ? (int) $bill['status'] : null,
            'amount'       => isset($bill['amount']) ? (float) $bill['amount'] : 0.0,
            'currency'     => $bill['currency'] ?? null,
            'account_ids'  => $account_ids,
        ];
    }

    private function format_bill_detail($bill)
    {
        $note = '';

        if (isset($bill->note) && $bill->note !== '') {
            $note = strip_tags(preg_replace('/<br\s*\/?\>/i', "\n", $bill->note));
        }

        $debits = [];

        if (isset($bill->debit_account) && is_array($bill->debit_account)) {
            foreach ($bill->debit_account as $entry) {
                if (!isset($entry['account'], $entry['amount'])) {
                    continue;
                }

                $debits[] = [
                    'account_id' => (int) $entry['account'],
                    'amount'     => (float) $entry['amount'],
                ];
            }
        }

        $credits = [];

        if (isset($bill->credit_account) && is_array($bill->credit_account)) {
            foreach ($bill->credit_account as $entry) {
                if (!isset($entry['account'], $entry['amount'])) {
                    continue;
                }

                $credits[] = [
                    'account_id' => (int) $entry['account'],
                    'amount'     => (float) $entry['amount'],
                ];
            }
        }

        $items = [];

        if (isset($bill->bill_items) && is_array($bill->bill_items)) {
            foreach ($bill->bill_items as $item) {
                if (!isset($item['item_id'], $item['amount'])) {
                    continue;
                }

                $items[] = [
                    'item_id'     => (int) $item['item_id'],
                    'description' => $item['description'] ?? '',
                    'qty'         => isset($item['qty']) ? (float) $item['qty'] : 0.0,
                    'cost'        => isset($item['cost']) ? (float) $item['cost'] : 0.0,
                    'amount'      => (float) $item['amount'],
                ];
            }
        }

        return [
            'id'                   => isset($bill->id) ? (int) $bill->id : null,
            'vendor_id'            => isset($bill->vendor) ? (int) $bill->vendor : null,
            'expense_name'         => $bill->expense_name ?? '',
            'reference_no'         => $bill->reference_no ?? '',
            'note'                 => $note,
            'date'                 => $bill->date ?? null,
            'due_date'             => $bill->due_date ?? null,
            'status'               => isset($bill->status) ? (int) $bill->status : null,
            'approved'             => isset($bill->approved) ? (int) $bill->approved : 0,
            'date_paid'            => $bill->date_paid ?? null,
            'amount'               => isset($bill->amount) ? (float) $bill->amount : 0.0,
            'currency'             => $bill->currency ?? null,
            'paymentmode'          => isset($bill->paymentmode) ? (int) $bill->paymentmode : null,
            'billable'             => isset($bill->billable) ? (int) $bill->billable : null,
            'tax'                  => isset($bill->tax) ? (int) $bill->tax : null,
            'tax2'                 => isset($bill->tax2) ? (int) $bill->tax2 : null,
            'debits'               => $debits,
            'credits'              => $credits,
            'items'                => $items,
            'attachment'           => $bill->attachment ?? '',
            'attachment_filetype'  => $bill->filetype ?? '',
            'attachment_added_by'  => isset($bill->attachment_added_from) ? (int) $bill->attachment_added_from : null,
        ];
    }

    private function normalize_date_string($value)
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);

            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        if (is_numeric($value)) {
            return date('Y-m-d', (int) $value);
        }

        return null;
    }

    private function is_books_closed_for_date($date)
    {
        $close_the_books = (int) get_option('acc_close_the_books');

        if ($close_the_books !== 1) {
            return false;
        }

        $closing_date_option = get_option('acc_closing_date');

        if (!$closing_date_option) {
            return false;
        }

        $closing_date = strtotime($closing_date_option);
        $bill_date    = strtotime($date);
        $current_date = strtotime(date('Y-m-d'));

        if ($closing_date === false || $bill_date === false || $current_date === false) {
            return false;
        }

        if (($current_date < $closing_date && $bill_date < $current_date)
            || ($current_date >= $closing_date && $bill_date < $closing_date)) {
            return true;
        }

        return false;
    }

    private function parse_numeric_value($value)
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function format_money_value($value)
    {
        return number_format((float) $value, 2, '.', '');
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
