<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['purchase/api/v1/(:any)']  = 'api_purchase/$1';
$route['purchase/api/(:any)']     = 'api_purchase/$1';
$route['purchases/api/v1/(:any)'] = 'api_purchase/$1';
$route['purchases/api/(:any)']    = 'api_purchase/$1';
