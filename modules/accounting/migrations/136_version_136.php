<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_136 extends App_module_migration
{
    public function up()
    {
        // Version 1.3.6
        add_option('acc_invoice_discount_automatic_conversion', 1);
    }
}
