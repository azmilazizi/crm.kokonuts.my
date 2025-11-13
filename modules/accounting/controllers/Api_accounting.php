<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_accounting extends API_Controller
{
    /** @var object|null */
    private $authenticatedStaff = null;

    /** @var array<string, bool> */
    private $expenseColumnCache = [];

    public function __construct()
    {
        $this->module_language_file      = 'accounting';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('accounting_model');
        $this->load->model('accounting/accounts_api_model', 'accounts_api_model');
    }

    public function account_transactions_get($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $limit  = $this->positive_int_from_query('limit', 50);
        $offset = $this->get('offset');
        $offset = is_numeric($offset) ? (int) $offset : 0;
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
        if (!$this->ensure_staff_context()) {
            return;
        }

        $filters = [
            'status'     => $this->sanitize_status($this->get('status')),
            'vendor_ids' => $this->extract_ids($this->get('vendor_id')),
            'search'     => trim((string) $this->get('search')),
            'from_date'  => $this->normalize_date((string) $this->get('from')),
            'to_date'    => $this->normalize_date((string) $this->get('to')),
        ];

        $page    = $this->positive_int_from_query('page', 1);
        $perPage = $this->positive_int_from_query('per_page', 20);
        $offset  = ($page - 1) * $perPage;

        try {
            $totalQuery = $this->build_bill_query($filters);
            $total      = $totalQuery->count_all_results();

            $dataQuery = $this->build_bill_query($filters);
            $data      = $dataQuery->order_by('e.date', 'DESC')->limit($perPage, $offset)->get()->result_array();
        } catch (\mysqli_sql_exception $exception) {
            log_message('error', 'Failed to load bills for API response: ' . $exception->getMessage());

            $this->response([
                'status'  => false,
                'message' => 'Unable to load bills with the current database schema.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        $this->response([
            'status' => true,
            'result' => $data,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ],
        ], self::HTTP_OK);
    }

    public function bills_post()
    {
        if (!$this->ensure_staff_context()) {
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

        $normalized = $this->prepare_bill_payload($payload);

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

        $bill = $this->accounting_model->get_bill($billId, ['is_bill' => 1]);

        $this->response([
            'status' => true,
            'result' => $bill,
        ], self::HTTP_CREATED);
    }

    public function bill_get($id = null)
    {
        if (!$this->ensure_staff_context()) {
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
            'result' => $bill,
        ], self::HTTP_OK);
    }

    public function bill_put($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->accounting_model->get_bill((int) $id, ['is_bill' => 1]);

        if (!$existing) {
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

        $normalized = $this->prepare_bill_payload($payload);

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->accounting_model->update_bill($normalized['data'], (int) $id);

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
            'result' => $bill,
        ], self::HTTP_OK);
    }

    public function bill_delete($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $result = $this->accounting_model->delete_bill((int) $id);

        if ($result === 'paid') {
            $this->response([
                'status'  => false,
                'message' => 'Bill cannot be deleted because it has payments or checks attached.',
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

    private function build_bill_query(array $filters)
    {
        $builder = $this->db->from(db_prefix() . 'expenses as e');
        $builder->select('e.*, v.company as vendor_name');
        $builder->join(db_prefix() . 'pur_vendor as v', 'v.userid = e.vendor', 'left');

        if ($this->expenses_column_exists('is_bill')) {
            $builder->where('e.is_bill', 1);
        }

        $hasApproved = $this->expenses_column_exists('approved');
        $hasVoided   = $this->expenses_column_exists('voided');
        $hasStatus   = $this->expenses_column_exists('status');

        if ($filters['status'] !== null) {
            switch ($filters['status']) {
                case 'approved':
                    if ($hasApproved) {
                        $builder->where('e.approved', 1);
                    }

                    if ($hasVoided) {
                        $builder->where('e.voided', 0);
                    }

                    if ($hasStatus) {
                        $builder->where('e.status !=', 2);
                    }
                    break;
                case 'unapproved':
                case 'draft':
                case 'unpaid':
                    if ($hasApproved) {
                        $builder->where('e.approved', 0);
                    }
                    break;
                case 'paid':
                    if ($hasStatus) {
                        $builder->group_start();
                        $builder->where_in('e.status', [2, 3]);

                        if ($hasVoided) {
                            $builder->or_where('e.voided', 1);
                        }

                        $builder->group_end();
                    } elseif ($hasVoided) {
                        $builder->where('e.voided', 1);
                    }
                    break;
                case 'voided':
                    if ($hasVoided) {
                        $builder->where('e.voided', 1);
                    }
                    break;
            }
        }

        if (!empty($filters['vendor_ids'])) {
            $builder->where_in('e.vendor', $filters['vendor_ids']);
        }

        if ($filters['search'] !== '') {
            $builder->group_start();
            $builder->like('v.company', $filters['search']);
            $builder->or_like('e.reference_no', $filters['search']);
            $builder->or_like('e.expense_name', $filters['search']);
            $builder->group_end();
        }

        if ($filters['from_date'] !== null) {
            $builder->where('e.date >=', $filters['from_date']);
        }

        if ($filters['to_date'] !== null) {
            $builder->where('e.date <=', $filters['to_date']);
        }

        return $builder;
    }

    private function prepare_bill_payload(array $input)
    {
        $errors = [];

        $vendorId   = isset($input['vendor_id']) ? (int) $input['vendor_id'] : 0;
        $categoryId = isset($input['category_id']) ? (int) $input['category_id'] : 0;
        $date       = $this->normalize_date($input['date'] ?? '');
        $dueDate    = $this->normalize_date($input['due_date'] ?? '');

        if ($vendorId <= 0) {
            $errors[] = 'Vendor is required.';
        }

        if ($categoryId <= 0) {
            $errors[] = 'Category is required.';
        }

        if ($date === null) {
            $errors[] = 'A valid bill date is required.';
        }

        if ($dueDate === null) {
            $errors[] = 'A valid due date is required.';
        }

        $debits  = isset($input['debits']) && is_array($input['debits']) ? $input['debits'] : [];
        $credits = isset($input['credits']) && is_array($input['credits']) ? $input['credits'] : [];

        if ($debits === []) {
            $errors[] = 'At least one debit entry is required.';
        }

        if ($credits === []) {
            $errors[] = 'At least one credit entry is required.';
        }

        $debitAccounts  = [];
        $debitAmounts   = [];
        $creditAccounts = [];
        $creditAmounts  = [];
        $debitTotal     = 0.0;
        $creditTotal    = 0.0;

        foreach ($debits as $row) {
            $accountId = isset($row['account_id']) ? (int) $row['account_id'] : 0;
            $amount    = isset($row['amount']) ? (float) $row['amount'] : 0.0;

            if ($accountId <= 0 || $amount <= 0) {
                continue;
            }

            $debitAccounts[] = $accountId;
            $debitAmounts[]  = $this->format_money_value($amount);
            $debitTotal     += $amount;
        }

        foreach ($credits as $row) {
            $accountId = isset($row['account_id']) ? (int) $row['account_id'] : 0;
            $amount    = isset($row['amount']) ? (float) $row['amount'] : 0.0;

            if ($accountId <= 0 || $amount <= 0) {
                continue;
            }

            $creditAccounts[] = $accountId;
            $creditAmounts[]  = $this->format_money_value($amount);
            $creditTotal     += $amount;
        }

        if ($debitAccounts === [] || $creditAccounts === []) {
            $errors[] = 'Valid debit and credit entries are required.';
        }

        if (abs($debitTotal - $creditTotal) > 0.01) {
            $errors[] = 'Debits and credits must balance.';
        }

        $itemIds          = [];
        $itemDescriptions = [];
        $itemQuantities   = [];
        $itemCosts        = [];
        $itemAmounts      = [];

        if (isset($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $item) {
                $itemId = isset($item['item_id']) ? (int) $item['item_id'] : 0;
                $qty    = isset($item['quantity']) ? (float) $item['quantity'] : 0.0;
                $cost   = isset($item['cost']) ? (float) $item['cost'] : 0.0;
                $amount = isset($item['amount']) ? (float) $item['amount'] : ($qty * $cost);

                if ($itemId <= 0 || $qty <= 0 || $amount <= 0) {
                    continue;
                }

                $itemIds[]          = $itemId;
                $itemDescriptions[] = isset($item['description']) ? (string) $item['description'] : '';
                $itemQuantities[]   = $this->format_quantity_value($qty);
                $itemCosts[]        = $this->format_money_value($cost);
                $itemAmounts[]      = $this->format_money_value($amount);
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $billData = [
            'vendor'        => $vendorId,
            'category'      => $categoryId,
            'clientid'      => isset($input['client_id']) ? (int) $input['client_id'] : 0,
            'billable'      => !empty($input['billable']) ? 1 : 0,
            'paymentmode'   => isset($input['payment_mode']) && $input['payment_mode'] !== '' ? (int) $input['payment_mode'] : null,
            'reference_no'  => isset($input['reference_no']) ? trim((string) $input['reference_no']) : '',
            'expense_name'  => isset($input['expense_name']) ? trim((string) $input['expense_name']) : '',
            'note'          => isset($input['note']) ? (string) $input['note'] : '',
            'date'          => $date,
            'due_date'      => $dueDate,
            'amount'        => $this->format_money_value($debitTotal),
            'debit_account' => $debitAccounts,
            'debit_amount'  => $debitAmounts,
            'credit_account'=> $creditAccounts,
            'credit_amount' => $creditAmounts,
        ];

        if ($itemIds !== []) {
            $billData['item_id']          = $itemIds;
            $billData['item_description'] = $itemDescriptions;
            $billData['item_qty']         = $itemQuantities;
            $billData['item_cost']        = $itemCosts;
            $billData['item_amount']      = $itemAmounts;
        }

        if (isset($input['currency']) && $input['currency'] !== '') {
            $billData['currency'] = $input['currency'];
        }

        return ['data' => $billData, 'errors' => []];
    }

    private function ensure_staff_context()
    {
        $tokenData = $this->authenticate_token();

        if ($tokenData === false) {
            return false;
        }

        if ($this->authenticatedStaff !== null) {
            return $tokenData;
        }

        $token = $this->authorization_token->get_token();

        if (!empty($token) && $token !== 'Token is not defined.') {
            $staff = $this->db->where('token', $token)->get(db_prefix() . 'staff')->row();
            if ($staff) {
                $this->authenticatedStaff = $staff;
                $this->session->set_userdata([
                    'staff_logged_in' => true,
                    'staff_user_id'   => $staff->staffid,
                ]);
                $GLOBALS['current_user'] = $staff;
            }
        }

        return $tokenData;
    }

    private function paginate_array(array $items, int $page, int $perPage)
    {
        $total      = count($items);
        $page       = max(1, $page);
        $perPage    = max(1, $perPage);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;
        $offset     = ($page - 1) * $perPage;

        if ($offset >= $total && $totalPages > 0) {
            $page   = $totalPages;
            $offset = ($totalPages - 1) * $perPage;
        }

        $pagedItems = array_slice($items, $offset, $perPage);

        return [
            'items' => $pagedItems,
            'meta'  => [
                'total'       => $total,
                'page'        => $totalPages === 0 ? 1 : $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ],
        ];
    }

    private function positive_int_from_query($key, $default)
    {
        $value = $this->get($key);

        if (!is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : $default;
    }

    private function normalize_date($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function format_money_value($amount)
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function format_quantity_value($quantity)
    {
        return number_format((float) $quantity, 4, '.', '');
    }

    private function expenses_column_exists($column)
    {
        if ($column === '') {
            return false;
        }

        if (!array_key_exists($column, $this->expenseColumnCache)) {
            $this->expenseColumnCache[$column] = $this->db->field_exists($column, db_prefix() . 'expenses');
        }

        return $this->expenseColumnCache[$column];
    }

    private function extract_ids($input)
    {
        if ($input === null || $input === '') {
            return [];
        }

        if (is_array($input)) {
            return array_values(array_filter(array_map('intval', $input), function ($value) {
                return $value > 0;
            }));
        }

        $parts = explode(',', (string) $input);

        $ids = [];
        foreach ($parts as $part) {
            $value = (int) trim($part);
            if ($value > 0) {
                $ids[] = $value;
            }
        }

        return $ids;
    }

    private function sanitize_status($status)
    {
        if (!is_string($status) || $status === '') {
            return null;
        }

        $status = strtolower(trim($status));

        $allowed = ['approved', 'unapproved', 'draft', 'unpaid', 'paid', 'voided'];

        return in_array($status, $allowed, true) ? $status : null;
    }

    private function get_request_payload($method)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        $filters = [
            'status'     => $this->sanitize_status($this->get('status')),
            'vendor_ids' => $this->extract_ids($this->get('vendor_id')),
            'search'     => trim((string) $this->get('search')),
            'from_date'  => $this->normalize_date((string) $this->get('from')),
            'to_date'    => $this->normalize_date((string) $this->get('to')),
        ];

        $page    = $this->positive_int_from_query('page', 1);
        $perPage = $this->positive_int_from_query('per_page', 20);
        $offset  = ($page - 1) * $perPage;

        try {
            $totalQuery = $this->build_bill_query($filters);
            $total      = $totalQuery->count_all_results();

            $dataQuery = $this->build_bill_query($filters);
            $data      = $dataQuery->order_by('e.date', 'DESC')->limit($perPage, $offset)->get()->result_array();
        } catch (\mysqli_sql_exception $exception) {
            log_message('error', 'Failed to load bills for API response: ' . $exception->getMessage());

            $this->response([
                'status'  => false,
                'message' => 'Unable to load bills with the current database schema.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        $this->response([
            'status' => true,
            'result' => $data,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ],
        ], self::HTTP_OK);
    }
}
