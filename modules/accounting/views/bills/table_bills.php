<?php

defined('BASEPATH') or exit('No direct script access allowed');

$this->ci->load->model('currencies_model');
$this->ci->load->model('accounting/accounting_model');
$currency = $this->ci->currencies_model->get_base_currency();


$aColumns = [
    db_prefix() . 'expenses.id as id',
    db_prefix() . 'expenses.id as id',
    db_prefix() . 'pur_vendor.company as vendor_name',
    db_prefix() . 'expenses.date as date',
    db_prefix() . 'expenses.due_date as due_date',
    db_prefix() . 'expenses.date_paid as date_paid',
    db_prefix() . 'expenses.amount as amount',
    '(SELECT GROUP_CONCAT(check_id SEPARATOR ",") FROM '.db_prefix().'acc_check_details WHERE ' . db_prefix() . 'acc_check_details.bill = '.db_prefix().'expenses.id) as check_ids',
    db_prefix() . 'expenses.status as status',
    db_prefix() . 'expenses.expense_name as expense_name',
];
$join = [
    'LEFT JOIN ' . db_prefix() . 'pur_vendor ON ' . db_prefix() . 'pur_vendor.userid = ' . db_prefix() . 'expenses.vendor',
];

$where  = [];
$filter = [];
//include_once(APPPATH . 'views/admin/tables/includes/expenses_filter.php');

if (!has_permission('accounting_bills', '', 'view')) {
    array_push($where, 'AND ' . db_prefix() . 'expenses.addedfrom=' . get_staff_user_id());
}

$type = '';
if ($this->ci->input->post('type')) {
    $type = $this->ci->input->post('type');
    switch ($type) {
        case 'draft':
            array_push($where, 'AND ' . db_prefix() . 'expenses.is_draft = 1');
            break;
        case 'unpaid':
            array_push($where, 'AND ' . db_prefix() . 'expenses.is_draft = 0');
            array_push($where, 'AND ' . db_prefix() . 'expenses.voided = 0');
            array_push($where, 'AND (' . db_prefix() . 'expenses.status = 0 OR ' . db_prefix() . 'expenses.status = 3)');
            break;
        case 'paid':
            array_push($where, 'AND ' . db_prefix() . 'expenses.is_draft = 0');
            array_push($where, 'AND (' . db_prefix() . 'expenses.status = 2 OR ' . db_prefix() . 'expenses.voided = 1)');
            break;
        case 'all':
        default:
            array_push($where, 'AND ' . db_prefix() . 'expenses.is_draft = 0');
            break;
    }
} else {
    array_push($where, 'AND ' . db_prefix() . 'expenses.is_draft = 0');
}

if ($this->ci->input->post('vendor_id') && $this->ci->input->post('vendor_id') != '') {
    $vendor_id = $this->ci->input->post('vendor_id');
   
    array_push($where, 'AND '.db_prefix().'pur_vendor.userid IN (' . implode(', ', $vendor_id) . ')');
}

$from_date = '';
$to_date = '';
if ($this->ci->input->post('from_date')) {
    $from_date = $this->ci->input->post('from_date');
    if (!$this->ci->accounting_model->check_format_date($from_date)) {
        $from_date = to_sql_date($from_date);
    }
}

if ($this->ci->input->post('to_date')) {
    $to_date = $this->ci->input->post('to_date');
    if (!$this->ci->accounting_model->check_format_date($to_date)) {
        $to_date = to_sql_date($to_date);
    }
}
if ($from_date != '' && $to_date != '') {
    array_push($where, 'AND (' . db_prefix() . 'expenses.date >= "' . $from_date . '" and ' . db_prefix() . 'expenses.date <= "' . $to_date . '")');
} elseif ($from_date != '') {
    array_push($where, 'AND (' . db_prefix() . 'expenses.date >= "' . $from_date . '")');
} elseif ($to_date != '') {
    array_push($where, 'AND (' . db_prefix() . 'expenses.date <= "' . $to_date . '")');
}

array_push($where, 'AND ' . db_prefix() . 'expenses.is_bill = 1');


$sIndexColumn = 'id';
$sTable       = db_prefix() . 'expenses';

