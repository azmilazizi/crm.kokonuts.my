<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_103 extends App_module_migration

{
	public function up()
	{      
		$CI = &get_instance();
		
		if (hr_payroll_payroll_column_exist('"bank_name"') == 0){
			$CI->db->query('INSERT INTO `' . db_prefix() . 'hrp_payroll_columns` (`column_key`, `taking_method`, `function_name`, `value_related_to`, `display_with_staff`, `description`, `date_created`, `staff_id_created`, `order_display`, `is_edit`) VALUES ("Bank Name", "system", "bank_name", "", "true", "Bank Name", "'.date("Y-m-d H:i:s").'", "'.get_staff_user_id().'", "17", "no");
				');
		}

		if (hr_payroll_payroll_column_exist('"account_number"') == 0){
			$CI->db->query('INSERT INTO `' . db_prefix() . 'hrp_payroll_columns` (`column_key`, `taking_method`, `function_name`, `value_related_to`, `display_with_staff`, `description`, `date_created`, `staff_id_created`, `order_display`, `is_edit`) VALUES ("Account Number", "system", "account_number", "", "true", "Account Number", "'.date("Y-m-d H:i:s").'", "'.get_staff_user_id().'", "17", "no");
				');
		}

		if (!$CI->db->field_exists('bank_name' ,db_prefix() . 'hrp_employees_value')) { 
			$CI->db->query('ALTER TABLE `' . db_prefix() . "hrp_employees_value`

				ADD COLUMN `bank_name` VARCHAR(500) ,
				ADD COLUMN `account_number` VARCHAR(200)

				;");
		}

	}

}

