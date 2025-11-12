<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['accounting/api/v1']        = 'api_accounting/index';
$route['accounting/api/v1/(:any)'] = 'api_accounting/$1';
$route['accounting/api/(:any)']    = 'api_accounting/$1';
