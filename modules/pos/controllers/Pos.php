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
}
