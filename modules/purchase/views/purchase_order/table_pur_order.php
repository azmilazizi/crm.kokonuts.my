<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * This table merges real purchase orders (tblpur_orders) and unconverted
 * PO drafts (tblpur_order_drafts) into a single DataTable via a UNION ALL
 * derived table, so $sTable below is passed as a raw subquery string
 * instead of a plain table name. Columns that only make sense for one
 * side (project/department/type/delivery/etc.) are NULL on the other side
 * and simply render blank.
 */

$aColumns = [
    'po_number',
    'vendor_name',
    'order_date',
    'description',
    'po_value',
    'approval_status',
    'delivery_date',
    'delivery_status',
    'is_paid',
];

if (isset($vendor) || isset($project)) {
    $aColumns = [
        'po_number',
        'po_value',
        'vendor_name',
        'order_date',
        'approval_status',
    ];
}

$po_branch = 'SELECT
        ' . db_prefix() . 'pur_orders.id as id,
        \'po\' as row_type,
        ' . db_prefix() . 'pur_orders.pur_order_number as po_number,
        ' . db_prefix() . 'pur_vendor.company as vendor_name,
        ' . db_prefix() . 'pur_orders.vendor as vendor,
        ' . db_prefix() . 'pur_orders.order_date as order_date,
        ' . db_prefix() . 'pur_orders.pur_order_name as description,
        ' . db_prefix() . 'pur_orders.subtotal as po_value,
        ' . db_prefix() . 'pur_orders.approve_status as approval_status,
        ' . db_prefix() . 'pur_orders.delivery_date as delivery_date,
        ' . db_prefix() . 'pur_orders.delivery_status as delivery_status,
        NULL as is_paid,
        ' . db_prefix() . 'pur_orders.currency as currency,
        ' . db_prefix() . 'pur_orders.project as project,
        ' . db_prefix() . 'pur_orders.department as department,
        ' . db_prefix() . 'pur_orders.type as type,
        ' . db_prefix() . 'pur_orders.pur_request as pur_request,
        ' . db_prefix() . 'pur_orders.addedfrom as addedfrom,
        ' . db_prefix() . 'pur_orders.buyer as buyer,
        ' . db_prefix() . 'pur_orders.total as full_total
    FROM ' . db_prefix() . 'pur_orders
    LEFT JOIN ' . db_prefix() . 'pur_vendor ON ' . db_prefix() . 'pur_vendor.userid = ' . db_prefix() . 'pur_orders.vendor';

$draft_branch = 'SELECT
        ' . db_prefix() . 'pur_order_drafts.id as id,
        \'draft\' as row_type,
        ' . db_prefix() . 'pur_order_drafts.order_name as po_number,
        ' . db_prefix() . 'pur_order_drafts.vendor_name as vendor_name,
        ' . db_prefix() . 'pur_order_drafts.vendor_id as vendor,
        ' . db_prefix() . 'pur_order_drafts.order_date as order_date,
        NULL as description,
        ' . db_prefix() . 'pur_order_drafts.grand_total as po_value,
        NULL as approval_status,
        NULL as delivery_date,
        NULL as delivery_status,
        ' . db_prefix() . 'pur_order_drafts.is_paid as is_paid,
        0 as currency,
        NULL as project,
        NULL as department,
        NULL as type,
        NULL as pur_request,
        NULL as addedfrom,
        NULL as buyer,
        ' . db_prefix() . 'pur_order_drafts.grand_total as full_total
    FROM ' . db_prefix() . 'pur_order_drafts';

$sIndexColumn = 'id';
$sTable       = '(' . $po_branch . ' UNION ALL ' . $draft_branch . ') as pur_orders_and_drafts';
$join         = [];

$where = [];

if(isset($vendor)){
    array_push($where, ' AND vendor = '.$vendor);
}

if(isset($project)){
    array_push($where, ' AND project = '.$project);
}

if ($this->ci->input->post('from_date')
    && $this->ci->input->post('from_date') != '') {
    array_push($where, 'AND order_date >= "'.$this->ci->input->post('from_date').'"');
}

if ($this->ci->input->post('to_date')
    && $this->ci->input->post('to_date') != '') {
    array_push($where, 'AND order_date <= "'.$this->ci->input->post('to_date').'"');
}

$status_post       = (array) $this->ci->input->post('status');
$numeric_statuses  = array_filter(array_map('intval', $status_post));
$include_drafts    = in_array('draft', $status_post, true);

$status_conditions = [];
if (count($numeric_statuses) > 0) {
    $status_conditions[] = "(row_type = 'po' AND approval_status IN (" . implode(',', $numeric_statuses) . "))";
}
if ($include_drafts) {
    $status_conditions[] = "(row_type = 'draft')";
}

if (count($status_conditions) > 0) {
    array_push($where, 'AND (' . implode(' OR ', $status_conditions) . ')');
} elseif (empty($status_post)) {
    // No status filter at all: keep the default view scoped to real POs only,
    // same as before drafts were merged into this table.
    array_push($where, "AND row_type = 'po'");
}

