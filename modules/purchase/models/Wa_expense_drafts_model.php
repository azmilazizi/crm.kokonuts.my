<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wa_expense_drafts_model extends App_Model
{
    protected $table       = 'wa_expense_drafts';
    protected $attach_table = 'wa_expense_draft_attachments';

    public function get_drafts(array $filters, int $limit, int $offset): array
    {
        $this->db->start_cache();
        $this->db->from(db_prefix() . $this->table . ' AS d');

        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $this->db->group_start();
            $this->db->like('d.vendor_name', $s);
            $this->db->or_like('d.expense_name', $s);
            $this->db->group_end();
        }

        if (!empty($filters['from_date'])) {
            $this->db->where('DATE(d.date) >=', to_sql_date($filters['from_date']));
        }

        if (!empty($filters['to_date'])) {
            $this->db->where('DATE(d.date) <=', to_sql_date($filters['to_date']));
        }

        $this->db->stop_cache();
        $total = $this->db->count_all_results();

        $this->db->select('d.*');
        $this->db->order_by('d.created_at', 'DESC');
        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        $drafts = $this->db->get()->result_array();
        $this->db->flush_cache();
        $this->db->reset_query();

        return ['total' => $total, 'drafts' => $drafts];
    }

    public function get_draft(string $id): ?array
    {
        $row = $this->db->where('id', $id)->get(db_prefix() . $this->table)->row_array();
        if (!$row) {
            return null;
        }
        $row['amount']      = (float) $row['amount'];
        $row['category_id'] = $row['category_id'] ? (int) $row['category_id'] : null;
        $row['attachments'] = $this->get_attachments($id);
        return $row;
    }

    public function create(array $data, ?array $imageFile = null): ?string
    {
        $this->db->insert(db_prefix() . $this->table, $data);
        if ($this->db->affected_rows() <= 0) {
            return null;
        }
        if ($imageFile) {
            $this->add_attachment($data['id'], $imageFile);
        }
        return $data['id'];
    }

    public function update(string $id, array $data): bool
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . $this->table, $data);
        return $this->db->affected_rows() >= 0;
    }

    public function delete(string $id): bool
    {
        $this->_delete_attachment_files($id);
        $this->db->where('draft_id', $id)->delete(db_prefix() . $this->attach_table);
        $this->db->where('id', $id)->delete(db_prefix() . $this->table);
        return $this->db->affected_rows() >= 0;
    }

    public function add_attachment(string $draftId, array $uploadedFile): ?string
    {
        if (empty($uploadedFile['tmp_name']) || !is_file($uploadedFile['tmp_name'])) {
            return null;
        }
        $blob = file_get_contents($uploadedFile['tmp_name']);
        if ($blob === false) {
            return null;
        }
        $id = app_generate_hash();
        $this->db->insert(db_prefix() . $this->attach_table, [
            'id'        => $id,
            'draft_id'  => $draftId,
            'file_name' => $uploadedFile['name'] ?? 'receipt.jpg',
            'size_bytes' => strlen($blob),
            'local_blob' => $blob,
        ]);
        return $this->db->affected_rows() > 0 ? $id : null;
    }

    public function get_attachments(string $draftId): array
    {
        $rows = $this->db->where('draft_id', $draftId)->get(db_prefix() . $this->attach_table)->result_array();
        foreach ($rows as &$r) {
            unset($r['local_blob']);
        }
        return $rows;
    }

    public function get_attachment_blob(string $draftId, string $attachId): ?string
    {
        $row = $this->db
            ->select('local_blob')
            ->where('id', $attachId)
            ->where('draft_id', $draftId)
            ->get(db_prefix() . $this->attach_table)
            ->row_array();
        return $row ? $row['local_blob'] : null;
    }

    private function _delete_attachment_files(string $draftId): void
    {
        // Blobs are stored in DB only — nothing on disk to clean up
    }
}
