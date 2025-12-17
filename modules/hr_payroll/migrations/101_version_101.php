<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration

{
	public function up()
	{      
		$CI = &get_instance();
		
		if (hr_payroll_payroll_column_exist('"total_hours_by_tasks"') == 0){
			$CI->db->query('INSERT INTO `' . db_prefix() . 'hrp_payroll_columns` (`column_key`, `taking_method`, `function_name`, `value_related_to`, `display_with_staff`, `description`, `date_created`, `staff_id_created`, `order_display`, `is_edit`) VALUES ("Total hours by tasks", "system", "total_hours_by_tasks", "", "true", "Total hours by tasks", "'.date("Y-m-d H:i:s").'", "'.get_staff_user_id().'", "16", "no");
				');
		}

		if (hr_payroll_payroll_column_exist('"salary_from_tasks"') == 0){
			$CI->db->query('INSERT INTO `' . db_prefix() . 'hrp_payroll_columns` (`column_key`, `taking_method`, `function_name`, `value_related_to`, `display_with_staff`, `description`, `date_created`, `staff_id_created`, `order_display`, `is_edit`) VALUES ("Salary from tasks", "system", "salary_from_tasks", "", "true", "Salary from tasks", "'.date("Y-m-d H:i:s").'", "'.get_staff_user_id().'", "16", "no");
				');
		}

		//v101: add two column: total_hours_by_tasks, salary_from_tasks
		if (!$CI->db->field_exists('salary_from_tasks' ,db_prefix() . 'hrp_payslip_details')) { 
			$CI->db->query('ALTER TABLE `' . db_prefix() . "hrp_payslip_details`
				ADD COLUMN `total_hours_by_tasks`  DECIMAL(15,2)  DEFAULT '0',
				ADD COLUMN `salary_from_tasks`  DECIMAL(15,2)  DEFAULT '0'

				;");
		}
	}

}

