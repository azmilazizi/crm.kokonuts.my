# Purchase Order Draft API — Attachment Upload Handling

## Current behaviour
- `POST /purchase/api/v1/purchase_order_drafts` and `PUT /purchase/api/v1/purchase_order_drafts/{id}` still expect JSON payloads. Attachments arrive in the `attachments` array with Base64-encoded `local_blob` values rather than multipart uploads.
- When an attachment includes `local_blob`, the binary is written to `modules/purchase/uploads/pur_order_draft/{draft_id}/{file_name}` (with a unique name when necessary) and the row is stored in `pur_order_draft_attachments` without persisting the blob column. Alternatively, attachments can be uploaded via `POST /purchase/api/v1/purchase_order_drafts/{id}/attachments` using multipart/form-data (`file` field).
- Draft reads load attachment content from the filesystem when available and Base64-encode it in the API response.
- Updating a draft removes files for attachments that are marked for deletion, omitted during a replace, or listed in `pending_deletion_attachments`. `DELETE /purchase/api/v1/purchase_order_drafts/{id}/attachments` accepts an `ids` array to remove specific files and their database rows.
- Deleting a draft now removes related attachment, item, and payment rows and deletes the `modules/purchase/uploads/pur_order_draft/{draft_id}` directory.

## Known limitations
- Core draft create/update endpoints still rely on Base64 blobs; multipart uploads are only supported through the dedicated attachmen
ts endpoint.
