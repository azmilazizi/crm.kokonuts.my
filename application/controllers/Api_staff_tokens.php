<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_staff_tokens extends API_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function token_match_get($staffId = null)
    {
        if ($staffId === null || $staffId === '') {
            $this->response([
                'status'  => false,
                'message' => 'Staff identifier is required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $token = $this->get('token');

        if ($token === null || $token === '') {
            $this->response([
                'status'  => false,
                'message' => 'Token is required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $staff = $this->db->where('staffid', (int) $staffId)->get(db_prefix() . 'staff')->row();

        if (!$staff) {
            $this->response([
                'status'  => false,
                'message' => 'Staff member not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $storedToken = $staff->token ?? '';
        $isMatch     = ($storedToken !== '') && hash_equals($storedToken, (string) $token);

        $this->response([
            'status' => true,
            'result' => [
                'staff_id' => (int) $staffId,
                'match'    => $isMatch,
            ],
        ], self::HTTP_OK);
    }
}
