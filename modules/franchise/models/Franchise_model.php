<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Franchise_model extends App_Model
{
    // =========================================================================
    // Franchisees
    // =========================================================================

    public function get_franchisees($active_only = false)
    {
        $this->db->select('f.*,
                (SELECT COUNT(*) FROM `' . db_prefix() . 'warehouse` w WHERE w.franchisee_id = f.id AND w.display = 1) AS outlet_count')
            ->from(db_prefix() . 'franchise_franchisees f');
        if ($active_only) {
            $this->db->where('f.is_active', 1);
        }
        return $this->db->order_by('f.name', 'ASC')->get()->result_array();
    }

    public function get_franchisee($id)
    {
        return $this->db->get_where(db_prefix() . 'franchise_franchisees', ['id' => (int)$id])->row_array();
    }

    public function create_franchisee($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'franchise_franchisees', $data);
        return $this->db->insert_id();
    }

    public function update_franchisee($id, $data)
    {
        $this->db->where('id', (int)$id)->update(db_prefix() . 'franchise_franchisees', $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete_franchisee($id)
    {
        $id = (int)$id;
        $outlets = (int)$this->db->where('franchisee_id', $id)->count_all_results(db_prefix() . 'warehouse');
        if ($outlets > 0) {
            return false;
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'franchise_franchisees');
        return true;
    }

    // =========================================================================
    // Outlet Ownership
    // =========================================================================

    public function get_stores_with_franchisee()
    {
        return $this->db
            ->select('w.warehouse_id AS id, w.warehouse_name AS name, w.franchisee_id, f.name AS franchisee_name')
            ->from(db_prefix() . 'warehouse w')
            ->join(db_prefix() . 'franchise_franchisees f', 'f.id = w.franchisee_id', 'left')
            ->where('w.display', 1)
            ->order_by('w.warehouse_name', 'ASC')
            ->get()->result_array();
    }

    public function set_store_franchisee($warehouse_id, $franchisee_id)
    {
        $this->db->where('warehouse_id', (int)$warehouse_id)
            ->update(db_prefix() . 'warehouse', ['franchisee_id' => $franchisee_id ? (int)$franchisee_id : null]);
        return $this->db->affected_rows() >= 0;
    }

    private function _franchisee_outlet_ids($franchisee_id)
    {
        $rows = $this->db->select('warehouse_id')->where('franchisee_id', (int)$franchisee_id)
            ->get(db_prefix() . 'warehouse')->result_array();
        return array_map(function ($r) { return (int)$r['warehouse_id']; }, $rows);
    }

    // =========================================================================
    // Cashback Settlement
    // =========================================================================

    public function get_franchisee_outstanding($franchisee_id)
    {
        $outlet_ids = $this->_franchisee_outlet_ids($franchisee_id);
        if (empty($outlet_ids)) {
            return 0.0;
        }

        $pfx = db_prefix();
        $ids = implode(',', $outlet_ids);
        $row = $this->db->query("
            SELECT COALESCE(SUM(t.points), 0) AS s
            FROM `{$pfx}pos_loyalty_transactions` t
            INNER JOIN `{$pfx}pos_receipts` r ON r.id = t.receipt_id
            WHERE t.type = 'redeem'
              AND t.franchise_transfer_id IS NULL
              AND t.warehouse_id IN ({$ids})
              AND r.cancelled_at IS NULL
        ")->row_array();

        return (float)($row['s'] ?? 0);
    }

    public function get_franchisees_summary()
    {
        $franchisees = $this->get_franchisees();
        foreach ($franchisees as &$f) {
            $f['outstanding'] = $this->get_franchisee_outstanding($f['id']);
            $f['lifetime_transferred'] = (float)($this->db->select('SUM(amount) as s')
                ->where('franchisee_id', $f['id'])
                ->get(db_prefix() . 'franchise_transfers')->row()->s ?? 0);
        }
        return $franchisees;
    }

    public function record_franchise_transfer($franchisee_id, $data)
    {
        $franchisee_id = (int)$franchisee_id;
        $outlet_ids    = $this->_franchisee_outlet_ids($franchisee_id);
        if (empty($outlet_ids)) {
            return false;
        }

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'franchise_transfers', [
            'franchisee_id'  => $franchisee_id,
            'amount'         => (float)$data['amount'],
            'reference_no'   => $data['reference_no'] ?? null,
            'method'         => $data['method'] ?? null,
            'note'           => $data['note'] ?? null,
            'transferred_at' => $data['transferred_at'] ?? date('Y-m-d H:i:s'),
            'staff_id'       => $data['staff_id'] ?? null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        $transfer_id = $this->db->insert_id();

        $pfx = db_prefix();
        $ids = implode(',', $outlet_ids);
        $this->db->query("
            UPDATE `{$pfx}pos_loyalty_transactions` t
            INNER JOIN `{$pfx}pos_receipts` r ON r.id = t.receipt_id
            SET t.franchise_transfer_id = {$transfer_id}
            WHERE t.type = 'redeem'
              AND t.franchise_transfer_id IS NULL
              AND t.warehouse_id IN ({$ids})
              AND r.cancelled_at IS NULL
        ");

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return false;
        }
        return $transfer_id;
    }

    public function delete_franchise_transfer($id)
    {
        $id = (int)$id;
        $this->db->trans_start();
        $this->db->where('franchise_transfer_id', $id)
            ->update(db_prefix() . 'pos_loyalty_transactions', ['franchise_transfer_id' => null]);
        $this->db->where('id', $id)->delete(db_prefix() . 'franchise_transfers');
        $this->db->trans_complete();
        return $this->db->trans_status() !== false;
    }

    public function count_franchise_transfers($franchisee_id)
    {
        return (int)$this->db->where('franchisee_id', (int)$franchisee_id)
            ->count_all_results(db_prefix() . 'franchise_transfers');
    }

    public function get_franchise_transfers($franchisee_id, $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;
        return $this->db->select('t.*, s.firstname, s.lastname')
            ->from(db_prefix() . 'franchise_transfers t')
            ->join(db_prefix() . 'staff s', 's.staffid = t.staff_id', 'left')
            ->where('t.franchisee_id', (int)$franchisee_id)
            ->order_by('t.transferred_at', 'DESC')
            ->limit($per_page, $offset)
            ->get()->result_array();
    }

    public function get_franchisee_redeem_transactions($franchisee_id, $settled = null, $page = 1, $per_page = 20)
    {
        $outlet_ids = $this->_franchisee_outlet_ids($franchisee_id);
        if (empty($outlet_ids)) {
            return [];
        }
        $offset = ((int)$page - 1) * (int)$per_page;

        $this->db->select('t.*, r.receipt_number, w.warehouse_name, c.name AS customer_name, c.phone AS customer_phone')
            ->from(db_prefix() . 'pos_loyalty_transactions t')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = t.receipt_id', 'left')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = t.warehouse_id', 'left')
            ->join(db_prefix() . 'pos_loyalty_customers c', 'c.id = t.customer_id', 'left')
            ->where('t.type', 'redeem')
            ->where_in('t.warehouse_id', $outlet_ids)
            ->where('r.cancelled_at IS NULL');

        if ($settled === true) {
            $this->db->where('t.franchise_transfer_id IS NOT NULL');
        } elseif ($settled === false) {
            $this->db->where('t.franchise_transfer_id IS NULL');
        }

        return $this->db->order_by('t.created_at', 'DESC')->limit($per_page, $offset)->get()->result_array();
    }
}
