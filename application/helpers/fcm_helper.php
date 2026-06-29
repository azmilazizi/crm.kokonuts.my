<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Schedule a data-only FCM push to every manager-app device whose owner has
 * access to $warehouse_id.  Execution is deferred until after the HTTP
 * response is sent so it never blocks the shift API.
 *
 * @param int   $warehouse_id  The warehouse whose authorised staff receive the push.
 * @param array $data          String key→value pairs (FCM data payload).
 */
function dispatch_shift_fcm(int $warehouse_id, array $data): void
{
    register_shutdown_function('_do_send_shift_fcm', $warehouse_id, $data);
}

/**
 * Schedule a sale FCM push, respecting per-user warehouse notification prefs.
 * Staff with no pref row are treated as opted-in by default.
 * Admins without a pref row always receive the notification.
 */
function dispatch_sale_fcm(int $warehouse_id, array $data): void
{
    register_shutdown_function('_do_send_sale_fcm', $warehouse_id, $data);
}

function _do_send_sale_fcm(int $warehouse_id, array $data): void
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $CI = &get_instance();
    $CI->config->load('fcm', true);

    $project_id           = $CI->config->item('fcm_project_id', 'fcm');
    $service_account_path = $CI->config->item('fcm_service_account_path', 'fcm');

    if (empty($project_id) || $project_id === 'YOUR_FIREBASE_PROJECT_ID') {
        log_message('error', '[FCM-sale] fcm_project_id not configured');
        return;
    }
    if (!file_exists($service_account_path)) {
        log_message('error', '[FCM-sale] service account file not found: ' . $service_account_path);
        return;
    }

    $sa = json_decode(file_get_contents($service_account_path), true);
    if (empty($sa['client_email']) || empty($sa['private_key'])) {
        log_message('error', '[FCM-sale] service account JSON invalid');
        return;
    }

    // Candidates: all active staff with a token for this warehouse.
    // Admins (admin=1) are always candidates; non-admins must have warehouse access.
    $rows = $CI->db
        ->select('t.fcm_token, t.staff_id, s.admin')
        ->from(db_prefix() . 'pos_manager_fcm_tokens t')
        ->join(db_prefix() . 'staff s', 's.staffid = t.staff_id')
        ->where('s.active', 1)
        ->get()->result_array();

    // Resolve allowed non-admin staff_ids for this warehouse (same dual-access logic as shift FCM)
    $token_rows    = $CI->db->select('staff_id')->where('warehouse_id', $warehouse_id)->get(db_prefix() . 'pos_api_tokens')->result_array();
    $allowed_ids   = array_map('intval', array_column($token_rows, 'staff_id'));
    $staff_rows    = $CI->db->select('staffid, warehouse_ids')->where('active', 1)->where('admin', 0)->get(db_prefix() . 'staff')->result_array();
    foreach ($staff_rows as $s) {
        $wh_ids = array_map('intval', json_decode($s['warehouse_ids'] ?? '[]', true) ?: []);
        if (in_array($warehouse_id, $wh_ids, true)) {
            $allowed_ids[] = (int) $s['staffid'];
        }
    }
    $allowed_ids = array_unique($allowed_ids);

    // Load this warehouse's sale notif prefs into a staff_id → enabled map
    $prefs = $CI->db
        ->select('staff_id, enabled')
        ->where('warehouse_id', $warehouse_id)
        ->get(db_prefix() . 'pos_manager_sale_notif_prefs')
        ->result_array();
    $pref_map = [];
    foreach ($prefs as $p) {
        $pref_map[(int) $p['staff_id']] = (int) $p['enabled'];
    }

    // Filter to tokens that should receive this notification
    $send_tokens = [];
    foreach ($rows as $row) {
        $sid      = (int) $row['staff_id'];
        $is_admin = (int) $row['admin'] === 1;

        // Must have warehouse access (admin always does)
        if (!$is_admin && !in_array($sid, $allowed_ids, true)) {
            continue;
        }

        // Check pref: if a row exists, honour it; if no row exists, default to enabled
        if (array_key_exists($sid, $pref_map) && $pref_map[$sid] === 0) {
            continue;
        }

        $send_tokens[] = $row['fcm_token'];
    }

    log_message('info', '[FCM-sale] warehouse=' . $warehouse_id . ' candidates=' . count($rows) . ' sending=' . count($send_tokens));

    if (empty($send_tokens)) return;

    $access_token = _fcm_get_access_token($sa);
    if (!$access_token) {
        log_message('error', '[FCM-sale] failed to obtain Google access token');
        return;
    }

    $notification = [
        'title' => $data['warehouse_name'] ?? '',
        'body'  => 'RM ' . ($data['total'] ?? '0.00') . ' · ' . ($data['payment_method'] ?? ''),
    ];

    foreach ($send_tokens as $token) {
        _fcm_send_one($project_id, $access_token, $token, $data, $notification);
    }
}

