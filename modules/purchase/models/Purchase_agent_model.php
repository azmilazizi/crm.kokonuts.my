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

        return $capture;
    }

    public function update_capture(int $captureId, array $data): bool
    {
        $allowed = [
            'source', 'source_reference', 'status', 'vendor_id', 'vendor_name',
            'warehouse_id', 'invoice_number', 'purchase_date', 'currency',
            'subtotal', 'tax', 'discount', 'shipping', 'grand_total',
            'receipt_hash', 'raw_extraction', 'notes', 'purchase_order_draft_id',
            'purchase_order_id', 'goods_receipt_id',
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
        $subtotal = 0.0;

        foreach ($capture['lines'] as $line) {
            $quantity = (float) ($line['quantity'] ?? 0);
            $lineTotal = $line['line_total'] !== null
                ? (float) $line['line_total']
                : round($quantity * (float) ($line['unit_price'] ?? 0), 2);
            $subtotal += $lineTotal;
            $items[] = [
                'id'                 => app_generate_hash(),
                'line_item_id'       => (string) $line['id'],
                'inventory_item_id'  => !empty($line['inventory_item_id']) ? (string) $line['inventory_item_id'] : null,
                'inventory_item_name'=> $line['inventory_item_id'] ? null : $line['source_description'],
                'description'        => $line['source_description'],
                'quantity'           => $quantity,
                'subtotal'           => $lineTotal,
                'total'              => $lineTotal,
                'discount'           => (float) ($line['discount'] ?? 0),
            ];
        }

        $draftData = [
            'id'             => $draftId,
            'vendor_id'      => $capture['vendor_id'] ?: null,
            'vendor_name'    => $capture['vendor_name'],
            'order_name'     => $capture['invoice_number'] ? 'Receipt ' . $capture['invoice_number'] : 'Receipt capture #' . $captureId,
            'order_date'     => $capture['purchase_date'],
            'is_paid'        => $isPaid ? 1 : 0,
            'items_subtotal' => $subtotal,
            'grand_total'    => $capture['grand_total'] !== null ? (float) $capture['grand_total'] : $subtotal,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        if (!$this->purchase_order_drafts_model->create_draft($draftData, $items, [])) {
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
        $allowed = [
            'source_line_number', 'source_description', 'source_barcode',
            'inventory_item_id', 'quantity', 'purchase_unit', 'base_quantity',
            'base_unit', 'conversion_factor', 'unit_price', 'tax', 'discount',
            'line_total', 'match_method', 'match_confidence', 'status',
            'matched_by', 'matched_at', 'review_reason',
        ];
        $data = array_intersect_key($data, array_flip($allowed));
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $lineId)->update(db_prefix() . $this->lines, $data);
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