if ($this->ci->input->post('vendor')
    && count($this->ci->input->post('vendor')) > 0) {
    array_push($where, 'AND vendor IN (' . implode(',', $this->ci->input->post('vendor')) . ')');
}

if ($this->ci->input->post('project')
    && count($this->ci->input->post('project')) > 0) {
    array_push($where, 'AND project IN (' . implode(',', $this->ci->input->post('project')) . ')');
}

if ($this->ci->input->post('department')
    && count($this->ci->input->post('department')) > 0) {
    array_push($where, 'AND department IN (' . implode(',', $this->ci->input->post('department')) . ')');
}

if ($this->ci->input->post('delivery_status')
    && count($this->ci->input->post('delivery_status')) > 0) {
    array_push($where, 'AND delivery_status IN (' . implode(',', $this->ci->input->post('delivery_status')) . ')');
}

if ($this->ci->input->post('purchase_request')
    && count($this->ci->input->post('purchase_request')) > 0) {
    array_push($where, 'AND pur_request IN (' . implode(',', $this->ci->input->post('purchase_request')) . ')');
}

if(!has_permission('purchase_orders', '', 'view')){
   // Drafts have no per-staff ownership column, so a view_own-only staff
   // member can't be scoped to "their" drafts — exclude drafts entirely
   // for them rather than showing everyone's.
   array_push($where, "AND (row_type = 'po' AND (addedfrom = ".get_staff_user_id()." OR buyer = ".get_staff_user_id()." OR vendor IN (SELECT vendor_id FROM " . db_prefix() . "pur_vendor_admin WHERE staff_id=" . get_staff_user_id() . ") OR ".get_staff_user_id()." IN (SELECT staffid FROM " . db_prefix() . "pur_approval_details WHERE " . db_prefix() . "pur_approval_details.rel_type = 'pur_order' AND " . db_prefix() . "pur_approval_details.rel_id = pur_orders_and_drafts.id)))");
}

