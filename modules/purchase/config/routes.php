<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['purchase/api'] = 'api_purchase/index';
$route['purchase/api/v1'] = 'api_purchase/index';
$route['purchase/api/v1/vendors'] = 'api_purchase/vendors';
$route['purchase/api/v1/vendors/(:any)'] = 'api_purchase/vendors/$1';
$route['purchase/api/v1/purchase_orders'] = 'api_purchase/purchase_orders';
$route['purchase/api/v1/purchase_orders/(:any)'] = 'api_purchase/purchase_orders/$1';
