<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Loyalty_model extends App_Model
{
    const POINTS_RATE    = 0.10; // 10% of spend = points
    const MIN_REDEEM     = 1.00; // minimum points to redeem in one transaction
    const POINTS_PER_MYR = 1.00; // 1 point = RM 1.00

    // =========================================================================
    // Customers
    // =========================================================================

    public function get_customers($search = '', $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;

        $this->db->select('lc.*, c.company as client_name')
            ->from(db_prefix() . 'pos_loyalty_customers lc')
            ->join(db_prefix() . 'clients c', 'c.userid = lc.client_id', 'left')
            ->order_by('lc.registered_at', 'DESC');

        if ($search !== '') {
            $s = $this->db->escape_like_str($search);
            $this->db->group_start()
                ->like('lc.name', $s, 'both')
                ->or_like('lc.phone', $s, 'both')
                ->or_like('lc.email', $s, 'both')
                ->group_end();
        }

        $rows = $this->db->limit((int)$per_page, $offset)->get()->result_array();

        foreach ($rows as &$row) {
            $row['tier'] = $this->get_tier((float)$row['total_points']);
        }

        return $rows;
    }

    public function count_customers($search = '')
    {
        $this->db->from(db_prefix() . 'pos_loyalty_customers lc');

        if ($search !== '') {
            $s = $this->db->escape_like_str($search);
            $this->db->group_start()
                ->like('lc.name', $s, 'both')
                ->or_like('lc.phone', $s, 'both')
                ->or_like('lc.email', $s, 'both')
                ->group_end();
        }

        return (int)$this->db->count_all_results();
    }

    public function get_customer($id)
    {
        $row = $this->db->select('lc.*, c.company as client_name')
            ->from(db_prefix() . 'pos_loyalty_customers lc')
            ->join(db_prefix() . 'clients c', 'c.userid = lc.client_id', 'left')
            ->where('lc.id', (int)$id)
            ->get()->row_array();

        if (!$row) return null;

        $row['tier'] = $this->get_tier((float)$row['total_points']);
        return $row;
    }

    public function get_customer_by_qr($token)
    {
        $row = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['qr_token' => $token])->row_array();
        if (!$row) return null;
        $row['tier'] = $this->get_tier((float)$row['total_points']);
        return $row;
    }

    // =========================================================================
    // Transactions
    // =========================================================================

    public function get_customer_transactions($customer_id, $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;

        return $this->db->select('lt.*, r.receipt_number')
            ->from(db_prefix() . 'pos_loyalty_transactions lt')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = lt.receipt_id', 'left')
            ->where('lt.customer_id', (int)$customer_id)
            ->order_by('lt.created_at', 'DESC')
            ->limit((int)$per_page, $offset)
            ->get()->result_array();
    }

    public function count_customer_transactions($customer_id)
    {
        return (int)$this->db->where('customer_id', (int)$customer_id)
            ->count_all_results(db_prefix() . 'pos_loyalty_transactions');
    }

    public function get_all_transactions($filters = [], $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;

        $this->db->select('lt.*, lc.name as customer_name, lc.phone as customer_phone, r.receipt_number')
            ->from(db_prefix() . 'pos_loyalty_transactions lt')
            ->join(db_prefix() . 'pos_loyalty_customers lc', 'lc.id = lt.customer_id', 'left')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = lt.receipt_id', 'left')
            ->order_by('lt.created_at', 'DESC');

        $this->_apply_transaction_filters($filters);

        return $this->db->limit((int)$per_page, $offset)->get()->result_array();
    }

    public function count_all_transactions($filters = [])
    {
        $this->db->from(db_prefix() . 'pos_loyalty_transactions lt')
            ->join(db_prefix() . 'pos_loyalty_customers lc', 'lc.id = lt.customer_id', 'left')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = lt.receipt_id', 'left');

        $this->_apply_transaction_filters($filters);

        return (int)$this->db->count_all_results();
    }

    private function _apply_transaction_filters($filters)
    {
        if (!empty($filters['type'])) {
            $this->db->where('lt.type', $filters['type']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(lt.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(lt.created_at) <=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $s = $this->db->escape_like_str($filters['search']);
            $this->db->group_start()
                ->like('lc.name', $s, 'both')
                ->or_like('lc.phone', $s, 'both')
                ->or_like('r.receipt_number', $s, 'both')
                ->group_end();
        }
    }

    // =========================================================================
    // Points Logic
    // =========================================================================

    public function earn_points($customer_id, $receipt_id, $amount_spent)
    {
        $points = round((float)$amount_spent * self::POINTS_RATE, 2);

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id' => (int)$customer_id,
            'receipt_id'  => $receipt_id ? (int)$receipt_id : null,
            'type'        => 'earn',
            'points'      => $points,
            'description' => 'Earned from purchase',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->set('total_points', 'total_points + ' . (float)$points, false)
            ->set('total_spent', 'total_spent + ' . (float)$amount_spent, false)
            ->set('last_visit', date('Y-m-d H:i:s'))
            ->where('id', (int)$customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');

        $this->db->trans_complete();
        return $this->db->trans_status() !== false ? $points : false;
    }

    public function redeem_points($customer_id, $receipt_id, $points)
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => (int)$customer_id])->row_array();
        if (!$lc || (float)$lc['total_points'] < (float)$points) return false;
        if ((float)$points < self::MIN_REDEEM) return false;

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id' => (int)$customer_id,
            'receipt_id'  => $receipt_id ? (int)$receipt_id : null,
            'type'        => 'redeem',
            'points'      => (float)$points,
            'description' => 'Redeemed at POS',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->set('total_points', 'total_points - ' . (float)$points, false)
            ->where('id', (int)$customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) return false;

        return [
            'points_redeemed'          => (float)$points,
            'points_value_in_currency' => round((float)$points * self::POINTS_PER_MYR, 2),
        ];
    }

    public function adjust_points($customer_id, $points, $description = 'Manual adjustment')
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => (int)$customer_id])->row_array();
        if (!$lc) return false;

        $new_total = max(0, (float)$lc['total_points'] + (float)$points);

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id' => (int)$customer_id,
            'receipt_id'  => null,
            'type'        => 'adjust',
            'points'      => (float)$points,
            'description' => $description ?: 'Manual adjustment',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->set('total_points', $new_total)
            ->where('id', (int)$customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');

        $this->db->trans_complete();
        return $this->db->trans_status() !== false;
    }

    public function get_balance($customer_id)
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => (int)$customer_id])->row_array();
        if (!$lc) return null;
        $lc['tier'] = $this->get_tier((float)$lc['total_points']);
        return $lc;
    }

    // =========================================================================
    // Tiers (reads ma_point_triggers — shared with the MA module)
    // =========================================================================

    public function get_tier($points)
    {
        if (!$this->db->table_exists(db_prefix() . 'ma_point_triggers')) return null;

        $tiers = $this->db->order_by('minimum_number_of_points', 'DESC')
            ->get(db_prefix() . 'ma_point_triggers')->result_array();

        foreach ($tiers as $tier) {
            if ($points >= (float)$tier['minimum_number_of_points']) return $tier;
        }
        return null;
    }

    // =========================================================================
    // Stats
    // =========================================================================

    public function get_stats()
    {
        $total_members  = (int)$this->db->count_all(db_prefix() . 'pos_loyalty_customers');
        $total_points   = (float)($this->db->select('SUM(total_points) as s')->get(db_prefix() . 'pos_loyalty_customers')->row()->s ?? 0);
        $total_earned   = (float)($this->db->select('SUM(points) as s')->where('type', 'earn')->get(db_prefix() . 'pos_loyalty_transactions')->row()->s ?? 0);
        $total_redeemed = (float)($this->db->select('SUM(points) as s')->where('type', 'redeem')->get(db_prefix() . 'pos_loyalty_transactions')->row()->s ?? 0);

        return compact('total_members', 'total_points', 'total_earned', 'total_redeemed');
    }

    // =========================================================================
    // API Token Verification (reuses pos_api_tokens)
    // =========================================================================

    public function verify_api_token($token)
    {
        if (!$this->db->table_exists(db_prefix() . 'pos_api_tokens')) return false;

        $row = $this->db->select('id, name')
            ->where('token', $token)
            ->where('active', 1)
            ->get(db_prefix() . 'pos_api_tokens')
            ->row_array();

        return $row ?: false;
    }
}
