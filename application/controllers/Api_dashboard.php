<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_dashboard extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('authorization_token');
    }

    public function expenses_percentage_by_type_get()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $startDate = $this->normalize_date($this->get('start_date'));
        $endDate   = $this->normalize_date($this->get('end_date'));

        if (($this->get('start_date') !== null && $startDate === null)
            || ($this->get('end_date') !== null && $endDate === null)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid start_date or end_date provided. Expected format: YYYY-MM-DD.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $response = [
            'purchase_orders'   => $this->build_purchase_orders_summary($startDate, $endDate),
            'expense_categories' => $this->build_expense_category_summary($startDate, $endDate),
            'bill_debit_accounts' => $this->build_bill_debit_summary($startDate, $endDate),
        ];

        $this->response([
            'status' => true,
            'result' => $response,
        ], self::HTTP_OK);
    }

    private function build_purchase_orders_summary($startDate, $endDate)
    {
        $invoiceIds = $this->load_paid_invoice_ids($startDate, $endDate);
        $orderIds   = $this->load_paid_order_ids($startDate, $endDate);

        $totalsByItem = [];

        if ($invoiceIds !== []) {
            $invoiceTotals = $this->load_item_totals(
                db_prefix() . 'pur_invoice_details',
                'pur_invoice',
                $invoiceIds
            );

            $totalsByItem = $this->merge_item_totals($totalsByItem, $invoiceTotals);
        }

        if ($orderIds !== []) {
            $orderTotals = $this->load_item_totals(
                db_prefix() . 'pur_order_detail',
                'pur_order',
                $orderIds
            );

            $totalsByItem = $this->merge_item_totals($totalsByItem, $orderTotals);
        }

        $grandTotal = array_sum($totalsByItem);

        $summary = [];
        foreach ($totalsByItem as $itemName => $amount) {
            $summary[] = [
                'name'  => $itemName,
                'value' => $this->format_amount($amount),
            ];
        }

        if ($grandTotal > 0) {
            $summary[] = [
                'name'  => 'Grand Total',
                'value' => $this->format_amount($grandTotal),
            ];
        }

        return $summary;
    }

    private function build_expense_category_summary($startDate, $endDate)
    {
        $this->db->select([
            db_prefix() . 'expenses_categories.name as category_name',
            'SUM(' . db_prefix() . 'expenses.amount) as total_amount',
        ]);
        $this->db->from(db_prefix() . 'expenses');
        $this->db->join(
            db_prefix() . 'expenses_categories',
            db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'expenses.category',
            'left'
        );

        if ($startDate !== null) {
            $this->db->where(db_prefix() . 'expenses.date >=', $startDate);
        }

        if ($endDate !== null) {
            $this->db->where(db_prefix() . 'expenses.date <=', $endDate);
        }

        if ($this->db->field_exists('is_bill', db_prefix() . 'expenses')) {
            $this->db->where(db_prefix() . 'expenses.is_bill', 0);
        }

        $this->db->group_by(db_prefix() . 'expenses.category');

        $results = $this->db->get()->result_array();

        $summary = [];
        foreach ($results as $row) {
            $name = $row['category_name'] ?? 'Uncategorized';
            $summary[] = [
                'name'  => $name,
                'value' => $this->format_amount((float) ($row['total_amount'] ?? 0)),
            ];
        }

        return $summary;
    }

    private function build_bill_debit_summary($startDate, $endDate)
    {
        $this->db->select([
            db_prefix() . 'acc_accounts.name as account_name',
            'SUM(' . db_prefix() . 'acc_bill_mappings.amount) as total_amount',
        ]);
        $this->db->from(db_prefix() . 'acc_bill_mappings');
        $this->db->join(
            db_prefix() . 'expenses',
            db_prefix() . 'expenses.id = ' . db_prefix() . 'acc_bill_mappings.bill_id',
            'left'
        );
        $this->db->join(
            db_prefix() . 'acc_accounts',
            db_prefix() . 'acc_accounts.id = ' . db_prefix() . 'acc_bill_mappings.account',
            'left'
        );
        $this->db->where(db_prefix() . 'acc_bill_mappings.type', 'debit');

        if ($startDate !== null) {
            $this->db->where(db_prefix() . 'expenses.date >=', $startDate);
        }

        if ($endDate !== null) {
            $this->db->where(db_prefix() . 'expenses.date <=', $endDate);
        }

        $this->db->group_by(db_prefix() . 'acc_bill_mappings.account');

        $results = $this->db->get()->result_array();

        $summary = [];
        foreach ($results as $row) {
            $summary[] = [
                'name'  => $row['account_name'] ?? 'Unknown Account',
                'value' => $this->format_amount((float) ($row['total_amount'] ?? 0)),
            ];
        }

        return $summary;
    }

    private function load_paid_invoice_ids($startDate, $endDate)
    {
        $this->db->select('pur_invoice');
        $this->db->from(db_prefix() . 'pur_invoice_payment');

        if ($startDate !== null) {
            $this->db->where('date >=', $startDate);
        }

        if ($endDate !== null) {
            $this->db->where('date <=', $endDate);
        }

        $ids = array_column($this->db->get()->result_array(), 'pur_invoice');

        return array_values(array_unique(array_filter($ids, 'strlen')));
    }

    private function load_paid_order_ids($startDate, $endDate)
    {
        $this->db->select('pur_order');
        $this->db->from(db_prefix() . 'pur_order_payment');

        if ($startDate !== null) {
            $this->db->where('date >=', $startDate);
        }

        if ($endDate !== null) {
            $this->db->where('date <=', $endDate);
        }

        $ids = array_column($this->db->get()->result_array(), 'pur_order');

        return array_values(array_unique(array_filter($ids, 'strlen')));
    }

    private function load_item_totals($table, $foreignKey, array $relIds)
    {
        if ($relIds === []) {
            return [];
        }

        $this->db->select([
            'COALESCE(' . db_prefix() . 'items.description, ' . $table . '.item_name, ' . $table . '.item_code) as item_name',
            'SUM(' . $table . '.total_money) as total_amount',
        ]);
        $this->db->from($table);
        $this->db->join(
            db_prefix() . 'items',
            db_prefix() . "items.sku_name = {$table}.item_code",
            'left'
        );
        $this->db->where_in($table . '.' . $foreignKey, $relIds);
        $this->db->group_by([$table . '.item_code', db_prefix() . 'items.description', $table . '.item_name']);

        $results = $this->db->get()->result_array();

        $totals = [];
        foreach ($results as $row) {
            $name = $row['item_name'] ?? 'Unknown Item';
            $totals[$name] = ($totals[$name] ?? 0) + (float) ($row['total_amount'] ?? 0);
        }

        return $totals;
    }

    private function merge_item_totals(array $existingTotals, array $newTotals)
    {
        foreach ($newTotals as $name => $amount) {
            if (!isset($existingTotals[$name])) {
                $existingTotals[$name] = 0;
            }

            $existingTotals[$name] += $amount;
        }

        return $existingTotals;
    }

    private function format_amount($value)
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function ensureAuthenticated()
    {
        if ($this->tokenPayload !== null) {
            return true;
        }

        $tokenData = $this->authenticate_token();

        if ($tokenData === false) {
            return false;
        }

        $tokenString = $this->authorization_token->get_token();

        if (!empty($tokenString) && $tokenString !== 'Token is not defined.') {
            $staff = $this->db->where('token', $tokenString)->get(db_prefix() . 'staff')->row();

            if ($staff) {
                $this->session->set_userdata([
                    'staff_logged_in' => true,
                    'staff_user_id'   => $staff->staffid,
                ]);

                $GLOBALS['current_user'] = $staff;
            }
        }

        $this->tokenPayload = isset($tokenData['data']) ? $tokenData['data'] : $tokenData;

        return true;
    }

    private function normalize_date($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return date('Y-m-d', (int) $value);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', DateTime::RFC3339, DateTime::ATOM];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);

            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);

        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }
}
