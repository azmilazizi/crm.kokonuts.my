<?php

defined('BASEPATH') or exit('No direct script access allowed');
require __DIR__ . '/API_purchase_Controller.php';

class Api_purchase extends API_purchase_Controller
{
    /**
     * Cached authenticated staff row.
     *
     * @var object|null
     */
    protected $authenticated_staff = null;

    /**
     * Cached permissions per staff id.
     *
     * @var array<int, array>
     */
    protected $staff_permissions = [];

    /**
     * Allowed contact fields when creating/updating vendors.
     *
     * @var string[]
     */
    protected $vendor_contact_fields = [
        'firstname',
        'lastname',
        'email',
        'phonenumber',
        'title',
        'password',
        'send_set_password_email',
        'donotsendwelcomeemail',
        'permissions',
        'direction',
        'invoice_emails',
        'estimate_emails',
        'credit_note_emails',
        'contract_emails',
        'task_emails',
        'project_emails',
        'ticket_emails',
        'is_primary',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('purchase/purchase_model', 'purchase_model');
        $this->load->model('staff_model');
        $this->load->helper('purchase/purchase');
    }

    public function index_get()
    {
        $this->response([
            'status' => true,
            'result' => [
                'message' => 'Purchase API is available.',
                'endpoints' => [
                    'GET    /purchase/api/v1/vendors',
                    'POST   /purchase/api/v1/vendors',
                    'GET    /purchase/api/v1/vendors/{id}',
                    'PUT    /purchase/api/v1/vendors/{id}',
                    'GET    /purchase/api/v1/purchase-orders',
                    'POST   /purchase/api/v1/purchase-orders',
                    'GET    /purchase/api/v1/purchase-orders/{id}',
                    'PUT    /purchase/api/v1/purchase-orders/{id}',
                    'DELETE /purchase/api/v1/purchase-orders/{id}',
                    'GET    /purchase/api/v1/options',
                    'GET    /purchase/api/v1/options/{name}',
                ],
            ],
        ], self::HTTP_OK);
    }

