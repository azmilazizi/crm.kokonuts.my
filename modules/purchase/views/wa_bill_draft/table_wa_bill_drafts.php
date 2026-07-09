<?php

defined('BASEPATH') or exit('No direct script access allowed');

$base_currency = get_base_currency_pur();

$aColumns = [
    'vendor_name',
    'date',
    'due_date',
    'amount',
    'bill_category_id',
    'created_at',
    'id',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'wa_bill_drafts';
$join         = ['LEFT JOIN ' . db_prefix() . 'acc_bill_categories ON ' . db_prefix() . 'acc_bill_categories.id = ' . db_prefix() . 'wa_bill_drafts.bill_category_id'];
$where        = [];

if ($this->ci->input->post('from_date') && $this->ci->input->post('from_date') != '') {
    array_push($where, 'AND date >= "' . to_sql_date($this->ci->input->post('from_date')) . '"');
}

if ($this->ci->input->post('to_date') && $this->ci->input->post('to_date') != '') {
    array_push($where, 'AND date <= "' . to_sql_date($this->ci->input->post('to_date')) . '"');
}

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix() . 'acc_bill_categories.name as bill_category_name']);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {
        $_data = $aRow[$aColumns[$i]];

        if ($aColumns[$i] == 'vendor_name') {
            $form_url = admin_url('purchase/wa_bill_draft_form/' . $aRow['id']);
            $name     = '<a href="' . $form_url . '">' . htmlspecialchars($aRow['vendor_name'] ?: '—') . '</a>';
            $name    .= '<div class="row-options">';
            $name    .= '<a href="' . $form_url . '">' . _l('edit') . '</a>';
            if (is_admin()) {
                $name .= ' | <a href="' . admin_url('purchase/delete_wa_bill_draft/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            }
            $name    .= '</div>';
            $_data    = $name;

        } elseif ($aColumns[$i] == 'date') {
            $_data = $aRow['date'] ? _d($aRow['date']) : '—';

        } elseif ($aColumns[$i] == 'due_date') {
            $_data = $aRow['due_date'] ? _d($aRow['due_date']) : '—';

        } elseif ($aColumns[$i] == 'amount') {
            $_data = app_format_money((float) $aRow['amount'], $base_currency->symbol);

        } elseif ($aColumns[$i] == 'bill_category_id') {
            $_data = htmlspecialchars($aRow['bill_category_name'] ?? '—');

        } elseif ($aColumns[$i] == 'created_at') {
            $_data = $aRow['created_at'] ? _dt($aRow['created_at']) : '—';

        } elseif ($aColumns[$i] == 'id') {
            $_data = '';
            if (is_admin()) {
                $_data = '<a href="' . admin_url('purchase/delete_wa_bill_draft/' . $aRow['id']) . '" class="btn btn-danger btn-xs _delete"><i class="fa fa-trash"></i></a>';
            }
        }

        $row[] = $_data;
    }

    $output['aaData'][] = $row;
}
