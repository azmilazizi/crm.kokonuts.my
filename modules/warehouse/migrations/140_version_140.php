<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_140 extends App_module_migration
{
	public function up()
	{
		$CI = &get_instance();

		if ($CI->db->field_exists('current_number', db_prefix() . 'wh_loss_adjustment_detail')) {
			$CI->db->query('ALTER TABLE `' . db_prefix() . 'wh_loss_adjustment_detail`
				MODIFY `current_number` decimal(15,2) NULL
			;');
		}

		if ($CI->db->field_exists('updates_number', db_prefix() . 'wh_loss_adjustment_detail')) {
			$CI->db->query('ALTER TABLE `' . db_prefix() . 'wh_loss_adjustment_detail`
				MODIFY `updates_number` decimal(15,2) NULL
			;');
		}
	}
}