// Fix for big queries. Some hosting have max_join_limit

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
     'vendor', db_prefix().'expenses.approved', db_prefix().'expenses.expense_name'
]);
$output  = $result['output'];
$rResult = $result['rResult'];

$this->ci->load->model('payment_modes_model');

foreach ($rResult as $aRow) {
    $row = [];
    $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '" data-vendor="'.$aRow['vendor'].'" name="bill_'.$aRow['id'].'" id="bill_'.$aRow['id'].'"><label for="bill_'.$aRow['id'].'"></label></div>';
    $row[] = $aRow['id'];

    $categoryOutput = '';

    $displayName = $aRow['vendor_name'] ?: $aRow['expense_name'] ?? '';
    $categoryOutput = '<a href="' . admin_url('accounting/bills/' . $aRow['id']) . '" onclick="init_bills(' . $aRow['id'] . ');return false;">' . $displayName . '</a>';

        switch ($type) {
            case 'draft':
                $categoryOutput .= '<div class="row-options">';
                $categoryOutput .= '<a href="' . admin_url('purchase/wa_bill_draft_form/' . $aRow['id']) . '">' . ucfirst(_l('review')) . '</a>';
                if (has_permission('accounting_bills', '', 'delete')) {
                    $categoryOutput .= ' | <a href="' . admin_url('purchase/delete_wa_bill_draft/' . $aRow['id']) . '" class="text-danger confirm-action">' . _l('delete') . '</a>';
                }
                $categoryOutput .= '</div>';
                break;
            case 'unpaid':
                $categoryOutput .= '<div class="row-options">';
                $categoryOutput .= '<a href="#" onclick="init_bills(' . $aRow['id'] . ');return false;">' . _l('acc_open') . '</a>';
                if (has_permission('accounting_bills', '', 'edit')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/pay_bill?bill=' . $aRow['id']) . '">' . _l('pay_bill') . '</a>';
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/bill/' . $aRow['id']) . '">' . _l('edit') . '</a>';
                }
                if (has_permission('accounting_bills', '', 'delete')) {
                    $categoryOutput .= ' | <a href="#" class="text-danger" onclick="delete_bill(' . $aRow['id'] . ');return false;">' . _l('delete') . '</a>';
                }
                $categoryOutput .= '</div>';
                break;
            case 'paid':
                $categoryOutput .= '<div class="row-options">';
                $categoryOutput .= '<a href="#" onclick="init_bills(' . $aRow['id'] . ');return false;">' . _l('acc_open') . '</a>';
                $categoryOutput .= '</div>';
                break;
            case 'all':
            default:
                $categoryOutput .= '<div class="row-options">';
                $categoryOutput .= '<a href="#" onclick="init_bills(' . $aRow['id'] . ');return false;">' . _l('acc_open') . '</a>';
                if (has_permission('accounting_bills', '', 'edit')) {
                    $categoryOutput .= ' | <a href="' . admin_url('accounting/bill/' . $aRow['id']) . '">' . _l('edit') . '</a>';
                }
                if (has_permission('accounting_bills', '', 'delete')) {
                    $categoryOutput .= ' | <a href="#" class="text-danger" onclick="delete_bill(' . $aRow['id'] . ');return false;">' . _l('delete') . '</a>';
                }
                $categoryOutput .= '</div>';
                break;
        }
   
    $row[] = $categoryOutput;
    $row[] = htmlspecialchars($aRow['expense_name'] ?? '');

    $row[] = _d($aRow['date']);
    $row[] = _d($aRow['due_date']);
    $row[] = _d($aRow['date_paid']);

    $row[] = app_format_money($aRow['amount'], $currency->name);

    $check_number = '';
    if ($aRow['check_ids']) {
        $checks = explode(',', $aRow['check_ids']);
        foreach ($checks as $check) {
            $number = acc_format_check_number($check);
            if($check_number == ''){
                $check_number .= $number;
            }else{
                $check_number .= ', '.$number;
            }
        }
    }

    $row[] = $check_number;

    $row[] = bill_status_html($aRow['id']);

    $row['DT_RowClass'] = 'has-row-options';

    $output['aaData'][] = $row;
}
