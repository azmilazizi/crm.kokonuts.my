<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Persistence boundary for the purchase-agent workflow.
 *
 * This model deliberately stores preparation, matching, approval and audit
 * data separately from the official purchasing/warehouse ledgers. Posting to
 * those ledgers belongs in a later, validated service method.
 */
class Purchase_agent_model extends App_Model
{
    private $captures   = 'purchase_agent_captures';
    private $lines      = 'purchase_agent_lines';
    private $aliases    = 'purchase_agent_vendor_aliases';
    private $corrections = 'purchase_agent_corrections';
    private $runs       = 'purchase_agent_workflow_runs';
    private $approvals  = 'purchase_agent_approvals';
    private $actions    = 'purchase_agent_actions';

    public function create_capture(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $receiptBase64 = $data['receipt_image_base64'] ?? null;
        unset($data['receipt_image_base64']);
        if (is_string($receiptBase64) && $receiptBase64 !== '') {
            $receiptBlob = base64_decode($receiptBase64, true);
            if ($receiptBlob !== false) {
                $data['receipt_blob'] = $receiptBlob;
                $data['receipt_file_name'] = $data['receipt_file_name'] ?? 'receipt';
                $data['receipt_mime_type'] = $data['receipt_mime_type'] ?? 'application/octet-stream';
            }
        }
        $data = array_merge([
            'source'     => 'claude',
            'status'     => 'captured',
            'created_at' => $now,
            'updated_at' => $now,
        ], $data);

        if (isset($data['raw_extraction']) && (is_array($data['raw_extraction']) || is_object($data['raw_extraction']))) {
            $data['raw_extraction'] = json_encode($data['raw_extraction'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->db->insert(db_prefix() . $this->captures, $data);
        return (int) $this->db->insert_id();
    }

    public function get_capture(int $captureId): ?array
    {
        $capture = $this->db
            ->where('id', $captureId)
            ->get(db_prefix() . $this->captures)
            ->row_array();

        if (!$capture) {
            return null;
        }

        $capture['lines'] = $this->db
            ->where('capture_id', $captureId)
            ->order_by('source_line_number', 'ASC')
            ->order_by('id', 'ASC')
            ->get(db_prefix() . $this->lines)
            ->result_array();

        // Keep binary receipt data out of normal MCP responses. It is fetched
        // only when a draft is being created and the receipt is copied to it.
        unset($capture['receipt_blob']);

        return $capture;
    }

    private function get_capture_receipt_blob(int $captureId): ?array
    {
        $row = $this->db->select('receipt_blob, receipt_file_name, receipt_mime_type')
            ->where('id', $captureId)
            ->get(db_prefix() . $this->captures)
            ->row_array();

        return $row && !empty($row['receipt_blob']) ? $row : null;
    }

    public function update_capture(int $captureId, array $data): bool
    {
        $allowed = [
            'source', 'source_reference', 'status', 'vendor_id', 'vendor_name',
            'warehouse_id', 'invoice_number', 'purchase_date', 'currency',
            'subtotal', 'tax', 'discount', 'shipping', 'grand_total',
            'payment_total', 'payment_method', 'payment_date', 'rounding_amount',
            'receipt_hash', 'raw_extraction', 'notes', 'purchase_order_draft_id',
            'purchase_order_id', 'goods_receipt_id', 'expense_draft_ids',
        ];
        $data = array_intersect_key($data, array_flip($allowed));
        if (array_key_exists('raw_extraction', $data)
            && (is_array($data['raw_extraction']) || is_object($data['raw_extraction']))) {
            $data['raw_extraction'] = json_encode($data['raw_extraction'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $captureId)->update(db_prefix() . $this->captures, $data);
        return $this->db->affected_rows() >= 0;
    }

    /**
     * Build the existing purchase-module draft from a capture. Inventory item
     * IDs remain nullable: a PO can preserve supplier wording and be mapped
     * later when the goods-receiving voucher is prepared.
     */
    public function create_purchase_order_draft_from_capture(int $captureId, bool $isPaid = false): ?string
    {
        $capture = $this->get_capture($captureId);
        if (!$capture || empty($capture['lines'])) {
            return null;
        }

        $CI = &get_instance();
        $CI->load->model('purchase/purchase_order_drafts_model');
        $draftId = app_generate_hash();
        $now = date('Y-m-d H:i:s');
        $items = [];
        $orderSummaryParts = [];
        $subtotal = 0.0;

        foreach ($capture['lines'] as $line) {
            if (in_array(($line['destination'] ?? 'po'), ['expense', 'personal', 'excluded'], true)) {
                continue;
            }
            $quantity = (float) ($line['quantity'] ?? 0);
            $lineTotal = $line['line_total'] !== null
                ? (float) $line['line_total']
                : round($quantity * (float) ($line['unit_price'] ?? 0), 2);
            $subtotal += $lineTotal;
            $summaryName = (string) $line['source_description'];
            if (!empty($line['inventory_item_id']) && $this->db->table_exists(db_prefix() . 'items')) {
                $inventoryItem = $this->db->select('description')
                    ->where('id', (int) $line['inventory_item_id'])
                    ->get(db_prefix() . 'items')->row();
                if ($inventoryItem && trim((string) $inventoryItem->description) !== '') {
                    $summaryName = trim((string) $inventoryItem->description);
                }
            }
            $formattedQuantity = rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
            $orderSummaryParts[] = $summaryName . ' x ' . $formattedQuantity;
            $items[] = [
                'id'                 => app_generate_hash(),
                'line_item_id'       => (string) $line['id'],
                'inventory_item_id'  => !empty($line['inventory_item_id']) ? (string) $line['inventory_item_id'] : null,
                'inventory_item_name'=> $line['inventory_item_id'] ? null : $line['source_description'],
                'description'        => $line['source_description'],
                'quantity'           => $quantity,
                // The official PO conversion requires this field. For an
                // unmapped receipt line, preserve the supplier quantity as a
                // provisional one-to-one purchase unit; receiving-time item
                // mapping can replace the conversion once the pack size is
                // confirmed.
                'units_per_batch'   => !empty($line['conversion_factor']) ? (float) $line['conversion_factor'] : 1,
                'subtotal'           => $lineTotal,
                'discount'           => (float) ($line['discount'] ?? 0),
            ];
        }

        if (!$items) {
            return null;
        }

        $draftData = [
            'id'             => $draftId,
            'vendor_id'      => $capture['vendor_id'] ?: null,
            'warehouse_id'   => $capture['warehouse_id'] ?: null,
            'vendor_name'    => $capture['vendor_name'],
            'order_name'     => implode(', ', $orderSummaryParts),
            'order_date'     => $capture['purchase_date'],
            'is_paid'        => $isPaid ? 1 : 0,
            'items_subtotal' => $subtotal,
            'grand_total'    => $capture['grand_total'] !== null ? (float) $capture['grand_total'] : $subtotal,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $attachments = [];
        $receipt = $this->get_capture_receipt_blob($captureId);
        if ($receipt) {
            $attachments[] = [
                'file_name' => $receipt['receipt_file_name'] ?: 'receipt',
                'local_blob' => $receipt['receipt_blob'],
                'uploaded_by' => get_staff_user_id(),
            ];
        }

        if (!$this->purchase_order_drafts_model->create_draft($draftData, $items, [], $attachments)) {
            return null;
        }

        $this->update_capture($captureId, [
            'status' => 'po_draft',
            'purchase_order_draft_id' => $draftId,
        ]);
        return $draftId;
    }

    public function discard_capture(int $captureId, string $reason = 'duplicate'): bool
    {
        return $this->update_capture($captureId, [
            'status' => 'discarded',
            'notes'  => 'Discarded by purchase agent: ' . $reason,
        ]);
    }

    public function find_expense_categories(string $query = '', int $limit = 20): array
    {
        if (!$this->db->table_exists(db_prefix() . 'expenses_categories')) {
            return [];
        }
        if ($query !== '') {
            $this->db->like('name', $query);
        }
        return $this->db->select('id, name, description')
            ->limit(max(1, min(50, $limit)))
            ->get(db_prefix() . 'expenses_categories')->result_array();
    }

    public function find_bill_categories(string $query = '', int $limit = 20): array
    {
        if (!$this->db->table_exists(db_prefix() . 'acc_bill_categories')) return [];
        if ($query !== '') $this->db->like('name', $query);
        return $this->db->select('id, name, debit_account, credit_account, description')
            ->where('active', 1)->limit(max(1, min(50, $limit)))
            ->get(db_prefix() . 'acc_bill_categories')->result_array();
    }

    /**
     * High-level receipt workflow used by Claude. It performs validation and
     * matching before creating any official purchase/expense drafts.
     */
    public function key_in_receipt(int $captureId, bool $isPaid = false, ?int $paymentModeId = null): array
    {
        $capture = $this->get_capture($captureId);
        if (!$capture) return ['status' => 'error', 'error' => 'Capture not found'];
        $existingExpenseDraftIds = json_decode($capture['expense_draft_ids'] ?: '[]', true);
        $hasExpenseLines = false;
        foreach ($capture['lines'] as $captureLine) {
            if (in_array($captureLine['destination'] ?? 'po', ['expense', 'personal'], true)) {
                $hasExpenseLines = true;
                break;
            }
        }
        if ((!empty($capture['purchase_order_draft_id']) && !$hasExpenseLines)
            || !empty($existingExpenseDraftIds)) {
            return ['status' => 'already_prepared', 'capture_id' => $captureId, 'purchase_order_draft_id' => $capture['purchase_order_draft_id'], 'expense_draft_ids' => $existingExpenseDraftIds];
        }

        $questions = [];
        if (empty($capture['vendor_id']) && !empty($capture['vendor_name'])) {
            $vendors = $this->find_vendors($capture['vendor_name'], 10);
            $normalizedVendor = $this->normalize_description($capture['vendor_name']);
            $exact = array_values(array_filter($vendors, function ($vendor) use ($normalizedVendor) {
                return $this->normalize_description((string) $vendor['company']) === $normalizedVendor
                    || strpos($this->normalize_description((string) $vendor['company']), $normalizedVendor) !== false
                    || strpos($normalizedVendor, $this->normalize_description((string) $vendor['company'])) !== false;
            }));
            if (count($exact) === 1) {
                $capture['vendor_id'] = (int) $exact[0]['id'];
                $this->update_capture($captureId, ['vendor_id' => $capture['vendor_id']]);
            } else {
                $questions[] = ['type' => 'vendor', 'message' => 'Select the vendor before creating the PO.', 'suggestions' => $vendors];
            }
        }
        if (empty($capture['warehouse_id'])) {
            $questions[] = ['type' => 'warehouse', 'message' => 'Which warehouse will receive these goods?', 'suggestions' => $this->find_warehouses('', 20)];
        }

        $lineSuggestions = [];
        foreach ($capture['lines'] as $line) {
            $destination = $line['destination'] ?? 'po';
            if ($destination === 'po' && empty($line['inventory_item_id'])) {
                $candidates = $this->find_inventory_candidates($line['source_description'], $capture['vendor_id'] ?: null, 5, $line['source_barcode'] ?? null);
                $lineSuggestions[] = [
                    'line_id' => (int) $line['id'], 'description' => $line['source_description'],
                    'message' => 'Confirm the canonical inventory item. The receipt line will not be silently assigned.',
                    'suggestions' => $candidates,
                ];
            }
            if (in_array($destination, ['expense', 'personal'], true) && empty($line['expense_category_id'])) {
                $query = $destination === 'personal' ? 'personal' : 'packaging';
                $lineSuggestions[] = [
                    'line_id' => (int) $line['id'], 'description' => $line['source_description'],
                    'message' => 'Select an Expenses module category before creating the expense draft.',
                    'suggestions' => $this->find_expense_categories($query, 10),
                ];
            } elseif (in_array($destination, ['expense', 'personal'], true) && !empty($line['expense_category_id'])) {
                $hasMapping = $this->db->where('category_id', (int) $line['expense_category_id'])
                    ->get(db_prefix() . 'acc_expense_category_mappings')->row();
                if (!$hasMapping) {
                    $lineSuggestions[] = [
                        'line_id' => (int) $line['id'], 'description' => $line['source_description'],
                        'message' => 'This Expenses category has no accounting mapping configured. Configure its payment/deposit mapping before creating the draft.',
                        'suggestions' => $this->find_expense_categories('', 10),
                    ];
                }
            }
        }
        if ($lineSuggestions) $questions = array_merge($questions, $lineSuggestions);
        if ($questions) {
            return ['status' => 'needs_confirmation', 'capture_id' => $captureId, 'questions' => $questions, 'next_step' => 'Update the capture or lines with the confirmed IDs, then call key_in_receipt again. Nothing was created.'];
        }

        $result = $this->create_split_drafts_from_capture($captureId, $isPaid, $paymentModeId);
        if (!$result || (!$result['purchase_order_draft_id'] && empty($result['expense_draft_ids']))) {
            return ['status' => 'error', 'capture_id' => $captureId, 'error' => 'The receipt had no creatable PO or expense lines.'];
        }
        $approvalId = $this->create_approval($captureId, 'approve_split_drafts');
        $result['approval_id'] = $approvalId;
        $result['status'] = 'drafts_created_approval_pending';
        return $result;
    }

    /** Create the PO and expense drafts for a mixed receipt in one agent action. */
    public function create_split_drafts_from_capture(int $captureId, bool $isPaid = false, ?int $paymentModeId = null): array
    {
        $capture = $this->get_capture($captureId);
        if (!$capture || empty($capture['lines'])) {
            return [];
        }

        if (!$paymentModeId && !empty($capture['payment_method']) && $this->db->table_exists(db_prefix() . 'payment_modes')) {
            $paymentMode = $this->db->select('id')->like('name', $capture['payment_method'])
                ->limit(1)->get(db_prefix() . 'payment_modes')->row();
            if ($paymentMode) {
                $paymentModeId = (int) $paymentMode->id;
            }
        }
        if (!$paymentModeId && !empty($capture['payment_method']) && $this->db->table_exists(db_prefix() . 'payment_modes')) {
            $method = strtolower((string) $capture['payment_method']);
            $modeQuery = null;
            if (strpos($method, 'visa') !== false || strpos($method, 'master') !== false
                || strpos($method, 'card') !== false) {
                $modeQuery = 'Credit/Debit Card';
            } elseif (strpos($method, 'cash') !== false) {
                $modeQuery = 'Cash';
            } elseif (strpos($method, 'transfer') !== false) {
                $modeQuery = 'Bank Transfer';
            }
            if ($modeQuery) {
                $paymentMode = $this->db->select('id')->where('name', $modeQuery)
                    ->limit(1)->get(db_prefix() . 'payment_modes')->row();
                if ($paymentMode) {
                    $paymentModeId = (int) $paymentMode->id;
                }
            }
        }

        $poTotal = 0.0;
        $expenseGroups = [];
        foreach ($capture['lines'] as $line) {
            $destination = $line['destination'] ?? 'po';
            $total = $line['line_total'] !== null
                ? (float) $line['line_total']
                : round((float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0), 2);
            if ($destination === 'po') {
                $poTotal += $total;
            } elseif (in_array($destination, ['expense', 'personal'], true)) {
                $categoryId = (int) ($line['expense_category_id'] ?? 0);
                $key = $destination . ':' . $categoryId;
                $expenseGroups[$key]['destination'] = $destination;
                $expenseGroups[$key]['category_id'] = $categoryId;
                $expenseGroups[$key]['amount'] = ($expenseGroups[$key]['amount'] ?? 0) + $total;
                $expenseGroups[$key]['lines'][] = $line;
            }
        }

        $result = ['capture_id' => $captureId, 'purchase_order_draft_id' => null, 'expense_draft_ids' => [], 'warnings' => []];
        $paymentTotal = $isPaid && $capture['payment_total'] !== null ? (float) $capture['payment_total'] : 0.0;
        $expenseTotal = array_sum(array_column($expenseGroups, 'amount'));
        // A previous run may have created the PO before an expense insert
        // failed. Reuse it so retrying only completes the missing side.
        $poId = !empty($capture['purchase_order_draft_id'])
            ? (string) $capture['purchase_order_draft_id']
            : $this->create_purchase_order_draft_from_capture($captureId, $isPaid && $paymentTotal > 0);
        if ($poId) {
            $result['purchase_order_draft_id'] = $poId;
            $poPayment = 0.0;
            if ($isPaid && $paymentTotal > 0) {
                // Keep the draft payment from exceeding the draft's own value.
                // Any card/receipt rounding difference remains visible on the capture.
                $poPayment = min($poTotal, max(0, round($paymentTotal - $expenseTotal, 2)));
                $hasDraftPayment = $this->db->where('draft_id', $poId)
                    ->count_all_results(db_prefix() . 'pur_order_draft_payments') > 0;
                if ($poPayment > 0 && !$hasDraftPayment) {
                    $this->db->insert(db_prefix() . 'pur_order_draft_payments', [
                        'id' => app_generate_hash(), 'draft_id' => $poId, 'amount' => $poPayment,
                        'payment_date' => $capture['payment_date'] ?: $capture['purchase_date'],
                        'payment_mode_id' => $paymentModeId,
                        'initial_payment_mode_label' => $capture['payment_method'],
                    ]);
                }
            }
            $hasPoAllocation = $this->db->where('record_type', 'purchase_order_draft')
                ->where('record_id', $poId)
                ->count_all_results(db_prefix() . 'purchase_agent_payment_allocations') > 0;
            if ($isPaid && $poPayment > 0 && !$hasPoAllocation) {
                $this->record_payment_allocation($capture, 'po', 'purchase_order_draft', $poId, $poPayment, $paymentModeId);
            }
            $this->db->where('id', $poId)->update(db_prefix() . 'pur_order_drafts', [
                'items_subtotal' => round($poTotal, 2),
                'grand_total' => round($poTotal, 2),
            ]);
        }

        foreach ($expenseGroups as $group) {
            $amount = round((float) $group['amount'], 2);
            $categoryId = (int) $group['category_id'];
            $category = $categoryId && $this->db->table_exists(db_prefix() . 'expenses_categories')
                ? $this->db->where('id', $categoryId)->get(db_prefix() . 'expenses_categories')->row()
                : null;
            if (!$category) {
                $result['warnings'][] = 'Expense lines need a valid accounting category before debit/credit mappings can be created.';
            }
            $names = array_map(static function ($line) { return $line['source_description']; }, $group['lines']);
            $data = [
                'category' => $categoryId, 'amount' => $amount, 'tax' => 0, 'reference_no' => $capture['invoice_number'],
                'note' => implode(', ', $names),
                // The live Expenses table does not have attachment/filetype
                // columns. Receipt files are retained on the capture instead.
                'clientid' => 0, 'billable' => 0,
                'paymentmode' => $isPaid ? $paymentModeId : null,
                'date' => $capture['purchase_date'],
                'date_paid' => $isPaid ? ($capture['payment_date'] ?: $capture['purchase_date']) : null,
                'dateadded' => date('Y-m-d H:i:s'), 'addedfrom' => get_staff_user_id(),
                'vendor' => $capture['vendor_id'] ?: null, 'expense_name' => implode(', ', $names),
                // Ordinary receipt expenses belong in the Expenses module.
                // Supplier bills (rent, utilities, etc.) use is_bill=1 and a
                // separate Bill Category workflow.
                'is_bill' => 0, 'is_draft' => 1, 'approved' => 0, 'status' => 0,
                'bill_category_id' => null,
            ];
            $this->db->insert(db_prefix() . 'expenses', $data);
            $expenseId = (int) $this->db->insert_id();
            if (!$expenseId) { continue; }
            if ($isPaid && $amount > 0) {
                $this->record_payment_allocation($capture, $group['destination'], 'expense_draft', (string) $expenseId, $amount, $paymentModeId);
            }
            $result['expense_draft_ids'][] = $expenseId;
        }

        $this->update_capture($captureId, [
            'status' => 'split_drafts',
            'expense_draft_ids' => json_encode($result['expense_draft_ids']),
        ]);
        if ($paymentTotal > 0) {
            $allocated = min($poTotal, max(0, $paymentTotal - $expenseTotal)) + min($expenseTotal, $paymentTotal);
            $rounding = round($paymentTotal - $allocated, 2);
            if (abs($rounding) > 0.009) {
                $this->record_payment_allocation($capture, 'rounding', 'receipt', (string) $captureId, $rounding, $paymentModeId, 'Unallocated receipt payment/rounding adjustment; not assigned to PO or expense.');
            }
        }
        if (round($poTotal + $expenseTotal, 2) !== round((float) $capture['grand_total'], 2)) {
            $result['warnings'][] = 'Receipt line allocation does not reconcile to the receipt total; review rounding or omitted lines.';
        }
        $unallocatedPayment = round($paymentTotal - min($poTotal, max(0, $paymentTotal - $expenseTotal)) - min($expenseTotal, $paymentTotal), 2);
        if ($paymentTotal > 0 && abs($unallocatedPayment) > 0.009) {
            $result['warnings'][] = 'Payment exceeds the PO and expense draft allocations by RM ' . number_format($unallocatedPayment, 2) . '; retain as receipt rounding/payment adjustment.';
        }
        return $result;
    }

    private function record_payment_allocation(array $capture, string $destination, string $recordType, string $recordId, float $amount, ?int $paymentModeId, string $notes = ''): void
    {
        if (!$this->db->table_exists(db_prefix() . 'purchase_agent_payment_allocations')) {
            return;
        }
        $this->db->insert(db_prefix() . 'purchase_agent_payment_allocations', [
            'capture_id' => (int) $capture['id'],
            'destination' => $destination,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'amount' => round($amount, 4),
            'payment_method' => $capture['payment_method'] ?? null,
            'payment_mode_id' => $paymentModeId,
            'payment_date' => $capture['payment_date'] ?: $capture['purchase_date'],
            'status' => 'draft',
            'notes' => $notes ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function add_line(int $captureId, array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data = array_merge([
            'capture_id' => $captureId,
            'status'     => 'needs_review',
            'created_at' => $now,
            'updated_at' => $now,
        ], $data);

        $this->db->insert(db_prefix() . $this->lines, $data);
        return (int) $this->db->insert_id();
    }

    public function update_line(int $lineId, array $data): bool
    {
        $existing = $this->db->where('id', $lineId)->get(db_prefix() . $this->lines)->row_array();
        $allowed = [
            'source_line_number', 'source_description', 'source_barcode',
            'inventory_item_id', 'quantity', 'purchase_unit', 'base_quantity',
            'base_unit', 'conversion_factor', 'unit_price', 'tax', 'discount',
            'line_total', 'match_method', 'match_confidence', 'status',
            'matched_by', 'matched_at', 'review_reason',
            'destination', 'expense_category_id', 'bill_category_id',
        ];
        $data = array_intersect_key($data, array_flip($allowed));
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $lineId)->update(db_prefix() . $this->lines, $data);
        $inventoryItemId = !empty($data['inventory_item_id']) ? (int) $data['inventory_item_id'] : (int) ($existing['inventory_item_id'] ?? 0);
        if ($inventoryItemId && !empty($existing['capture_id'])) {
            $capture = $this->get_capture((int) $existing['capture_id']);
            if ($capture && !empty($capture['vendor_id'])) {
                $description = (string) ($data['source_description'] ?? $existing['source_description'] ?? '');
                $normalized = $this->normalize_description($description);
                $alias = $this->db->where('vendor_id', (int) $capture['vendor_id'])
                    ->where('normalized_description', $normalized)
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('active', 1)->get(db_prefix() . $this->aliases)->row();
                if (!$alias && $normalized !== '') {
                    $this->save_vendor_alias([
                        'vendor_id' => (int) $capture['vendor_id'],
                        'vendor_description' => $description,
                        'source_barcode' => $data['source_barcode'] ?? ($existing['source_barcode'] ?? null),
                        'normalized_description' => $normalized,
                        'inventory_item_id' => $inventoryItemId,
                        'purchase_unit' => $data['purchase_unit'] ?? ($existing['purchase_unit'] ?? null),
                        'base_unit' => $data['base_unit'] ?? ($existing['base_unit'] ?? null),
                        'units_per_pack' => $data['conversion_factor'] ?? ($existing['conversion_factor'] ?? null),
                        'confidence' => 100,
                        'confirmed_by' => get_staff_user_id(),
                        'confirmed_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
        return $this->db->affected_rows() >= 0;
    }

    public function save_vendor_alias(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data = array_merge([
            'confidence' => 100,
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $data);

        $this->db->insert(db_prefix() . $this->aliases, $data);
        return (int) $this->db->insert_id();
    }

    public function find_vendor_aliases(?int $vendorId, string $description, int $limit = 10): array
    {
        $this->db->like('normalized_description', $this->normalize_description($description));
        $this->db->where('active', 1);
        $this->db->group_start();
        $this->db->where('vendor_id', $vendorId);
        $this->db->or_where('vendor_id IS NULL', null, false);
        $this->db->group_end();
        return $this->db
            ->order_by('confidence', 'DESC')
            ->limit($limit)
            ->get(db_prefix() . $this->aliases)
            ->result_array();
    }

    /**
     * Return ranked inventory candidates without assigning one. The agent can
     * use this at PO preparation and again at receiving time.
     */
    public function find_inventory_candidates(string $description, ?int $vendorId = null, int $limit = 8, ?string $barcode = null): array
    {
        $normalized = $this->normalize_description($description);
        $barcode = trim((string) $barcode);

        if ($barcode !== '') {
            $this->db->where('source_barcode', $barcode);
            $this->db->where('active', 1);
            if ($vendorId !== null) {
                $this->db->where('vendor_id', $vendorId);
            }
            $alias = $this->db->limit(1)->get(db_prefix() . $this->aliases)->row_array();
            if ($alias) {
                $item = $this->db
                    ->select('id, description, commodity_code, commodity_barcode, sku_code, sku_name, purchase_price, unit_id')
                    ->where('id', (int) $alias['inventory_item_id'])
                    ->where('active', 1)
                    ->get(db_prefix() . 'items')
                    ->row_array();
                if ($item) {
                    return [[
                        'item_id' => (int) $item['id'],
                        'description' => $item['description'],
                        'commodity_code' => $item['commodity_code'],
                        'commodity_barcode' => $item['commodity_barcode'],
                        'sku_code' => $item['sku_code'],
                        'sku_name' => $item['sku_name'],
                        'purchase_price' => $item['purchase_price'],
                        'unit_id' => $item['unit_id'],
                        'score' => 1.0,
                        'match_source' => 'confirmed_vendor_barcode',
                        'reason' => 'Previously confirmed barcode mapping for this vendor',
                    ]];
                }
            }

            $row = $this->db
                ->select('id, description, long_description, commodity_code, commodity_barcode, sku_code, sku_name, purchase_price, unit_id')
                ->group_start()
                ->where('commodity_barcode', $barcode)
                ->or_where('commodity_code', $barcode)
                ->or_where('sku_code', $barcode)
                ->group_end()
                ->where('active', 1)
                ->get(db_prefix() . 'items')
                ->row_array();

            if ($row) {
                return [[
                    'item_id'        => (int) $row['id'],
                    'description'    => $row['description'],
                    'commodity_code' => $row['commodity_code'],
                    'commodity_barcode' => $row['commodity_barcode'],
                    'sku_code'       => $row['sku_code'],
                    'sku_name'       => $row['sku_name'],
                    'purchase_price' => $row['purchase_price'],
                    'unit_id'        => $row['unit_id'],
                    'score'          => 1.0,
                    'match_source'   => 'barcode',
                    'reason'         => 'Exact barcode match',
                ]];
            }
        }

        if ($normalized === '') {
            return [];
        }

        $aliases = $this->find_vendor_aliases($vendorId, $description, 20);
        $aliasByItem = [];
        foreach ($aliases as $alias) {
            $aliasByItem[(int) $alias['inventory_item_id']] = $alias;
        }

        $tokens = array_values(array_filter(explode(' ', $normalized), function ($token) {
            return strlen($token) >= 2;
        }));

        $this->db->select('id, description, long_description, commodity_code, sku_code, sku_name, purchase_price, unit_id');
        $this->db->where('active', 1);
        $this->db->group_start();
        foreach ($tokens as $token) {
            $this->db->or_like('description', $token);
            $this->db->or_like('long_description', $token);
            $this->db->or_like('commodity_code', $token);
            $this->db->or_like('sku_code', $token);
            $this->db->or_like('sku_name', $token);
        }
        $this->db->group_end();
        $rows = $this->db->limit(100)->get(db_prefix() . 'items')->result_array();

        $candidates = [];
        foreach ($rows as $row) {
            $itemId = (int) $row['id'];
            $haystack = $this->normalize_description(implode(' ', [
                $row['description'] ?? '',
                $row['long_description'] ?? '',
                $row['commodity_code'] ?? '',
                $row['sku_code'] ?? '',
                $row['sku_name'] ?? '',
            ]));
            $score = 0.0;
            $matchedTokens = 0;
            foreach ($tokens as $token) {
                if (strpos($haystack, $token) !== false) {
                    $matchedTokens++;
                }
            }
            if ($tokens) {
                $score = ($matchedTokens / count($tokens)) * 70;
            }
            if (strpos($haystack, $normalized) !== false || strpos($normalized, $haystack) !== false) {
                $score += 15;
            }
            if (isset($aliasByItem[$itemId])) {
                $score = max($score, (float) $aliasByItem[$itemId]['confidence']);
            }
            if ($score < 20) {
                continue;
            }

            $candidates[] = [
                'item_id'       => $itemId,
                'description'   => $row['description'],
                'commodity_code'=> $row['commodity_code'],
                'commodity_barcode' => $row['commodity_barcode'] ?? null,
                'sku_code'      => $row['sku_code'],
                'sku_name'      => $row['sku_name'],
                'purchase_price'=> $row['purchase_price'],
                'unit_id'       => $row['unit_id'],
                'score'         => round(min(100, $score) / 100, 4),
                'match_source'  => isset($aliasByItem[$itemId]) ? 'confirmed_vendor_alias' : 'inventory_text_match',
                'reason'        => isset($aliasByItem[$itemId])
                    ? 'Previously confirmed for this vendor'
                    : 'Matched inventory description, SKU or commodity code',
            ];
        }

        usort($candidates, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        return array_slice($candidates, 0, max(1, min(20, $limit)));
    }

    public function find_vendors(string $query, int $limit = 10): array
    {
        $this->db->select('userid AS id, company, vat, phonenumber');
        $this->db->group_start();
        $this->db->like('company', $query);
        $this->db->or_like('vat', $query);
        $this->db->or_like('phonenumber', $query);
        $this->db->group_end();
        return $this->db->limit(max(1, min(20, $limit)))->get(db_prefix() . 'pur_vendor')->result_array();
    }

    public function find_warehouses(string $query = '', int $limit = 20): array
    {
        if ($query !== '') {
            $this->db->group_start();
            $this->db->like('warehouse_name', $query);
            $this->db->or_like('warehouse_code', $query);
            $this->db->group_end();
        }
        return $this->db->select('warehouse_id AS id, warehouse_name, warehouse_code')
            ->where('display', 1)
            ->limit(max(1, min(50, $limit)))
            ->get(db_prefix() . 'warehouse')
            ->result_array();
    }

    public function record_correction(int $captureId, ?int $lineId, string $field, $oldValue, $newValue, ?string $reason, ?int $staffId): int
    {
        $this->db->insert(db_prefix() . $this->corrections, [
            'capture_id'  => $captureId,
            'line_id'     => $lineId,
            'field_name'  => $field,
            'old_value'   => $this->scalar_json($oldValue),
            'new_value'   => $this->scalar_json($newValue),
            'reason'      => $reason,
            'corrected_by'=> $staffId,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    public function create_approval(int $captureId, string $action, string $requestedBy = 'claude'): int
    {
        $this->db->insert(db_prefix() . $this->approvals, [
            'capture_id'      => $captureId,
            'requested_action'=> $action,
            'status'          => 'pending',
            'requested_by'    => $requestedBy,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    public function log_action(array $data): int
    {
        if (!empty($data['request_json']) && is_array($data['request_json'])) {
            $data['request_json'] = json_encode($data['request_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (!empty($data['response_json']) && is_array($data['response_json'])) {
            $data['response_json'] = json_encode($data['response_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . $this->actions, $data);
        return (int) $this->db->insert_id();
    }

    public function normalize_description(string $description): string
    {
        $description = strtolower(trim($description));
        $description = preg_replace('/[^a-z0-9]+/i', ' ', $description);
        return trim(preg_replace('/\s+/', ' ', $description));
    }

    private function scalar_json($value): ?string
    {
        if ($value === null) {
            return null;
        }
        return is_scalar($value)
            ? (string) $value
            : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
