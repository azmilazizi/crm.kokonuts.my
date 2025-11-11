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
        $this->load->helper('purchase/purchase');
    }

    public function vendors_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $filters = [];

        $activeParam = $this->input->get('active');
        if ($activeParam !== null && $activeParam !== '') {
            $activeFilter = filter_var($activeParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($activeFilter === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid active flag provided. Expected a boolean value.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $filters[db_prefix() . 'pur_vendor.active'] = $activeFilter ? 1 : 0;
        }

        $countryParam = $this->input->get('country_id');
        if ($countryParam !== null && $countryParam !== '') {
            if (!ctype_digit((string) $countryParam)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid country identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $filters[db_prefix() . 'pur_vendor.country'] = (int) $countryParam;
        }

        $vendors = $this->purchase_model->get_vendor('', $filters);

        $searchTerm = trim((string) $this->input->get('search'));
        if ($searchTerm !== '') {
            $vendors = $this->filter_vendors_by_term($vendors, $searchTerm);
        }

        $vendors = array_values($vendors);

        $page = $this->resolvePositiveIntParam($this->input->get('page'), 'page', 1);
        if ($page === null) {
            return;
        }

        $perPage = $this->resolvePositiveIntParam($this->input->get('per_page'), 'per_page', 20);
        if ($perPage === null) {
            return;
        }

        $totalVendors = count($vendors);
        $offset       = ($page - 1) * $perPage;
        $paginated    = $totalVendors > 0 ? array_slice($vendors, $offset, $perPage) : [];
        $totalPages   = $totalVendors > 0 ? (int) ceil($totalVendors / $perPage) : 0;

        $this->response([
            'status'     => true,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $totalVendors,
                'total_pages' => $totalPages,
                'returned'    => count($paginated),
            ],
            'result'     => array_values(array_map([$this, 'format_vendor_summary'], $paginated)),
        ], self::HTTP_OK);
    }

    public function vendor_get($id = null)
    {
        if (!$this->authenticate_token()) {
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

        $vendorData = $this->format_vendor_detail($vendor);

        $includeContactsParam = $this->input->get('include_contacts');
        if ($includeContactsParam !== null && $includeContactsParam !== '') {
            $includeContacts = filter_var($includeContactsParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($includeContacts === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid include_contacts flag provided. Expected a boolean value.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            if ($includeContacts) {
                $vendorData['contacts'] = $this->purchase_model->get_contacts((int) $id);
            }
        }

        $this->response([
            'status' => true,
            'result' => $vendorData,
        ], self::HTTP_OK);
    }

    public function purchase_orders_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $page = $this->resolvePositiveIntParam($this->input->get('page'), 'page', 1);
        if ($page === null) {
            return;
        }

        $perPage = $this->resolvePositiveIntParam($this->input->get('per_page'), 'per_page', 20);
        if ($perPage === null) {
            return;
        }

        $vendorId = $this->parseOptionalIntParam($this->input->get('vendor_id'), 'vendor_id');
        if ($vendorId === false) {
            return;
        }

        $approveStatus = $this->parseOptionalIntParam($this->input->get('approve_status'), 'approve_status');
        if ($approveStatus === false) {
            return;
        }

        $fromDate = $this->parseDateParam($this->input->get('from'), 'from');
        if ($fromDate === false) {
            return;
        }

        $toDate = $this->parseDateParam($this->input->get('to'), 'to');
        if ($toDate === false) {
            return;
        }

        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            $this->response([
                'status'  => false,
                'message' => 'The from date must be before or equal to the to date.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $orderStatus = trim((string) $this->input->get('order_status'));
        $searchTerm  = trim((string) $this->input->get('search'));

        $this->db->start_cache();
        $this->db->from(db_prefix() . 'pur_orders as po');
        $this->db->join(db_prefix() . 'pur_vendor as v', 'v.userid = po.vendor', 'left');
        $this->db->join(db_prefix() . 'currencies as cur', 'cur.id = po.currency', 'left');

        if (!has_permission('purchase_orders', '', 'view') && is_staff_logged_in()) {
            $staffId       = get_staff_user_id();
            $vendorAdminTb = db_prefix() . 'pur_vendor_admin';

            $restriction = sprintf(
                '(po.addedfrom = %1$d OR po.buyer = %1$d OR po.vendor IN (SELECT vendor_id FROM %2$s WHERE staff_id = %1$d))',
                $staffId,
                $vendorAdminTb
            );

            $this->db->where($restriction, null, false);
        }

        if ($vendorId !== null) {
            $this->db->where('po.vendor', $vendorId);
        }

        if ($approveStatus !== null) {
            $this->db->where('po.approve_status', $approveStatus);
        }

        if ($orderStatus !== '') {
            $this->db->where('po.order_status', $orderStatus);
        }

        if ($fromDate !== null) {
            $this->db->where('po.order_date >=', $fromDate);
        }

        if ($toDate !== null) {
            $this->db->where('po.order_date <=', $toDate);
        }

        if ($searchTerm !== '') {
            $this->db->group_start();
            $this->db->like('po.pur_order_number', $searchTerm);
            $this->db->or_like('po.reference_no', $searchTerm);
            $this->db->or_like('v.company', $searchTerm);
            $this->db->group_end();
        }

        $this->db->stop_cache();

        $totalOrders = $this->db->count_all_results();

        $this->db->select([
            'po.id',
            'po.pur_order_number',
            'po.vendor',
            'po.order_date',
            'po.delivery_date',
            'po.order_status',
            'po.approve_status',
            'po.delivery_status',
            'po.total',
            'po.subtotal',
            'po.discount_total',
            'po.total_tax',
            'po.shipping_fee',
            'po.currency',
            'po.project',
            'po.department',
            'po.reference_no',
            'po.hash',
            'po.datecreated',
        ]);
        $this->db->select('v.company as vendor_name');
        $this->db->select('cur.name as currency_name, cur.symbol as currency_symbol');

        $this->db->order_by('po.order_date', 'DESC');
        $this->db->limit($perPage, ($page - 1) * $perPage);

        $orders = $this->db->get()->result_array();

        $this->db->flush_cache();

        $formattedOrders = array_map([$this, 'format_purchase_order_summary'], $orders);

        $this->response([
            'status'     => true,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $totalOrders,
                'total_pages' => $totalOrders > 0 ? (int) ceil($totalOrders / $perPage) : 0,
                'returned'    => count($formattedOrders),
            ],
            'result'     => $formattedOrders,
        ], self::HTTP_OK);
    }

    private function filter_vendors_by_term(array $vendors, string $term): array
    {
        $term = $this->toLower($term);

        return array_values(array_filter($vendors, function ($vendor) use ($term) {
            $fieldsToSearch = [
                'company',
                'phonenumber',
                'vat',
                'email',
                'vendor_code',
            ];

            foreach ($fieldsToSearch as $field) {
                if (!isset($vendor[$field]) || $vendor[$field] === '') {
                    continue;
                }

                $value = $this->toLower((string) $vendor[$field]);

                if ($this->containsTerm($value, $term)) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function toLower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }

        return strtolower($value);
    }

    private function containsTerm(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('mb_strpos')) {
            return mb_strpos($haystack, $needle) !== false;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle) !== false;
        }

        return strpos($haystack, $needle) !== false;
    }

    private function format_vendor_summary(array $vendor): array
    {
        return [
            'id'         => isset($vendor['userid']) ? (int) $vendor['userid'] : null,
            'company'    => $vendor['company'] ?? '',
            'email'      => $vendor['email'] ?? '',
            'phonenumber'=> $vendor['phonenumber'] ?? '',
            'vat'        => $vendor['vat'] ?? null,
            'city'       => $vendor['city'] ?? '',
            'state'      => $vendor['state'] ?? '',
            'country_id' => isset($vendor['country']) ? (int) $vendor['country'] : null,
            'active'     => isset($vendor['active']) ? (bool) $vendor['active'] : null,
        ];
    }

    private function format_vendor_detail($vendor): array
    {
        if (is_object($vendor)) {
            $vendor = (array) $vendor;
        }

        $detail = $this->format_vendor_summary($vendor);

        $detail['address']        = $vendor['address'] ?? '';
        $detail['zip']            = $vendor['zip'] ?? '';
        $detail['website']        = $vendor['website'] ?? '';
        $detail['bank']           = $vendor['bank'] ?? '';
        $detail['payment_terms']  = $vendor['payment_terms'] ?? '';
        $detail['balance']        = isset($vendor['balance']) ? (float) $vendor['balance'] : null;
        $detail['balance_as_of']  = $vendor['balance_as_of'] ?? null;
        $detail['datecreated']    = $vendor['datecreated'] ?? null;
        $detail['default_currency']= isset($vendor['default_currency']) ? (int) $vendor['default_currency'] : null;

        if (isset($vendor['company'])) {
            $detail['display_name'] = $vendor['company'];
        } elseif (isset($vendor['firstname']) || isset($vendor['lastname'])) {
            $detail['display_name'] = trim(($vendor['firstname'] ?? '') . ' ' . ($vendor['lastname'] ?? ''));
        } else {
            $detail['display_name'] = '';
        }

        return $detail;
    }

    private function format_purchase_order_summary(array $order): array
    {
        return [
            'id'           => isset($order['id']) ? (int) $order['id'] : null,
            'number'       => $order['pur_order_number'] ?? '',
            'reference_no' => $order['reference_no'] ?? null,
            'order_date'   => $order['order_date'] ?? null,
            'delivery_date'=> $order['delivery_date'] ?? null,
            'status'       => [
                'order'    => $order['order_status'] ?? null,
                'approval' => isset($order['approve_status']) ? (int) $order['approve_status'] : null,
                'delivery' => isset($order['delivery_status']) ? (int) $order['delivery_status'] : null,
            ],
            'vendor'       => [
                'id'   => isset($order['vendor']) ? (int) $order['vendor'] : null,
                'name' => $order['vendor_name'] ?? '',
            ],
            'totals'       => [
                'subtotal' => isset($order['subtotal']) ? (float) $order['subtotal'] : 0.0,
                'discount' => isset($order['discount_total']) ? (float) $order['discount_total'] : 0.0,
                'tax'      => isset($order['total_tax']) ? (float) $order['total_tax'] : 0.0,
                'shipping' => isset($order['shipping_fee']) ? (float) $order['shipping_fee'] : 0.0,
                'total'    => isset($order['total']) ? (float) $order['total'] : 0.0,
            ],
            'currency'     => [
                'id'     => isset($order['currency']) ? (int) $order['currency'] : null,
                'name'   => $order['currency_name'] ?? null,
                'symbol' => $order['currency_symbol'] ?? null,
            ],
            'project_id'    => isset($order['project']) ? (int) $order['project'] : null,
            'department_id' => isset($order['department']) ? (int) $order['department'] : null,
            'hash'          => $order['hash'] ?? null,
            'created_at'    => $order['datecreated'] ?? null,
        ];
    }

    private function resolvePositiveIntParam($value, string $field, int $default): ?int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (ctype_digit((string) $value) && (int) $value > 0) {
            return (int) $value;
        }

        $this->response([
            'status'  => false,
            'message' => sprintf('Invalid %s value provided. Expected a positive integer.', $field),
        ], self::HTTP_BAD_REQUEST);

        return null;
    }

    private function parseOptionalIntParam($value, string $field)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (ctype_digit((string) $value)) {
            return (int) $value;
        }

        $this->response([
            'status'  => false,
            'message' => sprintf('Invalid %s value provided. Expected a non-negative integer.', $field),
        ], self::HTTP_BAD_REQUEST);

        return false;
    }

    private function parseDateParam($value, string $field)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->response([
                'status'  => false,
                'message' => sprintf('Invalid %s value provided. Expected format YYYY-MM-DD.', $field),
            ], self::HTTP_BAD_REQUEST);

            return false;
        }

        return $date->format('Y-m-d');
    }
}
