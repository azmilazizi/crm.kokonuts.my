<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Keys match the full URI; values are controller/method (module name is prepended by the HMVC router).

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
$route['pos/api/v1/login']                          = 'api/login';
$route['pos/api/v1/me']                             = 'api/me';
$route['pos/api/v1/verify_passcode']                = 'api/verify_passcode';

// ---------------------------------------------------------------------------
// Stores
// ---------------------------------------------------------------------------
$route['pos/api/v1/stores']                        = 'api/stores';
$route['pos/api/v1/stores/(:num)']                 = 'api/store/$1';

// ---------------------------------------------------------------------------
// Categories / Item groups / Sub-groups
// ---------------------------------------------------------------------------
$route['pos/api/v1/categories']                    = 'api/categories';
$route['pos/api/v1/item_groups']                   = 'api/item_groups';
$route['pos/api/v1/sub_groups']                    = 'api/sub_groups';

// ---------------------------------------------------------------------------
// Items  (barcode must precede :num so it is matched first)
// ---------------------------------------------------------------------------
$route['pos/api/v1/items/barcode/(:any)']          = 'api/item_by_barcode/$1';
$route['pos/api/v1/items/(:num)']                  = 'api/item/$1';
$route['pos/api/v1/items']                         = 'api/items';

// ---------------------------------------------------------------------------
// Employees
// ---------------------------------------------------------------------------
$route['pos/api/v1/employees']                     = 'api/employees';
$route['pos/api/v1/employee_login']                = 'api/employee_login';

// ---------------------------------------------------------------------------
// Modifiers / Payment types / Payment modes / Taxes
// ---------------------------------------------------------------------------
$route['pos/api/v1/modifiers']                     = 'api/modifiers';
$route['pos/api/v1/payment_types']                 = 'api/payment_types';
$route['pos/api/v1/payment_modes']                 = 'api/payment_modes';
$route['pos/api/v1/taxes']                         = 'api/taxes';

// ---------------------------------------------------------------------------
// Bundles
// ---------------------------------------------------------------------------
$route['pos/api/v1/bundles/(:num)']                = 'api/bundle/$1';
$route['pos/api/v1/bundles']                       = 'api/bundles';

// ---------------------------------------------------------------------------
// Promotions
// ---------------------------------------------------------------------------
$route['pos/api/v1/promotions/validate']           = 'api/promotions_validate';
$route['pos/api/v1/promotions']                    = 'api/promotions';

// ---------------------------------------------------------------------------
// Shifts  (specific sub-routes before the bare :num catch-all)
// ---------------------------------------------------------------------------
$route['pos/api/v1/shifts/open']                   = 'api/shifts_open';
$route['pos/api/v1/shifts/current']                = 'api/shift_current';
$route['pos/api/v1/shifts/(:num)/cash_movement']   = 'api/shift_cash_movement/$1';
$route['pos/api/v1/shifts/(:num)/close']           = 'api/shift_close/$1';
$route['pos/api/v1/shifts/(:num)/report']          = 'api/shift_report/$1';
$route['pos/api/v1/shifts/(:num)']                 = 'api/shift/$1';

// ---------------------------------------------------------------------------
// Customers
// ---------------------------------------------------------------------------
$route['pos/api/v1/customers/search']                          = 'api/customers_search';
$route['pos/api/v1/customers/(:num)/cashback/redeem']          = 'api/customer_cashback_redeem/$1';
$route['pos/api/v1/customers/(:num)']                          = 'api/customer/$1';
$route['pos/api/v1/customers']                                 = 'api/customers_create';

// ---------------------------------------------------------------------------
// Loyalty
// ---------------------------------------------------------------------------
$route['pos/api/v1/loyalty/members']               = 'api/loyalty_members';
$route['pos/api/v1/loyalty/balance/(:num)']        = 'api/loyalty_balance/$1';
$route['pos/api/v1/loyalty/earn']                  = 'api/loyalty_earn';
$route['pos/api/v1/loyalty/redeem']                = 'api/loyalty_redeem';
$route['pos/api/v1/loyalty/register']              = 'api/loyalty_register';

// ---------------------------------------------------------------------------
// Receipt settings
// ---------------------------------------------------------------------------
$route['pos/api/v1/receipt_settings']              = 'api/receipt_settings';

