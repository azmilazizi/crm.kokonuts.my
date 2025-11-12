<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_purchase extends API_Controller
{
    /**
     * Cached staff context resolved from the bearer token.
     *
     * @var object|null
     */
    private $authenticatedStaff = null;
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
        if (!$this->ensureStaffAuthenticated()) {
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
                'c.long_name    as country_name',
                'c.short_name   as country_short_name',
                'c.iso2         as country_iso2',
                'c.iso3         as country_iso3',
                'c.phone_code   as country_phone_code',
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
        if (!$this->ensureStaffAuthenticated()) {
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
        if (!$this->ensureStaffAuthenticated()) {
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

    public function purchase_orders_post()
    {
        if (!$this->ensureStaffAuthenticated()) {
            return;
        }

        if (function_exists('has_permission') && !has_permission('purchase_orders', '', 'create')) {
            $this->response([
                'status'  => false,
                'message' => _l('access_denied'),
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $payload = $this->getRequestPayload();

        try {
            [$orderName, $vendorId] = $this->resolvePurchaseOrderBasics($payload);
            $orderDate              = $this->resolveDateField($payload, 'order_date', true);
            $deliveryDate           = $this->resolveDateField($payload, 'delivery_date', false) ?? $orderDate;
            $currencyId             = $this->resolveCurrencyId($payload['currency_id'] ?? ($payload['currency'] ?? null));
            $currencyRate           = $this->resolveNumeric($payload['currency_rate'] ?? 1, 'currency_rate', false, true);
            $buyerId                = $this->resolveOptionalPositiveInt($payload['buyer_id'] ?? $payload['buyer'] ?? null);
            $departmentId           = $this->resolveOptionalPositiveInt($payload['department_id'] ?? $payload['department'] ?? null);
            $itemsSummary           = $this->buildPurchaseOrderItems($payload['items'] ?? ($payload['newitems'] ?? []));
        } catch (InvalidArgumentException $exception) {
            $this->respondBadRequest($exception->getMessage());

            return;
        }

        $orderDiscount      = $this->resolveNumeric($payload['order_discount'] ?? 0, 'order_discount', true, true);
        $additionalDiscount = $this->resolveNumeric($payload['additional_discount'] ?? 0, 'additional_discount', true, true);
        $shippingFee        = $this->resolveNumeric($payload['shipping_fee'] ?? 0, 'shipping_fee', true, true);
        $adjustment         = $this->resolveNumeric($payload['adjustment'] ?? 0, 'adjustment', true, false, true);

        $subtotal  = $itemsSummary['subtotal'];
        $taxTotal  = $itemsSummary['tax_total'];
        $grandTotal = $subtotal + $taxTotal + $shippingFee + $adjustment;

        $orderDiscount = min($orderDiscount, $grandTotal);
        $grandTotal   -= $orderDiscount + $additionalDiscount;

        if ($grandTotal < 0) {
            $grandTotal = 0.0;
        }

        $orderData = [
            'pur_order_name'   => $orderName,
            'vendor'           => $vendorId,
            'terms'            => (string) ($payload['terms'] ?? ''),
            'vendornote'       => (string) ($payload['vendor_note'] ?? ($payload['vendornote'] ?? '')),
            'buyer'            => $buyerId,
            'department'       => $departmentId,
            'currency'         => $currencyId,
            'currency_rate'    => $this->formatDecimal($currencyRate),
            'order_date'       => $orderDate->format('Y-m-d'),
            'delivery_date'    => $deliveryDate->format('Y-m-d'),
            'shipping_fee'     => $this->formatDecimal($shippingFee),
            'adjustment'       => $this->formatDecimal($adjustment),
            'order_discount'   => $this->formatDecimal($orderDiscount),
            'add_discount_type'=> 'amount',
            'discount_type'    => 'before_tax',
            'discount_total'   => $this->formatDecimal($orderDiscount),
            'additional_discount' => $this->formatDecimal($additionalDiscount),
            'newitems'         => $itemsSummary['items'],
            'total_mn'         => $this->formatDecimal($subtotal),
            'grand_total'      => $this->formatDecimal($grandTotal),
        ];

        $this->applyNextPurchaseOrderNumber($orderData, $orderDate, $vendorId);

        $orderId = $this->purchase_model->add_pur_order($orderData);

        if (!$orderId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create purchase order with the provided data.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $order   = $this->purchase_model->get_pur_order($orderId);
        $details = $this->purchase_model->get_pur_order_detail($orderId);

        $result = $this->transformPurchaseOrderDetail((array) $order);
        $result['items'] = array_map([$this, 'transformPurchaseOrderItem'], $details);

        $this->response([
            'status' => true,
            'result' => $result,
        ], self::HTTP_CREATED);
    }

    public function purchase_order_get($id = null)
    {
        if (!$this->ensureStaffAuthenticated()) {
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

    private function getRequestPayload(): array
    {
        $raw = trim((string) $this->input->raw_input_stream);

        if ($raw !== '') {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $payload = $this->post();

        if (is_array($payload) && $payload !== []) {
            return $payload;
        }

        return is_array($_POST) ? $_POST : [];
    }

    private function resolvePurchaseOrderBasics(array $payload): array
    {
        $orderName = trim((string) ($payload['order_name'] ?? $payload['pur_order_name'] ?? ''));

        if ($orderName === '') {
            throw new InvalidArgumentException('Purchase order name is required.');
        }

        $vendorRaw = $payload['vendor_id'] ?? ($payload['vendor'] ?? null);

        if ($vendorRaw === null || $vendorRaw === '') {
            throw new InvalidArgumentException('Vendor identifier is required.');
        }

        if (!preg_match('/^\d+$/', (string) $vendorRaw)) {
            throw new InvalidArgumentException('Vendor identifier must be a positive integer.');
        }

        $vendorId = (int) $vendorRaw;

        if ($vendorId < 1) {
            throw new InvalidArgumentException('Vendor identifier must be greater than or equal to 1.');
        }

        return [$orderName, $vendorId];
    }

    private function resolveDateField(array $payload, string $key, bool $required): ?DateTimeImmutable
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === null) {
            if ($required) {
                throw new InvalidArgumentException(sprintf('The %s field is required and must use the YYYY-MM-DD format.', $key));
            }

            return null;
        }

        $value = trim((string) $payload[$key]);
        $date  = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if (!$date) {
            throw new InvalidArgumentException(sprintf('The %s field must use the YYYY-MM-DD format.', $key));
        }

        return $date;
    }

    private function resolveCurrencyId($raw): int
    {
        if ($raw === null || $raw === '') {
            $baseCurrency = function_exists('get_base_currency') ? get_base_currency() : null;

            return $baseCurrency ? (int) $baseCurrency->id : 0;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        $candidate = $this->db
            ->where('name', $raw)
            ->or_where('symbol', $raw)
            ->get(db_prefix() . 'currencies')
            ->row();

        if ($candidate) {
            return (int) $candidate->id;
        }

        throw new InvalidArgumentException('Unable to resolve the provided currency.');
    }

    private function resolveNumeric($value, string $label, bool $allowZero, bool $positive, bool $allowNegative = false): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('The %s value must be numeric.', $label));
        }

        $number = (float) $value;

        if (!$allowNegative && $number < 0) {
            throw new InvalidArgumentException(sprintf('The %s value cannot be negative.', $label));
        }

        if ($positive && !$allowZero && $number <= 0) {
            throw new InvalidArgumentException(sprintf('The %s value must be greater than 0.', $label));
        }

        if ($positive && $allowZero && $number < 0) {
            throw new InvalidArgumentException(sprintf('The %s value cannot be negative.', $label));
        }

        return $number;
    }

    private function resolveOptionalPositiveInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!preg_match('/^\d+$/', (string) $value)) {
            throw new InvalidArgumentException('Identifiers must be positive integers.');
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function buildPurchaseOrderItems($items): array
    {
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('At least one line item is required to create a purchase order.');
        }

        $normalized = [];
        $subtotal   = 0.0;
        $taxTotal   = 0.0;

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Each purchase order item must be an object.');
            }

            $name = trim((string) ($item['name'] ?? $item['item_name'] ?? ''));

            if ($name === '') {
                throw new InvalidArgumentException(sprintf('Item %d is missing a name.', $index + 1));
            }

            $quantity  = $this->resolveNumeric($item['quantity'] ?? $item['qty'] ?? null, 'quantity', false, true);
            $unitPrice = $this->resolveNumeric($item['unit_price'] ?? $item['rate'] ?? $item['price'] ?? null, 'unit_price', false, true);

            $discountPercent = $this->resolveNumeric($item['discount_percent'] ?? $item['discount'] ?? null, 'discount_percent', true, false, true);
            $discountMoney   = $this->resolveNumeric($item['discount_amount'] ?? $item['discount_money'] ?? null, 'discount_amount', true, false, true);

            $lineSubtotal = $quantity * $unitPrice;

            if ($discountMoney === 0.0 && $discountPercent > 0) {
                $discountMoney = $lineSubtotal * $discountPercent / 100;
            }

            if ($discountPercent === 0.0 && $discountMoney > 0 && $lineSubtotal > 0) {
                $discountPercent = ($discountMoney / $lineSubtotal) * 100;
            }

            if ($discountMoney > $lineSubtotal) {
                throw new InvalidArgumentException(sprintf('Discount for item %d exceeds its subtotal.', $index + 1));
            }

            $netBeforeTax = $lineSubtotal - $discountMoney;

            if ($netBeforeTax < 0) {
                $netBeforeTax = 0.0;
            }

            $taxSelections = $this->resolveTaxSelections($item['taxes'] ?? ($item['tax_select'] ?? []));
            $taxValue      = $this->calculateTax($netBeforeTax, $taxSelections);
            $lineTotal     = $netBeforeTax + $taxValue;

            $normalized[] = [
                'item_code'        => $item['item_code'] ?? ($item['code'] ?? ''),
                'item_name'        => $name,
                'item_description' => (string) ($item['description'] ?? $item['item_description'] ?? ''),
                'quantity'         => $this->formatDecimal($quantity, 4),
                'unit_price'       => $this->formatDecimal($unitPrice),
                'unit_id'          => $item['unit_id'] ?? null,
                'unit_name'        => $item['unit_name'] ?? null,
                'discount'         => $this->formatDecimal($discountPercent, 4),
                'discount_money'   => $this->formatDecimal($discountMoney),
                'into_money'       => $this->formatDecimal($netBeforeTax),
                'total_money'      => $this->formatDecimal($lineTotal),
                'total'            => $this->formatDecimal($lineTotal),
                'tax_value'        => $this->formatDecimal($taxValue),
                'tax_select'       => $taxSelections,
            ];

            $subtotal += $netBeforeTax;
            $taxTotal += $taxValue;
        }

        return [
            'items'     => $normalized,
            'subtotal'  => $subtotal,
            'tax_total' => $taxTotal,
        ];
    }

    private function resolveTaxSelections($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (!is_array($raw)) {
            if (is_string($raw) && strpos($raw, '|') !== false) {
                return [$raw];
            }

            throw new InvalidArgumentException('Tax selections must be provided as an array.');
        }

        if ($raw === []) {
            return [];
        }

        $resolved = [];
        $ids      = [];

        foreach ($raw as $entry) {
            if (is_string($entry) && strpos($entry, '|') !== false) {
                $resolved[] = $entry;

                continue;
            }

            if (is_array($entry)) {
                if (isset($entry['id'])) {
                    $ids[] = (int) $entry['id'];

                    continue;
                }

                if (isset($entry['tax_id'])) {
                    $ids[] = (int) $entry['tax_id'];

                    continue;
                }
            }

            if (is_numeric($entry)) {
                $ids[] = (int) $entry;

                continue;
            }

            throw new InvalidArgumentException('Each tax selection must reference a valid tax identifier.');
        }

        if ($ids !== []) {
            $uniqueIds = array_unique(array_filter($ids, static fn ($value) => $value > 0));

            if ($uniqueIds !== []) {
                $taxes = $this->db
                    ->where_in('id', $uniqueIds)
                    ->get(db_prefix() . 'taxes')
                    ->result_array();

                $map = [];
                foreach ($taxes as $tax) {
                    $map[(int) $tax['id']] = $tax['name'] . '|' . $tax['taxrate'];
                }

                foreach ($ids as $id) {
                    if (isset($map[$id])) {
                        $resolved[] = $map[$id];
                    }
                }
            }
        }

        return $resolved;
    }

    private function calculateTax(float $amount, array $taxSelections): float
    {
        if ($amount <= 0 || $taxSelections === []) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($taxSelections as $selection) {
            $parts = explode('|', (string) $selection);
            if (!isset($parts[1])) {
                continue;
            }

            $rate = (float) $parts[1];
            if ($rate === 0.0) {
                continue;
            }

            $total += $amount * $rate / 100;
        }

        return $total;
    }

    private function applyNextPurchaseOrderNumber(array &$orderData, DateTimeImmutable $orderDate, int $vendorId): void
    {
        $prefix     = function_exists('get_purchase_option') ? get_purchase_option('pur_order_prefix') : '';
        $nextNumber = function_exists('get_purchase_option') ? (int) get_purchase_option('next_po_number') : 0;

        if ($nextNumber < 1) {
            $nextNumber = 1;
        }

        $baseNumber = $prefix . '-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

        if ((int) get_option('po_only_prefix_and_number') === 1) {
            $orderData['pur_order_number'] = $baseNumber;
        } else {
            $dateFragment = $orderDate->format('dmY');
            $vendorName   = function_exists('get_vendor_company_name') ? get_vendor_company_name($vendorId) : '';

            $orderData['pur_order_number'] = $baseNumber . '-' . $dateFragment . ($vendorName !== '' ? '-' . $vendorName : '');
        }

        $orderData['number'] = $nextNumber;
    }

    private function formatDecimal($value, int $precision = 2): string
    {
        return number_format((float) $value, $precision, '.', '');
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
            'phone_code' => $vendor['country_phone_code'] ?? null,
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

    private function ensureStaffAuthenticated()
    {
        $token = $this->authenticate_token();

        if ($token === false || !isset($token['data']) || !is_object($token['data'])) {
            return false;
        }

        $payload = $token['data'];

        if (!isset($payload->staffid) || (int) $payload->staffid <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Authenticated staff context is required for this request.',
            ], self::HTTP_UNAUTHORIZED);

            return false;
        }

        $staffId = (int) $payload->staffid;

        $currentSessionId = (int) ($this->session->userdata('staff_user_id') ?? 0);

        if ($currentSessionId !== $staffId || !$this->session->userdata('staff_logged_in')) {
            $this->session->set_userdata([
                'staff_user_id'   => $staffId,
                'staff_logged_in' => true,
            ]);
        }

        if (!isset($this->authenticatedStaff) || (int) $this->authenticatedStaff->staffid !== $staffId) {
            $this->load->model('staff_model');

            $staff = $this->staff_model->get($staffId);

            if (!$staff || (int) $staff->active !== 1) {
                $this->session->unset_userdata('staff_logged_in');
                $this->session->unset_userdata('staff_user_id');

                $this->response([
                    'status'  => false,
                    'message' => 'Unable to resolve the authenticated staff member.',
                ], self::HTTP_UNAUTHORIZED);

                return false;
            }

            $GLOBALS['current_user'] = $staff;
            $this->authenticatedStaff = $staff;
        }

        return $payload;
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
