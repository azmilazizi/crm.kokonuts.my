<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_purchase extends API_Controller
{
    /** @var object|null */
    private $authenticatedStaff = null;

    public function __construct()
    {
        $this->module_language_file      = 'purchase';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('purchase_model');
        $this->load->helper(['purchase/purchase']);
    }

    public function vendors_get()
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        $filters = [
            'search'     => trim((string) $this->get('search')),
            'country_id' => $this->get_numeric($this->get('country_id')),
        ];

        $page    = $this->positive_int_from_query('page', 1);
        $perPage = $this->positive_int_from_query('per_page', 20);
        $offset  = ($page - 1) * $perPage;

        $totalQuery = $this->build_vendor_query($filters);
        $total      = $totalQuery->count_all_results();

        $dataQuery = $this->build_vendor_query($filters);
        $vendors   = $dataQuery->order_by('v.datecreated', 'DESC')->limit($perPage, $offset)->get()->result_array();

        $includeContacts = $this->boolean_from_query('include_contacts', false);

        if ($includeContacts && !empty($vendors)) {
            foreach ($vendors as &$vendor) {
                $vendor['contacts'] = $this->purchase_model->get_contacts($vendor['userid']);
            }
        }

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        $this->response([
            'status' => true,
            'result' => $vendors,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ],
        ], self::HTTP_OK);
    }

    public function vendors_post()
    {
        if (!$this->ensure_staff_context()) {
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

        $normalized = $this->prepare_vendor_payload($payload, false);

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $vendorId = $this->purchase_model->add_vendor($normalized['data']);

        if (!$vendorId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create vendor with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $vendor = $this->purchase_model->get_vendor($vendorId);

        $this->response([
            'status' => true,
            'result' => $vendor,
        ], self::HTTP_CREATED);
    }

    public function vendor_get($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid vendor identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $vendor = $this->purchase_model->get_vendor((int) $id);

        if (!$vendor) {
            $this->response([
                'status'  => false,
                'message' => 'Vendor not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $includeContacts = $this->boolean_from_query('include_contacts', false);
        if ($includeContacts && isset($vendor->userid)) {
            $vendor->contacts = $this->purchase_model->get_contacts($vendor->userid);
        }

        $this->response([
            'status' => true,
            'result' => $vendor,
        ], self::HTTP_OK);
    }

    public function vendor_put($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid vendor identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if (!$this->purchase_model->get_vendor((int) $id)) {
            $this->response([
                'status'  => false,
                'message' => 'Vendor not found.',
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

        $normalized = $this->prepare_vendor_payload($payload, true);

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->purchase_model->update_vendor($normalized['data'], (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update vendor with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $vendor = $this->purchase_model->get_vendor((int) $id);

        $this->response([
            'status' => true,
            'result' => $vendor,
        ], self::HTTP_OK);
    }

    public function vendor_delete($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid vendor identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $deleted = $this->purchase_model->delete_vendor((int) $id);

        if (!$deleted) {
            $this->response([
                'status'  => false,
                'message' => 'Vendor not found or already deleted.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Vendor deleted successfully.',
        ], self::HTTP_OK);
    }

    public function purchase_orders_get()
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        $filters = [
            'search'        => trim((string) $this->get('search')),
            'vendor_ids'    => $this->extract_ids($this->get('vendor_id')),
            'approve_status'=> $this->get_numeric($this->get('approve_status')),
            'order_status'  => trim((string) $this->get('order_status')),
            'from_date'     => $this->normalize_date((string) $this->get('from')),
            'to_date'       => $this->normalize_date((string) $this->get('to')),
        ];

        $page    = $this->positive_int_from_query('page', 1);
        $perPage = $this->positive_int_from_query('per_page', 20);
        $offset  = ($page - 1) * $perPage;

        $totalQuery = $this->build_purchase_order_query($filters);
        $total      = $totalQuery->count_all_results();

        $dataQuery = $this->build_purchase_order_query($filters);
        $orders    = $dataQuery->order_by('o.order_date', 'DESC')->limit($perPage, $offset)->get()->result_array();

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        $this->response([
            'status' => true,
            'result' => $orders,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ],
        ], self::HTTP_OK);
    }

    public function purchase_orders_post()
    {
        if (!$this->ensure_staff_context()) {
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

        $normalized = $this->prepare_purchase_order_payload($payload, false);

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $orderId = $this->purchase_model->add_pur_order($normalized['data']);

        if (!$orderId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create purchase order with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_purchase_order_result($orderId),
        ], self::HTTP_CREATED);
    }

    public function purchase_order_get($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if (!$this->purchase_model->get_pur_order((int) $id)) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_purchase_order_result((int) $id),
        ], self::HTTP_OK);
    }

    public function purchase_order_payments_get($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $orderId = (int) $id;
        $order   = $this->purchase_model->get_pur_order($orderId);

        if (!$order) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $payments = $this->purchase_model->get_payment_purchase_order($orderId);

        if (!is_array($payments)) {
            $payments = [];
        }

        $currency    = function_exists('get_base_currency') ? get_base_currency() : null;
        $currencySet = null;

        if (is_object($currency)) {
            $currencySet = [
                'id'     => property_exists($currency, 'id') ? (int) $currency->id : null,
                'name'   => property_exists($currency, 'name') ? (string) $currency->name : null,
                'symbol' => property_exists($currency, 'symbol') ? (string) $currency->symbol : null,
            ];
        }

        $formatted = [];

        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $formatted[] = $this->format_purchase_order_payment($payment, $orderId, $currencySet);
        }

        usort($formatted, function ($left, $right) {
            $leftDate  = isset($left['date']['value']) ? (string) $left['date']['value'] : '';
            $rightDate = isset($right['date']['value']) ? (string) $right['date']['value'] : '';

            if ($leftDate === $rightDate) {
                $leftId  = isset($left['id']) ? (int) $left['id'] : 0;
                $rightId = isset($right['id']) ? (int) $right['id'] : 0;

                return $leftId <=> $rightId;
            }

            return strcmp($leftDate, $rightDate);
        });

        $this->response([
            'status' => true,
            'result' => $formatted,
        ], self::HTTP_OK);
    }

    public function purchase_order_put($id = null)
    {
        if (!$this->ensure_staff_context()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $order = $this->purchase_model->get_pur_order((int) $id);

        if (!$order) {
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

        $existingDetails = $this->purchase_model->get_pur_order_detail((int) $id);
        $normalized      = $this->prepare_purchase_order_payload($payload, true, $existingDetails);

        if (!empty($normalized['errors'])) {
            $this->response([
                'status'  => false,
                'message' => $normalized['errors'],
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $updated = $this->purchase_model->update_pur_order($normalized['data'], (int) $id);

        if (!$updated) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update purchase order with the provided information.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status' => true,
            'result' => $this->format_purchase_order_result((int) $id),
        ], self::HTTP_OK);
    }

    public function purchase_order_delete($id = null)
    {
        if (!$this->ensure_staff_context()) {
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
                'message' => 'Purchase order not found or already deleted.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Purchase order deleted successfully.',
        ], self::HTTP_OK);
    }

    private function build_vendor_query(array $filters)
    {
        $builder = $this->db->from(db_prefix() . 'pur_vendor as v');
        $builder->select('v.*, c.short_name as country_short_name, c.long_name as country_name');
        $builder->join(db_prefix() . 'countries as c', 'c.country_id = v.country', 'left');

        if ($filters['search'] !== '') {
            $builder->group_start();
            $builder->like('v.company', $filters['search']);
            $builder->or_like('v.phonenumber', $filters['search']);
            $builder->or_like('v.email', $filters['search']);
            $builder->group_end();
        }

        if (!empty($filters['country_id'])) {
            $builder->where('v.country', $filters['country_id']);
        }

        return $builder;
    }

    private function build_purchase_order_query(array $filters)
    {
        $builder = $this->db->from(db_prefix() . 'pur_orders as o');
        $builder->select('o.*, v.company as vendor_name');
        $builder->join(db_prefix() . 'pur_vendor as v', 'v.userid = o.vendor', 'left');

        if ($filters['search'] !== '') {
            $builder->group_start();
            $builder->like('o.pur_order_number', $filters['search']);
            $builder->or_like('o.pur_order_name', $filters['search']);
            $builder->group_end();
        }

        if (!empty($filters['vendor_ids'])) {
            $builder->where_in('o.vendor', $filters['vendor_ids']);
        }

        if (!empty($filters['approve_status'])) {
            $builder->where('o.approve_status', $filters['approve_status']);
        }

        if ($filters['order_status'] !== '') {
            $builder->where('o.order_status', $filters['order_status']);
        }

        if ($filters['from_date'] !== null) {
            $builder->where('o.order_date >=', $filters['from_date']);
        }

        if ($filters['to_date'] !== null) {
            $builder->where('o.order_date <=', $filters['to_date']);
        }

        return $builder;
    }

    private function prepare_vendor_payload(array $input, bool $is_update)
    {
        $errors = [];
        $company = isset($input['company']) ? trim((string) $input['company']) : '';

        if ($company === '') {
            $errors[] = 'Company name is required.';
        }

        $data = [
            'company'      => $company,
            'phonenumber'  => isset($input['phone']) ? trim((string) $input['phone']) : (isset($input['phonenumber']) ? trim((string) $input['phonenumber']) : ''),
            'email'        => isset($input['email']) ? trim((string) $input['email']) : '',
            'website'      => isset($input['website']) ? trim((string) $input['website']) : '',
            'address'      => isset($input['address']) ? trim((string) $input['address']) : '',
            'city'         => isset($input['city']) ? trim((string) $input['city']) : '',
            'state'        => isset($input['state']) ? trim((string) $input['state']) : '',
            'zip'          => isset($input['zip']) ? trim((string) $input['zip']) : '',
            'country'      => isset($input['country']) ? (int) $input['country'] : 0,
            'vat'          => isset($input['vat']) ? trim((string) $input['vat']) : '',
            'default_currency' => isset($input['default_currency']) ? (int) $input['default_currency'] : 0,
            'note'         => isset($input['note']) ? (string) $input['note'] : '',
        ];

        if (isset($input['categories']) && is_array($input['categories'])) {
            $data['category'] = $input['categories'];
        } elseif (isset($input['category'])) {
            $data['category'] = $input['category'];
        }

        if (isset($input['groups']) && is_array($input['groups'])) {
            $data['groups_in'] = $input['groups'];
        }

        if (isset($input['balance'])) {
            $data['balance'] = $this->format_money_value($input['balance']);
        }

        if (isset($input['balance_as_of'])) {
            $data['balance_as_of'] = $input['balance_as_of'];
        }

        if (isset($input['billing_address']) && is_array($input['billing_address'])) {
            $billing = $input['billing_address'];
            $data['billing_street']  = isset($billing['street']) ? (string) $billing['street'] : '';
            $data['billing_city']    = isset($billing['city']) ? (string) $billing['city'] : '';
            $data['billing_state']   = isset($billing['state']) ? (string) $billing['state'] : '';
            $data['billing_zip']     = isset($billing['zip']) ? (string) $billing['zip'] : '';
            $data['billing_country'] = isset($billing['country']) ? (int) $billing['country'] : 0;
        }

        if (isset($input['shipping_address']) && is_array($input['shipping_address'])) {
            $shipping = $input['shipping_address'];
            $data['shipping_street']  = isset($shipping['street']) ? (string) $shipping['street'] : '';
            $data['shipping_city']    = isset($shipping['city']) ? (string) $shipping['city'] : '';
            $data['shipping_state']   = isset($shipping['state']) ? (string) $shipping['state'] : '';
            $data['shipping_zip']     = isset($shipping['zip']) ? (string) $shipping['zip'] : '';
            $data['shipping_country'] = isset($shipping['country']) ? (int) $shipping['country'] : 0;
        }

        if (isset($input['primary_contact']) && is_array($input['primary_contact'])) {
            $contact = $input['primary_contact'];
            $data['firstname'] = isset($contact['firstname']) ? (string) $contact['firstname'] : '';
            $data['lastname']  = isset($contact['lastname']) ? (string) $contact['lastname'] : '';
            $data['title']     = isset($contact['title']) ? (string) $contact['title'] : '';
            $data['password']  = isset($contact['password']) ? (string) $contact['password'] : '';
            $data['send_set_password_email'] = !empty($contact['send_set_password_email']) ? 1 : 0;
            $data['contact_phonenumber']     = isset($contact['phone']) ? (string) $contact['phone'] : '';
            $data['email'] = isset($contact['email']) ? trim((string) $contact['email']) : $data['email'];
        }

        return ['data' => $data, 'errors' => $errors];
    }

    private function prepare_purchase_order_payload(array $input, bool $is_update, array $existingDetails = [])
    {
        $errors   = [];
        $vendorId = isset($input['vendor_id']) ? (int) $input['vendor_id'] : (isset($input['vendor']) ? (int) $input['vendor'] : 0);

        if ($vendorId <= 0) {
            $errors[] = 'Vendor is required.';
        }

        $orderDate    = $this->normalize_date($input['order_date'] ?? '');
        $deliveryDate = $this->normalize_date($input['delivery_date'] ?? '');

        if ($orderDate === null) {
            $errors[] = 'A valid order date is required.';
        }

        if ($deliveryDate === null) {
            $errors[] = 'A valid delivery date is required.';
        }

        $itemsInput = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

        if ($itemsInput === []) {
            $errors[] = 'At least one line item is required.';
        }

        $newItems      = [];
        $updateItems   = [];
        $existingIds   = [];
        $subtotal      = 0.0;
        $discountTotal = 0.0;
        $grandTotal    = 0.0;

        foreach ($itemsInput as $item) {
            $line = $this->prepare_purchase_order_item($item);

            if ($line === null) {
                continue;
            }

            $lineSubtotal = (float) $line['total_money'];
            $lineDiscount = (float) $line['discount_money'];
            $lineTotal    = (float) $line['total'];

            $subtotal      += $lineSubtotal;
            $discountTotal += $lineDiscount;
            $grandTotal    += $lineTotal;

            if (isset($item['id']) && (int) $item['id'] > 0) {
                $line['id'] = (int) $item['id'];
                $updateItems[] = $line;
                $existingIds[] = (int) $item['id'];
            } else {
                $newItems[] = $line;
            }
        }

        if ($newItems === [] && $updateItems === []) {
            $errors[] = 'At least one valid line item is required.';
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $data = [
            'vendor'         => $vendorId,
            'order_date'     => $orderDate,
            'delivery_date'  => $deliveryDate,
            'currency'       => isset($input['currency']) ? (int) $input['currency'] : 0,
            'terms'          => isset($input['terms']) ? (string) $input['terms'] : '',
            'vendornote'     => isset($input['vendor_note']) ? (string) $input['vendor_note'] : (isset($input['vendornote']) ? (string) $input['vendornote'] : ''),
            'project'        => isset($input['project_id']) ? (int) $input['project_id'] : (isset($input['project']) ? (int) $input['project'] : 0),
            'department'     => isset($input['department_id']) ? (int) $input['department_id'] : (isset($input['department']) ? (int) $input['department'] : 0),
            'subtotal'       => $this->format_money_value($subtotal),
            'discount_total' => $this->format_money_value($discountTotal),
            'total'          => $this->format_money_value($grandTotal),
            'total_mn'       => $this->format_money_value($subtotal),
            'dc_total'       => $this->format_money_value($discountTotal),
            'grand_total'    => $this->format_money_value($grandTotal),
        ];

        if (isset($input['order_name'])) {
            $data['pur_order_name'] = (string) $input['order_name'];
        }

        if (!$is_update) {
            $identifiers             = $this->generate_purchase_order_identifiers($vendorId);
            $data['number']          = $identifiers['number'];
            $data['pur_order_number']= $identifiers['code'];
            $data['pur_order_name']  = isset($input['order_name']) ? (string) $input['order_name'] : '';
            $data['newitems']        = $newItems;
        } else {
            $data['newitems'] = $newItems;
            $data['items']    = $updateItems;

            $existingIdsFromDb = array_map(function ($detail) {
                return isset($detail['id']) ? (int) $detail['id'] : null;
            }, $existingDetails);
            $existingIdsFromDb = array_filter($existingIdsFromDb, function ($value) {
                return $value !== null;
            });

            $toRemove = array_diff($existingIdsFromDb, $existingIds);
            if (!empty($toRemove)) {
                $data['removed_items'] = array_values($toRemove);
            }
        }

        return ['data' => $data, 'errors' => []];
    }

    private function prepare_purchase_order_item(array $item)
    {
        $quantity   = isset($item['quantity']) ? (float) $item['quantity'] : 0.0;
        $unitPrice  = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.0;
        $discount   = isset($item['discount_percent']) ? (float) $item['discount_percent'] : 0.0;
        $description= isset($item['description']) ? (string) $item['description'] : '';
        $itemName   = isset($item['name']) ? (string) $item['name'] : '';

        if ($quantity <= 0 || $unitPrice <= 0) {
            return null;
        }

        $lineSubtotal = $quantity * $unitPrice;
        $discountMoney = $discount > 0 ? $lineSubtotal * ($discount / 100) : 0;
        $netSubtotal   = $lineSubtotal - $discountMoney;

        $taxSelect = [];
        if (isset($item['taxes']) && is_array($item['taxes'])) {
            foreach ($item['taxes'] as $tax) {
                if (is_array($tax) && isset($tax['name'], $tax['rate'])) {
                    $taxSelect[] = $tax['name'] . '|' . $tax['rate'];
                } elseif (is_string($tax)) {
                    $taxSelect[] = $tax;
                }
            }
        }

        $taxRate = 0;
        $taxValue = 0;
        if ($taxSelect !== []) {
            $taxRateData = $this->purchase_model->pur_get_tax_rate($taxSelect);
            $taxRate  = isset($taxRateData['tax_rate']) ? (float) $taxRateData['tax_rate'] : 0;
            $taxValue = $netSubtotal * ($taxRate / 100);
        }

        $total = $netSubtotal + $taxValue;

        return [
            'item_code'        => isset($item['item_code']) ? (string) $item['item_code'] : '',
            'item_name'        => $itemName,
            'item_description' => $description,
            'quantity'         => $this->format_quantity_value($quantity),
            'unit_price'       => $this->format_money_value($unitPrice),
            'unit_id'          => isset($item['unit_id']) ? (int) $item['unit_id'] : null,
            'unit_name'        => isset($item['unit_name']) ? (string) $item['unit_name'] : '',
            'discount'         => $discount,
            'discount_money'   => $this->format_money_value($discountMoney),
            'into_money'       => $this->format_money_value($netSubtotal),
            'total_money'      => $this->format_money_value($netSubtotal),
            'tax_select'       => $taxSelect,
            'tax_rate'         => $taxRate,
            'tax_value'        => $this->format_money_value($taxValue),
            'total'            => $this->format_money_value($total),
        ];
    }

    private function format_purchase_order_payment(array $payment, int $orderId, ?array $currency)
    {
        $paymentId = isset($payment['id']) ? (int) $payment['id'] : null;
        $rawAmount = isset($payment['amount']) ? (float) $payment['amount'] : 0.0;

        $modeValue = isset($payment['paymentmode']) ? $payment['paymentmode'] : null;
        $modeId    = null;
        $modeName  = null;

        if ($modeValue !== null && $modeValue !== '') {
            if (is_numeric($modeValue)) {
                $modeId = (int) $modeValue;

                if (function_exists('get_payment_mode_name_by_id')) {
                    $resolvedModeName = (string) get_payment_mode_name_by_id($modeId);

                    if ($resolvedModeName !== '') {
                        $modeName = $resolvedModeName;
                    }
                }
            } else {
                $modeName = (string) $modeValue;
            }
        }

        $dateValue      = isset($payment['date']) ? $payment['date'] : null;
        $dateIso        = null;
        $dateFormatted  = null;
        $timestampValue = null;

        if ($dateValue !== null && $dateValue !== '') {
            $timestampValue = strtotime($dateValue);

            if ($timestampValue !== false) {
                $dateIso       = date('Y-m-d', $timestampValue);
                $dateFormatted = date('d-m-Y', $timestampValue);
            }
        }

        $recordedAt    = isset($payment['daterecorded']) ? $payment['daterecorded'] : null;
        $transactionId = isset($payment['transactionid']) ? $payment['transactionid'] : null;
        $note          = isset($payment['note']) ? $payment['note'] : null;

        return [
            'id'                => $paymentId,
            'purchase_order_id' => $orderId,
            'amount'            => [
                'raw'       => $rawAmount,
                'formatted' => $this->format_money_value($rawAmount),
                'currency'  => $currency,
            ],
            'payment_mode'      => [
                'id'    => $modeId,
                'name'  => $modeName,
                'value' => ($modeValue !== null && $modeValue !== '') ? (string) $modeValue : null,
            ],
            'transaction_id'    => ($transactionId !== null && $transactionId !== '') ? $transactionId : null,
            'note'              => ($note !== null && $note !== '') ? $note : null,
            'date'              => [
                'value'     => $dateIso,
                'formatted' => $dateFormatted,
            ],
            'recorded_at'       => ($recordedAt !== null && $recordedAt !== '') ? $recordedAt : null,
        ];
    }

    private function format_purchase_order_result(int $orderId)
    {
        $order   = $this->purchase_model->get_pur_order($orderId);
        $details = $this->purchase_model->get_pur_order_detail($orderId);

        if ($order) {
            $vendorName = '';

            if (isset($order->vendor) && (int) $order->vendor > 0 && function_exists('get_vendor_company_name')) {
                $vendorName = (string) get_vendor_company_name($order->vendor);
            }

            if ($vendorName === '' && isset($order->deleted_vendor_name) && $order->deleted_vendor_name !== '') {
                $vendorName = (string) $order->deleted_vendor_name;
            }

            $order->vendor_name = $vendorName;
        }

        return [
            'order' => $order,
            'items' => $details,
        ];
    }

    private function format_purchase_order_payment(array $payment, string $currencySymbol)
    {
        $amount = isset($payment['amount']) ? (float) $payment['amount'] : 0.0;
        $rawPaymentMode = isset($payment['paymentmode']) ? $payment['paymentmode'] : null;
        $paymentModeId = is_numeric($rawPaymentMode)
            ? (int) $rawPaymentMode
            : null;
        $date = isset($payment['date']) ? (string) $payment['date'] : null;
        $orderId = isset($payment['pur_order']) && is_numeric($payment['pur_order'])
            ? (int) $payment['pur_order']
            : null;
        $paymentModeName = '';

        if ($paymentModeId !== null) {
            $paymentModeName = (string) get_payment_mode_by_id($paymentModeId);
        } elseif (is_string($rawPaymentMode) && $rawPaymentMode !== '') {
            $paymentModeName = (string) $rawPaymentMode;
        }

        return [
            'id'                 => isset($payment['id']) ? (int) $payment['id'] : null,
            'purchase_order_id'  => $orderId,
            'amount'             => $this->format_money_value($amount),
            'amount_formatted'   => $this->format_money_display($amount, $currencySymbol),
            'payment_mode_id'    => $paymentModeId,
            'payment_mode_name'  => $paymentModeName,
            'transaction_id'     => isset($payment['transactionid']) ? (string) $payment['transactionid'] : '',
            'note'               => isset($payment['note']) ? (string) $payment['note'] : '',
            'date'               => $date,
            'date_formatted'     => $this->format_display_date($date),
            'recorded_at'        => isset($payment['daterecorded']) ? (string) $payment['daterecorded'] : null,
            'approval_status'    => isset($payment['approval_status']) && is_numeric($payment['approval_status'])
                ? (int) $payment['approval_status']
                : null,
        ];
    }

    private function format_money_display(float $amount, string $currencySymbol)
    {
        if ($currencySymbol !== '' && function_exists('app_format_money')) {
            return app_format_money($amount, $currencySymbol);
        }

        return number_format($amount, 2, '.', '');
    }

    private function compare_purchase_payments(array $left, array $right)
    {
        $leftTimestamp  = $this->resolve_payment_timestamp($left);
        $rightTimestamp = $this->resolve_payment_timestamp($right);

        if ($leftTimestamp === $rightTimestamp) {
            return 0;
        }

        return $leftTimestamp > $rightTimestamp ? -1 : 1;
    }

    private function resolve_payment_timestamp(array $payment)
    {
        $dateFields = ['date', 'daterecorded', 'created_at'];

        foreach ($dateFields as $field) {
            if (!isset($payment[$field])) {
                continue;
            }

            $value = trim((string) $payment[$field]);

            if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                continue;
            }

            $timestamp = strtotime($value);

            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return 0;
    }

    private function format_display_date($date)
    {
        if ($date === null) {
            return null;
        }

        $date = trim((string) $date);

        if ($date === '' || $date === '0000-00-00') {
            return null;
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return null;
        }

        return date('d-m-Y', $timestamp);
    }

    private function generate_purchase_order_identifiers($vendorId)
    {
        $number = function_exists('get_purchase_option') ? (int) get_purchase_option('next_po_number') : 0;
        $prefix = function_exists('get_purchase_option') ? (string) get_purchase_option('pur_order_prefix') : '';
        $code   = $prefix . '-' . str_pad($number, 5, '0', STR_PAD_LEFT);

        if ((function_exists('get_option') ? (int) get_option('po_only_prefix_and_number') : 0) !== 1) {
            $vendorName = '';

            if (function_exists('get_vendor_company_name')) {
                $vendorName = (string) get_vendor_company_name($vendorId);
            }

            $code .= '-' . date('d') . '-' . $vendorName;
        }

        return ['number' => $number, 'code' => $code];
    }

    private function ensure_staff_context()
    {
        $tokenData = $this->authenticate_token();

        if ($tokenData === false) {
            return false;
        }

        if ($this->authenticatedStaff !== null) {
            return $tokenData;
        }

        $token = $this->authorization_token->get_token();

        if (!empty($token) && $token !== 'Token is not defined.') {
            $staff = $this->db->where('token', $token)->get(db_prefix() . 'staff')->row();
            if ($staff) {
                $this->authenticatedStaff = $staff;
                $this->session->set_userdata([
                    'staff_logged_in' => true,
                    'staff_user_id'   => $staff->staffid,
                ]);
                $GLOBALS['current_user'] = $staff;
            }
        }

        return $tokenData;
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

    private function positive_int_from_query($key, $default)
    {
        $value = $this->get($key);

        if (!is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : $default;
    }

    private function boolean_from_query($key, $default)
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function normalize_date($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function format_money_value($amount)
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function format_quantity_value($quantity)
    {
        return number_format((float) $quantity, 4, '.', '');
    }

    private function extract_ids($input)
    {
        if ($input === null || $input === '') {
            return [];
        }

        if (is_array($input)) {
            return array_values(array_filter(array_map('intval', $input), function ($value) {
                return $value > 0;
            }));
        }

        $parts = explode(',', (string) $input);

        $ids = [];
        foreach ($parts as $part) {
            $value = (int) trim($part);
            if ($value > 0) {
                $ids[] = $value;
            }
        }

        return $ids;
    }

    private function get_numeric($value)
    {
        if (is_numeric($value)) {
            $int = (int) $value;
            return $int > 0 ? $int : null;
        }

        return null;
    }
}
