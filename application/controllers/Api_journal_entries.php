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
            $filters[db_prefix() . 'journal_entries.entry_date'] = $entryDate;
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

            $filters[db_prefix() . 'journal_entries.created_by'] = (int) $createdBy;
        }

        $entries = $this->journal_entries_model->get(null, $filters);

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

        $entry = $this->journal_entries_model->get((int) $id);

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

        $entryId = $this->journal_entries_model->create($payload);
        $entry   = $this->journal_entries_model->get($entryId);

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

        $existing = $this->journal_entries_model->get((int) $id);
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

        $this->journal_entries_model->update_entry((int) $id, $payload);
        $entry = $this->journal_entries_model->get((int) $id);

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

        $existing = $this->journal_entries_model->get((int) $id);
        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Journal entry not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->journal_entries_model->delete((int) $id);

        $this->response([
            'status'  => true,
            'message' => 'Journal entry deleted successfully.',
        ], self::HTTP_OK);
    }

    private function buildEntryPayloadFromRequest($method, $requireTitle = true)
    {
        $title = $this->{$method}('title');
        $title = is_string($title) ? trim($title) : $title;

        if ($requireTitle && ($title === null || $title === '')) {
            $this->response([
                'status'  => false,
                'message' => 'The title field is required.',
            ], self::HTTP_BAD_REQUEST);

            return null;
        }

        $payload = [];

        if ($title !== null && $title !== '') {
            $payload['title'] = $title;
        }

        $content = $this->{$method}('content');
        if ($content !== null) {
            $payload['content'] = (string) $content;
        }

        $entryDateRaw = $this->{$method}('entry_date');
        if ($entryDateRaw !== null && $entryDateRaw !== '') {
            $entryDate = $this->normalize_date($entryDateRaw);

            if ($entryDate === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid entry_date value provided. Expected format: YYYY-MM-DD.',
                ], self::HTTP_BAD_REQUEST);

                return null;
            }

            $payload['entry_date'] = $entryDate;
        } elseif ($requireTitle) {
            $payload['entry_date'] = date('Y-m-d');
        }

        if ($requireTitle) {
            $payload['created_by'] = $this->getAuthenticatedUserId();
        }

        return $payload;
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
            $entry['entry_date'] = $formatDate($entry['entry_date'] ?? null);
            $entry['created_at'] = $formatDate($entry['created_at'] ?? null);
            $entry['updated_at'] = $formatDate($entry['updated_at'] ?? null);

            if (isset($entry['created_by']) && $entry['created_by'] !== null && $entry['created_by'] !== '') {
                $entry['created_by'] = (int) $entry['created_by'];
            }

            return $entry;
        }

        if (is_object($entry)) {
            $entry->entry_date = $formatDate($entry->entry_date ?? null);
            $entry->created_at = $formatDate($entry->created_at ?? null);
            $entry->updated_at = $formatDate($entry->updated_at ?? null);

            if (isset($entry->created_by) && $entry->created_by !== null && $entry->created_by !== '') {
                $entry->created_by = (int) $entry->created_by;
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
