<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_expenses extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('expenses_model');
    }

    public function expenses_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $where = [];

        $clientId = $this->get('client_id');
        if ($clientId !== null && $clientId !== '') {
            if (!is_numeric($clientId)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid client identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'expenses.clientid'] = (int) $clientId;
        }

        $projectId = $this->get('project_id');
        if ($projectId !== null && $projectId !== '') {
            if (!is_numeric($projectId)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid project identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'expenses.project_id'] = (int) $projectId;
        }

        $categoryId = $this->get('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            if (!is_numeric($categoryId)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid category identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'expenses.category'] = (int) $categoryId;
        }

        $paymentMode = $this->get('payment_mode');
        if ($paymentMode !== null && $paymentMode !== '') {
            if (!is_numeric($paymentMode)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid payment mode identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'expenses.paymentmode'] = (int) $paymentMode;
        }

        $billableParam = $this->get('billable');
        if ($billableParam !== null) {
            $billable = $this->interpret_boolean($billableParam, null);

            if ($billable === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid billable flag provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'expenses.billable'] = $billable ? 1 : 0;
        }

        $statusParam = $this->get('status');
        if ($statusParam !== null && $statusParam !== '') {
            if (!is_numeric($statusParam)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid status identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'expenses.status'] = (int) $statusParam;
        }

        $dateFromRaw = $this->get('date_from');
        if ($dateFromRaw !== null && $dateFromRaw !== '') {
            $dateFrom = $this->normalize_date($dateFromRaw);

            if ($dateFrom === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid date_from value provided. Expected format: YYYY-MM-DD.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'expenses.date >='] = $dateFrom;
        }

        $dateToRaw = $this->get('date_to');
        if ($dateToRaw !== null && $dateToRaw !== '') {
            $dateTo = $this->normalize_date($dateToRaw);

            if ($dateTo === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid date_to value provided. Expected format: YYYY-MM-DD.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'expenses.date <='] = $dateTo;
        }

        $expenses = $this->expenses_model->get('', $where);

        if (is_array($expenses)) {
            $vendorLookup = $this->build_vendor_lookup($expenses);

            foreach ($expenses as &$expense) {
                $expense = $this->format_expense_record($expense, $vendorLookup);
            }
            unset($expense);
        }

        $this->response([
            'status' => true,
            'result' => $expenses,
        ], self::HTTP_OK);
    }

    public function categories_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $this->load->helper('template');

        $categories = $this->expenses_model->get_category();
        if (!is_array($categories)) {
            $categories = [];
        }

        $formatted = array_map(function ($category) {
            return $this->format_category_record($category);
        }, $categories);

        $this->response([
            'status' => true,
            'result' => $formatted,
        ], self::HTTP_OK);
    }

    public function expense_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid expense identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $expense = $this->expenses_model->get((int) $id);

        if (!$expense) {
            $this->response([
                'status'  => false,
                'message' => 'Expense not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $vendorLookup = $this->build_vendor_lookup([$expense]);
        $expense       = $this->format_expense_record($expense, $vendorLookup);

        $this->response([
            'status' => true,
            'result' => $expense,
        ], self::HTTP_OK);
    }

    public function expense_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid expense identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $expenseId = (int) $id;
        $expense   = $this->expenses_model->get($expenseId);

        if (!$expense) {
            $this->response([
                'status'  => false,
                'message' => 'Expense not found.',
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

        $normalized = $this->prepare_expense_payload($payload, true, $expense);

        if ($normalized['errors'] !== []) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if ($normalized['touched'] === []) {
            $this->response([
                'status'  => false,
                'message' => 'No updatable fields were provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->expenses_model->update($normalized['data'], $expenseId);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Expense update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $updatedExpense = $this->expenses_model->get($expenseId);
        $vendorLookup   = $this->build_vendor_lookup([$updatedExpense]);
        $updatedExpense = $this->format_expense_record($updatedExpense, $vendorLookup);

        $this->response([
            'status' => true,
            'result' => $updatedExpense,
        ], self::HTTP_OK);
    }

    public function expenses_post()
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

        $normalized = $this->prepare_expense_payload($payload, false);

        if ($normalized['errors'] !== []) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $expenseId = $this->expenses_model->add($normalized['data']);

        if (!$expenseId) {
            $this->response([
                'status'  => false,
                'message' => 'Expense creation failed.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $expense      = $this->expenses_model->get($expenseId);
        $vendorLookup = $this->build_vendor_lookup([$expense]);
        $expense      = $this->format_expense_record($expense, $vendorLookup);

        $this->response([
            'status' => true,
            'result' => $expense,
        ], self::HTTP_OK);
    }

    public function expense_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid expense identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $expenseId = (int) $id;
        $expense   = $this->expenses_model->get($expenseId);

        if (!$expense) {
            $this->response([
                'status'  => false,
                'message' => 'Expense not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $result = $this->expenses_model->delete($expenseId);

        if (is_array($result) && isset($result['invoiced']) && $result['invoiced'] === true) {
            $this->response([
                'status'  => false,
                'message' => 'Cannot delete expense because it has already been invoiced.',
            ], self::HTTP_CONFLICT);

            return;
        }

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Expense not found or already deleted.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Expense deleted successfully.',
        ], self::HTTP_OK);
    }

    public function expense_attachment_post($id)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $id = (int) $id;
        if (!$id) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid expense identifier provided.',
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        $this->load->helper('upload');
        handle_expense_attachments($id);

        $this->response([
            'status'  => true,
            'message' => 'Expense attachment uploaded successfully.',
        ], self::HTTP_OK);
    }

    public function expense_attachment_delete($id)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $id = (int) $id;
        if (!$id) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid expense identifier provided.',
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        $result = $this->expenses_model->delete_expense_attachment($id);

        if ($result) {
            $this->response([
                'status'  => true,
                'message' => 'Expense attachment deleted successfully.',
            ], self::HTTP_OK);
        } else {
            $this->response([
                'status'  => false,
                'message' => 'Expense attachment not found or could not be deleted.',
            ], self::HTTP_BAD_REQUEST);
        }
    }

    public function expense_attachment_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid expense identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $expenseId = (int) $id;
        $expense   = $this->expenses_model->get($expenseId);

        if (!$expense) {
            $this->response([
                'status'  => false,
                'message' => 'Expense not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->db->where('rel_id', $expenseId);
        $this->db->where('rel_type', 'expense');
        $file = $this->db->get(db_prefix() . 'files')->row();

        if (!$file) {
            $this->response([
                'status'  => false,
                'message' => 'Expense has no attachment.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $path = get_upload_path_by_type('expense') . $expenseId . '/' . $file->file_name;

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
    }

    public function bulk_payments_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $payload = $this->get_request_payload('post');

        if (empty($payload['expense_ids']) || !is_array($payload['expense_ids'])) {
            $this->response([
                'status'  => false,
                'message' => 'Field "expense_ids" is required and must be an array.',
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        if (empty($payload['payment_mode'])) {
            $this->response([
                'status'  => false,
                'message' => 'Field "payment_mode" is required.',
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        $updated_count = 0;
        foreach ($payload['expense_ids'] as $expense_id) {
            $data = ['paymentmode' => $payload['payment_mode']];
            if ($this->expenses_model->update($data, $expense_id)) {
                $updated_count++;
            }
        }

        $this->response([
            'status'  => true,
            'message' => "$updated_count expenses updated successfully.",
        ], self::HTTP_OK);
    }

    public function bulk_payments_delete()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $payload = $this->get_request_payload('delete');

        if (empty($payload['expense_ids']) || !is_array($payload['expense_ids'])) {
            $this->response([
                'status'  => false,
                'message' => 'Field "expense_ids" is required and must be an array.',
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        $deleted_count = 0;
        foreach ($payload['expense_ids'] as $expense_id) {
            $data = ['paymentmode' => 0];
            if ($this->expenses_model->update($data, $expense_id)) {
                $deleted_count++;
            }
        }

        $this->response([
            'status'  => true,
            'message' => "$deleted_count payments removed successfully.",
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

    private function format_category_record(array $category)
    {
        return [
            'id'          => isset($category['id']) ? (int) $category['id'] : null,
            'name'        => isset($category['name']) ? (string) $category['name'] : '',
            'description' => clear_textarea_breaks($category['description'] ?? ''),
        ];
    }

    private function format_expense_record($expense, array $vendorLookup = [])
    {
        if (is_array($expense)) {
            if (isset($expense['note'])) {
                $expense['note'] = $this->convert_breaks_to_newlines($expense['note']);
            }

            if (array_key_exists('vendor', $expense)) {
                $vendorId = $expense['vendor'];
                if ($vendorId !== null && $vendorId !== '' && is_numeric($vendorId)) {
                    $vendorId = (int) $vendorId;
                    if (isset($vendorLookup[$vendorId])) {
                        $expense['vendor_name'] = $vendorLookup[$vendorId];
                    } elseif (!array_key_exists('vendor_name', $expense)) {
                        $expense['vendor_name'] = null;
                    }
                } elseif (!array_key_exists('vendor_name', $expense)) {
                    $expense['vendor_name'] = null;
                }
            } elseif (!array_key_exists('vendor_name', $expense)) {
                $expense['vendor_name'] = null;
            }

            if (isset($expense['date'])) {
                $normalizedDate = $this->normalize_date($expense['date']);
                if ($normalizedDate !== null) {
                    $expense['date'] = $normalizedDate;
                }
            }

            if (isset($expense['amount']) && $expense['amount'] !== null && $expense['amount'] !== '') {
                $expense['amount'] = $this->normalize_decimal($expense['amount']);
            }

            return $expense;
        }

        if (is_object($expense)) {
            if (isset($expense->note)) {
                $expense->note = $this->convert_breaks_to_newlines($expense->note);
            }

            if (isset($expense->vendor)) {
                $vendorId = $expense->vendor;
                if ($vendorId !== null && $vendorId !== '' && is_numeric($vendorId)) {
                    $vendorId = (int) $vendorId;
                    if (isset($vendorLookup[$vendorId])) {
                        $expense->vendor_name = $vendorLookup[$vendorId];
                    } elseif (!isset($expense->vendor_name)) {
                        $expense->vendor_name = null;
                    }
                } elseif (!isset($expense->vendor_name)) {
                    $expense->vendor_name = null;
                }
            } elseif (!isset($expense->vendor_name)) {
                $expense->vendor_name = null;
            }

            if (isset($expense->date)) {
                $normalizedDate = $this->normalize_date($expense->date);
                if ($normalizedDate !== null) {
                    $expense->date = $normalizedDate;
                }
            }

            if (isset($expense->amount) && $expense->amount !== null && $expense->amount !== '') {
                $expense->amount = $this->normalize_decimal($expense->amount);
            }
        }

        return $expense;
    }

    private function build_vendor_lookup(array $expenses)
    {
        $vendorIds = [];

        foreach ($expenses as $expense) {
            $vendorId = null;

            if (is_array($expense) && array_key_exists('vendor', $expense)) {
                $vendorId = $expense['vendor'];
            } elseif (is_object($expense) && isset($expense->vendor)) {
                $vendorId = $expense->vendor;
            }

            if ($vendorId === null || $vendorId === '' || !is_numeric($vendorId)) {
                continue;
            }

            $vendorIds[] = (int) $vendorId;
        }

        if ($vendorIds === []) {
            return [];
        }

        $vendorIds = array_values(array_unique($vendorIds));

        $vendors = $this->db
            ->select('userid, company')
            ->from(db_prefix() . 'pur_vendor')
            ->where_in('userid', $vendorIds)
            ->get()
            ->result_array();

        $lookup = [];
        foreach ($vendors as $vendor) {
            if (!array_key_exists('userid', $vendor)) {
                continue;
            }

            $lookup[(int) $vendor['userid']] = $vendor['company'] ?? null;
        }

        return $lookup;
    }

    private function prepare_expense_payload(array $input, bool $isUpdate, $existingExpense = null)
    {
        $errors  = [];
        $data    = [];
        $touched = [];

        $dateSource = array_key_exists('date', $input) ? $input['date'] : ($existingExpense->date ?? null);
        if (array_key_exists('date', $input)) {
            $touched[] = 'date';
        }
        $dateValue = $this->normalize_date($dateSource);
        if ($dateValue === null) {
            $errors[] = 'Field "date" is required and must be a valid date (YYYY-MM-DD).';
        } else {
            $data['date'] = $dateValue;
        }

        $amountSource = array_key_exists('amount', $input) ? $input['amount'] : ($existingExpense->amount ?? null);
        if (array_key_exists('amount', $input)) {
            $touched[] = 'amount';
        }
        if ($amountSource === null || $amountSource === '') {
            $errors[] = 'Field "amount" is required.';
        } else {
            $data['amount'] = $this->normalize_decimal($amountSource);
        }

        $categorySource = array_key_exists('category', $input) ? $input['category'] : ($existingExpense->category ?? null);
        if (array_key_exists('category', $input)) {
            $touched[] = 'category';
        }
        if ($categorySource === null || $categorySource === '') {
            $errors[] = 'Field "category" is required.';
        } else {
            $data['category'] = (int) $categorySource;
        }

        $expenseNameSource = array_key_exists('expense_name', $input)
            ? $input['expense_name']
            : ($existingExpense->expense_name ?? '');
        if (array_key_exists('expense_name', $input)) {
            $touched[] = 'expense_name';
        }
        $data['expense_name'] = trim((string) $expenseNameSource);

        $noteSource = array_key_exists('note', $input)
            ? $input['note']
            : ($existingExpense ? $this->convert_breaks_to_newlines($existingExpense->note ?? '') : '');
        if (array_key_exists('note', $input)) {
            $touched[] = 'note';
        }
        $data['note'] = is_string($noteSource) ? $noteSource : '';

        $stringFields = ['reference_no'];
        foreach ($stringFields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]);
                $touched[]    = $field;
            } elseif ($existingExpense && isset($existingExpense->{$field})) {
                $data[$field] = trim((string) $existingExpense->{$field});
            }
        }

        $intFields = ['clientid', 'project_id', 'paymentmode', 'tax', 'tax2', 'currency', 'paymentmethod', 'vendor', 'department', 'status', 'approved'];
        foreach ($intFields as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                if ($value === '' || $value === null) {
                    continue;
                }
                $data[$field] = (int) $value;
                $touched[]    = $field;
            } elseif ($existingExpense && isset($existingExpense->{$field}) && $existingExpense->{$field} !== '' && $existingExpense->{$field} !== null) {
                $data[$field] = (int) $existingExpense->{$field};
            }
        }

        $boolFields = ['billable', 'create_invoice_billable', 'send_invoice_to_customer'];
        foreach ($boolFields as $field) {
            if (array_key_exists($field, $input)) {
                $value = $this->interpret_boolean($input[$field], null);
                if ($value !== null) {
                    $data[$field] = $value ? 1 : 0;
                    $touched[]    = $field;
                }
            } elseif ($existingExpense && isset($existingExpense->{$field})) {
                $data[$field] = ((int) $existingExpense->{$field}) === 1 ? 1 : 0;
            }
        }

        if (array_key_exists('repeat_every', $input)) {
            $data['repeat_every'] = (string) $input['repeat_every'];
            $touched[]            = 'repeat_every';
        } elseif ($existingExpense && (int) ($existingExpense->recurring ?? 0) === 1) {
            if ((int) ($existingExpense->custom_recurring ?? 0) === 1) {
                $data['repeat_every']       = 'custom';
                $data['repeat_every_custom'] = (string) ($existingExpense->repeat_every ?? '');
                $data['repeat_type_custom']  = (string) ($existingExpense->recurring_type ?? '');
            } else {
                $repeatEveryValue = (string) ($existingExpense->repeat_every ?? '');
                $recurringType    = (string) ($existingExpense->recurring_type ?? '');
                if ($repeatEveryValue !== '' && $recurringType !== '') {
                    $data['repeat_every'] = $repeatEveryValue . '-' . $recurringType;
                }
            }
        }

        if (array_key_exists('repeat_every_custom', $input)) {
            $data['repeat_every_custom'] = trim((string) $input['repeat_every_custom']);
            $touched[]                   = 'repeat_every_custom';
        }

        if (array_key_exists('repeat_type_custom', $input)) {
            $data['repeat_type_custom'] = trim((string) $input['repeat_type_custom']);
            $touched[]                  = 'repeat_type_custom';
        }

        if (array_key_exists('cycles', $input)) {
            $data['cycles'] = (int) $input['cycles'];
            $touched[]      = 'cycles';
        } elseif ($existingExpense && isset($existingExpense->cycles)) {
            $data['cycles'] = (int) $existingExpense->cycles;
        }

        if (array_key_exists('custom_fields', $input) && is_array($input['custom_fields'])) {
            $data['custom_fields'] = $input['custom_fields'];
            $touched[]             = 'custom_fields';
        }

        $touched = array_values(array_unique($touched));

        return [
            'data'    => $data,
            'errors'  => array_values(array_unique($errors)),
            'touched' => $touched,
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
            $rawInput = $this->input->raw_input_stream;

            if ($rawInput !== '') {
                $decoded = json_decode($rawInput, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        return $data;
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

    private function convert_breaks_to_newlines($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        return preg_replace('/<br\s*\/?>(\r\n)?/i', PHP_EOL, $decoded);
    }
}
