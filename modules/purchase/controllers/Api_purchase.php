<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_purchase extends API_Controller
{
    public function __construct()
    {
        $this->module_language_file      = 'purchase';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->model('purchase/purchase_model');
        $this->load->model('currencies_model');
        $this->load->helper('purchase/purchase');
    }

    public function orders_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $limit  = $this->get('limit');
        $offset = $this->get('offset');
        $vendor = $this->get('vendor');
        $status = $this->get('status');
        $approval = $this->get('approve_status');
        $search = trim((string) $this->get('search'));
        $from   = $this->get('from_date');
        $to     = $this->get('to_date');

        $limit  = is_numeric($limit) ? (int) $limit : 50;
        $offset = is_numeric($offset) ? (int) $offset : 0;

        if ($limit <= 0) {
            $limit = 50;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $this->db->select('po.*, v.company as vendor_name, c.name as currency_name, c.symbol as currency_symbol');
        $this->db->from(db_prefix() . 'pur_orders as po');
        $this->db->join(db_prefix() . 'pur_vendor as v', 'v.userid = po.vendor', 'left');
        $this->db->join(db_prefix() . 'currencies as c', 'c.id = po.currency', 'left');

        if (is_numeric($vendor)) {
            $this->db->where('po.vendor', (int) $vendor);
        }

        if (is_numeric($status)) {
            $this->db->where('po.status', (int) $status);
        }

        if (is_numeric($approval)) {
            $this->db->where('po.approve_status', (int) $approval);
        }

        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('po.pur_order_number', $search);
            $this->db->or_like('po.pur_order_name', $search);
            $this->db->or_like('v.company', $search);
            $this->db->group_end();
        }

        if ($from) {
            $from_sql = to_sql_date($from);
            if ($from_sql) {
                $this->db->where('po.order_date >=', $from_sql);
            }
        }

        if ($to) {
            $to_sql = to_sql_date($to);
            if ($to_sql) {
                $this->db->where('po.order_date <=', $to_sql);
            }
        }

        $this->db->order_by('po.id', 'DESC');
        $this->db->limit($limit, $offset);

        $orders = $this->db->get()->result_array();

        $results = [];
        foreach ($orders as $order) {
            $results[] = $this->format_order_summary($order);
        }

        $this->response([
            'status' => true,
            'result' => $results,
        ], self::HTTP_OK);
    }

    public function orders_post()
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

        $prepared = $this->prepare_order_payload($payload, false);

        if ($prepared['errors'] !== []) {
            $this->response([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $prepared['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $order_id = $this->purchase_model->add_pur_order($prepared['data']);

        if (!$order_id) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create purchase order with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $order = $this->get_order_with_details($order_id);

        $this->response([
            'status' => true,
            'result' => $order,
        ], self::HTTP_CREATED);
    }

    public function order_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $order = $this->get_order_with_details((int) $id);

        if (!$order) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $order,
        ], self::HTTP_OK);
    }

    public function order_put($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $existing = $this->purchase_model->get_pur_order((int) $id);

        if (!$existing) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found.',
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

        $prepared = $this->prepare_order_payload($payload, true, $existing);

        if ($prepared['errors'] !== []) {
            $this->response([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $prepared['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $prepared['data']['isedit'] = true;
        $prepared['data']['pur_order_number'] = isset($prepared['data']['pur_order_number'])
            ? $prepared['data']['pur_order_number']
            : $existing->pur_order_number;

        $current_items = $this->purchase_model->get_pur_order_detail((int) $id);
        $prepared['data']['removed_items'] = [];

        foreach ($current_items as $item) {
            if (isset($item['id'])) {
                $prepared['data']['removed_items'][] = (int) $item['id'];
            }
        }

        $success = $this->purchase_model->update_pur_order($prepared['data'], (int) $id);

        if (!$success) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order update failed or no changes were detected.',
            ], self::HTTP_OK);

            return;
        }

        $order = $this->get_order_with_details((int) $id);

        $this->response([
            'status' => true,
            'result' => $order,
        ], self::HTTP_OK);
    }

    public function order_delete($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $deleted = $this->purchase_model->delete_pur_order((int) $id);

        if (!$deleted) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to delete purchase order or it was not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Purchase order deleted successfully.',
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

    private function prepare_order_payload(array $payload, bool $is_update, $existing = null)
    {
        $errors = [];

        $name = isset($payload['pur_order_name']) ? trim((string) $payload['pur_order_name']) : '';
        if ($name === '') {
            $errors['pur_order_name'] = 'Purchase order name is required.';
        }

        $vendor_id = isset($payload['vendor_id']) ? (int) $payload['vendor_id'] : (isset($payload['vendor']) ? (int) $payload['vendor'] : 0);
        if ($vendor_id <= 0) {
            $errors['vendor_id'] = 'Vendor identifier is required.';
        } else {
            $vendor = $this->purchase_model->get_vendor($vendor_id);
            if (!$vendor) {
                $errors['vendor_id'] = 'The specified vendor could not be found.';
            }
        }

        $line_items = isset($payload['line_items']) && is_array($payload['line_items']) ? $payload['line_items'] : [];
        if ($line_items === []) {
            $errors['line_items'] = 'At least one line item is required.';
        }

        $currency_id = isset($payload['currency_id']) ? (int) $payload['currency_id'] : 0;
        if ($currency_id > 0) {
            $currency = $this->currencies_model->get($currency_id);
            if (!$currency) {
                $errors['currency_id'] = 'The specified currency could not be found.';
            }
        } else {
            $base_currency = get_base_currency_pur();
            $currency_id   = $base_currency ? (int) $base_currency->id : 0;
        }

        if ($errors !== []) {
            return ['data' => [], 'errors' => $errors];
        }

        $order_number_data = $this->generate_order_number($vendor_id, $existing, $is_update);

        $items_result = $this->build_order_items($line_items);

        if ($items_result['errors'] !== []) {
            return ['data' => [], 'errors' => $items_result['errors']];
        }

        $order_date    = isset($payload['order_date']) ? $payload['order_date'] : date('Y-m-d');
        $delivery_date = isset($payload['delivery_date']) ? $payload['delivery_date'] : null;

        $discount_type = isset($payload['discount_type']) && in_array($payload['discount_type'], ['before_tax', 'after_tax'], true)
            ? $payload['discount_type']
            : 'after_tax';

        $buyer_id = isset($payload['buyer_id']) ? (int) $payload['buyer_id'] : (isset($payload['buyer']) ? (int) $payload['buyer'] : 0);
        $department = isset($payload['department_id']) ? (int) $payload['department_id'] : 0;
        $project    = isset($payload['project_id']) ? (int) $payload['project_id'] : 0;
        $type       = isset($payload['type']) ? trim((string) $payload['type']) : '';

        $clients = [];
        if (isset($payload['client_ids']) && is_array($payload['client_ids'])) {
            foreach ($payload['client_ids'] as $client_id) {
                if (is_numeric($client_id)) {
                    $clients[] = (int) $client_id;
                }
            }
        }

        $data = [
            'pur_order_name' => $name,
            'vendor'         => $vendor_id,
            'estimate'       => isset($payload['estimate_id']) ? (int) $payload['estimate_id'] : (isset($payload['estimate']) ? (int) $payload['estimate'] : 0),
            'pur_request'    => isset($payload['request_id']) ? (int) $payload['request_id'] : (isset($payload['pur_request']) ? (int) $payload['pur_request'] : 0),
            'currency'       => $currency_id,
            'currency_rate'  => isset($payload['currency_rate']) ? (float) $payload['currency_rate'] : 1,
            'buyer'          => $buyer_id,
            'department'     => $department,
            'project'        => $project,
            'type'           => $type,
            'discount_type'  => $discount_type,
            'terms'          => isset($payload['terms']) ? (string) $payload['terms'] : '',
            'vendornote'     => isset($payload['vendor_note']) ? (string) $payload['vendor_note'] : '',
            'order_date'     => $order_date,
            'delivery_date'  => $delivery_date,
            'days_owed'      => isset($payload['days_owed']) ? (int) $payload['days_owed'] : 0,
            'sale_invoice'   => isset($payload['sale_invoice_id']) ? (int) $payload['sale_invoice_id'] : (isset($payload['sale_invoice']) ? (int) $payload['sale_invoice'] : 0),
            'shipping_fee'   => isset($payload['shipping_fee']) ? (float) $payload['shipping_fee'] : 0,
            'shipping_address'      => isset($payload['shipping_address']) ? (string) $payload['shipping_address'] : '',
            'shipping_city'         => isset($payload['shipping_city']) ? (string) $payload['shipping_city'] : '',
            'shipping_state'        => isset($payload['shipping_state']) ? (string) $payload['shipping_state'] : '',
            'shipping_zip'          => isset($payload['shipping_zip']) ? (string) $payload['shipping_zip'] : '',
            'shipping_country'      => isset($payload['shipping_country']) ? (string) $payload['shipping_country'] : '',
            'shipping_country_text' => isset($payload['shipping_country_text']) ? (string) $payload['shipping_country_text'] : '',
            'clients'        => $clients,
            'total_mn'       => $items_result['subtotal'],
            'grand_total'    => $items_result['total'],
            'dc_total'       => $items_result['discount_total'],
            'total_tax'      => $items_result['tax_total'],
            'newitems'       => $items_result['items'],
            'add_discount_type' => isset($payload['order_discount_type']) ? (string) $payload['order_discount_type'] : 'percent',
        ];

        if (!$is_update) {
            $data['number']           = $order_number_data['number'];
            $data['pur_order_number'] = $order_number_data['formatted'];
        } else {
            if (isset($payload['pur_order_number']) && trim((string) $payload['pur_order_number']) !== '') {
                $data['pur_order_number'] = trim((string) $payload['pur_order_number']);
            }

            if ($existing && isset($existing->number)) {
                $data['number'] = (int) $existing->number;
            }
        }

        if (isset($payload['order_discount'])) {
            $data['order_discount'] = (float) $payload['order_discount'];
        }

        if (isset($payload['tags']) && is_array($payload['tags'])) {
            $data['tags'] = array_values(array_filter(array_map('trim', $payload['tags'])));
        }

        if (isset($payload['custom_fields']) && is_array($payload['custom_fields'])) {
            $data['custom_fields'] = $payload['custom_fields'];
        }

        return ['data' => $data, 'errors' => []];
    }

    private function generate_order_number($vendor_id, $existing, $is_update)
    {
        if ($is_update && $existing) {
            return [
                'number'    => isset($existing->number) ? (int) $existing->number : 0,
                'formatted' => $existing->pur_order_number,
            ];
        }

        $number = (int) get_purchase_option('next_po_number');
        if ($number <= 0) {
            $number = 1;
        }

        $prefix = get_purchase_option('pur_order_prefix');
        if ($prefix === '') {
            $prefix = 'PO';
        }

        if ((int) get_option('po_only_prefix_and_number') === 1) {
            $formatted = $prefix . '-' . str_pad($number, 5, '0', STR_PAD_LEFT);
        } else {
            $formatted = $prefix . '-' . str_pad($number, 5, '0', STR_PAD_LEFT) . '-' . date('dmY');
        }

        return [
            'number'    => $number,
            'formatted' => $formatted,
        ];
    }

    private function build_order_items(array $items)
    {
        $result_items   = [];
        $subtotal       = 0;
        $tax_total      = 0;
        $discount_total = 0;
        $errors         = [];

        $all_tax_ids = [];
        foreach ($items as $item) {
            if (isset($item['tax_ids']) && is_array($item['tax_ids'])) {
                foreach ($item['tax_ids'] as $tax_id) {
                    if (is_numeric($tax_id)) {
                        $all_tax_ids[] = (int) $tax_id;
                    }
                }
            }
        }

        $tax_lookup = $this->get_taxes_by_ids(array_unique($all_tax_ids));

        foreach ($items as $index => $item) {
            $item_name = isset($item['name']) ? trim((string) $item['name']) : (isset($item['item_name']) ? trim((string) $item['item_name']) : '');
            if ($item_name === '') {
                $errors['line_items.' . $index . '.name'] = 'Item name is required.';
                continue;
            }

            $quantity   = isset($item['quantity']) ? (float) $item['quantity'] : 0.0;
            $unit_price = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.0;

            if ($quantity <= 0) {
                $errors['line_items.' . $index . '.quantity'] = 'Quantity must be greater than zero.';
                continue;
            }

            if ($unit_price < 0) {
                $errors['line_items.' . $index . '.unit_price'] = 'Unit price cannot be negative.';
                continue;
            }

            $into_money = $quantity * $unit_price;

            $discount_percent = isset($item['discount_percent']) ? (float) $item['discount_percent'] : (isset($item['discount']) ? (float) $item['discount'] : 0.0);
            $discount_amount  = isset($item['discount_amount']) ? (float) $item['discount_amount'] : (isset($item['discount_money']) ? (float) $item['discount_money'] : 0.0);

            if ($discount_amount <= 0 && $discount_percent > 0) {
                $discount_amount = ($into_money * $discount_percent) / 100;
            } elseif ($discount_amount > 0 && $discount_percent <= 0 && $into_money > 0) {
                $discount_percent = ($discount_amount / $into_money) * 100;
            }

            if ($discount_amount > $into_money) {
                $discount_amount  = $into_money;
                $discount_percent = 100;
            }

            $line_subtotal = $into_money - $discount_amount;

            $tax_ids = [];
            if (isset($item['tax_ids']) && is_array($item['tax_ids'])) {
                foreach ($item['tax_ids'] as $tax_id) {
                    if (is_numeric($tax_id)) {
                        $tax_ids[] = (int) $tax_id;
                    }
                }
            }

            $tax_select = [];
            $tax_rate_total = 0;
            foreach ($tax_ids as $tax_id) {
                if (isset($tax_lookup[$tax_id])) {
                    $tax_data        = $tax_lookup[$tax_id];
                    $tax_select[]    = $tax_data['name'] . '|' . $tax_data['rate'];
                    $tax_rate_total += $tax_data['rate'];
                }
            }

            $tax_value = $tax_rate_total > 0 ? ($line_subtotal * $tax_rate_total) / 100 : 0;
            $line_total = $line_subtotal + $tax_value;

            $subtotal       += $line_subtotal;
            $tax_total      += $tax_value;
            $discount_total += $discount_amount;

            $result_items[] = [
                'item_code'       => isset($item['item_code']) ? (string) $item['item_code'] : '',
                'item_name'       => $item_name,
                'item_description'=> isset($item['description']) ? (string) $item['description'] : (isset($item['item_description']) ? (string) $item['item_description'] : ''),
                'unit_id'         => isset($item['unit_id']) ? $item['unit_id'] : (isset($item['unit']) ? $item['unit'] : null),
                'unit_price'      => $unit_price,
                'quantity'        => $quantity,
                'into_money'      => $into_money,
                'total_money'     => $line_subtotal,
                'total'           => $line_total,
                'discount_money'  => $discount_amount,
                'discount'        => $discount_percent,
                'tax_value'       => $tax_value,
                'tax_select'      => $tax_select,
            ];
        }

        return [
            'items'          => $result_items,
            'subtotal'       => $subtotal,
            'total'          => $subtotal + $tax_total,
            'tax_total'      => $tax_total,
            'discount_total' => $discount_total,
            'errors'         => $errors,
        ];
    }

    private function get_taxes_by_ids(array $ids)
    {
        $tax_lookup = [];
        $ids        = array_values(array_filter(array_unique($ids)));

        if ($ids === []) {
            return $tax_lookup;
        }

        $this->db->where_in('id', $ids);
        $taxes = $this->db->get(db_prefix() . 'taxes')->result();

        foreach ($taxes as $tax) {
            $tax_lookup[(int) $tax->id] = [
                'id'   => (int) $tax->id,
                'name' => $tax->name,
                'rate' => (float) $tax->taxrate,
            ];
        }

        return $tax_lookup;
    }

    private function get_order_with_details($id)
    {
        $this->db->select('po.*, v.company as vendor_name, c.name as currency_name, c.symbol as currency_symbol');
        $this->db->from(db_prefix() . 'pur_orders as po');
        $this->db->join(db_prefix() . 'pur_vendor as v', 'v.userid = po.vendor', 'left');
        $this->db->join(db_prefix() . 'currencies as c', 'c.id = po.currency', 'left');
        $this->db->where('po.id', $id);

        $order = $this->db->get()->row_array();

        if (!$order) {
            return null;
        }

        $details = $this->db->where('pur_order', $id)->get(db_prefix() . 'pur_order_detail')->result_array();

        $formatted_items = [];
        foreach ($details as $detail) {
            $formatted_items[] = $this->format_order_item($detail);
        }

        $order['line_items'] = $formatted_items;

        return $this->format_order_summary($order, true);
    }

    private function format_order_summary(array $order, $include_details = false)
    {
        $result = [
            'id'               => (int) $order['id'],
            'pur_order_number' => $order['pur_order_number'],
            'pur_order_name'   => isset($order['pur_order_name']) ? $order['pur_order_name'] : '',
            'vendor_id'        => isset($order['vendor']) ? (int) $order['vendor'] : 0,
            'vendor_name'      => isset($order['vendor_name']) ? $order['vendor_name'] : null,
            'status'           => isset($order['status']) ? (int) $order['status'] : null,
            'approve_status'   => isset($order['approve_status']) ? (int) $order['approve_status'] : null,
            'order_date'       => isset($order['order_date']) ? _d($order['order_date']) : null,
            'delivery_date'    => isset($order['delivery_date']) ? _d($order['delivery_date']) : null,
            'currency_id'      => isset($order['currency']) ? (int) $order['currency'] : null,
            'currency_name'    => isset($order['currency_name']) ? $order['currency_name'] : null,
            'currency_symbol'  => isset($order['currency_symbol']) ? $order['currency_symbol'] : null,
            'subtotal'         => isset($order['subtotal']) ? (float) $order['subtotal'] : 0.0,
            'total_tax'        => isset($order['total_tax']) ? (float) $order['total_tax'] : 0.0,
            'total'            => isset($order['total']) ? (float) $order['total'] : 0.0,
            'created_at'       => isset($order['datecreated']) ? _dt($order['datecreated']) : null,
            'buyer_id'         => isset($order['buyer']) ? (int) $order['buyer'] : null,
            'department_id'    => isset($order['department']) ? (int) $order['department'] : null,
            'project_id'       => isset($order['project']) ? (int) $order['project'] : null,
            'type'             => isset($order['type']) ? $order['type'] : null,
        ];

        if ($include_details) {
            $result['terms']          = isset($order['terms']) ? $order['terms'] : '';
            $result['vendor_note']    = isset($order['vendornote']) ? $order['vendornote'] : '';
            $result['line_items']     = isset($order['line_items']) ? $order['line_items'] : [];
            $result['discount_total'] = isset($order['discount_total']) ? (float) $order['discount_total'] : 0.0;
            $result['discount_percent'] = isset($order['discount_percent']) ? (float) $order['discount_percent'] : 0.0;
            $result['shipping_fee']   = isset($order['shipping_fee']) ? (float) $order['shipping_fee'] : 0.0;
        }

        return $result;
    }

    private function format_order_item(array $detail)
    {
        $taxes = [];
        if (isset($detail['tax']) && $detail['tax'] !== '') {
            $tax_ids = array_filter(explode('|', $detail['tax']));
            if ($tax_ids !== []) {
                $this->db->where_in('id', $tax_ids);
                $tax_rows = $this->db->get(db_prefix() . 'taxes')->result();
                foreach ($tax_rows as $tax_row) {
                    $taxes[] = [
                        'id'   => (int) $tax_row->id,
                        'name' => $tax_row->name,
                        'rate' => (float) $tax_row->taxrate,
                    ];
                }
            }
        }

        $description = isset($detail['description']) ? $detail['description'] : '';
        if ($description !== '') {
            $description = str_ireplace(['<br />', '<br>'], "\n", $description);
            $description = trim(pur_html_entity_decode(strip_tags($description)));
        }

        return [
            'id'             => isset($detail['id']) ? (int) $detail['id'] : null,
            'item_code'      => isset($detail['item_code']) ? $detail['item_code'] : '',
            'item_name'      => isset($detail['item_name']) ? pur_html_entity_decode($detail['item_name']) : '',
            'description'    => $description,
            'unit_id'        => isset($detail['unit_id']) ? $detail['unit_id'] : null,
            'unit_price'     => isset($detail['unit_price']) ? (float) $detail['unit_price'] : 0.0,
            'quantity'       => isset($detail['quantity']) ? (float) $detail['quantity'] : 0.0,
            'into_money'     => isset($detail['into_money']) ? (float) $detail['into_money'] : 0.0,
            'total_money'    => isset($detail['total_money']) ? (float) $detail['total_money'] : 0.0,
            'total'          => isset($detail['total']) ? (float) $detail['total'] : 0.0,
            'discount_money' => isset($detail['discount_money']) ? (float) $detail['discount_money'] : 0.0,
            'discount_percent' => isset($detail['discount_%']) ? (float) $detail['discount_%'] : 0.0,
            'tax_value'      => isset($detail['tax_value']) ? (float) $detail['tax_value'] : 0.0,
            'taxes'          => $taxes,
        ];
    }
}

