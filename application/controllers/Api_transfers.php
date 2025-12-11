<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_transfers extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('accounting/accounting_model');
    }

    public function transfers_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $transferFrom   = $this->get('transfer_funds_from');
        $transferTo     = $this->get('transfer_funds_to');
        $startDateInput = $this->get('start_date') ?? $this->get('from_date');
        $endDateInput   = $this->get('end_date') ?? $this->get('to_date');

        $startDate = $this->normalize_date($startDateInput);
        $endDate   = $this->normalize_date($endDateInput);

        if (($startDateInput !== null && $startDate === null) || ($endDateInput !== null && $endDate === null)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid date format provided. Please supply dates in YYYY-MM-DD format.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if ($startDate !== null && $endDate !== null && strtotime($startDate) > strtotime($endDate)) {
            $this->response([
                'status'  => false,
                'message' => 'The start_date must be on or before end_date.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $this->db->from(db_prefix() . 'acc_transfers');

        if ($transferFrom !== null && $transferFrom !== '') {
            if (!is_numeric($transferFrom)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid transfer_funds_from value provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $this->db->where('transfer_funds_from', (int) $transferFrom);
        }

        if ($transferTo !== null && $transferTo !== '') {
            if (!is_numeric($transferTo)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid transfer_funds_to value provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $this->db->where('transfer_funds_to', (int) $transferTo);
        }

        if ($startDate !== null) {
            $this->db->where('date >=', $startDate);
        }

        if ($endDate !== null) {
            $this->db->where('date <=', $endDate);
        }

        $this->db->order_by('date', 'DESC');

        $transfers = $this->db->get()->result_array();

        $result = array_map([$this, 'format_transfer'], $transfers);

        $this->response([
            'status' => true,
            'result' => $result,
        ], self::HTTP_OK);
    }

    public function transfer_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid transfer identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $transfer = $this->accounting_model->get_transfer((int) $id);

        if (!$transfer) {
            $this->response([
                'status'  => false,
                'message' => 'Transfer not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_transfer((array) $transfer),
        ], self::HTTP_OK);
    }

    public function transfers_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $payload = $this->buildTransferPayload('post');
        if ($payload === null) {
            return;
        }

        $result = $this->accounting_model->add_transfer($payload);

        if ($result === 'close_the_book') {
            $this->response([
                'status'  => false,
                'message' => 'The books are closed for the selected date.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create transfer.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $transfer = $this->accounting_model->get_transfer($this->db->insert_id());

        $this->response([
            'status' => true,
            'result' => $this->format_transfer((array) $transfer),
        ], self::HTTP_CREATED);
    }

    public function transfer_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid transfer identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->accounting_model->get_transfer((int) $id);
        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Transfer not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $payload = $this->buildTransferPayload('put', false);
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

        $result = $this->accounting_model->update_transfer($payload, (int) $id);

        if ($result === 'close_the_book') {
            $this->response([
                'status'  => false,
                'message' => 'The books are closed for the selected date.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update the transfer.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $transfer = $this->accounting_model->get_transfer((int) $id);

        $this->response([
            'status' => true,
            'result' => $this->format_transfer((array) $transfer),
        ], self::HTTP_OK);
    }

    public function transfer_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid transfer identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->accounting_model->get_transfer((int) $id);
        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Transfer not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $deleted = $this->accounting_model->delete_transfer((int) $id);

        $this->response([
            'status'  => (bool) $deleted,
            'message' => $deleted ? 'Transfer deleted successfully.' : 'Unable to delete transfer.',
        ], $deleted ? self::HTTP_OK : self::HTTP_BAD_REQUEST);
    }

    private function buildTransferPayload($method, $isCreate = true)
    {
        $input   = $this->getRequestInput($method);
        $payload = [];

        if (isset($input['transfer_funds_from'])) {
            if (!is_numeric($input['transfer_funds_from'])) {
                $this->response([
                    'status'  => false,
                    'message' => 'transfer_funds_from must be a numeric account identifier.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['transfer_funds_from'] = (int) $input['transfer_funds_from'];
        } elseif ($isCreate) {
            $this->response([
                'status'  => false,
                'message' => 'transfer_funds_from is required.',
            ], self::HTTP_BAD_REQUEST);

            return null;
        }

        if (isset($input['transfer_funds_to'])) {
            if (!is_numeric($input['transfer_funds_to'])) {
                $this->response([
                    'status'  => false,
                    'message' => 'transfer_funds_to must be a numeric account identifier.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['transfer_funds_to'] = (int) $input['transfer_funds_to'];
        } elseif ($isCreate) {
            $this->response([
                'status'  => false,
                'message' => 'transfer_funds_to is required.',
            ], self::HTTP_BAD_REQUEST);

            return null;
        }

        if (isset($input['transfer_amount'])) {
            $amount = $this->normalize_decimal($input['transfer_amount']);

            if ($amount <= 0) {
                $this->response([
                    'status'  => false,
                    'message' => 'transfer_amount must be greater than zero.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['transfer_amount'] = $this->format_decimal_string($amount);
        } elseif ($isCreate) {
            $this->response([
                'status'  => false,
                'message' => 'transfer_amount is required.',
            ], self::HTTP_BAD_REQUEST);

            return null;
        }

        $dateRaw = $input['date'] ?? null;
        if ($dateRaw !== null && $dateRaw !== '') {
            $date = $this->normalize_date($dateRaw);

            if ($date === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid date value provided. Expected format: YYYY-MM-DD.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['date'] = $date;
        } elseif ($isCreate) {
            $payload['date'] = date('Y-m-d');
        }

        if (isset($input['description'])) {
            $payload['description'] = (string) $input['description'];
        }

        return $payload;
    }

    private function format_transfer(array $transfer)
    {
        return [
            'id'                  => isset($transfer['id']) ? (int) $transfer['id'] : null,
            'transfer_funds_from' => isset($transfer['transfer_funds_from']) ? (int) $transfer['transfer_funds_from'] : null,
            'transfer_funds_to'   => isset($transfer['transfer_funds_to']) ? (int) $transfer['transfer_funds_to'] : null,
            'transfer_amount'     => $this->format_decimal_string($this->normalize_decimal($transfer['transfer_amount'] ?? 0)),
            'date'                => $this->normalize_date($transfer['date'] ?? null) ?? ($transfer['date'] ?? null),
            'description'         => $transfer['description'] ?? '',
            'datecreated'         => $transfer['datecreated'] ?? null,
            'addedfrom'           => isset($transfer['addedfrom']) ? (int) $transfer['addedfrom'] : null,
        ];
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

    private function normalize_decimal($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) str_replace(',', '', $value);
    }

    private function format_decimal_string($value)
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function normalize_date($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function ensureAuthenticated()
    {
        $is_logged_in = is_staff_logged_in();

        if (!$is_logged_in) {
            $headerToken = $this->input->get_request_header('Authorization');
            $token       = $headerToken ? trim(str_ireplace('Bearer', '', $headerToken)) : null;

            if ($token) {
                try {
                    $validationResponse = $this->authorization_token->validateToken($token);
                    $is_logged_in       = !empty($validationResponse) && !empty($validationResponse['status']);

                    if ($is_logged_in) {
                        $this->tokenPayload = $validationResponse['data'] ?? null;
                    }
                } catch (Exception $e) {
                    $is_logged_in = false;
                }
            }
        }

        if ($is_logged_in) {
            return true;
        }

        $this->response([
            'status'  => false,
            'message' => 'Unauthorized. Please provide a valid authentication token.',
        ], self::HTTP_UNAUTHORIZED);

        return false;
    }
}
