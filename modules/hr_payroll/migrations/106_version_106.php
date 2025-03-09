<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_106 extends App_module_migration

{
	public function up()
	{      
		$CI = &get_instance();
		if (!$CI->db->table_exists(db_prefix() . 'hrp_employees_timeshee_leaves')) {
			$CI->db->query('CREATE TABLE `' . db_prefix() . "hrp_employees_timeshee_leaves` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`staff_id` INT(11) NULL,
				`month` DATE NOT NULL,

				`day_1`  TEXT  DEFAULT '',
				`day_2`  TEXT  DEFAULT '',
				`day_3`  TEXT  DEFAULT '',
				`day_4`  TEXT  DEFAULT '',
				`day_5`  TEXT  DEFAULT '',
				`day_6`  TEXT  DEFAULT '',
				`day_7`  TEXT  DEFAULT '',
				`day_8`  TEXT  DEFAULT '',
				`day_9`  TEXT  DEFAULT '',
				`day_10`  TEXT  DEFAULT '',
				`day_11`  TEXT  DEFAULT '',
				`day_12`  TEXT  DEFAULT '',
				`day_13`  TEXT  DEFAULT '',
				`day_14`  TEXT  DEFAULT '',
				`day_15`  TEXT  DEFAULT '',
				`day_16`  TEXT  DEFAULT '',
				`day_17`  TEXT  DEFAULT '',
				`day_18`  TEXT  DEFAULT '',
				`day_19`  TEXT  DEFAULT '',
				`day_20`  TEXT  DEFAULT '',
				`day_21`  TEXT  DEFAULT '',
				`day_22`  TEXT  DEFAULT '',
				`day_23`  TEXT  DEFAULT '',
				`day_24`  TEXT  DEFAULT '',
				`day_25`  TEXT  DEFAULT '',
				`day_26`  TEXT  DEFAULT '',
				`day_27`  TEXT  DEFAULT '',
				`day_28`  TEXT  DEFAULT '',
				`day_29`  TEXT  DEFAULT '',
				`day_30`  TEXT  DEFAULT '',
				`day_31`  TEXT  DEFAULT '',

				`paid_leave` DECIMAL(15,2)  NULL,
				`unpaid_leave` DECIMAL(15,2)  NULL,

				`rel_type` VARCHAR(100),

				PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
			}

			if (!$CI->db->field_exists('payslip_range' ,db_prefix() . 'hrp_payslips')) { 
				$CI->db->query('ALTER TABLE `' . db_prefix() . "hrp_payslips`

				ADD COLUMN `payslip_range` VARCHAR(500)

				;");
			}


		}

	}

