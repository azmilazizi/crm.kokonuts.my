# Branch-specific Google reviews

Receipts already store their sale branch in `pos_receipts.warehouse_id`. That identity comes from the POS account on order creation and is preserved when a queued sale is synchronized. Cashback ledger entries also already store `warehouse_id`. This feature uses that existing branch identity; it does not accept a branch or review destination from the customer's URL.

## Configure branches

In CRM, open **POS → Settings → Receipt**, select the store, fill in **Google review link for this branch**, and save. Use the review link from that branch's Google Business Profile. Repeat for each branch. The field accepts HTTPS links hosted by `g.page`, `maps.app.goo.gl`, `search.google.com`, `maps.google.com`, `www.google.com`, or `google.com`. Blank disables the invitation for that branch. No default destination is substituted.

The existing POS edit permission protects changes. Older receipt-settings clients that omit the new field preserve the configured link. Saving an empty field explicitly clears it.

## Schema and release

- POS migration **149** adds nullable `google_review_url VARCHAR(2048)` to `pos_receipt_settings`, which is already unique per `warehouse_id`. The POS module version is now **1.4.9**.
- Loyalty migration **112** creates `loyalty_branch_reviews` with `(customer_id, warehouse_id)` as its primary key and `prompted_at` / nullable `opened_at` timestamps. The Loyalty module version is now **1.1.2**.
- Fresh-install schemas include both changes. Neither migration rewrites receipts or cashback balances.

Deploy the CRM changes (including `application/helpers/google_review_helper.php`), then run the normal module migrations through the CRM admin initialization. Configure each branch's link. Deploy a new `kokonuts_loyalty` web build so customers receive the updated claim screen. POS application changes are not needed for this feature.

The APIs tolerate the review settings column or tracking table being absent during a staged release by omitting invitations. Verify that both migrations have run before testing invitations. Existing cached loyalty builds still contain the previous hardcoded destination until updated.

## Claim behavior

Both GET and successful POST `/loyalty/api/claim/{token}` now return these additional fields:

```json
{
  "warehouse_id": 27,
  "branch_name": "Kokonuts Branch B",
  "store_address": "Branch address",
  "google_review_url": "https://g.page/r/BRANCH/review"
}
```

`requires_review` in the POST result is true only when the branch has a configured valid link, branch tracking is available, and this customer has not already opened that branch's review link. A global legacy `google_review_done` flag does not suppress invitations at other branches.

The loyalty claim page displays the branch name and address. After a successful cashback credit, it displays the success screen and offers an optional review invitation for that branch. Customers can skip it. Missing links skip the invitation entirely. Failed link launches keep the invitation open with retry/skip options.

New clients call POST `/loyalty/api/confirm_review` with `{ "phone": "...", "token": "<receipt claim token>" }` after Google opens successfully. The backend checks that the claimed receipt belongs to the customer, derives its branch from that receipt and marks only that branch's `opened_at`. Repeated calls are idempotent. This records a link opening, not a verified submitted Google review. The original phone-only endpoint behavior remains for older clients.

Offline-generated, immediately redeemable cashback QR codes are outside this change. The future signed claim payload should carry the same immutable warehouse identity and the backend should resolve its review link from these settings.

## Validation

- `php tools/tests/branch-reviews.php` exercises real model methods against an in-memory query-builder test double without connecting to CRM's database: branch isolation, receipt-derived destinations, blank/invalid links, per-branch prompts, token/phone checks and idempotent openings.
- In `kokonuts_loyalty`: `flutter test --no-pub test/branch_reviews_test.dart` covers API parsing, URL validation, token-bearing tracking requests, named branch invitations and a missing-link claim.
- Smoke-test against a staging database with two configured branches: claim a receipt from each, verify each Google destination, then leave a third branch unconfigured and confirm cashback succeeds without an invitation.
