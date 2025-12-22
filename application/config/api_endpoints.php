<?php

defined('BASEPATH') or exit('No direct script access allowed');

//
// API endpoint definitions
//
// Each version entry defines a prefix (e.g. api/v1) and a list of resources. A resource
// maps to a module controller and contains the route segments that should resolve to the
// corresponding controller actions. Update this file when adding, removing, or renaming
// API endpoints to keep the routing layer consistent across the application.
//

$expensesResource = [
    'group_prefix' => 'expenses',
    'controller'   => 'api_expenses',
    'routes'       => [
        [
            'path'                => '',
            'action'              => 'expenses',
            'methods'             => ['GET'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'categories',
            'action'              => 'categories',
            'methods'             => ['GET'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => '(:num)',
            'action'              => 'expense/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => '(:num)/attachment',
            'action'              => 'expense_attachment/$1',
            'methods'             => ['GET', 'POST', 'DELETE'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'bulk_payments',
            'action'              => 'bulk_payments',
            'methods'             => ['POST', 'DELETE'],
            'with_trailing_slash' => true,
        ],
    ],
];

$invoicesResource = [
    'group_prefix' => 'invoices',
    'controller'   => 'api_invoices',
    'routes'       => [
        [
            'path'                => '',
            'action'              => 'invoices',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => '(:num)',
            'action'              => 'invoice/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
    ],
];

$invoicePaymentRecordsResource = [
    'group_prefix' => 'invoice_payment_records',
    'controller'   => 'api_invoice_payment_records',
    'routes'       => [
        [
            'path'                => '',
            'action'              => 'invoice_payment_records',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => '(:num)',
            'action'              => 'invoice_payment_record/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
    ],
];

$omniSalesInstallResource = [
    'group_prefix' => 'omni_sales',
    'controller'   => 'omni_sales/api_install_app',
    'routes'       => [
        [
            'path'                => 'install/verify',
            'action'              => 'verify',
            'methods'             => ['POST'],
            'with_trailing_slash' => true,
        ],
    ],
];

$omniSalesInstallResource = [
    'group_prefix' => 'omni_sales',
    'controller'   => 'omni_sales/api_install_app',
    'routes'       => [
        [
            'path'                => 'install/verify',
            'action'              => 'verify',
            'methods'             => ['POST'],
            'with_trailing_slash' => true,
        ],
    ],
];

$accountingResource = [
    'group_prefix' => 'accounting',
    'controller'   => 'accounting/api_accounting',
    'routes'       => [
        [
            'path'                => 'accounts',
            'action'              => 'accounts',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'account_types',
            'action'              => 'account_type',
            'methods'             => ['GET'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'account/(:num)',
            'action'              => 'account/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'account_type/(:num)/account_type_detail',
            'action'              => 'account_type_detail/$1',
            'methods'             => ['GET'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'bills',
            'action'              => 'bills',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'bill/(:num)',
            'action'              => 'bill/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'bills/(:num)',
            'action'              => 'bill/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'bill/(:num)/payment',
            'action'              => 'bill_payments_by_bill/$1',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'bill/(:num)/payment/(:num)',
            'action'              => 'bill_payment_for_bill/$1/$2',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
    ],
];

$accountingModuleResource = array_merge($accountingResource, [
    'group_prefix' => '',
]);

$expensesModuleResource = array_merge($expensesResource, [
    'group_prefix' => '',
]);

$purchaseResource = [
    'group_prefix' => 'purchase',
    'controller'   => 'purchase/api_purchase',
    'routes'       => [
        [
            'path'                => '',
            'action'              => 'index',
            'methods'             => ['GET'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'vendors',
            'action'              => 'vendors',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'vendors/(:any)',
            'action'              => 'vendors/$1',
            'methods'             => ['GET', 'PUT'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'vendor/(:any)',
            'action'              => 'vendors/$1',
            'methods'             => ['GET', 'PUT'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase-orders',
            'action'              => 'purchase_orders',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase_orders',
            'action'              => 'purchase_orders',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase-orders/(:any)',
            'action'              => 'purchase_orders/$1',
            'methods'             => ['GET', 'PUT'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase-orders/(:num)/attachments',
            'action'              => 'purchase_order_attachments/$1',
            'methods'             => ['POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase_orders/(:any)',
            'action'              => 'purchase_orders/$1',
            'methods'             => ['GET', 'PUT'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase_orders/(:num)/attachments',
            'action'              => 'purchase_order_attachments/$1',
            'methods'             => ['POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase-order/(:any)',
            'action'              => 'purchase_orders/$1',
            'methods'             => ['GET', 'PUT'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase_order/(:any)',
            'action'              => 'purchase_orders/$1',
            'methods'             => ['GET', 'PUT'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'purchase_order/(:num)/attachments',
            'action'              => 'purchase_order_attachments/$1',
            'methods'             => ['POST'],
            'with_trailing_slash' => true,
        ],
    ],
];

$dashboardResource = [
    'group_prefix' => '',
    'controller'   => 'api_dashboard',
    'routes'       => [
        [
            'path'                => 'expenses_percentage_by_type',
            'action'              => 'expenses_percentage_by_type',
            'methods'             => ['GET'],
            'with_trailing_slash' => true,
        ],
    ],
];

$journalEntriesResource = [
    'group_prefix' => '',
    'controller'   => 'api_journal_entries',
    'routes'       => [
        [
            'path'                => 'journal_entries',
            'action'              => 'journal_entries',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'journal_entries/(:num)',
            'action'              => 'journal_entry/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
    ],
];

$transfersResource = [
    'group_prefix' => '',
    'controller'   => 'api_transfers',
    'routes'       => [
        [
            'path'                => 'transfers',
            'action'              => 'transfers',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'transfer/(:num)',
            'action'              => 'transfer/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
    ],
];

$accountHistoryResource = [
    'group_prefix' => '',
    'controller'   => 'api_account_history',
    'routes'       => [
        [
            'path'                => 'account_history',
            'action'              => 'account_history',
            'methods'             => ['GET', 'POST'],
            'with_trailing_slash' => true,
        ],
        [
            'path'                => 'account_history/(:num)',
            'action'              => 'account_history_item/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
            'with_trailing_slash' => true,
        ],
    ],
];

$purchaseModuleResource = array_merge($purchaseResource, [
    'group_prefix' => '',
]);

return [
    'default_version' => 'v1',
        'versions'        => [
        'v1' => [
            'prefix'    => 'api/v1',
            'resources' => [
                [
                    'group_prefix' => '',
                    'controller'   => 'api_payment_modes',
                    'routes'       => [
                        [
                            'path'                => 'payment_mode',
                            'action'              => 'payment_modes',
                            'methods'             => ['GET'],
                            'with_trailing_slash' => true,
                        ],
                        [
                            'path'                => 'payment_mode/(:any)',
                            'action'              => 'payment_mode/$1',
                            'methods'             => ['GET'],
                            'with_trailing_slash' => true,
                        ],
                    ],
                ],
                [
                    'group_prefix' => '',
                    'controller'   => 'api_payments',
                    'routes'       => [
                        [
                            'path'                => 'payments',
                            'action'              => 'payments',
                            'methods'             => ['GET'],
                            'with_trailing_slash' => true,
                        ],
                    ],
                ],
                [
                    'group_prefix' => 'goals',
                    'controller'   => 'goals/api_goals',
                    'routes'       => [
                        [
                            'path'    => '',
                            'action'  => 'goals',
                            'methods' => ['GET', 'POST'],
                        ],
                        [
                            'path'    => '(:num)',
                            'action'  => 'goal/$1',
                            'methods' => ['GET', 'PUT'],
                        ],
                    ],
                ],
                [
                    'group_prefix' => 'warehouse',
                    'controller'   => 'warehouse/api_warehouse',
                    'routes'       => [
                        [
                            'path'    => 'warehouses',
                            'action'  => 'warehouses',
                            'methods' => ['GET'],
                        ],
                        [
                            'path'    => 'items',
                            'action'  => 'items',
                            'methods' => ['GET', 'POST'],
                        ],
                        [
                            'path'    => 'items/(:num)',
                            'action'  => 'item/$1',
                            'methods' => ['GET', 'PUT'],
                        ],
                        [
                            'path'    => 'units',
                            'action'  => 'units',
                            'methods' => ['GET'],
                        ],
                        [
                            'path'    => 'item_groups',
                            'action'  => 'item_groups',
                            'methods' => ['GET'],
                        ],
                    ],
                ],
                [
                    'group_prefix' => '',
                    'controller'   => 'api_options',
                    'routes'       => [
                        [
                            'path'                => 'options',
                            'action'              => 'options',
                            'methods'             => ['GET'],
                            'with_trailing_slash' => true,
                        ],
                        [
                            'path'                => 'option/(:any)',
                            'action'              => 'option/$1',
                            'methods'             => ['GET'],
                            'with_trailing_slash' => true,
                        ],
                    ],
                ],
                [
                    'group_prefix' => 'timesheets',
                    'controller'   => 'timesheets/api_timesheets',
                    'routes'       => [
                        [
                            'path'    => 'login',
                            'action'  => 'login',
                            'methods' => ['POST'],
                        ],
                        [
                            'path'    => 'logout',
                            'action'  => 'logout',
                            'methods' => ['POST'],
                        ],
                        [
                            'path'    => 'attendance/check-in-out',
                            'action'  => 'check_in_out',
                            'methods' => ['POST'],
                        ],
                        [
                            'path'    => 'leave-applications',
                            'action'  => 'add_leave_application',
                            'methods' => ['POST'],
                        ],
                        [
                            'path'    => 'staff',
                            'action'  => 'staff',
                            'methods' => ['GET'],
                        ],
                        [
                            'path'    => 'staff/(:any)',
                            'action'  => 'staff/$1',
                            'methods' => ['GET'],
                        ],
                        [
                            'path'    => 'leave-types/custom',
                            'action'  => 'type_of_leave_custom',
                            'methods' => ['GET'],
                        ],
                        [
                            'path'    => 'leave-days/calculate',
                            'action'  => 'calculate_number_days_off',
                            'methods' => ['POST'],
                        ],
                        [
                            'path'    => 'leave-days/dates',
                            'action'  => 'get_date_leave',
                            'methods' => ['POST'],
                        ],
                        [
                            'path'    => 'attendance/history',
                            'action'  => 'get_history_check_in_out',
                            'methods' => ['POST'],
                        ],
                        [
                            'path'    => 'options',
                            'action'  => 'get_timesheets_option',
                            'methods' => ['GET'],
                        ],
                        [
                            'path'    => 'options/(:any)',
                            'action'  => 'get_timesheets_option/$1',
                            'methods' => ['GET'],
                        ],
                        [
                            'path'    => 'attendance/route-points',
                            'action'  => 'get_route_point_check_in_out',
                            'methods' => ['POST'],
                        ],
                        [
                            'path'    => 'server-time',
                            'action'  => 'server_time',
                            'methods' => ['GET'],
                        ],
                    ],
                ],
                $accountingResource,
                $purchaseResource,
                $expensesResource,
                $invoicesResource,
                $invoicePaymentRecordsResource,
                $omniSalesInstallResource,
            ],
        ],
        'default' => [
            'prefix'    => 'api',
            'resources' => [
                $expensesResource,
                $invoicesResource,
                $invoicePaymentRecordsResource,
                $omniSalesInstallResource,
            ],
        ],
        'accounting_default' => [
            'prefix'    => 'accounting/api',
            'resources' => [
                $accountingModuleResource,
                $journalEntriesResource,
                $transfersResource,
                $accountHistoryResource,
            ],
        ],
        'purchase_default' => [
            'prefix'    => 'purchase/api',
            'resources' => [
                $purchaseModuleResource,
            ],
        ],
        'expenses_v1' => [
            'prefix'    => 'expenses/api/v1',
            'resources' => [
                $expensesModuleResource,
            ],
        ],
        'expenses_default' => [
            'prefix'    => 'expenses/api',
            'resources' => [
                $expensesModuleResource,
            ],
        ],
        'accounting_v1' => [
            'prefix'    => 'accounting/api/v1',
            'resources' => [
                $accountingModuleResource,
                $journalEntriesResource,
                $transfersResource,
                $accountHistoryResource,
            ],
        ],
        'purchase_v1' => [
            'prefix'    => 'purchase/api/v1',
            'resources' => [
                $purchaseModuleResource,
            ],
        ],
        'dashboard' => [
            'prefix'    => 'dashboard',
            'resources' => [
                $dashboardResource,
            ],
        ],
    ],
];
