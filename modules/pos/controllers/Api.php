<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pos/pos_model');
        $this->_verify_token();
    }

    public function stores()
    {
        $this->_json($this->pos_model->get_stores());
    }

    public function store($id)
    {
        $store = $this->pos_model->get_store($id);
        $store ? $this->_json($store) : $this->_not_found('Store');
    }

    public function categories()
    {
        $this->_json($this->pos_model->get_categories());
    }

    public function employees()
    {
        $this->_json($this->pos_model->get_employees());
    }

    public function employee_login()
    {
        $pin      = $this->input->post('pin');
        $store_id = $this->input->post('store_id');
        $employee = $this->pos_model->get_employee_by_pin($pin, $store_id);
        $employee ? $this->_json($employee) : $this->_error('Invalid PIN', 401);
    }

    public function modifiers()
    {
        $this->_json($this->pos_model->get_modifiers());
    }

    public function payment_types()
    {
        $this->_json($this->pos_model->get_payment_types($this->input->get('store_id')));
    }

    public function receipts()
    {
        $receipts = $this->pos_model->get_receipts(
            $this->input->get('store_id'),
            $this->input->get('date_from'),
            $this->input->get('date_to')
        );
        $this->_json($receipts);
    }

    public function receipt($receipt_number)
    {
        $receipt = $this->pos_model->get_receipt($receipt_number);
        $receipt ? $this->_json($receipt) : $this->_not_found('Receipt');
    }

    public function create_receipt()
    {
        $data   = json_decode(file_get_contents('php://input'), true);
        $result = $this->pos_model->create_receipt($data);
        $result ? $this->_json(['receipt_number' => $result], 201) : $this->_error('Failed to create receipt');
    }

    public function create_refund()
    {
        $data   = json_decode(file_get_contents('php://input'), true);
        $result = $this->pos_model->create_refund($data);
        $result ? $this->_json(['id' => $result], 201) : $this->_error('Failed to create refund');
    }

    private function _verify_token()
    {
        $headers = $this->input->request_headers();
        $auth    = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        $token   = trim(str_replace('Bearer', '', $auth));
        if (empty($token) || !$this->pos_model->verify_api_token($token)) {
            $this->_error('Unauthorized', 401);
            exit;
        }
    }

    private function _json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function _error($message, $status = 400)
    {
        $this->_json(['error' => $message], $status);
    }

    private function _not_found($entity)
    {
        $this->_error($entity . ' not found', 404);
    }
}
