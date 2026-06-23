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

function _do_send_shift_fcm(int $warehouse_id, array $data): void
{
    // Close the HTTP connection first so the client is not waiting for FCM
    // round-trips (works with PHP-FPM; no-ops on mod_php).
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $CI = &get_instance();
    $CI->config->load('fcm', true);

    $project_id           = $CI->config->item('fcm_project_id', 'fcm');
    $service_account_path = $CI->config->item('fcm_service_account_path', 'fcm');

    if (empty($project_id) || $project_id === 'YOUR_FIREBASE_PROJECT_ID') return;
    if (!file_exists($service_account_path)) return;

    $sa = json_decode(file_get_contents($service_account_path), true);
    if (empty($sa['client_email']) || empty($sa['private_key'])) return;

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

    if (empty($tokens)) return;

    $access_token = _fcm_get_access_token($sa);
    if (!$access_token) return;

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
 * Send a single data-only FCM v1 message. Removes the token from the database
 * if FCM signals it is no longer valid (404 = not found, 410 = unregistered).
 */
function _fcm_send_one(
    string $project_id,
    string $access_token,
    string $device_token,
    array  $data
): void {
    $payload = json_encode([
        'message' => [
            'token' => $device_token,
            'data'  => array_map('strval', $data),
        ],
    ]);

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
