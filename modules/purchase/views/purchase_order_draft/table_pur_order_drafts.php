<?php

defined('BASEPATH') or exit('No direct script access allowed');

$base_currency = get_base_currency_pur();

$aColumns = [
    'order_name',
    'order_number',
    'vendor_name',
    'order_date',
    'grand_total',
    'is_paid',
    'created_at',
    'id',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'pur_order_drafts';
$join         = [];
$where        = [];

if ($this->ci->input->post('from_date') && $this->ci->input->post('from_date') != '') {
    array_push($where, 'AND order_date >= "' . $this->ci->input->post('from_date') . '"');
}

if ($this->ci->input->post('to_date') && $this->ci->input->post('to_date') != '') {
    array_push($where, 'AND order_date <= "' . $this->ci->input->post('to_date') . '"');
}

$isPaid = $this->ci->input->post('is_paid');
if ($isPaid !== false && $isPaid !== '' && $isPaid !== null) {
    array_push($where, 'AND is_paid = ' . (int) $isPaid);
}

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['id']);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {
        $_data = $aRow[$aColumns[$i]];

        if ($aColumns[$i] == 'order_name') {
            $form_url = admin_url('purchase/pur_order_draft_form/' . $aRow['id']);
            $name     = '<a href="' . $form_url . '">';
            $name    .= pur_html_entity_decode($aRow['order_name']);
            $name    .= '</a>';

            $name .= '<div class="row-options">';
            $name .= '<a href="' . $form_url . '">' . _l('edit') . '</a>';
            if (has_permission('purchase_orders', '', 'delete') || is_admin()) {
                $name .= ' | <a href="' . admin_url('purchase/delete_pur_order_draft/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            }
            $name .= '</div>';

            $_data = $name;

        } elseif ($aColumns[$i] == 'order_date') {
            $_data = $aRow['order_date'] ? _d($aRow['order_date']) : '-';

        } elseif ($aColumns[$i] == 'grand_total') {
            $_data = app_format_money((float) $aRow['grand_total'], $base_currency->symbol);

        } elseif ($aColumns[$i] == 'is_paid') {
            if ($aRow['is_paid']) {
                $_data = '<span class="label label-success">' . _l('paid') . '</span>';
            } else {
                $_data = '<span class="label label-default">' . _l('unpaid') . '</span>';
            }

        } elseif ($aColumns[$i] == 'created_at') {
            $_data = $aRow['created_at'] ? _dt($aRow['created_at']) : '-';

        } elseif ($aColumns[$i] == 'id') {
            $_data = '';
            if (has_permission('purchase_orders', '', 'delete') || is_admin()) {
                $_data = '<a href="' . admin_url('purchase/delete_pur_order_draft/' . $aRow['id']) . '" class="btn btn-danger btn-xs _delete"><i class="fa fa-trash"></i></a>';
            }
        }

        $row[] = $_data;
    }

    $output['aaData'][] = $row;
}
