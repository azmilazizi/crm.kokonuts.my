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

    public function api_tokens()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $data['title']  = 'POS API Tokens';
        $data['tokens'] = $this->db->order_by('created_at', 'DESC')->get(db_prefix() . 'pos_api_tokens')->result_array();
        $this->load->view('pos/admin/api_tokens', $data);
    }

    public function ajax_generate_token()
    {
        if (!has_permission('pos', '', 'create')) {
            ajax_access_denied();
        }
        $name  = $this->input->post('name');
        $token = bin2hex(random_bytes(32));
        $this->db->insert(db_prefix() . 'pos_api_tokens', [
            'token'      => $token,
            'name'       => $name ?: null,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
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
        $new_active = (int)$row->active === 1 ? 0 : 1;
        $this->db->where('id', $id)->update(db_prefix() . 'pos_api_tokens', ['active' => $new_active]);
        echo json_encode(['success' => true, 'active' => $new_active]);
    }

    public function ajax_delete_token($id)
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'pos_api_tokens');
        echo json_encode(['success' => (bool)$this->db->affected_rows()]);
    }
}
