<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration

{
	public function up()
	{      
		$CI = &get_instance();
		
		if (!$CI->db->field_exists('day_26' ,db_prefix() . 'hrp_employees_timesheets')) { 
			$CI->db->query('ALTER TABLE `' . db_prefix() . "hrp_employees_timesheets`
				ADD COLUMN `day_26` DECIMAL(15,2) DEFAULT '0' ;");
		}
	}

}

