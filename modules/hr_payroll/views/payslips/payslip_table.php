<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
	'id',
	'payslip_name',
	'payslip_template_id',
	'payslip_month',
	'payslip_range',
	'staff_id_created',
	'date_created',
	'payslip_status',
	'1',
];
$sIndexColumn = 'id';
$sTable = db_prefix() . 'hrp_payslips';

$where = [];
$join= [];

$array_staffid_by_permission = get_array_staffid_by_permission();

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['id']);

$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
	$row = [];

	//load by staff
	if(!is_admin() && !has_permission('hrp_payslip','','view') && $aRow['staff_id_created'] != get_staff_user_id()){
  	//View own
		$staffids = $this->ci->hr_payroll_model->payslip_of_staff($aRow['id']);

		if(count($staffids) > 0){
			$check_dp=false;

			foreach ($staffids as $staffid) {
			    if(in_array($staffid, $array_staffid_by_permission)){
			    	$check_dp = true;//jump
			    }

			    if($check_dp == true){
					break;//jump
				}
			}

			if($check_dp == false){
					continue;//jump
			}
		}else{
			continue;//jump
		}
	}

	for ($i = 0; $i < count($aColumns); $i++) {

		if($aColumns[$i] == 'id') {
			$_data = $aRow['id'];

		}elseif ($aColumns[$i] == 'payslip_name') {

			//load by manager
			if(!is_admin() && !has_permission('hrp_payslip','','view')){
			//View own
				$code = '<a href="' . admin_url('hr_payroll/view_payslip_detail_v2/' . $aRow['id']) . '">' . $aRow['payslip_name'] . '</a>';
				$code .= '<div class="row-options">';

				$code .= '<a href="' . admin_url('hr_payroll/view_payslip_detail_v2/' . $aRow['id']) . '" >' . _l('view') . '</a>';

			}else{
			//admin or view global
				$code = '<a href="' . admin_url('hr_payroll/view_payslip_detail/' . $aRow['id']) . '">' . $aRow['payslip_name'] . '</a>';
				$code .= '<div class="row-options">';

				$code .= '<a href="' . admin_url('hr_payroll/view_payslip_detail/' . $aRow['id']) . '" >' . _l('view') . '</a>';
			}

			if (has_permission('hrp_payslip', '', 'edit') || is_admin()) {
				$code .= ' | <a href="#" onclick="edit_payslip(this, '.$aRow['id'] .', '.$aRow['payslip_template_id'] .'); return false;"  >' . _l('edit') . '</a>';

			}
			if (has_permission('hrp_payslip', '', 'delete') || is_admin()) {
				$code .= ' | <a href="' . admin_url('hr_payroll/delete_payslip/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
			}
			$code .= '</div>';

			$_data = $code;

		}elseif($aColumns[$i] == 'payslip_template_id'){
			$_data = get_payslip_template_name($aRow['payslip_template_id']);

		}elseif($aColumns[$i] == 'payslip_month'){
			$_data =  date('m-Y', strtotime($aRow['payslip_month']));

		}elseif($aColumns[$i] == 'payslip_range'){
			$_data =  $aRow['payslip_range'];

		} elseif ($aColumns[$i] == 'staff_id_created') {
			$_data = '<a href="' . admin_url('staff/profile/' . $aRow['staff_id_created']) . '">' . staff_profile_image($aRow['staff_id_created'], [
                'staff-profile-image-small',
                ]) . '</a>';
            $_data .= ' <a href="' . admin_url('staff/profile/' . $aRow['staff_id_created']) . '">' . get_staff_full_name($aRow['staff_id_created']) . '</a>';

		} elseif ($aColumns[$i] == 'date_created') {
			$_data = _dt($aRow['date_created']);
		}elseif ($aColumns[$i] == 'payslip_status') {
			if($aRow['payslip_status'] == 'payslip_closing'){
				$_data = ' <span class="label label-success "> '._l($aRow['payslip_status']).' </span>';
			}else{
				$_data = ' <span class="label label-primary"> '._l($aRow['payslip_status']).' </span>';
			}

		}elseif($aColumns[$i] == '1') {

			if((has_permission('hrp_payslip','','delete')) && $aRow['payslip_status'] == 'payslip_closing' ){

				$_data = '<a class="btn btn-primary btn-xs mleft5" id="confirmDelete" data-toggle="tooltip" title="" href="'. admin_url('hr_payroll/payslip_update_status/'.$aRow['id']).'"  data-original-title="'._l('payslip_opening').'"><i class="fa fa-check"></i></a>';

				$_data .= '<a class="btn btn-success btn-xs mleft5 hrp_payslip_download" data-toggle="tooltip" title="" href="'. admin_url('hr_payroll/payslip_manage_export_pdf/'.$aRow['id']).'"  data-original-title="'._l('hrp_payslip_download').'" data-loading-text="Waitting..."><i class="fa fa-download"></i></a>';
				
			}else{
				$_data ='';
			}

		}

		$row[] = $_data;
	}

	$output['aaData'][] = $row;
}
