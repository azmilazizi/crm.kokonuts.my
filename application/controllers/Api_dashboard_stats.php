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

        if (!$start_date || !$end_date) {
            $this->response([
                'status' => false,
                'message' => 'start_date and end_date are required (YYYY-MM-DD)',
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        // 1. Purchase Order Items Spent
        // Table: tblpur_order_detail (items), tblpur_orders (orders)
        // Filter: approve_status = 2 (Approved), order_date between range
        $this->db->select('COALESCE(tblitems.description, tblpur_order_detail.item_name) as name, SUM(tblpur_order_detail.total_money) as value');
        $this->db->from(db_prefix() . 'pur_order_detail');
        $this->db->join(db_prefix() . 'pur_orders', db_prefix() . 'pur_orders.id = ' . db_prefix() . 'pur_order_detail.pur_order');
        $this->db->join(db_prefix() . 'items', db_prefix() . 'items.id = ' . db_prefix() . 'pur_order_detail.item_code', 'left');
        $this->db->where(db_prefix() . 'pur_orders.approve_status', 2);
        $this->db->where(db_prefix() . 'pur_orders.order_date >=', $start_date);
        $this->db->where(db_prefix() . 'pur_orders.order_date <=', $end_date);
        $this->db->group_by(db_prefix() . 'pur_order_detail.item_code');
        // If item_code is null or 0, it groups by that, essentially grouping "manual" items together if names differ?
        // Better to group by name if code is missing, but usually item_code is reliable for "Items".
        // Let's stick to item_code grouping, but for display use name.
        $po_stats = $this->db->get()->result_array();

        // 2. Expenses Category Spent
        // Table: tblexpenses
        // Filter: date between range
        $this->db->select('tblexpenses_categories.name as name, SUM(tblexpenses.amount) as value');
        $this->db->from(db_prefix() . 'expenses');
        $this->db->join(db_prefix() . 'expenses_categories', db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'expenses.category');
        $this->db->where(db_prefix() . 'expenses.date >=', $start_date);
        $this->db->where(db_prefix() . 'expenses.date <=', $end_date);
        $this->db->group_by(db_prefix() . 'expenses.category');
        $expense_stats = $this->db->get()->result_array();

        // 3. Bills Debit Account Spent
        // Table: tblacc_bill_mappings (debit), tblexpenses (is_bill=1)
        // Filter: date between range
        // Note: Checking if tables exist to avoid crashing if accounting module is missing
        $bill_stats = [];
        if ($this->db->table_exists(db_prefix() . 'acc_bill_mappings') && $this->db->table_exists(db_prefix() . 'acc_accounts')) {
            $this->db->select('tblacc_accounts.name as name, SUM(tblacc_bill_mappings.amount) as value');
            $this->db->from(db_prefix() . 'acc_bill_mappings');
            $this->db->join(db_prefix() . 'expenses', db_prefix() . 'expenses.id = ' . db_prefix() . 'acc_bill_mappings.bill_id');
            $this->db->join(db_prefix() . 'acc_accounts', db_prefix() . 'acc_accounts.id = ' . db_prefix() . 'acc_bill_mappings.account');
            $this->db->where(db_prefix() . 'acc_bill_mappings.type', 'debit');
            $this->db->where(db_prefix() . 'expenses.date >=', $start_date);
            $this->db->where(db_prefix() . 'expenses.date <=', $end_date);
            $this->db->group_by(db_prefix() . 'acc_bill_mappings.account');
            $bill_stats = $this->db->get()->result_array();
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
