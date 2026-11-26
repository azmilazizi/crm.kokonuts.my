<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Affiliate
Description: Numbered affiliate product catalogue with public links and click analytics.
Version: 1.0.2
Requires at least: 2.3.*
*/

define('AFFILIATE_MODULE_NAME', 'affiliate');

hooks()->add_action('admin_init', 'affiliate_module_init_menu_items');
hooks()->add_action('admin_init', 'affiliate_permissions');
hooks()->add_action('init', 'affiliate_run_module_migrations');
register_activation_hook(AFFILIATE_MODULE_NAME, 'affiliate_module_activation_hook');

function affiliate_module_activation_hook()
{
    require_once(__DIR__ . '/install.php');
    affiliate_create_tables();
}

function affiliate_module_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('affiliate_products', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('affiliate', [
            'slug' => 'affiliate', 'name' => 'Affiliate', 'icon' => 'fa fa-link',
            'href' => admin_url('affiliate'), 'position' => 53,
        ]);
        $CI->app_menu->add_sidebar_children_item('affiliate', [
            'slug' => 'affiliate-products', 'name' => 'Products',
            'href' => admin_url('affiliate'), 'position' => 1,
        ]);
        $CI->app_menu->add_sidebar_children_item('affiliate', [
            'slug' => 'affiliate-categories', 'name' => 'Categories',
            'href' => admin_url('affiliate/categories'), 'position' => 2,
        ]);
        $CI->app_menu->add_sidebar_children_item('affiliate', [
            'slug' => 'affiliate-analytics', 'name' => 'Click Analytics',
            'href' => admin_url('affiliate/analytics'), 'position' => 3,
        ]);
        $CI->app_menu->add_sidebar_children_item('affiliate', [
            'slug' => 'affiliate-settings', 'name' => 'Public Page Settings',
            'href' => admin_url('affiliate/settings'), 'position' => 4,
        ]);
    }
}

function affiliate_run_module_migrations()
{
    $migration = new App_module_migration(AFFILIATE_MODULE_NAME);
    $migration->to_latest();
}

function affiliate_permissions()
{
    $capabilities['capabilities'] = [
        'view' => _l('permission_view'), 'create' => _l('permission_create'),
        'edit' => _l('permission_edit'), 'delete' => _l('permission_delete'),
    ];
    register_staff_capabilities('affiliate_products', $capabilities, 'Affiliate Products');
}
