<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_payments extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('expenses_model');
    }

    public function payments_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $payments = array_merge(
            $this->purchase_order_payments(),
            $this->bill_payments(),
            $this->expense_payments()
        );

        $this->response([
            'status' => true,
            'result' => $payments,
        ], self::HTTP_OK);
    }

    private function purchase_order_payments(): array
    {
        $this->db->select([
            'pop.id',
            'pop.pur_order as source_id',
            'pop.amount',
            'pop.date',
            'pop.paymentmode',
            'pop.transactionid',
            'po.pur_order_number',
            'po.pur_order_name',
        ]);
        $this->db->from(db_prefix() . 'pur_order_payment as pop');
        $this->db->join(db_prefix() . 'pur_orders as po', 'po.id = pop.pur_order', 'left');
        $records = $this->db->get()->result_array();

        return array_map(function (array $record) {
            $record['amount'] = (float) ($record['amount'] ?? 0);
            $record['source_type'] = 'purchase_order';
            $record['label'] = trim(($record['pur_order_number'] ?? '') . ' ' . ($record['pur_order_name'] ?? ''));

            return $record;
        }, $records);
    }

    private function bill_payments(): array
    {
        $this->db->select([
            'pb.id',
            'pb.bill as source_id',
            'pb.amount',
            'pb.date',
            'pb.reference_no',
            'pb.payment_method as paymentmode',
            'pb.pay_number',
        ]);
        $this->db->from(db_prefix() . 'acc_pay_bills as pb');
        $records = $this->db->get()->result_array();

        return array_map(function (array $record) {
            $record['amount'] = (float) ($record['amount'] ?? 0);
            $record['source_type'] = 'bill';
            $record['label'] = $record['reference_no'] ?? '';

            return $record;
        }, $records);
    }

    private function expense_payments(): array
    {
        $this->db->select([
            'e.id',
            'e.amount',
            'e.date',
            'e.clientid as source_id',
            'e.paymentmode',
            'e.reference_no',
        ]);
        $this->db->from(db_prefix() . 'expenses as e');
        $records = $this->db->get()->result_array();

        return array_map(function (array $record) {
            $record['amount'] = (float) ($record['amount'] ?? 0);
            $record['source_type'] = 'expense';
            $record['label'] = $record['reference_no'] ?? '';

            return $record;
        }, $records);
    }
}
