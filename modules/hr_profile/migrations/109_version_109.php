<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_109 extends App_module_migration
{
	public function up()
	{
		
		$CI = &get_instance();
		if (!$CI->db->field_exists('epf_no' ,db_prefix() . 'staff')) { 
			$CI->db->query('ALTER TABLE `' . db_prefix() . "staff`
				ADD COLUMN `epf_no` TEXT,
				ADD COLUMN `social_security_no` TEXT

				;");
		}

	}
}
