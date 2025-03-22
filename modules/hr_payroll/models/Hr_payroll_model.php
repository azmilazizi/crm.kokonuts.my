<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * hr payroll model
 */
class Hr_payroll_model extends App_Model {
	public function __construct() {
		parent::__construct();
	}

	
	/**
	 * check format date Y-m-d
	 * @param  [type] $date 
	 * @return boolean       
	 */
	public function check_format_date($date){
		if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$date)) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * get income tax rate
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_income_tax_rate($id = false){
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'hrp_income_tax_rates')->row();
        }

        if ($id == false) {
        return $this->db->query('select * from '.db_prefix().'hrp_income_tax_rates')->result_array();
        }

    }



	/**
	 * update income tax rates
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function update_income_tax_rates($data)
	{
		$affectedRows = 0;

		if (isset($data['incometax_rate_hs'])) {
			$incometax_rate_hs = $data['incometax_rate_hs'];
			unset($data['incometax_rate_hs']);
		}

		if(isset($incometax_rate_hs)){
			$incometax_rate_detail = json_decode($incometax_rate_hs);

			$es_detail = [];
			$row = [];
			$rq_val = [];
			$header = [];

			$header[] = 'tax_bracket_value_from';
			$header[] = 'tax_bracket_value_to';
			$header[] = 'tax_rate';
			$header[] = 'equivalent_value';
			$header[] = 'effective_rate';
			$header[] = 'id';

			foreach ($incometax_rate_detail as $key => $value) {
				if($value[2] != ''){
					$es_detail[] = array_combine($header, $value);

				}
			}
		}

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];


		foreach ($es_detail as $key => $value) {
			if($value['id'] != ''){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		$row['delete'] = implode(",",$row['delete']);
		$this->db->where('id NOT IN ('.$row['delete'] .') ');
		$this->db->delete(db_prefix().'hrp_income_tax_rates');
		if($this->db->affected_rows() > 0){
			$affectedRows++;
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_income_tax_rates', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_income_tax_rates', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}

		return false;
	}

	/**
	 * get income tax rebates
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_income_tax_rebates($id = false){
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'hrp_income_tax_rebates')->row();
        }

        if ($id == false) {
        return $this->db->query('select * from '.db_prefix().'hrp_income_tax_rebates')->result_array();
        }

    }

	/**
	 * update income tax rebates
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function update_income_tax_rebates($data)
	{
		$affectedRows = 0;

		if (isset($data['incometax_rebates_hs'])) {
			$incometax_rebates_hs = $data['incometax_rebates_hs'];
			unset($data['incometax_rebates_hs']);
		}

		if(isset($incometax_rebates_hs)){
			$incometax_rate_detail = json_decode($incometax_rebates_hs);

			$es_detail = [];
			$row = [];
			$rq_val = [];
			$header = [];

			$header[] = 'code';
			$header[] = 'description';
			$header[] = 'value';
			$header[] = 'total';
			$header[] = 'id';

			foreach ($incometax_rate_detail as $key => $value) {
				//only get row "value" != 0
				if($value[2] != ''){
					$es_detail[] = array_combine($header, $value);

				}
			}
		}
		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];


		foreach ($es_detail as $key => $value) {
			if($value['id'] != ''){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}


		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		$row['delete'] = implode(",",$row['delete']);
		$this->db->where('id NOT IN ('.$row['delete'] .') ');
		$this->db->delete(db_prefix().'hrp_income_tax_rebates');
		if($this->db->affected_rows() > 0){
			$affectedRows++;
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_income_tax_rebates', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_income_tax_rebates', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}

		return false;
	}


	/**
	 * get earnings list
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_earnings_list($id = false){
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'hrp_earnings_list')->row();
        }

        if ($id == false) {
        return $this->db->query('select * from '.db_prefix().'hrp_earnings_list')->result_array();
        }

    }


    /**
     * update earnings list
     * @param  [type] $data 
     * @return [type]       
     */
    public function update_earnings_list($data)
	{
		$affectedRows = 0;

		if (isset($data['earnings_list_hs'])) {
			$earnings_list_hs = $data['earnings_list_hs'];
			unset($data['earnings_list_hs']);
		}

		if(isset($earnings_list_hs)){
			$incometax_rate_detail = json_decode($earnings_list_hs);

			$es_detail = [];
			$row = [];
			$rq_val = [];
			$header = [];

			$header[] = 'code';
			$header[] = 'description';
			$header[] = 'short_name';
			$header[] = 'taxable';
			$header[] = 'basis_type';
			$header[] = 'id';

			foreach ($incometax_rate_detail as $key => $value) {
				//only get row "value" != 0
				if($value[0] != '' ){
					$es_detail[] = array_combine($header, $value);

				}
			}
		}
		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		foreach ($es_detail as $key => $value) {
			if($value['id'] != ''){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}


		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		$row['delete'] = implode(",",$row['delete']);
		$this->db->where('id NOT IN ('.$row['delete'] .') ');
		$this->db->delete(db_prefix().'hrp_earnings_list');
		if($this->db->affected_rows() > 0){
			$affectedRows++;
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_earnings_list', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_earnings_list', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}

		return false;
	}


	/**
	 * get salary deductions list
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_salary_deductions_list($id = false){
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'hrp_salary_deductions_list')->row();
        }

        if ($id == false) {
        return $this->db->query('select * from '.db_prefix().'hrp_salary_deductions_list')->result_array();
        }

    }


    /**
     * update earnings list
     * @param  [type] $data 
     * @return [type]       
     */
    public function update_salary_deductions_list($data)
	{
		$affectedRows = 0;

		if (isset($data['salary_deductions_list_hs'])) {
			$salary_deductions_list_hs = $data['salary_deductions_list_hs'];
			unset($data['salary_deductions_list_hs']);
		}

		if(isset($salary_deductions_list_hs)){
			$incometax_rate_detail = json_decode($salary_deductions_list_hs);

			$es_detail = [];
			$row = [];
			$rq_val = [];
			$header = [];

			$header[] = 'code';
			$header[] = 'description';
			$header[] = 'rate';
			$header[] = 'basis';
			$header[] = 'id';

			foreach ($incometax_rate_detail as $key => $value) {
				//only get row "value" != 0
				if($value[0] != ''  && $value[3] != ''){
					$es_detail[] = array_combine($header, $value);

				}
			}
		}
		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		foreach ($es_detail as $key => $value) {
			if($value['id'] != ''){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}


		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		$row['delete'] = implode(",",$row['delete']);
		$this->db->where('id NOT IN ('.$row['delete'] .') ');
		$this->db->delete(db_prefix().'hrp_salary_deductions_list');
		if($this->db->affected_rows() > 0){
			$affectedRows++;
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_salary_deductions_list', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_salary_deductions_list', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}

		return false;
	}


	/**
	 * get insurance list
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_insurance_list($id = false){
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'hrp_insurance_list')->row();
        }

        if ($id == false) {
        return $this->db->query('select * from '.db_prefix().'hrp_insurance_list')->result_array();
        }

    }


    /**
     * update insurance list
     * @param  [type] $data 
     * @return [type]       
     */
    public function update_insurance_list($data)
	{
		$affectedRows = 0;

		if (isset($data['insurance_list_hs'])) {
			$insurance_list_hs = $data['insurance_list_hs'];
			unset($data['insurance_list_hs']);
		}

		if(isset($insurance_list_hs)){
			$incometax_rate_detail = json_decode($insurance_list_hs);

			$es_detail = [];
			$row = [];
			$rq_val = [];
			$header = [];

			$header[] = 'code';
			$header[] = 'description';
			$header[] = 'rate';
			$header[] = 'basis';
	
			$header[] = 'id';

			foreach ($incometax_rate_detail as $key => $value) {
				//only get row "value" != 0
				if($value[0] != ''  && $value[3] != ''){
					$es_detail[] = array_combine($header, $value);

				}
			}
		}
		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		foreach ($es_detail as $key => $value) {
			if($value['id'] != ''){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		$row['delete'] = implode(",",$row['delete']);
		$this->db->where('id NOT IN ('.$row['delete'] .') ');
		$this->db->delete(db_prefix().'hrp_insurance_list');
		if($this->db->affected_rows() > 0){
			$affectedRows++;
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_insurance_list', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_insurance_list', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}

		return false;
	}


	/**
	 * get company contributions list
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_company_contributions_list($id = false){
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'hrp_company_contributions_list')->row();
        }

        if ($id == false) {
        return $this->db->query('select * from '.db_prefix().'hrp_company_contributions_list')->result_array();
        }

    }


    /**
     * update company contributions list
     * @param  [type] $data 
     * @return [type]       
     */
    public function update_company_contributions_list($data)
	{
		$affectedRows = 0;

		if (isset($data['company_contributions_list_hs'])) {
			$company_contributions_list_hs = $data['company_contributions_list_hs'];
			unset($data['company_contributions_list_hs']);
		}

		if(isset($company_contributions_list_hs)){
			$incometax_rate_detail = json_decode($company_contributions_list_hs);

			$es_detail = [];
			$row = [];
			$rq_val = [];
			$header = [];

			$header[] = 'code';
			$header[] = 'description';
			$header[] = 'rate';
			$header[] = 'basis';
			$header[] = 'earn_inclusion';
			$header[] = 'earn_exclusion';
			$header[] = 'earnings_max';
			$header[] = 'id';

			foreach ($incometax_rate_detail as $key => $value) {
				//only get row "value" != 0
				if($value[0] != ''  && $value[4] != ''){
					$es_detail[] = array_combine($header, $value);

				}
			}
		}
		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		foreach ($es_detail as $key => $value) {
			if($value['id'] != ''){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}


		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		$row['delete'] = implode(",",$row['delete']);
		$this->db->where('id NOT IN ('.$row['delete'] .') ');
		$this->db->delete(db_prefix().'hrp_company_contributions_list');
		if($this->db->affected_rows() > 0){
			$affectedRows++;
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_company_contributions_list', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_company_contributions_list', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}

		return false;
	}


	/**
	 * update data integration
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function update_data_integration($data)
	{
		if(isset($data['column_name'])){
			$column_name = $data['column_name'];
			unset($data['column_name']);
		}
		if(isset($data['order_number'])){
			$order_number = $data['order_number'];
			unset($data['order_number']);
		}
		
		$affected_rows = 0;
		
		$data_integration = array(
			'option_val' => 0
		);
		$this->db->where('option_name', 'integrated_hrprofile');
		$this->db->or_where('option_name', 'integrated_timesheets');
		$this->db->or_where('option_name', 'integrated_commissions');
		$this->db->or_where('option_name', 'hrp_customize_staff_payslip_column');
		$this->db->update(db_prefix().'hr_payroll_option', $data_integration); 
		if($this->db->affected_rows() > 0){
			$affected_rows++;
		}

		if(count($data) > 0){
			foreach ($data as $key => $value) {
					
				switch ($key) {
					case 'integration_actual_workday':
						$this->db->where('option_name', $key);
						$this->db->update(db_prefix().'hr_payroll_option', ['option_val' => implode(',', $value)]); 
						break;

					case 'integration_paid_leave':
						$this->db->where('option_name', $key);
						$this->db->update(db_prefix().'hr_payroll_option', ['option_val' => implode(',', $value)]); 
						break;

					case 'integration_unpaid_leave':
						$this->db->where('option_name', $key);
						$this->db->update(db_prefix().'hr_payroll_option', ['option_val' => implode(',', $value)]); 
						break;

					case 'standard_working_time':
						$this->db->where('option_name', $key);
						$this->db->update(db_prefix().'hr_payroll_option', ['option_val' => $value]); 
						break;
					
					default:
					$this->db->where('option_name', $value);
					$this->db->update(db_prefix().'hr_payroll_option', ['option_val' => 1]); 
						break;
				}


			    if($this->db->affected_rows() > 0){
			    	$affected_rows++;
			    }
			}
		}

		$this->db->where('id > 0');
		$this->db->delete(db_prefix().'hrp_customize_staff_payslip_columns');

		if(isset($column_name)){
			$hrp_customize_staff_payslip_columns = [];

			foreach ($column_name as $key => $value) {
				if(strlen($value) > 0){
					$order_number_value = isset($order_number[$key]) ? $order_number[$key] : 1;
					$hrp_customize_staff_payslip_columns[] = [
						'column_name' => $value,
						'order_number' => $order_number_value,
					];
				}
			}
			if(count($hrp_customize_staff_payslip_columns) > 0){
				$total_row =  $this->db->insert_batch(db_prefix().'hrp_customize_staff_payslip_columns', $hrp_customize_staff_payslip_columns);
				if($total_row > 0){
					$affected_rows++;
				}
			}
		}

		if ($affected_rows > 0) {
			return true;
		}
		return false;
	}


	/**
	 * delete hr payroll permission
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_hr_payroll_permission($id)
	{
		$str_permissions ='';
		foreach (list_hr_payroll_permisstion() as $per_key =>  $per_value) {
			if(new_strlen($str_permissions) > 0){
				$str_permissions .= ",'".$per_value."'";
			}else{
				$str_permissions .= "'".$per_value."'";
			}
		}

		$sql_where = " feature IN (".$str_permissions.") ";

		$this->db->where('staff_id', $id);
		$this->db->where($sql_where);
		$this->db->delete(db_prefix() . 'staff_permissions');

		if ($this->db->affected_rows() > 0) {
			return true;
		}

		return false;
	}


	/**
	 * setting get attendance data
	 * @return [type] 
	 */
	public function setting_get_attendance_type()
	{
	    $actual_type=[];
	    $paid_leave_type=[];
	    $unpaid_leave_type=[];

	    $attendance_types = hrp_attendance_type();
	    $actual_workday   = new_explode(',', get_hr_payroll_option('integration_actual_workday'));
	    $paid_leave       = new_explode(',', get_hr_payroll_option('integration_paid_leave'));
	    $unpaid_leave     = new_explode(',', get_hr_payroll_option('integration_unpaid_leave'));

	    foreach ($attendance_types as $key => $value) {
	    	//actual_workday
	        if(!in_array($key, $paid_leave) && !in_array($key, $unpaid_leave)){
	        	$actual_type[$key] = $value;
	        }

	        //paid_leave
	        if(!in_array($key, $actual_workday) && !in_array($key, $unpaid_leave)){
	        	$paid_leave_type[$key] = $value;
	        }

	        //unpaid_leave
	        if(!in_array($key, $actual_workday) && !in_array($key, $paid_leave)){
	        	$unpaid_leave_type[$key] = $value;
	        }

	    }

	    $results=[];
	    $results['actual_workday'] 	= $actual_type;
	    $results['paid_leave'] 		= $paid_leave_type;
	    $results['unpaid_leave'] 	= $unpaid_leave_type;

	    return $results;
	}


	/**
	 * get timesheet type for setting
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function get_timesheet_type_for_setting($data)
	{
		$actual_type ='';
	    $paid_leave_type ='';
	    $unpaid_leave_type ='';

	    $attendance_types = hrp_attendance_type();
	    $actual_workday   = isset($data['actual_workday']) ? $data['actual_workday'] : [];
	    $paid_leave       = isset($data['paid_leave']) ? $data['paid_leave'] : [];
	    $unpaid_leave     = isset($data['unpaid_leave']) ? $data['unpaid_leave'] : [];

	    foreach ($attendance_types as $key => $value) {
	    	//actual_workday
	        if(!in_array($key, $paid_leave) && !in_array($key, $unpaid_leave)){
				$select ='';
				if(in_array($key, $actual_workday)){
					$select = ' selected';
				}	        	
				$actual_type .= '<option value="' . $key . '" '.$select.'>' . $value . '</option>';
	        }

	        //paid_leave
	        if(!in_array($key, $actual_workday) && !in_array($key, $unpaid_leave)){
	        	$select ='';
				if(in_array($key, $paid_leave)){
					$select = ' selected';
				}	        	
				$paid_leave_type .= '<option value="' . $key . '" '.$select.'>' . $value . '</option>';
	        }

	        //unpaid_leave
	        if(!in_array($key, $actual_workday) && !in_array($key, $paid_leave)){
	        	$select ='';
				if(in_array($key, $unpaid_leave)){
					$select = ' selected';
				}	        	
				$unpaid_leave_type .= '<option value="' . $key . '" '.$select.'>' . $value . '</option>';
	        }

	    }

	    $results=[];
	    $results['actual_workday'] 	= $actual_type;
	    $results['paid_leave'] 		= $paid_leave_type;
	    $results['unpaid_leave'] 	= $unpaid_leave_type;
	    return $results;

	}

	/**
	 * hr records get earnings list
	 * @param  boolean $id 
	 * @return [type]
	 * get data: salary type, allowance type from HR records module when use feature "data integration" in settings menu.      
	 */
	public function hr_records_get_earnings_list($id = false){
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'hrp_earnings_list_hr_records')->row();
        }

        if ($id == false) {
        return $this->db->query('select * from '.db_prefix().'hrp_earnings_list_hr_records')->result_array();
        }

    }


    /**
     * hr records update earnings list
     * @param  [type] $data 
     * @return [type]       
     */
    public function earnings_list_synchronization($data)
	{
		$affectedRows = 0;
		$hr_payroll_alphabeticala = hr_payroll_alphabeticala();
		$array_salary_allowance=[];
		if(hr_payroll_get_status_modules('hr_profile') && get_hr_payroll_option('integrated_hrprofile') == 1){

			$this->load->model('hr_profile/hr_profile_model');
		//get salary type
			$salary_types = $this->hr_profile_model->get_salary_form();
		//get allowance type
			$allowance_types = $this->hr_profile_model->get_allowance_type();

			foreach ($salary_types as $key=>  $value) {
				$code = new_str_replace("-", "", strtoupper($value['form_name']));
				$code = new_str_replace(" ", "_", strtoupper($value['form_name']));

			    $array_salary_allowance['salary_'.$value['form_id']] = [
			    	'code' 			=> $code,
			    	'description' 	=> $value['form_name'],
			    	'short_name' 	=> $value['form_name'],
			    	'taxable' 		=> 0,
			    	'basis_type' 	=> 'monthly',
			    	'rel_type' 		=> 'salary',
			    	'rel_id' 		=> $value['form_id']
			    ];
			}

			foreach ($allowance_types as $key=>  $value) {
				$code = new_str_replace("-", "", strtoupper($value['type_name']));
				$code = new_str_replace(" ", "_", strtoupper($value['type_name']));
			    $array_salary_allowance['allowance_'.$value['type_id']] = [
			    	'code' 			=> $code,
			    	'description' 	=> $value['type_name'],
			    	'short_name' 	=> $value['type_name'],
			    	'taxable' 		=> 0,
			    	'basis_type' 	=> 'monthly',
			    	'rel_type' 		=> 'allowance',
			    	'rel_id' 		=> $value['type_id']
			    ];
			}
			

		}

		if (isset($data['earnings_list_hr_records_hs'])) {
			$earnings_list_hr_records_hs = $data['earnings_list_hr_records_hs'];
			unset($data['earnings_list_hr_records_hs']);
		}

		if(isset($earnings_list_hr_records_hs)){
			$incometax_rate_detail = json_decode($earnings_list_hr_records_hs);

			$es_detail = [];
			$row = [];
			$rq_val = [];
			$header = [];

			$header[] = 'code';
			$header[] = 'description';
			$header[] = 'short_name';
			$header[] = 'taxable';
			$header[] = 'basis_type';
			$header[] = 'id';
			$header[] = 'rel_type';
			$header[] = 'rel_id';

			foreach ($incometax_rate_detail as $key => $value) {
				//only get row "value" != 0
				if($value[0] != '' ){
					$es_detail[] = array_combine($header, $value);

				}
			}
		}

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];
		foreach ($es_detail as $key => $value) {
			if($value['id'] != ''){
				$row['delete'][] = $value['id'];

				if(isset($array_salary_allowance[$value['rel_type'].'_'.$value['rel_id']])){
					$value['description'] = $array_salary_allowance[$value['rel_type'].'_'.$value['rel_id']]['description'];
					$row['update'][] = $value;

					unset($array_salary_allowance[$value['rel_type'].'_'.$value['rel_id']]);

					if(isset($hr_payroll_alphabeticala[$value['code']])){
						unset($hr_payroll_alphabeticala[$value['code']]);
					}
				}
			}

		}
		foreach ($array_salary_allowance as $value) {
			$value['code'] = reset($hr_payroll_alphabeticala);
		    $row['insert'][] =  $value;

		    unset($hr_payroll_alphabeticala[$value['code']]);
		}


		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		$row['delete'] = implode(",",$row['delete']);
		$this->db->where('id NOT IN ('.$row['delete'] .') ');
		$this->db->delete(db_prefix().'hrp_earnings_list_hr_records');
		if($this->db->affected_rows() > 0){
			$affectedRows++;
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_earnings_list_hr_records', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_earnings_list_hr_records', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}

		return false;
	}


	/**
	 * get hrp employees header
	 * @param  [type] $rel_type 
	 * @return [type]           
	 */
	public function get_hrp_employees_header($rel_type)
	{
		$this->db->where('rel_type', $rel_type);
		$this->db->order_by('header_oder', 'asc');
		return $this->db->get(db_prefix() . 'hrp_employees_header')->result_array();
	}


	/**
	 * get hrp employees value
	 * @param  [type] $rel_type 
	 * @return [type]           
	 */
	public function get_hrp_employees_value($rel_type)
	{
		$this->db->where('rel_type', $rel_type);
		$this->db->order_by('header_id', 'asc');
		return $this->db->get(db_prefix() . 'hrp_employees_value')->result_array();
	}

	/**
	 * get employees data
	 * @return [type] 
	 */
	public function get_employees_data1()
	{
		              
		if(hr_payroll_get_status_modules('hr_profile') && get_hr_payroll_option('integrated_hrprofile') == 1){
			$rel_type = 'hr_records';
		}else{
			$rel_type = 'none';
		}
			$header_id_code=[];
			$header_code=[];
			$header_column=[];
			$header_value=[];
			$body_value=[];
			//get header
			$employees_header = $this->get_hrp_employees_header($rel_type);
			foreach ($employees_header as $value) {
			    array_push($header_column, [
			    	'data' => $value['header_code'],
			    	'type' => 'text'
			    ]);
			    $header_code[] = $value['header_code'];
			    $header_id_code[$value['header_code']] = $value['id'];

			    $header_value[]=_l($value['header_value']);
			}

			array_push($header_column, [
				'data' => 'staff_id',
				'type' => 'text'
			]);

			$header_code[] = 'staff_id';

			$header_value[]='staff_id';

			//get body
			$sql_query = "SELECT  staff_id, header_code, value, v.rel_type, v.header_id  FROM ".db_prefix()."hrp_employees_value as v
left join ".db_prefix()."hrp_employees_header as h on h.id = v.header_id
where v.rel_type = '".$rel_type."'
order by staff_id, header_oder
";
			$employees_value = $this->db->query($sql_query)->result_array();
			$body_temp=[];
			foreach ($employees_value as $key => $value) {
			    if($key+1 < count($employees_value)){
			    	if($value['staff_id'] != $employees_value[$key+1]['staff_id']){
			    		$body_temp[$value['header_code']] = $value['value'] ;
			    		$body_temp['staff_id'] = $value['staff_id'] ;
			    		array_push($body_value, $body_temp);

			    		$body_temp=[];
			    	}else{
			    		$body_temp[$value['header_code']] = $value['value'] ;
			    	}
			    }else{
			    	$body_temp[$value['header_code']] = $value['value'] ;
			    	$body_temp['staff_id'] = $value['staff_id'] ;
			    	array_push($body_value, $body_temp);
			    }
			}

			$employees_data=[];
			$employees_data['header_id_code'] 	= $header_id_code;
			$employees_data['header_column'] 	= $header_column;
			$employees_data['header_code'] 		= $header_code;
			$employees_data['header_value'] 	= $header_value;
			$employees_data['body_value'] 		= $body_value;

			return $employees_data;
		

	}


	/**
	 * get employees data
	 * @param  [type] $month    
	 * @param  [type] $rel_type 
	 * @return [type]           
	 */
	public function get_employees_data($month, $rel_type ='', $where='')
	{
		if($rel_type == ''){
			$rel_type = hrp_get_hr_profile_status();
		}

		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees = $this->db->get(db_prefix() . 'hrp_employees_value')->result_array();

		$employees_decode = $this->employees_data_json_encode_decode('decode', $employees, '');

		return $employees_decode;
	}


	/**
	 * employees data json encode decode
	 * @param  [type] $type   
	 * @param  [type] $data   
	 * @param  [type] $header 
	 * @return [type]         
	 */
	public function employees_data_json_encode_decode($type, $data, $header ='')
	{
		if($type == 'decode'){
			// json decode
			foreach ($data as $key => $value) {
			    $probationary_contracts = json_decode($value['probationary_contracts']);
			    $primary_contracts = json_decode($value['primary_contracts']);

			    unset($data[$key]['probationary_contracts']);
			    unset($data[$key]['primary_contracts']);

			    foreach ($probationary_contracts as $probationary_key => $probationary_value) {

			    	foreach (get_object_vars($probationary_value) as $column_key => $column_value) {
			    		$data[$key][$column_key] = $column_value;

			    		$data[$key]['contract_value'][$column_key] = $column_value;
			    	}
			    }

			    foreach ($primary_contracts as $primary_key => $primary_value) {
			        foreach (get_object_vars($primary_value) as $column_key => $column_value) {
			    		$data[$key][$column_key] = $column_value;

			    		$data[$key]['contract_value'][$column_key] = $column_value;

			    	}
			    }
			    
			}

		}else{
			// json encode
			$data_detail=[];
			$data_detail = $data;

			$data=[];
			$rel_type = hrp_get_hr_profile_status();


			foreach ($data_detail as $data_detail_value) {

				$temp = [];
				$probationary_contracts = [];
				$primary_contracts = [];

				foreach ($data_detail_value as $key => $value) {

					if($rel_type == 'hr_records'){
						//integration Hr records module
						if(preg_match('/^st1_/', $key) || preg_match('/^al1_/', $key)){
							array_push($probationary_contracts, [
								$key => $value
							]);
						}elseif(preg_match('/^st2_/', $key) || preg_match('/^al2_/', $key)){
							array_push($primary_contracts, [
								$key => $value
							]);
						}elseif($key != 'department_name' && $key != 'employee_name' && $key != 'employee_number'  ){
							if(($key == 'probationary_effective' || $key == 'probationary_expiration' || $key == 'primary_effective' || $key == 'primary_expiration')  ){
								if($value != ''){
									$temp[$key] = $value;
								}else{
									$temp[$key] = null;
								}
							}else{
								$temp[$key] = $value;
							}
						}
					}else{
						//none integration Hr records module
						//earning1_ (of propational contract)
						//earning2_ (of formal contract)
						if(preg_match('/^earning1_/', $key) ){
							array_push($probationary_contracts, [
								$key => $value
							]);
						}elseif(preg_match('/^earning2_/', $key) || preg_match('/^al2_/', $key)){
							array_push($primary_contracts, [
								$key => $value
							]);
						}elseif($key != 'department_name' && $key != 'employee_name' && $key != 'employee_number'){
							if(($key == 'probationary_effective' || $key == 'probationary_expiration' || $key == 'primary_effective' || $key == 'primary_expiration')  ){
								if($value != ''){
									$temp[$key] = $value;
								}else{
									$temp[$key] = null;
								}
							}else{
								$temp[$key] = $value;
							}
						}
					}

				}

				$temp['probationary_contracts'] = json_encode($probationary_contracts);
				$temp['primary_contracts'] = json_encode($primary_contracts);

				$data[] = $temp;
			}

		}
		return $data;
	}


	/**
	 * get format employees data
	 * @param  [type] $rel_type 
	 * @return [type]   
	 * Description: Each staff will have a maximun 2 Contract: probationary, formal
	 */
	public function get_format_employees_data($rel_type)
	{	
		$staff_information=[];
		$staff_information[] = 'id';
		$staff_information[] = 'staff_id';
		$staff_information[] = 'rel_type';
		$staff_information[] = 'employee_number';
		$staff_information[] = 'employee_name';
		$staff_information[] = 'job_title';
		$staff_information[] = 'department_name';
		$staff_information[] = 'income_tax_number';
		$staff_information[] = 'residential_address';
		$staff_information[] = 'income_rebate_code';
		$staff_information[] = 'income_tax_rate';
		$staff_information[] = 'bank_name';
		$staff_information[] = 'account_number';
		$staff_information[] = 'epf_no';
		$staff_information[] = 'social_security_no';

		//get column header name, column format
		$column_format=[];
		$staff_information_header=[];

		foreach ($staff_information as $value) {
			if($value == 'staff_id'){
				$staff_information_header[] = 'staff_id';
			}elseif($value == 'id'){
				$staff_information_header[] = 'id';
			}else{
				$staff_information_header[] = _l($value);
			}
		    array_push($column_format, [
		    	'data' => $value,
		    	'type' => 'text'
		    ]);
		}
		

		//get value for probationary contract, formal contract
		$prefix_probationary = HR_PAYROLL_PREFIX_PROBATIONARY;
		$prefix_formal = HR_PAYROLL_PREFIX_FORMAL;
		$array_earnings_list_probationary = [];
		$array_earnings_list_formal = [];

		$array_earnings_list_probationary_header = [];
		$array_earnings_list_formal_header = [];

	    if($rel_type == 'hr_records'){
	    	//get earning list from setting
			$hr_records_earnings_list = $this->hr_records_get_earnings_list();
			foreach ($hr_records_earnings_list as $key => $value) {
				$name ='';
				
				switch ($value['rel_type']) {
					case 'salary':
						$probationary_code = 'st1_'.$value['rel_id'];
						$formal_code = 'st2_'.$value['rel_id'];
						break;

					case 'allowance':
						$probationary_code = 'al1_'.$value['rel_id'];
						$formal_code  = 'al2_'.$value['rel_id'];
						break;
					
					default:
						# code...
						break;
				}

				if($value['short_name'] != ''){
					$name .= $value['short_name'];
				}elseif($value['description'] != ''){
					$name .= $value['description'];
				}elseif($value['code'] != ''){
					$name .= $value['code'];
				}elseif($value['id'] != ''){
					$name .= $value['id'];
				}

				$array_earnings_list_probationary[$probationary_code] = 0;
				$array_earnings_list_formal[$formal_code] = 0;

				$array_earnings_list_probationary_header[] = $name.$prefix_probationary;
				$array_earnings_list_formal_header[] = $name.$prefix_formal;

			}
	    }else{
	    	$earnings_list = $this->get_earnings_list();

			foreach ($earnings_list as $key => $value) {
				$name ='';

				$array_earnings_list_probationary['earning1_'.$value['id']] = 0;
				$array_earnings_list_formal['earning2_'.$value['id']] = 0;

				$array_earnings_list_probationary_header[] = $value['short_name'].$prefix_probationary;
				$array_earnings_list_formal_header[] = $value['short_name'].$prefix_formal;

			}
	    }

		//probationary_effective
		//probationary_expiration
		//primary_effective
		//primary_expiration
		$probationary_date=[];
		$primary_date=[];

		$probationary_key =[];
		$primary_key =[];

		$probationary_date[] = _l('probationary_effective');
		$probationary_date[] = _l('probationary_expiration');
		$primary_date[] = _l('primary_effective');
		$primary_date[] = _l('primary_expiration');

		$probationary_key[] = 'probationary_effective';
		$probationary_key[] = 'probationary_expiration';
		$primary_key[] = 'primary_effective';
		$primary_key[] = 'primary_expiration';
		
		

	    //get column format for probationary, formal
	    foreach ($array_earnings_list_probationary as $key => $value) {
	    	array_push($column_format, [
	    		'data' => $key,
	    		'type'=> 'numeric',
	    		'numericFormat'=> [
	    			'pattern' => '0,00',
	    		]
	    	]);
	    }

	    array_push($column_format, [
	    	'data' => 'probationary_effective',
	    	'type'=> 'date',
	    	'correctFormat'=> 'true',
	    	'dateFormat'=> 'YYYY-MM-DD'
	    ]);

	    array_push($column_format, [
	    	'data' => 'probationary_expiration',
	    	'type'=> 'date',
	    	'correctFormat'=> 'true',
	    	'dateFormat'=> 'YYYY-MM-DD'
	    ]);
	    

	    foreach ($array_earnings_list_formal as $key => $value) {
	    	array_push($column_format, [
	    		'data' => $key,
	    		'type'=> 'numeric',
	    		'numericFormat'=> [
	    			'pattern' => '0,00',
	    		]
	    	]);
	    }

	    array_push($column_format, [
	    	'data' => 'primary_effective',
	    	'type'=> 'date',
	    	'correctFormat'=> 'true',
	    	'dateFormat'=> 'YYYY-MM-DD',
	    ]);

	    array_push($column_format, [
	    	'data' => 'primary_expiration',
	    	'type'=> 'date',
	    	'correctFormat'=> 'true',
	    	'dateFormat'=> 'YYYY-MM-DD',
	    ]);
	    

	    $results=[];
	    $results['probationary']=$array_earnings_list_probationary;
	    $results['formal']		=$array_earnings_list_formal;
	    $results['staff_information']		= $staff_information;
	    $results['probationary_key']		= $probationary_key;
	    $results['primary_key']				= $primary_key;

	    $results['header']		= array_merge($staff_information_header, $array_earnings_list_probationary_header, $probationary_date, $array_earnings_list_formal_header, $primary_date);
	    $results['column_format']		= $column_format;
	    return $results;
	}


	/**
	 * FunctionNam
	 * @param [type]  $prefix_str          
	 * @param [type]  $number              
	 * @param integer $number_of_characters
	 */
	public function hrp_format_code($prefix_str, $number, $number_of_characters = 5)
	{
		$str_result = $prefix_str.str_pad($number,$number_of_characters,'0',STR_PAD_LEFT);
		return $str_result;
	}


	/**
	 * get list staff contract
	 * @return [type]
	 * get staff contract, detail by staff all, Each employee will take the last 2 contracts from the search month (if the employee has 2 contracts in 1 month) and status = valid  
	 */
	public function get_list_staff_contract($month)
	{
		$month = date("Y-m", strtotime($month));
		$sql_temp = "select * FROM ".db_prefix()."hr_staff_contract 
		left join ".db_prefix()."hr_staff_contract_detail on ".db_prefix()."hr_staff_contract_detail.staff_contract_id = ".db_prefix()."hr_staff_contract.id_contract
		where ".db_prefix()."hr_staff_contract.id_contract IN (
		SELECT id_contract FROM ".db_prefix()."hr_staff_contract
		where ".db_prefix()."hr_staff_contract.contract_status = 'valid' AND date_format(start_valid, '%Y-%m-%d') >= '".$month."'

		)";

		$sql = "SELECT *  FROM ".db_prefix()."hr_staff_contract as ct
		left join ".db_prefix()."hr_staff_contract_detail on ".db_prefix()."hr_staff_contract_detail.staff_contract_id = ct.id_contract
		where (
		select count(*) from ".db_prefix()."hr_staff_contract as f
		where f.staff = ct.staff and f.start_valid >= ct.start_valid
		) <= 2
		AND date_format(ct.start_valid, '%Y-%m') <= '".$month."' AND if(ct.end_valid is NULL, 1=1, date_format(ct.end_valid, '%Y-%m') >= '".$month."')
		AND ct.contract_status ='valid' 
		order by staff,start_valid  desc";

		$staff_contracts = $this->db->query($sql)->result_array();

		$contracts=[];

		$check_contract_detail=[];
		$contract_detail=[];
		foreach ($staff_contracts as $key => $value) {
			if(count($check_contract_detail) == 0){
				$check_contract_detail['id_contract']=$value['id_contract'];
				$check_contract_detail['staff_id']=$value['staff'];
			}

			$contract_detail[$value['rel_type']] = $value['rel_value'];
			$contract_detail['start_valid'] = $value['start_valid'];
			$contract_detail['end_valid'] = $value['end_valid'];

			if(count($staff_contracts) != $key+1){
				if($check_contract_detail['id_contract'] != $staff_contracts[$key+1]['id_contract'] || $check_contract_detail['staff_id'] != $staff_contracts[$key+1]['staff'] ){

					//formal
					if(!isset($contracts[$check_contract_detail['staff_id']]['formal'])){
						$contract_detail_temp=[];

						foreach ($contract_detail as $contract_detail_key => $contract_detail_value) {
							if($contract_detail_key == 'start_valid'){
							    $contract_detail_temp['primary_effective'] = $contract_detail_value;
							}elseif($contract_detail_key == 'end_valid'){
							    $contract_detail_temp['primary_expiration'] = $contract_detail_value;
							}else{
							    $contract_detail_key = new_str_replace('_', '2_', $contract_detail_key);
							    $contract_detail_temp[$contract_detail_key] = $contract_detail_value;
							}
						}

						$contract_detail_temp['hourly_or_month'] = $value['hourly_or_month'];
						$contracts[$check_contract_detail['staff_id']]['formal'] = $contract_detail_temp;

					}elseif(!isset($contracts[$check_contract_detail['staff_id']]['probationary'])){
					//probationary	
						$contract_detail_temp=[];
						foreach ($contract_detail as $contract_detail_key => $contract_detail_value) {
							if($contract_detail_key == 'start_valid'){
							    $contract_detail_temp['probationary_effective'] = $contract_detail_value;
							}elseif($contract_detail_key == 'end_valid'){
							    $contract_detail_temp['probationary_expiration'] = $contract_detail_value;
							}else{
							    $contract_detail_key = new_str_replace('_', '1_', $contract_detail_key);
							    $contract_detail_temp[$contract_detail_key] = $contract_detail_value;
							}
						}
						$contract_detail_temp['hourly_or_month'] = $value['hourly_or_month'];
						$contracts[$check_contract_detail['staff_id']]['probationary'] = $contract_detail_temp;
					}
					
					$contract_detail=[];
					$check_contract_detail=[];
				}
			}else{

				if(!isset($contracts[$check_contract_detail['staff_id']]['formal'])){
				// formal	
					$contract_detail_temp=[];
					foreach ($contract_detail as $contract_detail_key => $contract_detail_value) {
						if($contract_detail_key == 'start_valid'){
							    $contract_detail_temp['primary_effective'] = $contract_detail_value;
							}elseif($contract_detail_key == 'end_valid'){
							    $contract_detail_temp['primary_expiration'] = $contract_detail_value;
							}else{
								$contract_detail_key = new_str_replace('_', '2_', $contract_detail_key);
								$contract_detail_temp[$contract_detail_key] = $contract_detail_value;
							}
					}
					$contract_detail_temp['hourly_or_month'] = $value['hourly_or_month'];
					$contracts[$check_contract_detail['staff_id']]['formal'] = $contract_detail_temp;

				}elseif(!isset($contracts[$check_contract_detail['staff_id']]['probationary'])){
				// probationary	
					$contract_detail_temp=[];
					foreach ($contract_detail as $contract_detail_key => $contract_detail_value) {
						if($contract_detail_key == 'start_valid'){
							    $contract_detail_temp['probationary_effective'] = $contract_detail_value;
							}elseif($contract_detail_key == 'end_valid'){
							    $contract_detail_temp['probationary_expiration'] = $contract_detail_value;
							}else{
							$contract_detail_key = new_str_replace('_', '1_', $contract_detail_key);
							$contract_detail_temp[$contract_detail_key] = $contract_detail_value;
						}
					}
					$contract_detail_temp['hourly_or_month'] = $value['hourly_or_month'];
					$contracts[$check_contract_detail['staff_id']]['probationary'] = $contract_detail_temp;
				}

			}


		}

		return $contracts;
	}


/* Function to synchronize data from HR records module (information related to the salary of employees in the contract) */
/* add columns, delete columns when settings change,
  TH1: Synchronize for the first time when there is no data
  TH2: Synchronize after having data: maybe some master data is changed (add, delete, edit)
 */
	/**
	 * employees synchronization
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function employees_synchronization($data)
	{

		$affectedRows = 0;

		$rel_type = hrp_get_hr_profile_status();
		$format_employees_data = $this->get_format_employees_data($rel_type);
		$staff_information_key = $format_employees_data['staff_information'];
		$staff_probationary_key = array_keys($format_employees_data['probationary']);
		$probationary_key = $format_employees_data['probationary_key'];
		$primary_key = $format_employees_data['primary_key'];

		$staff_formal_key = array_keys($format_employees_data['formal']);

		$employees_month = date('Y-m-d',strtotime($data['employees_fill_month'].'-01'));
		$staff_contract = $this->get_list_staff_contract($employees_month);
		if (isset($data['hrp_employees_value'])) {
			$hrp_employees_value = $data['hrp_employees_value'];
			unset($data['hrp_employees_value']);
		}

		/*update save note*/
		if(isset($hrp_employees_value)){
			$hrp_employees_detail = json_decode($hrp_employees_value);

			$es_detail = [];
			$row = [];
			$header = array_merge($staff_information_key, $staff_probationary_key, $probationary_key, $staff_formal_key, $primary_key);

			foreach ($hrp_employees_detail as $key => $value) {				
				$temp = [];
				$probationary_contracts = [];
				$primary_contracts = [];
				$combine_data = [];
				$combine_data = array_combine($header, $value);

				//st1: is Salary type (of propational contract)
				//al1: is Allowance  type (of propational contract)
				//ts2: is Salary type (of formal contract)
				//al2: is Allowance  type (of formal contract)
				foreach ($combine_data as $combine_key => $combine_value) {
					if($rel_type == 'hr_records'){
						//integration Hr records module
						if(preg_match('/^st1_/', $combine_key) || preg_match('/^al1_/', $combine_key)){
							$combine_value = 0;
							//get value from staff contract if exist
							if(isset($staff_contract[$combine_data['staff_id']]['probationary'][$combine_key])){
								$combine_value = $staff_contract[$combine_data['staff_id']]['probationary'][$combine_key];
							}

							array_push($probationary_contracts, [
								$combine_key => $combine_value
							]);
						}elseif(preg_match('/^st2_/', $combine_key) || preg_match('/^al2_/', $combine_key)){
							//get value from staff contract if exist
							$combine_value = 0;
							
							if(isset($staff_contract[$combine_data['staff_id']]['formal'][$combine_key])){
								$combine_value = $staff_contract[$combine_data['staff_id']]['formal'][$combine_key];
							}

							array_push($primary_contracts, [
								$combine_key => $combine_value
							]);
						}elseif($combine_key == 'probationary_effective' ||$combine_key == 'probationary_expiration'){
							$combine_value = null;

							if(isset($staff_contract[$combine_data['staff_id']]['probationary'][$combine_key])){
								$combine_value = $staff_contract[$combine_data['staff_id']]['probationary'][$combine_key];
							}

							$temp[$combine_key] = $combine_value;

						}elseif($combine_key == 'primary_effective' ||$combine_key == 'primary_expiration' ){
							$combine_value = null;
							if(isset($staff_contract[$combine_data['staff_id']]['formal'][$combine_key])){
								$combine_value = $staff_contract[$combine_data['staff_id']]['formal'][$combine_key];
							}

							$temp[$combine_key] = $combine_value;

						}elseif($combine_key != 'department_name' && $combine_key != 'employee_name' && $combine_key != 'employee_number'  ){
							$temp[$combine_key] = $combine_value;
						}
					}
				}


				$temp['probationary_contracts'] = json_encode($probationary_contracts);
				$temp['primary_contracts'] = json_encode($primary_contracts);
				$temp['month'] = $employees_month;
				$es_detail[] = $temp;
			}
		}

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;

		foreach ($es_detail as $key => $value) {

			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}

		if($data['department_employees_filter'] == '' && $data['staff_employees_filter'] == '' && $data['role_employees_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .') and rel_type = "'.$rel_type.'" AND date_format(month,"%Y-%m-%d") = "'.$employees_month.'"');
			$this->db->delete(db_prefix().'hrp_employees_value');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_employees_value', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_employees_value', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;


	}



/*TO DO: XỬ LÝ CẬP NHẬT, cập nhật thông tin nhân viên sau khi người dùng thực hiện thay đổi trên handsome table, dùng cho 2 trường hợp: có tích hợp HR records module, không tích hợp Hr records module.  đang xử lý!!!*/
	/**
	 * 
	 * employees update
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function employees_update($data)
	{
		$affectedRows = 0;

		$rel_type = hrp_get_hr_profile_status();
		$format_employees_data = $this->get_format_employees_data($rel_type);
		$staff_information_key = $format_employees_data['staff_information'];
		$probationary_key = $format_employees_data['probationary_key'];
		$primary_key = $format_employees_data['primary_key'];

		$staff_probationary_key = array_keys($format_employees_data['probationary']);
		$staff_formal_key = array_keys($format_employees_data['formal']);

		$employees_month = date('Y-m-d',strtotime($data['employees_fill_month'].'-01'));

		if (isset($data['hrp_employees_value'])) {
			$hrp_employees_value = $data['hrp_employees_value'];
			unset($data['hrp_employees_value']);
		}

		/*update save note*/

		if(isset($hrp_employees_value)){
			$hrp_employees_detail = json_decode($hrp_employees_value);

			$es_detail = [];
			$row = [];
			$header = array_merge($staff_information_key, $staff_probationary_key, $probationary_key, $staff_formal_key, $primary_key);
			foreach ($hrp_employees_detail as $key => $value) {				
				$temp = [];
				$probationary_contracts = [];
				$primary_contracts = [];
				$combine_data = [];
				
				$combine_data = array_combine($header, $value);

				//st1: is Salary type (of propational contract)
				//al1: is Allowance  type (of propational contract)
				//ts2: is Salary type (of formal contract)
				//al2: is Allowance  type (of formal contract)
				
					
				foreach ($combine_data as $combine_key => $combine_value) {

					if($rel_type == 'hr_records'){
						//integration Hr records module
						if(preg_match('/^st1_/', $combine_key) || preg_match('/^al1_/', $combine_key)){
							array_push($probationary_contracts, [
								$combine_key => $combine_value
							]);
						}elseif(preg_match('/^st2_/', $combine_key) || preg_match('/^al2_/', $combine_key)){
							array_push($primary_contracts, [
								$combine_key => $combine_value
							]);
						}elseif($combine_key != 'department_name' && $combine_key != 'employee_name' && $combine_key != 'employee_number'  ){
							if(($combine_key == 'probationary_effective' || $combine_key == 'probationary_expiration' || $combine_key == 'primary_effective' || $combine_key == 'primary_expiration')  ){
								if($combine_value != ''){
									$temp[$combine_key] = $combine_value;
								}else{
									$temp[$combine_key] = null;
								}
							}else{
								$temp[$combine_key] = $combine_value;
							}
						}
					}else{
						//none integration Hr records module
						//earning1_ (of propational contract)
						//earning2_ (of formal contract)
						if(preg_match('/^earning1_/', $combine_key) ){
							array_push($probationary_contracts, [
								$combine_key => $combine_value
							]);
						}elseif(preg_match('/^earning2_/', $combine_key) || preg_match('/^al2_/', $combine_key)){
							array_push($primary_contracts, [
								$combine_key => $combine_value
							]);
						}elseif($combine_key != 'department_name' && $combine_key != 'employee_name' && $combine_key != 'employee_number'){
							if(($combine_key == 'probationary_effective' || $combine_key == 'probationary_expiration' || $combine_key == 'primary_effective' || $combine_key == 'primary_expiration')  ){
								if($combine_value != ''){
									$temp[$combine_key] = $combine_value;
								}else{
									$temp[$combine_key] = null;
								}
							}else{
								$temp[$combine_key] = $combine_value;
							}
						}
					}
					
				}

				$temp['probationary_contracts'] = json_encode($probationary_contracts);
				$temp['primary_contracts'] = json_encode($primary_contracts);
				$temp['month'] = $employees_month;

				$es_detail[] = $temp;
			}
		}

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;
		foreach ($es_detail as $key => $value) {

			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		if($data['department_employees_filter'] == '' && $data['staff_employees_filter'] == '' && $data['role_employees_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .') and rel_type = "'.$rel_type.'" AND date_format(month,"%Y-%m-%d") = "'.$employees_month.'"');
			$this->db->delete(db_prefix().'hrp_employees_value');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_employees_value', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_employees_value', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;

	}



	/**
	 * get hrp attendance
	 * @param  [type] $rel_type 
	 * @return [type]           
	 */
	public function get_hrp_attendance($month, $where='')
	{
		$rel_type = hrp_get_timesheets_status();

		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees_timesheets = $this->db->get(db_prefix() . 'hrp_employees_timesheets')->result_array();

		return $employees_timesheets;
	}


	/**
	 * get day in month
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function get_day_header_in_month($month, $rel_type='', $timesheet = true)
	{
		$_month = (int)date('m',strtotime($month));
		$_year = (int)date('Y',strtotime($month));

		$staff_key=[];
		$attendance_key=[];
		$days_key=[];
		$days_header=[];
		$days_header_name=[];
		$columns_type=[];
		$days_header_type=[];


		$staff_key[] = 'staff_id';
		$staff_key[] = 'id';
		$staff_key[] = 'rel_type';
		$staff_key[] = 'month';
		$staff_key[] = 'hr_code';
		$staff_key[] = 'staff_name';
		$staff_key[] = 'staff_departments';

		if($timesheet){			
			$attendance_key[] = 'actual_workday_probation';
			$attendance_key[] = 'actual_workday';
		}

		$attendance_key[] = 'paid_leave';
		$attendance_key[] = 'unpaid_leave';
		if($timesheet){
			$attendance_key[] = 'standard_workday';
		}

		$total_day_in_month = cal_days_in_month(CAL_GREGORIAN,$_month,$_year);
		for ($d = 1; $d <= $total_day_in_month; $d++) {
			$days_key[] = 'day_'.$d;

			$jd=cal_to_jd(CAL_GREGORIAN,$_month,$d,$_year);
            $day=jddayofweek($jd,0);
                switch($day){
                    case 0:
                    if($timesheet){
                    	$days_header['day_'.$d] = 0;
                    }else{			
                    	$days_header['day_'.$d] = '';
                    }
                       	$days_header_name[] = _l('sunday').' '. $d;
                        break;
                    case 1:
                    if($timesheet){
                    	$days_header['day_'.$d] = 0;
                    }else{			
                    	$days_header['day_'.$d] = '';
                    }
                       	$days_header_name[] = _l('monday').' '. $d;

                        break;
                    case 2:
                    if($timesheet){
                    	$days_header['day_'.$d] = 0;
                    }else{			
                    	$days_header['day_'.$d] = '';
                    }
                       	$days_header_name[] = _l('tuesday').' '. $d;

                        break;
                    case 3:
                    if($timesheet){
                    	$days_header['day_'.$d] = 0;
                    }else{			
                    	$days_header['day_'.$d] = '';
                    }
                       	$days_header_name[] = _l('wednesday').' '. $d;

                        break;
                    case 4:
                    if($timesheet){
                    	$days_header['day_'.$d] = 0;
                    }else{			
                    	$days_header['day_'.$d] = '';
                    }
                       	$days_header_name[] = _l('thursday').' '. $d;

                        break;
                    case 5:
                    if($timesheet){
                    	$days_header['day_'.$d] = 0;
                    }else{			
                    	$days_header['day_'.$d] = '';
                    }
                       	$days_header_name[] = _l('friday').' '. $d;

                        break;
                    case 6:
                    if($timesheet){
                    	$days_header['day_'.$d] = 0;
                    }else{			
                    	$days_header['day_'.$d] = '';
                    }
                       	$days_header_name[] = _l('saturday').' '. $d;
                        break;
                        
                }

                if($timesheet){			
                	array_push($days_header_type, [
                		'data' => 'day_'.$d,
                		'type'=> 'numeric',
                		'numericFormat'=> [
                			'pattern' => '0,00',
                		]
                	]);
                }else{
                	array_push($days_header_type, [
                		'data' => 'day_'.$d,
                		'type'=> 'text',
                	]);
                }
		}

		$headers=[];
		foreach ($staff_key as $value) {
			if($value == 'staff_id'){
				$headers[] = 'staff_id';
			}else{
				$headers[] = _l($value);
			}
		    
		    array_push($columns_type, [
		    	'data' => $value,
		    	'type' => 'text'
		    ]);
		}

		$headers = array_merge($headers, $days_header_name);
		$columns_type = array_merge($columns_type, array_values($days_header_type));

		foreach ($attendance_key as $value) {
		    $headers[] = _l($value);

		    array_push($columns_type, [
		    	'data' => $value,
		    	'type'=> 'numeric',
		    	'numericFormat'=> [
		    		'pattern' => '0,00',
		    	]
		    ]);
		}

		$results=[];
		$results['headers'] = $headers;
		$results['staff_key'] = $staff_key;
		$results['attendance_key'] = $attendance_key;
		$results['days_key'] = $days_key;
		$results['days_header'] = $days_header;
		$results['columns_type'] = $columns_type;

		return $results;
	}

	/**
	 * add update attendance
	 * @param [type] $data 
	 */
	public function add_update_attendance($data)
	{	

		$affectedRows = 0;
		$rel_type = hrp_get_timesheets_status();

		$attendance_month = date('Y-m-d',strtotime($data['attendance_fill_month'].'-01'));

		$days_header_in_month = $this->hr_payroll_model->get_day_header_in_month($attendance_month);
		$header_key = array_merge($days_header_in_month['staff_key'], $days_header_in_month['days_key'], $days_header_in_month['attendance_key']);
		
		if (isset($data['hrp_attendance_value'])) {
			$hrp_attendance_value = $data['hrp_attendance_value'];
			unset($data['hrp_attendance_value']);
		}

		/*update save note*/

		if(isset($hrp_attendance_value)){
			$hrp_attendance_detail = json_decode($hrp_attendance_value);

			$es_detail = [];
			$row = [];

			foreach ($hrp_attendance_detail as $key => $value) {				
					$es_detail[] = array_combine($header_key, $value);
			}
		}

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;
		foreach ($es_detail as $key => $value) {
			if(isset($value['staff_departments'])){
				unset($value['staff_departments']);
			}
			if(isset($value['hr_code'])){
				unset($value['hr_code']);
			}
			if(isset($value['staff_name'])){
				unset($value['staff_name']);
			}
			
			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}

		if($data['department_attendance_filter'] == '' && $data['staff_attendance_filter'] == '' && $data['role_attendance_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .') and rel_type = "'.$rel_type.'" AND date_format(month,"%Y-%m-%d") = "'.$attendance_month.'"');
			$this->db->delete(db_prefix().'hrp_employees_timesheets');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_employees_timesheets', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}

		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_employees_timesheets', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}

		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;

	}


	/**
	 * synchronization attendance
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function synchronization_attendance($data)
	{
		$affectedRows = 0;
		$rel_type = hrp_get_timesheets_status();

		$attendance_month = date('Y-m-d',strtotime($data['attendance_fill_month'].'-01'));
		$timesheets_data = $this->hrp_get_timesheets_data($attendance_month, $rel_type);

		//get day header in month
		$days_header_in_month = $this->get_day_header_in_month($attendance_month, $rel_type);
		$header_key = array_merge($days_header_in_month['staff_key'], $days_header_in_month['days_key'], $days_header_in_month['attendance_key']);
		
		if (isset($data['hrp_attendance_value'])) {
			$hrp_attendance_value = $data['hrp_attendance_value'];
			unset($data['hrp_attendance_value']);
		}

		/*update save note*/

		if(isset($hrp_attendance_value)){
			$hrp_attendance_detail = json_decode($hrp_attendance_value);

			$es_detail = [];
			$row = [];
			foreach ($hrp_attendance_detail as $key => $value) {

				$attendance_temp = [];
			
				$combine_temp = array_combine($header_key, $value);
				$combine_temp = array_merge($combine_temp, $days_header_in_month['days_header']);

				if(isset($timesheets_data['staff_timesheets'][$combine_temp['staff_id']])){
					$combine_temp = array_merge($combine_temp, $timesheets_data['staff_timesheets'][$combine_temp['staff_id']]);
				}

				if(isset($timesheets_data['staff_timesheet_details'][$combine_temp['staff_id']])){
					$combine_temp = array_merge($combine_temp, $timesheets_data['staff_timesheet_details'][$combine_temp['staff_id']]);
				}

				$es_detail[] = $combine_temp;
				
			}
		}


		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;

		foreach ($es_detail as $key => $value) {
			if(isset($value['hr_code'])){
				unset($value['hr_code']);
			}
			if(isset($value['staff_name'])){
				unset($value['staff_name']);
			}
			if(isset($value['staff_departments'])){
				unset($value['staff_departments']);
			}
			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}

		if($data['department_attendance_filter'] == '' && $data['staff_attendance_filter'] == '' && $data['role_attendance_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .') and rel_type = "'.$rel_type.'" AND date_format(month,"%Y-%m-%d") = "'.$attendance_month.'"');
			$this->db->delete(db_prefix().'hrp_employees_timesheets');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_employees_timesheets', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_employees_timesheets', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		//add or update data to staff_timesheet_leave_data
		$staff_timesheet_leave_data = $timesheets_data['staff_timesheet_leave_data'];
		$insert_batch_employees_timesheet = [];
		$update_batch_employees_timesheet = [];
		$str_select_day = '*, ';
		$this->db->select($str_select_day);
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$attendance_month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees_timesheet_leaves = $this->db->get(db_prefix() . 'hrp_employees_timeshee_leaves')->result_array();
		if(count($employees_timesheet_leaves) > 0){

			foreach ($employees_timesheet_leaves as $key => $value) {
				if(isset($staff_timesheet_leave_data[$value['staff_id']])){
					$insert_batch_employees_timesheet[$key]['id'] = $value['id'] ;
					$insert_batch_employees_timesheet[$key] = array_merge($insert_batch_employees_timesheet[$key], $staff_timesheet_leave_data[$value['staff_id']]);
				}
			}
			if(count($insert_batch_employees_timesheet) > 0){
				$this->db->update_batch(db_prefix().'hrp_employees_timeshee_leaves', $insert_batch_employees_timesheet, 'id');
			}
		}else{
			foreach ($staff_timesheet_leave_data as $key => $batch_employees_timesheet) {
				$staff_timesheet_leave_data[$key]['staff_id'] = $key;
				$staff_timesheet_leave_data[$key]['month'] = $attendance_month;
				$staff_timesheet_leave_data[$key]['rel_type'] = $rel_type;
			}

			if(count($staff_timesheet_leave_data) > 0){
				$this->db->insert_batch(db_prefix().'hrp_employees_timeshee_leaves', $staff_timesheet_leave_data);
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;
	}


	/**
	 * hrp get timesheets data
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function hrp_get_timesheets_data($month, $rel_type)
	{
		$timesheet_get_shifts = $this->timesheet_get_shifts($month);
		$get_employees_data = $this->get_employees_data($month);
		$employees_data = [];
		foreach ($get_employees_data as $employee_key => $employee_value) {
		    $employees_data[$employee_value['staff_id']] = $employee_value;
		}

		$y_month = date('Y-m',strtotime($month));
		//get option for timesheets type
		$actual_workday   = new_explode(',', get_hr_payroll_option('integration_actual_workday'));
		$paid_leave       = new_explode(',', get_hr_payroll_option('integration_paid_leave'));
		$unpaid_leave     = new_explode(',', get_hr_payroll_option('integration_unpaid_leave'));

		$date_to_column_name = date_to_column_name();

	    //need close attendance before synchronization
		$sql_where_1 = "SELECT staff_id, type, sum(value) as total_time FROM ".db_prefix()."timesheets_timesheet
		where date_format(date_work,'%Y-%m') = '".$y_month."'
		group by staff_id, type
		;";

		$sql_where = "SELECT *, date_work as date_work_before, date_format(date_work, '%d') as date_work FROM ".db_prefix()."timesheets_timesheet
		where date_format(date_work,'%Y-%m') = '".$y_month."'
		;";
		

		$staff_timesheet_leave_data=[];
		$staff_timesheets=[];
		$staff_timesheet_details=[];
		$timesheets = $this->db->query($sql_where)->result_array();
		foreach ($timesheets as $timesheet) {

			$timesheet_rel_type ='';

			if(in_array($timesheet['type'], $actual_workday)){

				if(isset($employees_data[$timesheet['staff_id']]) && !is_null($employees_data[$timesheet['staff_id']]['probationary_expiration'])){

					//check timesheet in formal contract or probationary contract.
					$payslip_month = date("m", strtotime($month));
					$probationary_expiration_month = date("m", strtotime($employees_data[$timesheet['staff_id']]['probationary_expiration']));

					//if probationary_expiration month == payslip month
					if($payslip_month == $probationary_expiration_month ){

						//if probationary_expiration day <= timesheet day
						if(  strtotime($timesheet['date_work_before']) <= strtotime($employees_data[$timesheet['staff_id']]['probationary_expiration'])){
							$timesheet_rel_type .= 'actual_workday_probation';
						}else{
						//if probationary_expiration day > timesheet day
							$timesheet_rel_type .= 'actual_workday';
						}
					}else{
							$timesheet_rel_type .= 'actual_workday';
					}

				}else{
					$timesheet_rel_type .= 'actual_workday';
				}

			}elseif(in_array($timesheet['type'], $paid_leave)){
				$timesheet_rel_type .= 'paid_leave';
				$column_name = $date_to_column_name[$timesheet['date_work']];

				if(isset($staff_timesheet_leave_data[$timesheet['staff_id']][$column_name])){
					$temp = $staff_timesheet_leave_data[$timesheet['staff_id']][$column_name];
					$staff_timesheet_leave_data[$timesheet['staff_id']][$column_name] = $temp.';PL:'.(float)$timesheet['value'];
				}else{
					$staff_timesheet_leave_data[$timesheet['staff_id']][$column_name] =  'PL:'.(float)$timesheet['value'];
				}

				if(isset($staff_timesheet_leave_data[$timesheet['staff_id']]['paid_leave'])){
					$staff_timesheet_leave_data[$timesheet['staff_id']]['paid_leave'] += (float)$timesheet['value'];
				}else{
					$staff_timesheet_leave_data[$timesheet['staff_id']]['paid_leave'] = (float)$timesheet['value'];
				}

			}elseif(in_array($timesheet['type'], $unpaid_leave)){
				$timesheet_rel_type .= 'unpaid_leave';
				$column_name = $date_to_column_name[$timesheet['date_work']];

				if(isset($staff_timesheet_leave_data[$timesheet['staff_id']][$column_name])){
					$temp = $staff_timesheet_leave_data[$timesheet['staff_id']][$column_name];
					$staff_timesheet_leave_data[$timesheet['staff_id']][$column_name] = $temp.';UPL:'.(float)$timesheet['value'];

				}else{
					$staff_timesheet_leave_data[$timesheet['staff_id']][$column_name] =  'UPL:'.(float)$timesheet['value'];
				}

				if(isset($staff_timesheet_leave_data[$timesheet['staff_id']]['unpaid_leave'])){
					$staff_timesheet_leave_data[$timesheet['staff_id']]['unpaid_leave'] += (float)$timesheet['value'];
				}else{
					$staff_timesheet_leave_data[$timesheet['staff_id']]['unpaid_leave'] = (float)$timesheet['value'];
				}
			}

			if($timesheet_rel_type != ''){

				if(isset($staff_timesheets[$timesheet['staff_id']])){
					$staff_timesheets[$timesheet['staff_id']][$timesheet_rel_type] += (float)$timesheet['value'];
				}else{
					$staff_timesheets[$timesheet['staff_id']] =  [
						'staff_id' 			=> $timesheet['staff_id'],
						'month' 			=> $month,
						'actual_workday' 	=> 0,
						'actual_workday_probation' 	=> 0,
						'paid_leave' 		=> 0,
						'unpaid_leave' 		=> 0,
						'rel_type' 			=> $rel_type,
						'standard_workday' 			=> isset($timesheet_get_shifts[$timesheet['staff_id']]) ? $timesheet_get_shifts[$timesheet['staff_id']] : 0,

					];

					$staff_timesheets[$timesheet['staff_id']][$timesheet_rel_type] += (float)$timesheet['value'];
				}
			}

			if($timesheet_rel_type == 'actual_workday'){
				$column_name = $date_to_column_name[$timesheet['date_work']];

				if(isset($staff_timesheet_details[$timesheet['staff_id']][$column_name])){
					$staff_timesheet_details[$timesheet['staff_id']][$column_name] += (float)$timesheet['value'];
				}else{
					$staff_timesheet_details[$timesheet['staff_id']][$column_name] =  (float)$timesheet['value'];

				}
			}

		}
		
		$results = [];
		$results['staff_timesheets'] = $staff_timesheets;
		$results['staff_timesheet_details'] = $staff_timesheet_details;
		$results['staff_timesheet_leave_data'] = $staff_timesheet_leave_data;

		return $results;

	}


	/**
	 * timesheet get shifts
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function timesheet_get_shifts($month)
	{
		// payslip_template_get_staffid
	
		$month_format = date("Y-m", strtotime($month));
		$this->db->where(' date_format(from_date, "%Y-%m") <= "'.$month_format.'" AND date_format(to_date, "%Y-%m") >= "'.$month_format.'"');
		$work_shifts = $this->db->get(db_prefix() . 'work_shift')->result_array();

		$staff_shift = [];
		foreach ($work_shifts as $value) {
			if($value['type_shiftwork'] == 'by_absolute_time'){

				$sql_query = "SELECT ws.id, ws.department, ws.position, ws.staff, ws.from_date, ws.to_date, wsd.work_shift_id, wsd.shift_id, st.id as st_id, wsd.staff_id, wsd.date, IFNULL(time_end_work, 0) - IFNULL(time_start_work, 0) - ( IFNULL(end_lunch_break_time, 0) - IFNULL(start_lunch_break_time, 0)) as shifts_time 
				FROM ".db_prefix()."work_shift as ws
				left join ".db_prefix()."work_shift_detail as wsd on ws.id = wsd.work_shift_id
				left join ".db_prefix()."shift_type as st on wsd.shift_id = st.id
				where ws.id = ".$value['id']."
				";

				$shift_details = $this->db->query($sql_query)->result_array();


				if($value['staff'] != 0 || $value['staff'] != ''){
				//staff != 0 OR != '' : assign shifts directly to employees
					foreach ($shift_details as $shift_detail) {
						// if assigned 1 shift twice in the same day, only 1 time
						if(!isset($staff_shift[$shift_detail['staff_id'].'_'.$shift_detail['date'].'_'.$shift_detail['shift_id']])){
							$staff_shift[$shift_detail['staff_id'].'_'.$shift_detail['date'].'_'.$shift_detail['shift_id']] = $shift_detail['shifts_time'];
						}
					}

				}else{
				//staff == 0 OR == '' : assign shifts directly to department or role
					$arr_staff_ids = $this->payslip_template_get_staffid($value['department'], $value['position'], '');

					if($arr_staff_ids != false){
						$arr_staff_ids = new_explode(',', $arr_staff_ids );
						foreach ($arr_staff_ids as $staff_id) {
							foreach ($shift_details as $shift_detail) {
							// if assigned 1 shift twice in the same day, only 1 time
								if(!isset($staff_shift[$staff_id.'_'.$shift_detail['date'].'_'.$shift_detail['shift_id']])){

									$staff_shift[$staff_id.'_'.$shift_detail['date'].'_'.$shift_detail['shift_id']] = $shift_detail['shifts_time'];
								}
							}

						}
					}


				}


			}elseif($value['type_shiftwork'] == 'repeat_periodically'){

				$sql_query = "SELECT ws.id, ws.department, ws.position, ws.staff, ws.from_date, ws.to_date, wsd.work_shift_id, wsd.shift_id, st.id as st_id, wsd.staff_id, wsd.number, IFNULL(time_end_work, 0) - IFNULL(time_start_work, 0) - ( IFNULL(end_lunch_break_time, 0) - IFNULL(start_lunch_break_time, 0)) as shifts_time 
				FROM ".db_prefix()."work_shift as ws
				left join ".db_prefix()."work_shift_detail_number_day as wsd on ws.id = wsd.work_shift_id
				left join ".db_prefix()."shift_type as st on wsd.shift_id = st.id
				where ws.id = ".$value['id']."
				";
				$shift_details = $this->db->query($sql_query)->result_array();

				$shift_details_value=[];
				foreach ($shift_details as $shift_detail) {
					$shift_details_value[$shift_detail['number']] = ['work_shift_id' => $shift_detail['work_shift_id'], 'shifts_time' => $shift_detail['shifts_time']];
				}

				//TO DO
				$shift_detail_from_month = date("m", strtotime($value['from_date']));
				$shift_detail_to_month = date("m", strtotime($value['to_date']));
				$attendance_month_format = date("m", strtotime($month_format));

				if((float)$attendance_month_format == (float)$shift_detail_to_month && (float)$attendance_month_format == (float)$shift_detail_from_month ){
					$from_day = date_format(date_create($value['from_date']),"j");
					$to_day = date_format(date_create($value['to_date']),"j");
				}elseif((float)$attendance_month_format == (float)$shift_detail_from_month ){
					
					$from_day = date_format(date_create($value['from_date']),"j");
					$to_day = cal_days_in_month(CAL_GREGORIAN,date("m", strtotime($month_format)),date("Y", strtotime($month_format)));

				}elseif((float)$attendance_month_format == (float)$shift_detail_to_month){

					$from_day = 1;
					$to_day = date_format(date_create($value['to_date']),"j");

				}else{
					$from_day = 1;
					$to_day = cal_days_in_month(CAL_GREGORIAN,date("m", strtotime($month_format)),date("Y", strtotime($month_format)));
				}


				if($value['staff'] != 0 || $value['staff'] != ''){
					//staff != 0 OR != '' : assign shifts directly to employees
					foreach ($shift_details as $shift_detail) {

						for ($day = $from_day; $day <= $to_day; $day++) { 
							if(new_strlen($day) == 1){
								$day = '0'.$day;
							}

							$shifts_date = date('Y-m-d', strtotime($month_format.'-'.$day));
							$shifts_number = date('N', strtotime($month_format.'-'.$day));

							if(date('N', strtotime($month_format.'-'.$day)) == $shift_detail['number']){
								if(!isset($staff_shift[$shift_detail['staff_id'].'_'.$shifts_date.'_'.$shift_detail['number']])){

									$staff_shift[$shift_detail['staff_id'].'_'.$shifts_date.'_'.$shift_detail['number']] = $shift_detail['shifts_time'];
								}
							}
						}
					}
					
					
				}else{
					//staff == 0 OR == '' : assign shifts directly to department or role
					$arr_staff_ids = $this->payslip_template_get_staffid($value['department'], $value['position'], '');

					if($arr_staff_ids != false){
						$arr_staff_ids = new_explode(',', $arr_staff_ids );
						foreach ($arr_staff_ids as $staff_id) {

							for ($day = $from_day; $day <= $to_day; $day++) { 

								if(new_strlen($day) == 1){
									$day = '0'.$day;
								}

								$shifts_date = date('Y-m-d', strtotime($month_format.'-'.$day));
								$shifts_number = date('N', strtotime($month_format.'-'.$day));
								if(isset($shift_details_value[$shifts_number])){
									$work_shift_id = $shift_details_value[$shifts_number]['work_shift_id']; 
									$shifts_time = $shift_details_value[$shifts_number]['shifts_time']; 

									if(!isset($staff_shift[$staff_id.'_'.$shifts_date.'_'.$work_shift_id])){
										$staff_shift[$staff_id.'_'.$shifts_date.'_'.$work_shift_id] = $shifts_time;

									}
								}

							}
							
						}
					}



				}



			}

		}

		$shift_by_staff=[];
		foreach ($staff_shift as $key => $staff_shift) {

			$staff_id = new_explode('_', $key)[0];

			if(isset($shift_by_staff[$staff_id])){
				$shift_by_staff[$staff_id] += (float)$staff_shift;
			}else{
				$shift_by_staff[$staff_id] = (float)$staff_shift;
			}

		}

		return $shift_by_staff;

	}


	/**
	 * import attendance data
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function import_attendance_data($es_detail)
	{
		$affectedRows=0;
		
		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;

		foreach ($es_detail as $key => $value) {
			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_employees_timesheets', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_employees_timesheets', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;
	}

	/**
	 * attendance calculation
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function attendance_calculation($data)
	{
		$month = date('Y-m-d',strtotime($data['month'].'-01'));

		//get employee data for caculation attendance
		$employees = $this->get_employees_data($month);
		$employees_data = [];
		foreach ($employees as $employee_key => $employee_value) {
			$employees_data[$employee_value['staff_id']] = $employee_value;
		}

		$rel_type = hrp_get_timesheets_status();
		$date_to_column_name = date_to_column_name();

		$str_select_day = '*, ';
		$str_select_day .= '('.implode("+", $date_to_column_name).') as actual_workday_temp';
		
		$this->db->select($str_select_day);
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees_timesheets = $this->db->get(db_prefix() . 'hrp_employees_timesheets')->result_array();

		foreach ($employees_timesheets as $em_key => $timesheet) {

			$employees_timesheets[$em_key]['actual_workday'] = 0;
			$employees_timesheets[$em_key]['actual_workday_probation'] = 0;

			if(isset($employees_data[$timesheet['staff_id']])){

					//check timesheet in formal contract or probationary contract.
				$payslip_month = date("m", strtotime($month));
				$probationary_expiration_month = date("m", strtotime($employees_data[$timesheet['staff_id']]['probationary_expiration'] ?? ''));
				$probationary_expiration_day = date("d", strtotime($employees_data[$timesheet['staff_id']]['probationary_expiration'] ?? ''));

					//if probationary_expiration month == payslip month
				foreach ($timesheet as $timesheet_key => $timesheet_value) {
					if((float)$payslip_month == (float)$probationary_expiration_month ){

						if(preg_match('/^day_/', $timesheet_key)){

							$day = new_str_replace('day_', '', $timesheet_key);
								//if probationary_expiration day <= timesheet day
							if( (float)$day <= (float)$probationary_expiration_day){
					
								$employees_timesheets[$em_key]['actual_workday_probation'] += $timesheet_value;
							}else{
								//if probationary_expiration day > timesheet day
								$employees_timesheets[$em_key]['actual_workday'] += $timesheet_value;
							}

						}

					}else{
						if(preg_match('/^day_/', $timesheet_key)){
							$employees_timesheets[$em_key]['actual_workday'] += $timesheet_value;
						}
					}
				}

			}else{
				$employees_timesheets[$em_key]['actual_workday'] = $timesheet['actual_workday_temp'];
			}

			unset($employees_timesheets[$em_key]['actual_workday_temp']);
		}

			if(count($employees_timesheets) > 0){
				$this->db->update_batch(db_prefix().'hrp_employees_timesheets', $employees_timesheets, 'id');
			}
		return true;
	}


	/**
	 * import employees data
	 * @param  [type] $es_detail 
	 * @return [type]            
	 */
	public function import_employees_data($es_detail)
	{
		$es_detail = $this->employees_data_json_encode_decode('json_encode', $es_detail);
		$affectedRows=0;
		
		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;
		foreach ($es_detail as $key => $value) {

			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(count($row['insert']) != 0){
			$affected_rows =  $this->db->insert_batch(db_prefix().'hrp_employees_value', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_employees_value', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		
		if ($affectedRows > 0) {
			return true;
		}
		return false;
	}


	/**
	 * get format deduction data
	 * @return [type] 
	 */
	public function get_format_deduction_data()
	{	
		$staff_information=[];
		$staff_information[] = 'id';
		$staff_information[] = 'staff_id';
		$staff_information[] = 'month';
		$staff_information[] = 'employee_number';
		$staff_information[] = 'employee_name';
		$staff_information[] = 'department_name';


		//get column header name, column format
		$column_format=[];
		$staff_information_header=[];

		foreach ($staff_information as $value) {
			if($value == 'staff_id'){
				$staff_information_header[] = 'staff_id';
			}elseif($value == 'id'){
				$staff_information_header[] = 'id';
			}else{
				$staff_information_header[] = _l($value);
			}
			array_push($column_format, [
				'data' => $value,
				'type' => 'text'
			]);
		}
		

		//get value for deduction
		$array_deduction = [];
		$array_deduction_header = [];

	    	//get salary deductions list from setting
		$salary_deductions = $this->get_salary_deductions_list();

		foreach ($salary_deductions as $key => $value) {
			$name ='';

			if($value['description'] != ''){
				$name .= $value['description'];
			}elseif($value['code'] != ''){
				$name .= $value['code'];
			}elseif($value['id'] != ''){
				$name .= $value['id'];
			}

			$array_deduction['deduction_'.$value['id']] = $value['rate'];

			if($value['basis'] == 'gross' || $value['basis'] == 'fixed_amount' ){
				$array_deduction_header[] = $name.' ('.$value['basis'].')';
			}else{
				$array_deduction_header[] = $name;
			}

			array_push($column_format, [
				'data' => 'deduction_'.$value['id'],
				'type'=> 'numeric',
				'numericFormat'=> [
					'pattern' => '0,00',
				]
			]);

		}


		$results=[];
		$results['staff_information']		= $staff_information;
		$results['array_deduction']			= $array_deduction;
		$results['staff_information_header']			= $staff_information_header;
		$results['array_deduction_header']			= $array_deduction_header;

		$results['column_format']		= $column_format;
		$results['header']		= array_merge($staff_information_header, $array_deduction_header);

		return $results;
	}


	/**
	 * get deductions data
	 * @param  [type] $month    
	 * @param  [type] $rel_type 
	 * @return [type]           
	 */
	public function get_deductions_data($month, $where ='')
	{	
		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$deductions = $this->db->get(db_prefix() . 'hrp_salary_deductions')->result_array();

		$deductions_decode = $this->deductions_data_json_encode_decode('decode', $deductions, '');
		return $deductions_decode;
	}


	/**
	 * deductions data json encode decode
	 * @param  [type] $type   
	 * @param  [type] $data   
	 * @param  string $header 
	 * @return [type]         
	 */
	public function deductions_data_json_encode_decode($type, $data, $header ='')
	{
		if($type == 'decode'){
			// json decode
			foreach ($data as $key => $value) {
				$deduction_list = json_decode($value['deduction_list']);

				unset($data[$key]['deduction_list']);

				foreach ($deduction_list as $deduction_key => $deduction_value) {

					foreach (get_object_vars($deduction_value) as $column_key => $column_value) {
						$data[$key][$column_key] = $column_value;

						$data[$key]['deduction_value'][$column_key] = $column_value;
					}
				}

			}

		}else{
			// json encode
			$data_detail=[];
			$data_detail = $data;

			$data=[];

			foreach ($data_detail as $data_detail_value) {

				$temp = [];
				$deduction_list = [];

				foreach ($data_detail_value as $key => $value) {

					//integration Hr records module
					if(preg_match('/^deduction_/', $key) ){
						array_push($deduction_list, [
							$key => $value
						]);
					}elseif($key != 'department_name' && $key != 'employee_name' && $key != 'employee_number'  ){
						$temp[$key] = $value;
					}
					

				}

				$temp['deduction_list'] = json_encode($deduction_list);
				$data[] = $temp;
			}

		}
		return $data;
	}


	/**
	 * deductions update
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function deductions_update($data)
	{
		$affectedRows = 0;
		
		$format_deductions_data = $this->get_format_deduction_data();
		$staff_information_key = $format_deductions_data['staff_information'];

		$array_deduction_key = array_keys($format_deductions_data['array_deduction']);

		$deductions_month = date('Y-m-d',strtotime($data['deductions_fill_month'].'-01'));

		if (isset($data['hrp_deductions_value'])) {
			$hrp_deductions_value = $data['hrp_deductions_value'];
			unset($data['hrp_deductions_value']);
		}

		/*update save note*/

		if(isset($hrp_deductions_value)){
			$hrp_deductions_detail = json_decode($hrp_deductions_value);

			$es_detail = [];
			$row = [];
			$header = array_merge($staff_information_key, $array_deduction_key);			
			foreach ($hrp_deductions_detail as $key => $value) {
				
				$temp = [];
				$probationary_contracts = [];
				$primary_contracts = [];
				$combine_data = [];
				
				$combine_data = array_combine($header, $value);

				$es_detail[] = $combine_data;
			}
		}

		$es_detail = $this->deductions_data_json_encode_decode('encode', $es_detail);

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;
		foreach ($es_detail as $key => $value) {

			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		if($data['department_deductions_filter'] == '' && $data['staff_deductions_filter'] == '' && $data['role_deductions_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .')  AND date_format(month,"%Y-%m-%d") = "'.$deductions_month.'"');
			$this->db->delete(db_prefix().'hrp_salary_deductions');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_salary_deductions', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_salary_deductions', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;

	}


	/**
	 * get format commission data
	 * @return [type]
	 */
	public function get_format_commission_data()
	{	
		$staff_information=[];
		$staff_information[] = 'id';
		$staff_information[] = 'staff_id';
		$staff_information[] = 'rel_type';
		$staff_information[] = 'month';
		$staff_information[] = 'employee_number';
		$staff_information[] = 'employee_name';
		$staff_information[] = 'department_name';
		$staff_information[] = 'commission_amount';


		//get column header name, column format
		$column_format=[];
		$staff_information_header=[];

		foreach ($staff_information as $value) {
			if($value == 'staff_id'){
				$staff_information_header[] = 'staff_id';
			}elseif($value == 'id'){
				$staff_information_header[] = 'id';
			}else{
				$staff_information_header[] = _l($value);
			}

			if($value == 'commission_amount'){
				array_push($column_format, [
					'data' => 'commission_amount',
					'type'=> 'numeric',
					'numericFormat'=> [
						'pattern' => '0,00',
					]
				]);
			}else{
				array_push($column_format, [
					'data' => $value,
					'type' => 'text'
				]);
			}
		}
		

		//get value for commission

		$results=[];
		$results['staff_information']		= $staff_information;
		$results['staff_information_header']			= $staff_information_header;

		$results['column_format']		= $column_format;
		$results['headers']		= $staff_information_header;

		return $results;
	}


	/**
	 * get commissions data
	 * @param  [type]
	 * @return [type]
	 */
	public function get_commissions_data($month, $where ='')
	{
		$rel_type = hrp_get_commission_status();

		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$commissions = $this->db->get(db_prefix() . 'hrp_commissions')->result_array();

		return $commissions;
	}


	/**
	 * commissions update
	 * @param  [type]
	 * @return [type]
	 */
	public function commissions_update($data)
	{
		$affectedRows = 0;
		$rel_type = hrp_get_commission_status();

		$format_commissions_data = $this->get_format_commission_data();
		$staff_information_key = $format_commissions_data['staff_information'];

		$commissions_month = date('Y-m-d',strtotime($data['commissions_fill_month'].'-01'));

		if (isset($data['hrp_commissions_value'])) {
			$hrp_commissions_value = $data['hrp_commissions_value'];
			unset($data['hrp_commissions_value']);
		}

		/*update save note*/

		if(isset($hrp_commissions_value)){
			$hrp_commissions_detail = json_decode($hrp_commissions_value);

			$es_detail = [];
			$row = [];
			$header = $staff_information_key;			
			foreach ($hrp_commissions_detail as $key => $value) {
				
				$temp = [];
				$combine_data = [];
				$combine_data = array_combine($header, $value);

				$es_detail[] = $combine_data;
			}
		}

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;
		foreach ($es_detail as $key => $value) {

			if(isset($value['employee_number']) || array_key_exists('employee_number', $value)){
				unset($value['employee_number']);
			}
			if(isset($value['employee_name']) || array_key_exists('employee_name', $value)){
				unset($value['employee_name']);
			}
			if(isset($value['department_name']) || array_key_exists('department_name', $value)){
				unset($value['department_name']);
			}

			
			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		if($data['department_commissions_filter'] == '' && $data['staff_commissions_filter'] == '' && $data['role_commissions_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .')  AND date_format(month,"%Y-%m-%d") = "'.$commissions_month.'" AND rel_type = "'.$rel_type.'"');
			$this->db->delete(db_prefix().'hrp_commissions');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_commissions', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_commissions', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;

	}


	/**
	 * import commissions data
	 * @param  [type] $es_detail 
	 * @return [type]            
	 */
	public function import_commissions_data($es_detail)
	{
		$affectedRows=0;
		
		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;

		foreach ($es_detail as $key => $value) {
			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_commissions', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_commissions', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;
	}


	/**
	 * commissions synchronization
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function commissions_synchronization($data)
	{
		$affectedRows = 0;
		$rel_type = hrp_get_commission_status();

		$format_commissions_data = $this->get_format_commission_data();
		$staff_information_key = $format_commissions_data['staff_information'];
		$commissions_month = date('Y-m-d',strtotime($data['commissions_fill_month'].'-01'));

		$staff_commissions = $this->get_list_staff_commissions($commissions_month);

		if (isset($data['hrp_commissions_value'])) {
			$hrp_commissions_value = $data['hrp_commissions_value'];
			unset($data['hrp_commissions_value']);
		}

		/*update save note*/

		if(isset($hrp_commissions_value)){
			$hrp_commissions_detail = json_decode($hrp_commissions_value);

			$es_detail = [];
			$row = [];
			$header = $staff_information_key;			
			foreach ($hrp_commissions_detail as $key => $value) {
				
				$temp = [];
				$combine_data = [];
				$combine_data = array_combine($header, $value);

				$es_detail[] = $combine_data;
			}
		}

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;
		foreach ($es_detail as $key => $value) {
			if(isset($staff_commissions[$value['staff_id']])){
				$value['commission_amount'] = $staff_commissions[$value['staff_id']]['commission_amount'];
			}

			if(isset($value['employee_number']) || array_key_exists('employee_number', $value)){
				unset($value['employee_number']);
			}
			if(isset($value['employee_name']) || array_key_exists('employee_name', $value)){
				unset($value['employee_name']);
			}
			if(isset($value['department_name']) || array_key_exists('department_name', $value)){
				unset($value['department_name']);
			}
			
			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		if($data['department_commissions_filter'] == '' && $data['staff_commissions_filter'] == '' && $data['role_commissions_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .')  AND date_format(month,"%Y-%m-%d") = "'.$commissions_month.'" AND rel_type = "'.$rel_type.'"');
			$this->db->delete(db_prefix().'hrp_commissions');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_commissions', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_commissions', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;

	}


	/**
	 * get list staff commissions
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function get_list_staff_commissions($month)
	{
		$month = date('Y-m', strtotime($month));
		$sql_where ="SELECT c.staffid, sum(crd.amount_paid) as commission_amount FROM ".db_prefix()."commission_receipt as cr
		left join ".db_prefix()."commission_receipt_detail as crd on cr.id = crd.receipt_id
		left join ".db_prefix()."commission as c on crd.commission_id = c.id
		where date_format(cr.date, '%Y-%m') = '".$month."' AND c.is_client = '0'
		group by c.staffid";

		$commissions = $this->db->query($sql_where)->result_array();

		$staff_commissions=[];
		foreach ($commissions as $value) {
		    $staff_commissions[$value['staffid']] = $value;
		}

		return $staff_commissions;
	}


	/**
	 * get income tax data
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function get_income_tax_data($month)
	{

		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$income_taxs = $this->db->get(db_prefix() . 'hrp_income_taxs')->result_array();
		return $income_taxs;
	}

	/**
	 * get total income tax in year
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function get_total_income_tax_in_year($month)
	{
		$month = date('Y', strtotime($month));

		$this->db->select('staff_id, sum(income_tax) as tax_for_year');
		$this->db->where("date_format(month, '%Y') = '".$month."'");
		$this->db->group_by('staff_id');
		$income_taxs = $this->db->get(db_prefix() . 'hrp_income_taxs')->result_array();
		return $income_taxs;
	}


	/**
	 * get format income tax data
	 * @return [type] 
	 */
	public function get_format_income_tax_data()
	{	
		$staff_information=[];
		$staff_information[] = 'id';
		$staff_information[] = 'staff_id';
		$staff_information[] = 'month';
		$staff_information[] = 'employee_number';
		$staff_information[] = 'employee_name';
		$staff_information[] = 'department_name';
		$staff_information[] = 'income_tax';
		$staff_information[] = 'tax_for_year';


		//get column header name, column format
		$column_format=[];
		$staff_information_header=[];

		foreach ($staff_information as $value) {
			if($value == 'staff_id'){
				$staff_information_header[] = 'staff_id';
			}elseif($value == 'id'){
				$staff_information_header[] = 'id';
			}else{
				$staff_information_header[] = _l($value);
			}

			if($value == 'income_tax' || $value == 'tax_for_year'){
				array_push($column_format, [
					'data' => 'income_tax',
					'type'=> 'numeric',
					'numericFormat'=> [
						'pattern' => '0,00',
					]
				]);
			}else{
				array_push($column_format, [
					'data' => $value,
					'type' => 'text'
				]);
			}
		}
		

		//get value for commission

		$results=[];
		$results['staff_information']		= $staff_information;
		$results['staff_information_header']			= $staff_information_header;

		$results['column_format']		= $column_format;
		$results['headers']		= $staff_information_header;

		return $results;
	}


	/**
	 * get format insurances data
	 * @return [type] 
	 */
	public function get_format_insurance_data()
	{	
		$staff_information=[];
		$staff_information[] = 'id';
		$staff_information[] = 'staff_id';
		$staff_information[] = 'month';
		$staff_information[] = 'employee_number';
		$staff_information[] = 'employee_name';
		$staff_information[] = 'department_name';


		//get column header name, column format
		$column_format=[];
		$staff_information_header=[];

		foreach ($staff_information as $value) {
			if($value == 'staff_id'){
				$staff_information_header[] = 'staff_id';
			}elseif($value == 'id'){
				$staff_information_header[] = 'id';
			}else{
				$staff_information_header[] = _l($value);
			}
			array_push($column_format, [
				'data' => $value,
				'type' => 'text'
			]);
		}
		

		//get value for insurance
		$array_insurance = [];
		$array_insurance_header = [];

	    //get salary insurance list from setting
		$salary_insurances = $this->get_insurance_list();

		foreach ($salary_insurances as $key => $value) {
			$name ='';

			if($value['description'] != ''){
				$name .= $value['description'];
			}elseif($value['code'] != ''){
				$name .= $value['code'];
			}elseif($value['id'] != ''){
				$name .= $value['id'];
			}

			$array_insurance['st_insurance_'.$value['id']] = $value['rate'];
			$array_insurance_header[] = $name.' ('._l($value['basis']).')';

			array_push($column_format, [
				'data' => 'st_insurance_'.$value['id'],
				'type'=> 'numeric',
				'numericFormat'=> [
					'pattern' => '0,00',
				]
			]);

		}


		$results=[];
		$results['staff_information']		= $staff_information;
		$results['array_insurance']			= $array_insurance;
		$results['staff_information_header']			= $staff_information_header;
		$results['array_insurance_header']			= $array_insurance_header;

		$results['column_format']		= $column_format;
		$results['header']		= array_merge($staff_information_header, $array_insurance_header);

		return $results;
	}


	/**
	 * get insurances data
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function get_insurances_data($month, $where ='')
	{	
		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$insurances = $this->db->get(db_prefix() . 'hrp_staff_insurances')->result_array();

		$insurances_decode = $this->insurances_data_json_encode_decode('decode', $insurances, '');
		return $insurances_decode;
	}


	/**
	 * insurances data json encode decode
	 * @param  [type] $type   
	 * @param  [type] $data   
	 * @param  string $header 
	 * @return [type]         
	 */
	public function insurances_data_json_encode_decode($type, $data, $header ='')
	{
		if($type == 'decode'){
			// json decode
			foreach ($data as $key => $value) {
				$insurance_list = json_decode($value['insurance_list']);

				unset($data[$key]['insurance_list']);

				foreach ($insurance_list as $insurance_key => $insurance_value) {

					foreach (get_object_vars($insurance_value) as $column_key => $column_value) {
						$data[$key][$column_key] = $column_value;

						$data[$key]['insurance_value'][$column_key] = $column_value;
					}
				}

			}

		}else{
			// json encode
			$data_detail=[];
			$data_detail = $data;

			$data=[];

			foreach ($data_detail as $data_detail_value) {

				$temp = [];
				$insurance_list = [];

				foreach ($data_detail_value as $key => $value) {

					//integration Hr records module
					if(preg_match('/^st_insurance_/', $key) ){
						array_push($insurance_list, [
							$key => $value
						]);
					}elseif($key != 'department_name' && $key != 'employee_name' && $key != 'employee_number'  ){
						$temp[$key] = $value;
					}
					

				}

				$temp['insurance_list'] = json_encode($insurance_list);
				$data[] = $temp;
			}

		}
		return $data;
	}


	/**
	 * insurances update
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function insurances_update($data)
	{
		$affectedRows = 0;
		
		$format_insurances_data = $this->get_format_insurance_data();
		$staff_information_key = $format_insurances_data['staff_information'];

		$array_insurance_key = array_keys($format_insurances_data['array_insurance']);

		$insurances_month = date('Y-m-d',strtotime($data['insurances_fill_month'].'-01'));

		if (isset($data['hrp_insurances_value'])) {
			$hrp_insurances_value = $data['hrp_insurances_value'];
			unset($data['hrp_insurances_value']);
		}

		/*update save note*/

		if(isset($hrp_insurances_value)){
			$hrp_insurances_detail = json_decode($hrp_insurances_value);

			$es_detail = [];
			$row = [];
			$header = array_merge($staff_information_key, $array_insurance_key);			
			foreach ($hrp_insurances_detail as $key => $value) {
				
				$temp = [];
				$probationary_contracts = [];
				$primary_contracts = [];
				$combine_data = [];
				
				$combine_data = array_combine($header, $value);

				$es_detail[] = $combine_data;
			}
		}

		$es_detail = $this->insurances_data_json_encode_decode('encode', $es_detail);

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;
		foreach ($es_detail as $key => $value) {

			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}
		if($data['department_insurances_filter'] == '' && $data['staff_insurances_filter'] == '' && $data['role_insurances_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .')  AND date_format(month,"%Y-%m-%d") = "'.$insurances_month.'"');
			$this->db->delete(db_prefix().'hrp_staff_insurances');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_staff_insurances', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_staff_insurances', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}
		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;

	}


	/**
	 * get hrp payroll columns
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_hrp_payroll_columns($id = false){
		if (is_numeric($id)) {
			$this->db->where('id', $id);
			return $this->db->get(db_prefix() . 'hrp_payroll_columns')->row();
		}
		if ($id == false) {
			return $this->db->query('select * from ' . db_prefix() . 'hrp_payroll_columns order by order_display asc')->result_array();
		}
	}


	/**
	 * get list payroll column method
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function get_list_payroll_column_method($data)
	{
		 $method_option = '';

		if(isset($data['taking_method']) && $data['taking_method'] != ''){

			$list_column_method=[];
			$list_column_method[] = ['name' => 'system', 'lable' => _l('system_method')];
			$list_column_method[] = ['name' => 'caculator', 'lable' => _l('caculator_method')];
			$list_column_method[] = ['name' => 'constant', 'lable' => _l('constant_method')];

			$method_option .= '<option value=""></option>';
			foreach ($list_column_method as $method) {

					$select='';
					if($method['name'] == $data['taking_method']){           
						$select .= 'selected';
					}
					$method_option .= '<option value="' . $method['name'] . '" '.$select.'>' . $method['lable'] . '</option>';
				}

		}else{
			/*get payroll column method for case create new*/

			$method_option .= '<option value=""></option>';

			$method_option .= '<option value="system">' . _l('system_method'). '</option>';
			$method_option .= '<option value="caculator">' . _l('caculator_method'). '</option>';
			$method_option .= '<option value="constant">' . _l('constant_method'). '</option>';

		}
	   
		$data_return =[];
		$data_return['method_option'] = $method_option;

		return $data_return;

	}


	/**
	 * get list payroll column function name
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function get_list_payroll_column_function_name($data)
	{
		$prefix_probationary = HR_PAYROLL_PREFIX_PROBATIONARY;
		$prefix_formal = HR_PAYROLL_PREFIX_FORMAL;
		$payroll_system_columns = payroll_system_columns();
		$payroll_columns = $this->get_hrp_payroll_columns();

		$hrp_payroll_columns=[];
		foreach ($payroll_columns as $key => $value) {
			$hrp_payroll_columns[] = $value['function_name'];
		}

		$method_option = '';


		 //define function for get data payroll column: only get from system
		$list_column_method=[];
		foreach ($payroll_system_columns as $column_value) {
		    $list_column_method[] = ['function_name' => $column_value,        'lable' => ($column_value)];
		}

		//staff contract: salary, allowance
		$integrate_hr_profile = hrp_get_hr_profile_status();

		if($integrate_hr_profile == 'hr_records'){
			//hr_records
			$hr_records_earnings_list = $this->hr_records_get_earnings_list();
			foreach ($hr_records_earnings_list as $key => $value) {
				
				switch ($value['rel_type']) {
					case 'salary':
						$code1 = 'st1_'.$value['rel_id'];
						$code2 = 'st2_'.$value['rel_id'];
						break;

					case 'allowance':
						$code1 = 'al1_'.$value['rel_id'];
						$code2 = 'al2_'.$value['rel_id'];
						break;
					
					default:
						# code...
						break;
				}


				if($value['short_name'] != ''){
					$list_column_method[] = ['function_name' => $code1,          'lable' => $value['short_name'].$prefix_probationary];
					$list_column_method[] = ['function_name' => $code2,          'lable' => $value['short_name'].$prefix_formal];
				}elseif($value['description'] != ''){
					$list_column_method[] = ['function_name' => $code1,          'lable' => $value['description'].$prefix_probationary];
					$list_column_method[] = ['function_name' => $code2,          'lable' => $value['description'].$prefix_formal];
				}elseif($value['code'] != ''){
					$list_column_method[] = ['function_name' => $code1,          'lable' => $value['code'].$prefix_probationary];
					$list_column_method[] = ['function_name' => $code2,          'lable' => $value['code'].$prefix_formal];
				}elseif($value['id'] != ''){
					$list_column_method[] = ['function_name' => $code1,          'lable' => $value['id'].$prefix_probationary];
					$list_column_method[] = ['function_name' => $code2,          'lable' => $value['id'].$prefix_formal];
				}
			}
		}else{
			//none
			$earnings_list = $this->get_earnings_list();
			foreach ($earnings_list as $key => $value) {
				$list_column_method[] = ['function_name' => 'earning1_'.$value['id'],          'lable' => $value['short_name'].$prefix_probationary];
				$list_column_method[] = ['function_name' => 'earning2_'.$value['id'],          'lable' => $value['short_name'].$prefix_formal];
			}
		}

		//get salary deductions list from setting
		$salary_deductions = $this->get_salary_deductions_list();
		foreach ($salary_deductions as $key => $value) {
			$name ='';

			if($value['description'] != ''){
				$name .= $value['description'];
			}elseif($value['code'] != ''){
				$name .= $value['code'];
			}elseif($value['id'] != ''){
				$name .= $value['id'];
			}

			if($value['basis'] == 'gross' || $value['basis'] == 'fixed_amount' ){
				$list_column_method[] = ['function_name' => 'deduction_'.$value['id'],        'lable' => $name.' ('.$value['basis'].')'];
			}else{
				$list_column_method[] = ['function_name' => 'deduction_'.$value['id'],        'lable' => $name];
			}

		}

		//get insurance list from setting
		$salary_insurances = $this->get_insurance_list();
		foreach ($salary_insurances as $key => $value) {
			$name ='';

			if($value['description'] != ''){
				$name .= $value['description'];
			}elseif($value['code'] != ''){
				$name .= $value['code'];
			}elseif($value['id'] != ''){
				$name .= $value['id'];
			}

			$list_column_method[] = ['function_name' => 'st_insurance_'.$value['id'],        'lable' => $name.' ('._l($value['basis']).')'];
		}
		
			
		if(isset($data['function_name']) && $data['function_name'] != ''){

			$method_option .= '<option value=""></option>';
			foreach ($list_column_method as $method) {
				if(!in_array($method['function_name'], $payroll_system_columns) && !in_array($method['function_name'], $hrp_payroll_columns) || $method['function_name'] == $data['function_name']){

					$select='';
					if($method['function_name'] == $data['function_name']){           
						$select .= 'selected';
					}
					$method_option .= '<option value="' . $method['function_name'] . '" '.$select.'>' . $method['lable'] . '</option>';
				}
			}

		}else{
			/*get payroll column method for case create new*/

			$method_option .= '<option value=""></option>';
			foreach ($list_column_method as $method) {
				if(!in_array($method['function_name'], $payroll_system_columns) && !in_array($method['function_name'], $hrp_payroll_columns)){
					$method_option .= '<option value="' . $method['function_name'] . '" >' . $method['lable'] . '</option>';
				}
			}

		}
	   
		$data_return =[];
		$data_return['method_option'] = $method_option;

		return $data_return;

	}


	/**
	 * add payroll column
	 * @param [type] $data 
	 */
	public function add_payroll_column($data){

		if(isset($data['display_with_staff'])){
			$data['display_with_staff'] = 'true';
		}else{
			$data['display_with_staff'] = 'false';
		}  

		$data['staff_id_created'] = get_staff_user_id();
		$data['date_created'] = date('Y-m-d H:i:s');

		$this->db->insert(db_prefix() . 'hrp_payroll_columns', $data);
		$insert_id = $this->db->insert_id();
		return $insert_id;
	}

	/**
	 * update insurance type
	 * @param  array $data 
	 * @param  integer $id   
	 * @return boolean       
	 */
	public function update_payroll_column($data, $id){
		$hrp_payroll_column = $this->get_hrp_payroll_columns();
		if($hrp_payroll_column){
			if($hrp_payroll_column->is_edit == 'no'){
				if(isset($data['taking_method'])){
					unset($data['taking_method']);
				}
				if(isset($data['function_name'])){
					unset($data['function_name']);
				}
			}
		}

		if(isset($data['display_with_staff'])){
			$data['display_with_staff'] = 'true';
		}else{
			$data['display_with_staff'] = 'false';
		}
		
		$data['staff_id_created'] = get_staff_user_id();
		$this->db->where('id',$id);
		$this->db->update(db_prefix().'hrp_payroll_columns', $data);
		if ($this->db->affected_rows() > 0) {
			return true;
		}else{
			return false;
		}

	}

	/**
	 * delete insurance type
	 * @param  integer $id 
	 * @return boolean     
	 */
	public function delete_payroll_column($id){
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'hrp_payroll_columns');
		if ($this->db->affected_rows() > 0) {
			return true;
		}
		return false;
	}

	/**
	 * count payroll column
	 * @return [type] 
	 */
	public function count_payroll_column()
	{
		$payroll_columns = count($this->get_hrp_payroll_columns());

		return (float)$payroll_columns + 1;
	}


	/**
	 * get hrp payslip templates
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_hrp_payslip_templates($id = false){
		if (is_numeric($id)) {
			$this->db->where('id', $id);
			return $this->db->get(db_prefix() . 'hrp_payslip_templates')->row();
		}
		if ($id == false) {
			return $this->db->query('select * from ' . db_prefix() . 'hrp_payslip_templates order by id desc')->result_array();
		}
	}


	/**
	 * get hrp payslip
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_hrp_payslip($id = false){
		if (is_numeric($id)) {
			$this->db->where('id', $id);
			return $this->db->get(db_prefix() . 'hrp_payslips')->row();
		}
		if ($id == false) {
			return $this->db->query('select * from ' . db_prefix() . 'hrp_payslips order by id desc')->result_array();
		}
	}

	/**
	 * get payslip template selected html
	 * @param  [type] $payslip_template_id 
	 * @return [type]                      
	 */
	public function get_payslip_template_selected_html($payslip_template_id)
	{
		$payslip_templates = $this->get_hrp_payslip_templates();
		$template_options = '';

		if(isset($payslip_template_id) && $payslip_template_id != ''){

			$template_options .= '<option value=""></option>';
			foreach ($payslip_templates as $template) {

					$select='';
					if($template['id'] == $payslip_template_id){           
						$select .= 'selected';
					}
					$template_options .= '<option value="' . $template['id'] . '" '.$select.'>' . $template['templates_name'] . '</option>';
				}

		}else{
			/*get payslip template for case create new*/

			$template_options .= '<option value=""></option>';
			foreach ($payslip_templates as $template) {
				$template_options .= '<option value="' . $template['id'] . '" >' . $template['templates_name'] . '</option>';
			}

		}

		return $template_options;

	}


	/**
	 * get payslip column html
	 * @param  [type] $payslip_columns 
	 * @return [type]                  
	 */
	public function get_payslip_column_html($payslip_columns)
	{
		
		$payroll_columns = $this->get_hrp_payroll_columns();
		$payroll_column_options = '';

		if(isset($payslip_columns) && $payslip_columns != ''){
			$array_payslip_column = new_explode(",", $payslip_columns);

			foreach ($payroll_columns as $column_id) {
					$select='';
					if(in_array($column_id['id'], $array_payslip_column)){
						$select .= 'selected';
					}
					
					$payroll_column_options .= '<option value="' . $column_id['id'] . '" '.$select.'>' . $column_id['column_key'] . '</option>';
				}

		}else{
			/*get payslip template for case create new*/

			foreach ($payroll_columns as $column_id) {
				$payroll_column_options .= '<option value="' . $column_id['id'] . '" >' . $column_id['column_key'] . '</option>';
			}

		}

		return $payroll_column_options;

	}


	/**
	 * add payslip template
	 * @param [type] $data 
	 */
	public function add_payslip_template($data){
		if(isset($data['department_id'])){
			$data['department_id'] = implode(',', $data['department_id']);
		}
		if(isset($data['role_employees'])){
			$data['role_employees'] = implode(',', $data['role_employees']);
		}
		if(isset($data['staff_employees'])){
			$data['staff_employees'] = implode(',', $data['staff_employees']);
		}
		if(isset($data['except_staff'])){
			$data['except_staff'] = implode(',', $data['except_staff']);
		}
		
		if(isset($data['edit_payslip_column'])){
			unset($data['edit_payslip_column']);
		}

		//add staff_id default to payslip template
		if(!in_array('1', $data['payslip_columns'])){
			array_unshift($data['payslip_columns'], '1');
		}

		$data['payslip_columns'] =  implode(',', $data['payslip_columns']);
		$data['staff_id_created'] = get_staff_user_id();
		$data['date_created'] = date('Y-m-d H:i:s');

		$this->db->insert(db_prefix() . 'hrp_payslip_templates', $data);
		$insert_id = $this->db->insert_id();
		return $insert_id;
	}

	/**
	 * update payslip template
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_payslip_template($data, $id)
	{
		if(isset($data['department_id'])){
			$data['department_id'] = implode(',', $data['department_id']);
		}else{
			$data['department_id'] = '';
		}
		if(isset($data['role_employees'])){
			$data['role_employees'] = implode(',', $data['role_employees']);
		}else{
			$data['role_employees'] = '';
		}
		if(isset($data['staff_employees'])){
			$data['staff_employees'] = implode(',', $data['staff_employees']);
		}else{
			$data['staff_employees'] = '';
		}

		if(isset($data['except_staff'])){
			$data['except_staff'] = implode(',', $data['except_staff']);
		}else{
			$data['except_staff'] = '';
		}

		$data['payslip_columns'] =  implode(',', $data['payslip_columns']);
		$data['staff_id_created'] = get_staff_user_id();

		$this->db->where('id',$id);
		$this->db->update(db_prefix().'hrp_payslip_templates', $data);
		if ($this->db->affected_rows() > 0) {
			return true;
		}else{
			return false;
		}

	}

	/**
	 * delete payslip template
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_payslip_template($id){
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'hrp_payslip_templates');
		if ($this->db->affected_rows() > 0) {
			return true;
		}
		return false;
	}


	/**
	 * delete payslip
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function delete_payslip($id)
	{

        hooks()->do_action('before_payslip_deleted', $id);
		
		$affected_rows =0;

		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'hrp_payslips');
		if ($this->db->affected_rows() > 0) {
			$affected_rows++;
		}

		$delete_payslip_detail = $this->delete_payslip_detail($id);
		if($delete_payslip_detail == true){
			$affected_rows++;
		}

		//delete income tax
		$this->db->where('payslip_id', $id);
		$this->db->delete(db_prefix() . 'hrp_income_taxs');
		if ($this->db->affected_rows() > 0) {
			$affected_rows++;
		}

		if($affected_rows > 0){
			return true;
		}
		return false;
	}


	/**
	 * delete_payslip_detail
	 * @param  [type] $payslip_id 
	 * @return [type]             
	 */
	public function delete_payslip_detail($payslip_id)
	{
		$this->db->where('payslip_id', $payslip_id);
		$this->db->delete(db_prefix() . 'hrp_payslip_details');
		if ($this->db->affected_rows() > 0) {
			return true;
		}
		
		return false;
	}


	/**
	 * update payslip templates detail
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_payslip_templates_detail($data, $id){  
		if(isset($data['image_flag'])){
			if($data['image_flag'] == "true"){
				$data['payslip_template_data'] = new_str_replace('[removed]', 'data:image/png;base64,', $data['payslip_template_data']); 
				$data['payslip_template_data'] = new_str_replace('imga$imga', '"', $data['payslip_template_data']); 
				$data['payslip_template_data'] = new_str_replace('""', '"', $data['payslip_template_data']); 
			}
		}

		$payslip_template_data_decode = json_decode($data['payslip_template_data']);
		if(isset($payslip_template_data_decode[0])){
			if(isset($payslip_template_data_decode[0]->celldata)){
				$data['cell_data'] = hrp_payslip_replace_string(json_encode($payslip_template_data_decode[0]->celldata));
			}
		}
		$data['payslip_template_data'] = hrp_payslip_replace_string($data['payslip_template_data']);
		$data['templates_name'] = $data['name']; 
		unset($data['name']);
		unset($data['image_flag']);

		$this->db->where('id', $id);
		$this->db->update(db_prefix().'hrp_payslip_templates', $data);

		if ($this->db->affected_rows() > 0) {
			return true;
		}else{
			return false;
		}

	}

	/**
	 * add payslip templates detail first
	 * @param [type] $id 
	 */
	public function add_payslip_templates_detail_first($id, $update= false, $old_column_formular=[])
	{
		$payslip_templates = $this->get_hrp_payslip_templates($id);

		$payslip_template_data = [];

		if($payslip_templates){

			$payslip_columns = new_explode(",", $payslip_templates->payslip_columns);

			$sql_where = "SELECT id, column_key, function_name, taking_method FROM ".db_prefix()."hrp_payroll_columns where find_in_set(id, '".$payslip_templates->payslip_columns."') order by order_display";
			$payroll_columns = $this->db->query($sql_where)->result_array();

			$payslip_template_data['name']      = $payslip_templates->templates_name;

			

			$data = [];
			$data_row_null = [];
			$data_row_name = [];

			$cell_data = [];

			//column A to Z
			$min_index_column = 25 < count($payslip_columns) ? count($payslip_columns) : 25;

			$payslip_template_data['column']    = $min_index_column;

			//render data null of row
			for ($x = 0; $x <= $min_index_column; $x++) {
				$data_row_null[] = null;
			}

			$columnlen=[];
			$rowlen=[];
			$rowlen[3] = 46;
			$rowlen[4] = 46;
			$rowlen[5] = 46;
			$calcChain =[];

			//add data value for cell			
			foreach ($payslip_columns as $value) {
				$columnlen[] = 183;

				$neededObject = array_filter(
					$payroll_columns,
					function ($e) use ($value) {
						return $e['id'] == $value ;
					}
				);

				foreach ($neededObject as $object_key => $object_value) {

					$data_row_name[]    = $this->general_template_cell_data($object_value['column_key']);
					$cell_data[]        = $this->general_cell_data(3, $object_key, $object_value['column_key'],'','',true , false);
					$cell_data[]        = $this->general_cell_data(4, $object_key, $object_value['function_name'],'','',false , true);

					$f='';
					if($update == true){
					//case update

						if($object_value['taking_method'] == 'caculator' || $object_value['taking_method'] == 'constant'){

							if($object_value['taking_method'] == 'caculator'){
								$object_value['taking_method'] = 'formula';
							}
							if(isset($old_column_formular[$object_value['function_name']])){
								$f = $old_column_formular[$object_value['function_name']];
							}

							if($f == 0){
								$f = '';
							}

			    			//calcChain: Formula chain, used when the cell linked by the formula is changed, all formulas referencing this cell will be refreshed.
							array_push($calcChain, [
								"r" => 5,
								"c" => $object_key,
								"index" => 0,
								"color" => "b",
								"parent" => null,
								"chidren" => new stdClass(),
								"times" => 1
							]);

						}

					}else{
					//case add new
						if($object_value['taking_method'] == 'caculator'){
							$object_value['taking_method'] = 'formula';
						}
					}


					$cell_data[]        = $this->general_cell_data(5, $object_key, $object_value['taking_method'],'',$f,false , true);

				}

			}

			//concat payslip template data with data fixed
			$payslip_template_data = array_merge($payslip_template_data, $this->payslip_template_data_fixed([], [], $columnlen, $rowlen));

			//add data null for cell
			if(count($payslip_columns) < $min_index_column){

				for ($x = 0; $x <= $min_index_column - count($payslip_columns); $x++) {
					$data_row_name[] =  null;
				}
			}

			$data[] = $data_row_null;
			$data[] = $data_row_null;
			$data[] = $data_row_null;
			$data[] = $data_row_name;

			$payslip_template_data['celldata']    = $cell_data;
			$payslip_template_data['calcChain']    = $calcChain;

			for ($x = 0; $x <= 31; $x++) {
				$data[] = $data_row_null;
			}

			$payslip_template_data['data'] = $data;

			$payslip_template_data_update = [];
			$payslip_template_data_update_temp = json_decode(hrp_payslip_replace_string(json_encode($payslip_template_data)));
			$payslip_template_data_update[] = $payslip_template_data_update_temp;
		

			$this->db->where('id', $id);
			$this->db->update(db_prefix().'hrp_payslip_templates', [
				'payslip_template_data' => json_encode($payslip_template_data_update),
				'cell_data' => json_encode($cell_data)
			]);

			if ($this->db->affected_rows() > 0) {
				return true;
			}else{
				return false;
			}
		}

		return false;
	}


	/**
	 * [general_cell_data description]
	 * @param  [type] $cell_name [description]
	 * @return [type]            [description]
	 *      Cell data format
			
			*  {
			*       "m":"Hr_code",
			*       "ct":{"fa":"General","t":"g"},
			*       "v":"Hr_code"
			*   }
			* 
			*
	 */
	public function general_template_cell_data($cell_name)
	{
		$ct_data = [];
		$ct_data = [
			"fa"    => "General",
			"t"     => "g",
		];

		$cell_data = [];
		$cell_data = [
			"m"     => $cell_name,
			"ct"    => $ct_data,
			"v"     => $cell_name,
			"bg" 	=> '#fff000',
			"bl" 	=> 1,
			"fs" 	=> 12,
			"ht" 	=> 0,
			"vt" 	=> 0,
		];

		return $cell_data;
	}


	/**
	 * general cell data
	 * @param  [type] $row   
	 * @param  [type] $col   
	 * @param  [type] $value 
	 * @return [type]
	 * {"r":2,"c":0,"v":{"m":"Hr_code","ct":{"fa":"General","t":"g"},"v":"Hr_code"}}        
	 */
	public function general_cell_data($row, $col, $value, $t, $f, $luckysheet_header_format, $luckysheet_row_format, $luckysheet_company_format='false', $number_format ='')
	{	
		$cell_format=[];

		if($t != ''){
			$t = 'g';
		}

		$ct_data = [];

		if($number_format == 11){
			$ht = 2;

			$ct_data = [
				"fa"    => '#,##0.00',
				"t"     => 'n',
			];
		}else{
			$ht = 1;

			$ct_data = [
				"fa"    => "General",
				"t"     => $t,
			];

		}

		$v_data = [];

		if($f != ''){
			if($luckysheet_row_format == true){
				$v_data = [
					"m"     => $value,
					"ct"    => $ct_data,
					"v"     => $value,
					"f"		=> $f,
					"bl" 	=> 0,
					"fs" 	=> 11,
					"vt" 	=> 0,
					"ht"	=> $ht,
				];
			}else{
				$v_data = [
					"m"     => $value,
					"ct"    => $ct_data,
					"v"     => $value,
					"f"		=> $f
				];
			}
		}else{

			if($luckysheet_header_format == true){
				$v_data = [
					"m"     => $value,
					"ct"    => $ct_data,
					"v"     => $value,
					"bg" 	=> '#fff000',
					"bl" 	=> 1,
					"fs" 	=> 12,
					"ht" 	=> 0,
					"vt" 	=> 0,
					"tb"	=> 2,
				];
			}elseif($luckysheet_row_format == true){
				$v_data = [
					"m"     => $value,
					"ct"    => $ct_data,
					"v"     => $value,
					"bl" 	=> 0,
					"fs" 	=> 11,
					"vt" 	=> 0,
					"ht"	=> $ht,

				];
			}elseif($luckysheet_company_format == true){
				$v_data = [
					"m"     => $value,
					"ct"    => $ct_data,
					"v"     => $value,
					"fs" 	=> 17,
					"tb" 	=> 1,
					"bl" 	=> 1,

				];

			}else{
				$v_data = [
					"m"     => $value,
					"ct"    => $ct_data,
					"v"     => $value,
				];
			}
			
		}

		$cell_data = [];
		$cell_data = [
			"r" => $row,
			"c" => $col,
			"v" => $v_data,
		];
		return $cell_data;
	}

	/**
	 * payslip template data fixed
	 * @param  string $value 
	 * @return [type]        
	 */
	public function payslip_template_data_fixed($visible_row =[], $visible_column = [], $columnlen =[], $rowlen=[])
	{   
		$payslip_template_data = [];

		$payslip_template_data['status']    = '1';
		$payslip_template_data['order']     = '0';
		$payslip_template_data['row']       = 36;
		$payslip_template_data['config']    = new stdClass();
		$payslip_template_data['config']->columnlen = $columnlen;
		$payslip_template_data['config']->rowlen = $rowlen;
		$payslip_template_data['index']    = 0;
		$payslip_template_data['load']    = '1';

		$visibledatarow = [];

		if(count($visible_row) > 0){
			$visibledatarow = $visible_row;
		}else{
			$visibledatarow = [20,40,60,80,100,120,140,160,180,200,220,240,260,280,300,320,340,360,380,400,420,440,460,480,500,520,540,560,580,600,620,640,660,680,700,720];
		}

		$payslip_template_data['visibledatarow']    = $visibledatarow;
		$visibledatacolumn = [];

		if(count($visible_column) > 0){
			$visibledatacolumn = $visible_column;
		}else{

			$visibledatacolumn = [74,148,222,296,370,444,518,592,666,740,814,888,962,1036,1110,1184,1258,1332,1406,1480,1554,1628,1702,1776,1850,1924];
		}
		$payslip_template_data['visibledatacolumn']    = $visibledatacolumn;
		$payslip_template_data['ch_width']    = 3009;
		$payslip_template_data['rh_height']    = 822;

		$luckysheet_select_save = [
			"left"          => 74,
			"width"         => 73,
			"top"           => 40,
			"height"        => 19,
			"left_move"     => 74,
			"width_move"    => 73,
			"top_move"      => 40,
			"height_move"   => 19,
			"row"           => array(0 => 3, 1 => 3),
			"column"        => array(0 => 1, 1 => 1),
			"row_focus"     => 2,
			"column_focus"  => 1,
		];
		$payslip_template_data['luckysheet_select_save']    = array(0 => $luckysheet_select_save);

		$luckysheet_selection_range =array();
		$payslip_template_data['luckysheet_selection_range']    = $luckysheet_selection_range;
		$payslip_template_data['zoomRatio']    = 1;

		return $payslip_template_data;
	}


	/**
	 * update payslip templates detail first
	 * @param  [type] $id 
	 * @return [type]  
	 *
	 * Update payslip template data when update column on Main management, ex: delete column
	 */
	public function update_payslip_templates_detail_first($old_column_formular, $id)
	{
		$result = $this->add_payslip_templates_detail_first($id, true, $old_column_formular);
		return $result;
	}


	/**
	 * check update payslip template detail
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function check_update_payslip_template_detail($data, $id)
	{
		$payslip_template = $this->get_hrp_payslip_templates($id);

		//get old column formula
		$old_cell_data = json_decode($payslip_template->cell_data);
		$array_cell_data = $this->array_cell_data($payslip_template);
		$old_column_formular = array_combine($array_cell_data['payroll_column_key'], $array_cell_data['payroll_formular']);

		if($payslip_template){
			$old_payslip_columns = new_explode(",", $payslip_template->payslip_columns);

			$diff = array_diff($old_payslip_columns, $data['payslip_columns']);
			$diff1 = array_diff($data['payslip_columns'], $old_payslip_columns);

			if(count($diff) > 0 || count($diff1) > 0){
				return ['status' => true, 'old_column_formular' => $old_column_formular];
			}
				return ['status' => false];
		}

		return ['status' => false];
	}


	/**
	 * get staff info
	 * @param  [type] $staffid 
	 * @return [type]          
	 */
	public function get_staff_info($staffid){
		$this->db->where('staffid', $staffid);
		$results = $this->db->get(db_prefix().'staff')->row();
		return $results;        
	}




	/**
	 * get staff departments
	 * @param  boolean $userid  
	 * @param  boolean $onlyids 
	 * @return [type]           
	 */
	public function get_staff_departments($userid = false, $onlyids = false)
	{
		if ($userid == false) {
			$userid = get_staff_user_id();
		}
		if ($onlyids == false) {
			$this->db->select();
		} else {
			$this->db->select(db_prefix() . 'staff_departments.departmentid');
		}
		$this->db->from(db_prefix() . 'staff_departments');
		$this->db->join(db_prefix() . 'departments', db_prefix() . 'staff_departments.departmentid = ' . db_prefix() . 'departments.departmentid', 'left');
		$this->db->where('staffid', $userid);
		$departments = $this->db->get()->result_array();
		if ($onlyids == true) {
			$departmentsid = [];
			foreach ($departments as $department) {
				array_push($departmentsid, $department['departmentid']);
			}

			return $departmentsid;
		}
		return $departments;
	}


	/**
	 * get all staff departments
	 * @return [type] 
	 */
	public function get_all_staff_departments()
	{
		$sql = "SELECT sdp.staffid, dp.name FROM ".db_prefix()."staff_departments as sdp
		left join ".db_prefix()."departments as dp on sdp.departmentid = dp.departmentid 
		left join ".db_prefix()."staff as s on sdp.staffid = s.staffid
		where s.active = 1
		order by sdp.staffid";

		$staff_departments = $this->db->query($sql)->result_array();

		$staff=[];
		foreach ($staff_departments as $value) {

			if(isset($staff[$value['staffid']])){
				$staff[$value['staffid']] = $staff[$value['staffid']].', '.$value['name'];
			}else{
				$staff[$value['staffid']] = $value['name'];
			}
		}

		return $staff;
	}
	
	/**
	 * get bonus
	 * @param  [integer] $staffid 
	 * @param  [] $month   
	 * @return object        
	 */
	public function get_bonus_by_month($staffid, $month)
	{

		$this->db->where('staffid', $staffid);
		$this->db->where('month_bonus_kpi', $month);

	   return $this->db->get(db_prefix() . 'hrp_bonus_kpi')->row();
		

	}


	/**
	 * get bonus kpi
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function get_bonus_kpi($month, $where='')
	{
		$month = date('Y-m', strtotime($month));
		if($where != ''){
			$this->db->where($where);
		}
		$this->db->select('*, staffid as staff_id');
		$this->db->where('month_bonus_kpi', $month);

	   $bonus_kpi = $this->db->get(db_prefix() . 'hrp_bonus_kpi')->result_array();
	   return $bonus_kpi;
	}


	/**
	 * get staff timekeeping applicable object
	 * @return [type] 
	 */
	public function get_staff_timekeeping_applicable_object($where = [])
	{
		$rel_type = hrp_get_hr_profile_status();

		$this->db->select('*,CONCAT(firstname," ",lastname) as full_name');
    	if($rel_type == 'hr_records'){
    		$this->db->join(db_prefix() . 'hr_job_position', db_prefix() . 'staff.job_position = ' . db_prefix() . 'hr_job_position.position_id', 'left');
    	}
    	$this->db->where('active', 1);
    	$this->db->where($where);
    	$this->db->order_by('firstname', 'desc');
		$staffs = $this->db->get(db_prefix().'staff')->result_array();
		return $staffs; 
	}

	/**
	 * add bonus kpi
	 * @param [type] $data array
	 */
	public function add_bonus_kpi($data)
	{

		$data_bonus_kpi = new_str_replace(', ','|/\|',$data['bonus_kpi_value']);

		$data_data_bonus_kpi = new_explode( ',', $data_bonus_kpi);
		$results = 0;
		$results_update = '';
		$flag_empty = 0;

		$month_add_update = new_str_replace('/', '-', $data['allowance_commodity_fill_month']);
		
		foreach ($data_data_bonus_kpi as  $data_bonus_key => $data_bonus_value) {
			if($data_bonus_value == ''){
					$data_bonus_value = 0;
				}
			if(($data_bonus_key+1)%6 == 0){
				$arr_temp['bonus_kpi'] = hr_payroll_reformat_currency($data_bonus_value);

				//check add or update data
				$this->db->where('staffid', $arr_temp['staffid']);
				$this->db->where('month_bonus_kpi', $month_add_update);

				$staff_point_in_month = $this->db->get(db_prefix() . 'hrp_bonus_kpi')->row();

				if($staff_point_in_month){
					//update
						$this->db->where('id', $staff_point_in_month->id);
						$this->db->update(db_prefix() . 'hrp_bonus_kpi', $arr_temp);
						if ($this->db->affected_rows() > 0) {
							$results_update = true;
						}

				}else{
					//insert
					$this->db->insert(db_prefix().'hrp_bonus_kpi', $arr_temp);
					$insert_id = $this->db->insert_id();
					if($insert_id){
						$results++;
					}

				}

				
				$arr_temp = [];

			}else{

				switch (($data_bonus_key+1)%6) {
					case 1:
					 $arr_temp['staffid'] = new_str_replace('|/\|',', ',$data_bonus_value);
						break;
										 
				}

				$arr_temp['month_bonus_kpi'] = $month_add_update;

			}

		}
		
		if($results > 0 || $results_update == true){
			return true;
		}else{
			return false;
		}
		

	}


	/**
	 * getStaff
	 * @param  string $id    
	 * @param  array  $where 
	 * @return [type]        
	 */
	public function getStaff($id = '', $where = [])
	{
		$select_str = '*,CONCAT(firstname," ",lastname) as full_name';

		// Used to prevent multiple queries on logged in staff to check the total unread notifications in core/AdminController.php
		if (is_staff_logged_in() && $id != '' && $id == get_staff_user_id()) {
			$select_str .= ',(SELECT COUNT(*) FROM ' . db_prefix() . 'notifications WHERE touserid=' . get_staff_user_id() . ' and isread=0) as total_unread_notifications, (SELECT COUNT(*) FROM ' . db_prefix() . 'todos WHERE finished=0 AND staffid=' . get_staff_user_id() . ') as total_unfinished_todos';
		}

		$this->db->select($select_str);
		$this->db->where($where);

		if (is_numeric($id)) {
			$this->db->where('staffid', $id);
			$staff = $this->db->get(db_prefix() . 'staff')->row();

			if ($staff) {
				$this->load->model('staff/staff_model');
				$staff->permissions = $this->staff_model->get_staff_permissions($id);
			}

			return $staff;
		}
		
		$this->db->order_by('firstname', 'desc');

		return $this->db->get(db_prefix() . 'staff')->result_array();
	}


	/**
	 * payslip template get staffid
	 * @param  [type] $departemnt_ids 
	 * @param  [type] $role_ids       
	 * @param  [type] $staff_ids      
	 * @return [type]                
	 */
	public function payslip_template_get_staffid($department_ids, $role_ids, $staff_ids, $except_staff='')
	{
		if(new_strlen($staff_ids) > 0){
			if( new_strlen($except_staff) > 0){
				$array_except_staff = new_explode(",", $except_staff);
				$array_staff_ids = new_explode(",", $staff_ids);

				$new_staff_ids=[];
				foreach ($array_staff_ids as $value) {
				    if(!in_array($value, $array_except_staff)){
				    	$new_staff_ids[] = $value;
				    }
				}

				if(count($new_staff_ids) > 0){
					return implode(",", $new_staff_ids);
				}
				return '';
			}else{
				return $staff_ids;
			}
		}	
		
		$department_querystring='';
		$role_querystring='';
		$except_staff_querystring='';

		if(new_strlen($department_ids) > 0){
			$arrdepartment = $this->staff_model->get('', 'staffid in (select '.db_prefix().'staff_departments.staffid from '.db_prefix().'staff_departments where departmentid IN( '.$department_ids.'))');
			$temp = '';
			foreach ($arrdepartment as $value) {
				$temp = $temp.$value['staffid'].',';
			}
			$temp = rtrim($temp,",");
			$department_querystring = 'FIND_IN_SET(staffid, "'.$temp.'")';
		}

		if( new_strlen($role_ids) > 0){
			$role_querystring = 'FIND_IN_SET(role, "'.$role_ids.'")';
		}

		if( new_strlen($except_staff) > 0){
			$except_staff_querystring = 'staffid NOT IN ('.$except_staff .')' ;
		}

		$arrQuery = array($department_querystring, $role_querystring, $except_staff_querystring);

		$newquerystring = '';
		foreach ($arrQuery as $string) {
			if($string != ''){
				$newquerystring = $newquerystring.$string.' AND ';
			}            
		}  

		$newquerystring=rtrim($newquerystring,"AND ");
		if($newquerystring == ''){
			$newquerystring = [];
		}
		$staffs = $this->get_staff_timekeeping_applicable_object($newquerystring);
		$staff_ids=[];
		foreach ($staffs as $key => $value) {
		    $staff_ids[] = $value['staffid'];
		}

		if(count($staff_ids) > 0){
			return implode(',', $staff_ids);
		}
		return false;
	}


	/**
	 * add payslip
	 * @param [type] $data 
	 */
	public function add_payslip($data)
	{   	
		$payslip_range = $data['payslip_range'];
		$arr_payslip_range =explode(' to ', $data['payslip_range']);
		// var_dump($arr_payslip_range);die;

		$staff_departments = $this->get_all_staff_departments();
		$render_income_tax_formular = $this->render_income_tax_formular('AX');
		$number_to_anphabe = hrp_payslip_number_to_anphabe();
		$payroll_templates = $this->get_hrp_payslip_templates($data['payslip_template_id']);
		$staffids = $this->payslip_template_get_staffid($payroll_templates->department_id, $payroll_templates->role_employees, $payroll_templates->staff_employees, $payroll_templates->except_staff);

		$str_sql1 = 'staffid  IN (0)';
		$str_sql = 'staff_id  IN (0)';
		if($staffids != false){
			$str_sql1 = 'staffid  IN ('.$staffids .')';
			$str_sql = 'staff_id  IN ('.$staffids .')';
		}

		$hr_profile_status = hrp_get_hr_profile_status();

		//get_staff based on payslip template ( staff_id)
		
		//staff information
		$staffs=[];
		$attendances=[];
		$employees_data=[];

		$payslip_month = date('Y-m-d',strtotime($data['payslip_month'].'-01'));

		if($str_sql1 == ''){
			$staffs = [];
		}else{
			$staffs = $this->get_staff_timekeeping_applicable_object($str_sql1);
		}

		$staffs_id=[];
		foreach ($staffs as $staff_key => $staff_value) {
			$staff_value['employee_name'] = $staff_value['firstname'].' '.$staff_value['lastname'];
			$staff_value['payment_run_date'] = _d(date('Y-m-d'));

			if(isset($staff_departments[$staff_value['staffid']])){
				$staff_value['dept_name'] = $staff_departments[$staff_value['staffid']];
			}else{
				$staff_value['dept_name'] = '';
			}

			if($hr_profile_status == 'hr_records'){
				$staff_value['employee_number'] = $staff_value['staff_identifi'];
				$staff_value['job_title'] = $staff_value['position_name'];
				$staff_value['income_tax_number'] = $staff_value['Personal_tax_code'];
				$staff_value['residential_address'] = $staff_value['resident'];

			}else{
				$staff_value['employee_number'] = $this->hrp_format_code('EXS', $staff_value['staffid'], 5);
			}

			$staff_value['pay_slip_number'] = $this->hrp_format_code('PS_'.date('Y-m', strtotime($data['payslip_month'])).'_', $staff_value['staffid'].rand(10,99), 5);

			$staff_value['staff_id'] = $staff_value['staffid'];
		    $staffs_id[$staff_value['staffid']] = $staff_value;

		}

		$payslip_range = '';

		if($arr_payslip_range[0] != $arr_payslip_range[1] && ((int)date('m', strtotime($arr_payslip_range[0])) == (int)date('m', strtotime($arr_payslip_range[1])))){
			$attendance_calculation_v2 = $this->attendance_calculation_v2($data['payslip_month'], $arr_payslip_range, $str_sql);

			$salary_by_tasks_v2 = $this->get_tasks_timer_by_month($payslip_month, $str_sql, $str_sql1, $hr_profile_status, $arr_payslip_range);

			$payslip_range = $data['payslip_range'];
		}elseif($arr_payslip_range[0] != $arr_payslip_range[1] && ((int)date('m', strtotime($arr_payslip_range[0])) != (int)date('m', strtotime($arr_payslip_range[1])))){

			$_month = (int)date('m',strtotime($arr_payslip_range[0]));
			$_year = (int)date('y',strtotime($arr_payslip_range[0]));

			$total_day_in_month = cal_days_in_month(CAL_GREGORIAN,$_month,$_year);
			$last_day_of_month = date('Y-m-d', strtotime(date('Y',strtotime($arr_payslip_range[0])).'-'.date('m',strtotime($arr_payslip_range[0])).'-'.$total_day_in_month));
			$arr_payslip_range[1] = $last_day_of_month;
			$attendance_calculation_v2 = $this->attendance_calculation_v2($data['payslip_month'], $arr_payslip_range, $str_sql);
			$salary_by_tasks_v2 = $this->get_tasks_timer_by_month($payslip_month, $str_sql, $str_sql1, $hr_profile_status, $arr_payslip_range);

			$payslip_range = $arr_payslip_range[0].' to '.$arr_payslip_range[1];

		}

		//get attendance by month
		$hrp_attendance = $this->get_hrp_attendance($payslip_month, $str_sql);
		if(isset($attendance_calculation_v2)){
			$hrp_attendance = $attendance_calculation_v2;
		}

		foreach ($hrp_attendance as $attendance_key => $attendance_value) {

		    $attendances[$attendance_value['staff_id']] = $attendance_value;

		    if(isset($staffs_id[$attendance_value['staff_id']])){
		    	$staffs_id[$attendance_value['staff_id']] = array_merge($staffs_id[$attendance_value['staff_id']], $attendance_value);
		    }else{
		    	$staffs_id[$attendance_value['staff_id']] = $attendance_value;
		    }

		}

		//get imcome tax rebate from setting
		$income_tax_rebates = $this->get_income_tax_rebates();
		$ic_rebates =[];
		foreach ($income_tax_rebates as $rebates_key => $rebates_value) {
		    $ic_rebates[$rebates_value['code']] = $rebates_value['total'];
		}


		//get employees data
		$get_employees_data = $this->get_employees_data($payslip_month,'', $str_sql);
		foreach ($get_employees_data as $employee_key => $employee_value) {
			$employee_value['it_rebate_code'] = $employee_value['income_rebate_code'];
			$employee_value['income_tax_code'] = $employee_value['income_tax_rate'];
			$employee_value['bank_name'] = $employee_value['bank_name'];
			$employee_value['account_number'] = $employee_value['account_number'];
			$employee_value['epf_no'] = $employee_value['epf_no'];
			$employee_value['social_security_no'] = $employee_value['social_security_no'];


			if(isset($ic_rebates[$employee_value['income_rebate_code']])){
				$employee_value['it_rebate_value'] = $ic_rebates[$employee_value['income_rebate_code']];
			}

		    $employees_data[$employee_value['staff_id']] = $employee_value;

		    if(isset($staffs_id[$employee_value['staff_id']])){
		    	$staffs_id[$employee_value['staff_id']] = array_merge($staffs_id[$employee_value['staff_id']], $employee_value);
		    }else{
		    	$staffs_id[$employee_value['staff_id']] = $employee_value;
		    }

		}

		//get salary deduction
    	$deductions_data = $this->get_deductions_data($payslip_month, $str_sql);
    	foreach ($deductions_data as $deduction_key => $deduction_value) {
    		$deductions_value[$deduction_value['staff_id']] = $deduction_value;

    		if(isset($staffs_id[$deduction_value['staff_id']])){
		    	$staffs_id[$deduction_value['staff_id']] = array_merge($staffs_id[$deduction_value['staff_id']], $deduction_value);
		    }else{
		    	$staffs_id[$deduction_value['staff_id']] = $deduction_value;
		    }

    	}

    	//get commission
    	$commissions_data = $this->get_commissions_data($payslip_month, $str_sql);
    	foreach ($commissions_data as $commission_key => $commission_value) {
    	    $commissions_value[$commission_value['staff_id']] = $commission_value;

    		if(isset($staffs_id[$commission_value['staff_id']])){
		    	$staffs_id[$commission_value['staff_id']] = array_merge($staffs_id[$commission_value['staff_id']], $commission_value);
		    }else{
		    	$staffs_id[$commission_value['staff_id']] = $commission_value;
		    }
    	}

    	//get bonus kpi
    	$bonus_kpi_data = $this->get_bonus_kpi($payslip_month, $str_sql1);
    	foreach ($bonus_kpi_data as $bonus_kpi_key => $bonus_kpi_value) {
    	    $bonus_kpis_value[$bonus_kpi_value['staff_id']] = $bonus_kpi_value;

    		if(isset($staffs_id[$bonus_kpi_value['staff_id']])){
		    	$staffs_id[$bonus_kpi_value['staff_id']] = array_merge($staffs_id[$bonus_kpi_value['staff_id']], $bonus_kpi_value);
		    }else{
		    	$staffs_id[$bonus_kpi_value['staff_id']] = $bonus_kpi_value;
		    }
    	}


    	//get insurance data
    	$insurances_data = $this->get_insurances_data($payslip_month, $str_sql);
    	foreach ($insurances_data as $insurance_key => $insurance_value) {
    		$insurances_value[$insurance_value['staff_id']] = $insurance_value;

    		if(isset($staffs_id[$insurance_value['staff_id']])){
		    	$staffs_id[$insurance_value['staff_id']] = array_merge($staffs_id[$insurance_value['staff_id']], $insurance_value);
		    }else{
		    	$staffs_id[$insurance_value['staff_id']] = $insurance_value;
		    }

    	}

    	//get salary deduction from setting
    	$get_salary_deductions_list_setting = $this->get_salary_deductions_list();

    	$salary_deductions_list_setting=[];
    	foreach ($get_salary_deductions_list_setting as $sl_key =>  $sl_value) {
    	    	$salary_deductions_list_setting['deduction_'.$sl_value['id']] = $sl_value['basis'];
    	}

    	//get insurance data from setting
    	$get_insurance_list_setting = $this->get_insurance_list();

    	$insurance_list_setting=[];
    	foreach ($get_insurance_list_setting as $sl_key =>  $sl_value) {
    	    	$insurance_list_setting['st_insurance_'.$sl_value['id']] = $sl_value['basis'];
    	}

    	//get salary by task
    	$salary_by_tasks = $this->get_tasks_timer_by_month($payslip_month, $str_sql, $str_sql1, $hr_profile_status);
    	if(isset($salary_by_tasks_v2)){
    		$salary_by_tasks = $salary_by_tasks_v2;
    	}
    	foreach ($salary_by_tasks as $staff_id_key => $task_value) {

    		if(isset($staffs_id[$staff_id_key])){
		    	$staffs_id[$staff_id_key] = array_merge($staffs_id[$staff_id_key], $task_value);
		    }else{
		    	$staffs_id[$staff_id_key] = $task_value;
		    }
    	}

		$array_cell_data = $this->array_cell_data($payroll_templates);
		//array payroll column name, array payroll column key, array payroll formular
		$payroll_column_name = $array_cell_data['payroll_column_name'];
		$payroll_column_key =  $array_cell_data['payroll_column_key'];
		$payroll_formular = $array_cell_data['payroll_formular'];


		//get formular with related key
		$payroll_formular = array_slice($payroll_formular, 0, count($payroll_column_key));
		$payroll_column_name = array_slice($payroll_column_name, 0, count($payroll_column_key));


		$payroll_key_formular = array_combine($payroll_column_key, $payroll_formular);
		$payroll_column_key_name = array_combine($payroll_column_key, $payroll_column_name);

		$payroll_system_columns = payroll_system_columns();
		$payroll_system_columns_dont_format = payroll_system_columns_dont_format();

		//get header, row format
		$luckysheet_header_format = luckysheet_header_format();
		$luckysheet_row_format = luckysheet_row_format();

		$payslip_cell_data =[];
		$staff_row = 5;

		$row_value_temp = 160;
		$column_value_temp = 191;
		$visibledatarow = [40,80,120,160];
		$visibledatacolumn = [];
		$columnlen=[];
		$rowlen=[];
		$calcChain=[];


		// set company logo
		$payslip_cell_data[] = $this->general_cell_data(1, 4, get_option('companyname') , $t='g', $f ='', false, false, true);
		$payslip_cell_data[] = $this->general_cell_data(2, 4, _l('payroll_in_month').$data['payslip_month'] , $t='g', $f ='', false, true, false);

		if(count($staffs_id) > 0){
			foreach ($staffs_id as $staff_id => $staff_value ) {
				$col = 0;
				foreach ($payroll_key_formular as $payroll_key  => $payroll_formular) {
				//get gross pay key 
					if($payroll_key == 'gross_pay'){
						$gross_pay_index = $col;
					}

				//get taxable salary
					if($payroll_key == 'taxable_salary'){
						$taxable_salary_index = $col;
					}


				// write header
					if($staff_row == 5){

						$payslip_cell_data[] = $this->general_cell_data($staff_row-2, $col, $payroll_column_key_name[$payroll_key], $t='g', $f ='', true, false);

						$payslip_cell_data[] = $this->general_cell_data($staff_row-1, $col, $payroll_key, $t='g', $f ='', false, true);



						$column_value_temp = $column_value_temp + 191;
						$visibledatacolumn[] = $column_value_temp;
						$columnlen[] = 183;
						$rowlen[$staff_row-2] = 46;


					}


				// check if key in system column, st1: salary type of (CT1: like Probationary contracts) , al1: allowance type (CT1: like Probationary contracts), st2: salary type of (CT2: like formal contracts) , al2: allowance type (CT2: like formal contracts), 
				// earning1_: salary or allowance type of (CT1: like Probationary contracts)
				// earning2_: salary or allowance type of (CT2: like Probationary contracts)
				// deduction_: salary deduction

					if(in_array($payroll_key, $payroll_system_columns) || preg_match('/^st1_/', $payroll_key) || preg_match('/^al1_/', $payroll_key) ||preg_match('/^st2_/', $payroll_key) || preg_match('/^al2_/', $payroll_key) || preg_match('/^earning1_/', $payroll_key) || preg_match('/^earning2_/', $payroll_key) || preg_match('/^deduction_/', $payroll_key) || preg_match('/^st_insurance_/', $payroll_key)  ){

						if(preg_match('/^deduction_/', $payroll_key)){

							$value= isset($staff_value[$payroll_key]) ? $staff_value[$payroll_key] : 0 ;

							if(isset($salary_deductions_list_setting[$payroll_key])){
								if($salary_deductions_list_setting[$payroll_key] == "gross"){
									if(isset($gross_pay_index)){
			    				//6 is formular is row 6
										$payroll_formular = "=".$number_to_anphabe[$gross_pay_index]."6*".$value."/100";
									}
								}elseif(preg_match('/^st_/', $salary_deductions_list_setting[$payroll_key]) || preg_match('/^al_/', $salary_deductions_list_setting[$payroll_key]) || preg_match('/^earning_/', $salary_deductions_list_setting[$payroll_key])){

									$salary_deductions_list_setting[$payroll_key];
									$deduction_explode = new_explode("_", $salary_deductions_list_setting[$payroll_key]);
									$deduction_prefix = $deduction_explode[0];
									$deduction_salary_id = $deduction_explode[1];

									$probationary_value = 0; 
									$formal_value = 0;
									$average_number = 0;

									if(isset($staff_value[$deduction_prefix.'1_'.$deduction_salary_id])){
										$probationary_value = $staff_value[$deduction_prefix.'1_'.$deduction_salary_id]; 

										if((float)$staff_value[$deduction_prefix.'1_'.$deduction_salary_id] > 0){

											$average_number ++;
										}

									}

									if(isset($staff_value[$deduction_prefix.'2_'.$deduction_salary_id])){
										$formal_value = $staff_value[$deduction_prefix.'2_'.$deduction_salary_id]; 

										if((float)$staff_value[$deduction_prefix.'2_'.$deduction_salary_id] > 0){
											$average_number ++;

										}

									}

									if($average_number > 0){

										$payroll_formular = "=".((float)$probationary_value + (float)$formal_value)/$average_number*($value/100);
									}else{

										$payroll_formular = "=0";

									}


								}
							}

						}elseif(preg_match('/^st_insurance_/', $payroll_key)){
							$value= isset($staff_value[$payroll_key]) ? $staff_value[$payroll_key] : 0 ;

							if(isset($insurance_list_setting[$payroll_key])){
								if($insurance_list_setting[$payroll_key] == "gross"){
									if(isset($gross_pay_index)){
			    				//6 is formular is row 6
										$payroll_formular = "=".$number_to_anphabe[$gross_pay_index]."6*".$value."/100";
									}
								}
							}

						}elseif($payroll_key == 'income_tax_paye'){

							if(isset($taxable_salary_index)){
								if(isset($staff_value['income_tax_code']) && $staff_value['income_tax_code'] == 'A'){
									$taxable_salary_formular = new_str_replace('AX', $number_to_anphabe[$taxable_salary_index]."6", $render_income_tax_formular);
		    				//6 is formular is row 6
		    						if($taxable_salary_formular != ''){
		    							$payroll_formular = "=".$taxable_salary_formular;
		    						}else{

		    							$payroll_formular =0;
		    							$value=0;
		    						}
								}else{
									$payroll_formular =0;
									$value=0;
								}
							}

						}else{
							$value= isset($staff_value[$payroll_key]) ? $staff_value[$payroll_key] : 0 ;
						}

						$t='g';
					}else{
						$value='';
						$t='n';
					}

					if($payroll_formular != '0'){
						if(isset($taxable_salary_index) &&  $payroll_key == 'income_tax_paye'){
							$f = new_str_replace($number_to_anphabe[$taxable_salary_index].'6', $number_to_anphabe[$taxable_salary_index].$staff_row+1, $payroll_formular);
						}else{
							$f = new_str_replace('6', $staff_row+1, $payroll_formular);
						}


			    	//calcChain: Formula chain, used when the cell linked by the formula is changed, all formulas referencing this cell will be refreshed.
						array_push($calcChain, [
							"r" => $staff_row,
							"c" => $col,
							"index" => 0,
							"color" => "b",
							"parent" => null,
							"chidren" => new stdClass(),
							"times" => 1
						]);

					}else{
						$f = '';
					}

			    // start row 5

					if(!in_array($payroll_key, $payroll_system_columns_dont_format)){
						$payslip_cell_data[] = $this->general_cell_data($staff_row, $col, $value, $t, $f, false , true,'', true);
					}else{
						$payslip_cell_data[] = $this->general_cell_data($staff_row, $col, $value, $t, $f, false , true,'', false);
					}

					$col++;
				}

				$row_value_temp = $row_value_temp + 40;
				$visibledatarow[] = $row_value_temp;
				$rowlen[$staff_row] = 25;

				$staff_row++;

			}
		}else{
			$payslip_cell_data[] = $this->general_cell_data(5, 4, _l('no_eligible_employee_was_found_for_this_payslip_template'), $t='g', $f ='', false, false, true);

		}


		$payslip_template_data['name']      = $data['payslip_name'];
		//concat payslip template data with data fixed
		$payslip_template_data = array_merge($payslip_template_data, $this->payslip_template_data_fixed($visibledatarow, $visibledatacolumn, $columnlen, $rowlen));
		//column A to Z
		$min_index_column = 25 < count($payroll_key_formular) ? count($payroll_key_formular) : 25;
		$payslip_template_data['column']    = $min_index_column;

		$payslip_template_data['celldata']    = $payslip_cell_data;
		$payslip_template_data['data']    = [];

		
		$payslip_template_data['calcChain']    = $calcChain;
		$payslip_template_data['defaultRowHeight']    = 19;
		$payslip_template_data['defaultColWidth']    = 73;

		$payslip_data[] = $payslip_template_data;

        $get_currency_rate = $this->get_currency_rate_infor($data['to_currency_id']);
        $from_currency_name = $get_currency_rate['base_currency_name'];
        $from_currency_rate = $get_currency_rate['base_currency_rate'];
        $to_currency_name = $get_currency_rate['to_currency_name'];
        $to_currency_rate = $get_currency_rate['currency_rate'];

		$this->db->insert(db_prefix().'hrp_payslips', [
			'payslip_name' => $data['payslip_name'],
			'payslip_month' => $payslip_month,
			'payslip_template_id' => $data['payslip_template_id'],
			'staff_id_created' => get_staff_user_id(),
			'date_created' => date('Y-m-d H:i:s'),
			'payslip_range' => $payslip_range,
			'pdf_template_id' => $data['pdf_template_id'],
			'from_currency_name' => $from_currency_name,
			'from_currency_rate' => $from_currency_rate,
			'to_currency_name' => $to_currency_name,
			'to_currency_rate' => $to_currency_rate,
			
		]);

		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			//insert payslip file
			$this->add_payslip_file($insert_id, json_encode($payslip_data), $data['payslip_name']);
			return $insert_id;
		}
		return false;

	}

	/**
	 * add payslip file
	 * @param [type] $data 
	 */
	public function add_payslip_file($insert_id, $data, $payslip_name)
	{	

		$path = HR_PAYROLL_PAYSLIP_FOLDER . $insert_id . '-'.$payslip_name.'.txt';
		$realpath_data = $insert_id . '-'.$payslip_name.'.txt';
		hrp_file_force_contents($path, $data);

		$this->db->where('id', $insert_id);
		$this->db->update(db_prefix() . 'hrp_payslips', ['file_name' => $realpath_data]);

		if ($this->db->affected_rows() > 0) {
			return true;
		}else{
			return false;
		}
	}


	/**
	 * update payslip
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_payslip($data, $id)
	{ 
		$affected_rows = 0;
		$payslip = $this->hr_payroll_model->get_hrp_payslip($id);
		unlink(HR_PAYROLL_PAYSLIP_FILE.$payslip->file_name);
		if(isset($data['image_flag'])){
			if($data['image_flag'] == "true"){
				$data['payslip_data'] = new_str_replace('[removed]', 'data:image/png;base64,', $data['payslip_data']); 
				$data['payslip_data'] = new_str_replace('imga$imga', '"', $data['payslip_data']); 
				$data['payslip_data'] = new_str_replace('""', '"', $data['payslip_data']); 
			}
		}

		$data['payslip_data'] = hrp_payslip_replace_string($data['payslip_data']);
		$payslip_data = $data['payslip_data'];
		$payslip_name = $data['name'];

		$data['payslip_name'] = $data['name']; 
		unset($data['name']);
		unset($data['image_flag']);
		unset($data['payslip_data']);

		$this->db->where('id', $id);
		$this->db->update(db_prefix().'hrp_payslips', $data);
		
		$add_payslip_file = $this->add_payslip_file($id, $payslip_data, $payslip_name);

		return true;
	}

	/**
	 * payslip_close
	 * @param  [type] $data  
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function payslip_close($data)
	{	
		$start = microtime(true);

		$affectedRows = 0;
		$payroll_column_key=[];
		$payroll_column_value=[];

		$payroll_system_columns = payroll_system_columns();
		$hrp_payslip = $this->get_hrp_payslip($data['id']);
		if($hrp_payslip){
			$month = $hrp_payslip->payslip_month;
		}else{
			$month = date('Y-m-d');
		}

		$payslip_data_decode = json_decode($data['payslip_data']);
		if(isset($payslip_data_decode[0])){
			if(isset($payslip_data_decode[0]->celldata)){
				$payslip_data = $payslip_data_decode[0]->celldata;
			}
		}


		if(isset($payslip_data)){
			foreach ($payslip_data as $key => $value) {
				//column key from row 4
				if($value->r == 4){
					$payroll_column_key[] = isset($value->v->m) ? $value->v->m : '';
				}

				//column value
				if($value->r > 4){
					$payroll_column_value[$value->r][] = isset($value->v->m) ? hrp_reformat_currency($value->v->m) : 0;
				}
			}
		}

		$payslip_detail=[];
		$income_taxs=[];
		$staff_ids=[];
		//add key: payslip_id, month to payroll column key
		array_unshift($payroll_column_key, "payslip_id", "month"); 
		if(count($payroll_column_value) > 0){
			foreach ($payroll_column_value as $key => $value) {
			    array_unshift($value, $data['id'], $month);

			    if(count($payroll_column_key) != count($value)){
			    	return false;
			    }
			    $check_array_combine = array_combine($payroll_column_key, $value);

			    $payslip_detail[] = array_combine($payroll_column_key, $value);
			}
		}
		foreach ($payslip_detail as $key => $value) {
			$payslip_json_data=[];
			foreach ($value as $payroll_key => $payroll_value) {
				if($payroll_key == 'payment_run_date'){

					$payslip_detail[$key][$payroll_key] = to_sql_date($payroll_value);
				}

			    if(!in_array($payroll_key, $payroll_system_columns) && $payroll_key != 'payslip_id' && $payroll_key != 'month' ||( $payroll_key == 'bank_name' || $payroll_key == 'account_number' || $payroll_key == 'epf_no' || $payroll_key == 'social_security_no') ){
			    	$payslip_json_data[$payroll_key] = $payroll_value;
			    	unset($payslip_detail[$key][$payroll_key]);
			    }
			}
			if(isset($payslip_detail[$key]['bank_name'])){
				unset($payslip_detail[$key]['bank_name']);
			}
			if(isset($payslip_detail[$key]['account_number'])){
				unset($payslip_detail[$key]['account_number']);
			}
			if(isset($payslip_detail[$key]['epf_no'])){
				unset($payslip_detail[$key]['epf_no']);
			}
			if(isset($payslip_detail[$key]['social_security_no'])){
				unset($payslip_detail[$key]['social_security_no']);
			}
			
			$payslip_detail[$key]['json_data'] =  json_encode($payslip_json_data);

			$income_taxs[$key]['staff_id'] = isset($value['staff_id']) ? $value['staff_id'] : 0;
			$income_taxs[$key]['month'] = isset($value['month']) ? $value['month'] : null;
			$income_taxs[$key]['income_tax'] = isset($value['income_tax_paye']) ? $value['income_tax_paye'] : 0;
			$income_taxs[$key]['payslip_id'] = $data['id'];
			$staff_ids[] = $value['staff_id'];
		}
					
		if(count($payslip_detail) != 0){
			//udpate payslip status
			$update_result = $this->update_payslip_status($data['id'], 'payslip_closing');
			if($update_result == true){
				$affectedRows++;
			}


			//delete mass paylip detail before update
			$this->db->where('payslip_id', $data['id']);
			$this->db->where('month', $month);
			$this->db->delete(db_prefix().'hrp_payslip_details');

			//delete mass hrp_income_taxs before update
			$this->db->where('staff_id IN ('.implode(",",$staff_ids) .') ');
			$this->db->where('month', $month);
			$this->db->delete(db_prefix().'hrp_income_taxs');

			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_payslip_details', $payslip_detail);
			if($affected_rows > 0){
				$affectedRows++;
			}

			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_income_taxs', $income_taxs);
			if($affected_rows > 0){
				$affectedRows++;
			}
		}
		if ($affectedRows > 0) {
			return true;
		}

		return false;

	}


	/**
	 * update payslip status
	 * @param  [type] $id     
	 * @param  [type] $status 
	 * @return [type]         
	 */
	public function update_payslip_status($id, $status)
	{	
	    $this->db->where('id', $id);
	    $this->db->update(db_prefix().'hrp_payslips', ['payslip_status' => $status]);
	    if($this->db->affected_rows() > 0){
	    	return true;
	    }
	    return false;
	}


	/**
	 * render personal income tax
	 * @param  [type] $PARAMETERS 
	 * @return [type]             
	 */
	public function render_personal_income_tax($PARAMETERS)
	{
	    
	}

	/**
	 * get payslip detail
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function get_payslip_detail($id = false)
	{
	    if (is_numeric($id)) {
            $this->db->where('id', $id);

            return $this->db->get(db_prefix() . 'hrp_payslip_details')->row();
        }

        if ($id == false) {
        return $this->db->query('select * from '.db_prefix().'hrp_payslip_details')->result_array();
        }
	}

	/**
	 * get income summary report
	 * @param  [type] $sql_where 
	 * @return [type]            
	 */
	public function get_income_summary_report($sql_where='')
	{
		$this->db->select(db_prefix().'hrp_payslip_details.staff_id, pay_slip_number, employee_number, employee_name,  month, net_pay, ' . db_prefix() . 'hrp_payslips.payslip_status,'.db_prefix().'hrp_payslip_details.employee_number');
		$this->db->join(db_prefix() . 'hrp_payslips', db_prefix() . 'hrp_payslip_details.payslip_id = ' . db_prefix() . 'hrp_payslips.id', 'left');
		if($sql_where != ''){
			$this->db->where($sql_where);
		}
		$this->db->order_by('staff_id', 'desc');
		$payslip_details = $this->db->get(db_prefix() . 'hrp_payslip_details')->result_array(); 

		$staff_income=[];
		foreach ($payslip_details as $key => $value) {
			$get_month = date("m", strtotime($value['month']));

			$staff_income[$value['staff_id']]['pay_slip_number'] = $value['pay_slip_number'];
			$staff_income[$value['staff_id']]['employee_name'] = $value['employee_name'];

			if(isset($staff_income[$value['staff_id']][$get_month])){
				$staff_income[$value['staff_id']][$get_month] += $value['net_pay'];
			}else{
				$staff_income[$value['staff_id']][$get_month] = $value['net_pay'];
			}

			if(isset($staff_income[$value['staff_id']]['average_income'])){
				$staff_income[$value['staff_id']]['average_income'] += (float)$value['net_pay'];
			}else{
				$staff_income[$value['staff_id']]['average_income'] = (float)$value['net_pay'];
			}
		}

		return $staff_income;

	}


	/**
	 * get insurance summary report
	 * @param  string $sql_where 
	 * @return [type]            
	 */
	public function get_insurance_summary_report($sql_where='')
	{
		$this->db->select(db_prefix().'hrp_payslip_details.staff_id, sum(total_insurance) as total_insurance');
		$this->db->join(db_prefix() . 'hrp_payslips', db_prefix() . 'hrp_payslip_details.payslip_id = ' . db_prefix() . 'hrp_payslips.id', 'left');
		if($sql_where != ''){
			$this->db->where($sql_where);
		}
		$this->db->group_by(db_prefix().'hrp_payslip_details.staff_id');
		$this->db->order_by(db_prefix().'hrp_payslip_details.staff_id', 'desc');
		$payslip_details = $this->db->get(db_prefix() . 'hrp_payslip_details')->result_array(); 

		$staff_insurance=[];
		foreach ($payslip_details as $key => $value) {
			$staff_insurance[$value['staff_id']] = $value['total_insurance'];
		}

		return $staff_insurance;

	}


	/**
	 * get staff in deparment
	 * @param  [type] $department_id 
	 * @return [type]                
	 */
	public function get_staff_in_deparment($department_id)
	{
		$data = [];
		$sql = 'select 
		departmentid 
		from    (select * from '.db_prefix().'departments
		order by '.db_prefix().'departments.parent_id, '.db_prefix().'departments.departmentid) departments_sorted,
		(select @pv := '.$department_id.') initialisation
		where   find_in_set(parent_id, @pv)
		and     length(@pv := concat(@pv, ",", departmentid)) OR departmentid = '.$department_id.'';
		$result_arr = $this->db->query($sql)->result_array();
		foreach ($result_arr as $key => $value) {
			$data[$key] = $value['departmentid'];
		}

		if (count($data) > 0) {

			$sql_where = db_prefix().'staff.staffid IN (SELECT staffid FROM '.db_prefix().'staff_departments WHERE departmentid IN (' . implode(', ', $data) . '))';
			$staffs = $this->get_staff_timekeeping_applicable_object($sql_where);

			$staff_id=[];
			foreach ($staffs as $key => $value) {
				$staff_id[] = $value['staffid'];
			}

			return $staff_id;
		}
		return [];
	}


	/**
	 * payslip chart
	 * @return [type] 
	 */
	public function payslip_chart($filter_by_year = '', $staff_id='')
	{
		$months_report = $this->input->post('months_report');
		$custom_date_select = '';

		if($filter_by_year != ''){
			$filter_by_year = $filter_by_year;
		}else{
			$filter_by_year = date('Y');
		}

		if($staff_id != ''){
			$staff_id = $staff_id;
		}else{
			$staff_id = get_staff_user_id();
		}
		

		$this->db->select(db_prefix().'hrp_payslip_details.staff_id, pay_slip_number, employee_number, employee_name,  month, net_pay, ' . db_prefix() . 'hrp_payslips.payslip_status, total_insurance, income_tax_paye, total_deductions');
		$this->db->join(db_prefix() . 'hrp_payslips', db_prefix() . 'hrp_payslip_details.payslip_id = ' . db_prefix() . 'hrp_payslips.id', 'left');
		$this->db->where($filter_by_year.' AND '.db_prefix().'hrp_payslip_details.staff_id = '.$staff_id.' AND payslip_status = "payslip_closing"');
		$this->db->order_by('staff_id', 'desc');
		$payslip_details = $this->db->get(db_prefix() . 'hrp_payslip_details')->result_array(); 

		$staff_income=[];
		foreach ($payslip_details as $key => $value) {
			$get_month = date("m", strtotime($value['month']));

			$staff_income[$value['staff_id']]['pay_slip_number'] = $value['pay_slip_number'];
			$staff_income[$value['staff_id']]['employee_name'] = $value['employee_name'];
			

			if(isset($staff_income[$value['staff_id']][$get_month]['total_insurance'])){
				$staff_income[$value['staff_id']][$get_month]['total_insurance'] += (float)$value['total_insurance'];
			}else{
				$staff_income[$value['staff_id']][$get_month]['total_insurance'] = (float)$value['total_insurance'];
			}

			if(isset($staff_income[$value['staff_id']][$get_month]['income_tax_paye'])){
				$staff_income[$value['staff_id']][$get_month]['income_tax_paye'] += (float)$value['income_tax_paye'];
			}else{
				$staff_income[$value['staff_id']][$get_month]['income_tax_paye'] = (float)$value['income_tax_paye'];
			}

			if(isset($staff_income[$value['staff_id']][$get_month]['total_deductions'])){
				$staff_income[$value['staff_id']][$get_month]['total_deductions'] += (float)$value['total_deductions'];
			}else{
				$staff_income[$value['staff_id']][$get_month]['total_deductions'] = (float)$value['total_deductions'];
			}

			if(isset($staff_income[$value['staff_id']][$get_month]['net_pay'])){
				$staff_income[$value['staff_id']][$get_month]['net_pay'] += (float)$value['net_pay'];
			}else{
				$staff_income[$value['staff_id']][$get_month]['net_pay'] = (float)$value['net_pay'];
			}
		}

		for($_month = 1 ; $_month <= 12; $_month++){
			$month_t = date('m',mktime(0, 0, 0, $_month, 04, 2016));

			if($_month == 5){
				$chart['categories'][] = _l('month_05');
			}else{
				$chart['categories'][] = _l('month_'.$_month);
			}

			if(isset($staff_income[$staff_id][$month_t])){
				$chart['hr_staff_insurance'][] = isset($staff_income[$staff_id][$month_t]['total_insurance']) ? $staff_income[$staff_id][$month_t]['total_insurance'] : 0;
				$chart['hr_staff_income_tax'][] = isset($staff_income[$staff_id][$month_t]['income_tax_paye']) ? $staff_income[$staff_id][$month_t]['income_tax_paye'] : 0;
				$chart['hr_staff_deduction'][] = isset($staff_income[$staff_id][$month_t]['total_deductions']) ? $staff_income[$staff_id][$month_t]['total_deductions'] : 0;
				$chart['hr_staff_net_pay'][] = isset($staff_income[$staff_id][$month_t]['net_pay']) ? $staff_income[$staff_id][$month_t]['net_pay'] : 0;

			}else{
				$chart['hr_staff_insurance'][] = 0;
				$chart['hr_staff_income_tax'][] = 0;
				$chart['hr_staff_deduction'][] = 0;
				$chart['hr_staff_net_pay'][] = 0;
			}

		}

		return $chart;
	}	


	/**
	 * render income tax formular
	 * @param  [type] $taxable_salary 
	 * @return [type]                 
	 */
	public function render_income_tax_formular($taxable_salary)
	{	
		$formular='';
		$income_tax_formular='';
		$formular_close='';
	    //get icome tax rate
		$income_tax_rate = $this->get_income_tax_rate();

		foreach ($income_tax_rate as $key => $value) {
			$formular_close .=')';

			if(new_strlen($income_tax_formular) == 0){
				$income_tax_formular .='IF('.$taxable_salary.'<='.$value['tax_bracket_value_to'].',(('.$taxable_salary.'-'.$value['tax_bracket_value_from'].')*'.$value['tax_rate'].'/100)';
			}elseif($key+1 != count($income_tax_rate)){
				$income_tax_formular .=',IF('.$taxable_salary.'<='.$value['tax_bracket_value_to'].',(('.$taxable_salary.'-'.$value['tax_bracket_value_from'].')*'.$value['tax_rate'].'/100)+'.$formular.'';
			}else{
				$income_tax_formular .=',IF('.$taxable_salary.'>='.$value['tax_bracket_value_from'].',(('.$taxable_salary.'-'.$value['tax_bracket_value_from'].')*'.$value['tax_rate'].'/100)+'.$formular.' , '.$formular.$formular_close;
			}


			if($value['tax_bracket_value_to'] != 0 ){

				if(new_strlen($formular) == 0){
					$formular .= '(('.$value['tax_bracket_value_to'].'-'.$value['tax_bracket_value_from'].')*'.$value['tax_rate'].'/100)';
				}else{
					$formular .= '+'.'(('.$value['tax_bracket_value_to'].'-'.$value['tax_bracket_value_from'].')*'.$value['tax_rate'].'/100)';
				}

			}


		}


		return $income_tax_formular;
		
	}

	/**
	 * get department payslip chart
	 * @param  string $month 
	 * @return [type]        
	 */
	public function get_department_payslip_chart($from_date, $to_date)
	{	
		$this->db->select();
		$this->db->from(db_prefix() . 'staff_departments');
		$this->db->join(db_prefix() . 'departments', db_prefix() . 'staff_departments.departmentid = ' . db_prefix() . 'departments.departmentid', 'left');
		$staff_departments = $this->db->get()->result_array();

	    //select payslip detail by month
		$this->db->where('month >= ', $from_date);
		$this->db->where('month <= ', $to_date);
		$this->db->order_by('staff_id', 'asc');
		$payslip_details = $this->db->get(db_prefix().'hrp_payslip_details')->result_array();

		$staff_payslip=[];
		$data_result=[];
		foreach ($payslip_details as $key => $payslip) {
			if(isset($staff_payslip[$payslip['staff_id']])){
				$staff_payslip[$payslip['staff_id']]['gross_pay'] 			+= $payslip['gross_pay'];
				$staff_payslip[$payslip['staff_id']]['total_insurance'] 	+= $payslip['total_insurance'];
				$staff_payslip[$payslip['staff_id']]['income_tax_paye'] 	+= $payslip['income_tax_paye'];
				$staff_payslip[$payslip['staff_id']]['total_deductions'] 	+= $payslip['total_deductions'];
				$staff_payslip[$payslip['staff_id']]['commission_amount'] 	+= $payslip['commission_amount'];
				$staff_payslip[$payslip['staff_id']]['bonus_kpi'] 			+= $payslip['bonus_kpi'];
				$staff_payslip[$payslip['staff_id']]['net_pay'] 			+= $payslip['net_pay'];
				$staff_payslip[$payslip['staff_id']]['total_cost'] 			+= $payslip['total_cost'];
			}else{
				$staff_payslip[$payslip['staff_id']]['gross_pay'] 			= $payslip['gross_pay'];
				$staff_payslip[$payslip['staff_id']]['total_insurance'] 	= $payslip['total_insurance'];
				$staff_payslip[$payslip['staff_id']]['income_tax_paye'] 	= $payslip['income_tax_paye'];
				$staff_payslip[$payslip['staff_id']]['total_deductions'] 	= $payslip['total_deductions'];
				$staff_payslip[$payslip['staff_id']]['commission_amount'] 	= $payslip['commission_amount'];
				$staff_payslip[$payslip['staff_id']]['bonus_kpi'] 			= $payslip['bonus_kpi'];
				$staff_payslip[$payslip['staff_id']]['net_pay'] 			= $payslip['net_pay'];
				$staff_payslip[$payslip['staff_id']]['total_cost'] 			= $payslip['total_cost'];
			}
		}

		$department_name=[];

		foreach ($staff_departments as $key => $staff_department) {
			if(isset($staff_payslip[$staff_department['staffid']])){

				if(isset($data_result[$staff_department['departmentid']])){

					$data_result[$staff_department['departmentid']]['gross_pay'] += $staff_payslip[$staff_department['staffid']]['gross_pay'];
					$data_result[$staff_department['departmentid']]['total_insurance'] += $staff_payslip[$staff_department['staffid']]['total_insurance'];
					$data_result[$staff_department['departmentid']]['income_tax_paye'] += $staff_payslip[$staff_department['staffid']]['income_tax_paye'];
					$data_result[$staff_department['departmentid']]['total_deductions'] += $staff_payslip[$staff_department['staffid']]['total_deductions'];
					$data_result[$staff_department['departmentid']]['commission_amount'] += $staff_payslip[$staff_department['staffid']]['commission_amount'];
					$data_result[$staff_department['departmentid']]['bonus_kpi'] += $staff_payslip[$staff_department['staffid']]['bonus_kpi'];
					$data_result[$staff_department['departmentid']]['net_pay'] += $staff_payslip[$staff_department['staffid']]['net_pay'];
					$data_result[$staff_department['departmentid']]['total_cost'] += $staff_payslip[$staff_department['staffid']]['total_cost'];
				}else{
					if(!in_array($staff_department['name'], $department_name)){
						$department_name[] =  $staff_department['name'];
					}
					$data_result[$staff_department['departmentid']]['gross_pay'] = $staff_payslip[$staff_department['staffid']]['gross_pay'];
					$data_result[$staff_department['departmentid']]['total_insurance'] = $staff_payslip[$staff_department['staffid']]['total_insurance'];
					$data_result[$staff_department['departmentid']]['income_tax_paye'] = $staff_payslip[$staff_department['staffid']]['income_tax_paye'];
					$data_result[$staff_department['departmentid']]['total_deductions'] = $staff_payslip[$staff_department['staffid']]['total_deductions'];
					$data_result[$staff_department['departmentid']]['commission_amount'] = $staff_payslip[$staff_department['staffid']]['commission_amount'];
					$data_result[$staff_department['departmentid']]['bonus_kpi'] = $staff_payslip[$staff_department['staffid']]['bonus_kpi'];
					$data_result[$staff_department['departmentid']]['net_pay'] = $staff_payslip[$staff_department['staffid']]['net_pay'];
					$data_result[$staff_department['departmentid']]['total_cost'] = $staff_payslip[$staff_department['staffid']]['total_cost'];
				}
			}else{
				if(!in_array($staff_department['name'], $department_name)){
					$department_name[] =  $staff_department['name'];
				}

				if(!isset($data_result[$staff_department['departmentid']])){

					$data_result[$staff_department['departmentid']]['gross_pay'] = 0;
					$data_result[$staff_department['departmentid']]['total_insurance'] = 0;
					$data_result[$staff_department['departmentid']]['income_tax_paye'] = 0;
					$data_result[$staff_department['departmentid']]['total_deductions'] = 0;
					$data_result[$staff_department['departmentid']]['commission_amount'] = 0;
					$data_result[$staff_department['departmentid']]['bonus_kpi'] = 0;
					$data_result[$staff_department['departmentid']]['net_pay'] = 0;
					$data_result[$staff_department['departmentid']]['total_cost'] = 0;
				}
			}
		}


		$payslip_columns=[];
		$payslip_columns[] = 'gross_pay';
		$payslip_columns[] = 'total_insurance';
		$payslip_columns[] = 'income_tax_paye';
		$payslip_columns[] = 'total_deductions';
		$payslip_columns[] = 'commission_amount';
		$payslip_columns[] = 'bonus_kpi';
		$payslip_columns[] = 'net_pay';
		$payslip_columns[] = 'total_cost';
		$list_result=[];

		foreach ($payslip_columns as $payslip_column) {
			$list_data_count = [];
			foreach ($data_result as $department) {

				$count = 0;
				if(isset($department[$payslip_column])){
					$count = round((float)$department[$payslip_column], 2);
				}
				$list_data_count[] = $count;
			}

			switch ($payslip_column) {
				case 'gross_pay':
					$payslip_column_name = _l('ps_gross_pay');
					break;
				case 'total_insurance':
					$payslip_column_name = _l('ps_total_insurance');
					break;
				case 'income_tax_paye':
					$payslip_column_name = _l('ps_income_tax_paye');
					break;
				case 'total_deductions':
					$payslip_column_name = _l('ps_total_deductions');
					break;
				case 'commission_amount':
					$payslip_column_name = _l('ps_commission_amount');
					break;
				case 'bonus_kpi':
					$payslip_column_name = _l('ps_bonus_kpi');
					break;
				case 'net_pay':
					$payslip_column_name = _l('ps_net_pay');
					break;
				case 'total_cost':
					$payslip_column_name = _l('ps_total_cost');
					break;
				
				
			}
			array_push($list_result,array('stack' => $payslip_column_name,'data' => $list_data_count));
		}

		$data=[];
		$data['list_result'] = $list_result;
		$data['department_name'] = $department_name;
		
		return $data;
	}


	/**
	 * array cell data
	 * @param  [type] $payroll_templates 
	 * @return [type]                    
	 */
	public function array_cell_data($payroll_templates)
	{
		$payroll_column_name =[];
		$payroll_column_key =[];
		$payroll_formular =[];

		if($payroll_templates){
			$payroll_cell_data = json_decode($payroll_templates->cell_data);

			foreach ($payroll_cell_data as $key => $value) {

				//column Name from row 3
				if($value->r == 3){
					$payroll_column_name[] = $value->v->m;
				}

				//column key from row 4
				if($value->r == 4){
					$payroll_column_key[] = $value->v->m;
				}

				//column formular from row 5
				if($value->r == 5){
					if(isset($value->v->f) ){
						//if column is formula
						$payroll_formular[] = $value->v->f;
					}else{
						$payroll_formular[] = 0;
					}
				}
				
			}
		}  

		$results=[];
		$results['payroll_column_name']	=	$payroll_column_name;
		$results['payroll_column_key']	=	$payroll_column_key;
		$results['payroll_formular']	=	$payroll_formular;

		return $results;
	}

	/**
	 * payslip template checked
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function payslip_template_checked($data)
	{
		//staff has payslip tempalte
		$staff_has_template=[];
		$str_staff_has_template='';

		if(isset($data['department_ids'])){
			$department_id = implode(',', $data['department_ids']);
		}else{
			$department_id ='';
		}

		if(isset($data['role_ids'])){
			$role_employees = implode(',', $data['role_ids']);
		}else{
			$role_employees = '';
		}

		if(isset($data['staff_ids'])){
			$staff_employees = implode(',', $data['staff_ids']);
		}else{
			$staff_employees = '';
		}

		if(isset($data['expect_staff_ids'])){
			$except_staff = $data['expect_staff_ids'];
		}else{
			$except_staff = [];
		}

		$staff_ids = $this->payslip_template_get_staffid($department_id, $role_employees, $staff_employees);
	
		if($staff_ids != false){

			$array_staff_ids = new_explode(",", $staff_ids);

			foreach ($array_staff_ids as $key => $value) {
				if(in_array($value, $except_staff)){
					unset($array_staff_ids[$key]);
				}
			}

			if(count($array_staff_ids) > 0){

				if(isset($data['id']) && is_numeric($data['id'])){
					//update payslip template
					
					$this->db->where('id != ', $data['id']);
					$payslip_templates = $this->db->get(db_prefix() . 'hrp_payslip_templates')->result_array();
				}else{
					//add payslip template

					$payslip_templates = $this->get_hrp_payslip_templates();
				}

				for ($i=0; $i < count($payslip_templates); $i++) { 
					if($payslip_templates[$i]['staff_employees'] != '' || $payslip_templates[$i]['staff_employees'] != null){
						$array_staffs = new_explode(",", $payslip_templates[$i]['staff_employees']);
					}else{
						$get_staffid_by_payslip_template = $this->payslip_template_get_staffid($payslip_templates[$i]['department_id'], $payslip_templates[$i]['role_employees'], $payslip_templates[$i]['staff_employees'], $payslip_templates[$i]['except_staff']);

						$array_staffs=[];
						if($get_staffid_by_payslip_template != false){
							$array_staffs = new_explode(",", $get_staffid_by_payslip_template);
						}
					}

					foreach ($array_staffs as $staff_key => $staff_value) {
						if(in_array($staff_value, $array_staff_ids) && !in_array($staff_value, $except_staff) && !in_array($staff_value, $staff_has_template)){
							$staff_has_template[] = $staff_value;

						}
					}

				}

				//TODO
				if(count($staff_has_template) > 0){
					$staff_str_query = ' staffid IN ('.implode(",",$staff_has_template) .') ';

					$array_staff_has_template = $this->get_staff_timekeeping_applicable_object($staff_str_query);

					foreach ($array_staff_has_template as $key => $value) {
						if(new_strlen($str_staff_has_template) > 0){
							$str_staff_has_template .= ', '. $value['firstname'].' '.$value['lastname'];

						}else{
							$str_staff_has_template .=  $value['firstname'].' '.$value['lastname'];
						}
					}					

					$str_staff_has_template .= _l('falls_within_other_the_payslip_template');
					return $str_staff_has_template;
				}
				return true;

			}

			return true;

		}
		return true;

	}


	/**
	 * payslip checked
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function payslip_checked($payslip_month, $payslip_template_id, $closing= false)
	{	
		if($closing == false){
			$payslip_month = date('Y-m-d', strtotime($payslip_month.'-01'));
		}
		$this->db->where('payslip_month', $payslip_month);
		$this->db->where('payslip_template_id', $payslip_template_id);
		$this->db->where('payslip_status', 'payslip_closing');
		$payslip_closing = $this->db->get(db_prefix().'hrp_payslips')->result_array();

		if(count($payslip_closing) > 0){
			return false;
		}
		return true;
	}


	/**
	 * payslip download
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function payslip_download($data)
	{	

		$affectedRows = 0;
		$payroll_header=[];
		$payroll_column_key=[];
		$payroll_column_value=[];

		$payroll_system_columns = payroll_system_columns();
		$hrp_payslip = $this->get_hrp_payslip($data['id']);
		if($hrp_payslip){
			$month = $hrp_payslip->payslip_month;
		}else{
			$month = date('Y-m-d');
		}

		$payslip_data_decode = json_decode($data['payslip_data']);
		if(isset($payslip_data_decode[0])){
			if(isset($payslip_data_decode[0]->celldata)){
				$payslip_data = $payslip_data_decode[0]->celldata;
			}
		}


		if(isset($payslip_data)){
			foreach ($payslip_data as $key => $value) {
				//column key from row 4
				if($value->r == 3){
					$payroll_header[] = isset($value->v->m) ? $value->v->m : '';
				}

				//column key from row 4
				if($value->r == 4){
					$payroll_column_key[] = isset($value->v->m) ? $value->v->m : '';
				}

				//column value
				if($value->r > 4){
					$payroll_column_value[$value->r][] = isset($value->v->m) ? hrp_reformat_currency($value->v->m) : 0;
				}
			}
		}

		$payslip_detail=[];
		//add key: payslip_id, month to payroll column key
		if(count($payroll_column_value) > 0){
			foreach ($payroll_column_value as $key => $value) {

			    if(count($payroll_column_key) != count($value)){
			    	return false;
			    }
			    $check_array_combine = array_combine($payroll_column_key, $value);

			    $payslip_detail[] = array_combine($payroll_column_key, $value);
			}
		}

		$result=[];
		$result['payroll_header'] = $payroll_header;
		$result['payroll_column_key'] = $payroll_column_key;
		$result['payslip_detail'] = $payslip_detail;
		$result['month'] = $month;
		return $result;

	}


	/**
	 * employees copy
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function employees_copy($data)
	{
		$message='';
		$affectedRows=0;
		$month = date('Y-m-d', strtotime("-1 months",strtotime($data['month'].'-01')));

		$rel_type = hrp_get_hr_profile_status();
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees = $this->db->get(db_prefix() . 'hrp_employees_value')->result_array();

		if(count($employees) > 0){
			//delete old data
			$this->db->where('rel_type', $rel_type);
			$this->db->where("date_format(month, '%Y-%m-%d') = '".date('Y-m-d', strtotime($data['month'].'-01'))."'");
			$affected_rows = $this->db->delete(db_prefix().'hrp_employees_value');
			if($affected_rows > 0){
				$affectedRows++;
			}

			//insert new data
			foreach ($employees as $key => $employee) {
			    if(isset($employee['id'])){
			    	unset($employees[$key]['id']);
			    }
			    $employees[$key]['month'] = date('Y-m-d', strtotime($data['month'].'-01'));

			}

			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_employees_value', $employees);
			if($affected_rows > 0){
				$affectedRows++;
			}

			if($affectedRows > 0){
				$message = _l('hrp_added_successfully');
				$status = 'success';
			}else{
				$message = _l('hrp_add_failed');
				$status = 'warning';
			}
		}else{

			$message = _l('No_data_for_the_previous_month');
			$status = 'warning';
		}

		return ['message' => $message, 'status' => $status];

	}

	/**
	 * get tasks timer by_month
	 * @param  [type] $month 
	 * @return [type]        
	 */
	public function get_tasks_timer_by_month($month, $staff_id, $str_sql1, $hr_profile_status, $payslip_range = [])
	{

		$month_temp = $month;
		//TODO get hourly rate by contract
		$salary_task_timers=[];

		$month = (int)date('m', strtotime($month));

		//get_staff
		$this->db->where($str_sql1);
		$this->db->order_by('firstname', 'desc');
		$staffs = $this->db->get(db_prefix().'staff')->result_array();

		$staff_data=[];
		foreach ($staffs as $key => $staff) {
			$staff_data[$staff['staffid']] = $staff;
		}

		if($hr_profile_status == 'hr_records'){

		//get_contract by staff
			$staff_contracts = $this->get_list_staff_contract($month_temp);
		}
		
		if(count($payslip_range) == 0){

			$sql_where="SELECT ".db_prefix()."tasks.hourly_rate, ".db_prefix()."taskstimers.staff_id, from_unixtime(start_time, '%Y-%m-%d %H:%i:%s') as start_time, from_unixtime(end_time, '%Y-%m-%d %H:%i:%s') as end_time, date_format(from_unixtime(end_time, '%Y-%m-%d'), '%c') as months, TIMESTAMPDIFF(MINUTE, from_unixtime(start_time, '%Y-%m-%d %H:%i:%s'), from_unixtime(end_time, '%Y-%m-%d %H:%i:%s')) as total_time FROM ".db_prefix()."taskstimers 
			LEFT join ".db_prefix()."tasks on ".db_prefix()."taskstimers.task_id = ".db_prefix()."tasks.id
			where date_format(from_unixtime(end_time, '%Y-%m-%d'), '%c') = ".$month." AND ".$staff_id."
			order by staff_id desc";

		}else{
			$sql_where="SELECT ".db_prefix()."tasks.hourly_rate, ".db_prefix()."taskstimers.staff_id, from_unixtime(start_time, '%Y-%m-%d %H:%i:%s') as start_time, from_unixtime(end_time, '%Y-%m-%d %H:%i:%s') as end_time, date_format(from_unixtime(end_time, '%Y-%m-%d'), '%c') as months, TIMESTAMPDIFF(MINUTE, from_unixtime(start_time, '%Y-%m-%d %H:%i:%s'), from_unixtime(end_time, '%Y-%m-%d %H:%i:%s')) as total_time FROM ".db_prefix()."taskstimers 
			LEFT join ".db_prefix()."tasks on ".db_prefix()."taskstimers.task_id = ".db_prefix()."tasks.id
			where from_unixtime(start_time, '%Y-%m-%d') >= '".$payslip_range[0]."' AND from_unixtime(end_time, '%Y-%m-%d') <= '".$payslip_range[1]."'  AND ".$staff_id."
			order by staff_id desc";

		}

		$task_timers = $this->db->query($sql_where)->result_array();

		foreach ($task_timers as $key => $task_timer) {

			if($task_timer['hourly_rate'] != 0){
				if(isset($salary_task_timers[$task_timer['staff_id']])){

					$salary_task_timers[$task_timer['staff_id']]['salary_from_tasks'] += (float)$task_timer['hourly_rate']*((float)$task_timer['total_time']/60);
					$salary_task_timers[$task_timer['staff_id']]['total_hours_by_tasks'] += (float)$task_timer['total_time']/60;

				}else{

					$salary_task_timers[$task_timer['staff_id']]['salary_from_tasks'] = (float)$task_timer['hourly_rate']*((float)$task_timer['total_time']/60);
					$salary_task_timers[$task_timer['staff_id']]['total_hours_by_tasks'] = (float)$task_timer['total_time']/60;
				}
			}else{
				if($hr_profile_status == 'hr_records'){
				//get by contract
					if(isset($staff_contracts[$task_timer['staff_id']])){
						$hourly_rate=0;
						$flag_contract = false;

					//formal contract
						if(isset($staff_contracts[$task_timer['staff_id']]['formal'])){
							if($staff_contracts[$task_timer['staff_id']]['formal']['hourly_or_month'] == 'hourly_rate'){
								if($staff_contracts[$task_timer['staff_id']]['formal']['primary_expiration'] == null){
									if(strtotime($staff_contracts[$task_timer['staff_id']]['formal']['primary_effective']) <= strtotime($task_timer['start_time']) ){
										foreach ($staff_contracts[$task_timer['staff_id']]['formal'] as $formal_key => $formal_value) {
											if(preg_match('/^st2_/', $formal_key) || preg_match('/^al2_/', $formal_key)){
											//get value from staff contract if exist
												$hourly_rate += (float)$formal_value;											
											}
										}
										$flag_contract = true;

									}
								}else{
									if(strtotime($staff_contracts[$task_timer['staff_id']]['formal']['primary_effective']) <= strtotime($task_timer['start_time']) &&  strtotime($task_timer['start_time']) <= strtotime($staff_contracts[$task_timer['staff_id']]['formal']['primary_expiration'])){

										foreach ($staff_contracts[$task_timer['staff_id']]['formal'] as $formal_key => $formal_value) {
											if(preg_match('/^st2_/', $formal_key) || preg_match('/^al2_/', $formal_key)){
											//get value from staff contract if exist
												$hourly_rate += (float)$formal_value;											
											}
										}
										$flag_contract = true;

									}
								}
							}
						}

					//probationary contract
						if($flag_contract == false && isset($staff_contracts[$task_timer['staff_id']]['probationary'])){
							if($staff_contracts[$task_timer['staff_id']]['probationary']['hourly_or_month'] == 'hourly_rate'){
								if($staff_contracts[$task_timer['staff_id']]['probationary']['probationary_expiration'] == null){
									if(strtotime($staff_contracts[$task_timer['staff_id']]['probationary']['probationary_effective']) <= strtotime($task_timer['start_time']) ){
										foreach ($staff_contracts[$task_timer['staff_id']]['probationary'] as $probationary_key => $probationary_value) {
											if(preg_match('/^st1_/', $probationary_key) || preg_match('/^al1_/', $probationary_key)){
											//get value from staff contract if exist
												$hourly_rate += (float)$probationary_value;											
											}
										}
										$flag_contract = true;

									}
								}else{
									if(strtotime($staff_contracts[$task_timer['staff_id']]['probationary']['probationary_effective']) <= strtotime($task_timer['start_time']) &&  strtotime($task_timer['start_time']) <= strtotime($staff_contracts[$task_timer['staff_id']]['probationary']['probationary_expiration'])){

										foreach ($staff_contracts[$task_timer['staff_id']]['probationary'] as $probationary_key => $probationary_value) {
											if(preg_match('/^st1_/', $probationary_key) || preg_match('/^al1_/', $probationary_key)){
											//get value from staff contract if exist
												$hourly_rate += (float)$probationary_value;											
											}
										}
										$flag_contract = true;

									}
								}
							}
						}

						if($flag_contract == false){
							if(isset($staff_data[$task_timer['staff_id']])){
								$hourly_rate = $staff_data[$task_timer['staff_id']]['hourly_rate'];
							}
						}

						if(isset($salary_task_timers[$task_timer['staff_id']])){

							$salary_task_timers[$task_timer['staff_id']]['salary_from_tasks'] += (float)$hourly_rate*((float)$task_timer['total_time']/60);
							$salary_task_timers[$task_timer['staff_id']]['total_hours_by_tasks'] += (float)$task_timer['total_time']/60;

						}else{
							$salary_task_timers[$task_timer['staff_id']]['salary_from_tasks'] = (float)$hourly_rate*((float)$task_timer['total_time']/60);
							$salary_task_timers[$task_timer['staff_id']]['total_hours_by_tasks'] = (float)$task_timer['total_time']/60;
						}

					}else{
					//get hourly rate in staff
						$hourly_rate=0;
						if(isset($staff_data[$task_timer['staff_id']])){
							$hourly_rate = $staff_data[$task_timer['staff_id']]['hourly_rate'];
							
						}

						if(isset($salary_task_timers[$task_timer['staff_id']])){
							$salary_task_timers[$task_timer['staff_id']]['salary_from_tasks'] += (float)$hourly_rate*((float)$task_timer['total_time']/60);
							$salary_task_timers[$task_timer['staff_id']]['total_hours_by_tasks'] += (float)$task_timer['total_time']/60;
						}else{
							$salary_task_timers[$task_timer['staff_id']]['salary_from_tasks'] = (float)$hourly_rate*((float)$task_timer['total_time']/60);
							$salary_task_timers[$task_timer['staff_id']]['total_hours_by_tasks'] = (float)$task_timer['total_time']/60;
						}

					}
				}else{
				//get hourly rate in staff
					$hourly_rate=0;
					if(isset($staff_data[$task_timer['staff_id']])){

						$hourly_rate = $staff_data[$task_timer['staff_id']]['hourly_rate'];
					}

					if(isset($salary_task_timers[$task_timer['staff_id']])){
						$salary_task_timers[$task_timer['staff_id']]['salary_from_tasks'] += (float)$hourly_rate*((float)$task_timer['total_time']/60);
						$salary_task_timers[$task_timer['staff_id']]['total_hours_by_tasks'] += (float)$task_timer['total_time']/60;
					}else{
						$salary_task_timers[$task_timer['staff_id']]['salary_from_tasks'] = (float)$hourly_rate*((float)$task_timer['total_time']/60);
						$salary_task_timers[$task_timer['staff_id']]['total_hours_by_tasks'] = (float)$task_timer['total_time']/60;
					}
				}

			}

		}

		return $salary_task_timers;
		
	}

	/**
	 * payslip of staff
	 * @param  [type] $payslip_id 
	 * @return [type]             
	 */
	public function payslip_of_staff($payslip_id)
	{
		$staff_ids=[];

		$this->db->where('payslip_id', $payslip_id);
		$payslip_details = $this->db->get(db_prefix().'hrp_payslip_details')->result_array();

		foreach ($payslip_details as $payslip_detail) {
			$staff_ids[] = $payslip_detail['staff_id'];
		}

		return $staff_ids;
	}

	/**
	 * remove employees not under management on payslip
	 * @param  [type] $payslip_data 
	 * @return [type]               
	 */
	public function remove_employees_not_under_management_on_payslip($payslip_data)
	{
		$payslip_data_decode = json_decode($payslip_data);
		if(is_array($payslip_data_decode)){
			if(isset($payslip_data_decode[0]->celldata)){
				$array_staffid_by_permission = get_array_staffid_by_permission();
				$row_remove=[];
				$staff_id_col;

				//get col of staff_id
				foreach ($payslip_data_decode[0]->celldata as $celldata) {
					if(isset($celldata->v->m) && $celldata->v->m =='staff_id' ){
						$staff_id_col = $celldata->c;
					}

					if(isset($staff_id_col) && new_strlen($staff_id_col) > 0){
						break;
					}
				}

				//get row remove on payslip
				if(isset($staff_id_col)){
					foreach ($payslip_data_decode[0]->celldata as $celldata) {
						if(isset($celldata->r) && $celldata->r > 4 && isset($celldata->c) && $celldata->c == $staff_id_col && isset($celldata->v->m) && !in_array($celldata->v->m, $array_staffid_by_permission)  ){
							$row_remove[] = $celldata->r;
						}
					}
				}

				//remove row on payslip
				foreach ($payslip_data_decode[0]->celldata as $key => $celldata) {
					if(in_array($celldata->r, $row_remove)){
						$payslip_data_decode[0]->celldata[$key]->v->m = '####';
						$payslip_data_decode[0]->celldata[$key]->v->v = '####';
					}
				}

				return json_encode($payslip_data_decode);

			}
			return $payslip_data;
		}
		return $payslip_data;
	}

	/**
	 * employee export pdf
	 * @param  [type] $export_employee 
	 * @return [type]                  
	 */
	public function employee_export_pdf($export_employee)
	{
		return app_pdf('export_employee', module_dir_path(HR_PAYROLL_MODULE_NAME, 'libraries/pdf/Export_employee_pdf.php'), $export_employee);
	}

	/**
	 * get payslip detail by payslip_id
	 * @param  [type] $payslip_id 
	 * @return [type]             
	 */
	public function get_payslip_detail_by_payslip_id($payslip_id)
	{
		$this->db->where('payslip_id', $payslip_id);
		return $this->db->get(db_prefix() . 'hrp_payslip_details')->result_array();
	}

	/**
	 * get hrp attendance timesheet leave
	 * @param  [type] $month 
	 * @param  string $where 
	 * @return [type]        
	 */
	public function get_hrp_attendance_timesheet_leave($month, $where='')
	{
		$rel_type = hrp_get_timesheets_status();

		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees_timesheets = $this->db->get(db_prefix() . 'hrp_employees_timeshee_leaves')->result_array();

		return $employees_timesheets;
	}
	
	/**
	 * add update attendance timesheets leave
	 * @param [type] $data 
	 */
	public function add_update_attendance_timesheets_leave($data)
	{	

		$affectedRows = 0;
		$rel_type = hrp_get_timesheets_status();

		$attendance_month = date('Y-m-d',strtotime($data['attendance_fill_month'].'-01'));

		$days_header_in_month = $this->hr_payroll_model->get_day_header_in_month($attendance_month, '', false);
		$header_key = array_merge($days_header_in_month['staff_key'], $days_header_in_month['days_key'], $days_header_in_month['attendance_key']);
		
		if (isset($data['hrp_attendance_value'])) {
			$hrp_attendance_value = $data['hrp_attendance_value'];
			unset($data['hrp_attendance_value']);
		}
		/*update save note*/

		if(isset($hrp_attendance_value)){
			$hrp_attendance_detail = json_decode($hrp_attendance_value);

			$es_detail = [];
			$row = [];

			foreach ($hrp_attendance_detail as $key => $value) {				
					$es_detail[] = array_combine($header_key, $value);
			}
		}

		$row = [];
		$row['update'] = []; 
		$row['insert'] = []; 
		$row['delete'] = [];
		$total = [];

		$total['total_amount'] = 0;
		foreach ($es_detail as $key => $value) {
			if(isset($value['staff_departments'])){
				unset($value['staff_departments']);
			}
			if(isset($value['hr_code'])){
				unset($value['hr_code']);
			}
			if(isset($value['staff_name'])){
				unset($value['staff_name']);
			}

			foreach ($value as $k_value => $val) {
				if($k_value != 'staff_id' && $k_value != 'id' && $k_value != 'rel_type' && $k_value != 'month' && $k_value != 'paid_leave' &&  $k_value != 'unpaid_leave'){

					if(strlen($val) > 0){

						$explode_temp = [];
						$val_explode = explode(';', $val);
						foreach ($val_explode as $ex_value) {
						    if(preg_match('/PL:/', $ex_value) || preg_match('/UPL:/', $ex_value)){
						    	$explode_temp[] = $ex_value;
						    }
						}
						if(count($explode_temp) > 0){
							$value[$k_value] = implode(';', $explode_temp);
						}else{
							$value[$k_value] = '';
						}
					}
				}
			    
			}
			
			if($value['id'] != 0){
				$row['delete'][] = $value['id'];
				$row['update'][] = $value;
			}else{
				unset($value['id']);
				$row['insert'][] = $value;
			}

		}

		if(empty($row['delete'])){
			$row['delete'] = ['0'];
		}

		if($data['department_attendance_filter'] == '' && $data['staff_attendance_filter'] == '' && $data['role_attendance_filter'] == ''){
			$row['delete'] = implode(",",$row['delete']);
			$this->db->where('id NOT IN ('.$row['delete'] .') and rel_type = "'.$rel_type.'" AND date_format(month,"%Y-%m-%d") = "'.$attendance_month.'"');
			$this->db->delete(db_prefix().'hrp_employees_timeshee_leaves');
			if($this->db->affected_rows() > 0){
				$affectedRows++;
			}
		}

		if(count($row['insert']) != 0){
			$affected_rows = $this->db->insert_batch(db_prefix().'hrp_employees_timeshee_leaves', $row['insert']);
			if($affected_rows > 0){
				$affectedRows++;
			}

		}
		if(count($row['update']) != 0){
			$affected_rows = $this->db->update_batch(db_prefix().'hrp_employees_timeshee_leaves', $row['update'], 'id');
			if($affected_rows > 0){
				$affectedRows++;
			}

		}

		if ($affectedRows > 0) {
			return true;
		}
		return false;

	}

	/**
	 * timesheet leave calculation
	 * @param  [type] $data 
	 * @return [type]       
	 */
	public function timesheet_leave_calculation($data)
	{
		$month = date('Y-m-d',strtotime($data['month'].'-01'));

		//get employee data for caculation attendance
		$employees = $this->get_employees_data($month);
		$employees_data = [];
		foreach ($employees as $employee_key => $employee_value) {
			$employees_data[$employee_value['staff_id']] = $employee_value;
		}

		$update_batch_data = [];
		$update_batch_employees_timesheet = [];
		$rel_type = hrp_get_timesheets_status();
		$date_to_column_name = date_to_column_name();

		$str_select_day = '*, ';
		
		$this->db->select($str_select_day);
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees_timesheets = $this->db->get(db_prefix() . 'hrp_employees_timeshee_leaves')->result_array();

		foreach ($employees_timesheets as $em_key => $timesheet_leave) {
			$paid_leave = 0;
			$unpaid_leave = 0;

			foreach ($timesheet_leave as $k_value => $val) {
				if($k_value != 'staff_id' && $k_value != 'id' && $k_value != 'rel_type' && $k_value != 'month' && $k_value != 'paid_leave' &&  $k_value != 'unpaid_leave'){

					if(strlen($val) > 0){

						$explode_temp = [];
						$val_explode = explode(';', $val);
						foreach ($val_explode as $ex_value) {
							if(preg_match('/UPL:/', $ex_value)){
								$unpaid_leave += (float)str_replace('UPL:', '', $ex_value);
							}elseif(preg_match('/PL:/', $ex_value)){
								$paid_leave += (float)str_replace('PL:', '', $ex_value);
							}
						}
					}
				}
			}

			$update_batch_data[$em_key]['id'] = $timesheet_leave['id'];
			$update_batch_data[$em_key]['paid_leave'] = $paid_leave;
			$update_batch_data[$em_key]['unpaid_leave'] = $unpaid_leave;
			$update_batch_employees_timesheet[$timesheet_leave['staff_id']] = [
				'paid_leave' => $paid_leave,
				'unpaid_leave' => $unpaid_leave,
			];

		}

		if(count($update_batch_data) > 0){
			$this->db->update_batch(db_prefix().'hrp_employees_timeshee_leaves', $update_batch_data, 'id');

			$str_select_day = '*, ';
			$this->db->select($str_select_day);
			$this->db->where('rel_type', $rel_type);
			$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
			$this->db->order_by('staff_id', 'asc');
			$employees_timesheets = $this->db->get(db_prefix() . 'hrp_employees_timesheets')->result_array();

			if(count($employees_timesheets) > 0){

				foreach ($employees_timesheets as $key => $value) {
					if(isset($update_batch_employees_timesheet[$value['staff_id']])){
						$employees_timesheets[$key]['paid_leave'] = $update_batch_employees_timesheet[$value['staff_id']]['paid_leave'];
						$employees_timesheets[$key]['unpaid_leave'] = $update_batch_employees_timesheet[$value['staff_id']]['unpaid_leave'];
					}
				}
				if(count($employees_timesheets) > 0){
					$this->db->update_batch(db_prefix().'hrp_employees_timesheets', $employees_timesheets, 'id');
				}
			}else{
				foreach ($update_batch_employees_timesheet as $key => $batch_employees_timesheet) {
				    $update_batch_employees_timesheet[$key]['staff_id'] = $key;
				    $update_batch_employees_timesheet[$key]['month'] = $month;
				    $update_batch_employees_timesheet[$key]['rel_type'] = $rel_type;
				}
				$this->db->insert_batch(db_prefix().'hrp_employees_timesheets', $update_batch_employees_timesheet);
			}


		}


		return true;
	}

	/**
	 * attendance calculation v2
	 * @param  [type] $payslip_month     
	 * @param  [type] $arr_payslip_range 
	 * @return [type]                    
	 */
	public function attendance_calculation_v2($payslip_month, $arr_payslip_range, $where = '')
	{
		// var_dump(($arr_payslip_range[0]));
		// var_dump( ($arr_payslip_range[1]));
		$total_day = (strtotime($arr_payslip_range[1])- strtotime($arr_payslip_range[0]))/60/60/24;
		// var_dump((strtotime($arr_payslip_range[1])- strtotime($arr_payslip_range[0]))/60/60/24);
		// die;
		$arr_employees_timeshee_leaves = [];
		$attendance_data = [];
		$date_column_header=[];
		$str_select_temp = '';


		$date['01'] = 'day_1';

		$rel_type = hrp_get_timesheets_status();
		$date_to_column_name = date_to_column_name();

		// for first day
		$temp_date = date('Y-m-d', strtotime($arr_payslip_range[0]));
		$column_name = $date_to_column_name[date('d',strtotime($temp_date))];
		$date_column_header[date('d',strtotime($temp_date))] = $column_name;
		if(strlen($str_select_temp) > 0){
			$str_select_temp .= ','.$column_name;
		}else{
			$str_select_temp .= $column_name;
		}

		// for last day
		$temp_date = date('Y-m-d', strtotime($arr_payslip_range[1]));
		$column_name = $date_to_column_name[date('d',strtotime($temp_date))];
		$date_column_header[date('d',strtotime($temp_date))] = $column_name;
		if(strlen($str_select_temp) > 0){
			$str_select_temp .= ','.$column_name;
		}else{
			$str_select_temp .= $column_name;
		}


		for ($i=1; $i < $total_day; $i++) { 
			$temp_date = date('Y-m-d', strtotime($arr_payslip_range[0].'+'.$i.' days'));

			$column_name = $date_to_column_name[date('d',strtotime($temp_date))];
			$date_column_header[date('d',strtotime($temp_date))] = $column_name;

			if(strlen($str_select_temp) > 0){
				$str_select_temp .= ','.$column_name;
			}else{
				$str_select_temp .= $column_name;
			}

		}

		$month = date('Y-m-d',strtotime($payslip_month.'-01'));

		//get employee data for caculation attendance
		$employees = $this->get_employees_data($month);
		$employees_data = [];
		foreach ($employees as $employee_key => $employee_value) {
			$employees_data[$employee_value['staff_id']] = $employee_value;
		}

		$str_select_day = $str_select_temp.', id, staff_id, month';
		$str_select_day .= '('.implode("+", $date_column_header).') as actual_workday_temp, standard_workday, rel_type';
		
		$this->db->select($str_select_day);
		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees_timesheets = $this->db->get(db_prefix() . 'hrp_employees_timesheets')->result_array();

		// get employees_timesheet leaves
		$this->db->select($str_select_temp.', id, staff_id, month');
		if($where != ''){
			$this->db->where($where);
		}
		$this->db->where('rel_type', $rel_type);
		$this->db->where("date_format(month, '%Y-%m-%d') = '".$month."'");
		$this->db->order_by('staff_id', 'asc');
		$employees_timesheet_leaves = $this->db->get(db_prefix() . 'hrp_employees_timeshee_leaves')->result_array();

		foreach ($employees_timesheet_leaves as $em_key => $timesheet_leave) {
			$paid_leave = 0;
			$unpaid_leave = 0;

			foreach ($timesheet_leave as $k_value => $val) {
				if($k_value != 'staff_id' && $k_value != 'id' && $k_value != 'rel_type' && $k_value != 'month' && $k_value != 'paid_leave' &&  $k_value != 'unpaid_leave'){

					if(strlen($val) > 0){

						$explode_temp = [];
						$val_explode = explode(';', $val);
						foreach ($val_explode as $ex_value) {
							if(preg_match('/UPL:/', $ex_value)){
								$unpaid_leave += (float)str_replace('UPL:', '', $ex_value);
							}elseif(preg_match('/PL:/', $ex_value)){
								$paid_leave += (float)str_replace('PL:', '', $ex_value);
							}
						}
					}
				}
			}
			$arr_employees_timeshee_leaves[$timesheet_leave['staff_id']] = [
				'paid_leave' => $paid_leave,
				'unpaid_leave' => $unpaid_leave,
			];
		}


		foreach ($employees_timesheets as $em_key => $timesheet) {

			$employees_timesheets[$em_key]['actual_workday'] = 0;
			$employees_timesheets[$em_key]['actual_workday_probation'] = 0;
			$paid_leave = 0;
			$unpaid_leave = 0;

			if(isset($arr_employees_timeshee_leaves[$timesheet['staff_id']])){
				$paid_leave = $arr_employees_timeshee_leaves[$timesheet['staff_id']]['paid_leave'];
				$unpaid_leave = $arr_employees_timeshee_leaves[$timesheet['staff_id']]['unpaid_leave'];
			}

			$employees_timesheets[$em_key]['paid_leave'] = $paid_leave;
			$employees_timesheets[$em_key]['unpaid_leave'] = $unpaid_leave;

			if(isset($employees_data[$timesheet['staff_id']])){

					//check timesheet in formal contract or probationary contract.
				$payslip_month = date("m", strtotime($month));
				$probationary_expiration_month = date("m", strtotime($employees_data[$timesheet['staff_id']]['probationary_expiration'] ?? ''));
				$probationary_expiration_day = date("d", strtotime($employees_data[$timesheet['staff_id']]['probationary_expiration'] ?? ''));

					//if probationary_expiration month == payslip month
				foreach ($timesheet as $timesheet_key => $timesheet_value) {
					if((float)$payslip_month == (float)$probationary_expiration_month ){

						if(preg_match('/^day_/', $timesheet_key)){

							$day = new_str_replace('day_', '', $timesheet_key);
								//if probationary_expiration day <= timesheet day
							if( (float)$day <= (float)$probationary_expiration_day){

								$employees_timesheets[$em_key]['actual_workday_probation'] += $timesheet_value;
							}else{
								//if probationary_expiration day > timesheet day
								$employees_timesheets[$em_key]['actual_workday'] += $timesheet_value;
							}

						}

					}else{
						if(preg_match('/^day_/', $timesheet_key)){
							$employees_timesheets[$em_key]['actual_workday'] += $timesheet_value;
						}
					}
				}

			}else{
				$employees_timesheets[$em_key]['actual_workday'] = $timesheet['actual_workday_temp'];
			}

			$attendance_data[$timesheet['staff_id']] = $employees_timesheets[$em_key];
		}
		return $employees_timesheets;
	}

	/**
	 * get customize payslip columns
	 * @return [type] 
	 */
	public function get_customize_payslip_columns()
	{
		$this->db->order_by('order_number', 'asc');
		return $this->db->get(db_prefix() . 'hrp_customize_staff_payslip_columns')->result_array();
	}

	/**
	 * get pdf payslip template
	 * @param  boolean $id 
	 * @return [type]      
	 */
	public function get_pdf_payslip_template($id = false, $payslip_template_id = false)
	{
		if (is_numeric($id)) {
			$this->db->where('id', $id);
			return $this->db->get(db_prefix() . 'hrp_payslip_pdf_templates')->row();
		}
		if ($id == false) {
			if($payslip_template_id){
				$this->db->where('payslip_template_id', $payslip_template_id);
			}
		   return  $this->db->get(db_prefix() . 'hrp_payslip_pdf_templates')->result_array();
		}

	}

	/**
	 * add contract template
	 * @param [type] $data 
	 */
	public function add_pdf_payslip_template($data){
		$data['content'] = $data['content'];

		$this->db->insert(db_prefix() . 'hrp_payslip_pdf_templates', $data);
		$insert_id = $this->db->insert_id();

		if ($insert_id) {
			return $insert_id;
		}
		return false;
	}

	/**
	 * update pdf_payslip template
	 * @param  [type] $data 
	 * @param  [type] $id   
	 * @return [type]       
	 */
	public function update_pdf_payslip_template($data, $id)
	{   
		$data['content'] = $data['content'];

		$this->db->where('id', $id);
		$this->db->update(db_prefix() . 'hrp_payslip_pdf_templates', $data);

		if ($this->db->affected_rows() > 0) {
			return true;
		}
		return false;
	}

	/**
	 * delete pdf_payslip template 
	 * @param  [type] $id [
	 * @return [type]     [
	 */
	public function delete_pdf_payslip_template($id){
		$this->db->where('id', $id);
		$this->db->delete(db_prefix() . 'hrp_payslip_pdf_templates');

		if ($this->db->affected_rows() > 0) {
			return true;
		}

		return false;
	}

	public function get_pdf_payslip_template_selected_html($payslip_template_id, $pdf_payslip_template_id)
	{

		if(is_numeric($payslip_template_id)){
			$pdf_payslip_templates = $this->get_pdf_payslip_template(false, $payslip_template_id);
		}else{
			$pdf_payslip_templates = [];
		}
		$template_options = '';

		if(isset($pdf_payslip_template_id) && $pdf_payslip_template_id != ''){

			$template_options .= '<option value=""></option>';
			foreach ($pdf_payslip_templates as $template) {

					$select='';
					if($template['id'] == $pdf_payslip_template_id){           
						$select .= 'selected';
					}
					$template_options .= '<option value="' . $template['id'] . '" '.$select.'>' . $template['name'] . '</option>';
				}

		}else{
			/*get payslip template for case create new*/

			$template_options .= '<option value=""></option>';
			foreach ($pdf_payslip_templates as $template) {
				$template_options .= '<option value="' . $template['id'] . '" >' . $template['name'] . '</option>';
			}

		}

		return $template_options;
	}

	public function hr_payroll_get_payslip_pdf_only_for_pdf($id = '', $where = [], $for_editor = false)
	{
		$this->db->where('id', $id);
		$hrp_payslip_details = $this->db->get(db_prefix() . 'hrp_payslip_details')->result_array();

		if (is_numeric($id)) {
			$this->db->where(db_prefix() . 'hrp_payslip_details.id', $id);
			$hrp_payslip_details = $this->db->get(db_prefix() . 'hrp_payslip_details')->row();

			// get pdf payslip template
			
			if ($hrp_payslip_details) {
				// get payslip
				$hrp_payslip = $this->get_hrp_payslip($hrp_payslip_details->payslip_id);
				if ($hrp_payslip && is_numeric($hrp_payslip->pdf_template_id) && $hrp_payslip->pdf_template_id != 0) {

					$pdf_payslip_template = $this->get_pdf_payslip_template($hrp_payslip->pdf_template_id);

					if ($pdf_payslip_template) {
						$pdf_payslip_template->payslip_number = $hrp_payslip_details->pay_slip_number;
						if ($for_editor == false) {
							$this->load->library('merge_fields/hr_payslip_merge_fields');
							$this->load->library('merge_fields/other_merge_fields');

							$merge_fields = [];
							$merge_fields = array_merge($merge_fields, $this->hr_payslip_merge_fields->format($id));
							$merge_fields = array_merge($merge_fields, $this->other_merge_fields->format());

							$logo_url = '';

							foreach ($merge_fields as $key => $val) {
								if($key == '{logo_url}'){
									$logo_url .= $val;
									
								}

								if($key == '{logo_image_with_url}'){
									$val ='';
									
									$val .= '<a href="'.$logo_url.'" class="logo hr-img-responsive" style=" width: 300px; height: auto;">';
									$val .= '<img src="'.$logo_url.'" class="hr-img-responsive" style=" width: 300px; height: auto;" alt="GTSS Solution Viet Nam">';
									$val .= '</a>';
									
								}

								if (stripos($pdf_payslip_template->content, $key) !== false) {
									$pdf_payslip_template->content = str_ireplace($key, $val ?? '', $pdf_payslip_template->content ?? '');
								} else {
									$pdf_payslip_template->content = str_ireplace($key, '', $pdf_payslip_template->content ?? '');
								}
							}

						}
					}
				}
			}

			return $pdf_payslip_template;
		}

		return $hrp_payslip_details;
	}

	/**
     * check auto create currency rate
     * @return [type]
     */
    public function check_auto_create_currency_rate() {
        $this->load->model('currencies_model');
        $currency_rates = $this->get_currency_rate();
        $currencies = $this->currencies_model->get();
        $currency_generator = $this->currency_generator($currencies);

        foreach ($currency_rates as $key => $currency_rate) {
            if (isset($currency_generator[$currency_rate['from_currency_id'] . '_' . $currency_rate['to_currency_id']])) {
                unset($currency_generator[$currency_rate['from_currency_id'] . '_' . $currency_rate['to_currency_id']]);
            }
        }

        //if have API, will get currency rate from here
        if (count($currency_generator) > 0) {
            $this->db->insert_batch(db_prefix() . 'currency_rates', $currency_generator);
        }

        return true;
    }

    /**
     * currency generator
     * @param  $variants
     * @param  integer $i
     * @return 
     */
    public function currency_generator($currencies) {

        $currency_rates = [];

        foreach ($currencies as $key_1 => $value_1) {
            foreach ($currencies as $key_2 => $value_2) {
                if ($value_1['id'] != $value_2['id']) {
                    $currency_rates[$value_1['id'] . '_' . $value_2['id']] = [
                        'from_currency_id' => $value_1['id'],
                        'from_currency_name' => $value_1['name'],
                        'from_currency_rate' => 1,
                        'to_currency_id' => $value_2['id'],
                        'to_currency_name' => $value_2['name'],
                        'to_currency_rate' => 0,
                        'date_updated' => date('Y-m-d H:i:s'),
                    ];
                }

            }
        }

        return $currency_rates;
    }

    /**
     * get currency rate
     * @param  boolean $id
     * @return [type]
     */
    public function get_currency_rate($id = false) {
        if (is_numeric($id)) {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'currency_rates')->row();
        }

        if ($id == false) {
            return $this->db->query('select * from ' . db_prefix() . 'currency_rates')->result_array();
        }
    }

    /**
     * update currency rate setting
     *
     * @param      array   $data   The data
     *
     * @return     boolean
     */
    public function update_setting_currency_rate($data) {
        $affectedRows = 0;
        if (!isset($data['cr_automatically_get_currency_rate'])) {
            $data['cr_automatically_get_currency_rate'] = 0;
        }

        foreach ($data as $key => $value) {
            $this->db->where('name', $key);
            $this->db->update(db_prefix() . 'options', [
                'value' => $value,
            ]);
            if ($this->db->affected_rows() > 0) {
                $affectedRows++;
            }
        }

        if ($affectedRows > 0) {
            return true;
        }
        return false;
    }

    /**
     * Gets the currency rate online.
     *
     * @param        $id     The identifier
     *
     * @return     bool    The currency rate online.
     */
    public function get_currency_rate_online($id) {
        $currency_rate = $this->get_currency_rate($id);

        if ($currency_rate) {
            return $this->currency_converter($currency_rate->from_currency_name, $currency_rate->to_currency_name);
        }

        return false;
    }

    /**
     * Gets all currency rate online.
     *
     * @return     bool  All currency rate online.
     */
    public function get_all_currency_rate_online() {
        $currency_rates = $this->get_currency_rate();
        $affectedRows = 0;
        foreach ($currency_rates as $currency_rate) {
            $rate = $this->currency_converter($currency_rate['from_currency_name'], $currency_rate['to_currency_name']);

            $data_update = ['to_currency_rate' => $rate];
            $success = $this->update_currency_rate($data_update, $currency_rate['id']);

            if ($success) {
                $affectedRows++;
            }
        }

        if ($affectedRows > 0) {
            return true;
        }

        return true;
    }

    /**
     * update currency rate
     * @param  [type] $data
     * @return [type]
     */
    public function update_currency_rate($data, $id) {

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'currency_rates', ['to_currency_rate' => $data['to_currency_rate'], 'date_updated' => date('Y-m-d H:i:s')]);
        if ($this->db->affected_rows() > 0) {
            $this->db->where('id', $id);
            $current_rate = $this->db->get(db_prefix() . 'currency_rates')->row();

            $data_log['from_currency_id'] = $current_rate->from_currency_id;
            $data_log['from_currency_name'] = $current_rate->from_currency_name;
            $data_log['to_currency_id'] = $current_rate->to_currency_id;
            $data_log['to_currency_name'] = $current_rate->to_currency_name;
            $data_log['from_currency_rate'] = isset($data['from_currency_rate']) ? $data['from_currency_rate'] : '';
            $data_log['to_currency_rate'] = isset($data['to_currency_rate']) ? $data['to_currency_rate'] : '';
            $data_log['date'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'currency_rate_logs', $data_log);
            return true;
        }
        return false;
    }

     /**
     * [currency_converter description]
     * @param  string $from   Currency Code
     * @param  string $to     Currency Code
     * @param  float $amount
     * @return float        
     */
    public function currency_converter($from,$to,$amount = 1)
    {   
        $url = "https://www.google.com/finance/quote/$from-$to";
        $response = $this->api_get($url);
        $string1 = explode('class="YMlKec fxKbKc">', $response);

        if(isset($string1[1])){

            $rate = explode('</div>', $string1[1]);

            if(isset($rate[0])){
            	$rate[0]             = hr_payroll_reformat_currency($rate[0]);

                $result = $rate[0] * $amount;
                
                return $result;
            }
        }

        return false;
    }

    /**
     * api get
     * @param  string $url
     * @return string
     */
    public function api_get($url) {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);

        return curl_exec($curl);
    }

    /**
     * delete currency rate
     * @param  [type] $id
     * @return [type]
     */
    public function delete_currency_rate($id) {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'currency_rates');
        if ($this->db->affected_rows() > 0) {
            return true;
        }
        return false;
    }

    /**
     * { cronjob currency rates }
     *
     * @param        $manually  The manually
     *
     * @return     bool    
     */
    public function cronjob_currency_rates($manually) {
        $currency_rates = $this->get_currency_rate();
        foreach ($currency_rates as $currency_rate) {
            $data_insert = $currency_rate;
            $data_insert['date'] = date('Y-m-d');
            unset($data_insert['date_updated']);
            unset($data_insert['id']);

            $this->db->insert(db_prefix() . 'currency_rate_logs', $data_insert);
        }

        if (get_option('cr_automatically_get_currency_rate') == 1) {
            $this->get_all_currency_rate_online();
        }

        $cr_global_amount_expiration = get_option('cr_global_amount_expiration');
        if ($cr_global_amount_expiration != 0 && $cr_global_amount_expiration != '') {
            $this->db->where('date < "' . date('Y-m-d', strtotime(date('Y-m-d') . ' - ' . $cr_global_amount_expiration . ' days')).'"');
            $this->db->delete(db_prefix() . 'currency_rate_logs');
        }
        update_option('cr_date_cronjob_currency_rates', date('Y-m-d'));

        return true;
    }

    /**
     * get currency rate
     * @param  [type] $currency_id 
     * @return [type]              
     */
    public function get_currency_rate_infor($currency_id)
    {
    	$base_currency = get_base_currency();
        $pr_currency = get_currency($currency_id);

        $base_currency_name = $base_currency->name;
        $to_currency_name = $base_currency->name;
        $base_currency_rate = 1;
        $currency_rate = 1;
        
        $convert_str = ' ('.$base_currency->name.' => '.$base_currency->name.')'; 
        $currency_name = '('.$base_currency->name.')';
        if($base_currency->id != $pr_currency->id){
            $currency_rate = hrp_get_currency_rate($base_currency->name, $pr_currency->name);
            $convert_str = ' ('.$base_currency->name.' => '.$pr_currency->name.')'; 
            $currency_name = '('.$pr_currency->name.')';
            $to_currency_name = $pr_currency->name;
        }

        $data = [];
        $data = [
        	'convert_str' => $convert_str,
        	'currency_name' => $currency_name,
        	'base_currency_name' => $base_currency_name,
        	'to_currency_name' => $to_currency_name,
        	'base_currency_rate' => $base_currency_rate,
        	'currency_rate' => $currency_rate,
        ];

        return $data;
    }

//End file
}

