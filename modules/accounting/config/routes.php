<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['accounting/api/v1/(.+)'] = 'api_accounting/$1';
$route['accounting/api/(.+)']    = 'api_accounting/$1';
