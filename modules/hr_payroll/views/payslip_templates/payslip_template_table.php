<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
	'id',
	'templates_name',
	'staff_id_created',
	'date_created',
];
$sIndexColumn = 'id';
$sTable = db_prefix() . 'hrp_payslip_templates';

$where = [];
$join= [];

$array_staffid_by_permission = get_array_staffid_by_permission();

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['id', 'department_id', 'role_employees', 'staff_employees', 'except_staff']);

$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
	$row = [];

		//load by staff
		if(!is_admin() && !has_permission('hrp_payslip_template','','view') && $aRow['staff_id_created'] != get_staff_user_id()){
      	//View own
			$staffids = $this->ci->hr_payroll_model->payslip_template_get_staffid($aRow['department_id'], $aRow['role_employees'], $aRow['staff_employees'], $aRow['except_staff']);

			if($staffids != false){
				$check_dp=false;

				foreach (new_explode(',', $staffids) as $staffid) {
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

		if($aColumns[$i] == 'id'){
			$_data = $aRow['id'];
		}elseif ($aColumns[$i] == 'templates_name') {
			$code = '<a href="' . admin_url('hr_payroll/view_payslip_templates_detail/' . $aRow['id']) . '">' . $aRow['templates_name'] . '</a>';
			$code .= '<div class="row-options">';

			$code .= '<a href="' . admin_url('hr_payroll/view_payslip_templates_detail/' . $aRow['id']) . '" >' . _l('view_detail') . '</a>';

			if (has_permission('hrp_payslip_template', '', 'edit') || is_admin()) {

				$code .= ' | <a href="#" onclick="edit_payslip_template(this, '.$aRow['id'] .'); return false;"  >' . _l('edit') . '</a>';
			}
			if (has_permission('hrp_payslip_template', '', 'delete') || is_admin()) {
				$code .= ' | <a href="' . admin_url('hr_payroll/delete_payslip_template/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
			}
			$code .= '</div>';

			$_data = $code;

		}elseif($aColumns[$i] == 'date_created'){
			$_data = _dt($aRow['date_created']);

		} elseif ($aColumns[$i] == 'staff_id_created') {
			$_data = '<a href="' . admin_url('staff/profile/' . $aRow['staff_id_created']) . '">' . staff_profile_image($aRow['staff_id_created'], [
                'staff-profile-image-small',
                ]) . '</a>';
            $_data .= ' <a href="' . admin_url('staff/profile/' . $aRow['staff_id_created']) . '">' . get_staff_full_name($aRow['staff_id_created']) . '</a>';

		}

		$row[] = $_data;
	}

	$output['aaData'][] = $row;
}

