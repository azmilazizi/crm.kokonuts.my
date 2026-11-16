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
        $this->load->model('purchase/admin_agent_model');
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
                    'instructions' => 'Use these tools for Kokonuts operational data entry: receipts, purchase and expense drafts, inventory matching, categories and chart-of-account proposals. Do not assume a receipt is a purchase order; request approval before official posting or configuration changes.',
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
                    'payment_total' => ['type' => 'number'],
                    'payment_method' => ['type' => 'string'],
                    'payment_date' => ['type' => 'string'],
                    'rounding_amount' => ['type' => 'number'],
                    'receipt_hash' => ['type' => 'string'],
                    'raw_extraction' => ['type' => 'object'],
                    'receipt_file_name' => ['type' => 'string', 'description' => 'Optional receipt image/PDF filename when receipt_image_base64 is supplied'],
                    'receipt_mime_type' => ['type' => 'string', 'description' => 'Optional MIME type, for example image/jpeg or application/pdf'],
                    'receipt_image_base64' => ['type' => 'string', 'description' => 'Optional base64-encoded receipt image/PDF for audit retention'],
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
                    'source_barcode' => ['type' => 'string'],
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
                    'destination' => ['type' => 'string', 'description' => 'po, expense, personal, or excluded'],
                    'expense_category_id' => ['type' => 'integer', 'description' => 'Expenses module reporting category'],
                    'bill_category_id' => ['type' => 'integer', 'description' => 'Accounting Bill category with debit/credit mapping'],
                ]],
                'annotations' => $write,
            ],
            [
                'name' => 'update_purchase_line',
                'description' => 'Update an existing purchase line after a candidate is confirmed. This does not post inventory or accounting.',
                'inputSchema' => ['type' => 'object', 'required' => ['line_id'], 'properties' => [
                    'line_id' => ['type' => 'integer'],
                    'source_barcode' => ['type' => 'string'],
                    'inventory_item_id' => ['type' => 'integer'],
                    'purchase_unit' => ['type' => 'string'],
                    'base_quantity' => ['type' => 'number'],
                    'base_unit' => ['type' => 'string'],
                    'conversion_factor' => ['type' => 'number'],
                    'match_method' => ['type' => 'string'],
                    'match_confidence' => ['type' => 'number'],
                    'status' => ['type' => 'string'],
                    'review_reason' => ['type' => 'string'],
                    'destination' => ['type' => 'string', 'description' => 'po, expense, personal, or excluded'],
                    'expense_category_id' => ['type' => 'integer'],
                    'bill_category_id' => ['type' => 'integer'],
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
                'name' => 'update_purchase_capture',
                'description' => 'Update an existing purchase capture in place, including saving raw receipt extraction. This prevents duplicate captures.',
                'inputSchema' => ['type' => 'object', 'required' => ['capture_id'], 'properties' => [
                    'capture_id' => ['type' => 'integer'],
                    'source_reference' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'vendor_id' => ['type' => 'integer'],
                    'vendor_name' => ['type' => 'string'],
                    'warehouse_id' => ['type' => 'integer'],
                    'invoice_number' => ['type' => 'string'],
                    'purchase_date' => ['type' => 'string'],
                    'currency' => ['type' => 'string'],
                    'subtotal' => ['type' => 'number'],
                    'tax' => ['type' => 'number'],
                    'discount' => ['type' => 'number'],
                    'shipping' => ['type' => 'number'],
                    'grand_total' => ['type' => 'number'],
                    'payment_total' => ['type' => 'number'],
                    'payment_method' => ['type' => 'string'],
                    'payment_date' => ['type' => 'string'],
                    'rounding_amount' => ['type' => 'number'],
                    'receipt_hash' => ['type' => 'string'],
                    'raw_extraction' => ['type' => 'object'],
                    'notes' => ['type' => 'string'],
                ]],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'discard_purchase_capture',
                'description' => 'Soft-discard an incorrect or duplicate purchase capture. The record is retained for audit and is not posted.',
                'inputSchema' => ['type' => 'object', 'required' => ['capture_id'], 'properties' => [
                    'capture_id' => ['type' => 'integer'],
                    'reason' => ['type' => 'string'],
                ]],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'key_in_receipt',
                'description' => 'Run the complete receipt key-in workflow: validate vendor and warehouse, suggest inventory matches, classify expenses, create separate PO/expense drafts, reconcile payment, and request approval. It stops without creating anything when a required decision is missing.',
                'inputSchema' => ['type' => 'object', 'required' => ['capture_id'], 'properties' => [
                    'capture_id' => ['type' => 'integer'], 'is_paid' => ['type' => 'boolean'], 'payment_mode_id' => ['type' => 'integer'],
                ]],
                'annotations' => $write,
            ],
            [
                'name' => 'find_inventory_candidates',
                'description' => 'Find and rank possible inventory items for a supplier description. This only proposes candidates; it never assigns an item.',
                'inputSchema' => ['type' => 'object', 'required' => ['description'], 'properties' => [
                    'description' => ['type' => 'string'],
                    'barcode' => ['type' => 'string'],
                    'vendor_id' => ['type' => 'integer'],
                    'limit' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'find_expense_categories',
                'description' => 'Find ordinary Expenses module categories for receipt lines that should not become inventory purchases. This is separate from accounting Bill categories.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string'], 'limit' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'find_bill_categories',
                'description' => 'Find accounting Bill categories and their debit/credit accounts. This is separate from Expenses module categories.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string'], 'limit' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_split_receipt_drafts',
                'description' => 'Create the PO draft and ordinary Expenses-module drafts for a classified mixed receipt. Bill categories are not used for ordinary receipt expenses. Only creates drafts; it does not finalize, post stock, or approve anything.',
                'inputSchema' => ['type' => 'object', 'required' => ['capture_id'], 'properties' => [
                    'capture_id' => ['type' => 'integer'], 'is_paid' => ['type' => 'boolean'], 'payment_mode_id' => ['type' => 'integer'],
                ]],
                'annotations' => $write,
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
                    'source_barcode' => ['type' => 'string'],
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
            [
                'name' => 'create_purchase_order_draft',
                'description' => 'Create the existing CRM Purchase Order draft from a purchase capture. Unmapped inventory items are allowed and retain the supplier description for later receiving-time mapping.',
                'inputSchema' => ['type' => 'object', 'required' => ['capture_id'], 'properties' => [
                    'capture_id' => ['type' => 'integer'],
                    'is_paid' => ['type' => 'boolean'],
                ]],
                'annotations' => $write,
            ],
            [
                'name' => 'find_accounts',
                'description' => 'Find active chart-of-account accounts, optionally under a parent. Read-only.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string'], 'parent_account_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'propose_account',
                'description' => 'Generate a new account name from the parent and sibling naming syntax. Does not create the account.',
                'inputSchema' => ['type' => 'object', 'required' => ['parent_account_id', 'leaf_name'], 'properties' => [
                    'parent_account_id' => ['type' => 'integer'], 'leaf_name' => ['type' => 'string'], 'segment' => ['type' => 'string'],
                ]],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'approve_account_proposal',
                'description' => 'Explicitly approve and create a previously proposed account.',
                'inputSchema' => ['type' => 'object', 'required' => ['proposal_id'], 'properties' => ['proposal_id' => ['type' => 'integer']]],
                'annotations' => $write,
            ],
            [
                'name' => 'find_categories',
                'description' => 'Find expense or bill categories. Read-only.',
                'inputSchema' => ['type' => 'object', 'required' => ['category_type'], 'properties' => [
                    'category_type' => ['type' => 'string', 'enum' => ['expense', 'bill']], 'query' => ['type' => 'string'], 'limit' => ['type' => 'integer'],
                ]],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'propose_category',
                'description' => 'Propose an expense or bill category. Bill categories require validated debit and credit accounts. Does not create anything.',
                'inputSchema' => ['type' => 'object', 'required' => ['category_type', 'name'], 'properties' => [
                    'category_type' => ['type' => 'string', 'enum' => ['expense', 'bill']], 'name' => ['type' => 'string'],
                    'debit_account' => ['type' => 'integer'], 'credit_account' => ['type' => 'integer'], 'description' => ['type' => 'string'],
                ]],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'approve_category_proposal',
                'description' => 'Explicitly approve and create a previously proposed category.',
                'inputSchema' => ['type' => 'object', 'required' => ['proposal_id'], 'properties' => ['proposal_id' => ['type' => 'integer']]],
                'annotations' => $write,
            ],
        ];
    }

    private function _call_tool(string $name, array $args)
    {
        try {
            switch ($name) {
                case 'key_in_receipt':
                    return $this->_text($this->purchase_agent_model->key_in_receipt(
                        (int) ($args['capture_id'] ?? 0), !empty($args['is_paid']), isset($args['payment_mode_id']) ? (int) $args['payment_mode_id'] : null
                    ));

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

                case 'update_purchase_line':
                    $lineId = (int) ($args['line_id'] ?? 0);
                    unset($args['line_id']);
                    if (!$this->purchase_agent_model->update_line($lineId, $args)) {
                        return $this->_text(['error' => 'Purchase line could not be updated'], true);
                    }
                    return $this->_text(['line_id' => $lineId, 'status' => 'updated']);

                case 'get_purchase_capture':
                    $capture = $this->purchase_agent_model->get_capture((int) ($args['capture_id'] ?? 0));
                    return $this->_text($capture ?: ['error' => 'Capture not found'], !$capture);

                case 'update_purchase_capture':
                    $captureId = (int) ($args['capture_id'] ?? 0);
                    if (!$this->purchase_agent_model->get_capture($captureId)) {
                        return $this->_text(['error' => 'Capture not found'], true);
                    }
                    unset($args['capture_id']);
                    $this->purchase_agent_model->update_capture($captureId, $args);
                    return $this->_text(['capture_id' => $captureId, 'status' => 'updated']);

                case 'discard_purchase_capture':
                    $captureId = (int) ($args['capture_id'] ?? 0);
                    if (!$this->purchase_agent_model->get_capture($captureId)) {
                        return $this->_text(['error' => 'Capture not found'], true);
                    }
                    $this->purchase_agent_model->discard_capture($captureId, (string) ($args['reason'] ?? 'duplicate'));
                    return $this->_text(['capture_id' => $captureId, 'status' => 'discarded']);

                case 'find_inventory_candidates':
                    return $this->_text($this->purchase_agent_model->find_inventory_candidates(
                        (string) ($args['description'] ?? ''),
                        isset($args['vendor_id']) ? (int) $args['vendor_id'] : null,
                        (int) ($args['limit'] ?? 8),
                        $args['barcode'] ?? null
                    ));

                case 'find_vendors':
                    return $this->_text($this->purchase_agent_model->find_vendors(
                        (string) ($args['query'] ?? ''),
                        (int) ($args['limit'] ?? 10)
                    ));

                case 'find_expense_categories':
                    return $this->_text($this->purchase_agent_model->find_expense_categories(
                        (string) ($args['query'] ?? ''), (int) ($args['limit'] ?? 20)
                    ));

                case 'find_bill_categories':
                    return $this->_text($this->purchase_agent_model->find_bill_categories(
                        (string) ($args['query'] ?? ''), (int) ($args['limit'] ?? 20)
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

                case 'create_purchase_order_draft':
                    $captureId = (int) ($args['capture_id'] ?? 0);
                    $draftId = $this->purchase_agent_model->create_purchase_order_draft_from_capture(
                        $captureId,
                        !empty($args['is_paid'])
                    );
                    return $this->_text(
                        $draftId
                            ? ['capture_id' => $captureId, 'purchase_order_draft_id' => $draftId, 'status' => 'po_draft']
                            : ['error' => 'Could not create purchase order draft'],
                        !$draftId
                    );

                case 'create_split_receipt_drafts':
                    $captureId = (int) ($args['capture_id'] ?? 0);
                    $result = $this->purchase_agent_model->create_split_drafts_from_capture(
                        $captureId, !empty($args['is_paid']), isset($args['payment_mode_id']) ? (int) $args['payment_mode_id'] : null
                    );
                    return $this->_text($result ?: ['error' => 'Could not create split receipt drafts'], !$result);

                case 'find_accounts':
                    return $this->_text($this->admin_agent_model->find_accounts(
                        (string) ($args['query'] ?? ''), isset($args['parent_account_id']) ? (int) $args['parent_account_id'] : null, (int) ($args['limit'] ?? 30)
                    ));

                case 'propose_account':
                    return $this->_text($this->admin_agent_model->propose_account(
                        (int) ($args['parent_account_id'] ?? 0), (string) ($args['leaf_name'] ?? ''), isset($args['segment']) ? (string) $args['segment'] : null
                    ));

                case 'approve_account_proposal':
                    return $this->_text($this->admin_agent_model->approve_account_proposal((int) ($args['proposal_id'] ?? 0)));

                case 'find_categories':
                    return $this->_text($this->admin_agent_model->find_categories(
                        (string) ($args['category_type'] ?? 'expense'), (string) ($args['query'] ?? ''), (int) ($args['limit'] ?? 30)
                    ));

                case 'propose_category':
                    return $this->_text($this->admin_agent_model->propose_category(
                        (string) ($args['category_type'] ?? ''), (string) ($args['name'] ?? ''),
                        isset($args['debit_account']) ? (int) $args['debit_account'] : null,
                        isset($args['credit_account']) ? (int) $args['credit_account'] : null,
                        (string) ($args['description'] ?? '')
                    ));

                case 'approve_category_proposal':
                    return $this->_text($this->admin_agent_model->approve_category_proposal((int) ($args['proposal_id'] ?? 0)));

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
