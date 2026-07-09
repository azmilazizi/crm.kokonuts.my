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

            if ($type === 'image') {
                $this->_handle_image($from, $message);
            } elseif ($type === 'text') {
                $this->_handle_text($from, $message);
            } else {
                $this->_reply($from, "Please send a receipt photo (image) to get started.");
            }
        }
    }

    // -------------------------------------------------------------------------
    // Image handler: download, store session, ask type
    // -------------------------------------------------------------------------

    private function _handle_image(string $from, array $message): void
    {
        $image_id = $message['image']['id'];
        $this->_log("IMAGE id={$image_id}");

        $image = $this->_download_media($image_id);
        if (!$image) {
            $this->_log('FAIL: could not download media');
            $this->_reply($from, 'Could not download the image. Please try again.');
            return;
        }
        $this->_log('OK: image downloaded mime=' . $image['mime_type']);

        // Store session so we can process after user picks type
        $this->_save_session($from, $image['data'], $image['mime_type']);

        $this->_reply($from,
            "Got your receipt! What type of record should I create?\n\n" .
            "1️⃣ Purchase Order\n" .
            "2️⃣ Expense\n" .
            "3️⃣ Bill\n\n" .
            "Reply with 1, 2, or 3."
        );
    }

    // -------------------------------------------------------------------------
    // Text handler: check for pending session and route to draft creation
    // -------------------------------------------------------------------------

    private function _handle_text(string $from, array $message): void
    {
        $text    = trim($message['text']['body'] ?? '');
        $session = $this->_get_session($from);

        if (!$session) {
            $this->_reply($from, "Send a receipt photo to get started.");
            return;
        }

        $choice = $this->_parse_type_choice($text);

        if (!$choice) {
            $this->_reply($from,
                "Please reply with:\n1 – Purchase Order\n2 – Expense\n3 – Bill"
            );
            return;
        }

        // Acknowledge so user knows something is happening
        $this->_reply($from, "Got it! Scanning your receipt now...");

        $extracted = $this->_scan_receipt($session['image_data'], $session['image_mime']);
        $this->_delete_session($from);

        if (!$extracted) {
            $this->_log('Gemini scan returned null — saving bare draft');
            $extracted = [];
        } else {
            $this->_log('OK: Gemini extracted vendor=' . ($extracted['vendor'] ?? 'null') . ' total=' . ($extracted['grand_total'] ?? 'null'));
        }

        $image = ['data' => $session['image_data'], 'mime_type' => $session['image_mime']];

        try {
            if ($choice === 'purchase_order') {
                $result = $this->_create_po_draft($extracted, $from, $image);
            } elseif ($choice === 'expense') {
                $result = $this->_create_expense_draft($extracted, $from, $image);
            } else {
                $result = $this->_create_bill_draft($extracted, $from, $image);
            }
        } catch (Throwable $e) {
            $this->_log('EXCEPTION in draft creation: ' . $e->getMessage());
            $this->_reply($from, 'Draft could not be saved due to a server error. Please contact support.');
            return;
        }

        if ($result) {
            $this->_reply($from, $result);
        } else {
            $this->_reply($from, 'Could not save the draft. Please try again.');
        }
    }

    // -------------------------------------------------------------------------
    // Draft creators
    // -------------------------------------------------------------------------

    private function _create_po_draft(array $data, string $from_phone, array $image): ?string
    {
        if (!defined('PURCHASE_MODULE_UPLOAD_FOLDER')) {
            define('PURCHASE_MODULE_UPLOAD_FOLDER', module_dir_path('purchase', 'uploads'));
        }
        $this->load->model('purchase/purchase_order_drafts_model', 'purchase_order_drafts_model');

        $draft_id = app_generate_hash();
        $vendor   = $data['vendor'] ?? null;
        $date     = $data['date'] ?? date('Y-m-d');
        $grand    = (float)($data['grand_total'] ?? 0);
        $tax      = (float)($data['tax'] ?? 0);
        $now      = date('Y-m-d H:i:s');

        $descs = array_filter(array_map(function($it) { return $it['description'] ?? ''; }, $data['items'] ?? []));
        if (!empty($descs)) {
            $order_name = implode(', ', array_map(function($d) {
                return ucwords(strtolower($d));
            }, $descs));
        } elseif ($vendor) {
            $order_name = $vendor;
        } else {
            $order_name = 'Receipt ' . ($data['receipt_number'] ?? $from_phone);
        }

        $items_subtotal = 0;
        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            $qty        = (float)($item['qty'] ?? 1);
            $unit_price = (float)($item['unit_price'] ?? 0);
            $subtotal   = round($qty * $unit_price, 2);
            $items_subtotal += $subtotal;
            $items[] = [
                'description' => $item['description'] ?? '',
                'quantity'    => $qty,
                'subtotal'    => $subtotal,
            ];
        }

        if (empty($items) && $grand > 0) {
            $items[] = [
                'description' => $vendor ?? 'Receipt total',
                'quantity'    => 1.0,
                'subtotal'    => $grand,
            ];
            $items_subtotal = $grand;
        }

        $draft_data = [
            'id'             => $draft_id,
            'vendor_name'    => $vendor,
            'order_name'     => $order_name,
            'order_date'     => $date,
            'shipping_fee'   => $tax,
            'items_subtotal' => $items_subtotal,
            'grand_total'    => $grand ?: ($items_subtotal + $tax),
            'is_paid'        => 0,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $result = $this->purchase_order_drafts_model->create_draft($draft_data, $items, []);
        if (!$result) {
            return null;
        }

        $this->_attach_image_to_po_draft($draft_id, $image);

        $total = $grand ? 'RM ' . number_format($grand, 2) : null;
        $scan_param = strtr(base64_encode(json_encode([
            'confidence'     => $data['confidence'] ?? 'medium',
            'vendor'         => $vendor,
            'receipt_number' => $data['receipt_number'] ?? null,
        ])), '+/', '-_');
        $crm_link = base_url('admin/purchase/pur_order_draft_form/' . $draft_id . '?s=' . $scan_param);

        if ($vendor && $total) {
            return "Done! Saved as a *Purchase Order Draft*.\n\nVendor: {$vendor}\nTotal: {$total}\n\nReview in CRM:\n{$crm_link}";
        }
        return "Purchase Order Draft saved! Some details could not be read.\n\nReview in CRM:\n{$crm_link}";
    }

    private function _create_expense_draft(array $data, string $from_phone, array $image): ?string
    {
        $vendor = $data['vendor'] ?? null;
        $grand  = (float)($data['grand_total'] ?? 0);
        $date   = $data['date'] ?? date('Y-m-d');
        $now    = date('Y-m-d H:i:s');

        $items = $data['items'] ?? [];
        $note  = '';
        if (!empty($items)) {
            $lines = [];
            foreach ($items as $item) {
                $lines[] = ($item['description'] ?? 'Item') . ' x' . ($item['qty'] ?? 1);
            }
            $note = implode(', ', $lines);
        }

        $this->db->insert(db_prefix() . 'expenses', [
            'expense_name'            => $vendor ?: 'Receipt',
            'category'                => 0,
            'amount'                  => $grand,
            'date'                    => $date,
            'note'                    => nl2br($note),
            'addedfrom'               => 0,
            'dateadded'               => $now,
            'billable'                => 0,
            'clientid'                => 0,
            'send_invoice_to_customer' => 0,
            'currency'                => 0,
            'recurring'               => 0,
            'custom_recurring'        => 0,
            'is_draft'                => 1,
        ]);
        $expense_id = $this->db->insert_id();

        if (!$expense_id) {
            return null;
        }

        $this->_attach_image_to_expense($expense_id, $image);

        $total    = $grand ? 'RM ' . number_format($grand, 2) : null;
        $crm_link = base_url('admin/purchase/wa_expense_draft_form/' . $expense_id);

        if ($vendor && $total) {
            return "Done! Saved as an *Expense Draft*.\n\nVendor: {$vendor}\nTotal: {$total}\n\nReview in CRM:\n{$crm_link}";
        }
        return "Expense Draft saved! Select a category to complete it.\n\nReview in CRM:\n{$crm_link}";
    }

    private function _create_bill_draft(array $data, string $from_phone, array $image): ?string
    {
        $vendor           = $data['vendor'] ?? null;
        $grand            = (float)($data['grand_total'] ?? 0);
        $date             = $data['date'] ?? date('Y-m-d');
        $due_date         = $data['due_date'] ?? null;
        $now              = date('Y-m-d H:i:s');
        $bill_category_id = $this->_match_bill_category($data);

        $this->db->insert(db_prefix() . 'expenses', [
            'is_bill'                 => 1,
            'is_draft'                => 1,
            'expense_name'            => $vendor ?: 'Bill',
            'vendor'                  => 0,
            'date'                    => $date,
            'due_date'                => $due_date,
            'amount'                  => $grand,
            'bill_category_id'        => $bill_category_id ?: null,
            'reference_no'            => $data['receipt_number'] ?? null,
            'note'                    => '',
            'addedfrom'               => 0,
            'dateadded'               => $now,
            'status'                  => 0,
            'billable'                => 0,
            'clientid'                => 0,
            'send_invoice_to_customer' => 0,
            'currency'                => 0,
            'recurring'               => 0,
            'custom_recurring'        => 0,
        ]);
        $bill_id = $this->db->insert_id();

        if (!$bill_id) {
            return null;
        }

        $this->_attach_image_to_bill($bill_id, $image);

        $total    = $grand ? 'RM ' . number_format($grand, 2) : null;
        $crm_link = base_url('admin/purchase/wa_bill_draft_form/' . $bill_id);

        if ($vendor && $total) {
            return "Done! Saved as a *Bill Draft*.\n\nVendor: {$vendor}\nTotal: {$total}\n\nReview in CRM:\n{$crm_link}";
        }
        return "Bill Draft saved! Fill in the bill category to complete it.\n\nReview in CRM:\n{$crm_link}";
    }

    private function _match_bill_category(array $extracted): int
    {
        $api_key = get_option('gemini_api_key');
        if (!$api_key) {
            return 0;
        }

        $categories = $this->db
            ->select('id, name, description')
            ->where('active', 1)
            ->get(db_prefix() . 'acc_bill_categories')
            ->result_array();

        if (empty($categories)) {
            return 0;
        }

        $prompt = 'You are a CRM data-matching assistant. Given an extracted receipt and the available bill categories, '
                . 'pick the category that best fits the nature of this bill based on the vendor name and item descriptions '
                . '(e.g. electricity/utility bills → Utilities; stationery → Office Supplies). '
                . 'Return ONLY a JSON object: {"bill_category_id": <number or null>}. No other text.'
                . "\n\nExtracted receipt:\n" . json_encode([
                    'vendor' => $extracted['vendor'] ?? null,
                    'items'  => $extracted['items']  ?? [],
                ])
                . "\n\nAvailable bill categories:\n" . json_encode($categories);

        $payload = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature'        => 0,
                'thinkingConfig'     => ['thinkingBudget' => 0],
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($api_key);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$raw) {
            $this->_log('FAIL: _match_bill_category Gemini HTTP ' . $code);
            return 0;
        }

        $resp = json_decode($raw, true);
        $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) {
            return 0;
        }

        $text  = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text  = preg_replace('/\s*```$/m', '', $text);
        $match = json_decode(trim($text), true);

        if (!is_array($match) || empty($match['bill_category_id'])) {
            return 0;
        }

        $this->_log('OK: matched bill_category_id=' . (int)$match['bill_category_id']);
        return (int) $match['bill_category_id'];
    }

    // -------------------------------------------------------------------------
    // Session management (wa_pending_sessions)
    // -------------------------------------------------------------------------

    private function _save_session(string $phone, string $image_data, string $mime): void
    {
        // Clean up stale sessions older than 30 minutes
        $this->db->where('created_at <', date('Y-m-d H:i:s', strtotime('-30 minutes')));
        $this->db->delete(db_prefix() . 'wa_pending_sessions');

        $this->db->replace(db_prefix() . 'wa_pending_sessions', [
            'phone'      => $phone,
            'image_data' => $image_data,
            'image_mime' => $mime,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function _get_session(string $phone): ?array
    {
        $row = $this->db
            ->where('phone', $phone)
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-30 minutes')))
            ->get(db_prefix() . 'wa_pending_sessions')
            ->row_array();
        return $row ?: null;
    }

    private function _delete_session(string $phone): void
    {
        $this->db->where('phone', $phone)->delete(db_prefix() . 'wa_pending_sessions');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _parse_type_choice(string $text): ?string
    {
        $t = strtolower(trim($text));

        if (in_array($t, ['1', 'po', 'purchase', 'purchase order'], true)) {
            return 'purchase_order';
        }
        if (in_array($t, ['2', 'expense', 'expenses'], true)) {
            return 'expense';
        }
        if (in_array($t, ['3', 'bill', 'bills'], true)) {
            return 'bill';
        }
        return null;
    }

    private function _attach_image_to_po_draft(string $draft_id, array $image): void
    {
        if (empty($image['data'])) return;
        $ext      = strstr($image['mime_type'], 'png') ? 'png' : 'jpg';
        $tmp_path = tempnam(sys_get_temp_dir(), 'wa_receipt_') . '.' . $ext;
        file_put_contents($tmp_path, base64_decode($image['data']));
        $this->purchase_order_drafts_model->add_attachment_from_upload($draft_id, [
            'tmp_name' => $tmp_path,
            'name'     => 'whatsapp_receipt.' . $ext,
            'size'     => filesize($tmp_path),
        ]);
        @unlink($tmp_path);
    }

    private function _attach_image_to_expense(int $expense_id, array $image): void
    {
        if (empty($image['data'])) return;
        $ext  = strstr($image['mime_type'], 'png') ? 'png' : 'jpg';
        $blob = base64_decode($image['data']);
        $this->db->insert(db_prefix() . 'wa_expense_attachments', [
            'id'         => app_generate_hash(),
            'expense_id' => $expense_id,
            'file_name'  => 'whatsapp_receipt.' . $ext,
            'size_bytes' => strlen($blob),
            'local_blob' => $blob,
        ]);
    }

    private function _attach_image_to_bill(int $bill_id, array $image): void
    {
        if (empty($image['data'])) return;
        $ext  = strstr($image['mime_type'], 'png') ? 'png' : 'jpg';
        $blob = base64_decode($image['data']);
        $this->db->insert(db_prefix() . 'wa_bill_attachments', [
            'id'        => app_generate_hash(),
            'bill_id'   => $bill_id,
            'file_name' => 'whatsapp_receipt.' . $ext,
            'size_bytes' => strlen($blob),
            'local_blob' => $blob,
        ]);
    }

    // -------------------------------------------------------------------------

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
        if (!$app_secret) return true;

        $header = $this->input->server('HTTP_X_HUB_SIGNATURE_256') ?? '';
        if (!$header) return false;

        $expected = 'sha256=' . hash_hmac('sha256', $raw, $app_secret);
        return hash_equals($expected, $header);
    }

    private function _download_media(string $media_id): ?array
    {
        $token = get_option('wa_access_token');

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

        $prompt = 'Extract receipt data and return ONLY valid JSON with this exact structure: '
                . '{"vendor":string_or_null,"date":"YYYY-MM-DD or null","receipt_number":string_or_null,'
                . '"due_date":"YYYY-MM-DD or null","payment_terms":string_or_null,'
                . '"tax":number_or_null,"grand_total":number,"confidence":"high|medium|low",'
                . '"items":[{"description":string,"qty":number,"unit_price":number}]}. '
                . 'Currency is Malaysian Ringgit. All monetary values as plain numbers. '
                . 'confidence=low if you are unsure about vendor, amounts, or item details. '
                . 'No explanation, no markdown fences, return raw JSON only.';

        $payload = [
            'contents'         => [[
                'role'  => 'user',
                'parts' => [
                    ['inlineData' => ['mimeType' => $mime, 'data' => $b64]],
                    ['text'       => $prompt],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => 0.1,
                'maxOutputTokens' => 4096,
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
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

        $text    = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text    = preg_replace('/\s*```$/m', '', $text);
        $decoded = json_decode(trim($text), true);

        if (!$decoded) {
            $this->_log('FAIL: Gemini returned non-JSON text: ' . substr($text, 0, 200));
        }

        return $decoded ?: null;
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
