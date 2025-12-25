<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_invoices extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    /** @var array|null */
    private $invoiceFields = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('invoices_model');
    }

    public function invoices_get()
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

            $where[db_prefix() . 'invoices.clientid'] = (int) $clientId;
        }

        $status = $this->get('status');
        if ($status !== null && $status !== '') {
            if (!is_numeric($status)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid status identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'invoices.status'] = (int) $status;
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

            $where[db_prefix() . 'invoices.project_id'] = (int) $projectId;
        }

        $currencyId = $this->get('currency_id');
        if ($currencyId !== null && $currencyId !== '') {
            if (!is_numeric($currencyId)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid currency identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $where[db_prefix() . 'invoices.currency'] = (int) $currencyId;
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

            $where[db_prefix() . 'invoices.date >='] = $dateFrom;
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

            $where[db_prefix() . 'invoices.date <='] = $dateTo;
        }

        $invoices = $this->invoices_model->get('', $where);

        $this->response([
            'status' => true,
            'result' => $invoices,
        ], self::HTTP_OK);
    }

    public function invoice_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid invoice identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $invoice = $this->invoices_model->get((int) $id);

        if (!$invoice) {
            $this->response([
                'status'  => false,
                'message' => 'Invoice not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $invoice,
        ], self::HTTP_OK);
    }

    public function invoices_post()
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

        $normalized = $this->prepare_invoice_payload($payload);

        if ($normalized['errors'] !== []) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $invoiceId = $this->invoices_model->add($normalized['data']);

        if (!$invoiceId) {
            $this->response([
                'status'  => false,
                'message' => 'Invoice creation failed.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $invoice = $this->invoices_model->get($invoiceId);

        // Trigger accounting conversion immediately for API-created invoices.
        $this->load->model('accounting/accounting_model');
        if (get_option('acc_invoice_automatic_conversion') == 1) {
            $this->accounting_model->automatic_invoice_conversion($invoiceId);
        } elseif (get_option('acc_invoice_discount_automatic_conversion') == 1) {
            $this->accounting_model->automatic_invoice_discount_conversion($invoiceId);
        }

        $this->response([
            'status' => true,
            'result' => $invoice,
        ], self::HTTP_OK);
    }

    public function invoice_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid invoice identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $invoice = $this->invoices_model->get((int) $id);

        if (!$invoice) {
            $this->response([
                'status'  => false,
                'message' => 'Invoice not found.',
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

        $normalized = $this->prepare_invoice_payload($payload, $invoice);

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

        $updated = $this->invoices_model->update($normalized['data'], (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Invoice update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $updatedInvoice = $this->invoices_model->get((int) $id);

        $this->response([
            'status' => true,
            'result' => $updatedInvoice,
        ], self::HTTP_OK);
    }

    public function invoice_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid invoice identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $invoice = $this->invoices_model->get((int) $id);

        if (!$invoice) {
            $this->response([
                'status'  => false,
                'message' => 'Invoice not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $result = $this->invoices_model->delete((int) $id);

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Invoice could not be deleted. Ensure it is the last invoice before deleting.',
            ], self::HTTP_CONFLICT);

            return;
        }

        // Ensure accounting conversions are removed for API-deleted invoices.
        $this->load->model('accounting/accounting_model');
        $this->accounting_model->delete_invoice_convert((int) $id);

        $this->response([
            'status'  => true,
            'message' => 'Invoice deleted successfully.',
        ], self::HTTP_OK);
    }

    private function prepare_invoice_payload(array $input, $existingInvoice = null)
    {
        $errors  = [];
        $data    = [];
        $touched = [];

        $fields = $this->get_invoice_fields();

        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
                $touched[]    = $field;
            } elseif ($existingInvoice !== null && isset($existingInvoice->{$field})) {
                $data[$field] = $existingInvoice->{$field};
            }
        }

        $extraFields = [
            'items',
            'newitems',
            'removed_items',
            'custom_fields',
            'tags',
            'billed_tasks',
            'billed_expenses',
            'invoices_to_merge',
            'cancel_merged_invoices',
            'save_as_draft',
            'save_and_send',
            'save_and_send_later',
        ];

        foreach ($extraFields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
                $touched[]    = $field;
            }
        }

        if (array_key_exists('allowed_payment_modes', $input)) {
            if (!is_array($input['allowed_payment_modes'])) {
                $errors[] = 'allowed_payment_modes must be an array.';
            } else {
                $data['allowed_payment_modes'] = $input['allowed_payment_modes'];
                $touched[]                     = 'allowed_payment_modes';
            }
        } elseif ($existingInvoice !== null) {
            $data['allowed_payment_modes'] = $this->normalize_allowed_payment_modes($existingInvoice->allowed_payment_modes ?? null);
        }

        if (!array_key_exists('clientid', $data) || $data['clientid'] === null || $data['clientid'] === '') {
            if ($existingInvoice === null) {
                $errors[] = 'clientid is required.';
            }
        } elseif (!is_numeric($data['clientid'])) {
            $errors[] = 'Invalid clientid provided.';
        } else {
            $data['clientid'] = (int) $data['clientid'];
        }

        if (!array_key_exists('currency', $data) || $data['currency'] === null || $data['currency'] === '') {
            if ($existingInvoice === null) {
                $errors[] = 'currency is required.';
            }
        } elseif (!is_numeric($data['currency'])) {
            $errors[] = 'Invalid currency value provided.';
        } else {
            $data['currency'] = (int) $data['currency'];
        }

        if (array_key_exists('date', $data)) {
            $normalizedDate = $this->normalize_date($data['date']);
            if ($normalizedDate === null) {
                $errors[] = 'Invalid date value provided. Expected format: YYYY-MM-DD.';
            } else {
                $data['date'] = $normalizedDate;
            }
        } elseif ($existingInvoice === null) {
            $errors[] = 'date is required.';
        }

        if (array_key_exists('duedate', $data) && $data['duedate'] !== null && $data['duedate'] !== '') {
            $normalizedDueDate = $this->normalize_date($data['duedate']);
            if ($normalizedDueDate === null) {
                $errors[] = 'Invalid duedate value provided. Expected format: YYYY-MM-DD.';
            } else {
                $data['duedate'] = $normalizedDueDate;
            }
        }

        if (!array_key_exists('billing_street', $data) || $data['billing_street'] === null) {
            if ($existingInvoice === null) {
                $errors[] = 'billing_street is required.';
            }
        } else {
            $data['billing_street'] = (string) $data['billing_street'];
        }

        if (array_key_exists('shipping_street', $data) && $data['shipping_street'] !== null) {
            $data['shipping_street'] = (string) $data['shipping_street'];
        }

        $touched = array_values(array_unique($touched));

        return [
            'data'    => $data,
            'errors'  => array_values(array_unique($errors)),
            'touched' => $touched,
        ];
    }

    private function get_invoice_fields()
    {
        if ($this->invoiceFields === null) {
            $this->invoiceFields = $this->db->list_fields(db_prefix() . 'invoices');
        }

        return $this->invoiceFields;
    }

    private function normalize_allowed_payment_modes($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $unserialized = @unserialize($value);

            if ($unserialized !== false || $value === 'b:0;') {
                return (array) $unserialized;
            }
        }

        return [];
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
}
