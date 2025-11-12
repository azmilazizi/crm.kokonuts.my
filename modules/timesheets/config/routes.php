<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['timesheets/api/v1/(:any)'] = 'api_timesheets/$1';
$route['timesheets/api/(:any)']    = 'api_timesheets/$1';
