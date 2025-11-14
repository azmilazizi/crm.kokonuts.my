<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['purchase/api/v1/purchase_order/(:num)/(.+)']      = '404_override';
$route['purchases/api/v1/purchase_order/(:num)/(.+)']     = '404_override';
$route['purchase/api/v1/(.+)']                            = 'api_purchase/$1';
$route['purchase/api/(.+)']                               = 'api_purchase/$1';
$route['purchases/api/v1/(.+)']                           = 'api_purchase/$1';
$route['purchases/api/(.+)']                              = 'api_purchase/$1';
