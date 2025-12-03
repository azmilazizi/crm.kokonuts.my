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

        if (!class_exists('Accounting_model')) {
            if (!function_exists('module_dir_path')) {
                $this->load->helper('modules');
            }

            $accountingModelPath = module_dir_path('accounting', 'models/Accounting_model.php');

            if (is_file($accountingModelPath)) {
                require_once $accountingModelPath;
            }
        }

        $this->load->library('authorization_token');
        $this->load->model('accounting/accounting_model');
        $this->load->helper('accounting/accounting');
    }

    public function accounts_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $withBalances      = $this->boolean_from_query('with_balances', false);
        $showAccountNumber = $this->boolean_from_query('show_account_numbers', true);
        $activeFilter      = $this->get('active');
        $parentAccount     = $this->get('parent_account');

        $where = [];

        if ($activeFilter !== null) {
            $active = $this->interpret_boolean($activeFilter, null);

            if ($active !== null) {
                $where['active'] = $active ? 1 : 0;
            }
        }

        if ($parentAccount !== null) {
            if ($parentAccount === '' || !is_numeric($parentAccount)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid parent_account value provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where['parent_account'] = (int) $parentAccount;
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

    /**
     * Retrieves all account types.
     */
    public function account_type_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $accountTypes = method_exists($this->accounting_model, 'get_account_types')
            ? $this->accounting_model->get_account_types()
            : [];

        $this->response([
            'status' => true,
            'result' => $accountTypes,
        ], self::HTTP_OK);
    }

    /**
     * Retrieves account type details for a given account type.
     *
     * @param int|null $accountTypeId
     */
    public function account_type_detail_get($accountTypeId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($accountTypeId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account type identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $accountTypeId = (int) $accountTypeId;

        $details = [];

        if (method_exists($this->accounting_model, 'get_account_type_details')) {
            $details = $this->accounting_model->get_account_type_details();
        }

        $filteredDetails = array_values(array_filter((array) $details, function ($detail) use ($accountTypeId) {
            return isset($detail['account_type_id']) && (int) $detail['account_type_id'] === $accountTypeId;
        }));

        $this->response([
            'status' => true,
            'result' => $filteredDetails,
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

    public function item_account_mapping_post()
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

        $itemId      = isset($payload['item_id']) ? (int) $payload['item_id'] : 0;
        $expenseId   = isset($payload['expense_account']) ? (int) $payload['expense_account'] : 0;
        $inventoryId = isset($payload['inventory_asset_account']) ? (int) $payload['inventory_asset_account'] : 0;
        $incomeId    = isset($payload['income_account']) ? (int) $payload['income_account'] : null;

        if ($itemId <= 0 || $expenseId <= 0 || $inventoryId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'item_id, expense_account, and inventory_asset_account are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $item = $this->accounting_model->get_item_by_id($itemId);
        if (!$item) {
            $this->response([
                'status'  => false,
                'message' => 'Item not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $expenseAccount = $this->accounting_model->get_accounts($expenseId);
        $inventoryAccount = $this->accounting_model->get_accounts($inventoryId);
        $incomeAccount = $incomeId ? $this->accounting_model->get_accounts($incomeId) : null;

        if (!$expenseAccount || !$inventoryAccount || ($incomeId && !$incomeAccount)) {
            $this->response([
                'status'  => false,
                'message' => 'One or more provided accounts were not found.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $mapping = $this->accounting_model->upsert_item_account_mapping($itemId, $inventoryId, $expenseId, $incomeId);

        if (!$mapping) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to save mapping for the provided item.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $mapping,
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

    public function money_out_summary_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $startDate = $this->normalize_date($this->get('start_date') ?? $this->get('date_from'));
        $endDate   = $this->normalize_date($this->get('end_date') ?? $this->get('date_to'));

        if ($startDate === null || $endDate === null) {
            $this->response([
                'status'  => false,
                'message' => 'Both start_date and end_date parameters are required and must be valid dates.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if (strtotime($startDate) > strtotime($endDate)) {
            $this->response([
                'status'  => false,
                'message' => 'The start_date must be on or before end_date.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $vendorId = $this->get('vendor');
        if ($vendorId !== null && $vendorId !== '' && !ctype_digit((string) $vendorId)) {
            $this->response([
                'status'  => false,
                'message' => 'The vendor parameter must be a valid numeric identifier when provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $vendorId = $vendorId !== null && $vendorId !== '' ? (int) $vendorId : null;

        $poStatus = $this->get('po_status');
        $billStatus = $this->get('bill_status');

        $poFilters = [];
        if ($vendorId !== null) {
            $poFilters['po.vendor'] = $vendorId;
        }

        if ($poStatus !== null && $poStatus !== '') {
            $poFilters['po.status'] = (int) $poStatus;
        }

        $billFilters = ['is_bill' => 1];
        $expenseFilters = ['is_bill' => 0];

        if ($vendorId !== null) {
            $billFilters['vendor']    = $vendorId;
            $expenseFilters['vendor'] = $vendorId;
        }

        if ($billStatus !== null && $billStatus !== '') {
            $billFilters['status'] = (int) $billStatus;
        }

        $type = $this->get('type');

        if ($type === 'payment') {
            // Cash Basis
            $purchasePaymentFilters = [
                'pip.approval_status' => 2
            ];

            if ($vendorId !== null) {
                $purchasePaymentFilters['pi.vendor'] = $vendorId;
            }

            $purchaseOrders = $this->aggregate_money_out(
                db_prefix() . 'pur_invoice_payment as pip',
                'pip.date',
                'pip.amount',
                $startDate,
                $endDate,
                $purchasePaymentFilters,
                [[db_prefix() . 'pur_invoices as pi', 'pip.pur_invoice = pi.id', 'left']]
            );

            $billPaymentFilters = [];
            if ($vendorId !== null) {
                $billPaymentFilters['vendor'] = $vendorId;
            }

            $bills = $this->aggregate_money_out(
                db_prefix() . 'acc_pay_bills',
                'date',
                'amount',
                $startDate,
                $endDate,
                $billPaymentFilters
            );

            // Direct expenses are considered cash
            $expenses = $this->aggregate_money_out(
                db_prefix() . 'expenses',
                'date',
                'amount',
                $startDate,
                $endDate,
                $expenseFilters
            );

        } else {
            // Accrual Basis (Issued)
            $purchaseOrders = $this->aggregate_money_out(
                db_prefix() . 'pur_orders as po',
                'po.order_date',
                'po.total',
                $startDate,
                $endDate,
                $poFilters
            );

            $expenses = $this->aggregate_money_out(
                db_prefix() . 'expenses',
                'date',
                'amount',
                $startDate,
                $endDate,
                $expenseFilters
            );

            $bills = $this->aggregate_money_out(
                db_prefix() . 'expenses',
                'date',
                'amount',
                $startDate,
                $endDate,
                $billFilters
            );
        }

        $grandTotal = $purchaseOrders['amount'] + $expenses['amount'] + $bills['amount'];

        $this->response([
            'status' => true,
            'result' => [
                'date_from' => $startDate,
                'date_to'   => $endDate,
                'filters'   => [
                    'vendor'      => $vendorId,
                    'po_status'   => $poStatus !== null && $poStatus !== '' ? (int) $poStatus : null,
                    'bill_status' => $billStatus !== null && $billStatus !== '' ? (int) $billStatus : null,
                ],
                'totals' => [
                    'purchase_orders' => $purchaseOrders,
                    'expenses'        => $expenses,
                    'bills'           => $bills,
                ],
                'grand_total' => $grandTotal,
            ],
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

    public function bill_get($id = null, $subresource = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if ($subresource === 'payments') {
            $this->bill_payments_by_bill_get($id);

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

        $this->delete_bill_by_id($id);
    }

    public function bills_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $this->delete_bill_by_id($id);
    }

    private function delete_bill_by_id($id)
    {
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

    public function bill_attachment_get($id = null)
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

        $attachmentId = $this->get('attachment_id');
        $download     = $this->boolean_from_query('download', true);

        if ($attachmentId !== null && !is_numeric($attachmentId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid attachment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $files = $this->get_bill_attachment_records($billId, $attachmentId !== null ? (int) $attachmentId : null);

        if (!$files) {
            $this->response([
                'status'  => false,
                'message' => 'Bill has no attachment.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!$download) {
            $attachments = $this->format_bill_attachments($billId, $files);

            $this->response([
                'status' => true,
                'result' => $attachments,
            ], self::HTTP_OK);

            return;
        }

        $file = reset($files);
        $path = $this->build_bill_attachment_path($billId, $file->file_name);

        if (!file_exists($path)) {
            $this->response([
                'status'  => false,
                'message' => 'File not found on server.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->load->helper('file');
        $mime = get_mime_by_extension($file->file_name);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $file->file_name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function bill_attachment_post($id)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $billId = (int) $id;
        if ($billId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $bill = $this->accounting_model->get_bill($billId);

        if (!$bill || (int) ($bill->is_bill ?? 0) !== 1) {
            $this->response([
                'status'  => false,
                'message' => 'Bill not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!isset($_FILES['file'])) {
            $this->response([
                'status'  => false,
                'message' => 'No attachment uploaded.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $this->load->helper('upload');
        handle_expense_attachments($billId);

        $attachments = $this->format_bill_attachments($billId, $this->get_bill_attachment_records($billId));

        $this->response([
            'status'  => true,
            'message' => 'Bill attachment uploaded successfully.',
            'result'  => $attachments,
        ], self::HTTP_OK);
    }

    public function bill_attachment_delete($id)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $billId = (int) $id;
        $attachmentId = $this->delete('attachment_id');
        if ($billId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $bill = $this->accounting_model->get_bill($billId);

        if (!$bill || (int) ($bill->is_bill ?? 0) !== 1) {
            $this->response([
                'status'  => false,
                'message' => 'Bill not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if ($attachmentId !== null && !is_numeric($attachmentId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid attachment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if ($attachmentId !== null) {
            $attachmentDeleted = $this->delete_single_bill_attachment($billId, (int) $attachmentId);

            if ($attachmentDeleted) {
                $this->response([
                    'status'  => true,
                    'message' => 'Bill attachment deleted successfully.',
                ], self::HTTP_OK);

                return;
            }

            $this->response([
                'status'  => false,
                'message' => 'Bill attachment not found or could not be deleted.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $this->load->model('expenses_model');
        $deleted = $this->expenses_model->delete_expense_attachment($billId);

        if ($deleted) {
            $this->response([
                'status'  => true,
                'message' => 'Bill attachments deleted successfully.',
            ], self::HTTP_OK);

            return;
        }

        $this->response([
            'status'  => false,
            'message' => 'Bill attachment not found or could not be deleted.',
        ], self::HTTP_BAD_REQUEST);
    }

    public function bill_payments_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $billId = $this->get('bill_id');

        if ($billId !== null) {
            if (!is_numeric($billId)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid bill identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $payments = $this->get_bill_payments_for_bill((int) $billId);

            $this->response([
                'status' => true,
                'result' => $payments,
            ], self::HTTP_OK);

            return;
        }

        $payments = $this->accounting_model->get_pay_bill();

        $result = array_map(function ($payment) {
            return $this->convert_bill_payment_output($payment);
        }, $payments);

        $this->response([
            'status' => true,
            'result' => $result,
        ], self::HTTP_OK);
    }

    public function bill_payments_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $payload = $this->get_request_payload('post');

        $this->create_bill_payment($payload);
    }

    public function bill_payments_by_bill_get($billId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($billId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $payments = $this->get_bill_payments_for_bill((int) $billId);

        $this->response([
            'status' => true,
            'result' => $payments,
        ], self::HTTP_OK);
    }

    public function bill_payments_by_bill_post($billId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($billId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $payload             = $this->get_request_payload('post');
        $payload['bill_ids'] = [(int) $billId];

        $this->create_bill_payment($payload);
    }

    public function bill_payment_for_bill_get($billId = null, $paymentId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($billId) || !is_numeric($paymentId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid identifiers provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $payment = $this->accounting_model->get_pay_bill((int) $paymentId);

        if (!$payment || !$this->bill_payment_belongs_to_bill((int) $paymentId, (int) $billId)) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found for the provided bill.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $attachment       = $this->get_pay_bill_attachment_record((int) $paymentId);
        $attachmentOutput = null;

        if ($attachment) {
            $attachmentOutput = [
                'id'        => (int) $attachment->id,
                'file_name' => $attachment->file_name,
                'filetype'  => $attachment->filetype,
                'added_by'  => (int) $attachment->staffid,
                'view_url'  => site_url('accounting/api/v1/bill/' . $billId . '/payment/' . $paymentId . '/attachment'),
            ];
        }

        $result = $this->convert_bill_payment_output($payment);

        if ($attachmentOutput !== null) {
            $result['attachment'] = $attachmentOutput;
        }

        $this->response([
            'status' => true,
            'result' => $result,
        ], self::HTTP_OK);
    }

    public function bill_payment_for_bill_put($billId = null, $paymentId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($billId) || !is_numeric($paymentId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid identifiers provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $payment = $this->accounting_model->get_pay_bill((int) $paymentId);

        if (!$payment || !$this->bill_payment_belongs_to_bill((int) $paymentId, (int) $billId)) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found for the provided bill.',
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

        $payload['bill_ids'] = array_values(array_unique(array_merge($payload['bill_ids'] ?? [], [(int) $billId])));

        [$normalized, $errors] = $this->prepare_bill_payment_payload($payload, true, $payment);

        if (!empty($errors)) {
            $this->response([
                'status'  => false,
                'message' => $errors,
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->accounting_model->update_pay_bill($normalized, (int) $paymentId);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'No changes were made to the bill payment.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updatedPayment = $this->accounting_model->get_pay_bill((int) $paymentId);

        $this->response([
            'status' => true,
            'result' => $this->convert_bill_payment_output($updatedPayment),
        ], self::HTTP_OK);
    }

    public function bill_payment_for_bill_delete($billId = null, $paymentId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($billId) || !is_numeric($paymentId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid identifiers provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if (!$this->bill_payment_belongs_to_bill((int) $paymentId, (int) $billId)) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found for the provided bill.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->bill_payment_delete((int) $paymentId);
    }

    public function bill_payment_attachment_get($billId = null, $paymentId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($billId) || !is_numeric($paymentId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid identifiers provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if (!$this->bill_payment_belongs_to_bill((int) $paymentId, (int) $billId)) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found for the provided bill.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $attachment = $this->get_pay_bill_attachment_record((int) $paymentId);

        if (!$attachment) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment has no attachment.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $path = ACCOUTING_MODULE_UPLOAD_FOLDER . '/pay_bills/' . (int) $paymentId . '/' . $attachment->file_name;

        if (!file_exists($path)) {
            $this->response([
                'status'  => false,
                'message' => 'File not found on server.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->load->helper('file');
        $mime = get_mime_by_extension($attachment->file_name);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $attachment->file_name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function bill_payment_attachment_post($billId = null, $paymentId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($billId) || !is_numeric($paymentId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid identifiers provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $billId    = (int) $billId;
        $paymentId = (int) $paymentId;

        $payment = $this->accounting_model->get_pay_bill($paymentId);

        if (!$payment) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!$this->bill_payment_belongs_to_bill($paymentId, $billId)) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found for the provided bill.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!isset($_FILES['file'])) {
            $this->response([
                'status'  => false,
                'message' => 'No attachment uploaded.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $this->load->helper('upload');
        handle_pay_bill_attachments($paymentId);

        $attachment = $this->get_pay_bill_attachment_record($paymentId);

        $result = null;

        if ($attachment) {
            $path = ACCOUTING_MODULE_UPLOAD_FOLDER . '/pay_bills/' . $paymentId . '/' . $attachment->file_name;

            $result = [
                'id'        => (int) $attachment->id,
                'file_name' => $attachment->file_name,
                'filetype'  => $attachment->filetype,
                'dateadded' => $attachment->dateadded,
                'file_size' => file_exists($path) ? filesize($path) : null,
            ];
        }

        $this->response([
            'status'  => true,
            'message' => 'Bill payment attachment uploaded successfully.',
            'result'  => $result,
        ], self::HTTP_OK);
    }

    public function bill_payment_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill payment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $payment = $this->accounting_model->get_pay_bill((int) $id);

        if (!$payment) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->convert_bill_payment_output($payment),
        ], self::HTTP_OK);
    }

    public function bill_payment_attachment_delete($billId = null, $paymentId = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($billId) || !is_numeric($paymentId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid identifiers provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $billId    = (int) $billId;
        $paymentId = (int) $paymentId;

        $payment = $this->accounting_model->get_pay_bill($paymentId);

        if (!$payment) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!$this->bill_payment_belongs_to_bill($paymentId, $billId)) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found for the provided bill.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $deleted = $this->accounting_model->delete_pay_bill_attachment($paymentId);

        if ($deleted) {
            $this->response([
                'status'  => true,
                'message' => 'Bill payment attachments deleted successfully.',
            ], self::HTTP_OK);

            return;
        }

        $this->response([
            'status'  => false,
            'message' => 'Bill payment attachments not found or could not be deleted.',
        ], self::HTTP_BAD_REQUEST);
    }

    public function bill_payment_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill payment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $paymentId = (int) $id;
        $payment   = $this->accounting_model->get_pay_bill($paymentId);

        if (!$payment) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found.',
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

        [$normalized, $errors] = $this->prepare_bill_payment_payload($payload, true, $payment);

        if (!empty($errors)) {
            $this->response([
                'status'  => false,
                'message' => $errors,
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->accounting_model->update_pay_bill($normalized, $paymentId);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'No changes were made to the bill payment.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updatedPayment = $this->accounting_model->get_pay_bill($paymentId);

        $this->response([
            'status' => true,
            'result' => $this->convert_bill_payment_output($updatedPayment),
        ], self::HTTP_OK);
    }

    public function bill_payment_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid bill payment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $paymentId = (int) $id;
        $payment   = $this->accounting_model->get_pay_bill($paymentId);

        if (!$payment) {
            $this->response([
                'status'  => false,
                'message' => 'Bill payment not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $result = $this->accounting_model->delete_pay_bill($paymentId);

        if ($result === 'bill') {
            $this->response([
                'status'  => false,
                'message' => 'Cannot delete bill payment because it is linked to protected bills.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if ($result) {
            $this->response([
                'status'  => true,
                'message' => 'Bill payment deleted successfully.',
            ], self::HTTP_OK);

            return;
        }

        $this->response([
            'status'  => false,
            'message' => 'Bill payment not found or could not be deleted.',
        ], self::HTTP_BAD_REQUEST);
    }

    private function get_bill_payments_for_bill(int $billId)
    {
        $details    = $this->accounting_model->get_list_pay_bill($billId);
        $paymentIds = array_unique(array_column($details, 'pay_bill'));

        $payments = [];

        foreach ($paymentIds as $paymentId) {
            $payment = $this->accounting_model->get_pay_bill($paymentId);

            if ($payment) {
                $payments[] = $this->convert_bill_payment_output($payment);
            }
        }

        return $payments;
    }

    private function bill_payment_belongs_to_bill(int $paymentId, int $billId): bool
    {
        $details = $this->accounting_model->get_pay_bill_details($paymentId);

        foreach ($details as $detail) {
            if ((int) ($detail['bill_id'] ?? 0) === $billId) {
                return true;
            }
        }

        return false;
    }

    private function create_bill_payment(array $payload)
    {
        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        [$normalized, $errors] = $this->prepare_bill_payment_payload($payload, false);

        if (!empty($errors)) {
            $this->response([
                'status'  => false,
                'message' => $errors,
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $paymentId = $this->accounting_model->add_pay_bill($normalized);

        if (!$paymentId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create bill payment with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $payment = $this->accounting_model->get_pay_bill($paymentId);

        $this->response([
            'status' => true,
            'result' => $this->convert_bill_payment_output($payment),
        ], self::HTTP_CREATED);
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

        if ((!isset($bill['vendor_name']) || empty($bill['vendor_name'])) && isset($bill['vendor']) && !empty($bill['vendor'])) {
            $bill['vendor_name'] = acc_get_vendor_company_name($bill['vendor']);
        }

        return $bill;
    }

    private function convert_bill_payment_output($payment)
    {
        if ($payment === null) {
            return null;
        }

        if (is_object($payment)) {
            $payment = json_decode(json_encode($payment), true);
        }

        if (!is_array($payment)) {
            return $payment;
        }

        if (isset($payment['date'])) {
            $normalizedDate = $this->normalize_date($payment['date']);

            if ($normalizedDate !== null) {
                $payment['date'] = $normalizedDate;
            }
        }

        foreach (['amount'] as $decimalField) {
            if (isset($payment[$decimalField])) {
                $payment[$decimalField] = $this->normalize_decimal($payment[$decimalField]);
            }
        }

        $paymentId = isset($payment['id']) ? (int) $payment['id'] : null;

        if (!isset($payment['pay_bill_item_paid']) && $paymentId !== null) {
            $payment['pay_bill_item_paid'] = $this->accounting_model->get_pay_bill_item_paid('', ['pay_bill_id' => $paymentId]);
        }

        if (isset($payment['pay_bill_item_paid']) && is_array($payment['pay_bill_item_paid'])) {
            foreach ($payment['pay_bill_item_paid'] as &$line) {
                if (isset($line['item_id'])) {
                    $line['item_id'] = (int) $line['item_id'];
                }

                foreach (['item_amount', 'amount_paid'] as $field) {
                    if (isset($line[$field])) {
                        $line[$field] = $this->normalize_decimal($line[$field]);
                    }
                }
            }
            unset($line);
        }

        if ($paymentId !== null) {
            $details = $this->accounting_model->get_pay_bill_details($paymentId);
            $payment['bills'] = array_map('intval', array_column($details, 'bill_id'));
        }

        if ((!isset($payment['vendor_name']) || empty($payment['vendor_name'])) && isset($payment['vendor']) && !empty($payment['vendor'])) {
            $payment['vendor_name'] = acc_get_vendor_company_name($payment['vendor']);
        }

        return $payment;
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

    private function aggregate_money_out(string $table, string $dateColumn, string $amountColumn, string $startDate, string $endDate, array $filters = [], array $joins = []): array
    {
        $this->db->select('COUNT(*) AS record_count, COALESCE(SUM(' . $amountColumn . '), 0) AS total_amount');
        $this->db->from($table);

        foreach ($joins as $join) {
            $this->db->join($join[0], $join[1], $join[2] ?? 'inner');
        }

        foreach ($filters as $column => $value) {
            $this->db->where($column, $value);
        }

        $this->db->where('DATE(' . $dateColumn . ') >=', $startDate);
        $this->db->where('DATE(' . $dateColumn . ') <=', $endDate);

        $row = $this->db->get()->row_array();

        return [
            'count'  => (int) ($row['record_count'] ?? 0),
            'amount' => $this->normalize_decimal($row['total_amount'] ?? 0),
        ];
    }

    private function prepare_bill_payment_payload(array $input, bool $is_update, $existingPayment = null)
    {
        $data   = [];
        $errors = [];

        $vendor        = $input['vendor'] ?? ($existingPayment->vendor ?? null);
        $date          = $input['date'] ?? ($existingPayment->date ?? null);
        $accountCredit = $input['account_credit'] ?? ($existingPayment->account_credit ?? null);
        $accountDebit  = $input['account_debit'] ?? ($existingPayment->account_debit ?? null);

        if ($vendor === null || $vendor === '') {
            $errors[] = 'Field "vendor" is required.';
        } else {
            $data['vendor'] = (int) $vendor;
        }

        if ($date === null || $date === '') {
            $errors[] = 'Field "date" is required.';
        } else {
            $normalizedDate = $this->normalize_date($date);

            if ($normalizedDate === null) {
                $errors[] = 'Invalid date format provided. Expected format: YYYY-MM-DD.';
            } else {
                $data['date'] = $normalizedDate;
            }
        }

        if ($accountCredit === null || $accountCredit === '') {
            $errors[] = 'Field "account_credit" is required.';
        } else {
            $data['account_credit'] = (int) $accountCredit;
        }

        if ($accountDebit === null || $accountDebit === '') {
            $errors[] = 'Field "account_debit" is required.';
        } else {
            $data['account_debit'] = (int) $accountDebit;
        }

        if (isset($input['reference_no'])) {
            $data['reference_no'] = trim((string) $input['reference_no']);
        } elseif ($is_update && isset($existingPayment->reference_no)) {
            $data['reference_no'] = $existingPayment->reference_no;
        }

        $billIds = [];

        if (isset($input['bill_ids']) && is_array($input['bill_ids'])) {
            $billIds = $input['bill_ids'];
        } elseif ($is_update && $existingPayment && isset($existingPayment->id)) {
            $billIds = array_column($this->accounting_model->get_pay_bill_details($existingPayment->id), 'bill_id');
        }

        $billIds = array_values(array_filter($billIds, static function ($value) {
            return $value !== null && $value !== '';
        }));

        if ($billIds === []) {
            $errors[] = 'At least one bill identifier must be provided.';
        } else {
            foreach ($billIds as $billId) {
                if (!is_numeric($billId)) {
                    $errors[] = 'Bill identifiers must be numeric.';
                    break;
                }
            }

            $data['bill_id_check'] = array_map('intval', $billIds);
        }

        $lines = [];

        if (isset($input['payment_lines']) && is_array($input['payment_lines'])) {
            $lines = $input['payment_lines'];
        } elseif ($is_update && $existingPayment && isset($existingPayment->pay_bill_item_paid)) {
            $lines = $existingPayment->pay_bill_item_paid;
        }

        $payBillItem       = [];
        $payBillAmount     = [];
        $payBillAmountPaid = [];
        $totalAmount       = 0.0;

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $itemId      = $line['item_id'] ?? ($line['id'] ?? null);
            $itemName    = isset($line['item_name']) ? (string) $line['item_name'] : ((string) ($line['name'] ?? ''));
            $itemAmount  = $line['item_amount'] ?? ($line['amount'] ?? null);
            $amountPaid  = $line['amount_paid'] ?? ($line['amount'] ?? null);

            if ($itemId === null || $itemId === '') {
                continue;
            }

            if ($amountPaid === null || $amountPaid === '') {
                $errors[] = sprintf('Missing amount_paid for item %s.', $itemId);
                continue;
            }

            if ($itemAmount === null || $itemAmount === '') {
                $itemAmount = $amountPaid;
            }

            $payBillItem[$itemId]       = $itemName;
            $payBillAmount[$itemId]     = $this->format_decimal_string($itemAmount);
            $payBillAmountPaid[$itemId] = $this->format_decimal_string($amountPaid);

            $totalAmount += $this->normalize_decimal($amountPaid);
        }

        if ($payBillAmountPaid === []) {
            $errors[] = 'At least one payment line with an amount_paid value is required.';
        }

        if ($totalAmount <= 0) {
            $errors[] = 'Payment amount must be greater than zero.';
        }

        $data['amount']             = $this->format_decimal_string($totalAmount);
        $data['pay_bill_item']      = $payBillItem;
        $data['pay_bill_amount']    = $payBillAmount;
        $data['pay_bill_amount_paid'] = $payBillAmountPaid;
        $data['bill_items']         = array_keys($payBillItem);

        return [$data, $errors];
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

    private function get_bill_attachment_records(int $billId, ?int $attachmentId = null): array
    {
        $this->db->where('rel_id', $billId);
        $this->db->where('rel_type', 'expense');

        if ($attachmentId !== null) {
            $this->db->where('id', $attachmentId);
        }

        return $this->db->get(db_prefix() . 'files')->result();
    }

    private function get_pay_bill_attachment_record(int $paymentId)
    {
        $this->db->where('rel_id', $paymentId);
        $this->db->where('rel_type', 'pay_bill');

        return $this->db->get(db_prefix() . 'files')->row();
    }

    private function format_bill_attachments(int $billId, array $files): array
    {
        return array_map(function ($file) use ($billId) {
            $path = $this->build_bill_attachment_path($billId, $file->file_name);

            return [
                'id'                  => (int) $file->id,
                'file_name'           => $file->file_name,
                'filetype'            => $file->filetype,
                'dateadded'           => $file->dateadded,
                'staffid'             => (int) $file->staffid,
                'visible_to_customer' => (bool) $file->visible_to_customer,
                'external'            => (bool) $file->external,
                'external_link'       => $file->external_link,
                'thumbnail_link'      => $file->thumbnail_link,
                'file_size'           => file_exists($path) ? filesize($path) : null,
            ];
        }, $files);
    }

    private function delete_single_bill_attachment(int $billId, int $attachmentId): bool
    {
        $files = $this->get_bill_attachment_records($billId, $attachmentId);

        if ($files === []) {
            return false;
        }

        $file = reset($files);
        $path = $this->build_bill_attachment_path($billId, $file->file_name);

        if (file_exists($path)) {
            @unlink($path);
        }

        $this->db->where('id', $file->id);
        $this->db->delete(db_prefix() . 'files');

        $directory = $this->build_bill_attachment_path($billId, '');
        if (is_dir($directory)) {
            $remainingFiles = array_diff(scandir($directory), ['.', '..']);

            if (count($remainingFiles) === 0) {
                @rmdir($directory);
            }
        }

        return true;
    }

    private function build_bill_attachment_path(int $billId, string $fileName): string
    {
        return get_upload_path_by_type('expense') . $billId . '/' . $fileName;
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
