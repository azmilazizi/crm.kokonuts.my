<?php
defined('BASEPATH') or exit('No direct script access allowed');

// REST API routes for the Accounting module.
$route = [
    'accounting/api'         => 'api_accounting/index',
    'accounting/api/'        => 'api_accounting/index',
    'accounting/api/v1'      => 'api_accounting/index',
    'accounting/api/v1/'     => 'api_accounting/index',
    'accounting/api/v1/(.+)' => 'api_accounting/$1',
    'accounting/api/(.+)'    => 'api_accounting/$1',
];
