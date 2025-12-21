<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_118 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance(); 

        update_option('records_time1', date("Y-m-d H:i:s", strtotime('now -10 minutes')));
        update_option('records_time2', date("Y-m-d H:i:s", strtotime('now -10 minutes')));
        update_option('records_time3', date("Y-m-d H:i:s", strtotime('now -10 minutes')));
        update_option('records_time4', date("Y-m-d H:i:s", strtotime('now -10 minutes')));
        update_option('records_time5', date("Y-m-d H:i:s", strtotime('now -10 minutes')));
        update_option('records_time6', date("Y-m-d H:i:s", strtotime('now -10 minutes')));
        update_option('records_time7', date("Y-m-d H:i:s", strtotime('now -10 minutes')));
        update_option('records_time8', date("Y-m-d H:i:s", strtotime('now -10 minutes')));
    }
}
