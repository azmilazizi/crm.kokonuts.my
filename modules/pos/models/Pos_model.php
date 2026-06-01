<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pos_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function verify_api_token($token)
    {
        $row = $this->db->get_where(db_prefix() . 'pos_api_tokens', ['token' => $token, 'active' => 1])->row();
        return $row !== null;
    }

    public function get_stores()
    {
        return $this->db->where('deleted_at IS NULL')->get(db_prefix() . 'pos_stores')->result_array();
    }

    public function get_store($id)
    {
        return $this->db->where('id', $id)->where('deleted_at IS NULL')->get(db_prefix() . 'pos_stores')->row_array();
    }

    public function get_categories()
    {
        return $this->db->where('deleted_at IS NULL')->order_by('name', 'ASC')->get(db_prefix() . 'pos_categories')->result_array();
    }

    public function get_employees()
    {
        return $this->db->where('deleted_at IS NULL')->get(db_prefix() . 'pos_employees')->result_array();
    }

    public function get_employee_by_pin($pin, $store_id = null)
    {
        $this->db->where('pin', $pin)->where('deleted_at IS NULL');
        $employee = $this->db->get(db_prefix() . 'pos_employees')->row_array();
        if (!$employee) return false;
        if ($store_id) {
            $store_ids = json_decode($employee['store_ids'] ?? '[]', true);
            if (!in_array((int)$store_id, $store_ids)) return false;
        }
        unset($employee['pin']);
        return $employee;
    }

    public function get_modifiers()
    {
        $sets = $this->db->where('deleted_at IS NULL')->order_by('position', 'ASC')->get(db_prefix() . 'pos_modifier_sets')->result_array();
        foreach ($sets as &$set) {
            $set['options'] = $this->db->where('modifier_id', $set['id'])->order_by('position', 'ASC')->get(db_prefix() . 'pos_modifier_options')->result_array();
        }
        return $sets;
    }

    public function get_payment_types($store_id = null)
    {
        $types = $this->db->where('deleted_at IS NULL')->get(db_prefix() . 'pos_payment_types')->result_array();
        if ($store_id) {
            $types = array_filter($types, function ($t) use ($store_id) {
                $ids = json_decode($t['store_ids'] ?? '[]', true);
                return empty($ids) || in_array((int)$store_id, $ids);
            });
        }
        return array_values($types);
    }

    public function get_receipts($store_id = null, $date_from = null, $date_to = null)
    {
        if ($store_id)  $this->db->where('store_id', $store_id);
        if ($date_from) $this->db->where('receipt_date >=', $date_from);
        if ($date_to)   $this->db->where('receipt_date <=', $date_to);
        $receipts = $this->db->order_by('receipt_date', 'DESC')->get(db_prefix() . 'pos_receipts')->result_array();
        foreach ($receipts as &$receipt) {
            $receipt = $this->_attach_receipt_details($receipt);
        }
        return $receipts;
    }

    public function get_receipt($receipt_number)
    {
        $receipt = $this->db->where('receipt_number', $receipt_number)->get(db_prefix() . 'pos_receipts')->row_array();
        return $receipt ? $this->_attach_receipt_details($receipt) : null;
    }

    private function _attach_receipt_details($receipt)
    {
        $receipt['line_items'] = $this->db->where('receipt_id', $receipt['id'])->get(db_prefix() . 'pos_receipt_line_items')->result_array();
        $receipt['payments']   = $this->db->where('receipt_id', $receipt['id'])->get(db_prefix() . 'pos_receipt_payments')->result_array();
        return $receipt;
    }

    public function create_receipt($data)
    {
        $receipt_number = 'RCP-' . strtoupper(uniqid());
        $this->db->insert(db_prefix() . 'pos_receipts', [
            'receipt_number'  => $receipt_number,
            'receipt_type'    => $data['receipt_type'] ?? 'SALE',
            'refund_for'      => $data['refund_for'] ?? null,
            'store_id'        => $data['store_id'],
            'employee_id'     => $data['employee_id'] ?? null,
            'customer_id'     => $data['customer_id'] ?? null,
            'note'            => $data['note'] ?? null,
            'dining_option'   => $data['dining_option'] ?? null,
            'source'          => $data['source'] ?? 'POS',
            'subtotal'        => $data['subtotal'] ?? 0,
            'total_discount'  => $data['total_discount'] ?? 0,
            'total_tax'       => $data['total_tax'] ?? 0,
            'tip'             => $data['tip'] ?? 0,
            'surcharge'       => $data['surcharge'] ?? 0,
            'total_money'     => $data['total_money'] ?? 0,
            'points_earned'   => $data['points_earned'] ?? 0,
            'points_deducted' => $data['points_deducted'] ?? 0,
            'receipt_date'    => date('Y-m-d H:i:s'),
        ]);
        $receipt_id = $this->db->insert_id();
        if (!$receipt_id) return false;
        foreach ($data['line_items'] ?? [] as $item) {
            $this->db->insert(db_prefix() . 'pos_receipt_line_items', [
                'receipt_id'      => $receipt_id,
                'item_id'         => $item['item_id'],
                'item_name'       => $item['item_name'],
                'variant_id'      => $item['variant_id'] ?? null,
                'variant_name'    => $item['variant_name'] ?? null,
                'quantity'        => $item['quantity'] ?? 1,
                'unit_price'      => $item['unit_price'] ?? 0,
                'cost'            => $item['cost'] ?? 0,
                'gross_total'     => $item['gross_total'] ?? 0,
                'total_discount'  => $item['total_discount'] ?? 0,
                'total_tax'       => $item['total_tax'] ?? 0,
                'total_money'     => $item['total_money'] ?? 0,
                'modifier_ids'    => json_encode($item['modifier_ids'] ?? []),
                'modifier_names'  => json_encode($item['modifier_names'] ?? []),
                'modifiers_price' => $item['modifiers_price'] ?? 0,
                'tax_ids'         => json_encode($item['tax_ids'] ?? []),
                'line_note'       => $item['line_note'] ?? null,
            ]);
        }
        foreach ($data['payments'] ?? [] as $payment) {
            $this->db->insert(db_prefix() . 'pos_receipt_payments', [
                'receipt_id'      => $receipt_id,
                'payment_type_id' => $payment['payment_type_id'],
                'payment_name'    => $payment['payment_name'],
                'type'            => $payment['type'] ?? 'CASH',
                'money_amount'    => $payment['money_amount'] ?? 0,
                'cash_back'       => $payment['cash_back'] ?? 0,
                'payment_date'    => date('Y-m-d H:i:s'),
            ]);
        }
        return $receipt_number;
    }

    public function create_refund($data)
    {
        $refund_receipt_number = 'RFD-' . strtoupper(uniqid());
        $this->db->insert(db_prefix() . 'pos_refunds', [
            'receipt_id'            => $data['receipt_id'],
            'refund_receipt_number' => $refund_receipt_number,
            'employee_id'           => $data['employee_id'] ?? null,
            'payment_type_id'       => $data['payment_type_id'] ?? null,
            'amount'                => $data['amount'] ?? 0,
            'note'                  => $data['note'] ?? null,
            'refunded_at'           => date('Y-m-d H:i:s'),
        ]);
        $refund_id = $this->db->insert_id();
        if (!$refund_id) return false;
        $this->db->where('id', $data['receipt_id'])->update(db_prefix() . 'pos_receipts', ['receipt_type' => 'REFUNDED']);
        return $refund_id;
    }
}
