<?php defined('BASEPATH') or exit('No direct script access allowed');

class Pos_grabfood extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pos/pos_grabfood_model');
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
}
