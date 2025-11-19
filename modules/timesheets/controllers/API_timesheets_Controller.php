<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

abstract class API_timesheets_Controller extends API_Controller
{
    public function __construct($config = 'rest')
    {
        $this->module_config_path        = __DIR__ . '/../config/';
        $this->module_language_file      = 'timesheets';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct($config);

        if (isset($this->authorization_token)) {
            // Disable token expiration for the Timesheets API.
            $this->authorization_token->set_token_expire_time(0);
        }
    }
}
