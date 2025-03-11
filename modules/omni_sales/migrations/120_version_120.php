<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_120 extends App_module_migration
{
	public function up()
	{
		$CI = &get_instance();
		add_option('omni_sell_the_warehouse_assigned', 0);
	}
}
