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
            'path'                => '(:num)',
            'action'              => 'expense/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
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
            'path'                => 'account/(:num)',
            'action'              => 'account/$1',
            'methods'             => ['GET', 'PUT', 'DELETE'],
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
            'path'                => 'purchase-orders',
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
                            'path'    => 'items',
                            'action'  => 'items',
                            'methods' => ['GET', 'POST'],
                        ],
                        [
                            'path'    => 'items/(:num)',
                            'action'  => 'item/$1',
                            'methods' => ['GET', 'PUT'],
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
            ],
        ],
        'default' => [
            'prefix'    => 'api',
            'resources' => [
                $expensesResource,
            ],
        ],
        'accounting_default' => [
            'prefix'    => 'accounting/api',
            'resources' => [
                $accountingModuleResource,
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
            ],
        ],
        'purchase_v1' => [
            'prefix'    => 'purchase/api/v1',
            'resources' => [
                $purchaseModuleResource,
            ],
        ],
    ],
];
