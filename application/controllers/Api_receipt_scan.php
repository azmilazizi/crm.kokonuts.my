<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_receipt_scan extends API_Controller
{
    /** @var array|null */
    private $tokenPayload = null;

    const GEMINI_API_URL       = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent';
    const GEMINI_TOOL_MAX_ITER = 15;

    const EXTRACTION_PROMPT = 'You are a receipt/invoice data extraction assistant. Extract all information from this document and return ONLY valid JSON with this exact structure:
{
  "vendor": "string or null",
  "date": "YYYY-MM-DD or null",
  "due_date": "YYYY-MM-DD or null",
  "payment_terms": "string e.g. Net 7, Net 30, Due on receipt, or null",
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
Rules: All monetary values must be plain numbers (no currency symbols). If a field is not visible or unclear, use null. The items array must never be null — use [] if no line items are visible. For due_date: if an explicit due date is shown on the document use it directly; if only payment terms are shown (e.g. "Net 7", "7 days", "due within 30 days"), compute due_date as invoice date plus that number of days; if neither is shown, set due_date to null.';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('authorization_token');
        $this->load->model('expenses_model');
        $this->load->model('accounting/accounting_model');
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
     *   - record_type: "purchase_order" (default) | "expense" | "bill" — which record to create
     *   - vendor_id: (int) override auto-lookup with a specific vendor ID
     *   - category: expense category ID or name (required when record_type=expense)
     *   - bill_category_id: (int) bill category with pre-mapped accounts (for record_type=bill, simple mode)
     *   - debit_account_id: (int) explicit debit account (for record_type=bill, advanced mode)
     *   - credit_account_id: (int) explicit credit account (for record_type=bill, advanced mode)
     *   - due_date: bill due date YYYY-MM-DD (for record_type=bill, defaults to date+30d)
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

        $recordType = strtolower(trim((string) ($this->post('record_type') ?: 'purchase_order')));

        $extracted = $this->matchVendorAndItems($apiKey, $extracted, $recordType);

        $mode = strtolower(trim((string) ($this->post('mode') ?: 'extract')));

        if ($mode !== 'save') {
            $this->response([
                'status' => true,
                'result' => $extracted,
            ], self::HTTP_OK);
            return;
        }

        if ($recordType === 'expense') {
            $saved = $this->saveAsExpense($extracted, []);
        } elseif ($recordType === 'bill') {
            $saved = $this->saveAsBill($extracted, []);
        } else {
            $saved = $this->saveAsPurchaseOrder($extracted, []);
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

    /**
     * POST api/v1/ai/receipt/confirm
     *
     * Saves a previously extracted (and user-reviewed) receipt as a record.
     * No image or AI call — accepts the reviewed JSON directly.
     *
     * Body (JSON):
     *   - extracted: object          — the (possibly edited) extraction result from scan
     *   - record_type: "purchase_order" (default) | "expense" | "bill"
     *   - vendor_id: int             — override auto-lookup with a specific vendor ID
     *   - currency_id: int           — CRM currency ID (default 1)
     *   - note: string               — extra note
     *   - category: string|int       — expense category name or ID (required for expenses)
     *   - paymentmode: int           — payment mode ID (optional, for expenses)
     *   - bill_category_id: int      — bill category with pre-mapped accounts (for bills, simple mode)
     *   - debit_account_id: int      — explicit debit account (for bills, advanced mode)
     *   - credit_account_id: int     — explicit credit account (for bills, advanced mode)
     *   - due_date: string           — bill due date YYYY-MM-DD (for bills, defaults to date+30d)
     */
    public function confirm_post()
    {
        if (!$this->ensureAuthenticated()) {
            return;
        }

        $raw       = $this->input->raw_input_stream;
        $payload   = json_decode($raw, true);
        $extracted = null;

        if (json_last_error() === JSON_ERROR_NONE && isset($payload['extracted'])) {
            $extracted = $payload['extracted'];
        } elseif (json_last_error() === JSON_ERROR_NONE && isset($payload['vendor'])) {
            // Allow passing the extracted object directly at the root level
            $extracted = $payload;
        }

        if (empty($extracted)) {
            $this->response([
                'status'  => false,
                'message' => 'Field "extracted" is required — pass the reviewed extraction JSON from the scan step.',
            ], self::HTTP_BAD_REQUEST);
            return;
        }

        // Allow POST fields to override values inside extracted for quick edits
        foreach (['vendor', 'date', 'receipt_number', 'currency', 'grand_total', 'subtotal', 'tax'] as $field) {
            $override = $this->post($field);
            if ($override !== null && $override !== '') {
                $extracted[$field] = $override;
            }
        }

        $recordType = strtolower(trim((string) ($this->post('record_type') ?? ($payload['record_type'] ?? 'purchase_order'))));

        if ($recordType === 'expense') {
            $saved = $this->saveAsExpense($extracted, $payload);
        } elseif ($recordType === 'bill') {
            $saved = $this->saveAsBill($extracted, $payload);
        } else {
            $saved = $this->saveAsPurchaseOrder($extracted, $payload);
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
            'status' => true,
            'result' => $saved['record'],
        ], self::HTTP_OK);
    }

    // -------------------------------------------------------------------------

    private function saveAsPurchaseOrder(array $extracted, array $payload): array
    {
        $grandTotal = $extracted['grand_total'] ?? null;
        if ($grandTotal === null || !is_numeric($grandTotal)) {
            return ['error' => 'Could not determine grand total from the receipt.'];
        }

        // Resolve vendor — prefer explicit override, then AI-matched id, then name LIKE fallback
        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        if ($vendorId === 0) {
            $vendorId = (int) ($extracted['vendor_id'] ?? 0);
        }
        if ($vendorId === 0 && !empty($extracted['vendor'])) {
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

        $orderDate = $extracted['date'] ?: date('Y-m-d');
        $staffId   = isset($GLOBALS['current_user']) ? (int) $GLOBALS['current_user']->staffid : 0;

        $currencyId = (int) ($payload['currency_id'] ?? 1);
        if ($currencyId < 1) {
            $currencyId = 1;
        }

        // Compute totals from line items (unit_price is already post-discount per user preference)
        $subtotal = 0;
        foreach (($extracted['items'] ?? []) as $item) {
            $subtotal += (float) ($item['qty'] ?? 1) * (float) ($item['unit_price'] ?? 0);
        }
        $taxAmount = (float) ($extracted['tax'] ?? 0);

        // PO number
        $prefix     = $this->_getPurchaseOption('pur_order_prefix', 'PO');
        $nextNumber = (int) $this->_getPurchaseOption('next_po_number', 1);
        $poNumber   = $prefix . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        while ($this->db->where('pur_order_number', $poNumber)->get(db_prefix() . 'pur_orders')->row()) {
            $nextNumber++;
            $poNumber = $prefix . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        }

        $orderName = $extracted['vendor'] ?: ('Scanned Receipt ' . date('d/m/Y'));

        $orderData = [
            'pur_order_name'   => $orderName,
            'vendor'           => $vendorId,
            'pur_order_number' => $poNumber,
            'number'           => $nextNumber,
            'order_date'       => $orderDate,
            'delivery_date'    => null,
            'subtotal'         => round($subtotal, 2),
            'total_tax'        => round($taxAmount, 2),
            'total'            => round((float) $grandTotal, 2),
            'addedfrom'        => $staffId,
            'added_from'       => $staffId,
            'buyer'            => $staffId,
            'status'           => 1,
            'approve_status'   => 2,
            'order_status'     => 'new',
            'status_goods'     => 0,
            'delivery_status'  => 0,
            'estimate'         => 0,
            'date_owed'        => 0,
            'discount_percent' => 0,
            'discount_total'   => 0,
            'discount_type'    => 'after_tax',
            'project'          => 0,
            'pur_request'      => 0,
            'department'       => 0,
            'sale_invoice'     => 0,
            'expense_convert'  => 0,
            'shipping_fee'     => 0,
            'shipping_country' => 0,
            'currency'         => $currencyId,
            'currency_rate'    => 1,
            'from_currency'    => $currencyId,
            'to_currency'      => $currencyId,
            'datecreated'      => date('Y-m-d H:i:s'),
            'hash'             => app_generate_hash(),
        ];

        $this->db->insert(db_prefix() . 'pur_orders', $orderData);
        $orderId = $this->db->insert_id();

        if (!$orderId) {
            return ['error' => 'Failed to create purchase order record.'];
        }

        $this->db
            ->where('option_name', 'next_po_number')
            ->update(db_prefix() . 'purchase_option', ['option_val' => $nextNumber + 1]);

        foreach (($extracted['items'] ?? []) as $item) {
            $qty       = (float) ($item['qty'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $lineTotal = round($qty * $unitPrice, 4);

            $this->db->insert(db_prefix() . 'pur_order_detail', [
                'pur_order'      => $orderId,
                'item_code'      => (int) ($item['item_id'] ?? 0),
                'item_name'      => (string) ($item['description'] ?? 'Item'),
                'description'    => '',
                'unit_id'        => null,
                'unit_price'     => $unitPrice,
                'quantity'       => $qty,
                'into_money'     => $lineTotal,
                'total'          => $lineTotal,
                'total_money'    => $lineTotal,
                'discount_%'     => 0,
                'discount_money' => 0,
                'tax_value'      => 0,
                'tax'            => null,
                'tax_rate'       => null,
                'tax_name'       => null,
            ]);
        }

        $order = $this->db
            ->where('id', $orderId)
            ->get(db_prefix() . 'pur_orders')
            ->row_array();

        $order['items'] = $this->db
            ->where('pur_order', $orderId)
            ->get(db_prefix() . 'pur_order_detail')
            ->result_array();

        return ['record' => $order];
    }

    private function saveAsExpense(array $extracted, array $payload): array
    {
        $grandTotal = $extracted['grand_total'] ?? null;
        if ($grandTotal === null || !is_numeric($grandTotal)) {
            return ['error' => 'Could not determine grand total from the receipt.'];
        }

        // category is required — user must select it in the review screen
        $categorySource = $payload['category'] ?? $this->post('category');
        if (empty($categorySource)) {
            return ['error' => 'Field "category" is required for expenses.'];
        }

        if (is_numeric($categorySource)) {
            $categoryId = (int) $categorySource;
        } else {
            $cat = $this->db->where('name', $categorySource)->get(db_prefix() . 'expenses_categories')->row();
            if (!$cat) {
                return ['error' => 'Expense category "' . $categorySource . '" not found.'];
            }
            $categoryId = (int) $cat->id;
        }

        // Resolve vendor — prefer explicit override, then AI-matched id, then name LIKE fallback
        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        if ($vendorId === 0) {
            $vendorId = (int) ($extracted['vendor_id'] ?? 0);
        }
        if ($vendorId === 0 && !empty($extracted['vendor'])) {
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

        // payment mode — optional, user selects in review screen
        $paymentMode = $payload['paymentmode'] ?? $this->post('paymentmode') ?? null;

        $date = $extracted['date'] ?: date('Y-m-d');

        $note = (string) ($payload['note'] ?? $this->post('note') ?? '');
        if (empty($note) && !empty($extracted['items'])) {
            $lines = [];
            foreach ($extracted['items'] as $item) {
                $lines[] = ($item['description'] ?? 'Item') . ' x' . ($item['qty'] ?? 1) . ' @ ' . ($item['unit_price'] ?? 0);
            }
            $note = implode("\n", $lines);
        }

        $data = [
            'expense_name' => $extracted['vendor'] ?: 'Receipt',
            'note'         => $note,
            'date'         => $date,
            'amount'       => (float) $grandTotal,
            'vendor'       => $vendorId,
            'category'     => $categoryId,
        ];

        if (!empty($paymentMode)) {
            $data['paymentmode'] = $paymentMode;
        }

        $currencyId = $payload['currency_id'] ?? $this->post('currency_id') ?? null;
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

    private function saveAsBill(array $extracted, array $payload): array
    {
        $grandTotal = $extracted['grand_total'] ?? null;
        if ($grandTotal === null || !is_numeric($grandTotal)) {
            return ['error' => 'Could not determine grand total from the receipt.'];
        }

        // Prefer payload override, then AI-matched vendor_match object, then fallback LIKE search
        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        if ($vendorId === 0) {
            $vendorMatch = $extracted['vendor_match'] ?? null;
            if (is_array($vendorMatch) && !empty($vendorMatch['userid'])) {
                $vendorId = (int) $vendorMatch['userid'];
            }
        }
        if ($vendorId === 0 && !empty($extracted['vendor'])) {
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

        $date = $extracted['date'] ?: date('Y-m-d');

        // AI may have computed due_date from payment_terms; payload can override
        $dueDate = $payload['due_date']
            ?? $this->post('due_date')
            ?? ($extracted['due_date'] ?: null);
        if (!$dueDate) {
            $dueDate = date('Y-m-d', strtotime($date . ' +30 days'));
        }

        $note = (string) ($payload['note'] ?? $this->post('note') ?? '');

        $vendorName = '';
        $vendorMatch = $extracted['vendor_match'] ?? null;
        if (is_array($vendorMatch) && !empty($vendorMatch['company'])) {
            $vendorName = $vendorMatch['company'];
        } elseif (!empty($extracted['vendor'])) {
            $vendorName = $extracted['vendor'];
        }

        $data = [
            'vendor'       => $vendorId,
            'date'         => $date,
            'due_date'     => $dueDate,
            'amount'       => (float) $grandTotal,
            'expense_name' => '',
            'note'         => $note,
            'reference_no' => $extracted['receipt_number'] ?? '',
        ];

        // Prefer payload override, then AI-matched bill_category_match object
        $billCategoryId = (int) ($payload['bill_category_id'] ?? 0);
        if ($billCategoryId === 0) {
            $categoryMatch = $extracted['bill_category_match'] ?? null;
            if (is_array($categoryMatch) && !empty($categoryMatch['id'])) {
                $billCategoryId = (int) $categoryMatch['id'];
            }
        }

        if ($billCategoryId > 0) {
            $data['bill_category_id_simple'] = $billCategoryId;
            $data['advanced_entry']          = 0;
            $data['item_id']          = [];
            $data['item_description'] = [];
            $data['item_qty']         = [];
            $data['item_cost']        = [];
            $data['item_amount']      = [];
        } else {
            $debitAccountId  = (int) ($payload['debit_account_id'] ?? $extracted['debit_account_id'] ?? 0);
            $creditAccountId = (int) ($payload['credit_account_id'] ?? $extracted['credit_account_id'] ?? 0);

            if ($debitAccountId > 0 && $creditAccountId > 0) {
                $data['debit_account']  = [$debitAccountId];
                $data['debit_amount']   = [(float) $grandTotal];
                $data['credit_account'] = [$creditAccountId];
                $data['credit_amount']  = [(float) $grandTotal];
            }

            $data['advanced_entry']   = 1;
            $data['item_id']          = [];
            $data['item_description'] = [];
            $data['item_qty']         = [];
            $data['item_cost']        = [];
            $data['item_amount']      = [];
        }

        $billId = $this->accounting_model->add_bill($data);
        if (!$billId) {
            return ['error' => 'Failed to create bill record.'];
        }

        return ['record' => $this->accounting_model->get_bill($billId)];
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

    private function searchVendorCandidates(string $vendorName): array
    {
        if ($vendorName === '') {
            return [];
        }

        $select = 'userid, company, vat, phonenumber, city, state, address, billing_street, billing_city, billing_state, billing_zip, active, default_currency';

        // Try exact / full-name LIKE first
        $vendors = $this->db
            ->select($select)
            ->like('company', $vendorName, 'both')
            ->limit(10)
            ->get(db_prefix() . 'pur_vendor')
            ->result_array();

        if (!empty($vendors)) {
            return $vendors;
        }

        // Fallback: split into words, drop legal/common suffixes, search each keyword
        $stopWords = [
            'BHD', 'SDN', 'PTE', 'LTD', 'HOLDINGS', 'HOLDING', 'GROUP',
            'ENTERPRISE', 'ENTERPRISES', 'CORPORATION', 'CORP', 'INC',
            'CO', 'COMPANY', 'COMPANIES', 'MALAYSIA', 'MALAYSIAN',
            'AND', 'THE', 'OF', 'FOR', 'DE', 'LA',
        ];

        $words    = preg_split('/[\s,\.&\/]+/', strtoupper($vendorName), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_values(array_unique(array_filter($words, static function (string $w) use ($stopWords): bool {
            return strlen($w) >= 3 && !in_array($w, $stopWords, true);
        })));

        $found   = [];
        $seenIds = [];

        foreach ($keywords as $keyword) {
            $rows = $this->db
                ->select($select)
                ->like('company', $keyword, 'both')
                ->limit(5)
                ->get(db_prefix() . 'pur_vendor')
                ->result_array();

            foreach ($rows as $row) {
                if (!in_array((int) $row['userid'], $seenIds, true)) {
                    $seenIds[] = (int) $row['userid'];
                    $found[]   = $row;
                }
            }

            if (count($found) >= 10) {
                break;
            }
        }

        return $found;
    }

    private function matchBillData(string $apiKey, array $extracted): array
    {
        $vendorName = trim((string) ($extracted['vendor'] ?? ''));
        $vendors    = $this->searchVendorCandidates($vendorName);

        $categories = $this->db
            ->select('id, name, debit_account, credit_account, description, active')
            ->where('active', 1)
            ->get(db_prefix() . 'acc_bill_categories')
            ->result_array();

        $extracted['vendor_match']        = null;
        $extracted['bill_category_match'] = null;
        $extracted['debit_account_id']    = 0;
        $extracted['credit_account_id']   = 0;

        if (empty($vendors) && empty($categories)) {
            return $extracted;
        }

        $prompt = 'You are a CRM data-matching assistant. Given an extracted bill and the available CRM data below, '
            . 'pick the best matching vendor and bill category.'
            . "\n\nRules:"
            . "\n- vendor_match: choose the vendor whose company name most closely matches the bill vendor (consider abbreviations, partial names, common trading names). Return the full object or null if no reasonable match."
            . "\n- bill_category_match: choose the category that best fits the nature of this bill based on vendor name and item descriptions (e.g. electricity/utility bills → Utilities; stationery → Office Supplies). Return the full object or null if nothing fits."
            . "\n- due_date: if the current due_date is null but payment_terms contains a number of days (e.g. \"Net 7\", \"Due 7 days\"), compute due_date as date + that many days in YYYY-MM-DD. Otherwise keep the existing value."
            . "\n\nReturn ONLY a JSON object with exactly these keys: vendor_match, bill_category_match, due_date. No other text."
            . "\n\nExtracted bill:\n" . json_encode([
                'vendor'        => $extracted['vendor'],
                'date'          => $extracted['date'],
                'due_date'      => $extracted['due_date'],
                'payment_terms' => $extracted['payment_terms'],
                'items'         => $extracted['items'],
                'grand_total'   => $extracted['grand_total'],
            ])
            . "\n\nAvailable vendors (" . count($vendors) . "):\n" . json_encode($vendors)
            . "\n\nAvailable bill categories (" . count($categories) . "):\n" . json_encode($categories);

        $payload = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
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
        curl_close($ch);

        if ($httpCode !== 200 || !$raw) {
            return $extracted;
        }

        $response = json_decode($raw, true);
        $text     = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            return $extracted;
        }

        $text  = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text  = preg_replace('/\s*```$/i', '', $text);
        $match = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($match)) {
            return $extracted;
        }

        if (isset($match['vendor_match'])) {
            $extracted['vendor_match'] = $match['vendor_match'];
        }
        if (isset($match['bill_category_match'])) {
            $extracted['bill_category_match'] = $match['bill_category_match'];
        }
        if (!empty($match['due_date'])) {
            $extracted['due_date'] = $match['due_date'];
        }

        return $extracted;
    }

    private function matchVendorAndItems(string $apiKey, array $extracted, string $recordType = 'purchase_order'): array
    {
        if ($recordType === 'bill') {
            return $this->matchBillData($apiKey, $extracted);
        }

        $functionDeclarations = [
            [
                'name'        => 'search_vendors',
                'description' => 'Search for vendors in the CRM by company name. Returns up to 5 matches with id and name.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Vendor company name to search for'],
                    ],
                    'required'   => ['name'],
                ],
            ],
            [
                'name'        => 'search_items',
                'description' => 'Search for items/products in the CRM inventory by description or name. Returns up to 5 matches with id, name, and code.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'description' => ['type' => 'string', 'description' => 'Item description or name to search for'],
                    ],
                    'required'   => ['description'],
                ],
            ],
            [
                'name'        => 'search_payment_modes',
                'description' => 'Search for payment modes in the CRM by name (e.g. Cash, Card, Bank Transfer). Returns up to 5 matches with id and name.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Payment mode name to search for'],
                    ],
                    'required'   => ['name'],
                ],
            ],
            [
                'name'        => 'search_expense_categories',
                'description' => 'Retrieve all expense categories from the CRM. Use this to pick the most logically suitable category for the receipt items.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                    'required'   => [],
                ],
            ],
        ];

        $tools = [['function_declarations' => $functionDeclarations]];

        $prompt = 'You are a CRM data-matching assistant. Use the search tools to find the best matching vendor, items, '
                . 'payment mode, and expense category from the CRM for this extracted receipt. '
                . 'Steps: (1) search_vendors for the vendor name, (2) search_items for each line item, '
                . '(3) search_payment_modes for the payment method, (4) search_expense_categories then pick the single '
                . 'most logically suitable category based on what the items are (e.g. office supplies → Office Expenses, '
                . 'groceries/food → Meals or similar). '
                . 'After all searches, return the same JSON structure with these additions: '
                . '"vendor_id" (int, 0 if no match), "payment_mode_id" (int, 0 if no match), '
                . '"expense_category_id" (int, 0 if no suitable category found) at the root, '
                . 'and "item_id" (int, 0 if no match) inside each item object. Return ONLY valid JSON, no extra text.'
                . "\n\nExtracted receipt:\n" . json_encode($extracted, JSON_PRETTY_PRINT);

        $contents = [
            ['role' => 'user', 'parts' => [['text' => $prompt]]],
        ];

        for ($i = 0; $i < self::GEMINI_TOOL_MAX_ITER; $i++) {
            $payload = [
                'contents'         => $contents,
                'tools'            => $tools,
                'generationConfig' => ['temperature' => 0],
            ];

            $ch = curl_init(self::GEMINI_API_URL . '?key=' . urlencode($apiKey));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $raw      = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$raw) {
                return $extracted;
            }

            $response = json_decode($raw, true);
            $parts    = $response['candidates'][0]['content']['parts'] ?? [];

            if (empty($parts)) {
                return $extracted;
            }

            $functionCalls = [];
            $textPart      = null;
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCalls[] = $part['functionCall'];
                } elseif (isset($part['text'])) {
                    $textPart = $part['text'];
                }
            }

            if (empty($functionCalls)) {
                if ($textPart === null) {
                    return $extracted;
                }
                $clean   = preg_replace('/^```(?:json)?\s*/i', '', trim($textPart));
                $clean   = preg_replace('/\s*```$/i', '', $clean);
                $matched = json_decode($clean, true);
                return (json_last_error() === JSON_ERROR_NONE) ? $matched : $extracted;
            }

            // Append model turn and execute tool calls
            $contents[] = ['role' => 'model', 'parts' => $parts];

            $toolResponses = [];
            foreach ($functionCalls as $call) {
                $result          = $this->executeMatchToolCall($call['name'], $call['args'] ?? [], $recordType);
                $toolResponses[] = [
                    'functionResponse' => [
                        'name'     => $call['name'],
                        'response' => ['result' => $result],
                    ],
                ];
            }

            $contents[] = ['role' => 'user', 'parts' => $toolResponses];
        }

        return $extracted;
    }

    private function executeMatchToolCall(string $name, array $args, string $recordType = 'purchase_order'): array
    {
        if ($name === 'search_vendors') {
            $query = trim((string) ($args['name'] ?? ''));
            if ($query === '') {
                return [];
            }
            if ($recordType === 'bill') {
                return $this->db
                    ->select('userid, company, vat, phonenumber, city, state, address, billing_street, billing_city, billing_state, billing_zip, active, default_currency')
                    ->like('company', $query, 'both')
                    ->limit(10)
                    ->get(db_prefix() . 'pur_vendor')
                    ->result_array();
            }
            return $this->db
                ->select('userid as id, company as name')
                ->like('company', $query, 'both')
                ->limit(5)
                ->get(db_prefix() . 'pur_vendor')
                ->result_array();
        }

        if ($name === 'search_items') {
            $query = trim((string) ($args['description'] ?? ''));
            if ($query === '') {
                return [];
            }
            return $this->db
                ->select('id, description as name, commodity_code as code')
                ->like('description', $query, 'both')
                ->limit(5)
                ->get(db_prefix() . 'items')
                ->result_array();
        }

        if ($name === 'search_payment_modes') {
            $query = trim((string) ($args['name'] ?? ''));
            if ($query === '') {
                return [];
            }
            return $this->db
                ->select('id, name')
                ->like('name', $query, 'both')
                ->limit(5)
                ->get(db_prefix() . 'payment_modes')
                ->result_array();
        }

        if ($name === 'search_expense_categories') {
            return $this->db
                ->select('id, name')
                ->get(db_prefix() . 'expenses_categories')
                ->result_array();
        }

        return [];
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