    public function vendors_get($id = '')
    {
        $scope = $this->get_vendor_access_scope();
        if ($scope === null) {
            return;
        }

        if ($id === '') {
            $page    = max(1, (int) $this->input->get('page'));
            $perPage = (int) $this->input->get('per_page');
            if ($perPage <= 0) {
                $perPage = 25;
            }
            $perPage = min($perPage, 100);
            $offset  = ($page - 1) * $perPage;

            $filters = [
                'search'         => $this->input->get('search', true),
                'category'       => $this->input->get('category', true),
                'active'         => $this->input->get('active', true),
                'country'        => $this->input->get('country', true),
                'created_from'   => $this->input->get('created_from', true),
                'created_to'     => $this->input->get('created_to', true),
                'sort_by'        => $this->input->get('sort_by', true),
                'sort_direction' => $this->input->get('sort_direction', true),
            ];

            $result = $this->purchase_model->get_vendors_for_api($filters, $perPage, $offset, $scope);
            $records = [];
            foreach ($result['vendors'] as $vendor) {
                $records[] = $this->format_vendor_record($vendor);
            }

            $this->response([
                'status' => true,
                'result' => [
                    'total'        => $result['total'],
                    'page'         => $page,
                    'per_page'     => $perPage,
                    'total_pages'  => $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 0,
                    'records'      => $records,
                ],
            ], self::HTTP_OK);

            return;
        }

        $vendorId = (int) $id;
        if ($vendorId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid vendor identifier.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        if (!$scope['can_view_all'] && !$this->is_vendor_accessible_by_staff($vendorId, $scope['staff_id'])) {
            $this->response([
                'status'  => false,
                'message' => 'Vendor not found or access denied.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $vendor = $this->purchase_model->get_vendor($vendorId);
        if (!$vendor) {
            $this->response([
                'status'  => false,
                'message' => 'Vendor not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $contacts = $this->purchase_model->get_contacts($vendorId, []);
        $formattedVendor = $this->format_vendor_record((array) $vendor);

        $this->response([
            'status' => true,
            'result' => [
                'vendor'   => $formattedVendor,
                'contacts' => $contacts,
            ],
        ], self::HTTP_OK);
    }
    public function vendors_post()
    {
        $staff = $this->get_authenticated_staff();
        if (!$staff) {
            return;
        }

        if (!$this->staff_has_permission($staff, 'purchase_vendors', 'create')) {
            $this->response([
                'status'  => false,
                'message' => 'You do not have permission to create vendors.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $payload = $this->get_json_input();
        if ($payload === null) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid JSON payload.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $vendorData = $this->prepare_vendor_payload($payload, $staff, false);
        $errors     = $this->validate_vendor_payload($vendorData, false);

        if (!empty($errors)) {
            $this->respond_with_errors($errors);

            return;
        }

        $vendorId = $this->purchase_model->add_vendor($vendorData);
        if (!$vendorId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create vendor.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        if (isset($payload['admin_ids']) && is_array($payload['admin_ids'])) {
            $admins = array_map('intval', $payload['admin_ids']);
            $admins = array_filter($admins, static function ($value) {
                return $value > 0;
            });

            if (!empty($admins)) {
                $this->purchase_model->assign_vendor_admins(['customer_admins' => $admins], $vendorId);
            }
        }

        $vendor   = $this->purchase_model->get_vendor($vendorId);
        $contacts = $this->purchase_model->get_contacts($vendorId, []);

        $this->response([
            'status' => true,
            'result' => [
                'vendor'   => $this->format_vendor_record((array) $vendor),
                'contacts' => $contacts,
            ],
        ], self::HTTP_CREATED);
    }

    public function vendors_put($id = '')
    {
        $vendorId = (int) $id;
        if ($vendorId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid vendor identifier.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $staff = $this->get_authenticated_staff();
        if (!$staff) {
            return;
        }

        if (!$this->staff_has_permission($staff, 'purchase_vendors', 'edit')) {
            $this->response([
                'status'  => false,
                'message' => 'You do not have permission to update vendors.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $scope = $this->get_vendor_access_scope();
        if ($scope === null) {
            return;
        }

        if (!$scope['can_view_all'] && !$this->is_vendor_accessible_by_staff($vendorId, $scope['staff_id'])) {
            $this->response([
                'status'  => false,
                'message' => 'Vendor not found or access denied.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $payload = $this->get_json_input();
        if ($payload === null) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid JSON payload.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $vendorData = $this->prepare_vendor_payload($payload, $staff, true);
        $errors     = $this->validate_vendor_payload($vendorData, true);

        if (!empty($errors)) {
            $this->respond_with_errors($errors);

            return;
        }

        $updated = $this->purchase_model->update_vendor($vendorData, $vendorId);
        if ($updated === false) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update vendor.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        if (isset($payload['admin_ids']) && is_array($payload['admin_ids'])) {
            $admins = array_map('intval', $payload['admin_ids']);
            $admins = array_filter($admins, static function ($value) {
                return $value > 0;
            });

            $this->purchase_model->assign_vendor_admins(['customer_admins' => $admins], $vendorId);
        }

        $vendor   = $this->purchase_model->get_vendor($vendorId);
        $contacts = $this->purchase_model->get_contacts($vendorId, []);

        $this->response([
            'status' => true,
            'result' => [
                'vendor'   => $this->format_vendor_record((array) $vendor),
                'contacts' => $contacts,
            ],
        ], self::HTTP_OK);
    }
    public function purchase_orders_get($id = '')
    {
        $scope = $this->get_purchase_order_access_scope();
        if ($scope === null) {
            return;
        }

        if ($id === '') {
            $page    = max(1, (int) $this->input->get('page'));
            $perPage = (int) $this->input->get('per_page');
            if ($perPage <= 0) {
                $perPage = 25;
            }
            $perPage = min($perPage, 100);
            $offset  = ($page - 1) * $perPage;

            $filters = [
                'search'          => $this->input->get('search', true),
                'vendor'          => $this->input->get('vendor', true),
                'status'          => $this->input->get('status', true),
                'approve_status'  => $this->input->get('approve_status', true),
                'order_status'    => $this->input->get('order_status', true),
                'delivery_status' => $this->input->get('delivery_status', true),
                'date_from'       => $this->input->get('date_from', true),
                'date_to'         => $this->input->get('date_to', true),
                'sort_by'         => $this->input->get('sort_by', true),
                'sort_direction'  => $this->input->get('sort_direction', true),
            ];

            $result = $this->purchase_model->get_purchase_orders_for_api($filters, $perPage, $offset, $scope);
            $orders = [];
            foreach ($result['orders'] as $order) {
                $orders[] = $this->format_purchase_order_record($order);
            }

            $this->response([
                'status' => true,
                'result' => [
                    'total'        => $result['total'],
                    'page'         => $page,
                    'per_page'     => $perPage,
                    'total_pages'  => $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 0,
                    'records'      => $orders,
                ],
            ], self::HTTP_OK);

            return;
        }

        $orderId = (int) $id;
        if ($orderId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $rawInclude      = $this->input->get('include');
        $hasIncludeParam = $rawInclude !== null;
        $sectionsFilter  = $hasIncludeParam ? $this->resolve_purchase_order_include_sections($rawInclude) : null;

        if ($hasIncludeParam && empty($sectionsFilter)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid include parameter. Allowed values are: order, attachments, payments.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $modelOptions = [];
        if ($sectionsFilter !== null) {
            $modelOptions['include_attachments'] = in_array('attachments', $sectionsFilter, true);
            $modelOptions['include_payments']    = in_array('payments', $sectionsFilter, true);
        }

        $order = $this->purchase_model->get_purchase_order_with_details($orderId, $modelOptions);
        if (!$order) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!$this->can_access_purchase_order($order, $scope)) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found or access denied.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        if ($sectionsFilter === null) {
            $this->response([
                'status' => true,
                'result' => $this->format_purchase_order_detail($order),
            ], self::HTTP_OK);

            return;
        }

        $result = [];

        if (in_array('order', $sectionsFilter, true)) {
            $result['order'] = $this->format_purchase_order_core($order);
        }

        if (in_array('attachments', $sectionsFilter, true)) {
            $result['attachments'] = $order['attachments'] ?? [];
        }

        if (in_array('payments', $sectionsFilter, true)) {
            $payments = $order['payments'] ?? [];
            if (!empty($payments)) {
                $payments = array_map([
                    $this,
                    'normalize_purchase_order_payment',
                ], $payments);
            }
            $result['payments'] = $payments;
        }

        $this->response([
            'status' => true,
            'result' => $result,
        ], self::HTTP_OK);
    }

    public function purchase_orders_post()
    {
        $staff = $this->get_authenticated_staff();
        if (!$staff) {
            return;
        }

        if (!$this->staff_has_permission($staff, 'purchase_orders', 'create')) {
            $this->response([
                'status'  => false,
                'message' => 'You do not have permission to create purchase orders.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $payload = $this->get_json_input();
        if ($payload === null) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid JSON payload.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $prepared = $this->prepare_purchase_order_payload($payload, $staff, false);
        if (isset($prepared['errors'])) {
            $this->respond_with_errors($prepared['errors']);

            return;
        }

        $orderId = $this->purchase_model->add_pur_order($prepared['data']);
        if (!$orderId) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to create purchase order.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $order = $this->purchase_model->get_purchase_order_with_details($orderId);

        $this->response([
            'status' => true,
            'result' => $this->format_purchase_order_detail($order),
        ], self::HTTP_CREATED);
    }

    public function purchase_orders_put($id = '')
    {
        $orderId = (int) $id;
        if ($orderId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $staff = $this->get_authenticated_staff();
        if (!$staff) {
            return;
        }

        if (!$this->staff_has_permission($staff, 'purchase_orders', 'edit')) {
            $this->response([
                'status'  => false,
                'message' => 'You do not have permission to update purchase orders.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $payload = $this->get_json_input();
        if ($payload === null) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid JSON payload.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $scope = $this->get_purchase_order_access_scope();
        if ($scope === null) {
            return;
        }

        $existingOrder = $this->purchase_model->get_purchase_order_with_details($orderId);
        if (!$existingOrder) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!$this->can_access_purchase_order($existingOrder, $scope)) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found or access denied.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $prepared = $this->prepare_purchase_order_payload($payload, $staff, true);
        if (isset($prepared['errors'])) {
            $this->respond_with_errors($prepared['errors']);

            return;
        }

        $updated = $this->purchase_model->update_pur_order($prepared['data'], $orderId);
        if ($updated === false) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to update purchase order.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $order = $this->purchase_model->get_purchase_order_with_details($orderId);

        $this->response([
            'status' => true,
            'result' => $this->format_purchase_order_detail($order),
        ], self::HTTP_OK);
    }

    public function purchase_orders_delete($id = '')
    {
        $orderId = (int) $id;
        if ($orderId <= 0) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid purchase order identifier.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $staff = $this->get_authenticated_staff();
        if (!$staff) {
            return;
        }

        if (!$this->staff_has_permission($staff, 'purchase_orders', 'delete')) {
            $this->response([
                'status'  => false,
                'message' => 'You do not have permission to delete purchase orders.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $scope = $this->get_purchase_order_access_scope();
        if ($scope === null) {
            return;
        }

        $order = $this->purchase_model->get_purchase_order_with_details($orderId);
        if (!$order) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        if (!$this->can_access_purchase_order($order, $scope)) {
            $this->response([
                'status'  => false,
                'message' => 'Purchase order not found or access denied.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        $deleted = $this->purchase_model->delete_pur_order($orderId);

        if ($deleted !== true) {
            $this->response([
                'status'  => false,
                'message' => 'Unable to delete purchase order.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }

        $this->response([
            'status'  => true,
            'message' => 'Purchase order deleted successfully.',
        ], self::HTTP_OK);
    }

    public function options_get($name = '')
    {
        $staff = $this->get_authenticated_staff();
        if (!$staff) {
            return;
        }

        $hasPermission = $this->staff_has_permission($staff, 'purchase_orders', 'view')
            || $this->staff_has_permission($staff, 'purchase_orders', 'view_own')
            || $this->staff_has_permission($staff, 'purchase_orders', 'create')
            || $this->staff_has_permission($staff, 'purchase_orders', 'edit');

        if (!$hasPermission) {
            $this->response([
                'status'  => false,
                'message' => 'You do not have permission to view purchase settings.',
            ], self::HTTP_FORBIDDEN);

            return;
        }

        if ($name === '') {
            $records = $this->db->select('option_name, option_val')
                ->from(db_prefix() . 'purchase_option')
                ->order_by('option_name', 'asc')
                ->get()
                ->result_array();

            $options = [];
            foreach ($records as $record) {
                $options[] = [
                    'name'  => $record['option_name'],
                    'value' => $record['option_val'],
                ];
            }

            $this->response([
                'status' => true,
                'result' => [
                    'options' => $options,
                ],
            ], self::HTTP_OK);

            return;
        }

        $optionName = urldecode($name);

        $option = $this->db->select('option_name, option_val')
            ->where('option_name', $optionName)
            ->get(db_prefix() . 'purchase_option')
            ->row();

        if (!$option) {
            $this->response([
                'status'  => false,
                'message' => 'Option not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'result' => [
                'name'  => $option->option_name,
                'value' => $option->option_val,
            ],
        ], self::HTTP_OK);
    }
    protected function get_authenticated_staff()
    {
        if ($this->authenticated_staff !== null) {
            return $this->authenticated_staff;
        }

        $validation = $this->authorization_token->validateToken();
        if (!isset($validation['status']) || $validation['status'] === false) {
            $this->response([
                'status'  => false,
                'message' => isset($validation['message']) ? $validation['message'] : 'Invalid token.',
            ], self::HTTP_UNAUTHORIZED);

            return null;
        }

        $token = $this->authorization_token->get_token();
        if (!is_string($token) || $token === '' || $token === 'Token is not defined.') {
            $this->response([
                'status'  => false,
                'message' => 'Token is not defined.',
            ], self::HTTP_UNAUTHORIZED);

            return null;
        }

        $staff = $this->db->where('token', $token)->get(db_prefix() . 'staff')->row();
        if (!$staff) {
            $this->response([
                'status'  => false,
                'message' => 'Authentication failed.',
            ], self::HTTP_UNAUTHORIZED);

            return null;
        }

        if (isset($staff->active) && (int) $staff->active !== 1) {
            $this->response([
                'status'  => false,
                'message' => 'User account is inactive.',
            ], self::HTTP_FORBIDDEN);

            return null;
        }

        $this->authenticated_staff = $staff;

        return $this->authenticated_staff;
    }

    protected function get_json_input()
    {
        $raw = $this->input->raw_input_stream;
        if ($raw === '' || $raw === null) {
            return [];
        }

        $payload = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $payload;
    }

    protected function respond_with_errors(array $errors, $status = self::HTTP_UNPROCESSABLE_ENTITY)
    {
        $this->response([
            'status'  => false,
            'message' => 'Validation failed.',
            'errors'  => $errors,
        ], $status);
    }

    protected function get_vendor_access_scope()
    {
        $staff = $this->get_authenticated_staff();
        if (!$staff) {
            return null;
        }

        $isAdmin     = isset($staff->admin) && (int) $staff->admin === 1;
        $canViewAll  = $isAdmin || $this->staff_has_permission($staff, 'purchase_vendors', 'view');
        $canViewOwn  = $isAdmin || $canViewAll || $this->staff_has_permission($staff, 'purchase_vendors', 'view_own');

        if (!$canViewAll && !$canViewOwn) {
            $this->response([
                'status'  => false,
                'message' => 'You do not have permission to view vendors.',
            ], self::HTTP_FORBIDDEN);

            return null;
        }

        return [
            'staff'        => $staff,
            'staff_id'     => (int) $staff->staffid,
            'is_admin'     => $isAdmin,
            'can_view_all' => $canViewAll,
            'can_view_own' => $canViewOwn,
        ];
    }

    protected function get_purchase_order_access_scope()
    {
        $staff = $this->get_authenticated_staff();
        if (!$staff) {
            return null;
        }

        $isAdmin     = isset($staff->admin) && (int) $staff->admin === 1;
        $canViewAll  = $isAdmin || $this->staff_has_permission($staff, 'purchase_orders', 'view');
        $canViewOwn  = $isAdmin || $canViewAll || $this->staff_has_permission($staff, 'purchase_orders', 'view_own');

        if (!$canViewAll && !$canViewOwn) {
            $this->response([
                'status'  => false,
                'message' => 'You do not have permission to view purchase orders.',
            ], self::HTTP_FORBIDDEN);

            return null;
        }

        return [
            'staff'        => $staff,
            'staff_id'     => (int) $staff->staffid,
            'is_admin'     => $isAdmin,
            'can_view_all' => $canViewAll,
            'can_view_own' => $canViewOwn,
        ];
    }

    protected function staff_has_permission($staff, $feature, $capability)
    {
        if (isset($staff->admin) && (int) $staff->admin === 1) {
            return true;
        }

        $staffId = (int) $staff->staffid;
        if (!isset($this->staff_permissions[$staffId])) {
            $this->staff_permissions[$staffId] = $this->staff_model->get_staff_permissions($staffId);
        }

        foreach ($this->staff_permissions[$staffId] as $permission) {
            if ($permission['feature'] === $feature && $permission['capability'] === $capability) {
                return true;
            }
        }

        return false;
    }

    protected function is_vendor_accessible_by_staff($vendorId, $staffId)
    {
        $this->db->where('vendor_id', $vendorId);
        $this->db->where('staff_id', $staffId);

        return $this->db->count_all_results(db_prefix() . 'pur_vendor_admin') > 0;
    }
    protected function format_vendor_record(array $row)
    {
        $category = [];
        if (isset($row['category']) && $row['category'] !== '' && !is_array($row['category'])) {
            $category = array_filter(array_map('trim', explode(',', $row['category'])));
            $category = array_map('intval', $category);
        } elseif (isset($row['category']) && is_array($row['category'])) {
            $category = array_map('intval', $row['category']);
        }

        $primaryContact = null;
        if (isset($row['primary_contact_id'])) {
            $primaryContact = [
                'id'         => (int) $row['primary_contact_id'],
                'firstname'  => $row['primary_contact_firstname'],
                'lastname'   => $row['primary_contact_lastname'],
                'email'      => $row['primary_contact_email'],
                'phonenumber'=> $row['primary_contact_phone'],
            ];
        }

        return [
            'id'               => isset($row['userid']) ? (int) $row['userid'] : (int) ($row['id'] ?? 0),
            'company'          => $row['company'] ?? '',
            'vendor_code'      => $row['vendor_code'] ?? null,
            'phonenumber'      => $row['phonenumber'] ?? null,
            'vat'              => $row['vat'] ?? null,
            'website'          => $row['website'] ?? null,
            'active'           => isset($row['active']) ? (int) $row['active'] : null,
            'datecreated'      => $row['datecreated'] ?? null,
            'addedfrom'        => isset($row['addedfrom']) ? (int) $row['addedfrom'] : null,
            'country'          => isset($row['country']) ? (int) $row['country'] : null,
            'city'             => $row['city'] ?? null,
            'state'            => $row['state'] ?? null,
            'zip'              => $row['zip'] ?? null,
            'address'          => $row['address'] ?? null,
            'category'         => $category,
            'primary_contact'  => $primaryContact,
        ];
    }

    protected function format_purchase_order_record(array $row)
    {
        return [
            'id'               => (int) $row['id'],
            'pur_order_name'   => $row['pur_order_name'] ?? null,
            'pur_order_number' => $row['pur_order_number'] ?? null,
            'vendor'           => [
                'id'   => isset($row['vendor']) ? (int) $row['vendor'] : null,
                'name' => $row['vendor_name'] ?? null,
                'code' => $row['vendor_code'] ?? null,
            ],
            'order_date'       => $row['order_date'] ?? null,
            'delivery_date'    => $row['delivery_date'] ?? null,
            'subtotal'         => isset($row['subtotal']) ? (float) $row['subtotal'] : 0.0,
            'total_tax'        => isset($row['total_tax']) ? (float) $row['total_tax'] : 0.0,
            'total'            => isset($row['total']) ? (float) $row['total'] : 0.0,
            'shipping_fee'     => isset($row['shipping_fee']) ? (float) $row['shipping_fee'] : 0.0,
            'approve_status'   => isset($row['approve_status']) ? (int) $row['approve_status'] : null,
            'status'           => isset($row['status']) ? (int) $row['status'] : null,
            'order_status'     => $row['order_status'] ?? null,
            'delivery_status'  => isset($row['delivery_status']) ? (int) $row['delivery_status'] : null,
            'addedfrom'        => isset($row['addedfrom']) ? (int) $row['addedfrom'] : null,
            'buyer'            => isset($row['buyer']) ? (int) $row['buyer'] : null,
            'currency'         => isset($row['currency']) ? (int) $row['currency'] : null,
            'datecreated'      => $row['datecreated'] ?? null,
        ];
    }

    protected function format_purchase_order_core(array $order)
    {
        $record = $this->format_purchase_order_record($order);
        $record['vendornote'] = $order['vendornote'] ?? null;
        $record['terms']      = $order['terms'] ?? null;
        $record['items']      = $order['items'] ?? [];

        return $record;
    }

    protected function format_purchase_order_detail(array $order)
    {
        $record = $this->format_purchase_order_core($order);
        $record['attachments'] = $order['attachments'] ?? [];

        if (!empty($order['payments']) && is_array($order['payments'])) {
            $record['payments'] = array_map([
                $this,
                'normalize_purchase_order_payment',
            ], $order['payments']);
        } else {
            $record['payments'] = [];
        }

        return $record;
    }

    protected function normalize_purchase_order_payment(array $payment)
    {
        $paymentMode = null;
        if (isset($payment['payment_mode'])) {
            $paymentMode = (int) $payment['payment_mode'];
        } elseif (isset($payment['paymentmethod'])) {
            $paymentMode = (int) $payment['paymentmethod'];
        } elseif (isset($payment['paymentmode'])) {
            $paymentMode = is_numeric($payment['paymentmode']) ? (int) $payment['paymentmode'] : $payment['paymentmode'];
        }

        return [
            'id'             => isset($payment['id']) ? (int) $payment['id'] : (isset($payment['payment_id']) ? (int) $payment['payment_id'] : 0),
            'order_id'       => isset($payment['pur_order']) ? (int) $payment['pur_order'] : (isset($payment['order_id']) ? (int) $payment['order_id'] : null),
            'invoice_id'     => isset($payment['pur_invoice']) ? (int) $payment['pur_invoice'] : null,
            'payment_number' => $payment['payment_number'] ?? ($payment['number'] ?? null),
            'amount'         => isset($payment['amount']) ? (float) $payment['amount'] : 0.0,
            'date'           => $payment['date'] ?? ($payment['date_payment'] ?? null),
            'payment_mode'   => $paymentMode,
            'transaction_id' => $payment['transaction_id'] ?? ($payment['transactionid'] ?? null),
            'note'           => $payment['note'] ?? ($payment['note_description'] ?? null),
            'created_by'     => isset($payment['addedfrom']) ? (int) $payment['addedfrom'] : (isset($payment['requester']) ? (int) $payment['requester'] : null),
            'date_created'   => $payment['datecreated'] ?? ($payment['created_at'] ?? ($payment['daterecorded'] ?? null)),
        ];
    }

    protected function resolve_purchase_order_include_sections($include)
    {
        if ($include === null) {
            return null;
        }

        $validSections = ['order', 'attachments', 'payments'];
        $candidates    = [];

        if (is_array($include)) {
            $candidates = $include;
        } else {
            $value = trim((string) $include);
            if ($value === '') {
                return [];
            }

            $candidates = explode(',', $value);
        }

        $sections = [];
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                continue;
            }

            $normalized = strtolower(trim((string) $candidate));
            if ($normalized === '') {
                continue;
            }

            if ($normalized === 'all') {
                return $validSections;
            }

            if (in_array($normalized, ['purchase_order', 'purchase-order', 'purchaseorder'], true)) {
                $normalized = 'order';
            } elseif ($normalized === 'attachment') {
                $normalized = 'attachments';
            } elseif ($normalized === 'payment') {
                $normalized = 'payments';
            }

            if (in_array($normalized, $validSections, true) && !in_array($normalized, $sections, true)) {
                $sections[] = $normalized;
            }
        }

        return $sections;
    }

    protected function can_access_purchase_order(array $order, array $scope)
    {
        if ($scope['is_admin'] || $scope['can_view_all']) {
            return true;
        }

        $staffId = $scope['staff_id'];
        if ((int) ($order['addedfrom'] ?? 0) === $staffId) {
            return true;
        }

        if ((int) ($order['buyer'] ?? 0) === $staffId) {
            return true;
        }

        if (isset($order['vendor']) && (int) $order['vendor'] > 0) {
            return $this->is_vendor_accessible_by_staff((int) $order['vendor'], $staffId);
        }

        return false;
    }

    protected function prepare_vendor_payload(array $payload, $staff, bool $isUpdate)
    {
        $vendorFields = [
            'company', 'vat', 'phonenumber', 'country', 'city', 'zip', 'state', 'address', 'website',
            'active', 'leadid', 'default_language', 'default_currency', 'show_primary_contact',
            'billing_street', 'billing_city', 'billing_state', 'billing_zip', 'billing_country',
            'shipping_street', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country',
            'longitude', 'latitude', 'bank_detail', 'payment_terms', 'vendor_code', 'category',
            'balance', 'balance_as_of', 'return_within_day', 'return_order_fee', 'return_policies',
        ];

        $data = [];
        foreach ($vendorFields as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if (isset($payload['category'])) {
            $categories = $payload['category'];
            if (is_string($categories)) {
                $categories = array_filter(array_map('trim', explode(',', $categories)));
            }
            if (is_array($categories)) {
                $data['category'] = array_map('intval', $categories);
            }
        }

        if (isset($payload['primary_contact']) && is_array($payload['primary_contact'])) {
            foreach ($this->vendor_contact_fields as $field) {
                if (array_key_exists($field, $payload['primary_contact'])) {
                    $data[$field] = $payload['primary_contact'][$field];
                }
            }
            if (!isset($payload['primary_contact']['is_primary'])) {
                $data['is_primary'] = 1;
            }
        }

        if (isset($payload['custom_fields']) && is_array($payload['custom_fields'])) {
            $data['custom_fields'] = ['vendors' => $payload['custom_fields']];
        }

        if (!$isUpdate) {
            $data['addedfrom'] = (int) $staff->staffid;
        }

        return $data;
    }

    protected function validate_vendor_payload(array $vendorData, bool $isUpdate)
    {
        $errors = [];

        if (!$isUpdate && (!isset($vendorData['company']) || trim((string) $vendorData['company']) === '')) {
            $errors['company'] = 'Company name is required.';
        }

        if (isset($vendorData['email']) && $vendorData['email'] !== '') {
            if (!filter_var($vendorData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Primary contact email is invalid.';
            }
        }

        return $errors;
    }
    protected function prepare_purchase_order_payload(array $payload, $staff, bool $isUpdate)
    {
        $errors = [];

        if (empty($payload['vendor'])) {
            $errors['vendor'] = 'Vendor is required.';
        }

        if (empty($payload['order_date'])) {
            $errors['order_date'] = 'Order date is required.';
        }

        if (!isset($payload['currency'])) {
            $errors['currency'] = 'Currency is required.';
        }

        $itemsData = $this->build_purchase_order_items($payload, $isUpdate);
        if (!empty($itemsData['errors'])) {
            $errors = array_merge($errors, $itemsData['errors']);
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $allowedFields = [
            'pur_order_name', 'vendor', 'estimate', 'pur_order_number', 'order_date', 'status', 'approve_status',
            'days_owed', 'delivery_date', 'subtotal', 'total_tax', 'total', 'vendornote', 'terms', 'discount_percent',
            'discount_total', 'discount_type', 'buyer', 'status_goods', 'department', 'project', 'type', 'pur_request',
            'tax_order_rate', 'tax_order_amount', 'currency', 'currency_rate', 'to_currency', 'from_currency',
            'shipping_fee', 'payment_terms', 'guarantee', 'warehouse_id', 'sale_estimate', 'without_checking_warehouse',
            'shipping_address', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country', 'shipping_country_text',
            'origin', 'color_id', 'style_id', 'model_id', 'size_id', 'order_status', 'shipping_note', 'vendor_invoice_number',
            'long_descriptions', 'series_id', 'profif_ratio', 'make_a_contract', 'compare_note',
        ];

        $data = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if (isset($payload['tags'])) {
            $data['tags'] = $payload['tags'];
        }

        if (isset($payload['clients'])) {
            $data['clients'] = $payload['clients'];
        }

        if (isset($payload['custom_fields'])) {
            $data['custom_fields'] = $payload['custom_fields'];
        }

        if (!empty($itemsData['newitems'])) {
            $data['newitems'] = $itemsData['newitems'];
        }

        if (!empty($itemsData['items'])) {
            $data['items'] = $itemsData['items'];
        }

        if (!empty($itemsData['removed_items'])) {
            $data['removed_items'] = $itemsData['removed_items'];
        }

        $totals = $itemsData['totals'];

        if (!isset($data['total_tax'])) {
            $data['total_tax'] = $this->round_money($totals['tax_total']);
        }

        if (!isset($data['dc_total'])) {
            $data['dc_total'] = isset($payload['dc_total']) ? $payload['dc_total'] : 0;
        }

        if (!isset($data['total_mn'])) {
            $data['total_mn'] = $this->round_money($totals['net_subtotal']);
        }

        if (!isset($data['grand_total'])) {
            $data['grand_total'] = $this->round_money($totals['grand_total']);
        }

        if (!isset($data['shipping_fee']) && isset($payload['shipping_fee'])) {
            $data['shipping_fee'] = $this->round_money($payload['shipping_fee']);
        }

        if (!isset($data['number'])) {
            $data['number'] = $this->resolve_next_purchase_order_number();
        }

        if (!isset($data['pur_order_number']) || $data['pur_order_number'] === '') {
            $vendorId = isset($data['vendor']) ? (int) $data['vendor'] : null;
            $data['pur_order_number'] = $this->build_purchase_order_number($vendorId, (int) $data['number']);
        }

        $data['addedfrom'] = (int) $staff->staffid;

        return ['data' => $data];
    }

    protected function build_purchase_order_items(array $payload, bool $isUpdate)
    {
        $errors       = [];
        $newitems     = [];
        $existing     = [];
        $removedItems = [];

        if (isset($payload['newitems']) && is_array($payload['newitems'])) {
            $newitems = $payload['newitems'];
        }

        if ($isUpdate && isset($payload['items']) && is_array($payload['items'])) {
            $existing = $payload['items'];
        }

        if (isset($payload['removed_items']) && is_array($payload['removed_items'])) {
            $removedItems = array_map('intval', $payload['removed_items']);
        } elseif (isset($payload['removed_item_ids']) && is_array($payload['removed_item_ids'])) {
            $removedItems = array_map('intval', $payload['removed_item_ids']);
        }

        $lineItems = [];
        if (isset($payload['line_items']) && is_array($payload['line_items'])) {
            $lineItems = $payload['line_items'];
        } elseif (!$isUpdate && empty($newitems) && isset($payload['items']) && is_array($payload['items'])) {
            $lineItems = $payload['items'];
        }

        if (!empty($lineItems)) {
            $transformed = $this->transform_line_items($lineItems, $isUpdate);
            $newitems    = array_merge($newitems, $transformed['newitems']);
            $existing    = array_merge($existing, $transformed['items']);
            $errors      = array_merge($errors, $transformed['errors']);
        }

        if (empty($newitems) && empty($existing) && empty($removedItems)) {
            $errors['items'] = 'At least one line item is required.';
        }

        $totals = $this->calculate_totals_from_items($newitems, $existing, $payload);

        return [
            'newitems'      => $newitems,
            'items'         => $existing,
            'removed_items' => $removedItems,
            'totals'        => $totals,
            'errors'        => $errors,
        ];
    }

    protected function transform_line_items(array $lineItems, bool $isUpdate)
    {
        $newitems  = [];
        $existing  = [];
        $errors    = [];

        foreach ($lineItems as $index => $item) {
            $quantity  = isset($item['quantity']) ? (float) $item['quantity'] : 0.0;
            $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.0;

            if ($quantity <= 0 || $unitPrice < 0) {
                $errors['items.' . $index] = 'Each line item must include a valid quantity and unit_price.';
                continue;
            }

            $lineSubtotal = $unitPrice * $quantity;
            $discountAmount = isset($item['discount_amount']) ? (float) $item['discount_amount'] : 0.0;
            $discountPercent = isset($item['discount_percent']) ? (float) $item['discount_percent'] : (isset($item['discount']) ? (float) $item['discount'] : 0.0);

            if ($discountAmount <= 0 && $discountPercent > 0) {
                $discountAmount = $lineSubtotal * $discountPercent / 100;
            } elseif ($discountAmount > 0 && $lineSubtotal > 0) {
                $discountPercent = ($discountAmount / $lineSubtotal) * 100;
            }

            if ($discountAmount > $lineSubtotal) {
                $discountAmount = $lineSubtotal;
            }

            $taxData           = $this->resolve_taxes($item['taxes'] ?? ($item['tax_ids'] ?? []));
            $lineAfterDiscount = $lineSubtotal - $discountAmount;
            $taxAmount         = $lineAfterDiscount * $taxData['total_rate'] / 100;
            $lineTotal         = $lineAfterDiscount + $taxAmount;

            $itemData = [
                'item_code'        => $item['item_code'] ?? ($item['sku'] ?? ''),
                'item_name'        => $item['item_name'] ?? ($item['name'] ?? ''),
                'item_description' => $item['item_description'] ?? ($item['description'] ?? ''),
                'unit_id'          => $item['unit_id'] ?? null,
                'unit_price'       => $this->round_money($unitPrice),
                'quantity'         => $this->round_quantity($quantity),
                'discount'         => $this->round_money($discountPercent),
                'discount_money'   => $this->round_money($discountAmount),
                'into_money'       => $this->round_money($lineSubtotal),
                'total'            => $this->round_money($lineTotal),
                'total_money'      => $this->round_money($lineTotal),
                'tax_value'        => $this->round_money($taxAmount),
                'tax_select'       => $taxData['tax_select'],
            ];

            if (isset($item['unit_name'])) {
                $itemData['unit_name'] = $item['unit_name'];
            }

            if ($isUpdate && isset($item['id'])) {
                $itemData['id'] = (int) $item['id'];
                $existing[]     = $itemData;
            } else {
                $newitems[] = $itemData;
            }
        }

        return [
            'newitems' => $newitems,
            'items'    => $existing,
            'errors'   => $errors,
        ];
    }

    protected function calculate_totals_from_items(array $newitems, array $existingItems, array $payload)
    {
        $subtotal       = 0.0;
        $discountTotal  = 0.0;
        $taxTotal       = 0.0;
        $grandTotal     = 0.0;

        $allItems = array_merge($newitems, $existingItems);
        foreach ($allItems as $item) {
            $subtotal      += isset($item['into_money']) ? (float) $item['into_money'] : 0.0;
            $discountTotal += isset($item['discount_money']) ? (float) $item['discount_money'] : 0.0;
            $taxTotal      += isset($item['tax_value']) ? (float) $item['tax_value'] : 0.0;

            if (isset($item['total_money'])) {
                $grandTotal += (float) $item['total_money'];
            } elseif (isset($item['total'])) {
                $grandTotal += (float) $item['total'];
            } elseif (isset($item['into_money'])) {
                $grandTotal += (float) $item['into_money'];
            }
        }

        $shipping = isset($payload['shipping_fee']) ? (float) $payload['shipping_fee'] : 0.0;
        $grandTotal += $shipping;

        return [
            'subtotal'      => $subtotal,
            'discount_total'=> $discountTotal,
            'net_subtotal'  => $subtotal - $discountTotal,
            'tax_total'     => $taxTotal,
            'grand_total'   => $grandTotal,
        ];
    }

    protected function resolve_taxes($taxes)
    {
        $result = [
            'tax_select' => [],
            'total_rate' => 0.0,
        ];

        if (!is_array($taxes)) {
            return $result;
        }

        foreach ($taxes as $tax) {
            $taxId   = null;
            $taxName = null;
            $taxRate = null;

            if (is_numeric($tax)) {
                $taxId = (int) $tax;
            } elseif (is_array($tax)) {
                if (isset($tax['id'])) {
                    $taxId = (int) $tax['id'];
                } elseif (isset($tax['tax_id'])) {
                    $taxId = (int) $tax['tax_id'];
                }
                if (isset($tax['name'])) {
                    $taxName = $tax['name'];
                }
                if (isset($tax['rate'])) {
                    $taxRate = (float) $tax['rate'];
                } elseif (isset($tax['taxrate'])) {
                    $taxRate = (float) $tax['taxrate'];
                }
            } elseif (is_string($tax) && strpos($tax, '|') !== false) {
                $parts = explode('|', $tax);
                if (count($parts) >= 2) {
                    $taxName = $parts[0];
                    $taxRate = (float) $parts[1];
                }
            }

            if ($taxId !== null && ($taxName === null || $taxRate === null)) {
                $taxRow = $this->db->where('id', $taxId)->get(db_prefix() . 'taxes')->row();
                if ($taxRow) {
                    $taxName = $taxRow->name;
                    $taxRate = (float) $taxRow->taxrate;
                }
            } elseif ($taxName !== null && $taxRate === null) {
                $taxRow = $this->db->where('name', $taxName)->get(db_prefix() . 'taxes')->row();
                if ($taxRow) {
                    $taxId  = $taxRow->id;
                    $taxRate = (float) $taxRow->taxrate;
                }
            }

            if ($taxName !== null && $taxRate !== null) {
                $result['tax_select'][] = $taxName . '|' . $this->round_money($taxRate);
                $result['total_rate']  += $taxRate;
            }
        }

        return $result;
    }

    protected function round_money($value, $precision = 2)
    {
        return round((float) $value, $precision);
    }

    protected function round_quantity($value)
    {
        return round((float) $value, 6);
    }

    protected function resolve_next_purchase_order_number()
    {
        $next = (int) get_purchase_option('next_po_number');
        if ($next <= 0) {
            $next = 1;
        }

        return $next;
    }

    protected function build_purchase_order_number($vendorId, $number)
    {
        $prefix   = get_purchase_option('pur_order_prefix');
        $prefix   = $prefix !== '' ? $prefix : 'PO';
        $poOnly   = (int) get_option('po_only_prefix_and_number') === 1;
        $formattedNumber = str_pad($number, 5, '0', STR_PAD_LEFT);

        if ($poOnly) {
            return $prefix . '-' . $formattedNumber;
        }

        return $prefix . '-' . $formattedNumber . '-' . date('dmY');
    }
}
