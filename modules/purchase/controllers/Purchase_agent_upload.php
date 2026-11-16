<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Receives a binary receipt uploaded from Claude's code-execution sandbox.
 * The bearer token is short-lived and single-use. This endpoint does not
 * create captures or post anything to purchasing, inventory, or accounting.
 */
class Purchase_agent_upload extends CI_Controller
{
    private const MAX_BYTES = 31457280;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('purchase/purchase_agent_model');
    }

    public function index($token = '')
    {
        header('Cache-Control: no-store');
        header('Content-Type: application/json');

        if ($this->input->server('REQUEST_METHOD') !== 'PUT') {
            header('Allow: PUT');
            $this->_send(405, ['error' => 'Use PUT with the raw image or PDF body.']);
        }

        $length = (int) ($this->input->server('CONTENT_LENGTH') ?: 0);
        if ($length > self::MAX_BYTES) {
            $this->_send(413, ['error' => 'Receipt file exceeds the 30 MB limit.']);
        }

        $body = file_get_contents('php://input', false, null, 0, self::MAX_BYTES + 1);
        if ($body === false || $body === '') {
            $this->_send(400, ['error' => 'Empty upload.']);
        }
        if (strlen($body) > self::MAX_BYTES) {
            $this->_send(413, ['error' => 'Receipt file exceeds the 30 MB limit.']);
        }

        $result = $this->purchase_agent_model->store_receipt_upload(
            (string) $token,
            $body,
            (string) ($this->input->server('CONTENT_TYPE') ?: 'application/octet-stream')
        );
        $this->_send(!empty($result['error']) ? 400 : 200, $result);
    }

    private function _send(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