// ---------------------------------------------------------------------------
// Customer Facing Display settings
// ---------------------------------------------------------------------------
$route['pos/api/v1/cfd_settings']                  = 'api/cfd_settings';

// ---------------------------------------------------------------------------
// Orders (Flutter-friendly alias for create_receipt)
// ---------------------------------------------------------------------------
$route['pos/api/v1/orders']                        = 'api/orders';

// ---------------------------------------------------------------------------
// Receipts
// ---------------------------------------------------------------------------
$route['pos/api/v1/receipts']                          = 'api/receipts';
$route['pos/api/v1/receipts/(:num)/refund']            = 'api/receipt_refund/$1';
$route['pos/api/v1/receipts/(:num)/cancel']            = 'api/receipt_cancel/$1';
$route['pos/api/v1/receipt/(:any)']                    = 'api/receipt/$1';
$route['pos/api/v1/create_receipt']                    = 'api/create_receipt';
$route['pos/api/v1/create_refund']                     = 'api/create_refund';

// ---------------------------------------------------------------------------
// Print Jobs (Flutter POS polls these — see Pos_grabfood_model::handle_order_state_update)
// ---------------------------------------------------------------------------
$route['pos/api/v1/print_jobs/(:num)/ack']             = 'api/print_job_ack/$1';
$route['pos/api/v1/print_jobs']                        = 'api/print_jobs';

// ---------------------------------------------------------------------------
// DuitNow QR (Chip-in)
// ---------------------------------------------------------------------------
$route['pos/api/v1/duitnow/settings']                  = 'api/duitnow_settings';
$route['pos/api/v1/duitnow/create']                    = 'api/duitnow_create';
$route['pos/api/v1/duitnow/(:any)/qr_image']           = 'api/duitnow_qr_image/$1';
$route['pos/api/v1/duitnow/(:any)/status']             = 'api/duitnow_status/$1';
$route['pos/api/v1/duitnow/(:any)/cancel']             = 'api/duitnow_cancel/$1';
$route['pos/webhook/chip']                             = 'api/chip_webhook';

// ---------------------------------------------------------------------------
// GrabFood — Inbound webhooks (Grab's servers call these; no admin prefix)
// Must be declared before the Flutter POS routes to avoid (:any) conflicts.
// ---------------------------------------------------------------------------
$route['pos/api/v1/grabfood/status']                   = 'api/grabfood_status';
$route['pos/api/v1/grabfood/oauth/token']              = 'api/grabfood_oauth_token';
$route['pos/api/v1/grabfood/menu']                     = 'api/grabfood_menu';
$route['pos/api/v1/grabfood/webhook/order_state']      = 'api/grabfood_webhook_order_state';
$route['pos/api/v1/grabfood/webhook/order']            = 'api/grabfood_webhook_order';
$route['pos/api/v1/grabfood/webhook/menu_sync']        = 'api/grabfood_webhook_menu_sync';

// ---------------------------------------------------------------------------
// GrabFood — Flutter POS API  (must come before the admin web routes)
// ---------------------------------------------------------------------------
$route['pos/api/v1/grabfood/orders/(:any)/accept']          = 'api/grabfood_order_action/$1/accept';
$route['pos/api/v1/grabfood/orders/(:any)/ready']           = 'api/grabfood_order_action/$1/ready';
$route['pos/api/v1/grabfood/orders/(:any)/cancel']          = 'api/grabfood_order_action/$1/cancel';
$route['pos/api/v1/grabfood/orders/(:any)/simulate_state']  = 'api/grabfood_order_action/$1/simulate_state';
$route['pos/api/v1/grabfood/orders/(:any)']            = 'api/grabfood_order/$1';
$route['pos/api/v1/grabfood/orders']                   = 'api/grabfood_orders';
$route['pos/api/v1/grabfood/sync']                     = 'api/grabfood_sync';

// ---------------------------------------------------------------------------
// GrabFood Integration — CRM admin web views
// Order details live in the regular Transactions list/detail pages; only the
// connection settings still need their own admin endpoints.
// ---------------------------------------------------------------------------
$route['pos/ajax_grabfood_save_settings']              = 'pos_grabfood/ajax_save_settings';
$route['pos/ajax_grabfood_test_connection']            = 'pos_grabfood/ajax_test_connection';
$route['pos/ajax_grabfood_activate']                   = 'pos_grabfood/ajax_grabfood_activate';