function _do_send_shift_fcm(int $warehouse_id, array $data): void
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $CI = &get_instance();
    $CI->config->load('fcm', true);

    $project_id           = $CI->config->item('fcm_project_id', 'fcm');
    $service_account_path = $CI->config->item('fcm_service_account_path', 'fcm');

    if (empty($project_id) || $project_id === 'YOUR_FIREBASE_PROJECT_ID') {
        log_message('error', '[FCM] fcm_project_id not configured');
        return;
    }
    if (!file_exists($service_account_path)) {
        log_message('error', '[FCM] service account file not found: ' . $service_account_path);
        return;
    }

    $sa = json_decode(file_get_contents($service_account_path), true);
    if (empty($sa['client_email']) || empty($sa['private_key'])) {
        log_message('error', '[FCM] service account JSON invalid');
        return;
    }

    // Access via pos_api_tokens (POS cashier / Manager.php auth)
    $rows        = $CI->db->select('staff_id')->where('warehouse_id', $warehouse_id)->get(db_prefix() . 'pos_api_tokens')->result_array();
    $allowed_ids = array_map('intval', array_column($rows, 'staff_id'));

    // Access via tblstaff.warehouse_ids JSON (manager/Api.php auth)
    $staff_rows = $CI->db->select('staffid, warehouse_ids')->where('active', 1)->where('admin', 0)->get(db_prefix() . 'staff')->result_array();
    foreach ($staff_rows as $s) {
        $wh_ids = array_map('intval', json_decode($s['warehouse_ids'] ?? '[]', true) ?: []);
        if (in_array($warehouse_id, $wh_ids, true)) {
            $allowed_ids[] = (int) $s['staffid'];
        }
    }
    $allowed_ids = array_unique($allowed_ids);

    // FCM tokens: administrators (admin = 1) always included; non-admins only
    // when their staff_id appears in $allowed_ids from either access mechanism.
    $CI->db
        ->select('DISTINCT t.fcm_token', false)
        ->from(db_prefix() . 'pos_manager_fcm_tokens t')
        ->join(db_prefix() . 'staff s', 's.staffid = t.staff_id')
        ->where('s.active', 1)
        ->group_start()
            ->where('s.admin', 1);

    if (!empty($allowed_ids)) {
        $CI->db->or_where_in('t.staff_id', $allowed_ids);
    }

    $tokens = $CI->db->group_end()->get()->result_array();

    log_message('info', '[FCM] warehouse=' . $warehouse_id . ' allowed_ids=' . implode(',', $allowed_ids) . ' tokens_found=' . count($tokens));

    if (empty($tokens)) return;

    $access_token = _fcm_get_access_token($sa);
    if (!$access_token) {
        log_message('error', '[FCM] failed to obtain Google access token');
        return;
    }

    foreach ($tokens as $row) {
        _fcm_send_one($project_id, $access_token, $row['fcm_token'], $data);
    }
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

function _fcm_get_access_token(array $sa): ?string
{
    $now  = time();
    $head = _b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $clm  = _b64url(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $signing_input = "$head.$clm";
    $key           = openssl_pkey_get_private($sa['private_key']);
    if (!$key) return null;

    openssl_sign($signing_input, $sig, $key, OPENSSL_ALGO_SHA256);
    $jwt = "$signing_input." . _b64url($sig);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $result = json_decode((string) curl_exec($ch), true);
    return $result['access_token'] ?? null;
}

/**
 * Send an FCM v1 message. Pass $notification to show a visible alert;
 * omit it for data-only messages. Removes the token from the database
 * if FCM signals it is no longer valid (404 = not found, 410 = unregistered).
 */
function _fcm_send_one(
    string $project_id,
    string $access_token,
    string $device_token,
    array  $data,
    ?array $notification = null
): void {
    $message = [
        'token' => $device_token,
        'data'  => array_map('strval', $data),
    ];

    if ($notification !== null) {
        $message['notification'] = $notification;
    }

    $payload = json_encode(['message' => $message]);

    $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $access_token",
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($status === 404 || $status === 410) {
        $CI = &get_instance();
        $CI->db->where('fcm_token', $device_token)
               ->delete(db_prefix() . 'pos_manager_fcm_tokens');
    }
}

function _b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
