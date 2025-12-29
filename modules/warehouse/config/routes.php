<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['warehouse/api/v1/(.+)'] = 'warehouse/api_warehouse/$1';
$route['warehouse/api/(.+)']    = 'warehouse/api_warehouse/$1';
