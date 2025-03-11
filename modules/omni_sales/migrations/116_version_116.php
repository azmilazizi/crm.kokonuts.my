<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_116 extends App_module_migration
{
	public function up()
	{
		$CI = &get_instance();   
		
		add_option('omni_pos_shipping_fee_form', 'fixed');

		if (!$CI->db->field_exists('shipping_form' ,db_prefix() . 'cart')) { 
			$CI->db->query('ALTER TABLE `' . db_prefix() . "cart`
				ADD COLUMN `shipping_form` VARCHAR(50) null DEFAULT 'fixed',   
				ADD COLUMN `shipping_value` DECIMAL(15,2) null DEFAULT '0.00'  
				;");
		}

		add_option('omni_synch_invoice_from_woo', '');

		if (!$CI->db->field_exists('woo_order_number' ,db_prefix() . 'invoices')) { 
			$CI->db->query('ALTER TABLE `' . db_prefix() . "invoices`
				ADD COLUMN `woo_order_number` VARCHAR(200) null
				;");
		}
	}
}
