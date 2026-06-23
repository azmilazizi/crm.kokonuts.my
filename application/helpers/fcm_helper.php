<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Send a push notification to every registered manager-app device.
 *
 * Uses the FCM HTTP v1 API with a service-account JWT — no third-party library
 * required.  Silently no-ops if Firebase is not yet configured.
 *
 * @param string $title  Notification title
 * @param string $body   Notification body
 * @param array  $data   Key→value pairs attached to the notification (all cast to string)
 */
function send_shift_push_notification(string $title, string $body, array $data = []): void
{
    $CI = &get_instance();
    $CI->config->load('fcm', true);

    $project_id           = $CI->config->item('fcm_project_id', 'fcm');
    $service_account_path = $CI->config->item('fcm_service_account_path', 'fcm');

    if (empty($project_id) || $project_id === 'YOUR_FIREBASE_PROJECT_ID') return;
    if (!file_exists($service_account_path)) return;

    $sa = json_decode(file_get_contents($service_account_path), true);
    if (empty($sa['client_email']) || empty($sa['private_key'])) return;

    $tokens = $CI->db
        ->select('fcm_token')
        ->get(db_prefix() . 'pos_manager_fcm_tokens')
        ->result_array();

    if (empty($tokens)) return;

    $access_token = _fcm_get_access_token($sa);
    if (!$access_token) return;

    foreach ($tokens as $row) {
        _fcm_send_one($project_id, $access_token, $row['fcm_token'], $title, $body, $data);
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
    $resp = curl_exec($ch);
    curl_close($ch);

    $result = json_decode((string) $resp, true);
    return $result['access_token'] ?? null;
}

function _fcm_send_one(
    string $project_id,
    string $access_token,
    string $device_token,
    string $title,
    string $body,
    array  $data
): void {
    $payload = json_encode([
        'message' => [
            'token'        => $device_token,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => array_map('strval', $data),
            'webpush'      => [
                'notification' => [
                    'icon'               => '/icons/Icon-192.png',
                    'badge'              => '/icons/Icon-192.png',
                    'requireInteraction' => true,
                ],
            ],
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
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Token no longer valid — remove it so we don't keep trying
    if ($status === 404) {
        $CI = &get_instance();
        $CI->db->where('fcm_token', $device_token)
               ->delete(db_prefix() . 'pos_manager_fcm_tokens');
    }
}

function _b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
