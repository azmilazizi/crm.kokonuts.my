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

    public function announcements()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }
        $data['title'] = 'Announcement';
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

        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            return;
        }

        $members = $this->db->select('id, name, phone, birthday, total_points')
            ->where("phone != ''")
            ->get(db_prefix() . 'pos_loyalty_customers')->result_array();

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

        foreach ($members as $m) {
            $p_title = $this->_substitute_vars($title, $m);
            $p_body  = $this->_substitute_vars($body, $m);

            if ($push) {
                $this->loyalty_model->send_notification([
                    'title'       => $p_title,
                    'message'     => $p_body,
                    'type'        => 'info',
                    'target'      => 'individual',
                    'customer_id' => (int)$m['id'],
                ]);
                $result['push_sent']++;
            }

            if ($sms_on && $sms_ready && !empty($m['phone'])) {
                $text = $p_title . ($p_body ? "\n" . $p_body : '');
                $res  = $this->twilio_sms->send($m['phone'], $text);
                $res === true ? $result['sms_sent']++ : $result['sms_failed']++;
            }
        }

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
            'title'               => $title,
            'description'         => trim($this->input->post('description')),
            'image_url'           => trim($this->input->post('image_url')),
            'type'                => $this->input->post('type') ?: 'promotion',
            'start_date'          => trim($this->input->post('start_date')) ?: null,
            'end_date'            => null,
            'is_active'           => 1,
            'trigger_type'        => $trigger_type,
            'target'              => $is_recurring ? 'all' : $target,
            'target_tier'         => trim($this->input->post('target_tier')) ?: null,
            'target_customer_id'  => ($target === 'individual') ? (trim($this->input->post('target_customer_id') ?: '') ?: null) : null,
            'notify_push'         => $notify_push,
            'notify_sms'          => $notify_sms,
            'notify_days_before'  => $days_before,
            'notify_status'       => $notify_status,
            'signup_recurrence'   => $this->input->post('signup_recurrence') ?: 'annual',
            'stale_days'          => max(1, (int)($this->input->post('stale_days') ?: 90)),
            'birthday_start_date' => trim($this->input->post('birthday_start_date')) ?: null,
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

        $trigger    = $promo['trigger_type'] ?? 'standard';
        $is_tracked = in_array($trigger, ['birthday', 'signup_freebies', 'stale_points']);
        $this_year  = (int)date('Y');

        // Filter out members already claimed this year (for annual triggers)
        $recipients = [];
        foreach ($candidates as $r) {
            if ($is_tracked) {
                // signup_freebies with recurrence=once uses year 0 as sentinel
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

        $is_recurring = in_array($trigger, ['birthday', 'signup_freebies', 'stale_points']);
        $notify_push  = !empty($promo['notify_push']);
        $notify_sms   = !empty($promo['notify_sms']);
        $sms_ready    = false;

        if ($notify_sms) {
            $this->load->library('loyalty/twilio_sms');
            $sms_ready = $this->twilio_sms->is_configured();
            if (!$sms_ready) $result['sms_error'] = 'Twilio not configured';
        }

        @set_time_limit(180);

        foreach ($recipients as $r) {
            $p_title = $this->_substitute_vars($title,       $r);
            $p_body  = $this->_substitute_vars($description, $r);

            if ($notify_push) {
                $this->loyalty_model->send_notification([
                    'title'        => $p_title,
                    'message'      => $p_body,
                    'type'         => 'promo',
                    'target'       => 'individual',
                    'customer_id'  => (int)$r['id'],
                    'promotion_id' => (int)$promo_id,
                ]);
                $result['push_sent']++;
            }

            if ($notify_sms && $sms_ready && !empty($r['phone'])) {
                $sms_body = $p_title . ($p_body ? "\n" . $p_body : '');
                $res      = $this->twilio_sms->send($r['phone'], $sms_body);
                $res === true ? $result['sms_sent']++ : $result['sms_failed']++;
            }

            // Record claim so this member isn't re-blasted for the same promo this year
            if ($is_tracked) {
                $claim_year = ($trigger === 'signup_freebies' && ($promo['signup_recurrence'] ?? 'annual') === 'once')
                    ? 0
                    : $this_year;
                $this->loyalty_model->record_promo_claim($promo_id, $r['id'], $claim_year);
            }
        }

        $this->loyalty_model->mark_promo_notified($promo_id, $is_recurring);
        return $result;
    }

    private function _substitute_vars($template, $member)
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
            ['{{firstname}}', '{{lastname}}', '{{name}}', '{{birthday}}', '{{points}}', '{{phone}}', '{{tier}}', '{{signup_date}}'],
            [$firstname,      $lastname,      $name,      $birthday,      $points,      $phone,      $tier,      $signup_date],
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
