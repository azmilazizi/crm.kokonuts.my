<?php

defined('BASEPATH') or exit('No direct script access allowed');

$base_currency = get_base_currency();

if(get_hr_payroll_option('hrp_customize_staff_payslip_column') == 1){
	$get_customize_staff_payslip_columns = get_customize_staff_payslip_columns(true);
	$column_names = $get_customize_staff_payslip_columns['column_names'];
	$aColumns = [];
	if(isset($column_names['month'])){
		$aColumns[] = db_prefix().'hrp_payslip_details.month';
		unset($column_names['month']);
	}
	$aColumns = array_merge($aColumns, $column_names);
}else{

	$aColumns = [
		db_prefix().'hrp_payslip_details.month',
		'payslip_range',
		'pay_slip_number',
		'gross_pay',
		'total_deductions',
		'income_tax_paye',
		'it_rebate_value',
		'commission_amount',
		'bonus_kpi',
		'total_insurance',
		'net_pay',
		'total_cost',
	];
}

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'hrp_payslip_details';

$join = [
	'LEFT JOIN ' . db_prefix() . 'hrp_payslips ON ' . db_prefix() . 'hrp_payslip_details.payslip_id = ' . db_prefix() . 'hrp_payslips.id',
];

$where  = [];
$filter = [];


if($this->ci->input->post('memberid')){
	$where_staff = '';
	$staffs = $this->ci->input->post('memberid');
	if($staffs != '')
	{
		if($where_staff == ''){
			$where_staff .= ' where '.db_prefix().'hrp_payslip_details.staff_id = "'.$staffs. '"';
		}else{
			$where_staff .= ' or '.db_prefix().'hrp_payslip_details.staff_id = "' .$staffs.'"';
		}
	}
	if($where_staff != '')
	{
		array_push($where, $where_staff);
	}
}
array_push($where, 'AND '.db_prefix().'hrp_payslips.payslip_status = "payslip_closing"');


// Fix for big queries. Some hosting have max_join_limit

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix().'hrp_payslip_details.id', db_prefix().'hrp_payslip_details.json_data', db_prefix().'hrp_payslip_details.actual_workday_probation', db_prefix().'hrp_payslip_details.payslip_id']);

