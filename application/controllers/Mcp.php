<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * MCP (Model Context Protocol) server — lets Claude (claude.ai custom
 * connector) or any other MCP client read the CRM's business data.
 *
 * Endpoint: POST {base_url}mcp/{mcp_access_token}
 *   Streamable HTTP transport, JSON responses only (no SSE stream).
 *   The secret token in the URL is the auth — set/regenerate it under
 *   Setup > Settings > AI. Empty token = connector disabled.
 *
 * Tools:
 *   - get_data_guide / list_tables / describe_table / run_query: read-only
 *     SQL over (almost) the whole database — sales, quotations, invoices,
 *     inventory, purchasing, accounting, HR…
 *   - the curated POS/loyalty/costing tools from Pos_ai_tools, which carry
 *     business logic (recipe costing, modifier ranges) raw SQL would miss.
 *
 * Safety: queries run inside START TRANSACTION READ ONLY with a time limit,
 * only SELECT/WITH/EXPLAIN are accepted, credential-bearing tables are
 * blocked and password/token/secret columns are rejected or redacted.
 */
class Mcp extends App_Controller
{
    const SERVER_VERSION   = '1.0.0';
    const DEFAULT_ROWS     = 200;
    const MAX_ROWS         = 1000;
    const MAX_RESULT_BYTES = 200000;
    const MAX_CELL_CHARS   = 1000;
    const QUERY_TIMEOUT_S  = 20;

    // Tables (without prefix) never readable: credentials, sessions, API keys.
    const BLOCKED_TABLES = [
        'options', 'sessions', 'user_auto_login', 'vault', 'user_api',
        'pos_api_tokens', 'pos_manager_sessions', 'pos_manager_fcm_tokens',
        'pos_loyalty_member_sessions', 'wa_pending_sessions',
        'leads_email_integration', 'pos_chip_settings', 'pos_grabfood_settings',
        'acc_plaid_transaction_logs', 'twocheckout_log',
    ];

    // Column names that hold secrets — rejected in SQL text, redacted in results.
    const SENSITIVE_PATTERN = '/\b\w*(password|passwd|pass_key|passkey|secret|api_key|apikey|token|pin_hash)\w*\b|\botp\w*\b/i';

    public function index($token = '')
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        $expected = (string) get_option('mcp_access_token');
        if (strlen($expected) < 32 || !hash_equals($expected, (string) $token)) {
            $this->_send(404, ['error' => 'Not found']);
        }

        $method = $this->input->server('REQUEST_METHOD');
        if ($method !== 'POST') {
            // No server-initiated SSE stream / sessions to delete.
            header('Allow: POST');
            $this->_send(405, ['error' => 'Method not allowed']);
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $this->_send(400, $this->_rpc_error(null, -32700, 'Parse error'));
        }

        $is_batch = array_keys($body) === range(0, count($body) - 1);
        $messages = $is_batch ? $body : [$body];

        $responses = [];
        foreach ($messages as $msg) {
            $res = $this->_handle_message($msg);
            if ($res !== null) {
                $responses[] = $res;
            }
        }

        if (empty($responses)) {
            // Only notifications / client responses — nothing to answer.
            http_response_code(202);
            exit;
        }

