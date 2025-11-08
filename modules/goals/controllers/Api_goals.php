<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_goals extends API_Controller
{
    public function __construct()
    {
        $this->module_language_file      = 'goals';
        $this->module_language_directory = APP_MODULES_PATH . 'goals/';

        parent::__construct();

        $this->load->model('goals_model');
    }

    public function goals_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $exclude_notified = $this->get('exclude_notified');
        $staff_id         = $this->get('staff_id');

        $exclude_notified = filter_var($exclude_notified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $exclude_notified = $exclude_notified === null ? false : $exclude_notified;

        if (is_numeric($staff_id)) {
            $goals = $this->goals_model->get_staff_goals((int) $staff_id, $exclude_notified);
        } else {
            $goals = $this->goals_model->get('', $exclude_notified);
        }

        $this->response([
            'status' => true,
            'result' => $goals,
        ], self::HTTP_OK);
    }

    public function goals_post()
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

        $subject   = trim((string) ($payload['subject'] ?? ''));
        $goal_type = $payload['goal_type'] ?? '';
        $start     = trim((string) ($payload['start_date'] ?? ''));
        $end       = trim((string) ($payload['end_date'] ?? ''));

        if ($subject === '' || $goal_type === '' || $start === '' || $end === '') {
            $this->response([
                'status'  => false,
                'message' => 'Subject, goal type, start date, and end date are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $goal_data = $this->prepare_goal_payload($payload, false);

        $goal_id = $this->goals_model->add($goal_data);

        if (!$goal_id) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create goal with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => [
                'id'      => $goal_id,
                'subject' => $subject,
            ],
        ], self::HTTP_CREATED);
    }

    public function goal_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid goal identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $goal = $this->goals_model->get((int) $id);

        if (!$goal) {
            $this->response([
                'status'  => false,
                'message' => 'Goal not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $goal,
        ], self::HTTP_OK);
    }

    public function goal_put($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid goal identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $goal = $this->goals_model->get((int) $id);

        if (!$goal) {
            $this->response([
                'status'  => false,
                'message' => 'Goal not found.',
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

        $goal_data = $this->prepare_goal_payload($payload, true);

        if ($goal_data === []) {
            $this->response([
                'status'  => false,
                'message' => 'No updatable fields were provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->goals_model->update($goal_data, (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Goal update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Goal updated successfully.',
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

    private function prepare_goal_payload(array $input, bool $is_update)
    {
        $allowed_fields = [
            'subject',
            'description',
            'goal_type',
            'staff_id',
            'achievement',
            'start_date',
            'end_date',
            'contract_type',
            'notify_when_fail',
            'notify_when_achieve',
        ];

        $payload = [];

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = $input[$field];
            }
        }

        if (!$is_update) {
            if (!isset($payload['description'])) {
                $payload['description'] = '';
            }

            if (!isset($payload['achievement']) || $payload['achievement'] === '') {
                $payload['achievement'] = 0;
            }

            if (!isset($payload['contract_type']) || $payload['contract_type'] === '') {
                $payload['contract_type'] = 0;
            }

            if (!isset($payload['staff_id']) || $payload['staff_id'] === '') {
                $payload['staff_id'] = 0;
            }
        }

        if (isset($payload['subject'])) {
            $payload['subject'] = trim((string) $payload['subject']);
        }

        if (isset($payload['description'])) {
            $payload['description'] = (string) $payload['description'];
        }

        if (isset($payload['goal_type']) && $payload['goal_type'] !== '') {
            $payload['goal_type'] = (int) $payload['goal_type'];
        } elseif (!$is_update) {
            unset($payload['goal_type']);
        }

        if (isset($payload['staff_id']) && $payload['staff_id'] !== '') {
            $payload['staff_id'] = (int) $payload['staff_id'];
        }

        if (isset($payload['achievement']) && $payload['achievement'] !== '') {
            $payload['achievement'] = (int) $payload['achievement'];
        }

        if (isset($payload['contract_type']) && $payload['contract_type'] !== '') {
            $payload['contract_type'] = (int) $payload['contract_type'];
        }

        if (isset($payload['notify_when_fail'])) {
            $payload['notify_when_fail'] = $this->interpret_boolean($payload['notify_when_fail']) ? 1 : null;
        }

        if (isset($payload['notify_when_achieve'])) {
            $payload['notify_when_achieve'] = $this->interpret_boolean($payload['notify_when_achieve']) ? 1 : null;
        }

        if (isset($payload['start_date'])) {
            $payload['start_date'] = trim((string) $payload['start_date']);
        }

        if (isset($payload['end_date'])) {
            $payload['end_date'] = trim((string) $payload['end_date']);
        }

        return $payload;
    }

    private function interpret_boolean($value)
    {
        if (is_bool($value)) {
            return $value;
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

        return false;
    }
}
