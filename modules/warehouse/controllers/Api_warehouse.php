<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_warehouse extends API_Controller
{
    public function __construct()
    {
        $this->module_language_file      = 'warehouse';
        $this->module_language_directory = APP_MODULES_PATH . 'warehouse/';

        parent::__construct();

        $this->load->model('warehouse_model');
    }

    public function items_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $limit  = $this->get('limit');
        $offset = $this->get('offset');
        $search = trim((string) $this->get('search'));

        $limit  = is_numeric($limit) ? (int) $limit : 50;
        $offset = is_numeric($offset) ? (int) $offset : 0;

        if ($limit <= 0) {
            $limit = 50;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $this->db->from(db_prefix() . 'items');

        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('description', $search);
            $this->db->or_like('long_description', $search);
            $this->db->or_like('commodity_code', $search);
            $this->db->or_like('sku_code', $search);
            $this->db->group_end();
        }

        $this->db->order_by('id', 'DESC');
        $this->db->limit($limit, $offset);
        $query = $this->db->get();

        $this->response([
            'status' => true,
            'result' => $query->result_array(),
        ], self::HTTP_OK);
    }

    public function items_post()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $payload = $this->get_request_payload('post');

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $item_data = $this->prepare_item_payload($payload, false);

        if ($item_data === []) {
            $this->response([
                'status'  => false,
                'message' => 'Commodity code, description, group, unit, and rate are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $result = $this->warehouse_model->add_commodity_one_item($item_data);

        if (!$result || !isset($result['insert_id'])) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create item with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => [
                'id'          => $result['insert_id'],
                'description' => $item_data['description'],
            ],
        ], self::HTTP_CREATED);
    }

    public function item_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid item identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $item = $this->warehouse_model->get_commodity((int) $id);

        if (!$item) {
            $this->response([
                'status'  => false,
                'message' => 'Item not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $item,
        ], self::HTTP_OK);
    }

    public function item_put($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid item identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $item = $this->warehouse_model->get_commodity((int) $id);

        if (!$item) {
            $this->response([
                'status'  => false,
                'message' => 'Item not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $payload = $this->get_request_payload('put');

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $item_data = $this->prepare_item_payload($payload, true);

        if ($item_data === []) {
            $this->response([
                'status'  => false,
                'message' => 'Commodity code, description, group, unit, and rate are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->warehouse_model->update_commodity_one_item($item_data, (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Item update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Item updated successfully.',
        ], self::HTTP_OK);
    }

    public function warehouses_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $warehouses = $this->warehouse_model->get_warehouse(false, true);

        $this->response([
            'status' => true,
            'result' => $warehouses,
        ], self::HTTP_OK);
    }

    public function warehouses_post()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $payload = $this->get_request_payload('post');

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouse_data = $this->prepare_warehouse_payload($payload, false);

        if ($warehouse_data === []) {
            $this->response([
                'status'  => false,
                'message' => 'Warehouse code and name are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouse_id = $this->warehouse_model->add_one_warehouse($warehouse_data);

        if (!$warehouse_id) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create warehouse with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => [
                'id'   => $warehouse_id,
                'code' => $warehouse_data['warehouse_code'],
            ],
        ], self::HTTP_CREATED);
    }

    public function warehouse_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid warehouse identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouse = $this->warehouse_model->get_warehouse((int) $id, true);

        if (!$warehouse) {
            $this->response([
                'status'  => false,
                'message' => 'Warehouse not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $warehouse,
        ], self::HTTP_OK);
    }

    public function warehouse_put($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid warehouse identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouse = $this->warehouse_model->get_warehouse((int) $id, true);

        if (!$warehouse) {
            $this->response([
                'status'  => false,
                'message' => 'Warehouse not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $payload = $this->get_request_payload('put');

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouse_data = $this->prepare_warehouse_payload($payload, true, $warehouse);

        if ($warehouse_data === []) {
            $this->response([
                'status'  => false,
                'message' => 'No updatable fields were provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->warehouse_model->update_one_warehouse($warehouse_data, (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Warehouse update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Warehouse updated successfully.',
        ], self::HTTP_OK);
    }

    private function get_request_payload($method)
    {
        $method = strtolower($method);

        if (!in_array($method, ['post', 'put'], true)) {
            $method = 'post';
        }

        $data = $this->{$method}();

        if (!is_array($data)) {
            $data = [];
        }

        if ($data === []) {
            $raw_input = $this->input->raw_input_stream;

            if ($raw_input !== '') {
                $decoded = json_decode($raw_input, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        return $data;
    }

    private function prepare_item_payload(array $input, bool $is_update)
    {
        $required_fields = ['commodity_code', 'description', 'group_id', 'unit_id', 'rate'];

        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || trim((string) $input[$field]) === '') {
                return [];
            }
        }

        $defaults = [
            'commodity_barcode' => '',
            'long_descriptions' => '',
            'sku_code'          => '',
            'warehouse_id'      => 0,
            'sub_group'         => '',
            'commodity_type'    => 0,
            'tax'               => '',
            'tax2'              => '',
            'origin'            => '',
            'style_id'          => 0,
            'model_id'          => 0,
            'size_id'           => 0,
            'images'            => '',
            'date_manufacture'  => '',
            'expiry_date'       => '',
            'purchase_price'    => 0,
            'minimum_inventory' => null,
            'tags'              => '',
        ];

        $payload = $defaults;

        foreach ($input as $key => $value) {
            $payload[$key] = $value;
        }

        $payload['commodity_code'] = trim((string) $input['commodity_code']);
        $payload['description']    = (string) $input['description'];
        $payload['group_id']       = (int) $input['group_id'];
        $payload['unit_id']        = (int) $input['unit_id'];
        $payload['rate']           = (string) (is_numeric($input['rate']) ? $input['rate'] : trim((string) $input['rate']));

        if (isset($input['purchase_price']) && $input['purchase_price'] !== '') {
            $payload['purchase_price'] = (string) (is_numeric($input['purchase_price']) ? $input['purchase_price'] : trim((string) $input['purchase_price']));
        }

        if (isset($input['sku_code'])) {
            $payload['sku_code'] = trim((string) $input['sku_code']);
        }

        if (isset($input['commodity_barcode'])) {
            $payload['commodity_barcode'] = trim((string) $input['commodity_barcode']);
        }

        if (isset($input['long_descriptions'])) {
            $payload['long_descriptions'] = (string) $input['long_descriptions'];
        }

        if (isset($input['warehouse_id']) && $input['warehouse_id'] !== '') {
            $payload['warehouse_id'] = (int) $input['warehouse_id'];
        }

        if (isset($input['commodity_type']) && $input['commodity_type'] !== '') {
            $payload['commodity_type'] = (int) $input['commodity_type'];
        }

        if (isset($input['style_id']) && $input['style_id'] !== '') {
            $payload['style_id'] = (int) $input['style_id'];
        }

        if (isset($input['model_id']) && $input['model_id'] !== '') {
            $payload['model_id'] = (int) $input['model_id'];
        }

        if (isset($input['size_id']) && $input['size_id'] !== '') {
            $payload['size_id'] = (int) $input['size_id'];
        }

        if (isset($input['minimum_inventory']) && $input['minimum_inventory'] !== '') {
            $payload['minimum_inventory'] = (float) $input['minimum_inventory'];
        }

        if (!isset($payload['tags']) || !is_string($payload['tags'])) {
            $payload['tags'] = '';
        }

        return $payload;
    }

    private function prepare_warehouse_payload(array $input, bool $is_update, $current = null)
    {
        $code = isset($input['warehouse_code']) ? trim((string) $input['warehouse_code']) : '';
        $name = isset($input['warehouse_name']) ? trim((string) $input['warehouse_name']) : '';

        if (!$is_update && ($code === '' || $name === '')) {
            return [];
        }

        $payload = [];

        if ($code !== '') {
            $payload['warehouse_code'] = $code;
        }

        if ($name !== '') {
            $payload['warehouse_name'] = $name;
        }

        $field_map = ['warehouse_address', 'city', 'state', 'zip_code', 'note'];

        foreach ($field_map as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = (string) $input[$field];
            }
        }

        if (isset($input['order']) && $input['order'] !== '') {
            $payload['order'] = (int) $input['order'];
        }

        if (isset($input['country']) && $input['country'] !== '') {
            $payload['country'] = (int) $input['country'];
        }

        if (isset($input['assign_to_staffs'])) {
            $payload['assign_to_staffs'] = is_array($input['assign_to_staffs']) ? $input['assign_to_staffs'] : [$input['assign_to_staffs']];
        }

        if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
            $payload['custom_fields'] = $input['custom_fields'];
        }

        $display_default = $current ? ((int) $current->display === 1) : true;
        $hide_default    = $current ? ((int) $current->hide_warehouse_when_out_of_stock === 1) : false;

        if (array_key_exists('display', $input)) {
            $display_default = $this->interpret_boolean($input['display'], $display_default);
        }

        if (array_key_exists('hide_warehouse_when_out_of_stock', $input)) {
            $hide_default = $this->interpret_boolean($input['hide_warehouse_when_out_of_stock'], $hide_default);
        }

        if (!$is_update || array_key_exists('display', $input)) {
            $payload['display'] = $display_default ? 'on' : 'off';
        }

        if (!$is_update || array_key_exists('hide_warehouse_when_out_of_stock', $input)) {
            $payload['hide_warehouse_when_out_of_stock'] = $hide_default ? 'on' : 'off';
        }

        return $payload;
    }

    private function interpret_boolean($value, $default = false)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }
}
