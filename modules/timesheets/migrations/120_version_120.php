<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_120 extends App_module_migration
{
	public function up()
	{
		$CI = &get_instance();   
		if (row_timesheets_options_exist('"allow_attendance_by_route"') == 0) {
			$CI->db->query('INSERT INTO `' . db_prefix() . 'timesheets_option` (`option_name`, `option_val`, `auto`) VALUES ("allow_attendance_by_route", "0", "1");
				');
		}
		
	}
}
