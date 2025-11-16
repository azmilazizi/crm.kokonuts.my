<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['warehouse/api/v1/(:any)'] = 'warehouse/api_warehouse/$1';
$route['warehouse/api/(:any)']    = 'warehouse/api_warehouse/$1';
