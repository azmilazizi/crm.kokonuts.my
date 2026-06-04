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

        $total = $this->loyalty_model->count_customers($search);
        $rows  = $this->loyalty_model->get_customers($search, $page, $per_page);
        $stats = $this->loyalty_model->get_stats();

        $data['title']   = 'Loyalty Members';
        $data['rows']    = $rows;
        $data['stats']   = $stats;
        $data['filters'] = compact('search', 'page', 'per_page');
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
}
