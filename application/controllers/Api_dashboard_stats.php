<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_dashboard_stats extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('authorization_token');
    }

    public function stats_get()
    {
        // Authenticate
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $start_date = $this->get('start_date');
        $end_date = $this->get('end_date');

        $type = $this->get('type');
        if($type){
            $type = strtolower(trim($type));
        } else {
            $type = 'issued';
        }

        if (!$start_date || !$end_date) {
            $this->response([
                'status' => false,
                'message' => 'start_date and end_date are required (YYYY-MM-DD)',
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        $po_stats = [];
        $expense_stats = [];
        $bill_stats = [];

        if ($type == 'payment') {
            // ==========================================
            // CASH BASIS (Payment Date)
            // ==========================================

            // 1. Purchase Order Payments (Cash Basis)
            // Logic: Payment -> Invoice (with linked PO) -> Items (allocate payments proportionally)
            if ($this->db->table_exists(db_prefix() . 'pur_invoice_payment') && $this->db->table_exists(db_prefix() . 'pur_invoices')) {
                // Payments per purchase order (plus any shipping collected with the payment range)
                $paymentSubquery = $this->db->select([
                    db_prefix() . 'pur_orders.id as purchase_order_id',
                    'SUM(' . db_prefix() . 'pur_invoice_payment.amount) as amount_paid',
                    'SUM(COALESCE(' . db_prefix() . 'pur_invoices.shipping_fee, ' . db_prefix() . 'pur_orders.shipping_fee, 0)) as shipping_fee_paid',
                ])
                    ->from(db_prefix() . 'pur_invoice_payment')
                    ->join(db_prefix() . 'pur_invoices', db_prefix() . 'pur_invoices.id = ' . db_prefix() . 'pur_invoice_payment.pur_invoice')
                    ->join(db_prefix() . 'pur_orders', db_prefix() . 'pur_orders.id = ' . db_prefix() . 'pur_invoices.pur_order', 'inner')
                    ->where(db_prefix() . 'pur_invoice_payment.date >=', $start_date)
                    ->where(db_prefix() . 'pur_invoice_payment.date <=', $end_date)
                    ->where(db_prefix() . 'pur_invoices.pur_order IS NOT NULL', null, false)
                    ->group_by(db_prefix() . 'pur_orders.id')
                    ->get_compiled_select();
                $this->db->reset_query();

                // Totals per purchase order to avoid recalculating for every item row
                $orderTotalsSubquery = $this->db->select([
                    db_prefix() . 'pur_order_detail.pur_order',
                    'SUM(' . db_prefix() . 'pur_order_detail.total_money) as total_money_sum',
                ])
                    ->from(db_prefix() . 'pur_order_detail')
                    ->group_by(db_prefix() . 'pur_order_detail.pur_order')
                    ->get_compiled_select();
                $this->db->reset_query();

                // Aggregate paid amounts per item by distributing the payment proportionally to item total
                $paymentSubquery     = '(' . $paymentSubquery . ') payments';
                $orderTotalsSubquery = '(' . $orderTotalsSubquery . ') order_totals';

                $this->db->select([
                    'COALESCE(' . db_prefix() . 'items.description, ' . db_prefix() . 'pur_order_detail.item_name) as name',
                    'SUM((' . db_prefix() . 'pur_order_detail.total_money / NULLIF(order_totals.total_money_sum, 0)) * payments.amount_paid) as value',
                ], false);
                $this->db->from($paymentSubquery, false);
                $this->db->join($orderTotalsSubquery, 'order_totals.pur_order = payments.purchase_order_id', 'left', false);
                $this->db->join(db_prefix() . 'pur_order_detail', db_prefix() . 'pur_order_detail.pur_order = payments.purchase_order_id');
                $this->db->join(db_prefix() . 'items', db_prefix() . 'items.id = ' . db_prefix() . 'pur_order_detail.item_code', 'left');
                $this->db->group_by([
                    db_prefix() . 'pur_order_detail.item_code',
                    db_prefix() . 'pur_order_detail.item_name',
                    db_prefix() . 'items.description',
                ]);
                $po_stats = $this->db->get()->result_array();

                // Shipping values paid within the date range
                $paymentSummary = $this->db->query($paymentSubquery)->result_array();
                $shipping_total = array_sum(array_column($paymentSummary, 'shipping_fee_paid'));
                if ($shipping_total > 0) {
                    $po_stats[] = [
                        'name'  => 'Shipping',
                        'value' => (float) $shipping_total,
                    ];
                }

                // Normalize values to float for consistency
                $po_stats = array_map(function ($row) {
                    $row['value'] = (float) $row['value'];
                    return $row;
                }, $po_stats);
            }

            // 2. Expenses Category Spent (Cash Basis)
            // Part A: Standard Expenses (is_bill=0) - assume date is payment date
            $this->db->select(db_prefix().'expenses_categories.name as name, SUM('.db_prefix().'expenses.amount) as value, '.db_prefix().'expenses.category as category_id');
            $this->db->from(db_prefix() . 'expenses');
            $this->db->join(db_prefix() . 'expenses_categories', db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'expenses.category');
            $this->db->where(db_prefix() . 'expenses.is_bill', 0);
            $this->db->where(db_prefix() . 'expenses.date >=', $start_date);
            $this->db->where(db_prefix() . 'expenses.date <=', $end_date);
            $this->db->group_by(db_prefix() . 'expenses.category');
            $expenses_part_a = $this->db->get()->result_array();

            // Part B: Bills (is_bill=1) - use payment date from Accounting module
            $expenses_part_b = [];
            if ($this->db->table_exists(db_prefix() . 'acc_pay_bills') && $this->db->table_exists(db_prefix() . 'acc_pay_bill_item_paid')) {
                $this->db->select(db_prefix().'expenses_categories.name as name, SUM('.db_prefix().'acc_pay_bill_item_paid.amount_paid) as value, '.db_prefix().'expenses.category as category_id');
                $this->db->from(db_prefix() . 'acc_pay_bills');
                $this->db->join(db_prefix() . 'acc_pay_bill_item_paid', db_prefix() . 'acc_pay_bill_item_paid.pay_bill_id = ' . db_prefix() . 'acc_pay_bills.id');
                $this->db->join(db_prefix() . 'acc_bill_mappings', db_prefix() . 'acc_bill_mappings.id = ' . db_prefix() . 'acc_pay_bill_item_paid.item_id');
                $this->db->join(db_prefix() . 'expenses', db_prefix() . 'expenses.id = ' . db_prefix() . 'acc_bill_mappings.bill_id');
                $this->db->join(db_prefix() . 'expenses_categories', db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'expenses.category');
                $this->db->where(db_prefix() . 'acc_pay_bills.date >=', $start_date);
                $this->db->where(db_prefix() . 'acc_pay_bills.date <=', $end_date);
                $this->db->group_by(db_prefix() . 'expenses.category');
                $expenses_part_b = $this->db->get()->result_array();
            }

            // Merge Part A and Part B
            $expense_map = [];
            foreach ($expenses_part_a as $row) {
                $expense_map[$row['category_id']] = [
                    'name' => $row['name'],
                    'value' => (float)$row['value']
                ];
            }
            foreach ($expenses_part_b as $row) {
                if (isset($expense_map[$row['category_id']])) {
                    $expense_map[$row['category_id']]['value'] += (float)$row['value'];
                } else {
                    $expense_map[$row['category_id']] = [
                        'name' => $row['name'],
                        'value' => (float)$row['value']
                    ];
                }
            }
            $expense_stats = array_values($expense_map);

            // 3. Bills Debit Account Spent (Cash Basis)
            if ($this->db->table_exists(db_prefix() . 'acc_pay_bills') && $this->db->table_exists(db_prefix() . 'acc_bill_mappings') && $this->db->table_exists(db_prefix() . 'acc_pay_bill_item_paid')) {
                // Using exact amount paid for each item mapping
                $this->db->select(db_prefix().'acc_accounts.name as name, SUM('.db_prefix().'acc_pay_bill_item_paid.amount_paid) as value');
                $this->db->from(db_prefix() . 'acc_pay_bills');
                $this->db->join(db_prefix() . 'acc_pay_bill_item_paid', db_prefix() . 'acc_pay_bill_item_paid.pay_bill_id = ' . db_prefix() . 'acc_pay_bills.id');
                $this->db->join(db_prefix() . 'acc_bill_mappings', db_prefix() . 'acc_bill_mappings.id = ' . db_prefix() . 'acc_pay_bill_item_paid.item_id');
                $this->db->join(db_prefix() . 'acc_accounts', db_prefix() . 'acc_accounts.id = ' . db_prefix() . 'acc_bill_mappings.account');
                $this->db->join(db_prefix() . 'expenses', db_prefix() . 'expenses.id = ' . db_prefix() . 'acc_bill_mappings.bill_id');

                $this->db->where(db_prefix() . 'acc_bill_mappings.type', 'debit');
                $this->db->where(db_prefix() . 'acc_pay_bills.date >=', $start_date);
                $this->db->where(db_prefix() . 'acc_pay_bills.date <=', $end_date);

                $this->db->group_by(db_prefix() . 'acc_bill_mappings.account');
                $bill_stats = $this->db->get()->result_array();
            }

        } else {
            // ==========================================
            // ACCRUAL BASIS (Issued Date) - Default
            // ==========================================

            // 1. Purchase Order Items Spent
            $this->db->select('COALESCE('.db_prefix().'items.description, '.db_prefix().'pur_order_detail.item_name) as name, SUM('.db_prefix().'pur_order_detail.total_money) as value');
            $this->db->from(db_prefix() . 'pur_order_detail');
            $this->db->join(db_prefix() . 'pur_orders', db_prefix() . 'pur_orders.id = ' . db_prefix() . 'pur_order_detail.pur_order');
            $this->db->join(db_prefix() . 'items', db_prefix() . 'items.id = ' . db_prefix() . 'pur_order_detail.item_code', 'left');
            $this->db->where(db_prefix() . 'pur_orders.approve_status', 2);
            $this->db->where(db_prefix() . 'pur_orders.order_date >=', $start_date);
            $this->db->where(db_prefix() . 'pur_orders.order_date <=', $end_date);
            $this->db->group_by(db_prefix() . 'pur_order_detail.item_code');
            $po_stats = $this->db->get()->result_array();

            // 2. Expenses Category Spent
            $this->db->select(db_prefix().'expenses_categories.name as name, SUM('.db_prefix().'expenses.amount) as value');
            $this->db->from(db_prefix() . 'expenses');
            $this->db->join(db_prefix() . 'expenses_categories', db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'expenses.category');
            $this->db->where(db_prefix() . 'expenses.date >=', $start_date);
            $this->db->where(db_prefix() . 'expenses.date <=', $end_date);
            $this->db->group_by(db_prefix() . 'expenses.category');
            $expense_stats = $this->db->get()->result_array();

            // 3. Bills Debit Account Spent
            if ($this->db->table_exists(db_prefix() . 'acc_bill_mappings') && $this->db->table_exists(db_prefix() . 'acc_accounts')) {
                $this->db->select(db_prefix().'acc_accounts.name as name, SUM('.db_prefix().'acc_bill_mappings.amount) as value');
                $this->db->from(db_prefix() . 'acc_bill_mappings');
                $this->db->join(db_prefix() . 'expenses', db_prefix() . 'expenses.id = ' . db_prefix() . 'acc_bill_mappings.bill_id');
                $this->db->join(db_prefix() . 'acc_accounts', db_prefix() . 'acc_accounts.id = ' . db_prefix() . 'acc_bill_mappings.account');
                $this->db->where(db_prefix() . 'acc_bill_mappings.type', 'debit');
                $this->db->where(db_prefix() . 'expenses.date >=', $start_date);
                $this->db->where(db_prefix() . 'expenses.date <=', $end_date);
                $this->db->group_by(db_prefix() . 'acc_bill_mappings.account');
                $bill_stats = $this->db->get()->result_array();
            }
        }

        $this->response([
            'status' => true,
            'data' => [
                'purchase_orders' => $po_stats,
                'expense_categories' => $expense_stats,
                'bill_debit_accounts' => $bill_stats,
            ],
        ], self::HTTP_OK);
    }

    private function ensureAuthenticated()
    {
        $tokenData = $this->authorization_token->validateToken();
        if ($tokenData['status'] === TRUE) {
            return true;
        }
        $this->response([
            'status' => false,
            'message' => 'Unauthorized',
        ], self::HTTP_UNAUTHORIZED);
        return false;
    }
}
