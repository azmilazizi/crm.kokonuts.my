<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: POS
Description: Point of Sale module for managing stores, categories, items, employees, modifiers, payment types, receipts, and refunds.
Version: 1.1.3
Requires at least: 2.3.*
*/

define('POS_MODULE_NAME', 'pos');

hooks()->add_action('admin_init', 'pos_module_init_menu_items');
hooks()->add_action('admin_init', 'pos_permissions');
hooks()->add_action('admin_init', 'pos_run_migrations');
hooks()->add_action('admin_init', 'pos_run_module_migrations');
hooks()->add_action('after_cron_run', 'pos_process_fd_sync_queue');

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
            'slug'     => 'pos-shifts',
            'name'     => 'Shifts',
            'href'     => admin_url('pos/shifts'),
            'position' => 3,
        ]);

        $CI->app_menu->add_sidebar_children_item('pos', [
            'slug'     => 'pos-products',
            'name'     => 'Products',
            'href'     => admin_url('pos/products'),
            'position' => 4,
        ]);

        $CI->app_menu->add_sidebar_children_item('pos', [
            'slug'     => 'pos-menu-layout',
            'name'     => 'FD Menu Layout',
            'href'     => admin_url('pos/menu_layout'),
            'position' => 5,
        ]);

        $CI->app_menu->add_sidebar_children_item('pos', [
            'slug'     => 'pos-modifiers',
            'name'     => 'Modifiers',
            'href'     => admin_url('pos/modifiers'),
            'position' => 6,
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
    // Global-only CRUD: settings-like features that apply across all warehouses
    $global_crud = [
        'capabilities' => [
            'view'   => _l('pos_perm_view'),
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
        ],
    ];

    // Warehouse-scoped CRUD: data features where access can be limited to assigned warehouse
    $warehouse_crud = [
        'capabilities' => [
            'view_own' => _l('pos_perm_view_own'),
            'view'     => _l('pos_perm_view_all'),
            'create'   => _l('permission_create'),
            'edit'     => _l('permission_edit'),
            'delete'   => _l('permission_delete'),
        ],
    ];

    register_staff_capabilities('pos_stores',        $warehouse_crud, _l('pos_perm_outlets'));
    register_staff_capabilities('pos_categories',    $global_crud,    _l('pos_perm_categories'));
    register_staff_capabilities('pos_employees',     $warehouse_crud, _l('pos_perm_staff'));
    register_staff_capabilities('pos_modifiers',     $global_crud,    _l('pos_perm_modifiers'));
    register_staff_capabilities('pos_payment_types', $global_crud,    _l('pos_perm_payment_methods'));
    register_staff_capabilities('pos_receipts',      $warehouse_crud, _l('pos_perm_sales'));
    register_staff_capabilities('pos_refunds',       $warehouse_crud, _l('pos_perm_refunds'));
}

// Drains the "notify GrabFood (and future platforms) of a menu sync" queue that the
// Food Delivery Menu Layout's Sync button writes to. Runs on the app's existing cron
// tick (every few minutes) instead of blocking the admin's click on an outbound API call.
function pos_process_fd_sync_queue()
{
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'pos_fd_sync_queue')) {
        return;
    }

    $pending = $CI->db->where('status', 'pending')->order_by('id', 'ASC')->limit(20)
        ->get(db_prefix() . 'pos_fd_sync_queue')->result_array();
    if (empty($pending)) {
        return;
    }

    $CI->load->model('pos/pos_grabfood_model');

    foreach ($pending as $job) {
        try {
            if ($job['channel'] === 'grabfood') {
                $CI->pos_grabfood_model->notify_menu_updated($job['warehouse_id']);
            }
            $CI->db->where('id', $job['id'])->update(db_prefix() . 'pos_fd_sync_queue', [
                'status'       => 'done',
                'processed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            $attempts = (int) $job['attempts'] + 1;
            $CI->db->where('id', $job['id'])->update(db_prefix() . 'pos_fd_sync_queue', [
                'attempts'     => $attempts,
                'status'       => $attempts >= 3 ? 'failed' : 'pending',
                'last_error'   => $e->getMessage(),
                'processed_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
