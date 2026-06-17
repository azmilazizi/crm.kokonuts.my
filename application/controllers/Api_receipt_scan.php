<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_receipt_scan extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    const EXTRACTION_PROMPT = 'You are a receipt/invoice data extraction assistant. Extract all information from this document and return ONLY valid JSON with this exact structure:
{
  "vendor": "string or null",
  "date": "YYYY-MM-DD or null",
  "receipt_number": "string or null",
  "currency": "3-letter ISO code e.g. MYR, USD or null",
  "items": [
    {
      "description": "string",
      "qty": number,
      "unit_price": number,
      "total": number
    }
  ],
  "subtotal": number or null,
  "tax": number or null,
  "grand_total": number,
  "payment_method": "string or null",
  "confidence": "high|medium|low"
}
Rules: All monetary values must be plain numbers (no currency symbols). If a field is not visible or unclear, use null. The items array must never be null — use [] if no line items are visible.';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('authorization_token');
        $this->load->model('expenses_model');
    }

    /**
     * POST api/v1/ai/receipt/scan
     *
     * Accepts either:
     *   - multipart/form-data with field "receipt" (image file)
     *   - application/json with field "image_base64" and "mime_type"
     *
     * Optional body fields:
     *   - mode: "extract" (default) or "save" — save also creates an expense record
     *   - category: expense category ID or name (required when mode=save)
     *   - currency_id: CRM currency ID (optional, for mode=save)
     *   - note: additional note appended to the expense (optional)
     */
    public function scan_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        if ($apiKey === '') {
            $this->response([
                'status'  => false,
                'message' => 'GEMINI_API_KEY is not configured on this server.',
            ], self::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $imageData = $this->resolveImageInput();
        if (isset($imageData['error'])) {
            $this->response([
                'status'  => false,
                'message' => $imageData['error'],
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        $extracted = $this->callGemini($apiKey, $imageData['base64'], $imageData['mime']);
        if (isset($extracted['error'])) {
            $this->response([
                'status'  => false,
                'message' => $extracted['error'],
            ], self::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $mode = strtolower(trim((string) ($this->post('mode') ?: 'extract')));

        if ($mode !== 'save') {
            $this->response([
                'status' => true,
                'result' => $extracted,
            ], self::HTTP_OK);
            return;
        }

        $saved = $this->saveAsExpense($extracted);
        if (isset($saved['error'])) {
            $this->response([
                'status'    => false,
                'message'   => $saved['error'],
                'extracted' => $extracted,
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        $this->response([
            'status'    => true,
            'result'    => $saved['expense'],
            'extracted' => $extracted,
        ], self::HTTP_OK);
    }

    private function resolveImageInput(): array
    {
        // Multipart file upload
        if (!empty($_FILES['receipt']['tmp_name'])) {
            $file = $_FILES['receipt'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                return ['error' => 'File upload failed with error code ' . $file['error'] . '.'];
            }

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
            $mime         = mime_content_type($file['tmp_name']);

            if (!in_array($mime, $allowedMimes, true)) {
                return ['error' => 'Unsupported file type. Allowed: JPEG, PNG, WEBP, GIF, PDF.'];
            }

            $maxBytes = 10 * 1024 * 1024; // 10 MB
            if ($file['size'] > $maxBytes) {
                return ['error' => 'File exceeds maximum allowed size of 10 MB.'];
            }

            return [
                'base64' => base64_encode(file_get_contents($file['tmp_name'])),
                'mime'   => $mime,
            ];
        }

        // JSON body with base64
        $raw     = $this->input->raw_input_stream;
        $payload = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && !empty($payload['image_base64'])) {
            $mime = $payload['mime_type'] ?? 'image/jpeg';
            return [
                'base64' => $payload['image_base64'],
                'mime'   => $mime,
            ];
        }

        return ['error' => 'No receipt image provided. Send a file via "receipt" form field or "image_base64" in JSON body.'];
    }

    private function callGemini(string $apiKey, string $base64, string $mime): array
    {
        $payload = [
            'contents'         => [[
                'parts' => [
                    ['text' => self::EXTRACTION_PROMPT],
                    ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                ],
            ]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature'        => 0,
            ],
        ];

        $ch = curl_init(self::GEMINI_API_URL . '?key=' . urlencode($apiKey));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            return ['error' => 'AI API request failed: ' . $curlErr];
        }

        $response = json_decode($raw, true);

        if ($httpCode !== 200 || empty($response['candidates'][0]['content']['parts'][0]['text'])) {
            $detail = $response['error']['message'] ?? 'Unexpected response from AI API.';
            return ['error' => $detail];
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'];
        // Strip markdown code fences if present
        $text      = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text      = preg_replace('/\s*```$/i', '', $text);
        $extracted = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse AI response as JSON.'];
        }

        return $extracted;
    }

    private function saveAsExpense(array $extracted): array
    {
        $categorySource = $this->post('category');
        if ($categorySource === null || $categorySource === '') {
            return ['error' => 'Field "category" is required when mode=save.'];
        }

        $grandTotal = $extracted['grand_total'] ?? null;
        if ($grandTotal === null || !is_numeric($grandTotal)) {
            return ['error' => 'Could not determine grand total from the receipt. Please extract only and enter manually.'];
        }

        $date = $extracted['date'] ?? date('Y-m-d');
        if ($date === null) {
            $date = date('Y-m-d');
        }

        // Resolve category
        if (is_numeric($categorySource)) {
            $categoryId = (int) $categorySource;
        } else {
            $this->db->where('name', $categorySource);
            $cat = $this->db->get(db_prefix() . 'expenses_categories')->row();
            if (!$cat) {
                return ['error' => 'Category "' . $categorySource . '" not found.'];
            }
            $categoryId = (int) $cat->id;
        }

        $vendor       = $extracted['vendor'] ?? null;
        $receiptNum   = $extracted['receipt_number'] ?? null;
        $extraNote    = $this->post('note');

        $lineItemSummary = '';
        if (!empty($extracted['items'])) {
            $lines = [];
            foreach ($extracted['items'] as $item) {
                $desc  = $item['description'] ?? 'Item';
                $qty   = $item['qty'] ?? 1;
                $price = $item['unit_price'] ?? 0;
                $lines[] = "{$desc} x{$qty} @ {$price}";
            }
            $lineItemSummary = implode("\n", $lines);
        }

        $note = trim(implode("\n", array_filter([
            'Scanned from receipt.',
            $lineItemSummary,
            $extraNote,
        ])));

        $data = [
            'date'         => $date,
            'amount'       => (float) $grandTotal,
            'category'     => $categoryId,
            'expense_name' => $vendor ?: 'Receipt',
            'note'         => $note,
        ];

        if ($receiptNum !== null && $receiptNum !== '') {
            $data['reference_no'] = (string) $receiptNum;
        }

        $currencyId = $this->post('currency_id');
        if ($currencyId !== null && is_numeric($currencyId)) {
            $data['currency'] = (int) $currencyId;
        }

        $expenseId = $this->expenses_model->add($data);

        if (!$expenseId) {
            return ['error' => 'Failed to create expense record.'];
        }

        if (!function_exists('acc_automatic_expense_conversion')) {
            $this->load->model('accounting/accounting_model');
            if (method_exists($this->accounting_model, 'automatic_expense_conversion')) {
                $this->accounting_model->automatic_expense_conversion($expenseId);
            }
        }

        $expense = $this->expenses_model->get($expenseId);

        return ['expense' => $expense];
    }

    private function ensureAuthenticated(): bool
    {
        if ($this->tokenPayload !== null) {
            return true;
        }

        $tokenData = $this->authenticate_token();

        if ($tokenData === false) {
            return false;
        }

        $tokenString = $this->authorization_token->get_token();

        if (!empty($tokenString) && $tokenString !== 'Token is not defined.') {
            $staff = $this->db->where('token', $tokenString)->get(db_prefix() . 'staff')->row();

            if ($staff) {
                $this->session->set_userdata([
                    'staff_logged_in' => true,
                    'staff_user_id'   => $staff->staffid,
                ]);

                $GLOBALS['current_user'] = $staff;
            }
        }

        $this->tokenPayload = isset($tokenData['data']) ? $tokenData['data'] : $tokenData;

        return true;
    }
}
