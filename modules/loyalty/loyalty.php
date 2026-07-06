<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Loyalty
Description: Customer loyalty points and cashback system for Kokonuts POS.
Version: 1.1.0
Requires at least: 2.3.*
*/

define('LOYALTY_MODULE_NAME', 'loyalty');

register_language_files(LOYALTY_MODULE_NAME, [LOYALTY_MODULE_NAME]);

hooks()->add_action('admin_init', 'loyalty_module_init_menu_items');
hooks()->add_action('admin_init', 'loyalty_permissions');
hooks()->add_action('admin_init', 'loyalty_run_module_migrations');

register_activation_hook(LOYALTY_MODULE_NAME, 'loyalty_module_activation_hook');

function loyalty_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

function loyalty_module_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('loyalty', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('loyalty', [
            'slug'     => 'loyalty',
            'name'     => 'Loyalty',
            'icon'     => 'fa fa-star',
            'href'     => admin_url('loyalty'),
            'position' => 51,
        ]);

        $CI->app_menu->add_sidebar_children_item('loyalty', [
            'slug'     => 'loyalty-dashboard',
            'name'     => 'Dashboard',
            'href'     => admin_url('loyalty/dashboard'),
            'position' => 1,
        ]);

        $CI->app_menu->add_sidebar_children_item('loyalty', [
            'slug'     => 'loyalty-members',
            'name'     => 'Members',
            'href'     => admin_url('loyalty/customers'),
            'position' => 2,
        ]);

        $CI->app_menu->add_sidebar_children_item('loyalty', [
            'slug'     => 'loyalty-transactions',
            'name'     => 'Transactions',
            'href'     => admin_url('loyalty/transactions'),
            'position' => 3,
        ]);

        $CI->app_menu->add_sidebar_children_item('loyalty', [
            'slug'     => 'loyalty-announcements',
            'name'     => 'Announcement',
            'href'     => admin_url('loyalty/announcements'),
            'position' => 4,
        ]);

        $CI->app_menu->add_sidebar_children_item('loyalty', [
            'slug'     => 'loyalty-promotions',
            'name'     => 'Event & Promotions',
            'href'     => admin_url('loyalty/promotions'),
            'position' => 5,
        ]);

        $CI->app_menu->add_sidebar_children_item('loyalty', [
            'slug'     => 'loyalty-blast-history',
            'name'     => 'Blast History',
            'href'     => admin_url('loyalty/blast_history'),
            'position' => 6,
        ]);

        if (has_permission('loyalty_customers', '', 'create')) {
            $CI->app_menu->add_sidebar_children_item('loyalty', [
                'slug'     => 'loyalty-import',
                'name'     => 'Import Members',
                'href'     => admin_url('loyalty/import_members'),
                'position' => 7,
            ]);
        }

        $CI->app_menu->add_sidebar_children_item('loyalty', [
            'slug'     => 'loyalty-reports',
            'name'     => 'Reports',
            'href'     => admin_url('loyalty/reports'),
            'position' => 8,
        ]);

        $CI->app_menu->add_sidebar_children_item('loyalty', [
            'slug'     => 'loyalty-sms-settings',
            'name'     => 'SMS Settings',
            'href'     => admin_url('loyalty/sms_settings'),
            'position' => 9,
        ]);
    }
}

function loyalty_run_module_migrations()
{
    $migration = new App_module_migration('loyalty');
    $migration->to_latest();
}

function loyalty_permissions()
{
    $capabilities['capabilities'] = [
        'view'   => _l('permission_view'),
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('loyalty_customers',    $capabilities, _l('loyalty_perm_members'));
    register_staff_capabilities('loyalty_transactions', $capabilities, _l('loyalty_perm_transactions'));
}
