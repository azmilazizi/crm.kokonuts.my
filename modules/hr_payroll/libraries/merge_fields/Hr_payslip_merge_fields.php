<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Hr_payslip_merge_fields extends App_merge_fields
{
	public function build()
	{
		$payslip_columns = [];
		$payslip_columns[] = [
			'name'      => _l('hrp_time_range'),
			'key'       => '{payslip_range}',
			'available' => [
				'hr_payslip',
			],
		];

		$CI             = &get_instance();
		$CI->load->model('hr_payroll/hr_payroll_model');

		$get_hrp_payroll_columns = $CI->hr_payroll_model->get_hrp_payroll_columns();
		foreach ($get_hrp_payroll_columns as $value) {
		    $payslip_columns[] = [
		    	'name'      => $value['column_key'],
				'key'       => '{'.$value['function_name'].'}',
				'available' => [
					'hr_payslip',
				],
		    ];
		}
		return $payslip_columns;
	}

	/**
	 * Merge field for contracts
	 * @param  mixed $contract_id contract id
	 * @return array
	 */
	public function format($payslip_detail_id)
	{
		$this->ci->load->model('hr_payroll/hr_payroll_model');


		$fields = [];
		$payslip_range = '';

		$this->ci->db->select('*');
        $this->ci->db->where('id', $payslip_detail_id);
		$payslip_detail = $this->ci->db->get(db_prefix() . 'hrp_payslip_details')->row();



		if (!$payslip_detail) {
			return $fields;
		}

		$hrp_payslip = $this->ci->hr_payroll_model->get_hrp_payslip($payslip_detail->payslip_id);
		if($hrp_payslip){
			$payslip_range = $hrp_payslip->payslip_range;
		}

		$currency = get_base_currency();

		if($hrp_payslip && is_null($hrp_payslip->to_currency_name)){
			if ($currency) {
				$hrp_payslip->to_currency_name = $currency->name;
			}
		}
// currency_converter_value($payslip_detail['commission_amount'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true)

		$fields['{staff_id}'] = $payslip_detail->staff_id;
		$fields['{month}'] = $payslip_detail->month;
		$fields['{pay_slip_number}'] = $payslip_detail->pay_slip_number;
		$fields['{payment_run_date}'] = $payslip_detail->payment_run_date;
		$fields['{employee_number}'] = $payslip_detail->employee_number;
		$fields['{employee_name}'] = $payslip_detail->employee_name;
		$fields['{dept_name}'] = $payslip_detail->dept_name;
		$fields['{standard_workday}'] = $payslip_detail->standard_workday;
		$fields['{actual_workday}'] = $payslip_detail->actual_workday;
		$fields['{paid_leave}'] = $payslip_detail->paid_leave;
		$fields['{unpaid_leave}'] = $payslip_detail->unpaid_leave;
		$fields['{gross_pay}'] = currency_converter_value($payslip_detail->gross_pay, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{income_tax_paye}'] = currency_converter_value($payslip_detail->income_tax_paye, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{total_deductions}'] = currency_converter_value($payslip_detail->total_deductions, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{net_pay}'] = currency_converter_value($payslip_detail->net_pay, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{it_rebate_code}'] = $payslip_detail->it_rebate_code;
		$fields['{it_rebate_value}'] = currency_converter_value($payslip_detail->it_rebate_value, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{income_tax_code}'] = $payslip_detail->income_tax_code;
		$fields['{commission_amount}'] = currency_converter_value($payslip_detail->commission_amount, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{bonus_kpi}'] = currency_converter_value($payslip_detail->bonus_kpi, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{total_cost}'] = currency_converter_value($payslip_detail->total_cost, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{total_insurance}'] = currency_converter_value($payslip_detail->total_insurance, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{salary_of_the_probationary_contract}'] = currency_converter_value($payslip_detail->salary_of_the_probationary_contract, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{salary_of_the_formal_contract}'] = currency_converter_value($payslip_detail->salary_of_the_formal_contract, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{taxable_salary}'] = currency_converter_value($payslip_detail->taxable_salary, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{actual_workday_probation}'] = $payslip_detail->actual_workday_probation;
		$fields['{total_hours_by_tasks}'] = $payslip_detail->total_hours_by_tasks;
		$fields['{salary_from_tasks}'] = currency_converter_value($payslip_detail->salary_from_tasks, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
		$fields['{payslip_range}'] = $payslip_range;

		$json_data = json_decode($payslip_detail->json_data, true);
		if(is_array($json_data)){
			foreach ($json_data as $key => $value) {
				if(preg_match('/^st1_/', $key) ){
					
					$value = currency_converter_value($value, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
				}elseif(preg_match('/^al1_/', $key) ){
					
					$value = currency_converter_value($value, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
				}elseif(preg_match('/^st2_/', $key) ){
					
					$value = currency_converter_value($value, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
				}elseif(preg_match('/^al2_/', $key)){
					
					$value = currency_converter_value($value, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
				}elseif(preg_match('/^earning1_/', $key) ){
					
					$value = currency_converter_value($value, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
				}elseif(preg_match('/^earning2_/', $key) ){
					
					$value = currency_converter_value($value, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
				}elseif(preg_match('/^deduction_/', $key) ){
					
					$value = currency_converter_value($value, $hrp_payslip->to_currency_rate, $hrp_payslip->to_currency_name ?? '', true);
				}

				$fields['{'.$key.'}'] = $value;
			}
		}
		return $fields;
	}
}
