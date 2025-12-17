# API Expenses Manual Verification

These checks validate the new expenses API controller and routing. They assume you have a valid staff API token generated through the existing authentication flow (e.g., via `/api/v1/timesheets/login`) and that at least one expense record already exists in the database. Replace `https://crm.local` with the base URL of your installation and `YOUR_TOKEN` with the active bearer token.

## 1. List expenses (`GET /api/expenses`)

1. Execute:
   ```bash
   curl -sS \
     -H "Authorization: Bearer YOUR_TOKEN" \
     https://crm.local/api/expenses | jq
   ```
2. Verify the response status is `200`, `status` is `true`, and `result` contains an array of expenses (possibly empty). Each expense entry should expose keys such as `id`, `date`, `amount`, `category`, and (when the record is linked to a vendor) `vendor_name` populated with the vendor's company name.

## 2. Retrieve a single expense (`GET /api/expenses/{id}`)

1. Identify a valid expense identifier (e.g., from the previous step).
2. Execute:
   ```bash
   curl -sS \
     -H "Authorization: Bearer YOUR_TOKEN" \
     https://crm.local/api/expenses/EXPENSE_ID | jq
   ```
3. Verify the response status is `200`, `status` is `true`, and `result` describes the requested expense. Confirm that multi-line notes are returned with newline characters instead of `<br>` tags and that `vendor_name` is present when the expense references a vendor.
4. Repeat the command with a non-numeric identifier (e.g., `abc`) and confirm the service responds with HTTP `400` and an error message about the invalid identifier.

## 3. Update an expense (`PUT /api/expenses/{id}`)

1. Choose an existing expense ID.
2. Run the update request with a JSON payload that modifies at least one field and supplies required attributes (`date`, `amount`, `category`, `expense_name`):
   ```bash
   curl -sS -X PUT \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
       "date": "2023-12-01",
       "amount": 175.50,
       "category": 2,
       "expense_name": "API verification expense",
       "note": "Updated through API"
     }' \
     https://crm.local/api/expenses/EXPENSE_ID | jq
   ```
3. Confirm the response status is `200`, `status` is `true`, and `result` reflects the modified fields.
4. Issue the same command with an empty JSON payload (`{}`) and verify the API responds with HTTP `400` and the `No updatable fields were provided.` message.

## 4. Delete an expense (`DELETE /api/expenses/{id}`)

1. Use an expense ID that is not linked to an invoice.
2. Execute:
   ```bash
   curl -sS -X DELETE \
     -H "Authorization: Bearer YOUR_TOKEN" \
     https://crm.local/api/expenses/EXPENSE_ID | jq
   ```
3. Check that the response status is `200`, `status` is `true`, and the message confirms successful deletion.
4. Attempt to delete the same expense again to ensure the API now returns HTTP `404` with `Expense not found or already deleted.`
5. (Optional) Try deleting an invoiced expense and confirm the API responds with HTTP `409` and the explanatory message about invoiced expenses.

These steps provide end-to-end coverage for each endpoint introduced by the new controller.
