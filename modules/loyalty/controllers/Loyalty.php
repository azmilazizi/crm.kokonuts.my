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

    public function promotions()
    {
        if (!has_permission('loyalty', '', 'view')) {
            access_denied('loyalty');
        }

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = 20;
        $total    = $this->loyalty_model->count_promotions();
        $rows     = $this->loyalty_model->get_promotions(false, $page, $per_page);

        $data['title']   = 'Promotions';
        $data['rows']    = $rows;
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

        $id    = (int)$this->input->post('id');
        $title = trim($this->input->post('title'));

        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            return;
        }

        $fields = [
            'title'       => $title,
            'description' => trim($this->input->post('description')),
            'image_url'   => trim($this->input->post('image_url')),
            'type'        => $this->input->post('type') ?: 'announcement',
            'start_date'  => trim($this->input->post('start_date')) ?: null,
            'end_date'    => trim($this->input->post('end_date')) ?: null,
            'target_tier' => trim($this->input->post('target_tier')) ?: null,
            'is_active'   => (int)(bool)$this->input->post('is_active'),
        ];

        if ($id) {
            if (!has_permission('loyalty', '', 'edit')) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            $ok = $this->loyalty_model->update_promotion($id, $fields);
            echo json_encode(['success' => (bool)$ok, 'id' => $id]);
        } else {
            if (!has_permission('loyalty', '', 'create')) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            $new_id = $this->loyalty_model->create_promotion($fields);
            echo json_encode(['success' => (bool)$new_id, 'id' => $new_id]);
        }
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

        $result = $this->loyalty_model->send_notification($data);
        echo json_encode(['success' => $result !== false, 'sent' => (int)$result]);
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
