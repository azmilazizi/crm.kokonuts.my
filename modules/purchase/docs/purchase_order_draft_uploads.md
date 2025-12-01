# Purchase Order Draft API — Attachment Upload Handling

## Current behaviour
- The `POST /purchase/api/v1/purchase_order_drafts` endpoint expects a JSON payload. Attachments are supplied as array entries under `attachments` with Base64-encoded `local_blob` values rather than real multipart file uploads.
- There is no multipart/form-data handler in `Api_purchase::purchase_order_drafts_post`, so raw file uploads are not processed. Attachment records are only persisted when the request includes metadata such as `file_name`, `size_bytes`, and optionally a decoded `local_blob` payload.

## Proposed upload workflow
1. **Endpoint contract**
   - Accept `multipart/form-data` with two parts:
     - `metadata`: JSON string containing the draft fields (`order_name`, `items`, `payments`, etc.) and an `attachments` array with metadata per file (e.g., `id`, `file_name`, `description`).
     - `files[]`: One or more file parts whose names correspond to `attachments[*].id`.
2. **Controller handling (`Api_purchase::purchase_order_drafts_post` and `purchase_order_drafts_put`)**
   - Parse `metadata` JSON as today, then iterate uploaded files from `$this->input->post()`/`$_FILES`.
   - For each attachment entry, match by `id` and append:
     - `size_bytes` from `$file['size']`.
     - `file_name` from original client name.
     - `uploaded_by` using authenticated staff ID.
     - `local_blob` set to `file_get_contents($file['tmp_name'])` so the existing `normalize_draft_attachments()` pipeline can persist the binary content.
3. **Storage service**
   - Add a helper (e.g., `modules/purchase/helpers/purchase_upload_helper.php`) that writes the binary to `modules/purchase/uploads/purchase_order_drafts/{draft_id}/{attachment_id}` and returns the stored path.
   - Update the attachments model to save the storage path and mime type alongside existing metadata.
4. **Update logic**
   - When handling `purchase_order_drafts_put`, treat new file parts the same way as create. Existing attachments without a matching uploaded file keep their current path and metadata.
   - Honour `pending_deletion_attachments` by deleting files from disk via the storage helper and marking rows as removed before saving updates.
5. **Delete endpoint**
   - Extend `purchase_order_drafts_delete` to call the storage helper and remove all attachment files for the draft before deleting the database records.

## Validation and responses
- Reject requests missing `metadata` or with JSON parse failures using `400 Bad Request` and a descriptive message.
- Limit accepted file size and MIME types via configuration constants; respond with `413 Payload Too Large` or `415 Unsupported Media Type` as appropriate.
- On success, respond with the updated draft detail including attachment entries and their download URLs.

## Sample multipart request
```http
POST /purchase/api/v1/purchase_order_drafts HTTP/1.1
Content-Type: multipart/form-data; boundary=---X

---X
Content-Disposition: form-data; name="metadata"
Content-Type: application/json

{
  "order_name": "PO-1001",
  "order_date": "2024-06-15",
  "is_paid": false,
  "attachments": [
    {"id": "att-1", "file_name": "invoice.pdf"},
    {"id": "att-2", "file_name": "quote.png"}
  ]
}
---X
Content-Disposition: form-data; name="files[]"; filename="invoice.pdf"
Content-Type: application/pdf

<binary>
---X
Content-Disposition: form-data; name="files[]"; filename="quote.png"
Content-Type: image/png

<binary>
---X--
```
