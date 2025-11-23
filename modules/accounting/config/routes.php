<?php
defined('BASEPATH') or exit('No direct script access allowed');

// REST API routes for the Accounting module.
$route = [
    'accounting/api/v1/bill/(\d+)/payments'    => 'api_accounting/bill_payments_by_bill/$1',
    'accounting/api/v1/bills/(\d+)/attachment' => 'api_accounting/bill_attachment/$1',
    'accounting/api'                            => 'api_accounting/index',
    'accounting/api/'                           => 'api_accounting/index',
    'accounting/api/v1'                         => 'api_accounting/index',
    'accounting/api/v1/'                        => 'api_accounting/index',
    'accounting/api/v1/(.+)'                    => 'api_accounting/$1',
    'accounting/api/(.+)'                       => 'api_accounting/$1',
];
