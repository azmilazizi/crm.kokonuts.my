<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_141 extends App_module_migration
{
	public function up()
	{
		$CI = &get_instance();

		if (!$CI->db->field_exists('batch_size', db_prefix() . 'goods_receipt_detail')) {
			$CI->db->query('ALTER TABLE `' . db_prefix() . 'goods_receipt_detail`
				ADD COLUMN `batch_size` DECIMAL(15,4) NULL,
				ADD COLUMN `units_per_batch` DECIMAL(15,4) NULL');
		}

		// Backfill so the new batch_size * units_per_batch formula reproduces
		// today's stored quantities/unit_price exactly for existing rows —
		// they were always effectively "1 batch of quantities units".
		$CI->db->query('UPDATE `' . db_prefix() . 'goods_receipt_detail`
			SET `batch_size` = `quantities`, `units_per_batch` = 1
			WHERE `batch_size` IS NULL');
	}
}
