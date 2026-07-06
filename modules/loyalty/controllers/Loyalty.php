<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Loyalty extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('loyalty/loyalty_model');
    }

    public function index()
    {
        redirect(admin_url('loyalty/dashboard'));
    }

    // =========================================================================
    // Dashboard
    // =========================================================================

    public function dashboard()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $period  = in_array($this->input->get('period'), ['today', 'week', 'month', 'year'])
            ? $this->input->get('period') : 'month';

        $data['title']         = 'Loyalty Dashboard';
        $data['period']        = $period;
        $data['stats']         = $this->loyalty_model->get_stats();
        $data['period_stats']  = $this->loyalty_model->get_period_stats($period);
        $data['member_growth'] = $this->loyalty_model->get_member_growth(12);
        $data['txn_trend']     = $this->loyalty_model->get_transaction_trend(30);
        $data['tier_dist']     = $this->loyalty_model->get_tier_distribution();
        $data['recent_txns']   = $this->loyalty_model->get_recent_transactions(10);

        $this->load->view('loyalty/admin/dashboard', $data);
    }

    // =========================================================================
    // Members List
    // =========================================================================

    public function customers()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $search   = $this->input->get('q') ?: '';
        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = in_array((int)($this->input->get('limit') ?: 20), [10, 20, 50, 100])
            ? (int)($this->input->get('limit') ?: 20) : 20;
        $sort     = $this->input->get('sort') ?: 'registered_at';
        $dir      = $this->input->get('dir')  ?: 'desc';

        $total = $this->loyalty_model->count_customers($search);
        $rows  = $this->loyalty_model->get_customers($search, $page, $per_page, $sort, $dir);
        $stats = $this->loyalty_model->get_stats();

        $data['title']   = 'Loyalty Members';
        $data['rows']    = $rows;
        $data['stats']   = $stats;
        $data['filters'] = compact('search', 'page', 'per_page', 'sort', 'dir');
        $data['result']  = [
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'page_count' => max(1, (int)ceil($total / $per_page)),
        ];

        $this->load->view('loyalty/admin/customers', $data);
    }

    // =========================================================================
    // Customer Detail
    // =========================================================================

    public function customer($id = 0)
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $customer = $this->loyalty_model->get_customer((int)$id);
        if (!$customer) {
            show_404();
        }

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = 20;
        $total    = $this->loyalty_model->count_customer_transactions((int)$id);
        $txns     = $this->loyalty_model->get_customer_transactions((int)$id, $page, $per_page);

        $data['title']    = 'Member: ' . htmlspecialchars($customer['name'] ?: $customer['phone']);
        $data['customer'] = $customer;
        $data['txns']     = $txns;
        $data['result']   = [
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'page_count' => max(1, (int)ceil($total / $per_page)),
        ];

        $this->load->view('loyalty/admin/customer_detail', $data);
    }

    // =========================================================================
    // Manual Point Adjustment (POST)
    // =========================================================================

    public function manual_adjust()
    {
        if (!has_permission('loyalty', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_404();
        }

        $customer_id = (int)$this->input->post('customer_id');
        $points      = (float)$this->input->post('points');
        $description = trim($this->input->post('description') ?: 'Manual adjustment');

        if (!$customer_id || $points == 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            return;
        }

        $ok = $this->loyalty_model->adjust_points($customer_id, $points, $description);
        $customer = $this->loyalty_model->get_customer($customer_id);

        echo json_encode([
            'success'       => (bool)$ok,
            'total_points'  => $ok ? (float)$customer['total_points'] : null,
        ]);
    }

    // =========================================================================
    // Update / Delete Member
    // =========================================================================

    public function ajax_update_customer()
    {
        if (!has_permission('loyalty', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_404();
        }

        $id = (int)$this->input->post('id');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
            return;
        }

        $ok = $this->loyalty_model->update_customer($id, [
            'name'     => trim($this->input->post('name')),
            'phone'    => trim($this->input->post('phone')),
            'email'    => trim($this->input->post('email')),
            'birthday' => trim($this->input->post('birthday')),
            'address1' => trim($this->input->post('address1')),
            'address2' => trim($this->input->post('address2')),
            'city'     => trim($this->input->post('city')),
            'state'    => trim($this->input->post('state')),
            'postcode' => trim($this->input->post('postcode')),
        ]);

        echo json_encode(['success' => (bool)$ok]);
    }

    public function ajax_set_account_status()
    {
        if (!has_permission('loyalty', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id     = (int)$this->input->post('id');
        $status = $this->input->post('status');

        if (!$id || !in_array($status, ['active', 'inactive', 'banned'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            return;
        }

        $this->db->where('id', $id)->update(db_prefix() . 'pos_loyalty_customers', ['account_status' => $status]);

        // Revoke all sessions when banning
        if ($status === 'banned') {
            $this->db->where('customer_id', $id)->delete(db_prefix() . 'pos_loyalty_member_sessions');
        }

        echo json_encode(['success' => true]);
    }

    public function ajax_delete_customer()
    {
        if (!has_permission('loyalty', '', 'delete')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_404();
        }

        $id = (int)$this->input->post('id');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
            return;
        }

        $ok = $this->loyalty_model->delete_customer($id);
        echo json_encode(['success' => (bool)$ok]);
    }

    // =========================================================================
    // Import Members
    // =========================================================================

    public function import_members()
    {
        if (!has_permission('loyalty', '', 'create')) {
            access_denied('loyalty');
        }

        $data['title'] = 'Import Members';
        $this->load->view('loyalty/admin/import_members', $data);
    }

    public function import_members_template()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="loyalty_members_template.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'name', 'phone', 'email', 'birthday',
            'address1', 'address2', 'city', 'state', 'postcode',
            'total_spent', 'total_points', 'total_transactions', 'last_purchase_date',
        ]);
        fputcsv($out, [
            'Ahmad Bin Ali', '0123456789', 'ahmad@example.com', '1990-05-15',
            '12 Jalan Bunga', '', 'Kuala Lumpur', 'WP Kuala Lumpur', '50000',
            '1200.00', '120.00', '8', '2025-06-01',
        ]);
        fputcsv($out, [
            'Siti Binti Omar', '0198765432', 'siti@example.com', '1985-11-23',
            '5 Lorong Damai', 'Taman Maju', 'Petaling Jaya', 'Selangor', '47810',
            '500.00', '50.00', '3', '2025-05-20',
        ]);
        fclose($out);
        exit;
    }

    public function import_members_submit()
    {
        if (!has_permission('loyalty', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_404();
        }

        $rows = json_decode($this->input->post('rows'), true);
        if (!is_array($rows)) {
            echo json_encode(['success' => false, 'message' => 'Invalid payload']);
            return;
        }

        $created = 0;
        $updated = 0;
        $errors  = [];

        foreach ($rows as $i => $row) {
            $result = $this->loyalty_model->import_member($row);
            if (isset($result['error'])) {
                $errors[] = 'Row ' . ($i + 1) . ': ' . $result['error'];
            } elseif (!empty($result['created'])) {
                $created++;
            } elseif (!empty($result['updated'])) {
                $updated++;
            }
        }

        echo json_encode(compact('created', 'updated', 'errors'));
    }

    // =========================================================================
    // Promotions
    // =========================================================================

    public function blast_history()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $filters = [
            'date_from'  => $this->input->get('date_from') ?: '',
            'date_to'    => $this->input->get('date_to')   ?: '',
            'blast_type' => $this->input->get('blast_type') ?: '',
            'channel'    => $this->input->get('channel')   ?: '',
        ];

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = 25;
        $total    = $this->loyalty_model->count_blast_logs($filters);
        $logs     = $this->loyalty_model->get_blast_logs($page, $per_page, $filters);

        // Attach voucher redemption counts
        foreach ($logs as &$log) {
            $log['voucher_redemptions'] = 0;
            if (!empty($log['voucher_id']) && !empty($log['blasted_at'])) {
                $log['voucher_redemptions'] = $this->loyalty_model->get_voucher_redemptions_after(
                    (int)$log['voucher_id'],
                    $log['blasted_at']
                );
            }
        }
        unset($log);

        $data['title']   = 'Blast History';
        $data['logs']    = $logs;
        $data['filters'] = $filters;
        $data['result']  = [
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'page_count' => max(1, (int)ceil($total / $per_page)),
        ];

        $this->load->view('loyalty/admin/blast_history', $data);
    }

    public function blast_history_export()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $filters = [
            'date_from'  => $this->input->get('date_from') ?: '',
            'date_to'    => $this->input->get('date_to')   ?: '',
            'blast_type' => $this->input->get('blast_type') ?: '',
            'channel'    => $this->input->get('channel')   ?: '',
        ];

        $logs = $this->loyalty_model->get_blast_logs(1, 5000, $filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="blast_history_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Time', 'Title', 'Type', 'Trigger', 'Channels', 'Recipients', 'Push Sent', 'SMS Sent', 'SMS Failed', 'Voucher Redemptions']);

        foreach ($logs as $log) {
            $redemptions = 0;
            if (!empty($log['voucher_id']) && !empty($log['blasted_at'])) {
                $redemptions = $this->loyalty_model->get_voucher_redemptions_after((int)$log['voucher_id'], $log['blasted_at']);
            }
            fputcsv($out, [
                date('d M Y', strtotime($log['blasted_at'])),
                date('H:i', strtotime($log['blasted_at'])),
                $log['promo_title'],
                $log['blast_type'],
                $log['trigger_type'],
                $log['channels'],
                $log['recipient_count'],
                $log['push_sent'],
                $log['sms_sent'],
                $log['sms_failed'],
                $redemptions,
            ]);
        }

        fclose($out);
        exit;
    }

    public function ajax_blast_log_members()
    {
        if (!has_permission('loyalty', '', 'view')) {
            echo json_encode(['success' => false]);
            return;
        }

        $log_id  = (int)$this->input->get('log_id');
        $members = $this->loyalty_model->get_blast_log_members($log_id);
        echo json_encode(['success' => true, 'members' => $members]);
    }

    public function ajax_delete_blast_log()
    {
        if (!has_permission('loyalty', '', 'delete')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id = (int)$this->input->post('id');
        echo json_encode(['success' => (bool)$this->loyalty_model->delete_blast_log($id)]);
    }

    public function announcements()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $tiers = [];
        if ($this->db->table_exists(db_prefix() . 'ma_point_triggers')) {
            $tiers = $this->db->order_by('minimum_number_of_points', 'ASC')
                ->get(db_prefix() . 'ma_point_triggers')->result_array();
        }

        $data['title'] = 'Announcement';
        $data['tiers'] = $tiers;
        $this->load->view('loyalty/admin/announcements', $data);
    }

    public function ajax_send_announcement()
    {
        if (!has_permission('loyalty', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $title   = trim($this->input->post('title'));
        $body    = trim($this->input->post('body'));
        $push    = (bool)$this->input->post('notify_push');
        $sms_on  = (bool)$this->input->post('notify_sms');
        $target  = $this->input->post('target') ?: 'all';
        $tier    = trim($this->input->post('target_tier') ?: '');
        $ind_ids = trim($this->input->post('target_customer_ids') ?: '');

        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            return;
        }

        $q = $this->db->select('id, name, phone, birthday, total_points')
            ->where("phone != ''");

        if ($target === 'individual' && $ind_ids !== '') {
            $ids = array_filter(array_map('intval', explode(',', $ind_ids)));
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'No members selected']);
                return;
            }
            $q->where_in('id', $ids);
        } elseif ($target === 'tier' && $tier !== '') {
            if ($this->db->table_exists(db_prefix() . 'ma_point_triggers')) {
                $tier_row = $this->db->get_where(db_prefix() . 'ma_point_triggers', ['name' => $tier])->row_array();
                if ($tier_row) {
                    $q->where('total_points >=', (float)$tier_row['minimum_number_of_points']);
                }
            }
        }

        $members = $q->get(db_prefix() . 'pos_loyalty_customers')->result_array();

        $result = ['push_sent' => 0, 'sms_sent' => 0, 'sms_failed' => 0, 'recipients' => count($members)];

        if (empty($members)) {
            echo json_encode(array_merge(['success' => true], $result));
            return;
        }

        $sms_ready = false;
        if ($sms_on) {
            $this->load->library('loyalty/twilio_sms');
            $sms_ready = $this->twilio_sms->is_configured();
            if (!$sms_ready) $result['sms_error'] = 'Twilio not configured';
        }

        @set_time_limit(180);

        $member_log = [];

        foreach ($members as $m) {
            $p_title  = $this->_substitute_vars($title, $m);
            $p_body   = $this->_substitute_vars($body,  $m);
            $push_ok  = false;
            $sms_ok   = false;
            $sms_fail = false;

            if ($push) {
                $this->loyalty_model->send_notification([
                    'title'       => $p_title,
                    'message'     => $p_body,
                    'type'        => 'info',
                    'target'      => 'individual',
                    'customer_id' => (int)$m['id'],
                ]);
                $push_ok = true;
                $result['push_sent']++;
            }

            if ($sms_on && $sms_ready && !empty($m['phone'])) {
                $text = $p_body ?: $p_title;
                $res  = $this->twilio_sms->send($m['phone'], $text);
                if ($res === true) { $sms_ok = true; $result['sms_sent']++; }
                else               { $sms_fail = true; $result['sms_failed']++; }
            }

            $member_log[] = [
                'id'         => $m['id'],
                'name'       => $m['name']  ?? '',
                'phone'      => $m['phone'] ?? '',
                'push_sent'  => $push_ok  ? 1 : 0,
                'sms_sent'   => $sms_ok   ? 1 : 0,
                'sms_failed' => $sms_fail ? 1 : 0,
            ];
        }

        // Persist blast history
        $channels = array_filter(['push' => $push, 'sms' => $sms_on]);
        $log_id   = $this->loyalty_model->create_blast_log([
            'promo_id'        => null,
            'promo_title'     => $title,
            'blast_type'      => 'announcement',
            'trigger_type'    => 'announcement',
            'channels'        => implode('+', array_keys($channels)),
            'recipient_count' => $result['recipients'],
            'push_sent'       => $result['push_sent'],
            'sms_sent'        => $result['sms_sent'],
            'sms_failed'      => $result['sms_failed'],
            'blasted_by'      => get_staff_user_id(),
        ]);
        $this->loyalty_model->log_blast_members($log_id, $member_log);

        echo json_encode(array_merge(['success' => true], $result));
    }

    public function promotions()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = 20;
        $total    = $this->loyalty_model->count_promotions();
        $rows     = $this->loyalty_model->get_promotions(false, $page, $per_page);

        // Attach voucher data to each promotion row
        foreach ($rows as &$row) {
            if (!empty($row['voucher_id'])) {
                $row['voucher'] = $this->loyalty_model->get_voucher((int)$row['voucher_id']);
            } else {
                $row['voucher'] = null;
            }
        }
        unset($row);

        $tiers = [];
        if ($this->db->table_exists(db_prefix() . 'ma_point_triggers')) {
            $tiers = $this->db->order_by('minimum_number_of_points', 'ASC')
                ->get(db_prefix() . 'ma_point_triggers')->result_array();
        }

        $data['title']   = 'Event & Promotions';
        $data['rows']    = $rows;
        $data['tiers']   = $tiers;
        $data['result']  = [
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'page_count' => max(1, (int)ceil($total / $per_page)),
        ];

        $this->load->view('loyalty/admin/promotions', $data);
    }

    public function ajax_save_promotion()
    {
        if (!has_permission('loyalty', '', 'create') && !has_permission('loyalty', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id           = (int)$this->input->post('id');
        $title        = trim($this->input->post('title'));
        $trigger_type = $this->input->post('trigger_type') ?: 'standard';
        $target       = $this->input->post('target') ?: 'all';
        $days_before  = trim($this->input->post('notify_days_before') ?: '0');
        $notify_push  = (int)(bool)$this->input->post('notify_push');
        $notify_sms   = (int)(bool)$this->input->post('notify_sms');

        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            return;
        }

        $is_recurring  = in_array($trigger_type, ['birthday', 'signup_freebies', 'stale_points']);
        $notify_status = 'pending';
        if ($id) {
            $existing_promo = $this->loyalty_model->get_promotion($id);
            if ($existing_promo && in_array($existing_promo['notify_status'] ?? '', ['recurring', 'sent'])) {
                $notify_status = $existing_promo['notify_status'];
            }
        }

        $fields = [
            'title'                    => $title,
            'description'              => trim($this->input->post('description')),
            'image_url'                => trim($this->input->post('image_url')),
            'type'                     => $this->input->post('type') ?: 'promotion',
            'start_date'               => trim($this->input->post('start_date')) ?: null,
            'end_date'                 => trim($this->input->post('end_date')) ?: null,
            'is_active'                => 1,
            'trigger_type'             => $trigger_type,
            'target'                   => $is_recurring ? 'all' : $target,
            'target_tier'              => trim($this->input->post('target_tier')) ?: null,
            'target_customer_id'       => ($target === 'individual') ? (trim($this->input->post('target_customer_id') ?: '') ?: null) : null,
            'notify_push'              => $notify_push,
            'notify_sms'               => $notify_sms,
            'notify_days_before'       => $days_before,
            'notify_status'            => $notify_status,
            'signup_recurrence'        => $this->input->post('signup_recurrence') ?: 'annual',
            'stale_days'               => max(1, (int)($this->input->post('stale_days') ?: 90)),
            'birthday_start_date'      => trim($this->input->post('birthday_start_date')) ?: null,
            'birthday_inactive_filter' => (int)(bool)$this->input->post('birthday_inactive_filter'),
            'birthday_inactive_days'   => max(1, (int)($this->input->post('birthday_inactive_days') ?: 90)),
            'signup_days_window'       => ($this->input->post('signup_days_window') !== '' && $this->input->post('signup_days_window') !== null)
                                              ? max(1, (int)$this->input->post('signup_days_window'))
                                              : null,
        ];

        if ($id) {
            if (!has_permission('loyalty', '', 'edit')) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            $this->loyalty_model->update_promotion($id, $fields);
            $promo_id = $id;
        } else {
            if (!has_permission('loyalty', '', 'create')) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            $promo_id = $this->loyalty_model->create_promotion($fields);
            if (!$promo_id) {
                echo json_encode(['success' => false, 'message' => 'Failed to save promotion']);
                return;
            }
        }

        // ── Voucher ───────────────────────────────────────────────────────────
        $has_voucher = (bool)$this->input->post('has_voucher');

        if ($has_voucher) {
            $code_mode  = $this->input->post('voucher_code_mode') === 'unique_per_member' ? 'unique_per_member' : 'shared';
            $base_code  = strtoupper(trim($this->input->post('voucher_base_code') ?: ''));
            $reward_type = $this->input->post('voucher_reward_type') ?: 'discount_pct';

            if (empty($base_code)) {
                echo json_encode(['success' => false, 'message' => 'Voucher code / prefix is required']);
                return;
            }

            // Shared codes must be unique across active vouchers
            $existing_promo = $this->loyalty_model->get_promotion($promo_id);
            $existing_vid   = !empty($existing_promo['voucher_id']) ? (int)$existing_promo['voucher_id'] : null;

            if ($code_mode === 'shared' && $this->loyalty_model->voucher_code_exists($base_code, $existing_vid)) {
                echo json_encode(['success' => false, 'message' => 'A shared voucher with code "' . $base_code . '" already exists']);
                return;
            }

            $reward_value = trim($this->input->post('voucher_reward_value') ?: '');
            $vdata = [
                'title'               => $title,
                'code_mode'           => $code_mode,
                'base_code'           => $base_code,
                'reward_type'         => $reward_type,
                'reward_value'        => in_array($reward_type, ['discount_pct', 'discount_fixed', 'points_bonus']) && $reward_value !== '' ? (float)$reward_value : null,
                'reward_item'         => $reward_type === 'free_item' ? trim($this->input->post('voucher_reward_item') ?: '') : null,
                'max_uses'            => trim($this->input->post('voucher_max_uses') ?: '') !== '' ? max(1, (int)$this->input->post('voucher_max_uses')) : null,
                'max_uses_per_member' => max(1, (int)($this->input->post('voucher_max_uses_per_member') ?: 1)),
                'valid_from'          => trim($this->input->post('voucher_valid_from') ?: '') ?: null,
                'valid_until'         => trim($this->input->post('voucher_valid_until') ?: '') ?: null,
                'is_active'           => 1,
            ];

            if ($existing_vid) {
                $this->loyalty_model->update_voucher($existing_vid, $vdata);
                $voucher_id = $existing_vid;
            } else {
                $voucher_id = $this->loyalty_model->create_voucher($vdata);
            }

            if ($voucher_id) {
                $this->loyalty_model->update_promotion($promo_id, ['voucher_id' => $voucher_id]);
            }
        } elseif ($id) {
            // Voucher detached — clear the link (don't delete, preserves redemption history)
            $existing_promo = $this->loyalty_model->get_promotion($promo_id);
            if (!empty($existing_promo['voucher_id'])) {
                $this->loyalty_model->update_promotion($promo_id, ['voucher_id' => null]);
            }
        }

        // Standard and event promos require a manual blast — never auto-fire on save
        echo json_encode(['success' => true, 'id' => $promo_id]);
    }

    public function ajax_blast_promotion()
    {
        if (!has_permission('loyalty', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id    = (int)$this->input->post('id');
        $promo = $this->loyalty_model->get_promotion($id);
        if (!$promo) {
            echo json_encode(['success' => false, 'message' => 'Promotion not found']);
            return;
        }

        $result = $this->_do_blast($id, $promo['title'], $promo['description'] ?? '');
        echo json_encode(array_merge(['success' => true], $result));
    }

    private function _do_blast($promo_id, $title, $description)
    {
        $promo      = $this->loyalty_model->get_promotion($promo_id);
        $candidates = $this->loyalty_model->get_blast_recipients($promo);

        $trigger      = $promo['trigger_type'] ?? 'standard';
        $is_tracked   = in_array($trigger, ['birthday', 'signup_freebies', 'stale_points']);
        $is_recurring = $is_tracked;
        $this_year    = (int)date('Y');

        // Resolve attached voucher (if any)
        $voucher     = null;
        $voucher_id  = !empty($promo['voucher_id']) ? (int)$promo['voucher_id'] : null;
        if ($voucher_id) {
            $voucher = $this->loyalty_model->get_voucher($voucher_id);
            if (!empty($voucher) && !$voucher['is_active']) $voucher = null;
        }

        // Filter out members already claimed this year
        $recipients = [];
        foreach ($candidates as $r) {
            if ($is_tracked) {
                $check_year = ($trigger === 'signup_freebies' && ($promo['signup_recurrence'] ?? 'annual') === 'once')
                    ? 0
                    : $this_year;
                if ($this->loyalty_model->has_promo_claim($promo_id, $r['id'], $check_year)) {
                    continue;
                }
            }
            $recipients[] = $r;
        }

        $result = ['push_sent' => 0, 'sms_sent' => 0, 'sms_failed' => 0, 'recipients' => count($recipients)];

        if (empty($recipients)) return $result;

        $notify_push = !empty($promo['notify_push']);
        $notify_sms  = !empty($promo['notify_sms']);
        $sms_ready   = false;

        if ($notify_sms) {
            $this->load->library('loyalty/twilio_sms');
            $sms_ready = $this->twilio_sms->is_configured();
            if (!$sms_ready) $result['sms_error'] = 'Twilio not configured';
        }

        @set_time_limit(180);

        $member_log = [];

        foreach ($recipients as $r) {
            // Resolve voucher code for this member
            $voucher_code = '';
            if ($voucher) {
                if ($voucher['code_mode'] === 'unique_per_member') {
                    $instance     = $this->loyalty_model->issue_voucher_instance($voucher['id'], (int)$r['id']);
                    $voucher_code = $instance['code'] ?? '';
                } else {
                    $voucher_code = $voucher['base_code'];
                }
            }

            $p_title   = $this->_substitute_vars($title,       $r, $voucher_code);
            $p_body    = $this->_substitute_vars($description, $r, $voucher_code);
            $push_ok   = false;
            $sms_ok    = false;
            $sms_fail  = false;

            if ($notify_push) {
                $this->loyalty_model->send_notification([
                    'title'        => $p_title,
                    'message'      => $p_body,
                    'type'         => 'promo',
                    'target'       => 'individual',
                    'customer_id'  => (int)$r['id'],
                    'promotion_id' => (int)$promo_id,
                ]);
                $push_ok = true;
                $result['push_sent']++;
            }

            if ($notify_sms && $sms_ready && !empty($r['phone'])) {
                $sms_body = $p_body ?: $p_title;
                $res      = $this->twilio_sms->send($r['phone'], $sms_body);
                if ($res === true) { $sms_ok = true; $result['sms_sent']++; }
                else               { $sms_fail = true; $result['sms_failed']++; }
            }

            if ($is_tracked) {
                $claim_year = ($trigger === 'signup_freebies' && ($promo['signup_recurrence'] ?? 'annual') === 'once')
                    ? 0
                    : $this_year;
                $this->loyalty_model->record_promo_claim($promo_id, $r['id'], $claim_year);
            }

            $member_log[] = [
                'id'         => $r['id'],
                'name'       => $r['name']  ?? '',
                'phone'      => $r['phone'] ?? '',
                'push_sent'  => $push_ok  ? 1 : 0,
                'sms_sent'   => $sms_ok   ? 1 : 0,
                'sms_failed' => $sms_fail ? 1 : 0,
            ];
        }

        $this->loyalty_model->mark_promo_notified($promo_id, $is_recurring);

        // Persist blast history
        $channels = array_filter(['push' => $notify_push, 'sms' => $notify_sms]);
        $log_id   = $this->loyalty_model->create_blast_log([
            'promo_id'        => (int)$promo_id,
            'promo_title'     => $title,
            'blast_type'      => 'promotion',
            'trigger_type'    => $trigger,
            'channels'        => implode('+', array_keys($channels)),
            'recipient_count' => $result['recipients'],
            'push_sent'       => $result['push_sent'],
            'sms_sent'        => $result['sms_sent'],
            'sms_failed'      => $result['sms_failed'],
            'blasted_by'      => get_staff_user_id(),
        ]);
        $this->loyalty_model->log_blast_members($log_id, $member_log);

        return $result;
    }

    private function _substitute_vars($template, $member, $voucher_code = '')
    {
        $name        = $member['name'] ?? '';
        $parts       = explode(' ', trim($name));
        $firstname   = $parts[0] ?? $name;
        $lastname    = count($parts) > 1 ? end($parts) : '';
        $birthday    = !empty($member['birthday']) ? date('d M', strtotime($member['birthday'])) : '';
        $points      = number_format((float)($member['total_points'] ?? 0), 0);
        $phone       = $member['phone'] ?? '';
        $tier        = $member['tier'] ?? '';
        $signup_date = !empty($member['created_at']) ? date('d M Y', strtotime($member['created_at'])) : '';

        return str_replace(
            ['{{firstname}}', '{{lastname}}', '{{name}}', '{{birthday}}', '{{points}}', '{{phone}}', '{{tier}}', '{{signup_date}}', '{{voucher_code}}'],
            [$firstname,      $lastname,      $name,      $birthday,      $points,      $phone,      $tier,      $signup_date,       $voucher_code],
            $template ?? ''
        );
    }

    public function ajax_delete_promotion()
    {
        if (!has_permission('loyalty', '', 'delete')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id = (int)$this->input->post('id');
        echo json_encode(['success' => (bool)$this->loyalty_model->delete_promotion($id)]);
    }

    public function ajax_delete_voucher()
    {
        if (!has_permission('loyalty', '', 'delete')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $voucher_id = (int)$this->input->post('voucher_id');
        $promo_id   = (int)$this->input->post('promo_id');

        if ($promo_id) {
            $this->loyalty_model->update_promotion($promo_id, ['voucher_id' => null]);
        }

        $this->loyalty_model->delete_voucher($voucher_id);
        echo json_encode(['success' => true]);
    }

    public function ajax_clone_promotion()
    {
        if (!has_permission('loyalty', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id     = (int)$this->input->post('id');
        $new_id = $this->loyalty_model->clone_promotion($id);

        if (!$new_id) {
            echo json_encode(['success' => false, 'message' => 'Promotion not found']);
            return;
        }
        echo json_encode(['success' => true, 'new_id' => $new_id]);
    }

    public function ajax_toggle_active()
    {
        if (!has_permission('loyalty', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id     = (int)$this->input->post('id');
        $active = $this->input->post('is_active') !== null
            ? (int)(bool)$this->input->post('is_active')
            : null;

        if ($active === null) {
            $promo  = $this->loyalty_model->get_promotion($id);
            $active = $promo ? (int)!$promo['is_active'] : 1;
        }

        $ok = $this->loyalty_model->update_promotion($id, ['is_active' => $active]);
        echo json_encode(['success' => (bool)$ok, 'is_active' => $active]);
    }

    public function ajax_preview_sms()
    {
        if (!has_permission('loyalty', '', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $body        = trim($this->input->post('body') ?: '');
        $voucher_code = trim($this->input->post('voucher_code') ?: '');

        // Build a sample member for substitution
        $sample = [
            'name'        => 'Siti Aminah',
            'first_name'  => 'Siti',
            'birthday'    => date('Y-m-d', mktime(0,0,0, date('m'), date('d'), date('Y')-30)),
            'total_points'=> 350,
            'phone'       => '+60123456789',
            'tier'        => 'Gold',
            'created_at'  => date('Y-m-d', strtotime('-60 days')),
        ];
        $preview = $this->_substitute_vars($body, $sample, $voucher_code);

        echo json_encode(['success' => true, 'preview' => $preview]);
    }

    // =========================================================================
    // Notifications
    // =========================================================================

    public function notifications()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = 20;
        $total    = $this->loyalty_model->count_all_notifications();
        $rows     = $this->loyalty_model->get_all_notifications($page, $per_page);

        // Load promotions for the send modal dropdown
        $promotions = $this->loyalty_model->get_promotions(false, 1, 100);

        // Load tier list for targeting
        $tiers = [];
        if ($this->db->table_exists(db_prefix() . 'ma_point_triggers')) {
            $tiers = $this->db->order_by('minimum_number_of_points', 'ASC')
                ->get(db_prefix() . 'ma_point_triggers')->result_array();
        }

        $data['title']      = 'Notifications';
        $data['rows']       = $rows;
        $data['promotions'] = $promotions;
        $data['tiers']      = $tiers;
        $data['result']     = [
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'page_count' => max(1, (int)ceil($total / $per_page)),
        ];

        $this->load->view('loyalty/admin/notifications', $data);
    }

    public function ajax_send_notification()
    {
        if (!has_permission('loyalty', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $data = [
            'title'        => trim($this->input->post('title')),
            'message'      => trim($this->input->post('message')),
            'type'         => $this->input->post('type') ?: 'info',
            'target'       => $this->input->post('target') ?: 'all',
            'target_tier'  => trim($this->input->post('target_tier') ?: ''),
            'customer_id'  => (int)$this->input->post('customer_id') ?: null,
            'promotion_id' => (int)$this->input->post('promotion_id') ?: null,
        ];

        if (empty($data['title']) || empty($data['message'])) {
            echo json_encode(['success' => false, 'message' => 'Title and message are required']);
            return;
        }

        $result   = $this->loyalty_model->send_notification($data);
        $response = ['success' => $result !== false, 'sent' => (int)$result];

        // Optional SMS blast
        if ($this->input->post('send_sms') == '1') {
            $this->load->library('loyalty/twilio_sms');
            if (!$this->twilio_sms->is_configured()) {
                $response['sms_error'] = 'Twilio is not configured. Go to SMS Settings to set it up.';
            } else {
                $phones  = $this->loyalty_model->get_member_phones($data['target'], $data['target_tier'], $data['customer_id']);
                $sms_body = $data['title'] . "\n" . $data['message'];
                @set_time_limit(120);
                $sms_result = $this->twilio_sms->send_bulk($phones, $sms_body);
                $response['sms_sent']   = $sms_result['sent'];
                $response['sms_failed'] = $sms_result['failed'];
            }
        }

        echo json_encode($response);
    }

    public function sms_settings()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            if (!has_permission('loyalty', '', 'edit')) {
                set_alert('danger', 'Access denied');
                redirect(admin_url('loyalty/sms_settings'));
            }
            update_option('twilio_account_sid',  trim($this->input->post('twilio_account_sid')));
            update_option('twilio_auth_token',   trim($this->input->post('twilio_auth_token')));
            update_option('twilio_from_number',  trim($this->input->post('twilio_from_number')));
            set_alert('success', 'SMS settings saved.');
            redirect(admin_url('loyalty/sms_settings'));
        }

        $data['title']             = 'SMS Settings';
        $data['twilio_account_sid'] = get_option('twilio_account_sid');
        $data['twilio_auth_token']  = get_option('twilio_auth_token');
        $data['twilio_from_number'] = get_option('twilio_from_number');
        $this->load->view('loyalty/admin/sms_settings', $data);
    }

    public function ajax_search_customers()
    {
        if (!has_permission('loyalty', '', 'view')) {
            echo json_encode(['rows' => []]);
            return;
        }

        $q    = trim($this->input->get('q') ?: '');
        $rows = $q ? $this->loyalty_model->get_customers($q, 1, 10) : [];

        // Slim the response
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'phone' => $r['phone']];
        }

        echo json_encode(['rows' => $out]);
    }

    public function ajax_delete_notification()
    {
        if (!has_permission('loyalty', '', 'delete')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id = (int)$this->input->post('id');
        echo json_encode(['success' => (bool)$this->loyalty_model->delete_notification($id)]);
    }

    // =========================================================================
    // All Transactions
    // =========================================================================

    public function transactions()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $filters = [
            'type'      => $this->input->get('type') ?: '',
            'date_from' => $this->input->get('date_from') ?: '',
            'date_to'   => $this->input->get('date_to') ?: '',
            'search'    => $this->input->get('q') ?: '',
            'limit'     => in_array((int)($this->input->get('limit') ?: 20), [10, 20, 50, 100])
                ? (int)($this->input->get('limit') ?: 20) : 20,
        ];
        $page = max(1, (int)($this->input->get('page') ?: 1));

        $total = $this->loyalty_model->count_all_transactions($filters);
        $rows  = $this->loyalty_model->get_all_transactions($filters, $page, $filters['limit']);

        $data['title']   = 'Loyalty Transactions';
        $data['rows']    = $rows;
        $data['filters'] = array_merge($filters, ['page' => $page]);
        $data['result']  = [
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $filters['limit'],
            'page_count' => max(1, (int)ceil($total / $filters['limit'])),
        ];

        $this->load->view('loyalty/admin/transactions', $data);
    }

    // =========================================================================
    // Reports
    // =========================================================================

    public function reports()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }
        redirect(admin_url('loyalty/reports/customers'));
    }

    public function reports_customers()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Loyalty Reports — Customers';
        $data['active_tab'] = 'customers';
        $data['warehouses'] = $this->_report_warehouses();
        $this->load->view('loyalty/admin/reports/customers', $data);
    }

    public function reports_promotions()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Loyalty Reports — Promotions';
        $data['active_tab'] = 'promotions';
        $data['warehouses'] = $this->_report_warehouses();
        $this->load->view('loyalty/admin/reports/promotions', $data);
    }

    public function reports_bundles()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Loyalty Reports — Bundles & Promos';
        $data['active_tab'] = 'bundles';
        $data['warehouses'] = $this->_report_warehouses();
        $this->load->view('loyalty/admin/reports/bundles', $data);
    }

    public function ajax_report_data()
    {
        if (!has_permission('loyalty', '', 'view')) {
            ajax_access_denied();
        }
        if (ob_get_level()) ob_end_clean();
        ob_start();
        header('Content-Type: application/json');

        try {
            $this->load->model('pos/pos_model');

            $section      = $this->input->post('section')      ?: 'customers';
            $date_from    = $this->input->post('date_from')    ?: date('Y-m-d');
            $date_to      = $this->input->post('date_to')      ?: date('Y-m-d');
            $warehouse_id = $this->input->post('warehouse_id') ?: null;

            $out = ['success' => true, 'section' => $section];

            switch ($section) {
                case 'customers':
                    $out['summary']   = $this->pos_model->get_report_customers_summary($date_from, $date_to, $warehouse_id);
                    $out['top']       = $this->pos_model->get_report_customers_top($date_from, $date_to, $warehouse_id);
                    $out['new_daily'] = $this->pos_model->get_report_customers_new_daily($date_from, $date_to, $warehouse_id);
                    $out['loyalty']   = $this->pos_model->get_report_loyalty_activity($date_from, $date_to, $warehouse_id);
                    break;
                case 'promotions':
                    $out['promotions']       = $this->pos_model->get_report_promotions($date_from, $date_to, $warehouse_id);
                    $out['discount_types']   = $this->pos_model->get_dashboard_discount_breakdown($date_from, $date_to, $warehouse_id);
                    $out['discounted_items'] = $this->pos_model->get_report_most_discounted_items($date_from, $date_to, $warehouse_id);
                    break;
                case 'bundles':
                    $out['crm_promos'] = $this->pos_model->get_report_crm_promo_feasibility($date_from, $date_to, $warehouse_id);
                    $out['pos_bundles'] = $this->pos_model->get_report_pos_bundle_feasibility();
                    break;
                default:
                    $out = ['success' => false, 'error' => 'Unknown report section'];
            }

            echo json_encode($out);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function _report_warehouses()
    {
        $this->load->model('pos/pos_model');
        return $this->db->select('warehouse_id, warehouse_name')
            ->from(db_prefix() . 'warehouse')
            ->where('active', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get()->result_array();
    }
}
