<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        require_once(dirname(__DIR__) . '/install.php');
        affiliate_create_tables();
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'affiliate_clicks`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'affiliate_products`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'affiliate_categories`');
    }
}
