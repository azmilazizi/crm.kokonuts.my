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
    }

    public function accounts_get()
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        $search  = trim((string) $this->get('search'));
        $page    = $this->positive_int_from_query('page', 1);
        $perPage = $this->positive_int_from_query('per_page', 50);

        try {
            $accounts = $this->accounting_model->get_accounts('', [], false);
        } catch (\mysqli_sql_exception $exception) {
            log_message('error', 'Failed to load accounts for API response: ' . $exception->getMessage());

            $this->response([
                'status'  => false,
                'message' => 'Unable to load accounts with the current database schema.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        if ($search !== '') {
            $accounts = array_values(array_filter($accounts, function ($account) use ($search) {
                $haystacks = [
                    isset($account['name']) ? $account['name'] : '',
                    isset($account['number']) ? $account['number'] : '',
                    isset($account['account_type_name']) ? $account['account_type_name'] : '',
                    isset($account['detail_type_name']) ? $account['detail_type_name'] : '',
                ];

                foreach ($haystacks as $value) {
                    if ($value !== '' && stripos($value, $search) !== false) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $pagination = $this->paginate_array($accounts, $page, $perPage);

        $this->response([
            'status'      => true,
            'result'      => $pagination['items'],
            'pagination'  => $pagination['meta'],
        ], self::HTTP_OK);
    }

    public function accounts_post()
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

    public function bills_post()
    {
        if (!$this->ensureStaffAuthenticated()) {
            return;
        }

        if (function_exists('has_permission') && !has_permission('accounting_bills', '', 'create')) {
            $this->response([
                'status'  => false,
                'message' => _l('access_denied'),
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $payload = $this->getRequestPayload();

        try {
            $vendorId   = $this->requirePositiveInt($payload['vendor_id'] ?? ($payload['vendor'] ?? null), 'vendor');
            $billDate   = $this->parseDateField($payload, 'date', true);
            $dueDate    = $this->parseDateField($payload, 'due_date', false) ?? $billDate;
            $currencyId = $this->resolveCurrencyId($payload['currency_id'] ?? ($payload['currency'] ?? null));
            $lineData   = $this->buildBillLines($payload);
        } catch (InvalidArgumentException $exception) {
            $this->respondBadRequest($exception->getMessage());

            return;
        }

        $expenseName = trim((string) ($payload['expense_name'] ?? ''));
        $reference   = trim((string) ($payload['reference_no'] ?? ($payload['reference'] ?? '')));
        $note        = (string) ($payload['note'] ?? '');
        $billable    = isset($payload['billable']) ? ((bool) $payload['billable'] ? 1 : 0) : 0;
        $categoryId  = $this->resolveOptionalPositiveInt($payload['category_id'] ?? ($payload['category'] ?? null)) ?? 0;
        $paymentMode = $this->resolveOptionalPositiveInt($payload['payment_mode'] ?? ($payload['paymentmode'] ?? null)) ?? 0;

        $data = [
            'vendor'          => $vendorId,
            'date'            => $billDate->format('Y-m-d'),
            'due_date'        => $dueDate->format('Y-m-d'),
            'expense_name'    => $expenseName,
            'reference_no'    => $reference,
            'note'            => $note,
            'currency'        => $currencyId,
            'amount'          => $this->formatDecimal($lineData['total_amount']),
            'paymentmode'     => $paymentMode,
            'billable'        => $billable,
            'category'        => $categoryId,
            'tax'             => 0,
            'tax2'            => 0,
            'debit_account'   => $lineData['debit_account'],
            'debit_amount'    => $lineData['debit_amount'],
            'credit_account'  => $lineData['credit_account'],
            'credit_amount'   => $lineData['credit_amount'],
            'item_id'         => $lineData['item_id'],
            'item_description'=> $lineData['item_description'],
            'item_qty'        => $lineData['item_qty'],
            'item_cost'       => $lineData['item_cost'],
            'item_amount'     => $lineData['item_amount'],
        ];

        $billId = $this->accounting_model->add_bill($data);

        if (!$billId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create bill with the provided data.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $bill = $this->accounting_model->get_bill($billId);

        if (!$bill) {
            $this->response([
                'status'  => true,
                'result'  => ['id' => $billId],
            ], self::HTTP_CREATED);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->transformBill($bill),
        ], self::HTTP_CREATED);
    }

    public function account_get($id = null)
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

    private function getRequestPayload(): array
    {
        $raw = trim((string) $this->input->raw_input_stream);

        if ($raw !== '') {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $payload = $this->post();

        if (is_array($payload) && $payload !== []) {
            return $payload;
        }

        return is_array($_POST) ? $_POST : [];
    }

    private function ensureStaffAuthenticated()
    {
        $token = $this->authenticate_token();

        if ($token === false || !isset($token['data']) || !is_object($token['data'])) {
            return false;
        }

        $payload = $token['data'];

        if (!isset($payload->staffid) || (int) $payload->staffid <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Authenticated staff context is required for this request.',
            ], self::HTTP_UNAUTHORIZED);

            return false;
        }

        $staffId = (int) $payload->staffid;

        $currentSessionId = (int) ($this->session->userdata('staff_user_id') ?? 0);

        if ($currentSessionId !== $staffId || !$this->session->userdata('staff_logged_in')) {
            $this->session->set_userdata([
                'staff_user_id'   => $staffId,
                'staff_logged_in' => true,
            ]);
        }

        if (!isset($this->authenticatedStaff) || (int) $this->authenticatedStaff->staffid !== $staffId) {
            $this->load->model('staff_model');

            $staff = $this->staff_model->get($staffId);

            if (!$staff || (int) $staff->active !== 1) {
                $this->session->unset_userdata('staff_logged_in');
                $this->session->unset_userdata('staff_user_id');

                $this->response([
                    'status'  => false,
                    'message' => 'Unable to resolve the authenticated staff member.',
                ], self::HTTP_UNAUTHORIZED);

                return false;
            }

            $GLOBALS['current_user'] = $staff;
            $this->authenticatedStaff = $staff;
        }

        return $payload;
    }

    private function respondBadRequest(string $message): void
    {
        $this->response([
            'status'  => false,
            'message' => $message,
        ], self::HTTP_BAD_REQUEST);
    }

    private function parseDateField(array $payload, string $key, bool $required): ?DateTimeImmutable
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === null) {
            if ($required) {
                throw new InvalidArgumentException(sprintf('The %s field is required and must use the YYYY-MM-DD format.', $key));
            }

            return null;
        }

        $value = trim((string) $payload[$key]);
        $date  = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if (!$date) {
            throw new InvalidArgumentException(sprintf('The %s field must use the YYYY-MM-DD format.', $key));
        }

        return $date;
    }

    private function requirePositiveInt($value, string $label): int
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException(ucfirst($label) . ' identifier is required.');
        }

        if (!preg_match('/^\d+$/', (string) $value)) {
            throw new InvalidArgumentException(sprintf('The %s identifier must be a positive integer.', $label));
        }

        $intValue = (int) $value;

        if ($intValue < 1) {
            throw new InvalidArgumentException(sprintf('The %s identifier must be greater than or equal to 1.', $label));
        }

        return $intValue;
    }

    private function resolveOptionalPositiveInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!preg_match('/^\d+$/', (string) $value)) {
            throw new InvalidArgumentException('Identifiers must be positive integers.');
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function resolveCurrencyId($raw): int
    {
        if ($raw === null || $raw === '') {
            $base = function_exists('get_base_currency') ? get_base_currency() : null;

            return $base ? (int) $base->id : 0;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        $currency = $this->db
            ->where('name', $raw)
            ->or_where('symbol', $raw)
            ->get(db_prefix() . 'currencies')
            ->row();

        if ($currency) {
            return (int) $currency->id;
        }

        throw new InvalidArgumentException('Unable to resolve the provided currency.');
    }

    private function buildBillLines(array $payload): array
    {
        $debitsRaw  = $payload['debit_lines'] ?? ($payload['debits'] ?? []);
        $creditsRaw = $payload['credit_lines'] ?? ($payload['credits'] ?? []);
        $itemsRaw   = $payload['items'] ?? [];

        if (!is_array($debitsRaw) || $debitsRaw === []) {
            throw new InvalidArgumentException('At least one debit line is required.');
        }

        if (!is_array($creditsRaw) || $creditsRaw === []) {
            throw new InvalidArgumentException('At least one credit line is required.');
        }

        if (!is_array($itemsRaw)) {
            throw new InvalidArgumentException('Items must be provided as an array.');
        }

        $debitAccounts  = [];
        $debitAmounts   = [];
        $debitTotal     = 0.0;
        foreach ($debitsRaw as $index => $line) {
            if (!is_array($line)) {
                throw new InvalidArgumentException('Each debit line must be an object.');
            }

            $accountId = $this->requirePositiveInt($line['account_id'] ?? ($line['account'] ?? null), 'debit account');
            $amount    = $this->resolveNumeric($line['amount'] ?? null, sprintf('debit amount #%d', $index + 1), false, true);

            $debitAccounts[] = $accountId;
            $debitAmounts[]  = $this->formatDecimal($amount);
            $debitTotal     += $amount;
        }

        $creditAccounts = [];
        $creditAmounts  = [];
        $creditTotal    = 0.0;
        foreach ($creditsRaw as $index => $line) {
            if (!is_array($line)) {
                throw new InvalidArgumentException('Each credit line must be an object.');
            }

            $accountId = $this->requirePositiveInt($line['account_id'] ?? ($line['account'] ?? null), 'credit account');
            $amount    = $this->resolveNumeric($line['amount'] ?? null, sprintf('credit amount #%d', $index + 1), false, true);

            $creditAccounts[] = $accountId;
            $creditAmounts[]  = $this->formatDecimal($amount);
            $creditTotal     += $amount;
        }

        if (abs($debitTotal - $creditTotal) > 0.01) {
            throw new InvalidArgumentException('Debits and credits must be balanced.');
        }

        $itemIds          = [];
        $itemDescriptions = [];
        $itemQuantities   = [];
        $itemCosts        = [];
        $itemAmounts      = [];
        $itemsTotal       = 0.0;

        foreach ($itemsRaw as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Each bill item must be an object.');
            }

            $itemId      = $this->requirePositiveInt($item['item_id'] ?? ($item['id'] ?? null), 'item');
            $description = (string) ($item['description'] ?? '');
            $quantity    = $this->resolveNumeric($item['quantity'] ?? $item['qty'] ?? null, sprintf('item quantity #%d', $index + 1), false, true);
            $cost        = $this->resolveNumeric($item['cost'] ?? $item['unit_price'] ?? null, sprintf('item cost #%d', $index + 1), false, true);

            $amount = $quantity * $cost;

            $itemIds[]          = $itemId;
            $itemDescriptions[] = $description;
            $itemQuantities[]   = $this->formatDecimal($quantity, 4);
            $itemCosts[]        = $this->formatDecimal($cost);
            $itemAmounts[]      = $this->formatDecimal($amount);
            $itemsTotal        += $amount;
        }

        $totalAmount = max($debitTotal, $creditTotal);

        if ($itemsTotal > 0) {
            $totalAmount += $itemsTotal;
        }

        return [
            'debit_account'   => $debitAccounts,
            'debit_amount'    => $debitAmounts,
            'credit_account'  => $creditAccounts,
            'credit_amount'   => $creditAmounts,
            'item_id'         => $itemIds,
            'item_description'=> $itemDescriptions,
            'item_qty'        => $itemQuantities,
            'item_cost'       => $itemCosts,
            'item_amount'     => $itemAmounts,
            'total_amount'    => $totalAmount,
        ];
    }

    private function resolveNumeric($value, string $label, bool $allowZero, bool $positive, bool $allowNegative = false): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('The %s value must be numeric.', $label));
        }

        $number = (float) $value;

        if (!$allowNegative && $number < 0) {
            throw new InvalidArgumentException(sprintf('The %s value cannot be negative.', $label));
        }

        if ($positive && !$allowZero && $number <= 0) {
            throw new InvalidArgumentException(sprintf('The %s value must be greater than 0.', $label));
        }

        if ($positive && $allowZero && $number < 0) {
            throw new InvalidArgumentException(sprintf('The %s value cannot be negative.', $label));
        }

        return $number;
    }

    private function transformBill($bill): array
    {
        $note = isset($bill->note) ? trim(strip_tags($bill->note)) : '';

        return [
            'id'            => isset($bill->id) ? (int) $bill->id : null,
            'vendor_id'     => isset($bill->vendor) ? (int) $bill->vendor : null,
            'expense_name'  => $bill->expense_name ?? null,
            'reference_no'  => $bill->reference_no ?? null,
            'note'          => $note,
            'amount'        => isset($bill->amount) ? (float) $bill->amount : null,
            'currency'      => isset($bill->currency) ? (int) $bill->currency : null,
            'status'        => isset($bill->status) ? (int) $bill->status : null,
            'billable'      => isset($bill->billable) ? (int) $bill->billable : null,
            'date'          => $bill->date ?? null,
            'due_date'      => $bill->due_date ?? null,
            'created_at'    => $bill->dateadded ?? null,
            'created_by'    => isset($bill->addedfrom) ? (int) $bill->addedfrom : null,
            'debit_lines'   => array_map([$this, 'transformBillMapping'], $bill->debit_account ?? []),
            'credit_lines'  => array_map([$this, 'transformBillMapping'], $bill->credit_account ?? []),
            'items'         => array_map([$this, 'transformBillItem'], $bill->bill_items ?? []),
        ];
    }

    private function transformBillMapping(array $line): array
    {
        return [
            'account_id' => isset($line['account']) ? (int) $line['account'] : null,
            'amount'     => isset($line['amount']) ? (float) $line['amount'] : null,
        ];
    }

    private function transformBillItem(array $item): array
    {
        return [
            'item_id'     => isset($item['item_id']) ? (int) $item['item_id'] : null,
            'description' => $item['description'] ?? null,
            'quantity'    => isset($item['qty']) ? (float) $item['qty'] : null,
            'cost'        => isset($item['cost']) ? (float) $item['cost'] : null,
            'amount'      => isset($item['amount']) ? (float) $item['amount'] : null,
        ];
    }

    private function formatDecimal($value, int $precision = 2): string
    {
        return number_format((float) $value, $precision, '.', '');
    }

    public function account_put($id = null)
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

        $result = $this->accounting_model->delete_account((int) $id);

        if ($result === 'have_transaction') {
            $this->response([
                'status'  => false,
                'message' => 'Account cannot be deleted because it has ledger activity.',
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

        $totalQuery = $this->build_bill_query($filters);
        $total      = $totalQuery->count_all_results();

        $dataQuery = $this->build_bill_query($filters);
        $data      = $dataQuery->order_by('e.date', 'DESC')->limit($perPage, $offset)->get()->result_array();

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
        $builder->where('e.is_bill', 1);

        if ($filters['status'] !== null) {
            switch ($filters['status']) {
                case 'approved':
                    $builder->where('e.approved', 1);
                    $builder->where('e.voided', 0);
                    $builder->where('e.status !=', 2);
                    break;
                case 'unapproved':
                case 'draft':
                case 'unpaid':
                    $builder->where('e.approved', 0);
                    break;
                case 'paid':
                    $builder->group_start();
                    $builder->where_in('e.status', [2, 3]);
                    $builder->or_where('e.voided', 1);
                    $builder->group_end();
                    break;
                case 'voided':
                    $builder->where('e.voided', 1);
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