$type = $this->ci->input->post('type');
if (isset($type)) {
    $where_type = '';
    foreach ($type as $t) {
        if ($t != '') {
            if ($where_type == '') {
                $where_type .= ' AND (type = "' . $t . '"';
            } else {
                $where_type .= ' or type = "' . $t . '"';
            }
        }
    }
    if ($where_type != '') {
        $where_type .= ')';
        array_push($where, $where_type);
    }
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['id', 'row_type', 'vendor', 'currency', 'full_total']);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    $is_draft = ($aRow['row_type'] === 'draft');

    $base_currency = get_base_currency_pur();
    if ($aRow['currency'] != 0) {
        $base_currency = pur_get_currency_by_id($aRow['currency']);
    }

   for ($i = 0; $i < count($aColumns); $i++) {
        $_data = $aRow[$aColumns[$i]];

        if ($aColumns[$i] == 'po_value') {
            $_data = ($_data === null) ? '' : app_format_money((float) $_data, $base_currency->symbol);

        } elseif ($aColumns[$i] == 'po_number') {

            if ($is_draft) {
                $editUrl = admin_url('purchase/pur_order_draft_form/' . $aRow['id']);
                $numberOutput = '<a href="' . $editUrl . '">' . pur_html_entity_decode($aRow['po_number']) . '</a>';
                $numberOutput .= '<div class="row-options">';
                $numberOutput .= '<a href="' . $editUrl . '">' . _l('edit') . '</a>';
                if (has_permission('purchase_orders', '', 'delete') || is_admin()) {
                    $numberOutput .= ' | <a href="' . admin_url('purchase/delete_pur_order_draft/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }
                $numberOutput .= '</div>';
            } else {
                $numberOutput = '<a href="' . admin_url('purchase/purchase_order/' . $aRow['id']) . '"  onclick="init_pur_order(' . $aRow['id'] . '); return false;" >'.$aRow['po_number']. '</a>';

                $numberOutput .= '<div class="row-options">';

                if (has_permission('purchase_orders', '', 'view') || has_permission('purchase_orders', '', 'view_own')) {
                    $numberOutput .= ' <a href="' . admin_url('purchase/purchase_order/' . $aRow['id']) . '" onclick="init_pur_order(' . $aRow['id'] . '); return false;" >' . _l('view') . '</a>';
                }
                if ((has_permission('purchase_orders', '', 'edit') || is_admin()) && $aRow['approval_status'] != 2 ) {
                    $numberOutput .= ' | <a href="' . admin_url('purchase/pur_order/' . $aRow['id']) . '">' . _l('edit') . '</a>';
                }
                if (has_permission('purchase_orders', '', 'delete') || is_admin()) {
                    $numberOutput .= ' | <a href="' . admin_url('purchase/delete_pur_order/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                }
                $numberOutput .= '</div>';
            }

            $_data = $numberOutput;

        } elseif ($aColumns[$i] == 'vendor_name') {
            if (!empty($aRow['vendor']) && !empty($aRow['vendor_name'])) {
                $_data = '<a href="' . admin_url('purchase/vendor/' . $aRow['vendor']) . '" >' .  $aRow['vendor_name'] . '</a>';
            } else {
                $_data = $aRow['vendor_name'];
            }

        } elseif ($aColumns[$i] == 'order_date') {
            $_data = $aRow['order_date'] ? _d($aRow['order_date']) : '';

        } elseif ($aColumns[$i] == 'approval_status') {
            $_data = $is_draft ? '<span class="label label-primary"> '._l('pur_order_draft_filter_option').' </span>' : get_status_approve($aRow['approval_status']);

        } elseif ($aColumns[$i] == 'delivery_date') {
            $_data = $aRow['delivery_date'] ? _d($aRow['delivery_date']) : '';

        } elseif ($aColumns[$i] == 'delivery_status') {
            if ($is_draft) {
                $_data = '';
            } else {
                $delivery_status = '';

                if($aRow['delivery_status'] == 0){
                    $delivery_status = '<span class="inline-block label label-danger" id="status_span_'.$aRow['id'].'" task-status-table="undelivered">'._l('undelivered');
                }else if($aRow['delivery_status'] == 1){
                    $delivery_status = '<span class="inline-block label label-success" id="status_span_'.$aRow['id'].'" task-status-table="completely_delivered">'._l('completely_delivered');
                }else if($aRow['delivery_status'] == 2){
                    $delivery_status = '<span class="inline-block label label-info" id="status_span_'.$aRow['id'].'" task-status-table="pending_delivered">'._l('pending_delivered');
                }else if($aRow['delivery_status'] == 3){
                    $delivery_status = '<span class="inline-block label label-warning" id="status_span_'.$aRow['id'].'" task-status-table="partially_delivered">'._l('partially_delivered');
                }

                if(has_permission('purchase_orders', '', 'edit') || is_admin()){
                    $delivery_status .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="tablePurOderStatus-' . $aRow['id'] . '">';

                    if($aRow['delivery_status'] == 0){
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 1 ,' . $aRow['id'] . '); return false;">' ._l('completely_delivered') . '</a></li>';
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 2 ,' . $aRow['id'] . '); return false;">' ._l('pending_delivered') . '</a></li>';
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 3 ,' . $aRow['id'] . '); return false;">' ._l('partially_delivered') . '</a></li>';
                    }else if($aRow['delivery_status'] == 1){
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 0 ,' . $aRow['id'] . '); return false;">' ._l('undelivered') . '</a></li>';
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 2 ,' . $aRow['id'] . '); return false;">' ._l('pending_delivered') . '</a></li>';
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 3 ,' . $aRow['id'] . '); return false;">' ._l('partially_delivered') . '</a></li>';
                    }else if($aRow['delivery_status'] == 2) {
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 0 ,' . $aRow['id'] . '); return false;">' ._l('undelivered') . '</a></li>';
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 1 ,' . $aRow['id'] . '); return false;">' ._l('completely_delivered') . '</a></li>';
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 3 ,' . $aRow['id'] . '); return false;">' ._l('partially_delivered') . '</a></li>';
                    }else if($aRow['delivery_status'] == 3){
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 0 ,' . $aRow['id'] . '); return false;">' ._l('undelivered') . '</a></li>';
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 1 ,' . $aRow['id'] . '); return false;">' ._l('completely_delivered') . '</a></li>';
                        $delivery_status .= '<li><a href="#" onclick="change_delivery_status( 2 ,' . $aRow['id'] . '); return false;">' ._l('pending_delivered') . '</a></li>';
                    }

                    $delivery_status .= '</ul>';
                }
                $delivery_status .= '</span>';
                $_data = $delivery_status;
            }

        } elseif ($aColumns[$i] == 'is_paid') {
            if ($is_draft) {
                $_data = $aRow['is_paid']
                    ? '<span class="label label-success">' . _l('paid') . '</span>'
                    : '<span class="label label-default">' . _l('unpaid') . '</span>';
            } else {
                $this->ci->load->model('purchase/purchase_model');
                $paid = $aRow['full_total'] - purorder_inv_left_to_pay($aRow['id']);
                $percent = ($aRow['full_total'] > 0) ? ($paid / $aRow['full_total']) * 100 : 0;

                $_data = '
                    <div class="progress" style="position: relative;">
                        <span style="
                            position: absolute;
                            top: 0;
                            left: 50%;
                            transform: translateX(-50%);
                            white-space: nowrap;
                            pointer-events: none"
                        > ' .round($percent).' % </span>
                        <div class="progress-bar progress-bar-success" role="progressbar"
                            aria-valuenow="' .round($percent).'" aria-valuemin="0" aria-valuemax="100" style="width:'.round($percent).'%" data-percent="' .round($percent).'">
                        </div>
                    </div>
                ';
            }
        }

        $row[] = $_data;
    }
    $output['aaData'][] = $row;

}
