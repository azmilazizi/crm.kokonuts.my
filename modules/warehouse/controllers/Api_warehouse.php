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

    public function item_get($id = null, $subresource = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if ($subresource === 'inventory') {
            $this->item_inventory_get($id);

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

    public function item_inventory_get($id = null)
    {
        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid item identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouseId = $this->get('warehouse_id');

        if ($warehouseId !== null && $warehouseId !== '' && !is_numeric($warehouseId)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid warehouse identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $warehouseId = is_numeric($warehouseId) ? (int) $warehouseId : null;

        $inventory = $this->warehouse_model->get_api_item_inventory((int) $id, $warehouseId);

        $this->response([
            'status' => true,
            'result' => $inventory,
        ], self::HTTP_OK);
    }

    /**
     * Lists inventory manage rows with inventory minimum data.
     */
    public function inventory_manages_get()
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

        $warehouseId = $this->get('warehouse_id');
        $commodityId = $this->get('commodity_id');

        $groupId = $this->get('group_id');
        $unitId = $this->get('unit_id');

        $itemFilters = array_filter([
            'search'           => trim((string) $this->get('search')),
            'warehouse_id'     => is_numeric($warehouseId) ? (int) $warehouseId : null,
            'group_id'         => is_numeric($groupId) ? (int) $groupId : null,
            'unit_id'          => is_numeric($unitId) ? (int) $unitId : null,
            'commodity_id'     => is_numeric($commodityId) ? (int) $commodityId : null,
            'commodity_code'   => trim((string) $this->get('commodity_code')),
            'sku_code'         => trim((string) $this->get('sku_code')),
            'can_be_inventory' => trim((string) $this->get('can_be_inventory')),
            'can_be_purchased' => trim((string) $this->get('can_be_purchased')),
        ], function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        });

        $inventoryFilters = array_filter([
            'search'          => trim((string) $this->get('search')),
            'warehouse_id'    => is_numeric($warehouseId) ? (int) $warehouseId : null,
            'commodity_id'    => is_numeric($commodityId) ? (int) $commodityId : null,
            'lot_number'      => trim((string) $this->get('lot_number')),
            'expiry_date'     => $this->normalize_date_for_filter($this->get('expiry_date')),
            'date_manufacture' => $this->normalize_date_for_filter($this->get('date_manufacture')),
        ], function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        });

        $items = $this->warehouse_model->get_api_inventory_items($itemFilters, $limit, $offset);
        $total = $this->warehouse_model->count_api_inventory_items($itemFilters);

        $itemIds = array_map(function ($item) {
            return (int) $item['id'];
        }, $items);

        $inventoryRows = $this->warehouse_model->get_api_inventory_manage_children($itemIds, $inventoryFilters);
        $inventoryByItem = [];

        foreach ($inventoryRows as $row) {
            $inventoryByItem[$row['commodity_id']][] = $row;
        }

        $result = [];

        foreach ($items as $item) {
            $inventoryMin = null;

            if (!empty($item['inventory_min_id'])) {
                $inventoryMin = [
                    'id'                 => (int) $item['inventory_min_id'],
                    'commodity_id'       => (int) ($item['inventory_min_commodity_id'] ?? $item['id']),
                    'commodity_code'     => $item['inventory_min_commodity_code'],
                    'commodity_name'     => $item['inventory_min_commodity_name'],
                    'inventory_number_min' => $item['inventory_number_min'],
                    'inventory_number_max' => $item['inventory_number_max'],
                ];
            }

            unset(
                $item['inventory_min_id'],
                $item['inventory_min_commodity_id'],
                $item['inventory_min_commodity_code'],
                $item['inventory_min_commodity_name'],
                $item['inventory_number_min'],
                $item['inventory_number_max']
            );

            $item['inventory_manage'] = $inventoryByItem[$item['id']] ?? [];
            $item['inventory_commodity_min'] = $inventoryMin;

            $result[] = $item;
        }

        $this->response([
            'status' => true,
            'result' => $result,
            'total'  => $total,
        ], self::HTTP_OK);
    }

    /**
     * Creates a new inventory manage row.
     */
    public function inventory_manages_post()
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

        $data = $this->prepare_inventory_manage_payload($payload, false);

        if ($data === []) {
            $this->response([
                'status'  => false,
                'message' => 'Warehouse ID, commodity ID, and inventory number are required.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $insertId = $this->warehouse_model->create_api_inventory_manage($data);

        if (!$insertId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create inventory manage row.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $inventory = $this->warehouse_model->get_api_inventory_manage($insertId);

        $this->response([
            'status' => true,
            'result' => $inventory,
        ], self::HTTP_CREATED);
    }

    /**
     * Retrieves a single inventory manage row.
     *
     * @param int|null $id
     */
    public function inventory_manage_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid inventory manage identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $inventory = $this->warehouse_model->get_api_inventory_manage((int) $id);

        if (!$inventory) {
            $this->response([
                'status'  => false,
                'message' => 'Inventory manage row not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $inventory,
        ], self::HTTP_OK);
    }

    /**
     * Updates an inventory manage row.
     *
     * @param int|null $id
     */
    public function inventory_manage_put($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid inventory manage identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $inventory = $this->warehouse_model->get_api_inventory_manage((int) $id);

        if (!$inventory) {
            $this->response([
                'status'  => false,
                'message' => 'Inventory manage row not found.',
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

        $data = $this->prepare_inventory_manage_payload($payload, true);

        if ($data === []) {
            $this->response([
                'status'  => false,
                'message' => 'No valid fields provided to update.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->warehouse_model->update_api_inventory_manage((int) $id, $data);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update inventory manage row.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $inventory = $this->warehouse_model->get_api_inventory_manage((int) $id);

        $this->response([
            'status' => true,
            'result' => $inventory,
        ], self::HTTP_OK);
    }

    /**
     * Deletes an inventory manage row.
     *
     * @param int|null $id
     */
    public function inventory_manage_delete($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid inventory manage identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $inventory = $this->warehouse_model->get_api_inventory_manage((int) $id);

        if (!$inventory) {
            $this->response([
                'status'  => false,
                'message' => 'Inventory manage row not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $deleted = $this->warehouse_model->delete_api_inventory_manage((int) $id);

        if (!$deleted) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to delete inventory manage row.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
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
     * Lists warehouse transaction history entries from goods_transaction_detail.
     */
    public function warehouse_history_get()
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

        $fromDate = $this->get('from_date');
        if ($fromDate === null || $fromDate === '') {
            $fromDate = $this->get('validity_start_date');
        }

        $toDate = $this->get('to_date');
        if ($toDate === null || $toDate === '') {
            $toDate = $this->get('validity_end_date');
        }

        $filters = array_filter([
            'warehouse_ids' => $this->normalize_int_list($this->get('warehouse_id')),
            'commodity_ids' => $this->normalize_int_list($this->get('commodity_id')),
            'status_ids'    => $this->normalize_int_list($this->get('status')),
            'from'          => $this->normalize_date_for_filter($fromDate),
            'to'            => $this->normalize_date_for_filter($toDate),
        ], function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_array($value)) {
                return $value !== [];
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        });

        $history = $this->warehouse_model->get_api_goods_transaction_history($filters, $limit, $offset);
        $history = $this->merge_warehouse_history_entries($history);
        $history = array_map([$this, 'format_warehouse_history_entry'], $history);
        $total   = $this->warehouse_model->count_api_goods_transaction_history($filters);

        $this->response([
            'status' => true,
            'result' => $history,
            'total'  => $total,
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

        if ($id === null) {
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

            $receipts = $this->warehouse_model->get_api_goods_receipts_with_details($filters, $limit, $offset);
            $total    = $this->warehouse_model->count_api_goods_receipts($filters);

            $this->response([
                'status' => true,
                'result' => $receipts,
                'total'  => $total,
            ], self::HTTP_OK);

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
    public function goods_receipt_post()
    {
        $this->goods_receipts_post();
    }

    /**
     * Deletes a goods receipt and its detail rows.
     *
     * @param int|null $id
     */
    public function goods_receipt_delete($id = null)
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

        $deleted = $this->warehouse_model->delete_goods_receipt((int) $id);

        if (!$deleted) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to delete goods receipt.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Goods receipt deleted successfully.',
        ], self::HTTP_OK);
    }

    /**
     * Updates an existing goods receipt and its detail rows.
     *
     * @param int|null $id
     */
    public function goods_receipt_put($id = null)
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

        $existing = $this->warehouse_model->get_goods_receipt((int) $id);

        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Goods receipt not found.',
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

        $prepared = $this->prepare_goods_receipt_update_payload($payload, (int) $id);

        if (isset($prepared['error'])) {
            $this->response([
                'status'  => false,
                'message' => $prepared['error'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $result = $this->warehouse_model->update_goods_receipt($prepared, $id);

        if (!$result) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update goods receipt with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => [
                'id' => (int) $id,
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

        $insertResult = $this->create_goods_receipt_direct($prepared);

        if (!$insertResult) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create goods receipt with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $insert_id = $insertResult['id'];
        $receipt = $this->warehouse_model->get_goods_receipt($insert_id);
        // Ensure approval workflow runs when auto-approved so inventory/PO updates happen.
        if ($receipt && (int) $receipt->approval === 1) {
            $this->warehouse_model->update_approve_request($insert_id, 1, 1);
            $receipt = $this->warehouse_model->get_goods_receipt($insert_id);
        }

        // Guarantee accounting conversion after a successful POST, regardless of hook timing.
        $this->load->model('accounting/accounting_model');
        $this->accounting_model->automatic_stock_import_conversion($insert_id);

        $this->response([
            'status' => true,
            'result' => [
                'id'   => $insert_id,
                'code' => $receipt ? $receipt->goods_receipt_code : null,
            ],
        ], self::HTTP_CREATED);
    }

    private function create_goods_receipt_direct(array $prepared)
    {
        $this->db->trans_start();

        $approval = 1;
        $checkAppr = $this->warehouse_model->get_approve_setting('1');
        if ($checkAppr) {
            $approval = 0;
        }

        $receiptData = [
            'goods_receipt_code'  => $this->warehouse_model->create_goods_code(),
            'date_c'              => $prepared['date_c'],
            'date_add'            => $prepared['date_add'],
            'supplier_name'       => $prepared['supplier_name'],
            'supplier_code'       => $prepared['supplier_code'],
            'buyer_id'            => $prepared['buyer_id'],
            'pr_order_id'         => $prepared['pr_order_id'],
            'warehouse_id'        => $prepared['warehouse_id'],
            'description'         => $prepared['description'],
            'approval'            => $approval,
            'addedfrom'           => get_staff_user_id(),
            'total_tax_money'     => reformat_currency_j($prepared['total_tax_money']),
            'total_goods_money'   => reformat_currency_j($prepared['total_goods_money']),
            'value_of_inventory'  => reformat_currency_j($prepared['value_of_inventory']),
            'total_money'         => reformat_currency_j($prepared['total_money']),
        ];

        if (!$this->db->field_exists('description', db_prefix() . 'goods_receipt')) {
            unset($receiptData['description']);
        }

        if ($receiptData['supplier_name'] === '' && $receiptData['supplier_code'] !== '' && get_status_modules_wh('purchase')) {
            $this->load->model('purchase/purchase_model');
            $vendor = $this->purchase_model->get_vendor($receiptData['supplier_code']);
            if ($vendor) {
                $receiptData['supplier_name'] = $vendor->company;
            }
        }

        $this->db->insert(db_prefix() . 'goods_receipt', $receiptData);
        $insertId = $this->db->insert_id();

        if ($insertId) {
            foreach ($prepared['newitems'] as $item) {
                $taxData = $this->warehouse_model->wh_get_tax_rate($item['tax_select'] ?? []);
                $taxRateValue = (float) $taxData['tax_rate'];

                $taxMoney = (float) $item['unit_price'] * (float) $item['quantities'] * $taxRateValue / 100;
                $goodsMoney = (float) $item['unit_price'] * (float) $item['quantities'] + $taxMoney;
                $subTotal = (float) $item['unit_price'] * (float) $item['quantities'];

                $detail = [
                    'goods_receipt_id' => $insertId,
                    'commodity_code'   => $item['commodity_code'],
                    'warehouse_id'     => $item['warehouse_id'],
                    'quantities'       => $item['quantities'],
                    'unit_price'       => $item['unit_price'],
                    'unit_id'          => $item['unit_id'] ?? null,
                    'lot_number'       => $item['lot_number'] ?? null,
                    'date_manufacture' => $item['date_manufacture'],
                    'expiry_date'      => $item['expiry_date'],
                    'note'             => $item['note'] ?? null,
                    'serial_number'    => $item['serial_number'] ?? null,
                    'tax_money'        => $taxMoney,
                    'tax'              => $taxData['tax_id_str'],
                    'goods_money'      => $goodsMoney,
                    'tax_rate'         => $taxData['tax_rate_str'],
                    'sub_total'        => $subTotal,
                    'tax_name'         => $taxData['tax_name_str'],
                ];

                $this->db->insert(db_prefix() . 'goods_receipt_detail', $detail);
            }

            $dataLog = [
                'rel_id'   => $insertId,
                'rel_type' => 'stock_import',
                'staffid'  => get_staff_user_id(),
                'date'     => date('Y-m-d H:i:s'),
                'note'     => 'stock_import',
            ];

            $this->warehouse_model->add_activity_log($dataLog);
            $this->warehouse_model->update_inventory_setting([
                'next_inventory_received_mumber' => get_warehouse_option('next_inventory_received_mumber') + 1,
            ]);
            $this->warehouse_model->update_purchase_order_from_goods_receipt($insertId);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false || !$insertId) {
            return null;
        }

        return [
            'id'       => $insertId,
            'approval' => $approval,
        ];
    }

    /**
     * Lists loss adjustments with optional pagination.
     */
    public function loss_adjustments_get()
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

        $lossAdjustments = $this->warehouse_model->get_loss_adjustment();
        $total           = count($lossAdjustments);

        if ($limit !== null) {
            $lossAdjustments = array_slice($lossAdjustments, $offset, $limit);
        }

        $this->response([
            'status' => true,
            'result' => $lossAdjustments,
            'total'  => $total,
        ], self::HTTP_OK);
    }

    /**
     * Retrieves a single loss adjustment with its detail rows.
     *
     * @param int|null $id
     */
    public function loss_adjustment_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid loss adjustment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $lossAdjustment = $this->warehouse_model->get_loss_adjustment((int) $id);

        if (!$lossAdjustment) {
            $this->response([
                'status'  => false,
                'message' => 'Loss adjustment not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $details = $this->warehouse_model->get_loss_adjustment_detail_with_items((int) $id);

        $this->response([
            'status' => true,
            'result' => [
                'loss_adjustment' => $lossAdjustment,
                'items'           => $details,
            ],
        ], self::HTTP_OK);
    }

    /**
     * Creates a new loss adjustment with detail rows.
     */
    public function loss_adjustments_post()
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

        $prepared = $this->prepare_loss_adjustment_payload($payload, false);

        if (isset($prepared['error'])) {
            $this->response([
                'status'  => false,
                'message' => $prepared['error'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $insertId = $this->warehouse_model->add_loss_adjustment($prepared, [
            'skip_hooks' => true,
            'skip_auto_adjust' => true,
        ]);

        if (!$insertId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create loss adjustment with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $lossAdjustment = $this->warehouse_model->get_loss_adjustment((int) $insertId);
        if ($lossAdjustment && (int) $lossAdjustment->status === 1) {
            $this->load_accounting_model();
            $this->accounting_model->automatic_loss_adjustment_conversion($insertId);
            $this->warehouse_model->change_adjust((int) $insertId);
        }

        $lossAdjustment = $this->warehouse_model->get_loss_adjustment((int) $insertId);
        if ($lossAdjustment && (int) $lossAdjustment->status === 1) {
            $this->warehouse_model->change_adjust((int) $insertId);
        }

        $this->response([
            'status' => true,
            'result' => [
                'id' => $insertId,
            ],
        ], self::HTTP_CREATED);
    }

    /**
     * Updates an existing loss adjustment with detail rows.
     *
     * @param int|null $id
     */
    public function loss_adjustment_put($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid loss adjustment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->warehouse_model->get_loss_adjustment((int) $id);

        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Loss adjustment not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $payload = $this->get_request_payload('put');
        $isDraftRequest = false;
        if (array_key_exists('is_draft', $payload)) {
            $isDraftRequest = $this->interpret_boolean($payload['is_draft']);
        } elseif (array_key_exists('status', $payload) && (int) $payload['status'] === 2) {
            $isDraftRequest = true;
        }

        if ($payload === []) {
            $this->response([
                'status'  => false,
                'message' => 'Empty request body provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $itemsPreparation = $this->prepare_loss_adjustment_items($payload['items'], false);

        if (isset($itemsPreparation['error'])) {
            $this->response([
                'status'  => false,
                'message' => $itemsPreparation['error'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $prepared = $this->prepare_loss_adjustment_payload($payload, true, $existing);

        if (isset($prepared['error'])) {
            $this->response([
                'status'  => false,
                'message' => $prepared['error'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existingDetails = $this->warehouse_model->get_loss_adjustment_detailt_by_masterid((int) $id);
        $existingDetailIds = [];
        foreach ($existingDetails as $detail) {
            if (isset($detail['id'])) {
                $existingDetailIds[] = (int) $detail['id'];
            }
        }

        $prepared['newitems'] = $itemsPreparation['items'];
        $prepared['removed_items'] = $existingDetailIds;
        unset($prepared['items']);
        $prepared['id'] = (int) $id;

        $updated = $this->warehouse_model->update_loss_adjustment($prepared);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update loss adjustment with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        if (!$isDraftRequest) {
            $updatedLossAdjustment = $this->warehouse_model->get_loss_adjustment((int) $id);

            if ($updatedLossAdjustment && (int) $updatedLossAdjustment->status === 1) {
                $this->db->where('goods_receipt_id', (int) $id);
                $this->db->where('status', 3);
                $this->db->delete(db_prefix() . 'goods_transaction_detail');

                $this->warehouse_model->change_adjust((int) $id);

                $this->load_accounting_model();
                $this->accounting_model->automatic_loss_adjustment_conversion((int) $id);
            }
        }

        $this->response([
            'status' => true,
            'result' => [
                'id' => (int) $id,
            ],
        ], self::HTTP_OK);
    }

    /**
     * Deletes a loss adjustment and its detail rows.
     *
     * @param int|null $id
     */
    public function loss_adjustment_delete($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid loss adjustment identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $lossAdjustment = $this->warehouse_model->get_loss_adjustment((int) $id);

        if (!$lossAdjustment) {
            $this->response([
                'status'  => false,
                'message' => 'Loss adjustment not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if ((int) $lossAdjustment->status === 1) {
            $this->warehouse_model->revert_loss_adjustment_inventory((int) $id);
        }
        $deleted = $this->warehouse_model->delete_loss_adjustment((int) $id, [
            'skip_hooks' => true,
            'skip_inventory' => true,
        ]);

        if (!$deleted) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to delete loss adjustment.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        if ((int) $lossAdjustment->status === 1) {
            $this->load_accounting_model();
            $this->accounting_model->delete_convert((int) $id, 'loss_adjustment');
        }

        $this->response([
            'status'  => true,
            'message' => 'Loss adjustment deleted successfully.',
        ], self::HTTP_OK);
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
        $prOrderId    = isset($payload['pr_order_id']) ? (int) $payload['pr_order_id'] : (isset($payload['purchase_order_id']) ? (int) $payload['purchase_order_id'] : '');
        $warehouseId  = isset($payload['warehouse_id']) ? (int) $payload['warehouse_id'] : '';

        return [
            'date_c'            => $date_c,
            'date_add'          => $date_add,
            'supplier_name'     => isset($payload['supplier_name']) ? (string) $payload['supplier_name'] : '',
            'supplier_code'     => $supplierCode,
            'buyer_id'          => $buyerId,
            'pr_order_id'       => $prOrderId,
            'warehouse_id'      => $warehouseId,
            'description'       => isset($payload['description']) ? (string) $payload['description'] : '',
            'total_tax_money'   => $itemsPreparation['totals']['tax'],
            'total_goods_money' => $itemsPreparation['totals']['goods'],
            'value_of_inventory'=> $itemsPreparation['totals']['goods'],
            'total_money'       => $itemsPreparation['totals']['goods'] + $itemsPreparation['totals']['tax'],
            'newitems'          => $itemsPreparation['items'],
        ];
    }

    private function prepare_loss_adjustment_payload(array $payload, bool $isUpdate, $current = null)
    {
        if (!isset($payload['items']) || !is_array($payload['items']) || $payload['items'] === []) {
            return ['error' => 'At least one item is required to create a loss adjustment.'];
        }

        $itemsPreparation = $this->prepare_loss_adjustment_items($payload['items'], $isUpdate);

        if (isset($itemsPreparation['error'])) {
            return ['error' => $itemsPreparation['error']];
        }

        $warehouseId = null;
        if (isset($payload['warehouse_id'])) {
            $warehouseId = (int) $payload['warehouse_id'];
        } elseif (isset($payload['warehouses'])) {
            $warehouseId = (int) $payload['warehouses'];
        } elseif ($current && isset($current->warehouses)) {
            $warehouseId = (int) $current->warehouses;
        }

        if ($warehouseId === null || $warehouseId <= 0) {
            return ['error' => 'warehouse_id is required to create a loss adjustment.'];
        }

        $type = isset($payload['type']) ? trim((string) $payload['type']) : '';
        if ($type === '' && $current && isset($current->type)) {
            $type = (string) $current->type;
        }

        if ($type === '') {
            return ['error' => 'type is required to create a loss adjustment.'];
        }

        $time = $payload['time'] ?? ($current && isset($current->time) ? $current->time : date('Y-m-d H:i:s'));
        $time = $this->normalize_datetime_value($time);

        $dateCreate = $payload['date_create'] ?? ($current && isset($current->date_create) ? $current->date_create : date('Y-m-d'));
        $dateCreate = $this->normalize_date_value($dateCreate);

        $addFrom = $payload['addfrom'] ?? ($current && isset($current->addfrom) ? $current->addfrom : get_staff_user_id());
        $addFrom = is_numeric($addFrom) ? (int) $addFrom : null;

        $items = $itemsPreparation['items'];
        $newItems = $items;
        $existingItems = [];

        if ($isUpdate) {
            $existingItems = [];
            $newItems = [];
            foreach ($items as $line) {
                if (isset($line['id'])) {
                    $existingItems[] = $line;
                } else {
                    $newItems[] = $line;
                }
            }
        }

        $removedItems = [];
        if ($isUpdate && isset($payload['removed_item_ids']) && is_array($payload['removed_item_ids'])) {
            foreach ($payload['removed_item_ids'] as $removedId) {
                if (is_numeric($removedId)) {
                    $removedItems[] = (int) $removedId;
                }
            }
        }

        $prepared = [
            'type'        => $type,
            'time'        => $time,
            'reason'      => isset($payload['reason']) ? (string) $payload['reason'] : ($current && isset($current->reason) ? $current->reason : ''),
            'addfrom'     => $addFrom,
            'date_create' => $dateCreate,
            'warehouses'  => $warehouseId,
            'newitems'    => $newItems,
        ];

        if ($isUpdate) {
            $prepared['items'] = $existingItems;
            $prepared['removed_items'] = $removedItems;
        }

        if (array_key_exists('is_draft', $payload)) {
            $prepared['is_draft'] = $this->interpret_boolean($payload['is_draft']) ? 1 : 0;
        } elseif (array_key_exists('status', $payload) && (int) $payload['status'] === 2) {
            $prepared['is_draft'] = 1;
        }

        return $prepared;
    }

    private function prepare_loss_adjustment_items(array $items, bool $allowExistingIds = false)
    {
        $prepared = [];

        foreach (array_values($items) as $item) {
            if (!isset($item['items'], $item['unit'], $item['current_number'], $item['updates_number'])) {
                return ['error' => 'items, unit, current_number, and updates_number are required for each item.'];
            }

            if (!is_numeric($item['items']) || !is_numeric($item['unit'])) {
                return ['error' => 'items and unit must be numeric values.'];
            }

            if (!is_numeric($item['current_number']) || !is_numeric($item['updates_number'])) {
                return ['error' => 'current_number and updates_number must be numeric values.'];
            }

            $expiryDate = $this->normalize_optional_date($item['expiry_date'] ?? null);

            $lotNumber = null;
            if (isset($item['lot_number'])) {
                $lotNumber = trim((string) $item['lot_number']);
                if ($lotNumber === '') {
                    $lotNumber = null;
                }
            }

            $serialNumber = null;
            if (isset($item['serial_number'])) {
                if (is_array($item['serial_number'])) {
                    $serialNumber = implode(',', $item['serial_number']);
                } else {
                    $serialNumber = trim((string) $item['serial_number']);
                }
                if ($serialNumber === '') {
                    $serialNumber = null;
                }
            }

            $preparedItem = [
                'items'          => (int) $item['items'],
                'unit'           => (int) $item['unit'],
                'current_number' => $item['current_number'],
                'updates_number' => $item['updates_number'],
                'commodity_name' => isset($item['commodity_name']) ? (string) $item['commodity_name'] : null,
                'lot_number'     => $lotNumber,
                'expiry_date'    => $expiryDate,
                'serial_number'  => $serialNumber,
            ];

            if ($allowExistingIds && isset($item['id']) && is_numeric($item['id'])) {
                $preparedItem['id'] = (int) $item['id'];
            }

            $prepared[] = $preparedItem;
        }

        return ['items' => $prepared];
    }

    private function prepare_goods_receipt_update_payload(array $payload, int $id)
    {
        if (!isset($payload['items']) || !is_array($payload['items']) || $payload['items'] === []) {
            return ['error' => 'At least one item is required to update a goods receipt.'];
        }

        $itemsPreparation = $this->prepare_goods_receipt_items($payload['items'], true);

        if (isset($itemsPreparation['error'])) {
            return ['error' => $itemsPreparation['error']];
        }

        $existingItems = [];
        $newItems      = [];

        foreach ($itemsPreparation['items'] as $line) {
            if (isset($line['id'])) {
                $existingItems[] = $line;
            } else {
                $newItems[] = $line;
            }
        }

        $removed = [];

        if (isset($payload['removed_item_ids']) && is_array($payload['removed_item_ids'])) {
            foreach ($payload['removed_item_ids'] as $removedId) {
                if (is_numeric($removedId)) {
                    $removed[] = (int) $removedId;
                }
            }
        }

        $date_c   = $this->normalize_date_value($payload['date_c'] ?? date('Y-m-d'));
        $date_add = $this->normalize_date_value($payload['date_add'] ?? date('Y-m-d'));

        $supplierCode = isset($payload['supplier_code']) ? (string) $payload['supplier_code'] : '';
        $buyerId      = isset($payload['buyer_id']) ? (int) $payload['buyer_id'] : '';
        $prOrderId    = isset($payload['pr_order_id']) ? (int) $payload['pr_order_id'] : (isset($payload['purchase_order_id']) ? (int) $payload['purchase_order_id'] : '');
        $warehouseId  = isset($payload['warehouse_id']) ? (int) $payload['warehouse_id'] : '';

        return [
            'id'                => $id,
            'date_c'            => $date_c,
            'date_add'          => $date_add,
            'supplier_name'     => isset($payload['supplier_name']) ? (string) $payload['supplier_name'] : '',
            'supplier_code'     => $supplierCode,
            'buyer_id'          => $buyerId,
            'pr_order_id'       => $prOrderId,
            'warehouse_id'      => $warehouseId,
            'description'       => isset($payload['description']) ? (string) $payload['description'] : '',
            'total_tax_money'   => $itemsPreparation['totals']['tax'],
            'total_goods_money' => $itemsPreparation['totals']['goods'],
            'value_of_inventory'=> $itemsPreparation['totals']['goods'],
            'total_money'       => $itemsPreparation['totals']['goods'] + $itemsPreparation['totals']['tax'],
            'items'             => $existingItems,
            'newitems'          => $newItems,
            'removed_items'     => $removed,
        ];
    }

    private function prepare_goods_receipt_items(array $items, bool $allowExistingIds = false)
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

            $lotNumber = null;

            if (isset($item['lot_number'])) {
                $lotNumber = trim((string) $item['lot_number']);

                if ($lotNumber === '') {
                    $lotNumber = null;
                }
            }

            if ($lotNumber === null && get_option('auto_generate_lotnumber') == 1) {
                $lotNumber = $this->warehouse_model->create_lot_number();
            }

            $preparedItem = [
                'commodity_code'  => (int) $item['commodity_code'],
                'commodity_name'  => isset($item['commodity_name']) ? (string) $item['commodity_name'] : null,
                'warehouse_id'    => (int) $item['warehouse_id'],
                'quantities'      => $quantity,
                'unit_price'      => $unitPrice,
                'tax_select'      => $taxSelect,
                'lot_number'      => $lotNumber,
                'note'            => isset($item['note']) ? (string) $item['note'] : null,
                'serial_number'   => isset($item['serial_number']) ? (string) $item['serial_number'] : null,
            ];

            if ($allowExistingIds && isset($item['id']) && is_numeric($item['id'])) {
                $preparedItem['id'] = (int) $item['id'];
            }

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

    private function normalize_datetime_value($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return date('Y-m-d H:i:s');
        }

        return $this->warehouse_model->check_format_date($value) ? $value : to_sql_date($value, true);
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

    private function normalize_int_list($value)
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $normalized = [];

        foreach ($items as $item) {
            if (is_numeric($item)) {
                $normalized[] = (int) $item;
            }
        }

        return array_values(array_unique($normalized));
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

    private function prepare_inventory_manage_payload(array $input, bool $is_update)
    {
        $payload = [];

        $warehouseId = $input['warehouse_id'] ?? null;
        $commodityId = $input['commodity_id'] ?? null;
        $inventoryNumber = $input['inventory_number'] ?? null;

        if (!$is_update && ($warehouseId === null || $warehouseId === '' || $commodityId === null || $commodityId === '' || $inventoryNumber === null || $inventoryNumber === '')) {
            return [];
        }

        if ($warehouseId !== null && $warehouseId !== '') {
            if (!is_numeric($warehouseId)) {
                return [];
            }

            $payload['warehouse_id'] = (int) $warehouseId;
        }

        if ($commodityId !== null && $commodityId !== '') {
            if (!is_numeric($commodityId)) {
                return [];
            }

            $payload['commodity_id'] = (int) $commodityId;
        }

        if ($inventoryNumber !== null && $inventoryNumber !== '') {
            $payload['inventory_number'] = is_numeric($inventoryNumber)
                ? (string) $inventoryNumber
                : trim((string) $inventoryNumber);
        }

        $optionalDateFields = ['date_manufacture', 'expiry_date'];

        foreach ($optionalDateFields as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = $this->normalize_optional_date($input[$field]);
            }
        }

        $optionalFields = ['lot_number', 'purchase_price'];

        foreach ($optionalFields as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];

                if ($value === null || $value === '') {
                    $payload[$field] = null;
                    continue;
                }

                $payload[$field] = is_numeric($value) ? (string) $value : trim((string) $value);
            }
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

    private function format_warehouse_history_entry(array $row)
    {
        $goodsReceipt = [
            'code' => $row['goods_receipt_code'] ?? null,
            'date_add' => $row['goods_receipt_date_add'] ?? null,
            'supplier_name' => $row['goods_receipt_supplier_name'] ?? null,
            'supplier_code' => $row['goods_receipt_supplier_code'] ?? null,
        ];
        $commodity = [
            'name' => $row['commodity_name'] ?? null,
        ];
        $warehouse = [
            'code' => $row['warehouse_code'] ?? null,
            'name' => $row['warehouse_name'] ?? null,
        ];
        $goodsReceiptDetail = [
            'commodity_code' => $row['goods_receipt_commodity_code'] ?? null,
        ];
        $goodsDelivery = [
            'code' => $row['goods_delivery_code'] ?? null,
            'date_add' => $row['goods_delivery_date_add'] ?? null,
        ];
        $internalDeliveryNote = [
            'code' => $row['internal_delivery_code'] ?? null,
            'date_add' => $row['internal_delivery_date_add'] ?? null,
        ];
        $lossAdjustment = [
            'date_create' => $row['loss_adjustment_date_create'] ?? null,
        ];

        $aliases = [
            'goods_receipt_code',
            'goods_receipt_date_add',
            'goods_receipt_supplier_name',
            'goods_receipt_supplier_code',
            'goods_receipt_commodity_code',
            'commodity_name',
            'warehouse_code',
            'warehouse_name',
            'goods_delivery_code',
            'goods_delivery_date_add',
            'internal_delivery_code',
            'internal_delivery_date_add',
            'loss_adjustment_date_create',
        ];

        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row)) {
                unset($row[$alias]);
            }
        }

        $row['goods_receipt'] = $this->normalize_history_payload($goodsReceipt);
        $row['commodity'] = $this->normalize_history_payload($commodity);
        $row['warehouse'] = $this->normalize_history_payload($warehouse);
        $row['goods_receipt_detail'] = $this->normalize_history_payload($goodsReceiptDetail);
        $row['goods_delivery'] = $this->normalize_history_payload($goodsDelivery);
        $row['internal_delivery_note'] = $this->normalize_history_payload($internalDeliveryNote);
        $row['loss_adjustment'] = $this->normalize_history_payload($lossAdjustment);

        return $row;
    }

    private function merge_warehouse_history_entries(array $history)
    {
        $merged = [];
        $sequence = [];

        foreach ($history as $index => $row) {
            $receiptId = $row['goods_receipt_id'] ?? null;
            $key = $receiptId !== null ? 'receipt_' . $receiptId : 'row_' . $index;

            if (!isset($merged[$key])) {
                $sequence[] = $key;
                $merged[$key] = $row;
                $merged[$key]['quantity'] = $this->normalize_history_quantity($row['quantity'] ?? null);
                $merged[$key]['commodities'] = [];
            } else {
                $merged[$key]['quantity'] = $this->sum_history_quantity(
                    $merged[$key]['quantity'] ?? null,
                    $row['quantity'] ?? null
                );
            }

            $commodityPayload = $this->extract_history_commodity($row);
            if ($commodityPayload !== null) {
                $merged[$key]['commodities'][] = $commodityPayload;
            }

            if (empty($merged[$key]['note']) && !empty($row['note'])) {
                $merged[$key]['note'] = $row['note'];
            }
        }

        $result = [];
        foreach ($sequence as $key) {
            $result[] = $merged[$key];
        }

        return $result;
    }

    private function extract_history_commodity(array $row)
    {
        $payload = [
            'commodity_id' => $row['commodity_id'] ?? null,
            'commodity_code' => $row['goods_receipt_commodity_code'] ?? null,
            'commodity_name' => $row['commodity_name'] ?? null,
            'quantity' => $row['quantity'] ?? null,
        ];

        foreach ($payload as $value) {
            if ($value !== null && $value !== '') {
                return $payload;
            }
        }

        return null;
    }

    private function normalize_history_quantity($quantity)
    {
        if ($quantity === null || $quantity === '') {
            return null;
        }

        if (!is_numeric($quantity)) {
            return $quantity;
        }

        return $this->format_history_quantity((float) $quantity);
    }

    private function sum_history_quantity($current, $next)
    {
        if (!is_numeric($current)) {
            return $current;
        }

        if (!is_numeric($next)) {
            return $current;
        }

        $sum = (float) $current + (float) $next;

        return $this->format_history_quantity($sum);
    }

    private function format_history_quantity(float $value)
    {
        if (floor($value) === $value) {
            return (string) ((int) $value);
        }

        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    private function normalize_history_payload(array $payload)
    {
        foreach ($payload as $value) {
            if ($value !== null && $value !== '') {
                return $payload;
            }
        }

        return null;
    }
}
