<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Purchase_order_drafts_model extends App_Model
{
    /** @var string */
    protected $draft_table = 'pur_order_drafts';

    /** @var string */
    protected $items_table = 'pur_order_draft_items';

    /** @var string */
    protected $payments_table = 'pur_order_draft_payments';

    /** @var string */
    protected $attachments_table = 'pur_order_draft_attachments';

    public function get_drafts(array $filters, int $limit, int $offset): array
    {
        $this->db->start_cache();
        $this->db->from(db_prefix() . $this->draft_table . ' AS d');

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $this->db->group_start();
            $this->db->like('d.order_name', $search);
            $this->db->or_like('d.order_number', $search);
            $this->db->or_like('d.vendor_name', $search);
            $this->db->or_like('d.vendor_code', $search);
            $this->db->group_end();
        }

        if (!empty($filters['vendor_id'])) {
            $this->db->where('d.vendor_id', $filters['vendor_id']);
        }

        if ($filters['is_paid'] !== null) {
            $this->db->where('d.is_paid', (bool) $filters['is_paid']);
        }

        if (!empty($filters['order_date_from'])) {
            $this->db->where('DATE(d.order_date) >=', to_sql_date($filters['order_date_from']));
        }

        if (!empty($filters['order_date_to'])) {
            $this->db->where('DATE(d.order_date) <=', to_sql_date($filters['order_date_to']));
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

        return ['total' => $total, 'drafts' => array_map([$this, 'cast_draft_numbers'], $drafts)];
    }

    public function get_draft_with_relations(string $id): ?array
    {
        $this->db->where('id', $id);
        $draft = $this->db->get(db_prefix() . $this->draft_table)->row_array();

        if (!$draft) {
            return null;
        }

        $draft = $this->cast_draft_numbers($draft);
        $draft['items'] = $this->get_items($id);
        $draft['payments'] = $this->get_payments($id);
        $draft['attachments'] = $this->get_attachments($id);

        return $draft;
    }

    public function create_draft(array $draftData, array $items, array $payments, array $attachments, array $pendingDeletionAttachments = [])
    {
        $this->db->trans_start();

        $this->db->insert(db_prefix() . $this->draft_table, $draftData);
        $this->store_items($draftData['id'], $items);
        $this->store_payments($draftData['id'], $payments);
        $this->store_attachments($draftData['id'], $attachments, false, $pendingDeletionAttachments);

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return false;
        }

        return $draftData['id'];
    }

    public function update_draft(string $id, array $draftData, array $items, array $payments, array $attachments, array $pendingDeletionAttachments = [])
    {
        $this->db->trans_start();

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . $this->draft_table, $draftData);

        $this->store_items($id, $items, true);
        $this->store_payments($id, $payments, true);
        $this->store_attachments($id, $attachments, true, $pendingDeletionAttachments);

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    public function add_attachment_from_upload(string $draftId, array $uploadedFile, ?string $uploadedBy = null)
    {
        if (empty($uploadedFile['tmp_name']) || !is_file($uploadedFile['tmp_name'])) {
            return null;
        }

        $blob = file_get_contents($uploadedFile['tmp_name']);
        if ($blob === false) {
            return null;
        }

        $record = [
            'id'                  => app_generate_hash(),
            'draft_id'            => $draftId,
            'file_name'           => $uploadedFile['name'] ?? null,
            'size_bytes'          => isset($uploadedFile['size']) ? (int) $uploadedFile['size'] : null,
            'uploaded_by'         => $uploadedBy,
            'is_existing'         => 0,
            'marked_for_deletion' => 0,
            'local_blob'          => $blob,
        ];

        $record = $this->persist_attachment_file($draftId, $record, null);

        $this->db->insert(db_prefix() . $this->attachments_table, $record);

        if ($this->db->affected_rows() <= 0) {
            $this->remove_attachment_file($draftId, $record['file_name'] ?? null);

            return null;
        }

        return $this->get_attachment($draftId, $record['id']);
    }

    public function delete_draft(string $id)
    {
        $this->db->trans_start();

        $attachments = $this->get_attachments($id);
        foreach ($attachments as $attachment) {
            $this->remove_attachment_file($id, $attachment['file_name'] ?? null);
        }

        $this->db->where('draft_id', $id);
        $this->db->delete(db_prefix() . $this->attachments_table);

        $this->db->where('draft_id', $id);
        $this->db->delete(db_prefix() . $this->items_table);

        $this->db->where('draft_id', $id);
        $this->db->delete(db_prefix() . $this->payments_table);

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . $this->draft_table);

        delete_dir($this->get_draft_upload_path($id));

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    protected function cast_draft_numbers(array $draft): array
    {
        $numericFields = [
            'discount_value',
            'shipping_fee',
            'items_subtotal',
            'total_discount',
            'grand_total',
        ];

        foreach ($numericFields as $field) {
            if (array_key_exists($field, $draft) && $draft[$field] !== null) {
                $draft[$field] = (float) $draft[$field];
            }
        }

        $draft['is_paid'] = isset($draft['is_paid']) ? (bool) $draft['is_paid'] : false;

        if (isset($draft['pending_deletion_attachments']) && is_string($draft['pending_deletion_attachments'])) {
            $decoded = json_decode($draft['pending_deletion_attachments'], true);
            $draft['pending_deletion_attachments'] = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        return $draft;
    }

    protected function get_items(string $draftId): array
    {
        $this->db->where('draft_id', $draftId);
        $items = $this->db->get(db_prefix() . $this->items_table)->result_array();

        foreach ($items as &$item) {
            $item['quantity'] = isset($item['quantity']) ? (float) $item['quantity'] : 0.0;
            $item['subtotal'] = isset($item['subtotal']) ? (float) $item['subtotal'] : 0.0;
            $item['discount'] = isset($item['discount']) ? (float) $item['discount'] : 0.0;
            if (isset($item['total'])) {
                $item['total'] = (float) $item['total'];
            }
        }
        unset($item);

        return $items;
    }

    protected function get_payments(string $draftId): array
    {
        $this->db->where('draft_id', $draftId);
        $payments = $this->db->get(db_prefix() . $this->payments_table)->result_array();

        foreach ($payments as &$payment) {
            $payment['amount'] = isset($payment['amount']) ? (float) $payment['amount'] : 0.0;
        }
        unset($payment);

        return $payments;
    }

    public function get_attachments(string $draftId): array
    {
        $this->db->where('draft_id', $draftId);
        $attachments = $this->db->get(db_prefix() . $this->attachments_table)->result_array();

        foreach ($attachments as &$attachment) {
            $attachment['is_existing'] = isset($attachment['is_existing']) ? (bool) $attachment['is_existing'] : false;
            $attachment['marked_for_deletion'] = isset($attachment['marked_for_deletion']) ? (bool) $attachment['marked_for_deletion'] : false;
            if (isset($attachment['size_bytes'])) {
                $attachment['size_bytes'] = (int) $attachment['size_bytes'];
            }

            $attachment['local_blob'] = $this->get_attachment_file_contents(
                $draftId,
                $attachment['file_name'] ?? null,
                $attachment['local_blob'] ?? null
            );
        }
        unset($attachment);

        return $attachments;
    }

    public function get_attachment(string $draftId, string $attachmentId): ?array
    {
        $this->db->where('draft_id', $draftId);
        $this->db->where('id', $attachmentId);

        $attachment = $this->db->get(db_prefix() . $this->attachments_table)->row_array();

        if (!$attachment) {
            return null;
        }

        $attachment['is_existing'] = isset($attachment['is_existing']) ? (bool) $attachment['is_existing'] : false;
        $attachment['marked_for_deletion'] = isset($attachment['marked_for_deletion']) ? (bool) $attachment['marked_for_deletion'] : false;
        if (isset($attachment['size_bytes'])) {
            $attachment['size_bytes'] = (int) $attachment['size_bytes'];
        }

        $attachment['local_blob'] = $this->get_attachment_file_contents(
            $draftId,
            $attachment['file_name'] ?? null,
            $attachment['local_blob'] ?? null
        );

        return $attachment;
    }

    public function delete_draft_attachments(string $draftId, array $attachmentIds): array
    {
        $attachmentIds = array_filter($attachmentIds, static function ($value) {
            return trim((string) $value) !== '';
        });

        if (empty($attachmentIds)) {
            return ['deleted' => [], 'missing' => []];
        }

        $this->db->where('draft_id', $draftId);
        $this->db->where_in('id', $attachmentIds);
        $existing = $this->db->get(db_prefix() . $this->attachments_table)->result_array();

        $existingById = [];
        foreach ($existing as $row) {
            $existingById[$row['id']] = $row;
        }

        $deleted = [];
        foreach ($existingById as $row) {
            $this->remove_attachment_file($draftId, $row['file_name'] ?? null);
            $deleted[] = $row['id'];
        }

        if (!empty($deleted)) {
            $this->db->where('draft_id', $draftId);
            $this->db->where_in('id', $deleted);
            $this->db->delete(db_prefix() . $this->attachments_table);
        }

        $missing = array_values(array_diff($attachmentIds, $deleted));

        return ['deleted' => $deleted, 'missing' => $missing];
    }

    protected function store_items(string $draftId, array $items, bool $replace = false): void
    {
        $existing = [];
        if ($replace) {
            $this->db->where('draft_id', $draftId);
            $existingItems = $this->db->get(db_prefix() . $this->items_table)->result_array();
            foreach ($existingItems as $row) {
                $existing[$row['id']] = $row;
            }
        }

        $seenIds = [];

        foreach ($items as $item) {
            $record              = $item;
            $record['id']        = $record['id'] ?? app_generate_hash();
            $record['draft_id']  = $draftId;
            $record['quantity']  = isset($record['quantity']) ? (float) $record['quantity'] : 0.0;
            $record['subtotal']  = isset($record['subtotal']) ? (float) $record['subtotal'] : 0.0;
            $record['discount']  = isset($record['discount']) ? (float) $record['discount'] : 0.0;
            if (isset($record['total'])) {
                $record['total'] = (float) $record['total'];
            }

            $seenIds[] = $record['id'];

            if (isset($existing[$record['id']])) {
                $this->db->where('id', $record['id']);
                $this->db->update(db_prefix() . $this->items_table, $record);
            } else {
                $this->db->insert(db_prefix() . $this->items_table, $record);
            }
        }

        if ($replace && !empty($existing)) {
            $this->db->where('draft_id', $draftId);
            if (!empty($seenIds)) {
                $this->db->where_not_in('id', $seenIds);
            }
            $this->db->delete(db_prefix() . $this->items_table);
        }
    }

    protected function store_payments(string $draftId, array $payments, bool $replace = false): void
    {
        $existing = [];
        if ($replace) {
            $this->db->where('draft_id', $draftId);
            $existingPayments = $this->db->get(db_prefix() . $this->payments_table)->result_array();
            foreach ($existingPayments as $row) {
                $existing[$row['id']] = $row;
            }
        }

        $seenIds = [];

        foreach ($payments as $payment) {
            $record              = $payment;
            $record['id']        = $record['id'] ?? app_generate_hash();
            $record['draft_id']  = $draftId;
            $record['amount']    = isset($record['amount']) ? (float) $record['amount'] : 0.0;

            $seenIds[] = $record['id'];

            if (isset($existing[$record['id']])) {
                $this->db->where('id', $record['id']);
                $this->db->update(db_prefix() . $this->payments_table, $record);
            } else {
                $this->db->insert(db_prefix() . $this->payments_table, $record);
            }
        }

        if ($replace && !empty($existing)) {
            $this->db->where('draft_id', $draftId);
            if (!empty($seenIds)) {
                $this->db->where_not_in('id', $seenIds);
            }
            $this->db->delete(db_prefix() . $this->payments_table);
        }
    }

    protected function store_attachments(string $draftId, array $attachments, bool $replace = false, array $pendingDeletionIds = []): void
    {
        $existing = [];
        if ($replace) {
            $this->db->where('draft_id', $draftId);
            $existingAttachments = $this->db->get(db_prefix() . $this->attachments_table)->result_array();
            foreach ($existingAttachments as $row) {
                $existing[$row['id']] = $row;
            }
        }

        $seenIds = [];

        foreach ($attachments as $attachment) {
            $record                          = $attachment;
            $record['id']                    = $record['id'] ?? app_generate_hash();
            $record['draft_id']              = $draftId;
            $record['is_existing']           = isset($record['is_existing']) ? (int) $record['is_existing'] : 0;
            $record['marked_for_deletion']   = isset($record['marked_for_deletion']) ? (int) $record['marked_for_deletion'] : 0;

            $record = $this->persist_attachment_file($draftId, $record, $existing[$record['id']] ?? null);

            if (in_array($record['id'], $pendingDeletionIds, true)) {
                $record['marked_for_deletion'] = 1;
            }

            $seenIds[] = $record['id'];

            if (isset($existing[$record['id']])) {
                $this->db->where('id', $record['id']);
                $this->db->update(db_prefix() . $this->attachments_table, $record);
            } else {
                $this->db->insert(db_prefix() . $this->attachments_table, $record);
            }
        }

        if (!empty($pendingDeletionIds)) {
            foreach ($pendingDeletionIds as $pendingId) {
                if (!in_array($pendingId, $seenIds, true) && isset($existing[$pendingId])) {
                    $this->db->where('id', $pendingId);
                    $this->db->update(db_prefix() . $this->attachments_table, ['marked_for_deletion' => 1]);

                    $this->remove_attachment_file($draftId, $existing[$pendingId]['file_name'] ?? null);
                }
            }
        }

        if ($replace && !empty($existing)) {
            $this->db->where('draft_id', $draftId);
            if (!empty($seenIds)) {
                $this->db->where_not_in('id', $seenIds);
            }
            $this->db->delete(db_prefix() . $this->attachments_table);

            foreach ($existing as $existingAttachment) {
                if (!in_array($existingAttachment['id'], $seenIds, true)) {
                    $this->remove_attachment_file($draftId, $existingAttachment['file_name'] ?? null);
                }
            }
        }
    }

    protected function get_attachment_file_contents(string $draftId, ?string $fileName, $databaseBlob)
    {
        if ($fileName) {
            $path = $this->get_draft_upload_path($draftId) . $fileName;
            if (is_file($path)) {
                return file_get_contents($path);
            }
        }

        return $databaseBlob;
    }

    protected function persist_attachment_file(string $draftId, array $record, ?array $existingRecord): array
    {
        if (isset($record['local_blob']) && $record['local_blob'] !== null) {
            $uploadPath = $this->get_draft_upload_path($draftId);
            _maybe_create_upload_path($uploadPath);

            $targetFileName = $record['file_name'] ?: 'attachment';

            if (!empty($existingRecord['file_name']) && $targetFileName === $existingRecord['file_name']) {
                $targetFileName = $existingRecord['file_name'];
            } else {
                if (!empty($existingRecord['file_name']) && $targetFileName !== $existingRecord['file_name']) {
                    $this->remove_attachment_file($draftId, $existingRecord['file_name']);
                }

                $targetFileName = unique_filename($uploadPath, $targetFileName);
            }

            $record['file_name'] = $targetFileName;
            $record['size_bytes'] = strlen($record['local_blob']);

            file_put_contents($uploadPath . $targetFileName, $record['local_blob']);
        }

        if (!empty($record['marked_for_deletion'])) {
            $this->remove_attachment_file($draftId, $record['file_name'] ?? null);
        }

        unset($record['local_blob']);

        return $record;
    }

    protected function remove_attachment_file(string $draftId, ?string $fileName): void
    {
        if (!$fileName) {
            return;
        }

        $path = $this->get_draft_upload_path($draftId) . $fileName;
        if (is_file($path)) {
            unlink($path);
        }
    }

    protected function get_draft_upload_path(string $draftId): string
    {
        return PURCHASE_MODULE_UPLOAD_FOLDER . '/pur_order_draft/' . $draftId . '/';
    }
}
