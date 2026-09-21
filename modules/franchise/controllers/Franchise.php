<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Franchise extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('franchise/franchise_model');
    }

    public function index()
    {
        if (!has_permission('franchise', '', 'view')) {
            access_denied('franchise');
        }

        $data['title']       = 'Franchise Settlement';
        $data['franchisees'] = $this->franchise_model->get_franchisees_summary();
        $data['stores']      = $this->franchise_model->get_stores_with_franchisee();
        $data['franchise_markup_percent']        = $this->franchise_model->get_markup_percent('franchisee');
        $data['franchise_client_markup_percent'] = $this->franchise_model->get_markup_percent('client');

        $this->load->view('franchise/admin/franchise', $data);
    }

    public function franchisee($id = 0)
    {
        if (!has_permission('franchise', '', 'view')) {
            access_denied('franchise');
        }

        $franchisee = $this->franchise_model->get_franchisee((int)$id);
        if (!$franchisee) {
            show_404();
        }

        $page     = max(1, (int)($this->input->get('page') ?: 1));
        $per_page = 20;

        $this->load->model('clients_model');

        $data['title']            = 'Franchisee: ' . htmlspecialchars($franchisee['name']);
        $data['franchisee']       = $franchisee;
        $data['clients']          = $this->clients_model->get('');
        $data['linked_client_name'] = $this->franchise_model->get_linked_client_name($franchisee['client_id'] ?? null);
        $data['outstanding'] = $this->franchise_model->get_franchisee_outstanding((int)$id);
        $data['outlets']     = array_filter($this->franchise_model->get_stores_with_franchisee(), function ($s) use ($id) {
            return (int)($s['franchisee_id'] ?? 0) === (int)$id;
        });
        $data['redemptions'] = $this->franchise_model->get_franchisee_redeem_transactions((int)$id, false, 1, 10);
        $data['transfers']   = $this->franchise_model->get_franchise_transfers((int)$id, $page, $per_page);
        $transfer_total      = $this->franchise_model->count_franchise_transfers((int)$id);
        $data['result']      = [
            'total'      => $transfer_total,
            'page'       => $page,
            'per_page'   => $per_page,
            'page_count' => max(1, (int)ceil($transfer_total / $per_page)),
        ];

        $this->load->view('franchise/admin/franchisee_detail', $data);
    }

    public function ajax_save_franchisee()
    {
        if (!has_permission('franchise', '', 'create') && !has_permission('franchise', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $id   = (int)$this->input->post('id');
        $name = trim($this->input->post('name'));
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Franchisee name is required']);
            return;
        }

        $fields = [
            'name'              => $name,
            'contact_person'    => trim($this->input->post('contact_person')) ?: null,
            'phone'             => trim($this->input->post('phone')) ?: null,
            'email'             => trim($this->input->post('email')) ?: null,
            'bank_name'         => trim($this->input->post('bank_name')) ?: null,
            'bank_account_name' => trim($this->input->post('bank_account_name')) ?: null,
            'bank_account_no'   => trim($this->input->post('bank_account_no')) ?: null,
            'notes'             => trim($this->input->post('notes')) ?: null,
            'is_active'         => (int)(bool)$this->input->post('is_active'),
        ];

        if ($id) {
            $this->franchise_model->update_franchisee($id, $fields);
        } else {
            $id = $this->franchise_model->create_franchisee($fields);
        }

        echo json_encode(['success' => true, 'id' => $id]);
    }

    public function ajax_delete_franchisee($id)
    {
        if (!has_permission('franchise', '', 'delete')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        $ok = $this->franchise_model->delete_franchisee((int)$id);
        echo json_encode($ok
            ? ['success' => true]
            : ['success' => false, 'message' => 'Cannot delete a franchisee with outlets still assigned to them']);
    }

    public function ajax_assign_store()
    {
        if (!has_permission('franchise', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $warehouse_id  = (int)$this->input->post('warehouse_id');
        $franchisee_id = trim($this->input->post('franchisee_id') ?: '');
        $franchisee_id = $franchisee_id !== '' ? (int)$franchisee_id : null;

        $this->franchise_model->set_store_franchisee($warehouse_id, $franchisee_id);
        echo json_encode(['success' => true]);
    }

    public function ajax_save_markup_setting()
    {
        if (!has_permission('franchise', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $this->franchise_model->set_markup_percent('franchisee', (float)$this->input->post('franchise_markup_percent'));
        $this->franchise_model->set_markup_percent('client', (float)$this->input->post('franchise_client_markup_percent'));

        echo json_encode(['success' => true]);
    }

    public function ajax_link_client($franchisee_id)
    {
        if (!has_permission('franchise', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $client_id = (int)$this->input->post('client_id');
        if (!$client_id) {
            $this->franchise_model->unlink_client((int)$franchisee_id);
            echo json_encode(['success' => true]);
            return;
        }

        $ok = $this->franchise_model->link_client((int)$franchisee_id, $client_id);
        echo json_encode($ok
            ? ['success' => true]
            : ['success' => false, 'message' => 'Failed to link client']);
    }

    public function ajax_set_warehouse_type()
    {
        if (!has_permission('franchise', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $warehouse_id = (int)$this->input->post('warehouse_id');
        $type         = $this->input->post('warehouse_type') === 'hq' ? 'hq' : 'outlet';

        $ok = $this->franchise_model->set_warehouse_type($warehouse_id, $type);
        echo json_encode(['success' => $ok]);
    }

    public function ajax_record_transfer($franchisee_id)
    {
        if (!has_permission('franchise', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $franchisee_id = (int)$franchisee_id;
        $outstanding   = $this->franchise_model->get_franchisee_outstanding($franchisee_id);

        if ($outstanding <= 0) {
            echo json_encode(['success' => false, 'message' => 'There is no outstanding cashback to transfer for this franchisee']);
            return;
        }

        $transfer_id = $this->franchise_model->record_franchise_transfer($franchisee_id, [
            'amount'         => $outstanding,
            'reference_no'   => trim($this->input->post('reference_no')) ?: null,
            'method'         => trim($this->input->post('method')) ?: null,
            'note'           => trim($this->input->post('note')) ?: null,
            'transferred_at' => trim($this->input->post('transferred_at')) ?: date('Y-m-d H:i:s'),
            'staff_id'       => get_staff_user_id(),
        ]);

        echo json_encode($transfer_id
            ? ['success' => true, 'id' => $transfer_id, 'amount' => $outstanding]
            : ['success' => false, 'message' => 'Failed to record transfer']);
    }

    public function ajax_delete_transfer($id)
    {
        if (!has_permission('franchise', '', 'delete')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        $ok = $this->franchise_model->delete_franchise_transfer((int)$id);
        echo json_encode(['success' => $ok]);
    }
}
