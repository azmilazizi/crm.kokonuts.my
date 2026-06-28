<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|   example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|   http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|   $route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|   $route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|   $route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples: my-controller/index -> my_controller/index
|       my-controller/my-method -> my_controller/my_method
*/

$route['default_controller']   = 'clients';
$route['404_override']         = '';
$route['translate_uri_dashes'] = false;

/**
 * Dashboard clean route
 */
$route['admin'] = 'admin/dashboard';

/**
 * Misc controller routes
 */
$route['admin/access_denied'] = 'admin/misc/access_denied';
$route['admin/not_found']     = 'admin/misc/not_found';

/**
 * Staff Routes
 */
$route['admin/profile']           = 'admin/staff/profile';
$route['admin/profile/(:num)']    = 'admin/staff/profile/$1';
$route['admin/tasks/view/(:any)'] = 'admin/tasks/index/$1';

/**
 * Items search rewrite
 */
$route['admin/items/search'] = 'admin/invoice_items/search';

/**
 * In case if client access directly to url without the arguments redirect to clients url
 */
$route['/'] = 'clients';

/**
 * @deprecated
 */
$route['viewinvoice/(:num)/(:any)'] = 'invoice/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['invoice/(:num)/(:any)'] = 'invoice/index/$1/$2';

/**
 * @deprecated
 */
$route['viewestimate/(:num)/(:any)'] = 'estimate/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['estimate/(:num)/(:any)'] = 'estimate/index/$1/$2';
$route['subscription/(:any)']    = 'subscription/index/$1';

/**
 * @deprecated
 */
$route['viewproposal/(:num)/(:any)'] = 'proposal/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['proposal/(:num)/(:any)'] = 'proposal/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['contract/(:num)/(:any)'] = 'contract/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['knowledge-base']                 = 'knowledge_base/index';
$route['knowledge-base/search']          = 'knowledge_base/search';
$route['knowledge-base/article']         = 'knowledge_base/index';
$route['knowledge-base/article/(:any)']  = 'knowledge_base/article/$1';
$route['knowledge-base/category']        = 'knowledge_base/index';
$route['knowledge-base/category/(:any)'] = 'knowledge_base/category/$1';

/**
 * @deprecated 2.2.0
 */
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'add_kb_answer') === false) {
    $route['knowledge-base/(:any)']         = 'knowledge_base/article/$1';
    $route['knowledge_base/(:any)']         = 'knowledge_base/article/$1';
    $route['clients/knowledge_base/(:any)'] = 'knowledge_base/article/$1';
    $route['clients/knowledge-base/(:any)'] = 'knowledge_base/article/$1';
}

/**
 * @deprecated 2.2.0
 * Fallback for auth clients area, changed in version 2.2.0
 */
$route['clients/reset_password']  = 'authentication/reset_password';
$route['clients/forgot_password'] = 'authentication/forgot_password';
$route['clients/logout']          = 'authentication/logout';
$route['clients/register']        = 'authentication/register';
$route['clients/login']           = 'authentication/login';

// Aliases for short routes
$route['reset_password']  = 'authentication/reset_password';
$route['forgot_password'] = 'authentication/forgot_password';
$route['login']           = 'authentication/login';
$route['logout']          = 'authentication/logout';
$route['register']        = 'authentication/register';

/**
 * Terms and conditions and Privacy Policy routes
 */
$route['terms-and-conditions'] = 'terms_and_conditions';
$route['privacy-policy']       = 'privacy_policy';

/**
 * @since 2.3.0
 * Routes for admin/modules URL because Modules.php class is used in application/third_party/MX
 */
$route['admin/modules']               = 'admin/mods';
$route['admin/modules/(:any)']        = 'admin/mods/$1';
$route['admin/modules/(:any)/(:any)'] = 'admin/mods/$1/$2';

// Public single ticket route
$route['forms/tickets/(:any)'] = 'forms/public_ticket/$1';

/**
 * @since  2.3.0
 * Route for clients set password URL, because it's using the same controller for staff to
 * If user addded block /admin by .htaccess this won't work, so we need to rewrite the URL
 * In future if there is implementation for clients set password, this route should be removed
 */
$route['authentication/set_password/(:num)/(:num)/(:any)'] = 'admin/authentication/set_password/$1/$2/$3';

// For backward compatilibilty
$route['survey/(:num)/(:any)'] = 'surveys/participate/index/$1/$2';

/**
 * API route registration
 */
$api_endpoint_config_path = APPPATH . 'config/api_endpoints.php';
$api_routes               = [];

if (file_exists($api_endpoint_config_path)) {
    $api_endpoint_config = include $api_endpoint_config_path;

    if (is_array($api_endpoint_config) && $api_endpoint_config !== []) {
        $api_registry_path = APPPATH . 'libraries/ApiEndpointRegistry.php';

        if (file_exists($api_registry_path)) {
            require_once $api_registry_path;

            $registry  = new ApiEndpointRegistry($api_endpoint_config);
            $api_routes = $registry->buildRouteMap();
        }
    }
}

if (file_exists(APPPATH . 'config/my_routes.php')) {
    include_once(APPPATH . 'config/my_routes.php');
}

if ($api_routes !== []) {
    foreach ($api_routes as $uri => $target) {
        $route[$uri] = $target;
    }
}

/**
 * Support legacy /warehouse/api/v1/* requests by forwarding them to the
 * warehouse API controller so clients that relied on the previous path keep
 * working.
 */
$route['warehouse/api/v1/goods_receipt']['post'] = 'warehouse/api_warehouse/goods_receipts';
$route['warehouse/api/goods_receipt']['post']    = 'warehouse/api_warehouse/goods_receipts';
$route['warehouse/api/v1/(.+)'] = 'warehouse/api_warehouse/$1';
$route['warehouse/api/(.+)']    = 'warehouse/api_warehouse/$1';

/**
 * Manager / Owner mobile app API routes
 */
$route['manager/api/auth/login']                    = 'manager/api/auth_login';
$route['manager/api/auth/logout']                   = 'manager/api/auth_logout';
$route['manager/api/auth/me']                       = 'manager/api/auth_me';
$route['manager/api/warehouses']                    = 'manager/api/warehouses';
$route['manager/api/dashboard/summary']             = 'manager/api/dashboard_summary';
$route['manager/api/dashboard/daily-trend']         = 'manager/api/dashboard_daily_trend';
$route['manager/api/dashboard/hourly-trend']        = 'manager/api/dashboard_hourly_trend';
$route['manager/api/dashboard/payment-breakdown']   = 'manager/api/dashboard_payment_breakdown';
$route['manager/api/dashboard/recent-shifts']       = 'manager/api/dashboard_recent_shifts';
$route['manager/api/sales/(:any)']                  = 'manager/api/sale/$1';
$route['manager/api/sales']                         = 'manager/api/sales';
$route['manager/api/shifts/(:num)']                 = 'manager/api/shift/$1';
$route['manager/api/shifts']                        = 'manager/api/shifts';
$route['manager/api/reports/top-products']          = 'manager/api/reports_top_products';
$route['manager/api/reports/product-sales']         = 'manager/api/reports_product_sales';
$route['manager/api/reports/staff-performance']     = 'manager/api/reports_staff_performance';
$route['manager/api/inventory/transactions']        = 'manager/api/inventory_transactions';
$route['manager/api/inventory']                     = 'manager/api/inventory';
$route['manager/api/fcm-token']                     = 'manager/api/fcm_token';
$route['manager/api/notification-preferences']      = 'manager/api/notification_preferences';
