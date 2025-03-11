<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_119 extends App_module_migration
{
	public function up()
	{
		$CI = &get_instance();
		add_option('omni_sale_hide_shipping_fee', 0, 1);
	}
}
