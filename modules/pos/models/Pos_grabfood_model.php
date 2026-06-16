<?php defined('BASEPATH') or exit('No direct script access allowed');

class Pos_grabfood_model extends App_Model
{
    // GrabFood Partner API base URLs
    const API_BASE_PROD    = 'https://partner-api.grab.com/grabfood/';
    const API_BASE_SANDBOX = 'https://partner-api.grab.com/grabfood-sandbox/';
    const TOKEN_URL        = 'https://partner-api.grab.com/grabid/v1/oauth2/token';
    const TOKEN_URL_SBX    = 'https://partner-api.grab.com/grabid/v1/oauth2/token';

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public function get_settings($warehouse_id)
    {
        return $this->db
            ->where('warehouse_id', (int)$warehouse_id)
            ->get(db_prefix() . 'pos_grabfood_settings')
            ->row_array();
    }

    public function get_all_active_settings()
    {
        return $this->db
            ->where('active', 1)
            ->where('client_id IS NOT NULL AND client_id != ""', null, false)
            ->where('client_secret IS NOT NULL AND client_secret != ""', null, false)
            ->get(db_prefix() . 'pos_grabfood_settings')
            ->result_array();
    }

    public function save_settings($warehouse_id, $data)
    {
        $warehouse_id = (int)$warehouse_id;
        $payload = [
            'client_id'        => trim($data['client_id'] ?? ''),
            'client_secret'    => trim($data['client_secret'] ?? ''),
            'partner_id'       => trim($data['partner_id'] ?? ''),
            'grabfood_store_id' => trim($data['grabfood_store_id'] ?? ''),
            'environment'      => ($data['environment'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox',
            'active'           => isset($data['active']) ? (int)(bool)$data['active'] : 1,
        ];

        $exists = $this->db->where('warehouse_id', $warehouse_id)->count_all_results(db_prefix() . 'pos_grabfood_settings');

        if ($exists) {
            // Clear cached token when credentials change
            if (!empty($payload['client_id']) || !empty($payload['client_secret'])) {
                $payload['access_token']     = null;
                $payload['token_expires_at'] = null;
            }
            $this->db->where('warehouse_id', $warehouse_id)->update(db_prefix() . 'pos_grabfood_settings', $payload);
        } else {
            $payload['warehouse_id'] = $warehouse_id;
            $payload['created_at']   = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'pos_grabfood_settings', $payload);
        }

        return $this->db->affected_rows() > 0 || $this->db->insert_id() > 0;
    }

    // -------------------------------------------------------------------------
    // OAuth Token Management
    // -------------------------------------------------------------------------

    public function get_access_token($warehouse_id)
    {
        $settings = $this->get_settings($warehouse_id);
        if (!$settings || empty($settings['client_id']) || empty($settings['client_secret'])) {
            return ['success' => false, 'error' => 'GrabFood credentials not configured for this store.'];
        }

        // Return cached token if still valid (with 60-second buffer)
        if (
            !empty($settings['access_token']) &&
            !empty($settings['token_expires_at']) &&
            strtotime($settings['token_expires_at']) > (time() + 60)
        ) {
            return ['success' => true, 'token' => $settings['access_token'], 'settings' => $settings];
        }

        // Fetch a fresh token
        $token_url = 'https://partner-api.grab.com/grabid/v1/oauth2/token';

        $ch = curl_init($token_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'grant_type'    => 'client_credentials',
                'scope'         => 'food.partner_api',
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => 'Connection error: ' . $err];
        }

        $resp = json_decode($body, true);

        if ($code !== 200 || empty($resp['access_token'])) {
            $msg = $resp['error_description'] ?? ($resp['error'] ?? ('HTTP ' . $code));
            return ['success' => false, 'error' => 'Token request failed: ' . $msg];
        }

        $expires_in  = (int)($resp['expires_in'] ?? 3600);
        $expires_at  = date('Y-m-d H:i:s', time() + $expires_in);

        $this->db->where('warehouse_id', (int)$warehouse_id)->update(db_prefix() . 'pos_grabfood_settings', [
            'access_token'     => $resp['access_token'],
            'token_expires_at' => $expires_at,
        ]);

        $settings['access_token'] = $resp['access_token'];
        return ['success' => true, 'token' => $resp['access_token'], 'settings' => $settings];
    }

