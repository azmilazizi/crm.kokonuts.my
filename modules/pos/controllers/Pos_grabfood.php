<?php defined('BASEPATH') or exit('No direct script access allowed');

class Pos_grabfood extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pos/pos_grabfood_model');
    }

    // =========================================================================
    // Orders List
    // =========================================================================

    public function orders()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $warehouses = $this->db
            ->select('warehouse_id as id, warehouse_name as name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();

        $filters = [
            'warehouse_id' => $this->input->get('store')     ?: null,
            'status'       => $this->input->get('status')    ?: '',
            'date_from'    => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days')),
            'date_to'      => $this->input->get('date_to')   ?: date('Y-m-d'),
            'search'       => $this->input->get('q')         ?: '',
            'page'         => $this->input->get('page')      ?: 1,
            'limit'        => $this->input->get('limit')     ?: 20,
        ];

        $result = $this->pos_grabfood_model->get_orders($filters);

        // Last sync time for the selected warehouse
        $last_sync = null;
        if ($filters['warehouse_id']) {
            $s = $this->pos_grabfood_model->get_settings((int)$filters['warehouse_id']);
            $last_sync = $s['last_sync_at'] ?? null;
        }

        $data['title']      = 'GrabFood Orders';
        $data['warehouses'] = $warehouses;
        $data['filters']    = $filters;
        $data['result']     = $result;
        $data['last_sync']  = $last_sync;
        $this->load->view('pos/admin/grabfood_orders', $data);
    }

    // =========================================================================
    // Order Detail
    // =========================================================================

    public function order($id)
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $order = $this->pos_grabfood_model->get_order((int)$id);
        if (!$order) {
            show_404();
        }

        $data['title'] = 'GrabFood Order ' . htmlspecialchars($order['order_short_ref'] ?: $order['grabfood_order_id']);
        $data['order'] = $order;
        $this->load->view('pos/admin/grabfood_order_detail', $data);
    }

    // =========================================================================
    // AJAX — Sync Orders
    // =========================================================================

    public function ajax_sync()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }

        if (ob_get_level()) ob_end_clean();
        ob_start();
        header('Content-Type: application/json');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        $date_from    = $this->input->post('date_from') ?: null;
        $date_to      = $this->input->post('date_to')   ?: null;

        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'error' => 'Store is required.']);
            return;
        }

        $result = $this->pos_grabfood_model->sync_orders($warehouse_id, $date_from, $date_to);
        echo json_encode($result);
    }

    // =========================================================================
    // AJAX — Save Settings
    // =========================================================================

    public function ajax_save_settings()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        header('Content-Type: application/json');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required.']);
            return;
        }

        $result = $this->pos_grabfood_model->save_settings($warehouse_id, [
            'client_id'         => $this->input->post('client_id'),
            'client_secret'     => $this->input->post('client_secret'),
            'partner_id'        => $this->input->post('partner_id'),
            'grabfood_store_id' => $this->input->post('grabfood_store_id'),
            'environment'       => $this->input->post('environment'),
            'active'            => $this->input->post('active') ? 1 : 0,
        ]);

        echo json_encode(['success' => (bool)$result]);
    }

    // =========================================================================
    // AJAX — Test Connection
    // =========================================================================

    public function ajax_test_connection()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        header('Content-Type: application/json');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'error' => 'Store is required.']);
            return;
        }

        // Save current form values before testing so token fetch uses them
        $this->pos_grabfood_model->save_settings($warehouse_id, [
            'client_id'         => $this->input->post('client_id'),
            'client_secret'     => $this->input->post('client_secret'),
            'partner_id'        => $this->input->post('partner_id'),
            'grabfood_store_id' => $this->input->post('grabfood_store_id'),
            'environment'       => $this->input->post('environment'),
            'active'            => 1,
        ]);

        $result = $this->pos_grabfood_model->test_connection($warehouse_id);
        echo json_encode($result);
    }

    // =========================================================================
    // AJAX — Order Action (accept / cancel / ready)
    // =========================================================================

    public function ajax_order_action()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        header('Content-Type: application/json');

        $action              = $this->input->post('action');
        $warehouse_id        = (int)$this->input->post('warehouse_id');
        $grabfood_order_id   = trim($this->input->post('grabfood_order_id'));
        $reason              = trim($this->input->post('reason') ?: '');

        if (!$warehouse_id || !$grabfood_order_id || !$action) {
            echo json_encode(['success' => false, 'error' => 'Missing required parameters.']);
            return;
        }

        switch ($action) {
            case 'accept':
                $result = $this->pos_grabfood_model->accept_order($warehouse_id, $grabfood_order_id);
                break;
            case 'cancel':
                $result = $this->pos_grabfood_model->cancel_order($warehouse_id, $grabfood_order_id, $reason);
                break;
            case 'ready':
                $result = $this->pos_grabfood_model->mark_order_ready($warehouse_id, $grabfood_order_id);
                break;
            default:
                $result = ['success' => false, 'error' => 'Unknown action.'];
        }

        echo json_encode($result);
    }

    // =========================================================================
    // AJAX — Dashboard Data (GrabFood-specific analytics)
    // =========================================================================

    public function ajax_dashboard_data()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }

        if (ob_get_level()) ob_end_clean();
        ob_start();
        header('Content-Type: application/json');

        try {
            $date_from    = $this->input->post('date_from')    ?: date('Y-m-d');
            $date_to      = $this->input->post('date_to')      ?: date('Y-m-d');
            $warehouse_id = $this->input->post('warehouse_id') ?: null;

            echo json_encode([
                'success' => true,
                'summary' => $this->pos_grabfood_model->get_summary($date_from, $date_to, $warehouse_id),
                'daily'   => $this->pos_grabfood_model->get_daily_trend($date_from, $date_to, $warehouse_id),
                'items'   => $this->pos_grabfood_model->get_top_items($date_from, $date_to, $warehouse_id),
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // Redirect from POS transactions list — looks up GF order by receipt number
    // =========================================================================

    public function by_receipt($receipt_number)
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $receipt_number = urldecode($receipt_number);

        $row = $this->db
            ->where('order_short_ref', $receipt_number)
            ->or_where('grabfood_order_id', $receipt_number)
            ->get(db_prefix() . 'pos_grabfood_orders')
            ->row_array();

        if ($row) {
            redirect(admin_url('pos/grabfood_order/' . (int)$row['id']));
        } else {
            redirect(admin_url('pos/grabfood_orders?q=' . urlencode($receipt_number)));
        }
    }

    // =========================================================================
    // Export CSV
    // =========================================================================

    public function export_csv()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $filters = [
            'warehouse_id' => $this->input->get('store')     ?: null,
            'status'       => $this->input->get('status')    ?: '',
            'date_from'    => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days')),
            'date_to'      => $this->input->get('date_to')   ?: date('Y-m-d'),
            'search'       => $this->input->get('q')         ?: '',
            'page'         => 1,
            'limit'        => 5000,
        ];

        $result = $this->pos_grabfood_model->get_orders($filters);
        $rows   = $result['data'];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="grabfood_orders_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Date', 'GF Order No.', 'Store', 'Status', 'Type', 'Customer', 'Subtotal', 'Discount', 'Delivery Fee', 'Total']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['created_at'],
                $r['order_short_ref'] ?: $r['grabfood_order_id'],
                $r['warehouse_name'],
                $r['order_status'],
                $r['order_type'] ?: '—',
                $r['customer_name'] ?: '—',
                number_format($r['subtotal'], 2),
                number_format($r['discount'], 2),
                number_format($r['delivery_fee'], 2),
                number_format($r['total'], 2),
            ]);
        }
        fclose($out);
        exit;
    }
}
