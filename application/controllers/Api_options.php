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

    public function option_get($name = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if ($name === null || $name === '') {
            $this->response([
                'status'  => false,
                'message' => 'Option identifier is required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $option = $this->db
            ->where('name', $name)
            ->get(db_prefix() . 'options')
            ->row_array();

        if (!$option) {
            $this->response([
                'status'  => false,
                'message' => 'Option not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $option,
        ], self::HTTP_OK);
    }
}
