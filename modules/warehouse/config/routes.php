<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['warehouse/api/v1/purchase-orders/(:num)/goods-receipts'] = 'warehouse/api_warehouse/purchase_order_goods_receipts/$1';
$route['warehouse/api/purchase-orders/(:num)/goods-receipts']    = 'warehouse/api_warehouse/purchase_order_goods_receipts/$1';
$route['warehouse/api/v1/(:any)'] = 'warehouse/api_warehouse/$1';
$route['warehouse/api/(:any)']    = 'warehouse/api_warehouse/$1';
