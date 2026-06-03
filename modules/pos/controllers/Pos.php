<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pos extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $data['title'] = 'Point of Sale';
        $this->load->view('pos/admin/index', $data);
    }
}
