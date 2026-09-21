<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Franchise
Description: Franchise outlet ownership and cashback settlement for Kokonuts POS.
Version: 1.0.2
Requires at least: 2.3.*
*/

define('FRANCHISE_MODULE_NAME', 'franchise');

register_language_files(FRANCHISE_MODULE_NAME, [FRANCHISE_MODULE_NAME]);

hooks()->add_action('admin_init', 'franchise_module_init_menu_items');
hooks()->add_action('admin_init', 'franchise_permissions');
hooks()->add_action('admin_init', 'franchise_run_module_migrations');
hooks()->add_action('after_payment_added', 'franchise_sync_sale_order_payment_status');

register_activation_hook(FRANCHISE_MODULE_NAME, 'franchise_module_activation_hook');

function franchise_module_activation_hook()
{
    require_once(__DIR__ . '/install.php');
}

function franchise_module_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('franchise', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('franchise', [
            'slug'     => 'franchise',
            'name'     => 'Franchise',
            'icon'     => 'fa fa-sitemap',
            'href'     => admin_url('franchise'),
            'position' => 52,
        ]);

        $CI->app_menu->add_sidebar_children_item('franchise', [
            'slug'     => 'franchise-franchisees',
            'name'     => 'Franchisees',
            'href'     => admin_url('franchise'),
            'position' => 1,
        ]);
    }
}

function franchise_run_module_migrations()
{
    $migration = new App_module_migration('franchise');
    $migration->to_latest();
}

/**
 * after_payment_added fires with the payment RECORD id, not the invoice id —
 * resolve the invoice it belongs to, then let Franchise_model decide whether
 * it belongs to a tracked franchise_sale_orders row.
 */
function franchise_sync_sale_order_payment_status($payment_id)
{
    $CI = &get_instance();
    $payment = $CI->db->select('invoiceid')->where('id', (int)$payment_id)
        ->get(db_prefix() . 'invoicepaymentrecords')->row_array();

    if (!$payment || empty($payment['invoiceid'])) {
        return;
    }

    $CI->load->model('franchise/franchise_model');
    $CI->franchise_model->sync_sale_order_status_from_invoice((int)$payment['invoiceid']);
}

function franchise_permissions()
{
    $capabilities['capabilities'] = [
        'view'   => _l('permission_view'),
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('franchise', $capabilities, _l('franchise_perm_group'));
}
