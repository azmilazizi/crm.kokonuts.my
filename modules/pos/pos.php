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
hooks()->add_action('admin_init', 'pos_run_migrations');
hooks()->add_action('admin_init', 'pos_run_module_migrations');

register_activation_hook(POS_MODULE_NAME, 'pos_module_activation_hook');

function pos_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

function pos_module_init_menu_items()
{
    $CI = &get_instance();
    if (has_permission('pos', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('pos', [
            'slug'     => 'pos',
            'name'     => 'POS',
            'icon'     => 'fa fa-desktop',
            'href'     => admin_url('pos'),
            'position' => 50,
        ]);

        $CI->app_menu->add_sidebar_children_item('pos', [
            'slug'     => 'pos-dashboard',
            'name'     => 'Dashboard',
            'href'     => admin_url('pos/dashboard'),
            'position' => 1,
        ]);

        $CI->app_menu->add_sidebar_children_item('pos', [
            'slug'     => 'pos-transactions',
            'name'     => 'Transactions',
            'href'     => admin_url('pos/transactions'),
            'position' => 2,
        ]);

        $CI->app_menu->add_sidebar_children_item('pos', [
            'slug'     => 'pos-products',
            'name'     => 'Products',
            'href'     => admin_url('pos/products'),
            'position' => 3,
        ]);

        $CI->app_menu->add_sidebar_children_item('pos', [
            'slug'     => 'pos-modifiers',
            'name'     => 'Modifiers',
            'href'     => admin_url('pos/modifiers'),
            'position' => 4,
        ]);

        $CI->app_menu->add_sidebar_children_item('pos', [
            'slug'     => 'pos-settings',
            'name'     => 'Settings',
            'href'     => admin_url('pos/settings'),
            'position' => 99,
        ]);
    }
}

function pos_run_migrations()
{
    // Runs pending schema changes without requiring module reactivation.
    // Each block is idempotent — safe to check on every request.
    if (get_option('pos_db_version') === '2') {
        return;
    }

    $CI = &get_instance();

    $rename = [
        'pos_api_tokens' => [['store_id', 'warehouse_id', 'INT(11) NULL']],
        'pos_receipts'   => [['store_id', 'warehouse_id', 'INT(11) NOT NULL DEFAULT 0']],
        'pos_shifts'     => [['store_id', 'warehouse_id', 'INT(11) NOT NULL DEFAULT 0']],
    ];

    foreach ($rename as $table => $columns) {
        if (!$CI->db->table_exists(db_prefix() . $table)) { continue; }
        $existing = array_column($CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . $table . '`')->result_array(), 'Field');
        foreach ($columns as [$old, $new, $def]) {
            if (in_array($old, $existing) && !in_array($new, $existing)) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . $table . '` CHANGE `' . $old . '` `' . $new . '` ' . $def);
            }
        }
    }

    $rename_json = ['pos_employees', 'pos_payment_types', 'pos_bundles', 'pos_promotions'];
    foreach ($rename_json as $table) {
        if (!$CI->db->table_exists(db_prefix() . $table)) { continue; }
        $existing = array_column($CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . $table . '`')->result_array(), 'Field');
        if (in_array('store_ids', $existing) && !in_array('warehouse_ids', $existing)) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . $table . '` CHANGE `store_ids` `warehouse_ids` TEXT NULL');
        }
    }

    update_option('pos_db_version', '2');
}

function pos_run_module_migrations()
{
    $migration = new App_module_migration('pos');
    $migration->to_latest();
}

function pos_permissions()
{
    $capabilities = ['view', 'create', 'edit', 'delete'];
    foreach (['pos_stores', 'pos_categories', 'pos_employees', 'pos_modifiers', 'pos_payment_types', 'pos_receipts', 'pos_refunds'] as $feature) {
        register_staff_capabilities($feature, $capabilities, _l('pos'));
    }
}
