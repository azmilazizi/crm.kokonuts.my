<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_invoice_payment_records extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('payments_model');
    }

    public function invoice_payment_records_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $invoiceId = $this->get('invoice_id');
        if ($invoiceId !== null && $invoiceId !== '') {
            if (!is_numeric($invoiceId)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid invoice identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $records = $this->payments_model->get_invoice_payments((int) $invoiceId);
        } else {
            $this->db->select('*,' . db_prefix() . 'invoicepaymentrecords.id as paymentid');
            $this->db->join(
                db_prefix() . 'payment_modes',
                db_prefix() . 'payment_modes.id = ' . db_prefix() . 'invoicepaymentrecords.paymentmode',
                'left'
            );
            $this->db->order_by(db_prefix() . 'invoicepaymentrecords.id', 'asc');
            $records = $this->db->get(db_prefix() . 'invoicepaymentrecords')->result_array();
        }

        $formatted = array_map(function ($record) {
            return $this->format_payment_record($record);
        }, $records ?? []);

        $this->response([
            'status' => true,
            'result' => $formatted,
        ], self::HTTP_OK);
    }

    public function invoice_payment_record_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid payment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $payment = $this->payments_model->get((int) $id);

        if (!$payment) {
            $this->response([
                'status'  => false,
                'message' => 'Payment record not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_payment_record($payment),
        ], self::HTTP_OK);
    }

    public function invoice_payment_records_post()
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

        $records = $this->extract_bulk_payload($payload);
        if ($records !== null) {
            if ($records === []) {
                $this->response([
                    'status'  => false,
                    'message' => 'Empty payment records payload provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $normalizedRecords = [];
            $errors            = [];

            foreach ($records as $index => $record) {
                if (!is_array($record)) {
                    $errors[$index] = ['Each record must be an object.'];
                    continue;
                }

                $normalized = $this->prepare_payment_payload($record);

                if ($normalized['errors'] !== []) {
                    $errors[$index] = $normalized['errors'];
                    continue;
                }

                $normalizedRecords[] = $normalized['data'];
            }

            if ($errors !== []) {
                $this->response([
                    'status'  => false,
                    'message' => $errors,
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $results = [];

            foreach ($normalizedRecords as $record) {
                $paymentId = $this->payments_model->add($record);

                if (!$paymentId) {
                    $this->response([
                        'status'  => false,
                        'message' => 'Payment record creation failed.',
                    ], self::HTTP_BAD_REQUEST);

                    return;
                }

                $this->load->model('accounting/accounting_model');
                if (get_option('acc_payment_automatic_conversion') == 1
                    || get_option('acc_active_payment_mode_mapping') == 1) {
                    $this->accounting_model->automatic_payment_conversion($paymentId);
                }

                $payment  = $this->payments_model->get($paymentId);
                $results[] = $this->format_payment_record($payment);
            }

            $this->response([
                'status' => true,
                'result' => $results,
            ], self::HTTP_OK);

            return;
        }

        $normalized = $this->prepare_payment_payload($payload);

        if ($normalized['errors'] !== []) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $paymentId = $this->payments_model->add($normalized['data']);

        if (!$paymentId) {
            $this->response([
                'status'  => false,
                'message' => 'Payment record creation failed.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $this->load->model('accounting/accounting_model');
        if (get_option('acc_payment_automatic_conversion') == 1
            || get_option('acc_active_payment_mode_mapping') == 1) {
            $this->accounting_model->automatic_payment_conversion($paymentId);
        }

        $payment = $this->payments_model->get($paymentId);

        $this->response([
            'status' => true,
            'result' => $this->format_payment_record($payment),
        ], self::HTTP_OK);
    }

    public function invoice_payment_record_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid payment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $payment = $this->payments_model->get((int) $id);

        if (!$payment) {
            $this->response([
                'status'  => false,
                'message' => 'Payment record not found.',
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

        $normalized = $this->prepare_payment_payload($payload, $payment);

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

        $updated = $this->payments_model->update($normalized['data'], (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Payment record update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $updatedPayment = $this->payments_model->get((int) $id);

        $this->response([
            'status' => true,
            'result' => $this->format_payment_record($updatedPayment),
        ], self::HTTP_OK);
    }

    public function invoice_payment_record_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid payment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $payment = $this->payments_model->get((int) $id);

        if (!$payment) {
            $this->response([
                'status'  => false,
                'message' => 'Payment record not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $result = $this->payments_model->delete((int) $id);

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Payment record could not be deleted.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Payment record deleted successfully.',
        ], self::HTTP_OK);
    }

    private function prepare_payment_payload(array $input, $existingPayment = null)
    {
        $errors  = [];
        $data    = [];
        $touched = [];

        if (array_key_exists('invoiceid', $input)) {
            $data['invoiceid'] = $input['invoiceid'];
            $touched[]         = 'invoiceid';
        } elseif ($existingPayment !== null) {
            $data['invoiceid'] = $existingPayment->invoiceid ?? null;
        }

        if (!array_key_exists('invoiceid', $data) || $data['invoiceid'] === null || $data['invoiceid'] === '') {
            if ($existingPayment === null) {
                $errors[] = 'invoiceid is required.';
            }
        } elseif (!is_numeric($data['invoiceid'])) {
            $errors[] = 'Invalid invoiceid provided.';
        } else {
            $data['invoiceid'] = (int) $data['invoiceid'];
        }

        if (array_key_exists('amount', $input)) {
            $data['amount'] = $input['amount'];
            $touched[]      = 'amount';
        } elseif ($existingPayment !== null) {
            $data['amount'] = $existingPayment->amount ?? null;
        }

        if (!array_key_exists('amount', $data) || $data['amount'] === null || $data['amount'] === '') {
            if ($existingPayment === null) {
                $errors[] = 'amount is required.';
            }
        } elseif (!is_numeric($data['amount'])) {
            $errors[] = 'Invalid amount provided.';
        }

        if (array_key_exists('paymentmode', $input)) {
            $data['paymentmode'] = $input['paymentmode'];
            $touched[]           = 'paymentmode';
        } elseif ($existingPayment !== null) {
            $data['paymentmode'] = $existingPayment->paymentmode ?? null;
        }

        if (!array_key_exists('paymentmode', $data) || $data['paymentmode'] === null || $data['paymentmode'] === '') {
            if ($existingPayment === null) {
                $errors[] = 'paymentmode is required.';
            }
        }

        if (array_key_exists('transactionid', $input)) {
            $data['transactionid'] = $input['transactionid'];
            $touched[]             = 'transactionid';
        } elseif ($existingPayment !== null && isset($existingPayment->transactionid)) {
            $data['transactionid'] = $existingPayment->transactionid;
        }

        if (array_key_exists('date', $input)) {
            $data['date'] = $input['date'];
            $touched[]    = 'date';
        } elseif ($existingPayment !== null) {
            $data['date'] = $existingPayment->date ?? null;
        }

        if (array_key_exists('note', $input)) {
            $data['note'] = $input['note'];
            $touched[]    = 'note';
        } elseif ($existingPayment !== null) {
            $data['note'] = $this->convert_breaks_to_newlines($existingPayment->note ?? '');
        }

        if (array_key_exists('date', $data) && $data['date'] !== null && $data['date'] !== '') {
            $normalizedDate = $this->normalize_date($data['date']);
            if ($normalizedDate === null) {
                $errors[] = 'Invalid date value provided. Expected format: YYYY-MM-DD.';
            } else {
                $data['date'] = $normalizedDate;
            }
        }

        if (array_key_exists('daterecorded', $input)) {
            $data['daterecorded'] = $input['daterecorded'];
            $touched[]            = 'daterecorded';
        } elseif ($existingPayment !== null) {
            $data['daterecorded'] = $existingPayment->daterecorded ?? null;
        }

        if (array_key_exists('daterecorded', $data) && $data['daterecorded'] !== null && $data['daterecorded'] !== '') {
            $normalizedRecordedAt = $this->normalize_datetime($data['daterecorded']);
            if ($normalizedRecordedAt === null) {
                $errors[] = 'Invalid daterecorded value provided. Expected format: YYYY-MM-DD HH:MM:SS.';
            } else {
                $data['daterecorded'] = $normalizedRecordedAt;
            }
        }

        $touched = array_values(array_unique($touched));

        return [
            'data'    => $data,
            'errors'  => array_values(array_unique($errors)),
            'touched' => $touched,
        ];
    }

    private function format_payment_record($payment)
    {
        if (is_array($payment)) {
            if (isset($payment['note'])) {
                $payment['note'] = $this->convert_breaks_to_newlines($payment['note']);
            }

            return $payment;
        }

        if (is_object($payment)) {
            if (isset($payment->note)) {
                $payment->note = $this->convert_breaks_to_newlines($payment->note);
            }
        }

        return $payment;
    }

    private function extract_bulk_payload(array $payload)
    {
        if (array_key_exists('records', $payload) && is_array($payload['records'])) {
            return $payload['records'];
        }

        if ($this->is_list_payload($payload)) {
            return $payload;
        }

        return null;
    }

    private function is_list_payload(array $payload)
    {
        if ($payload === []) {
            return false;
        }

        $keys = array_keys($payload);

        return $keys === range(0, count($payload) - 1);
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

    private function normalize_datetime($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', DateTime::RFC3339, DateTime::ATOM];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);

            if ($date instanceof DateTime) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($value);

        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
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
