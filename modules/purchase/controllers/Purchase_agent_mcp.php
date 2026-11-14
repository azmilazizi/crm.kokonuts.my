<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Claude-facing purchase workflow MCP endpoint.
 *
 * This endpoint is intentionally separate from the read-only /mcp connector.
 * It exposes preparation, matching and approval records only. Posting into
 * the official purchase, warehouse or accounting ledgers will be added behind
 * explicit validation and approval gates.
 */
class Purchase_agent_mcp extends CI_Controller
{
    const MAX_REQUEST_BYTES = 262144;
    const PROTOCOL_VERSIONS  = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('purchase/purchase_agent_model');
    }

    public function index($token = '')
    {
        header('Cache-Control: no-store');
        header('Content-Type: application/json');

        $expected = (string) get_option('purchase_agent_mcp_token');
        if (strlen($expected) < 32 || !hash_equals($expected, (string) $token)) {
            $this->_send(404, ['error' => 'Not found']);
        }

        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            header('Allow: POST');
            $this->_send(405, $this->_rpc_error(null, -32600, 'Method not allowed'));
        }

        $raw = file_get_contents('php://input', false, null, 0, self::MAX_REQUEST_BYTES + 1);
        if (strlen($raw) > self::MAX_REQUEST_BYTES) {
            $this->_send(413, $this->_rpc_error(null, -32600, 'Request too large'));
        }

        $body = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->_send(400, $this->_rpc_error(null, -32700, 'Parse error'));
        }

        $messages  = is_array($body) ? $body : [$body];
        $responses = [];
        foreach ($messages as $message) {
            $response = $this->_handle_message($message);
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        if (!$responses) {
            http_response_code(202);
            return;
        }

        $this->_send(200, is_array($body) ? $responses : $responses[0]);
    }

    private function _handle_message($message)
    {
        if (!($message instanceof stdClass)) {
            return $this->_rpc_error(null, -32600, 'Invalid request');
        }

        $message = (array) $message;
        $id      = $message['id'] ?? null;
        $hasId   = array_key_exists('id', $message);

        if (($message['jsonrpc'] ?? null) !== '2.0' || !isset($message['method'])) {
            return $this->_rpc_error($id, -32600, 'Invalid request');
        }
        if (!$hasId) {
            return null;
        }

        $params = $message['params'] ?? new stdClass();
        if (!$params instanceof stdClass) {
            return $this->_rpc_error($id, -32602, 'Params must be an object');
        }
        $params = (array) $params;

        switch ($message['method']) {
            case 'initialize':
                $requested = $params['protocolVersion'] ?? null;
                return $this->_rpc_result($id, [
                    'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true)
                        ? $requested : self::PROTOCOL_VERSIONS[0],
                    'capabilities' => ['tools' => ['listChanged' => false]],
                    'serverInfo' => [
                        'name' => 'kokonuts-purchase-agent',
                        'title' => 'Kokonuts Purchase Agent',
                        'version' => '0.1.0',
                    ],
                    'instructions' => 'Use these tools to prepare and review purchase captures. Do not assume a receipt is a purchase order; request approval before official posting.',
                ]);

            case 'ping':
                return $this->_rpc_result($id, new stdClass());

            case 'tools/list':
                return $this->_rpc_result($id, ['tools' => $this->_tools()]);

            case 'tools/call':
                $name = $params['name'] ?? '';
                if (!is_string($name) || (array_key_exists('arguments', $params) && !($params['arguments'] instanceof stdClass))) {
                    return $this->_rpc_error($id, -32602, 'Expected a tool name and arguments object');
                }
                $args = $params['arguments'] ?? new stdClass();
                if (!$args instanceof stdClass) {
                    return $this->_rpc_error($id, -32602, 'Arguments must be an object');
                }
                return $this->_rpc_result($id, $this->_call_tool($name, (array) $args));

            default:
                return $this->_rpc_error($id, -32601, 'Method not found: ' . $message['method']);
        }
    }

    private function _tools()
    {
        $write = ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false];
        return [
            [
                'name' => 'create_purchase_capture',
                'description' => 'Create an auditable purchase receipt capture from a receipt or invoice. This does not post inventory or accounting records.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'vendor_id' => ['type' => 'integer'],
                    'vendor_name' => ['type' => 'string'],
                    'warehouse_id' => ['type' => 'integer'],
                    'invoice_number' => ['type' => 'string'],
                    'purchase_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'currency' => ['type' => 'string'],
                    'subtotal' => ['type' => 'number'],
                    'tax' => ['type' => 'number'],
                    'discount' => ['type' => 'number'],
                    'shipping' => ['type' => 'number'],
                    'grand_total' => ['type' => 'number'],
                    'receipt_hash' => ['type' => 'string'],
                    'raw_extraction' => ['type' => 'object'],
                    'source_reference' => ['type' => 'string'],
                ]],
                'annotations' => $write,
            ],
            [
                'name' => 'add_purchase_line',
                'description' => 'Add one extracted line to a purchase capture. Use needs_review when the item or unit is uncertain.',
                'inputSchema' => ['type' => 'object', 'required' => ['capture_id', 'source_description'], 'properties' => [
                    'capture_id' => ['type' => 'integer'],
                    'source_line_number' => ['type' => 'integer'],
                    'source_description' => ['type' => 'string'],
                    'inventory_item_id' => ['type' => 'integer'],
                    'quantity' => ['type' => 'number'],
                    'purchase_unit' => ['type' => 'string'],
                    'base_quantity' => ['type' => 'number'],
                    'base_unit' => ['type' => 'string'],
                    'conversion_factor' => ['type' => 'number'],
                    'unit_price' => ['type' => 'number'],
                    'tax' => ['type' => 'number'],
                    'discount' => ['type' => 'number'],
                    'line_total' => ['type' => 'number'],
                    'match_method' => ['type' => 'string'],
                    'match_confidence' => ['type' => 'number'],
                    'status' => ['type' => 'string'],
                    'review_reason' => ['type' => 'string'],
                ]],
                'annotations' => $write,
            ],
            [
                'name' => 'get_purchase_capture',
                'description' => 'Read a purchase capture and its structured lines for review.',
                'inputSchema' => ['type' => 'object', 'required' => ['capture_id'], 'properties' => [
                    'capture_id' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'find_inventory_candidates',
                'description' => 'Find and rank possible inventory items for a supplier description. This only proposes candidates; it never assigns an item.',
                'inputSchema' => ['type' => 'object', 'required' => ['description'], 'properties' => [
                    'description' => ['type' => 'string'],
                    'vendor_id' => ['type' => 'integer'],
                    'limit' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'find_vendors',
                'description' => 'Find CRM vendors by name, tax number or phone number before creating a purchase order.',
                'inputSchema' => ['type' => 'object', 'required' => ['query'], 'properties' => [
                    'query' => ['type' => 'string'],
                    'limit' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'find_warehouses',
                'description' => 'Find active CRM outlets/warehouses for assigning the purchase order and later receiving voucher.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string'],
                    'limit' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'save_vendor_item_alias',
                'description' => 'Save a confirmed vendor description to inventory SKU mapping for future receipts.',
                'inputSchema' => ['type' => 'object', 'required' => ['vendor_description', 'inventory_item_id'], 'properties' => [
                    'vendor_id' => ['type' => 'integer'],
                    'vendor_description' => ['type' => 'string'],
                    'inventory_item_id' => ['type' => 'integer'],
                    'purchase_unit' => ['type' => 'string'],
                    'base_unit' => ['type' => 'string'],
                    'units_per_pack' => ['type' => 'number'],
                ]],
                'annotations' => $write,
            ],
            [
                'name' => 'request_purchase_approval',
                'description' => 'Request human approval before a purchase capture is posted to official purchasing or inventory records.',
                'inputSchema' => ['type' => 'object', 'required' => ['capture_id', 'requested_action'], 'properties' => [
                    'capture_id' => ['type' => 'integer'],
                    'requested_action' => ['type' => 'string'],
                ]],
                'annotations' => $write,
            ],
        ];
    }

    private function _call_tool(string $name, array $args)
    {
        try {
            switch ($name) {
                case 'create_purchase_capture':
                    $captureId = $this->purchase_agent_model->create_capture($args);
                    return $this->_text(['capture_id' => $captureId, 'status' => 'captured']);

                case 'add_purchase_line':
                    $captureId = (int) ($args['capture_id'] ?? 0);
                    if (!$this->purchase_agent_model->get_capture($captureId)) {
                        return $this->_text(['error' => 'Capture not found'], true);
                    }
                    $lineId = $this->purchase_agent_model->add_line($captureId, $args);
                    return $this->_text(['line_id' => $lineId, 'capture_id' => $captureId]);

                case 'get_purchase_capture':
                    $capture = $this->purchase_agent_model->get_capture((int) ($args['capture_id'] ?? 0));
                    return $this->_text($capture ?: ['error' => 'Capture not found'], !$capture);

                case 'find_inventory_candidates':
                    return $this->_text($this->purchase_agent_model->find_inventory_candidates(
                        (string) ($args['description'] ?? ''),
                        isset($args['vendor_id']) ? (int) $args['vendor_id'] : null,
                        (int) ($args['limit'] ?? 8)
                    ));

                case 'find_vendors':
                    return $this->_text($this->purchase_agent_model->find_vendors(
                        (string) ($args['query'] ?? ''),
                        (int) ($args['limit'] ?? 10)
                    ));

                case 'find_warehouses':
                    return $this->_text($this->purchase_agent_model->find_warehouses(
                        (string) ($args['query'] ?? ''),
                        (int) ($args['limit'] ?? 20)
                    ));

                case 'save_vendor_item_alias':
                    $args['normalized_description'] = $this->purchase_agent_model->normalize_description((string) $args['vendor_description']);
                    $args['confirmed_by'] = null;
                    $args['confirmed_at'] = date('Y-m-d H:i:s');
                    $aliasId = $this->purchase_agent_model->save_vendor_alias($args);
                    return $this->_text(['alias_id' => $aliasId]);

                case 'request_purchase_approval':
                    $captureId = (int) ($args['capture_id'] ?? 0);
                    if (!$this->purchase_agent_model->get_capture($captureId)) {
                        return $this->_text(['error' => 'Capture not found'], true);
                    }
                    $approvalId = $this->purchase_agent_model->create_approval($captureId, (string) $args['requested_action']);
                    return $this->_text(['approval_id' => $approvalId, 'status' => 'pending']);

                default:
                    return $this->_text(['error' => 'Unknown tool'], true);
            }
        } catch (Throwable $e) {
            return $this->_text(['error' => $e->getMessage()], true);
        }
    }

    private function _text($value, bool $isError = false): array
    {
        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]],
            'isError' => $isError,
        ];
    }

    private function _rpc_result($id, $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function _rpc_error($id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    private function _send(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
