<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_accounting extends API_Controller
{
    /**
     * Cached staff context resolved from the bearer token.
     *
     * @var object|null
     */
    private $authenticatedStaff = null;
    public function __construct()
    {
        $this->module_language_file      = 'accounting';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->model('accounting_model');
    }

    public function accounts_get()
    {
        if (!$this->ensureStaffAuthenticated()) {
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
        if (!$this->ensureStaffAuthenticated()) {
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
        if (!$this->ensureStaffAuthenticated()) {
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
        if (!$this->ensureStaffAuthenticated()) {
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
        if (!$this->ensureStaffAuthenticated()) {
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
