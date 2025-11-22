<?php
defined('BASEPATH') or exit('No direct script access allowed');

$moduleHelper = FCPATH . 'modules/accounting/helpers/accounting_helper.php';

if (file_exists($moduleHelper)) {
    require_once $moduleHelper;
}
