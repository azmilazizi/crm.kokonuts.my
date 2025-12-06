<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_options extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function options_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $options = $this->db->get(db_prefix() . 'options')->result_array();

        $this->response([
            'status' => true,
            'result' => $options,
        ], self::HTTP_OK);
    }
}
