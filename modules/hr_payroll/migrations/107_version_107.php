<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_107 extends App_module_migration

{
	public function up()
	{     
		$CI = &get_instance(); 
		
		if (row_hr_payroll_options_exist('"hrp_customize_staff_payslip_column"') == 0){
			$CI->db->query('INSERT INTO `' . db_prefix() . 'hr_payroll_option` (`option_name`,`option_val`, `auto`) VALUES ("hrp_customize_staff_payslip_column", "0", "1");
				');
		}

		if (!$CI->db->table_exists(db_prefix() . "hrp_customize_staff_payslip_columns")) {
			$CI->db->query("CREATE TABLE `" . db_prefix() . "hrp_customize_staff_payslip_columns` (

				`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`column_name` TEXT NULL,
				`order_number` INT(11) NULL,

				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
		}

		if (!$CI->db->table_exists(db_prefix() . "hrp_payslip_pdf_templates")) {
			$CI->db->query("CREATE TABLE `" . db_prefix() . "hrp_payslip_pdf_templates` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`name` TEXT NULL,
				`payslip_template_id` INT(11) NULL,
				`content` LONGTEXT NULL,

				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
		}

		if (!$CI->db->field_exists("pdf_template_id" ,db_prefix() . "hrp_payslips")) { 
			$CI->db->query('ALTER TABLE `' . db_prefix() . "hrp_payslips`

				ADD COLUMN `pdf_template_id` int(11)

				;");
		}

	}

}

