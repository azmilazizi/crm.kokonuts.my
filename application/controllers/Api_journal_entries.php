<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_journal_entries extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('journal_entries_model');
        $this->load->model('accounting/accounting_model');
    }

    public function journal_entries_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $filters = [];
        $entryDate = $this->normalize_date($this->get('entry_date'));

        if ($entryDate === null && $this->get('entry_date') !== null && $this->get('entry_date') !== '') {
            $this->response([
                'status'  => false,
                'message' => 'Invalid entry_date value provided. Expected format: YYYY-MM-DD.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if ($entryDate !== null) {
            $filters[db_prefix() . 'acc_journal_entries.journal_date'] = $entryDate;
        }

        $createdBy = $this->get('created_by');
        if ($createdBy !== null && $createdBy !== '') {
            if (!is_numeric($createdBy)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid created_by value provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $filters[db_prefix() . 'acc_journal_entries.addedfrom'] = (int) $createdBy;
        }

        $entries = $this->journal_entries_model->get(null, $filters);
        $entries = array_map(function ($entry) {
            $entryId = isset($entry['id']) ? (int) $entry['id'] : null;

            return $entryId ? $this->accounting_model->get_journal_entry($entryId) : $entry;
        }, $entries);

        $this->response([
            'status' => true,
            'result' => array_map([$this, 'format_entry'], $entries),
        ], self::HTTP_OK);
    }

    public function journal_entry_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid journal entry identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $entry = $this->accounting_model->get_journal_entry((int) $id);

        if (!$entry) {
            $this->response([
                'status'  => false,
                'message' => 'Journal entry not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_entry($entry),
        ], self::HTTP_OK);
    }

    public function journal_entries_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $payload = $this->buildEntryPayloadFromRequest('post');
        if ($payload === null) {
            return;
        }

        $entryId = $this->accounting_model->add_journal_entry($payload);

        if ($entryId === 'close_the_book') {
            $this->response([
                'status'  => false,
                'message' => 'The books are closed for the selected date.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        if (!$entryId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create journal entry.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $entry   = $this->accounting_model->get_journal_entry((int) $entryId);

        $this->response([
            'status' => true,
            'result' => $this->format_entry($entry),
        ], self::HTTP_CREATED);
    }

    public function journal_entry_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid journal entry identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->accounting_model->get_journal_entry((int) $id);
        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Journal entry not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $payload = $this->buildEntryPayloadFromRequest('put', false);
        if ($payload === null) {
            return;
        }

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'No valid fields were provided for update.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->accounting_model->update_journal_entry($payload, (int) $id);

        if ($updated === 'close_the_book') {
            $this->response([
                'status'  => false,
                'message' => 'The books are closed for the selected date.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update the journal entry or no changes detected.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $entry = $this->accounting_model->get_journal_entry((int) $id);

        $this->response([
            'status' => true,
            'result' => $this->format_entry($entry),
        ], self::HTTP_OK);
    }

    public function journal_entry_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid journal entry identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->accounting_model->get_journal_entry((int) $id);
        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Journal entry not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->accounting_model->delete_journal_entry((int) $id);

        $this->response([
            'status'  => true,
            'message' => 'Journal entry deleted successfully.',
        ], self::HTTP_OK);
    }

    private function buildEntryPayloadFromRequest($method, $isCreate = true)
    {
        $input   = $this->getRequestInput($method);
        $payload = [];

        $entryDateRaw = $input['entry_date'] ?? $input['journal_date'] ?? null;
        if ($entryDateRaw !== null && $entryDateRaw !== '') {
            $entryDate = $this->normalize_date($entryDateRaw);

            if ($entryDate === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid entry_date value provided. Expected format: YYYY-MM-DD.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['journal_date'] = $entryDate;
        } elseif ($isCreate) {
            $payload['journal_date'] = date('Y-m-d');
        }

        if (isset($input['description'])) {
            $payload['description'] = (string) $input['description'];
        } elseif (isset($input['content'])) {
            $payload['description'] = (string) $input['content'];
        }

        if (isset($input['number'])) {
            $payload['number'] = (string) $input['number'];
        }

        $lineErrors = [];
        $lines      = $this->normalizeLineInput($input, $lineErrors);

        if ($lineErrors !== []) {
            $this->response([
                'status'  => false,
                'message' => $lineErrors,
            ], self::HTTP_BAD_REQUEST);

            return null;
        }

        if ($lines === []) {
            if ($isCreate) {
                $this->response([
                    'status'  => false,
                    'message' => 'At least one journal entry line is required.',
                ], self::HTTP_BAD_REQUEST);
            }

            return [];
        }

        $payload['account']            = array_column($lines, 'account');
        $payload['debit_amount']       = array_column($lines, 'debit');
        $payload['credit_amount']      = array_column($lines, 'credit');
        $payload['description_detail'] = array_column($lines, 'description');

        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($payload['debit_amount'] as $value) {
            $totalDebit += $this->normalize_decimal($value);
        }

        foreach ($payload['credit_amount'] as $value) {
            $totalCredit += $this->normalize_decimal($value);
        }

        if ($totalDebit <= 0 || $totalCredit <= 0 || abs($totalDebit - $totalCredit) > 0.0001) {
            $this->response([
                'status'  => false,
                'message' => 'Total debits must equal total credits and both must be greater than zero.',
            ], self::HTTP_BAD_REQUEST);

            return null;
        }

        $payload['amount'] = $this->format_decimal_string($totalDebit);

        if ($isCreate) {
            $payload['created_by'] = $this->getAuthenticatedUserId();
        }

        return $payload;
    }

    private function getRequestInput($method)
    {
        $data = $this->{$method}() ?: [];

        $raw = json_decode($this->input->raw_input_stream, true);

        if (is_array($raw)) {
            $data = array_merge($raw, $data);
        }

        return $data;
    }

    private function normalizeLineInput(array $input, array &$errors)
    {
        $errors = [];
        $lines  = [];

        if (isset($input['lines']) && is_array($input['lines'])) {
            $lines = $input['lines'];
        } elseif (isset($input['account'], $input['debit_amount'], $input['credit_amount'])) {
            $lines = [];
            $descriptions = isset($input['description_detail']) && is_array($input['description_detail'])
                ? $input['description_detail']
                : [];

            foreach ((array) $input['account'] as $index => $account) {
                $lines[] = [
                    'account'     => $account,
                    'debit'       => $input['debit_amount'][$index] ?? 0,
                    'credit'      => $input['credit_amount'][$index] ?? 0,
                    'description' => $descriptions[$index] ?? '',
                ];
            }
        }

        $normalized = [];

        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                $errors[] = sprintf('Line %s is invalid.', $index);
                continue;
            }

            $account = $line['account'] ?? null;
            $debit   = $this->normalize_decimal($line['debit'] ?? 0);
            $credit  = $this->normalize_decimal($line['credit'] ?? 0);

            if (!is_numeric($account)) {
                $errors[] = sprintf('Line %s is missing a valid account identifier.', $index);
                continue;
            }

            if ($debit <= 0 && $credit <= 0) {
                $errors[] = sprintf('Line %s must include a debit or credit amount.', $index);
                continue;
            }

            $normalized[] = [
                'account'     => (int) $account,
                'debit'       => $this->format_decimal_string($debit),
                'credit'      => $this->format_decimal_string($credit),
                'description' => isset($line['description']) ? (string) $line['description'] : '',
            ];
        }

        return $normalized;
    }

    private function normalize_decimal($value)
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return (float) $value;
    }

    private function format_decimal_string($value)
    {
        return number_format($this->normalize_decimal($value), 2, '.', '');
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

    private function getAuthenticatedUserId()
    {
        if (function_exists('get_staff_user_id')) {
            $staffId = get_staff_user_id();
            if ($staffId) {
                return (int) $staffId;
            }
        }

        if (is_array($this->tokenPayload)) {
            if (isset($this->tokenPayload['user_id'])) {
                return (int) $this->tokenPayload['user_id'];
            }

            if (isset($this->tokenPayload['id'])) {
                return (int) $this->tokenPayload['id'];
            }
        }

        return null;
    }

    private function format_entry($entry)
    {
        if ($entry === null) {
            return null;
        }

        $formatDate = function ($value) {
            $normalized = $this->normalize_date($value);
            return $normalized ?: null;
        };

        if (is_array($entry)) {
            $entry['entry_date'] = $formatDate($entry['entry_date'] ?? $entry['journal_date'] ?? null);
            $entry['created_at'] = $formatDate($entry['created_at'] ?? $entry['datecreated'] ?? null);
            $entry['updated_at'] = $formatDate($entry['updated_at'] ?? null);

            if (!isset($entry['created_by']) && isset($entry['addedfrom'])) {
                $entry['created_by'] = $entry['addedfrom'];
            }

            if (isset($entry['created_by']) && $entry['created_by'] !== null && $entry['created_by'] !== '') {
                $entry['created_by'] = (int) $entry['created_by'];
            }

            if (isset($entry['details']) && is_array($entry['details'])) {
                $entry['details'] = array_map(function ($detail) use ($formatDate) {
                    if (isset($detail['account'])) {
                        $detail['account'] = (int) $detail['account'];
                    }

                    foreach (['debit', 'credit'] as $amountField) {
                        if (isset($detail[$amountField])) {
                            $detail[$amountField] = $this->normalize_decimal($detail[$amountField]);
                        }
                    }

                    if (isset($detail['date'])) {
                        $detail['date'] = $formatDate($detail['date']);
                    }

                    return $detail;
                }, $entry['details']);
            }

            return $entry;
        }

        if (is_object($entry)) {
            $entry->entry_date = $formatDate($entry->entry_date ?? $entry->journal_date ?? null);
            $entry->created_at = $formatDate($entry->created_at ?? $entry->datecreated ?? null);
            $entry->updated_at = $formatDate($entry->updated_at ?? null);

            if (!isset($entry->created_by) && isset($entry->addedfrom)) {
                $entry->created_by = $entry->addedfrom;
            }
            if (isset($entry->created_by) && $entry->created_by !== null && $entry->created_by !== '') {
                $entry->created_by = (int) $entry->created_by;
            }

            if (isset($entry->details) && is_array($entry->details)) {
                foreach ($entry->details as &$detail) {
                    if (isset($detail['account'])) {
                        $detail['account'] = (int) $detail['account'];
                    }

                    foreach (['debit', 'credit'] as $amountField) {
                        if (isset($detail[$amountField])) {
                            $detail[$amountField] = $this->normalize_decimal($detail[$amountField]);
                        }
                    }

                    if (isset($detail['date'])) {
                        $detail['date'] = $formatDate($detail['date']);
                    }
                }
                unset($detail);
            }
        }

        return $entry;
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
