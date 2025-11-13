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

    public function bills_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $where = ['is_bill' => 1];

        $status = $this->get('status');
        if ($status !== null && $status !== '') {
            $where['status'] = (int) $status;
        }

        $vendor = $this->get('vendor');
        if ($vendor !== null && $vendor !== '') {
            $where['vendor'] = (int) $vendor;
        }

        $bills = $this->accounting_model->get_bill('', $where);

        $result = array_map(function ($bill) {
            return $this->convert_bill_output($bill);
        }, $bills);

        $this->response([
            'status' => true,
            'result' => $result,
        ], self::HTTP_OK);
    }

    public function bills_post()
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

        $normalized = $this->prepare_bill_payload($payload, false);

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $billId = $this->accounting_model->add_bill($normalized['data']);

        if (!$billId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create bill with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $bill = $this->accounting_model->get_bill($billId);

        $this->response([
            'status' => true,
            'result' => $this->convert_bill_output($bill),
        ], self::HTTP_CREATED);
    }

    public function bill_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $bill = $this->accounting_model->get_bill((int) $id);

        if (!$bill || (int) ($bill->is_bill ?? 0) !== 1) {
            $this->response([
                'status'  => false,
                'message' => 'Bill not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->convert_bill_output($bill),
        ], self::HTTP_OK);
    }

    public function bill_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $billId = (int) $id;
        $bill   = $this->accounting_model->get_bill($billId);

        if (!$bill || (int) ($bill->is_bill ?? 0) !== 1) {
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

        $normalized = $this->prepare_bill_payload($payload, true, $bill);

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->accounting_model->update_bill($normalized['data'], $billId);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Bill update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $updatedBill = $this->accounting_model->get_bill($billId);

        $this->response([
            'status' => true,
            'result' => $this->convert_bill_output($updatedBill),
        ], self::HTTP_OK);
    }

    public function bill_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $billId = (int) $id;
        $bill   = $this->accounting_model->get_bill($billId);

        if (!$bill || (int) ($bill->is_bill ?? 0) !== 1) {
            $this->response([
                'status'  => false,
                'message' => 'Bill not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $result = $this->accounting_model->delete_bill($billId);

        if ($result === 'paid') {
            $this->response([
                'status'  => false,
                'message' => 'Cannot delete bill because it has payments or checks associated.',
            ], self::HTTP_CONFLICT);

            return;
        }

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Bill not found or already deleted.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Bill deleted successfully.',
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

    private function prepare_bill_payload(array $input, bool $is_update, $existingBill = null)
    {
        $errors = [];
        $data   = [];

        $dateSource = array_key_exists('date', $input) ? $input['date'] : ($existingBill ? $existingBill->date : null);
        $dateValue  = $this->normalize_date($dateSource);

        if ($dateValue === null) {
            $errors[] = 'Field "date" is required and must be a valid date (YYYY-MM-DD).';
        } else {
            $data['date'] = $dateValue;
        }

        $dueDateSource = array_key_exists('due_date', $input) ? $input['due_date'] : ($existingBill ? $existingBill->due_date : null);
        $dueDateValue  = $this->normalize_date($dueDateSource);

        if ($dueDateValue === null) {
            $errors[] = 'Field "due_date" is required and must be a valid date (YYYY-MM-DD).';
        } else {
            $data['due_date'] = $dueDateValue;
        }

        $noteSource = array_key_exists('note', $input)
            ? $input['note']
            : ($existingBill ? $this->convert_breaks_to_newlines($existingBill->note) : '');
        $data['note'] = is_string($noteSource) ? $noteSource : '';

        if (array_key_exists('vendor', $input)) {
            if ($input['vendor'] === null || $input['vendor'] === '') {
                $errors[] = 'Field "vendor" is required.';
            } else {
                $data['vendor'] = (int) $input['vendor'];
            }
        } elseif (!$is_update) {
            $errors[] = 'Field "vendor" is required.';
        } elseif ($existingBill && isset($existingBill->vendor)) {
            $data['vendor'] = (int) $existingBill->vendor;
        }

        $amountSet = false;

        if (array_key_exists('amount', $input)) {
            $data['amount'] = $this->normalize_decimal($input['amount']);
            $amountSet      = true;
        } elseif ($existingBill && isset($existingBill->amount)) {
            $data['amount'] = $this->normalize_decimal($existingBill->amount);
            $amountSet      = true;
        }

        $stringFields = ['expense_name', 'reference_no', 'number', 'terms'];
        foreach ($stringFields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]);
            }
        }

        $intFields = ['category', 'paymentmode', 'tax', 'tax2', 'clientid', 'project_id', 'department', 'currency', 'paymentmethod', 'status', 'approved', 'recurring', 'repeat_every', 'cycles'];
        foreach ($intFields as $field) {
            if (array_key_exists($field, $input) && $input[$field] !== '' && $input[$field] !== null) {
                $data[$field] = (int) $input[$field];
            }
        }

        $boolFields = ['billable', 'create_invoice_billable', 'send_email_to_customer'];
        foreach ($boolFields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->interpret_boolean($input[$field]) ? 1 : 0;
            }
        }

        $decimalFields = ['sub_total', 'total_tax', 'discount_total', 'adjustment'];
        foreach ($decimalFields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->normalize_decimal($input[$field]);
            }
        }

        [$debitLines, $debitErrors]   = $this->normalize_bill_ledger_lines($input, 'debit', $existingBill, $is_update);
        [$creditLines, $creditErrors] = $this->normalize_bill_ledger_lines($input, 'credit', $existingBill, $is_update);

        if (!$amountSet && !$is_update) {
            $sum = 0.0;

            if (count($debitLines['amounts']) > 0) {
                foreach ($debitLines['amounts'] as $lineAmount) {
                    $sum += $this->normalize_decimal($lineAmount);
                }
            } elseif (count($creditLines['amounts']) > 0) {
                foreach ($creditLines['amounts'] as $lineAmount) {
                    $sum += $this->normalize_decimal($lineAmount);
                }
            }

            if ($sum > 0) {
                $data['amount'] = $sum;
                $amountSet      = true;
            }
        }

        if (!$amountSet && !$is_update) {
            $errors[] = 'Field "amount" is required.';
        }

        $data['debit_account']  = $debitLines['accounts'];
        $data['debit_amount']   = $debitLines['amounts'];
        $data['credit_account'] = $creditLines['accounts'];
        $data['credit_amount']  = $creditLines['amounts'];

        [$itemData, $itemErrors] = $this->normalize_bill_items($input, $existingBill, $is_update);

        $data   = array_merge($data, $itemData);
        $errors = array_merge($errors, $debitErrors, $creditErrors, $itemErrors);

        return [
            'data'   => $data,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function normalize_bill_ledger_lines(array $input, string $type, $existingBill = null, bool $is_update = false)
    {
        $accounts = [];
        $amounts  = [];
        $errors   = [];

        $lineKey = $type . '_lines';

        if (isset($input[$lineKey]) && is_array($input[$lineKey])) {
            foreach ($input[$lineKey] as $index => $line) {
                if (!is_array($line)) {
                    continue;
                }

                $account = $line['account'] ?? ($line['account_id'] ?? null);
                $amount  = $line['amount'] ?? null;

                if ($account === null || $account === '') {
                    continue;
                }

                if ($amount === null || $amount === '') {
                    $errors[] = sprintf('Missing amount for %s line at position %d.', $type, $index + 1);
                    continue;
                }

                $accounts[] = (int) $account;
                $amounts[]  = $this->format_decimal_string($amount);
            }
        } elseif (isset($input[$type . '_account']) && is_array($input[$type . '_account'])) {
            $accountsRaw = $input[$type . '_account'];
            $amountsRaw  = isset($input[$type . '_amount']) && is_array($input[$type . '_amount']) ? $input[$type . '_amount'] : [];

            foreach ($accountsRaw as $index => $account) {
                if ($account === null || $account === '') {
                    continue;
                }

                $amount = $amountsRaw[$index] ?? null;

                if ($amount === null || $amount === '') {
                    $errors[] = sprintf('Missing amount for %s line at position %d.', $type, $index + 1);
                    continue;
                }

                $accounts[] = (int) $account;
                $amounts[]  = $this->format_decimal_string($amount);
            }
        } elseif ($is_update && $existingBill) {
            $field = $type === 'debit' ? 'debit_account' : 'credit_account';

            if (isset($existingBill->{$field}) && is_array($existingBill->{$field})) {
                foreach ($existingBill->{$field} as $line) {
                    if (!isset($line['account'])) {
                        continue;
                    }

                    $accounts[] = (int) $line['account'];
                    $amounts[]  = $this->format_decimal_string($line['amount'] ?? 0);
                }
            }
        }

        return [
            [
                'accounts' => $accounts,
                'amounts'  => $amounts,
            ],
            $errors,
        ];
    }

    private function normalize_bill_items(array $input, $existingBill = null, bool $is_update = false)
    {
        $items = [
            'item_id'          => [],
            'item_description' => [],
            'item_qty'         => [],
            'item_cost'        => [],
            'item_amount'      => [],
        ];

        $errors = [];

        $source = null;
        $mode   = null;

        if (isset($input['items']) && is_array($input['items'])) {
            $source = $input['items'];
            $mode   = 'objects';
        } elseif (isset($input['item_lines']) && is_array($input['item_lines'])) {
            $source = $input['item_lines'];
            $mode   = 'objects';
        } elseif (isset($input['item_id']) && is_array($input['item_id'])) {
            $source = $input;
            $mode   = 'arrays';
        } elseif ($is_update && $existingBill && isset($existingBill->bill_items) && is_array($existingBill->bill_items)) {
            $source = $existingBill->bill_items;
            $mode   = 'existing';
        }

        if ($mode === 'objects') {
            foreach ($source as $index => $line) {
                if (!is_array($line)) {
                    continue;
                }

                $itemId      = $line['item_id'] ?? ($line['id'] ?? null);
                $qty         = $line['qty'] ?? ($line['quantity'] ?? 1);
                $cost        = $line['cost'] ?? ($line['rate'] ?? null);
                $amount      = $line['amount'] ?? null;
                $description = isset($line['description']) ? (string) $line['description'] : '';

                if ($itemId === null || $itemId === '') {
                    continue;
                }

                if ($amount === null) {
                    $amount = $this->normalize_decimal($qty) * $this->normalize_decimal($cost);
                }

                if ($cost === null) {
                    $normalizedQty = $this->normalize_decimal($qty);

                    $cost = $normalizedQty != 0.0 ? ($this->normalize_decimal($amount) / $normalizedQty) : 0;
                }

                $items['item_id'][]          = (int) $itemId;
                $items['item_description'][] = $description;
                $items['item_qty'][]         = $this->format_decimal_string($qty);
                $items['item_cost'][]        = $this->format_decimal_string($cost);
                $items['item_amount'][]      = $this->format_decimal_string($amount);
            }
        } elseif ($mode === 'arrays') {
            $ids          = $source['item_id'];
            $descriptions = isset($source['item_description']) && is_array($source['item_description']) ? $source['item_description'] : [];
            $qtys         = isset($source['item_qty']) && is_array($source['item_qty']) ? $source['item_qty'] : [];
            $costs        = isset($source['item_cost']) && is_array($source['item_cost']) ? $source['item_cost'] : [];
            $amounts      = isset($source['item_amount']) && is_array($source['item_amount']) ? $source['item_amount'] : [];

            foreach ($ids as $index => $itemId) {
                if ($itemId === null || $itemId === '') {
                    continue;
                }

                $qtyValue    = $qtys[$index] ?? 1;
                $costValue   = $costs[$index] ?? null;
                $amountValue = $amounts[$index] ?? null;

                if ($amountValue === null) {
                    $amountValue = $this->normalize_decimal($qtyValue) * $this->normalize_decimal($costValue ?? 0);
                }

                if ($costValue === null) {
                    $normalizedQty = $this->normalize_decimal($qtyValue);

                    $costValue = $normalizedQty != 0.0 ? ($this->normalize_decimal($amountValue) / $normalizedQty) : 0;
                }

                $items['item_id'][]          = (int) $itemId;
                $items['item_description'][] = isset($descriptions[$index]) ? (string) $descriptions[$index] : '';
                $items['item_qty'][]         = $this->format_decimal_string($qtyValue);
                $items['item_cost'][]        = $this->format_decimal_string($costValue);
                $items['item_amount'][]      = $this->format_decimal_string($amountValue);
            }
        } elseif ($mode === 'existing') {
            foreach ($source as $line) {
                $items['item_id'][]          = isset($line['item_id']) ? (int) $line['item_id'] : 0;
                $items['item_description'][] = isset($line['description']) ? (string) $line['description'] : '';
                $items['item_qty'][]         = $this->format_decimal_string($line['qty'] ?? 0);
                $items['item_cost'][]        = $this->format_decimal_string($line['cost'] ?? 0);
                $items['item_amount'][]      = $this->format_decimal_string($line['amount'] ?? 0);
            }
        }

        return [$items, $errors];
    }

    private function convert_bill_output($bill)
    {
        if ($bill === null) {
            return null;
        }

        if (is_object($bill)) {
            $bill = json_decode(json_encode($bill), true);
        }

        if (!is_array($bill)) {
            return $bill;
        }

        if (isset($bill['note'])) {
            $bill['note'] = $this->convert_breaks_to_newlines($bill['note']);
        }

        if (isset($bill['date'])) {
            $normalizedDate = $this->normalize_date($bill['date']);
            if ($normalizedDate !== null) {
                $bill['date'] = $normalizedDate;
            }
        }

        if (isset($bill['due_date'])) {
            $normalizedDueDate = $this->normalize_date($bill['due_date']);
            if ($normalizedDueDate !== null) {
                $bill['due_date'] = $normalizedDueDate;
            }
        }

        if (isset($bill['amount'])) {
            $bill['amount'] = $this->normalize_decimal($bill['amount']);
        }

        foreach (['debit_account', 'credit_account'] as $field) {
            if (!isset($bill[$field]) || !is_array($bill[$field])) {
                continue;
            }

            foreach ($bill[$field] as &$line) {
                if (isset($line['account'])) {
                    $line['account'] = (int) $line['account'];
                }

                if (isset($line['amount'])) {
                    $line['amount'] = $this->normalize_decimal($line['amount']);
                }
            }
            unset($line);
        }

        if (isset($bill['bill_items']) && is_array($bill['bill_items'])) {
            foreach ($bill['bill_items'] as &$item) {
                if (isset($item['item_id'])) {
                    $item['item_id'] = (int) $item['item_id'];
                }

                if (isset($item['qty'])) {
                    $item['qty'] = $this->normalize_decimal($item['qty']);
                }

                if (isset($item['cost'])) {
                    $item['cost'] = $this->normalize_decimal($item['cost']);
                }

                if (isset($item['amount'])) {
                    $item['amount'] = $this->normalize_decimal($item['amount']);
                }
            }
            unset($item);
        }

        return $bill;
    }

    private function convert_breaks_to_newlines($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        return preg_replace('/<br\s*\/?>(\r\n)?/i', PHP_EOL, $decoded);
    }

    private function format_decimal_string($value)
    {
        $normalized = $this->normalize_decimal($value);

        return number_format($normalized, 2, '.', '');
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
