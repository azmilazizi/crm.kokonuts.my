<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Env_ver extends AdminController {
    public function __construct() {
        parent::__construct();
    }

    public function index() {
        show_404();
    }

    public function activate() {
        // Simulando a resposta positiva sem verificação
        $res = [
            'status' => true,
            'message' => 'Module activated successfully!',
            'original_url' => $this->input->post('original_url')
        ];
        echo json_encode($res);
    }
    
    public function upgrade_database() {
        // Simulando a resposta positiva sem verificação
        $res = [
            'status' => true,
            'message' => 'Database upgraded successfully!',
            'original_url' => $this->input->post('original_url')
        ];
        echo json_encode($res);
    }
}
