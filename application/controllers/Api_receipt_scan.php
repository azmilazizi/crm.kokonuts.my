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
     *   - application/json with fields "image_base64" and "mime_type"
     *
     * Body fields:
     *   - mode: "extract" (default) | "save" — save creates the record
     *   - record_type: "purchase_invoice" (default) | "expense" — which record to create
     *   - vendor_id: (int) override auto-lookup with a specific vendor ID
     *   - category: expense category ID or name (required when record_type=expense)
     *   - currency_id: CRM currency ID (default 1)
     *   - note: extra note appended to the record
     */
    public function scan_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $apiKey = (string) get_option('gemini_api_key');
        if ($apiKey === '') {
            $this->response([
                'status'  => false,
                'message' => 'Gemini API key is not configured. Go to Admin > Settings > Integrations > AI to set it.',
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

        $recordType = strtolower(trim((string) ($this->post('record_type') ?: 'purchase_invoice')));

        if ($recordType === 'expense') {
            $saved = $this->saveAsExpense($extracted);
        } else {
            $saved = $this->saveAsPurchaseInvoice($extracted);
        }

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
            'result'    => $saved['record'],
            'extracted' => $extracted,
        ], self::HTTP_OK);
    }

    // -------------------------------------------------------------------------

    private function saveAsPurchaseInvoice(array $extracted): array
    {
        if (empty($extracted['items'])) {
            return ['error' => 'No line items found in the receipt. Cannot create a purchase invoice without items.'];
        }

        $grandTotal = $extracted['grand_total'] ?? null;
        if ($grandTotal === null || !is_numeric($grandTotal)) {
            return ['error' => 'Could not determine grand total from the receipt.'];
        }

        // Resolve vendor
        $vendorId     = 0;
        $vendorIdPost = $this->post('vendor_id');
        if ($vendorIdPost !== null && is_numeric($vendorIdPost)) {
            $vendorId = (int) $vendorIdPost;
        } elseif (!empty($extracted['vendor'])) {
            $vendorRow = $this->db
                ->select('userid')
                ->like('company', $extracted['vendor'], 'both')
                ->limit(1)
                ->get(db_prefix() . 'pur_vendor')
                ->row();
            if ($vendorRow) {
                $vendorId = (int) $vendorRow->userid;
            }
        }

        // Invoice number
        $prefix     = $this->_getPurchaseOption('pur_inv_prefix', '#INV');
        $nextNumber = (int) $this->_getPurchaseOption('next_inv_number', 1);
        $invoiceNum = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        // Ensure uniqueness
        while ($this->db->where('invoice_number', $invoiceNum)->get(db_prefix() . 'pur_invoices')->row()) {
            $nextNumber++;
            $invoiceNum = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        }

        $invoiceDate = $extracted['date'] ?? date('Y-m-d');
        if (!$invoiceDate) {
            $invoiceDate = date('Y-m-d');
        }

        $currencyId = 1;
        $currPost   = $this->post('currency_id');
        if ($currPost !== null && is_numeric($currPost)) {
            $currencyId = (int) $currPost;
        }

        // Totals
        $subtotal = 0;
        foreach ($extracted['items'] as $item) {
            $subtotal += (float) ($item['qty'] ?? 1) * (float) ($item['unit_price'] ?? 0);
        }
        $taxAmount = (float) ($extracted['tax'] ?? 0);

        $staffId     = isset($GLOBALS['current_user']) ? (int) $GLOBALS['current_user']->staffid : 0;
        $receiptNote = $extracted['receipt_number'] ? 'Receipt ref: ' . $extracted['receipt_number'] : '';
        $extraNote   = (string) ($this->post('note') ?: '');
        $adminNote   = trim(implode("\n", array_filter([$receiptNote, $extraNote])));

        $invoiceData = [
            'number'          => $nextNumber,
            'invoice_number'  => $invoiceNum,
            'invoice_date'    => $invoiceDate,
            'transaction_date'=> $invoiceDate,
            'subtotal'        => round($subtotal, 2),
            'tax'             => round($taxAmount, 2),
            'total'           => round((float) $grandTotal, 2),
            'vendor'          => $vendorId,
            'payment_status'  => 'unpaid',
            'add_from'        => $staffId,
            'add_from_type'   => 'admin',
            'date_add'        => date('Y-m-d'),
            'currency'        => $currencyId,
            'to_currency'     => $currencyId,
            'adminnote'       => $adminNote,
        ];

        $this->db->insert(db_prefix() . 'pur_invoices', $invoiceData);
        $invoiceId = $this->db->insert_id();

        if (!$invoiceId) {
            return ['error' => 'Failed to create purchase invoice record.'];
        }

        // Update next invoice number
        $this->db
            ->where('option_name', 'next_inv_number')
            ->update(db_prefix() . 'purchase_option', ['option_val' => $nextNumber + 1]);

        // Insert line items
        foreach ($extracted['items'] as $item) {
            $qty        = (float) ($item['qty'] ?? 1);
            $unitPrice  = (float) ($item['unit_price'] ?? 0);
            $lineTotal  = round($qty * $unitPrice, 2);

            $this->db->insert(db_prefix() . 'pur_invoice_details', [
                'pur_invoice'      => $invoiceId,
                'item_code'        => '',
                'item_name'        => (string) ($item['description'] ?? 'Item'),
                'description'      => '',
                'unit_price'       => $unitPrice,
                'quantity'         => $qty,
                'into_money'       => $lineTotal,
                'total'            => $lineTotal,
                'total_money'      => $lineTotal,
                'discount_percent' => 0,
                'discount_money'   => 0,
                'tax_value'        => 0,
            ]);
        }

        $invoice = $this->db
            ->where('id', $invoiceId)
            ->get(db_prefix() . 'pur_invoices')
            ->row_array();

        $details = $this->db
            ->where('pur_invoice', $invoiceId)
            ->get(db_prefix() . 'pur_invoice_details')
            ->result_array();

        $invoice['items'] = $details;

        return ['record' => $invoice];
    }

    private function saveAsExpense(array $extracted): array
    {
        $categorySource = $this->post('category');
        if ($categorySource === null || $categorySource === '') {
            return ['error' => 'Field "category" is required when record_type=expense.'];
        }

        $grandTotal = $extracted['grand_total'] ?? null;
        if ($grandTotal === null || !is_numeric($grandTotal)) {
            return ['error' => 'Could not determine grand total from the receipt.'];
        }

        $date = $extracted['date'] ?? date('Y-m-d');
        if (!$date) {
            $date = date('Y-m-d');
        }

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

        $vendor      = $extracted['vendor'] ?? null;
        $receiptNum  = $extracted['receipt_number'] ?? null;
        $extraNote   = (string) ($this->post('note') ?: '');

        $lineItemSummary = '';
        if (!empty($extracted['items'])) {
            $lines = [];
            foreach ($extracted['items'] as $item) {
                $lines[] = ($item['description'] ?? 'Item') . ' x' . ($item['qty'] ?? 1) . ' @ ' . ($item['unit_price'] ?? 0);
            }
            $lineItemSummary = implode("\n", $lines);
        }

        $note = trim(implode("\n", array_filter(['Scanned from receipt.', $lineItemSummary, $extraNote])));

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

        $this->load->model('accounting/accounting_model');
        if (method_exists($this->accounting_model, 'automatic_expense_conversion')) {
            $this->accounting_model->automatic_expense_conversion($expenseId);
        }

        return ['record' => $this->expenses_model->get($expenseId)];
    }

    // -------------------------------------------------------------------------

    private function resolveImageInput(): array
    {
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

            if ($file['size'] > 10 * 1024 * 1024) {
                return ['error' => 'File exceeds maximum allowed size of 10 MB.'];
            }

            return [
                'base64' => base64_encode(file_get_contents($file['tmp_name'])),
                'mime'   => $mime,
            ];
        }

        $raw     = $this->input->raw_input_stream;
        $payload = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && !empty($payload['image_base64'])) {
            return [
                'base64' => $payload['image_base64'],
                'mime'   => $payload['mime_type'] ?? 'image/jpeg',
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
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);

        $extracted = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse AI response as JSON.'];
        }

        return $extracted;
    }

    private function _getPurchaseOption(string $name, $default = '')
    {
        $row = $this->db
            ->select('option_val')
            ->where('option_name', $name)
            ->get(db_prefix() . 'purchase_option')
            ->row();

        return $row ? $row->option_val : $default;
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
