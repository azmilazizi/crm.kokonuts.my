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

    public function get_customers($search = '', $page = 1, $per_page = 20, $sort_by = 'registered_at', $sort_dir = 'DESC')
    {
        $offset = ((int)$page - 1) * (int)$per_page;

        $allowed_sort = [
            'name'          => 'lc.name',
            'total_points'  => 'lc.total_points',
            'total_spent'   => 'lc.total_spent',
            'last_visit'    => 'lc.last_visit',
            'registered_at' => 'lc.registered_at',
        ];
        $sort_col = $allowed_sort[$sort_by] ?? 'lc.registered_at';
        $sort_dir = strtoupper($sort_dir) === 'ASC' ? 'ASC' : 'DESC';

        $this->db->select('lc.*, c.company as client_name')
            ->from(db_prefix() . 'pos_loyalty_customers lc')
            ->join(db_prefix() . 'clients c', 'c.userid = lc.client_id', 'left')
            ->order_by($sort_col, $sort_dir);

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

    public function update_customer($id, $data)
    {
        $allowed = ['name', 'phone', 'email', 'birthday', 'address1', 'address2', 'city', 'state', 'postcode'];
        $row = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $row[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        if (empty($row)) return false;
        $this->db->where('id', (int)$id)->update(db_prefix() . 'pos_loyalty_customers', $row);
        return $this->db->affected_rows() >= 0;
    }

    public function delete_customer($id)
    {
        $id = (int)$id;
        $this->db->where('customer_id', $id)->delete(db_prefix() . 'pos_loyalty_transactions');
        $this->db->where('id', $id)->delete(db_prefix() . 'pos_loyalty_customers');
        return $this->db->affected_rows() > 0;
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

    public function earn_points($customer_id, $receipt_id, $amount_spent, $warehouse_id = null)
    {
        $points = round((float)$amount_spent * self::POINTS_RATE, 2);
        $lc = $this->db->select('total_points')->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => (int)$customer_id])->row_array();
        $balance_after = round((float)($lc['total_points'] ?? 0) + $points, 2);
        $tier = $this->get_tier($balance_after);

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id'  => (int)$customer_id,
            'receipt_id'   => $receipt_id ? (int)$receipt_id : null,
            'warehouse_id' => $warehouse_id ? (int)$warehouse_id : null,
            'type'         => 'earn',
            'points'       => $points,
            'balance_after' => $balance_after,
            'tier_name'    => $tier ? $tier['name'] : null,
            'description'  => 'Earned from purchase',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->db->set('total_points', 'total_points + ' . (float)$points, false)
            ->set('total_spent', 'total_spent + ' . (float)$amount_spent, false)
            ->set('total_transactions', 'total_transactions + 1', false)
            ->set('last_visit', date('Y-m-d H:i:s'))
            ->where('id', (int)$customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers');

        $this->db->trans_complete();
        return $this->db->trans_status() !== false ? $points : false;
    }

    public function redeem_points($customer_id, $receipt_id, $points, $warehouse_id = null)
    {
        $lc = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['id' => (int)$customer_id])->row_array();
        if (!$lc || (float)$lc['total_points'] < (float)$points) return false;
        if ((float)$points < self::MIN_REDEEM) return false;

        $balance_after = round((float)$lc['total_points'] - (float)$points, 2);
        $tier = $this->get_tier($balance_after);

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id'  => (int)$customer_id,
            'receipt_id'   => $receipt_id ? (int)$receipt_id : null,
            'warehouse_id' => $warehouse_id ? (int)$warehouse_id : null,
            'type'         => 'redeem',
            'points'       => (float)$points,
            'balance_after' => $balance_after,
            'tier_name'    => $tier ? $tier['name'] : null,
            'description'  => 'Redeemed at POS',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->db->set('total_points', 'total_points - ' . (float)$points, false)
            ->set('total_transactions', 'total_transactions + 1', false)
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
        $tier = $this->get_tier($new_total);

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
            'customer_id'  => (int)$customer_id,
            'receipt_id'   => null,
            'type'         => 'adjust',
            'points'       => (float)$points,
            'balance_after' => $new_total,
            'tier_name'    => $tier ? $tier['name'] : null,
            'description'  => $description ?: 'Manual adjustment',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->db->set('total_points', $new_total)
            ->set('total_transactions', 'total_transactions + 1', false)
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
    // Dashboard
    // =========================================================================

    public function get_period_stats($period = 'month')
    {
        list($date_from, $date_to) = $this->_period_range($period);

        $new_members = (int)$this->db->where('DATE(registered_at) >=', $date_from)
            ->where('DATE(registered_at) <=', $date_to)
            ->count_all_results(db_prefix() . 'pos_loyalty_customers');

        $points_earned = (float)($this->db->select('SUM(points) as s')
            ->where('type', 'earn')
            ->where('DATE(created_at) >=', $date_from)
            ->where('DATE(created_at) <=', $date_to)
            ->get(db_prefix() . 'pos_loyalty_transactions')->row()->s ?? 0);

        $points_redeemed = (float)($this->db->select('SUM(points) as s')
            ->where('type', 'redeem')
            ->where('DATE(created_at) >=', $date_from)
            ->where('DATE(created_at) <=', $date_to)
            ->get(db_prefix() . 'pos_loyalty_transactions')->row()->s ?? 0);

        $txn_count = (int)$this->db->where('DATE(created_at) >=', $date_from)
            ->where('DATE(created_at) <=', $date_to)
            ->count_all_results(db_prefix() . 'pos_loyalty_transactions');

        return compact('new_members', 'points_earned', 'points_redeemed', 'txn_count', 'date_from', 'date_to');
    }

    public function get_member_growth($months = 12)
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-{$i} months"));
            $month_end   = date('Y-m-t', strtotime("-{$i} months"));

            $count = (int)$this->db->where('DATE(registered_at) >=', $month_start)
                ->where('DATE(registered_at) <=', $month_end)
                ->count_all_results(db_prefix() . 'pos_loyalty_customers');

            $data[] = ['label' => date('M Y', strtotime($month_start)), 'count' => $count];
        }
        return $data;
    }

    public function get_transaction_trend($days = 30)
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));

            $earn = (float)($this->db->select('SUM(points) as s')
                ->where('type', 'earn')->where('DATE(created_at)', $d)
                ->get(db_prefix() . 'pos_loyalty_transactions')->row()->s ?? 0);

            $redeem = (float)($this->db->select('SUM(points) as s')
                ->where('type', 'redeem')->where('DATE(created_at)', $d)
                ->get(db_prefix() . 'pos_loyalty_transactions')->row()->s ?? 0);

            $data[] = ['label' => date('d/m', strtotime($d)), 'earn' => $earn, 'redeem' => $redeem];
        }
        return $data;
    }

    public function get_tier_distribution()
    {
        if (!$this->db->table_exists(db_prefix() . 'ma_point_triggers')) {
            return [];
        }

        $tiers = $this->db->order_by('minimum_number_of_points', 'ASC')
            ->get(db_prefix() . 'ma_point_triggers')->result_array();

        if (empty($tiers)) return [];

        $total_members = (int)$this->db->count_all(db_prefix() . 'pos_loyalty_customers');
        $distribution  = [];
        $assigned      = 0;

        foreach (array_reverse($tiers) as $tier) {
            $count = (int)$this->db->where('total_points >=', (float)$tier['minimum_number_of_points'])
                ->count_all_results(db_prefix() . 'pos_loyalty_customers');
            $tier_count   = max(0, $count - $assigned);
            $distribution[] = ['name' => $tier['name'], 'color' => $tier['color'] ?? '#888', 'count' => $tier_count];
            $assigned = $count;
        }

        $no_tier = $total_members - $assigned;
        if ($no_tier > 0) {
            $distribution[] = ['name' => 'Member', 'color' => '#aaaaaa', 'count' => $no_tier];
        }

        return $distribution;
    }

    public function get_recent_transactions($limit = 10)
    {
        return $this->db->select('lt.*, lc.name as customer_name, lc.phone as customer_phone, lc.id as customer_id, r.receipt_number')
            ->from(db_prefix() . 'pos_loyalty_transactions lt')
            ->join(db_prefix() . 'pos_loyalty_customers lc', 'lc.id = lt.customer_id', 'left')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = lt.receipt_id', 'left')
            ->order_by('lt.created_at', 'DESC')
            ->limit((int)$limit)
            ->get()->result_array();
    }

    private function _period_range($period)
    {
        switch ($period) {
            case 'today':
                return [date('Y-m-d'), date('Y-m-d')];
            case 'week':
                return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')];
            case 'year':
                return [date('Y-01-01'), date('Y-m-d')];
            case 'month':
            default:
                return [date('Y-m-01'), date('Y-m-d')];
        }
    }

    // =========================================================================
    // Import
    // =========================================================================

    public function import_member($data)
    {
        $phone    = trim($data['phone'] ?? '');
        $name     = trim($data['name'] ?? '');
        $email    = trim($data['email'] ?? '');
        $points   = max(0, (float)($data['points'] ?? 0));
        $spent    = max(0, (float)($data['total_spent'] ?? 0));
        $txn_count = max(0, (int)($data['total_transactions'] ?? 0));

        $birthday   = $this->_parse_import_date($data['birthday'] ?? '');
        $address1   = trim($data['address1'] ?? '');
        $address2   = trim($data['address2'] ?? '');
        $city       = trim($data['city'] ?? '');
        $state      = trim($data['state'] ?? '');
        $postcode   = trim($data['postcode'] ?? '');
        $last_visit = $this->_parse_import_date($data['last_purchase_date'] ?? '');

        if ($phone === '' && $name === '') {
            return ['error' => 'Name or phone is required'];
        }

        $existing = null;
        if ($phone !== '') {
            $existing = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['phone' => $phone])->row_array();
        }

        if ($existing) {
            $update = [];
            if ($name !== '')     $update['name']     = $name;
            if ($email !== '')    $update['email']    = $email;
            if ($birthday)        $update['birthday'] = $birthday;
            if ($address1 !== '') $update['address1'] = $address1;
            if ($address2 !== '') $update['address2'] = $address2;
            if ($city !== '')     $update['city']     = $city;
            if ($state !== '')    $update['state']    = $state;
            if ($postcode !== '') $update['postcode'] = $postcode;
            if ($last_visit)      $update['last_visit'] = $last_visit;
            if ($spent > 0)       $update['total_spent'] = $spent;
            if ($txn_count > 0)   $update['total_transactions'] = $txn_count;
            if (!empty($update)) {
                $this->db->where('id', $existing['id'])->update(db_prefix() . 'pos_loyalty_customers', $update);
            }
            if ($points > 0) {
                $this->adjust_points($existing['id'], $points, 'Imported balance');
            }
            return ['updated' => true, 'id' => $existing['id']];
        }

        $qr_token = bin2hex(random_bytes(16));
        while ($this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['qr_token' => $qr_token])->num_rows() > 0) {
            $qr_token = bin2hex(random_bytes(16));
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'pos_loyalty_customers', [
            'client_id'         => null,
            'phone'             => $phone,
            'name'              => $name,
            'email'             => $email,
            'birthday'          => $birthday ?: null,
            'address1'          => $address1 ?: null,
            'address2'          => $address2 ?: null,
            'city'              => $city ?: null,
            'state'             => $state ?: null,
            'postcode'          => $postcode ?: null,
            'total_points'      => $points,
            'total_spent'       => $spent,
            'total_transactions' => $txn_count,
            'qr_token'          => $qr_token,
            'registered_at'     => $now,
            'last_visit'        => $last_visit ?: ($spent > 0 ? $now : null),
        ]);

        $id = $this->db->insert_id();

        if ($points > 0 && $id) {
            $this->db->insert(db_prefix() . 'pos_loyalty_transactions', [
                'customer_id' => $id,
                'receipt_id'  => null,
                'type'        => 'adjust',
                'points'      => $points,
                'description' => 'Opening balance (imported)',
                'created_at'  => $now,
            ]);
        }

        return ['created' => true, 'id' => $id];
    }

    private function _parse_import_date($value)
    {
        $value = trim((string)$value);
        if ($value === '' || strtolower($value) === 'n/a') return null;

        // Excel serial date (numeric)
        if (is_numeric($value) && (float)$value > 1000) {
            $unix = ($value - 25569) * 86400;
            $d = date('Y-m-d', (int)$unix);
            return $d !== '1970-01-01' ? $d : null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y', 'Y/m/d'] as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $value);
            if ($dt && $dt->format($fmt) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    // =========================================================================
    // Cashback Claim (QR on receipt)
    // =========================================================================

    public function get_receipt_for_claim($token)
    {
        $pfx = db_prefix();
        $receipt = $this->db
            ->select('id, receipt_number, receipt_date, total_money, loyalty_customer_id, cashback_claimed_at, cancelled_at')
            ->from($pfx . 'pos_receipts')
            ->where('cashback_qr_token', $token)
            ->where('receipt_type', 'SALE')
            ->get()->row_array();

        if (!$receipt) return null;

        $expires_at = strtotime($receipt['receipt_date']) + (12 * 3600);

        if ($receipt['cancelled_at']) {
            $status = 'cancelled';
        } elseif ($receipt['cashback_claimed_at']) {
            $status = 'claimed';
        } elseif (time() > $expires_at) {
            $status = 'expired';
        } else {
            $status = 'valid';
        }

        return [
            'receipt_number' => $receipt['receipt_number'],
            'receipt_date'   => $receipt['receipt_date'],
            'total_money'    => (float)$receipt['total_money'],
            'points_to_earn' => round((float)$receipt['total_money'] * self::POINTS_RATE, 2),
            'expires_at'     => date('Y-m-d H:i:s', $expires_at),
            'status'         => $status,
            'claimed_at'     => $receipt['cashback_claimed_at'],
        ];
    }

    public function process_claim($token, $phone, $name = '', $birthday = '', $pdpa_consent = false)
    {
        $pfx = db_prefix();

        $receipt = $this->db
            ->select('id, receipt_number, receipt_date, total_money, loyalty_customer_id, cashback_claimed_at, cancelled_at, warehouse_id')
            ->from($pfx . 'pos_receipts')
            ->where('cashback_qr_token', $token)
            ->where('receipt_type', 'SALE')
            ->get()->row_array();

        if (!$receipt)                       return ['error' => 'Receipt not found',         'code' => 404];
        if ($receipt['cancelled_at'])        return ['error' => 'Receipt is cancelled',      'code' => 400];
        if ($receipt['cashback_claimed_at']) return ['error' => 'Cashback already claimed',  'code' => 409];
        if (time() > strtotime($receipt['receipt_date']) + (12 * 3600))
                                             return ['error' => 'Claim link has expired',    'code' => 410];

        $customer = $this->db->get_where($pfx . 'pos_loyalty_customers', ['phone' => $phone])->row_array();

        if (!$customer) {
            // Not registered — name, birthday, and PDPA consent are all required
            $required_fields = [];
            if (empty($name))     $required_fields[] = 'name';
            if (empty($birthday)) $required_fields[] = 'birthday';

            if (!empty($required_fields) || !$pdpa_consent) {
                return [
                    'error'           => 'Registration information required',
                    'code'            => 422,
                    'status'          => 'needs_registration',
                    'is_registered'   => false,
                    'required_fields' => $required_fields,
                    'needs_consent'   => !$pdpa_consent,
                ];
            }

            $now = date('Y-m-d H:i:s');
            $this->db->insert($pfx . 'pos_loyalty_customers', [
                'client_id'        => null,
                'phone'            => $phone,
                'name'             => $name,
                'birthday'         => $birthday,
                'email'            => '',
                'total_points'     => 0,
                'total_spent'      => 0,
                'qr_token'         => bin2hex(random_bytes(16)),
                'registered_at'    => $now,
                'last_visit'       => $now,
                'pdpa_consent'     => 1,
                'pdpa_consented_at' => $now,
            ]);
            $customer_id = $this->db->insert_id();
        } else {
            $customer_id = $customer['id'];
            $has_consent = !empty($customer['pdpa_consented_at']);

            // Determine which data fields are still missing
            $missing = [];
            if (empty($customer['name']))     $missing[] = 'name';
            if (empty($customer['birthday'])) $missing[] = 'birthday';

            $still_missing = [];
            if (in_array('name', $missing)     && empty($name))     $still_missing[] = 'name';
            if (in_array('birthday', $missing) && empty($birthday)) $still_missing[] = 'birthday';

            $needs_consent = !$has_consent && !$pdpa_consent;

            if (!empty($still_missing) || $needs_consent) {
                return [
                    'error'           => !empty($still_missing) ? 'Additional information required' : 'Consent required',
                    'code'            => 422,
                    'status'          => !empty($still_missing) ? 'needs_info' : 'needs_consent',
                    'is_registered'   => true,
                    'required_fields' => $still_missing,
                    'needs_consent'   => $needs_consent,
                ];
            }

            // Apply any updates (fill missing fields, record consent if new)
            $update = [];
            if (in_array('name', $missing)     && !empty($name))     $update['name']     = $name;
            if (in_array('birthday', $missing) && !empty($birthday)) $update['birthday'] = $birthday;
            if (!$has_consent && $pdpa_consent) {
                $update['pdpa_consent']      = 1;
                $update['pdpa_consented_at'] = date('Y-m-d H:i:s');
            }
            if (!empty($update)) {
                $this->db->where('id', $customer_id)->update($pfx . 'pos_loyalty_customers', $update);
            }
        }

        $points = round((float)$receipt['total_money'] * self::POINTS_RATE, 2);
        $now    = date('Y-m-d H:i:s');

        $current = $this->db->select('total_points')->get_where($pfx . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();
        $balance_after = round((float)($current['total_points'] ?? 0) + $points, 2);
        $tier = $this->get_tier($balance_after);

        $this->db->trans_start();

        $this->db->insert($pfx . 'pos_loyalty_transactions', [
            'customer_id'  => $customer_id,
            'receipt_id'   => $receipt['id'],
            'warehouse_id' => $receipt['warehouse_id'] ? (int)$receipt['warehouse_id'] : null,
            'type'         => 'earn',
            'points'       => $points,
            'balance_after' => $balance_after,
            'tier_name'    => $tier ? $tier['name'] : null,
            'description'  => 'Cashback from receipt ' . $receipt['receipt_number'],
            'created_at'   => $now,
        ]);

        $this->db->set('total_points', 'total_points + ' . (float)$points, false)
            ->set('total_spent', 'total_spent + ' . (float)$receipt['total_money'], false)
            ->set('last_visit', $now)
            ->where('id', $customer_id)
            ->update($pfx . 'pos_loyalty_customers');

        $this->db->where('id', $receipt['id'])->update($pfx . 'pos_receipts', [
            'cashback_claimed_at' => $now,
            'loyalty_customer_id' => $customer_id,
        ]);

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) return ['error' => 'Failed to process claim', 'code' => 500];

        $updated = $this->db->get_where($pfx . 'pos_loyalty_customers', ['id' => $customer_id])->row_array();

        $requires_review = array_key_exists('google_review_done', $updated) && !$updated['google_review_done'];
        if ($requires_review && empty($updated['google_review_prompted_at'])) {
            $this->db->where('id', $customer_id)
                ->update($pfx . 'pos_loyalty_customers', ['google_review_prompted_at' => date('Y-m-d H:i:s')]);
        }

        return [
            'points_earned'   => $points,
            'total_points'    => (float)$updated['total_points'],
            'customer_name'   => $updated['name'],
            'customer_phone'  => $updated['phone'],
            'requires_review' => $requires_review,
        ];
    }

    // =========================================================================
    // Member Account Auth
    // =========================================================================

    public function create_member($data)
    {
        $phone = trim($data['phone'] ?? '');
        $name  = trim($data['name'] ?? '');

        if (empty($phone) || empty($name)) return ['error' => 'name and phone are required'];

        $existing = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['phone' => $phone])->row_array();
        if ($existing) {
            if (!empty($existing['password_hash'])) {
                return ['error' => 'An account with this phone number already exists. Please log in.', 'code' => 409];
            }
            // Existing cashback record with no password — claim it
            $update = ['account_status' => 'active'];
            if (!empty($data['name']))    $update['name']    = $name;
            if (!empty($data['email']))   $update['email']   = trim($data['email']);
            if (!empty($data['birthday'])) $update['birthday'] = $data['birthday'];
            if (!empty($data['pdpa_consent'])) $update['pdpa_consented_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $existing['id'])->update(db_prefix() . 'pos_loyalty_customers', $update);
            return ['id' => $existing['id'], 'claimed' => true];
        }

        $insert = [
            'name'            => $name,
            'phone'           => $phone,
            'email'           => trim($data['email'] ?? ''),
            'birthday'        => $data['birthday'] ?? null,
            'address1'        => trim($data['address1'] ?? ''),
            'address2'        => trim($data['address2'] ?? ''),
            'city'            => trim($data['city'] ?? ''),
            'state'           => trim($data['state'] ?? ''),
            'postcode'        => trim($data['postcode'] ?? ''),
            'total_points'    => 0,
            'total_spent'     => 0,
            'account_status'  => 'active',
            'registered_at'   => date('Y-m-d H:i:s'),
        ];
        if (!empty($data['pdpa_consent'])) {
            $insert['pdpa_consented_at'] = date('Y-m-d H:i:s');
        }
        $this->db->insert(db_prefix() . 'pos_loyalty_customers', $insert);
        $id = $this->db->insert_id();
        return $id ? ['id' => $id, 'claimed' => false] : ['error' => 'Failed to create account', 'code' => 500];
    }

    public function set_member_password($customer_id, $plain_password)
    {
        $hash = password_hash($plain_password, PASSWORD_BCRYPT);
        $this->db->where('id', (int)$customer_id)
            ->update(db_prefix() . 'pos_loyalty_customers', [
                'password_hash'  => $hash,
                'account_status' => 'active',
            ]);
        return $this->db->affected_rows() >= 0;
    }

    public function authenticate_member($phone, $plain_password)
    {
        $row = $this->db->get_where(db_prefix() . 'pos_loyalty_customers', ['phone' => $phone])->row_array();
        if (!$row || empty($row['password_hash'])) return false;
        if ($row['account_status'] !== 'active') return false;
        if (!password_verify($plain_password, $row['password_hash'])) return false;
        return $row;
    }

    public function create_member_session($customer_id)
    {
        $token      = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->db->insert(db_prefix() . 'pos_loyalty_member_sessions', [
            'customer_id' => (int)$customer_id,
            'token'       => $token,
            'expires_at'  => $expires_at,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id() ? $token : false;
    }

    public function verify_member_session($token)
    {
        if (empty($token)) return false;

        $session = $this->db->select('s.customer_id')
            ->from(db_prefix() . 'pos_loyalty_member_sessions s')
            ->join(db_prefix() . 'pos_loyalty_customers lc', 'lc.id = s.customer_id')
            ->where('s.token', $token)
            ->where('s.expires_at >', date('Y-m-d H:i:s'))
            ->where('lc.account_status', 'active')
            ->get()->row_array();

        return $session ? (int)$session['customer_id'] : false;
    }

    public function revoke_member_session($token)
    {
        $this->db->where('token', $token)->delete(db_prefix() . 'pos_loyalty_member_sessions');
    }

    public function revoke_all_member_sessions($customer_id)
    {
        $this->db->where('customer_id', (int)$customer_id)
            ->delete(db_prefix() . 'pos_loyalty_member_sessions');
    }

    // =========================================================================
    // Promotions
    // =========================================================================

    public function get_promotions($active_only = false, $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;
        $this->db->from(db_prefix() . 'pos_loyalty_promotions')->order_by('created_at', 'DESC');

        if ($active_only) {
            $today = date('Y-m-d');
            $this->db->where('is_active', 1)
                ->group_start()
                    ->where('start_date IS NULL', null, false)
                    ->or_where('start_date <=', $today)
                ->group_end()
                ->group_start()
                    ->where('end_date IS NULL', null, false)
                    ->or_where('end_date >=', $today)
                ->group_end();
        }

        return $this->db->limit((int)$per_page, $offset)->get()->result_array();
    }

    public function count_promotions($active_only = false)
    {
        $this->db->from(db_prefix() . 'pos_loyalty_promotions');
        if ($active_only) {
            $today = date('Y-m-d');
            $this->db->where('is_active', 1)
                ->group_start()
                    ->where('start_date IS NULL', null, false)
                    ->or_where('start_date <=', $today)
                ->group_end()
                ->group_start()
                    ->where('end_date IS NULL', null, false)
                    ->or_where('end_date >=', $today)
                ->group_end();
        }
        return (int)$this->db->count_all_results();
    }

    public function get_promotion($id)
    {
        return $this->db->get_where(db_prefix() . 'pos_loyalty_promotions', ['id' => (int)$id])->row_array() ?: null;
    }

    public function clone_promotion($id)
    {
        $promo = $this->get_promotion((int)$id);
        if (!$promo) return false;

        unset($promo['id'], $promo['created_at'], $promo['notified_at']);
        $promo['title']         = $promo['title'] . ' (Copy)';
        $promo['notify_status'] = 'pending';
        $promo['is_active']     = 0;
        $promo['voucher_id']    = null;

        $this->db->insert(db_prefix() . 'pos_loyalty_promotions', array_merge($promo, ['created_at' => date('Y-m-d H:i:s')]));
        return $this->db->insert_id() ?: false;
    }

    public function create_promotion($data)
    {
        $allowed = [
            'title', 'description', 'image_url', 'type',
            'start_date', 'end_date', 'target_tier', 'is_active',
            'trigger_type', 'target', 'target_customer_id',
            'notify_push', 'notify_sms', 'notify_days_before', 'notify_status',
            'signup_recurrence', 'stale_days', 'birthday_start_date',
            'birthday_inactive_filter', 'birthday_inactive_days', 'signup_days_window',
        ];
        $row = ['created_at' => date('Y-m-d H:i:s')];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $row[$f] = $data[$f] !== '' ? $data[$f] : null;
            }
        }
        $this->db->insert(db_prefix() . 'pos_loyalty_promotions', $row);
        return $this->db->insert_id();
    }

    public function update_promotion($id, $data)
    {
        $allowed = [
            'title', 'description', 'image_url', 'type',
            'start_date', 'end_date', 'target_tier', 'is_active',
            'trigger_type', 'target', 'target_customer_id',
            'notify_push', 'notify_sms', 'notify_days_before', 'notify_status',
            'signup_recurrence', 'stale_days', 'birthday_start_date',
            'birthday_inactive_filter', 'birthday_inactive_days', 'signup_days_window',
            'voucher_id',
        ];
        $row = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $row[$f] = $data[$f] !== '' ? $data[$f] : null;
            }
        }
        if (empty($row)) return false;
        $this->db->where('id', (int)$id)->update(db_prefix() . 'pos_loyalty_promotions', $row);
        return $this->db->affected_rows() >= 0;
    }

    public function delete_promotion($id)
    {
        $this->db->where('id', (int)$id)->delete(db_prefix() . 'pos_loyalty_promotions');
        return $this->db->affected_rows() > 0;
    }

    /**
     * Resolve blast recipients for a promotion.
     * Returns array of rows with id, name, phone, birthday, total_points for variable substitution.
     * For birthday/signup_freebies: filters by timing window based on notify_days_before.
     * For stale_points: filters by last-transaction date vs stale_days threshold.
     */
    public function get_blast_recipients($promo)
    {
        $pfx     = db_prefix() . 'pos_loyalty_customers';
        $trigger = $promo['trigger_type'] ?? 'standard';
        $target  = $promo['target'] ?? 'all';
        $tier    = $promo['target_tier'] ?? '';

        if (in_array($trigger, ['birthday', 'signup_freebies'])) {
            $field    = ($trigger === 'birthday') ? 'birthday' : 'created_at';
            $days_raw = trim($promo['notify_days_before'] ?? '0');
            $values   = array_unique(array_filter(array_map('trim', explode(',', $days_raw)), 'strlen'));
            if (empty($values)) $values = ['0'];

            $conditions = [];
            foreach ($values as $dv) {
                if ($dv === 'month_start') {
                    $conditions[] = "(DAY(NOW()) = 1 AND MONTH(`{$field}`) = MONTH(NOW()))";
                } else {
                    $mmdd = date('m-d', strtotime("+{$dv} days"));
                    $conditions[] = "DATE_FORMAT(`{$field}`, '%m-%d') = '{$mmdd}'";
                }
            }

            $where = '(' . implode(' OR ', $conditions) . ')';
            $query = "SELECT id, name, phone, birthday, created_at, total_points FROM `{$pfx}`
                      WHERE {$where} AND `phone` != ''";

            // Birthday: optional campaign start date filter
            if ($trigger === 'birthday' && !empty($promo['birthday_start_date'])) {
                $safe_start = $this->db->escape($promo['birthday_start_date']);
                $query .= " AND DATE_FORMAT(`birthday`, CONCAT(YEAR(NOW()), '-%m-%d')) >= {$safe_start}";
            }

            // Birthday: inactive-only filter (FR-05.11)
            if ($trigger === 'birthday' && !empty($promo['birthday_inactive_filter'])) {
                $inactive_days = max(1, (int)($promo['birthday_inactive_days'] ?? 90));
                $cutoff        = $this->db->escape(date('Y-m-d', strtotime("-{$inactive_days} days")));
                $txn_table     = db_prefix() . 'pos_loyalty_transactions';
                $query .= " AND (
                    NOT EXISTS (SELECT 1 FROM `{$txn_table}` t WHERE t.customer_id = `{$pfx}`.id)
                    OR (SELECT MAX(t.created_at) FROM `{$txn_table}` t WHERE t.customer_id = `{$pfx}`.id) < {$cutoff}
                )";
            }

            // Sign-up freebies: only within first N days of signup (FR-06.8)
            if ($trigger === 'signup_freebies' && !empty($promo['signup_days_window'])) {
                $win = max(1, (int)$promo['signup_days_window']);
                $query .= " AND `{$pfx}`.`created_at` >= DATE_SUB(NOW(), INTERVAL {$win} DAY)";
            }

            if ($tier !== '') {
                $safe_tier = $this->db->escape($tier);
                $query .= " AND `tier` = {$safe_tier}";
            }

            $rows = $this->db->query($query)->result_array();
            // Deduplicate by id (a member can match multiple timing windows)
            $seen = [];
            $out  = [];
            foreach ($rows as $r) {
                if (!isset($seen[$r['id']])) {
                    $seen[$r['id']] = true;
                    $out[] = $r;
                }
            }
            return $out;
        }

        if ($trigger === 'stale_points') {
            $stale_days = max(1, (int)($promo['stale_days'] ?? 90));
            $cutoff     = $this->db->escape(date('Y-m-d', strtotime("-{$stale_days} days")));
            $txn_table  = db_prefix() . 'pos_loyalty_transactions';

            $query = "SELECT lc.id, lc.name, lc.phone, lc.birthday, lc.total_points
                      FROM `{$pfx}` lc
                      WHERE lc.phone != ''
                        AND (
                            NOT EXISTS (SELECT 1 FROM `{$txn_table}` t WHERE t.customer_id = lc.id)
                            OR (SELECT MAX(t.created_at) FROM `{$txn_table}` t WHERE t.customer_id = lc.id) < {$cutoff}
                        )";

            if ($tier !== '') {
                $safe_tier = $this->db->escape($tier);
                $query .= " AND lc.`tier` = {$safe_tier}";
            }

            return $this->db->query($query)->result_array();
        }

        // Standard / individual / tier / all
        $this->db->select('id, name, phone, birthday, total_points');

        if ($target === 'individual') {
            $ids_raw = trim($promo['target_customer_id'] ?? '');
            $ids     = array_filter(array_map('intval', explode(',', $ids_raw)));
            if (empty($ids)) return [];
            $this->db->where_in('id', $ids);
        } elseif ($target === 'tier' && $tier !== '') {
            if ($this->db->table_exists(db_prefix() . 'ma_point_triggers')) {
                $tier_row = $this->db->get_where(db_prefix() . 'ma_point_triggers', ['name' => $tier])->row_array();
                if ($tier_row) {
                    $this->db->where('total_points >=', (float)$tier_row['minimum_number_of_points']);
                }
            }
        }
        // 'all' — no extra filter

        return $this->db->get($pfx)->result_array();
    }

    public function mark_promo_notified($id, $recurring = false)
    {
        $this->db->where('id', (int)$id)->update(db_prefix() . 'pos_loyalty_promotions', [
            'notify_status' => $recurring ? 'recurring' : 'sent',
            'notified_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // Blast Log
    // =========================================================================

    public function create_blast_log($data)
    {
        $this->db->insert(db_prefix() . 'pos_loyalty_blast_log', array_merge($data, [
            'blasted_at' => date('Y-m-d H:i:s'),
        ]));
        return (int)$this->db->insert_id();
    }

    public function log_blast_members($blast_log_id, array $members)
    {
        if (empty($members)) return;
        $rows = [];
        foreach ($members as $m) {
            $rows[] = [
                'blast_log_id'   => (int)$blast_log_id,
                'customer_id'    => (int)$m['id'],
                'customer_name'  => $m['name']  ?? '',
                'customer_phone' => $m['phone'] ?? '',
                'push_sent'      => (int)($m['push_sent']  ?? 0),
                'sms_sent'       => (int)($m['sms_sent']   ?? 0),
                'sms_failed'     => (int)($m['sms_failed'] ?? 0),
            ];
        }
        $this->db->insert_batch(db_prefix() . 'pos_loyalty_blast_log_members', $rows);
    }

    public function get_blast_logs($page = 1, $per_page = 25, $filters = [])
    {
        $offset = ((int)$page - 1) * (int)$per_page;
        $pfx    = db_prefix();

        $this->db->select('bl.*, p.voucher_id')
            ->from($pfx . 'pos_loyalty_blast_log bl')
            ->join($pfx . 'pos_loyalty_promotions p', 'p.id = bl.promo_id', 'left');

        $this->_apply_blast_log_filters($filters);

        return $this->db->order_by('bl.blasted_at', 'DESC')
            ->limit((int)$per_page, $offset)
            ->get()->result_array();
    }

    public function count_blast_logs($filters = [])
    {
        $pfx = db_prefix();
        $this->db->from($pfx . 'pos_loyalty_blast_log bl')
            ->join($pfx . 'pos_loyalty_promotions p', 'p.id = bl.promo_id', 'left');
        $this->_apply_blast_log_filters($filters);
        return (int)$this->db->count_all_results();
    }

    private function _apply_blast_log_filters($filters)
    {
        if (!empty($filters['date_from'])) {
            $this->db->where('bl.blasted_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('bl.blasted_at <=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['blast_type'])) {
            $this->db->where('bl.blast_type', $filters['blast_type']);
        }
        if (!empty($filters['channel'])) {
            $this->db->like('bl.channels', $filters['channel'], 'both');
        }
    }

    public function get_voucher_redemptions_after($voucher_id, $after_datetime)
    {
        return (int)$this->db->where('voucher_id', (int)$voucher_id)
            ->where('redeemed_at >=', $after_datetime)
            ->count_all_results(db_prefix() . 'pos_loyalty_voucher_redemptions');
    }

    public function get_blast_log($id)
    {
        return $this->db->get_where(db_prefix() . 'pos_loyalty_blast_log', ['id' => (int)$id])->row_array() ?: null;
    }

    public function get_blast_log_members($blast_log_id)
    {
        return $this->db->where('blast_log_id', (int)$blast_log_id)
            ->order_by('customer_name', 'ASC')
            ->get(db_prefix() . 'pos_loyalty_blast_log_members')
            ->result_array();
    }

    public function get_member_blast_history($customer_id, $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;
        return $this->db
            ->select('bl.id, bl.promo_title, bl.blast_type, bl.trigger_type, bl.channels, bl.blasted_at, m.push_sent, m.sms_sent, m.sms_failed')
            ->from(db_prefix() . 'pos_loyalty_blast_log_members m')
            ->join(db_prefix() . 'pos_loyalty_blast_log bl', 'bl.id = m.blast_log_id')
            ->where('m.customer_id', (int)$customer_id)
            ->order_by('bl.blasted_at', 'DESC')
            ->limit((int)$per_page, $offset)
            ->get()->result_array();
    }

    /**
     * Check if a member has already been sent this promo in the given year.
     * year=0 means a one-time claim (signup_freebies with recurrence=once).
     */
    public function has_promo_claim($promo_id, $customer_id, $year)
    {
        return (bool)$this->db->get_where(db_prefix() . 'pos_loyalty_promo_claims', [
            'promo_id'    => (int)$promo_id,
            'customer_id' => (int)$customer_id,
            'claim_year'  => (int)$year,
        ])->row();
    }

    /**
     * Record that a member was sent this promo for the given year.
     * INSERT IGNORE handles duplicates gracefully.
     */
    public function record_promo_claim($promo_id, $customer_id, $year)
    {
        $this->db->query(
            'INSERT IGNORE INTO `' . db_prefix() . 'pos_loyalty_promo_claims`
             (promo_id, customer_id, claim_year, claimed_at) VALUES (?, ?, ?, NOW())',
            [(int)$promo_id, (int)$customer_id, (int)$year]
        );
    }

    // =========================================================================
    // Notifications
    // =========================================================================

    public function get_member_notifications($customer_id, $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;

        // Returns broadcast (customer_id = NULL) + targeted (customer_id = this member)
        return $this->db->select('n.*, p.title as promotion_title')
            ->from(db_prefix() . 'pos_loyalty_notifications n')
            ->join(db_prefix() . 'pos_loyalty_promotions p', 'p.id = n.promotion_id', 'left')
            ->group_start()
                ->where('n.customer_id', (int)$customer_id)
                ->or_where('n.customer_id IS NULL', null, false)
            ->group_end()
            ->order_by('n.created_at', 'DESC')
            ->limit((int)$per_page, $offset)
            ->get()->result_array();
    }

    public function get_all_notifications($page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;

        return $this->db->select('n.*, lc.name as customer_name, lc.phone as customer_phone, p.title as promotion_title')
            ->from(db_prefix() . 'pos_loyalty_notifications n')
            ->join(db_prefix() . 'pos_loyalty_customers lc', 'lc.id = n.customer_id', 'left')
            ->join(db_prefix() . 'pos_loyalty_promotions p', 'p.id = n.promotion_id', 'left')
            ->order_by('n.created_at', 'DESC')
            ->limit((int)$per_page, $offset)
            ->get()->result_array();
    }

    public function count_all_notifications()
    {
        return (int)$this->db->count_all(db_prefix() . 'pos_loyalty_notifications');
    }

    public function mark_notification_read($notification_id, $customer_id)
    {
        $this->db->where('id', (int)$notification_id)
            ->where('customer_id', (int)$customer_id)
            ->update(db_prefix() . 'pos_loyalty_notifications', ['is_read' => 1]);
    }

    /**
     * Send a notification. If customer_id is null, it is a broadcast to all members.
     * If target_tier is set, creates individual rows for members in that tier.
     */
    public function send_notification($data)
    {
        $customer_id  = isset($data['customer_id']) ? (int)$data['customer_id'] : null;
        $target       = $data['target'] ?? 'all'; // 'all', 'tier', 'individual'
        $target_tier  = trim($data['target_tier'] ?? '');
        $promotion_id = !empty($data['promotion_id']) ? (int)$data['promotion_id'] : null;
        $title        = trim($data['title'] ?? '');
        $message      = trim($data['message'] ?? '');
        $type         = in_array($data['type'] ?? '', ['promo', 'event', 'info', 'system'])
            ? $data['type'] : 'info';

        if ($title === '' || $message === '') return false;

        $now = date('Y-m-d H:i:s');
        $base = compact('title', 'message', 'type', 'promotion_id') + ['created_at' => $now, 'is_read' => 0];

        if ($target === 'individual' && $customer_id) {
            $this->db->insert(db_prefix() . 'pos_loyalty_notifications', $base + ['customer_id' => $customer_id]);
            return $this->db->insert_id() ? 1 : false;
        }

        if ($target === 'tier' && $target_tier !== '') {
            // Fan-out to members in the given tier
            if (!$this->db->table_exists(db_prefix() . 'ma_point_triggers')) return false;

            $tier_row = $this->db->get_where(db_prefix() . 'ma_point_triggers', ['name' => $target_tier])->row_array();
            if (!$tier_row) return false;

            $min_pts = (float)$tier_row['minimum_number_of_points'];
            $members = $this->db->select('id')
                ->where('total_points >=', $min_pts)
                ->get(db_prefix() . 'pos_loyalty_customers')->result_array();

            $count = 0;
            foreach ($members as $m) {
                $this->db->insert(db_prefix() . 'pos_loyalty_notifications', $base + ['customer_id' => (int)$m['id']]);
                $count++;
            }
            return $count;
        }

        // Broadcast (customer_id = NULL)
        $this->db->insert(db_prefix() . 'pos_loyalty_notifications', $base + ['customer_id' => null]);
        return $this->db->insert_id() ? 1 : false;
    }

    public function delete_notification($id)
    {
        $this->db->where('id', (int)$id)->delete(db_prefix() . 'pos_loyalty_notifications');
        return $this->db->affected_rows() > 0;
    }

    /**
     * Returns an array of phone numbers for the given SMS blast target.
     * target: 'all' | 'tier' | 'individual'
     */
    public function get_member_phones($target, $target_tier = '', $customer_id = null)
    {
        $pfx = db_prefix() . 'pos_loyalty_customers';

        if ($target === 'individual' && $customer_id) {
            $row = $this->db->select('phone')->where('id', (int)$customer_id)
                ->where('phone !=', '')->get($pfx)->row_array();
            return $row ? [$row['phone']] : [];
        }

        if ($target === 'tier' && $target_tier !== '') {
            if (!$this->db->table_exists(db_prefix() . 'ma_point_triggers')) return [];
            $tier_row = $this->db->get_where(db_prefix() . 'ma_point_triggers', ['name' => $target_tier])->row_array();
            if (!$tier_row) return [];
            $min_pts = (float)$tier_row['minimum_number_of_points'];
            $rows = $this->db->select('phone')->where('phone !=', '')->where('total_points >=', $min_pts)
                ->get($pfx)->result_array();
            return array_column($rows, 'phone');
        }

        // all
        $rows = $this->db->select('phone')->where('phone !=', '')->get($pfx)->result_array();
        return array_column($rows, 'phone');
    }

    public function get_unread_count($customer_id)
    {
        return (int)$this->db->group_start()
            ->where('customer_id', (int)$customer_id)
            ->or_where('customer_id IS NULL', null, false)
            ->group_end()
            ->where('is_read', 0)
            ->count_all_results(db_prefix() . 'pos_loyalty_notifications');
    }

    // =========================================================================
    // Google Review Tracking
    // =========================================================================

    public function confirm_google_review($phone)
    {
        $this->db->where('phone', $phone)
            ->update(db_prefix() . 'pos_loyalty_customers', ['google_review_done' => 1]);
        return $this->db->affected_rows() > 0;
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

    // =========================================================================
    // Vouchers
    // =========================================================================

    public function create_voucher($data)
    {
        $allowed = [
            'title', 'code_mode', 'base_code', 'reward_type', 'reward_value',
            'reward_item', 'max_uses', 'max_uses_per_member',
            'valid_from', 'valid_until', 'is_active',
        ];
        $row = ['created_at' => date('Y-m-d H:i:s'), 'used_count' => 0];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $row[$f] = ($data[$f] !== '' && $data[$f] !== null) ? $data[$f] : null;
            }
        }
        $this->db->insert(db_prefix() . 'pos_loyalty_vouchers', $row);
        return $this->db->insert_id() ?: false;
    }

    public function update_voucher($id, $data)
    {
        $allowed = [
            'title', 'code_mode', 'base_code', 'reward_type', 'reward_value',
            'reward_item', 'max_uses', 'max_uses_per_member',
            'valid_from', 'valid_until', 'is_active',
        ];
        $row = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $row[$f] = ($data[$f] !== '' && $data[$f] !== null) ? $data[$f] : null;
            }
        }
        if (empty($row)) return false;
        $this->db->where('id', (int)$id)->update(db_prefix() . 'pos_loyalty_vouchers', $row);
        return $this->db->affected_rows() >= 0;
    }

    public function delete_voucher($id)
    {
        $this->db->where('id', (int)$id)->delete(db_prefix() . 'pos_loyalty_vouchers');
        $this->db->where('voucher_id', (int)$id)->delete(db_prefix() . 'pos_loyalty_voucher_instances');
        return true;
    }

    public function get_voucher($id)
    {
        return $this->db->get_where(db_prefix() . 'pos_loyalty_vouchers', ['id' => (int)$id])->row_array() ?: null;
    }

    public function voucher_code_exists($base_code, $exclude_id = null)
    {
        $this->db->where('base_code', strtoupper(trim($base_code)))->where('code_mode', 'shared');
        if ($exclude_id) $this->db->where('id !=', (int)$exclude_id);
        return (bool)$this->db->count_all_results(db_prefix() . 'pos_loyalty_vouchers');
    }

    /**
     * Resolve any code (shared or unique instance) → ['voucher'=>[...], 'instance'=>[...] or null].
     * Returns null when code is not found.
     */
    public function get_voucher_by_code($code)
    {
        $code = strtoupper(trim($code));

        // Try shared first
        $voucher = $this->db->get_where(db_prefix() . 'pos_loyalty_vouchers', [
            'base_code'  => $code,
            'code_mode'  => 'shared',
        ])->row_array();

        if ($voucher) {
            return ['voucher' => $voucher, 'instance' => null];
        }

        // Try unique instance
        $instance = $this->db->get_where(db_prefix() . 'pos_loyalty_voucher_instances', ['code' => $code])->row_array();
        if (!$instance) return null;

        $voucher = $this->get_voucher((int)$instance['voucher_id']);
        if (!$voucher) return null;

        return ['voucher' => $voucher, 'instance' => $instance];
    }

    /**
     * Full validation: returns ['valid'=>true,'voucher'=>[...],'instance'=>[...]] or ['valid'=>false,'error'=>'...'].
     */
    public function validate_voucher($code, $customer_id)
    {
        $resolved = $this->get_voucher_by_code($code);
        if (!$resolved) return ['valid' => false, 'error' => 'Invalid voucher code'];

        $voucher  = $resolved['voucher'];
        $instance = $resolved['instance'];

        if (!$voucher['is_active']) {
            return ['valid' => false, 'error' => 'This voucher is no longer active'];
        }

        $today = date('Y-m-d');
        if (!empty($voucher['valid_from']) && $today < $voucher['valid_from']) {
            return ['valid' => false, 'error' => 'This voucher is not valid yet'];
        }
        if (!empty($voucher['valid_until']) && $today > $voucher['valid_until']) {
            return ['valid' => false, 'error' => 'This voucher has expired'];
        }

        // Unique-per-member: code must belong to this customer
        if ($instance && (int)$instance['customer_id'] !== (int)$customer_id) {
            return ['valid' => false, 'error' => 'This code does not belong to this member'];
        }

        // Global cap
        if (!is_null($voucher['max_uses']) && (int)$voucher['used_count'] >= (int)$voucher['max_uses']) {
            return ['valid' => false, 'error' => 'This voucher has reached its maximum redemptions'];
        }

        // Per-member cap
        $member_uses = (int)$this->db->where('voucher_id', (int)$voucher['id'])
            ->where('customer_id', (int)$customer_id)
            ->count_all_results(db_prefix() . 'pos_loyalty_voucher_redemptions');

        if ($member_uses >= (int)$voucher['max_uses_per_member']) {
            return ['valid' => false, 'error' => 'You have already redeemed this voucher'];
        }

        return ['valid' => true, 'voucher' => $voucher, 'instance' => $instance];
    }

    /**
     * Record a redemption and increment the global used_count.
     */
    public function redeem_voucher($voucher_id, $customer_id, $instance_id = null, $order_id = null)
    {
        $this->db->insert(db_prefix() . 'pos_loyalty_voucher_redemptions', [
            'voucher_id'  => (int)$voucher_id,
            'customer_id' => (int)$customer_id,
            'instance_id' => $instance_id ? (int)$instance_id : null,
            'order_id'    => $order_id    ? (int)$order_id    : null,
            'redeemed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->where('id', (int)$voucher_id)
            ->set('used_count', 'used_count + 1', false)
            ->update(db_prefix() . 'pos_loyalty_vouchers');

        return $this->db->insert_id() ?: false;
    }

    /**
     * Get or create a unique-per-member code instance for this member.
     * Returns the instance row.
     */
    public function issue_voucher_instance($voucher_id, $customer_id)
    {
        $existing = $this->db->get_where(db_prefix() . 'pos_loyalty_voucher_instances', [
            'voucher_id'  => (int)$voucher_id,
            'customer_id' => (int)$customer_id,
        ])->row_array();

        if ($existing) return $existing;

        $voucher = $this->get_voucher((int)$voucher_id);
        $prefix  = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $voucher['base_code'] ?? 'V'));

        // Generate a collision-free code
        do {
            $code = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while ($this->db->get_where(db_prefix() . 'pos_loyalty_voucher_instances', ['code' => $code])->row());

        $this->db->insert(db_prefix() . 'pos_loyalty_voucher_instances', [
            'voucher_id'  => (int)$voucher_id,
            'customer_id' => (int)$customer_id,
            'code'        => $code,
            'issued_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->db->get_where(db_prefix() . 'pos_loyalty_voucher_instances', [
            'voucher_id'  => (int)$voucher_id,
            'customer_id' => (int)$customer_id,
        ])->row_array();
    }

    public function get_voucher_redemptions($voucher_id, $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;
        return $this->db
            ->select('r.*, c.name as customer_name, c.phone as customer_phone')
            ->from(db_prefix() . 'pos_loyalty_voucher_redemptions r')
            ->join(db_prefix() . 'pos_loyalty_customers c', 'c.id = r.customer_id', 'left')
            ->where('r.voucher_id', (int)$voucher_id)
            ->order_by('r.redeemed_at', 'DESC')
            ->limit((int)$per_page, $offset)
            ->get()->result_array();
    }
}
