<?php

defined('BASEPATH') or exit('No direct script access allowed');

// MCP requests can enter the purchase workflow without loading the normal
// purchase module bootstrap. Draft attachment code still uses this module
// constant, so provide the same canonical fallback used by Purchase_model.
if (!defined('PURCHASE_MODULE_UPLOAD_FOLDER')) {
    define('PURCHASE_MODULE_UPLOAD_FOLDER', module_dir_path('purchase', 'uploads'));
}

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
    private $uploads    = 'purchase_agent_uploads';

    private const RECEIPT_UPLOAD_MAX_BYTES = 31457280;
    private const RECEIPT_BASE64_MAX_BYTES = 15728640;

    public function request_receipt_upload(array $data): array
    {
        $fileName = trim((string) ($data['file_name'] ?? 'receipt'));
        $fileName = basename(str_replace('\\', '/', $fileName));
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            $fileName = 'receipt';
        }

        $mime = strtolower(trim((string) ($data['mime_type'] ?? 'application/octet-stream')));
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            return ['error' => 'Only JPEG, PNG, GIF, WebP and PDF receipts are supported.'];
        }
        $declaredSize = isset($data['size_bytes']) ? (int) $data['size_bytes'] : null;
        if ($declaredSize !== null && ($declaredSize < 1 || $declaredSize > self::RECEIPT_UPLOAD_MAX_BYTES)) {
            return ['error' => 'Receipt file exceeds the 30 MB limit.'];
        }

        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + 600);
        $this->db->insert(db_prefix() . $this->uploads, [
            'token_hash' => hash('sha256', $token),
            'status' => 'requested',
            'file_name' => substr($fileName, 0, 255),
            'mime_type' => $mime,
            'max_bytes' => self::RECEIPT_UPLOAD_MAX_BYTES,
            'expires_at' => $expires,
            'created_at' => $now,
        ]);

        return [
            'upload_id' => (int) $this->db->insert_id(),
            'upload_url' => site_url('purchase/agent_upload/' . $token),
            'upload_method' => 'PUT',
            'expires_at' => $expires,
            'max_bytes' => self::RECEIPT_UPLOAD_MAX_BYTES,
            'content_type' => $mime,
            'file_name' => $fileName,
        ];
    }

    public function start_receipt_mcp_upload(array $data): array
    {
        $fileName = basename(str_replace('\\', '/', trim((string) ($data['file_name'] ?? 'receipt'))));
        $mime = strtolower(trim((string) ($data['mime_type'] ?? 'application/octet-stream')));
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true)) return ['error' => 'Convert HEIC or unsupported formats to JPEG or PDF first.'];
        $token = bin2hex(random_bytes(32));
        $this->db->insert(db_prefix() . $this->uploads, [
            'token_hash' => hash('sha256', $token), 'upload_source' => 'mcp_base64', 'status' => 'requested',
            'file_name' => substr($fileName ?: 'receipt', 0, 255), 'mime_type' => $mime,
            'max_bytes' => self::RECEIPT_BASE64_MAX_BYTES, 'expires_at' => date('Y-m-d H:i:s', time() + 600),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['upload_id' => (int) $this->db->insert_id(), 'chunk_size' => 48000, 'expires_in_seconds' => 600, 'max_decoded_bytes' => self::RECEIPT_BASE64_MAX_BYTES, 'file_name' => $fileName, 'mime_type' => $mime];
    }

    public function append_receipt_mcp_upload_chunk(int $uploadId, string $chunk, int $chunkIndex, int $totalChunks): array
    {
        if ($chunk === '' || !preg_match('/^[A-Za-z0-9+\/=]+$/', $chunk)) return ['error' => 'Chunk is not valid base64.'];
        $upload = $this->get_receipt_upload($uploadId);
        if (!$upload || $upload['upload_source'] !== 'mcp_base64' || $upload['status'] !== 'requested') return ['error' => 'Upload session is invalid or already finalized.'];
        if (strtotime($upload['expires_at']) < time()) return ['error' => 'Upload session has expired.'];
        if ($chunkIndex !== (int) $upload['next_chunk']) return ['error' => 'Chunks must be sent in order. Expected chunk ' . (int) $upload['next_chunk'] . '.'];
        if ($totalChunks < 1 || $chunkIndex >= $totalChunks) return ['error' => 'Invalid total chunk count.'];
        $existing = (string) ($upload['base64_data'] ?? '');
        if (strlen($existing) + strlen($chunk) > 20971520) return ['error' => 'Base64 upload exceeds the permitted size.'];
        $this->db->where('id', $uploadId)->update(db_prefix() . $this->uploads, ['base64_data' => $existing . $chunk, 'next_chunk' => $chunkIndex + 1, 'total_chunks' => $totalChunks]);
        return ['upload_id' => $uploadId, 'received_chunk' => $chunkIndex, 'next_chunk' => $chunkIndex + 1, 'total_chunks' => $totalChunks];
    }

    public function finalize_receipt_mcp_upload(int $uploadId): array
    {
        $upload = $this->get_receipt_upload($uploadId);
        if (!$upload || $upload['upload_source'] !== 'mcp_base64') return ['error' => 'Upload session not found.'];
        if ((int) $upload['next_chunk'] !== (int) $upload['total_chunks']) return ['error' => 'Upload is incomplete.'];
        $blob = base64_decode((string) $upload['base64_data'], true);
        if ($blob === false || $blob === '' || strlen($blob) > self::RECEIPT_BASE64_MAX_BYTES) return ['error' => 'The assembled base64 data is invalid or too large.'];
        $this->db->where('id', $uploadId)->update(db_prefix() . $this->uploads, ['status' => 'ready', 'size_bytes' => strlen($blob), 'sha256' => hash('sha256', $blob), 'file_blob' => $blob, 'finalized_at' => date('Y-m-d H:i:s')]);
        return ['upload_id' => $uploadId, 'status' => 'ready', 'file_name' => $upload['file_name'], 'mime_type' => $upload['mime_type'], 'size_bytes' => strlen($blob), 'sha256' => hash('sha256', $blob)];
    }

    public function store_receipt_upload(string $token, string $body, string $contentType): array
    {
        $upload = $this->get_receipt_upload_by_token($token);
        if (!$upload) return ['error' => 'Upload URL is invalid or expired.'];
        if ($upload['status'] !== 'requested') return ['error' => 'Upload URL has already been used.'];
        if (strtotime($upload['expires_at']) < time()) return ['error' => 'Upload URL has expired.'];
        if (strlen($body) < 1 || strlen($body) > (int) $upload['max_bytes']) {
            return ['error' => 'Receipt file exceeds the permitted size.'];
        }

        $expectedMime = strtolower((string) $upload['mime_type']);
        $actualMime = strtolower(trim(explode(';', $contentType)[0]));
        if ($actualMime !== 'application/octet-stream' && $actualMime !== $expectedMime) {
            return ['error' => 'Uploaded content type does not match the requested file type.'];
        }
        $this->db->where('id', (int) $upload['id'])->update(db_prefix() . $this->uploads, [
            'status' => 'uploaded',
            'size_bytes' => strlen($body),
            'sha256' => hash('sha256', $body),
            'file_blob' => $body,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);
        return ['upload_id' => (int) $upload['id'], 'status' => 'uploaded', 'size_bytes' => strlen($body), 'sha256' => hash('sha256', $body)];
    }

    public function finalize_receipt_upload(int $uploadId): array
    {
        $upload = $this->db->where('id', $uploadId)->get(db_prefix() . $this->uploads)->row_array();
        if (!$upload) return ['error' => 'Upload not found.'];
        if ($upload['status'] !== 'uploaded' || empty($upload['file_blob'])) return ['error' => 'The receipt has not been uploaded yet.'];
        if (strtotime($upload['expires_at']) < time()) return ['error' => 'Upload has expired.'];
        $this->db->where('id', $uploadId)->update(db_prefix() . $this->uploads, ['status' => 'ready', 'finalized_at' => date('Y-m-d H:i:s')]);
        return ['upload_id' => $uploadId, 'status' => 'ready', 'file_name' => $upload['file_name'], 'mime_type' => $upload['mime_type'], 'size_bytes' => (int) $upload['size_bytes'], 'sha256' => $upload['sha256']];
    }

    private function get_receipt_upload_by_token(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $row = $this->db->where('token_hash', hash('sha256', $token))->get(db_prefix() . $this->uploads)->row_array();
        return $row ?: null;
    }

    private function get_receipt_upload(int $uploadId): ?array
    {
        $row = $this->db->where('id', $uploadId)->get(db_prefix() . $this->uploads)->row_array();
        return $row ?: null;
    }

    public function create_capture(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $receiptUploadId = isset($data['receipt_upload_id']) ? (int) $data['receipt_upload_id'] : 0;
        unset($data['receipt_upload_id']);
        $receiptBase64 = $data['receipt_image_base64'] ?? null;
        unset($data['receipt_image_base64']);
        if (is_string($receiptBase64) && $receiptBase64 !== '') {
            // Accept either plain base64 or a data URL produced by local
            // preprocessing in Claude's sandbox.
            if (preg_match('/^data:[^;]+;base64,(.*)$/s', $receiptBase64, $matches)) {
                $receiptBase64 = $matches[1];
            }
            $receiptBlob = base64_decode($receiptBase64, true);
            if ($receiptBlob === false || strlen($receiptBlob) < 1) {
                throw new RuntimeException('receipt_image_base64 is not valid base64.');
            }
            if (strlen($receiptBlob) > self::RECEIPT_BASE64_MAX_BYTES) {
                throw new RuntimeException('The processed receipt must be 15 MB or smaller. Resize or compress it before sending.');
            }
            $mime = strtolower((string) ($data['receipt_mime_type'] ?? 'application/octet-stream'));
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            if (!in_array($mime, $allowedMimes, true)) {
                throw new RuntimeException('Unsupported receipt MIME type. Convert HEIC or another format to JPEG or PDF first.');
            }
            if ($receiptBlob !== false) {
                $data['receipt_blob'] = $receiptBlob;
                $data['receipt_file_name'] = $data['receipt_file_name'] ?? 'receipt';
                $data['receipt_mime_type'] = $mime;
            }
        }
        if ($receiptUploadId > 0 && empty($data['receipt_blob'])) {
            $upload = $this->get_receipt_upload($receiptUploadId);
            if (!$upload || !in_array($upload['status'], ['uploaded', 'ready'], true) || empty($upload['file_blob'])) {
                throw new RuntimeException('Receipt upload is not ready. Finalize the upload before creating the capture.');
            }
            if (strtotime($upload['expires_at']) < time()) {
                throw new RuntimeException('Receipt upload has expired.');
            }
            $data['receipt_blob'] = $upload['file_blob'];
            $data['receipt_file_name'] = $upload['file_name'];
            $data['receipt_mime_type'] = $upload['mime_type'];
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
        $captureId = (int) $this->db->insert_id();
        if ($captureId && $receiptUploadId > 0) {
            $this->db->where('id', $receiptUploadId)->update(db_prefix() . $this->uploads, ['status' => 'attached', 'capture_id' => $captureId]);
        }
        return $captureId;
    }

    /**
     * Replace a capture's retained receipt and propagate it to any drafts
     * already linked to that capture. This repairs an attachment without
     * creating a second PO or expense draft.
     */
    public function replace_receipt_attachment(int $captureId, array $data): array
    {
        $capture = $this->db->where('id', $captureId)->get(db_prefix() . $this->captures)->row_array();
        if (!$capture) return ['error' => 'Capture not found.'];

        $base64 = (string) ($data['receipt_image_base64'] ?? '');
        if (preg_match('/^data:[^;]+;base64,(.*)$/s', $base64, $matches)) {
            $base64 = $matches[1];
        }
        $blob = base64_decode($base64, true);
        if ($blob === false || $blob === '') return ['error' => 'receipt_image_base64 is not valid base64.'];
        if (strlen($blob) > self::RECEIPT_BASE64_MAX_BYTES) return ['error' => 'The processed receipt must be 15 MB or smaller.'];

        $mime = strtolower((string) ($data['receipt_mime_type'] ?? 'application/octet-stream'));
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true)) {
            return ['error' => 'Unsupported receipt MIME type. Convert HEIC to JPEG first.'];
        }
        $fileName = basename(str_replace('\\', '/', (string) ($data['receipt_file_name'] ?? 'receipt')));
        if ($fileName === '' || $fileName === '.' || $fileName === '..') $fileName = 'receipt';

        $this->db->where('id', $captureId)->update(db_prefix() . $this->captures, [
            'receipt_blob' => $blob,
            'receipt_file_name' => substr($fileName, 0, 255),
            'receipt_mime_type' => $mime,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = [
            'capture_id' => $captureId,
            'receipt_attachment' => [
                'attached' => true,
                'file_name' => $fileName,
                'mime_type' => $mime,
                'size_bytes' => strlen($blob),
                'sha256' => hash('sha256', $blob),
            ],
            'purchase_order_draft_id' => $capture['purchase_order_draft_id'] ?: null,
            'expense_draft_ids' => json_decode($capture['expense_draft_ids'] ?: '[]', true) ?: [],
            'updated_drafts' => [],
        ];

        if (!empty($capture['purchase_order_draft_id'])) {
            $CI = &get_instance();
            $CI->load->model('purchase/purchase_order_drafts_model');
            $attachment = $CI->purchase_order_drafts_model->replace_attachment_from_blob(
                (string) $capture['purchase_order_draft_id'], $fileName, $blob, get_staff_user_id()
            );
            if ($attachment) $result['updated_drafts'][] = ['type' => 'purchase_order', 'id' => $capture['purchase_order_draft_id']];
        }
        foreach ($result['expense_draft_ids'] as $expenseId) {
            if ($this->replace_expense_attachment((int) $expenseId, $fileName, $blob)) {
                $result['updated_drafts'][] = ['type' => 'expense', 'id' => (int) $expenseId];
            }
        }
        return $result;
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

        foreach ($capture['lines'] as &$line) {
            $line = $this->normalize_purchase_line_units($line);
            $line['batch_quantity'] = $line['quantity'] ?? null;
            $line['units_per_batch'] = $line['conversion_factor'] ?? null;
        }
        unset($line);

        // Keep binary receipt data out of normal MCP responses. It is fetched
        // only when a draft is being created and the receipt is copied to it.
        $capture['receipt_attachment'] = !empty($capture['receipt_blob']) ? [
            'attached' => true,
            'file_name' => $capture['receipt_file_name'] ?? null,
            'mime_type' => $capture['receipt_mime_type'] ?? null,
            'size_bytes' => strlen($capture['receipt_blob']),
            'sha256' => hash('sha256', $capture['receipt_blob']),
        ] : ['attached' => false];
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
            'payment_total', 'payment_method', 'payment_date', 'rounding_amount', 'payment_adjustment_amount',
            'receipt_hash', 'raw_extraction', 'notes', 'purchase_order_draft_id',
            'purchase_order_id', 'goods_receipt_id', 'expense_draft_ids', 'bill_draft_ids',
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
    public function create_purchase_order_draft_from_capture(int $captureId, bool $isPaid = false, bool $itemsReceived = false, ?string $itemsReceivedDate = null): ?string
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
            'items_received' => $itemsReceived ? 1 : 0,
            // Walk-in stock is received when the draft is keyed in. Keep this
            // separate from the purchase date printed on the receipt.
            'items_received_date' => $itemsReceived ? ($itemsReceivedDate ?: date('Y-m-d')) : null,
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

    public function find_purchase_order_drafts(string $query = '', int $limit = 20): array
    {
        $CI = &get_instance();
        $CI->load->model('purchase/purchase_order_drafts_model');
        return $CI->purchase_order_drafts_model->get_drafts([
            'search' => trim($query), 'vendor_id' => null, 'is_paid' => null,
            'order_date_from' => null, 'order_date_to' => null,
        ], max(1, min(50, $limit)), 0);
    }

    public function get_purchase_order_draft(string $draftId): ?array
    {
        $CI = &get_instance();
        $CI->load->model('purchase/purchase_order_drafts_model');
        return $CI->purchase_order_drafts_model->get_draft_with_relations($draftId);
    }

    public function find_expense_drafts(string $query = '', int $limit = 20): array
    {
        $this->db->from(db_prefix() . 'expenses e');
        $this->db->select('e.id, e.vendor, e.expense_name, e.note, e.amount, e.date, e.category, e.paymentmode, e.is_draft, e.approved, v.company as vendor_name, c.name as category_name');
        $this->db->join(db_prefix() . 'pur_vendor v', 'v.id = e.vendor', 'left');
        $this->db->join(db_prefix() . 'expenses_categories c', 'c.id = e.category', 'left');
        $this->db->where('e.is_draft', 1);
        $this->db->where('e.purchase_agent_cancelled', 0);
        if (trim($query) !== '') {
            $this->db->group_start();
            $this->db->like('e.expense_name', trim($query));
            $this->db->or_like('e.note', trim($query));
            $this->db->or_like('v.company', trim($query));
            $this->db->group_end();
        }
        return $this->db->order_by('e.id', 'DESC')->limit(max(1, min(50, $limit)))->get()->result_array();
    }

    public function get_expense_draft(int $expenseId): ?array
    {
        $CI = &get_instance();
        $CI->load->model('expenses_model');
        $expense = $CI->expenses_model->get($expenseId);
        if (!$expense || empty($expense->is_draft)) return null;
        return json_decode(json_encode($expense), true);
    }

    public function update_purchase_order_draft(string $draftId, array $data): array
    {
        $current = $this->get_purchase_order_draft($draftId);
        if (!$current) return ['error' => 'Purchase order draft not found.'];
        if (($current['status'] ?? 'draft') === 'cancelled') return ['error' => 'Cancelled purchase order drafts cannot be edited.'];
        $items = $current['items'] ?? [];
        if (isset($data['items'])) {
            $items = [];
            foreach ((array) $data['items'] as $item) {
                $item = (array) $item;
                if (!array_key_exists('quantity', $item) && array_key_exists('batch_quantity', $item)) {
                    $item['quantity'] = $item['batch_quantity'];
                }
                $items[] = array_intersect_key($item, array_flip(['id', 'line_item_id', 'inventory_item_id', 'inventory_item_name', 'description', 'quantity', 'units_per_batch', 'subtotal', 'discount']));
            }
        }
        $subtotal = 0.0;
        foreach ($items as $item) $subtotal += (float) ($item['subtotal'] ?? 0);
        $header = array_intersect_key($data, array_flip(['vendor_id', 'vendor_name', 'warehouse_id', 'order_name', 'order_date', 'is_paid', 'items_received', 'items_received_date']));
        if (array_key_exists('items_received', $header) && !$header['items_received']) {
            $header['items_received_date'] = null;
        } elseif (!empty($header['items_received']) && empty($header['items_received_date'])) {
            $header['items_received_date'] = date('Y-m-d');
        }
        $header['items_subtotal'] = round($subtotal, 2);
        $header['grand_total'] = round($subtotal, 2);
        $header['updated_at'] = date('Y-m-d H:i:s');
        $CI = &get_instance();
        $CI->load->model('purchase/purchase_order_drafts_model');
        $ok = $CI->purchase_order_drafts_model->update_draft($draftId, $header, $items, $current['payments'] ?? []);
        return $ok ? ['draft_id' => $draftId, 'status' => 'updated', 'draft' => $this->get_purchase_order_draft($draftId)] : ['error' => 'Purchase order draft could not be updated.'];
    }

    public function cancel_purchase_order_draft(string $draftId, string $reason): array
    {
        $current = $this->get_purchase_order_draft($draftId);
        if (!$current) return ['error' => 'Purchase order draft not found.'];
        if (($current['status'] ?? 'draft') === 'cancelled') return ['draft_id' => $draftId, 'status' => 'cancelled'];
        $CI = &get_instance();
        $CI->load->model('purchase/purchase_order_drafts_model');
        return $CI->purchase_order_drafts_model->cancel_draft($draftId, $reason)
            ? ['draft_id' => $draftId, 'status' => 'cancelled'] : ['error' => 'Purchase order draft could not be cancelled.'];
    }

    public function update_expense_draft(int $expenseId, array $data): array
    {
        $current = $this->get_expense_draft($expenseId);
        if (!$current) return ['error' => 'Expense draft not found.'];
        $update = array_intersect_key($data, array_flip(['vendor', 'expense_name', 'category', 'amount', 'date', 'paymentmode', 'note', 'reference_no']));
        if (!$update) return ['error' => 'No editable expense fields were supplied.'];
        $this->db->where('id', $expenseId)->where('is_draft', 1)->where('purchase_agent_cancelled', 0)->update(db_prefix() . 'expenses', $update);
        return ['expense_id' => $expenseId, 'status' => 'updated', 'expense' => $this->get_expense_draft($expenseId)];
    }

    public function cancel_expense_draft(int $expenseId, string $reason): array
    {
        $current = $this->get_expense_draft($expenseId);
        if (!$current) return ['error' => 'Expense draft not found.'];
        $this->db->where('id', $expenseId)->where('is_draft', 1)->update(db_prefix() . 'expenses', [
            'purchase_agent_cancelled' => 1, 'purchase_agent_cancelled_at' => date('Y-m-d H:i:s'), 'purchase_agent_cancellation_note' => $reason,
        ]);
        return ['expense_id' => $expenseId, 'status' => 'cancelled'];
    }

    public function delete_purchase_order_draft(string $draftId, string $confirmation): array
    {
        if ($confirmation !== 'DELETE_DRAFT') return ['error' => 'Explicit confirmation DELETE_DRAFT is required.'];
        $draft = $this->get_purchase_order_draft($draftId);
        if (!$draft) return ['error' => 'Purchase order draft not found.'];
        if (($draft['status'] ?? 'draft') !== 'draft') return ['error' => 'Only active, unapproved PO drafts can be permanently deleted.'];

        $captures = $this->db->where('purchase_order_draft_id', $draftId)->get(db_prefix() . $this->captures)->result_array();
        $CI = &get_instance();
        $CI->load->model('purchase/purchase_order_drafts_model');
        if (!$CI->purchase_order_drafts_model->delete_draft($draftId)) return ['error' => 'Purchase order draft could not be deleted.'];
        foreach ($captures as $capture) {
            $this->update_capture((int) $capture['id'], ['purchase_order_draft_id' => null, 'status' => 'captured']);
        }
        return ['draft_id' => $draftId, 'status' => 'deleted', 'reopened_capture_ids' => array_map('intval', array_column($captures, 'id'))];
    }

    public function delete_expense_draft(int $expenseId, string $confirmation): array
    {
        if ($confirmation !== 'DELETE_DRAFT') return ['error' => 'Explicit confirmation DELETE_DRAFT is required.'];
        $expense = $this->get_expense_draft($expenseId);
        if (!$expense) return ['error' => 'Expense draft not found.'];
        if ((int) ($expense['purchase_agent_cancelled'] ?? 0) !== 0) return ['error' => 'Cancelled expense drafts cannot be deleted again.'];
        if ((int) ($expense['approved'] ?? 0) !== 0 || (int) ($expense['is_draft'] ?? 0) !== 1) {
            return ['error' => 'Only unapproved expense drafts can be permanently deleted.'];
        }
        $attachmentTable = db_prefix() . 'wa_expense_attachments';
        if ($this->db->table_exists($attachmentTable)) {
            $this->db->where('expense_id', $expenseId)->delete($attachmentTable);
        }
        $this->db->where('id', $expenseId)->where('is_draft', 1)->delete(db_prefix() . 'expenses');
        $captures = $this->db->where('expense_draft_ids IS NOT NULL', null, false)->get(db_prefix() . $this->captures)->result_array();
        $captures = array_values(array_filter($captures, static function ($capture) use ($expenseId) {
            $ids = json_decode($capture['expense_draft_ids'] ?: '[]', true) ?: [];
            return in_array($expenseId, array_map('intval', $ids), true);
        }));
        foreach ($captures as $capture) {
            $ids = json_decode($capture['expense_draft_ids'] ?: '[]', true) ?: [];
            $ids = array_values(array_filter($ids, static function ($id) use ($expenseId) { return (int) $id !== $expenseId; }));
            $this->update_capture((int) $capture['id'], ['expense_draft_ids' => json_encode($ids)]);
        }
        return ['expense_id' => $expenseId, 'status' => 'deleted', 'updated_capture_ids' => array_map('intval', array_column($captures, 'id'))];
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
    public function key_in_receipt(int $captureId, bool $isPaid = false, ?int $paymentModeId = null, bool $walkIn = false): array
    {
        $capture = $this->get_capture($captureId);
        if (!$capture) return ['status' => 'error', 'error' => 'Capture not found'];
        $existingExpenseDraftIds = json_decode($capture['expense_draft_ids'] ?: '[]', true);
        $existingBillDraftIds = json_decode($capture['bill_draft_ids'] ?? '[]', true) ?: [];
        $hasExpenseLines = false;
        $hasBillLines = false;
        $hasPoLines = false;
        foreach ($capture['lines'] as $captureLine) {
            $destination = $captureLine['destination'] ?? 'po';
            if (in_array($destination, ['expense', 'personal'], true)) {
                $hasExpenseLines = true;
            } elseif ($destination === 'bill') {
                $hasBillLines = true;
            } elseif ($destination === 'po') {
                $hasPoLines = true;
            }
        }
        if ((!empty($capture['purchase_order_draft_id']) && !$hasExpenseLines && !$hasBillLines)
            || !empty($existingExpenseDraftIds) || !empty($existingBillDraftIds)) {
            return ['status' => 'already_prepared', 'capture_id' => $captureId, 'purchase_order_draft_id' => $capture['purchase_order_draft_id'], 'expense_draft_ids' => $existingExpenseDraftIds, 'bill_draft_ids' => $existingBillDraftIds];
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
        if ($hasPoLines && empty($capture['warehouse_id'])) {
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
            if ($destination === 'bill') {
                $categoryId = (int) ($line['bill_category_id'] ?? 0);
                $category = $categoryId && $this->db->table_exists(db_prefix() . 'acc_bill_categories')
                    ? $this->db->where('id', $categoryId)->where('active', 1)->get(db_prefix() . 'acc_bill_categories')->row()
                    : null;
                if (!$category) {
                    $lineSuggestions[] = ['line_id' => (int) $line['id'], 'description' => $line['source_description'], 'message' => 'Select an Accounting Bill category with debit and credit accounts before creating the bill draft.', 'suggestions' => $this->find_bill_categories($line['source_description'], 10)];
                } elseif (empty($category->debit_account) || empty($category->credit_account)) {
                    $lineSuggestions[] = ['line_id' => (int) $line['id'], 'description' => $line['source_description'], 'message' => 'The selected Bill category is missing debit or credit mapping.', 'suggestions' => $this->find_bill_categories('', 10)];
                }
            }
        }
        if ($lineSuggestions) $questions = array_merge($questions, $lineSuggestions);
        if ($questions) {
            return ['status' => 'needs_confirmation', 'capture_id' => $captureId, 'questions' => $questions, 'next_step' => 'Update the capture or lines with the confirmed IDs, then call key_in_receipt again. Nothing was created.'];
        }

        $result = $this->create_split_drafts_from_capture($captureId, $isPaid, $paymentModeId, $walkIn);
        if (!$result || (!$result['purchase_order_draft_id'] && empty($result['expense_draft_ids']) && empty($result['bill_draft_ids']))) {
            return ['status' => 'error', 'capture_id' => $captureId, 'error' => 'The receipt had no creatable PO or expense lines.'];
        }
        $approvalId = $this->create_approval($captureId, 'approve_split_drafts');
        $result['approval_id'] = $approvalId;
        $result['status'] = 'drafts_created_approval_pending';
        return $result;
    }

    /** Create the PO and expense drafts for a mixed receipt in one agent action. */
    public function create_split_drafts_from_capture(int $captureId, bool $isPaid = false, ?int $paymentModeId = null, bool $walkIn = false): array
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
        $billGroups = [];
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
            } elseif ($destination === 'bill') {
                $categoryId = (int) ($line['bill_category_id'] ?? 0);
                $key = (string) $categoryId;
                $billGroups[$key]['category_id'] = $categoryId;
                $billGroups[$key]['amount'] = ($billGroups[$key]['amount'] ?? 0) + $total;
                $billGroups[$key]['lines'][] = $line;
            }
        }

        $result = ['capture_id' => $captureId, 'purchase_order_draft_id' => null, 'expense_draft_ids' => [], 'bill_draft_ids' => [], 'warnings' => []];
        $paymentTotal = $isPaid && $capture['payment_total'] !== null ? (float) $capture['payment_total'] : 0.0;
        $expenseTotal = array_sum(array_column($expenseGroups, 'amount'));
        // A previous run may have created the PO before an expense insert
        // failed. Reuse it so retrying only completes the missing side.
        $poId = !empty($capture['purchase_order_draft_id'])
            ? (string) $capture['purchase_order_draft_id']
            : $this->create_purchase_order_draft_from_capture($captureId, $isPaid && $paymentTotal > 0, $walkIn, $walkIn ? date('Y-m-d') : null);
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
            $this->copy_receipt_attachment_to_expense($captureId, $expenseId);
            if ($isPaid && $amount > 0) {
                $this->record_payment_allocation($capture, $group['destination'], 'expense_draft', (string) $expenseId, $amount, $paymentModeId);
            }
            $result['expense_draft_ids'][] = $expenseId;
        }

        foreach ($billGroups as $group) {
            $amount = round((float) $group['amount'], 2);
            $categoryId = (int) $group['category_id'];
            $category = $categoryId && $this->db->table_exists(db_prefix() . 'acc_bill_categories')
                ? $this->db->where('id', $categoryId)->where('active', 1)->get(db_prefix() . 'acc_bill_categories')->row()
                : null;
            if (!$category || empty($category->debit_account) || empty($category->credit_account)) {
                $result['warnings'][] = 'Bill lines need a valid Bill category with debit and credit accounts.';
                continue;
            }
            $names = array_map(static function ($line) { return $line['source_description']; }, $group['lines']);
            $this->db->insert(db_prefix() . 'expenses', [
                'category' => 0, 'amount' => $amount, 'tax' => 0, 'reference_no' => $capture['invoice_number'],
                'note' => implode(', ', $names), 'clientid' => 0, 'billable' => 0,
                'paymentmode' => null, 'date' => $capture['purchase_date'], 'date_paid' => null,
                'dateadded' => date('Y-m-d H:i:s'), 'addedfrom' => get_staff_user_id(),
                'vendor' => $capture['vendor_id'] ?: null, 'expense_name' => implode(', ', $names),
                'is_bill' => 1, 'is_draft' => 1, 'approved' => 0, 'status' => 0,
                'bill_category_id' => $categoryId,
            ]);
            $billId = (int) $this->db->insert_id();
            if (!$billId) continue;

            // Keep draft bills consistent with bills created through the
            // Accounting module: the selected Bill category must immediately
            // provide its debit and credit mappings, even before approval.
            $billAmount = number_format($amount, 2, '.', '');
            $this->db->insert_batch(db_prefix() . 'acc_bill_mappings', [
                [
                    'bill_id' => $billId, 'type' => 'debit',
                    'account' => (int) $category->debit_account,
                    'amount' => $billAmount, 'item_id' => 0, 'qty' => 0,
                    'cost' => 0, 'description' => '',
                ],
                [
                    'bill_id' => $billId, 'type' => 'credit',
                    'account' => (int) $category->credit_account,
                    'amount' => $billAmount, 'item_id' => 0, 'qty' => 0,
                    'cost' => 0, 'description' => '',
                ],
            ]);
            $this->copy_receipt_attachment_to_bill($captureId, $billId);
            $result['bill_draft_ids'][] = $billId;
        }

        $this->update_capture($captureId, [
            'status' => 'split_drafts',
            'expense_draft_ids' => json_encode($result['expense_draft_ids']),
            'bill_draft_ids' => json_encode($result['bill_draft_ids']),
        ]);
        if ($paymentTotal > 0) {
            $allocated = min($poTotal, max(0, $paymentTotal - $expenseTotal)) + min($expenseTotal, $paymentTotal);
            $rounding = round($paymentTotal - $allocated, 2);
            $paymentAdjustment = $capture['payment_adjustment_amount'] !== null
                ? round((float) $capture['payment_adjustment_amount'], 2)
                : $rounding;
            if (abs($paymentAdjustment) > 0.009) {
                $this->record_payment_allocation($capture, 'rounding', 'receipt', (string) $captureId, $paymentAdjustment, $paymentModeId, 'Payment adjustment; the receipt printed rounding amount is retained separately and is not overwritten.');
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

    private function copy_receipt_attachment_to_expense(int $captureId, int $expenseId): void
    {
        $table = db_prefix() . 'wa_expense_attachments';
        $receipt = $this->get_capture_receipt_blob($captureId);
        if (!$receipt || !$this->db->table_exists($table)) {
            return;
        }

        $exists = $this->db->where('expense_id', $expenseId)
            ->where('file_name', $receipt['receipt_file_name'] ?: 'receipt')
            ->count_all_results($table);
        if ($exists) {
            return;
        }

        $this->db->insert($table, [
            'id' => app_generate_hash(),
            'expense_id' => $expenseId,
            'file_name' => $receipt['receipt_file_name'] ?: 'receipt',
            'size_bytes' => strlen($receipt['receipt_blob']),
            'local_blob' => $receipt['receipt_blob'],
        ]);
    }

    private function copy_receipt_attachment_to_bill(int $captureId, int $billId): void
    {
        $table = db_prefix() . 'wa_bill_draft_attachments';
        $receipt = $this->get_capture_receipt_blob($captureId);
        if (!$receipt || !$this->db->table_exists($table)) return;
        $this->db->insert($table, [
            'id' => app_generate_hash(), 'draft_id' => (string) $billId,
            'file_name' => $receipt['receipt_file_name'] ?: 'receipt',
            'size_bytes' => strlen($receipt['receipt_blob']), 'uploaded_by' => get_staff_user_id(),
            'local_blob' => $receipt['receipt_blob'],
        ]);
    }

    private function replace_expense_attachment(int $expenseId, string $fileName, string $blob): bool
    {
        $table = db_prefix() . 'wa_expense_attachments';
        if (!$this->db->table_exists($table)) return false;
        $this->db->where('expense_id', $expenseId)->where('file_name', $fileName)->delete($table);
        $this->db->insert($table, [
            'id' => app_generate_hash(), 'expense_id' => $expenseId,
            'file_name' => $fileName, 'size_bytes' => strlen($blob), 'local_blob' => $blob,
        ]);
        return $this->db->affected_rows() > 0;
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
        $data = $this->normalize_purchase_line_units($data);
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
        $data = $this->normalize_purchase_line_units($data, $existing);
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

    /**
     * Normalize MCP PO terminology while retaining the capture table's
     * existing column names. quantity is the purchased batch count and
     * conversion_factor is the stock-unit count per purchased batch.
     */
    private function normalize_purchase_line_units(array $data, array $existing = []): array
    {
        if (!array_key_exists('quantity', $data) && array_key_exists('batch_quantity', $data)) {
            $data['quantity'] = $data['batch_quantity'];
        }
        if (!array_key_exists('conversion_factor', $data) && array_key_exists('units_per_batch', $data)) {
            $data['conversion_factor'] = $data['units_per_batch'];
        }

        $quantity = array_key_exists('quantity', $data) ? $data['quantity'] : ($existing['quantity'] ?? null);
        $factor = array_key_exists('conversion_factor', $data) ? $data['conversion_factor'] : ($existing['conversion_factor'] ?? null);
        if ($quantity !== null && $factor !== null && is_numeric($quantity) && is_numeric($factor)) {
            $data['base_quantity'] = round((float) $quantity * (float) $factor, 6);
        }

        unset($data['batch_quantity'], $data['units_per_batch']);
        return $data;
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
