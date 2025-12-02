# Purchase Order Draft API — Attachment Upload Handling

## Current behaviour
- `POST /purchase/api/v1/purchase_order_drafts` and `PUT /purchase/api/v1/purchase_order_drafts/{id}` accept JSON payloads for draft metadata, items, and payments only. Attachments are **not** processed in these requests; send files through the attachment upload endpoint instead.
- Upload files via `POST /purchase/api/v1/purchase_order_drafts/{id}/attachments` (`file` field, multipart/form-data). Files are stored under `modules/purchase/uploads/pur_order_draft/{draft_id}/{file_name}` and recorded in `pur_order_draft_attachments`.
- Remove files with `DELETE /purchase/api/v1/purchase_order_drafts/{id}/attachments`. Provide an `ids` array to delete specific attachments or omit it to remove all attachments for the draft. Draft updates no longer trigger attachment deletions.
- Deleting a draft removes related attachment, item, and payment rows and deletes the `modules/purchase/uploads/pur_order_draft/{draft_id}` directory.

## Known limitations
- Attachment lifecycle now mirrors live purchase orders: uploads use multipart/form-data on the attachments endpoint, and create/update payloads ignore attachment blobs.
