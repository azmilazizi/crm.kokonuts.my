<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_purchase extends API_Controller
{
    private const DEFAULT_PAGE      = 1;
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE     = 100;

    public function __construct()
    {
        $this->module_language_file      = 'purchase';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->library('authorization_token');
        $this->load->model('purchase_model');
    }

    public function vendors_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $pagination = $this->resolvePagination();
        if ($pagination === null) {
            return;
        }

        [$page, $perPage] = $pagination;

        try {
            $activeFilter  = $this->readOptionalBooleanParam('active');
            $countryFilter = $this->readOptionalPositiveIntParam('country_id');
        } catch (InvalidArgumentException $exception) {
            $this->respondBadRequest($exception->getMessage());

            return;
        }

        $searchTerm = trim((string) $this->input->get('search', true));

        $query = $this->db
            ->from(db_prefix() . 'pur_vendor as v')
            ->select([
                'v.userid',
                'v.company',
                'v.vendor_code',
                'v.vat',
                'v.phonenumber',
                'v.active',
                'v.country',
                'v.city',
                'v.state',
                'v.zip',
                'v.address',
                'v.website',
                'v.datecreated',
                'v.default_currency',
                'v.default_language',
                'v.billing_street',
                'v.billing_city',
                'v.billing_state',
                'v.billing_zip',
                'v.billing_country',
                'v.shipping_street',
                'v.shipping_city',
                'v.shipping_state',
                'v.shipping_zip',
                'v.shipping_country',
                'v.longitude',
                'v.latitude',
            ])
            ->select([
                'c.country_id   as country_id',
                'c.country      as country_name',
                'c.short_name   as country_short_name',
                'c.iso2         as country_iso2',
                'c.iso3         as country_iso3',
            ])
            ->select([
                'pc.id          as primary_contact_id',
                'pc.firstname   as primary_contact_first_name',
                'pc.lastname    as primary_contact_last_name',
                'pc.email       as primary_contact_email',
                'pc.phonenumber as primary_contact_phone',
            ])
            ->join(db_prefix() . 'countries as c', 'c.country_id = v.country', 'left')
            ->join(
                db_prefix() . 'pur_contacts as pc',
                'pc.userid = v.userid AND pc.is_primary = 1',
                'left'
            );

        if ($activeFilter !== null) {
            $query->where('v.active', $activeFilter ? 1 : 0);
        }

        if ($countryFilter !== null) {
            $query->where('v.country', $countryFilter);
        }

        if ($searchTerm !== '') {
            $query
                ->group_start()
                ->like('v.company', $searchTerm)
                ->or_like('v.vendor_code', $searchTerm)
                ->or_like('v.phonenumber', $searchTerm)
                ->group_end();
        }

        $this->applyVendorVisibilityFilter($query);

        $query->order_by('v.company', 'asc');

        $totalQuery = clone $query;
        $total      = (int) $totalQuery->count_all_results();

        $results = $query
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->result_array();

        $this->response([
            'status'     => true,
            'pagination' => $this->buildPaginationMeta($page, $perPage, $total, count($results)),
            'result'     => array_map([$this, 'transformVendorSummary'], $results),
        ], self::HTTP_OK);
    }

    public function vendor_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $vendorId = $this->normalizeIdentifier($id, 'vendor');
        if ($vendorId === null) {
            return;
        }

        $vendor = $this->purchase_model->get_vendor($vendorId);

        if (!$vendor) {
            $this->respondNotFound('Vendor');

            return;
        }

        $includeContactsValue = null;
        $includeContacts      = false;

        try {
            $includeContactsValue = $this->readOptionalBooleanParam('include_contacts');
        } catch (InvalidArgumentException $exception) {
            $this->respondBadRequest($exception->getMessage());

            return;
        }

        $payload = $this->transformVendorDetail((array) $vendor);

        if ($includeContactsValue !== null) {
            $includeContacts = $includeContactsValue;
        }

        if ($includeContacts) {
            $contacts = $this->purchase_model->get_contacts($vendorId);
            $payload['contacts'] = array_map([$this, 'transformVendorContact'], $contacts);
        }

        $this->response([
            'status' => true,
            'result' => $payload,
        ], self::HTTP_OK);
    }

    public function purchase_orders_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $pagination = $this->resolvePagination();
        if ($pagination === null) {
            return;
        }

        [$page, $perPage] = $pagination;

        try {
            $vendorFilter       = $this->readOptionalPositiveIntParam('vendor_id');
            $statusFilter       = $this->readOptionalPositiveIntParam('status', true);
            $approveStatus      = $this->readOptionalPositiveIntParam('approve_status', true);
            $fromDate           = $this->readOptionalDateParam('from');
            $toDate             = $this->readOptionalDateParam('to');
        } catch (InvalidArgumentException $exception) {
            $this->respondBadRequest($exception->getMessage());

            return;
        }

        if ($fromDate && $toDate && $fromDate > $toDate) {
            $this->respondBadRequest('The from date must be earlier than or equal to the to date.');

            return;
        }

        $searchTerm = trim((string) $this->input->get('search', true));

        $query = $this->db
            ->from(db_prefix() . 'pur_orders as po')
            ->select([
                'po.id',
                'po.pur_order_name',
                'po.pur_order_number',
                'po.order_date',
                'po.delivery_date',
                'po.status',
                'po.approve_status',
                'po.subtotal',
                'po.total_tax',
                'po.total',
                'po.discount_percent',
                'po.discount_total',
                'po.discount_type',
                'po.datecreated',
                'po.vendor',
                'po.addedfrom',
                'po.buyer',
                'po.vendornote',
                'po.terms',
            ])
            ->select([
                'v.company     as vendor_name',
                'v.vendor_code as vendor_code',
            ])
            ->join(db_prefix() . 'pur_vendor as v', 'v.userid = po.vendor', 'left');

        if ($this->db->field_exists('currency', db_prefix() . 'pur_orders')) {
            $query
                ->select('po.currency')
                ->select([
                    'cur.name   as currency_name',
                    'cur.symbol as currency_symbol',
                    'cur.decimal_separator as currency_decimal_separator',
                    'cur.thousand_separator as currency_thousand_separator',
                ])
                ->join(db_prefix() . 'currencies as cur', 'cur.id = po.currency', 'left');
        }

        if ($vendorFilter !== null) {
            $query->where('po.vendor', $vendorFilter);
        }

        if ($statusFilter !== null) {
            $query->where('po.status', $statusFilter);
        }

        if ($approveStatus !== null) {
            $query->where('po.approve_status', $approveStatus);
        }

        if ($fromDate !== null) {
            $query->where('po.order_date >=', $fromDate->format('Y-m-d'));
        }

        if ($toDate !== null) {
            $query->where('po.order_date <=', $toDate->format('Y-m-d'));
        }

        if ($searchTerm !== '') {
            $query
                ->group_start()
                ->like('po.pur_order_name', $searchTerm)
                ->or_like('po.pur_order_number', $searchTerm)
                ->group_end();
        }

        $this->applyPurchaseOrderVisibilityFilter($query);

        $query->order_by('po.order_date', 'desc');

        $totalQuery = clone $query;
        $total      = (int) $totalQuery->count_all_results();

        $results = $query
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->result_array();

        $this->response([
            'status'     => true,
            'pagination' => $this->buildPaginationMeta($page, $perPage, $total, count($results)),
            'result'     => array_map([$this, 'transformPurchaseOrderSummary'], $results),
        ], self::HTTP_OK);
    }

    public function purchase_order_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $orderId = $this->normalizeIdentifier($id, 'purchase order');
        if ($orderId === null) {
            return;
        }

        $order = $this->purchase_model->get_pur_order($orderId);

        if (!$order) {
            $this->respondNotFound('Purchase order');

            return;
        }

        $payload = $this->transformPurchaseOrderDetail((array) $order);

        $details = $this->purchase_model->get_pur_order_detail($orderId);
        if (is_array($details) && $details !== []) {
            $payload['items'] = array_map([$this, 'transformPurchaseOrderItem'], $details);
        } else {
            $payload['items'] = [];
        }

        $this->response([
            'status' => true,
            'result' => $payload,
        ], self::HTTP_OK);
    }

    private function resolvePagination(): ?array
    {
        try {
            $page    = $this->readPositiveIntParam('page', self::DEFAULT_PAGE);
            $perPage = $this->readPositiveIntParam('per_page', self::DEFAULT_PAGE_SIZE, self::MAX_PAGE_SIZE);
        } catch (InvalidArgumentException $exception) {
            $this->respondBadRequest($exception->getMessage());

            return null;
        }

        return [$page, $perPage];
    }

    private function readPositiveIntParam(string $name, int $default, ?int $max = null): int
    {
        $raw = $this->input->get($name, true);

        if ($raw === null || $raw === '') {
            return $default;
        }

        if (!preg_match('/^\d+$/', (string) $raw)) {
            throw new InvalidArgumentException(sprintf('The %s parameter must be a positive integer.', $name));
        }

        $value = (int) $raw;

        if ($value < 1) {
            throw new InvalidArgumentException(sprintf('The %s parameter must be greater than or equal to 1.', $name));
        }

        if ($max !== null && $value > $max) {
            throw new InvalidArgumentException(sprintf('The %s parameter cannot be greater than %d.', $name, $max));
        }

        return $value;
    }

    private function readOptionalPositiveIntParam(string $name, bool $allowZero = false): ?int
    {
        $raw = $this->input->get($name, true);

        if ($raw === null || $raw === '') {
            return null;
        }

        if (!preg_match('/^-?\d+$/', (string) $raw)) {
            throw new InvalidArgumentException(sprintf('The %s parameter must be an integer.', $name));
        }

        $value = (int) $raw;

        $minimum = $allowZero ? 0 : 1;
        if ($value < $minimum) {
            throw new InvalidArgumentException(sprintf('The %s parameter must be greater than or equal to %d.', $name, $minimum));
        }

        return $value;
    }

    private function readOptionalBooleanParam(string $name): ?bool
    {
        $raw = $this->input->get($name, true);

        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        $normalized = strtolower((string) $raw);

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        throw new InvalidArgumentException(sprintf('The %s parameter must be a boolean value.', $name));
    }

    private function readOptionalDateParam(string $name): ?DateTimeImmutable
    {
        $raw = trim((string) $this->input->get($name, true));

        if ($raw === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $raw);

        if (!$date) {
            throw new InvalidArgumentException(sprintf('The %s parameter must use the YYYY-MM-DD format.', $name));
        }

        return $date;
    }

    private function normalizeIdentifier($value, string $label): ?int
    {
        if ($value === null || $value === '') {
            $this->respondBadRequest(ucfirst($label) . ' identifier is required.');

            return null;
        }

        if (!preg_match('/^\d+$/', (string) $value)) {
            $this->respondBadRequest('Invalid ' . $label . ' identifier provided.');

            return null;
        }

        return (int) $value;
    }

    private function transformVendorSummary(array $vendor): array
    {
        return [
            'id'              => (int) $vendor['userid'],
            'company'         => $vendor['company'],
            'vendor_code'     => $vendor['vendor_code'],
            'vat'             => $vendor['vat'],
            'phone'           => $vendor['phonenumber'],
            'active'          => (bool) $vendor['active'],
            'country'         => $this->buildCountryPayload($vendor),
            'city'            => $vendor['city'],
            'state'           => $vendor['state'],
            'zip'             => $vendor['zip'],
            'address'         => $vendor['address'],
            'website'         => $vendor['website'],
            'date_created'    => $this->formatDateTime($vendor['datecreated']),
            'primary_contact' => $this->buildPrimaryContactPayload($vendor),
        ];
    }

    private function transformVendorDetail(array $vendor): array
    {
        return [
            'id'               => (int) $vendor['userid'],
            'company'          => $vendor['company'],
            'vendor_code'      => $vendor['vendor_code'] ?? null,
            'vat'              => $vendor['vat'] ?? null,
            'phone'            => $vendor['phonenumber'] ?? null,
            'active'           => isset($vendor['active']) ? (bool) $vendor['active'] : null,
            'country'          => $this->buildCountryPayload($vendor),
            'city'             => $vendor['city'] ?? null,
            'state'            => $vendor['state'] ?? null,
            'zip'              => $vendor['zip'] ?? null,
            'address'          => $vendor['address'] ?? null,
            'website'          => $vendor['website'] ?? null,
            'date_created'     => isset($vendor['datecreated']) ? $this->formatDateTime($vendor['datecreated']) : null,
            'default_currency' => isset($vendor['default_currency']) ? (int) $vendor['default_currency'] : null,
            'default_language' => $vendor['default_language'] ?? null,
            'billing_address'  => [
                'street'  => $vendor['billing_street'] ?? null,
                'city'    => $vendor['billing_city'] ?? null,
                'state'   => $vendor['billing_state'] ?? null,
                'zip'     => $vendor['billing_zip'] ?? null,
                'country' => isset($vendor['billing_country']) ? (int) $vendor['billing_country'] : null,
            ],
            'shipping_address' => [
                'street'  => $vendor['shipping_street'] ?? null,
                'city'    => $vendor['shipping_city'] ?? null,
                'state'   => $vendor['shipping_state'] ?? null,
                'zip'     => $vendor['shipping_zip'] ?? null,
                'country' => isset($vendor['shipping_country']) ? (int) $vendor['shipping_country'] : null,
            ],
            'location'         => [
                'latitude'  => isset($vendor['latitude']) ? (float) $vendor['latitude'] : null,
                'longitude' => isset($vendor['longitude']) ? (float) $vendor['longitude'] : null,
            ],
            'primary_contact'  => $this->buildPrimaryContactPayload($vendor),
        ];
    }

    private function transformVendorContact(array $contact): array
    {
        return [
            'id'          => (int) $contact['id'],
            'vendor_id'   => (int) $contact['userid'],
            'first_name'  => $contact['firstname'],
            'last_name'   => $contact['lastname'],
            'email'       => $contact['email'],
            'phone'       => $contact['phonenumber'],
            'title'       => $contact['title'],
            'is_primary'  => isset($contact['is_primary']) ? (bool) $contact['is_primary'] : false,
            'is_active'   => isset($contact['active']) ? (bool) $contact['active'] : true,
            'last_login'  => $this->formatDateTime($contact['last_login'] ?? null),
            'created_at'  => $this->formatDateTime($contact['datecreated'] ?? null),
        ];
    }

    private function transformPurchaseOrderSummary(array $order): array
    {
        return [
            'id'              => (int) $order['id'],
            'name'            => $order['pur_order_name'],
            'number'          => $order['pur_order_number'],
            'order_date'      => $this->formatDate($order['order_date'] ?? null),
            'delivery_date'   => $this->formatDate($order['delivery_date'] ?? null),
            'status'          => isset($order['status']) ? (int) $order['status'] : null,
            'approve_status'  => isset($order['approve_status']) ? (int) $order['approve_status'] : null,
            'subtotal'        => $this->toFloat($order['subtotal'] ?? null),
            'total_tax'       => $this->toFloat($order['total_tax'] ?? null),
            'total'           => $this->toFloat($order['total'] ?? null),
            'discount'        => [
                'percent' => $this->toFloat($order['discount_percent'] ?? null),
                'total'   => $this->toFloat($order['discount_total'] ?? null),
                'type'    => $order['discount_type'] ?? null,
            ],
            'vendor'          => [
                'id'   => isset($order['vendor']) ? (int) $order['vendor'] : null,
                'name' => $order['vendor_name'] ?? null,
                'code' => $order['vendor_code'] ?? null,
            ],
            'currency'        => $this->buildCurrencyPayload($order),
            'notes'           => $order['vendornote'] ?? null,
            'terms'           => $order['terms'] ?? null,
            'created_at'      => $this->formatDateTime($order['datecreated'] ?? null),
        ];
    }

    private function transformPurchaseOrderDetail(array $order): array
    {
        $payload = $this->transformPurchaseOrderSummary($order);

        $payload['created_by'] = isset($order['addedfrom']) ? (int) $order['addedfrom'] : null;
        $payload['buyer_id']   = isset($order['buyer']) ? (int) $order['buyer'] : null;

        return $payload;
    }

    private function transformPurchaseOrderItem(array $item): array
    {
        return [
            'id'             => isset($item['id']) ? (int) $item['id'] : null,
            'purchase_order' => isset($item['pur_order']) ? (int) $item['pur_order'] : null,
            'item_code'      => $item['item_code'] ?? null,
            'unit_id'        => isset($item['unit_id']) ? (int) $item['unit_id'] : null,
            'unit_price'     => $this->toFloat($item['unit_price'] ?? null),
            'quantity'       => $this->toFloat($item['quantity'] ?? null),
            'into_money'     => $this->toFloat($item['into_money'] ?? null),
            'tax'            => $item['tax'] ?? null,
            'total'          => $this->toFloat($item['total'] ?? null),
            'discount_rate'  => $this->toFloat($item['discount_%'] ?? null),
            'discount_money' => $this->toFloat($item['discount_money'] ?? null),
            'total_money'    => $this->toFloat($item['total_money'] ?? null),
        ];
    }

    private function buildCountryPayload(array $vendor): array
    {
        return [
            'id'         => isset($vendor['country']) ? (int) $vendor['country'] : (isset($vendor['country_id']) ? (int) $vendor['country_id'] : null),
            'name'       => $vendor['country_name'] ?? null,
            'short_name' => $vendor['country_short_name'] ?? null,
            'iso2'       => $vendor['country_iso2'] ?? null,
            'iso3'       => $vendor['country_iso3'] ?? null,
        ];
    }

    private function buildPrimaryContactPayload(array $vendor): ?array
    {
        if (!isset($vendor['primary_contact_id']) || $vendor['primary_contact_id'] === null) {
            return null;
        }

        return [
            'id'         => (int) $vendor['primary_contact_id'],
            'first_name' => $vendor['primary_contact_first_name'] ?? null,
            'last_name'  => $vendor['primary_contact_last_name'] ?? null,
            'email'      => $vendor['primary_contact_email'] ?? null,
            'phone'      => $vendor['primary_contact_phone'] ?? null,
        ];
    }

    private function buildCurrencyPayload(array $order): ?array
    {
        if (!isset($order['currency']) && !isset($order['currency_name']) && !isset($order['currency_symbol'])) {
            return null;
        }

        return [
            'id'                 => isset($order['currency']) ? (int) $order['currency'] : null,
            'name'               => $order['currency_name'] ?? null,
            'symbol'             => $order['currency_symbol'] ?? null,
            'decimal_separator'  => $order['currency_decimal_separator'] ?? null,
            'thousand_separator' => $order['currency_thousand_separator'] ?? null,
        ];
    }

    private function buildPaginationMeta(int $page, int $perPage, int $total, int $returned): array
    {
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);

        return [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
            'returned'    => $returned,
        ];
    }

    private function formatDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->createDate($value)?->format('Y-m-d');
    }

    private function formatDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $this->createDate($value);

        return $date ? $date->format(DateTimeInterface::ATOM) : null;
    }

    private function createDate(string $value): ?DateTimeImmutable
    {
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function respondBadRequest(string $message): void
    {
        $this->response([
            'status'  => false,
            'message' => $message,
        ], self::HTTP_BAD_REQUEST);
    }

    private function respondNotFound(string $resource): void
    {
        $this->response([
            'status'  => false,
            'message' => $resource . ' not found.',
        ], self::HTTP_NOT_FOUND);
    }

    private function applyVendorVisibilityFilter(CI_DB_query_builder $query): void
    {
        if (!function_exists('is_staff_logged_in') || !is_staff_logged_in()) {
            return;
        }

        if (has_permission('purchase_vendors', '', 'view')) {
            return;
        }

        $staffId = (int) get_staff_user_id();

        $query->where(
            sprintf(
                'v.userid IN (SELECT vendor_id FROM %1$spur_vendor_admin WHERE staff_id = %2$d)',
                db_prefix(),
                $staffId
            )
        );
    }

    private function applyPurchaseOrderVisibilityFilter(CI_DB_query_builder $query): void
    {
        if (!function_exists('is_staff_logged_in') || !is_staff_logged_in()) {
            return;
        }

        if (has_permission('purchase_orders', '', 'view')) {
            return;
        }

        $staffId = (int) get_staff_user_id();

        $query->group_start();
        $query->where('po.addedfrom', $staffId);
        $query->or_where('po.buyer', $staffId);
        $query->or_where(
            sprintf(
                'po.vendor IN (SELECT vendor_id FROM %1$spur_vendor_admin WHERE staff_id = %2$d)',
                db_prefix(),
                $staffId
            )
        );
        $query->group_end();
    }
}
