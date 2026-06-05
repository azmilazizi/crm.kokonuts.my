<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pos extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        redirect(admin_url('pos/dashboard'));
    }

    // =========================================================================
    // Dashboard
    // =========================================================================

    public function dashboard()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'POS Dashboard';
        $data['warehouses'] = $this->db
            ->select('warehouse_id, warehouse_name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();
        $this->load->view('pos/admin/dashboard', $data);
    }

    public function ajax_dashboard_data()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }

        // Discard any stray output (notices, debug) that would corrupt JSON
        if (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();

        header('Content-Type: application/json');

        try {
            $this->load->model('pos/pos_model');

            $date_from    = $this->input->post('date_from')    ?: date('Y-m-d');
            $date_to      = $this->input->post('date_to')      ?: date('Y-m-d');
            $prev_from    = $this->input->post('prev_from')    ?: date('Y-m-d', strtotime('-1 day'));
            $prev_to      = $this->input->post('prev_to')      ?: date('Y-m-d', strtotime('-1 day'));
            $warehouse_id = $this->input->post('warehouse_id') ?: null;

            echo json_encode([
                'success'  => true,
                'summary'  => $this->pos_model->get_dashboard_summary($date_from, $date_to, $warehouse_id),
                'previous' => $this->pos_model->get_dashboard_summary($prev_from, $prev_to, $warehouse_id),
                'daily'    => $this->pos_model->get_dashboard_daily_trend($date_from, $date_to, $warehouse_id),
                'hourly'   => $this->pos_model->get_dashboard_hourly($date_from, $date_to, $warehouse_id),
                'products' => $this->pos_model->get_dashboard_top_products($date_from, $date_to, $warehouse_id),
                'payments' => $this->pos_model->get_dashboard_payments($date_from, $date_to, $warehouse_id),
                'shifts'   => $this->pos_model->get_dashboard_recent_shifts($warehouse_id),
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // Products
    // =========================================================================

    public function products()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $items = $this->db
            ->select('i.id, i.sku_name, i.sku_code, i.rate, i.active, g.name as group_name, sg.sub_group_name')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'items_groups g', 'g.id = i.group_id', 'left')
            ->join(db_prefix() . 'wh_sub_group sg', 'sg.id = i.sub_group', 'left')
            ->where('i.can_be_sold', 'can_be_sold')
            ->where('i.can_be_manufacturing', 'can_be_manufacturing')
            ->where('i.parent_id IS NULL')
            ->order_by('i.sku_name', 'ASC')
            ->get()->result_array();

        $this->load->model('pos/pos_model');
        $data['title']           = 'POS Products';
        $data['items']           = $items;
        $data['modifier_groups'] = $this->pos_model->get_modifier_groups();
        $this->load->view('pos/admin/products', $data);
    }

    public function ajax_get_item_modifiers($item_id)
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        echo json_encode([
            'success' => true,
            'data'    => $this->pos_model->get_item_modifier_groups($item_id),
        ]);
    }

    public function ajax_assign_modifier_group()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id           = $this->input->post('item_id');
        $modifier_group_id = (int)$this->input->post('modifier_group_id');
        $sort_order        = (int)$this->input->post('sort_order');

        if (!$item_id || !$modifier_group_id) {
            echo json_encode(['success' => false, 'message' => 'item_id and modifier_group_id are required']);
            return;
        }
        $result = $this->pos_model->assign_modifier_group($item_id, $modifier_group_id, $sort_order);
        $assigned = $this->pos_model->get_item_modifier_groups($item_id);
        echo json_encode(['success' => $result, 'data' => $assigned]);
    }

    public function ajax_unassign_modifier_group()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id           = $this->input->post('item_id');
        $modifier_group_id = (int)$this->input->post('modifier_group_id');
        $result = $this->pos_model->unassign_modifier_group($item_id, $modifier_group_id);
        $assigned = $this->pos_model->get_item_modifier_groups($item_id);
        echo json_encode(['success' => $result, 'data' => $assigned]);
    }

    public function ajax_get_item_individual_modifiers($item_id)
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        echo json_encode([
            'success' => true,
            'data'    => $this->pos_model->get_item_modifiers($item_id),
        ]);
    }

    public function ajax_save_item_modifier()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id = $this->input->post('item_id');
        $id      = (int)$this->input->post('id') ?: null;
        $name    = trim($this->input->post('name'));

        if (!$item_id || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'item_id and name are required']);
            return;
        }

        $options_raw = $this->input->post('options');
        $options     = is_array($options_raw) ? $options_raw : [];

        $saved_id = $this->pos_model->save_item_modifier($item_id, [
            'name'           => $name,
            'selection_type' => $this->input->post('selection_type'),
            'sort_order'     => $this->input->post('sort_order'),
            'options'        => $options,
        ], $id);

        echo json_encode([
            'success' => (bool)$saved_id,
            'data'    => $this->pos_model->get_item_modifiers($item_id),
        ]);
    }

    public function ajax_delete_item_modifier()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id = $this->input->post('item_id');
        $id      = (int)$this->input->post('id');
        $result  = $this->pos_model->delete_item_modifier($id, $item_id);
        echo json_encode([
            'success' => $result,
            'data'    => $this->pos_model->get_item_modifiers($item_id),
        ]);
    }

    // =========================================================================
    // Modifiers
    // =========================================================================

    public function modifiers()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']  = 'Modifiers';
        $data['groups'] = $this->pos_model->get_modifier_groups();
        $this->load->view('pos/admin/modifiers', $data);
    }

    public function modifier_form($id = null)
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $group = $id ? $this->pos_model->get_modifier_group($id) : null;
        if ($id && !$group) {
            show_404();
        }

        $all_items = $this->db
            ->select('i.id, i.sku_name, i.sku_code')
            ->from(db_prefix() . 'items i')
            ->where('i.can_be_sold', 'can_be_sold')
            ->where('i.can_be_manufacturing', 'can_be_manufacturing')
            ->where('i.parent_id IS NULL')
            ->where('i.active', 1)
            ->order_by('i.sku_name', 'ASC')
            ->get()->result_array();

        $data['title']        = $group ? 'Edit Modifier' : 'Add Modifier';
        $data['group']        = $group;
        $data['all_items']    = $all_items;
        $data['linked_items'] = $group ? $this->pos_model->get_modifier_group_items($group['id']) : [];
        $this->load->view('pos/admin/modifier_form', $data);
    }

    public function ajax_assign_items_to_modifier()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $modifier_group_id = (int)$this->input->post('modifier_group_id');
        $item_ids          = $this->input->post('item_ids');

        if (!$modifier_group_id || empty($item_ids) || !is_array($item_ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected']);
            return;
        }

        $this->pos_model->assign_items_to_modifier_group($modifier_group_id, $item_ids);
        $linked = $this->pos_model->get_modifier_group_items($modifier_group_id);
        echo json_encode(['success' => true, 'data' => $linked]);
    }

    public function ajax_unassign_item_from_modifier()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $modifier_group_id = (int)$this->input->post('modifier_group_id');
        $item_id           = $this->input->post('item_id');
        $result            = $this->pos_model->unassign_item_from_modifier_group($modifier_group_id, $item_id);
        $linked            = $this->pos_model->get_modifier_group_items($modifier_group_id);
        echo json_encode(['success' => $result, 'data' => $linked]);
    }

    public function ajax_save_modifier_form()
    {
        if (!has_permission('pos', '', 'create') && !has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $id      = (int)$this->input->post('id') ?: null;
        $name    = trim($this->input->post('name'));
        $options = $this->input->post('options') ?: [];

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Modifier name is required']);
            return;
        }

        $data = [
            'name'            => $name,
            'selection_type'  => $this->input->post('selection_type') ?: 'single',
            'min_selections'  => (int)$this->input->post('min_selections'),
            'max_selections'  => (int)$this->input->post('max_selections') ?: 1,
            'active'          => 1,
            'options'         => $options,
        ];

        $result = $this->pos_model->save_modifier_with_options($data, $id);
        echo json_encode(['success' => (bool)$result, 'id' => $result]);
    }

    public function ajax_delete_modifier_group($id)
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        echo json_encode(['success' => $this->pos_model->delete_modifier_group($id)]);
    }

    public function ajax_delete_modifier_groups_bulk()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $ids = $this->input->post('ids');
        if (empty($ids) || !is_array($ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected']);
            return;
        }
        echo json_encode(['success' => $this->pos_model->delete_modifier_groups_bulk($ids)]);
    }

    // =========================================================================
    // API Tokens
    // =========================================================================

    public function api_tokens()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $tokens = $this->db
            ->select('t.*, CONCAT(s.firstname, " ", s.lastname) as staff_name, w.warehouse_name')
            ->from(db_prefix() . 'pos_api_tokens t')
            ->join(db_prefix() . 'staff s', 's.staffid = t.staff_id', 'left')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = t.warehouse_id', 'left')
            ->order_by('t.created_at', 'DESC')
            ->get()->result_array();

        $data['title']      = 'POS API Tokens';
        $data['tokens']     = $tokens;
        $data['warehouses'] = $this->db->select('warehouse_id, warehouse_name, warehouse_code')->where('display', 1)->order_by('warehouse_name', 'ASC')->get(db_prefix() . 'warehouse')->result_array();
        $data['staff']      = $this->db->select('staffid, firstname, lastname, email')->where('active', 1)->order_by('firstname', 'ASC')->get(db_prefix() . 'staff')->result_array();
        $this->load->view('pos/admin/api_tokens', $data);
    }

    public function ajax_generate_token()
    {
        if (!has_permission('pos', '', 'create')) {
            ajax_access_denied();
        }

        $staff_id     = (int) $this->input->post('staff_id');
        $warehouse_id = (int) $this->input->post('warehouse_id');
        $name         = $this->input->post('name');

        if (!$staff_id || !$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Staff member and warehouse are required']);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->db->insert(db_prefix() . 'pos_api_tokens', [
            'token'        => $token,
            'name'         => $name ?: null,
            'staff_id'     => $staff_id,
            'warehouse_id' => $warehouse_id,
            'active'       => 1,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        echo json_encode(['success' => true, 'token' => $token, 'id' => $this->db->insert_id()]);
    }

    public function ajax_toggle_token($id)
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $row = $this->db->get_where(db_prefix() . 'pos_api_tokens', ['id' => $id])->row();
        if (!$row) {
            echo json_encode(['success' => false]);
            return;
        }
        $new_active = (int) $row->active === 1 ? 0 : 1;
        $this->db->where('id', $id)->update(db_prefix() . 'pos_api_tokens', ['active' => $new_active]);
        echo json_encode(['success' => true, 'active' => $new_active]);
    }

    public function ajax_delete_token($id)
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'pos_api_tokens');
        echo json_encode(['success' => (bool) $this->db->affected_rows()]);
    }

    // =========================================================================
    // Transactions
    // =========================================================================

    public function transactions()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $warehouses = $this->db
            ->select('warehouse_id as id, warehouse_name as name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();

        $filters = [
            'warehouse_id' => $this->input->get('store')     ?: null,
            'date_from'    => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days')),
            'date_to'      => $this->input->get('date_to')   ?: date('Y-m-d'),
            'search'       => $this->input->get('q')         ?: '',
            'page'         => $this->input->get('page')      ?: 1,
            'limit'        => $this->input->get('limit')     ?: 20,
        ];

        $result = $this->pos_model->get_transactions($filters);

        $data['title']      = 'Transactions';
        $data['warehouses'] = $warehouses;
        $data['filters']    = $filters;
        $data['result']     = $result;
        $this->load->view('pos/admin/transactions', $data);
    }

    public function transaction($receipt_number)
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $receipt = $this->pos_model->get_receipt($receipt_number);
        if (!$receipt) {
            show_404();
        }

        $data['title']   = 'Transaction ' . $receipt_number;
        $data['receipt'] = $receipt;
        $this->load->view('pos/admin/transaction_detail', $data);
    }

    public function ajax_delete_transaction()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $id = (int)$this->input->post('id');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            return;
        }
        echo json_encode(['success' => $this->pos_model->delete_transaction($id)]);
    }

    public function export_transactions_csv()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $filters = [
            'warehouse_id' => $this->input->get('store')     ?: null,
            'date_from'    => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days')),
            'date_to'      => $this->input->get('date_to')   ?: date('Y-m-d'),
            'search'       => $this->input->get('q')         ?: '',
            'page'         => 1,
            'limit'        => 5000,
        ];

        $result = $this->pos_model->get_transactions($filters);
        $rows   = $result['data'];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="transactions_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel
        fputcsv($out, ['Time', 'Receipt No.', 'Shift', 'Store', 'Employee', 'Type', 'Order Type', 'Subtotal', 'Discount', 'Tax', 'Total']);

        foreach ($rows as $r) {
            $type = !empty($r['cancelled_at']) ? 'Cancelled' : (!empty($r['refund_for']) ? 'Return' : 'Sale');
            fputcsv($out, [
                $r['receipt_date'],
                $r['receipt_number'],
                $r['shift_id'] ?: '—',
                $r['warehouse_name'],
                $r['employee_name'] ?: '—',
                $type,
                $r['dining_option'] ?: '—',
                number_format($r['subtotal'], 2),
                number_format($r['total_discount'], 2),
                number_format($r['total_tax'], 2),
                number_format($r['total_money'], 2),
            ]);
        }
        fclose($out);
        exit;
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public function settings($section = 'receipt')
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $warehouses = $this->db
            ->select('warehouse_id as id, warehouse_name as name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();

        $data['title']      = 'POS Settings';
        $data['section']    = $section;
        $data['warehouses'] = $warehouses;

        $warehouse_id = (int)($this->input->get('store') ?: ($warehouses[0]['id'] ?? 0));
        $data['warehouse_id'] = $warehouse_id;

        if ($section === 'receipt') {
            $data['receipt_settings'] = $warehouse_id ? $this->pos_model->get_receipt_settings($warehouse_id) : [];
            $data['cfd_settings']     = [];
            $data['cfd_media_items']  = [];
            $data['payment_modes']    = [];
        } elseif ($section === 'cfd') {
            $data['receipt_settings']  = [];
            $data['cfd_settings']      = $warehouse_id ? ($this->pos_model->get_cfd_settings($warehouse_id) ?: []) : [];
            $data['cfd_media_items']   = $warehouse_id ? $this->pos_model->get_cfd_media_items($warehouse_id) : [];
            $data['payment_modes']     = [];
        } elseif ($section === 'payment_modes') {
            $data['receipt_settings'] = [];
            $data['cfd_settings']     = [];
            $data['cfd_media_items']  = [];
            $data['payment_modes']    = $this->pos_model->get_payment_modes_with_pos_status();
        } else {
            $data['receipt_settings'] = [];
            $data['cfd_settings']     = [];
            $data['cfd_media_items']  = [];
            $data['payment_modes']    = [];
        }

        $this->load->view('pos/admin/settings', $data);
    }

    public function ajax_save_receipt_settings()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required']);
            return;
        }

        $result = $this->pos_model->save_receipt_settings($warehouse_id, [
            'company_name'   => $this->input->post('company_name'),
            'company_reg_id' => $this->input->post('company_reg_id'),
            'address'        => $this->input->post('address'),
            'phone'          => $this->input->post('phone'),
            'header'         => $this->input->post('header'),
            'footer'         => $this->input->post('footer'),
        ]);
        echo json_encode(['success' => $result]);
    }

    public function ajax_upload_receipt_logo()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required']);
            return;
        }

        if (empty($_FILES['logo']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $this->load->model('pos/pos_model');

        $existing = $this->pos_model->get_receipt_settings($warehouse_id);
        if ($existing && !empty($existing['logo'])) {
            $old_path = FCPATH . $existing['logo'];
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }

        $upload_dir = FCPATH . 'uploads/pos/logos/' . $warehouse_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $this->load->library('upload', [
            'upload_path'   => $upload_dir,
            'allowed_types' => 'jpg|jpeg|png|gif|webp',
            'max_size'      => 2048,
            'encrypt_name'  => true,
        ]);

        if (!$this->upload->do_upload('logo')) {
            echo json_encode(['success' => false, 'message' => $this->upload->display_errors('', '')]);
            return;
        }

        $info     = $this->upload->data();
        $rel_path = 'uploads/pos/logos/' . $warehouse_id . '/' . $info['file_name'];

        $this->pos_model->save_receipt_settings($warehouse_id, ['logo' => $rel_path]);
        echo json_encode(['success' => true, 'logo_url' => base_url($rel_path)]);
    }

    public function ajax_delete_receipt_logo()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        $warehouse_id = (int)$this->input->post('warehouse_id');
        $this->load->model('pos/pos_model');
        $existing = $this->pos_model->get_receipt_settings($warehouse_id);

        if ($existing && !empty($existing['logo'])) {
            $path = FCPATH . $existing['logo'];
            if (file_exists($path)) {
                unlink($path);
            }
            $this->pos_model->save_receipt_settings($warehouse_id, ['logo' => null]);
        }

        echo json_encode(['success' => true]);
    }

    // =========================================================================
    // CFD Settings
    // =========================================================================

    public function ajax_save_cfd_settings()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required']);
            return;
        }

        $allowed_types = ['static_image', 'slideshow', 'video', 'playlist'];
        $display_type  = $this->input->post('display_type');
        if (!in_array($display_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid display type']);
            return;
        }

        $slide_duration = max(1, (int)$this->input->post('slide_duration'));

        $this->pos_model->save_cfd_settings($warehouse_id, [
            'display_type'   => $display_type,
            'slide_duration' => $slide_duration,
        ]);
        echo json_encode(['success' => true]);
    }

    public function ajax_upload_cfd_media()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required']);
            return;
        }

        if (empty($_FILES['media']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $this->load->model('pos/pos_model');

        $mime       = $_FILES['media']['type'];
        $is_video   = strpos($mime, 'video/') === 0;
        $media_type = $is_video ? 'video' : 'image';

        $upload_dir = FCPATH . 'uploads/pos/cfd/' . $warehouse_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $this->load->library('upload', [
            'upload_path'   => $upload_dir,
            'allowed_types' => 'jpg|jpeg|png|gif|webp|mp4|mov|webm',
            'max_size'      => 51200, // 50 MB
            'encrypt_name'  => true,
        ]);

        if (!$this->upload->do_upload('media')) {
            echo json_encode(['success' => false, 'message' => $this->upload->display_errors('', '')]);
            return;
        }

        $info     = $this->upload->data();
        $rel_path = 'uploads/pos/cfd/' . $warehouse_id . '/' . $info['file_name'];

        $insert_id = $this->pos_model->add_cfd_media_item($warehouse_id, [
            'media_type' => $media_type,
            'file_path'  => $rel_path,
        ]);

        echo json_encode([
            'success'    => true,
            'id'         => $insert_id,
            'media_type' => $media_type,
            'url'        => base_url($rel_path),
        ]);
    }

    public function ajax_delete_cfd_media()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $id           = (int)$this->input->post('id');
        $warehouse_id = (int)$this->input->post('warehouse_id');

        $items = $this->pos_model->get_cfd_media_items($warehouse_id);
        foreach ($items as $item) {
            if ((int)$item['id'] === $id) {
                $path = FCPATH . $item['file_path'];
                if (file_exists($path)) {
                    unlink($path);
                }
                break;
            }
        }

        $this->pos_model->delete_cfd_media_item($id, $warehouse_id);
        echo json_encode(['success' => true]);
    }

    public function ajax_reorder_cfd_media()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        $ids          = $this->input->post('ids');
        if (!is_array($ids)) {
            echo json_encode(['success' => false]);
            return;
        }

        $this->pos_model->reorder_cfd_media_items($warehouse_id, $ids);
        echo json_encode(['success' => true]);
    }

    public function ajax_toggle_payment_mode()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $payment_mode_id = (int)$this->input->post('payment_mode_id');
        $enabled         = $this->input->post('enabled');

        if (!$payment_mode_id) {
            echo json_encode(['success' => false, 'message' => 'payment_mode_id is required']);
            return;
        }

        $this->pos_model->toggle_payment_mode_for_pos($payment_mode_id, $enabled);
        echo json_encode(['success' => true]);
    }

    // =========================================================================
    // Import Modifier Groups
    // =========================================================================

    public function import_modifier_groups()
    {
        if (!has_permission('pos', '', 'create')) {
            access_denied('pos');
        }
        $data['title'] = 'Import Modifier Groups';
        $this->load->view('pos/admin/import_modifier_groups', $data);
    }

    public function download_modifier_groups_sample()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        require_once(module_dir_path('warehouse') . '/assets/plugins/XLSXWriter/xlsxwriter.class.php');

        $writer = new XLSXWriter();

        $header = [
            '(*)Group Name'          => 'string',
            '(*)Selection Type'      => 'string',
            'Min Selections'         => 'integer',
            'Max Selections'         => 'integer',
            'Option 1 Name'          => 'string',
            'Option 1 Price'         => 'price',
            'Option 2 Name'          => 'string',
            'Option 2 Price'         => 'price',
            'Option 3 Name'          => 'string',
            'Option 3 Price'         => 'price',
            'Option 4 Name'          => 'string',
            'Option 4 Price'         => 'price',
            'Option 5 Name'          => 'string',
            'Option 5 Price'         => 'price',
            'Option 6 Name'          => 'string',
            'Option 6 Price'         => 'price',
            'Option 7 Name'          => 'string',
            'Option 7 Price'         => 'price',
            'Option 8 Name'          => 'string',
            'Option 8 Price'         => 'price',
            'Option 9 Name'          => 'string',
            'Option 9 Price'         => 'price',
            'Option 10 Name'         => 'string',
            'Option 10 Price'        => 'price',
            'Linked Item SKU 1'      => 'string',
            'Linked Item SKU 2'      => 'string',
            'Linked Item SKU 3'      => 'string',
            'Linked Item SKU 4'      => 'string',
            'Linked Item SKU 5'      => 'string',
        ];

        $widths = array_fill(0, count($header), 22);

        $col_style = array_keys(array_fill(0, count($header), 0));
        $header_style = [
            'widths'       => $widths,
            'fill'         => '#1e88e5',
            'font-style'   => 'bold',
            'color'        => '#ffffff',
            'border'       => 'left,right,top,bottom',
            'border-color' => '#0a0a0a',
            'font-size'    => 12,
        ];

        $writer->writeSheetHeader_v2('Modifier Groups', $header, ['widths' => $widths], $col_style, $header_style);

        // Sample rows
        $writer->writeSheetRow('Modifier Groups', [
            'Sugar Level', 'single', 0, 1,
            'No Sugar', 0, 'Less Sugar', 0, 'Normal', 0, 'Extra Sugar', 0,
            '', 0, '', 0, '', 0, '', 0, '', 0, '', 0,
            'ITEM-001', 'ITEM-002', '', '', '',
        ]);
        $writer->writeSheetRow('Modifier Groups', [
            'Add-ons', 'multiple', 0, 3,
            'Cheese', 1.50, 'Bacon', 2.00, 'Egg', 1.00, 'Mushroom', 1.50,
            '', 0, '', 0, '', 0, '', 0, '', 0, '', 0,
            'ITEM-001', '', '', '', '',
        ]);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="modifier_groups_template.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->writeToStdOut();
        exit;
    }

    public function import_file_modifier_groups()
    {
        if (!has_permission('pos', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        if (!class_exists('XLSXReader_fin')) {
            require_once(module_dir_path('warehouse') . '/assets/plugins/XLSXReader/XLSXReader.php');
        }

        $this->load->model('pos/pos_model');

        if (empty($_FILES['file_xlsx']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $tmpFilePath = $_FILES['file_xlsx']['tmp_name'];
        if (empty($tmpFilePath)) {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
            return;
        }

        $ext = strtolower(pathinfo($_FILES['file_xlsx']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            echo json_encode(['success' => false, 'message' => 'Only .xlsx files are supported']);
            return;
        }

        $tmpDir     = TEMP_FOLDER . '/' . time() . uniqid() . '/';
        if (!file_exists(TEMP_FOLDER)) {
            mkdir(TEMP_FOLDER, 0755, true);
        }
        mkdir($tmpDir, 0755, true);
        $newFilePath = $tmpDir . basename($_FILES['file_xlsx']['name']);
        move_uploaded_file($tmpFilePath, $newFilePath);

        $xlsx   = new XLSXReader_fin($newFilePath);
        $sheets = $xlsx->getSheetNames();
        $cells  = $xlsx->getSheet($sheets[0])->getData();

        $update_existing = (int)$this->input->post('update_existing');
        $total   = 0;
        $success = 0;
        $errors  = [];

        // Row 0 is the header; data starts at row 1
        foreach ($cells as $row_index => $row) {
            if ($row_index === 0) continue;

            $group_name = isset($row[0]) ? trim((string)$row[0]) : '';
            if ($group_name === '') continue;

            $total++;

            $selection_type  = isset($row[1]) ? strtolower(trim((string)$row[1])) : 'single';
            if (!in_array($selection_type, ['single', 'multiple'])) {
                $selection_type = 'single';
            }
            $min_selections  = isset($row[2]) ? max(0, (int)$row[2]) : 0;
            $max_selections  = isset($row[3]) ? max(1, (int)$row[3]) : 1;

            // Collect options: columns 4-5, 6-7, 8-9, ..., 22-23 (10 option pairs)
            $options = [];
            for ($i = 0; $i < 10; $i++) {
                $name_col  = 4 + ($i * 2);
                $price_col = 5 + ($i * 2);
                $opt_name  = isset($row[$name_col]) ? trim((string)$row[$name_col]) : '';
                if ($opt_name === '') continue;
                $options[] = [
                    'name'             => $opt_name,
                    'price_adjustment' => isset($row[$price_col]) ? (float)$row[$price_col] : 0,
                ];
            }

            if (empty($options)) {
                $errors[] = "Row " . ($row_index + 1) . " – \"$group_name\": must have at least one option.";
                continue;
            }

            // Check if a group with this name already exists
            $existing = $this->db
                ->where('name', $group_name)
                ->get(db_prefix() . 'modifier_groups')
                ->row_array();

            if ($existing && !$update_existing) {
                $errors[] = "Row " . ($row_index + 1) . " – \"$group_name\": already exists (skipped).";
                continue;
            }

            $data = [
                'name'           => $group_name,
                'selection_type' => $selection_type,
                'min_selections' => $min_selections,
                'max_selections' => $max_selections,
                'active'         => 1,
                'options'        => $options,
            ];

            // Collect linked item SKUs: columns 24–28
            $linked_skus = [];
            for ($i = 0; $i < 5; $i++) {
                $sku = isset($row[24 + $i]) ? trim((string)$row[24 + $i]) : '';
                if ($sku !== '') {
                    $linked_skus[] = $sku;
                }
            }

            $existing_id = $existing ? $existing['id'] : null;
            $result = $this->pos_model->save_modifier_with_options($data, $existing_id);

            if ($result) {
                $success++;

                if (!empty($linked_skus)) {
                    $item_ids = [];
                    foreach ($linked_skus as $sku) {
                        $item = $this->db
                            ->where('sku_code', $sku)
                            ->get(db_prefix() . 'items')
                            ->row_array();
                        if ($item) {
                            $item_ids[] = $item['id'];
                        } else {
                            $errors[] = "Row " . ($row_index + 1) . " – \"$group_name\": item SKU \"$sku\" not found (skipped).";
                        }
                    }
                    if (!empty($item_ids)) {
                        $this->pos_model->assign_items_to_modifier_group($result, $item_ids);
                    }
                }
            } else {
                $errors[] = "Row " . ($row_index + 1) . " – \"$group_name\": failed to save.";
            }
        }

        @unlink($newFilePath);
        @rmdir($tmpDir);

        echo json_encode([
            'success' => true,
            'total'   => $total,
            'saved'   => $success,
            'failed'  => count($errors),
            'errors'  => $errors,
        ]);
    }
}
