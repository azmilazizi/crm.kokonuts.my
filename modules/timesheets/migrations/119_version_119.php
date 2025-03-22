<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_119 extends App_module_migration
{
	public function up()
	{
		$CI = &get_instance();   
		if (!$CI->db->field_exists('staff_id', db_prefix() . 'timesheets_additional_timesheet')) {
			$CI->db->query('ALTER TABLE `' . db_prefix() . "timesheets_additional_timesheet`
				ADD COLUMN `staff_id` INT(11) NULL  DEFAULT '0';");
		}
		
	}
}
