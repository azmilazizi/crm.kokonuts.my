<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Journal_entries_model extends App_Model
{
    /**
     * Fetch all journal entries or a single entry by id.
     *
     * @param int|null $id
     * @param array    $filters
     *
     * @return array|null
     */
    public function get($id = null, array $filters = [])
    {
        $table = db_prefix() . 'acc_journal_entries';

        $this->db->select($table . '.*, ' . $table . '.journal_date as entry_date');
        $this->db->from($table);

        if ($id !== null) {
            $this->db->where('id', (int) $id);
        }

        foreach ($filters as $column => $value) {
            $this->db->where($column, $value);
        }

        if ($id !== null) {
            return $this->db->get()->row_array();
        }

        $this->db->order_by('journal_date', 'desc');
        $this->db->order_by('id', 'desc');

        return $this->db->get()->result_array();
    }

    /**
     * Create a new journal entry record.
     *
     * @param array $data
     *
     * @return int Inserted id
     */
    public function create(array $data)
    {
        $table = db_prefix() . 'acc_journal_entries';

        if (isset($data['entry_date']) && !isset($data['journal_date'])) {
            $data['journal_date'] = $data['entry_date'];
        }

        unset($data['entry_date']);

        $timestamp         = date('Y-m-d H:i:s');
        $data['created_at'] = $timestamp;
        $data['updated_at'] = $timestamp;

        $this->db->insert($table, $data);

        return (int) $this->db->insert_id();
    }

    /**
     * Update an existing journal entry.
     *
     * @param int   $id
     * @param array $data
     *
     * @return bool
     */
    public function update_entry($id, array $data)
    {
        $table = db_prefix() . 'acc_journal_entries';

        if (isset($data['entry_date']) && !isset($data['journal_date'])) {
            $data['journal_date'] = $data['entry_date'];
        }

        unset($data['entry_date']);

        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', (int) $id);
        $this->db->update($table, $data);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Delete a journal entry by id.
     *
     * @param int $id
     *
     * @return bool
     */
    public function delete($id)
    {
        $table = db_prefix() . 'acc_journal_entries';

        $this->db->where('id', (int) $id);
        $this->db->delete($table);

        return $this->db->affected_rows() > 0;
    }
}
