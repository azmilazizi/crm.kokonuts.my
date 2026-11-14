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

        if (isset($data['raw_extraction']) && is_array($data['raw_extraction'])) {
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
