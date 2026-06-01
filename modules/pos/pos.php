<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: POS
Description: Point of Sale module for managing stores, categories, items, employees, modifiers, payment types, receipts, and refunds.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('POS_MODULE_NAME', 'pos');

hooks()->add_action('admin_init', 'pos_module_init_menu_items');
hooks()->add_action('admin_init', 'pos_permissions');

function pos_module_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('pos', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('pos', [
            'slug'     => 'pos',
            'name'     => _l('pos'),
            'icon'     => 'fa fa-shopping-cart',
            'href'     => admin_url('pos'),
            'position' => 50,
        ]);
    }
}

function pos_permissions()
{
    $capabilities = ['view', 'create', 'edit', 'delete'];
    foreach (['pos_stores', 'pos_categories', 'pos_employees', 'pos_modifiers', 'pos_payment_types', 'pos_receipts', 'pos_refunds'] as $feature) {
        register_staff_capabilities($feature, $capabilities, _l('pos'));
    }
}
