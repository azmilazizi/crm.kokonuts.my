<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Keys match the full URI; values are controller/method (module name is prepended by the HMVC router).

// ---------------------------------------------------------------------------
// Stores
// ---------------------------------------------------------------------------
$route['pos/api/stores']                        = 'api/stores';
$route['pos/api/stores/(:num)']                 = 'api/store/$1';

// ---------------------------------------------------------------------------
// Categories / Item groups
// ---------------------------------------------------------------------------
$route['pos/api/categories']                    = 'api/categories';
$route['pos/api/item_groups']                   = 'api/item_groups';

// ---------------------------------------------------------------------------
// Items  (barcode must precede :num so it is matched first)
// ---------------------------------------------------------------------------
$route['pos/api/items/barcode/(:any)']          = 'api/item_by_barcode/$1';
$route['pos/api/items/(:num)']                  = 'api/item/$1';
$route['pos/api/items']                         = 'api/items';

// ---------------------------------------------------------------------------
// Employees
// ---------------------------------------------------------------------------
$route['pos/api/employees']                     = 'api/employees';
$route['pos/api/employee_login']                = 'api/employee_login';

// ---------------------------------------------------------------------------
// Modifiers / Payment types / Payment modes / Taxes
// ---------------------------------------------------------------------------
$route['pos/api/modifiers']                     = 'api/modifiers';
$route['pos/api/payment_types']                 = 'api/payment_types';
$route['pos/api/payment_modes']                 = 'api/payment_modes';
$route['pos/api/taxes']                         = 'api/taxes';

// ---------------------------------------------------------------------------
// Bundles
// ---------------------------------------------------------------------------
$route['pos/api/bundles/(:num)']                = 'api/bundle/$1';
$route['pos/api/bundles']                       = 'api/bundles';

// ---------------------------------------------------------------------------
// Promotions
// ---------------------------------------------------------------------------
$route['pos/api/promotions/validate']           = 'api/promotions_validate';
$route['pos/api/promotions']                    = 'api/promotions';

// ---------------------------------------------------------------------------
// Shifts  (specific sub-routes before the bare :num catch-all)
// ---------------------------------------------------------------------------
$route['pos/api/shifts/open']                   = 'api/shifts_open';
$route['pos/api/shifts/current']                = 'api/shift_current';
$route['pos/api/shifts/(:num)/cash_movement']   = 'api/shift_cash_movement/$1';
$route['pos/api/shifts/(:num)/close']           = 'api/shift_close/$1';
$route['pos/api/shifts/(:num)/report']          = 'api/shift_report/$1';
$route['pos/api/shifts/(:num)']                 = 'api/shift/$1';

// ---------------------------------------------------------------------------
// Customers
// ---------------------------------------------------------------------------
$route['pos/api/customers/search']              = 'api/customers_search';
$route['pos/api/customers/(:num)']              = 'api/customer/$1';
$route['pos/api/customers']                     = 'api/customers_create';

// ---------------------------------------------------------------------------
// Loyalty
// ---------------------------------------------------------------------------
$route['pos/api/loyalty/balance/(:num)']        = 'api/loyalty_balance/$1';
$route['pos/api/loyalty/earn']                  = 'api/loyalty_earn';
$route['pos/api/loyalty/redeem']                = 'api/loyalty_redeem';
$route['pos/api/loyalty/register']              = 'api/loyalty_register';

// ---------------------------------------------------------------------------
// Receipts
// ---------------------------------------------------------------------------
$route['pos/api/receipts']                      = 'api/receipts';
$route['pos/api/receipt/(:any)']                = 'api/receipt/$1';
$route['pos/api/create_receipt']                = 'api/create_receipt';
$route['pos/api/create_refund']                 = 'api/create_refund';