    // -------------------------------------------------------------------------
    // API Request
    // -------------------------------------------------------------------------

    private function _api_request($settings, $method, $path, $params = [], $body = null)
    {
        $base = ($settings['environment'] ?? 'sandbox') === 'production'
            ? self::API_BASE_PROD
            : self::API_BASE_SANDBOX;

        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $headers = [
            'Authorization: Bearer ' . $settings['access_token'],
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $resp_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            return ['success' => false, 'error' => 'Connection error: ' . $curl_err];
        }

        $data = json_decode($resp_body, true);

        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true, 'data' => $data, 'http_code' => $http_code];
        }

        $msg = $data['message'] ?? ($data['error'] ?? ('HTTP ' . $http_code));
        return ['success' => false, 'error' => $msg, 'http_code' => $http_code, 'raw' => $resp_body];
    }

    // -------------------------------------------------------------------------
    // Test Connection
    // -------------------------------------------------------------------------

    public function test_connection($warehouse_id)
    {
        $token_result = $this->get_access_token($warehouse_id);
        if (!$token_result['success']) {
            return $token_result;
        }

        $settings = $token_result['settings'];
        if (empty($settings['partner_id'])) {
            // Token obtained successfully, but no partner ID to validate further
            return ['success' => true, 'message' => 'Token obtained successfully. Add your Partner ID to enable order sync.'];
        }

        $result = $this->_api_request($settings, 'GET', 'partner/v1/store/status', [
            'merchantID' => $settings['partner_id'],
            'storeID'    => $settings['grabfood_store_id'],
        ]);

        if ($result['success']) {
            return ['success' => true, 'message' => 'Connection successful. Store is reachable.'];
        }

        // A 404 on store status just means store ID may differ — token itself is valid
        if (($result['http_code'] ?? 0) === 404) {
            return ['success' => true, 'message' => 'Token valid. Verify your Store ID in settings.'];
        }

        return ['success' => false, 'error' => $result['error']];
    }

    // -------------------------------------------------------------------------
    // Sync Orders
    // -------------------------------------------------------------------------

    public function sync_orders($warehouse_id, $date_from = null, $date_to = null)
    {
        $token_result = $this->get_access_token($warehouse_id);
        if (!$token_result['success']) {
            return ['success' => false, 'error' => $token_result['error']];
        }

        $settings = $token_result['settings'];

        if (empty($settings['partner_id'])) {
            return ['success' => false, 'error' => 'Partner ID not configured.'];
        }

        $date_from = $date_from ?: date('Y-m-d', strtotime('-30 days'));
        $date_to   = $date_to   ?: date('Y-m-d');

        $params = [
            'merchantID' => $settings['partner_id'],
            'fromDate'   => $date_from . 'T00:00:00Z',
            'toDate'     => $date_to   . 'T23:59:59Z',
        ];

        if (!empty($settings['grabfood_store_id'])) {
            $params['storeID'] = $settings['grabfood_store_id'];
        }

        $result = $this->_api_request($settings, 'GET', 'partner/v1/orders', $params);

        if (!$result['success']) {
            return ['success' => false, 'error' => 'Failed to fetch orders: ' . $result['error']];
        }

        $orders = $result['data']['orders'] ?? [];
        $synced = 0;
        $errors = [];

        foreach ($orders as $order) {
            try {
                $this->upsert_order($warehouse_id, $order);
                $synced++;
            } catch (Exception $e) {
                $errors[] = ($order['orderID'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        // Update last sync timestamp
        $this->db->where('warehouse_id', (int)$warehouse_id)->update(db_prefix() . 'pos_grabfood_settings', [
            'last_sync_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'success' => true,
            'synced'  => $synced,
            'total'   => count($orders),
            'errors'  => $errors,
        ];
    }

    // -------------------------------------------------------------------------
    // Upsert a single GrabFood order
    // -------------------------------------------------------------------------

    public function upsert_order($warehouse_id, array $order)
    {
        $gf_order_id  = $order['orderID'] ?? '';
        $short_ref     = $order['shortRef'] ?? $order['displayOrderNumber'] ?? '';
        $order_status  = $order['orderState'] ?? 'PLACED';
        $order_type    = $order['orderType']  ?? null;

        // GrabFood prices can come as cents (integer) or dollars (decimal)
        $price_details = $order['priceDetails'] ?? [];
        $subtotal    = $this->_parse_price($price_details['subtotal']    ?? 0);
        $discount    = $this->_parse_price($price_details['totalDiscount'] ?? 0);
        $delivery_fee= $this->_parse_price($price_details['deliveryFee'] ?? 0);
        $total       = $this->_parse_price($price_details['totalAmount'] ?? $price_details['total'] ?? 0);

        $eater    = $order['eater']        ?? [];
        $delivery = $order['deliveryInfo'] ?? [];

        $cust_name  = $eater['name']    ?? null;
        $cust_phone = $eater['phoneNo'] ?? null;
        $del_addr   = is_array($delivery) ? ($delivery['address'] ?? null) : null;

        $scheduled_at = !empty($order['scheduledAt'])
            ? date('Y-m-d H:i:s', strtotime($order['scheduledAt']))
            : null;

        $eta = !empty($order['estimatedPickUpTime'])
            ? date('Y-m-d H:i:s', strtotime($order['estimatedPickUpTime']))
            : null;

        $order_date = !empty($order['submittedAt'])
            ? date('Y-m-d H:i:s', strtotime($order['submittedAt']))
            : (!empty($order['createdAt']) ? date('Y-m-d H:i:s', strtotime($order['createdAt'])) : date('Y-m-d H:i:s'));

        // Upsert into pos_grabfood_orders
        $existing = $this->db
            ->where('grabfood_order_id', $gf_order_id)
            ->get(db_prefix() . 'pos_grabfood_orders')
            ->row_array();

        $gf_row = [
            'warehouse_id'          => (int)$warehouse_id,
            'order_short_ref'       => $short_ref,
            'order_status'          => $order_status,
            'order_type'            => $order_type,
            'subtotal'              => $subtotal,
            'discount'              => $discount,
            'delivery_fee'          => $delivery_fee,
            'total'                 => $total,
            'customer_name'         => $cust_name,
            'customer_phone'        => $cust_phone,
            'delivery_address'      => $del_addr,
            'scheduled_at'          => $scheduled_at,
            'estimated_pickup_time' => $eta,
            'raw_payload'           => json_encode($order),
            'synced_at'             => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->where('grabfood_order_id', $gf_order_id)->update(db_prefix() . 'pos_grabfood_orders', $gf_row);
            $gf_db_id = $existing['id'];
        } else {
            $gf_row['grabfood_order_id'] = $gf_order_id;
            $gf_row['created_at']        = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'pos_grabfood_orders', $gf_row);
            $gf_db_id = $this->db->insert_id();
        }

        // Sync order items
        $this->db->where('grabfood_order_id', $gf_order_id)->delete(db_prefix() . 'pos_grabfood_order_items');
        foreach ($order['items'] ?? [] as $item) {
            $item_unit_price  = $this->_parse_price($item['price'] ?? 0);
            $item_qty         = (int)($item['quantity'] ?? 1);
            $mods = [];
            foreach ($item['modifiers'] ?? [] as $m) {
                $mods[] = [
                    'name'     => $m['name'] ?? '',
                    'price'    => $this->_parse_price($m['price'] ?? 0),
                    'quantity' => (int)($m['quantity'] ?? 1),
                ];
            }
            $this->db->insert(db_prefix() . 'pos_grabfood_order_items', [
                'grabfood_order_id' => $gf_order_id,
                'item_id'           => $item['id'] ?? null,
                'item_name'         => $item['name'] ?? '',
                'quantity'          => $item_qty,
                'unit_price'        => $item_unit_price,
                'total_price'       => round($item_unit_price * $item_qty, 2),
                'modifiers_json'    => !empty($mods) ? json_encode($mods) : null,
                'notes'             => $item['comments'] ?? null,
            ]);
        }

        // Upsert into pos_receipts so the order appears in POS analytics
        $receipt_number = $short_ref ?: ('GF-' . strtoupper(substr($gf_order_id, 0, 12)));
        $dining_option  = $order_type; // DELIVERY / SELF_PICKUP

        $existing_receipt = $this->db
            ->where('source', 'GRABFOOD')
            ->where('receipt_number', $receipt_number)
            ->get(db_prefix() . 'pos_receipts')
            ->row_array();

        $is_cancelled = in_array(strtoupper($order_status), ['CANCELLED', 'DRIVER_NOT_FOUND', 'FAILED']);

        $receipt_row = [
            'receipt_type'   => 'SALE',
            'warehouse_id'   => (int)$warehouse_id,
            'source'         => 'GRABFOOD',
            'dining_option'  => $dining_option,
            'subtotal'       => $subtotal,
            'total_discount' => $discount,
            'total_tax'      => 0.00,
            'total_money'    => $total,
            'receipt_date'   => $order_date,
            'note'           => $cust_name ? 'Customer: ' . $cust_name : null,
            'cancelled_at'   => $is_cancelled ? ($existing_receipt['cancelled_at'] ?? $order_date) : null,
        ];

        if ($existing_receipt) {
            $this->db->where('id', $existing_receipt['id'])->update(db_prefix() . 'pos_receipts', $receipt_row);
            $receipt_id = $existing_receipt['id'];
        } else {
            $receipt_row['receipt_number'] = $receipt_number;
            $receipt_row['created_at']     = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'pos_receipts', $receipt_row);
            $receipt_id = $this->db->insert_id();

            // Sync line items to pos_receipt_line_items
            foreach ($order['items'] ?? [] as $item) {
                $item_unit_price = $this->_parse_price($item['price'] ?? 0);
                $item_qty        = (int)($item['quantity'] ?? 1);
                $this->db->insert(db_prefix() . 'pos_receipt_line_items', [
                    'receipt_id'   => $receipt_id,
                    'item_id'      => 0,
                    'item_name'    => $item['name'] ?? '',
                    'quantity'     => $item_qty,
                    'unit_price'   => $item_unit_price,
                    'gross_total'  => round($item_unit_price * $item_qty, 2),
                    'total_money'  => round($item_unit_price * $item_qty, 2),
                ]);
            }

            // Add a single "GrabFood" payment entry
            $this->db->insert(db_prefix() . 'pos_receipt_payments', [
                'receipt_id'      => $receipt_id,
                'payment_type_id' => 0,
                'payment_name'    => 'GrabFood',
                'type'            => 'GRABFOOD',
                'money_amount'    => $total,
                'payment_date'    => $order_date,
            ]);
        }

        // Keep receipt_id in sync
        $this->db->where('id', $gf_db_id)->update(db_prefix() . 'pos_grabfood_orders', [
            'receipt_id' => $receipt_id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Order Queries
    // -------------------------------------------------------------------------

    public function get_orders($filters = [])
    {
        $warehouse_id = $filters['warehouse_id'] ?? null;
        $date_from    = $filters['date_from']    ?? null;
        $date_to      = $filters['date_to']      ?? null;
        $status       = $filters['status']       ?? '';
        $search       = trim($filters['search']  ?? '');
        $page         = max(1, (int)($filters['page']  ?? 1));
        $limit        = max(10, min(100, (int)($filters['limit'] ?? 20)));
        $offset       = ($page - 1) * $limit;

        $this->db
            ->from(db_prefix() . 'pos_grabfood_orders g')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = g.warehouse_id', 'left');

        if ($warehouse_id) $this->db->where('g.warehouse_id', (int)$warehouse_id);
        if ($status)       $this->db->where('g.order_status', $status);
        if ($date_from)    $this->db->where('DATE(g.created_at) >=', $date_from);
        if ($date_to)      $this->db->where('DATE(g.created_at) <=', $date_to);
        if ($search)       $this->db->group_start()
            ->like('g.grabfood_order_id', $search, 'both')
            ->or_like('g.order_short_ref', $search, 'both')
            ->or_like('g.customer_name', $search, 'both')
            ->group_end();

        $total = $this->db->count_all_results('', false);

        $rows = $this->db
            ->select('g.*, w.warehouse_name')
            ->order_by('g.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();

        return [
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'page_count' => max(1, (int)ceil($total / $limit)),
        ];
    }

    public function get_order($id)
    {
        $order = $this->db
            ->select('g.*, w.warehouse_name')
            ->from(db_prefix() . 'pos_grabfood_orders g')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = g.warehouse_id', 'left')
            ->where('g.id', (int)$id)
            ->get()->row_array();

        if (!$order) return null;

        $order['items'] = $this->db
            ->where('grabfood_order_id', $order['grabfood_order_id'])
            ->get(db_prefix() . 'pos_grabfood_order_items')
            ->result_array();

        foreach ($order['items'] as &$item) {
            $item['modifiers'] = !empty($item['modifiers_json'])
                ? (json_decode($item['modifiers_json'], true) ?: [])
                : [];
        }

        // Receipt settings for receipt printing
        $order['receipt_settings'] = $this->db
            ->where('warehouse_id', $order['warehouse_id'])
            ->get(db_prefix() . 'pos_receipt_settings')
            ->row_array() ?: [];

        return $order;
    }

    // -------------------------------------------------------------------------
    // Order Status Actions
    // -------------------------------------------------------------------------

    public function accept_order($warehouse_id, $grabfood_order_id)
    {
        $token_result = $this->get_access_token($warehouse_id);
        if (!$token_result['success']) return $token_result;

        $settings = $token_result['settings'];
        $result   = $this->_api_request($settings, 'POST', 'partner/v1/order/accept', [], [
            'merchantID' => $settings['partner_id'],
            'orderID'    => $grabfood_order_id,
        ]);

        if ($result['success']) {
            $this->_update_local_status($grabfood_order_id, 'ACCEPTED');
            return ['success' => true];
        }
        return ['success' => false, 'error' => $result['error']];
    }

    public function cancel_order($warehouse_id, $grabfood_order_id, $reason = '')
    {
        $token_result = $this->get_access_token($warehouse_id);
        if (!$token_result['success']) return $token_result;

        $settings = $token_result['settings'];
        $result   = $this->_api_request($settings, 'PUT', 'partner/v1/order/cancel', [], [
            'merchantID'       => $settings['partner_id'],
            'orderID'          => $grabfood_order_id,
            'cancellationCode' => 300,
            'cancellationReason' => $reason ?: 'Cancelled by merchant',
        ]);

        if ($result['success']) {
            $this->_update_local_status($grabfood_order_id, 'CANCELLED');
            // Mark receipt as cancelled
            $gf = $this->db->where('grabfood_order_id', $grabfood_order_id)->get(db_prefix() . 'pos_grabfood_orders')->row_array();
            if ($gf && $gf['receipt_id']) {
                $this->db->where('id', $gf['receipt_id'])->update(db_prefix() . 'pos_receipts', [
                    'cancelled_at'        => date('Y-m-d H:i:s'),
                    'cancellation_reason' => $reason ?: 'Cancelled by merchant',
                ]);
            }
            return ['success' => true];
        }
        return ['success' => false, 'error' => $result['error']];
    }

    public function mark_order_ready($warehouse_id, $grabfood_order_id)
    {
        $token_result = $this->get_access_token($warehouse_id);
        if (!$token_result['success']) return $token_result;

        $settings = $token_result['settings'];
        $result   = $this->_api_request($settings, 'POST', 'partner/v1/order/ready', [], [
            'merchantID' => $settings['partner_id'],
            'orderID'    => $grabfood_order_id,
        ]);

        if ($result['success']) {
            $this->_update_local_status($grabfood_order_id, 'READY_FOR_PICKUP');
            return ['success' => true];
        }
        return ['success' => false, 'error' => $result['error']];
    }

    private function _update_local_status($grabfood_order_id, $status)
    {
        $this->db->where('grabfood_order_id', $grabfood_order_id)->update(db_prefix() . 'pos_grabfood_orders', [
            'order_status' => $status,
        ]);
    }

    // -------------------------------------------------------------------------
    // Analytics
    // -------------------------------------------------------------------------

    public function get_summary($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND g.warehouse_id = ' . (int)$warehouse_id : '';

        return $this->db->query("
            SELECT
                COUNT(CASE WHEN g.order_status NOT IN ('CANCELLED','FAILED') THEN 1 END) AS order_count,
                COALESCE(SUM(CASE WHEN g.order_status NOT IN ('CANCELLED','FAILED') THEN g.total ELSE 0 END), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN g.order_status = 'CANCELLED' THEN 1 END), 0) AS cancelled_count,
                COALESCE(AVG(CASE WHEN g.order_status NOT IN ('CANCELLED','FAILED') THEN g.total END), 0) AS avg_order_value
            FROM `" . db_prefix() . "pos_grabfood_orders` g
            WHERE g.created_at BETWEEN ? AND ? $wh
        ", [$from, $to])->row_array();
    }

    public function get_top_items($date_from, $date_to, $warehouse_id = null, $limit = 10)
    {
        $from = $date_from . ' 00:00:00';
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND g.warehouse_id = ' . (int)$warehouse_id : '';

        return $this->db->query("
            SELECT i.item_name,
                   SUM(i.quantity)    AS qty_sold,
                   SUM(i.total_price) AS revenue
            FROM `" . db_prefix() . "pos_grabfood_order_items` i
            JOIN `" . db_prefix() . "pos_grabfood_orders` g ON g.grabfood_order_id = i.grabfood_order_id
            WHERE g.order_status NOT IN ('CANCELLED','FAILED')
              AND g.created_at BETWEEN ? AND ? $wh
            GROUP BY i.item_name
            ORDER BY revenue DESC
            LIMIT " . (int)$limit
        , [$from, $to])->result_array();
    }

    public function get_daily_trend($date_from, $date_to, $warehouse_id = null)
    {
        $from = $date_from . ' 00:00:00';
        $to   = $date_to   . ' 23:59:59';
        $wh   = $warehouse_id ? 'AND warehouse_id = ' . (int)$warehouse_id : '';

        return $this->db->query("
            SELECT DATE(created_at) AS date,
                   COUNT(*) AS orders,
                   COALESCE(SUM(total), 0) AS revenue
            FROM `" . db_prefix() . "pos_grabfood_orders`
            WHERE order_status NOT IN ('CANCELLED','FAILED')
              AND created_at BETWEEN ? AND ? $wh
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", [$from, $to])->result_array();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function _parse_price($value)
    {
        // GrabFood API may return prices in cents (integers) or as decimals
        // Heuristic: if integer and > 100, treat as cents
        if (is_int($value) && $value > 100) {
            return round($value / 100, 2);
        }
        return round((float)$value, 2);
    }
}
