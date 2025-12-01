# Purchase Order Draft API — Attachment Upload Handling

## Current behaviour
- `POST /purchase/api/v1/purchase_order_drafts` and `PUT /purchase/api/v1/purchase_order_drafts/{id}` still expect JSON payloads. Attachments arrive in the `attachments` array with Base64-encoded `local_blob` values rather than multipart uploads.
- When an attachment includes `local_blob`, the binary is written to `modules/purchase/uploads/pur_order_draft/{draft_id}/{file_name}` (with a unique name when necessary) and the row is stored in `pur_order_draft_attachments` without persisting the blob column.
- Draft reads load attachment content from the filesystem when available and Base64-encode it in the API response.
- Updating a draft removes files for attachments that are marked for deletion, omitted during a replace, or listed in `pending_deletion_attachments`.
- Deleting a draft now removes related attachment, item, and payment rows and deletes the `modules/purchase/uploads/pur_order_draft/{draft_id}` directory.

## Known limitations
- There is still no multipart/form-data handler; callers must continue supplying Base64 blobs in JSON.
