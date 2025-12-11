<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_account_history extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
    }

    public function account_history_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $account   = $this->get('account');
        $relId     = $this->get('rel_id');
        $relType   = $this->get('rel_type');
        $fromInput = $this->get('start_date') ?? $this->get('from_date');
        $toInput   = $this->get('end_date') ?? $this->get('to_date');

        $startDate = $this->normalize_date($fromInput);
        $endDate   = $this->normalize_date($toInput);

        if (($fromInput !== null && $startDate === null) || ($toInput !== null && $endDate === null)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid date format provided. Use YYYY-MM-DD.',
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

        $this->db->from(db_prefix() . 'acc_account_history');

        if ($account !== null && $account !== '') {
            if (!is_numeric($account)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid account identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $this->db->where('account', (int) $account);
        }

        if ($relId !== null && $relId !== '') {
            if (!is_numeric($relId)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid rel_id provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $this->db->where('rel_id', (int) $relId);
        }

        if ($relType !== null && $relType !== '') {
            $this->db->where('rel_type', $relType);
        }

        if ($startDate !== null) {
            $this->db->where('date >=', $startDate);
        }

        if ($endDate !== null) {
            $this->db->where('date <=', $endDate);
        }

        $this->db->order_by('date', 'DESC');

        $history = $this->db->get()->result_array();

        $this->response([
            'status' => true,
            'result' => array_map([$this, 'format_history'], $history),
        ], self::HTTP_OK);
    }

    public function account_history_item_get($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account history identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $record = $this->db
            ->where('id', (int) $id)
            ->get(db_prefix() . 'acc_account_history')
            ->row_array();

        if (!$record) {
            $this->response([
                'status'  => false,
                'message' => 'Account history record not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_history($record),
        ], self::HTTP_OK);
    }

    public function account_history_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $payload = $this->buildPayloadFromRequest('post');
        if ($payload === null) {
            return;
        }

        $this->db->insert(db_prefix() . 'acc_account_history', $payload);
        $insertId = $this->db->insert_id();

        if (!$insertId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create account history record.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $record = $this->db
            ->where('id', $insertId)
            ->get(db_prefix() . 'acc_account_history')
            ->row_array();

        $this->response([
            'status' => true,
            'result' => $this->format_history($record),
        ], self::HTTP_CREATED);
    }

    public function account_history_item_put($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account history identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->db
            ->where('id', (int) $id)
            ->get(db_prefix() . 'acc_account_history')
            ->row_array();

        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Account history record not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $payload = $this->buildPayloadFromRequest('put', false);
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

        $this->db->where('id', (int) $id);
        $updated = $this->db->update(db_prefix() . 'acc_account_history', $payload);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update account history record.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $record = $this->db
            ->where('id', (int) $id)
            ->get(db_prefix() . 'acc_account_history')
            ->row_array();

        $this->response([
            'status' => true,
            'result' => $this->format_history($record),
        ], self::HTTP_OK);
    }

    public function account_history_item_delete($id = null)
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid account history identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->db
            ->where('id', (int) $id)
            ->get(db_prefix() . 'acc_account_history')
            ->row_array();

        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Account history record not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->db->where('id', (int) $id);
        $deleted = $this->db->delete(db_prefix() . 'acc_account_history');

        $this->response([
            'status'  => (bool) $deleted,
            'message' => $deleted ? 'Account history record deleted successfully.' : 'Unable to delete account history record.',
        ], $deleted ? self::HTTP_OK : self::HTTP_BAD_REQUEST);
    }

    private function buildPayloadFromRequest($method, $requireMandatory = true)
    {
        $input   = $this->getRequestInput($method);
        $payload = [];

        if (isset($input['account'])) {
            if (!is_numeric($input['account'])) {
                $this->response([
                    'status'  => false,
                    'message' => 'account must be a numeric identifier.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['account'] = (int) $input['account'];
        } elseif ($requireMandatory) {
            $this->response([
                'status'  => false,
                'message' => 'account is required.',
            ], self::HTTP_BAD_REQUEST);

            return null;
        }

        if (isset($input['split'])) {
            if (!is_numeric($input['split'])) {
                $this->response([
                    'status'  => false,
                    'message' => 'split must be a numeric identifier.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['split'] = (int) $input['split'];
        }

        if (isset($input['parent_account'])) {
            if (!is_numeric($input['parent_account'])) {
                $this->response([
                    'status'  => false,
                    'message' => 'parent_account must be numeric.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['parent_account'] = (int) $input['parent_account'];
        }

        if (isset($input['rel_id'])) {
            if (!is_numeric($input['rel_id'])) {
                $this->response([
                    'status'  => false,
                    'message' => 'rel_id must be numeric.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['rel_id'] = (int) $input['rel_id'];
        }

        if (isset($input['rel_type'])) {
            $payload['rel_type'] = (string) $input['rel_type'];
        }

        if (isset($input['customer'])) {
            if (!is_numeric($input['customer'])) {
                $this->response([
                    'status'  => false,
                    'message' => 'customer must be numeric.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['customer'] = (int) $input['customer'];
        }

        if (isset($input['description'])) {
            $payload['description'] = (string) $input['description'];
        }

        if (isset($input['debit'])) {
            $payload['debit'] = $this->format_decimal_string($this->normalize_decimal($input['debit']));
        }

        if (isset($input['credit'])) {
            $payload['credit'] = $this->format_decimal_string($this->normalize_decimal($input['credit']));
        }

        if ($requireMandatory && (!isset($payload['debit']) && !isset($payload['credit']))) {
            $this->response([
                'status'  => false,
                'message' => 'Either debit or credit must be provided.',
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
        } elseif ($requireMandatory) {
            $payload['date'] = date('Y-m-d');
        }

        if (isset($input['datecreated'])) {
            $payload['datecreated'] = $input['datecreated'];
        } elseif ($requireMandatory) {
            $payload['datecreated'] = date('Y-m-d H:i:s');
        }

        if ($this->tokenPayload && isset($this->tokenPayload->user_id)) {
            $payload['addedfrom'] = (int) $this->tokenPayload->user_id;
        } elseif (isset($input['addedfrom'])) {
            $payload['addedfrom'] = (int) $input['addedfrom'];
        }

        return $payload;
    }

    private function format_history(array $record)
    {
        return [
            'id'             => isset($record['id']) ? (int) $record['id'] : null,
            'account'        => isset($record['account']) ? (int) $record['account'] : null,
            'parent_account' => isset($record['parent_account']) ? (int) $record['parent_account'] : null,
            'split'          => isset($record['split']) ? (int) $record['split'] : null,
            'rel_id'         => isset($record['rel_id']) ? (int) $record['rel_id'] : null,
            'rel_type'       => $record['rel_type'] ?? null,
            'customer'       => isset($record['customer']) ? (int) $record['customer'] : null,
            'debit'          => $this->format_decimal_string($this->normalize_decimal($record['debit'] ?? 0)),
            'credit'         => $this->format_decimal_string($this->normalize_decimal($record['credit'] ?? 0)),
            'description'    => $record['description'] ?? '',
            'date'           => $this->normalize_date($record['date'] ?? null) ?? ($record['date'] ?? null),
            'datecreated'    => $record['datecreated'] ?? null,
            'addedfrom'      => isset($record['addedfrom']) ? (int) $record['addedfrom'] : null,
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