        $this->_send(200, $is_batch ? $responses : $responses[0]);
    }

    // =========================================================================
    // JSON-RPC
    // =========================================================================

    private function _handle_message($msg)
    {
        if (!is_array($msg) || !isset($msg['method'])) {
            // A response to a server request (we never send any), or junk.
            return isset($msg['id']) ? $this->_rpc_error($msg['id'], -32600, 'Invalid request') : null;
        }

        $has_id = array_key_exists('id', $msg);
        $id     = $has_id ? $msg['id'] : null;
        $params = isset($msg['params']) && is_array($msg['params']) ? $msg['params'] : [];

        if (strpos($msg['method'], 'notifications/') === 0 || !$has_id) {
            return null;
        }

        switch ($msg['method']) {
            case 'initialize':
                return $this->_rpc_result($id, [
                    'protocolVersion' => $params['protocolVersion'] ?? '2025-06-18',
                    'capabilities'    => ['tools' => ['listChanged' => false]],
                    'serverInfo'      => [
                        'name'    => 'kokonuts-crm',
                        'title'   => 'Kokonuts CRM',
                        'version' => self::SERVER_VERSION,
                    ],
                    'instructions' => $this->_instructions(),
                ]);

            case 'ping':
                return $this->_rpc_result($id, new stdClass());

            case 'tools/list':
                return $this->_rpc_result($id, ['tools' => $this->_tool_list()]);

            case 'tools/call':
                return $this->_rpc_result($id, $this->_call_tool($params['name'] ?? '', $params['arguments'] ?? []));

            case 'resources/list':
                return $this->_rpc_result($id, ['resources' => []]);

            case 'resources/templates/list':
                return $this->_rpc_result($id, ['resourceTemplates' => []]);

            case 'prompts/list':
                return $this->_rpc_result($id, ['prompts' => []]);

            default:
                return $this->_rpc_error($id, -32601, 'Method not found: ' . $msg['method']);
        }
    }

    private function _rpc_result($id, $result)
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function _rpc_error($id, $code, $message)
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    private function _send($status, $payload)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // =========================================================================
    // Tools
    // =========================================================================

    private function _tool_list()
    {
        $read_only = ['readOnlyHint' => true, 'openWorldHint' => false];

        $tools = [
            [
                'name'        => 'get_data_guide',
                'description' => 'Map of the business database: which tables hold POS sales, quotations, invoices, inventory, purchasing, costing, loyalty, accounting and HR data, how they join, and what status codes mean. Call this first before writing SQL.',
                'inputSchema' => ['type' => 'object', 'properties' => new stdClass()],
            ],
            [
                'name'        => 'list_tables',
                'description' => 'Lists database tables with approximate row counts. Optionally filter by a substring of the table name (e.g. "pos_", "pur_", "estimate").',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Substring to filter table names by'],
                ]],
            ],
            [
                'name'        => 'describe_table',
                'description' => 'Shows a table\'s columns (name, type, key, comment) and a few sample rows. Use before querying a table you haven\'t inspected yet.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'table' => ['type' => 'string', 'description' => 'Full table name including prefix, e.g. ' . db_prefix() . 'pos_receipts'],
                ], 'required' => ['table']],
            ],
            [
                'name'        => 'run_query',
                'description' => 'Runs one read-only MySQL SELECT (or WITH … SELECT / EXPLAIN) and returns columns + rows. Aggregate in SQL (SUM/COUNT/GROUP BY) rather than pulling raw rows. Results are capped at max_rows and about 200 KB.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'sql'      => ['type' => 'string', 'description' => 'A single SELECT statement, no trailing semicolon needed'],
                    'max_rows' => ['type' => 'integer', 'description' => 'Row cap (default ' . self::DEFAULT_ROWS . ', max ' . self::MAX_ROWS . ')'],
                ], 'required' => ['sql']],
            ],
        ];

        $this->load->library('pos/pos_ai_tools');
        foreach ($this->pos_ai_tools->definitions() as $def) {
            $schema = $def['parameters'];
            if (empty($schema['properties'])) {
                $schema['properties'] = new stdClass();
            }
            if (isset($schema['required']) && empty($schema['required'])) {
                unset($schema['required']);
            }
            $tools[] = [
                'name'        => $def['name'],
                'description' => $def['description'],
                'inputSchema' => $schema,
            ];
        }

        foreach ($tools as &$tool) {
            $tool['annotations'] = $read_only;
        }
        unset($tool);

        return $tools;
    }

    private function _call_tool($name, $args)
    {
        $args = is_array($args) ? $args : [];

        try {
            switch ($name) {
                case 'get_data_guide':
                    return $this->_tool_text($this->_data_guide());
                case 'list_tables':
                    $result = $this->_list_tables($args['search'] ?? '');
                    break;
                case 'describe_table':
                    $result = $this->_describe_table($args['table'] ?? '');
                    break;
                case 'run_query':
                    $result = $this->_run_query($args['sql'] ?? '', $args['max_rows'] ?? self::DEFAULT_ROWS);
                    break;
                default:
                    $this->load->library('pos/pos_ai_tools');
                    if (!in_array($name, $this->pos_ai_tools->names(), true)) {
                        return $this->_tool_text('Unknown tool: ' . $name, true);
                    }
                    $result = $this->pos_ai_tools->execute($name, $args);
            }
        } catch (Throwable $e) {
            return $this->_tool_text('Tool failed: ' . $e->getMessage(), true);
        }

        $is_error = is_array($result) && isset($result['error']) && count($result) === 1;

        return $this->_tool_text(
            json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            $is_error
        );
    }

    private function _tool_text($text, $is_error = false)
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $is_error];
    }

    // =========================================================================
    // SQL tools
    // =========================================================================

    private function _list_tables($search)
    {
        $rows = $this->db->query(
            'SELECT table_name AS name, table_rows AS approx_rows, table_comment AS comment
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = "BASE TABLE"
             ORDER BY table_name'
        )->result_array();

        $search = strtolower(trim((string) $search));
        $out    = [];
        foreach ($rows as $r) {
            if ($this->_is_blocked_table($r['name'])) {
                continue;
            }
            if ($search !== '' && strpos(strtolower($r['name']), $search) === false) {
                continue;
            }
            $entry = ['table' => $r['name'], 'rows' => (int) $r['approx_rows']];
            if ($r['comment'] !== '') {
                $entry['comment'] = $r['comment'];
            }
            $out[] = $entry;
        }

        return ['count' => count($out), 'tables' => $out];
    }

    private function _describe_table($table)
    {
        $table = trim((string) $table, " `\t\n");
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return ['error' => 'Invalid table name'];
        }
        if ($this->_is_blocked_table($table)) {
            return ['error' => 'That table holds credentials/sessions and is not available.'];
        }
        if (!$this->db->table_exists($table)) {
            return ['error' => 'No such table: ' . $table . ' (tables are prefixed with "' . db_prefix() . '")'];
        }

        $cols = $this->db->query(
            'SELECT column_name AS name, column_type AS type, is_nullable AS nullable, column_key AS `key`, column_comment AS comment
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
             ORDER BY ordinal_position',
            [$table]
        )->result_array();

        $columns = array_map(function ($c) {
            $col = ['name' => $c['name'], 'type' => $c['type']];
            if ($c['key'] !== '') {
                $col['key'] = $c['key'];
            }
            if ($c['comment'] !== '') {
                $col['comment'] = $c['comment'];
            }
            if (preg_match(self::SENSITIVE_PATTERN, $c['name'])) {
                $col['note'] = 'sensitive — not queryable';
            }
            return $col;
        }, $cols);

        $sample = $this->_run_query('SELECT * FROM `' . $table . '` ORDER BY 1 DESC LIMIT 3', 3, true);

        return ['table' => $table, 'columns' => $columns, 'sample_rows' => $sample['rows'] ?? []];
    }

    /**
     * @param bool $trusted Internal call with a query we built ourselves —
     *                      skips the text checks (still read-only + redacted).
     */
    private function _run_query($sql, $max_rows, $trusted = false)
    {
        $sql      = rtrim(trim((string) $sql), "; \t\n\r");
        $max_rows = min(self::MAX_ROWS, max(1, (int) $max_rows));

        if (!$trusted && ($problem = $this->_validate_sql($sql)) !== null) {
            return ['error' => $problem];
        }

        $conn = $this->db->conn_id;

        // Time limit: MySQL (ms) and MariaDB (s) use different variables —
        // whichever the server doesn't know just fails.
        $this->_try_query($conn, 'SET SESSION max_execution_time = ' . (self::QUERY_TIMEOUT_S * 1000));
        $this->_try_query($conn, 'SET SESSION max_statement_time = ' . self::QUERY_TIMEOUT_S);

        if (!$this->_try_query($conn, 'START TRANSACTION READ ONLY')) {
            return ['error' => 'Could not open a read-only transaction; query not run.'];
        }

        try {
            $res = mysqli_query($conn, $sql, MYSQLI_USE_RESULT);
            if ($res === false) {
                $result = ['error' => 'SQL error: ' . mysqli_error($conn)];
            } elseif ($res === true) {
                $result = ['error' => 'Statement returned no result set.'];
            } else {
                $result = $this->_collect_rows($res, $max_rows);
            }
        } catch (Throwable $e) {
            $result = ['error' => 'SQL error: ' . $e->getMessage()];
        }

        $this->_try_query($conn, 'ROLLBACK');
        $this->_try_query($conn, 'SET SESSION max_execution_time = 0');
        $this->_try_query($conn, 'SET SESSION max_statement_time = 0');

        return $result;
    }

    private function _collect_rows($res, $max_rows)
    {
        $columns  = array_map(function ($f) { return $f->name; }, $res->fetch_fields());
        $redacted = [];
        foreach ($columns as $i => $name) {
            if (preg_match(self::SENSITIVE_PATTERN, $name)) {
                $redacted[$i] = true;
            }
        }

        $rows      = [];
        $bytes     = 0;
        $truncated = false;
        while ($row = $res->fetch_row()) {
            if (count($rows) >= $max_rows || $bytes >= self::MAX_RESULT_BYTES) {
                $truncated = true;
                break;
            }
            foreach ($row as $i => $val) {
                if (isset($redacted[$i]) && $val !== null) {
                    $row[$i] = '[redacted]';
                } elseif (is_string($val) && mb_strlen($val) > self::MAX_CELL_CHARS) {
                    $row[$i] = mb_substr($val, 0, self::MAX_CELL_CHARS) . '…[truncated]';
                }
            }
            $bytes += strlen(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            $rows[] = $row;
        }
        // MYSQLI_USE_RESULT: free() drains whatever rows are still pending.
        $res->free();

        $out = ['columns' => $columns, 'row_count' => count($rows), 'rows' => $rows];
        if ($truncated) {
            $out['truncated'] = true;
            $out['note']      = 'More rows exist — aggregate with GROUP BY or add a LIMIT/filters.';
        }

        return $out;
    }

    /** Runs a statement directly, swallowing errors (PHP 8.1+ mysqli throws). */
    private function _try_query($conn, $sql)
    {
        try {
            return mysqli_query($conn, $sql) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return string|null problem description, or null if acceptable */
    private function _validate_sql($sql)
    {
        if ($sql === '') {
            return 'Empty query.';
        }
        if (strpos($sql, ';') !== false) {
            return 'Only one statement per call — remove semicolons.';
        }
        if (strpos($sql, '/*') !== false) {
            return 'Block comments (/* */) are not allowed.';
        }

        // First keyword, ignoring leading "-- " / "#" comment lines.
        $head = preg_replace('/^(\s*(--[^\n]*|#[^\n]*)\n)+/', '', $sql);
        if (!preg_match('/^\s*\(?\s*(select|with|explain)\b/i', $head)) {
            return 'Only SELECT, WITH … SELECT and EXPLAIN queries are allowed (read-only access).';
        }

        $forbidden = [
            '/\binto\s+(outfile|dumpfile)\b/i'             => 'INTO OUTFILE/DUMPFILE',
            '/\b(load_file|sleep|benchmark|get_lock)\s*\(/i' => 'that function',
            '/\bfor\s+update\b|\block\s+in\s+share\s+mode\b|\bfor\s+share\b/i' => 'row locking',
            '/\b(mysql|performance_schema|sys)\s*\.\s*`?\w/i' => 'system schemas',
        ];
        foreach ($forbidden as $pattern => $what) {
            if (preg_match($pattern, $sql)) {
                return 'Not allowed: ' . $what . '.';
            }
        }

        if (preg_match(self::SENSITIVE_PATTERN, $sql, $m)) {
            return 'The query mentions "' . $m[0] . '", which looks like a password/token/secret field — those are not available. Name the columns you need instead of referencing it.';
        }

        foreach (self::BLOCKED_TABLES as $t) {
            if (preg_match('/\b' . preg_quote(db_prefix() . $t, '/') . '\b/i', $sql)) {
                return 'Table ' . db_prefix() . $t . ' holds credentials/settings and is not available.';
            }
        }

        return null;
    }

    private function _is_blocked_table($table)
    {
        $bare = strtolower($table);
        if (strpos($bare, db_prefix()) === 0) {
            $bare = substr($bare, strlen(db_prefix()));
        }

        return in_array($bare, self::BLOCKED_TABLES, true)
            || preg_match('/(session|token|auto_?login|vault)/', $bare);
    }

    // =========================================================================
    // Context for the model
    // =========================================================================

    private function _instructions()
    {
        return 'Live business data for Kokonuts, a Malaysian F&B brand (currency RM, timezone ' . date_default_timezone_get() . ', today ' . date('Y-m-d') . '). '
             . 'For POS sales, product cost/profit, loyalty and promotion questions prefer the dedicated get_* tools — they include recipe/modifier costing logic. '
             . 'For anything else (quotations, invoices, inventory, purchasing, expenses, accounting, HR, or custom cuts of sales) call get_data_guide, then describe_table, then run_query. '
             . 'All access is read-only. Factor in the Malaysian calendar (Ramadan, Hari Raya, CNY, Deepavali, school holidays) when analysing trends.';
    }

    private function _data_guide()
    {
        $p = db_prefix();

        return <<<GUIDE
# Kokonuts CRM data guide
All tables are prefixed "{$p}". Currency RM. Column names can drift between versions — describe_table before relying on a column.

## Outlets
- {$p}warehouse — outlets/stores and storage locations (warehouse_id, warehouse_code, warehouse_name). "warehouse_id" everywhere = outlet.

## POS sales (the café/retail tills)
- {$p}pos_receipts — one row per ticket. receipt_type 'SALE' or 'REFUND'. Valid = cancelled_at IS NULL.
  subtotal = gross, total_discount, total_tax, tip, surcharge, total_money = net sales. receipt_date, warehouse_id, employee_id, customer_id (→ {$p}clients.userid), loyalty_customer_id, dining_option, source (POS, GrabFood…), shift_id.
- {$p}pos_receipt_line_items — receipt_id → pos_receipts.id; item_id → {$p}items.id; item_name, quantity, unit_price, gross_total, total_discount, total_money, modifier_names, modifiers_price.
- {$p}pos_receipt_payments — receipt_id; payment_name/type, money_amount.
- {$p}pos_refunds, {$p}pos_shifts (+ pos_shift_cash_movements), {$p}pos_grabfood_orders (+ _items).
- Net sales for a period: SUM(total_money) FROM pos_receipts WHERE receipt_type='SALE' AND cancelled_at IS NULL AND receipt_date BETWEEN … .
- Profit/cost: prefer get_sales_summary / get_product_cost_profit / get_product_cost_detail — costing walks BOMs, mixed-ingredient yields and modifiers.

## Products, recipes & costing
- {$p}items — every product, ingredient and packaging item (sku_code, sku_name, rate = selling price, purchase_price, group_id → {$p}items_groups).
- {$p}pos_product_bom (product recipes), {$p}pos_mixed_ingredients + pos_mixed_ingredient_components (prep batches), {$p}pos_modifier_bom, {$p}pos_item_yields, {$p}pos_combo_components, {$p}pos_cost_snapshot_values.
- Modifiers: {$p}modifier_groups, {$p}modifiers, {$p}item_modifier_groups / item_modifier_options.
- Production: {$p}pos_production_runs (+ _deductions).

## Customers, loyalty & marketing
- {$p}clients (userid, company) + {$p}contacts. {$p}customer_groups / customers_groups.
- {$p}pos_loyalty_customers (client_id → clients.userid, name, phone, email, total_points, total_spent, registered_at, last_visit), {$p}pos_loyalty_transactions (points earn/redeem),
  {$p}pos_loyalty_promotions, {$p}pos_loyalty_vouchers (+ voucher_instances, voucher_redemptions), {$p}pos_crm_promos, loyalty blasts/notifications.

## Quotations, proposals & invoices (B2B / catering / franchise)
- {$p}estimates = quotations. status: 1 Draft, 2 Sent, 3 Declined, 4 Accepted, 5 Expired. clientid, number, prefix, date, expirydate, subtotal, total, invoiceid (set once converted).
- {$p}proposals — status: 1 Open, 2 Declined, 3 Accepted, 4 Sent, 5 Revised, 6 Draft.
- {$p}invoices — status: 1 Unpaid, 2 Paid, 3 Partially paid, 4 Overdue, 5 Cancelled, 6 Draft. clientid, number, date, duedate, total.
- Line items for all three: {$p}itemable WHERE rel_type IN ('estimate','invoice','proposal') AND rel_id = parent id (description, long_description, qty, rate, unit).
- Payments: {$p}invoicepaymentrecords (invoiceid, amount, date, paymentmode). Credit notes: {$p}creditnotes.
- Franchise: {$p}franchise_franchisees, {$p}franchise_sale_orders, {$p}franchise_transfers.

## Inventory (warehouse module)
- {$p}inventory_manage — stock on hand: warehouse_id, commodity_id → items.id, inventory_number = quantity (may have several rows per item for lots/expiry; SUM them).
- Stock in: {$p}goods_receipt (+ goods_receipt_detail). Stock out: {$p}goods_delivery (+ goods_delivery_detail).
- Transfers: {$p}internal_delivery_note (+ _detail). Adjustments/wastage: {$p}wh_loss_adjustment (+ _detail).
- Movement ledger: {$p}goods_transaction_detail. Reorder levels: {$p}inventory_commodity_min.
- POS auto-deductions: {$p}pos_receipt_inventory_deductions.

## Purchasing
- {$p}pur_vendor (suppliers), {$p}pur_orders (+ pur_order_detail), {$p}pur_request (+ _detail), {$p}pur_estimates (supplier quotes), {$p}pur_invoices (+ pur_invoice_details, pur_invoice_payment), {$p}pur_debit_notes.

## Expenses & accounting
- {$p}expenses (category → {$p}expenses_categories, amount, date).
- {$p}acc_accounts (chart of accounts) and {$p}acc_account_history (ledger lines: account, debit, credit, date, rel_type) — P&L/balance sheet come from here.
- {$p}acc_journal_entries, {$p}acc_budgets (+ details), {$p}acc_transfers.

## People
- {$p}staff (staffid, firstname, lastname, role), {$p}departments, HR ({$p}hr_*), payroll ({$p}hrp_payslips + hrp_payslip_details), timesheets ({$p}timesheets_*), {$p}pos_employees.

## Rules
- Read-only. One SELECT per run_query. Aggregate in SQL; don't pull thousands of raw rows.
- Password/token/secret columns and credential tables are blocked.
GUIDE;
    }
}
