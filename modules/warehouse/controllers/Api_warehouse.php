<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_warehouse extends API_Controller
{
    public function __construct()
    {
        $this->module_language_file      = 'warehouse';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->model('warehouse_model');
    }

    public function items_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $limit        = $this->get('limit');
        $offset       = $this->get('offset');
        $search       = trim((string) $this->get('search'));
        $warehouse_id = $this->get('warehouse_id');
        $group_id     = $this->get('group_id');
        $unit_id      = $this->get('unit_id');
        $sku_code     = trim((string) $this->get('sku_code'));
        $code         = trim((string) $this->get('commodity_code'));
        $can_inventory = trim((string) $this->get('can_be_inventory'));

        $isLimitAll = is_string($limit) && strtolower($limit) === 'all';
        $limit      = is_numeric($limit) ? (int) $limit : ($isLimitAll ? null : null);
        $offset     = is_numeric($offset) ? (int) $offset : 0;

        $warehouse_id = is_numeric($warehouse_id) ? (int) $warehouse_id : null;
        $group_id     = is_numeric($group_id) ? (int) $group_id : null;
        $unit_id      = is_numeric($unit_id) ? (int) $unit_id : null;

        if ($limit !== null) {
            if ($limit <= 0) {
                $limit = null;
            }

            if ($offset < 0) {
                $offset = 0;
            }
        }

        $filters = array_filter([
            'search'         => $search !== '' ? $search : null,
            'warehouse_id'   => $warehouse_id,
            'group_id'       => $group_id,
            'unit_id'        => $unit_id,
            'sku_code'       => $sku_code !== '' ? $sku_code : null,
            'commodity_code' => $code !== '' ? $code : null,
            'can_be_inventory' => $can_inventory !== '' ? $can_inventory : null,
            'can_be_sold' => trim((string) $this->get('can_be_sold')),
            'can_be_purchased' => trim((string) $this->get('can_be_purchased')),
            'can_be_manufacturing' => trim((string) $this->get('can_be_manufacturing')),
        ], function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        });

        $items = $this->warehouse_model->get_api_items($filters, $limit, $offset);

        $this->response([
            'status' => true,
            'result' => $items,
        ], self::HTTP_OK);
    }

    /**
     * Lists available unit types.
     */
    public function units_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $units = $this->warehouse_model->get_units_code_name();

        $this->response([
            'status' => true,
            'result' => $units,
        ], self::HTTP_OK);
    }

    /**
     * Lists available item groups.
     */
    public function item_groups_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $groups = $this->warehouse_model->get_item_group();

        $this->response([
            'status' => true,
            'result' => $groups,
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

    /**
     * Lists goods receipts with optional pagination and search filters.
     */
    public function goods_receipts_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $limit  = $this->get('limit');
        $offset = $this->get('offset');

        $isLimitAll = is_string($limit) && strtolower($limit) === 'all';
        $limit      = is_numeric($limit) ? (int) $limit : ($isLimitAll ? null : null);
        $offset     = is_numeric($offset) ? (int) $offset : 0;

        if ($limit !== null) {
            if ($limit <= 0) {
                $limit = null;
            }

            if ($offset < 0) {
                $offset = 0;
            }
        }

        $filters = array_filter([
            'search'   => trim((string) $this->get('search')),
            'from'     => $this->normalize_date_for_filter($this->get('from_date')),
            'to'       => $this->normalize_date_for_filter($this->get('to_date')),
            'approval' => $this->get('approval'),
        ], function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        });

        $receipts = $this->warehouse_model->get_api_goods_receipts($filters, $limit, $offset);
        $total    = $this->warehouse_model->count_api_goods_receipts($filters);

        $this->response([
            'status' => true,
            'result' => $receipts,
            'total'  => $total,
        ], self::HTTP_OK);
    }

    /**
     * Retrieves a single goods receipt with its detail rows.
     *
     * @param int|null $id
     */
    public function goods_receipt_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid goods receipt identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $receipt = $this->warehouse_model->get_goods_receipt((int) $id);

        if (!$receipt) {
            $this->response([
                'status'  => false,
                'message' => 'Goods receipt not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $details = $this->warehouse_model->get_goods_receipt_detail((int) $id);

        $this->response([
            'status' => true,
            'result' => [
                'receipt' => $receipt,
                'items'   => $details,
            ],
        ], self::HTTP_OK);
    }

    /**
     * Creates a new goods receipt with line items.
     */
    public function goods_receipts_post()
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

        $prepared = $this->prepare_goods_receipt_payload($payload);

        if (isset($prepared['error'])) {
            $this->response([
                'status'  => false,
                'message' => $prepared['error'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $insert_id = $this->warehouse_model->add_goods_receipt($prepared);

        if (!$insert_id) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create goods receipt with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $receipt = $this->warehouse_model->get_goods_receipt($insert_id);

        $this->response([
            'status' => true,
            'result' => [
                'id'   => $insert_id,
                'code' => $receipt ? $receipt->goods_receipt_code : null,
            ],
        ], self::HTTP_CREATED);
    }

    public function item_account_mapping_post()
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

        $itemId      = isset($payload['item_id']) ? (int) $payload['item_id'] : 0;
        $expenseId   = isset($payload['expense_account']) ? (int) $payload['expense_account'] : 0;
        $inventoryId = isset($payload['inventory_asset_account']) ? (int) $payload['inventory_asset_account'] : 0;
        $incomeId    = isset($payload['income_account']) ? (int) $payload['income_account'] : null;

        if ($itemId <= 0 || $expenseId <= 0 || $inventoryId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'item_id, expense_account, and inventory_asset_account are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $this->load_accounting_model();

        $item = $this->accounting_model->get_item_by_id($itemId);
        if (!$item) {
            $this->response([
                'status'  => false,
                'message' => 'Item not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $expenseAccount   = $this->accounting_model->get_accounts($expenseId);
        $inventoryAccount = $this->accounting_model->get_accounts($inventoryId);
        $incomeAccount    = $incomeId ? $this->accounting_model->get_accounts($incomeId) : null;

        if (!$expenseAccount || !$inventoryAccount || ($incomeId && !$incomeAccount)) {
            $this->response([
                'status'  => false,
                'message' => 'One or more provided accounts were not found.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $mapping = $this->accounting_model->upsert_item_account_mapping($itemId, $inventoryId, $expenseId, $incomeId);

        if (!$mapping) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to save mapping for the provided item.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $mapping,
        ], self::HTTP_CREATED);
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

    private function prepare_goods_receipt_payload(array $payload)
    {
        if (!isset($payload['items']) || !is_array($payload['items']) || $payload['items'] === []) {
            return ['error' => 'At least one item is required to create a goods receipt.'];
        }

        $itemsPreparation = $this->prepare_goods_receipt_items($payload['items']);

        if (isset($itemsPreparation['error'])) {
            return ['error' => $itemsPreparation['error']];
        }

        $date_c   = $this->normalize_date_value($payload['date_c'] ?? date('Y-m-d'));
        $date_add = $this->normalize_date_value($payload['date_add'] ?? date('Y-m-d'));

        $supplierCode = isset($payload['supplier_code']) ? (string) $payload['supplier_code'] : '';
        $buyerId      = isset($payload['buyer_id']) ? (int) $payload['buyer_id'] : '';
        $prOrderId    = isset($payload['purchase_order_id']) ? (int) $payload['purchase_order_id'] : '';

        return [
            'date_c'            => $date_c,
            'date_add'          => $date_add,
            'supplier_name'     => isset($payload['supplier_name']) ? (string) $payload['supplier_name'] : '',
            'supplier_code'     => $supplierCode,
            'buyer_id'          => $buyerId,
            'pr_order_id'       => $prOrderId,
            'description'       => isset($payload['description']) ? (string) $payload['description'] : '',
            'total_tax_money'   => $itemsPreparation['totals']['tax'],
            'total_goods_money' => $itemsPreparation['totals']['goods'],
            'value_of_inventory'=> $itemsPreparation['totals']['goods'],
            'total_money'       => $itemsPreparation['totals']['goods'] + $itemsPreparation['totals']['tax'],
            'newitems'          => $itemsPreparation['items'],
        ];
    }

    private function prepare_goods_receipt_items(array $items)
    {
        $prepared = [];
        $totalGoods = 0;
        $totalTax   = 0;

        foreach (array_values($items) as $index => $item) {
            if (!isset($item['commodity_code'], $item['warehouse_id'], $item['quantity'], $item['unit_price'])) {
                return ['error' => 'commodity_code, warehouse_id, quantity, and unit_price are required for each item.'];
            }

            $quantity  = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];

            if ($quantity <= 0 || $unitPrice < 0) {
                return ['error' => 'Item quantities must be greater than zero and prices cannot be negative.'];
            }

            $taxSelect = $this->build_tax_selection($item);
            $taxData   = $this->warehouse_model->wh_get_tax_rate($taxSelect);

            $lineGoods = $unitPrice * $quantity;
            $lineTax   = $lineGoods * ((float) $taxData['tax_rate'] / 100);

            $preparedItem = [
                'commodity_code'  => (int) $item['commodity_code'],
                'warehouse_id'    => (int) $item['warehouse_id'],
                'quantities'      => $quantity,
                'unit_price'      => $unitPrice,
                'tax_select'      => $taxSelect,
                'lot_number'      => isset($item['lot_number']) ? (string) $item['lot_number'] : null,
                'note'            => isset($item['note']) ? (string) $item['note'] : null,
                'serial_number'   => isset($item['serial_number']) ? (string) $item['serial_number'] : null,
            ];

            if (isset($item['unit_id'])) {
                $preparedItem['unit_id'] = (int) $item['unit_id'];
            }

            $preparedItem['date_manufacture'] = $this->normalize_optional_date($item['date_manufacture'] ?? null);
            $preparedItem['expiry_date']      = $this->normalize_optional_date($item['expiry_date'] ?? null);

            $prepared[] = $preparedItem;

            $totalGoods += $lineGoods;
            $totalTax   += $lineTax;
        }

        return [
            'items'  => $prepared,
            'totals' => [
                'goods' => $totalGoods,
                'tax'   => $totalTax,
            ],
        ];
    }

    private function build_tax_selection(array $item)
    {
        $taxes = [];

        if (isset($item['taxes'])) {
            $taxes = $item['taxes'];
        } elseif (isset($item['tax_ids'])) {
            $taxes = $item['tax_ids'];
        } elseif (isset($item['tax_select'])) {
            $taxes = $item['tax_select'];
        }

        if (!is_array($taxes)) {
            $taxes = [$taxes];
        }

        $taxes = array_filter(array_map(function ($tax) {
            if (is_numeric($tax)) {
                return (int) $tax;
            }

            return null;
        }, $taxes), function ($value) {
            return $value !== null;
        });

        if ($taxes === []) {
            return [];
        }

        $taxRows = [];

        foreach ($taxes as $taxId) {
            $this->db->where('id', $taxId);
            $tax = $this->db->get(db_prefix() . 'taxes')->row();

            if ($tax) {
                $taxRows[] = $tax->name . '|' . $tax->taxrate;
            }
        }

        return $taxRows;
    }

    private function normalize_date_value($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return date('Y-m-d');
        }

        return $this->warehouse_model->check_format_date($value) ? $value : to_sql_date($value);
    }

    private function normalize_optional_date($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalize_date_value($value);
    }

    private function normalize_date_for_filter($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalize_date_value($value);
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
            'purchase_price'    => 0,
        ];

        $payload = $defaults;

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

    private function load_accounting_model()
    {
        if (!class_exists('Accounting_model')) {
            if (!function_exists('module_dir_path')) {
                $this->load->helper('modules');
            }

            $accountingModelPath = module_dir_path('accounting', 'models/Accounting_model.php');

            if (is_file($accountingModelPath)) {
                require_once $accountingModelPath;
            }
        }

        $this->load->model('accounting/accounting_model');
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
