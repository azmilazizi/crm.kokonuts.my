<?php
defined('BASEPATH') or exit('No direct script access allowed');

// REST API routes for the Accounting module.
$route = [
    'accounting/api/v1/bill/(\d+)/payment/(\d+)/attachment' => 'api_accounting/bill_payment_attachment/$1/$2',
    'accounting/api/v1/bill/(\d+)/payment/(\d+)' => 'api_accounting/bill_payment_for_bill/$1/$2',
    'accounting/api/v1/bill/(\d+)/payments'    => 'api_accounting/bill_payments_by_bill/$1',
    'accounting/api/v1/bill/(\d+)/attachment' => 'api_accounting/bill_attachment/$1',
    'accounting/api/v1/journal_entry/(\d+)/attachments' => 'api_accounting/journal_entry_attachments/$1',
    'accounting/api'                            => 'api_accounting/index',
    'accounting/api/'                           => 'api_accounting/index',
    'accounting/api/v1'                         => 'api_accounting/index',
    'accounting/api/v1/'                        => 'api_accounting/index',
    'accounting/api/v1/(.+)'                    => 'api_accounting/$1',
    'accounting/api/(.+)'                       => 'api_accounting/$1',
];