$output  = $result['output'];
$rResult = $result['rResult'];
foreach ($rResult as $aRow) {

	$payslip = $this->ci->hr_payroll_model->get_hrp_payslip($aRow['payslip_id']);
	if($payslip && is_null($payslip->to_currency_name)){
		$base_currency = get_base_currency();
		$base_currency_id = 0;
		if ($base_currency) {
			$payslip->to_currency_name = $base_currency->name;
		}
	}

	$row = [];

	$check_payslip_has_pdf_template = check_payslip_has_pdf_template($aRow['payslip_id']);

	if(get_hr_payroll_option('hrp_customize_staff_payslip_column') == 1){
		
		foreach ($column_names as $key => $column_name) {
			if($key == 1){
				$subjectOutput = '';
				if(is_numeric($check_payslip_has_pdf_template) && is_numeric($check_payslip_has_pdf_template) != 0 ){

				$subjectOutput .= '<a href="'.admin_url('hr_payroll/new_employee_export_pdf/'.$aRow['id'].'?output_type=I').'" o>' . $aRow[$column_name] . '</a>';

				$subjectOutput .= '<div class="row-options">';
				$subjectOutput .= '<a href="'.admin_url('hr_payroll/new_employee_export_pdf/'.$aRow['id'].'?output_type=I').'" target="_blank">' . _l('view_pdf_in_new_window') .' </a>';
				$subjectOutput .= '</div>';

				}else{

				$subjectOutput .= '<a href="#" onclick="member_view_payslip(' . $aRow['id'] . ');return false;">' . $aRow[$column_name] . '</a>';

				$subjectOutput .= '<div class="row-options">';
				$subjectOutput .= '<a href="#" onclick="member_view_payslip(' . $aRow['id'] . ');return false;">' . _l('hr_view') .' </a>';
				$subjectOutput .= '| <a href="'.admin_url('hr_payroll/employee_export_pdf/'.$aRow['id'].'?output_type=I').'" target="_blank">' . _l('view_pdf_in_new_window') .' </a>';
				$subjectOutput .= '</div>';

				}

				$row[] = $subjectOutput;

			}else{
				$row[] = $aRow[$column_name];
			}
		}

	}else{
		$row[] = $aRow['id'];

		$subjectOutput = '';
		if(is_numeric($check_payslip_has_pdf_template) && is_numeric($check_payslip_has_pdf_template) != 0 ){

			$subjectOutput .= '<a href="'.admin_url('hr_payroll/new_employee_export_pdf/'.$aRow['id'].'?output_type=I').'" >' . $aRow['pay_slip_number'] . '</a>';

			$subjectOutput .= '<div class="row-options">';
			$subjectOutput .= '<a href="'.admin_url('hr_payroll/new_employee_export_pdf/'.$aRow['id'].'?output_type=I').'" target="_blank">' . _l('view_pdf_in_new_window') .' </a>';
			$subjectOutput .= '</div>';

		}else{


			$subjectOutput .= '<a href="#" onclick="member_view_payslip(' . $aRow['id'] . ');return false;">' . $aRow['pay_slip_number'] . '</a>';


			$subjectOutput .= '<div class="row-options">';
			$subjectOutput .= '<a href="#" onclick="member_view_payslip(' . $aRow['id'] . ');return false;">' . _l('hr_view') .' </a>';
			$subjectOutput .= '| <a href="'.admin_url('hr_payroll/employee_export_pdf/'.$aRow['id'].'?output_type=I').'" target="_blank">' . _l('view_pdf_in_new_window') .' </a>';
			$subjectOutput .= '</div>';
		}


		$row[] = $subjectOutput;

		$row[] = date('m-Y',strtotime($aRow[db_prefix().'hrp_payslip_details.month']));
		$row[] = $aRow['payslip_range'];

		$hrp_payslip_salary_allowance = hrp_payslip_json_data_decode($aRow['json_data'], $payslip);


		if( $hrp_payslip_salary_allowance['integration_hr']){
		//probation contract
			$probation_salary ='';
			$probation_salary .= _l('hrp_salary').': '.currency_converter_value($hrp_payslip_salary_allowance['probation_salary'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true).'<br>';
			$probation_salary .= _l('hrp_allowance').': '.currency_converter_value($hrp_payslip_salary_allowance['probation_allowance'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);

			$row[] = $probation_salary;

		//formal contract
			$formal_salary ='';
			$formal_salary .= _l('hrp_salary').': '.currency_converter_value($hrp_payslip_salary_allowance['formal_salary'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true).'<br>';
			$formal_salary .= _l('hrp_allowance').': '.currency_converter_value($hrp_payslip_salary_allowance['formal_allowance'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);

			$row[] = $formal_salary;

		}else{

			$probation_salary ='';
			$probation_salary .= _l('hrp_salary').' + '._l('hrp_allowance').': '.currency_converter_value($hrp_payslip_salary_allowance['probation_salary'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true).'<br>';

			$row[] = $probation_salary;

		//formal contract
			$formal_salary ='';
			$formal_salary .= _l('hrp_salary').' + '._l('hrp_allowance').': '.currency_converter_value($hrp_payslip_salary_allowance['formal_salary'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true).'<br>';

			$row[] = $formal_salary;

		}


		$row[] = currency_converter_value($aRow['gross_pay'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
		$row[] = currency_converter_value($aRow['total_deductions'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
		$row[] = currency_converter_value($aRow['income_tax_paye'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
		$row[] = currency_converter_value($aRow['it_rebate_value'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
		$row[] = currency_converter_value($aRow['commission_amount'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
		$row[] = currency_converter_value($aRow['bonus_kpi'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
		$row[] = currency_converter_value($aRow['total_insurance'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
		$row[] = currency_converter_value($aRow['net_pay'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
		$row[] = currency_converter_value($aRow['total_cost'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
	}

	$row['DT_RowClass'] = 'has-row-options';
	
	$output['aaData'][] = $row;
}
