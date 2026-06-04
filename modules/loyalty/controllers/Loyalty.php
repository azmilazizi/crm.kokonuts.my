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
        redirect(admin_url('loyalty/customers'));
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
