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
        $data['title'] = 'Point of Sale';
        $this->load->view('pos/admin/index', $data);
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
            ->select('i.id, i.commodity_name, i.commodity_code, i.sku_code, i.rate, i.active, g.group_name, sg.sub_group_name')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'items_groups g', 'g.id = i.group_id', 'left')
            ->join(db_prefix() . 'wh_sub_group sg', 'sg.id = i.sub_group', 'left')
            ->where('i.can_be_sold', 'can_be_sold')
            ->where('i.can_be_manufacturing', 'can_be_manufacturing')
            ->where('i.parent_id IS NULL')
            ->order_by('i.commodity_name', 'ASC')
            ->get()->result_array();

        $data['title'] = 'POS Products';
        $data['items'] = $items;
        $this->load->view('pos/admin/products', $data);
    }

    // =========================================================================
    // Modifiers
    // =========================================================================

    public function modifiers()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $sets = $this->db
            ->where('deleted_at IS NULL')
            ->order_by('position', 'ASC')
            ->get(db_prefix() . 'pos_modifier_sets')->result_array();

        foreach ($sets as &$set) {
            $set['options'] = $this->db
                ->where('modifier_id', $set['id'])
                ->order_by('position', 'ASC')
                ->get(db_prefix() . 'pos_modifier_options')->result_array();
        }

        $data['title']     = 'POS Modifiers';
        $data['sets'] = $sets;
        $this->load->view('pos/admin/modifiers', $data);
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
