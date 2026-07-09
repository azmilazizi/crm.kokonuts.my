<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Whatsapp_webhook extends CI_Controller
{
    private $log_file;

    public function __construct()
    {
        parent::__construct();
        $this->log_file = FCPATH . 'uploads/wa_shoebox.log';
    }

    private function _log(string $msg): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        file_put_contents($this->log_file, $line, FILE_APPEND | LOCK_EX);
    }

    // Entry point — GET = Meta verification, POST = incoming message
    public function index()
    {
        $method = $this->input->server('REQUEST_METHOD');
        $this->_log("REQUEST {$method}");

        if ($method === 'GET') {
            $this->_verify();
            return;
        }

        if ($method === 'POST') {
            // Respond 200 immediately so Meta doesn't retry
            http_response_code(200);
            echo 'OK';
            if (ob_get_level()) ob_end_flush();
            flush();

            // Keep processing even if the HTTP connection closes
            ignore_user_abort(true);
            set_time_limit(120);

            $raw     = file_get_contents('php://input');
            $payload = json_decode($raw, true);
            if (!$payload) {
                $this->_log('FAIL: empty or invalid JSON payload');
                return;
            }

            if (!$this->_verify_signature($raw)) {
                $this->_log('FAIL: signature verification failed');
                return;
            }

            $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
            if (!$message) {
                $this->_log('INFO: no message in payload (status update or other event)');
                return;
            }

            $from = $message['from'];
            $type = $message['type'];
            $this->_log("MESSAGE from={$from} type={$type}");

            if ($type !== 'image') {
                $this->_reply($from, 'Send a receipt photo to create a purchase draft.');
                return;
            }

            $image_id = $message['image']['id'];
            $this->_log("IMAGE id={$image_id}");
            $image    = $this->_download_media($image_id);

            if (!$image) {
                $this->_log('FAIL: could not download media');
                $this->_reply($from, 'Could not download the image. Please try again.');
                return;
            }
            $this->_log('OK: image downloaded mime=' . $image['mime_type']);

            // Acknowledge immediately before the slow Gemini scan
            $this->_reply($from, "Got your receipt! Scanning it now — I'll message you once it's saved.");

            $extracted = $this->_scan_receipt($image['data'], $image['mime_type']);

            if (!$extracted) {
                $this->_log('FAIL: Gemini scan returned null');
                $this->_reply($from, 'Could not read the receipt. Please send a clearer photo.');
                return;
            }
            $this->_log('OK: Gemini extracted vendor=' . ($extracted['vendor_name'] ?? 'null') . ' total=' . ($extracted['grand_total'] ?? 'null'));

            $draft_id = $this->_create_draft($extracted, $from);

            if ($draft_id) {
                $vendor = $extracted['vendor_name'] ?? 'Unknown Vendor';
                $total  = number_format((float)($extracted['grand_total'] ?? 0), 2);
                $this->_log("OK: draft created id={$draft_id}");
                $this->_reply($from, "Done! Receipt saved to Purchase Drafts.\n\nVendor: {$vendor}\nTotal: RM {$total}\n\nReview it in the CRM under Purchase → Purchase Order Drafts.");
            } else {
                $this->_log('FAIL: create_draft returned null');
                $this->_reply($from, 'Receipt was scanned but could not be saved. Please try again.');
            }
        }
    }

    private function _verify(): void
    {
        $mode      = $this->input->get('hub_mode');
        $token     = $this->input->get('hub_verify_token');
        $challenge = $this->input->get('hub_challenge');

        if ($mode === 'subscribe' && $token === get_option('wa_verify_token')) {
            http_response_code(200);
            echo $challenge;
        } else {
            http_response_code(403);
            echo 'Forbidden';
        }
    }

    private function _verify_signature(string $raw): bool
    {
        $app_secret = get_option('wa_app_secret');
        if (!$app_secret) return true; // skip if not configured

        $header = $this->input->server('HTTP_X_HUB_SIGNATURE_256') ?? '';
        if (!$header) return false;

        $expected = 'sha256=' . hash_hmac('sha256', $raw, $app_secret);
        return hash_equals($expected, $header);
    }

    private function _download_media(string $media_id): ?array
    {
        $token = get_option('wa_access_token');

        // Step 1: resolve media URL from Meta
        $ch = curl_init("https://graph.facebook.com/v19.0/{$media_id}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $meta = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $url       = $meta['url']       ?? null;
        $mime_type = $meta['mime_type'] ?? 'image/jpeg';
        if (!$url) return null;

        // Step 2: download image bytes
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $bytes = curl_exec($ch);
        curl_close($ch);

        if (!$bytes) return null;

        return ['data' => base64_encode($bytes), 'mime_type' => $mime_type];
    }

    private function _scan_receipt(string $b64, string $mime): ?array
    {
        $api_key = get_option('gemini_api_key');
        if (!$api_key) {
            $this->_log('FAIL: gemini_api_key not set in CRM settings');
            return null;
        }

        $prompt = 'Extract receipt data and return ONLY valid JSON with these exact keys: '
                . '"vendor_name" (string or null), '
                . '"receipt_date" (YYYY-MM-DD or null), '
                . '"items_subtotal" (number before tax, or null), '
                . '"grand_total" (final total amount as number), '
                . '"items" (array of objects with keys: "description" string, "quantity" number, "subtotal" number, "total" number). '
                . 'Currency is Malaysian Ringgit. No explanation, no markdown fences, return raw JSON only.';

        $payload = [
            'contents'         => [[
                'role'  => 'user',
                'parts' => [
                    ['inlineData' => ['mimeType' => $mime, 'data' => $b64]],
                    ['text'       => $prompt],
                ],
            ]],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 1000],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($api_key);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 60,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $this->_log("FAIL: Gemini curl error: {$err}");
            return null;
        }

        $this->_log("Gemini HTTP {$code} response: " . substr($raw, 0, 500));

        $resp = json_decode($raw, true);
        $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) {
            $this->_log('FAIL: no text in Gemini response');
            return null;
        }

        // Strip markdown fences if Gemini wraps the JSON
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/m', '', $text);

        $decoded = json_decode(trim($text), true);
        if (!$decoded) {
            $this->_log('FAIL: Gemini returned non-JSON text: ' . substr($text, 0, 200));
        }

        return $decoded ?: null;
    }

    private function _create_draft(array $data, string $from_phone): ?string
    {
        $this->load->model('purchase/Purchase_order_drafts_model');

        $draft_id = app_generate_hash();
        $vendor   = $data['vendor_name'] ?? null;
        $date     = $data['receipt_date'] ?? date('Y-m-d');
        $subtotal = (float)($data['items_subtotal'] ?? $data['grand_total'] ?? 0);
        $grand    = (float)($data['grand_total'] ?? 0);
        $now      = date('Y-m-d H:i:s');

        $draft_data = [
            'id'             => $draft_id,
            'vendor_name'    => $vendor,
            'order_name'     => ($vendor ?? 'Receipt') . ' — ' . $date . ' (WA:' . $from_phone . ')',
            'order_date'     => $date,
            'items_subtotal' => $subtotal,
            'grand_total'    => $grand,
            'is_paid'        => 0,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            $items[] = [
                'description' => $item['description'] ?? '',
                'quantity'    => (float)($item['quantity'] ?? 1),
                'subtotal'    => (float)($item['subtotal'] ?? 0),
                'total'       => (float)($item['total'] ?? 0),
            ];
        }

        // Fallback: single summary line if no items extracted
        if (empty($items) && $grand > 0) {
            $items[] = [
                'description' => $vendor ?? 'Receipt total',
                'quantity'    => 1.0,
                'subtotal'    => $grand,
                'total'       => $grand,
            ];
        }

        $result = $this->Purchase_order_drafts_model->create_draft($draft_data, $items, []);
        return $result ? $draft_id : null;
    }

    private function _reply(string $to, string $message): void
    {
        $token    = get_option('wa_access_token');
        $phone_id = get_option('wa_phone_number_id');
        if (!$token || !$phone_id) return;

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ];

        $ch = curl_init("https://graph.facebook.com/v19.0/{$phone_id}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$token}",
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
